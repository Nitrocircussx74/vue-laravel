<?php namespace App\Http\Controllers\User;
use Auth;
use File;
use Request;
use Storage;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use Redirect;
use App\Http\Controllers\GeneralFeesBillsReportController;
# Model
use App\Post;
use App\PostFile;
use App\Comment;
use App\Like;
use App\Property;
use App\Notification;
use App\PostReport;
use App\PostReportDetail;
use App\User;
use App\Transaction;
use App\PostByNabour;
use App\PostByNabourFile;
class PostController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		if( Auth::check() && !in_array(Auth::user()->role,[1,2,3]) ) {
            Redirect::to('/')->send();
        }
		view()->share('active_menu', 'feed');
	}

	public function feed () {

		$posts = Post::with('owner','comments','comments.owner','postFile')->where('property_id','=',Auth::user()->property_id)->where('sticky',false)->orderBy('post.created_at', 'desc')->paginate(10);
		$post_like = [];
		$property = Property::find(Auth::user()->property_id);
		foreach ($posts as $post) {
			$count = Like::where('post_id','=', $post->id)->where('user_id','=',Auth::user()->id)->count();
			$post_like[$post->id] = ($count == 0)?false:true;
		}

		if(Request::ajax()) {
			return view('post.feed')->with(compact('posts','post_like','property'));
		} else {
			//Sticky Post
			$sticky_posts = Post::with('owner','comments','comments.owner','postFile')->where('property_id','=',Auth::user()->property_id)->where('sticky',true)->orderBy('post.created_at', 'desc')->get();
			$sticky_post_like = [];
			foreach ($sticky_posts as $post) {
				$count = Like::where('post_id','=', $post->id)->where('user_id','=',Auth::user()->id)->count();
				$sticky_post_like[$post->id] = ($count == 0)?false:true;
			}
			if(Auth::user()->role != 2) {
				$report_controller = new GeneralFeesBillsReportController();
				$infaBill = $report_controller->getMiniInfaReport (); 
			}
			return view('post.feed_stream')->with(compact('posts','post_like','property','sticky_posts','sticky_post_like','infaBill'));
		}
	}

	public function gettext() {
		if ( Request::isMethod('post') ) {
			$post = Post::find(Request::get('pid'));
			return response()->json(['text' =>$post->description]);
		}
	}

	public function add () {
		if ( Request::isMethod('post') ) {

		
			$post = new Post;
			$post->user_id      = Auth::user()->id;
	        $post->property_id  = Auth::user()->property_id;
			if(Request::get('title')) {
				$post->title  = Request::get('title');
				$post->title_en  = Request::get('title_en');
			}
	        if(Request::get('description')) {
				$post->description  = Request::get('description');
				$post->description_en  = Request::get('description_en');
	        }
	        $post->like_count   = $post->comment_count = 0;
	        $post->description  = Request::get('description');
	        if(Auth::user()->role == 1 || Auth::user()->is_chief || Auth::user()->role == 3) {
	        	if(Request::get('sticky')) {
					$post->sticky = true;
				}

	        	if(Request::get('act_as') == "prop") {
					$post->act_as_property = true;
				}
	        }
	        if(!empty(Request::get('attachment'))) {
	        	$post->post_type = 1;
	        } else {
				$post->post_type = 0;
			}

			if(Request::get('category')) {
				$post->category  = Request::get('category');
			}else{
				$post->category = 3;
			}

			if(Request::get('template')) {
				$post->template  = Request::get('template');
			}else{
				$post->template = 0;
			}

	        $post->save();
			if(!empty(Request::get('attachment'))) {
				$postimg = [];
				foreach (Request::get('attachment') as $img) {
					//Move Image
					$path = $this->createLoadBalanceDir($img['name']);
					$postimg[] = new PostFile([
						'name' => strtolower($img['name']),
						'url' => $path,
						'file_type' => $img['mime'],
						'is_image'	=> $img['isImage'],
						'original_name'	=> strtolower($img['originalName'])
						]);
				}
				$post->postFile()->saveMany($postimg);
			}

			if (!empty(Request::get('img_post_banner'))) {
                $file = Request::get('img_post_banner');
                $name 	= $file['name'];
                $x 		= Request::get('img-x');
                $y 		= Request::get('img-y');
                $w 		= Request::get('img-w');
                $h 		= Request::get('img-h');
                cropBannerImg ($name,$x,$y,$w,$h);
                $path = $this->createLoadBalanceDir($file['name']);
				$tempUrl = "/%s%s";
				$type =explode(".",$file['name']);

				if($type[1] == "jpg")
				{
					$t ='jpeg';
				}else{
					$t =$type[1];
				}

				$img ="image/";	
				$postimg[] = new PostFile([
					'name' => strtolower($file['name']),
					'url' => $path,
					'file_type' => $img."".$t,
					'is_image'	=> 't',
					'original_name'	=> strtolower($file['name'])
					]);
				$post->postFile()->saveMany($postimg);
			}
			if( $post->publish_status == "t" ) {
				$this->addCreatePostNotification($post);
			}
		}
		return redirect('feed');
	}

	public function edit() {
		if ( Request::isMethod('post') ) {
			// dd( Request::all());
			$post = Post::find(Request::get('id'));
			if($post){
				$post->title = Request::get('title');
				$post->description = Request::get('description');
				$post->template  = Request::get('template');
				$post->save();
			}

			if(!empty(Request::get('attachment'))) {
				$postimg = [];
				foreach (Request::get('attachment') as $file) {
					//Move Image
					$path = $this->createLoadBalanceDir($file['name']);
					$postfile[] = new PostFile([
							'name' => strtolower($file['name']),
							'url' => $path,
							'file_type' => $file['mime'],
							'is_image'	=> $file['isImage'],
							'original_name'	=> strtolower($file['originalName'])
						]
					);
				}
				$post->postFile()->saveMany($postfile);
			}

			if (!empty(Request::get('img_post_banner'))) {
                $file = Request::get('img_post_banner');
                $name 	= $file['name'];
                $x 		= Request::get('img-x');
                $y 		= Request::get('img-y');
                $w 		= Request::get('img-w');
                $h 		= Request::get('img-h');
                cropBannerImg ($name,$x,$y,$w,$h);
                $path = $this->createLoadBalanceDir($file['name']);
				$tempUrl = "/%s%s";
				$type =explode(".",$file['name']);
				$img ="image/";
				// dd($img."".$type[1]);
				// dd($type[1]);	
				$postimg[] = new PostFile([
					'name' => strtolower($file['name']),
					'url' => $path,
					'file_type' => $img."".$type[1],
					'is_image'	=> 't',
					'original_name'	=> strtolower($file['name'])
					]);
				$post->postFile()->saveMany($postimg);
                // $pdpa->cover_image = sprintf($tempUrl, $path, $file['name']);
            }

			$remove = Request::get('remove');
			if(!empty($remove['post-file'])) {
				foreach ($remove['post-file'] as $file) {
					$file = PostFile::find($file);
					$this->removeFile($file->name);
					$file->delete();
				}
			}

			return redirect('feed');
		}
	}
	
	public function delete ($id = 0) {
			if(!Request::isMethod('get')) {
				if($this->deletePost (Request::get('pid')))
				{
					return response()->json(['status'=>true]);
				} else return response()->json(['status'=>false]);

			} else {
				if($this->deletePost ($id)) {
					return redirect('feed');
				}
				else redirect()->back();
			}
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

	public function deletePost ($id) {
		$post = Post::with('comments','postFile','likes')->find($id);
		if($post) {
			//remove post report
			$report = PostReport::where('post_id',$post->id)->where('post_type',1)->first();
			if($report) {
				$report->reportList()->delete();
				$report->delete();
			}

			if($post->user_id == Auth::user()->id || ($post->act_as_property && (Auth::user()->role == 1 || Auth::user()->is_chief || Auth::user()->role == 3)) ) {

				if(!$post->postFile->isEmpty()) {
					foreach ($post->postFile as $file) {
						$this->removeFile($file->name);
					}
					$post->postFile()->delete();
				}
				$this->clearNotification($id);
				$post->comments()->delete();
				$post->likes()->delete();
				return $post->delete();
			}
		}
	}

	public function addComment () {
		if (Request::isMethod('post')) {
			$comment = new Comment([
				'description' 	=> Request::get('comment'),
				'user_id'		=> Auth::user()->id
			]);

			$post = Post::with('owner')->find(Request::get('pid'));
			if( $post->act_as_property && (Auth::user()->role == 1 || Auth::user()->is_chief || Auth::user()->role == 3) ) {
				$comment->act_as_property = true;
			}

			if($post) {
				$comment = $post->comments()->save($comment);
				$post->comment_count = $post->comments()->count();
				$post->save();
				$isOwner = ($post->user_id == Auth::user()->id || ($post->act_as_property && (Auth::user()->role == 1 || Auth::user()->is_chief || Auth::user()->role == 3)));
				$comments 	= Comment::with('owner')->where('post_id','=', Request::get('pid'))->get()->sortBy('created_at');
				$property = Property::find(Auth::user()->property_id);
				$contents 	= view('post.render_comment')->with(compact('comments','isOwner','property'))->render();
				//Add Notification
				if( $post->user_id != Auth::user()->id && $post->owner->notification ) {
					$this->addCommentNotification ($post);
				}
				$status = true;
			} else {
				$status = false;
			}
			return response()->json(['status' => $status,'content'=>$contents,'count'=>$comments->count()]);
		}
	}

	public function deleteComment () {
		if (Request::isMethod('post') && Request::ajax()) {
			$comment = Comment::with('post')->find(Request::get('cid'));

			if($comment) {
				$post = Post::find($comment->post_id);
				if($post->user_id== Auth::user()->id || $comment->user_id == Auth::user()->id || ($post->act_as_property && (Auth::user()->role == 1 || Auth::user()->is_chief || Auth::user()->role == 3))) {
					$comment->delete();
					$post->comment_count =  $post->comment_count-1;
					$post->save();
					return response()->json(['status'=>true,'count'=>$post->comment_count,'pid'=>$post->id]);
				}
			}

		}
	}

	public function like() {
		if(Request::ajax()) {
			$post = Post::with('owner')->find(Request::get('pid'));
			if($post) {
				$like = new Like([
					'user_id' => Auth::user()->id
				]);
				$like = $post->likes()->save($like);
				$post->like_count++;
				$post->save();
				$like_count = Like::where('post_id','=', Request::get('pid'))->count();
				//Add Notification
				// remove then user hasn't permission for posting
				if( $post->user_id != Auth::user()->id && $post->owner->notification ) {
					$this->addLikeNotification ($post);
				}

				return response()->json(['status'=>true,'count'=>$like_count]);
			} else {
				return response()->json(['status'=>false]);
			}
		}
	}

	public function viewPost($id) {
		$post = Post::with('owner','comments','comments.owner','postFile')->find($id);
		$post_nabour_file = [];
		if($post->is_nabour_post && $post->attach_nabour_id != null){
			$post_nabour_file_query = PostByNabourFile::where('attach_nabour_post_key','=',$post->attach_nabour_id)->get();
			$post_nabour_file = $post_nabour_file_query;
		}
		$post_like = [];
		if($post) {
			$count = Like::where('post_id','=', $post->id)->where('user_id','=',Auth::user()->id)->count();
			$post_like[$post->id] = ($count == 0)?false:true;
		}
		$property = Property::find(Auth::user()->property_id);
		return view('post.view')->with(compact('post','post_like','property','post_nabour_file'));
	}

	public function reportCheck () {
		if(Request::ajax()) {
			$old_report = PostReportDetail::where('post_id', Request::get('pid'))
							->where('report_by', Auth::user()->id)
							->where('post_type', 1)
							->get();
			if($old_report->isEmpty()) {
				return response()->json(['status'=>true]);
			} else {
				return response()->json(['status'=>false,'msg'=>trans('messages.Post.reporte_dup')]);
			}
		}
	}
	public function report () {
		if(Request::ajax()) {
			$post = Post::find(Request::get('pid'));
			if($post->count()) {
				$report = PostReport::firstOrCreate(array('post_id' => Request::get('pid'), 'property_id' => $post->property_id));
				$report_detail = new PostReportDetail;
				$report_detail->post_report_id 	= $report->id;
				$report_detail->post_id 		= $post->id;
				$report_detail->report_by 		= Auth::user()->id;
				$report_detail->reason 			= Request::get('reason');
				$report_detail->post_type		= 1;
				$report_detail->save();
				$report->updated_at = time();
				$report->save();
				return response()->json(['status'=>true,'msg'=>trans('messages.Post.reported')]);
			}
		} else {
			return response()->json(['status'=>false]);
		}
	}

	/*public function addCreatePostNotification($post) {
		$users = User::where('property_id',Auth::user()->property_id)->whereNull('verification_code')->whereNotIn('id', [Auth::user()->id])->get();
		if($users->count()) {
			$title = json_encode( ['type'=>'post_created','title'=>''] );
			foreach ($users as $user) {
				$notification = Notification::create([
					'title'				=> $title,
					'description' 		=> "",
					'notification_type' => 11,
					'subject_key'		=> $post->id,
					'to_user_id'		=> $user->id,
					'from_user_id'		=> Auth::user()->id
				]);
				$controller_push_noti = new PushNotificationController();
				$controller_push_noti->pushNotification($notification->id);
			}

		}
	}*/
	
	// Test Change method to send notification
	public function addCreatePostNotification($post) {
		$users = User::where('property_id',Auth::user()->property_id)->whereNull('verification_code')->whereNotIn('id', [Auth::user()->id])->get();
		if($users->count()) {
			$title = json_encode( ['type'=>'post_created','title'=>''] );
			foreach ($users as $user) {
				$notification[] = Notification::create([
					'title'				=> $title,
					'description' 		=> "",
					'notification_type' => 11,
					'subject_key'		=> $post->id,
					'to_user_id'		=> $user->id,
					'from_user_id'		=> Auth::user()->id
				]);
			}
			$controller_push_noti = new PushNotificationController();
			$controller_push_noti->dispatchBatchNotification ($title,$post->id,11,Auth::user()->id,Auth::user()->property_id);
			//$controller_push_noti->pushNotificationArray($notification);
		}
	}

	public function addCommentNotification($post) {
		$notification = Notification::create([
			'title'				=> ($post->post_type == 1)?'comment_photo':'comment_status',
			'description' 		=> "",
			'notification_type' => '0',
			'subject_key'		=> $post->id,
			'to_user_id'		=> $post->user_id,
			'from_user_id'		=> Auth::user()->id
		]);

        $controller_push_noti = new PushNotificationController();
        $controller_push_noti->pushNotification($notification->id);
	}

	public function addLikeNotification($post) {
        $notification = Notification::create([
			'title'				=> ($post->post_type == 1)?'like_photo':'like_status',
			'description' 		=> "",
			'notification_type' => '1',
			'subject_key'		=> $post->id,
			'to_user_id'		=> $post->user_id,
			'from_user_id'		=> Auth::user()->id
		]);

        //$controller_push_noti = new PushNotificationController();
        //$controller_push_noti->pushNotification($notification->id);
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'post-file/'.$folder;
        $directories = Storage::disk('s3')->directories('post-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".strtolower($name);
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function removeFile ($name) {
        $folder = substr($name, 0,2);
        $file_path = 'post-file'."/".$folder."/".$name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            Storage::disk('s3')->delete($file_path);
        }
	}

	public function getAttach ($id) {
		$file = PostFile::find($id);
		$file_path = 'post-file'.'/'.$file->url.$file->name;
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
	
	public function getNabourAttach ($id) {
		$file = PostByNabourFile::find($id);
		$file_path = 'post-nabour-file'.'/'.$file->url.$file->name;
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

	public function getform () {
		$post = Post::find(Request::get('id'));
		return view('post.edit')->with(compact('post'));
	}
}
