<?php

namespace App\Http\Controllers\PropertyAdmin;

use Request;
use App\Http\Controllers\GeneralFeesBillsController;
use Auth;
use File;
use Redirect;
use App;
//use DateTime;
# Model
use App\Invoice;
use App\Property;
use App\Vehicle;
use App\BankTransaction;
use App\Bank;
use App\User;
use App\Transaction;
use App\InvoiceInstalmentLog;
use App\PropertyUnit;
use App\CommonFeesRef;
use App\SmartBill;
use App\SmartBillReceiptLog;
use Carbon\Carbon;
use DB;

class SmartBillController extends GeneralFeesBillsController
{
    public function __construct () {
        if(Auth::check() && Auth::user()->role == 3){
            $this->middleware('auth:menu_finance_group');
        }
		$this->middleware('auth');
		view()->share('active_menu', 'bill');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
    }
    
    public function invoiceList (Request $form) {
		$show_debt = false;
		$bills = Invoice::where('property_id','=',Auth::user()->property_id)
            ->where('is_retroactive_record',false)
            ->where('is_revenue_record',false)->where('payment_status',1)->has('smartPaymentLog');

		if( $form::get('invoice-status') != "") {
			$bills->where('payment_status',$form::get('invoice-status'));
		} elseif( empty($form::get('invoice-no')) ) {
			$bills->whereIn('payment_status',[0,1]);
		}

		if($form::isMethod('post')) {
			$debt = 0;
            $invoice_no = $form::get('invoice-no');
            if($invoice_no != ""){
                $bills->where('invoice_no_label','like','%'.trim($invoice_no).'%');
            }

			if(!empty($form::get('invoice-unit_id')) && ($form::get('invoice-unit_id') != "-")) {
                $bid = $form::get('invoice-unit_id');
                $bills->whereHas('property_unit', function ($query) use ($bid) {
                    $query->where('property_unit_unique_id', $bid);
                });

				$show_debt = true;
				$debt = $this->getPropertyUnitDebt($form::get('invoice-unit_id'));
			} elseif($form::get('invoice-unit_id') != "-") {
				$bills->whereNull('property_unit_id');
			}

            if(!empty($form::get('owner_name'))) {
                $name = $form::get('owner_name');
                $bills->whereHas('property_unit', function ($query) use ($name) {
                    $query->where('owner_name_th', 'like', "%".$name."%");
                    $query->where('owner_name_en', 'like', "%".$name."%");
                });
            }

            if(!empty($form::get('floor'))) {
                $floor = $form::get('floor');
                $bills->whereHas('property_unit', function ($query) use ($floor) {
                    $query->where('unit_floor', $floor);
                });
            }

            if(!empty($form::get('start-due-date'))) {
				$bills->where('due_date','>=',$form::get('start-due-date'));
			}

			if(!empty($form::get('end-due-date'))) {
				$bills->where('due_date','<=',$form::get('end-due-date'));
			}

			if( $form::get('payment-method') == 1) {
				$bills->where('transfer_only', true);
			}

			if( $form::get('payer') == 1) {
				$bills->where('for_external_payer', true);
			}
		}

        $bills = $bills->where('type',1)->orderBy('invoice_no_label','desc')->paginate(50);
		if(!$form::ajax()) {
			$unit_list = array('-'=> trans('messages.unit_no'));
            $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','property_unit_unique_id')->toArray();

            $history = SmartBillReceiptLog::where('property_id', Auth::user()->property_id)->orderBy('created_at','desc')->paginate(20);
            $officers = User::where('property_id',Auth::user()->property_id)->whereIn('role',[1,3])->lists('name','id')->toArray();

			return view('feesbills.smart-bill.admin-invoice-list')->with(compact('bills','unit_list','show_debt','history','officers'));
		} else {
			return view('feesbills.smart-bill.admin-invoice-list-element')->with(compact('bills','show_debt','debt'));
		}

    }
    
