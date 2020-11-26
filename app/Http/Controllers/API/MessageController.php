<?php namespace App\Http\Controllers\API;
use Auth;
use Request;
use Illuminate\Routing\Controller;
use App\Http\Controllers\PushNotificationController;
# Model
use App\User;
use App\Notification;
use App\Message;
use App\MessageText;
use App\Property;

class MessageController extends Controller {

	public function __construct () {
        $this->middleware('jwt.feature_menu:menu_message');
	}

	public function index () {
		$message = $this->checkExistdMessage ();

		if($message) {
			$messageTexts = MessageText::with('owner')->where('message_id',$message->id)->orderBy('created_at','desc')->paginate(10);
            $results = $messageTexts->toArray();

            $message->flag_new_from_admin 	= false;
            $message->save();
        }else{
            $results = array(
                "data" => array(),
                "message" => "Empty message"
            );
        }

        return response()->json($results);
	}

    public function listAllMessage () {
        $message = $this->checkExistdMessage ();

        if($message) {
            $messageTexts = MessageText::with('owner')->where('message_id',$message->id)->orderBy('created_at','desc')->get();
            //$results = $messageTexts->toArray();
            $results = array(
                "data" => $messageTexts->toArray()
            );
            $message->flag_new_from_admin 	= false;
            $message->save();

        }else{
            $results = array(
                "data" => array(),
                "message" => "Empty message"
            );
        }

        return response()->json($results);
    }

	public function sendMessage () {
        try {
            if (Request::isMethod('post')) {
                $message = $this->checkExistdMessage();
                if (!$message) {
                    $message = Message::create([
                        'user_id' => Auth::user()->id,
                        'property_id' => Auth::user()->property_id
                    ]);
                }
                $messageText = new messageText;
                $messageText->message_id = $message->id;
                $messageText->user_id = Auth::user()->id;
                $messageText->text = Request::get('message_text');
                $messageText->save();

                $message->flag_new_from_user 	= true;
                $message->last_user_message_date = date('Y-m-d H:i:s');
                $message->save(); //Update updated_at
                //$this->userSendMessageNotification($messageText = array());

                return response()->json(['status' => true]);
            }
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
	}

	public function checkExistdMessage () {
		$message = Message::where('user_id',Auth::user()->id)->first();
		return $message;
	}

    public function checkHaveNewMessage () {
        $message_count = Message::where('user_id',Auth::user()->id)->where('flag_new_from_admin', true)->count();
        if($message_count > 0){
            return response()->json(['status' => true]);
        }

        return response()->json(['status' => false]);
    }

	public function userSendMessageNotification($messageText) {
		$admins = $this->getAdmin();
		$title = json_encode( ['type'=>'message_sent','text'=> $this->cutText ($messageText->text) ] );
		if( $admins->count() ) {
			foreach ($admins as $admin) {
				$notification = Notification::create([
					'title'				=> $title,
					'description' 		=> "",
					'notification_type' => 8,
					'subject_key'		=> $messageText->message_id,
					'to_user_id'		=> $admin->id,
					'from_user_id'		=> Auth::user()->id
				]);
				//$controller_push_noti = new PushNotificationController();
				//$controller_push_noti->pushNotification($notification->id);
			}
		}
	}

	public function getAdmin () {
		return User::where('property_id',Auth::user()->property_id)
				->where(function ($q) {
					 $q	->where('role',1)
					 	->whereOr('role',3);
				})->get();
	}

	public function cutText ($text) {
		if(strlen($text) > 30 ) {
			return substr($text,30)."...";
		} else return $text;
	}
}