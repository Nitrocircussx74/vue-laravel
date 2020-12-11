<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use Redirect;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\DirectNotifyAccoutStatusController;
# Model
use DB;
use App\PropertyMember;
use App\PropertyUnit;
use App\User;
use App\Province;
use App\Notification;
use App\OfficerRoleAccess;
use App\PropertyFeature;
use App\Installation;
# Test Pubnub
use Pubnub\Pubnub;

class PropertyMemberController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu','members');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function memberlist () {
		$members 		= PropertyMember::with('unit')
							->where('property_id',Auth::user()->property_id)
							->whereNull('verification_code')
							->where('id','!=',Auth::user()->id)
							->where('role',2)
							->orderBy('created_at','DESC')
							->paginate(30);
		$unit_list = array(''=> trans('messages.unit_no') );
		$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
		return view('property_members.view')->with(compact('members','unit_list'));
	}

	public function memberlistPage () {
		if(Request::ajax()) {
			$members 	= PropertyMember::with('unit')
							->where('property_id',Auth::user()->property_id)
							->where('verification_code',NULL)
							->where('role',2)
							->where('id','!=',Auth::user()->id);

			if(Request::get('type') == 'cm') {
				$members->where('is_chief',true);
			} else if(Request::get('type') == 'gm') {
				$members->where('is_chief',false);
			}

			if(Request::get('active') != '-') {
				$members->where('active',intval(Request::get('active')));
			}

			if(Request::get('unit_id')) {
				$members->where('property_unit_id',Request::get('unit_id'));
			}

			if(Request::get('name')) {
				$members->where('name','like',"%".Request::get('name')."%");
			}

			$members = $members->orderBy('created_at','DESC')->paginate(30);
			return view('property_members.member-list')->with(compact('members'));
		}
	}

	public function setChief () {
		if(Request::ajax()) {
			$user = PropertyMember::find(Request::get('uid'));
			if($user) {
				$user->is_chief = Request::get('status');
				$user->save();

                // Test Add Pubnub API
                /*$pubnub = new Pubnub(array(
                    'subscribe_key' => 'sub-c-d99bfa84-bb54-11e5-a9aa-02ee2ddab7fe',
                    'publish_key' => 'pub-c-0007ebbe-803b-45d4-a481-ea1963e29935',
                    'uuid' => 'sec-c-MmYyNTc4OTAtNWM5YS00Zjg5LThkNmEtNzM2Njc4OTdiODc3',
                    'ssl' => false
                ));

                $user_id = $user->id;

                if(Request::get('status') == "1") {
                    $info = $pubnub->publish($user_id, "true");
                }else{
                    $info = $pubnub->publish($user_id, "false");
                }*/

                if(Request::get('status') == "1") {
                    $status = true;
                }else{
                    $status = false;
                }

                // Test add new Committee
                $controller_push_noti = new PushNotificationController();
                $controller_push_noti->pushNotificationSetCommitter($user->id,$status);

				return response()->json(['result'=>true]);
			}
		}
	}

	public function setActive () {
		if(Request::ajax()) {
			$user = PropertyMember::find(Request::get('uid'));
			if($user) {
				$user->active = Request::get('status');
				$user->notification = Request::get('status');
                $user->save();
                if( !Request::get('status') ) {
                    $controller_push_noti = new DirectNotifyAccoutStatusController();
                    $controller_push_noti->sentNotifyAccountStatus($user->id);
                    // delete installation
                    $ins = Installation::where('user_id', $user->id)->count(); 
                    if( $ins ) {
                        Installation::where('user_id', $user->id)->delete(); 
                    }
                }
				return response()->json(['result'=>true]);
			}
		}
	}

	public function getMember () {
		if(Request::ajax()) {
			$member = User::find(Request::get('uid'));
			return view('property_members.view-member')->with(compact('member'));
		}
	}

	public function newMembers() {
		$p = new Province;
		$provinces = $p->getProvince();

		if(Request::ajax()) {
			$users = User::whereNotNull('verification_code')->where('verification_stage',0)->where('role',2);
			if(Request::get('name')) {
				$users->where('name','like',"%".Request::get('name')."%");
			}

			if(Request::get('property_unit_id')) {
				$users->where('property_unit_id', Request::get('property_unit_id'));
			}

			$users = $users->where('property_id',Auth::user()->property_id)->orderBy('created_at','desc')->paginate(15);
			return view('property_members.new-member-list-page')->with(compact('users','provinces'));
		} else {
			$users = User::where('verification_code','!=',"")->where('verification_stage',0)->where('property_id',Auth::user()->property_id)->orderBy('created_at','desc')->paginate(15);
			$property_unit_list = array(''=> trans('messages.unit_no') );
			$property_unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
			return view('property_members.new-member-list')->with(compact('users','property_unit_list','provinces'));
		}
	}

    public function officerList() {
		$officer = [];
        view()->share('active_menu','officer');
        $officers = User::with('position')->where('property_id',Auth::user()->property_id)
            ->where('id','!=',Auth::user()->id)
            ->where('role','=',3)
            ->where('deleted','=',false)
            ->orderBy('created_at','DESC')
            ->paginate(30);
			if(Request::ajax()) {
				return view('property_members.officer-list')->with(compact('officers','officer'));
			}
        	else return view('property_members.view-officer-list')->with(compact('officers','officer'));
    }

    public function addOfficer() {
        if (Request::isMethod('post')) {
            $officer = Request::all();

            $new_officer = new User();
            unset($new_officer->rules['fname']);
            unset($new_officer->rules['lname']);
			$new_officer->rules['name'] = 'required';
            $vu = $new_officer->validate($officer);

            if($vu->fails()){
                //$v = array_merge_recursive($vu->messages()->toArray());
                //return redirect()->back()->withInput()->withErrors($vu)->with(compact('officer'));
				return view('property_members.officer-form')->withErrors($vu)->with(compact('officer'));
            }else {
                $user = User::create([
                    'name' => $officer['name'],
                    'email' => $officer['email'],
                    'phone' => $officer['phone'],
                    'password' => bcrypt($officer['password']),
                    'property_id' => Auth::user()->property_id,
                    'role' => 3
                ]);

                OfficerRoleAccess::create([
                    'property_id' => Auth::user()->property_id,
                    'user_id' => $user->id
                ]);

				echo "saved";
            }
        }
    }

	public function getOfficer () {
		if(Request::ajax()) {
			$officer = User::find(Request::get('uid'));
			$position = OfficerRoleAccess::where('property_id','=',Auth::user()->property_id)->where('user_id','=',Request::get('uid'))->first();

			return view('property_members.officer-form-edit')->with(compact('officer','position'));
		}
	}

	public function editOfficer() {
        if (Request::isMethod('post')) {

			$officer = User::find(Request::get('id'));
			$request = Request::except('email');
            unset($officer->rules['fname']);
            unset($officer->rules['lname']);
			unset($officer->rules['email']);
			if(empty($request['password'])) {
				unset($officer->rules['password']);
				unset($officer->rules['password_confirm']);
			}
			$officer->rules['name'] = 'required';
            $vu = $officer->validate($request);
            if($vu->fails()){
				$officer->fill(Request::except(['email','id']));
				return view('property_members.officer-form-edit')->withErrors($vu)->with(compact('officer'));
            }else {
                $officer->name = $request['name'];
				if(!empty($request['password'])) {
					$officer->password = bcrypt($request['password']);
				}
				$officer->save();

                $position = Request::get('position');
                $counterCheckOfficer = OfficerRoleAccess::where('property_id','=',Auth::user()->property_id)->where('user_id','=',Request::get('id'));
                if($counterCheckOfficer->count() > 0) {
                    $access = $counterCheckOfficer->first();
                    $officer = OfficerRoleAccess::find($access->id);
                    $officer->position = $position;
                    $officer->save();
                }else{
                    $officer = new OfficerRoleAccess;
                    $officer->user_id = Request::get('user_id');
                    $officer->position = $position;
                    $officer->property_id = Auth::user()->property_id;
                    $officer->save();
                }

				echo "saved";
            }
        }
    }

	public function deleteMembers() {
		if (Request::isMethod('post')) {
			$user = User::find(Request::get('uid'));
			if(isset($user)) {
				$notis = Notification::where('to_user_id',$user->id)->get();
				if($notis->count()) {
					foreach ($notis as $noti) {
						$noti->delete();
					}
				}
				$user->delete();
			}
			return response()->json(['result'=>true]);
		}
	}

    public function getRoleAccess () {
        if(Request::ajax()) {
            $role_access = OfficerRoleAccess::where('property_id','=',Auth::user()->property_id)->where('user_id','=',Request::get('uid'))->first();
            $user = User::find(Request::get('uid'));
            return view('property_members.officer-role-form-edit')->with(compact('role_access','user'));
        }
    }

	public function editRoleAccess(){
        try{
            if (Request::isMethod('post')) {
                if(Request::get('id') != null) {
                    $access = OfficerRoleAccess::find(Request::get('id'));
                }else{
                    $access = new OfficerRoleAccess;
                }

                $counter = 0;

                $access->user_id = Request::get('user_id');
                $access->property_id = Auth::user()->property_id;
                $access->menu_committee_room = Request::get('menu_committee_room') ? true : false;
                $access->menu_event = Request::get('menu_event') ? true : false;
                $access->menu_vote = Request::get('menu_vote') ? true : false;
                $access->menu_tenant = Request::get('menu_tenant') ? true : false;
                $access->menu_vehicle = Request::get('menu_vehicle') ? true : false;
                $access->menu_utility = Request::get('menu_utility') ? true : false;
                $access->menu_complain = Request::get('menu_complain') ? true : false;
                $access->menu_parcel = Request::get('menu_parcel') ? true : false;
                $access->menu_message = Request::get('menu_message') ? true : false;
                $access->menu_property_setting = Request::get('menu_property_setting') ? true : false;
                $access->menu_property_member = Request::get('menu_property_member') ? true : false;


                if(Request::get('menu_prepaid')){
                    $access->menu_prepaid = true;
                    $counter++;
                }else{
                    $access->menu_prepaid = false;
                }

                if(Request::get('menu_revenue_record')){
                    $access->menu_revenue_record = true;
                    $counter++;
                }else{
                    $access->menu_revenue_record = false;
                }

                if(Request::get('menu_retroactive_receipt')){
                    $access->menu_retroactive_receipt = true;
                    $counter++;
                }else{
                    $access->menu_retroactive_receipt = false;
                }

                if(Request::get('menu_common_fee')){
                    $access->menu_common_fee = true;
                    $counter++;
                }else{
                    $access->menu_common_fee = false;
                }

                if(Request::get('menu_cash_on_hand')){
                    $access->menu_cash_on_hand = true;
                    $counter++;
                }else{
                    $access->menu_cash_on_hand = false;
                }

                if(Request::get('menu_pettycash')){
                    $access->menu_pettycash = true;
                    $counter++;
                }else{
                    $access->menu_pettycash = false;
                }

                if(Request::get('menu_fund')){
                    $access->menu_fund = true;
                    $counter++;
                }else{
                    $access->menu_fund = false;
                }

                if(Request::get('menu_statement_of_cash')){

                    $counter++;
                }else{
                    $access->menu_statement_of_cash = false;
                }

                if($counter > 0 ){
                    $access->menu_finance_group = true;
                    $access->menu_statement_of_cash = true;
                    $access->cancel_invoice         = Request::get('cancel_invoice') ? true : false;
                    $access->cancel_receipt_expense = Request::get('cancel_receipt_expense') ? true : false;

                }else{

                    $access->menu_finance_group = false;
                    $access->menu_statement_of_cash = false;
                    $access->cancel_invoice         = false;
                    $access->cancel_receipt_expense = false;
                }

                $access->save();
                echo "saved";

            }else {
                echo "error";
            }
        }catch(Exception $ex){
            echo "error";
        }
    }

    public function dumpDataOfficerToDB(){
        $user = User::where('role','=',3)->get();

        foreach ($user as $item) {
            $access = new OfficerRoleAccess;
            $access->user_id = $item->id;
            $access->property_id = $item->property_id;
            $access->menu_committee_room = true;
            $access->menu_event = true;
            $access->menu_vote = true;
            $access->menu_tenant = true;
            $access->menu_vehicle = true;
            $access->menu_utility = true;
            $access->menu_complain = true;
            $access->menu_parcel = true;
            $access->menu_message = true;
            $access->menu_property_setting = true;
            $access->menu_property_member = true;
            $access->menu_prepaid = true;
            $access->menu_revenue_record = true;
            $access->menu_retroactive_receipt = true;
            $access->menu_common_fee = true;
            $access->menu_cash_on_hand = true;
            $access->menu_pettycash = true;
            $access->menu_fund = true;
            $access->menu_finance_group = true;

            $access->save();
        }

        return "true";
    }
}
