<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use App\Http\Controllers\GeneralFeesBillsController;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use App;
# Model
use DB;
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\PropertyUnit;
use App\Property;
use App\Province;
use App\Vehicle;
use App\Notification;
use App\User;
use App\InvoiceRevision;
use App\CommonFeesRef;
use App\Bank;
use App\BankTransaction;
use Carbon\Carbon;

class RetroactiveReceiptsController extends GeneralFeesBillsController {

	public function __construct () {
		$this->middleware('auth:menu_retroactive_receipt');
		view()->share('active_menu', 'bill');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function index (Request $form) {

		$bills = Invoice::where('property_id','=',Auth::user()->property_id)->where('is_retroactive_record',true);
		if($form::isMethod('post')) {

			if(!empty($form::get('invoice-no')) && intval($form::get('invoice-no')) != 0) {
				$bills->where('receipt_no',intval($form::get('invoice-no')));
			}

			if(!empty($form::get('invoice-unit_id')) && ($form::get('invoice-unit_id') != "-")) {
				$bills->where('property_unit_id',$form::get('invoice-unit_id'));
			} elseif($form::get('invoice-unit_id') != "-") {
				$bills->whereNull('property_unit_id');
			}

			if(!empty($form::get('start-due-date'))) {
				$bills->where('payment_date','>=',$form::get('start-due-date'));
			}

			if(!empty($form::get('end-due-date'))) {
				$bills->where('payment_date','<=',$form::get('end-due-date'));
			}

			if( $form::get('payment-method') == 1) {
				$bills->where('transfer_only', true);
			}

			if( $form::get('payer') == 1) {
				$bills->where('for_external_payer', true);
			}
		}
		$bills = $bills->where('type',1)->orderBy('receipt_no_label','desc')->orderBy('receipt_no','desc')->paginate(50);

		if(!$form::ajax()) {
			$property = Property::find(Auth::user()->property_id);
			$unit_list = array('-'=> trans('messages.unit_no'));
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
			return view('retroactive-receipt.receipt-list')->with(compact('bills','unit_list','property'));
		} else {
			return view('retroactive-receipt.receipt-list-element')->with(compact('bills'));
		}

	}

	public function viewReceipt ($id) {
		$bill_check_type = Invoice::find($id);
        if(isset($bill_check_type->property_unit_id)) {
            $bill = Invoice::with('property', 'property_unit', 'transaction', 'invoiceFile')->find($id);
        }else{
            $bill = Invoice::with('property', 'transaction', 'invoiceFile')->find($id);
        }
		return view('feesbills.admin-receipt-view')->with(compact('bill'));
	}

	public function create () {
		if(Request::isMethod('post')) {

			//dd(Request::all());
			$tax = Request::get('tax')?Request::get('tax'):0;
			$for_external_payer = false;
			if(Request::get('for') == 0) {
				$unit = Request::get('unit_id');
			} else {
				$for_external_payer = true;
			}

			$property 	= Property::find(Auth::user()->property_id);
			$discount = str_replace(',', '', Request::get('discount'));

            $invoice = new Invoice;
            $invoice->fill(Request::all());
            $invoice->created_by  = $invoice->approved_by	= Auth::user()->id;
            $invoice->type 				= 1;
            $invoice->property_id 		= Auth::user()->property_id;
            $invoice->receipt_no 		= ++$property->receipt_counter;
            $invoice->transfer_only 	= (Request::get('transfer_only'))?true:false;
            $invoice->discount 			= $discount;
            $invoice->is_retroactive_record	 = true;
            $invoice->payment_status	 = 2;
            $invoice->final_grand_total = $invoice->grand_total;
            // set receipt number
            $month = Carbon::now()->month;
            $year = Carbon::now()->year;
            $date_period = $year.$month;
            $this->getMonthlyCounterDoc($date_period, Auth::user()->property_id);
            $receipt_no_label = $this->generateRunningLabel('RECEIPT', null, null, Auth::user()->property_id);
            // Increase monthlyCounterDoc
            $this->increaseMonthlyCounterDocByPeriod($date_period,'RECEIPT', Auth::user()->property_id);
            // End Generate Running Number

            $invoice->receipt_no_label = $receipt_no_label;

			if($for_external_payer) {
				// Fro exaternal payee
					$invoice->for_external_payer 	= true;
					$invoice->payer_name 		= Request::get('payer_name');

					if($invoice->payment_type == 2) {
						$invoice->bank_transfer_date = $invoice->payment_date;
						$invoice->transfered_to_bank = true;
					}
					$invoice->save();
					$trans = [];
					foreach (Request::get('transaction') as $t) {
						$trans[] = new Transaction([
							'detail' 	=> $t['detail'],
							'quantity' 	=> str_replace(',', '', $t['quantity']),
							'price' 	=> str_replace(',', '', $t['price']),
							'total' 	=> $t['total'],
							'transaction_type' 	=> $invoice->type,
							'property_id' 		=> Auth::user()->property_id,
							'for_external_payer' => true,
							'category' 			=> $t['category'],
							'due_date'			=> Request::get('due_date'),
							'payment_date'		=> Request::get('payment_date'),
							'submit_date'		=> Request::get('payment_date'),
							'payment_status' 	=> true,
							'bank_transfer_date'  => $invoice->bank_transfer_date
						]);
					}
					// Save discount transaction
					if($invoice->discount > 0) {
						$trans[] = new Transaction([
							'detail' 	=> 'discount',
							'quantity' 	=> 1,
							'price' 	=> $invoice->discount,
							'total' 	=> $invoice->discount,
							'transaction_type' => 3,
							'property_id' => Auth::user()->property_id,
							'for_external_payer' => true,
							'due_date'	=> Request::get('due_date'),
							'payment_date'	=> Request::get('payment_date'),
							'submit_date'	=> Request::get('payment_date'),
							'payment_status' => true
						]);
					}
					$invoice->transaction()->saveMany($trans);
			} else {
				// For unit
				$invoice->property_unit_id 	= $unit;
				if($invoice->payment_type == 2) {
					$invoice->bank_transfer_date = $invoice->payment_date;
					$invoice->transfered_to_bank = true;
				}
				$invoice->save();
				$trans = [];
				foreach (Request::get('transaction') as $t) {
					$total = str_replace(',', '', $t['total']);
					$trans[] = new Transaction([
						'detail' 	=> $t['detail'],
						'quantity' 	=> str_replace(',', '', $t['quantity']),
						'price' 	=> str_replace(',', '', $t['price']),
						'total' 	=> $total,
						'transaction_type' 	=> $invoice->type,
						'property_id' 		=> Auth::user()->property_id,
						'property_unit_id' 	=> $unit,
						'category' 			=> $t['category'],
						'due_date'			=> $invoice->due_date,
						'payment_date'		=> $invoice->payment_date,
						'submit_date'		=> $invoice->payment_date,
						'payment_status' 	=> true,
						'bank_transfer_date'  => $invoice->bank_transfer_date
					]);
				}
				// Save discount transaction
				if($invoice->discount > 0) {
					$trans[] = new Transaction([
						'detail' 	=> 'discount',
						'quantity' 	=> 1,
						'price' 	=> $discount,
						'total' 	=> $discount,
						'transaction_type' 	=> 3,
						'property_id' 		=> Auth::user()->property_id,
						'property_unit_id' 	=> $unit,
						'due_date'			=> $invoice->due_date,
						'payment_date'		=> $invoice->payment_date,
						'submit_date'		=> $invoice->payment_date,
						'payment_status' 	=> true
					]);
				}
				$invoice->transaction()->saveMany($trans);
			}

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

			// Save Counter
			$property->save();
			// Save Bank transfer transaction
			if($invoice->payment_type == 2) {
				$bt = new BankTransaction;
				$bt->saveBankRevenueTransaction($invoice,Request::get('bank_id'));
				$bank = new Bank;
				$bank->updateBalance (Request::get('bank_id'),$invoice->final_grand_total);
			}

			return redirect('admin/retroactive-receipt');
		}
		$unit_list = [];
		$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
		$bank = new Bank;
	    $bank_list = $bank->getBankList();
		return view('retroactive-receipt.create-receipt')->with(compact('unit_list','type','bank_list'));
	}

	public function commonfeeBill (Request $r) {
        
		if($r::isMethod('post')) {
			$unit = $r::get('unit_id');
			$property = Property::find(Auth::user()->property_id);
			$tax = $r::get('tax')?$r::get('tax'):0;
			$discount = str_replace(',', '', Request::get('discount'));
			
			$from_date 		= Request::get('from_year')."-".Request::get('from_month').'-01';
			$_from_y		= date('Y',strtotime($from_date));
			$to_date 		= date('Y-m-t',strtotime(Request::get('to_date')));
			$invoice_name 	= trans('messages.feesBills.cf_generate_head')." ".trans('messages.dateMonth.'.Request::get('from_month'));

			if(Request::get('range') != 1) {
				$_to_m = date('m',strtotime($to_date));
				$_to_y = date('Y',strtotime($to_date));
				if($_from_y != $_to_y) {
					$invoice_name  .= " ".localYear($_from_y);
				}
				$invoice_name  .= " - ".trans('messages.dateMonth.'.$_to_m). " ".localYear($_to_y);
			} else {
				$invoice_name .= " ".localYear($_from_y);
			}

			//get transaction data
			$t_data = $r::get('transaction');
			$tf_data = $t_data[0];
			
			$tf_data['quantity'] 	= str_replace(',', '', $tf_data['quantity']);
			$tf_data['price'] 		= str_replace(',', '', $tf_data['price']);
			$tf_data['total'] 		= str_replace(',', '', $tf_data['total']);
			$tf_data['sub_from_discount'] = $discount;

			$unit_ = PropertyUnit::find($unit);
			$invoice = new Invoice;
			$invoice->fill($r::all());
            $invoice->created_by  = $invoice->approved_by	= Auth::user()->id;

			if( $invoice->discount > $invoice->grand_total )
			$invoice->discount 			= $invoice->grand_total;
			$invoice->name				= $invoice_name;
			$invoice->grand_total 		= $invoice->total-$invoice->discount ;
			$invoice->tax 				= 0;
			$invoice->type 				= 1;
			$invoice->property_id 		= $property->id;
			$invoice->property_unit_id 	= $unit;
			$invoice->receipt_no 		= ++$property->receipt_counter;
			$invoice->transfer_only 	= (Request::get('transfer_only'))?true:false;
			$invoice->payment_status	= 2;
			$invoice->is_retroactive_record	= true;
			$invoice->is_common_fee_bill = true;
			$invoice->final_grand_total = $invoice->total-$invoice->discount;

            $month = Carbon::now()->month;
            $year = Carbon::now()->year;
            $date_period = $year.$month;
            $this->getMonthlyCounterDoc($date_period, Auth::user()->property_id);
            $receipt_no_label = $this->generateRunningLabel('RECEIPT', null, null, Auth::user()->property_id);

            $invoice->receipt_no_label = $receipt_no_label;

			if($invoice->payment_type == 2) {
				$invoice->bank_transfer_date = $invoice->payment_date;
				$invoice->transfered_to_bank = true;
			}
			$invoice->save();

            // Increase monthlyCounterDoc
            $this->increaseMonthlyCounterDocByPeriod($date_period, 'RECEIPT', Auth::user()->property_id);
            // End Generate Running Number

			$trans = new Transaction();
			$trans->fill($tf_data);
			$trans->invoice_id 			= $invoice->id;
			$trans->transaction_type 	= 1;
			$trans->property_id 		= $property->id;
			$trans->property_unit_id 	= $unit;
			$trans->category 			= 1;
			$trans->due_date			= $r::get('due_date');
			$trans->payment_date		= $invoice->payment_date;
			$trans->submit_date			= $invoice->payment_date;
			$trans->bank_transfer_date  = $invoice->bank_transfer_date;
			$trans->payment_status	 	= true;

			if($invoice->discount > 0)
			$trans->sub_from_discount   = $invoice->discount;
			$trans->save();
			// Save discount transaction
			if($invoice->discount > 0) {
				$trans = new Transaction();
				$trans->invoice_id 			= $invoice->id;
				$trans->detail 				= 'discount';
				$trans->quantity 			= 1;
				$trans->price 				= $discount;
				$trans->total 				= $discount;
				$trans->transaction_type 	= 3;
				$trans->property_id 		= Auth::user()->property_id;
				$trans->property_unit_id 	= $unit;
				$trans->due_date			= $invoice->due_date;
				$trans->payment_date		= $invoice->payment_date;
				$trans->submit_date			= $invoice->payment_date;
				$trans->payment_status		= true;
				$trans->bank_transfer_date  = $invoice->bank_transfer_date;
				$trans->save();
			}

			array_forget($t_data,0);
			if(count($t_data)) {
				$trans = [];
				foreach ($t_data as $t) {
					$trans[] = new Transaction([
						'detail' 	=> $t['detail'],
						'quantity' 	=> str_replace(',', '', $t['quantity']),
						'price' 	=> str_replace(',', '', $t['price']),
						'total' 	=> $t['total'],
						'transaction_type' 	=> $invoice->type,
						'property_id' 		=> Auth::user()->property_id,
						'for_external_payer' => true,
						'category' 			=> $t['category'],
						'due_date'			=> Request::get('due_date'),
						'payment_date'		=> Request::get('payment_date'),
						'submit_date'		=> Request::get('payment_date'),
						'payment_status' 	=> true,
						'bank_transfer_date'  => $invoice->bank_transfer_date
					]);
				}
				$invoice->transaction()->saveMany($trans);
			}
			
			//save common fee reference table
			$crf = new CommonFeesRef();
			$crf->invoice_id				= $invoice->id;
			$crf->property_id				= $property->id;
			$crf->property_unit_id 			= $unit;
			$crf->property_unit_unique_id 	= $unit_->property_unit_unique_id;
			$crf->from_date					= $from_date;
			$crf->to_date 					= $to_date;
			$crf->payment_status			= true;
			$crf->range_type 				= Request::get('range');
			$crf->save();

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

			$property->save();

			// Save Bank transfer transaction
			if($invoice->payment_type == 2) {
				$bt = new BankTransaction;
				$bt->saveBankBillTransaction($invoice,Request::get('bank_id'));
				$bank = new Bank;
				$bank->updateBalance (Request::get('bank_id'),$invoice->final_grand_total);
			}
			
			return redirect('admin/retroactive-receipt');
		}

		$unit_list = [];
		$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
		$bank = new Bank;
	    $bank_list = $bank->getBankList();
		return view('retroactive-receipt.create-common-fee-receipt')->with(compact('unit_list','type','bank_list'));
	}

	public function revision () {
		if(Request::isMethod('post')) {
			$revisions = InvoiceRevision::with('by')->where('invoice_id',Request::get('invoice_id'))->orderBy('revision_no','asc')->get();
			if($revisions->count()) {
				return view('retroactive-receipt.revision-list')->with(compact('revisions'));
			}
		}
	}

	public function viewRevision ($id) {
		$revision = InvoiceRevision::find($id);
		$bill = json_decode($revision['details']);
		if($bill->property_unit_id) {
			$property = PropertyUnit::with('property')->find($bill->property_unit_id);
		}
		return view('retroactive-receipt.view-revision')->with(compact('revision','bill','property'));
	}

}
