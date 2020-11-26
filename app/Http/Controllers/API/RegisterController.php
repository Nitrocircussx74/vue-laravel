<?php namespace App\Http\Controllers\API;
use Request;
use Auth;
use Mail;
use Hash;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Foundation\Bus\DispatchesJobs;
use DB;
# Model
use App\User;
use App\Property;
use App\PropertyUnit;
use App\RequestedProperty;
use App\Province;
use JWTAuth;
use App\Installation;

use GuzzleHttp\Exception\RequestException;

# Jobs
use App\Jobs\SendEmailUser;
class RegisterController extends Controller {

    use DispatchesJobs;

    public function __construct () {

    }

    public function getProvinceList(){
        $province = Province::all();
        $results = $province->toArray();

        return response()->json($results);
    }

    public function getPropertylist () {
        if (Request::isMethod('post')) {
            $pid = Request::get('province');
            $props = Property::where('province','=',$pid)->where('is_demo', '=', 'false')->get();

            return response()->json($props);
        }
    }

    function getPropertyAddresslist () {
        if (Request::isMethod('post')) {
            $prop_id = Request::get('prop_id');
            $prop_ = Property::find($prop_id)->toArray();
            $unos = PropertyUnit::where('property_id','=',$prop_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->get();

            $results = [
                'property' => $prop_,
                'property_unit' => $unos
            ];

            return response()->json($results);
        }
    }

    public function signup(){
        if (Request::isMethod('post')) {
            $regis_data = Request::all();
            $user = [
                'name' => $regis_data['name'],
                'email' => strtolower($regis_data['email'])
            ];

            //$new_propu = new PropertyUnit();
            //$vpu = $new_propu->validate($user);
            $new_user = new User();
            unset($new_user->rules['fname']);
            unset($new_user->rules['lname']);
            unset($new_user->rules['password']);
            unset($new_user->rules['password_confirm']);
            $vu = $new_user->validate($user);

            if($vu->passes()) {
                //save to user
                $code = $this->generateCode();
                $count = User::where('verification_code', '=', $code)->count(); // 1
                while($count > 0) {
                    $code = $this->generateCode();
                    $count = User::where('verification_code', '=', $code)->count();
                }
                //Save and send verify code to email
                User::create([
                    'name' => $user['name'],
                    'email' => $user['email'],
                    //'gender' => $regis_data['gender'],
                    'property_id' => $regis_data['property_id'],
                    'property_unit_id' => $regis_data['property_unit_id'],
                    'role' => 2,
                    'lang' => $regis_data['language'],
                    'verification_code' => $code,
                    'is_subscribed_newsletter' => (isset($regis_data['is_subscribed_newsletter']) && $regis_data['is_subscribed_newsletter'] === true) ? true : false
                ]);

                $this->dispatch(new SendEmailUser($user['name'],trans('messages.Email.thanks_signup'),$user['email']));
                return response()->json(['success' => true]);
            } else{
                $error_msg = $vu->messages()->toArray();
                $results = [
                    'status' => false,
                    'msg' => $error_msg
                ];
                return response()->json($results);
            }
        } else{
            return response()->json(['success' => false]);
        }
    }

    public function requestProperty(){
        try{
            $req_data = Request::all();
            $new_request = new RequestedProperty();
            unset($new_request->rules['fname']);
            unset($new_request->rules['lname']);
            unset($new_request->rules['email']);
            $vpu = $new_request->validate($req_data);

            if($vpu->passes()) {
                $new_p = new RequestedProperty();
                $new_p->name = $req_data['name'];
                $new_p->email = $req_data['email'];
                $new_p->province = $req_data['province'];
                $new_p->property_name = $req_data['new_property_name'];
                $new_p->save();
                //return view('home.success_request');
                Request::session()->put('success.request', true);
                $status = true;
                return response()->json(['success' =>$status]);
            }else{
                $status = false;
                $error_msg = $vpu->messages()->toArray();
                $results = [
                    'status' => $status,
                    'msg' => $error_msg
                ];
                return response()->json($results);
            }
        } catch(Exception $ex){
            $status = false;
            return response()->json($status);
        }
    }

    function generateCode() {
        $chars = "abcdefghijkmnpqrstuvwxyz123456789";
        srand((double)microtime()*1000000);
        $i = 0;
        $pass = '' ;
        while ($i < 4) {
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

    public function verify(){
        try {
            $vcode = Request::get('code');
            $email = Request::get('email');
            $user = User::where('email', '=', $email)->where('verification_code', '=', $vcode)->first();
            if (!empty($user)) {
                $status = true;
                $user->verification_code = null;
                $user->save();
                $results = [
                    'status' => $status,
                    'user' => $user->toArray()
                ];
                return response()->json($results);
            } else {
                $status = false;
                $results = [
                    'status' => $status
                ];
                return response()->json($results);
            }
        }catch(Exception $ex){
            $status = false;
            return response()->json($status);
        }
    }

    function setPassword() {
        try{
            //$vcode 		= Request::get('code');
            $email 		= Request::get('email');
            $user 		= User::where('email', '=', $email)->where('verification_code', '=', null)->first();
            if(!empty($user)) {
                $user_valid = new User();
                unset($user_valid->rules['fname']);
                unset($user_valid->rules['lname']);
                unset($user_valid->rules['name']);
                unset($user_valid->rules['email']);
                $vu = $user_valid->validate(Request::all());
                if($vu->passes()) {
                    $user->password = bcrypt(Request::get('password'));
                    //$user->verification_code = null;
                    $user->save();
                    //dd('Congratulation!!! You are Verified. Please Login');
                    Request::session()->put('success.verify', true);
                    $status = true;
                    $results = [
                        'status' => $status
                    ];
                    return response()->json($results);
                } else {
                    $status = false;
                    $error_msg = $vu->messages()->toArray();
                    $results = [
                        'status' => $status,
                        'msg' => $error_msg
                    ];
                    return response()->json($results);
                }
            } else {
                // Wrong Code!!
                $status = false;
                $results = [
                    'status' => $status
                ];
                return response()->json($results);
            }
        } catch(Exception $ex){
            $status = false;
            return response()->json($status);
        }
    }

    function verifyAPI() {
        if (Request::isMethod('get')) {
            $vcode 		= Request::get('code');
            $user 		= User::where('verification_code', '=', $vcode)->first();
            if(!empty($user)) {
                echo json_encode(array('status' => '1'));
            } else {
                echo json_encode(array('status' => '0'));
            }
        }
    }

    function checkEmail() {
        $user = User::where('email','=',Request::get('email'))->first();
        $status = false;
        if(isset($user)){
            if($user->verification_code == null){
                if($user->password != null){
                    $status = 0;
                    $message = "email verified";
                }else {
                    $status = 1;
                    $message = "set password not yet";
                }
            }else{
                $status = 2;
                $message = "email verify not yet";
            }
        }else{
            $status = 3;
            $message = "email not use in system";
        }

        $results = [
            'status' => $status,
            'msg' => $message
        ];
        return response()->json($results);
    }

    public function getPropertyUnitFromInviteCode(){
        try {
            $invite_code = Request::get('invite_code');
            $property_unit = PropertyUnit::with('property')->where('invite_code', '=', $invite_code)->first();
            if (!empty($property_unit)) {
                $status = true;
                $results = [
                    'status' => $status,
                    'property_unit' => $property_unit->toArray()
                ];
                return response()->json($results);
            } else {
                $status = false;
                $results = [
                    'status' => $status
                ];
                return response()->json($results);
            }
        }catch(Exception $ex){
            $status = false;
            return response()->json($status);
        }
    }

    public function signUpByInviteCode(){
        try {
            $regis_data = Request::all();
            $user = [
                'name' => $regis_data['name'],
                'email' => strtolower($regis_data['email'])
            ];

            $new_user = new User();
            unset($new_user->rules['fname']);
            unset($new_user->rules['lname']);
            unset($new_user->rules['password']);
            unset($new_user->rules['password_confirm']);
            $vu = $new_user->validate($user);

            if($vu->passes()) {

                //Get Property & PropertyUnit Data
                $invite_code = Request::get('invite_code');
                $property_unit = PropertyUnit::with('property')->where('invite_code', '=', $invite_code)->first();

                if (!empty($property_unit)) {
                    //Save New User
                    User::create([
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'property_id' => $property_unit->property->id,
                        'property_unit_id' => $property_unit->id,
                        'role' => 2,
                        'lang' => $regis_data['language'],
                        'password' => Hash::make($regis_data['password']),
                        'is_subscribed_newsletter' => (isset($regis_data['is_subscribed_newsletter']) && $regis_data['is_subscribed_newsletter'] === true) ? true : false
                    ]);

                    // generate new InviteCode for PropertyUnit (Disable Generate)
                    //$propUnit = PropertyUnit::find($property_unit->id);
                    //$propUnit->invite_code = $this->generateInviteCode();
                    //$propUnit->save();

                    //$this->dispatch(new SendEmailUser($user['name'],trans('messages.Email.thanks_signup'),$user['email']));
                    return response()->json(['success' => true]);
                }else{
                    $error_msg = "Wrong Invite Code";
                    $results = [
                        'status' => false,
                        'msg' => $error_msg
                    ];
                    return response()->json($results);
                }
            } else{
                $error_msg = $vu->messages()->toArray();
                $results = [
                    'status' => false,
                    'msg' => $error_msg
                ];
                return response()->json($results);
            }
        }catch(Exception $ex){
            $status = false;
            return response()->json($status);
        }
    }

    /* Authentication with Line account */
    public function authWithLine () {
        $l_token_id = Request::get('line_token_id');
        $l_user_id  = Request::get('line_user_id');

        /// Register code + login code here
        try {
            $headers = [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ];
            $params = [
                'id_token' => $l_token_id,
                'client_id' => '1653838027',
                //'nonce' => '',
                'user_id' => $l_user_id
            ];
    
            $client = new \GuzzleHttp\Client([
                'headers' => $headers
            ]);

            $res_profile = $client->request('POST', 'https://api.line.me/oauth2/v2.1/verify', [
                'form_params' => $params
            ]);
            $res = json_decode((string) $res_profile->getBody());

            $result_regis = $this->signUpLoginLine($res->name,$res->email,$l_user_id);

            $results = [
                'status' => true,
                'msg' => 'success',
                'email' => $res->email
            ];

            $results += $result_regis;

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $result = json_decode((string) $response->getBody());
            $status = false;
            $msg = $result->error_description;
            $results = [
                'status' => false,
                'msg' => $msg
            ];
        }

        return response()->json($results);
    }

    function signUpLoginLine ($name,$email,$line_user_id) {
        $user = User::where('email',$email)->first();
        if($user) {
            if( $user->property_unit_id ) {
                $r['regis_state'] = 'verified';
                $r['token'] = $token = JWTAuth::fromUser($user);

            } else {
                $r['regis_state'] = 'need_property_unit_verify';
                $r['token'] = null;
            }
            $user->line_user_id = $line_user_id;
            $user->save();
        } else {
            $r['regis_state'] = 'need_property_unit_verify';
            $r['token'] = null;
            $user = [
                'name' => $name,
                'email' => strtolower($email)
            ];
            $new_user = new User();
            unset($new_user->rules['fname']);
            unset($new_user->rules['lname']);
            unset($new_user->rules['password']);
            unset($new_user->rules['password_confirm']);
            $vu = $new_user->validate($user);
    
            if($vu->passes()) {
                //Save and send verify code to email
                $user = User::create([
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => 2,
                    'lang' => 'th'
                ]);
                $user->line_user_id = $line_user_id;
                $user->save();
                //$this->dispatch(new SendEmailUser($user['name'],trans('messages.Email.thanks_signup'),$user['email']));
            }
        }
        // Add installation what ever
        $this->addDeviceInstallation($user->id);

        return $r;
    }

    public function signUpByInviteCodeWithLine (){
        try {
            $l_user_id =  Request::get('line_user_id');
            //Get Property & PropertyUnit Data
            $invite_code = Request::get('invite_code');
            $property_unit = PropertyUnit::with('property')->where('invite_code', '=', $invite_code)->first();

            if (!empty($property_unit)) {
                //Save property unit to line user
                $user = User::where('line_user_id', $l_user_id)->first();
                if( $user ) {
                    $user->name = Request::get('fname')." ".Request::get('lname');
                    $user->property_id = $property_unit->property->id;
                    $user->property_unit_id = $property_unit->id;
                    $user->is_subscribed_newsletter = ( Request::get('is_subscribed_newsletter') === true || Request::get('is_subscribed_newsletter') === "true") ? true : false;
                    $user->save();
                    return response()->json([
                        'success'  => true,
                        'token'    => JWTAuth::fromUser($user)
                    ]);
                } else {
                    $results = [
                        'status' => false,
                        'msg' => 'line user not found'
                    ];
                }
                
            }else{
                $error_msg = "Wrong Invite Code";
                $results = [
                    'status' => false,
                    'msg' => $error_msg
                ];
            }

            return response()->json($results);
           
        }catch(Exception $ex){
            $status = false;
            return response()->json($status);
        }
    }

    public function check3rdPartyAccount () {
        $email = trim(Request::get('email'));
        if( $email ) {
            $user = User::where('email',$email)->first();
            if( $user ) {
                if( $user->line_user_id ) {
                    $lf = true;
                } else {
                    $lf = false;
                }

                if( $user->apple_user_id ) {
                    $af = true;
                } else {
                    $af = false;
                }

                if( $user->password ) {
                    $password = true;
                } else {
                    $password = false;
                }
                $msg = 'success';
            } else {
                $msg        = 'user_not_found';
                $lf         = false;
                $af         = false;
                $password   = false;
            }
           
        } else {
            $msg        = 'email_not_provide';
            $lf         = false;
            $af         = false; 
            $password   = false;
        }

        return response()->json(array(
            'status' => true,
            'msg' => $msg,
            'is_line_account' => $lf,
            'is_apple_account' => $af,
            'flag_password' => $password
        ));
    }

    /* Authentication with apple id */

    public function authenithAppleId () {
        //JWT::decode($jwt, $key, ['HS256']);
        try {

            $token = Request::get('apple_token_id');
            $data = $this->decodeJWTApple($token);

            if( $data && $data->sub ) {
                $user = User::where('apple_user_id',$data->sub)->first();
                if($user) {
                    if( $user->property_unit_id ) {
                        $r['regis_state'] = 'verified';
                        $r['token'] = $token = JWTAuth::fromUser($user);

                    } else {
                        $r['regis_state'] = 'need_property_unit_verify';
                        $r['token'] = null;
                    }
                    $user->apple_user_id = $data->sub;
                    $user->save();
                } else {
                    $r['regis_state'] = 'need_property_unit_verify';
                    $r['token'] = null;
                    $user = [
                        'name' => '',
                        'email' => strtolower($data->email)
                    ];
                    $new_user = new User();
                    unset($new_user->rules['fname']);
                    unset($new_user->rules['lname']);
                    unset($new_user->rules['password']);
                    unset($new_user->rules['password_confirm']);
                    $vu = $new_user->validate($user);
            
                    if($vu->passes()) {
                        //Save and send verify code to email
                        $user = User::create([
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'role' => 2,
                            'lang' => 'th'
                        ]);
                        $user->apple_user_id = $data->sub;
                        $user->save();
                        //$this->dispatch(new SendEmailUser($user['name'],trans('messages.Email.thanks_signup'),$user['email']));
                    }
                }
                // Add installation what ever
                $this->addDeviceInstallation($user->id);

                $results = [
                    'status' => true,
                    'msg' => 'success',
                    'email' => $data->email
                ];
                $results += $r;

            } else {
                $results = [
                    'status' => false,
                    'msg' => 'cannot_retrieve_data',
                ];
            }

            return response()->json($results);

        } catch ( Exception $ex ) {
            $status = false;
            return response()->json($status);
        }
    }

    public function signUpByInviteCodeWithApple (){
        try {
            $token = Request::get('apple_token_id');
            $data = $this->decodeJWTApple($token);
            if( $data ) {
                $a_user_id =  $data->sub;
                //Get Property & PropertyUnit Data
                $invite_code = Request::get('invite_code');
                $property_unit = PropertyUnit::with('property')->where('invite_code', '=', $invite_code)->first();

                if (!empty($property_unit)) {
                    //Save property unit to apple user
                    $user = User::where('apple_user_id', $a_user_id)->first();
                    if( $user ) {
                        $user->name = Request::get('fname')." ".Request::get('lname');
                        $user->property_id = $property_unit->property->id;
                        $user->property_unit_id = $property_unit->id;
                        $user->is_subscribed_newsletter = ( Request::get('is_subscribed_newsletter') === true || Request::get('is_subscribed_newsletter') === "true") ? true : false;
                        $user->save();
                        return response()->json([
                            'success'  => true,
                            'token'    => JWTAuth::fromUser($user)
                        ]);
                    } else {
                        $results = [
                            'status' => false,
                            'msg' => 'apple user not found'
                        ];
                    }
                    
                }else{
                    $error_msg = "Wrong Invite Code";
                    $results = [
                        'status' => false,
                        'msg' => $error_msg
                    ];
                }
            } else {
                $results = [
                    'status' => false,
                    'msg' => 'cannot_retrieve_data'
                ];
            }
            

            return response()->json($results);
           
        }catch(Exception $ex){
            $status = false;
            return response()->json($status);
        }
    }

    public function addDeviceInstallation ($user_id) {
        try {

            $device_token   = Request::get('device_token');
            $device_type    = Request::get('device_type');
            $device_uuid    = Request::get('device_uuid');

            $countDevice = Installation::where('device_uuid', $device_uuid)->count();
            if ($countDevice > 0) {
                $this->deleteDeviceInstallationByUuid($device_uuid);
            }

            $this->deleteDeviceInstallationByTokenId($device_token);

            $device = new Installation;
            $device->user_id = $user_id;
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

    public function decodeJWTApple ($token) {
        return json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $token)[1]))));
    }
}
