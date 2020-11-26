<?php namespace App\Http\Controllers\ChiefAdmin;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
# Model
use App\Discussion;
use App\DiscussionComment;
use App\DiscussionFile;
use App\PostReport;
use App\Notification;
use App\User;

class DiscussionController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_committee_room');
		view()->share('active_menu', 'discussion');
		if(Auth::check() && (Auth::user()->role == 2 && !Auth::user()->is_chief)) Redirect::to('feed')->send();
	}

	public function discussion () {
		if(Request::ajax()) {
			$discussions = Discussion::with('owner')->where('property_id','=',Auth::user()->property_id);
			$discussions = $discussions->orderBy('created_at','DESC')->paginate(30);
			return view('discussion.discussion-list')->with(compact('discussions'));
		} else {
			$discussions = Discussion::with('owner')->where('property_id','=',Auth::user()->property_id)->orderBy('created_at','DESC')->paginate(30);
			$count_new = Discussion::where('property_id','=',Auth::user()->property_id)->count();
			return view('discussion.index')->with(compact('discussions','count_new'));
		}
	}

	public function add () {
		if(Request::isMethod('post')) {
			$discussion = new Discussion;
			$discussion->fill(Request::all());
			$discussion->user_id 			= Auth::user()->id;
			$discussion->property_id 		= Auth::user()->property_id;
			$discussion->save();
			if(!empty(Request::get('attachment'))) {
				$attach = [];
				foreach (Request::get('attachment') as $key => $file) {
					//Move Image
					$path = $this->createLoadBalanceDir($file['name']);
					$attach[] = new DiscussionFile([
							'name' => $file['name'],
							'url' => $path,
							'file_type' => $file['mime'],
							'is_image'	=> $file['isImage'],
							'original_name'	=> $file['originalName']
					]);
				}
				$discussion->attachment_count = ++$key;
				$discussion->save();
				$discussion->discussionFile()->saveMany($attach);
			}
			$this->addCreateDiscussionNotification($discussion);
			return redirect('discussion');
		}
	}

	public function changeStatus () {
		if(Request::isMethod('post')) {
			$cid = Request::get('cid');
			$discussion = Discussion::find($cid);
			if($discussion->count() && (Auth::user()->role == 1 || Auth::user()->is_chief || Auth::user()->role == 3)) {
				$discussion->discussion_status = Request::get('status');
				$discussion->save();
				//Add Notification
				$this->addDiscussionNotification ($discussion);
			}
			return redirect('admin/discussion/view/'.Request::get('cid'));
		}
		return redirect('admin/discussion');
	}

	public function view ($id) {
		$discussion = Discussion::with('owner','comments','comments.owner')->find($id);
		return view('discussion.view',compact('discussion'));
	}

	public function addComment () {
		if (Request::isMethod('post')) {
			$comment = new DiscussionComment([
				'description' 	=> Request::get('comment'),
				'user_id'		=> Auth::user()->id
			]);
			$discussion = Discussion::with('owner')->find(Request::get('did'));
			if($discussion) {
				$comment = $discussion->comments()->save($comment);
				//$isOwner = ($discussion->user_id == Auth::user()->id);
				$comments 	= DiscussionComment::with('owner')->where('discussion_id','=', Request::get('did'))->get()->sortBy('created_at');
				$contents 	= view('discussion.render_comment')->with(compact('comments'))->render();
				//Add Notification
				if( $discussion->user_id != Auth::user()->id && $discussion->owner->notification ) {
					$this->addDiscussionCommentNotification ($discussion);
				}

				$status = true;
			} else {
				$status = false;
			}
			return response()->json(['status' => $status,'content'=>$contents]);
		}
	}

	public function getAttach ($id) {
		$file = DiscussionFile::find($id);
        $file_path = 'discussion-file'.'/'.$file->url.$file->name;
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

	public function delete ( ) {
		if(Request::isMethod('post')) {

			$id=Request::get('d-id');
			// dd($id);
			$discussion = Discussion::with('discussionFile')->find($id);
			$report = PostReport::where('post_id',$discussion->id)->where('post_type',4)->first();
			if($report) {
				$report->delete();
			}
			$this->clearNotification($id);
			if($discussion->user_id == Auth::user()->id) {
				$discussion->comments()->delete();
				if(!$discussion->discussionFile->isEmpty()) {
					foreach ($discussion->discussionFile as $file) {
						$this->removeFile($file->name);
					}
					$discussion->discussionFile()->delete();
				}
				$discussion->delete();
			}
		}
		return redirect('discussion');
	}

	public function clearNotification ($subject_id) {
		$notis = Notification::where('subject_key',$subject_id)->get();
		// dd($notis);
        if($notis->count()) {
            foreach ($notis as $noti) {
                $noti->delete();
            }
        }

        return true;
    }

	public function removeFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'discussion-file'.'/'.$folder.'/'.$name;
        Storage::disk('s3')->delete($file_path);
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'discussion-file/'.$folder;
        $directories = Storage::disk('s3')->directories('discussion-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function addCreateDiscussionNotification($discussion) {
		$users = $this->getChief ();
		if($users->count()) {
			$title = json_encode( ['type'=>'discussion_created','title'=>$discussion->title] );
			foreach ($users as $user) {
				$notification = Notification::create([
					'title'				=> $title,
					'description' 		=> "",
					'notification_type' => '7',
					'subject_key'		=> $discussion->id,
					'to_user_id'		=> $user->id,
					'from_user_id'		=> Auth::user()->id
				]);
				$controller_push_noti = new PushNotificationController();
        		$controller_push_noti->pushNotification($notification->id);
			}

		}
	}

	public function addDiscussionCommentNotification($discussion) {

		$title = json_encode( ['type'=>'discussion_comment','title'=>$discussion->title] );
		$notification = Notification::create([
			'title'				=> $title,
			'description' 		=> "",
			'notification_type' => '7',
			'subject_key'		=> $discussion->id,
			'to_user_id'		=> $discussion->user_id,
			'from_user_id'		=> Auth::user()->id
		]);
		$controller_push_noti = new PushNotificationController();
		$controller_push_noti->pushNotification($notification->id);
	}

	public function getChief () {
		return User::where('property_id',Auth::user()->property_id)
				->where(function ($q) {
					 $q ->where('role',1)
					 	->orWhere('is_chief',true);
				})->where('id','!=',Auth::user()->id)->get();
	}

}
