<?php

namespace App\Http\Controllers\Officer;

use Request;
use Illuminate\Routing\Controller;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\GeneralFeesBillsController;
use App\Http\Controllers\PropertyAdmin\FeesBillsController;
use App;
use DB;
use Carbon\Carbon;

use App\Invoice;
use App\Transaction;
use App\Property;
use App\PropertyUnit;
use App\CommonFeesRef;
use App\Bank;
use App\BankTransaction;
use App\ReceiptInvoiceAggregate;
use App\Vehicle;
use App\InvoiceFile;
use App\PropertyFeature;


class AggregateInvoiceController extends GeneralFeesBillsController
{

    public function __construct () {
        $this->middleware('auth:menu_finance_group');
        view()->share('active_menu', 'bill');
        if(Auth::check() && !in_array(Auth::user()->role,[1,3])) Redirect::to('feed')->send();
    }

    public function invoiceList (Request $form) {

        $debt = 0;
        if($form::isMethod('post') && $form::get('invoice-unit_id') != "-") {

            $bills = Invoice::where('property_id','=',Auth::user()->property_id)
                ->where('is_retroactive_record',false)
                ->where('is_revenue_record',false)->whereNotIn('payment_status',[2,5])
                ->whereIn('payment_status',[0,1]);

            $bid = $form::get('invoice-unit_id');
            $bills->whereHas('property_unit', function ($query) use ($bid) {
                $query->where('property_unit_unique_id', $bid);
            });

            $feesBills = new FeesBillsController();
            $debt = $feesBills->getPropertyUnitDebt($form::get('invoice-unit_id'));

            if(!empty($form::get('start-due-date'))) {
                $bills->where('due_date','>=',$form::get('start-due-date'));
            }

            if(!empty($form::get('end-due-date'))) {
                $bills->where('due_date','<=',$form::get('end-due-date'));
            }

            $bills = $bills->where('type',1)->orderBy('invoice_no_label','desc')->get();
        } else {
            $bills = Invoice::where('id',null)->get();
        }


        if(!$form::ajax()) {
            $unit_list = array('-'=> trans('messages.unit_no'));
            $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','property_unit_unique_id')->toArray();
            return view('feesbills.aggregate.admin-invoice-list')->with(compact('bills','unit_list','debt'));
        } else {
            return view('feesbills.aggregate.admin-invoice-list-element')->with(compact('bills','debt'));
        }

    }


    public function aggregateInvoice(Request $r) {

        if( $r::isMethod('post')) {
            parse_str($r::get('targets'),$op );
            $invoices = Invoice::with('transaction')->whereIn('id',$op['bills'])->orderBy('invoice_no_label','asc')->get();
            $sample_iv = $invoices->first();
            $sample_iv->load('property_unit');
            $p_unit = $sample_iv->property_unit;
            $cf_start = $cf_end = [];
            $discount = $sub_from_balance = 0;
            if($invoices) {

                foreach ($invoices as $iv) {
                    // get invoice no label
                    $iv_list[]      = $iv->invoice_no_label;
                    $iv_list_id[]   = $iv->id;
                    $discount           += $iv->discount;
                    $sub_from_balance   += $iv->sub_from_balance;

                    //get common fee ref
                    if($iv->is_common_fee_bill && $iv->commonFeesRef) {
                       $cf_start[]  = $iv->commonFeesRef->from_date;
                       $cf_end[]    = $iv->commonFeesRef->to_date;
                    }
                    foreach ($iv->transaction as $t) {
                        $trans[] = $t;
                    }
                }
                $iv_ref = "รายการหมายเลขอ้างอิงใบแจ้งหนี้ : " . implode(', ',$iv_list);

                // set common fee variable
                if(count($cf_start) && count($cf_end) ) {
                    usort($cf_start, "date_sort");
                    usort($cf_end, "date_sort");
                    $date = explode('-',$cf_start[0]);
                    $cf_from_month  = $date[1];
                    $cf_from_year   = $date[0];

                    $date_from  = new Carbon($cf_start[0]);
                    $date_to    = new Carbon(array_pop($cf_end));

                    $diff_cf_month = $date_from->diffInMonths($date_to) + 1;
                    $is_cf_iv = true;
                } else {
                    $is_cf_iv = false;
                    $cf_from_month  = date('m');
                    $cf_from_year   = date('Y');
                    $diff_cf_month = 1;
                }
                $bank = new Bank;
                $bank_list = $bank->getBankList();

                $iv_list_id = json_encode($iv_list_id);
                return view('feesbills.aggregate.create-receipt-form')->with(compact('p_unit','bank_list','trans','iv_ref',
                    'diff_cf_month','cf_from_month','cf_from_year','is_cf_iv','discount','iv_list_id','sub_from_balance'));

            } else {
                return redirect('admin/fees-bills/invoice/aggregate');
            }
        }
    }

