<?php namespace App\Http\Controllers\PropertyAdmin;
use Auth;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\PushNotificationController;
use Vinkla\Pusher\Facades\Pusher;
# Model
use App\User;
use App\Notification;
use App\Message;
use App\MessageText;
use App\PropertyUnit;
use App\PropertyMember;
use App\MessageTextFile;
use DB;

class MessageController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_message');
		view()->share('active_menu', 'messages');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function index () {
		$latest_message = Message::with('owner');

        $latest_message->has('hasText');

        if( Request::get('keyword') ) {
            $key = Request::get('keyword');
            $latest_message->whereHas('hasText', function ($q) use($key) {
                $q->where('text','like','%'.$key.'%');
            });
        }

        $latest_message = $latest_message->where('property_id',Auth::user()->property_id)->orderBy('updated_at', 'desc')->orderBy('last_user_message_date','desc')
            ->paginate(20);

		if(Request::ajax()) {
			return view('message.admin-user-message-list')->with(compact('latest_message'));
		} else {
            $members 	= PropertyMember::where('property_id',Auth::user()->property_id)
            ->where(function ($q) {
                $q->where('property_id',Auth::user()->property_id)
                    ->whereNotNull('property_unit_id')
                    ->where('active',true);
            })
            ->where('role',2)
            ->orderBy('created_at','DESC')
            ->paginate(20);

			$unit_list = array(''=> trans('messages.unit_no') );
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
			return view('message.admin-index')->with(compact('latest_message','members','unit_list'));
		}
	}

	public function view ($id) {
		$message 	= Message::where('property_id',Auth::user()->property_id)->where('id',$id)->first();
		if( $message ) {
		    $latest_unread = MessageText::where('message_id',$id)
                            ->where('is_admin_reply',false)
                            ->where('read_status', false)
                            ->orderBy('created_at','asc')->first();
		    if( $latest_unread ) {
                $messageTexts = MessageText::where('message_id',$id)->where('created_at', '>=',$latest_unread->created_at)->orderBy('created_at','desc')->paginate(5);//->get();
                if ($messageTexts->count()){
                    foreach ($messageTexts as $m) {
                        if (!$m->is_admin_reply && !$m->read_status) {
                            $m->read_status = true;
                            $m->save();
                        }
                    }
                }
            } else {
		        $date = date('Y-m-d H:i:s');
                $messageTexts = MessageText::where('message_id',$id)->where('created_at', '<=',$date)->orderBy('created_at','desc')->paginate(5);
            }
		}
		if(Request::ajax()) {
			return view('message.admin-message-page')->with(compact('messageTexts','message'));
		} else {
			if($message) {
				$message->flag_new_from_user 	= false;
				$message->save();
				// unflag new message
				$flag_new_message = Message::where('property_id',Auth::user()->property_id)->where('flag_new_from_user',true)->first();
				$flag_new_message = $flag_new_message?true:false;
				view()->share('flag_new_message',$flag_new_message);
			} else {
			    return redirect()->back();
            }
            $user       = User::where('property_id', Auth::user()->property_id)->find($message->user_id);
			return view('message.admin-view-message')->with(compact('messageTexts','message','user'));
		}
	}

	public function oldMessagePage () {
		$message 	= Message::find(Request::get('mid'));
		if( $message ) {
            if (Request::get('lm-date')) {
                $date = Request::get('lm-date');
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $messageTexts = MessageText::where('message_id', Request::get('mid'))->where('created_at', '<', $date)->orderBy('created_at', 'desc')->take(5)->get();
            if ($messageTexts->count()){
                foreach ($messageTexts as $m) {
                    if (!$m->is_admin_reply && !$m->read_status) {
                        $m->read_status = true;
                        $m->save();
                    }
                }
            }
            return view('message.admin-old-message-page')->with(compact('messageTexts','message'));
		}
	}

	public function sendMessage () {
		if(Request::isMethod('post')) {
            $text_box = '';
			$message = Message::find(Request::get('mid'));
			if($message) {
				$messageText = new messageText;
				$messageText->message_id 	= $message->id;
				$messageText->user_id 		= Auth::user()->id;
				$messageText->text			= Request::get('text');
				$messageText->is_admin_reply 	= true;
                $messageText->save();
                
                $message->flag_new_from_admin = true;
				$message->save();
                $this->saveAttachment($messageText);

                //Send Push Notification to user
                $controller_push_noti = new PushNotificationController();
                $controller_push_noti->pushNotificationMessageSend($message->user_id);


                if($messageText->messageTextFile != null) {
                    foreach ($messageText->messageTextFile as &$item_img){
                        $item_img['image_full_path'] = env('URL_S3') . '/messages-file/' . $item_img['url'] . $item_img['name'];
                    }
                }

                $chat_obj_return = $messageText;
                $chat_obj_return->owner = $messageText->owner;
                $chat_obj_return->messageTextFile = $messageText->messageTextFile;

                $message_text_arr = $chat_obj_return->toArray();

                Pusher::trigger(Request::get('mid'), 'chat_message', $message_text_arr);

			}
			return response()->json(['r'=>true,'box' => $text_box]);
		}
	}

	public function renderMessageClient(){
        $message_text_id = Request::get('message_text_id');
        $message_text = messageText::find($message_text_id);
        $text_box = $this->renderMessage($message_text);
        return response()->json(['r'=>true,'box' => $text_box]);
    }

	public function sendUserMessage () {

		if(Request::isMethod('post')) {
            $user = User::find(Request::get('uid'));
			if($user) {
				$message = $this->checkExistedMessage(Request::get('uid'));
				if(!$message) {
					$message = Message::create([
						'user_id' 			=> $user->id,
						'property_id' 		=> Auth::user()->property_id
					]);
				}
				if($message) {
					$messageText = new messageText;
					$messageText->message_id 	= $message->id;
					$messageText->user_id 		= Auth::user()->id;
					$messageText->text			= Request::get('text');
					$messageText->is_admin_reply 	= true;
					$messageText->save();
					$message->flag_new_from_admin = true;

                    //Send Push Notification to user
                    //$controller_push_noti = new PushNotificationController();
                    //$controller_push_noti->pushNotificationMessageSend($message->user_id);

					$message->save();
					$this->saveAttachment($messageText);
                    $text_box = $this->renderMessage($messageText);
				}

                return response()->json(['r'=>true,'mid' => $message->id,'box' => $text_box]);
			}
            return response()->json(['r'=>false]);
		}
	}

	public function sendNewMessage ($uid) {
	    
        $user       = User::where('property_id', Auth::user()->property_id)->find($uid);
        $message    = $this->checkExistedMessage($uid);

        if( $message ) {
            return redirect('admin/messages/view/'.$message->id);
        } else {
            $messageTexts = null;
        }
        return view('message.admin-new-message')->with(compact('messageTexts','message','user'));
    }


	public function adminSendMessageNotification($messageText,$message) {
		$title = json_encode( ['type'=>'message_reply','text'=> $this->cutText ($messageText->text) ] );
		$notification = Notification::create([
			'title'				=> $title,
			'description' 		=> "",
			'notification_type' => 8,
			'subject_key'		=> $messageText->message_id,
			'to_user_id'		=> $message->user_id,
			'from_user_id'		=> Auth::user()->id
		]);
		//$controller_push_noti = new PushNotificationController();
		//$controller_push_noti->pushNotification($notification->id);
	}

	public function cutText ($text) {
		if(strlen($text) > 80 ) {
			return mb_substr($text,0,80)."...";
		} else return $text;
	}

	public function memberlistPage () {
		if(Request::ajax()) {
            $unit_list = array(''=> trans('messages.unit_no') );
            $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();

            $members 	= PropertyMember::where(function ($q) {
                $q->where('property_id',Auth::user()->property_id)
                    ->whereNotNull('property_unit_id')
                    ->where('active',true);
            });
            

			if(Request::get('unit_id')) {
                $unit = Request::get('unit_id');
                $members = $members->where('property_unit_id',$unit)->where('active',true);
			}

			if(Request::get('name')) {
                $members = $members->where('name','like',"%".Request::get('name')."%");
			}

			$members = $members->where('role',2)
            ->orderBy('created_at','DESC')
            ->paginate(20);
			return view('message.user-list')->with(compact('members','unit_list'));
		}
	}

	public function checkExistedMessage ($uid) {
		$message = Message::where('user_id',$uid)->where('property_id',Auth::user()->property_id)->first();
		return $message;
	}

    public function saveAttachment ($messageText) {
        if(!empty(Request::get('attachment'))) {
            $attach = [];
            foreach (Request::get('attachment') as $key => $file) {
                //Move Image
                $path = $this->createLoadBalanceDir($file['name']);
                $attach[] = new MessageTextFile([
                    'name' => $file['name'],
                    'url' => $path,
                    'file_type' => $file['mime'],
                    'is_image'	=> $file['isImage'],
                    'original_name'	=> $file['originalName']
                ]);
            }
            $messageText->messageTextFile()->saveMany($attach);
        }
    }

    public function createLoadBalanceDir ($name) {
        $targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
        $folder = substr($name, 0,2);
        $pic_folder = 'messages-file/'.$folder;
        $directories = Storage::disk('s3')->directories('messages-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
        return $folder."/";
    }

    public function renderMessage ($message) {
        $message->load('messageTextFile');
        $s = linkify($message->text);
        if($message->is_admin_reply) $class = 'admin-reply'; else $class = 'user-reply';
	    $box = '<div class="col-md-12">
                <div class="message '.$class.'">
                    <p>'.nl2br($s).'</p>';

                if( $message->messageTextFile ) {
                    $box .= '<div>';
                    foreach ($message->messageTextFile as $file) {
                        $box .= '<a class="fancybox" rel="gal-' . $message->message_id . '" href="' . env('URL_S3') . '/messages-file/' . $file->url . $file->name . '">
                                <img src="' . env('URL_S3') . '/messages-file/' . $file->url . $file->name . '" alt="album-image" /></a>';
                    }
                    $box .= '</div>';
                }
        $box .= '<time>'.chatTime($message->created_at).'</time>';

        if( $message->is_admin_reply ) {
            $box .= '<span class="res-by">'.$message->owner->name.'</span>';
        }

        $box .= '</div></div>';
        return $box;
    }
}
