<?php namespace App\Http\Controllers\RootAdmin;
use Illuminate\Http\Request;
use Storage;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\FileContoller;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use Carbon\Carbon;
use DateTime;
# Model
use App\SystemSettings;
use App\User;




class SettingLoginController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu', 'settings');
	}



    public function SettingLogin(Request $r) {
      
        $sys = SystemSettings::first();
        return view('settings.setting-login')->with(compact('sys'));
    }

    public function edit (Request $r) {
        if($r->isMethod('post')) {
            if($r->get('id')){
                $sys = SystemSettings::find($r->get('id'));
                $sys->login_with_line = $r->get('login_with_line')? true : false;
                $sys->login_with_apple_id = $r->get('login_with_apple_id')? true : false; 
                $sys->save();
                return redirect()->back()->withInput();
            }
          
		}
    }   
}