    public function saveAggregateInvoice (Request $r) {

        $property 	= Property::find(Auth::user()->property_id);
        $unit       = PropertyUnit::find($r::get('property_unit_id'));
        $discount   = str_replace(',', '', Request::get('discount'));
        // For unit
        $receipt = new Invoice;
        $receipt->fill(Request::all());
        $receipt->type 				= 1;
        $receipt->property_id 		= Auth::user()->property_id;
        $receipt->property_unit_id 	= $unit->id;
        $receipt->receipt_no 		= ++$property->receipt_counter;
        $receipt->transfer_only 	= false;
        $receipt->discount 			= $discount;
        $receipt->payment_status	= 2;
        $receipt->final_grand_total     = $receipt->grand_total;
        $receipt->is_aggregate_receipt  = true;
        $receipt->created_by        = Auth::user()->id;

        $month = Carbon::now()->month;
        $year = Carbon::now()->year;
        $date_period = $year.$month;
        $this->getMonthlyCounterDoc($date_period,Auth::user()->property_id);
        $receipt_no_label = $this->generateRunningLabel('RECEIPT', null, null, Auth::user()->property_id);
        $receipt->receipt_no_label = $receipt_no_label;

        if($receipt->payment_type == 2) {
            $receipt->bank_transfer_date = $receipt->payment_date;
            $receipt->transfered_to_bank = true;
        }

        $receipt->save();

        // Increase monthlyCounterDoc
        $this->increaseMonthlyCounterDocByPeriod($date_period,'RECEIPT',Auth::user()->property_id);
        // End Generate Running Number

        $trans = [];
        foreach (Request::get('transaction') as $t) {
            $total = str_replace(',', '', $t['total']);
            if(empty($t['sub_from_balance'])) $t['sub_from_balance'] = 0;
            if(empty($t['sub_from_discount'])) $t['sub_from_discount'] = 0;
            $trans[] = new Transaction([
                'detail' 	=> $t['detail'],
                'quantity' 	=> str_replace(',', '', $t['quantity']),
                'price' 	=> str_replace(',', '', $t['price']),
                'total' 	=> $total,
                'transaction_type' 	=> $receipt->type,
                'property_id' 		=> Auth::user()->property_id,
                'property_unit_id' 	=> $unit->id,
                'category' 			=> $t['category'],
                'due_date'			=> $receipt->due_date,
                'payment_date'		=> $receipt->payment_date,
                'submit_date'		=> $receipt->payment_date,
                'payment_status' 	=> true,
                'bank_transfer_date'=> $receipt->bank_transfer_date,
                'sub_from_balance'  => $t['sub_from_balance'],
                'sub_from_discount' => $t['sub_from_discount']
            ]);
        }
        // Save discount transaction
        if($receipt->discount > 0) {
            $trans[] = new Transaction([
                'detail' 	=> 'discount',
                'quantity' 	=> 1,
                'price' 	=> $discount,
                'total' 	=> $discount,
                'transaction_type' 	=> 3,
                'property_id' 		=> Auth::user()->property_id,
                'property_unit_id' 	=> $unit->id,
                'due_date'			=> $receipt->due_date,
                'payment_date'		=> $receipt->payment_date,
                'submit_date'		=> $receipt->payment_date,
                'payment_status' 	=> true
            ]);
        }
        $receipt->transaction()->saveMany($trans);

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
            $receipt->invoiceFile()->saveMany($attach);
        }

        if($r::get('is_cf_invoice')) {
            $from_date 		= Request::get('from_year')."-".Request::get('from_month').'-01';
            $to_date 		= date('Y-m-t',strtotime(Request::get('to_date')));
            //save common fee reference table
            $crf = new CommonFeesRef();
            $crf->invoice_id				= $receipt->id;
            $crf->property_id				= $property->id;
            $crf->property_unit_id 			= $r::get('property_unit_id');
            $crf->property_unit_unique_id 	= $unit->property_unit_unique_id;
            $crf->from_date					= $from_date;
            $crf->to_date 					= $to_date;
            $crf->payment_status			= true;
            $crf->range_type 				= Request::get('range');
            $crf->save();
        }

        // Save Counter
        $property->save();
        // Save Bank transfer transaction
        if($receipt->payment_type == 2) {
            $bt = new BankTransaction;
            $bt->saveBankRevenueTransaction($receipt,Request::get('bank_id'));
            $bank = new Bank;
            $bank->updateBalance (Request::get('bank_id'),$receipt->final_grand_total);
        }

        // Save aggregated invoice
        $agg_invoices = json_decode($r::get('invoice_id'));
        $agg_invoices = Invoice::whereIn('id',$agg_invoices)->get();
        foreach ($agg_invoices as $inv) {
            $agg_in[] = new ReceiptInvoiceAggregate([
                'invoice_id' => $inv->id,
                'receipt_id' => $receipt->id,
            ]);

            // change sticker status
            $vehicle = Vehicle::where('invoice_id',$inv->id)->first();
            if($vehicle) {
                $vehicle->sticker_status = 3;
                $vehicle->invoice_id = null;
                $vehicle->save();
            }

            // Aggregated invoice // instalment completed invoice
            $inv->payment_status = 6;
            $inv->save();

            foreach ($inv->transaction as $t) {
                $t->is_rejected = true;
                $t->save();
            }
        }

        $receipt->receiptInvoiceAggregate()->saveMany($agg_in);

        Request::session()->flash('class', 'success');
        Request::session()->flash('message', trans('messages.feesBills.Aggregate.aggregate_complete',['l' => $receipt->receipt_no_label]));


        return redirect('admin/fees-bills/invoice/aggregate');
    }

    public function aggregateInvoicePrint (Request $r) {

        $bills   = Invoice::with('transaction')->whereIn('id',$r::get('invoices'))->orderBy('invoice_no_label','asc')->get();
        $print_manuscript = false;

        $feature = PropertyFeature::where('property_id',Auth::user()->property_id)->first();
        if($feature->preprint_invoice) {
            $print_type = 1;
            return view('feesbills.print-view.preprint.admin-invoice-print')->with(compact('bills','print_type','feature'));
        } else {
            return view('feesbills.aggregate.admin-invoice-log-print')->with(compact('bills','print_manuscript'));
        }

        //return view('feesbills.aggregate.admin-invoice-log-print')->with(compact('bills','print_manuscript'));
    }
}
