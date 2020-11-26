<?php namespace App\Http\Controllers\User;
use Request;
use Illuminate\Routing\Controller;
use Auth;
# Model
use DB;
use App\Notification;
use App\Property;
use App\PropertyUnit;
class NotificationController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu', 'noti');
	}
	public function index()
	{
		$property = Property::where('id', Auth::user()->property_id)->first();
		if(Request::ajax()) {

			if (Request::get('type') == "3") {
				$notis = Notification::with('sender')
					->where('to_user_id', '=', Auth::user()->id)
					->whereIn('notification_type',['3','12'])
					->orderBy('notification.created_at', 'desc')
					->paginate(15);
			}else{
				$notis = Notification::with('sender')
					->where('to_user_id', '=', Auth::user()->id)
					->where('notification_type', '=', Request::get('type'))
					->orderBy('notification.created_at', 'desc')
					->paginate(15);
			}
	    	return view('notification.notification-list',compact('notis','property'));
		} else {
			$all_noti = Notification::where('to_user_id','=',Auth::user()->id)->where('read_status','=',false)->select('notification_type')->get();
			$c_ = ['comment' 	=> 0,
					'like'		=> 0,
					'complain'	=> 0,
					'feesbills'	=> 0,
					'message'	=> 0,
					'event'		=> 0,
					'vote'		=> 0,
					'postparcel'=> 0,
					'other'		=> 0,
					'announcement'		=> 0
			];
			foreach ($all_noti as $noti ) {
				if( $noti->notification_type == 0 ) $c_['comment']++;
				elseif( $noti->notification_type == 1 ) $c_['like']++;
				elseif( $noti->notification_type == 2 ) $c_['complain']++;
				elseif( $noti->notification_type == 3 ) $c_['feesbills']++;
				elseif( $noti->notification_type == 4 ) $c_['event']++;
				elseif( $noti->notification_type == 5 ) $c_['vote']++;
				elseif( $noti->notification_type == 6 ) $c_['postparcel']++;
				elseif( $noti->notification_type == 7 ) $c_['other']++;
				elseif( $noti->notification_type == 8 ) $c_['message']++;
				elseif( $noti->notification_type == 11 ) $c_['announcement']++;
				else ;
			}

			if(Auth::user()->role == 2) {
				$ntype = 2;
			}else {
				$ntype = 1;
			}
			$notis = Notification::with('sender')
						->where('to_user_id','=',Auth::user()->id)
						->where('notification_type',$ntype)
						->orderBy('notification.created_at', 'desc')
						->paginate(15);
			if(Auth::user()->role == 1 || Auth::user()->role == 3) {
				$unit_list = array();
				$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
				return view('notification.admin-index',compact('notis','c_','unit_list','property'));
			}
			else
	    		return view('notification.index',compact('notis','c_','property'));
		}
	}

	public function get()
	{
		if(Request::ajax()) {
			$notis = Notification::with('sender')->find(Request::get('nid'));
			//dd($notis);
			echo view('notification.view')->with(compact('notis'))->render();
		}
	}

	public function markAsRead () {
		if(Request::ajax()) {
			$notis = Notification::find(Request::get('nid'));
			$notis->read_status = true;
			$notis->save();
			return response()->json(['status'=>true]);
		}
	}

	public function NotiPage($value='')
	{
		$notis_head = Notification::where('to_user_id',Auth::user()->id)->orderBy('created_at','desc')->paginate(15);
		$property = Property::where('id', Auth::user()->property_id)->first();
		return view('layout.notification-element',compact('notis_head','property'));
	}

	public function NotiLast()
	{
		$notis_head = Notification::where('to_user_id',Auth::user()->id)->orderBy('created_at','desc')->first();
		$property = Property::where('id', Auth::user()->property_id)->first();
		return view('layout.notification-element',compact('notis_head','property'));
	}
}
