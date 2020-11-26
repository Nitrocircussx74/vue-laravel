<?php namespace App\Http\Controllers\API;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Storage;
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
use Auth;
use File;
class ComplainController extends Controller {

    public function __construct () {
        $this->middleware('jwt.feature_menu:menu_complain');
    }

    public function complain () {
        $complains = Complain::with('owner','category','comments','comments.owner')->where(function ($q) {
            $q->where('user_id','=',Auth::user()->id)->orWhere('property_unit_id','=',Auth::user()->property_unit_id);
        })->get()->sortByDesc('created_at');
        $count_new = Complain::where(function ($q) {
            $q->where('user_id','=',Auth::user()->id)->orWhere('property_unit_id','=',Auth::user()->property_unit_id);
        })->where('complain_status','=',0)->count();
        $c_cate = ComplainCategory::all();

        $results = [
            'complain_new_count' => $count_new,
            'complain' => array_values($complains->toArray()),
            'complain_category' => $c_cate
        ];

        return response()->json($results);
    }

    public function complainListAll () {
        $complains = Complain::with('owner','category')->where('property_id','=',Auth::user()->property_id)->get()->sortByDesc('created_at');
			  $count_new = Complain::where('property_id','=',Auth::user()->property_id)->where('complain_status','=',0)->count();

        $c_cate = ComplainCategory::all();

        $results = [
            'complain_new_count' => $count_new,
            'complain' => array_values($complains->toArray()),
            'complain_category' => $c_cate
        ];

        return response()->json($results);
    }

    public function add () {
        if(Request::isMethod('post')) {
            $complain = new Complain;
            $complain->fill(Request::all());
            $complain->user_id 			= Auth::user()->id;
            $complain->property_id 		= Auth::user()->property_id;
            $complain->property_unit_id = Auth::user()->property_unit_id;
            $complain->complain_status 	= 0;
            $complain->save();

            $this->addCreateComplainNotification($complain);

            return response()->json(['cid' =>$complain->id]);
        }
    }

    public function addFileComplain () {
        try {
            // Get Post
            $complain = Complain::find(Request::get('cid'));
            $complain->save();

            $attach = [];

            /* New Function */
            if(count(Request::file('attachment'))) {
                foreach (Request::file('attachment') as $key => $file) {
                    $name =  md5($file->getFilename());//getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $targetName = $name.".".$extension;

                    $path = $this->createLoadBalanceDir($file);

                    $isImage = 0;
                    if(in_array($extension, ['jpeg','jpg','gif','png'])) {
                        $isImage = 1;
                    }

                    $attach[] = new ComplainFile([
                        'name' => $targetName,
                        'url' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'is_image'	=> $isImage,
                        'original_name'	=> $file->getClientOriginalName()
                    ]);
                }
                $complain->attachment_count = ++$key;
                $complain->save();
                $complain->complainFile()->saveMany($attach);
            }

            return response()->json(['success' =>'true']);

        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function detail($id) {
        $complain = Complain::with('owner','category','complainFile','comments','comments.owner')->find($id);

        $results = $complain->toArray();

        // Change File type in Array
        foreach($results['complain_file'] as &$value)
        {
            $splitType = explode(".",$value['name']);
            $value['file_type'] = end($splitType);
        }

        return response()->json($results);
    }

    public function chiefChangeStatus () {
        try {
            $cid = Request::get('cid');
            $complain = Complain::find($cid);
            if($complain->count() && (Auth::user()->role == 1 || Auth::user()->role == 3 || Auth::user()->is_chief )) {
                $complain->complain_status = Request::get('status');
                $complain->save();
                //Add Notification
                $this->addChangeStatusComplainNotification ($complain);
            }
            return response()->json(['success' =>'true']);
        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function userConfirm(){
        try{
            $cid = Request::get('cid');
            $complain = Complain::find($cid);
            if($complain->user_id == Auth::user()->id) {
                if(Request::get('status') == "true") {
                    // User confirm
                    $complain->complain_status = 3;
                    $complain->review_rate = Request::get('review_rate');
                    $complain->review_comment = Request::get('review_comment');
                    $complain->save();
                    //Add Notification
                    $this->addUserChangeStatusComplainNotification($complain);
                }else{
                    // User reject
                    $complain->complain_status = 0;
                    $complain->save();

                    $comment = new ComplainComment([
                        'description' 	=> Request::get('comment'),
                        'user_id'		=> Auth::user()->id,
                        'is_reject'     => true
                    ]);

                    $complain->comments()->save($comment);
                    //Add Notification
                    $this->addUserChangeStatusComplainNotification($complain);
                }

                return response()->json(['success' =>'true']);
            }else{
                return response()->json(['success' =>'false']);
            }
        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function addComment () {
        if (Request::isMethod('post')) {
            $comment = new ComplainComment([
                'description' 	=> Request::get('comment'),
                'user_id'		=> Auth::user()->id
            ]);
            $complain = Complain::with('owner')->find(Request::get('cid'));
            if($complain) {
                $complain->comments()->save($comment);
                //Add Notification
                if( $complain->user_id != Auth::user()->id && $complain->owner->notification ) {
                    $this->addComplainCommentNotification ($complain);
                }

                $status = true;
            } else {
                $status = false;
            }
            return response()->json(['success' =>$status]);
        }
    }

    public function getAttach ($id) {
        $file = ComplainFile::find($id);
        $folder = str_replace('/', DIRECTORY_SEPARATOR, $file->url);
        $file_path = 'complain-file'.DIRECTORY_SEPARATOR.$folder.$file->name;
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

    public function createLoadBalanceDir ($imageFile) {
        $name =  md5($imageFile->getFilename());//getClientOriginalName();
        $extension = $imageFile->getClientOriginalExtension();
        $targetName = $name.".".$extension;

        $folder = substr($name, 0,2);

        $pic_folder = 'complain-file'.DIRECTORY_SEPARATOR.$folder;
        $directories = Storage::disk('s3')->directories('complain-file'); // Directory in Amazon
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

    public function addCreateComplainNotification($complain) {
        // Get Admin and Committee
        /*$users = User::where('property_id',Auth::user()->property_id)
            ->where(function ($q) {
                $q ->orWhere('role',1)
                    ->orWhere('is_chief',true);
            })->get();*/

        // Get Admin
        $users = $this->getChief();


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

    public function addUserChangeStatusComplainNotification($complain) {
        // Get Just Admin
        $users = $this->getChief();

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

                Pusher::trigger(Auth::user()->property_id."_".$user->id, 'notification_event', $dataPusher);
            }

        }
    }

    public function addChangeStatusComplainNotification($complain) {
        $status = ['status_rj','status_ip','status_ck','status_cf','status_cls'];
        $title = json_encode( ['type'=>'change_status','c_title'=>$complain->title,'status' => $status[$complain->complain_status]] );
        $notification = Notification::create([
            'title'				=> $title,
            'description' 		=> "",
            'notification_type' => 2,
            'subject_key'		=> $complain->id,
            'to_user_id'		=> $complain->user_id,
            'from_user_id'		=> Auth::user()->id
        ]);
        $controller_push_noti = new PushNotificationController();
        $controller_push_noti->pushNotification($notification->id);
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
}
