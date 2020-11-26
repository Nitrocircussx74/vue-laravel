<?php namespace App\Http\Controllers\User;
use Request;
use Storage;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\FileContoller;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use Carbon\Carbon;
use DateTime;
# Model
use App\User;
use App\Property;
use App\PropertyUnit;
use App\Vehicle;
use App\Pet;
use App\VehicleMake;
use App\Keycard;
use Auth;
use File;
use Hash;

# Test Pubnub
use Pubnub\Pubnub;

class SettingsController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu', 'settings');
	}
	public function index()
	{
		$user_forsave = $user = User::find(Auth::user()->id);
		if(Request::isMethod('post')) {
			$input = Request::except('email','password');
            $user_forsave->name = trim(Request::get('fname'))." ".trim(Request::get('lname'));
			$user_forsave->phone = Request::get('phone');

			if(Request::get('gender')){
				$user_forsave->gender = Request::get('gender');
			}
            if(Request::get('dob') != null){
                $user_forsave->dob = Request::get('dob');
            }

			if(!empty(Request::get('pic_name'))) {
				if(!empty($user->profile_pic_name)) {
					$this->removeFile($user->profile_pic_name);
				}
				$name 	= Request::get('pic_name');
				$x 		= Request::get('img-x');
				$y 		= Request::get('img-y');
				$w 		= Request::get('img-w');
				$h 		= Request::get('img-h');

				cropProfileImg ($name,$x,$y,$w,$h);
				$path 	= $this->createLoadBalanceDir(Request::get('pic_name'));
				$user_forsave->profile_pic_name = Request::get('pic_name');
				$user_forsave->profile_pic_path = $path;
			}
			if($user_forsave->save()) {
				Auth::loginUsingId($user_forsave->id);
				return redirect('settings');
			}
		} else {
			$name =  explode(" ",$user->name);
			$user->fname = $name[0];
			$user->lname = empty($name[1])?"":$name[1];
	        if($user->dob) {
	            $temp_dob = $user->dob;
	            $date = DateTime::createFromFormat("Y-m-d", $temp_dob);
	            $user->dob = $date->format("D, d M Y");
	        }
			return view('settings.index')->with(compact('user'));
		}

		return view('settings.index')->with(compact('user'));
	}

	public function password () {
		$property = Property::find(Auth::user()->property_id);
		$is_demo = false;
		if(isset($property)){
			if($property->is_demo) {
				$is_demo = true;
			}
		}

		if(Request::isMethod('post')) {
			if ( !Hash::check(Request::get('old_password'), Auth::user()->password) ) {
		        return redirect()->back()->withErrors(['password'=> trans('messages.Settings.old_not_match') ]);
		    } else {
				if(!$is_demo) {
					$user = User::find(Auth::user()->id);
					$user->password = Hash::make(Request::get('new_password'));
					$user->save();
					Auth::loginUsingId($user->id);
					Request::session()->put('success.message', trans('messages.Settings.change_pass_success'));
				}else{
					Request::session()->put('success.message', "Function Disable");
				}
		    }
		}

		return view('settings.password')->with(compact('is_demo'));
	}

	public function notification () {
		$user = User::find(Auth::user()->id);
		if(Request::isMethod('post')) {
			$user->notification = Request::get('notification')? true : false;
			$user->save();
		}
		return view('settings.notification')->with(compact('user'));
	}

	public function language (Application $app) {
		$user = User::find(Auth::user()->id);
		if(Request::isMethod('post')) {
			$user->lang = Request::get('language');
			$user->save();
			Auth::loginUsingId($user->id);
			$app->setLocale(Auth::user()->lang);
		}
		return view('settings.language')->with(compact('user'));
	}

	public function home () {
		$home = PropertyUnit::with('home_pet','home_vehicle','home_keycard')->find(Auth::user()->property_unit_id);
        $property = Property::find(Auth::user()->property_id);

		return view('settings.home')->with(compact('home','property'));
	}

	public function saveHome () {
		$home = PropertyUnit::find(Auth::user()->property_unit_id);
		$home->fill(Request::all());
		$home->save();
		return redirect('/settings/home');
	}

	public function saveHomePet () {
		if(Request::isMethod('post')) {
			$pet = new Pet;
			$pet->fill(Request::all());
			$pet->property_id = Auth::user()->property_id;
			$pet->property_unit_id = Auth::user()->property_unit_id;
			$pet->save();
		}
		return redirect('/settings/home');
	}

	public function saveHomeVehicle () {
		if(Request::isMethod('post')) {
			$vehicle = new Vehicle;
			$vehicle->fill(Request::all());

			if(Request::get('type') == 1 || Request::get('type') == 2 ) {
				$vehicle->brand = Request::get('s_brand');
			} else {
				$vehicle->brand = Request::get('o_brand');
			}
			$vehicle->property_id = Auth::user()->property_id;
			$vehicle->property_unit_id = Auth::user()->property_unit_id;
			$vehicle->save();
		}
		return redirect('/settings/home');
	}

	public function getVehicle () {
		if(Request::isMethod('post')) {
			$vehicle = Vehicle::find(Request::get('id'));
			return view('settings.vehicle-detail')->with(compact('vehicle'));
		}
		//return redirect('/settings/home');
	}

	public function deleteHomePet ($id) {
		$pet = Pet::find($id);
		$pet->delete();
		return redirect('/settings/home');
	}

	public function deleteHomeVehicle ($id) {
		$vehicle = Vehicle::find($id);
		$vehicle->delete();
		return redirect('/settings/home');
	}

    public function getKeyCard () {
        if(Request::isMethod('post')) {
            $keycard = Keycard::find(Request::get('id'));
            return view('settings.vehicle-detail')->with(compact('keycard')); // ยังไมได้ใส่ view
        }
    }

    public function saveKeyCard () {
        if(Request::isMethod('post')) {
            $keycard = new Keycard;
            $keycard->fill(Request::all());
            $keycard->property_id = Auth::user()->property_id;
            $keycard->property_unit_id = Auth::user()->property_unit_id;
            $keycard->save();
        }
        return redirect('/settings/home'); // ยังไมได้ใส่ view
    }

    public function deleteKeyCard ($id) {
        $keycard = Keycard::find($id);
        $keycard->delete();
        return redirect('/settings/home'); // ยังไมได้ใส่ view
    }

	public function requestSticker () {
		if(Request::isMethod('post')) {
			$vehicle = Vehicle::find(Request::get('id'));
			$vehicle->sticker_status = 1;
			$vehicle->sticker_request_date = date('Y-m-d');
			$vehicle->sticker_expire_date = NULL;
			$vehicle->save();
			return response()->json(['result'=>true, 'msg'=> trans('messages.Vehicle.sticker_status_1')]);
		}
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'profile-img/'.$folder;
        $directories = Storage::disk('s3')->directories('profile-img'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function removeFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'profile-img/'.$folder."/".$name;
		if(Storage::disk('s3')->has($file_path)) {
			Storage::disk('s3')->delete($file_path);
		}

	}

    public function testPubnubByMan(){
        /*
         *  Publish Key : pub-c-0007ebbe-803b-45d4-a481-ea1963e29935
            Subscribe Key : sub-c-d99bfa84-bb54-11e5-a9aa-02ee2ddab7fe
            Secret Key : sec-c-MmYyNTc4OTAtNWM5YS00Zjg5LThkNmEtNzM2Njc4OTdiODc3
         * */
        $pubnub = new Pubnub(array(
            'subscribe_key' => 'sub-c-d99bfa84-bb54-11e5-a9aa-02ee2ddab7fe',
            'publish_key' => 'pub-c-0007ebbe-803b-45d4-a481-ea1963e29935',
            'uuid' => 'sec-c-MmYyNTc4OTAtNWM5YS00Zjg5LThkNmEtNzM2Njc4OTdiODc3',
            'ssl' => false
        ));

        $user_id = "2468280d-2c7f-47bd-b68f-be84ec63acb4";

        $info = $pubnub->publish($user_id, 'Hey Dude!');

        $var_man = $info;

        return "true";
    }
}
