<?php namespace App\Http\Controllers\PropertyAdmin;
use App\Property;
use League\Flysystem\Exception;
use Maatwebsite\Excel\Exceptions\LaravelExcelException;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use Redirect;
use Storage;
use DB;
use File;
use Excel;
use View;
# Model
use App\PropertyUnit;
use App\User;
use App\Tenant;
use App\Keycard;
class PropertyUnitController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu','units');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function unitList () {
		//$units 		= PropertyUnit::with('home_tenant')->where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->paginate(30);
		$units 		= PropertyUnit::with('home_tenant','home_keycard')->where('property_id',Auth::user()->property_id)->where('active',true);

		$unit_list = array(''=> trans('messages.unit_no') );
		$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();

		if(Request::ajax()) {

			if(Request::get('unit_id')) {
				$units->where('id',Request::get('unit_id'));
			}

			$units = $units->orderBy(DB::raw('natsortInt(unit_number)'))->paginate(30);
			return view('property_units.unit-list-page')->with(compact('units','unit_list'));
		}
		else{
			$units = $units->orderBy(DB::raw('natsortInt(unit_number)'))->paginate(30);
			return view('property_units.unit-list')->with(compact('units','unit_list'));
		}
	}

	public function getUnit () {
		$unit = PropertyUnit::with('home_pet','home_vehicle')->find(Request::get('unit_id'));
		return view('property_units.get-unit')->with(compact('unit'));
	}

	public function add () {
		$unit = new PropertyUnit;
		$unit->fill(Request::all());
		if(Request::get('is_land')) {
			$unit->is_land = true;
		} else $unit->is_land = false;

		if(Request::get('transferred_date')){
			$unit->transferred_date = Request::get('transferred_date');
		}else{
			$unit->transferred_date = null;
		}
		if(Request::get('insurance_expire_date')){
			$unit->insurance_expire_date = Request::get('insurance_expire_date');
		}else{
			$unit->insurance_expire_date = null;
		}
		
		$unit->property_id = Auth::user()->property_id;
		$unit->property_size = str_replace(',', "", Request::get('property_size'));
		$unit->extra_cf_charge = str_replace(',', "", Request::get('extra_cf_charge'));
		$unit->property_size = ($unit->property_size>0)?$unit->property_size:0;
		$unit->invite_code = $this->generateInviteCode();
		$unit->save();
		return redirect('admin/property/units');
	}

	public function editForm () {
		$unit = PropertyUnit::find(Request::get('unit_id'));
		return view('property_units.get-unit-edit-form')->with(compact('unit'));
	}

	public function editTenantForm () {
		$unit = PropertyUnit::find(Request::get('unit_id'));
		//$unit = PropertyUnit::with('home_tenant')->find(Request::get('unit_id'));
		$tenant = Tenant::where('property_unit_id',Request::get('unit_id'))->first();
		return view('property_units.get-unit-edit-tenant-form')->with(compact('tenant','unit'));
	}

	public function edit () {
		$unit = PropertyUnit::find(Request::get('id'));
		if($unit) {
			// dd(Request::all());
			$unit->fill(Request::all());
			if (Request::get('type') == 1) {
				$unit->sub_type =Request::get('sub_type');
            } else $unit->sub_type ="";
            if (Request::get('is_land')) {
                $unit->is_land = true;
            } else $unit->is_land = false;
            if (Request::get('transferred_date')) {
                $unit->transferred_date = Request::get('transferred_date');
            } else {
                $unit->transferred_date = null;
            }
            if (Request::get('insurance_expire_date')) {
                $unit->insurance_expire_date = Request::get('insurance_expire_date');
            } else {
                $unit->insurance_expire_date = null;
            }
            $unit->property_id = Auth::user()->property_id;
            $unit->property_size = Request::get('property_size') ? str_replace(',', "", Request::get('property_size')) : 0;
            $unit->extra_cf_charge = Request::get('extra_cf_charge') ? str_replace(',', "", Request::get('extra_cf_charge')) : 0;
            $unit->ownership_ratio = Request::get('ownership_ratio') ? str_replace(',', "", Request::get('ownership_ratio')) : 0;
            $unit->public_utility_fee = Request::get('public_utility_fee') ? str_replace(',', "", Request::get('public_utility_fee')) : 0;
            $unit->utility_discount = Request::get('utility_discount') ? str_replace(',', "", Request::get('utility_discount')) : 0;
            $unit->property_size = ($unit->property_size > 0) ? $unit->property_size : 0;
            // Garbage collection
            $unit->garbage_collection_fee = Request::get('utility_discount') ? str_replace(',', "", Request::get('garbage_collection_fee')) : 0;
            $unit->static_cf_rate = Request::get('static_cf_rate') ? str_replace(',', "", Request::get('static_cf_rate')) : 0;

            $unit->save();

            $key_card_id_arr = [];

            if (Request::get('keycard') != null) {
                foreach (Request::get('keycard') as $key_card_item) {
                    if ($key_card_item['id'] != "") {
                        $key_card_update = Keycard::find($key_card_item['id']);
                        $key_card_update->serial_number = $key_card_item['serial_number'];
                        $key_card_update->status = $key_card_item['status'];
                        $key_card_update->save();

                        $key_card_id_arr[] = $key_card_item['id'];
                    } else {
                        if ($key_card_item['serial_number'] != "") {
                            $key_card = new Keycard;
                            $key_card->serial_number = $key_card_item['serial_number'];
                            $key_card->property_id = Auth::user()->property_id;
                            $key_card->property_unit_id = Request::get('id');
                            $key_card->save();

                            $key_card_id_arr[] = $key_card->id;
                        }
                    }
                }

                if (!empty($key_card_id_arr)) {
                    $keycard_old = Keycard::where('property_unit_id', Request::get('id'))->whereNotIn('id', $key_card_id_arr)->get();
                    unset($key_card_id_arr);
                    foreach ($keycard_old as $item_delete) {
                        $item_delete->delete();
                    }
                }
            } else {
                $keycard_old = Keycard::where('property_unit_id', Request::get('id'))->get();
                unset($key_card_id_arr);
                foreach ($keycard_old as $item_delete) {
                    $item_delete->delete();
                }
            }
        }

		return redirect('admin/property/units');
	}
	
	public function editTenant () {
		if(Request::get('id')) {
			$unit = Tenant::find(Request::get('id'));
			$unit->fill(Request::all());
			$unit->save();
		}else{
			if(Request::get('name') != null) {
				$unit = new Tenant;
				$unit->fill(Request::all());
				$unit->save();
			}
		}
		return redirect('admin/property/units');
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
	
	public function deleteTenant () {
		$data = [
			'msg' => 'fail'
		];
		if (Request::isMethod('post')) {
			$tenant = Tenant::find(Request::get('tenant_id'));
			if (isset($tenant)) {
				$tenant->delete();
				$data = [
					'msg' => 'success'
				];
			}
		}

		return $data;
	}

	public function clearUnit () {
		$unit = PropertyUnit::find(Request::get('id'));
		$new_unit = new PropertyUnit;
		$new_unit->fill($unit->toArray());
		// disable property
		$unit->active = false;
		$unit->save();
		// disable user account
		$users = User::where('property_unit_id',Request::get('id'))->get();
		foreach ($users as &$user) {
			$user->active = false;
			$user->save();
		}
		$new_unit->owner_name_th = Request::get('owner_name_th');
		$new_unit->owner_name_en = Request::get('owner_name_en');
		$new_unit->pet = $new_unit->vehicle = $new_unit->phone = $new_unit->resident_count = $new_unit->delivery_address = NULL;
		// set invite code
        $new_unit->invite_code = $this->generateInviteCode();
		$new_unit->save();
		return redirect('admin/property/units');
	}

	public function checkBalance () {
		$unit = PropertyUnit::find(Request::get('unit_id'));
		if( $unit && $unit->balance > 0 ) {
			$r = array(
				'result' => true,
				'balance' => $unit->balance
			);
		} else {
			$r = array(
				'result' => false
			);
		}
		return response()->json($r);
	}

	function generateInviteCode() {
		$code = $this->randomInviteCodeCharacter();
		$count = PropertyUnit::where('invite_code', '=', $code)->count();
		while($count > 0) {
			$code = $this->randomInviteCodeCharacter();
			$count = PropertyUnit::where('invite_code', '=', $code)->count();
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

	public function exportPropertyUnit(){

        $filename = trans('messages.Prop_unit.page_head');
        $property = Property::find(Auth::user()->property_id);
        $units 		= PropertyUnit::with('home_tenant','home_keycard')->where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->get();

        try {
            Excel::create($filename, function ($excel) use ($units, $filename, $property) {
               $excel->sheet("ข้อมูลที่พักอาศัย", function ($sheet) use ($units, $property) {
                    $sheet->setWidth(array(
                        'A' => 20,
                        'B' => 20,
                        'C' => 30,
                        'D' => 15,
                        'E' => 15,
                        'F' => 15,
                        'G' => 15,
                        'H' => 15,
                        'I' => 15,
                        'J' => 20,
                        'K' => 20,
                        'L' => 20,
                        'O' => 20,
                        'P' => 20,
                        'Q' => 30,
                        'R' => 30,
                        'S' => 30,
                    ));
                    $sheet->loadView('property_units.data-export')->with(compact('units', 'property'));
                });

                $excel->setCreator('Nabour Application');
                $excel->setKeywords('Nabour PropertyUnit utility settings');
            })->export('xls');
        }catch (LaravelExcelException $ex){
            $error= $ex;
	    }
    }
}
