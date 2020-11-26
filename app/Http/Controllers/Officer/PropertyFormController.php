<?php namespace App\Http\Controllers\Officer;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use Redirect;
use Mail;
# Model
use App\PropertyForm;
use App\Province;
use App\User;
use App\Property;
use App\PropertyUnit;
use App\PropertyFeature;
class PropertyFormController extends Controller {

	public function __construct () {
		$this->middleware('auth',['except' => ['login']]);
		if( Auth::check() && Auth::user()->role !== 4 ) {
			Redirect::to('feed')->send();
		}
	}

	public function index () {
		$p = new Province;
		$provinces = $p->getProvince();
		$_form = new PropertyForm;
		if(Request::ajax()) {
			if(Request::get('name')) {
				$_form = $_form->where('name','like',"%".Request::get('name')."%");
			}

			if(Request::get('province')) {
				$_form = $_form->where('province',Request::get('province'));
			}

			if(Request::get('status') != '-') {
				$_form = $_form->where('status',Request::get('status'));
			}
		}
		$_form = $_form->where('user_id','=',Auth::user()->id)->orderBy('created_at','desc')->paginate(30);
		//$_form = $_form->orderBy('created_at','desc')->paginate(30);
		if(Request::ajax()) {
			return view('property_form.form-list-page')->with(compact('_form','provinces'));
		} else {
			return view('property_form.form-list')->with(compact('_form','provinces'));
		}
	}

	public function add () {
		if( Request::isMethod('post') ) {
			$p = new PropertyForm;
			$p->fill(Request::all());

			//$count = -1;
			$code 	= $this->generateCode();
			$count 	= PropertyForm::where('form_code', $code)->count();
			while($count > 0) {
				$code 	= $this->generateCode();
				$count 	= PropertyForm::where('form_code', $code)->count();
			}
			$p->status 		= 0;
			$p->form_code 	= $code;
			$p->user_id = Auth::user()->id;
			$p->save();
			$this->mail_form_created(Request::get('name'), Request::get('email'), Request::get('property_name'),$code);
		}
		return redirect('officer/property-form');
	}

	public function view_form ($id) {
		$b_form = $_form = PropertyForm::find($id);
		if($_form->detail){
			$_form = $_form->toArray();
			$_form = json_decode($_form['detail'],true);
			$_form['id'] = $id;
			$_form['user']['name'] = $b_form->name;
			$_form['user']['email'] = $b_form->email;
		}
		$p = new Province;
		$provinces = $p->getProvince();
		return view('property_form.view-form')->with(compact('_form','provinces'));
	}

