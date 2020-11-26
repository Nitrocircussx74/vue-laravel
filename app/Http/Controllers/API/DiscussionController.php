<?php namespace App\Http\Controllers\API;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
# Model
use App\Discussion;
use App\DiscussionComment;
use App\DiscussionFile;
use App\Notification;
use App\User;
use Auth;
use File;
class DiscussionController extends Controller {

    public function __construct () {
        $this->middleware('jwt.feature_menu:menu_committee_room');
    }

    public function discussion () {
        if(Auth::user()->is_chief) {
            $discussions = Discussion::with('owner','comments')->where('property_id', '=', Auth::user()->property_id)->orderBy('created_at', 'DESC')->paginate(15);
            $count_new = Discussion::where('property_id', '=', Auth::user()->property_id)->count();

            $resultsDiscussion = $discussions->toArray();
            foreach($resultsDiscussion['data'] as &$value)
            {
                $value['comment_counter'] = count($value['comments']);
                unset($value['comments']);
            }

            $results = [
                'discussions_new_count' => $count_new,
                'discussions' => $resultsDiscussion
            ];

        }else{
            $results = [
                'discussions_new_count' => 0,
                'discussions' => 0,
                'msg'=>'Not role for access for discussion'
            ];
        }

        return response()->json($results);
    }

    public function add () {
        if(Request::isMethod('post')) {
            $discussion = new Discussion;
            $discussion->fill(Request::all());
            $discussion->user_id 			= Auth::user()->id;
            $discussion->property_id 		= Auth::user()->property_id;
            $discussion->save();

            $this->addCreateDiscussionNotification($discussion);

            return response()->json(['discuss_id' =>$discussion->id]);
        }
    }

    public function addFileDiscussion() {
        try {
            // Get Discussion
            $discussion = Discussion::find(Request::get('discuss_id'));
            $attach = [];

            if(count(Request::file('attachment'))) {
                foreach (Request::file('attachment') as $key => $file) {
                    $name =  md5($file->getFilename());//getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $targetName = $name.".".$extension;

                    //Move Image
                    $path = $this->createLoadBalanceDir($file);

                    $isImage = 0;
                    if(in_array($extension, ['jpeg','jpg','gif','png'])) {
                        $isImage = 1;
                    }

                    $attach[] = new DiscussionFile([
                        'name' => $targetName,
                        'url' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'is_image'	=> $isImage,
                        'original_name'	=> $file->getClientOriginalName()
                    ]);
                }
                $discussion->attachment_count = ++$key;
                $discussion->save();
                $discussion->discussionFile()->saveMany($attach);
            }

            return response()->json(['success' =>'true']);

        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function view ($id) {
        if(Auth::user()->is_chief) {
            $discussion = Discussion::with('owner', 'discussionFile', 'comments', 'comments.owner')->find($id);

            $resultsDiscussion = $discussion->toArray();

            // Change File type in Array
            foreach ($resultsDiscussion['discussion_file'] as &$value) {
                $splitType = explode(".", $value['name']);
                $value['file_type'] = end($splitType);
            }

            $results = [
                'discussion' => $resultsDiscussion,
                'comment_count' => count($resultsDiscussion['comments'])
            ];
        }else{
            $results = [
                'discussion' => "",
                'comment_count' => "",
                'msg' => "Not Role to Access"
            ];
        }

        return response()->json($results);
    }

    public function addComment () {
        if (Request::isMethod('post')) {
            $comment = new DiscussionComment([
                'description' 	=> Request::get('comment'),
                'user_id'		=> Auth::user()->id
            ]);
            $discussion = Discussion::with('owner')->find(Request::get('discuss_id'));
            if($discussion) {
                $comment = $discussion->comments()->save($comment);
                //Add Notification
                if( $discussion->user_id != Auth::user()->id && $discussion->owner->notification ) {
                    $this->addDiscussionCommentNotification ($discussion);
                }

                $status = true;
            } else {
                $status = false;
            }
            return response()->json(['status' => $status]);
        }
    }

    public function edit() {
        try{
            if ( Request::isMethod('post') ) {
                $discussion = Discussion::find(Request::get('discuss_id'));
                if ($discussion->user_id == Auth::user()->id) {
                    $discussion->detail = Request::get('detail');
                    $discussion->save();
                }else{
                    return response()->json(['success' =>'false', 'description' => 'not owner post']);
                }
            }

            return response()->json(['success' =>'true']);

        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
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

    public function delete () {
        try {
            $discuss_id = Request::get('discuss_id');
            $discussion = Discussion::with('discussionFile')->find($discuss_id);
            if ($discussion->user_id == Auth::user()->id) {
                $discussion->comments()->delete();
                if (!$discussion->discussionFile->isEmpty()) {
                    foreach ($discussion->discussionFile as $file) {
                        $this->removeFile($file->name);
                    }
                    $discussion->discussionFile()->delete();
                }
                $discussion->delete();
            }

            return response()->json(['success' =>'true']);

        } catch(Exception $ex){
            return response()->json(['success' =>'false', 'msg' => $ex->getMessage()]);
        }
    }

    public function deleteFile () {
        try {
            $discuss_fid = Request::get('discuss_fid');// Discuss file ID
            $discuss_id = Request::get('discuss_id');// Discuss ID

            $discuss = Discussion::find($discuss_id);
            if ($discuss && $discuss->user_id == Auth::user()->id) {

                $discussFile = DiscussionFile::find($discuss_fid);
                if ($discussFile) {
                    $discuss->attachment_count = $discuss->attachment_count - 1;
                    $discuss->save();
                    $this->removeFile($discussFile->name);
                    $discussFile->delete();
                    return response()->json(['status' => true]);
                }else{
                    return response()->json(['status' => false, 'msg'=> 'File not found']);
                }
            }else {
                return response()->json(['status' => false, 'msg'=> 'Not owner file']);
            }
        }catch (Exception $e){
            return response()->json(['status' => false, 'msg'=> $e->getMessage()]);
        }
    }

    public function removeFile ($name) {
        $folder = substr($name, 0,2);
        $file_path = 'discussion-file'.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            Storage::disk('s3')->delete($file_path);
        }
    }

    public function createLoadBalanceDir ($imageFile) {
        $name =  md5($imageFile->getFilename());//getClientOriginalName();
        $extension = $imageFile->getClientOriginalExtension();
        $targetName = $name.".".$extension;

        $folder = substr($name, 0,2);

        $pic_folder = 'discussion-file'.DIRECTORY_SEPARATOR.$folder;
        $directories = Storage::disk('s3')->directories('discussion-file'); // Directory in Amazon
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
