<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use App;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\GeneralFeesBillsController;
# Model
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\Property;
use App\Payee;
use App\PettyCash;
use App\Bank;
use App\BankTransaction;
use Carbon\Carbon;

class ExpensesController extends GeneralFeesBillsController {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu', 'expenses');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function expensesList (Request $form) {
		$bills = Invoice::where('property_id','=',Auth::user()->property_id)->whereIn('payment_status',[1,3]);
		if($form::isMethod('post')) {

            if(!empty($form::get('invoice-no'))) {
                $bills->where('expense_no_label','like','%'.trim($form::get('invoice-no')).'%');
            }

			if(!empty($form::get('start-payment-date'))) {
				$bills->where('payment_date','>=',$form::get('start-payment-date'));
			}

			if(!empty($form::get('end-payment-date'))) {
				$bills->where('payment_date','<=',$form::get('end-payment-date'));
			}

			if(!empty($form::get('payee'))) {
				$bills->where('payee_id',$form::get('payee'));
			}
		}
		$bills = $bills->where('type',2)->orderBy('from_imported','asc')->orderBy('expense_no','desc')->paginate(50);
		if(!$form::ajax()) {
			$payees_list = array( "" => trans('messages.Payee.select_name'));
			$payees_list += Payee::where('property_id',Auth::user()->property_id)->lists('name','id')->toArray();
			return view('expenses.admin-expense-list')->with(compact('bills','payees_list'));
		} else {
			return view('expenses.admin-expense-list-element')->with(compact('bills'));
		}
	}

	public function viewExpense ($id) {
		$bill_check_type = Invoice::find($id);
        $bill = Invoice::with('property','transaction', 'invoiceFile')->find($id);
		return view('expenses.admin-expense-view')->with(compact('bill'));
	}

	public function create () {

		if(Request::isMethod('post')) {
			$tax 		= Request::get('tax')?Request::get('tax'):0;
			$w_tax 		= Request::get('withholding_tax')?Request::get('withholding_tax'):0;
			$property 	= Property::find(Auth::user()->property_id);
			$invoice = new Invoice;
			$invoice->fill(Request::all());
			$invoice->tax 				= $tax;
			$invoice->withholding_tax 	= $w_tax;
			$invoice->property_id 		= Auth::user()->property_id;
			$invoice->property_unit_id 	= null;
			$invoice->expense_no 		= ++$property->expense_counter;
			$invoice->type = 2;
			$invoice->payment_status 	= 1;
			$invoice->payment_type 			= Request::get('payment_type');
			$invoice->final_grand_total 	= $invoice->grand_total;

			if(Request::get('bank_id')) {
				$invoice->bank_transfer_date = Request::get('bank_transfer_date');
				$invoice->transfered_to_bank = true;
			}

            // Generate Running Number
            $month = Carbon::now()->month;
            $year = Carbon::now()->year;
            $date_period = $year.$month;

            $this->getMonthlyCounterDoc($date_period, Auth::user()->property_id);
            $expense_no_label = $this->generateRunningLabel('EXPENSE', null, null, Auth::user()->property_id);

            $invoice->expense_no_label = $expense_no_label;

            // Increase monthlyCounterDoc
            $this->increaseMonthlyCounterDocByPeriod($date_period, 'EXPENSE', Auth::user()->property_id);
            // End Generate Running Number

			$invoice->save();
			$trans = [];
			foreach (Request::get('transaction') as $t) {
				$vat = $_wtax = 0;
				if(!empty($t['vat'])){
                    $vat    = $invoice->tax;
                    $_wtax  = $invoice->withholding_tax;
                }
				$trans[] = new Transaction([
					'detail' 	=> $t['detail'],
					'quantity' 	=> str_replace(',', '', $t['quantity']),
					'price' 	=> str_replace(',', '', $t['price']),
					'total' 	=> str_replace(',', '', $t['total']),
					'transaction_type' 	=> $invoice->type,
					'property_id' 		=> Auth::user()->property_id,
					'property_unit_id' 	=> null,
					'payment_date' 		=> $invoice->payment_date,
					'payment_status' 	=> true,
					'category' 			=> $t['category'],
					'due_date'  		=> $invoice->payment_date,
					'bank_transfer_date'  => $invoice->bank_transfer_date,
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
					'payment_date' 		=> $invoice->payment_date,
					'payment_status' 	=> true,
					'due_date'  		=> $invoice->payment_date,
					'bank_transfer_date'  => $invoice->bank_transfer_date
				]);
			}
			$tax_value = 0;
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
					'payment_date' 		=> $invoice->payment_date,
					'payment_status' 	=> true,
					'due_date'  		=> $invoice->payment_date,
					'bank_transfer_date'  => $invoice->bank_transfer_date
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

			// Save Bank transfer transaction
			if(Request::get('bank_id')) {
				$bt = new BankTransaction;
				$bt->saveBankExpenseTransaction($invoice,Request::get('bank_id'));
				$bank = new Bank;
				$bank->updateBalance (Request::get('bank_id'),-($invoice->grand_total+$tax_value));
			}

			$property->save();
			return redirect('admin/expenses');
		}
		$payees_list = array( "" => trans('messages.Payee.select_name'));
		$payees_list += Payee::where('property_id',Auth::user()->property_id)->lists('name','id')->toArray();
		$bank = new Bank;
		$bank_list = $bank->getBankList(true);
		return view('expenses.admin-create-expense')->with(compact('payees_list','bank_list'));
	}

    function rejectExpense () {

        $id = Request::get('bid');
        $receipt = Invoice::with('transaction')->find($id);
        if($receipt) {
            $receipt->timestamps = false;

            foreach ($receipt->transaction as $t) {
                $t->is_rejected = true;
                $t->save();
            }

            // refresh
            $receipt = Invoice::with('transaction')->find($id);
            $receipt->payment_status = 3;
            $receipt->cancelled_by = Auth::user()->id;
            $receipt->cancel_reason = Request::get('reason');
            $receipt->cancelled_at = date('Y-m-d H:i:s');
            $receipt->timestamps = false;
            $receipt->save();

            if ($receipt->transfered_to_bank) {
                // update bank balance
                $bank_transaction = BankTransaction::with('getBank')->where('invoice_id', $receipt->id)->first();

                if ($bank_transaction) {
                    $bank = $bank_transaction->getBank;
                    $bank->timestamps = false;
                    $bank->balance += $receipt->final_grand_total;
                    $bank->save();
                    $bank_transaction->delete();
                }
            }

            // check petty cash
            $petty = PettyCash::where('invoice_id',$receipt->id)->first();
            if( $petty ) {
                $property = Property::find(Auth::user()->property_id);
                if( $property ) {
                    $property->petty_cash_balance -= $petty->get;
                    $property->save();
                }
                $petty->delete();
            }
        }
        return response()->json(['result' => true ]);
    }
}
