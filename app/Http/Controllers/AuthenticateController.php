<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use JWTAuth;
use Auth;
use DB;
use DateTime;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;
use App\Installation;
use App\User;
use App\SalePropertyDemo;
class AuthenticateController extends Controller
{
    protected $user;

    public function __construct(JWTAuth $auth)
    {
        //$this->user = $auth->toUser();
    }
    public function index()
    {
        // TODO: show users
    }

    public function authenticate(Request $request)
    {
        $device_token = $request->get('device_token');
        $device_type = $request->get('device_type');
        $device_uuid = $request->get('device_uuid');
        $email = strtolower($request->get('email'));
        $password = $request->get('password');

        $isFirst = false;
        //$credentials = $request->only('email','password');

        // Verify code in First time
        $user = User::where('email','=',$email)->first();
        if( !$user ) {
            return response()->json(['error' => 'user_not_found'], 401);
        } else {
            if( $user->verification_code !== null){
                if($user->verification_code == $password){
                    $isFirst = true;
                    $user->verification_code = null;
                    $user->password = bcrypt($password);
                    $user->save();
                }
            }
        }

        $credentials = array(
            "email" => $email,
            "password" => $password
        );

        if($request->get('remember_me') == "true"){
            $timestamp = Carbon::now()->addYear(5)->timestamp; // Session 5 years on mobile (Remember me)
            $customClaims = ['exp' => $timestamp];
        }else{
            //$timestamp = Carbon::now()->addDays(7)->timestamp;
            $timestamp = Carbon::now()->addYear()->timestamp; // Session 1 years on mobile
            $customClaims = ['exp' => $timestamp];
        }

        //$user = User::where('email','=',$request->get('email'))->first();

        // Disable login when disable Property demo
        if(isset($user)){
            $sale_property_demo = SalePropertyDemo::where('property_id','=',$user->property_id)->first();

            if(isset($sale_property_demo) && $sale_property_demo->status == 3){
                return response()->json(['error' => 'could_not_create_token'], 500);
            }

            if($user->expire_trial != null){
                $expire_trial = Carbon::createFromFormat('Y-m-d H:i:s', $user->expire_trial);
                $datetime_now = Carbon::now();
                $result_cal_trial = $datetime_now->diffInDays($expire_trial,false);

                if($result_cal_trial>=0){
                    $auth = true;
                }else{
                    return response()->json(['error' => 'could_not_create_token'], 500);
                }
            }
        }

        if(isset($user) && $user->role == 1){
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        if(isset($user) && $user->role == 3){
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        if(isset($user) && $user->role == 4){
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        if(isset($user) && $user->role == 5){
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        if(isset($user) && $user->active == false){
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        try {
            // verify the credentials and create a token for the user
            if (! $token = JWTAuth::attempt($credentials,$customClaims)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        }
        // if no errors are encountered we can return a JWT

        $device_status = $this->addDeviceInstallation($device_token,$device_uuid,$device_type);

        if($device_status) {
            $results = [
                'token' => $token,
                'isFirst' => $isFirst
            ];
            return response()->json($results);
        }else{
            return response()->json(['error' => 'could_not_create_token'], 500);
        }
    }
    public function logoutApi(Request $request) {
        try {
            $this->validate($request, [
                'token' => 'required'
            ]);

            $device_token = $request->input('device_token');
            $device_uuid = $request->input('device_uuid');

            if(isset($device_uuid) && isset($device_token)){
                $this->deleteDeviceInstallation($device_token,$device_uuid);
            }else{
                if(isset($device_token)) {
                    $this->deleteDeviceInstallationByTokenId($device_token);
                }
            }

            JWTAuth::invalidate($request->input('token'));
            
            return response()->json(['success'=>'true']);
        } catch(JWTException $e){
            return response()->json(['success'=>'false']);
        }
    }

    public function addDeviceInstallation ($device_token,$device_uuid,$device_type) {
        try {
            $countDevice = Installation::where('device_uuid', $device_uuid)->count();
            if ($countDevice > 0) {
                $this->deleteDeviceInstallationByUuid($device_uuid);
            }

            $this->deleteDeviceInstallationByTokenId($device_token);

            $device = new Installation();
            $device->user_id = Auth::user()->id;
            $device->device_token = $device_token;
            $device->device_type = $device_type;
            $device->device_uuid = $device_uuid;
            $device->save();

            return true;

        } catch(Exception $ex){
            return false;
        }
    }

    public function deleteDeviceInstallationByTokenId ($device_token) {
        try {
            $device = Installation::where('device_token', $device_token);
            if (isset($device)) {
                $device->delete();
                return true;
            }else{
                return false;
            }
        } catch(Exception $ex){
            return false;
        }
    }

    public function deleteDeviceInstallationByUuid ($device_uuid) {
        try {
            $device = Installation::where('device_uuid', $device_uuid);
            if (isset($device)) {
                $device->delete();
                return true;
            }else{
                return false;
            }
        } catch(Exception $ex){
            return false;
        }
    }

    public function deleteDeviceInstallation ($device_token,$device_uuid) {
        try {
            $device = Installation::where('device_token', $device_token)->where('device_uuid',$device_uuid);

            if ($device->count() > 0) {
                $device->delete();
                return true;
            }else{
                return false;
            }
        } catch(Exception $ex){
            return false;
        }
    }
}
