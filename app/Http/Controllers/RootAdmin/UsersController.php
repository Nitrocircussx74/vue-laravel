<?php namespace App\Http\Controllers\RootAdmin;;

use App\Http\Controllers\Officer\AccountController;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use Redirect;
use Mail;
use Hash;
//use Session;
# Model
use App\User;
use App\Property;
use App\Province;
class UsersController extends Controller {

	protected $app;

	public function __construct () {
		$this->middleware('auth',['except' => ['login']]);
		if( Auth::check() && Auth::user()->role !== 0 ) {
            if(Auth::user()->role !== 5) {
                Redirect::to('feed')->send();
            }
		}
	}

	public function newUsers() {
		$p = new Province;
		$provinces = $p->getProvince();

		if(Request::ajax()) {
			$users = User::whereNotNull('verification_code')->where('verification_stage',0);
			if(Request::get('province')) {
				if(Request::get('property_id')) {
					$users->where('property_id',Request::get('property_id'));
				} else {
					$p = Request::get('province');
					$users->whereHas('property',function ($q) use ($p) {
						$q->where('province', $p);
					});
				}
			}

			if(Request::get('name')) {
				$users->where('name','like',"%".Request::get('name')."%");
			}

			$users = $users->orderBy('created_at','desc')->paginate(400);
			return view('users.new-user-list')->with(compact('users','provinces'));
		} else {
			$users = User::with('property')->where('verification_code','!=',"")->where('verification_stage',0)->orderBy('created_at','desc')->paginate(400);
			$property_list = array(''=> trans('messages.Signup.select_property') );
			return view('users.new-list')->with(compact('users','property_list','provinces'));
		}
	}


	public function allUsers() {
		$p = new Province;
		$provinces = $p->getProvince();

		if(Request::ajax()) {
			$users = User::with('property')->whereHas('property', function($query){
                $query->where('is_demo', false);
            });
			if(Request::get('province')) {
				if(Request::get('property_id')) {
					$users = $users->where('property_id',Request::get('property_id'));
				} else {
					$p = Request::get('province');
					$users = $users->whereHas('property',function ($q) use ($p) {
						$q->where('province', $p);
					});
				}
			}

			if(Request::get('role') != 0) {
				$users = $users->where('role',Request::get('role'));
			} else {
				$users = $users->whereIn('role',[1,2]);
			}

			if(Request::get('name')) {
				$users = $users->where('name','like',"%".Request::get('name')."%");
			}

			$users = $users->orderBy('created_at','desc')->paginate(40);
			return view('users.all-users-list-element')->with(compact('users','provinces'));
		} else {
			$users = User::with('property')->whereHas('property', function($query){
                $query->where('is_demo', false);
            })->whereIn('role',[1,2])->orderBy('created_at','desc')->paginate(400);
			
			$property_list = array(''=> trans('messages.Signup.select_property') );
			
			return view('users.all-users-list')->with(compact('users','property_list','provinces'));
		}
	}


	public function export(Request $request) {
		$p = new Province;
		$provinces = $p->getProvince();
		$users = $this->filterUser($request);
		if($users->count()) {
			return view('users.export-list')->with(compact('users','provinces','request'));
		} else {
			return redirect()->back();
		}
	}

	function propertylist () {
		$lang = session()->get('lang');
		if (Request::isMethod('post')) {
			$pid = Request::get('pid');
			$props = Property::where('province','=',$pid)->get();
			echo "<option value='0'>".trans('messages.Signup.select_property')."</option>";
			if(!empty($props)) {
				foreach ($props as $prop) {
					echo "<option value='".$prop->id."'>".$prop->{'property_name_'.$lang}."</option>";
				}
			}
		}
	}

	function filterUser ($r) {
		$users = User::where('verification_code','!=',"")->where('verification_stage',0)->with('property_unit','property');
		if($r::get('province')){
			if($r::get('property_id')) {
				$users->where('property_id',$r::get('property_id'));
			} else {
				$p = $r::get('province');
				$users->whereHas('property',function ($q) use ($p) {
					$q->where('province', $p);
				});
			}
		}

		if($r::get('name')) {
			$users->where('name','like',"%".$r::get('name')."%");
		}

		return $users->orderBy('created_at','desc')->get();
	}

	public function markUserCodeAsSent (Request $request) {
		$users = $this->filterUser($request);
		if($users->count()) {
			foreach ($users as $user) {
				// May be mailing included
				$user->verification_stage = 1;
				$user->save();
			}
		}
		return redirect('root/admin/users/new');
	}

	public function delete ($id) {
		$user = User::where('id',$id)->first();
		if(isset($user)) {
			$user->delete();
		}
		return redirect('root/admin/users/new');
	}

	/*public function sendEmail () {
		if (Request::isMethod('post')) {
			$user = User::find(Request::get('id'));
			if($user) {
				Mail::send('emails.admin_user_email', [
		        		'name'		=> $user->name,
						'messages'	=> Request::get('message'),
		        	], function ($message) use ($user) {
					$message->subject(Request::get('subject'));
				    $message->from('noreply@nabour.me', 'Nabour');
				    $message->to( $user->email );
				});
				return response()->json(['r'=>true]);
			}
			return response()->json(['r'=>false]);
		}
	}*/

	public function internalUserEmail ($id) {
		if (Request::isMethod('get')) {
			@session_start();
			$user = User::find($id);
			if($user) {
				$_SESSION['allow_upload_kc'] = false;
				return view('users.email-internal-user')->with(compact('user'));
			}
		} else {
			$user = User::find(Request::get('uid'));
			if( $this->sendMailToUser($user->name,$user->email,Request::get('subject'),Request::get('message')) ) {
				$_SESSION['allow_upload_kc'] = true;
				return redirect('root/admin/users/all');
			} else {
				return redirect()->back()->withInput();
			}
		}
	}

	public function externalUserEmail () {
		if (Request::isMethod('get')) {
			@session_start();
			$_SESSION['allow_upload_kc'] = false;
			return view('users.email-external-user')->with(compact('user'));
		} else {
			if( $this->sendMailToUser(Request::get('name'),Request::get('email'),Request::get('subject'),Request::get('message')) ) {
				$_SESSION['allow_upload_kc'] = true;
				return redirect('root/admin/users/all');
			} else {
				return redirect()->back()->withInput();
			}
		}
	}

	public function sendMailToUser ($name,$email,$subject,$message) {
		Mail::send('emails.admin_user_email', [
				'name'		=> $name,
				'messages'	=> $message,
			], function ($message) use ($email,$subject) {
			$message->subject($subject);
			$message->from('no-reply@nabour.me', 'Nabour');
			$message->to( $email );
		});

		if( count(Mail::failures()) > 0 ) {
			return false;
		} else {
			return true;
		}
	}

    public function sendResetPasswordAndMailToUser () {
        if (Request::isMethod('post')) {
            $subject = "รหัสผ่านใหม่ / New Password";
            $id = Request::get('id');
            $account = new AccountController();
            $new_password = $account->generatePassword();

            $user = User::find($id);
            $user->password = Hash::make($new_password);
            $user->save();

            $email = $user->email;

            Mail::send('emails.reset_password_by_admin', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $new_password
            ], function ($message) use ($email, $subject) {
                $message->subject($subject);
                $message->from('no-reply@nabour.me', 'Nabour');
                $message->to($email);
            });

            if (count(Mail::failures()) > 0) {
                $data = [
                    'msg' => 'fail'
                ];
            } else {
                $data = [
                    'msg' => 'success'
                ];
            }
            return $data;
        }else{
            $data = [
                'msg' => 'fail'
            ];
            return $data;
        }
    }
}
