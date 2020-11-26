<?php namespace App\Http\Controllers\RootAdmin;;
use Request;
use Auth;
use Redirect;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;
# Model

use App\SmartBill;
use App\SmartBillLog;
use App\Property;
use App\PropertyUnit;
use App\User;
use App\Province;
use App\PropertyFeature;
use App\UserPropertyFeature;
use App\BillWater;
use App\BillElectric;
use App\PropertyContract;
use App\Bank;
class PropertyController extends Controller {

    protected $app;

    public function __construct () {
        $this->middleware('auth',['except' => ['login']]);
        if( Auth::check() && Auth::user()->role !== 0 ) {
            if(Auth::user()->role !== 5) {
                Redirect::to('feed')->send();
            }
        }
    }
    public function add () {
        $property = new Property;
        $p = new Province;
        $provinces = $p->getProvince();
        if (Request::isMethod('post'))
        {

            $property = Request::except('id','_token');
            $new_prop = new Property;
            $vp = $new_prop->validate($property);

            $new_user = new User();
            unset($new_user->rules['fname']);
            unset($new_user->rules['lname']);
            $property['user']['email'] = strtolower(trim($property['user']['email']));
            $vu = $new_user->validate($property['user']);

            if($vp->fails() or $vu->fails()) {
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
                                // dd($array_prop);
                                $units[] = new PropertyUnit([
                                    'is_land' 			=> false,
                                    'unit_number' 		=> isset($unit[0]) ? $unit[0] : null,
                                    'owner_name_th' 	=> isset($unit[1]) ? $unit[1] : null,
                                    'owner_name_en' 	=> isset($unit[1]) ? $unit[1] : null,
                                    'property_size' 	=> isset($unit[2]) ? $unit[2] : null,
                                    'transferred_date'	=> empty($unit[3])?NULL:$unit[3],
                                    'insurance_expire_date'	=> empty($unit[4])?NULL:$unit[4],
                                    'phone'				=> isset($unit[5]) ? $unit[5] : null,
                                    'delivery_address' 	=> empty($unit[6])?'':$unit[6],
                                    'unit_floor' 	    => isset($unit[7]) ? $unit[7] : null,
                                    'invite_code'		=> $this->generateInviteCode()
                                ]);
                            }
                            $new_prop->property_unit()->saveMany($units);
                        }
                    } elseif( !empty($property['unit']) ) {
                        foreach ($property['unit'] as $unit) {
                            //Get Area
                            $units[] = new PropertyUnit([
                                'unit_number' 	=>  isset($unit['no']) ? $unit['no'] : null,
                                'property_size' => empty(str_replace(" ","",$unit['area']))?0:$unit['area'],
                                'is_land' 		=> isset($unit['is_land']) ? $unit['is_land'] : null,
                                'owner_name_th' => isset($unit['owner_name_th']) ? $unit['owner_name_th'] : null,
                                'owner_name_en' => isset($unit['owner_name_en']) ? $unit['owner_name_en'] : null,
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

                if(!empty($property['contract']['contract_sign_no'])) {
                    $property_sign = new PropertyContract;
                    $property_sign->fill($property['contract']);
                    $property_sign->property_id = $new_prop->id;
                    $property_sign->save();
                }


                return redirect('root/admin/property/list');
            }
        }
        return view('property.add')->with(compact('property','provinces'));
    }

    public function edit ($id) {
        $p = new Province;
        $provinces = $p->getProvince();
        if (Request::isMethod('post'))
        {
            $property = Request::all();
            $prop = new Property();
            $vp = $prop->validate($property);

            $user = new User();
            unset($user->rules['fname']);
            unset($user->rules['lname']);
            $user->rules['email'] = 'required|email|max:255|unique:users,email,'.$property['user']['id'];
            if(empty($property['user']['password'])) {
                unset($user->rules['password']);
                unset($user->rules['password_confirm']);
            }
            $vu = $user->validate($property['user']);

            if($vp->fails() or $vu->fails()) {
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
                return redirect('root/admin/property/list');
            }
        }
        else {
            $property = Property::with(['property_admin' => function ($query) {
                $query->where('role', '=', '1');
            }])->find($id);
            $user = $property->property_admin;
            if(isset($user)) {
                $property = $property->toArray();
                $property['user'] = $user->toArray();
            }
            return view('property.edit')->with(compact('property','provinces'));
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
        return view('property.view')->with(compact('property','provinces'));
    }

    public function index ()  {
        $props = new Property;
        $props = $props->with('userCount');

        if(Request::get('sign_status')) {
            if(Request::get('sign_status') == 1) {
                $props = $props->whereHas('lastest_contract', function ($q) {
                    $q->where('contract_end_date', '>=', date('Y-m-d 23:59:59'));
                });
            } else {
                $props = $props->whereHas('lastest_contract', function ($q) {
                    $q->where('contract_end_date', '<', date('Y-m-d 23:59:59'));
                });
            }
        } else {
            $props = $props->with('lastest_contract');
        }

        $props = $props->where('is_demo',false);

        if(Request::get('province')) {
            $props = $props->where('province','=',Request::get('province'));
        }

        if(Request::get('name')) {
            $props = $props->with('lastest_contract')->where(function ($q) {
                $q ->where('property_name_th','like',"%".Request::get('name')."%")
                    ->orWhere('property_name_en','like',"%".Request::get('name')."%");
            });
        }


        
        $p_rows = $props->orderBy('created_at','desc')->paginate(50);
        $p = new Province;
        $provinces = $p->getProvince();
        if(!Request::ajax()) {
            $property_list = array(''=> trans('messages.Signup.select_property') );
            return view('property.list')->with(compact('p_rows','provinces','property_list'));
        } else {
            $bill = new SmartBill;
            return view('property.list-element')->with(compact('p_rows','provinces','bill'));
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
        $p_rows = $props->orderBy('juristic_person_name_th')->paginate(50);
        $p = new Province;
        $provinces = $p->getProvince();
        if(!Request::ajax()) {
            $property_list = array(''=> trans('messages.Signup.select_property') );
            return view('property.list')->with(compact('p_rows','provinces','property_list','demo'));
        } else {
            return view('property.demo-list-element')->with(compact('p_rows','provinces'));
        }

    }

    public function status () {
        if(Request::ajax()) {
            $property = Property::find(Request::get('pid'));
            if($property) {
                $property->active_status = Request::get('status');
                if($property->active_status == 0) {
                    $property->last_inactive_date = date('Y-m-d H:i:s');
                }
                $property->save();
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
        return redirect('root/admin/property/list');
    }

    public function getSmartBill () {
        if(Request::ajax()) {
            $bill = SmartBill::where('property_id','=',Request::get('pid'))->first();
            $property = Property::find(Request::get('pid'));
            $banks = Bank::where('property_id','=',Request::get('pid'))->get();
            $p_bank = ['' => trans('messages.smart_bill.select_bank')];
            if( $banks ) {
                foreach( $banks as  $bank ) {
                    $p_bank[$bank->id] = $bank->account_name ." (".$bank->account_number.")";
                }
            }
            $p_bank['new'] = trans('messages.smart_bill.add_new_bank');
            return view('property.smart-bill-form-edit')->with(compact('bill','property','p_bank'));
        }
    }
    public function editSmartBill() {    
        try{
            if (Request::isMethod('post')) {
                if(Request::get('id') != null) {
                    $bill = SmartBill::find(Request::get('id'));
                    $flag_log = true;
                    $smart_bill_log['original'] = $bill->toArray();
                }else{
                    $bill = new SmartBill;
                    $flag_log = false;
                }
                $bill->property_id = Request::get('property_id');
                $bill->fill(Request::all());
                $bill->activated = Request::get('activated')?true:false;
                

                if(Request::get('property_bank_id') == "new") {
                    $bank = new Bank;
                    $bank->fill(Request::all());
                    $bank->property_id = Request::get('property_id');
                    $bank->is_fund_account = (Request::get('is_fund_account'))?true:false;
                    $bank->save();
                    $bill->property_bank_id = $bank->id;
                }
                
                $bill->save();

                if( $flag_log ) {
                    $smart_bill_log['new'] = $bill->toArray();
                    $bill_log = new SmartBillLog;
                    $bill_log->smart_bill_payment_setting_id = Request::get('id');
                    $bill_log->by_user = Auth::user()->id;
                    $bill_log->content_log = json_encode($smart_bill_log);
                    $bill_log->save();
                }

                echo "saved";
            }else {
                echo "error";
            }
        }catch(Exception $ex){
            echo "error";
        }
    }

   public function LogList (){
        if (Request::isMethod('post')) {
            if( Request::get('id') ) {
                $log_list = SmartBillLog::with('users')->where('smart_bill_payment_setting_id','=',Request::get('id'))->orderBy('created_at', 'DESC')->get();  
            } else {
                $log_list = null;
            }
           return view('property.smart-bill-log-list')->with(compact('log_list'));
       }
   }


    public function smartlog (){

        $log = SmartBillLog::find(Request::get('id'));

        if($log == null){
            $content_log = false;
        }else{
            $content_log = json_decode($log->content_log); 
        }
         return view('property.smart-bill-view-log')->with(compact('content_log'));
    }





    public function getPropertyFeature () {
        if(Request::ajax()) {
            $feature = PropertyFeature::where('property_id','=',Request::get('pid'))->first();
            $property = Property::find(Request::get('pid'));
            return view('property.property-feature-form-edit')->with(compact('feature','property'));
        }
    }

    public function getUserPropertyFeature () {
        if(Request::ajax()) {
            $feature = UserPropertyFeature::where('property_id','=',Request::get('pid'))->first();
            // dd($feature);
            $property = Property::find(Request::get('pid'));
            return view('property.user-property-feature-form-edit')->with(compact('feature','property'));
        }
    }

    public function editUserPropertyFeature() {
        try{
            if (Request::isMethod('post')) {
                if(Request::get('id') != null) {
                    $feature = UserPropertyFeature::find(Request::get('id'));
                }else{
                    $feature = new UserPropertyFeature;
                }
                $feature->property_id = Request::get('property_id');
                $feature->menu_finance_group = Request::get('menu_finance_group') ? true : false;
                // $feature->menu_committee_room = Request::get('menu_committee_room') ? true : false;
                // $feature->menu_event = Request::get('menu_event') ? true : false;
                // $feature->menu_vote = Request::get('menu_vote') ? true : false;
                // $feature->menu_tenant = Request::get('menu_tenant') ? true : false;
                // $feature->menu_vehicle = Request::get('menu_vehicle') ? true : false;
                // $feature->menu_prepaid = Request::get('menu_prepaid') ? true : false;
                // $feature->menu_revenue_record = Request::get('menu_revenue_record') ? true : false;
                // $feature->menu_retroactive_receipt = Request::get('menu_retroactive_receipt') ? true : false;
                // $feature->menu_common_fee = Request::get('menu_common_fee') ? true : false;
                // $feature->menu_cash_on_hand = Request::get('menu_cash_on_hand') ? true : false;
                // $feature->menu_pettycash = Request::get('menu_pettycash') ? true : false;
                // $feature->menu_fund = Request::get('menu_fund') ? true : false;
                // $feature->menu_complain = Request::get('menu_complain') ? true : false;
                // $feature->menu_parcel = Request::get('menu_parcel') ? true : false;
                // $feature->menu_message = Request::get('menu_message') ? true : false;
                // $feature->menu_complain = Request::get('menu_complain') ? true : false;
                // dd( $feature->property_id);
                $feature->save();
                echo "saved";
            }else {
                echo "error";
            }
        }catch(Exception $ex){
            echo "error";
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
                $feature->smart_bill_payment = Request::get('smart_bill_payment') ? true : false;
                $feature->menu_utility = Request::get('menu_utility') ? true : false;
                $feature->market_place_singha = Request::get('market_place_singha') ? true : false;
                $feature->menu_beneat = Request::get('menu_beneat') ? true : false;
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
            return view('property.property-initial-meter-form-edit')->with(compact('property'));
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
            return view('property_contract_sign.contract-list-detail')->with(compact('prop'));
        }
    }

    function addContract () {
        $property_sign = new PropertyContract;
        $property_sign->fill(Request::all());
        $property_sign->property_id = Request::get('property_id');
        $property_sign->save();
        return redirect('root/admin/property/list');
    }

    function editContract () {
        if(Request::ajax()) {
            $sign = PropertyContract::find(Request::get('tid'));
            $sign->contract_date 		= date('Y/m/d', strtotime($sign->contract_date));
            $sign->contract_end_date 	= date('Y/m/d', strtotime($sign->contract_end_date));
            $sign->info_delivery_date 	= date('Y/m/d', strtotime($sign->info_delivery_date));
            return view('property_contract_sign.contract-edit-form')->with(compact('sign'));
        } else {
            $sign = PropertyContract::find(Request::get('id'));
            $sign->fill(Request::all());
            $sign->save();
            return redirect('root/admin/property/list');
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
            // dd(Request::all());
            $property = Property::find(Request::get('property_id'));
            if($property) {
                $lines = explode(PHP_EOL, Request::get('units'));
              
                $array_prop = array();
                foreach ($lines as $line) {
                    $array_prop[] = str_getcsv($line);
                }
                $result = $this->checkUnitFormat($array_prop);
                // dd($array_prop);
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
                            'sub_type'   => (empty($unit[17]))? '': $unit[17],
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
                            'type'   => (empty($unit[16]))? 1: $unit[16],
                            'sub_type'   => (empty($unit[17]))? '': $unit[17],
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
                // dd($array_prop);
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
                        $property_unit->sub_type = (empty($unit[18]))?'': $unit[18];
                        $property_unit->waste_water_treatment = (empty($unit[19]))? 0 : $unit[19];

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
                if(count($unit) != 18) {
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
                if(count($unit) != 20) {
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

}
