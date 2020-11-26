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

class DirectPushNotificationController extends Controller {

    public function pushNotification( $notification_id ){
       
        $notification = Notification::with('sender')->find($notification_id);

        $user_id = $notification->to_user_id;

        $user_receive = User::find($user_id);

        $property = Property::find($notification->sender->property_id);

        $countInstallation = Installation::select('device_token')->where('user_id', '=', $user_id)->whereNotNull('device_token')->count();

        if(isset($user_receive) && $user_receive->notification && $countInstallation > 0) {

            $noti_count = Notification::with('sender')->where('to_user_id', '=', $user_id)
                ->where('read_status', '=', false)->count();

            $message_string = "";

            $sender_name = "";

            if($notification->sender->role == 1 || $notification->sender->role == 3) {
                if ($user_receive->lang == 'en') {
                    $sender_name = $property->juristic_person_name_en;
                }else{
                    $sender_name = $property->juristic_person_name_th;
                }
            }else{
                $sender_name = $notification->sender->name;
            }

            switch ($notification->notification_type) {
                case "0"://Comment
                    $message_string = trans('messages.Notification.post_created_msg', array(), null, $user_receive->lang);
                    break;
                case "1"://Like
                    $message_string = $sender_name . " " . trans('messages.Notification.' . $notification->title, array(), null, $user_receive->lang);
                    break;
                case "2"://Complain
                    $data = json_decode($notification->title, true);
                    if (isset($data['type']) && $data['type'] == 'comment') {
                        $message_string = $sender_name . " " .trans('messages.Notification.complain_comment', $data, null, $user_receive->lang);
                    }elseif($data['type'] == 'change_status'){
                        $data['status'] = trans('messages.Complain.' . $data['status'], array(), null, $user_receive->lang);
                        $message_string = trans('messages.Notification.complain_change_status', $data, null, $user_receive->lang);
                    } elseif($data['type'] == 'complain_created') {
                        $message_string = trans('messages.Notification.complain_created', array(), null, $user_receive->lang);
                    } elseif ( $data['type']=='complain_created_by_juristic') {
                        $message_string = trans('messages.Notification.complain_created_by_juristic', $data, null, $user_receive->lang);
                    }
                    break;
                case "3"://Fees&Bills
                    $data = json_decode($notification->title, true);
                    if ($data['type'] == 'sticker_approved') {
                        $message_string = trans('messages.Notification.sticker_approved_head', array(), null, $user_receive->lang);
                    } elseif ($data['type'] == 'invoice_created') {
                        $message_string = trans('messages.Notification.invoice_created_msg', ['name' => $data['title']], null, $user_receive->lang);
                    } elseif ($data['type'] == 'payment_notification') {
                        $message_string = trans('messages.Notification.payment_notify', ['in_no' => $data['invoice_no']], null, $user_receive->lang);
                    }
                    break;
                case "4"://Event
                    $data = json_decode($notification->title, true);
                    if( $data['type'] == 'event_created') {
                        $message_string = $sender_name ." ". trans('messages.Notification.event_created_msg',['name'=> $data['title']], null, $user_receive->lang);
                    }
                    break;
                case "5"://Vote
                    $data = json_decode($notification->title, true);
                    if( $data['type'] == 'vote_created') {
                        $message_string = $sender_name ." ". trans('messages.Notification.vote_created_msg',['name'=> $data['title']], null, $user_receive->lang);
                    }
                    break;
                case "6"://Post&Parcel
                    $data = json_decode($notification->title, true);
                    if( $data['type'] == 'receive_post_parcel') {
                        $message_string = trans('messages.Notification.receive_post_parcel_msg',$data,null, $user_receive->lang);
                    }
                    break;
                case "7"://Other
                    $data = json_decode($notification->title, true);
                    if ($data['type'] == 'general') {
                        $message_string = $data['n_title'];
                    } elseif ($data['type'] == 'sticker_approved') {
                        $message_string = trans('messages.Notification.sticker_approved_head', array(), null, $user_receive->lang);
                    } elseif ($data['type'] == 'invoice_created') {
                        $message_string = trans('messages.Notification.invoice_created_msg', ['name' => $data['title']], null, $user_receive->lang);
                    } elseif( $data['type'] == 'event_created') {
                        $message_string = $sender_name ." ". trans('messages.Notification.event_created_msg',['name'=> $data['title']], null, $user_receive->lang);
                    } elseif( $data['type'] == 'vote_created') {
                        $message_string = $sender_name ." ". trans('messages.Notification.vote_created_msg',['name'=> $data['title']], null, $user_receive->lang);
                    } elseif( $data['type'] == 'receive_post_parcel') {
                        $message_string = trans('messages.Notification.receive_post_parcel_msg',$data,null, $user_receive->lang);
                    } elseif( $data['type'] == 'discussion_created') {
                        $message_string = trans('messages.Notification.discussion_created_msg',['name'=> $data['title']], null, $user_receive->lang);
                    } elseif( $data['type'] == 'discussion_comment') {
                        $message_string  = trans('messages.Notification.discussion_comment_msg',['name'=> $data['title']], null, $user_receive->lang);
                    }elseif( $data['type'] == 'post_created') {
                        $message_string = trans('messages.Notification.post_created_msg', array(), null, $user_receive->lang);
                    }
                    break;
                case "11"://Post Created
                    $data = json_decode($notification->title, true);
                    if ($data['type'] == 'post_created') {
                        $message_string = trans('messages.Notification.post_created_msg', array(), null, $user_receive->lang);
                    }
                    break;
                case "12"://Complete Bills Submit
                    $data = json_decode($notification->title, true);
                    if ($data['type'] == 'transaction_complete') {
                        $message_string = trans('messages.Notification.transaction_complete_msg', ['name' => $data['title']], null, $user_receive->lang);
                    }
                    break;
                case "14"://SOS Notification
                    $data = json_decode($notification->title, true);
                    if ($data['type'] == 'order_arrived') {
                        $message_string = trans('messages.Notification.sos_order_arrived', ['order_no' => $data['order_no']], null, $user_receive->lang);
                    }
                    if ($data['type'] == 'status_change') {
                        $status = trans('messages.MarketPlace.singha.status_'.$data['status'],null, $user_receive->lang);
                        $message_string = trans('messages.Notification.sos_order_status_change', [
                            'order_no'  => $data['order_no'],
                            'status'    => $status
                        ], null, $user_receive->lang);
                    }
                    if ($data['type'] == 'order_reset') {
                        $message_string = trans('messages.Notification.sos_order_reset', ['order_no'  => $data['order_no']], null, $user_receive->lang);
                    }
                    break;
                default:
                    $message_string = $sender_name . " " . trans('messages.Notification.' . $notification->title, array(), null, $user_receive->lang);
                    break;
            }
            // Change to FCM Notification
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
                    'text' => $message_string,
                    'click_action' => $notification->notification_type
                ),
                'data' => array(
                    'notification_id' => $notification_id,
                    'subject_key' => $notification->subject_key,
                    'notification_type' => $notification->notification_type
                ),
                'badge' => $noti_count
            );

            $optionBuiler = new OptionsBuilder();
            $optionBuiler->setTimeToLive(60*20);
            $priority = 'high'; // or 'normal'
            $optionBuiler->setPriority($priority);


            $notificationBuilder = new PayloadNotificationBuilder('Nabour');
            $notificationBuilder->setBody($message_string)
                ->setClickAction("notification")
                ->setIcon("ic_icon_notification")
                ->setSound('default')
                ->setBadge($noti_count);

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
