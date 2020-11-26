<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use App;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
# Model
use App\Property;
use App\PettyCash;
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\PettyCashEditLog;
use App\Bank;
use App\BankTransaction;
use App\PettyCashLogFile;

class PettyCashController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_pettycash');
		view()->share('active_menu', 'expenses');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function pettyCash () {
		$pv = PettyCash::with('createdBy')->where('property_id',Auth::user()->property_id);
		$property 	= Property::find(Auth::user()->property_id);
		if(!Request::ajax()) {
			$date_bw 	= array(date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59'));
			$pv 		= $pv->whereBetween('payment_date', $date_bw)->orderBy('payment_date','desc')->paginate(50);
			$bank 		= new Bank;
	    	$bank_list 	= $bank->getBankList();
			return view('pettycash.pettycash-log-list')->with(compact('pv','property','bank_list'));
		} else {
			$date 	 	= Request::get('year')."-".Request::get('month');
			$l_date  	= date('Y-m-t 23:59:59',strtotime($date));
			$date_bw 	= array($date."-01 00:00:00", $l_date);
			$pv 		= $pv->whereBetween('payment_date', $date_bw)->orderBy('payment_date','desc')->paginate(50);
			return view('pettycash.pettycash-log-list-element')->with(compact('pv'));
		}
	}

	public function pettyCashPrint () {
		
		if(Request::isMethod('post')) {
			$pv = PettyCash::with('createdBy')->where('property_id',Auth::user()->property_id);
			$property 			= Property::find(Auth::user()->property_id);
			$date 	 = Request::get('year')."-".Request::get('month');
			$l_date  = date('Y-m-t 23:59:59',strtotime($date));
			$date_bw = array($date."-01 00:00:00", $l_date);

			$month = trans('messages.dateMonth.'.Request::get('month'));
			$year = localYear(Request::get('year'));

			$pv = $pv->whereBetween('payment_date', $date_bw)->get();
			return view('pettycash.pettycash-log-print')->with(compact('pv','property','month','year'));
		}
	}

	public function savePettyCashLog () {
		if(Request::isMethod('post')) {
			$property 	= Property::find(Auth::user()->property_id);
			$new_pv_log = new PettyCash;
			$new_pv_log->fill(Request::all());
			$new_pv_log->pay 				= str_replace(',', '', $new_pv_log->pay);
			$new_pv_log->creator 			= Auth::user()->id;
			$new_pv_log->property_id 		= Auth::user()->property_id;
			$new_pv_log->save();
			$property->petty_cash_balance 	= $property->petty_cash_balance - $new_pv_log->pay;
			$property->save();

            if(!empty(Request::get('attachment'))) {
                $petty_cash_img = [];
                foreach (Request::get('attachment') as $img) {
                    //Move Image
                    $path = $this->createLoadBalanceDir($img['name']);
                    $petty_cash_img[] = new PettyCashLogFile([
                        'name' => $img['name'],
                        'url' => $path,
                        'file_type' => $img['mime'],
                        'is_image'	=> $img['isImage'],
                        'original_name'	=> $img['originalName']
                    ]);
                }
                $new_pv_log->pettyCashFile()->saveMany($petty_cash_img);
            }

			return redirect('admin/expenses/pettycash');
		}
	}

	public function getPettyCashLog () {
		$pv_log = PettyCash::select('id', 'detail', 'ref_no','pay','payment_date')->find(Request::get('id'));
		$pv_log->pay 			= number_format($pv_log->pay,2);
		$pv_log->payment_date 	= date('Y/m/d',strtotime( $pv_log->payment_date ));
		return response()->json($pv_log->toArray());
	}

	public function editPettyCashLog () {
		if(Request::isMethod('post')) {
			$property 	= Property::find(Auth::user()->property_id);
			$pv_log 	= PettyCash::find(Request::get('id'));
			if($pv_log->editable) {
				// Save previous data to log
				$log = new PettyCashEditLog;
				$log->pc_log_id 	= $pv_log->id;
				$log->editor 		= Auth::user()->id;
				$log->content 		= json_encode( $pv_log->toArray() );
				$log->save();

				$old_pay 			= $pv_log->pay;
				$pay 				= str_replace(',', '', Request::get('pay'));
				$pv_log->fill(Request::all());
				$pv_log->pay 		= $pay;
				//$pv_log->creator 	= Auth::user()->id;
				$pv_log->save();
				$property->petty_cash_balance = $property->petty_cash_balance + $old_pay - $pay;
				$property->save();
			}
			
			return redirect('admin/expenses/pettycash');
		}
	}

	public function deletePettyCashLog () {
		if(Request::isMethod('post')) {
			$property 	= Property::find(Auth::user()->property_id);
			$pv_log 	= PettyCash::find(Request::get('id'));
			if($pv_log->editable) {
				$old_pay 			= $pv_log->pay;
				$pv_log->editLog()->delete();
				$pv_log->delete();
				$property->petty_cash_balance += $old_pay;
				$property->save();
			}
			
			return redirect('admin/expenses/pettycash');
		}
	}

	public function pettyCashReturn () {
		if(Request::isMethod('post')) {

			$property 	= Property::find(Auth::user()->property_id);
			$month 	= trans('messages.dateMonth.'.date('m'));
			$year 	= localYear(date('Y'));

			$invoice = new Invoice;
			$invoice->name 				= trans('messages.PettyCash.return_name',['m'=>$month,'y'=>$year]);
			$invoice->type 				= 1;
			$invoice->property_id 		= $property->id;
			$invoice->final_grand_total = $invoice->grand_total;
			$invoice->payment_status	= 2;
			$invoice->payment_type		= Request::get('payment_type');
			$invoice->remark			= Request::get('remark');
			$invoice->total				= 
			$invoice->final_grand_total = 
			$invoice->grand_total 		= $property->petty_cash_balance;
			$invoice->payment_date		= 
			$invoice->submit_date		= 
			$invoice->due_date			= date('Y-m-d H:i:s');
			$invoice->is_petty_cash_bill = true;
			$invoice->is_revenue_record	= true;
			if($invoice->payment_type == 2) {
				$invoice->bank_transfer_date = $invoice->payment_date;
				$invoice->transfered_to_bank = true;
			}
			$invoice->save();

			// unset petty cash
			$property->petty_cash_balance = 0;
			$property->save();

			$trans = new Transaction;
			$trans->invoice_id			= $invoice->id;
			$trans->detail 				= $invoice->name;
			$trans->quantity 			= 1;
			$trans->price 				= 
			$trans->total 				= $invoice->grand_total;
			$trans->transaction_type 	= $invoice->type;
			$trans->property_id 		= $invoice->property_id;
			$trans->category 			= 11; // returning petty cash
			$trans->due_date			= 
			$trans->submit_date			= 
			$trans->payment_date		= $invoice->payment_date;
			$trans->payment_status		= true;
			$trans->bank_transfer_date  = $invoice->bank_transfer_date;
			$trans->save();

			// Save attachments
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
				$invoice->invoiceFile()->saveMany($attach);
			}
			// save pettycash log
			$new_pv_log = new PettyCash;
			$new_pv_log->detail 		= $invoice->name;
			$new_pv_log->invoice_id 	= $invoice->id;
			$new_pv_log->payment_date 	= $invoice->payment_date;
			$new_pv_log->pay 			= $invoice->grand_total;
			$new_pv_log->creator 		= Auth::user()->id;
			$new_pv_log->property_id 	= Auth::user()->property_id;
			$new_pv_log->editable		= false;
			$new_pv_log->save();

			// Save Bank transfer transaction
			if($invoice->payment_type == 2) {
				$bt = new BankTransaction;
				$bt->saveBankRevenueTransaction($invoice,Request::get('bank_id'));
				$bank = new Bank;
				$bank->updateBalance (Request::get('bank_id'),$invoice->final_grand_total);
			}

			return redirect('admin/expenses/pettycash');
		}
	}

	function viewPCLog () {
        $pc = PettyCash::with('createdBy','pettyCashFile','refInvoice')->find(Request::get('id'));
		return view('pettycash.pettycash-view')->with(compact('pc'));
	}

	function getPCEditLog () {
        $pce = PettyCashEditLog::with('logEditor')->where('pc_log_id',Request::get('id'))->orderBy('created_at','asc')->get();
		return view('pettycash.pettycash-edit-log-view')->with(compact('pce'));
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
}