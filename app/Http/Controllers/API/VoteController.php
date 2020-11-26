<?php namespace App\Http\Controllers\API;
use Request;
use Illuminate\Routing\Controller;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use Carbon\Carbon;
# Model
use App\Vote;
use App\VoteFile;
use App\Choice;
use App\UserChoice;
use App\Notification;
use App\User;

use Auth;
use File;
use View;
class VoteController extends Controller {

    public function __construct () {
        $this->middleware('jwt.feature_menu:menu_vote');
    }
    public function index()
    {
        $votes = new Vote;
        if(Request::get('type')) {
            $type = Request::get('type');
            //Type 0 = New, 1 = Voted/Result, 2 = My Poll, 3 = All Poll
            switch ($type) {
                case "0":
                    $votes_data = $votes->with('creator')
                        ->doesntHave('userChoose')
                        ->where('property_id', Auth::user()->property_id)
                        ->orderBy('vote.start_date','desc')
                        ->paginate(15);
                    break;
                case "1":
                    $votes_data = $votes->with('creator')
                        ->has('userChoose')
                        ->orderBy('vote.start_date','desc')
                        ->where('property_id', Auth::user()->property_id)
                        ->paginate(15);
                    break;
                case "2":
                    $votes_data = $votes->with('creator')
                        ->where('user_id',Auth::user()->id)
                        ->orderBy('vote.start_date','desc')
                        ->paginate(15);
                    break;
                case "3":
                    $votes_data = Vote::with(['creator','userChoose' => function($query) {
                      $query->where('user_id', Auth::user()->id);
                    }])->where('property_id', Auth::user()->property_id)
                      ->orderBy('vote.start_date','desc')
                      ->paginate(15);
                    break;
                default:
                    $votes_data = $votes->with('creator')
                        ->doesntHave('userChoose')
                        ->where('property_id', Auth::user()->property_id)
                        ->orderBy('vote.start_date','desc')
                        ->paginate(15);
                    break;
            }
        } else {
            $votes_data = $votes->with('creator')
                ->where('property_id', Auth::user()->property_id)
                ->orderBy('vote.start_date','desc')
                ->paginate(15);
        }

        $results = [
            'vote' => $votes_data->toArray()
        ];

        return response()->json($results);
    }

