<?php namespace App\Http\Controllers\API;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
# Model
use App\User;
use App\PropertyUnit;
use App\Vehicle;
use App\Pet;
use App\Property;
use App\Installation;
use App\Keycard;
use JWTAuth;
use Auth;
use File;
use Hash;
use App\SystemSettings;
class SettingsController extends Controller {

	public function __construct () {

	}
	public function index(){
        try{
            if(Auth::user()->active == false){
                JWTAuth::parseToken()->invalidate();
                return response()->json(['status'=>false]);
            }
            $user = User::find(Auth::user()->id);
            $property = Property::find(Auth::user()->property_id);
            $property_unit = PropertyUnit::find(Auth::user()->property_unit_id);

            // Min and Max price
            $price = [
                1 => '1,000,000 - 3,000,000 '.trans('messages.Report.baht',[],null,Auth::user()->lang),
                2 => '3,000,001 - 5,000,000 '.trans('messages.Report.baht',[],null,Auth::user()->lang),
                3 => '5,000,001 - 10,000,000 '.trans('messages.Report.baht',[],null,Auth::user()->lang),
                4 => '10,000,001+'
            ];
            if( $property->min_price ) {
                $property->min_price = $price[$property->min_price];
            } else {
                $property->min_price = "-";
            }
            if( $property->max_price ) {
                $property->max_price = $price[$property->max_price];
            } else {
                $property->max_price = "-";
            }
            if( $property->project_banner ) {
                $property->project_banner = env('URL_S3')."/property-file".$property->project_banner;
            }
            $balance = $property_unit->balance;
            $user['unit_number'] = $property_unit->unit_number;

            $results = array(
                "user" => $user,
                "property" => $property,
                'balance' => $balance
            );

            return response()->json($results);
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
	}

    public function updateInstall(){
        try{
            if (Request::isMethod('post')) {
                $device_token = Request::get('device_token');
                $device_uuid = Request::get('device_uuid');

                // Case 01 : Delete App BUT forget logout => device_token : OLD, device_uuid : CHANGE
                $device_case01 = Installation::where('device_token','=', $device_token)
                                    ->where('device_uuid','!=', $device_uuid)
                                    ->where('user_id','=', Auth::user()->id);

                if($device_case01->count() > 0){
                    $device_type = $device_case01->first()->device_type;
                    $device_case01->delete();

                    $device = new Installation();
                    $device->user_id = Auth::user()->id;
                    $device->device_token = $device_token;
                    $device->device_type = $device_type;
                    $device->device_uuid = $device_uuid;
                    $device->save();
                }


                // Case 02 : Update OS BUT forget logout => device_token : CHANGE, device_uuid : OLD
                $device_case02 = Installation::where('device_token','!=', $device_token)
                                    ->where('device_uuid','=', $device_uuid)
                                    ->where('user_id','=', Auth::user()->id);

                if($device_case02->count() > 0){
                    $device_type = $device_case02->first()->device_type;
                    $device_case02->delete();

                    $device = new Installation();
                    $device->user_id = Auth::user()->id;
                    $device->device_token = $device_token;
                    $device->device_type = $device_type;
                    $device->device_uuid = $device_uuid;
                    $device->save();
                }
            }

            return response()->json(['status'=>true]);
        }catch (Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function userUpdate(){
        try {
        $user = User::find(Auth::user()->id);
        //$user->fill(Request::all());
        if(Request::get('name') != null){
        	$user->name = Request::get('name');
				}

				if(Request::get('phone') != null){
        	$user->phone = Request::get('phone');
				}

        if(Request::get('dob') != null){
            $user->dob = Request::get('dob');
        }

				if(Request::get('gender') != null){
						$user->gender = Request::get('gender');
				}

        $user->save();

        return response()->json(['status'=>true]);

        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function userUpdateProfile(){
        try {
            $user = User::find(Auth::user()->id);
            if(!empty(Request::file('pic_profile'))) {
                $img = Request::file('pic_profile');
                if(!empty($user->profile_pic_name)) {
                    $this->removeFile($user->profile_pic_name);
                }

                $name =  md5($img->getFilename());//getClientOriginalName();
                $extension = $img->getClientOriginalExtension();
                $targetName = $name.".".$extension;

                $path = $this->createLoadBalanceDir(Request::file('pic_profile'));
                $user->profile_pic_name = $targetName;
                $user->profile_pic_path = $path;
            }
            $user->save();
            return response()->json(['status'=>true]);

        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

	public function password () {
        try {
            if (Request::isMethod('post')) {
                if (!Hash::check(Request::get('old_password'), Auth::user()->password)) {
                    return response()->json(['status' => false, 'msg' => 'ไม่สามารถเปลี่ยนรหัสผ่านได้ เนื่องจากรหัสผ่านเดิมไม่ถูกต้อง']);
                } else {
                    $user = User::find(Auth::user()->id);
                    $user->password = Hash::make(Request::get('new_password'));
                    $user->save();
                    Auth::loginUsingId($user->id);
                    return response()->json(['status' => true, 'msg' => 'เปลี่ยนรหัสผ่านแล้ว']);
                }
            }
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
	}

    public function notification () {
        try {
            $user = User::find(Auth::user()->id);
            if(Request::isMethod('post')) {
                $user->notification = Request::get('notification') == 'true' ? true : false;
                $user->save();
            }
            return response()->json(['status'=>true]);
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function home () {
        $home = PropertyUnit::with('home_pet','home_vehicle')->find(Auth::user()->property_unit_id);
        $results = $home->toArray();

        return response()->json($results);
    }

    public function saveHome () {
        try{
            $home = PropertyUnit::find(Auth::user()->property_unit_id);
            $home->fill(Request::all());
            if(Request::get('resident_count') == ""){
                $home->resident_count = 0;
            }else{
                if(is_numeric(Request::get('resident_count'))){
                    $home->resident_count = (int)Request::get('resident_count');
                }else{
                    $home->resident_count = 0;
                }
            }
            $home->save();
            return response()->json(['status'=>true]);
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function saveHomePet () {
        try {
            if (Request::isMethod('post')) {
                $pet = new Pet;
                $pet->fill(Request::all());
                $pet->property_id = Auth::user()->property_id;
                $pet->property_unit_id = Auth::user()->property_unit_id;
                $pet->save();
            }
            return response()->json(['status'=>true]);
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function saveHomeVehicle () {
        try {
            if (Request::isMethod('post')) {
                $vehicle = new Vehicle;
                $vehicle->fill(Request::all());
                $vehicle->type = intval($vehicle->type);
                $vehicle->property_id = Auth::user()->property_id;
                $vehicle->property_unit_id = Auth::user()->property_unit_id;
                $vehicle->save();

                return response()->json(['vehicle_id'=>$vehicle->id]);
            } else{
                return response()->json(['vehicle_id' => ""]);
            }

        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function getVehicleList () {
        $vehicle = Vehicle::where('property_unit_id','=',Auth::user()->property_unit_id)->get();
        return response()->json($vehicle);
    }

    public function getVehicle () {
        if(Request::isMethod('post')) {
            $vehicle = Vehicle::find(Request::get('id'));
            return view('settings.vehicle-detail')->with(compact('vehicle'));
        }
    }

    public function deleteHomePet () {
        try {
            if(Request::isMethod('post')){
                $id = Request::get('pet_id');
                $pet = Pet::find($id);
                $pet->delete();
                return response()->json(['status' => true]);
            }
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function deleteHomeVehicle () {
        try {
            if(Request::isMethod('post')){
                $id = Request::get('vehicle_id');
                $vehicle = Vehicle::find($id);
                $vehicle->delete();
                return response()->json(['status' => true]);
            }
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function getKeyCardList () {
        $key_card = Keycard::where('property_unit_id','=',Auth::user()->property_unit_id)->get();
        return response()->json($key_card);
    }

    public function saveKeyCard () {
        try {
            if(Request::isMethod('post') && Request::get('serial_number') != "") {
                $keycard = new Keycard;
                $keycard->serial_number = Request::get('serial_number');
                $keycard->property_id = Auth::user()->property_id;
                $keycard->property_unit_id = Auth::user()->property_unit_id;
                $keycard->save();
            }
            return response()->json(['status' => true]);
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function deleteKeyCard () {
        try {
            if(Request::isMethod('post')) {
                $keycard = Keycard::find(Request::get('keycard_id'));
                if(isset($keycard)) {
                    $keycard->delete();
                }
                return response()->json(['status' => true]);
            }
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function requestSticker () {
        try {
            if(Request::isMethod('post')) {
                $vehicle = Vehicle::find(Request::get('vehicle_id'));
                $vehicle->sticker_status = 1;
                $vehicle->sticker_request_date = date('Y-m-d');
                $vehicle->sticker_expire_date = NULL;
                $vehicle->save();
                return response()->json(['status' => true]);
            }
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function createLoadBalanceDir ($imageFile) {
        $name =  md5($imageFile->getFilename());//getClientOriginalName();
        $extension = $imageFile->getClientOriginalExtension();
        $targetName = $name.".".$extension;

        $folder = substr($name, 0,2);

        $pic_folder = 'profile-img'.DIRECTORY_SEPARATOR.$folder;
        $directories = Storage::disk('s3')->directories('profile-img'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }

        $full_path_upload = $pic_folder.DIRECTORY_SEPARATOR.$targetName;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($imageFile), 'public');// public set in photo upload
        if($upload){
            // Success
        }

        return $folder."/";
    }

    public function removeFile ($name) {
        $folder = substr($name, 0,2);
        $file_path = 'profile-img'.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            Storage::disk('s3')->delete($file_path);
        }
    }

    public function language () {
        try {
            $user = User::find(Auth::user()->id);
            if (Request::isMethod('post')) {
                $user->lang = Request::get('lang');
                $user->save();
                Auth::loginUsingId($user->id);
                Request::session()->put('lang', Auth::user()->lang);
            }
            return response()->json(['status'=>true]);
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function statusViewReport() {
        try {
            if(Auth::user()->is_chief){
                return response()->json(['status' => true]);
            }else {
                $property = Property::find(Auth::user()->property_id);
                if ($property->allow_user_view_cf_report != false) {
                    return response()->json(['status' => true]);
                }

                return response()->json(['status' => false]);
            }
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function set3rdPartyAccountPassword () {
        try {
            if (Request::isMethod('post')) {
                $user = User::find(Auth::user()->id);
                if ( ($user->line_user_id || $user->apple_user_id) && $user->password ) {
                    return response()->json(['status' => false, 'msg' => 'ไม่สามารถตั้งรหัสผ่านได้ เนื่องจากไม่ได้อยู่ในสถานะที่อนุญาต']);
                } else {
                    $user->password = Hash::make(Request::get('new_password'));
                    $user->save();
                    Auth::loginUsingId($user->id);
                    return response()->json(['status' => true, 'msg' => 'เปลี่ยนรหัสผ่านแล้ว']);
                }
            }
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }
    /// Setting login with Lie/Apple
    public function checkLoginButton () {
        $st = SystemSettings::first();
        if( $st ) {
            return response()->json([
                'line_login'        => $st->login_with_line,
                'apple_id_login'    => $st->login_with_apple_id
            ]);
        } else {
            return response()->json([
                'line_login'        => true,
                'apple_id_login'    => true
            ]);
        }
        
    }
}
