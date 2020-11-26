<?php namespace App\Http\Controllers\AdminNabour\RootAdmin;;
use Request;
use Auth;
use Redirect;
use Mail;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;
# Model
use App\Property;
use App\BackendModel\Property as BackendProperty;
use App\PropertyUnit;
use App\BackendModel\User as BackendUser;
use App\User;
use App\Province;
use App\PropertyFeature;
use App\BillWater;
use App\BillElectric;
//use App\PropertyContract;
use App\UserPropertyFeature;
use App\ManagementGroup;
use App\SalePropertyDemo;
use App\BackendModel\Package;
use App\BackendModel\Quotation;
use Validator;
use App\BackendModel\Contract;
use App\Notification;
use App\PropertyForm;
use App\BackendModel\Customer;
use App\Http\Controllers\AdminNabour\Sales\AccountController as AccountController;
use DB;
class PropertyController extends Controller {

    protected $app;

    public function __construct () {
        $this->middleware('admin');
    }

    public function add () {
        $property = new Property;
        $p = new Province;
        //dd(Request::all());
        $provinces = $p->getProvince();
        if (Request::isMethod('post'))
        {

            $property = Request::except('id','_token');
            $new_prop = new Property;
            //$vp = $new_prop->validate($property);


            $vu = Validator::make($property['user'], [
                'name' => 'required|max:255',
                'email' => 'unique:back_office.users',
                'password' => 'alpha_num|min:6|required',
                'password_confirm' => 'alpha_num|min:6|required|same:password'
            ]);

            $vp = Validator::make($property, [
                'property_name_th'          => 'required',
                'property_name_en'          => 'required',
                'juristic_person_name_th'   => 'required',
                'juristic_person_name_en'   => 'required'
            ]);

            if ($vu->fails() or $vu->fails()) {
                $v = array_merge_recursive($vp->messages()->toArray(), $vu->messages()->toArray());
                return redirect()->back()->withInput()->withErrors($v);
            } else {
                $new_prop->fill($property);
                $new_prop->unit_size = str_replace(',', '', $new_prop->unit_size);
                if(empty($new_prop->unit_size)) $new_prop->unit_size = 0;

                $new_prop->min_price = str_replace(',', '', $new_prop->min_price);
                if(empty($new_prop->min_price)) $new_prop->min_price = 0;

                $new_prop->max_price = str_replace(',', '', $new_prop->max_price);
                if(empty($new_prop->max_price)) $new_prop->max_price = 0;
                $new_prop->property_no_label = Request::get('number');
                $new_prop->save();
               
                User::create([
                    'name' => $property['user']['name'],
                    'email' => $property['user']['email'],
                    'password' => bcrypt($property['user']['password']),
                    'property_id' => $new_prop->id,
                    'role' => 1
                ]);

                //Save Property unit
                if(!Request::get('unit_later')) {
                    if(!empty(Request::get('units'))) {
                        $lines = explode(PHP_EOL, Request::get('units'));
                        $array_prop = array();
                        foreach ($lines as $line) {
                            $array_prop[] = str_getcsv($line);
                        }

                        if(!empty($array_prop)) {
                            $units = array();

                            foreach ($array_prop as $unit) {
                                $units[] = new PropertyUnit([
                                    'is_land' 			=> false,
                                    'unit_number' 		=> $unit[0],
                                    'owner_name_th' 	=> $unit[1],
                                    'owner_name_en' 	=> $unit[1],
                                    'property_size' 	=> empty($unit[2])?0:$unit[2],
                                    'transferred_date'	=> empty($unit[3])?NULL:$unit[3],
                                    'insurance_expire_date'	=> empty($unit[4])?NULL:$unit[4],
                                    'phone'				=> $unit[5],
                                    'delivery_address' 	=> empty($unit[6])?'':$unit[6],
                                    'unit_floor' 	    => (strtolower($unit[7]) == 'null')?'':$unit[7],
                                    'invite_code'		=> $this->generateInviteCode()
                                ]);
                            }
                            $new_prop->property_unit()->saveMany($units);
                        }
                    } elseif( !empty($property['unit']) ) {
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
                }

                // Property Feature Setup
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

                // User Property Menu Feature
                $user_feature = new UserPropertyFeature;
                $user_feature->property_id = $new_prop->id;
                $user_feature->menu_committee_room = true;
                $user_feature->menu_event = true;
                $user_feature->menu_vote = true;
                $user_feature->menu_tenant = true;
                $user_feature->menu_vehicle = true;
                $user_feature->menu_prepaid = true;
                $user_feature->menu_revenue_record = true;
                $user_feature->menu_retroactive_receipt = true;
                $user_feature->menu_common_fee = true;
                $user_feature->menu_cash_on_hand = true;
                $user_feature->menu_pettycash = true;
                $user_feature->menu_fund = true;
                $user_feature->menu_complain = true;
                $user_feature->menu_parcel = true;
                $user_feature->menu_message = true;
                $user_feature->menu_utility = true;
                $user_feature->menu_finance_group = true;
                $user_feature->save();

                return redirect('customer/property/list'); 
            }
        }
        $pmg = new ManagementGroup;
        $pmg = $pmg->get();


            $_props = new BackendProperty;
            $max_cus = $_props->max('property_no_label');


        return view('Backend.property.add')->with(compact('property','provinces','pmg','max_cus'));
        //dd($pmg);
    }

    public function edit($id) {
        $p = new Province;
        $provinces = $p->getProvince();
        //$request=null;
        if (Request::isMethod('post'))
        {

            $backendproperty = BackendProperty::find($id);
            $backendproperty->juristic_person_name_th   = Request::get('juristic_person_name_th');
            $backendproperty->province                  = Request::get('province');
            $backendproperty->juristic_person_name_en   = Request::get('juristic_person_name_en');
            $backendproperty->property_name_th          = Request::get('property_name_th');
            $backendproperty->property_name_en          = Request::get('property_name_en');
            $backendproperty->developer_group_id        = Request::get('developer_group_id');
            $backendproperty->save();

            //dd($backendproperty);
            $property = Request::all();

            $rules = ['name' => 'required|max:255'];

            if(!empty($property['password'])) {
                $rules += [
                    'password' => 'alpha_num|min:6|required',
                    'password_confirm' => 'alpha_num|min:6|required|same:password'
                ];
            }
            $vu = Validator::make($property['user'], $rules);

            $vp = Validator::make($property, [
                'property_name_th'          => 'required',
                'property_name_en'          => 'required',
                'juristic_person_name_th'   => 'required',
                'juristic_person_name_en'   => 'required'
            ]);

            if ($vp->fails() or $vu->fails()) {
                $v = array_merge_recursive($vp->messages()->toArray(), $vu->messages()->toArray());
                return redirect()->back()->withInput()->withErrors($v);
            } else {
                $prop = Property::find($property['id']);
                $prop->fill($property);
                $prop->unit_size = str_replace(',', '', $prop->unit_size);
                if(empty($prop->unit_size)) $prop->unit_size = 0;

                $prop->min_price = str_replace(',', '', $prop->min_price);
                if(empty($prop->min_price)) $prop->min_price = 0;

                $prop->max_price = str_replace(',', '', $prop->max_price);
                if(empty($prop->max_price)) $prop->max_price = 0;
                $prop->save();
                //$this->updateBackendProperty ($prop);
             
                $user = User::find($property['user']['id']);
                //$user->fill($property['user']);
                if(!empty($property['user']['password'])) {
                    $user->fill($property['user']);
                    if ($user->password != bcrypt($property['user']['password'])) {
                        $user->password = bcrypt($property['user']['password']);
                    }
                }else{
                    $user->name = $property['user']['name'];
                    $user->email = $property['user']['email'];
                }
                $user->save();
                return redirect('customer/property/list');
            }
        }
        else {
            $property = Property::with(['property_admin' => function ($query) {
                $query->where('role', '=', '1');
            }])->find($id);
            $property1 = Property::with(['property_admin' => function ($query) {
                $query->where('role', '=', '1');
            }])->find($id);

            $user = $property->property_admin;
           if(isset($user)) {
                $property = $property->toArray();
                $property['user'] = $user->toArray();
            }

            if(isset($data)) {
                $data_array = $data->toArray();
            }

            $pmg = new ManagementGroup;
            $pmg = $pmg->get();


            $sale1 = BackendUser::where('id','!=',Auth::user()->id)
                ->where('role','=',4)
                ->orderBy('created_at','DESC')
                ->paginate(30);

            $package = array();


            return view('Backend.property.edit')->with(compact('data_array','sing','max_cus','property','provinces','pmg','property1','sale1','max_quo','package'));
        }

    }

    public function view ($id) {
        $p = new Province;
        $provinces = $p->getProvince();
        $property = Property::with(['property_admin' => function ($query) {
            $query->where('role', '=', '1');
        }])->find($id);
        $user = $property->property_admin;
        if(isset($user)) {
            $property = $property->toArray();
            $property['user'] = $user->toArray();
        }
        return view('Backend.property.view')->with(compact('property','provinces'));
    }

    public function index ()  {
        $props = new BackendProperty;
        $props = $props->where('is_demo',false);

        if(Request::get('customer')) {
            $props = $props->where('property_no_label','=',Request::get('customer'));
        }

        if(Request::get('province')) {
            $props = $props->where('province','=',Request::get('province'));
        }

        if(Request::get('developer_group_id')) {
            $props = $props->where('developer_group_id','=',Request::get('developer_group_id'));
        }

        if(Request::get('package')){
            $props = $props->whereHas('latest_contract', function ($q) {
                $q ->where('package','=',Request::get('package'));
            });
        }

        if(Request::get('contract_code')){
            //dd(Request::get('contract_code'));
            $props = $props->whereHas('latest_contract', function ($q) {
                $q ->where('contract_id','=',Request::get('contract_code'));
            });
        }

        //Join table
        if(Request::get('property_type')){
            $props = $props->whereHas('latest_contract', function ($q) {
                $q ->where('property_type','=',Request::get('property_type'));
            });
        }//Join table


        if(Request::get('name')) {
            $props = $props->where('property_name_th','like',"%".Request::get('name')."%")
                    ->orWhere('property_name_en','like',"%".Request::get('name')."%");
        }

        


        $p_rows = $props->orderBy('property_no_label','desc')->paginate(50);
        $p = new Province;
        $provinces = $p->getProvince();

        $pmg = new ManagementGroup;
        $pmg = $pmg->lists('name','id');//->toArray();

        $package = $quotation = array();



        if(!Request::ajax()) {
            $property_list = array(''=> trans('messages.Signup.select_property') );
            return view('Backend.property.list')->with(compact('p_rows','provinces','property_list','pmg','package','quotation'));
        } else {
            return view('Backend.property.list-element')->with(compact('p_rows','provinces','pmg','package','quotation'));
        }

    }

    public function demoList ()  {
        $props = new Property;
        $props = $props->with('sale_property')->where('is_demo',true);
        $demo = true;
        if(Request::get('province')) {
            $props = $props->where('province','=',Request::get('province'));
        }

        if(Request::get('name')) {
            $props = $props->where(function ($q) {
                $q ->where('property_name_th','like',"%".Request::get('name')."%")
                    ->orWhere('property_name_en','like',"%".Request::get('name')."%");
            });
        }

         if(Request::get('name_used')) {
             $props = $props->whereHas('sale_property',function ($q) {
                 $q ->where('property_test_name','like',"%".Request::get('name_used')."%");
             });
         }

        if(Request::get('contact_name')) {
            $props = $props->whereHas('sale_property',function ($q) {
                $q ->where('contact_name','like',"%".Request::get('contact_name')."%");
            });
        }


        $p_rows = $props->with('sale_property')->orderBy('updated_at','DESC')->paginate(50);
        $p = new Province;
        $provinces = $p->getProvince();

        $_lead = new Customer;
        $_lead = $_lead->where('role','=',1);
        $_lead = $_lead->orderBy('created_at','desc')->get();

        if(!Request::ajax()) {
            $property_list = array(''=> trans('messages.Signup.select_property') );
            return view('Backend.property.demo-list')->with(compact('p_rows','provinces','property_list','demo','_lead'));
        } else {
            return view('Backend.property.demo-list-element')->with(compact('p_rows','provinces','_lead'));
        }

    }

    public function status () {
        if(Request::ajax()) {
            $property   = Property::find(Request::get('pid'));
            $backendproperty   = BackendProperty::find(Request::get('pid'));
            //$_property  = BackendProperty::find(Request::get('pid'));

            if($property) {
                //$property->active_status = $_property->active_status = Request::get('status');
                $property->active_status = Request::get('status');
                $backendproperty->active_status = Request::get('status');

                if($property->active_status == 0) {
                    $property->last_inactive_date = date('Y-m-d H:i:s');
                }
                $property->save();
                $backendproperty->save();
               // $_property->save();
                return response()->json(['result'=>true]);
            }
        }
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

    public function generateAllInviteCodePropertyUnit($pid){
        $property_unit = PropertyUnit::where('property_id',$pid)->get();
        foreach($property_unit as $item){
            $item->invite_code = $this->generateInviteCode();
            $item->save();
        }
        return redirect('customer/property/list');
    }


    public function getPropertyFeature () {
        if(Request::ajax()) {
            $feature = PropertyFeature::where('property_id','=',Request::get('pid'))->first();
            $property = Property::find(Request::get('pid'));
            return view('Backend.property.property-feature-form-edit')->with(compact('feature','property'));
        }
    }

    public function editPropertyFeature() {
        try{
            if (Request::isMethod('post')) {
                if(Request::get('id') != null) {
                    $feature = PropertyFeature::find(Request::get('id'));
                }else{
                    $feature = new PropertyFeature;
                }

                $feature->property_id = Request::get('property_id');
                $feature->menu_committee_room = Request::get('menu_committee_room') ? true : false;
                $feature->menu_event = Request::get('menu_event') ? true : false;
                $feature->menu_vote = Request::get('menu_vote') ? true : false;
                $feature->menu_tenant = Request::get('menu_tenant') ? true : false;
                $feature->menu_vehicle = Request::get('menu_vehicle') ? true : false;
                $feature->menu_prepaid = Request::get('menu_prepaid') ? true : false;
                $feature->menu_revenue_record = Request::get('menu_revenue_record') ? true : false;
                $feature->menu_retroactive_receipt = Request::get('menu_retroactive_receipt') ? true : false;
                $feature->menu_common_fee = Request::get('menu_common_fee') ? true : false;
                $feature->menu_cash_on_hand = Request::get('menu_cash_on_hand') ? true : false;
                $feature->menu_pettycash = Request::get('menu_pettycash') ? true : false;
                $feature->menu_fund = Request::get('menu_fund') ? true : false;
                $feature->menu_complain = Request::get('menu_complain') ? true : false;
                $feature->menu_parcel = Request::get('menu_parcel') ? true : false;
                $feature->menu_message = Request::get('menu_message') ? true : false;
                $feature->menu_utility = Request::get('menu_utility') ? true : false;
                $feature->market_place_singha = Request::get('market_place_singha') ? true : false;
                $feature->menu_statement_of_cash = Request::get('menu_statement_of_cash') ? true : false;
                $feature->aggregate_invoice = Request::get('aggregate_invoice') ? true : false;
                $feature->preprint_invoice = Request::get('preprint_invoice') ? true : false;
                $feature->preprint_invoice_view_prefix = Request::get('preprint_invoice_view_prefix');
                $feature->preprint_receipt = Request::get('preprint_receipt') ? true : false;
                $feature->preprint_receipt_view_prefix = Request::get('preprint_receipt_view_prefix');


                if($feature->menu_prepaid == false
                    && $feature->menu_revenue_record == false
                    && $feature->menu_retroactive_receipt == false
                    && $feature->menu_common_fee == false
                    && $feature->menu_cash_on_hand == false
                    && $feature->menu_pettycash == false
                    && $feature->menu_fund == false
                    && $feature->menu_utility == false
                    && $feature->menu_statement_of_cash == false
                    && $feature->aggregate_invoice == false
                ) {
                    $feature->menu_finance_group = false;
                }else{
                    $feature->menu_finance_group = true;
                }

                $feature->save();

                // user app menu
                if( !$feature->menu_common_fee || !$feature->menu_event || !$feature->menu_vote ) {
                    $property = Property::find($feature->property_id);
                    if( !$feature->menu_common_fee ) {
                        $property->allow_user_view_cf_report = false;
                    }
                    if( !$feature->menu_event ) {
                        $property->allow_user_add_event = false;
                    }
                    if( !$feature->menu_vote ) {
                        $property->allow_user_add_vote = false;
                    }
                    $property->save();
                }
                
                echo "saved";

            }else {
                echo "error";
            }
        }catch(Exception $ex){
            echo "error";
        }
    }

    public function getInitialUnit () {
        if(Request::ajax()) {
            $property = Property::find(Request::get('pid'));
            return view('Backend.property.property-initial-meter-form-edit')->with(compact('property'));
        }
    }
//setting function user
    public function getInitialUnit_user () {
        if(Request::ajax()) {
            $feature = UserPropertyFeature::where('property_id','=',Request::get('pid'))->first();
            $property = Property::find(Request::get('pid'));
            return view('Backend.property.property-feature-form-edit-user')->with(compact('feature','property'));
        }
    }

    public function importInitialUnit(){
        try {
            if(Request::isMethod('post')) {
                $property_id = Request::get('property_id');
                // water data
                $water_text = explode(PHP_EOL, Request::get('water_units'));
                $array_water_meter = array();
                foreach ($water_text as $line) {
                    $array_water_meter[] = str_getcsv($line);
                }

                // Add water data
                $initial_water_counter = 0;
                foreach ($array_water_meter as $item_meter) {
                    $property_unit = PropertyUnit::find($item_meter['0']);
                    //$property_unit = PropertyUnit::where('property_id', $property_id)->where('unit_number', $item_meter['0'])->first();
                    if (isset($property_unit)) {
                        $bill = new BillWater();
                        $bill->property_id = $property_id;
                        $bill->property_unit_id = $property_unit->id;
                        $bill->bill_date_period = $item_meter['1'];
                        $bill->unit = $item_meter['2'] != "" ? $item_meter['2'] : 0;
                        $bill->net_unit = $item_meter['3'] != "" ? $item_meter['3'] : 0;
                        $bill->status = strtolower($item_meter['4']) == "true" ? true : false;
                        $bill->save();
                        $initial_water_counter++;
                    }
                }
                if ($initial_water_counter > 0) {
                    $property_save = Property::find($property_id);
                    $property_save->is_add_initial_water_meter = true;
                    $property_save->save();
                }

                //electric data
                $electric_text = explode(PHP_EOL, Request::get('electric_units'));
                $array_electric_meter = array();
                foreach ($electric_text as $line) {
                    $array_electric_meter[] = str_getcsv($line);
                }
                // Add electric data
                $initial_electric_counter = 0;
                foreach ($array_electric_meter as $item_meter) {
                    $property_unit = PropertyUnit::find($item_meter['0']);
                    //$property_unit = PropertyUnit::where('property_id', $property_id)->where('unit_number', $item_meter['0'])->first();
                    if (isset($property_unit)) {
                        $bill = new BillElectric();
                        $bill->property_id = $property_id;
                        $bill->property_unit_id = $property_unit->id;
                        $bill->bill_date_period = $item_meter['1'];
                        $bill->unit = $item_meter['2'] != "" ? $item_meter['2'] : 0;
                        $bill->net_unit = $item_meter['3'] != "" ? $item_meter['3'] : 0;
                        $bill->status = strtolower($item_meter['4']) == "true" ? true : false;
                        $bill->save();
                        $initial_electric_counter++;
                    }
                }
                if ($initial_electric_counter > 0) {
                    $property_save = Property::find($property_id);
                    $property_save->is_add_initial_electric_meter = true;
                    $property_save->save();
                }
                echo "saved";
            }else{
                echo "error";
            }
        }catch(Exception $ex){
            echo "error";
        }
    }

    function getContractList () {
        $prop = Property::with('has_contract')->find(Request::get('pid'));
        if( $prop ) {
            //dd($prop->has_contract->toArray());
            return view('Backend.property_contract_sign.contract-list-detail')->with(compact('prop'));
        }
    }

    function addContract () {
        $property_sign = new PropertyContract;
        $property_sign->fill(Request::all());
        $property_sign->property_id = Request::get('property_id');
        $property_sign->save();
        return redirect('customer/property/list');
    }

    function editContract () {
        if(Request::ajax()) {
            $sign = PropertyContract::find(Request::get('tid'));
            $sign->contract_date 		= date('Y/m/d', strtotime($sign->contract_date));
            $sign->contract_end_date 	= date('Y/m/d', strtotime($sign->contract_end_date));
            $sign->info_delivery_date 	= date('Y/m/d', strtotime($sign->info_delivery_date));
            return view('Backend.property_contract_sign.contract-edit-form')->with(compact('sign'));
        } else {
            $sign = PropertyContract::find(Request::get('id'));
            $sign->fill(Request::all());
            $sign->save();
            return redirect('customer/property/list');
        }
    }

    function directLogin ($id) {
        $user = User::where('property_id',$id)->where('role',1)->first();
        //dd($user);
        Request::session()->put('auth.root_admin',Auth::user());
        Auth::login($user);
        return redirect('feed');
    }

    function createUnitCsv () {
        if(!empty(Request::get('units'))) {
            dd(Request::all());
            $property = Property::find(Request::get('property_id'));
            if($property) {
                $lines = explode(PHP_EOL, Request::get('units'));
                $array_prop = array();
                foreach ($lines as $line) {
                    $array_prop[] = str_getcsv($line);
                }
                $result = $this->checkUnitFormat($array_prop);
                if($result['result']) {
                    $property->property_unit()->delete();
                    $units = array();
                    foreach ($array_prop as $unit) {
                        $units[] = new PropertyUnit([
                            'is_land' 			=> false,
                            'unit_number' 		=> $unit[0],
                            'owner_name_th' 	=> $unit[1],
                            'owner_name_en' 	=> $unit[1],
                            'property_size' 	=> empty(str_replace(" ","",$unit[2]))?0:$unit[2],
                            'transferred_date'	=> empty($unit[3])?NULL:$unit[3],
                            'insurance_expire_date'	=> empty($unit[4])?NULL:$unit[4],
                            'phone'				=> $unit[5],
                            'delivery_address' 	=> empty($unit[6])?'':$unit[6],
                            'building' 	        => empty($unit[7])?'':$unit[7],
                            'unit_floor' 	    => (strtolower($unit[8]) == 'null')?'':$unit[8],
                            'email'             => empty($unit[9])?NULL:$unit[9],
                            'is_billing_water'  => (empty($unit[10]) || strtolower($unit[10]) == 'false')?false : true,
                            'water_billing_rate'  => (empty($unit[11]))? 0 : $unit[11],
                            'is_billing_electric'  => (empty($unit[12]) || strtolower($unit[12]) == 'false')?false : true,
                            'electric_billing_rate'  => (empty($unit[13]))? 0 : $unit[13],
                            'public_utility_fee'  => (empty($unit[14]))? 0 : $unit[14],
                            'ownership_ratio'   => (empty($unit[15]))? 0 : $unit[15],
                            'type'   => (empty($unit[16]))? 1 : $unit[16],
                            'sub_type'   => (empty($unit[17]))? 1 : $unit[17],
                            'invite_code'		=> $this->generateInviteCode()
                        ]);
                    }
                    $property->property_unit()->saveMany($units);
                    return response()->json([
                        'result' => true,
                        'message' => 'นำเข้าข้อมูลเสร็จสมบูรณ์'
                    ]);
                } else {
                    return response()->json([
                        'result' => false,
                        'message' => $result['messages']//'ข้อมูลที่พักอาศัยไม่ถูกแบบฟอร์ม'
                    ]);
                }
            } else {
                return response()->json([
                    'result' => false,
                    'message' => 'ไม่พบนิติบุคคล'
                ]);
            }

        } else {
            return response()->json([
                'result' => false,
                'message' => 'ไม่มีข้อมูลที่พักอาศัย'
            ]);
        }
    }

    function addUnitCsvAfter () {
        if(!empty(Request::get('update_units'))) {
            $property = Property::find(Request::get('property_id_update'));
            if($property) {
                $lines = explode(PHP_EOL, Request::get('update_units'));
                $array_prop = array();
                foreach ($lines as $line) {
                    $array_prop[] = str_getcsv($line);
                }
                $result = $this->checkUnitFormat($array_prop);
                if($result['result']) {
                    $units = array();
                    foreach ($array_prop as $unit) {
                        $units[] = new PropertyUnit([
                            'is_land' 			=> false,
                            'unit_number' 		=> $unit[0],
                            'owner_name_th' 	=> $unit[1],
                            'owner_name_en' 	=> $unit[1],
                            'property_size' 	=> empty(str_replace(" ","",$unit[2]))?0:$unit[2],
                            'transferred_date'	=> empty($unit[3])?NULL:$unit[3],
                            'insurance_expire_date'	=> empty($unit[4])?NULL:$unit[4],
                            'phone'				=> $unit[5],
                            'delivery_address' 	=> empty($unit[6])?'':$unit[6],
                            'building' 	        => empty($unit[7])?'':$unit[7],
                            'unit_floor' 	    => (strtolower($unit[8]) == 'null')?'':$unit[8],
                            'email'             => empty($unit[9])?NULL:$unit[9],
                            'is_billing_water'  => (empty($unit[10]) || strtolower($unit[10]) == 'false')?false : true,
                            'water_billing_rate'  => (empty($unit[11]))? 0 : $unit[11],
                            'is_billing_electric'  => (empty($unit[12]) || strtolower($unit[12]) == 'false')?false : true,
                            'electric_billing_rate'  => (empty($unit[13]))? 0 : $unit[13],
                            'public_utility_fee'  => (empty($unit[14]))? 0 : $unit[14],
                            'ownership_ratio'   => (empty($unit[15]))? 0 : $unit[15],
                            'type'   => (empty($unit[16]))? 1 : $unit[16],
                            'invite_code'		=> $this->generateInviteCode()
                        ]);
                    }
                    $property->property_unit()->saveMany($units);
                    return response()->json([
                        'result' => true,
                        'message' => 'นำเข้าข้อมูลเสร็จสมบูรณ์'
                    ]);
                } else {
                    return response()->json([
                        'result' => false,
                        'message' => $result['messages']//'ข้อมูลที่พักอาศัยไม่ถูกแบบฟอร์ม'
                    ]);
                }
            } else {
                return response()->json([
                    'result' => false,
                    'message' => 'ไม่พบนิติบุคคล'
                ]);
            }

        } else {
            return response()->json([
                'result' => false,
                'message' => 'ไม่มีข้อมูลที่พักอาศัย'
            ]);
        }
    }

    public function updatePropertyUnitCsv(){
        if(!empty(Request::get('edit_units'))) {
            $property = Property::find(Request::get('property_id_edit'));
            if($property) {
                $lines = explode(PHP_EOL, Request::get('edit_units'));
                $array_prop = array();
                foreach ($lines as $line) {
                    $array_prop[] = str_getcsv($line);
                }
                $result = $this->checkUnitFormatForUpdateUnits($array_prop);
                if($result['result']) {
                    //$units = array();
                    foreach ($array_prop as $unit) {
                        $property_unit = PropertyUnit::find($unit[0]);

                        $property_unit->unit_number = $unit[1];
                        $property_unit->owner_name_th = $unit[2];
                        $property_unit->owner_name_en = $unit[2];
                        $property_unit->property_size = empty(str_replace(" ","",$unit[3]))?0:$unit[3];
                        $property_unit->transferred_date = empty($unit[4])?NULL:$unit[4];
                        $property_unit->insurance_expire_date = empty($unit[5])?NULL:$unit[5];
                        $property_unit->phone = $unit[6];
                        $property_unit->delivery_address = empty($unit[7])?'':$unit[7];
                        $property_unit->building = empty($unit[8])?'':$unit[8];
                        $property_unit->unit_floor = (strtolower($unit[9]) == 'null')?'':$unit[9];
                        $property_unit->email = empty($unit[10])?NULL:$unit[10];
                        $property_unit->is_billing_water = (empty($unit[11]) || strtolower($unit[11]) == 'false')?false : true;
                        $property_unit->water_billing_rate = (empty($unit[12]))? 0 : $unit[12];
                        $property_unit->is_billing_electric = (empty($unit[13]) || strtolower($unit[13]) == 'false')?false : true;
                        $property_unit->electric_billing_rate = (empty($unit[14]))? 0 : $unit[14];
                        $property_unit->public_utility_fee = (empty($unit[15]))? 0 : $unit[15];
                        $property_unit->ownership_ratio = (empty($unit[16]))? 0 : $unit[16];
                        $property_unit->type = (empty($unit[17]))? 1 : $unit[17];
                        $property_unit->waste_water_treatment = (empty($unit[18]))? 0 : $unit[18];

                        $property_unit->save();
                    }

                    return response()->json([
                        'result' => true,
                        'message' => 'นำเข้าข้อมูลเสร็จสมบูรณ์'
                    ]);
                } else {
                    return response()->json([
                        'result' => false,
                        'message' => $result['messages']//'ข้อมูลที่พักอาศัยไม่ถูกแบบฟอร์ม'
                    ]);
                }
            } else {
                return response()->json([
                    'result' => false,
                    'message' => 'ไม่พบนิติบุคคล'
                ]);
            }

        } else {
            return response()->json([
                'result' => false,
                'message' => 'ไม่มีข้อมูลที่พักอาศัย'
            ]);
        }
    }

    private function checkUnitFormat ($units) {
        $valid = true;
        $msg = "";
        if(!empty($units)) {
            foreach($units as $row => $unit) {
                if(count($unit) != 17) {
                    $valid = false;
                    $msg .= 'ข้อมูลบรรทัดที่ '.($row +1).' จำนวนข้อมูลไม่ถูกต้อง<br/>';
                }
            }
            //return true;
        } else {
            $valid =  false;
            $msg = 'ไม่มีข้อมูล';
        }
        return array('result'=> $valid, 'messages' => $msg);
    }

    private function checkUnitFormatForUpdateUnits ($units) {
        $valid = true;
        $msg = "";
        if(!empty($units)) {
            foreach($units as $row => $unit) {
                if(count($unit) != 19) {
                    $valid = false;
                    $msg .= 'ข้อมูลบรรทัดที่ '.($row +1).' จำนวนข้อมูลไม่ถูกต้อง<br/>';
                }
            }
            //return true;
        } else {
            $valid =  false;
            $msg = 'ไม่มีข้อมูล';
        }
        return array('result'=> $valid, 'messages' => $msg);
    }

//    savefunctionuser
    public function savefunctionuser () {
        try{
            if (Request::isMethod('post')) {
                if(Request::get('id') != null) {
                    $feature = UserPropertyFeature::find(Request::get('id'));
                }else{
                    $feature = new UserPropertyFeature;
                }

                $feature->property_id = Request::get('property_id');
                $feature->menu_committee_room = Request::get('menu_committee_room') ? true : false;
                $feature->menu_event = Request::get('menu_event') ? true : false;
                $feature->menu_vote = Request::get('menu_vote') ? true : false;
                $feature->menu_tenant = Request::get('menu_tenant') ? true : false;
                $feature->menu_vehicle = Request::get('menu_vehicle') ? true : false;
                $feature->menu_prepaid = Request::get('menu_prepaid') ? true : false;
                $feature->menu_revenue_record = Request::get('menu_revenue_record') ? true : false;
                $feature->menu_retroactive_receipt = Request::get('menu_retroactive_receipt') ? true : false;
                $feature->menu_common_fee = Request::get('menu_common_fee') ? true : false;
                $feature->menu_cash_on_hand = Request::get('menu_cash_on_hand') ? true : false;
                $feature->menu_pettycash = Request::get('menu_pettycash') ? true : false;
                $feature->menu_fund = Request::get('menu_fund') ? true : false;
                $feature->menu_complain = Request::get('menu_complain') ? true : false;
                $feature->menu_parcel = Request::get('menu_parcel') ? true : false;
                $feature->menu_message = Request::get('menu_message') ? true : false;
                $feature->menu_utility = Request::get('menu_utility') ? true : false;


                if($feature->menu_prepaid == false
                    && $feature->menu_revenue_record == false
                    && $feature->menu_retroactive_receipt == false
                    && $feature->menu_common_fee == false
                    && $feature->menu_cash_on_hand == false
                    && $feature->menu_pettycash == false
                    && $feature->menu_fund == false
                    && $feature->menu_utility == false
                ) {
                    $feature->menu_finance_group = false;
                }else{
                    $feature->menu_finance_group = true;
                }

                $feature->save();
                echo "saved";

            }else {
                echo "error";
            }
        }catch(Exception $ex){
            echo "error";
        }
    }

    public function updateBackendProperty ($property) {

        $_property = BackendProperty::firstOrNew(array('id' => $property->id) );
        $_property->juristic_person_name_th = $property->juristic_person_name_th;
        $_property->juristic_person_name_en = $property->juristic_person_name_en;
        $_property->province                = $property->province;
        $_property->property_name_th        = $property->property_name_th;
        $_property->juristic_person_name_th = $property->juristic_person_name_th;
        $_property->property_name_en        = $property->property_name_en;
        $_property->developer_group_id      = $property->developer_group_id;
        $_property->id                      = $property->id;
        $_property->property_no_label       = $property->property_no_label;
        $_property->save();
        //dd($_property);
    }

    public function create_demo()
    {
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

            //dd(Request::get('property'));
            $property_demo = SalePropertyDemo::find(Request::get('property'));

            $property_demo->default_password   = $code;
            $property_demo->status             = 1;
            $property_demo->contact_name       = Request::get('contact_name');
            $property_demo->property_test_name = Request::get('property_test_name');
            $property_demo->province           = Request::get('province');
            $property_demo->email_contact      = Request::get('email');
            $property_demo->property_id        = Request::get('property');
            $property_demo->lead_id            = Request::get('lead_id');
            $property_demo->sale_id            = Request::get('sales_id');
            $property_demo->tel_contact        = Request::get('tel_contact');
            $property_demo->save();

            $property = new Property;
            $property = $property->where('id', Request::get('property'));
            $property = $property->first();

            //dd($property_demo);
            $this->mail_form_created(Request::get('name'), Request::get('email'),$property->property_name_th,$code);

        }

        //dump($property_demo->toArray());
        return redirect('customer/property/demo/list');
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

    public function reset(){
        $property_id = Request::get('id');
        $property = Property::find($property_id);
        $property->invoice_counter = 0;
        $property->post_parcel_counter = 0;
        $property->receipt_counter = 0;
        $property->expense_counter = 0;
        $property->payee_counter = 0;
        $property->withdrawal_slip_counter = 0;
        $property->petty_cash_balance = 0;
        $property->prepaid_slip_counter = 0;
        $property->fund_balance = 0;

        $property_demo = SalePropertyDemo::where('property_id',$property_id)->first();

        if($property_demo) {
            $property_demo->status = 0;
            $property_demo->trial_expire = null;
            $property_demo->email_contact = null;
            $property_demo->property_test_name = null;
            $property_demo->contact_name = null;
            $property_demo->tel_contact = null;
            $property_demo->lead_id = null;
            $property_demo->default_password = "demo1234";
            $property_demo->save();
        }


        // Restore Basic Data
        $user_admin_demo = User::where('property_id',$property_id)->first();
        //admin_nb029@nabour.me
        $email_admin_demo = $user_admin_demo->email;
        $prefix_email = explode("@",$email_admin_demo);
        $prefix_property = explode("_",$prefix_email[0]);
        $prefix_demo_name = strtoupper($prefix_property[1]);

        $property->property_name_th = "หมู่บ้าน " . $prefix_demo_name;
        $property->property_name_en = "Demo " . $prefix_demo_name;
        $property->juristic_person_name_th = "หมู่บ้าน " . $prefix_demo_name;
        $property->juristic_person_name_en = "Demo " . $prefix_demo_name;
        $property->area_size = "300";
        $property->unit_size = 20;
        $property->construction_by = "Nabour Construction";
        $property->address_th = "ต.สุเทพ อ.เมือง";
        $property->street_th = "ถนนศิริมังคลาจารย์ ซอย 7";
        $property->province = 50;
        $property->postcode = "50200";
        $property->lat = "18.795263983660067";
        $property->lng = "98.97235203995058";
        $property->address_en = "T.Suthep A.Mueang";
        $property->street_en = "Siri Mangkalajarn Road Soi 7";
        $property->property_type = "1";
        $property->address_no = "17/1";

        $property->save();

        $this->clearUserAccountForDemo($property_id);

        $account = new AccountController();

        $account->clearPostReport($property_id);
        $account->clearPost($property_id);
        $account->clearEvent($property_id);
        $account->clearVote($property_id);
        $account->clearDiscussion($property_id);
        $account->clearComplain($property_id);
        $account->clearCommonFeeRef($property_id);
        $account->clearTransaction($property_id);
        $account->clearPostParcel($property_id);
        $account->clearMessage($property_id);
        $account->clearInvoice($property_id);
        //$account->clearPayee($property_id);
        $account->clearVehicle($property_id);
        $account->resetBankBalance($property_id);
        $account->resetPropertyUnitBalance($property_id);
        $account->clearPropertyPettyCash($property_id);
        $account->clearPropertyFund($property_id);


        $data = [
            'msg' => 'success'
        ];
        return $data;
    }

    function clearUserAccountForDemo($property_id){
        $user_demo = User::where('property_id',$property_id)->get();
        foreach ($user_demo as $item){
            //$item->password = null;
            $item->remember_token = null;
            //$item->expire_trial = null;
            $item->active = true;
            $item->password = bcrypt("demo1234");

            // Delete notification
            $this->clearNotificationForDemo($item->id);

            $item->save();
        }

        return true;
    }

    public function assignDemoProperty(){
        $assign_form_list = Request::get('assign_form_list');
        if($assign_form_list!=1){
            $data = Request::all();
            $id = $data['lead_id'];
            $property_id=Request::get('property');
            $new_password = $this->generatePassword();
            $property_demo = SalePropertyDemo::where('property_id', $property_id)->first();
            $property_demo->status = 1;
            $property_demo->contact_name = $data['name'];
            $property_demo->email_contact = $data['email'];
            $property_demo->tel_contact = $data['tel'];
            $property_demo->default_password = $new_password;
            $property_demo->property_test_name = $data['property_test_name'];
            $property_demo->save();
            $this->setUpUserAccountForDemo($property_demo->property_id,$new_password);
            $this->mail_assign_demo_property($property_id,$property_demo->default_password);
        }else{
            $data = Request::all();
            $id = $data['property_assign_id'];
            $new_password = $this->generatePassword();
            $property_demo = SalePropertyDemo::find($id);
            $property_demo->status = 1;
            $property_demo->contact_name = $data['name'];
            $property_demo->email_contact = $data['email'];
            $property_demo->tel_contact = $data['tel'];
            $property_demo->default_password = $new_password;
            $property_demo->property_test_name = $data['property_test_name'];
            $property_demo->save();
            $this->setUpUserAccountForDemo($property_demo->property_id,$new_password);
            $this->mail_assign_demo_property($property_demo->property_id,$property_demo->default_password);
        }
            return redirect('customer/property/demo/list');
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

    function setUpUserAccountForDemo($property_id,$default_password){
        $user_demo = User::where('property_id',$property_id)->get();
        $encode_pass = bcrypt($default_password);
        foreach ($user_demo as $item){
            $item->password = $encode_pass;
            $item->remember_token = null;
            //$item->expire_trial = Carbon::today()->addDays(7)->toDateTimeString();
            $item->active = true;

            // Delete notification
            $this->clearNotificationForDemo($item->id);

            $item->save();
        }

        return true;
    }

    function mail_assign_demo_property($id,$default_password){
        $property_demo = SalePropertyDemo::where('property_id',$id)->first();
        $property_data = Property::find($property_demo->property_id);
        //$user = $property->property_admin;
        $property = $property_data->toArray();
        $admin_demo = User::where('property_id',$property_demo->property_id)->where('role','=',1)->first();
        $committee_demo = User::where('property_id',$property_demo->property_id)->where('is_chief','=',true)->first();
        $user_demo = User::where('property_id',$property_demo->property_id)->where('is_chief','=',false)->where('role','=',2)->get()->toArray();

        $email = $property_demo->email_contact;
        Mail::send('emails.demo_property_assign', [
            'property_demo'	=> $property_demo,
            'property' => $property,
            'admin_demo'		=> $admin_demo,
            'committee_demo'		=> $committee_demo,
            'user_demo'		=> $user_demo,
            'password'		=> $default_password

        ], function ($message) use($email) {
            $message->subject('ทดลองใช้งานการใช้งานระบบเนเบอร์');
            $message->from(env('MAIL_USERNAME'),'Nabour');
            $message->bcc(env('MAIL_BCC'));
            $message->to($email);
        });

        return true;
    }

    function clearNotificationForDemo($user_id){
        $notification_list = Notification::where('to_user_id',$user_id)->get();
        foreach ($notification_list as $item){
            $item->delete();
        }

        return true;
    }
}
