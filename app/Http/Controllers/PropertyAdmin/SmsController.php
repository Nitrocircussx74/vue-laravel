<?php namespace App\Http\Controllers\PropertyAdmin;

require_once base_path('vendor/basic-authen-sdk/ServerAuthenService.php');
require base_path('vendor/messenger-sdk/MessengerAPIService.php');
//require_once base_path('vendor/messenger-sdk/common/Mediatype.php');

// AIS Authen
use _server_sdk\ServerAuthenService;
use _server_sdk\model\ServerConfigParameters;
// AIS SMS Engine
use _messenger_sdk\MessengerAPIService;
//use _messenger_sdk\common\MediaType;

use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use Auth;
use File;
use Redirect;
use Storage;
# Model
use App\Property;
use App\PropertyFile;
use App\Bank;
use App\Province;
use App\PropertySettings;
class SmsController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu','about');
		if(Auth::check() && Auth::user()->role  == 2) Redirect::to('feed')->send();
	}



	public function authen () {
        //require_once base_path('vendor/basic-authen-sdk/ServerAuthenService.php');
		$aaa = "test";

        /*$configParam = new ServerConfigParameters();
        $configParam->setClientId('ABCD');
        $configParam->setSecret('pathfile/secret.crt');
        $configParam->setLiveKeyPath('pathfile/YOURLIVEKEY.dat');
        $configParam->setEmail('YOUREMAIL@YOUREMAIL.com');
        $configParam->setEnvironment(2);*/

        $configParam = new ServerConfigParameters();
        $configParam->setClientId('wvwQpufioz6bpDWTtBO8FiNO+uszOiY1YPx+kRfKTuE=');
        $configParam->setSecret(storage_path() . '/cert/ais/ais-partner_28000.crt');
        $configParam->setLiveKeyPath(storage_path() . '/cert/ais/livekey_DemoApp_Android_1.0.0_201706021625.dat');
        $configParam->setEmail('demoapp01@sand.ais.co.th');
        $configParam->setEnvironment(2); //(Environment 1 = Production, 2 = development)

        $serverAuthenService = new ServerAuthenService();
        $serverAuthen = $serverAuthenService->buildServerAuthen($configParam);
        $result = $serverAuthen->login();

        //$this->send($result);

        return "true";
	}

	function send($result){
        $configParam = new ServerConfigParameters();
        $configParam->setClientId('wvwQpufioz6bpDWTtBO8FiNO+uszOiY1YPx+kRfKTuE=');
        $configParam->setLiveKeyPath(storage_path() . '/cert/ais/livekey_DemoApp_Android_1.0.0_201706021625.dat');
        $configParam->setEmail('demoapp01@sand.ais.co.th');
        $configParam->setEnvironment(2);

        $paramResult = (object)[];

        $paramResult->{'from'} = 'Nabour';
        $paramResult->{'to'} = '66884339217'; // Man
        $paramResult->{'deliveryReport'}  = 'N';
        $paramResult->{'contentType'}  =  'TEXT';
        $paramResult->{'deliveryReportURL'} =  '';
        $paramResult->{'content'}	=  'Text message';
        $paramResult->{'senderName'} =  '';
        $paramResult->{'accessToken'} = 'eifu11EKDFGT1313';

        /*$paramResult->{'from'} = 'Nabour';
        $paramResult->{'to'} = '66839435554'; // Q
        $paramResult->{'deliveryReport'}  = 'N';
        $paramResult->{'contentType'}  =  MediaType::TEXT;
        $paramResult->{'deliveryReportURL'} =  '';
        $paramResult->{'content'}	=  'Text message';
        $paramResult->{'senderName'} =  '';
        $paramResult->{'accessToken'} = 'eifu11EKDFGT1313';*/

        $MessengerAPIService = new MessengerAPIService($configParam);
        $sendMsg = $MessengerAPIService->buildMessengerAPI();
        $result = $sendMsg->sendSMS($paramResult);

        return "true";
    }
}
