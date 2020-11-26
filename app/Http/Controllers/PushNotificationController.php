<?php namespace App\Http\Controllers;
use Auth;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;
use Illuminate\Bus\Dispatcher;
use Illuminate\Foundation\Bus\DispatchesJobs;
use PushNotification;

# Model
use App\Property;
use App\User;
use App\Installation;
use App\Notification;

# Jobs
use App\Jobs\PushNotificationUserAndroid;
use App\Jobs\PushNotificationUserIOS;
use App\Jobs\PushNotificationSender;
use App\Jobs\PushNotificationToDevice;
use App\Jobs\PushNotificationToDeviceSetCommittee;
use App\Jobs\PushNotificationToDeviceMessage;
use App\Jobs\BatchNotification;

class PushNotificationController extends Controller {

    use DispatchesJobs;

    public function __construct () {
    }

    public function pushNotification($notification_id){
        $this->dispatch(new PushNotificationToDevice($notification_id));
    }

    public function pushNotificationSetCommitter($user_id,$status){
        $this->dispatch(new PushNotificationToDeviceSetCommittee($user_id,$status));
    }

    public function pushNotificationMessageSend($user_id){
        $this->dispatch(new PushNotificationToDeviceMessage($user_id));
    }

    public function pushNotificationArray($notification_array){
        foreach ($notification_array as $notification) {
            $this->dispatch(new PushNotificationToDevice($notification->id));
        }
    }

    public function dispatchBatchNotification ($title,$subject_id,$notification_type,$sender_id,$property_id) {
        $langs = ['th', 'en'];
        foreach ($langs as $lang ) {
            $in = Installation::whereHas('user',function ($q) use ($lang, $property_id) {
                $q->where('lang',$lang);
                $q->where('property_id',$property_id);
                $q->where('notification', true);
            })->whereNotNull('device_token')->chunk(1000, function($installation) use ($title,$subject_id,$notification_type,$lang,$sender_id) {
                $tokens = [];
                if( $installation ) {
                    foreach( $installation as $in ) {
                        $tokens[] = $in->device_token;
                    }
                }
                if( !empty($tokens) ) {
                    $this->dispatch(new BatchNotification($tokens,$title,$subject_id,$notification_type,$lang,$sender_id));
                }
            });
        }
    }

}