    public function smartToReceipt () {
        if(Request::isMethod('post')) {
            $ids =  Request::get('list-bill');
            $ids = explode(',',$ids);
            $receipt = [];
            if( $ids ) {
                $data = Invoice::with('transaction','smartPaymentLog')->whereIn('id', $ids)->chunk(100, function($invoices) use (&$receipt) {
                    foreach ($invoices as $inv) {
                        if( $inv->smartPaymentLog ) {
                            $rid = $this->updateInvoice($inv);
                            if( $rid ) {
                                $receipt[] = $rid;
                            }
                        }
                    }
                });
            }
            if( !empty($receipt) ) {
                $log = new SmartBillReceiptLog;
                $log->property_id   = Auth::user()->property_id;
                $log->by_user_id    = Auth::user()->id;
                $log->invoice_ids   = json_encode($receipt);
                $log->save();
            }
            Request::session()->flash('class', 'success');
            Request::session()->flash('message', trans('messages.feesBills.Smart.smart_receipt_success',['count' => count($ids)]));
        }

        return redirect()->back();
    }
    
    function updateInvoice ($bill) {
        
        if( $bill->payment_status == 1 ) {
            $noti_log = $bill->smartPaymentLog;
            if(  $bill->instalmentLog->count() ) {
                $receipt_id = $this->updateInvoiceInstalment($noti_log,$bill);
            } else {
                $property 	= Property::find( $bill->property_id );
                $bill->payment_status 	= 2;
                if(!$bill->submit_date) {
                    $bill->submit_date = date('Y-m-d h:i:s');
                }
                $bill->receipt_no 	=  ++$property->receipt_counter;
                // Generate Running Number
                $month = Carbon::now()->month;
                $year = Carbon::now()->year;
                $date_period = $year.$month;

                $this->getMonthlyCounterDoc($date_period,$bill->property_id);
                $receipt_no_label = $this->generateRunningLabel('RECEIPT', null, null, $bill->property_id);

                $bill->receipt_no_label = $receipt_no_label;

                // Increase monthlyCounterDoc
                $this->increaseMonthlyCounterDocByPeriod($date_period, 'RECEIPT', $bill->property_id);
                // End Generate Running Number

                $bill->payment_type = 2;
                $bill->payment_date = $noti_log->transDate;
                $bill->bank_transfer_date = $bill->payment_date;
                $bill->transfered_to_bank = true;

                // Check installment
                if( $bill->instalmentLog->count() ) {
                    $bill->final_grand_total 	= $bill->total - $bill->discount - $bill->sub_from_balance;
                    $bill->grand_total 			= $bill->final_grand_total ;
                }

                $bill->approved_by = Auth::user()->id;
                $bill->save();
                $property->save();

                foreach ($bill->transaction as $tr) {
                    if(!$tr->submit_date) {
                        $tr->submit_date = $bill->submit_date;
                    }
                    $tr->payment_date 		= $bill->payment_date;
                    $tr->bank_transfer_date = $bill->bank_transfer_date;
                    $tr->payment_status 	= true;
                    $tr->save();
                }

                // Check if sticker
                $vehicle = Vehicle::where('invoice_id',$bill->id)->first();
                if($vehicle) {
                    $vehicle->sticker_status = 3;
                    $vehicle->invoice_id = null;
                    $vehicle->save();
                }

                // save common fee ref.
                if($bill->commonFeesRef) {
                    $bill->commonFeesRef->payment_status = true;
                    $bill->commonFeesRef->save();
                }

                // Save Bank transfer transaction
                $smart_bill_setting = SmartBill::where('property_id',$bill->property_id)->first();
                if( $smart_bill_setting && $smart_bill_setting->property_bank_id) {
                    $bt = new BankTransaction;
                    $bt->saveBankBillTransaction($bill,$smart_bill_setting->property_bank_id);
                    $bank = new Bank;
                    $bank->updateBalance ($smart_bill_setting->property_bank_id,$bill->final_grand_total);
                }

                // TODO: notification success to mobile
                if(!$bill->for_external_payer){
                    $admin = User::where('role',1)->where('property_id',$bill->property_id)->first();
                    $this->sendTransactionCompleteNotification($bill->property_id, $bill->property_unit_id,$bill->name,$bill->id,Auth::user()->id);
                }
                $receipt_id = $bill->id;
            }
        } else {
            $receipt_id = false;
        }
        return $receipt_id;
    }

