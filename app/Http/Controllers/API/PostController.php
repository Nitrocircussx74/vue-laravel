<?php namespace App\Http\Controllers\API;

use App\Jobs\PushNotificationSender;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Http\Controllers\PushNotificationController;
use Request;
//use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;

use JWTAuth;
use Storage;
use Illuminate\Bus\Dispatcher;
use League\Flysystem\AwsS3v2\AwsS3Adapter;

// Firebase
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;

# Model
use App\Post;
use App\PostFile;
use App\Comment;
use App\Like;
use App\Property;
use App\Notification;
use App\PostReport;
use App\Installation;
use Auth;
use File;
use Mail;
use App\User;
use App\PostByNabour;
use App\PostByNabourFile;

# Jobs
use App\Jobs\PushNotificationUser;

class PostController extends Controller {

    use DispatchesJobs;

	public function __construct () {

	}

	public function feed () {

        $posts = Post::with('owner','comments','comments.owner','postFile')->where('property_id','=',Auth::user()->property_id)->orderBy('post.created_at', 'desc')->paginate(15);

        $property = Property::find(Auth::user()->property_id);
        //Sticky Post (Disable now)
        /*$sticky_posts = Post::with('owner','comments','comments.owner','postFile')->where('property_id','=',Auth::user()->property_id)->where('sticky',true)->orderBy('post.created_at', 'desc')->get();
        foreach ($sticky_posts as &$post) {
            $count = Like::where('post_id','=', $post->id)->where('user_id','=',Auth::user()->id)->count();
            if($count > 0){
                $post['like_flag'] = true;
            }else{
                $post['like_flag'] = false;
            }
        }*/

        $postArr = $posts->toArray();
        foreach($postArr['data'] as &$postItem){
            $count = Like::where('post_id','=', $postItem['id'])->where('user_id','=',Auth::user()->id)->count();
            if($count > 0){
                $postItem['like_flag'] = true;
            }else{
                $postItem['like_flag'] = false;
            }

            $template_num = $postItem['template'];

            /*if($template_num != 0){
                $url_template = url('/')."/images/image_theme_select/0".$template_num.".png";
                $postItem['url_template'] = $url_template;
            }else{
                $rand = rand(1,4);
                $url_template = url('/')."/images/image_theme_select/0".$rand.".png";
                $postItem['url_template'] = $url_template;
            }*/

            $aaa = "";
            if($postItem['owner']['role'] == 0) {
                if ($postItem['attach_nabour_id'] != null){
                    // Get post nabour file
                    $attach_file = PostByNabourFile::where('attach_nabour_post_key', '=', $postItem['attach_nabour_id'])->get();
                    if (count($attach_file) > 0) {
                        foreach ($attach_file as $attach_item) {
                            $new_attach_arr[] = ([
                                "id" => $attach_item->id,
                                "name" => $attach_item->name,
                                "post_id" => $postItem['id'],
                                "url" => $attach_item->url,
                                "file_type" => $attach_item->file_type,
                                "created_at" => (string)$attach_item->created_at,
                                "updated_at" => (string)$attach_item->updated_at,
                                "is_image" => $attach_item->is_image,
                                "original_name" => $attach_item->original_name
                            ]);
                        }
                        $postItem['post_file'] = $new_attach_arr;
                    } else {
                        $postItem['post_file'] = [];
                    }
                }else{
                    $postItem['post_file'] = [];
                }

                // Get post image
                if($postItem['img_nabour_name'] != null && $postItem['img_nabour_name'] != null) {
                    $postItem['url_template'] = env('URL_S3')."/post-nabour-image/".$postItem['img_nabour_path'].$postItem['img_nabour_name'];
                }else{
                    $postItem['url_template'] = url('/')."/images/image_theme_select/05.png";
                }
            }else{
                if($template_num != 0){
                    $url_template = url('/')."/images/image_theme_select/0".$template_num."_".Auth::user()->lang.".png";
                    $postItem['url_template'] = $url_template;
                }else{
                    $rand = rand(1,4);
                    $url_template = url('/')."/images/image_theme_select/0".$rand."_".Auth::user()->lang.".png";
                    $postItem['url_template'] = $url_template;
                }
            }

            /*if($postItem['img_nabour_name'] == null){
                $postItem['img_nabour_name'] = "";
            }

            if($postItem['img_nabour_path'] == null){
                $postItem['img_nabour_path'] = "";
            }

            if($postItem['attach_nabour_id'] == null){
                $postItem['attach_nabour_id'] = "";
            }

            if($postItem['post_by_nabour_id'] == null){
                $postItem['post_by_nabour_id'] = "";
            }*/
            unset($postItem['img_nabour_name']);
            unset($postItem['img_nabour_path']);
            unset($postItem['attach_nabour_id']);
            unset($postItem['post_by_nabour_id']);
        }

        $resultsData = array(
            //"sticky" => $sticky_posts,
            "posts" => $postArr,
            "property" => $property,
            "root_url_nabour_attach" => env('URL_S3')."/post-nabour-file/",
        );

        $results = array(
            "status" => true,
            "data" => $resultsData,
            "message" => "Success."/*,
            "token" => $this->newToken*/
        );

        return response()->json($results);
	}

