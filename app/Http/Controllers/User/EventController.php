<?php namespace App\Http\Controllers\User;
use Request;
use Auth;
use File;
use View;
use Storage;
use Illuminate\Routing\Controller;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
# Model
use App\Event;
use App\EventFile;
use App\EventConfirmation;
use App\PostReport;
use App\User;
use App\Notification;
class EventController extends Controller {

	public function __construct (Event $Event) {
		$this->Event = $Event;
		$this->middleware('auth:menu_event');
		view()->share('active_menu', 'event');
	}
	public function index()
	{
		$status = 'new';
		$events = Event::with(['creator','confirmation' => function($query){
					    $query->where('user_id', '=', Auth::user()->id);
					}])
						->where('property_id', Auth::user()->property_id)
						->where('end_date_time',">=",date('Y-m-d H:i:s'))
						->orderBy('event.start_date_time')
						->get();
		return view('event.index')->with(compact('events','status'));
	}

	public function eventByStatus()
	{
		$status = Request::get('type');
		if( !in_array( $status, ['my', 'all', 'new'] ) ) {
			$events = Event::whereHas('confirmation',function ($query) use ($status) {
							$query->where('confirm_status', '=', $status)
							->where('user_id',"=",Auth::user()->id);
						})
						->orderBy('event.start_date_time')
						->get();
		} elseif ( $status == 'new' ) {
				$events = Event::with(['creator','confirmation' => function($query){
					    $query->where('user_id', '=', Auth::user()->id);
					}])
					->where('property_id', Auth::user()->property_id)
					->where('end_date_time',">=",date('Y-m-d H:i:s'))
					->orderBy('event.start_date_time')
					->get();
		} elseif ( $status == 'my' ) {
				$events = Event::with(['creator','confirmation' => function($query){
					    $query->where('user_id', '=', Auth::user()->id);
					}])
					->where('event.user_id',Auth::user()->id)
					->orderBy('event.start_date_time','desc')
					->get();
		} else {
			$events = Event::with(['creator','confirmation' => function($query){
			    $query->where('user_id', '=', Auth::user()->id);
			}])
			->orderBy('event.start_date_time')
			->paginate(10);
		}
		return view('event.event-list')->with(compact('events','status'));
	}

	public function add () {
		$event = new Event;
		$event->fill(Request::all());
		$event->start_date_time = date('Y-m-d',strtotime(Request::get('start_date')))." ".date('H:i:s',strtotime(Request::get('start_time')));
		$event->end_date_time 	= date('Y-m-d',strtotime(Request::get('end_date')))." ".date('H:i:s',strtotime(Request::get('end_time')));
		$event->property_id 	= Auth::user()->property_id;
		$event->user_id 		= Auth::user()->id;
		$event->save();
		if(!empty(Request::get('attachment'))) {
			$postimg = [];
			foreach (Request::get('attachment') as $img) {
				//Move Image
				$path = $this->createLoadBalanceDir($img['name']);
				$eventimg[] = new EventFile([
					'name' => strtolower($img['name']),
					'url' => $path,
					'file_type' => $img['mime'],
					'is_image'	=> $img['isImage'],
					'original_name'	=> strtolower($img['originalName'])
				]);
			}
			$event->eventFile()->saveMany($eventimg);
		}
		$this->addCreateEventNotification($event);
		return redirect('events');
	}