    function updateInvoiceInstalment ($noti_log,$invoice) {
    
        $admin = User::where('role',1)->where('property_id',$invoice->property_id)->first();
        $property = Property::find($invoice->property_id);
        
        $maxlengthMonth 			= $invoice->commonFeesRef->range_type;
        $last_month_to_instalment 	= $invoice->commonFeesRef->to_date;
        $sum_month 					= 0;
        foreach ($invoice->instalmentLog as $log) {
            $sum_month += $log->range_type;
        }
        $remain_month 	= $maxlengthMonth - $sum_month;
        $ci 			= $invoice->instalmentLog->count();
        if( $ci ) {
            $start_month = $invoice->instalmentLog->toArray();
            $start_month = $start_month[$ci-1]['to_date'];
        } else {
            $start_month = $invoice->commonFeesRef->from_date;
        }

        $time_start 				= strtotime($start_month);
        // get start date of next month to start instalment by payment
        $next_month_to_instalment 	= date('Y-m-d',strtotime("+1 day",$time_start));

        //Latest instalment month: date('Y-m-d',$time_start)
        $new_bill = new Invoice;	
        $new_bill->fill($invoice->toArray());
        $new_bill->receipt_no 		= ++$property->receipt_counter;

        // Generate Running Number RECEIPT
        $month = Carbon::now()->month;
        $year = Carbon::now()->year;
        $date_period = $year.$month;

        $this->getMonthlyCounterDoc($date_period, $admin->property_id);
        $receipt_no_label = $this->generateRunningLabel('RECEIPT',null,null, $admin->property_id);

        $new_bill->receipt_no_label = $receipt_no_label;

        // Increase monthlyCounterDoc
        $this->increaseMonthlyCounterDocByPeriod($date_period,'RECEIPT', $admin->property_id);
        // End Generate Running Number

        $new_bill->payment_status	= 2;
        $new_bill->is_common_fee_bill = true;
        $new_bill->total 			= $invoice->total - $invoice->instalment_balance;
        $new_bill->grand_total 		= $invoice->grand_total - $invoice->instalment_balance;
        $new_bill->final_grand_total= $invoice->grand_total - $invoice->instalment_balance;
        $new_bill->due_date			= $invoice->due_date;
        $new_bill->submit_date		= date('Y-m-d h:i:s');
        $new_bill->payment_date		= $noti_log->transDate;
        $new_bill->smart_bill_ref_code = $invoice->smart_bill_ref_code;
        $new_bill->approved_by      = Auth::user()->id;
        $new_bill->bank_transfer_date = $new_bill->payment_date;
        $new_bill->transfered_to_bank = true;
        $new_bill->payment_type = 2;
        $new_bill->save();

        // save counter
        $property->save();

        $remaining_cf = 0;
        foreach( $invoice->transaction as $t_old) {
            
            $t = new Transaction;
            $t->fill($t_old->toArray());
            $remain_total 			= $t_old->total - $t_old->instalment_balance;

            if($t_old->category == 1) {
                //check remaining amount of common fee
                $remain_total = $remain_total - $t_old->sub_from_balance - $t_old->sub_from_discount;
                $remaining_cf = $remain_total;
                
            }

            if($remain_total > 0) {
                $t->payment_date 		= $new_bill->payment_date;
                $t->payment_status 		= true;
                $t->quantity 			= 1;
                $t->price = $t->total 	= $remain_total;
                $t->submit_date 		= $new_bill->submit_date;
                $t->instalment_balance	= 0;
                $t->bank_transfer_date 	= $new_bill->bank_transfer_date;
                $t->invoice_id 			= $new_bill->id;
                $t->save();
            }
            
            // reject transaction
            $t_old->is_rejected = true;
            $t_old->save();
        }

        // Save instalment log
        $ins = new InvoiceInstalmentLog;
        $ins->invoice_id 	= $invoice->id;
        $ins->to_receipt_id = $new_bill->id;
        $ins->title 		= $new_bill->name;
        $ins->amount 		= $new_bill->final_grand_total;
        $ins->receipt_no 	= $new_bill->receipt_no;
        $ins->receipt_no_label 	= $new_bill->receipt_no_label;
        if($remaining_cf > 0 ) {
            $ins->range_type 	= $remain_month;
            $ins->from_date 	= $next_month_to_instalment;
            $ins->to_date 		= $last_month_to_instalment;

            $unit = PropertyUnit::find($new_bill->property_unit_id);

            //save common fee reference table
            $crf = new CommonFeesRef;
            $crf->invoice_id				= $new_bill->id;
            $crf->property_id				= $property->id;
            $crf->property_unit_id 			= $new_bill->property_unit_id;
            $crf->property_unit_unique_id 	= $unit->property_unit_unique_id;
            $crf->from_date					= $next_month_to_instalment;
            $crf->to_date 					= $last_month_to_instalment;
            $crf->payment_status			= true;
            $crf->range_type 				= $remain_month;
            $crf->save();

            // check for change bill name
            if( $invoice->is_common_fee_bill && $invoice->instalmentLog->count() ) {
                $new_bill = $this->changeStartCfMonthLabel ($new_bill);
            }

        } else {
            $ins->range_type 	= 0;
        }
        $ins->save();

        // Save Bank transfer transaction
        $smart_bill_setting = SmartBill::where('property_id',$new_bill->property_id)->first();
        if( $smart_bill_setting && $smart_bill_setting->property_bank_id) {
            $bt = new BankTransaction;
            $bt->saveBankBillTransaction($new_bill,$smart_bill_setting->property_bank_id);
            $bank = new Bank;
            $bank->updateBalance ($smart_bill_setting->property_bank_id,$new_bill->final_grand_total);
        }

        // remove commom fee ref
        if( $invoice->commonFeesRef()->count() ) {
            $invoice->commonFeesRef()->delete();
        }

        //reject old invoice
        $invoice->payment_status = 4;
        $invoice->save();

        if(!$new_bill->for_external_payer){
            $this->sendTransactionCompleteNotification($new_bill->property_id, $new_bill->property_unit_id,$new_bill->name,$new_bill->id,Auth::user()->id);
        }

        return $new_bill->id;
    }

