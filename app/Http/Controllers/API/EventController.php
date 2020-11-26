<?php namespace App\Http\Controllers\API;
use Mockery\CountValidator\Exception;
use Request;
use Illuminate\Routing\Controller;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use Carbon\Carbon;
# Model
use App\Event;
use App\EventFile;
use App\EventConfirmation;
use App\Notification;
use App\User;
use Auth;
use File;
use View;
class EventController extends Controller {

    public function __construct (Event $Event) {
        $this->middleware('jwt.feature_menu:menu_event');
        $this->Event = $Event;
    }
    public function index()
    {
        $status = Request::get('type'); // Type => 0=new event, 1=join event, 2=my event, 3=all event

        $events = "";
        switch ($status) {
            case "0":
                $events = Event::with(['creator','confirmationAll'])
                    ->where('property_id', Auth::user()->property_id)
                    ->where('end_date_time',">=",date('Y-m-d H:i:s'))
                    ->orderBy('event.start_date_time')
                    ->paginate(15);
                break;
            case "1":
                $events = Event::with('creator')->whereHas('confirmationAll',function ($query) use ($status) {
                    $query->where('confirm_status', '=', $status)
                        ->where('user_id',"=",Auth::user()->id);
                })
                    ->orderBy('event.start_date_time')
                    ->paginate(15);
                break;
            case "2":
                $events = Event::with(['creator','confirmationAll'])
                    ->where('event.user_id',Auth::user()->id)
                    ->orderBy('event.start_date_time','desc')
                    ->paginate(15);
                break;
            case "3":
                $events = Event::with(['creator','confirmationAll'])
                    ->where('property_id', Auth::user()->property_id)
                    ->orderBy('event.start_date_time')
                    ->paginate(15);
                break;
            default:
                $events = Event::with(['creator','confirmationAll'])
                    ->where('property_id', Auth::user()->property_id)
                    ->orderBy('event.start_date_time')
                    ->paginate(15);
                break;
        }

        foreach ($events as &$item){
            $events_start_array = explode(" ", $item->start_date_time);
            $events_end_array = explode(" ", $item->end_date_time);
            $item['start_date'] = $events_start_array[0];
            $item['start_time'] = $events_start_array[1];
            $item['end_date'] = $events_end_array[0];
            $item['end_time'] = $events_end_array[1];

            $confirm_count = $item->confirmationAll;
            foreach ($confirm_count as $item_confirm){
                if($item_confirm->confirm_status == "1"){
                    $item->join_count++;
                }

                if($item_confirm->confirm_status == "2"){
                    $item->maybe_count++;
                }

                if($item_confirm->confirm_status == "3"){
                    $item->cantgo_count++;
                }
            }
        }

        $results = [
            'status' => $status,
            'events'=> $events->toArray()
        ];

        return response()->json($results);
    }

