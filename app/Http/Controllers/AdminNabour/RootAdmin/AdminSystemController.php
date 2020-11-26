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
use App\BackendModel\User as BackendUser;
use Validator;


class AdminSystemController extends Controller {

    public function __construct () {
		$this->middleware('auth',['except' => ['login']]);
		if( Auth::check() && Auth::user()->role !== 0 ) {
			Redirect::to('feed')->send();
		}
	}

    public function adminList() {
        $officer = [];
        $officers = BackendUser::where('id','!=',Auth::user()->id)
            ->where('role','=',1)
            //
            ->orderBy('active','DESC')
            ->orderBy('created_at','DESC')
            ->paginate(30);

        return view('Backend.admin.view-officer-list')->with(compact('officers','officer'));
    }

    public function addAdmin() {
        if (Request::isMethod('post')) {
            $officer = Request::all();

            $validator = Validator::make($officer, [
                'name' => 'required|max:255',
                'email' => 'unique:back_office.users',
                'password' => 'alpha_num|min:6|required',
                'password_confirm' => 'alpha_num|min:6|required|same:password'
            ]);

            if ($validator->fails()) {
                return view('Backend.admin.officer-form')->withErrors($validator)->with(compact('officer'));
            } else {

                $this->createAccount($officer['name'], $officer['email'], $officer['phone'], bcrypt($officer['password']));
                echo "saved";
            }
        }
    }

    public function createAccount($name,$email,$phone,$password){
        try{
            $user_create = BackendUser::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'role' => 1 // Admin System role
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
                $officer = BackendUser::where('email',$user['email'])->first();
                if($officer) {
                    $officer->active = ($user['status'] == "1") ? true : false;
                    $officer->save();
                }
            }
        }
    }

    public function viewAdmin () {
        if(Request::ajax()) {
            $member = BackendUser::find(Request::get('uid'));
            return view('Backend.admin.view-officer')->with(compact('member'));
        }
    }

    public function getAdmin () {
        if(Request::ajax()) {
            $officer = BackendUser::find(Request::get('uid'));
            return view('Backend.admin.officer-form-edit')->with(compact('officer'));
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

    public function editAdmin() {
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
                return view('Backend.admin.officer-form-edit')->withErrors($validator)->with(compact('officer'));
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

    public function deleteAdmin(){
        try{
            if(Request::ajax()) {
                $user = BackendUser::find(Request::get('uid'));
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
