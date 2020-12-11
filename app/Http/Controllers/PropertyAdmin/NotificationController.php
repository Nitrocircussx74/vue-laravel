<?php namespace App\Http\Controllers\PropertyAdmin;
use Auth;
use Request;
use Illuminate\Routing\Controller;
use App\Http\Controllers\PushNotificationController;
# Model
use App\Notification;
use App\User;

class NotificationController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu', 'noti');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}
	public function sendNotification () {
		if(Request::isMethod('post')) {
			$title = json_encode( ['type' => 'general','n_title' => Request::get('title')] );
			$units = Request::get('unit_id');
			foreach ($units as $unit) {
				$users = User::where('property_unit_id',$unit)->get();
				if($users->count()) {
					foreach ($users as $user) {
						$notification = Notification::create([
							'title'				=> $title,
							'description' 		=> Request::get('detail'),
							'notification_type' => '7',
							'from_user_id'		=> Auth::user()->id,
							'to_user_id'		=> $user->id
						]);
						$controller_push_noti = new PushNotificationController();
						$controller_push_noti->pushNotification($notification->id);
					}
				}

			}
		}
		return redirect('notification');
	}
}
