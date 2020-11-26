<?php namespace App\Http\Controllers\API\v2;
use Auth;
use Request;
use Illuminate\Routing\Controller;
use App\Http\Controllers\PushNotificationController;
# Model
use App\User;
use Storage;
use File;
use App\Notification;
use App\Message;
use App\MessageText;
use App\Property;
use App\MessageTextFile;
use Vinkla\Pusher\Facades\Pusher;

class MessageController extends Controller {

	public function __construct () {
        $this->middleware('jwt.feature_menu:menu_message');
    }

    public function listAllMessage () {

       
	    $message = Message::where('user_id',Auth::user()->id)->first();

		if($message) {
			$messageTexts = MessageText::with('owner','messageTextFile')->where('message_id',$message->id)->orderBy('created_at','desc')->paginate(30);

            if ($messageTexts->count()){
                foreach ($messageTexts as $m) {
                    if ($m->is_admin_reply && !$m->read_status) {
                        $m->read_status = true;
                        $m->save();
                    }
                }
            }
			
			$results = $messageTexts->toArray();

            foreach ($results['data'] as &$item){
                if($item['message_text_file'] != null) {
                    foreach ($item['message_text_file'] as &$item_img){
                        $item_img['image_full_path'] = env('URL_S3') . '/messages-file/' . $item_img['url'] . $item_img['name'];
                    }
                }

                if($item['owner']['profile_pic_name'] != null){
                    $item['owner']['profile_full_path'] = env('URL_S3')."/profile-img/".$item['owner']['profile_pic_path'].$item['owner']['profile_pic_name'];
                }else{
                    $item['owner']['profile_full_path'] ="";
                }
            }

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
                // dd($message);
                if (!$message) {
                    $message = Message::create([
                        'user_id' => Auth::user()->id,
                        'property_id' => Auth::user()->property_id
                    ]);
                }
                $messageText = new MessageText;
                $messageText->message_id = $message->id;
                $messageText->user_id = Auth::user()->id;
                $messageText->text = Request::get('message_text');
                $messageText->save();
                
                // dd($messageText);
                $message->flag_new_from_user 	= true;
                $message->last_user_message_date = date('Y-m-d H:i:s');
                $message->save(); //Update updated_at
                $this->saveAttachment($messageText);
                $this->pusherChat($message->property_id,$messageText,$message->id);
              
                return response()->json(['status' => true]);
            }
        }catch(Exception $ex) {
            return response()->json(['status'=>false]);
        }
    }

    public function saveAttachment ($messageText) {
        if(!empty(Request::file('attachment'))) {
            $attach = [];
            foreach (Request::file('attachment') as $file) {
                $name =  md5($file->getFilename());
                $extension = $file->getClientOriginalExtension();
                $targetName = $name.".".$extension;

                $path = $this->createLoadBalanceDir($file);
                $isImage = 0;
                if(in_array($extension, ['jpeg','jpg','gif','png'])) {
                    $isImage = 1;
                }
                //Move Image
                $attach[] = new MessageTextFile([
                    'name' => $targetName,
                    'url' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'is_image'	=> $isImage,
                    'original_name'	=> $file->getClientOriginalName()
                ]);
            }
            $messageText->messageTextFile()->saveMany($attach);
        }
    }

    public function createLoadBalanceDir ($imageFile) {
        $name =  md5($imageFile->getFilename());
        $extension = $imageFile->getClientOriginalExtension();
        $targetName = $name.".".$extension;

        $folder = substr($name, 0,2);

        $pic_folder = 'messages-file'.DIRECTORY_SEPARATOR.$folder;
        $directories = Storage::disk('s3')->directories('messages-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }

        $full_path_upload = $pic_folder.DIRECTORY_SEPARATOR.$targetName;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($imageFile), 'public');// public set in photo upload
        if($upload){
            // Success
            File::delete($imageFile);
        }

        return $folder."/";
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
		if(strlen($text) > 30 ) {
			return substr($text,30)."...";
		} else return $text;
    }
    
    public function pusherChat($property_id,$chat_obj,$message_room_id){

        
        if($chat_obj->messageTextFile != null) {
            foreach ($chat_obj->messageTextFile as &$item_img){
                $item_img['image_full_path'] = env('URL_S3') . '/messages-file/' . $item_img['url'] . $item_img['name'];
            }
        }else{
            // Nothing to do
        }

        $chat_obj_return = $chat_obj;
        $chat_obj_return->owner = $chat_obj->owner;
        $chat_obj_return->messageTextFile = $chat_obj->messageTextFile;

        $message_text_arr = $chat_obj_return->toArray();

        Pusher::trigger($message_room_id, 'chat_message', $message_text_arr);

        $dataPusher = [
            'title' => trans('messages.Message.receive_message'),
            'notification' => [
                'set_read'      => false,
                'sticky'        => true,
                'target_menu'   => 'chat',
                'subject_key'    => $chat_obj->message_id
            ]
        ];

        Pusher::trigger($property_id, 'notification_event', $dataPusher);

        return "true";
    }
}
