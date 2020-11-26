<?php namespace App\Http\Controllers;
use Auth;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;

# Model
use App\Property;
use App\User;
use App\Installation;
use App\Notification;

// Firebase
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;

class DirectNotifyAccoutStatusController extends Controller {


    public function sentNotifyAccountStatus ($user_id)
    {

        $user_receive = User::find($user_id);

        $countInstallation = Installation::select('device_token')->where('user_id', '=', $user_id)->whereNotNull('device_token')->count();

        $message_string = trans('messages.Member.account_disabled', array(), null, $user_receive->lang);

        if(isset($user_receive) && $countInstallation > 0) {

            $device_token_obj = Installation::select('device_token')->where('user_id', '=', $user_id)->whereNotNull('device_token')->get();
            $device_token_array = $device_token_obj->toArray();

            $device_token_list = array();
            foreach ($device_token_array as $item){
                $device_token_list[] = $item['device_token'];
            }
            $message = array(
                "priority" => "high",
                'notification' => array(
                    'title' => "Nabour",
                    'text' => "account_disabled",
                    'click_action' => 401
                ),
                'data' => array(
                    'notification_id' => "",
                    'subject_key' => "",
                    'notification_type' => 401
                )
            );

            $optionBuiler = new OptionsBuilder();
            $optionBuiler->setTimeToLive(60*20);
            $priority = 'high'; // or 'normal'
            $optionBuiler->setPriority($priority);

            $notificationBuilder = new PayloadNotificationBuilder('Nabour');
            $notificationBuilder->setBody($message_string)
                ->setClickAction("notification")
                ->setIcon("ic_icon_notification")
                ->setSound('default');

            $dataBuilder = new PayloadDataBuilder();
            $dataBuilder->addData($message);

            $option = $optionBuiler->build();
            $notification = $notificationBuilder->build();
            $data = $dataBuilder->build();

            $tokens = $device_token_list;
            $downstreamResponse = FCM::sendTo($tokens, $option, $notification,$data);

            $downstreamResponse->numberSuccess();
            $downstreamResponse->numberFailure();
            $downstreamResponse->numberModification();

            //return Array - you must remove all this tokens in your database
            $downstreamResponse->tokensToDelete();

            //return Array (key : oldToken, value : new token - you must change the token in your database )
            $downstreamResponse->tokensToModify();

            //return Array - you should try to resend the message to the tokens in the array
            $downstreamResponse->tokensToRetry();
        }
    }
}