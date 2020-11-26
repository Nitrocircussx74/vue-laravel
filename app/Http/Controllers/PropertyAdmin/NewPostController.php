<?php namespace App\Http\Controllers\PropertyAdmin;


use Auth;
use DB;
use File;
use Illuminate\Http\Request;
use Storage;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use Redirect;
use App\Http\Controllers\GeneralFeesBillsReportController;
# Model
use App\NewPost;
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

class NewPostController extends Controller
{
    public function __construct () {
        $this->middleware('auth');
        $this->imgFolder = 'new-post';
        // dd(Auth::user()->role);
        if( Auth::check() && Auth::user()->role == 2 ) {
          
                Redirect::to('feed')->send();
        }
    }

    public function index(Request $r) 
    {

        $posts = $this->MakeQuery($r);
    
        $posts = $posts->orderBy('created_at', 'desc')->paginate(10);
        if ($r->ajax()) {
            return view('new_post.list-element')->with(compact('posts'));
        } else {
            return view('new_post.list')->with(compact('posts'));
        }
    }
    private function MakeQuery ($request,$export = 0 ) {
            
        $publish = $request->get('publish_status');
        $created = $request->has('created_date') ? $request->get('created_date') : null ;
        $keyword = $request->has('keyword') ? $request->get('keyword') : null ;

        $item = NewPost::where('property_id','=',Auth::user()->property_id);
        if($export) {
            $item = $item->with('trackingCount');
        }
        if (!empty($keyword)) {

            $item = $item->where(function ($q) use ($keyword) {
                $q->where('title','like',"%".$keyword."%");
                $q->orWhere('title_en','like',"%".$keyword."%");
                $q->orWhere('detail','like',"%".$keyword."%");
                $q->orWhere('detail_en','like',"%".$keyword."%");
            });
        }
        if (!empty($created)) {
            $item = $item->whereRaw(DB::raw("DATE(created_at) = '".str_replace('/','-',$created)."'"));
        }
       

        if( $request->get('publish_status') && $request->get('publish_status') != "-") {
            $item = $item->where('publish_status',$request->get('publish_status'));
        }
        
        return $item;
    }


    public function add()
    {
    
        $post = new NewPost;
        $existed_product = [];
        $existed_province = [];
        return view('new_post.add')->with(compact('post','existed_product','existed_province'));
    }

    public function edit($id)
    {
    
        $existed_product = [];
        $existed_province = [];
        $post = NewPost::find($id);
     
    
        return view('new_post.edit')->with(compact('post'));
    }

    public function save(Request $r)
    {

        if ($r->isMethod('post')) { 
            // dd($r->all());          
            if ($r->get('id')) {
                
                $post = NewPost::find($r->get('id'));
            } else {
                $post = new NewPost;
                $post->user_id      = Auth::user()->id;
                $post->property_id  = Auth::user()->property_id;
            }

            $post->fill($r->all());

            $remove = $r->get('remove');
			if(!empty($remove['post-file'])) {
				foreach ($remove['post-file'] as $file) {
					$file = PostFile::find($file);
					$this->removeFile($file->name);
					$file->delete();
				}
			}
            $post->save();

            if(!empty($r->get('attachment_file'))) {
				
				foreach ($r->get('attachment_file') as $img) {
                    //Move Image
                    $path = $this->createLoadBalanceDir($img['name']);
                    $bc = new PostFile;
                    $bc->name = strtolower($img['name']);
                    $bc->url = $path;
                    $bc->post_id = $post->id;
                    $bc->file_type = $img['mime'];
                    $bc->is_image = $img['isImage'];
                    // $bc->flag_cover =  'f';
                    $bc->original_name = strtolower($img['originalName']);
                    $bc->save();
                    }
				
			}
            if(!empty($r->get('attachment'))) {
                $ax =  $r->get('img-x');
                $ay =   $r->get('img-y');
                $aw =   $r->get('img-w');
                $ah =   $r->get('img-h');
                foreach ($r->get('attachment') as $index => $file) {
                
                    $name 	= $file['name'];
                    $x 		= $ax[$index];
                    $y 		= $ay[$index];
                    $w 		= $aw[$index];
                    $h 		= $ah[$index];
                    cropBannerImg ($name,$x,$y,$w,$h);
                    $path = $this->createLoadBalanceDir($file['name']);
                    $tempUrl = "/%s%s";

                    $bc = new PostFile;
                    $bc->name = strtolower($file['name']);
                    $bc->url = $path;
                    $bc->post_id = $post->id;
                    $bc->file_type = $file['mime'];
                    $bc->is_image = 't';
                    $bc->flag_cover =  't';
                    $bc->original_name = strtolower($file['originalName']);
                    $bc->save();
                }
            }
            if( $post->publish_status == "t" ) {
				$this->addCreatePostNotification($post);
			}
        }
        return redirect('feed');
    }

    public function createLoadBalanceDir($name)
    {
        $targetFolder = public_path() . DIRECTORY_SEPARATOR . 'upload_tmp' . DIRECTORY_SEPARATOR;
        $folder = substr($name, 0, 2);
        $pic_folder = 'post-file/' . $folder;
        $directories = Storage::disk('s3')->directories('post-file'); // Directory in Amazon
        if (!in_array($pic_folder, $directories)) {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder . "/" . strtolower($name);
        Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder . $name), 'public');
        File::delete($targetFolder . $name);
        return $folder . "/";
    }
  

    public function removeFile($name)
    {
        $folder = substr($name, 0,2);
        $file_path = 'post-file'."/".$folder."/".$name;
        $exists = Storage::disk('s3')->has($file_path)
        ;
        if ($exists) {
            Storage::disk('s3')->delete($file_path);
        }
    }

    public function view($id)
    {
        $post = NewPost::find($id);
        return view('new_post.view')->with(compact('post'));
    }

    public function delete(Request $r)
    {
        if( $r->ajax() ) {
            $post = NewPost::find($r->get('id'));
            $image = PostFile::where('post_id',$r->get('id'))->get();
            if($image){
                foreach($image as $row){
                    $this->removeFile($row->name);
                    $res = PostFile::where('id',$row->id)->delete();
                  }
                 }
            $post->delete();
            return response()->json(['status'=>true]);
        }   
    }
    public function addCreatePostNotification($post) {
        $users = User::where('property_id',Auth::user()->property_id)->whereNull('verification_code')->whereNotIn('id', [Auth::user()->id])->get();
        // dd($users);
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
}