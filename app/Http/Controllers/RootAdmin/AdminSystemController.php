<?php namespace App\Http\Controllers\RootAdmin;
use App\Http\Controllers\Officer\AccountController;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use Redirect;
use App\Http\Controllers\PushNotificationController;
# Model
use DB;
use App\PropertyMember;
use App\PropertyUnit;
use App\User;
use App\Province;
use App\Property;
use App\SalePropertyDemo;
use App\PropertyFeature;
# Test Pubnub
use Pubnub\Pubnub;
use GuzzleHttp\Client;

class AdminSystemController extends Controller {

    public function __construct () {
        $this->middleware('auth');
        //view()->share('active_menu','members');
        if( Auth::check() && Auth::user()->role !== 0 ) {
                Redirect::to('feed')->send();
        }
    }

    public function adminList() {
        $officer = [];
        $officers = User::where('id','!=',Auth::user()->id)
            ->whereIn('role',[5,7])
            //
            ->orderBy('active','DESC')
            ->orderBy('created_at','DESC')
            ->paginate(30);

        return view('admin.view-officer-list')->with(compact('officers','officer'));
    }

    public function addAdmin() {
        if (Request::isMethod('post')) {
            $officer = Request::all();

            $new_officer = new User();
            unset($new_officer->rules['fname']);
            unset($new_officer->rules['lname']);
            $new_officer->rules['name'] = 'required';
            $officer['email'] = strtolower(trim($officer['email']));
            $vu = $new_officer->validate($officer);

            if($vu->fails()){
                return view('admin.officer-form')->withErrors($vu)->with(compact('officer'));
            }else {
                $this->createAccount($officer['name'], $officer['email'], $officer['phone'], bcrypt($officer['password']),$officer['role']);
                echo "saved";
            }
        }
    }

    public function createAccount($name,$email,$phone,$password,$role = 5){
        try{
            $user_create = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'role' => $role
            ]);

            return true;

        }catch(Exception $ex){
            return false;
        }
    }

    public function changeStatusAdmin(){
        if ( Request::isMethod('post') ) {
            $app_key = Request::get('app_key');
            if($app_key == $_ENV['APP_KEY']) {
                $user = Request::get('user');
                $officer = User::where('email',$user['email'])->first();
                if($officer) {
                    $officer->active = ($user['status'] == "1") ? true : false;
                    $officer->save();
                }
            }
        }
    }

    public function viewAdmin () {
        if(Request::ajax()) {
            $member = User::find(Request::get('uid'));
            return view('admin.view-officer')->with(compact('member'));
        }
    }

    public function getAdmin () {
        if(Request::ajax()) {
            $officer = User::find(Request::get('uid'));
            return view('admin.officer-form-edit')->with(compact('officer'));
        }
    }

    public function setActive () {
        if(Request::ajax()) {
            $user = User::find(Request::get('uid'));
            if($user) {
                $user->active = Request::get('status');
                $user->save();

                return response()->json(['result'=>true]);
            }
        }
    }

    public function editAdmin() {
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
                return view('admin.officer-form-edit')->withErrors($vu)->with(compact('officer'));
            }else {
                $officer->name = $request['name'];
                $officer->role = Request::get('role');
                if(!empty($request['password'])) {
                    $officer->password = bcrypt($request['password']);
                }
                $officer->save();
                echo "saved";
            }
        }
    }

    public function deleteAdmin(){
        try{
            if(Request::ajax()) {
                $user = User::find(Request::get('uid'));
                if($user) {
                    //$email = $user->email;
                    //$this->deleteOfficerAccount($email);

                    //$user->delete();
                    $user->active = false;
                    $user->save();
                    return response()->json(['result'=>true]);
                }
            }else{
                return response()->json(['result'=>false]);
            }
        }catch(Exception $ex){
            return response()->json(['result'=>false]);
        }
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
