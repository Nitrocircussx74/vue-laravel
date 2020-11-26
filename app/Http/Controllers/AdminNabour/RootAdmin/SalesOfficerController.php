<?php namespace App\Http\Controllers\AdminNabour\RootAdmin;

use App\Http\Controllers\AdminNabour\Officer\AccountController;
use Request;
use Illuminate\Routing\Controller;
use Auth;
use Redirect;
use App\Http\Controllers\AdminNabour\PushNotificationController;

# Model
use DB;
use App\PropertyMember;
use App\PropertyUnit;
use App\BackendModel\User as BackendUser;
use App\User;
use App\Province;
use App\Property;
use App\SalePropertyDemo;
use App\UserPropertyFeature;
use App\PropertyFeature;
use Validator;

class SalesOfficerController extends Controller {

    public function __construct () {
        $this->middleware('admin');
    }

    public function salesList() {
        $officer = [];
        $officers = BackendUser::where('id','!=',Auth::user()->id)
            ->where('role','=',4)
            ->orderBy('created_at','DESC')
            ->paginate(30);

        return view('Backend.sales.view-sales-list')->with(compact('officers','officer'));
    }

    public function addSales() {
        if (Request::isMethod('post')) {
            $officer = Request::all();

            $validator = Validator::make($officer, [
                'name' => 'required|max:255',
                'email' => 'unique:back_office.users',
                'password' => 'alpha_num|min:6|required',
                'password_confirm' => 'alpha_num|min:6|required|same:password'
            ]);

            if ($validator->fails()) {
                return view('Backend.sales.sales-form')->withErrors($validator)->with(compact('officer'));
            }else {
                $this->createAccount($officer['name'], $officer['email'], $officer['phone'], bcrypt($officer['password']));
                echo "saved";
            }
        }
    }

