<?php

namespace App\Http\Controllers\RootAdmin;

use Auth;
use Redirect;
use App\Bank;
use App\CommonFeesRef;
use Illuminate\Http\Request;
use DB;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Invoice;
use App\Transaction;
use App\BankTransaction;
use App\InvoiceInstalmentLog;
use App\BillElectric;
use App\BillWater;
use App\PropertyUnit;
use App\Vehicle;
use App\Property;
use App\MonthlyCounterDoc;
use Carbon\Carbon;
use App\PropertyUnitBalanceLog;

class feesBillsController extends Controller
{
    public function __construct () {
        $this->middleware('auth',['except' => ['login']]);
        if( Auth::check() && Auth::user()->role !== 0 ) {
            if(Auth::user()->role !== 5) {
                Redirect::to('feed')->send();
            }
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('root-admin.receipt.receipt-form');
    }


    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $r)
    {
        if ($r->get('id')) {
            $receipt = Invoice::with('property')
                ->where('type', 1)
                ->where('payment_status', 2)
                ->where('id', $r->get('id'))->first();
            //dd($receipt);
            if ($receipt) {
                if ($receipt->property_unit)
                    $receipt->load('property_unit');
                if ($receipt->payment_type == 1) $receipt->payment_label = trans('messages.feesBills.cash');
                elseif ($receipt->payment_type == 2) $receipt->payment_label = trans('messages.feesBills.transfer');
                else $receipt->payment_label = trans('messages.feesBills.substract');

                if ($receipt->is_retroactive_record)
                    $receipt->receipt_type = "บันทึกรายรับ";
                else if ($receipt->is_revenue_record)
                    $receipt->receipt_type = "บันทึกรายรับยกมา";
                else $receipt->receipt_type = "ใบเสร็จทั่วไป";

                if ($receipt->property_unit) {
                    $receipt->to = $receipt->property_unit->unit_number;
                } else {
                    $receipt->to = $receipt->payer_name;
                }

                if ($receipt->receipt_no_label)
                    $receipt->receipt_no = $receipt->receipt_no_label;
                //else $receipt->receipt_no = $receipt->receipt_no_label;

                //if($receipt->receipt_no_lable == null) {
                //$receipt->receipt_no_label = NB_RECEIPT.invoiceNumber($receipt->receipt_no);
                //}

                $data['result'] = true;
                $data['data'] = $receipt->toArray();
            } else {
                $data['result'] = false;
                $data['message'] = 'ไม่พบใบเสร็จรับเงินหรือ id ไม่ตรงกับประเภทใบเสร็จ';
            }
        } else {
            $data['result'] = false;
            $data['message'] = 'ไม่พบใบเสร็จรับเงิน';
        }


        return response()->json($data);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $r)
    {
        $receipt = Invoice::with('property', 'transaction', 'commonFeesRef', 'instalmentLog', 'invoiceFile', 'requester','bankTransaction')
            ->where('type', 1)
            ->where('payment_status', 2)
            ->where('id', $r->get('rid'))->first();
        $delete_receipt_flag = ($r->get('action') == 'remove') ? true : false;
        if ($receipt) {
            $flag_instalment = false;
            $instalments = InvoiceInstalmentLog::with('fromInvoice')->where('to_receipt_id', $receipt->id)->first();
            if ($instalments) {
                $status = $instalments->fromInvoice->payment_status;
                if ($status == 0 || $status == 1) {
                    $flag_instalment = true;
                }
            }
            // check instalment
            if (!$flag_instalment) {
                if ($receipt->transfered_to_bank) {
                    // update bank balance
                    $bank_transaction = BankTransaction::with('getBank')->where('invoice_id', $receipt->id)->first();

                    if ($bank_transaction) {
                        $bank = $bank_transaction->getBank;
                        $bank->timestamps = false;
                        $bank->balance -= $receipt->final_grand_total;
                        $bank->save();
                        $bank_transaction->delete();
                    }

                    // add property unit prepaid balance back from receipt
                    if ($receipt->sub_from_balance > 0) {
                        $property_unit = PropertyUnit::find($receipt->property_unit_id);
                        if ($receipt->is_common_fee_bill)
                            $property_unit->cf_balance += $receipt->sub_from_balance;
                        else
                            $property_unit->balance += $receipt->sub_from_balance;
                        $property_unit->save();
                    }

                    //check paid over common fee
                    $paid_over = Transaction::where('invoice_id', $receipt->id)->where('category', 19)->first();
                    if ($paid_over) {
                        $property_unit = PropertyUnit::find($receipt->property_unit_id);
                        $property_unit->cf_balance -= $paid_over->total;
                        $property_unit->save();
                    }

                    // reset vehicle bill
                    $vehicle = Vehicle::where('invoice_id', $receipt->id)->first();
                    if (isset($vehicle)) {
                        if ($delete_receipt_flag) {
                            $vehicle->sticker_status = 0;
                            $vehicle->invoice_id = null;
                        } else {
                            $vehicle->sticker_status = 1;
                        }
                        $vehicle->save();
                    }
                }

                if (!$receipt->is_revenue_record) {

                    if ($r->get('re_counter')) {
                        // if $receipt isn't revenue record receipt (รายรับยกมา)
                        // decrement counter

                        $property = $receipt->property;
                        if($property->receipt_counter > 0) {
                            $property->decrement('receipt_counter');
                        }

                        //update receipt no for following receipt
                        if($receipt->receipt_no) {
                            $following_receipts = Invoice::where('property_id', $receipt->property_id)
                                ->where('type', 1)
                                ->where('payment_status', 2)
                                ->where('receipt_no', '>', $receipt->receipt_no)
                                ->get();

                            //decrement receipt no of following receipt
                            if ($following_receipts->count()) {
                                foreach ($following_receipts as $f_receipt) {
                                    $f_receipt->timestamps = false;
                                    $f_receipt->decrement('receipt_no');
                                }
                            }
                        }
                        // update receipt_no_label for following receipt
                        $this->forceReRunningReceiptNumber($r->get('rid'));
                    }

                    // Re-Status if this bill is Water/Electric Bill
                    $bill_electric = BillElectric::where('invoice_id', $receipt->id)->first();
                    if (isset($bill_electric)) {
                        if ($delete_receipt_flag) {
                            $bill_electric->invoice_id = null;
                        }
                        $bill_electric->status = 0;
                        $bill_electric->save();
                    }

                    $bill_water = BillWater::where('invoice_id', $receipt->id)->first();
                    if (isset($bill_water)) {
                        if ($delete_receipt_flag) {
                            $bill_water->invoice_id = null;
                        }
                        $bill_water->status = 0;
                        $bill_water->save();
                    }
                }

                if ($delete_receipt_flag) {
                    // delete related relation
                    $receipt->transaction()->delete();
                    if( $receipt->commonFeesRef )
                        $receipt->commonFeesRef()->delete();
                    if( $receipt->invoiceFile )
                        $receipt->invoiceFile()->delete();
                    if( $receipt->requester )
                        $receipt->requester()->delete();
                    if( $receipt->instalmentLog )
                        $receipt->instalmentLog()->delete();
                    if( $receipt->bankTransaction )
                        $receipt->bankTransaction()->delete();
                    $receipt->delete();
                } else {
                    $transactions = $receipt->transaction;

                    foreach ($transactions as $t) {
                        $t->timestamps = false;
                        $t->payment_status = false;
                        $t->payment_date = null;
                        $t->bank_transfer_date = null;
                        $t->save();
                    }
                    if( $receipt->commonFeesRef ) {
                        // re-status common fee ref
                        $receipt->commonFeesRef->payment_status = false;
                        $receipt->commonFeesRef->save();
                    }

                    // reset payment status
                    $receipt->payment_type   = null;
                    $receipt->payment_status = 0;
                    $receipt->payment_date   = null;
                    $receipt->submit_date    = null;
                    $receipt->bank_transfer_date = null;
                    $receipt->receipt_no_label   = null;

                    $receipt->save();
                }

                if ($delete_receipt_flag) {
                    $msg = 'ลบใบเสร็จเรียบร้อยแล้ว';
                } else {
                    $msg = "คืนสถานะใบเสร็จเรียบร้อยแล้ว";
                }
                $class = 'success';

            } else {
                $msg = 'ใบแจ้งหนี้ที่มีใบเสร็จนี้เป็นประวัติการผ่อนชำระยังไมได้รับการยกเลิก กรุณายกเลิกใบแจ้งหนี้ก่อนเพื่อป้องกันความผิดพลาดในการแสดงจำนวนยอดเงินคงเหลือในใบแจ้งหนี้';
                $class = 'danger';
            }
        } else {
            $msg = 'ไม่พบใบเสร็จรับเงินหรือ id ไม่ตรงกับประเภทใบเสร็จ';
            $class = 'danger';
        }

        $r->session()->flash('class', $class);
        $r->session()->flash('message', $msg);
        return view('root-admin.receipt.receipt-form');

    }

