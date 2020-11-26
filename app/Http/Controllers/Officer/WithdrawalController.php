<?php namespace App\Http\Controllers\Officer;
use Request;
use Illuminate\Routing\Controller;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\GeneralFeesBillsController;
use Carbon\Carbon;

# Model
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\Property;
use App\Payee;
use App\WithdrawalRequester;

class WithdrawalController extends GeneralFeesBillsController {

	public function __construct () {
		$this->middleware('auth:menu_pettycash');
		view()->share('active_menu', 'expenses');
		if(Auth::check() && !in_array(Auth::user()->role,[1,3])) Redirect::to('feed')->send();
	}
	public function createPCWithdrawal () {
		$property = Property::with('has_province')->find(Auth::user()->property_id);
		return view('expenses.admin-create-pettycash-withdrawal-slip')->with(compact('property'));
	}
	public function createWithdrawal () {
		if(Request::isMethod('post')) {
			$tax = Request::get('tax')?Request::get('tax'):0;
			$w_tax 		= (Request::get('withholding_tax') && Request::get('withholding_total') > 0)?Request::get('withholding_tax'):0;
			$property 	= Property::find(Auth::user()->property_id);
			$invoice = new Invoice;
			$invoice->fill(Request::all());
			$invoice->tax 				= $tax;
			$invoice->withholding_tax 	= $w_tax;
			$invoice->property_id 		= Auth::user()->property_id;
			$invoice->property_unit_id 	= null;
			$invoice->withdrawal_no 	= ++$property->withdrawal_slip_counter;
			$invoice->type 				= 2;
			$invoice->payment_status 	= 0;
			$invoice->payment_type 		= Request::get('payment_type');
			$invoice->due_date			= date('Y-m-t');
			$invoice->submit_date 		= date('Y-m-d H:i:s');
			$invoice->final_grand_total = $invoice->grand_total;

            // Generate Running Number
            $month = Carbon::now()->month;
            $year = Carbon::now()->year;
            $date_period = $year.$month;

            $this->getMonthlyCounterDoc($date_period,Auth::user()->property_id);
            $withdrawal_no_label = $this->generateRunningLabel('WITHDRAWAL', null, null, Auth::user()->property_id);

            $invoice->withdrawal_no_label = $withdrawal_no_label;

            // Increase monthlyCounterDoc
            $this->increaseMonthlyCounterDocByPeriod($date_period, 'WITHDRAWAL', Auth::user()->property_id);
            // End Generate Running Number

			$invoice->save();
			$trans = [];
			foreach (Request::get('transaction') as $t) {
                $vat = $_wtax = 0;
                if(!empty($t['vat'])){
                    $vat    = $invoice->tax;
                    $_wtax  = $invoice->withholding_tax;
                }
				if(!empty($t['vat'])) $vat = $invoice->tax;
				$trans[] = new Transaction([
					'detail' 	=> $t['detail'],
					'quantity' 	=> str_replace(',', '', $t['quantity']),
					'price' 	=> str_replace(',', '', $t['price']),
					'total' 	=> str_replace(',', '', $t['total']),
					'transaction_type' 	=> $invoice->type,
					'property_id' 		=> Auth::user()->property_id,
					'property_unit_id' 	=> null,
					'payment_status' 	=> false,
					'category' 			=> $t['category'],
					'due_date'  		=> $invoice->due_date,
					'submit_date'  		=> $invoice->submit_date,
					'vat'				=> $vat,
                    'w_tax'             => $_wtax
				]);
			}

			if($invoice->tax > 0) {
				$tax_value = Request::get('tax_total');
				$trans[] = new Transaction([
					'detail' 	=> trans('messages.feesBills.vat'),
					'quantity' 	=> 1,
					'price' 	=> $tax_value,
					'total' 	=> $tax_value,
					'transaction_type' 	=> 5,
					'property_id' 		=> Auth::user()->property_id,
					'property_unit_id' 	=> null,
					'payment_status' 	=> false,
					'due_date'  		=> $invoice->due_date,
					'submit_date'  		=> $invoice->submit_date
				]);
			}

			if($invoice->withholding_tax > 0) {
				$tax_value = Request::get('withholding_total');
				$trans[] = new Transaction([
					'detail' 	=> trans('messages.feesBills.withholding_tax'),
					'quantity' 	=> 1,
					'price' 	=> $tax_value,
					'total' 	=> $tax_value,
					'transaction_type' 	=> 6,
					'property_id' 		=> Auth::user()->property_id,
					'property_unit_id' 	=> null,
					'payment_status' 	=> false,
					'due_date'  		=> $invoice->due_date,
					'submit_date'  		=> $invoice->submit_date
				]);
			}

			$invoice->transaction()->saveMany($trans);

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

			// Save requester
			$wr = new WithdrawalRequester;
			$wr->user_id = Auth::user()->id;
			$wr->invoice_id = $invoice->id;
			$wr->save();
			
			return redirect('admin/expenses/withdrawal/list');
		}
		$payees_list = array( "" => trans('messages.Payee.select_name'));
		$payees_list += Payee::where('property_id',Auth::user()->property_id)->lists('name','id')->toArray();
		return view('expenses.admin-create-withdrawal-slip')->with(compact('payees_list'));
	}
}
