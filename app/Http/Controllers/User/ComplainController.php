<?php namespace App\Http\Controllers\User;
use Auth;
use File;
use Request;
use Storage;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use Vinkla\Pusher\Facades\Pusher;
# Model
use App\Complain;
use App\ComplainCategory;
use App\ComplainFile;
use App\ComplainComment;
use App\Notification;
use App\User;
use App\ComplainAction;

class ComplainController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_complain');
		view()->share('active_menu', 'complain');
	}

	public function complain () {
		$complains = Complain::with('category','comments')->where('user_id','=',Auth::user()->id)->get()->sortByDesc('created_at');
		$count_new = Complain::where('user_id','=',Auth::user()->id)->where('complain_status','=',0)->count();
		$c_cate = ComplainCategory::all()->sortBy('id');
		return view('complain.user-index')->with(compact('c_cate','complains','count_new'));
	}

	public function add () {
		if(Request::isMethod('post')) {
            $is_appointment = Request::get('is_appointment') != null ? true : false;
            $is_deposit_key = Request::get('is_deposit_key') == "true" ? true : false;
            $is_juristic_complain = Request::get('is_juristic_complain') == "true" ? true : false;

			$complain = new Complain;
			$complain->fill(Request::all());
			$complain->user_id 			= Auth::user()->id;
			$complain->property_id 		= Auth::user()->property_id;
			$complain->property_unit_id = Auth::user()->property_unit_id;
			$complain->complain_status 	= 0;
			$complain->is_juristic_complain = $is_juristic_complain;
			$complain->is_appointment = $is_appointment;
			$complain->is_deposit_key = $is_deposit_key;
			$complain->user_appointment_note = Request::get('user_appointment_note');

			$complain->save();

			if(!empty(Request::get('attachment'))) {
				$attach = [];
				foreach (Request::get('attachment') as $key => $file) {
					//Move Image
					$path = $this->createLoadBalanceDir($file['name']);
					$attach[] = new ComplainFile([
							'name' => $file['name'],
							'url' => $path,
							'file_type' => $file['mime'],
							'is_image'	=> $file['isImage'],
							'original_name'	=> $file['originalName']
					]);
				}
				$complain->attachment_count = ++$key;
				$complain->save();
				$complain->complainFile()->saveMany($attach);
			}
			$this->addCreateComplainNotification($complain);
			return redirect('complain');
		}
	}

	public function changeStatus () {
		if(Request::isMethod('post')) {
			//dd(Request::all());
			$status = Request::get('status');
			$complain = Complain::find(Request::get('cid'));
			//dd($complain->toArray());
			if($complain && $complain->complain_status == 2) {
				// save complain action time stamp
				$ca = new ComplainAction;
				$ca->saveAction($complain,$status);

				$complain->complain_status = $status;
				if(Request::get('rating') != ""){
                    $complain->review_rate = Request::get('rating');
                }
                if(Request::get('review_comment') != ""){
                    $complain->review_comment = Request::get('review_comment');
                }
				$complain->save();
				
				if($status == 0) {
					$comment = new ComplainComment([
						'description' 	=> Request::get('comment'),
						'user_id'		=> Auth::user()->id,
						'is_reject'		=> true
					]);
					$complain->comments()->save($comment);
				}
			}

			$this->addChangeStatusComplainNotification($complain);
		}
		return redirect('complain');
	}


	public function view($id) {
		$this->markAsRead($id);
		$complain = Complain::with('owner','category','complainFile','comments')->find($id);

		$notis = Notification::where('to_user_id','=',Auth::user()->id)->where('subject_key','=',$id)->first();
		if(isset($notis)){
			$notification_update = Notification::find($notis->id);
			$notification_update->read_status = true;
			$notification_update->save();
		}

		return view('complain.user-view')->with(compact('complain'));
	}

	public function addComment () {
		if (Request::isMethod('post')) {
			$comment = new ComplainComment([
				'description' 	=> Request::get('comment'),
				'user_id'		=> Auth::user()->id
			]);
			$complain = Complain::with('owner')->find(Request::get('cid'));
			if($complain) {
				$comment = $complain->comments()->save($comment);
				//$isOwner = ($complain->user_id == Auth::user()->id);
				$comments 	= ComplainComment::with('owner')->where('complain_id','=', Request::get('cid'))->get()->sortBy('created_at');
				$contents 	= view('complain.render_comment')->with(compact('comments'))->render();
				//Add Notification
				if( $complain->user_id != Auth::user()->id && $complain->owner->notification ) {
					$this->addComplainCommentNotification ($complain);
				}

				if( $complain->created_by_admin && !$complain->is_juristic_complain ) {
					$this->addCommplainByAdminCommentNotification ($complain);
				}

				$status = true;
			} else {
				$status = false;
			}
			return response()->json(['status' => $status,'content'=>$contents]);
		}
	}

	public function getAttach ($id) {
		$file = ComplainFile::find($id);
        $file_path = 'complain-file'.'/'.$file->url.$file->name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            $response = response(Storage::disk('s3')->get($file_path), 200, [
                'Content-Type' => $file->file_type,
                'Content-Length' => Storage::disk('s3')->size($file_path),
                'Content-Description' => 'File Transfer',
                'Content-Disposition' => "attachment; filename={$file->original_name}",
                'Content-Transfer-Encoding' => 'binary',
            ]);
            ob_end_clean();
            return $response;
        }
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'complain-file/'.$folder;
        $directories = Storage::disk('s3')->directories('complain-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function addComplainCommentNotification($complain) {
		$title = json_encode( ['type'=>'comment','c_title'=>$complain->title] );
		$notification = Notification::create([
			'title'				=> $title,
			'description' 		=> "",
			'notification_type' => '2',
			'subject_key'		=> $complain->id,
			'to_user_id'		=> $complain->user_id,
			'from_user_id'		=> Auth::user()->id
		]);
		$controller_push_noti = new PushNotificationController();
        $controller_push_noti->pushNotification($notification->id);

		$textNoti = $this->convertTitleTolongTxt($notification);

		$dataPusher = [
			'title'			=> $textNoti,
			'notification'  => $notification
		];

		Pusher::trigger(Auth::user()->property_id."_".$complain->user_id, 'notification_event', $dataPusher);
	}

	public function addCommplainByAdminCommentNotification($complain) {
		$title = json_encode( ['type'=>'comment','c_title'=>$complain->title] );
		$users = User::where('property_unit_id',$complain->property_unit_id)->whereNull('verification_code')->where('active', true)->get();
		foreach ($users as $user) {
			$notification = Notification::create([
				'title'				=> $title,
				'description' 		=> "",
				'notification_type' => '2',
				'subject_key'		=> $complain->id,
				'to_user_id'		=> $user->id,
				'from_user_id'		=> Auth::user()->id
			]);
			$controller_push_noti = new PushNotificationController();
			$controller_push_noti->pushNotification($notification->id);
		}

	}

	public function addCreateComplainNotification($complain) {
		$users = $this->getChief ();
		if($users->count()) {
			$title = json_encode( ['type'=>'complain_created','c_title'=>$complain->title] );
			foreach ($users as $key=>$user) {
				$notification = Notification::create([
					'title'				=> $title,
					'description' 		=> "",
					'notification_type' => '2',
					'subject_key'		=> $complain->id,
					'to_user_id'		=> $user->id,
					'from_user_id'		=> Auth::user()->id
				]);
				$controller_push_noti = new PushNotificationController();
        		$controller_push_noti->pushNotification($notification->id);

				$textNoti = $this->convertTitleTolongTxt($notification);

				$dataPusher = [
					'title' => $textNoti,
					'notification' => $notification
				];

				Pusher::trigger(Auth::user()->property_id."_".$user->id, 'notification_event', $dataPusher);
			}

		}
	}

	public function addChangeStatusComplainNotification($complain) {
		$users = $this->getChief ();
		if($users->count()) {
			$status = ['status_rj','status_ip','status_ck','status_cf','status_cls'];
			$title = json_encode( ['type'=>'change_status','c_title'=>$complain->title,'status' => $status[$complain->complain_status]] );
			foreach ($users as $key=>$user) {
				$notification = Notification::create([
					'title'				=> $title,
					'description' 		=> "",
					'notification_type' => '2',
					'subject_key'		=> $complain->id,
					'to_user_id'		=> $user->id,
					'from_user_id'		=> Auth::user()->id
				]);
				$controller_push_noti = new PushNotificationController();
        		$controller_push_noti->pushNotification($notification->id);

				$textNoti = $this->convertTitleTolongTxt($notification);

				$dataPusher = [
					'title' => $textNoti,
					'notification' => $notification
				];

				// Channel is PropertyId + _ + UserId
				Pusher::trigger(Auth::user()->property_id."_".$user->id, 'notification_event', $dataPusher);
			}

		}
	}

	public function getChief () {
		/*return User::where('property_id',Auth::user()->property_id)
				->where(function ($q) {
					 $q ->where('role',1)
					 	->orWhere('is_chief',true);
				})->get();*/
		return User::where('property_id',Auth::user()->property_id)->whereIn('role', array(1,3))->get();
	}

	function convertTitleTolongTxt($notification){
		$data = json_decode($notification->title, true);
		$message_string = "";
		if ($data['type'] == 'comment') {
			$message_string = $notification->sender->name." ".trans('messages.Notification.complain_comment',$data);
		}elseif($data['type'] == 'change_status'){
			$data['status'] = trans('messages.Complain.'.$data['status']);
			$message_string = trans('messages.Notification.complain_change_status',$data);
		} elseif($data['type'] == 'complain_created') {
			$message_string = $notification->sender->name." ".trans('messages.Notification.complain_created_');
		}

		return $message_string;
	}

	public function markAsRead ($id) {
		try {
			$notis_counter = Notification::where('subject_key', '=', $id)->where('to_user_id', '=', Auth::user()->id)->get();
			if ($notis_counter->count() > 0) {
				foreach ($notis_counter as $item) {
					$notis = Notification::find($item->id);
					$notis->read_status = true;
					$notis->save();
				}
			}
			return true;
		}catch(Exception $ex){
			return false;
		}
	}
}
