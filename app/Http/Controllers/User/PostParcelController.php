<?php namespace App\Http\Controllers\User;
use Auth;
use Request;
use Illuminate\Routing\Controller;
use App\Http\Controllers\PushNotificationController;
# Model
use App\PropertyUnit;
use App\PostParcel;
use App\Property;
use App\Notification;
use App\User;

class PostParcelController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_parcel');
		view()->share('active_menu', 'post-parcel');
	}
	
	public function postParcellist()
	{
		if(Request::isMethod('post')) {
			$post_parcels = PostParcel::where('property_unit_id',Auth::user()->property_unit_id);

			if(Request::get('type')) {
				$post_parcels = $post_parcels->where('type',Request::get('type'));
			}

			if(Request::get('date_received')) {
				$post_parcels = $post_parcels->where('date_received',Request::get('date_received'));
			}

			if(Request::get('receive_code')) {
				$post_parcels = $post_parcels->where('receive_code',intval(Request::get('receive_code')));
			}

			if(Request::get('status')) {
				if(Request::get('status') == 1)
					$post_parcels = $post_parcels->where('status',false);
				else $post_parcels = $post_parcels->where('status',true);
			}

			$post_parcels = $post_parcels->orderBy('date_received','desc')->paginate(30);
			return view('post_parcels.user-pp-list-element')->with(compact('post_parcels'));
		} else {
			$post_parcels = PostParcel::where('property_unit_id',Auth::user()->property_unit_id)->orderBy('date_received','desc')->paginate(30);
			return view('post_parcels.user-pp-list')->with(compact('post_parcels'));
		}
	}

	public function viewPostParcel () {
		$this->markAsRead(Request::get('id'));
		$post_parcel = PostParcel::find(Request::get('id'));
		return view('post_parcels.details')->with(compact('post_parcel'));
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
