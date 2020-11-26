<?php
namespace App\Http\Controllers\API\Partner;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use JWTAuth;
use Auth;
use DB;
use DateTime;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;
use App\User;

class AuthenticateController extends Controller
{
    protected $user;

    public function __construct(JWTAuth $auth) {
       
    }

    public function authenticate(Request $request)  {
        $email = strtolower($request->get('username'));
        $password = $request->get('password');
        //return \bcrypt($password);
        $credentials = array(
            "email" => $email,
            "password" => $password
        );

        $timestamp = Carbon::now()->addMinutes(10)->timestamp;
        $customClaims = ['exp' => $timestamp];
       
        $user = User::where('email','=',$request->get('username'))->first();

        if(isset($user) && $user->role != 99){
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        try {
            // verify the credentials and create a token for the user
            if (! $token = JWTAuth::attempt($credentials,$customClaims)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }

            $results = [
                'token' => $token,
                'expired' => $timestamp
            ];
            return response()->json($results);
            
        } catch (JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        }
        // if no errors are encountered we can return a JWT
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
}
