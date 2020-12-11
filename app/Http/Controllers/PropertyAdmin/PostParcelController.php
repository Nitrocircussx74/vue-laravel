<?php namespace App\Http\Controllers\PropertyAdmin;
use Auth;
use Request;
use Illuminate\Routing\Controller;
use App\Http\Controllers\DirectPushNotificationController;
# Model
use DB;
use App\PropertyUnit;
use App\PostParcel;
use App\Property;
use App\Notification;
use App\User;
use App\PropertyMember;
use App\Tenant;


class PostParcelController extends Controller {
 
	public function __construct () {
		$this->middleware('auth:menu_parcel');
		view()->share('active_menu', 'post-parcel');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function postParcellist()
	{
		if(Request::isMethod('post')) {
			$post_parcels = PostParcel::where('property_id',Auth::user()->property_id);

			if(!empty(Request::get('property_unit_id')) && Request::get('property_unit_id') != "-") {
				$post_parcels->where('property_unit_id',Request::get('property_unit_id'));
			}

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

            if(Request::get('ems_code')){
                $post_parcels->Where('ems_code', 'LIKE', '%'.Request::get('ems_code').'%');
            }

			$post_parcels = $post_parcels->orderBy('receive_code','desc')->paginate(30);
			return view('post_parcels.list-element')->with(compact('post_parcels'));
		} else {
			$unit_list = array(''=> trans('messages.unit_no'));
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
			$post_parcels = PostParcel::where('property_id',Auth::user()->property_id)->orderBy('receive_code','desc')->paginate(30);
			return view('post_parcels.list')->with(compact('post_parcels','unit_list'));
		}
	}

	public function add () {
		if(Request::isMethod('post')) {
			// dd(Request::all());
			$post_parcel = new PostParcel;
			$property = Property::find(Auth::user()->property_id);
			$post_parcel->fill(Request::all());
			$post_parcel->receive_code 	= $property->post_parcel_counter+1;
			$post_parcel->property_id 	= Auth::user()->property_id;
			if( Request::get('to_resident') != 'other') {
				$d = explode('|', Request::get('to_resident'));
				if($d[1] != ''){
					if( $d[1] == 'c') {
						$member = PropertyMember::find($d[0]);
						
					} 
					else if($d[1]=='t'){
						$member = Tenant::find($d[0]);
						
					}
					$post_parcel->to_name = $member->name;
				}
			} 
			
			$post_parcel->ref_code = $this->generateRefCode();
			$post_parcel->save();
			$property->increment('post_parcel_counter');
			$this->sendCreateNotification ($post_parcel->id,Request::get('property_unit_id'),$post_parcel->ems_code, receivedNumber($post_parcel->receive_code));
		}
		return redirect('admin/post-and-parcel');
	}

	public function delete ($id) {
		$post_parcel = PostParcel::find($id);
		$this->clearNotification($id);
		$post_parcel->delete();
		return redirect('admin/post-and-parcel');
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
	public function viewPostParcel () {
		$post_parcel = PostParcel::find(Request::get('id'));
		return view('post_parcels.details')->with(compact('post_parcel'));
	}

	public function delivered () {
		if(Request::isMethod('post')) {
			$post_parcel = PostParcel::find(Request::get('id'));
			$post_parcel->status = true;
			$post_parcel->receiver_name = Request::get('receiver_name');
			$post_parcel->delivered_date = date('Y-m-d');
			$post_parcel->save();
		}
		return redirect('admin/post-and-parcel');
	}

	public function sendCreateNotification ($subject_key,$unit_id,$ems_code,$receive_code) {
			$title = json_encode( ['type' => 'receive_post_parcel','ems_code'=>$ems_code,'receive_code'=>$receive_code] );
			$users = User::where('property_unit_id',$unit_id)->whereNull('verification_code')->get();
			foreach ($users as $user) {
				$notification = Notification::create([
					'title'				=> $title,
					'notification_type' => '6',
					'subject_key'		=> $subject_key,
					'from_user_id'		=> Auth::user()->id,
					'to_user_id'		=> $user->id,
				]);
				$controller_push_noti = new DirectPushNotificationController();
				$controller_push_noti->pushNotification($notification->id);
			}
	}

    public function printNewList()
    {
        if(Request::isMethod('post')) {
            $post_parcels = PostParcel::where('property_id',Auth::user()->property_id)->where('status',false);
            $date_r = Request::get('date_received');
            if(Request::get('date_received')) {
                $post_parcels = $post_parcels->where('date_received',Request::get('date_received'));
            }

            $post_parcels = $post_parcels->orderBy('date_received','desc')->orderBy('receive_code','asc')->get();
            return view('post_parcels.print-new-post-parcel-list')->with(compact('post_parcels','date_r'));
        }
    }

    public function printLabel()
    {
        if(Request::isMethod('post')) {
            $post_parcels = PostParcel::where('property_id',Auth::user()->property_id)->where('status',false);
            $date_r = Request::get('date_received');
            if(Request::get('date_received')) {
                $post_parcels = $post_parcels->where('date_received',Request::get('date_received'));
            }

            $post_parcels = $post_parcels->orderBy('date_received','desc')->orderBy('receive_code','asc')->get();
            $date_r = date('Y-m-d', strtotime($date_r));
            return view('post_parcels.print-label-post-parcel-list')->with(compact('post_parcels','date_r'));
        }
    }

    public function printNotReceived () {
//        if(Request::isMethod('post')) {
            $post_parcels = PostParcel::where('property_id',Auth::user()->property_id)->where('status',false);
            $post_parcels = $post_parcels->orderBy('date_received','desc')->orderBy('receive_code','asc')->get();
            return view('post_parcels.print-not-received-post-parcel-list')->with(compact('post_parcels'));
        //}
	}
	
	// -----------
	public function getResident() {
	    if( Request::isMethod('post') ) {
			$resident_list = [];
			$customer_list = PropertyMember::where('property_unit_id', Request::get('uid'))->get();
	        if( !empty($customer_list) ) {
                foreach ($customer_list as $c) {
					$resident_list[$c->id.'|c'] = $c->name;	
                }
            }

            $tenant_list = Tenant::where('property_unit_id', Request::get('uid'))->get();
            if( !empty($tenant_list) ) {
                foreach ( $tenant_list as $t ) {
                    $resident_list[$t->id.'|t'] =  $t->name;
                }
            }
            if( !empty($resident_list) ) {
                $result_data = true;
            } else {
                $result_data = 'EMPTY_DATA';
            }
            return response()->json([
                'result'       => $result_data,
                'data'          => $resident_list
            ]);
	    }
	}
	// --------------------
	function generateRefCode() {
        $code = $this->randomRefCodeCharacter();
        $count = PostParcel::where('ref_code', '=', $code)->count();
        while($count > 0) {
            $code = $this->randomRefCodeCharacter();
            $count = PostParcel::where('ref_code', '=', $code)->count();
        }
        return $code;
	}
	function randomRefCodeCharacter(){
        $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ123456789";
        srand((double)microtime()*1000000);
        $i = 0;
        $pass = '' ;
        while ($i < 5) {
            $num = rand() % 33;
            $tmp = substr($chars, $num, 1);
            $pass = $pass . $tmp;
            $i++;
        }
        return $pass;
    }
}