    public function add () {
        try {
            if (Auth::user()->property->allow_user_add_vote){
                // Sat, 23 Jan 2016
                // 3:23 PM
                $start_date_formate = date("Y-m-d", strtotime(Request::get('start_date')));
                $start_time_formate = date("H:i:s", strtotime(Request::get('start_time')));
                $dt_start = Carbon::parse($start_date_formate . " " . $start_time_formate);
    
                if ($dt_start->isFuture()) {
                    $end_date_formate = date("Y-m-d", strtotime(Request::get('end_date')));
                    $end_time_formate = date("H:i:s", strtotime(Request::get('end_time')));
                    $dt_end = Carbon::parse($end_date_formate . " " . $end_time_formate);
    
                    $check_diff = $dt_start->diffInMinutes($dt_end, false);
                    if ($check_diff > 0) {
    
                        $event = new Event;
                        //$event->fill(Request::all());
                        /*$event->start_date_time = $dt_start;
                        $event->end_date_time = $dt_end;*/
                        $event->title = Request::get('title');
                        $event->location = Request::get('location');
                        $event->description = Request::get('description');;
                        $event->start_date_time = $dt_start;
                        $event->end_date_time = $dt_end;
                        $event->property_id = Auth::user()->property_id;
                        $event->user_id = Auth::user()->id;
                        $event->save();
    
                        $this->addCreateEventNotification($event);
                        return response()->json(['eid' => $event->id]);
                    } else {
                        return response()->json(['success' => 'false', 'msg' => 'StartDateTime more than EndDateTime']);
                    }
                } else {
                    return response()->json(['success' => 'false', 'msg' => 'StartDate is past']);
                }
            }else{
                return response()->json(['success' => 'false', 'msg' => 'Disallow create event']);
            }

        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function edit (){
        try {
            // Sat, 23 Jan 2016
            // 3:23 PM

            $event = Event::find(Request::get('eid'));
            //$dt_start_old = Carbon::parse($event->start_date . " " . $event->start_time);
            $dt_start_old = Carbon::parse($event->start_date_time);

            if($dt_start_old->isFuture()) {
                $start_date_formate = date("Y-m-d", strtotime(Request::get('start_date')));
                $start_time_formate = date("H:i:s", strtotime(Request::get('start_time')));
                $dt_start = Carbon::parse($start_date_formate . " " . $start_time_formate);
                //$dt_start = Carbon::parse(strtotime(Request::get('start_date_time')));

                if ($dt_start->isFuture()) {
                    $end_date_formate = date("Y-m-d", strtotime(Request::get('end_date')));
                    $end_time_formate = date("H:i:s", strtotime(Request::get('end_time')));
                    $dt_end = Carbon::parse($end_date_formate . " " . $end_time_formate);
                    //$dt_end = Carbon::parse(strtotime(Request::get('end_date_time')));

                    $check_diff = $dt_start->diffInMinutes($dt_end, false);
                    if ($check_diff > 0) {
                        $event = Event::find(Request::get('eid'));
                        if ($event->user_id == Auth::user()->id) {
                            $event->fill(Request::all());
                            $event->start_date_time = $dt_start;
                            $event->end_date_time = $dt_end;
                            $event->property_id = Auth::user()->property_id;
                            $event->user_id = Auth::user()->id;
                            $event->save();

                            //$this->addCreateEventNotification($event);
                            return response()->json(['eid' => $event->id]);
                        } else {
                            return response()->json(['success' => 'false', 'msg' => 'Not owner of Events']);
                        }
                    } else {
                        return response()->json(['success' => 'false', 'msg' => 'StartDateTime more than EndDateTime']);
                    }
                } else {
                    return response()->json(['success' => 'false', 'msg' => 'StartDate is past']);
                }
            }else{
                return response()->json(['success' => 'false', 'msg' => 'Events already start']);
            }

        } catch(Exception $ex){
            return response()->json(['success' =>'false', 'msg' => $ex->getMessage()]);
        }
    }

    public function addImageEvents () {
        try {
            // Get Post
            $event = Event::find(Request::get('eid'));

            $eventimg = [];

            /* New Function */
            if(count(Request::file('attachment'))) {
                foreach (Request::file('attachment') as $img) {
                    $name =  md5($img->getFilename());//getClientOriginalName();
                    $extension = $img->getClientOriginalExtension();
                    $targetName = $name.".".$extension;

                    $path = $this->createLoadBalanceDir($img);
                    $eventimg[] = new EventFile([
                        'name' => $targetName,
                        'url' => $path,
                        'file_type' => $img->getClientMimeType(),
                        'is_image'	=> true,
                        'original_name'	=> $img->getClientOriginalName()
                    ]);
                }
                $event->eventFile()->saveMany($eventimg);
            }

            return response()->json(['success' =>'true']);

        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function view ($eid) {
        try {
            $event = Event::with(['creator', 'eventFile', 'confirmation' => function ($query) {
                $query->where('user_id', '=', Auth::user()->id);
            }])
                ->find($eid);

            $count_go = EventConfirmation::where('event_id', $eid)->where('confirm_status', 1)->count();
            $count_maybe = EventConfirmation::where('event_id', $eid)->where('confirm_status', 2)->count();
            $count_not_go = EventConfirmation::where('event_id', $eid)->where('confirm_status', 3)->count();
            $counter = [
                'count_go' =>$count_go,
                'count_maybe' => $count_maybe,
                'count_not_go' => $count_not_go
            ];

            $eventArr = $event->toArray();

            // Add Key StartDate StartTime and EndDate EndTime
            $events_start_array = explode(" ", $eventArr['start_date_time']);
            $events_end_array = explode(" ", $eventArr['end_date_time']);
            $eventArr['start_date'] = $events_start_array[0];
            $eventArr['start_time'] = $events_start_array[1];
            $eventArr['end_date'] = $events_end_array[0];
            $eventArr['end_time'] = $events_end_array[1];

            // Change File type in Array
            foreach($eventArr['event_file'] as &$value)
            {
                $splitType = explode(".",$value['name']);
                $value['file_type'] = end($splitType);
            }

            $results = [
                'event' => $eventArr,
                'count' => $counter
            ];

            return response()->json($results);
        }catch (Exception $e){
            return response()->json(['status' => false]);
        }
    }

    public function actionEvent () {
        try {
            $confirm = EventConfirmation::firstOrCreate(['user_id' => Auth::user()->id, 'event_id' => Request::get('eid')]);
            $confirm->confirm_status = Request::get('action');
            $confirm->save();
            return response()->json(['status' => true]);
        }catch (Exception $e){
            return response()->json(['status' => false]);
        }
    }

    public function delete () {
        try {
            $eid = Request::get('eid');

            $event = Event::with('creator', 'eventFile')->find($eid);
            if ($event && $event->creator->id == Auth::user()->id) {
                $event->confirmationAll()->delete();
                if (!$event->eventFile->isEmpty()) {
                    foreach ($event->eventFile as $file) {
                        $this->removeFile($file->name);
                    }
                    $event->eventFile()->delete();
                }
                $event->delete();
            }
            return response()->json(['status' => true]);
        }catch (Exception $e){
            return response()->json(['status' => false]);
        }
    }

    public function deleteFile () {
        try {
            $efid = Request::get('efid');// Event file ID
            $eid = Request::get('eid');// Event ID

            $event = Event::with('creator', 'eventFile')->find($eid);
            if ($event && $event->creator->id == Auth::user()->id) {

                $eventFile = EventFile::find($efid);
                if ($eventFile) {
                    $this->removeFile($eventFile->name);
                    $eventFile->delete();
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

    public function createLoadBalanceDir ($imageFile) {
        $name =  md5($imageFile->getFilename());//getClientOriginalName();
        $extension = $imageFile->getClientOriginalExtension();
        $targetName = $name.".".$extension;

        $folder = substr($name, 0,2);

        $pic_folder = 'event-file'.DIRECTORY_SEPARATOR.$folder;
        $directories = Storage::disk('s3')->directories('event-file'); // Directory in Amazon
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
        $file_path = 'event-file'.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
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
                $controller_push_noti = new PushNotificationController();
                $controller_push_noti->pushNotification($notification->id);
            }

        }
    }
}