	public function edit () {
		if(Request::isMethod('post')) {
			$event = Event::find(Request::get('id'));
			$event->fill(Request::all());
			$event->start_date_time = date('Y-m-d',strtotime(Request::get('start_date')))." ".date('H:i:s',strtotime(Request::get('start_time')));
			$event->end_date_time 	= date('Y-m-d',strtotime(Request::get('end_date')))." ".date('H:i:s',strtotime(Request::get('end_time')));
			$event->property_id 	= Auth::user()->property_id;
			$event->user_id 		= Auth::user()->id;
			$event->save();
			if(!empty(Request::get('attachment'))) {
				$postimg = [];
				foreach (Request::get('attachment') as $img) {
					//Move Image
					$path = $this->createLoadBalanceDir($img['name']);
					$eventimg[] = new EventFile([
						'name' => strtolower($img['name']),
						'url' => $path,
						'file_type' => $img['mime'],
						'is_image'	=> $img['isImage'],
						'original_name'	=> strtolower($img['originalName'])
					]);
				}
				$event->eventFile()->saveMany($eventimg);
			}

			if(!empty(Request::get('remove'))) {
				$remove = Request::get('remove');
				// Remove old files
				if(!empty($remove['event-file']))
				foreach ($remove['event-file'] as $file) {
					$file = EventFile::find($file);
					$this->removeFile($file->name);
					$file->delete();
				}
			}
		}
		return redirect('events');
	}

	public function view () {
		if(Request::ajax()) {
			$this->markAsRead(Request::get('eid'));
			$event = Event::with(['creator','eventFile','confirmation' => function($query){
							    $query->where('user_id', '=', Auth::user()->id);
							}])
							->find(Request::get('eid'));

			$count_go 		= EventConfirmation::where('event_id',Request::get('eid'))->where('confirm_status',1)->count();
			$count_maybe 	= EventConfirmation::where('event_id',Request::get('eid'))->where('confirm_status',2)->count();
			$count_not_go 	= EventConfirmation::where('event_id',Request::get('eid'))->where('confirm_status',3)->count();
			$detail = view('event.detail')->with(compact('event','count_go','count_maybe','count_not_go'))->render();
			return response()->json(['status' => true, 'detail' => $detail,'head'=>e($event->title)]);
		}
	}

	public function delete ($id) {
		$event = Event::with('creator','eventFile')->find($id);
		if($event && $event->creator->id == Auth::user()->id) {

			//remove post report
			$report = PostReport::where('post_id',$event->id)->where('post_type',1)->first();
			if($report) {
				$report->reportList()->delete();
				$report->delete();
			}
			$this->clearNotification($id);

			$event->confirmationAll()->delete();
			if(!$event->eventFile->isEmpty()) {
				foreach ($event->eventFile as $file) {
					$this->removeFile($file->name);
				}
				$event->eventFile()->delete();
			}
			$event->delete();
		}
		return redirect('events');
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

	public function confirm () {
		if(Request::ajax()) {
			$confirm = EventConfirmation::firstOrCreate(['user_id' => Auth::user()->id, 'event_id' => Request::get('eid')]);
			$confirm->confirm_status = Request::get('status');
			$confirm->save();
			return response()->json(['status' => true,'confirm'=>Request::get('status')]);
		}
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'event-file/'.$folder;
        $directories = Storage::disk('s3')->directories('event-file'); // Directory in Amazon
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
		$file_path = 'event-file/'.$folder."/".$name;
		if(Storage::disk('s3')->has($file_path)) {
			Storage::disk('s3')->delete($file_path);
		}
	}

	public function addCreateEventNotification($event) {
		$users = User::where('property_id',Auth::user()->property_id)->whereNull('verification_code')->whereNotIn('id', [Auth::user()->id])->get();
		if($users->count()) {
			$title = json_encode( ['type'=>'event_created','title'=>$event->title] );
			foreach ($users as $user) {
				$notification = Notification::create([
					'title'				=> $title,
					'description' 		=> "",
					'notification_type' => 4,
					'subject_key'		=> $event->id,
					'to_user_id'		=> $user->id,
					'from_user_id'		=> Auth::user()->id
				]);
			}
			$controller_push_noti = new PushNotificationController();
			$controller_push_noti->dispatchBatchNotification ($title,$event->id,4,Auth::user()->id,Auth::user()->property_id);
			//$controller_push_noti->pushNotification($notification->id);
		}
	}

	public function getform () {
		$event = Event::find(Request::get('id'));
		return view('event.edit')->with(compact('event'));
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
