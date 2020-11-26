<?php namespace App\Http\Controllers\API;
use Request;
use Illuminate\Routing\Controller;
use Auth;
# Model

use App\Notification;
use App\PropertyUnit;
use App\Message;
use App\PropertyFeature;
class NotificationController extends Controller {

    public function __construct () {

    }
    public function index()
    {
        $all_noti = Notification::where('to_user_id','=',Auth::user()->id)
            ->where('read_status','=',false)
            ->select('notification_type')->get();

        $counter_noti = [
            'comment' 	=> 0,
            'like'		=> 0,
            'complain'	=> 0,
            'bill'		=> 0,
            'event'		=> 0,
            'vote'		=> 0,
            'parcel'	=> 0,
            'other'		=> 0,
            'announcement'		=> 0
        ];

        foreach ($all_noti as $noti) {
            if( $noti->notification_type == 0 ) $counter_noti['comment']++;
            elseif( $noti->notification_type == 1 ) $counter_noti['like']++;
            elseif( $noti->notification_type == 2 ) $counter_noti['complain']++;
            elseif( $noti->notification_type == 3 ) $counter_noti['bill']++;
            elseif( $noti->notification_type == 4 ) $counter_noti['event']++;
            elseif( $noti->notification_type == 5 ) $counter_noti['vote']++;
            elseif( $noti->notification_type == 6 ) $counter_noti['parcel']++;
            elseif( $noti->notification_type == 7 || $noti->notification_type == 12) $counter_noti['other']++;
            elseif( $noti->notification_type == 11 ) $counter_noti['announcement']++;
            else ;
        }

        if(Request::get('type') != null) {
            if(Request::get('type') == "3") {
                $notis = Notification::with('sender')
                    ->whereIn('notification_type',['3','4','5','6','7','11','12'])
                    ->where('to_user_id', '=', Auth::user()->id)
                    ->where('read_status','=',false)
                    ->orderBy('notification.created_at', 'desc')
                    ->paginate(15);
            }else{
                $notis = Notification::with('sender')
                    ->where('to_user_id', '=', Auth::user()->id)
                    ->where('read_status','=',false)
                    ->where('notification_type', '=', Request::get('type'))
                    ->orderBy('notification.created_at', 'desc')
                    ->paginate(15);
            }
        }else{
            $notis = Notification::with('sender')
                ->where('to_user_id', '=', Auth::user()->id)
                ->where('read_status','=',false)
                ->orderBy('notification.created_at', 'desc')
                ->paginate(15);
        }

        $results_noti = $notis->toArray();

        foreach($results_noti['data'] as &$value)
        {
            $tempTitle = $value['title'];
            switch ($value['notification_type']) {
                case "0"://Comment
                    $value['title'] = trans('messages.Notification.' . $tempTitle, array(), null, Auth::user()->lang);
                    break;
                case "1"://Like
                    $value['title'] = trans('messages.Notification.' . $tempTitle, array(), null, Auth::user()->lang);
                    break;
                case "2"://Complain
                    $data = json_decode($tempTitle, true);
                    if (isset($data['type']) && $data['type'] == 'comment') {
                        $value['title'] = $value['sender']['name'] . " " .trans('messages.Notification.complain_comment', $data, null, Auth::user()->lang);
                    }elseif($data['type'] == 'change_status'){
                        $data['status'] = trans('messages.Complain.' . $data['status']);
                        $value['title'] = trans('messages.Notification.complain_change_status', $data, null, Auth::user()->lang);
                    } elseif($data['type'] == 'complain_created') {
                        $value['title'] = trans('messages.Notification.complain_created_', array(), null, Auth::user()->lang);
                    } elseif ( $data['type']=='complain_created_by_juristic') {
                        $value['title'] = trans('messages.Notification.complain_created_by_juristic', $data, null,  Auth::user()->lang);
                    }
                    break;
                case "3"://Fees&Bills
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'sticker_approved') {
                        $value['title'] = trans('messages.Notification.sticker_approved_head', array(), null, Auth::user()->lang);
                    } elseif ($data['type'] == 'invoice_created') {
                        $value['title'] = trans('messages.Notification.invoice_created_msg', ['name' => $data['title']], null, Auth::user()->lang);
                    } elseif ($data['type'] == 'payment_notification') {
                        $value['title'] = trans('messages.Notification.payment_notify', ['in_no' => $data['invoice_no']], null, Auth::user()->lang);
                    }
                    break;
                case "4"://Event
                    $data = json_decode($tempTitle, true);
                    if( $data['type'] == 'event_created') {
                        $value['title'] = $value['sender']['name'] ." ". trans('messages.Notification.event_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    }
                    break;
                case "5"://Vote
                    $data = json_decode($tempTitle, true);
                    if( $data['type'] == 'vote_created') {
                        $value['title'] = $value['sender']['name'] ." ". trans('messages.Notification.vote_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    }
                    break;
                case "6"://Post&Parcel
                    $data = json_decode($tempTitle, true);
                    if( $data['type'] == 'receive_post_parcel') {
                        $value['title'] = trans('messages.Notification.receive_post_parcel_msg',$data, null, Auth::user()->lang);
                    }
                    break;
                case "7"://Other
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'general') {
                        $value['title'] = $data['n_title'];
                    } elseif ($data['type'] == 'sticker_approved') {
                        $value['title'] = trans('messages.Notification.sticker_approved_head', array(), null, Auth::user()->lang);
                    } elseif ($data['type'] == 'invoice_created') {
                        $value['title'] = trans('messages.Notification.invoice_created_msg', ['name' => $data['title']], null, Auth::user()->lang);
                    } elseif( $data['type'] == 'event_created') {
                        $value['title'] = $value['sender']['name'] ." ". trans('messages.Notification.event_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    } elseif( $data['type'] == 'vote_created') {
                        $value['title'] = $value['sender']['name'] ." ". trans('messages.Notification.vote_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    } elseif( $data['type'] == 'receive_post_parcel') {
                        $value['title'] = trans('messages.Notification.receive_post_parcel_msg',$data, null, Auth::user()->lang);
                    } elseif( $data['type'] == 'discussion_created') {
                        $value['title'] = trans('messages.Notification.discussion_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    } elseif( $data['type'] == 'discussion_comment') {
                        $value['title'] = trans('messages.Notification.discussion_comment_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    }
                    break;
                case "11"://Post Created
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'post_created') {
                        $value['title'] = trans('messages.Notification.post_created_msg', array(), null, Auth::user()->lang);
                    }
                    break;
                case "12"://Complete Bills Submit
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'transaction_complete') {
                        $value['title'] = trans('messages.Notification.transaction_complete_msg', ['name' => $data['title']], null, Auth::user()->lang);
                    }
                    break;
                case "14"://SOS Notification
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'order_arrived') {
                       $value['title'] = trans('messages.Notification.sos_order_arrived', ['order_no' => $data['order_no']], null, Auth::user()->lang);
                    }
                    if ($data['type'] == 'status_change') {
                        $status = trans('messages.MarketPlace.singha.status_'.$data['status'],null, Auth::user()->lang);
                        $value['title'] = trans('messages.Notification.sos_order_status_change', [
                            'order_no'  => $data['order_no'],
                            'status'    => $status
                        ], null, Auth::user()->lang);
                    }
                    if ($data['type'] == 'order_reset') {
                        $value['title'] = trans('messages.Notification.sos_order_reset', ['order_no'  => $data['order_no']], null, Auth::user()->lang);
                    }
                    break;
                default:
                    $value['title'] = $value['sender']['name'] . " " . trans('messages.Notification.' . $tempTitle, array(), null, Auth::user()->lang);
                    break;
            }
        }

        $message_count = Message::where('user_id',Auth::user()->id)->where('flag_new_from_admin', true)->count();
        $message_flag = true;
        if($message_count > 0){
            $message_flag = true;
        }else{
            $message_flag = false;
        }

        if(Auth::user()->role == 1 || Auth::user()->role == 3) {
            $unit_list = array(''=>'ยูนิต');
            $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->lists('unit_number','id')->toArray();

            $result = [
                'noti_counter_new' => $counter_noti,
                'noti_detail' => $results_noti,
                'unit_list' => $unit_list,
                'new_message' => $message_flag
            ];
            return response()->json($result);
        }
        else {
            $result = [
                'noti_counter_new' => $counter_noti,
                'noti_detail' => $results_noti,
                'new_message' => $message_flag
            ];
            return response()->json($result);
        }
    }

    public function notificationAll()
    {
        $feature = PropertyFeature::where('property_id',Auth::user()->property_id)->first();
        //$arr_feature = "";
        $arr_feature = array();
        $arr_feature[] = '11'; // Create Post
        if(isset($feature)){
            if($feature->menu_committee_room){
                $arr_feature[] = '7'; // create discussion
                $arr_feature[] = '8'; // Set Committee Room
                $arr_feature[] = '9'; // Unset Committee Room
            }

            if($feature->menu_event){
                $arr_feature[] = '4'; // Event
            }

            if($feature->menu_vote){
                $arr_feature[] = '5'; // Vote
            }

            if($feature->menu_common_fee){
                $arr_feature[] = '3'; // Fees&Bills
                $arr_feature[] = '12'; // Fees&Bills
            }

            if($feature->menu_complain){
                $arr_feature[] = '2'; // Complain
            }

            if($feature->menu_parcel){
                $arr_feature[] = '6'; // PostParcel
            }

            if($feature->market_place_singha){
                $arr_feature[] = '14'; // PostParcel
            }

        }else{

        }


        $all_noti = Notification::where('to_user_id','=',Auth::user()->id)
            ->whereIn('notification_type',$arr_feature)
            ->where('read_status','=',false)
            ->select('notification_type')->get();

        $counter_noti = [
            'comment' 	=> 0,
            'like'		=> 0,
            'complain'	=> 0,
            'bill'		=> 0,
            'event'		=> 0,
            'vote'		=> 0,
            'parcel'	=> 0,
            'other'		=> 0,
            'announcement'		=> 0,
            'total' => 0
        ];

        foreach ($all_noti as $noti) {
            $counter_noti['total']++;
            if( $noti->notification_type == 0 ) $counter_noti['comment']++;
            elseif( $noti->notification_type == 1 ) $counter_noti['like']++;
            elseif( $noti->notification_type == 2 ) $counter_noti['complain']++;
            elseif( $noti->notification_type == 3 ) $counter_noti['bill']++;
            elseif( $noti->notification_type == 4 ) $counter_noti['event']++;
            elseif( $noti->notification_type == 5 ) $counter_noti['vote']++;
            elseif( $noti->notification_type == 6 ) $counter_noti['parcel']++;
            elseif( $noti->notification_type == 7 || $noti->notification_type == 12) $counter_noti['other']++;
            elseif( $noti->notification_type == 11 ) $counter_noti['announcement']++;
            else ;
        }

        if(Request::get('type') != null) {
            if(Request::get('type') == "3") {
                $notis = Notification::with('sender')
                    ->whereIn('notification_type',['3','4','5','6','7','11','12'])
                    ->where('to_user_id', '=', Auth::user()->id)
                    ->where('read_status','=',false)
                    ->orderBy('notification.created_at', 'desc')
                    ->paginate(15);
            }else{
                $notis = Notification::with('sender')
                    ->whereIn('notification_type',$arr_feature)
                    ->where('to_user_id', '=', Auth::user()->id)
                    ->where('read_status','=',false)
                    ->where('notification_type', '=', Request::get('type'))
                    ->orderBy('notification.created_at', 'desc')
                    ->paginate(15);
            }
        }else{
            $notis = Notification::with('sender')
                ->whereIn('notification_type',$arr_feature)
                ->where('to_user_id', '=', Auth::user()->id)
                //->where('read_status','=',false)
                ->orderBy('notification.created_at', 'desc')
                ->paginate(15);
        }

        $results_noti = $notis->toArray();

        foreach($results_noti['data'] as &$value)
        {
            $tempTitle = $value['title'];
            switch ($value['notification_type']) {
                case "0"://Comment
                    $value['title'] = trans('messages.Notification.' . $tempTitle, array(), null, Auth::user()->lang);
                    break;
                case "1"://Like
                    $value['title'] = trans('messages.Notification.' . $tempTitle, array(), null, Auth::user()->lang);
                    break;
                case "2"://Complain
                    $data = json_decode($tempTitle, true);
                    if (isset($data['type']) && $data['type'] == 'comment') {
                        $value['title'] = $value['sender']['name'] . " " .trans('messages.Notification.complain_comment', $data, null, Auth::user()->lang);
                    }elseif($data['type'] == 'change_status'){
                        $data['status'] = trans('messages.Complain.' . $data['status']);
                        $value['title'] = trans('messages.Notification.complain_change_status', $data, null, Auth::user()->lang);
                    } elseif($data['type'] == 'complain_created') {
                        $value['title'] = trans('messages.Notification.complain_created_', array(), null, Auth::user()->lang);
                    } elseif ( $data['type']=='complain_created_by_juristic') {
                        $value['title'] = trans('messages.Notification.complain_created_by_juristic', $data, null,  Auth::user()->lang);
                    }
                    break;
                case "3"://Fees&Bills
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'sticker_approved') {
                        $value['title'] = trans('messages.Notification.sticker_approved_head', array(), null, Auth::user()->lang);
                    } elseif ($data['type'] == 'invoice_created') {
                        $value['title'] = trans('messages.Notification.invoice_created_msg', ['name' => $data['title']], null, Auth::user()->lang);
                    } elseif ($data['type'] == 'payment_notification') {
                        $value['title'] = trans('messages.Notification.payment_notify', ['in_no' => $data['invoice_no']], null, Auth::user()->lang);
                    }
                    break;
                case "4"://Event
                    $data = json_decode($tempTitle, true);
                    if( $data['type'] == 'event_created') {
                        $value['title'] = $value['sender']['name'] ." ". trans('messages.Notification.event_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    }
                    break;
                case "5"://Vote
                    $data = json_decode($tempTitle, true);
                    if( $data['type'] == 'vote_created') {
                        $value['title'] = $value['sender']['name'] ." ". trans('messages.Notification.vote_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    }
                    break;
                case "6"://Post&Parcel
                    $data = json_decode($tempTitle, true);
                    if( $data['type'] == 'receive_post_parcel') {
                        $value['title'] = trans('messages.Notification.receive_post_parcel_msg',$data, null, Auth::user()->lang);
                    }
                    break;
                case "7"://Other
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'general') {
                        $value['title'] = $data['n_title'];
                    } elseif ($data['type'] == 'sticker_approved') {
                        $value['title'] = trans('messages.Notification.sticker_approved_head', array(), null, Auth::user()->lang);
                    } elseif ($data['type'] == 'invoice_created') {
                        $value['title'] = trans('messages.Notification.invoice_created_msg', ['name' => $data['title']], null, Auth::user()->lang);
                    } elseif( $data['type'] == 'event_created') {
                        $value['title'] = $value['sender']['name'] ." ". trans('messages.Notification.event_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    } elseif( $data['type'] == 'vote_created') {
                        $value['title'] = $value['sender']['name'] ." ". trans('messages.Notification.vote_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    } elseif( $data['type'] == 'receive_post_parcel') {
                        $value['title'] = trans('messages.Notification.receive_post_parcel_msg',$data, null, Auth::user()->lang);
                    } elseif( $data['type'] == 'discussion_created') {
                        $value['title'] = trans('messages.Notification.discussion_created_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    } elseif( $data['type'] == 'discussion_comment') {
                        $value['title'] = trans('messages.Notification.discussion_comment_msg',['name'=> $data['title']], null, Auth::user()->lang);
                    }
                    break;
                case "11"://Post Created
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'post_created') {
                        $value['title'] = trans('messages.Notification.post_created_msg', array(), null, Auth::user()->lang);
                    }
                    break;
                case "12"://Complete Bills Submit
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'transaction_complete') {
                        $value['title'] = trans('messages.Notification.transaction_complete_msg', ['name' => $data['title']], null, Auth::user()->lang);
                    }
                    break;
                case "14"://SOS Notification
                    $data = json_decode($tempTitle, true);
                    if ($data['type'] == 'order_arrived') {
                        $value['title'] = trans('messages.Notification.sos_order_arrived', ['order_no' => $data['order_no']], null, Auth::user()->lang);
                    }
                    if ($data['type'] == 'status_change') {
                        $status = trans('messages.MarketPlace.singha.status_'.$data['status'],null, Auth::user()->lang);
                        $value['title'] = trans('messages.Notification.sos_order_status_change', [
                            'order_no'  => $data['order_no'],
                            'status'    => $status
                        ], null, Auth::user()->lang);
                    }
                    if ($data['type'] == 'order_reset') {
                        $value['title'] = trans('messages.Notification.sos_order_reset', ['order_no'  => $data['order_no']], null, Auth::user()->lang);
                    }
                    break;
                default:
                    $value['title'] = $value['sender']['name'] . " " . trans('messages.Notification.' . $tempTitle, array(), null, Auth::user()->lang);
                    break;
            }
        }

        $message_count = Message::where('user_id',Auth::user()->id)->where('flag_new_from_admin', true)->count();
        $message_flag = true;
        if($message_count > 0){
            $message_flag = true;
        }else{
            $message_flag = false;
        }

        if(Auth::user()->role == 1 || Auth::user()->role == 3) {
            $unit_list = array(''=>'ยูนิต');
            $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->lists('unit_number','id')->toArray();

            $result = [
                'noti_counter_new' => $counter_noti,
                'noti_detail' => $results_noti,
                'unit_list' => $unit_list,
                'new_message' => $message_flag
            ];
            return response()->json($result);
        }
        else {
            $result = [
                'noti_counter_new' => $counter_noti,
                'noti_detail' => $results_noti,
                'new_message' => $message_flag
            ];
            return response()->json($result);
        }
    }

    public function markAsRead () {
        if(Request::get('nid') != null) {
            $notis = Notification::find(Request::get('nid'));
            $notis->read_status = true;
            $notis->save();
            return response()->json(['status'=>true]);
        }else{
            return response()->json(['status'=>false]);
        }
    }

    public function softdelete()
    {
        $noti_id =Request::get('noti_id');
        $check = $this->isValidUuid($noti_id); 
        $user_id = auth()->user()->id;
        if($noti_id =="deall"){ 
            //delete all
            $delete_all = Notification::where('to_user_id',$user_id)->get();
            foreach($delete_all as $key=>$row)
            {
                $delete = Notification::find($row->id);
                $delete->delete();
            }
            return response()->json(['status'=>true,'message'=>'success']);
        }
        if($check == false)
        { 
            return response()->json(['status'=>false,'message'=>'Request Notification ID.']);
        }else{
                $delete = Notification::find($noti_id);
                $delete->delete();
                return response()->json(['status'=>true,'message'=>'success']);
        }
    } 

    function isValidUuid( $uuid ) {
    
        if (!is_string($uuid) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1)) {
            return false;
        }
    
        return true;
    }

}
