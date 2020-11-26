<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use File;
use Redirect;
use Storage;
use DB;
use App;
use View;
use Excel;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
# Model
use App\Complain;
use App\ComplainCategory;
use App\ComplainFile;
use App\Notification;
use App\ComplainComment;
use App\User;
use App\PropertyUnit;
use App\Property;
use App\ComplainAction;

class ComplainController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_complain');
		view()->share('active_menu', 'complain');
		if(Auth::check() && (Auth::user()->role  == 2 && !Auth::user()->is_chief)) Redirect::to('feed')->send();
	}

	public function complain () {
		$complains = Complain::with('owner','property_unit','category')->where('property_id','=',Auth::user()->property_id)->where('is_juristic_complain',false);
		if(Request::ajax()) {
			if(Request::get('type') != "-") {
				$complains->where('complain_status','=',Request::get('type'));
			}
			$complains = $complains->orderBy('created_at','DESC')->paginate(30);
			return view('complain.admin-complain-list')->with(compact('complains'));
		} else {
			$c_cate = ComplainCategory::all()->sortBy('id');
			$unit_list = array('-'=> trans('messages.unit_no'));
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
			$complains = $complains->orderBy('created_at','DESC')->paginate(30);
			$count_new = Complain::where('property_id','=',Auth::user()->property_id)->where('complain_status','=',0)->where('is_juristic_complain',false)->count();
			return view('complain.admin-index')->with(compact('complains','count_new','c_cate','unit_list'));
		}
	}

	public function juristicComplain () {
		$complains = Complain::with('owner','category')->where('property_id','=',Auth::user()->property_id)->where('is_juristic_complain',true);
		if(Request::ajax()) {
			if(Request::get('type') != "-") {
				$complains->where('complain_status','=',Request::get('type'));
			}
			$complains = $complains->orderBy('created_at','DESC')->paginate(30);
			return view('complain.admin-juristic-complain-list')->with(compact('complains'));
		} else {
			$c_cate = ComplainCategory::all()->sortBy('id');
			$complains = $complains->orderBy('created_at','DESC')->paginate(30);
			$count_new = Complain::where('property_id','=',Auth::user()->property_id)->where('complain_status','=',0)->where('is_juristic_complain',true)->count();
			return view('complain.admin-juristic-index')->with(compact('complains','count_new','c_cate'));
		}
	}

	public function addForUser () {
		if(Request::isMethod('post')) {
			$complain = new Complain;
			$complain->fill(Request::all());
			$complain->user_id 			= Auth::user()->id;
			$complain->property_id 		= Auth::user()->property_id;
			$complain->complain_status 		= 0;
			$complain->created_by_admin 	= true;
			$complain->save();
			$this->addCreateComplainNotification($complain);
			$this->saveAttachment ($complain);
			return redirect('admin/complain');
		}
	}

	public function addForJuristic () {
		if(Request::isMethod('post')) {
			$complain = new Complain;
			$complain->fill(Request::all());
			$complain->user_id 			= Auth::user()->id;
			$complain->property_id 		= Auth::user()->property_id;
			$complain->complain_status 	= 0;
			$complain->is_juristic_complain 	= true;
			$complain->save();
			$this->saveAttachment ($complain);
			return redirect('admin/complain/juristic');
		}
	}

	public function saveAttachment ($complain) {
		if(!empty(Request::get('attachment'))) {
			$attach = [];
			foreach (Request::get('attachment') as $key => $file) {
				//Move Image
				$path = $this->createLoadBalanceDir($file['name']);
				$attach[] = new ComplainFile([
						'name' => $file['name'],
						'url' => $path,
						'file_type' => $file['mime'],
						'is_image'	=> $file['isImage'],
						'original_name'	=> $file['originalName']
				]);
			}
			$complain->attachment_count = ++$key;
			$complain->save();
			$complain->complainFile()->saveMany($attach);
		}
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'complain-file/'.$folder;
        $directories = Storage::disk('s3')->directories('complain-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function changeStatus () {
		if(Request::isMethod('post')) {
			$cid = Request::get('cid');
			$complain = Complain::find($cid);
			if($complain->count()) {
				$status = Request::get('status');
				// save complain action time stamp
				$ca = new ComplainAction;
				$ca->saveAction($complain,$status);
				
				$complain->complain_status = $status;
				$complain->save();
				// Save comments
				if(Request::get('comment')) {
					$comment = new ComplainComment([
						'description' 	=> Request::get('comment'),
						'user_id'		=> Auth::user()->id
					]);
					$complain->comments()->save($comment);
				}
				//Add Notification
				if($complain->user_id != Auth::user()->id && !$complain->is_juristic_complain && !$complain->created_by_admin ) {
					// prevent admin sent motification to ownself
					$this->addComplainNotification ($complain);
				} 
				
				if( $complain->created_by_admin && !$complain->is_juristic_complain ) {
					$this->statusComplainNotification ($complain);
				}
			}
			if($complain->is_juristic_complain)
				return redirect('admin/complain/juristic/view/'.Request::get('cid'));
			else
				return redirect('admin/complain/view/'.Request::get('cid'));
		}
		return redirect('admin/complain');
	}

	public function view ($id) {
		$complain = Complain::with('owner','property_unit','category','comments','comments.owner')->find($id);
		$is_disable_checking_complain = Auth::user()->property->is_disable_checking_complain;
		if(!$complain->is_juristic_complain) {
			return view('complain.admin-view',compact('complain','is_disable_checking_complain'));
		} else {
			return redirect('admin/complain/juristic');
		}

	}

    public function updateComplainDetail () {
        if(Request::isMethod('post')) {
            $cid = Request::get('cid');
            $complain = Complain::find($cid);
            if($complain->count()) {
                $is_appointment = Request::get('is_appointment') == "true" ? true : false;
                $is_deposit_key = Request::get('is_deposit_key') == "true" ? true : false;
                $is_juristic_complain = Request::get('is_juristic_complain') == "true" ? true : false;

                $complain->is_juristic_complain = $is_juristic_complain;
                $complain->is_appointment = $is_appointment;
                $complain->is_deposit_key = $is_deposit_key;
                $complain->technician_name = Request::get('technician_name');

                if(Request::get('appointment_date') != null) {
                    $appointment_datetime = date('Y-m-d',strtotime(Request::get('appointment_date')))." ".date('H:i:s',strtotime(Request::get('appointment_time')));
                    $complain->appointment_date = $appointment_datetime;
                }

                $complain->save();

                //Add Notification
                if($complain->user_id != Auth::user()->id && !$complain->is_juristic_complain && !$complain->created_by_admin ) {
                    // prevent admin sent motification to ownself
                    //$this->addComplainNotification ($complain);
                }

                if($complain->is_juristic_complain != true){
                    return redirect('admin/complain/view/'.Request::get('cid'));
                }else{
                    return redirect('admin/complain/juristic/view/'.Request::get('cid'));
                }
            }
        }
        return redirect('admin/complain/view/'.Request::get('cid'));
    }

	public function JcView ($id) {
		$complain = Complain::with('owner','category')->find($id);
		if($complain->is_juristic_complain) {
			return view('complain.admin-jc-view',compact('complain'));
		} else {
			return redirect('admin/complain');
		}
	}

	public function addComplainNotification($complain) {
		$status = ['status_rj','status_ip','status_ck','status_cf','status_cls'];
		$title = json_encode( ['type'=>'change_status','c_title'=>$complain->title,'status' => $status[$complain->complain_status]] );
		$notification = Notification::create([
			'title'				=> $title,
			'description' 		=> "",
			'notification_type' => 2,
			'subject_key'		=> $complain->id,
			'to_user_id'		=> $complain->user_id,
			'from_user_id'		=> Auth::user()->id
		]);
		$controller_push_noti = new PushNotificationController();
		$controller_push_noti->pushNotification($notification->id);
	}

	public function addCreateComplainNotification($complain) {
		$title = json_encode( ['type'=>'complain_created_by_juristic','c_title'=>$complain->title] );
		$users = User::where('property_unit_id',$complain->property_unit_id)->whereNull('verification_code')->where('active', true)->get();
		foreach ($users as $user) {
			$notification = Notification::create([
				'title'				=> $title,
				'notification_type' => 2,
				'subject_key'		=> $complain->id,
				'from_user_id'		=> Auth::user()->id,
				'to_user_id'		=> $user->id,
			]);
			$controller_push_noti = new PushNotificationController();
			$controller_push_noti->pushNotification($notification->id);
		}
	}

	public function statusComplainNotification($complain) {
		$status = ['status_rj','status_ip','status_ck','status_cf','status_cls'];
		$title = json_encode( ['type'=>'change_status','c_title'=>$complain->title,'status' => $status[$complain->complain_status]] );
		$users = User::where('property_unit_id',$complain->property_unit_id)->whereNull('verification_code')->where('active', true)->get();
		foreach ($users as $user) {
			$notification = Notification::create([
				'title'				=> $title,
				'description' 		=> "",
				'notification_type' => 2,
				'subject_key'		=> $complain->id,
				'to_user_id'		=> $user->id,
				'from_user_id'		=> Auth::user()->id
			]);
			$controller_push_noti = new PushNotificationController();
			$controller_push_noti->pushNotification($notification->id);
		}
	}

	public function delete ($id) {
		$complain = Vote::with('complainFile')->find($id);
		if($complain->user_id == Auth::user()->id) {
			$complain->comments()->delete();
			if(!$complain->complainFile->isEmpty()) {
				foreach ($complain->complainFile as $file) {
					$this->removeFile($file->name);
				}
				$complain->complainFile()->delete();
			}
			$complain->delete();
		}
		return redirect('admin/complain');
	}

	public function removeFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'complain-file'.'/'.$folder.'/'.$name;
        Storage::disk('s3')->delete($file_path);
	}

	public function complainReport () {
		return view('complain.admin-complain-report');
	}

	public function complainReportMonth () {
		$date 	 	= Request::get('year')."-".Request::get('month');
		$l_date  	= date('Y-m-t 23:59:59',strtotime($date));
		$date_bw 	= array($date."-01 00:00:00", $l_date);
		$reports 	= Complain::whereBetween('created_at',$date_bw)->where('property_id',Auth::user()->property_id)->orderBy('complain_category_id','asc')->get();
		$result 	= [];
		if($reports->count()) {
			$result['result'] 		= true;
			$head 	= trans('messages.dateMonth.'.Request::get('month')) ." ". localYear(Request::get('year'));
			$result['head'] 		= trans("messages.Complain.report_monthly_title").$head;
			$result['head_pie'] 	= trans("messages.Complain.report_monthly_by_cate_title").$head;
			$result['head_status'] 	= trans("messages.Complain.report_monthly_by_status_title").$head;
			$result_data 			= $this->returnReport( $reports );
			$result['graph']  		= $result_data['graph_data'];
			$result['status']  		= $result_data['status_data'];
			$result['timeline'] 	= $this->exportReport();
			$result['timeline_head'] = trans('messages.Complain.m_timeline_report_head').$head;
		} else {
			$result['result'] = false;
		}
		return response()->json( $result );
	}

	public function complainReportYear () {
		$date 	 	= Request::get('year')."-01-01 00:00:00";
		$l_date  	= Request::get('year')."-12-31 23:59:59";
		$date_bw 	= array($date, $l_date);
		$reports 	= Complain::whereBetween('created_at',$date_bw)->where('property_id',Auth::user()->property_id)->orderBy('complain_category_id','asc')->get();
		$result 	= [];
		if($reports->count()) {
			$result['result'] 		= true;
			$head 	= localYear(Request::get('year'));
			$result['head'] 		= trans("messages.Complain.report_yearly_title"). $head;
			$result['head_pie'] 	= trans("messages.Complain.report_yearly_by_cate_title"). $head;
			$result['head_status'] 	= trans("messages.Complain.report_yearly_by_status_title"). $head;
			$result_data 			= $this->returnReport( $reports );
			$result['graph']  		= $result_data['graph_data'];
			$result['status']  		= $result_data['status_data'];
			$result['timeline'] 	= $this->exportReport();
			$result['timeline_head'] = trans('messages.Complain.y_timeline_report_head')." ".$head;
		} else {
			$result['result'] = false;
		}
		return response()->json( $result );
	}

	public function returnReport ($reports) {
		$c_cate = ComplainCategory::all();
		$cate = [];
		foreach($c_cate as $c) {
			$cate[$c->id] = $c['name_'.App::getLocale()];
		}
		$result = $status_result = [];

		$c_status = [
			0 => trans('messages.Complain.status_wt'),
			1 => trans('messages.Complain.status_ip'),
			2 => trans('messages.Complain.status_ck'),
			3 => trans('messages.Complain.status_cf'),
			4 => trans('messages.Complain.status_cls')
		];

		foreach ($reports as $report) {
			if(empty($result[$cate[$report->complain_category_id]][$report->complain_status])) {
				$result[$cate[$report->complain_category_id]][$report->complain_status] = 0;
			}
			$result[$cate[$report->complain_category_id]][$report->complain_status]++;

			if( empty($status_result[$c_status[$report->complain_status]]['value']) ) {
				$status_result[$c_status[$report->complain_status]]['value'] = 0;
			}
			$status_result[$c_status[$report->complain_status]]['value']++;
		}
		//Data for category graph
		$rs = [];
		foreach ($result as $key => $r) {
			$rs[] = ['cate' => $key] +  $r ;
		}

		//Data for status graph
		$rs_ = [];
		foreach ($status_result as $key => $r) {
			$rs_[] = ['status' => $key] +  $r ;
		}

		return(array('graph_data'=>$rs,'status_data'=>$rs_));
	}

	public function exportReport () {
		if(Request::get('month')) {
			$date 	 	= Request::get('year')."-".Request::get('month')."-01 00:00:00";
			$l_date  	= date('Y-m-t 23:59:59',strtotime($date));
		} else {
			$date 	 	= Request::get('year')."-01-01 00:00:00";
			$l_date  	= Request::get('year')."-12-31 23:59:59";
		}
		
		$date_bw 	= array($date, $l_date);
		$reports 	= Complain::with('complainAction','property_unit')->whereBetween('created_at',$date_bw)->where('property_id',Auth::user()->property_id)->orderBy('created_at','desc')->get();
		
		if(Request::ajax()) {
			$head = "";
			if(Request::get('month')) {
				$head .= trans('messages.dateMonth.'.Request::get('month')) ." ";
			}
			$head 	.= localYear(Request::get('year'));

			$month = Request::get('month');
			$year = Request::get('year');
			$view = View::make('complain.admin-complain-timeline-report', ['reports' => $reports, 'month' => $month, 'year' => $year,'head' => $head]);
			$contents = $view;
			return $view->render();
		} else {
			
			if(Request::get('month')) {
				$head = trans('messages.Complain.m_timeline_report_head');
				$head .= trans('messages.dateMonth.'.Request::get('month')) ." ";
			} else {
				$head = trans('messages.Complain.y_timeline_report_head');
			}
			$head 	.= localYear(Request::get('year'));

			$filename = "fixing_report";
			Excel::create($filename, function($excel) use ($reports,$filename,$head) {
			    $excel->sheet($filename, function($sheet) use ($reports,$head){
					$sheet->setWidth(array(
						'A'     =>  30,
						'B'     =>  30,
						'C'     =>  30,
						'D'     =>  30,
						'E'     =>  30,
						'F'     =>  30
					));
			        $sheet->loadView('complain.admin-complain-report-export')->with(compact('reports','head'));
			    })->export('xls');
			});
		}

	}
}