    // Force function Duplicate Invoice_no to Invoice_no_label
    function forceDuplicateInvoiceNoLabel($property_id)
    {
        if ($property_id == 'ff4055ef-b800-43fb-8f63-a0087fa9ec04') {
            $property = Property::find($property_id);
            DB::table('invoice')->where('property_id', $property->id)->where('invoice_no_label', null)->orderBy('created_at')->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    //
                    $update_invoice = Invoice::find($invoice->id);
                    $running_no = str_pad($update_invoice->invoice_no, 5, '0', STR_PAD_LEFT);
                    $custom_label = "NBH.IV" . "60" . "10" . $running_no;
                    $update_invoice->invoice_no_label = $custom_label;

                    if ($update_invoice->receipt_no != null) {
                        $running_no_receipt = str_pad($update_invoice->receipt_no, 5, '0', STR_PAD_LEFT);
                        $custom_label_receipt = "NBH.RE" . "60" . "10" . $running_no_receipt;
                        $update_invoice->receipt_no_label = $custom_label_receipt;
                    }

                    if ($update_invoice->expense_no != null) {
                        $running_no_receipt = str_pad($update_invoice->expense_no, 5, '0', STR_PAD_LEFT);
                        $custom_label_expense = "NBH.EX" . "60" . "10" . $running_no_receipt;
                        $update_invoice->expense_no_label = $custom_label_expense;
                    }

                    $update_invoice->save();
                }
            });
        } else {
            DB::table('invoice')->where('invoice_no_label', null)->orderBy('created_at')->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    //
                    $update_invoice = Invoice::find($invoice->id);
                    $running_no = str_pad($update_invoice->invoice_no, 8, '0', STR_PAD_LEFT);
                    $custom_label = "NBI" . $running_no;
                    $update_invoice->invoice_no_label = $custom_label;

                    if ($update_invoice->receipt_no != null) {
                        $running_no_receipt = str_pad($update_invoice->receipt_no, 8, '0', STR_PAD_LEFT);
                        $custom_label_receipt = "NBR" . $running_no_receipt;
                        $update_invoice->receipt_no_label = $custom_label_receipt;
                    }

                    if ($update_invoice->expense_no != null) {
                        $running_no_expense = str_pad($update_invoice->expense_no, 8, '0', STR_PAD_LEFT);
                        $custom_label_expense = "NBE" . $running_no_expense;
                        $update_invoice->expense_no_label = $custom_label_expense;
                    }
                    $update_invoice->save();
                }
            });

            DB::table('invoice_instalment_log')->where('receipt_no_label', null)->orderBy('created_at')->chunk(1000, function ($invoice_instalment_logs) {
                foreach ($invoice_instalment_logs as $instalment_log) {
                    //
                    $update_invoice = InvoiceInstalmentLog::find($instalment_log->id);
                    $running_no = str_pad($update_invoice->receipt_no, 8, '0', STR_PAD_LEFT);
                    $custom_label_receipt = "NBR" . $running_no;
                    $update_invoice->receipt_no_label = $custom_label_receipt;

                    $update_invoice->save();
                }
            });
        }

        return "true";
    }

    public function forceReRunningReceiptNumber($invoice_id)
    {
        $receipt = Invoice::find($invoice_id);
        $period_of_receipt = $receipt->created_at->format('Ym');

        $format_running_del = substr($receipt->receipt_no_label, 0, -5);
        $receipt_all_in_period = Invoice::where('property_id', $receipt->property_id)->where('receipt_no_label', 'like', $format_running_del . '%')->orderBy('receipt_no_label', 'desc')->get();

        $array_receipt_in_period_arr = $receipt_all_in_period->toArray();
        foreach ($array_receipt_in_period_arr as $item) {
            if ($item['receipt_no_label'] != $receipt->receipt_no_label) {
                $receipt_edit = Invoice::find($item['id']);
                $running_edit = substr($receipt_edit->receipt_no_label, -5);
                $running_to_del_num = intval($running_edit); // ex: 6
                $running_decrease = $running_to_del_num - 1;
                $running_no = str_pad($running_decrease, 5, '0', STR_PAD_LEFT);
                $custom_label = $format_running_del . $running_no;
                $receipt_edit->receipt_no_label = $custom_label;
                $receipt_edit->timestamps = false;
                $receipt_edit->save();
            } else {
                break;
            }
        }

        $monthly_counter_doc = MonthlyCounterDoc::where('date_period', $period_of_receipt)->where('property_id', $receipt->property_id)->first();
        if (!isset($monthly_counter_doc)) {
            // ไม่มี period ใน table monthly_counter_doc
        } else {
            $monthly_counter_doc_update = MonthlyCounterDoc::find($monthly_counter_doc->id);
            $monthly_counter_doc_update->receipt_counter = $monthly_counter_doc->receipt_counter - 1;
            $monthly_counter_doc_update->save();
        }

        return true;
    }

    public function importReceipt($property_id)
    {
        $property = Property::find($property_id);
        return view('root-admin.receipt.import-receipt')->with('property', $property);
    }

    public function startImportReceipt(Request $r)
    {
        if ($r->isMethod('post')) {
            $lines = explode(PHP_EOL, $r->get('data'));
            $property = Property::find($r->get('id'));
            $remark = $property->settings->common_fee_footer;

            foreach ($lines as $line) {
                $array_invoice[] = str_getcsv($line);
            }

            $array_result = $this->checkAndMakeGrouping($array_invoice, $property);

            if ($array_result['valid']) {

                $array_invoice = $array_result['result'];

                foreach ($array_invoice as $key => $bill) {
                        $receipt = new Invoice;
                        $receipt->timestamps        = false;
                        $receipt->type              = 1;
                        $receipt->name              = $bill['name'];
                        $receipt->due_date          = $bill['due_date'];
                        $receipt->created_at        = $bill['create_date'];
                        $receipt->updated_at        = $receipt->created_at;
                        $receipt->payment_status    = ($bill['payment_status']) ? 2 : 0;
                        if( $receipt->payment_status )
                        $receipt->from_imported     = true;

                        $receipt->property_id = $r->get('id');
                        if($bill['unit_id']) {
                            $receipt->property_unit_id  = $bill['unit_id'];
                            $receipt->for_external_payer = false;
                        } else {
                            $receipt->for_external_payer = true;
                            $receipt->payer_name = $bill['unit_address'];
                        }

                        $receipt->is_common_fee_bill = (!empty($bill['is_cf_invoice'])) ? true : false;

                        $receipt->total =
                        $receipt->final_grand_total = $receipt->grand_total =  $bill['grand_total'];

                        if ($bill['payment_status']) {
                            $receipt->updated_at    = $bill['payment_date'];
                            $receipt->payment_date  = $receipt->updated_at;
                            $receipt->payment_type  = $bill['payment_type'];

                            if ($receipt->payment_type == 2) {
                                $receipt->transfered_to_bank = true;
                                $receipt->bank_transfer_date = $receipt->payment_date;
                            }
                        }

                        if ( !$bill['payment_status'] ) {
                            $receipt->invoice_no_label = $key;
                        } else {
                            if( !empty($bill['from_invoice']) && count($bill['from_invoice']) == 1) {
                                $receipt->invoice_no_label = $bill['from_invoice'][0];
                            }
                        }
                        $receipt->receipt_no_label = $bill['receipt_no'];
                        if($receipt->is_common_fee_bill) {
                            $receipt->remark = $remark;
                        }

                        if (!empty($bill['paid_over_from_last_receipt'])) {
                            $receipt->sub_from_balance = $bill['paid_over_from_last_receipt'];
                            $receipt->total = $bill['total'];
                        }

                        if( $receipt->payment_status ) {
                            if(!empty($bill['from_invoice'])) {
                                if($receipt->remark) $receipt->remark = "";
                                $receipt->remark .= "\r\n*".implode(', ',$bill['from_invoice']);
                            } else {
                                $receipt->is_retroactive_record = true;
                            }
                        }

                        $receipt->save();

                        foreach ($bill['data'] as $t) {
                            $transaction = new Transaction;
                            $receipt->timestamps            = false;
                            if( !$transaction->for_external_payer )
                            $transaction->property_unit_id  = $receipt->property_unit_id;

                            $transaction->detail            = $t[11];
                            $transaction->transaction_type  = 1;
                            $transaction->total             = $t['total'];
                            $transaction->created_at        = $receipt->created_at;
                            $transaction->updated_at        = $receipt->updated_at;
                            $transaction->invoice_id        = $receipt->id;
                            $transaction->property_id       = $receipt->property_id;
                            $transaction->payment_status    = $bill['payment_status'];
                            $transaction->quantity          = str_replace(',', '', trim($t[2]));
                            $transaction->price             = str_replace(',', '', trim($t[3]));
                            $transaction->category          = $t[8];
                            $transaction->payment_date      = $receipt->payment_date;
                            $transaction->due_date          = $receipt->due_date;
                            $transaction->bank_transfer_date = $receipt->bank_transfer_date;
                            $transaction->for_external_payer = $receipt->for_external_payer;

                            if($receipt->sub_from_balance && $transaction->category == 1) {
                                $transaction->sub_from_balance = $receipt->sub_from_balance;
                            }
                            $transaction->save();
                        }

                        // Update unit common fee balance
                        $save_log_flag = false;
                        if ($receipt->is_common_fee_bill && $bill['unit_id'] != '') {
                            $unit = PropertyUnit::find(trim($bill['unit_id']));
                            $cmf_ref = new CommonFeesRef;
                            $cmf_ref->invoice_id            = $receipt->id;
                            $cmf_ref->property_id           = $receipt->property_id;
                            $cmf_ref->property_unit_id      = $receipt->property_unit_id;
                            $cmf_ref->from_date             = $bill['from'];
                            $cmf_ref->to_date               = $bill['to'];
                            $cmf_ref->payment_status        = $bill['payment_status'];
                            $cmf_ref->created_at            = $receipt->created_at;
                            $cmf_ref->updated_at            = $receipt->updated_at;
                            $cmf_ref->range_type            = $bill['month_length'];
                            $cmf_ref->property_unit_unique_id = $unit->property_unit_unique_id;
                            $cmf_ref->save();

                            if (!empty($bill['paid_over'])) {
                                $cf_balance = $bill['paid_over'];
                                $save_log_flag = true;
                                // save paid over transaction
                                $transaction = new Transaction;
                                $receipt->timestamps            = false;
                                $transaction->property_unit_id  = $receipt->property_unit_id;
                                $transaction->detail            = trans('messages.feesBills.paid_over_common_fee');
                                $transaction->transaction_type  = 1;
                                $transaction->total             = $cf_balance;
                                $transaction->created_at        = $receipt->created_at . " 00:00:01";
                                $transaction->updated_at        = $receipt->updated_at;
                                $transaction->invoice_id        = $receipt->id;
                                $transaction->property_id       = $receipt->property_id;
                                $transaction->payment_status    = $bill['payment_status'];
                                $transaction->quantity          = 1;
                                $transaction->price             = $cf_balance;
                                $transaction->category          = 19;
                                $transaction->payment_date      = $receipt->payment_date;
                                $transaction->due_date          = $receipt->due_date;
                                $transaction->bank_transfer_date = $receipt->bank_transfer_date;
                                $transaction->save();

                            } elseif (!empty($bill['short_payment'])) {
                                $cf_balance = -abs($bill['short_payment']);
                                $save_log_flag = true;
                            }

                        }

                        if ($bill['payment_status'] && $receipt->payment_type == 2) {

                            $bank = $array_result['bank'][trim($bill['transfer_to'])];
                            $bank_transaction = new BankTransaction;
                            if( !$receipt->for_external_payer )
                            $bank_transaction->property_unit_id  = $receipt->property_unit_id;
                            $bank_transaction->property_id      = $receipt->property_id;
                            $bank_transaction->invoice_id       = $receipt->id;
                            // TODO::check bank transfer date when payment type in bank transfer
                            $bank_transaction->bank_id          = $bank->id;
                            $bank_transaction->get              = $receipt->final_grand_total;
                            $bank_transaction->transfer_date    = $receipt->payment_date;
                            $bank_transaction->bill_type        = 'b';
                            $bank_transaction->created_at       = $receipt->created_at;
                            $bank_transaction->updated_at       = $receipt->updated_at;
                            $bank_transaction->save();

                            // Update bank balance
                            $bank->balance += $bank_transaction->get;
                            $bank->save();
                        }

                        if ($save_log_flag) {
                            $balance_log = new PropertyUnitBalanceLog;
                            $balance_log->cf_balance        = $cf_balance;
                            $balance_log->property_id       = $unit->property_id;
                            $balance_log->property_unit_id  = $unit->id;
                            $balance_log->invoice_id        = $receipt->id;
                            $balance_log->save();
                        }
                }

                /*if ($array_result['counter']) {
                    foreach ($array_result['counter'] as $date => $count) {
                        $counter = new MonthlyCounterDoc;
                        $counter->property_id = $property->id;
                        $counter->date_period = $date;
                        if (!empty($count['receipt'])) {
                            $counter->receipt_counter = $count['receipt'];
                        }

                        if (!empty($count['invoice'])) {
                            $counter->invoice_counter = $count['invoice'];
                        }
                        $counter->save();
                    }
                } */
                return response()->json([
                    'result' => true,
                    'message' => 'นำเข้าข้อมูลเสร็จสมบูรณ์'
                ]);
            } else {
                return response()->json([
                    'result' => false,
                    'message' => 'ไม่สามารถนำเข้าข้อมูล เนื่องจาก <br/>' . $array_result['error']
                ]);
            }
        }
    }

    public function checkAndMakeGrouping($array_bills, $property)
    {
        $valid      = true;
        $message    = "";
        $temp       = $temp_counter  = $temp_bank_id = $temp_bank = array();

        foreach ($array_bills as $key => $bill) {
            // if paid
            if($bill[22]) {
                $bill_key = $bill[7];
                if(!empty(trim($bill[6])))
                $temp[$bill_key]['from_invoice'][] = $bill[6];
            } else {
                $bill_key = $bill[6];
            }
            $temp[$bill_key]['payment_status']   = $bill[22];
            $temp[$bill_key]['receipt_no']       = $bill[7];

            if(empty($temp[$bill_key]['unit_id']))
                $temp[$bill_key]['unit_id']      = $bill[0];

            if(empty($temp[$bill_key]['unit_address']))
                $temp[$bill_key]['unit_address'] = $bill[1];

            if(empty($temp[$bill_key]['due_date']))
                $temp[$bill_key]['due_date']     = $bill[13];

            if(empty($temp[$bill_key]['create_date']))
                $temp[$bill_key]['create_date']  = $bill[12];

            if (empty($temp[$bill_key]['is_cf_invoice'])) {
                $temp[$bill_key]['is_cf_invoice'] = false;
            }

            if ($temp[$bill_key]['payment_status']) {

                //------------- Start validation code -------------
                //  - check bank account isn't an empty field
                //  - check if existed bank account in property

                // payment type
                if ($bill[23] == 2) {
                    if (empty($bill[24])) {
                        $message .= "ข้อมูล row " . ($key + 1) . " - เลขที่บัญชีธนาคารไม่ถูกต้อง<br/>";
                        $valid = false;
                    } else {
                        if(!in_array($bill[24],$temp_bank)) {
                            $bank = Bank::where('property_id', $property->id)->where('account_number', '=', trim($bill[24]))->first();
                            if (!$bank) {
                                $message .= "ข้อมูล row " . ($key + 1) . " - เลขที่บัญชีธนาคารไม่ถูกต้อง ไม่มีอยู่ในระบบ<br/>";
                                $valid = false;
                            } else {
                                $temp_bank[$bill[24]] = $bank;
                            }
                            $temp_bank_id[] = trim($bill[24]);
                        }
                    }

                    $temp[$bill_key]['transfer_to'] = trim($bill[24]);
                }

                 //  - check payment date for it's not an empty field
                if (empty($bill[14])) {
                    $message .= "ข้อมูล row " . ($key + 1) . " - วันที่ออกใบเสร็จไม่ถูกต้อง<br/>";
                    $valid = false;
                }
                //------------- End validation code -------------

                if($valid) {
                    if(empty($temp[$bill_key]['payment_date']))
                        $temp[$bill_key]['payment_date'] = $bill[14];

                    if(empty($temp[$bill_key]['name']))
                        $temp[$bill_key]['name']         = $bill[10];

                    if(empty($temp[$bill_key]['payment_type']))
                        $temp[$bill_key]['payment_type'] = $bill[23];

                    $total                          = str_replace(',', '', trim($bill[5]));

                    /// grouping counter by year amd month
                    $created_date = date('Ym', strtotime($bill[14]));
                    if (empty($temp_counter[$created_date]['receipt'])) $temp_counter[$created_date]['receipt'] = 0;
                    $temp_counter[$created_date]['receipt']++;
                }
            } else {

                if(empty($temp[$bill_key]['name']))
                    $temp[$bill_key]['name'] = $bill[9];

                $total = floatval(str_replace(',', '', trim($bill[4])));

                $created_date = date('Ym', strtotime($bill[12]));
                if (empty($temp_counter[$created_date]['invoice'])) $temp_counter[$created_date]['invoice'] = 0;
                $temp_counter[$created_date]['invoice']++;
            }
            // is common fee transaction
            if ($bill[8] == 1  && !empty(trim($bill[0]))) {
                //  - check start and end date for common fee
                if(empty(trim($bill[15])) || empty(trim($bill[16]))) {
                    $message .= "ข้อมูล row " . ($key + 1) . " - เริ่มหรือสิ้นสุดค่าส่วนกลางไม่ถูกต้อง<br/>";
                    $valid = false;
                }

                if($valid) {
                    $temp[$bill_key]['is_cf_invoice']    = true;
                    $temp[$bill_key]['from']             = date('Y-m-01', strtotime($bill[15]));
                    $temp[$bill_key]['to']               = date('Y-m-t', strtotime($bill[16] . '-01'));
                    $temp[$bill_key]['paid_over']        = floatval(str_replace(',', '', trim($bill[19])));
                    $temp[$bill_key]['short_payment']    = floatval(str_replace(',', '', trim($bill[21])));
                    $temp[$bill_key]['month_length']     = $bill[17];

                }
            }

            if( $temp[$bill_key]['payment_status'] ) {
                if(trim($bill[4]) != trim($bill[5])) {
                    $bill[3] = str_replace(',', '', trim($bill[5]));
                    // set unit to 1
                    $bill[2] = 1;
                    // set total equal rate
                    $total = floatval($bill[3]);
                    $bill['total']              = $bill[3];
                } else {
                    $bill[3] = str_replace(',', '', $bill[3]);
                    $bill[2] = str_replace(',', '', $bill[2]);
                    $bill['total']              = str_replace(',', '', trim($bill[4]));
                }
            } else {
                $bill[3] = str_replace(',', '', $bill[3]);
                $bill[2] = str_replace(',', '', $bill[2]);
                $bill['total']              = str_replace(',', '', trim($bill[4]));
            }

            if($valid) {
                //$bill['total']              = floatval($bill[3]) * floatval($bill[2]);
                $temp[$bill_key]['data'][]   = $bill;

                if (empty($temp[$bill_key]['total'])) $temp[$bill_key]['total'] = 0;
                $temp[$bill_key]['total'] += $total;

                if (empty($temp[$bill_key]['grand_total'])) $temp[$bill_key]['grand_total'] = 0;
                $temp[$bill_key]['grand_total'] += $total;
                //$temp[$bill_key]['grand_total'] += $grand_total;
            }
        }

        $result = array('valid' => $valid,'result' => $temp, 'counter' => $temp_counter, 'error' => $message, 'bank' => $temp_bank);
        return $result;
    }

    public function importExpense($property_id)
    {
        $property = Property::find($property_id);
        return view('root-admin.receipt.import-expense')->with('property', $property);
    }

    public function startImportExpense(Request $r)
    {
        if ($r->isMethod('post')) {
            $lines = explode(PHP_EOL, $r->get('data'));
            $property = Property::find($r->get('id'));
            $remark = $property->settings->common_fee_footer;

            foreach ($lines as $line) {
                $array_invoice[] = str_getcsv($line);
            }

            $array_result = $this->checkAndMakeExpenseGrouping($array_invoice, $property);

            if ($array_result['valid']) {

                $array_invoice = $array_result['result'];

                foreach ($array_invoice as $key => $bill) {

                    $receipt = new Invoice;
                    $receipt->timestamps        = false;
                    $receipt->type              = 2;
                    $receipt->name              = $bill['name'];
                    $receipt->created_at        = $bill['create_date'];
                    $receipt->updated_at        = $receipt->created_at;
                    $receipt->payment_date      = $receipt->updated_at;
                    $receipt->payment_status    = 1;
                    $receipt->from_imported     = true;
                    $receipt->property_id       = $r->get('id');
                    $receipt->payee_id          = $bill['payee_id'];
                    $receipt->for_external_payer    = false;
                    $receipt->payment_type      = $bill['payment_type'];
                    $receipt->remark            = $bill['remark'];
                    $receipt->ref_no            = $bill['ref_no'];

                    $receipt->total =
                    $receipt->final_grand_total = $receipt->grand_total =  $bill['grand_total'];

                    if ($receipt->payment_type == 2) {
                        $receipt->transfered_to_bank = true;
                        $receipt->bank_transfer_date = $receipt->payment_date;
                    }
                    $receipt->expense_no_label = $key;
                    $receipt->save();

                    foreach ($bill['data'] as $t) {
                        $transaction = new Transaction;
                        $receipt->timestamps            = false;
                        $transaction->detail            = $t[5];
                        $transaction->transaction_type  = 2;
                        $transaction->total             = $t[4];
                        $transaction->created_at        = $receipt->created_at;
                        $transaction->updated_at        = $receipt->updated_at;
                        $transaction->invoice_id        = $receipt->id;
                        $transaction->property_id       = $receipt->property_id;
                        $transaction->payment_status    = 1;
                        $transaction->quantity          = str_replace(',', '', trim($t[8]));
                        $transaction->price             = str_replace(',', '', trim($t[7]));
                        $transaction->category          = $t[6];
                        $transaction->payment_date      = $receipt->payment_date;
                        $transaction->due_date          = $receipt->due_date;
                        $transaction->bank_transfer_date = $receipt->bank_transfer_date;
                        $transaction->save();
                    }

                    if ($receipt->payment_type == 2) {

                        $bank = $array_result['bank'][trim($bill['transfer_to'])];
                        $bank_transaction = new BankTransaction;
                        $bank_transaction->property_id      = $receipt->property_id;
                        $bank_transaction->invoice_id       = $receipt->id;
                        // TODO::check bank transfer date when payment type in bank transfer
                        $bank_transaction->bank_id          = $bank->id;
                        $bank_transaction->pay              = $receipt->final_grand_total;
                        $bank_transaction->transfer_date    = $receipt->payment_date;
                        $bank_transaction->bill_type        = 'e';
                        $bank_transaction->created_at       = $receipt->created_at;
                        $bank_transaction->updated_at       = $receipt->updated_at;
                        $bank_transaction->save();

                        // Update bank balance
                        $bank->balance -= $bank_transaction->pay;
                        $bank->save();
                    }
                }

                return response()->json([
                    'result' => true,
                    'message' => 'นำเข้าข้อมูลเสร็จสมบูรณ์'
                ]);
            } else {
                return response()->json([
                    'result' => false,
                    'message' => 'ไม่สามารถนำเข้าข้อมูล เนื่องจาก <br/>' . $array_result['error']
                ]);
            }
        }
    }

    public function checkAndMakeExpenseGrouping($array_bills, $property)
    {
        $valid      = true;
        $message    = "";
        $temp       = $temp_counter  = $temp_bank_id = $temp_bank = array();

        foreach ($array_bills as $key => $bill) {
            $bill_key = $bill[1];
            //$temp[$bill_key]['expense_no'] = $bill[1];

            if(empty($temp[$bill_key]['payee_id']))
                $temp[$bill_key]['payee_id'] = $bill[0];

            if(empty($temp[$bill_key]['payment_date']))
                $temp[$bill_key]['payment_date']  = $bill[2];

            if(empty($temp[$bill_key]['name']))
                $temp[$bill_key]['name'] = $bill[5];

            if(empty($temp[$bill_key]['create_date']))
                $temp[$bill_key]['create_date'] = $bill[2];

            if(empty($temp[$bill_key]['payment_type']))
                $temp[$bill_key]['payment_type'] = $bill[10];

            if(empty($temp[$bill_key]['remark']))
                $temp[$bill_key]['remark'] = '';

            if(empty($temp[$bill_key]['ref_no']))
                $temp[$bill_key]['ref_no'] = $bill[9];

            // payment type
            if ($bill[10] == 2) {
                if (empty($bill[11])) {
                    $message .= "ข้อมูล row " . ($key + 1) . " - เลขที่บัญชีธนาคารไม่ถูกต้อง<br/>";
                    $valid = false;
                } else {
                    if(!in_array($bill[11],$temp_bank)) {
                        $bank = Bank::where('property_id', $property->id)->where('account_number', '=', trim($bill[11]))->first();
                        if (!$bank) {
                            $message .= "ข้อมูล row " . ($key + 1) . " - เลขที่บัญชีธนาคารไม่ถูกต้อง ไม่มีอยู่ในระบบ<br/>";
                            $valid = false;
                        } else {
                            $temp_bank[$bill[11]] = $bank;
                        }
                        $temp_bank_id[] = trim($bill[11]);
                    }
                }

                $temp[$bill_key]['transfer_to'] = trim($bill[11]);
            }

            //  - check payment date for it's not an empty field
            if (empty($bill[2])) {
                $message .= "ข้อมูล row " . ($key + 1) . " - วันที่ออกใบบันทึกรายจ่ายไม่ถูกต้อง<br/>";
                $valid = false;
            }
            //------------- End validation code -------------
            if($valid) {
                $bill[7] = str_replace(',', '', trim($bill[7]));
                $bill[8] = str_replace(',', '', trim($bill[8]));
                $bill[4] = str_replace(',', '', trim($bill[4]));
                $temp[$bill_key]['data'][]   = $bill;
                if( !empty(trim($bill[12])) ) {
                    $temp[$bill_key]['remark'] .= $bill[12] . "\r\n";
                }

                if (empty($temp[$bill_key]['total'])) $temp[$bill_key]['total'] = 0;
                $temp[$bill_key]['total'] += $bill[4];

                if (empty($temp[$bill_key]['grand_total'])) $temp[$bill_key]['grand_total'] = 0;
                $temp[$bill_key]['grand_total'] += $bill[4];
            }
        }

        $result = array('valid' => $valid,'result' => $temp, 'error' => $message, 'bank' => $temp_bank);
        return $result;
    }
}