    public function smartBillHistory () {
        if(Request::isMethod('post') ) {
            
            $history = SmartBillReceiptLog::where('property_id', Auth::user()->property_id);
            if( Request::get('start-created-date') ) {
                $history = $history->where('created_at','>=',Request::get('start-created-date')." 00:00:00");
            }
            if( Request::get('end-created-date') ) {
                $history = $history->where('created_at','<=',Request::get('end-created-date')." 23:59:59");
            }
            if( Request::get('receipt_by') ) {
                $history = $history->where('by_user_id',Request::get('receipt_by'));
            }
            $history = $history->orderBy('created_at','desc')->paginate(20);
            return view('feesbills.smart-bill.smart-history-list')->with(compact('history'));
        }
    }

    public function getReceiptByHistory () {
        if(Request::isMethod('post') ) {
            $hid = Request::get('hid');
            $history = SmartBillReceiptLog::find( $hid );
            $invoices = null;
            if( $history ) {
                $ids = json_decode($history->invoice_ids);
                $invoices = Invoice::whereIn('id', $ids)->get();
            }
            return view('feesbills.smart-bill.smart-receipt-list')->with(compact('invoices','hid'));
        }
    }

    public function downloadReceiptByHistory () {
        if(Request::isMethod('post') ) {
            $officer = null;
            $history = SmartBillReceiptLog::where('property_id', Auth::user()->property_id);
            if( Request::get('start-created-date') ) {
                $history = $history->where('created_at','>=',Request::get('start-created-date')." 00:00:00");
            }
            if( Request::get('end-created-date') ) {
                $history = $history->where('created_at','<=',Request::get('end-created-date')." 23:59:59");
            }
            if( Request::get('receipt_by') ) {
                $history = $history->where('by_user_id',Request::get('receipt_by'));
                $officer = User::find(Request::get('receipt_by'));
            }
            $history = $history->get();
            $invoices = [];
            if( $history ) {
                foreach( $history as $his ) {
                    $ids = json_decode($his->invoice_ids, true);
                    foreach( $ids as $id ) {
                        $invoices[] = $id;
                    }
                }
            }
            $invoices_list = null;
            if( !empty($invoices) ) {
                $invoices_list = Invoice::whereIn('id', $invoices)->orderBy('updated_at', 'desc')->get();
            }
            
            $r = Request::all();
            $filename = trans('messages.Report.receipt_invoice_report');
            $property_name = Property::select('property_name_th','property_name_en')->find(Auth::user()->property_id);

            return view('feesbills.smart-bill.receipt-export')->with(compact('invoices_list','filename','r','property_name','officer'));
        }
    }

    public function getInvoices () {
        if(Request::isMethod('post') ) {
            $ids =  Request::get('list-bill');
            $ids = explode(',',$ids);
            $invoices = Invoice::whereIn('id', $ids)->orderBy('updated_at', 'desc')->get();
            return view('feesbills.smart-bill.invoice-review')->with(compact('invoices'));
        }
    }
}
