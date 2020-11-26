<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use App\Http\Controllers\GeneralFeesBillsController;
use App;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use Carbon\Carbon;
# Model
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\Property;
use App\Payee;
use App\PettyCash;
use App\Bank;
use App\BankTransaction;

class WithdrawalController extends GeneralFeesBillsController {

	public function __construct () {
		$this->middleware('auth:menu_pettycash');
		view()->share('active_menu', 'expenses');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function withdrawalList (Request $form) {
		
		$bills = Invoice::where('property_id','=',Auth::user()->property_id)->whereIn('payment_status',[0,2]);
		if($form::isMethod('post')) {

			if(!empty($form::get('invoice-no')) && intval($form::get('invoice-no')) != 0) {
				$bills->where('withdrawal_no',intval($form::get('invoice-no')));
			}

			if(!empty($form::get('start-submit-date'))) {
				$bills->where('submit_date','>=',$form::get('start-submit-date'));
			}

			if(!empty($form::get('end-submit-date'))) {
				$bills->where('submit_date','<=',$form::get('end-submit-date'));
			}

			if(!empty($form::get('payee'))) {
				$bills->where('payee_id',$form::get('payee'));
			}
		}
		$bills = $bills->where('type',2)->orderBy('withdrawal_no','desc')->paginate(50);
		if(!$form::ajax()) {
			$payees_list = array( "" => trans('messages.Payee.select_name'));
			$payees_list += Payee::where('property_id',Auth::user()->property_id)->lists('name','id')->toArray();
			return view('expenses.admin-expense-withdrawal-list')->with(compact('bills','payees_list'));
		} else {
			return view('expenses.admin-expense-withdrawal-list-element')->with(compact('bills'));
		}
	}

	public function viewWithdrawal ($id) {
		$bill_check_type = Invoice::find($id);
        $bill = Invoice::with('property','transaction', 'invoiceFile')->find($id);
		if(Auth::user()->role == 1 || Auth::user()->role == 3) {
			$payees_list = array( "" => trans('messages.Payee.select_name'));
			$payees_list += Payee::where('property_id',Auth::user()->property_id)->lists('name','id')->toArray();
			$bank = new Bank;
			$bank_list = $bank->getBankList(true);
			return view('expenses.admin-expense-withdrawal-admin-view')->with(compact('bill','payees_list','bank_list'));
		} else {
			return view('expenses.admin-expense-withdrawal-view')->with(compact('bill'));
		}

	}

    public function printWithdrawal($id){
        $bill_check_type = Invoice::find($id);
        $bill = Invoice::with('property','transaction', 'invoiceFile')->find($id);
        if(Auth::user()->role == 1 || Auth::user()->role == 3) {
            $payees_list = array( "" => trans('messages.Payee.select_name'));
            $payees_list += Payee::where('property_id',Auth::user()->property_id)->lists('name','id')->toArray();
            $bank = new Bank;
            $bank_list = $bank->getBankList(true);
            return view('expenses.print-view.admin-expense-withdrawal-admin-view')->with(compact('bill','payees_list','bank_list'));
        } else {
            return view('expenses.print-view.admin-expense-withdrawal-view')->with(compact('bill'));
        }
    }

	public function viewCanceledWithdrawal ($id) {
		$bill_check_type = Invoice::find($id);
        $bill = Invoice::with('property','transaction')->find($id);
		return view('expenses.admin-expense-withdrawal-view')->with(compact('bill'));
	}

	public function approveWithdrawal () {
		if(Request::isMethod('post')) {
            
			$id = Request::get('id');
			$slip = Invoice::with('transaction')->find($id);
			$slip->fill(Request::all());
			$property 	= Property::find(Auth::user()->property_id);
			$slip->payment_status 	= 1;
			$slip->expense_no 		= ++$property->expense_counter;

			if(Request::get('bank_id')) {
				$slip->bank_transfer_date = Request::get('bank_transfer_date');
				$slip->transfered_to_bank = true;
			}

            // Generate Running Number
            $month = Carbon::now()->month;
            $year = Carbon::now()->year;
            $date_period = $year.$month;

            $this->getMonthlyCounterDoc($date_period, Auth::user()->property_id);
            $expense_no_label = $this->generateRunningLabel('EXPENSE', null, null, Auth::user()->property_id);

            $slip->expense_no_label = $expense_no_label;

            // Increase monthlyCounterDoc
            $this->increaseMonthlyCounterDocByPeriod($date_period, 'EXPENSE', Auth::user()->property_id);
            // End Generate Running Number

			$slip->save();
			// check petty cash
			$petty_cash = 0;
			foreach ($slip->transaction as $t) {
				$t->payment_status 	= true;
				$t->payment_date 	= $slip->payment_date;
				$t->bank_transfer_date  = $slip->bank_transfer_date;
				//is petty cash category
				if($t->category == 6) {
					$petty_cash += $t->total;
				}
				$t->save();
			}
			//// check petty cash
			if($petty_cash) {
				$pc = new PettyCash;
				$pc->property_id 	= Auth::user()->property_id;
				$pc->get 			= $petty_cash;
				$pc->detail 		= $slip->name;
				$pc->invoice_id 	= $slip->id; 
				$pc->creator 		= Auth::user()->id; 
				$pc->payment_date 	= ($slip->bank_transfer_date)?$slip->bank_transfer_date:$slip->payment_date;//date('Y-m-d');
				$pc->editable		= false;
				$pc->save();

				$property->petty_cash_balance = $petty_cash+$property->petty_cash_balance;
				$slip->is_petty_cash_bill = true;
				$slip->save();
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
				$slip->invoiceFile()->saveMany($attach);
			}

			// Save Bank transfer transaction
			if(Request::get('bank_id')) {
				$bt = new BankTransaction;
				$bt->saveBankExpenseTransaction($slip,Request::get('bank_id'));
				$tax_value = 0;
				if($slip->withholding_tax){
					$tax_value = $slip->withholding_tax*$slip->total/100;
				}
				$bank = new Bank;
				$bank->updateBalance (Request::get('bank_id'), -($slip->final_grand_total+$tax_value));
			}

			$property->save();
		}

		return redirect('admin/expenses/withdrawal/list');
	}

	public function cancelWithdrawal () {
		if(Request::isMethod('post')) {
			$id = Request::get('bid');
	        $slip = Invoice::find($id);
			$slip->payment_status 	= 2;
			$slip->save();
		}
		return redirect('admin/expenses/withdrawal/list');
	}
}