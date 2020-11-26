<?php namespace App\Http\Controllers\RootAdmin;
use Auth;
use File;
use Request;
use Storage;
use Illuminate\Database\Eloquent;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use Redirect;
use App\Http\Controllers\GeneralFeesBillsReportController;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
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
use App\Province;
use App\PostByNabour;
use App\PostByNabourFile;

class PostController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		//if( Auth::check() && !in_array(Auth::user()->role,[1,2,3]) ) {
		if( Auth::check() && Auth::user()->role !== 0 ) {
            if(Auth::user()->role !== 5) {
                Redirect::to('feed')->send();
            }
        }
		view()->share('active_menu', 'feed');
	}

	public function feed () {

		$posts = PostByNabour::where('user_id','=',Auth::user()->id)->orderBy('created_at', 'desc')->paginate(10);
		$post_like = [];
		$property = Property::find(Auth::user()->property_id);

		//on innitial page
		$prop_list = array(trans('messages.Signup.select_property'));

		$p = new Province;
		$provinces = $p->getProvince();

		$props = Property::all();

		if(Request::ajax()) {
			return view('post_by_nabour.feed')->with(compact('posts','post_like','property','prop_list','provinces'));
		} else {
			$sticky_posts = Post::with('owner','comments','comments.owner','postFile')->where('property_id','=',Auth::user()->property_id)->where('sticky',true)->orderBy('post.created_at', 'desc')->get();
			$sticky_post_like = [];
			foreach ($sticky_posts as $post) {
				$count = Like::where('post_id','=', $post->id)->where('user_id','=',Auth::user()->id)->count();
				$sticky_post_like[$post->id] = ($count == 0)?false:true;
			}
			return view('post_by_nabour.feed_stream')->with(compact('posts','post_like','property','sticky_posts','sticky_post_like','prop_list','provinces'));
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
			$post = new Post();
			$post->user_id = Auth::user()->id;
			$post->is_nabour_post = true;
			if(Request::get('title')) {
				$post->title  = Request::get('title');
			}
	        if(Request::get('description')) {
	        	$post->description  = Request::get('description');
	        }
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

			// img post
			if(!empty(Request::get('img-nb-file'))) {
				if(!empty($post->img_nb_name)) {
					$this->removeImage($post->img_nb_name);
				}
				$name 	= Request::get('img-nb-file');
				/*$x 		= Request::get('img-x');
				$y 		= Request::get('img-y');
				$w 		= Request::get('img-w');
				$h 		= Request::get('img-h');*/

				//cropProfileImg ($name,$x,$y,$w,$h);
				foreach (Request::get('img-nb-file') as $img) {
					$path = $this->createLoadBalanceDirImagePost($img['name']);
					$post->img_nabour_name = $img['name'];
					$post->img_nabour_path = $path;
				}
			}

			// attach uuid
			$attach_nabour_post_uuid = null;

			// Post Nabour Stat
			$property_list = Request::get('property_id');
			$values= serialize($property_list);
			$post->property_id = $values;

			if(!empty(Request::get('attachment'))) {
				//  generate uuid
				$attach_nabour_post_uuid = Uuid::uuid4();
				$postimg = [];
				foreach (Request::get('attachment') as $img) {
					//Move Image
					$path = $this->createLoadBalanceDir($img['name']);
					$attach_nabour = new PostByNabourFile([
						'attach_nabour_post_key' => $attach_nabour_post_uuid,
						'name' => $img['name'],
						'url' => $path,
						'file_type' => $img['mime'],
						'is_image'	=> $img['isImage'],
						'original_name'	=> $img['originalName']
					]);

					$attach_nabour->save();
				}
				//$post->postNabourFile()->saveMany($postimg);
			}

			// Post Nabour Stat
			$post_stat = new PostByNabour();
			$property_list = Request::get('property_id');
			$values= serialize($property_list);
			$post_stat->property_id = $values;
			$post_stat->user_id = $post->user_id;
			$post_stat->like_count = 0;
			$post_stat->title = $post->title;
			$post_stat->description = $post->description;
			$post_stat->post_type = $post->post_type;
			$post_stat->category = $post->category;
			$post_stat->template = $post->template;
			$post_stat->img_name = $post->img_nabour_name;
			$post_stat->img_path = $post->img_nabour_path;
			$post_stat->attach_nabour_id = $attach_nabour_post_uuid;
			$post_stat->province = Request::get('province');
			$post_stat->save();

			$property_list = Request::get('property_id');
			if($property_list[0] == "all")
			{
			$props_select = Property::where('province',$post_stat->province)->get()->pluck('id');
			// dd($props_select);	
				foreach ($props_select as $property){
					$new_post = new Post();
					$new_post->user_id = $post->user_id;
					$new_post->property_id = $property;
					$new_post->like_count = 0;
					$new_post->comment_count = 0;
					$new_post->is_nabour_post = $post->is_nabour_post;
					$new_post->title = $post->title;
					$new_post->description = $post->description;
					$new_post->sticky = false;
					$new_post->act_as_property = false;
					$new_post->post_type = $post->post_type;
					$new_post->category = $post->category;
					$new_post->template = $post->template;
					$new_post->img_nabour_name = $post->img_nabour_name;
					$new_post->img_nabour_path = $post->img_nabour_path;
					$new_post->attach_nabour_id = $attach_nabour_post_uuid;
					$new_post->post_by_nabour_id = $post_stat->id;
	
					$new_post->save();
	
					$this->addCreatePostNotification($new_post,$property);
				}
			}else{
			// dd($property_list );
			foreach ($property_list as $property){

				$new_post = new Post();

				$new_post->user_id = $post->user_id;
				$new_post->property_id = $property;
				$new_post->like_count = 0;
				$new_post->comment_count = 0;
				$new_post->is_nabour_post = $post->is_nabour_post;
				$new_post->title = $post->title;
				$new_post->description = $post->description;
				$new_post->sticky = false;
				$new_post->act_as_property = false;
				$new_post->post_type = $post->post_type;
				$new_post->category = $post->category;
				$new_post->template = $post->template;
				$new_post->img_nabour_name = $post->img_nabour_name;
				$new_post->img_nabour_path = $post->img_nabour_path;
				$new_post->attach_nabour_id = $attach_nabour_post_uuid;
				$new_post->post_by_nabour_id = $post_stat->id;

				$new_post->save();

				$this->addCreatePostNotification($new_post,$property);
			}
		}

		}
		return redirect('root/admin/post');
	}

	public function edit() {
		if ( Request::isMethod('post') ) {
			$property_list_new = Request::get('property_id');
			// post by nabour stat table
			$post_by_nabour = PostByNabour::find(Request::get('id'));

			if($post_by_nabour) {
				$property_list_old = unserialize($post_by_nabour->property_id);

				if(!empty(Request::get('img-nb-file'))) {
					if(!empty($post_by_nabour->img_name)) {
						$this->removeImage($post_by_nabour->img_name);
					}
					$name 	= Request::get('img-nb-file');
					/*$x 		= Request::get('img-x');
                    $y 		= Request::get('img-y');
                    $w 		= Request::get('img-w');
                    $h 		= Request::get('img-h');*/

					//cropProfileImg ($name,$x,$y,$w,$h);
					foreach (Request::get('img-nb-file') as $img) {
						$path = $this->createLoadBalanceDirImagePost($img['name']);
						$post_by_nabour->img_name = $img['name'];
						$post_by_nabour->img_path = $path;
					}
				}

				if(!empty(Request::get('attachment'))) {
					//  generate uuid
					//$attach_nabour_post_uuid = Uuid::uuid4();
					$postimg = [];
					foreach (Request::get('attachment') as $img) {
						//Move Image
						$path = $this->createLoadBalanceDir($img['name']);
						$attach_nabour = new PostByNabourFile([
							'attach_nabour_post_key' => $post_by_nabour->attach_nabour_id,
							'name' => $img['name'],
							'url' => $path,
							'file_type' => $img['mime'],
							'is_image'	=> $img['isImage'],
							'original_name'	=> $img['originalName']
						]);

						$attach_nabour->save();
					}
					//$post->postNabourFile()->saveMany($postimg);
				}

				$remove = Request::get('remove');
				if(!empty($remove['post-file'])) {
					foreach ($remove['post-file'] as $file) {
						$file = PostByNabourFile::find($file);
						$this->removeFile($file->name);
						$file->delete();
					}
				}

				// save edit post by nabour table
				$post_by_nabour->title = Request::get('title');
				$post_by_nabour->description = Request::get('description');
				$post_by_nabour->property_id = serialize($property_list_new);
				$post_by_nabour->save();

				// post table edit post
				$post = Post::where('post_by_nabour_id', '=', Request::get('id'))->get();
				foreach ($post as $post_item) {
					$post_item->title = $post_by_nabour->title;
					$post_item->description = $post_by_nabour->description;
					$post_item->img_nabour_name = $post_by_nabour->img_name;
					$post_item->img_nabour_path = $post_by_nabour->img_path;
					$post_item->attach_nabour_id = $post_by_nabour->attach_nabour_id;
					$post_item->save();
				}

				// post table delete (if have remove property)
				$property_list = Request::get('property_id');
				$show_remove_property_list = array_diff($property_list_old, $property_list_new);

				if (count($show_remove_property_list) > 0) {
					$property_remove_data = Post::where('post_by_nabour_id', '=', Request::get('id'))->whereIn('property_id', $show_remove_property_list)->get();

					foreach ($property_remove_data as $remove_item) {
						$remove_item->delete();
					}
				}

				// post table add (if have new property)
				$show_new_property_list = array_diff($property_list_new, $property_list_old);

				if (count($show_new_property_list) > 0) {
					foreach ($show_new_property_list as $add_item) {
						$new_post = new Post();

						$new_post->user_id = $post_by_nabour->user_id;
						$new_post->property_id = $add_item;
						$new_post->like_count = 0;
						$new_post->comment_count = 0;
						$new_post->is_nabour_post = true;
						$new_post->title = $post_by_nabour->title;
						$new_post->description = $post_by_nabour->description;
						$new_post->sticky = false;
						$new_post->act_as_property = false;
						$new_post->post_type = $post_by_nabour->post_type;
						$new_post->category = $post_by_nabour->category;
						$new_post->template = $post_by_nabour->template;
						$new_post->img_nabour_name = $post_by_nabour->img_name;
						$new_post->img_nabour_path = $post_by_nabour->img_path;
						$new_post->attach_nabour_id = $post_by_nabour->attach_nabour_id;
						$new_post->post_by_nabour_id = $post_by_nabour->id;

						$new_post->save();

						$this->addCreatePostNotification($new_post,$add_item);
					}
				}

				return redirect('root/admin/post');
			}else{
				return redirect('root/admin/post');
			}
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

	public function deletePost ($id) {
		$post_by_nabour = PostByNabour::find($id);
		$post_by_nabour_arr = $post_by_nabour->toArray();

		// delete post nabour attachment
		$attach_nabour_id = $post_by_nabour->attach_nabour_id;
		if($attach_nabour_id != null) {
			$post_nabour_file = PostByNabourFile::where('attach_nabour_post_key','=',$attach_nabour_id)->get();

			foreach ($post_nabour_file as $file_item){
				//delete file
				$this->removeFile($file_item->name);
				//delete record
				$file_item->delete();
			}
		}

		// delete post
		if($id != null) {
			$post_data_arr = Post::where('post_by_nabour_id', '=', $id)->get();
			foreach ($post_data_arr as $remove_item) {
				$post = Post::with('comments', 'postFile', 'likes')->find($remove_item->id);
				if ($post) {
					//remove post report
					$report = PostReport::where('post_id', $post->id)->where('post_type', 1)->first();
					if ($report) {
						$report->reportList()->delete();
						$report->delete();
					}

					if (Auth::user()->role == 0) {

						if (!$post->postFile->isEmpty()) {
							foreach ($post->postFile as $file) {
								$this->removeFile($file->name);
							}
							$post->postFile()->delete();
						}
						$post->comments()->delete();
						$post->likes()->delete();
						$remove_item->delete();
					}
				}
			}
		}

		// delete post by nabour
		if($post_by_nabour_arr['img_name'] != null) {
			$this->removeImage($post_by_nabour->img_name);
		}

		return $post_by_nabour->delete();
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
		$post_like = [];
		if($post) {
			$count = Like::where('post_id','=', $post->id)->where('user_id','=',Auth::user()->id)->count();
			$post_like[$post->id] = ($count == 0)?false:true;
		}
		$property = Property::find(Auth::user()->property_id);
		return view('post.view')->with(compact('post','post_like','property'));
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

	public function addCreatePostNotification($post,$property_id) {
		$users = User::where('property_id',$property_id)->whereNull('verification_code')->whereNotIn('id', [Auth::user()->id])->get();
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
		$pic_folder = 'post-nabour-file/'.$folder;
        $directories = Storage::disk('s3')->directories('post-nabour-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function createLoadBalanceDirImagePost ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'post-nabour-image/'.$folder;
		$directories = Storage::disk('s3')->directories('post-nabour-image'); // Directory in Amazon
		if(!in_array($pic_folder, $directories))
		{
			Storage::disk('s3')->makeDirectory($pic_folder);
		}
		$full_path_upload = $pic_folder."/".$name;
		$upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
		File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function removeImage ($name) {
        $folder = substr($name, 0,2);
        $file_path = 'post-nabour-image'."/".$folder."/".$name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            Storage::disk('s3')->delete($file_path);
        }
	}

	public function removeFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'post-nabour-file'."/".$folder."/".$name;
		$exists = Storage::disk('s3')->has($file_path);
		if ($exists) {
			Storage::disk('s3')->delete($file_path);
		}
	}

	public function getAttach ($id) {
		$file = PostFile::find($id);
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
		$post = PostByNabour::find(Request::get('id'));

		$property = Property::find(Auth::user()->property_id);

		//on innitial page
		$prop_list = array(trans('messages.Signup.select_property'));

		$p = new Province;
		$provinces = $p->getProvince();

		$props = Property::all();

		$post_data = $post->toArray();

		$props_select = Property::where('province','=',$post_data['province'])->get();

		$select_prop = $post_data['property_id'];
		$select_prop_array = unserialize($select_prop);

		$property_select_data = Property::whereIn('id', $select_prop_array)->get();

		foreach ($property_select_data as $itemtest){
			$var_arr[] = $itemtest['id'];
		}

		if(isset($var_arr)) {
			$select_prop_id_arr = $var_arr;
		}else{
			$select_prop_id_arr = null;
		}

		if($post->attach_nabour_id != null) {
			$post_by_nabour_file = PostByNabourFile::where('attach_nabour_post_key', '=', $post->attach_nabour_id)->get();
		}else{
			$post_by_nabour_file = null;
		}

		return view('post_by_nabour.edit')->with(compact('post','property','prop_list','provinces','props_select','select_prop_id_arr','post_by_nabour_file'));
	}

	function propertylist () {
		$lang = session()->get('lang');
		if (Request::isMethod('post')) {
			$pid = Request::get('pid');
			$props = Property::where('province','=',$pid)->get();

			if($props->count() > 0) {
				echo "<option value='all'>"."ทั้งหมด"."</option>";
				foreach ($props as $prop) {
					echo "<option value='".$prop->id."'>".$prop->{'property_name_'.$lang}."</option>";
				}
			}
		}
	}
}