    public function createAccount($name,$email,$phone,$password){
        try{
            $user = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'role' => 2
            ];

            $officer_id = $this->createUserSales($user);
            for ($i = 1; $i <= 20; $i++) {
                $default_password = $this->generatePassword();
                $property_id = $this->createProperty($default_password);
                $this->createSaleDemoProperty($officer_id, $property_id, $default_password);
            }

            return false;

        }catch(Exception $ex){
            return false;
        }
    }

    function createUserSales($user){
        try {
            $user_create = BackendUser::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'password' => $user['password'],
                'role' => 2
            ]);
            return $user_create->id;

        }catch(Exception $ex){
            return false;
        }
    }

    function createProperty($default_password){
        try {
            $last_property = Property::where('is_demo', '=', 'true')->orderBy('prefix_code', 'desc')->first();
            if(isset($last_property)) {
                $prefix = $last_property->toArray()['prefix_code'];

            }else{
                $prefix = 0;
            }

            $next_prefix = "00" . strval($prefix + 1);
            $name_new = "NB" . substr($next_prefix, -3, 3);

            $new_property = new Property();
            $new_property->property_name_th = "หมู่บ้าน " . $name_new;
            $new_property->property_name_en = "Demo " . $name_new;
            $new_property->juristic_person_name_th = "หมู่บ้าน " . $name_new;
            $new_property->juristic_person_name_en = "Demo " . $name_new;
            $new_property->area_size = "300";
            $new_property->unit_size = 20;
            $new_property->construction_by = "Nabour Construction";
            $new_property->address_th = "ต.สุเทพ อ.เมือง";
            $new_property->street_th = "ถนนศิริมังคลาจารย์ ซอย 7";
            $new_property->province = 50;
            $new_property->postcode = "50200";
            $new_property->lat = "18.795263983660067";
            $new_property->lng = "98.97235203995058";
            $new_property->address_en = "T.Suthep A.Mueang";
            $new_property->street_en = "Siri Mangkalajarn Road Soi 7";
            $new_property->property_type = "1";
            $new_property->address_no = "17/1";
            $new_property->prefix_code = $prefix + 1;
            $new_property->is_demo = true;
            $new_property->save();

            // Property Feature Setup
            $new_feature = new PropertyFeature;
            $new_feature->property_id = $new_property->id;
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
            $new_feature->save();

            // User Property Menu Feature
            $user_feature = new UserPropertyFeature;
            $user_feature->property_id = $new_property->id;
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

            $this->createPropertyUnit($new_property->id,$name_new,$default_password);
            return $new_property->id;
        }catch(Exception $ex){
            return null;
        }
    }

    function createPropertyUnit($property_id, $property_name_new,$default_password){
        try{
            // Create Admin User
            $user_create = User::create([
                'name' => "ผู้จัดการนิติ " . $property_name_new,
                'email' => "admin_".strtolower($property_name_new)."@nabour.me",
                'password' => bcrypt($default_password),
                'property_id' => $property_id,
                'role' => 1
            ]);

            // Create Property Unit
            for ($i = 1; $i <= 4; $i++) {
                $unit = new PropertyUnit;
                $unit->property_id = $property_id;
                $unit->property_size = 150;
                $unit->unit_number = "17/".$i;
                $unit->is_land = false;
                $unit->save();

                $unit_id = $unit->id;

                // Create Member User
                if($i == 1){
                    $this->createCommitteeUser($property_id, $unit_id, $default_password, $property_name_new);
                    //$this->createMemberUser($i, $property_id, $unit_id, $default_password, $property_name_new);
                }else {
                    $this->createMemberUser($i, $property_id, $unit_id, $default_password, $property_name_new);
                }
            }
        }catch(Exception $ex){
            return null;
        }
    }

    function createCommitteeUser($property_id,$property_unit_id, $default_password, $property_name_new){
        try{
            // Committee Member
            $user_create = User::create([
                'name' => "กรรมการนิติ " . $property_name_new,
                'email' => "com_".strtolower($property_name_new)."@nabour.me",
                'password' => bcrypt($default_password),
                'property_id' => $property_id,
                'property_unit_id' => $property_unit_id,
                'role' => 2,
                'is_chief' => true
            ]);
        }catch (Exception $ex){
            return null;
        }
    }

    function createMemberUser($i, $property_id, $property_unit_id, $default_password, $property_name_new){
        try{
            // Member
            $user_create = User::create([
                'name' => "ลูกบ้านคนที่".$i." ". $property_name_new,
                'email' => "user".$i."_".strtolower($property_name_new)."@nabour.me",
                'password' => bcrypt($default_password),
                'property_id' => $property_id,
                'property_unit_id' => $property_unit_id,
                'role' => 2
            ]);

            // Update PropertyUnit Data (Owner name PropertyUnit)
            $property_unit = PropertyUnit::find($property_unit_id);
            $property_unit->is_land = 0;
            $property_unit->owner_name_th = "ลูกบ้านคนที่".$i." ". $property_name_new;
            $property_unit->owner_name_en = "user".$i." ". $property_name_new;
            $property_unit->save();

        }catch (Exception $ex){
            return null;
        }
    }

    function createSaleDemoProperty($officer_id, $property_id, $default_password){
        //echo $property_id;
        $user_create = SalePropertyDemo::create([
            'sale_id' => $officer_id,
            'property_id' => $property_id,
            'default_password' => $default_password
        ]);
    }

    public function changeStatusSales(){
        if ( Request::isMethod('post') ) {
            $app_key = Request::get('app_key');
            if($app_key == $_ENV['APP_KEY']) {
                $user = Request::get('user');
                $officer = BackendUser::where('email',$user['email'])->first();
                if($officer) {
                    $officer->active = ($user['status'] == "1") ? true : false;
                    $officer->save();
                }
            }
        }
    }

    public function viewSales () {
        if(Request::ajax()) {
            $member = BackendUser::find(Request::get('uid'));
            return view('Backend.sales.view-sales')->with(compact('member'));
        }
    }

    public function getSales () {
        if(Request::ajax()) {
            $officer = BackendUser::find(Request::get('uid'));
            return view('Backend.sales.sales-form-edit')->with(compact('officer'));
        }
    }

    public function setActive () {
        if(Request::ajax()) {
            $user = BackendUser::find(Request::get('uid'));
            if($user) {
                $user->active = Request::get('status');
                $user->save();

                return response()->json(['result'=>true]);
            }
        }
    }

    public function editSales() {
        if (Request::isMethod('post')) {
            $officer = BackendUser::find(Request::get('id'));
            $request = Request::except('email');
            $rules = ['name' => 'required|max:255'];
            if(!empty($request['password'])) {
                $rules += [
                    'password' => 'alpha_num|min:6|required',
                    'password_confirm' => 'alpha_num|min:6|required|same:password'
                ];
            }
            $validator = Validator::make($request, $rules);
            $officer->fill(Request::except(['email','id']));
            
            if ($validator->fails()) {
                $officer->fill(Request::except(['email','id']));
                return view('Backend.sales.officer-form-edit')->withErrors($vu)->with(compact('officer'));
            }else {
                $officer->name = $request['name'];
                if(!empty($request['password'])) {
                    $officer->password = bcrypt($request['password']);
                }
                $officer->save();
                echo "saved";
            }
        }
    }

    public function deleteSales(){
        try{
            if(Request::ajax()) {
                $user = BackendUser::find(Request::get('uid'));
                if($user) {
                    $email = $user->email;
                    $this->deleteSalesAccount($email);

                    $user->delete();
                    return response()->json(['result'=>true]);
                }
            }else{
                return response()->json(['result'=>false]);
            }
        }catch(Exception $ex){
            return response()->json(['result'=>false]);
        }
    }

    public function deleteSalesAccount($email){
        try{
            $account = new AccountController();
            $officer = BackendUser::where('email',$email)->first();
            if($officer) {
                $sale_id = $officer->id;
                $list_property = SalePropertyDemo::with('property')->where('sale_id','=',$sale_id)->get();
                foreach ($list_property as $item){
                    $property_id = $item->property_id;

                    $account->clearUserAccountForDemo($property_id);
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
                    $account->clearPayee($property_id);
                    $account->clearVehicle($property_id);
                    $account->deleteUser($property_id);
                    $account->deleteProperty($property_id);
                }

                $officer->delete();
            }

            return true;
        }catch(Exception $ex){
            return false;
        }
    }

    public function getSubDomain(){
        $url_array  = explode('.', parse_url(Request::root(), PHP_URL_HOST));
        $sub_domain = $subdomain = $url_array[0];

        return $sub_domain;
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
}
