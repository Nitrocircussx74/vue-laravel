<?php namespace App\Http\Controllers\API;

use App\Jobs\PushNotificationSender;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Http\Controllers\PushNotificationController;
use Request;
//use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;

use JWTAuth;
use Storage;
use Illuminate\Bus\Dispatcher;
use League\Flysystem\AwsS3v2\AwsS3Adapter;

// Firebase
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;

# Model
use App\Post;
use App\PostFile;
use App\Comment;
use App\Like;
use App\Property;
use App\Notification;
use App\PostReport;
use App\Installation;
use Auth;
use File;
use Mail;
use App\User;
use App\PostByNabour;
use App\PostByNabourFile;

class SmsController extends Controller {

    //use DispatchesJobs;

	public function __construct () {
        $adasda = "asasd";
	}

	public function send () {
	    $sddadas = "dsasdad";
	    $asdasdsad = "";
    }


}
