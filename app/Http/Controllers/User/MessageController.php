<?php namespace App\Http\Controllers\User;
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
		$this->middleware('auth:menu_message');
		view()->share('active_menu', 'messages');
	}

	public function index () {
		$message = $this->checkExistdMessage ();
		$property = Property::find(Auth::user()->property_id);
		if($message) {
			$messageTexts = MessageText::where('message_id',$message->id)->orderBy('created_at','desc')->paginate(5);
		}
		if(Request::ajax()) {
			return view('message.message-page')->with(compact('messageTexts'));
		} else {
			// set read
			if($message) {
				$message->flag_new_from_admin 	= false;
				$message->save();
				// unflag new message
				$flag_new_message = Message::where('user_id',Auth::user()->id)->where('flag_new_from_admin',true)->first();
				$flag_new_message = $flag_new_message?true:false;
				view()->share('flag_new_message',$flag_new_message);
			}
			return view('message.user-index')->with(compact('messageTexts'));
		}
	}

	public function sendMessage () {
		if(Request::isMethod('post')) {
			$message = $this->checkExistdMessage();
			if(!$message) {
				$message = Message::create([
					'user_id' 			=> Auth::user()->id,
					'property_id' 		=> Auth::user()->property_id,
				]);
			}
			$messageText = new messageText;
			$messageText->message_id 	= $message->id;
			$messageText->user_id 		= Auth::user()->id;
			$messageText->text			= Request::get('text');
			$messageText->save();
			$message->flag_new_from_user 	= true;
			$message->last_user_message_date = date('Y-m-d H:i:s');
			$message->save(); //Update updated_at
			//$this->userSendMessageNotification($messageText);
			return response()->json(['r'=>true]);
		}
	}

	public function checkExistdMessage () {
		$message = Message::where('user_id',Auth::user()->id)->first();
		return $message;
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
		if(strlen($text) > 80 ) {
			return mb_substr($text,0,80)."...";
		} else return $text;
	}
}