    public function add () {
        try {
            if(Auth::user()->property->allow_user_add_vote) {
                $start_date_formate = date("Y-m-d", strtotime(Request::get('start_date')));
                $start_time_formate = date("H:i", strtotime(Request::get('start_time')));
                $dt_start = Carbon::parse($start_date_formate . " " . $start_time_formate);

                if ($dt_start->isFuture()) {
                    $end_date_formate = date("Y-m-d", strtotime(Request::get('end_date')));
                    $end_time_formate = date("H:i", strtotime(Request::get('end_time')));
                    $dt_end = Carbon::parse($end_date_formate . " " . $end_time_formate);

                    $check_diff = $dt_start->diffInMinutes($dt_end, false);
                    if ($check_diff > 0) {
                        $vote = new Vote;
                        $vote->fill(Request::all());
                        $vote->property_id = Auth::user()->property_id;
                        $vote->user_id = Auth::user()->id;
                        $vote->save();

                        if (!empty(Request::get('choice'))) {
                            foreach (Request::get('choice') as $key => $choice) {
                                $choices[] = new Choice (['title' => $choice, 'order_choice' => $key]);
                            }
                            $vote->voteChoice()->saveMany($choices);
                        }

                        $this->addCreateVoteNotification($vote);
                        return response()->json(['vote_id' => $vote->id]);
                    } else {
                        return response()->json(['success' => 'false', 'msg' => 'StartDateTime more than EndDateTime']);
                    }
                } else {
                    return response()->json(['success' => 'false', 'msg' => 'StartDate is past']);
                }
            }else{
                return response()->json(['success' => 'false', 'msg' => 'Disallow create vote']);
            }
        }catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function edit () {
        try{
            $vote_old = Vote::find(Request::get('vid'));
            $dt_start_old = Carbon::parse($vote_old->start_date . " " . $vote_old->start_time);

            if($dt_start_old->isFuture()) {
                $start_date_formate = date("Y-m-d", strtotime(Request::get('start_date')));
                $start_time_formate = date("H:i", strtotime(Request::get('start_time')));
                $dt_start = Carbon::parse($start_date_formate . " " . $start_time_formate);

                if ($dt_start->isFuture()) {
                    $end_date_formate = date("Y-m-d", strtotime(Request::get('end_date')));
                    $end_time_formate = date("H:i", strtotime(Request::get('end_time')));
                    $dt_end = Carbon::parse($end_date_formate . " " . $end_time_formate);

                    $check_diff = $dt_start->diffInMinutes($dt_end, false);
                    if ($check_diff > 0) {
                        $vote = Vote::find(Request::get('vid'));
                        if ($vote->user_id == Auth::user()->id) {
                            $vote->fill(Request::all());
                            $vote->property_id = Auth::user()->property_id;
                            $vote->user_id = Auth::user()->id;
                            $vote->save();

                            if (!empty(Request::get('choice'))) {
                                // Delete Old Choice
                                $vote->voteChoice()->delete();

                                // Add new Choice
                                foreach (Request::get('choice') as $choice) {
                                    $choices[] = new Choice (['title' => $choice]);
                                }
                                $vote->voteChoice()->saveMany($choices);
                            }

                            //$this->addCreateVoteNotification($vote);
                            return response()->json(['vote_id' => $vote->id]);
                        } else {
                            return response()->json(['success' => 'false', 'msg' => 'Not owner of Votes']);
                        }
                    } else {
                        return response()->json(['success' => 'false', 'msg' => 'StartDateTime more than EndDateTime']);
                    }
                } else {
                    return response()->json(['success' => 'false', 'msg' => 'StartDate is past']);
                }
            }else{
                return response()->json(['success' => 'false', 'msg' => 'Votes already start']);
            }
        }catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function addFileVote () {
        try {
            // Get Vote
            $vote = Vote::find(Request::get('vote_id'));
            $vote->save();

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

                    $attach[] = new VoteFile([
                        'name' => $targetName,
                        'url' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'is_image'	=> $isImage,
                        'original_name'	=> $file->getClientOriginalName()
                    ]);
                }
                //$vote->attachment_count = ++$key;
                $vote->save();
                $vote->voteFile()->saveMany($attach);
            }

            return response()->json(['success' =>'true']);

        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function view ($id) {
        $vote = Vote::with(['creator','voteChoice'=> function ($query) {
            $query->orderBy('choice.order_choice', 'asc');

        },'voteFile','userChoose' => function($query){
            $query->where('user_id', '=', Auth::user()->id);
        }])
            ->find($id);

        $results = $vote->toArray();

        foreach($results['vote_file'] as &$value)
        {
            $splitType = explode(".",$value['name']);
            $value['file_type'] = end($splitType);
        }

        return response()->json($results);
    }

    public function vote () {
        try {
            if (Request::isMethod('post')) {
                $voted = UserChoice::where('user_id', Auth::user()->id)->where('vote_id', Request::get('vote_id'))->count();
                if ($voted == 0) {
                    $choice = Choice::find(Request::get('vote_choice_id'));
                    $user_vote = new UserChoice;
                    $user_vote->vote_id = Request::get('vote_id');
                    $user_vote->choice_id = Request::get('vote_choice_id');
                    $user_vote->user_id = Auth::user()->id;
                    $user_vote->save();
                    // choice count increment
                    ++$choice->choice_count;
                    $choice->save();
                }
            }
            return response()->json(['success' => 'true']);

        }catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function delete () {
        try {
            if(Request::get('vote_id')) {
                $id = Request::get('vote_id');
                $vote = Vote::with('creator', 'voteFile')->find($id);
                if ($vote && $vote->creator->id == Auth::user()->id) {
                    $vote->userChoose()->delete();
                    $vote->voteChoice()->delete();
                    if (!$vote->voteFile->isEmpty()) {
                        foreach ($vote->voteFile as $file) {
                            $this->removeFile($file->name);
                        }
                        $vote->voteFile()->delete();
                    }
                    $vote->delete();
                }
                return response()->json(['success' => 'true']);
            }
        }catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function deleteFile () {
        try {
            $vfid = Request::get('vfid');// Vote file ID
            $vid = Request::get('vid');// Vote ID

            $vote = Vote::with('creator', 'voteFile')->find($vid);
            if ($vote && $vote->creator->id == Auth::user()->id) {

                $voteFile = VoteFile::find($vfid);
                if ($voteFile) {
                    $this->removeFile($voteFile->name);
                    $voteFile->delete();
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

    public function getAttach ($id) {
        $file = VoteFile::find($id);
        $folder = str_replace('/', DIRECTORY_SEPARATOR, $file->url);
        $file_path = 'vote-file'.DIRECTORY_SEPARATOR.$folder.$file->name;
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

        $pic_folder = 'vote-file'.DIRECTORY_SEPARATOR.$folder;
        $directories = Storage::disk('s3')->directories('vote-file'); // Directory in Amazon
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
        $file_path = 'vote-file'.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            Storage::disk('s3')->delete($file_path);
        }
    }

    public function addCreateVoteNotification($vote) {
        $users = User::where('property_id',Auth::user()->property_id)->whereNull('verification_code')->whereNotIn('id', [Auth::user()->id])->get();
        if($users->count()) {
            $title = json_encode( ['type'=>'vote_created','title'=>$vote->title] );
            foreach ($users as $user) {
                $notification = Notification::create([
                    'title'				=> $title,
                    'description' 		=> "",
                    'notification_type' => 5,
                    'subject_key'		=> $vote->id,
                    'to_user_id'		=> $user->id,
                    'from_user_id'		=> Auth::user()->id
                ]);
                $controller_push_noti = new PushNotificationController();
                $controller_push_noti->pushNotification($notification->id);
            }

        }
    }
}