	public function gettext() {
		if ( Request::isMethod('post') ) {
			$post = Post::find(Request::get('pid'));
			return response()->json(['text' =>$post->description]);
		}
	}

	public function add () {
        $post = new Post;
        $post->user_id      = Auth::user()->id;
        $post->property_id  = Auth::user()->property_id;
        $post->like_count   = $post->comment_count = 0;
        if(Request::get('description') != null) {
            $post->description = Request::get('description');
        }
        if(Auth::user()->role == 1 || Auth::user()->is_chief || Auth::user()->role == 3) {
            if(Request::get('act_as') == "prop") {
                $post->act_as_property = true;

                if(Request::get('sticky') == "true"){
                    $post->sticky  = true;
                }
            }else{
                $post->act_as_property = false;
                $post->sticky  = false;
            }
        }
        if(!empty(Request::get('postimg'))) {
            $post->post_type = 1;
        } else $post->post_type = 0;
        $post->save();

        return response()->json(['pid' =>$post->id]);
	}

    public function addImagePost () {
        try {
            // Get Post
            $post = Post::find(Request::get('pid'));
            $post->post_type = 1;
            $post->save();

            $postimg = [];

            /* New Function */
            if(count(Request::file('attachment'))) {
                foreach (Request::file('attachment') as $img) {
                    $name =  md5($img->getFilename());//getClientOriginalName();
                    $extension = $img->getClientOriginalExtension();
                    $targetName = $name.".".$extension;

                    $path = $this->createLoadBalanceDir($img);
                    $postimg[] = new PostFile([
                        'name' => $targetName,
                        'url' => $path,
                        'file_type' => $img->getClientMimeType(),
                        'is_image'	=> true,
                        'original_name'	=> $img->getClientOriginalName()
                    ]);
                }
                $post->postFile()->saveMany($postimg);
            }

            return response()->json(['success' =>'true']);

        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    // TODO : All-in-one add
    public function addFullPost () {
        try {
            // Add Post
            $post = new Post;
            $post->user_id      = Auth::user()->id;
            $post->property_id  = Auth::user()->property_id;
            $post->description  = Request::get('description');
            $post->like_count   = $post->comment_count = 0;
            $post->post_type = 0;
            $post->save();

            /* Add image Function */
            if(count(Request::file('attachment'))) {
                $postimg = [];
                foreach (Request::file('attachment') as $img) {
                    $name =  md5($img->getFilename());//getClientOriginalName();
                    $extension = $img->getClientOriginalExtension();
                    $targetName = $name.".".$extension;

                    $path = $this->createLoadBalanceDir($img);
                    $postimg[] = new PostFile(['name' => $targetName, 'path' => $path]);
                }
                $post->postFile()->saveMany($postimg);
            }

            return response()->json(['success' =>'true']);

        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

	public function edit() {
        try{
            if ( Request::isMethod('post') ) {
                $post = Post::find(Request::get('pid'));
                if ($post->user_id == Auth::user()->id) {
                    $post->description = Request::get('description');
                    $post->save();
                }else{
                    return response()->json(['success' =>'false', 'description' => 'not owner post']);
                }
            }

            return response()->json(['success' =>'true']);

        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
	}

	public function deletePost () {
        try{
            if ( Request::isMethod('post') ) {
                $id = Request::get('pid');
                $post = Post::with('comments', 'postFile', 'likes')->find($id);
                if ($post) {
                    if ($post->user_id == Auth::user()->id) {

                        if (!$post->postFile->isEmpty()) {
                            foreach ($post->postFile as $file) {
                                $this->removeFile($file->name);
                            }
                            $post->postFile()->delete();
                        }
                        $post->comments()->delete();
                        $post->likes()->delete();
                        $post->delete();

                        return response()->json(['success' => 'true']);
                    }
                }
            }
        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
	}

	public function addComment () {
        try {
            if (Request::isMethod('post')) {
                $comment = new Comment([
                    'description' => Request::get('comment'),
                    'user_id' => Auth::user()->id
                ]);
                $post = Post::with('owner')->find(Request::get('pid'));
                if ($post) {
                    $post->comments()->save($comment);
                    $post->comment_count = $post->comments()->count();
                    $post->save();

                    //Add Notification
                    if ($post->user_id != Auth::user()->id && $post->owner->notification) {
                        $this->addCommentNotification($post);
                    }
                    $status = true;
                } else {
                    $status = false;
                }
                return response()->json(['success' => $status]);
            }
        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
	}

	public function deleteComment () {
		try {
			$comment = Comment::with('post')->find(Request::get('cid'));

			if($comment) {
				$post = Post::find($comment->post_id);
				if($post->user_id== Auth::user()->id || $comment->user_id == Auth::user()->id ) {
					$comment->delete();
					$post->comment_count =  $post->comment_count-1;
					$post->save();
					return response()->json(['status'=>true]);
				}
			}
		}catch(Exception $ex){
            return response()->json(['success' =>false]);
        }
	}

	public function like() {
		try {
			$post = Post::with('owner')->find(Request::get('pid'));
			if($post) {
                $checkLike = Like::where('post_id','=', $post->id)->where('user_id','=',Auth::user()->id)->count();
                if($checkLike == 0) {
                    $like = new Like([
                        'user_id' => Auth::user()->id
                    ]);
                    $post->likes()->save($like);
                    $post->like_count++;
                    $post->save();
                    //Add Notification
                    if ($post->user_id != Auth::user()->id && $post->owner->notification) {
                        $this->addLikeNotification($post);
                    }
                }else{
                    // already like
                }

				return response()->json(['status'=>true]);
			} else {
				return response()->json(['status'=>false]);
			}

		} catch(Exception $ex){
            return response()->json(['success' =>false]);
        }
	}

    public function viewPostApi($id) {
        $post_query = Post::with('owner','comments','comments.owner','postFile')->find($id);

        $post_data = $post_query->toArray();
        //$post_like = [];
        $count = Like::where('post_id','=', $post_data['id'])->where('user_id','=',Auth::user()->id)->count();
        if($count > 0){
            $post_data['like_flag'] = true;
        }else{
            $post_data['like_flag'] = false;
        }

        $template_num = $post_data['template'];

        if($post_data['owner']['role'] == 0){
            if($post_data['attach_nabour_id'] != null){
                // Get post nabour file
                $attach_file = PostByNabourFile::where('attach_nabour_post_key','=',$post_data['attach_nabour_id'])->get();
                if(count($attach_file) >0) {
                    foreach ($attach_file as $attach_item) {
                        $new_attach_arr[] = ([
                            "id" => $attach_item->id,
                            "name" => $attach_item->name,
                            "post_id" => $post_data['id'],
                            "url" => $attach_item->url,
                            "file_type" => $attach_item->file_type,
                            "created_at" => (string)$attach_item->created_at,
                            "updated_at" => (string)$attach_item->updated_at,
                            "is_image" => $attach_item->is_image,
                            "original_name" => $attach_item->original_name
                        ]);
                    }
                    $post_data['post_file'] = $new_attach_arr;
                }else{
                    $post_data['post_file'] = [];
                }
            }else{
                $post_data['post_file'] = [];
            }

            // Get post image
            if($post_data['img_nabour_name'] != null && $post_data['img_nabour_name'] != null) {
                $post_data['url_template'] = env('URL_S3')."/post-nabour-image/".$post_data['img_nabour_path'].$post_data['img_nabour_name'];
            }else{
                $post_data['url_template'] = url('/')."/images/image_theme_select/05.png";
            }
        }else {
            if ($template_num != 0) {
                $url_template = url('/') . "/images/image_theme_select/0" . $template_num ."_".Auth::user()->lang. ".png";
                $post_data['url_template'] = $url_template;
            } else {
                $rand = rand(1, 4);
                $url_template = url('/') . "/images/image_theme_select/0" . $rand ."_".Auth::user()->lang. ".png";
                $post_data['url_template'] = $url_template;
            }
        }

        /*if($post_data['img_nabour_name'] == null){
            $post_data['img_nabour_name'] = "";
        }

        if($post_data['img_nabour_path'] == null){
            $post_data['img_nabour_path'] = "";
        }

        if($post_data['attach_nabour_id'] == null){
            $post_data['attach_nabour_id'] = "";
        }

        if($post_data['post_by_nabour_id'] == null){
            $post_data['post_by_nabour_id'] = "";
        }*/
        unset($post_data['img_nabour_name']);
        unset($post_data['img_nabour_path']);
        unset($post_data['attach_nabour_id']);
        unset($post_data['post_by_nabour_id']);

        foreach($post_data['post_file'] as &$value)
        {
            $splitType = explode(".",$value['name']);
            $value['file_type'] = end($splitType);
        }

        $post_data['root_url_nabour_attach'] = env('URL_S3')."/post-nabour-file/";

        $post = $post_data;

        return response()->json(compact('post'));
    }

	public function report () {
		try {
			$post = Post::find(Request::get('pid'));
			if($post) {
				$old_report = PostReport::where('post_id','=',$post->id)->where('report_by','=',Auth::user()->id)->get();
				if($old_report->isEmpty()) {
					$report = new PostReport;
					$report->post_id 	= $post->id;
					$report->report_by 	= Auth::user()->id;
					$report->reason 	= Request::get('reason');
					$report->save();
					return response()->json(['status'=>true,'msg'=>'เราได้เรื่องรายงานของท่านแล้ว และจะส่งเรื่องเพื่อให้นิติบุคคลของท่านพิจารณาความเหมาะสมต่อไป']);
				}
				else
					return response()->json(['status'=>false,'msg'=>'คุณเคยรายงานไปก่อนหน้านี้แล้ว']);
			}
		} catch(Exception $ex) {
			return response()->json(['status'=>false]);
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

        $controller_push_noti = new PushNotificationController();
        $controller_push_noti->pushNotification($notification->id);
    }

    public function createLoadBalanceDir ($imageFile) {
        $name =  md5($imageFile->getFilename());//getClientOriginalName();
        $extension = $imageFile->getClientOriginalExtension();
        $targetName = $name.".".$extension;

        $folder = substr($name, 0,2);

        $pic_folder = 'post-file'.DIRECTORY_SEPARATOR.$folder;
        $directories = Storage::disk('s3')->directories('post-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }

        $full_path_upload = $pic_folder.DIRECTORY_SEPARATOR.$targetName;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($imageFile), 'public');// public set in photo upload
        if($upload){
            // Success
        }

        return $folder."/";
    }

    public function removeFile ($name) {
        $folder = substr($name, 0,2);
        $file_path = 'post-file'.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            Storage::disk('s3')->delete($file_path);
        }
    }

    public function testPushNotification(){
        $notification_id = "3a355fde-7746-4f52-aaf3-f245df407c87";

        $controller = new PushNotificationController();
        $controller->pushNotification($notification_id);
    }

    public function testSendEmail(){
        $email = "sjinadech@gmail.com";

        try {

            Mail::send('emails.success_signup', ['name' => 'Suttipong Jinadech Test'], function ($message) {
                $message->subject("mantest");
                $message->to("sjinadech@gmail.com");
            });

            return "yes";
        }catch(Exception $ex) {
            return "no";
        }
    }

    public function convertIntToText(){
        $number = Request::get('number');
        $lang = Request::get('lang');
        if($lang == 'th'){
            $string_number = convertIntToTextThai($number);
            $currency_readable = $string_number."บาทถ้วน";
        }else{
            $string_number = convertIntToTextEng($number);
            $currency_readable = $string_number." Baht";
        }

        return response()->json([
            'number'=>$number,
            'lang'=>$lang,
            'string'=>$currency_readable
        ]);
    }
}
