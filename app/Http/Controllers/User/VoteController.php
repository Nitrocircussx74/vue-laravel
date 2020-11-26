<?php namespace App\Http\Controllers\User;
use Request;
use Auth;
use File;
use Storage;
use Illuminate\Routing\Controller;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
# Model
use App\Vote;
use App\VoteFile;
use App\Choice;
use App\UserChoice;
use App\PostReport;
use App\User;
use App\Notification;
class VoteController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_vote');
		view()->share('active_menu', 'vote');
	}
	public function index()
	{
		if(Request::ajax()) {
			$votes = new Vote;
			if(Request::get('type')) {
				if(Request::get('type') == 'new'){
					$votes = $votes->with('creator')
							->doesntHave('userChoose')
							->where('property_id', Auth::user()->property_id)
							->orderBy('vote.start_date','desc')
							->paginate(15);
				} elseif( Request::get('type') == 'my' ) {
					$votes = $votes->with('creator')
							->where('user_id',Auth::user()->id)
							->orderBy('vote.start_date','desc')
							->paginate(15);
				} else {
				$votes = $votes->with('creator')
							->has('userChoose')
							->orderBy('vote.start_date','desc')
							->where('property_id', Auth::user()->property_id)
							->paginate(15);
				}
			} else {
				$votes = Vote::with(['creator','userChoose' => function($query) {
					$query->where('user_id', Auth::user()->id);
				}])->where('property_id', Auth::user()->property_id)
					->orderBy('vote.start_date','desc')
					->paginate(15);
			}
			return view('vote.vote-list')->with(compact('votes'));
		} else {
			$status = 'new';
			$votes = Vote::with(['creator','userChoose' => function($query) {
				$query->where('user_id', Auth::user()->id);
			}])
							->where('property_id', Auth::user()->property_id)
							->orderBy('vote.start_date','desc')
							->paginate(15);
			$new_count  = Vote::doesntHave('userChoose')
						->where('property_id', Auth::user()->property_id)
						->count();
			return view('vote.index')->with(compact('votes','status','new_count'));
		}

	}

	public function voteByStatus()
	{
		$status = Request::get('type');
		return view('vote.vote-list')->with(compact('votes','status'));
	}

	public function add () {
		if(Request::ajax()) {
				$vote = new Vote;
				return view('vote.add')->with(compact('vote'));
		} else if(Request::isMethod('post')) {
			if(Auth::user()->property->allow_user_add_vote || Auth::user()->role == 1 || Auth::user()->role == 3) {
				$vote = new Vote;
				$vote->fill(Request::all());
				$vote->property_id = Auth::user()->property_id;
				$vote->user_id = Auth::user()->id;
				$vote->save();
				if (!empty(Request::get('attachment'))) {
					$postimg = [];
					foreach (Request::get('attachment') as $file) {
						//Move Image
						$path = $this->createLoadBalanceDir($file['name']);
						$votefile[] = new VoteFile([
								'name' => strtolower($file['name']),
								'url' => $path,
								'file_type' => $file['mime'],
								'is_image' => $file['isImage'],
								'original_name' => strtolower($file['originalName'])
							]
						);
					}
					$vote->voteFile()->saveMany($votefile);
				}
				if (!empty(Request::get('choice'))) {
					foreach (Request::get('choice') as $key => $choice) {
						$choices[] = new Choice (['title' => $choice, 'order_choice' => $key]);
					}
					$vote->voteChoice()->saveMany($choices);
				}
				$this->addCreateVoteNotification($vote);
				return redirect('votes');
			}
		}
	}

	public function edit () {
		if(Request::isMethod('post')) {
			$vote = Vote::find(Request::get('id'));
			if($vote) {
				$vote->fill(Request::all());
				$vote->save();
				if(!empty(Request::get('attachment'))) {
					$postimg = [];
					foreach (Request::get('attachment') as $file) {
						//Move Image
						$path = $this->createLoadBalanceDir($file['name']);
						$votefile[] = new VoteFile([
								'name' => strtolower($file['name']),
								'url' => $path,
								'file_type' => $file['mime'],
								'is_image'	=> $file['isImage'],
								'original_name'	=> strtolower($file['originalName'])
							]
						);
					}
					$vote->voteFile()->saveMany($votefile);
				}
				if(!empty(Request::get('choice'))) {
					foreach (Request::get('choice') as $choice) {
						$choices[] = new Choice (['title' => $choice]);
					}
					$vote->voteChoice()->saveMany($choices);
				}

				if(!empty(Request::get('remove'))) {
					$remove = Request::get('remove');
					// Remove old choices
					if(!empty($remove['choice']))
					Choice::whereIn('id', $remove['choice'])->delete();
					// Remove old files
					if(!empty($remove['vote-file']))
					foreach ($remove['vote-file'] as $file) {
						$file = VoteFile::find($file);
						$this->removeFile($file->name);
						$file->delete();
					}
				}
			}

			return redirect('votes');
		}
	}

	public function view ($id) {
		$this->markAsRead($id);
		$vote = Vote::with(['creator','voteChoice'=> function ($query) {
			$query->orderBy('choice.order_choice', 'asc');

		},'voteFile','userChoose' => function($query){
						    $query->where('user_id', '=', Auth::user()->id);
						}])
						->find($id);

		return view('vote.view')->with('vote', $vote);
	}

	public function vote () {
		if(Request::isMethod('post')) {
			$voted = UserChoice::where('user_id',Auth::user()->id)->where('vote_id',Request::get('vote_id'))->count();
			if($voted == 0) {
				$choice = Choice::find(Request::get('vote_choice'));
				$user_vote = new UserChoice;
				$user_vote->vote_id = Request::get('vote_id');
				$user_vote->choice_id = Request::get('vote_choice');
				$user_vote->user_id = Auth::user()->id;
				$user_vote->save();
				// choice count increment
				++$choice->choice_count;
				$choice->save();
			}
		}
		return redirect('votes/view/'.Request::get('vote_id'));

	}

	public function confirm () {
		if(Request::ajax()) {
			$confirm = VoteConfirmation::firstOrCreate(['user_id' => Auth::user()->id, 'vote_id' => Request::get('eid')]);
			$confirm->confirm_status = Request::get('status');
			$confirm->save();
			return response()->json(['status' => true,'confirm'=>Request::get('status')]);
		}
	}

	public function delete ($id) {
		$vote = Vote::with('creator','voteFile')->find($id);
		if($vote && $vote->creator->id == Auth::user()->id) {

			//remove post report
			$report = PostReport::where('post_id',$vote->id)->where('post_type',3)->first();
			if($report) {
				$report->reportList()->delete();
				$report->delete();
			}
			$this->clearNotification($id);
			$vote->userChoose()->delete();
			$vote->voteChoice()->delete();
			if(!$vote->voteFile->isEmpty()) {
				foreach ($vote->voteFile as $file) {
					$this->removeFile($file->name);
				}
				$vote->voteFile()->delete();
			}
			$vote->delete();
		}
		return redirect('votes');
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
	public function getAttach ($id) {
		$file = VoteFile::find($id);
        $file_path = 'vote-file'.'/'.$file->url.$file->name;
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
		$pic_folder = 'vote-file/'.$folder;
        $directories = Storage::disk('s3')->directories('vote-file'); // Directory in Amazon
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
		$file_path = 'vote-file'.'/'.$folder.'/'.$name;
        Storage::disk('s3')->delete($file_path);
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
			}
			$controller_push_noti = new PushNotificationController();
			$controller_push_noti->dispatchBatchNotification ($title,$vote->id,5,Auth::user()->id,Auth::user()->property_id);
			//$controller_push_noti->pushNotification($notification->id);
		}
	}

	public function getform () {
		$vote = Vote::find(Request::get('id'));
		return view('vote.edit')->with(compact('vote'));
	}

	public function markAsRead ($id) {
		try {
			$notis_counter = Notification::where('subject_key', '=', $id)->where('to_user_id', '=', Auth::user()->id)->get();
			if ($notis_counter->count() > 0) {
				$notis = Notification::find($notis_counter->first()->id);
				$notis->read_status = true;
				$notis->save();
			}
			return true;
		}catch(Exception $ex){
			return false;
		}
	}
}