	public function save_form () {
		$property = Request::except('id','_token');
		$new_prop = new Property;
		$vp = $new_prop->validate($property);
		$new_user = new User();
		unset($new_user->rules['fname']);
		unset($new_user->rules['lname']);
		unset($new_user->rules['password']);
		unset($new_user->rules['password_confirm']);
		$vu = $new_user->validate($property['user']);
		if($vp->fails() or $vu->fails()) {
			$v = array_merge_recursive($vp->messages()->toArray(), $vu->messages()->toArray());
			return redirect()->back()->withInput()->withErrors($v);
		} else {
			$password = $this->generatePassword();
			$new_prop->fill($property);
			$new_prop->min_price = str_replace(',', '', $new_prop->min_price);
			$new_prop->max_price = str_replace(',', '', $new_prop->max_price);

			if($new_prop->min_price == "") $new_prop->min_price = 0;
			else $new_prop->min_price = str_replace(',', '', $new_prop->min_price);
			if($new_prop->max_price == "") $new_prop->max_price = 0;
			else $new_prop->max_price = str_replace(',', '', $new_prop->max_price);

			$new_prop->save();

			$new_feature = new PropertyFeature;
			$new_feature->property_id = $new_prop->id;
			$new_feature->menu_committee_room = true;
			$new_feature->menu_event = true;
			$new_feature->menu_vote = true;
			$new_feature->menu_tenant = true;
			$new_feature->menu_vehicle = true;
			$new_feature->menu_prepaid = true;
			$new_feature->menu_revenue_record = true;
			$new_feature->menu_retroactive_receipt = true;
			$new_feature->menu_common_fee = true;
			$new_feature->menu_cash_on_hand = true;
			$new_feature->menu_pettycash = true;
			$new_feature->menu_fund = true;
			$new_feature->menu_complain = true;
			$new_feature->menu_parcel = true;
			$new_feature->menu_message = true;
			$new_feature->menu_utility = true;
			$new_feature->save();

			$password = $this->generatePassword();
			User::create([
				'name' => $property['user']['name'],
				'email' => $property['user']['email'],
				'password' => bcrypt($password),
				'property_id' => $new_prop->id,
				'role' => 1
			]);
			//Save Property unit
			if( !empty($property['unit']) ) {
				foreach ($property['unit'] as $unit) {
					//Get Area
					$units[] = new PropertyUnit([
						'unit_number' 	=> $unit['no'],
						'property_size' => empty($unit['area'])?0:$unit['area'],
						'is_land' 		=> $unit['is_land'],
						'owner_name_th' => $unit['owner_name_th'],
						'owner_name_en' => $unit['owner_name_en'],
						'invite_code'	=> $this->generateInviteCode()
					]);
				}
				$new_prop->property_unit()->saveMany($units);
			}
			// Send mail
			$this->mail_account_created($property['user']['name'],$property['property_name_th'],$property['user']['email'],$password);
			// Delete form
			$form = PropertyForm::find(Request::get('id'));
			$form->delete();
			return redirect('officer/property-form');
		}
	}

	function delete_form () {
		$form = PropertyForm::find(Request::get('form_id'));
		if($form) {
			$form->delete();
		}
		return redirect('officer/property-form');
	}

	function mail_account_created ($name,$property_name,$email,$password) {
		Mail::send('emails.property_account_created', [
			'name'			=> $name,
			'property_name' => $property_name,
			'username'		=> $email,
			'password'		=> $password

		], function ($message) use($email) {
			$message->subject('บัญชีสำหรับนิติบุคคลได้ถูกสร้าง');
			$message->from('noreply@nabour.me', 'Nabour');
			$message->to($email);
		});
	}

	function mail_form_created ($name,$email,$property_name,$code) {
		Mail::send('emails.property_form_created', [
			'name'			=> $name,
			'property_name' => $property_name,
			'code'		=> $code

		], function ($message) use($email) {
			$message->subject('รหัสแบบฟอร์มสำหรับข้อมูลนิติบุคคล');
			$message->from('noreply@nabour.me', 'Nabour');
			$message->to($email);
		});
	}

	function generateCode() {
		$chars = "abcdefghijkmnpqrstuvwxyz123456789";
		$i = 0;
		$pass = '' ;
		while ($i < 5) {
			$num = rand() % 33;
			$tmp = substr($chars, $num, 1);
			$pass = $pass . $tmp;
			$i++;
		}
		return $pass;
	}

	function generatePassword() {
		$chars = "abcdefghijkmnpqrstuvwxyz123456789";
		$i = 0;
		$pass = '' ;
		while ($i < 6) {
			$num = rand() % 33;
			$tmp = substr($chars, $num, 1);
			$pass = $pass . $tmp;
			$i++;
		}
		return $pass;
	}

	function generateInviteCode() {
		$code = $this->randomInviteCodeCharacter();
		$count = PropertyUnit::where('invite_code', '=', $code)->count();
		while($count > 0) {
			$code = $this->randomInviteCodeCharacter();
			$count = PropertyUnit::where('verification_code', '=', $code)->count();
		}
		return $code;
	}

	function randomInviteCodeCharacter(){
		$chars = "abcdefghijkmnpqrstuvwxyz123456789";
		srand((double)microtime()*1000000);
		$i = 0;
		$pass = '' ;
		while ($i < 5) {
			$num = rand() % 33;
			$tmp = substr($chars, $num, 1);
			$pass = $pass . $tmp;
			$i++;
		}
		return $pass;
	}
}
