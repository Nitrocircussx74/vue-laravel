<?php namespace App\Http\Controllers\User;
use Request;
use Storage;
use Illuminate\Routing\Controller;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App;
use Auth;
use File;
use DB;
use Vinkla\Pusher\Facades\Pusher;
use App\Http\Controllers\GeneralFeesBillsReportController;
# Model
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\Bank;
use App\Province;
use App\CommonFeesRef;
use App\Property;
use App\PropertyUnit;
use App\Notification;
use App\User;
use App\PropertyFund;
class FeesBillsController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu', 'bill');
        view()->share('active_sub', '');
	}

	public function invoiceList (Request $form) {

		$bills = Invoice::where('property_unit_id','=',Auth::user()->property_unit_id)->whereIn('payment_status',[0,1]);
		if($form::isMethod('post')) {
			if(!empty($form::get('invoice-type')) && $form::get('invoice-type') != '-') {
				$bills->where('type',$form::get('invoice-type'));
			}

			if(!empty($form::get('start-due-date'))) {
				$bills->where('due_date','>=',$form::get('start-due-date'));
			}

			if(!empty($form::get('end-due-date'))) {
				$bills->where('due_date','<=',$form::get('end-due-date'));
			}

			if( $form::get('invoice-status') != "") {
				$bills->where('payment_status',$form::get('invoice-status'));
			}

			if( $form::get('payment-method') == 1) {
				$bills->where('transfer_only', true);
			}
		} else {
			$bills->where('payment_status','!=',3);
		}

		$bills = $bills->orderBy('invoice_no','desc')->paginate(30);
		if(Request::ajax()) {
			return view('feesbills.user-invoice-list-element')->with(compact('bills'));
		} else return view('feesbills.user-invoice-list')->with(compact('bills','form'));
	}

	public function receiptList (Request $form) {
		$bills = Invoice::where('property_unit_id','=',Auth::user()->property_unit_id);
		if($form::isMethod('post')) {
			if(!empty($form::get('invoice-type')) && $form::get('invoice-type') != '-') {
				$bills->where('type',$form::get('invoice-type'));
			}

			if(!empty($form::get('start-due-date'))) {
				$bills->where('due_date','>=',$form::get('start-due-date'));
			}

			if(!empty($form::get('end-due-date'))) {
				$bills->where('due_date','<=',$form::get('end-due-date'));
			}

			if( $form::get('payment-method') ) {
				$bills->where('payment_type', $form::get('payment-method'));
			}
		}
		$bills = $bills->where('payment_status',2)->orderBy('receipt_no','desc')->paginate(30);
		if(Request::ajax()) {
			return view('feesbills.user-receipt-list-element')->with(compact('bills'));
		} else return view('feesbills.user-receipt-list')->with(compact('bills','form'));
	}

	public function viewbill ($id) {
		$this->markAsRead($id);
		$bill 	= Invoice::with( array('instalmentLog' => function($q) {
				    return $q->orderBy('created_at', 'ASC');
				},'property','property_unit','transaction','invoiceFile'))->find($id);
		if($bill->payment_status != 3 && $bill->payment_status != 4) {
			if($bill->invoice_read_status != true) {
				$bill->invoice_read_status  = 1;
				$bill->timestamps           = false;
				$bill->save();
			}
			$banks 	= Bank::where('property_id',Auth::user()->property_id)->where('is_fund_account',false)->where('active',true)->get();
	        if($bill->payment_status <= 1) view()->share('active_sub', 'invoice');
	        else view()->share('active_sub', 'receipt');

	        $is_overdue_invoice = false;
		    if(!$bill->submit_date) {
		        if( strtotime(date('Y-m-d')) > strtotime($bill->due_date) )
		        $is_overdue_invoice = true;
		    } else {
		        $day_submit = date('Y-m-d',strtotime($bill->submit_date));
		        if ( strtotime( $day_submit ) > strtotime( $bill->due_date ) )
		        $is_overdue_invoice = true;
		    }
		    $can_submit = true;
		    // if invoice is overdue invoice
		    if($is_overdue_invoice) {
		    	// if property type is condominium
		        if($bill->property->property_type == 3) {
		        	// if invoice is common fee invoice
		            if($bill->is_common_fee_bill) {
		                $cal_normal_bill_fine_flag = false;
		                $overdue_ms = calOverdueMonth($bill->due_date,null,false);
		                //if overdue month is over three months
		                if($overdue_ms > 3) {
		                    $can_submit = false;
		                }
		            } else {
		                $can_submit = false;
		            }

		        } else {
		            $can_submit = false;
		        }
		    }

			return view('feesbills.user-bill-view')->with(compact('bill','banks','can_submit'));
		} else {
			return redirect('fees-bills/invoice');
		}

	}

	public function payBill () {
		if(Request::isMethod('post')) {
			$bill = Invoice::with('transaction','property_unit')->find(Request::get('invoice_id'));
			$bill->payment_status 	= 1;
			$bill->payment_type 	= Request::get('payment_type');
			$bill->submit_date 		= date('Y-m-d h:i:s');
			$bill->payer_name 		= Auth::user()->name;
			$bill->save();
			foreach ($bill->transaction as $tr) {
				$tr->submit_date	= date('Y-m-d h:i:s');
				$tr->save();
			}
			if(!empty(Request::get('attachment'))) {
				$attach = [];
				foreach (Request::get('attachment') as $key => $file) {
					//Move Image
					$path = $this->createLoadBalanceDir($file['name']);
					$attach[] = new InvoiceFile([
							'name' => $file['name'],
							'url' => $path,
							'file_type' => $file['mime'],
							'is_image'	=> $file['isImage'],
							'original_name'	=> $file['originalName']
					]);
				}
				$bill->invoiceFile()->saveMany($attach);
			}
            //$this->sendAdminInvoiceNotification($bill->id,$bill->invoice_no, $bill->property_unit->unit_number); // comment for change to invoice_no_label
            $this->sendAdminInvoiceNotification($bill->id,$bill->invoice_no_label, $bill->property_unit->unit_number);
			return redirect('fees-bills/invoice');
		}
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'bills/'.$folder;
        $directories = Storage::disk('s3')->directories('bills'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function getAttach ($id) {
		$file = InvoiceFile::find($id);
        $file_path = 'bills'.'/'.$file->url.$file->name;
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

	public function incomeExpenseReport () {
		$property = Property::find(Auth::user()->property_id);
		return view('feesbills.report')->with(compact('property'));
	}

	public function reportMonth () {
		if(Request::ajax()) {
			$date 	 = Request::get('year')."-".Request::get('month');
			$l_date  = date('Y-m-t 23:59:59',strtotime($date));
			$date_bw = array($date."-01 00:00:00", $l_date);
			$report_controller = new GeneralFeesBillsReportController;
			$trans 	 	= $report_controller->getActiveTransaction($date_bw);
			$result 	= $report_controller->returnReport($trans);
			$result 	+= $report_controller->fundReport ($date_bw);
			return response()->json( $result );
		}
	}

	public function reportYear () {
		if(Request::ajax()) {
			$date 	 = Request::get('year');
			$date_bw = array($date."-01-01 00:00:00", $date."-12-31 23:59:59");
			$report_controller = new GeneralFeesBillsReportController;
			$trans 	 	= $report_controller->getActiveTransaction($date_bw);
			$result 	= $report_controller->returnReport($trans);
			$result 	+= $report_controller->fundReport ($date_bw);
			return response()->json( $result );
		}
	}


	public function CommonFeeReport () {
		$paid = array();
		$date 	= Request::get('year')."-".Request::get('month');
		$cfr_count 	= CommonFeesRef::where('from_date','<=', $date."-15")->where('to_date','>=', $date."-15")->where('property_id',Auth::user()->property_id)->count();
		if($cfr_count) {
			$cfr 	= CommonFeesRef::where('from_date','<=', $date."-15")->where('to_date','>=', $date."-15")->where('property_id',Auth::user()->property_id)->where('payment_status',true)->get();
			if(!empty($cfr)) {
				foreach ($cfr as $pay) {
					$paid[] = $pay->property_unit_unique_id;
				}
			}
			$paid = array_unique($paid);
		}
		$date = strtotime($date."-01");
		$property = Property::find(Auth::user()->property_id);
		$property_unit = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->get();
		return view('feesbills.cf-report')->with(compact('cfr_count','paid','property_unit','property','date'));
	}

    public function sendAdminInvoiceNotification ($invoice_id,$invoice_no, $unit_no) {
		//$title = json_encode( ['invoice_no' => NB_INVOICE.invoiceNumber($invoice_no),'unit_no' => $unit_no] ); // comment for change to invoice_no_label
		$title = json_encode( ['invoice_no' => $invoice_no,'unit_no' => $unit_no] );
        $users = User::where('property_id',Auth::user()->property_id)->where( function ($q) {
            $q->where( 'role', 1 )->orWhere(function ($q_) {
                $q_->where( 'role', 3 )->whereHas('position', function ($query) {
                    $query->where('menu_finance_group', true);
                });
            });
        })->get();
		foreach ($users as $user) {
			$notification = Notification::create([
				'title'				=> $title,
				'notification_type' => '13',
				'from_user_id'		=> Auth::user()->id,
				'to_user_id'		=> $user->id,
				'subject_key'		=> $invoice_id
			]);

			$textNoti = $this->convertTitleTolongTxt($notification);

			$dataPusher = [
				'title'			=> $textNoti,
				'notification'  => $notification
			];

			Pusher::trigger(Auth::user()->property_id."_".$user->id, 'notification_event', $dataPusher);
		}
	}

	function convertTitleTolongTxt($notification){
		$data = json_decode($notification->title,true);
		return $notification->sender->name." ".trans('messages.Notification.invoice_paid',['in_no'=> $data['invoice_no'],'unit_no'=> $data['unit_no']]);
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
