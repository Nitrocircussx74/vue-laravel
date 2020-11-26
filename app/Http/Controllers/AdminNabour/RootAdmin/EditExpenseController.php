<?php

namespace App\Http\Controllers\AdminNabour\RootAdmin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

use App\Invoice;
use App\PropertyUnit;
use App\Bank;
use App\BankTransaction;
use App\Transaction;
use App\Payee;

class EditExpenseController extends Controller
{
    public function __construct () {
        $this->middleware('admin');
        view()->share('active_menu','finance');
    }

    public function getExpenseForAdjust(Request $r) {
        if($r->isMethod('post')) {

            $bill = Invoice::where('type',2)->where('payment_status',1)->where('id',$r->get('id'))->first();
            if( $bill ) {
                $unit_list = [];
                $unit_list += PropertyUnit::where('property_id',$bill->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->pluck('unit_number','id')->toArray();
                $bank = new Bank;
                $bank_list = $bank->getBankList(false,$bill->property_id);

                $payees_list = array( "" => trans('messages.Payee.select_name'));
                $payees_list += Payee::where('property_id',$bill->property_id)->pluck('name','id')->toArray();
            }
            return view('Backend.expense.adjust-receipt-form')->with(compact('bill','unit_list','bank_list','payees_list'));

        } else {
            return view('Backend.expense.adjust-receipt');
        }
    }

    public function adjustExpense(Request $r) {
        if($r->isMethod('post')) {

            $receipt = Invoice::with('bankTransaction')->find($r->get('id'));
            $receipt->timestamps    = false;
            $validDate              = true;
            $msg = [];

            if( !$this->validateDate($r->get('payment_date')) ) {
                $validDate = false;
                $msg[] = "วันที่ชำระเงินไม่ถูกต้อง";
            }

            if( !$this->validateDate($r->get('created_at')) ) {
                $validDate = false;
                $msg[] = "วันที่ออกใบสำคัญจ่ายไม่ถูกต้อง";
            }

            if($r->get('bank_id') && !$this->validateDate($r->get('bank_transfer_date'))) {
                $validDate = false;
                $msg[] = "วันที่ถอนเงินไม่ถูกต้อง";
            }

            if( !$validDate ) {
                $msg = implode('<br/>',$msg);
                $r->session()->flash('class', 'warning');
                $r->session()->flash('message', $msg);
                return redirect()->back()->withInput();
            } else {

                $receipt->payment_type      = $r->get('payment_type');
                $receipt->payment_date      = $r->get('payment_date');
                $receipt->created_at        = $r->get('created_at');
                $receipt->ref_no            = $r->get('ref_no');
                $receipt->remark            = $r->get('remark');

                if( $r->get('bank_id') ) {
                    $receipt->transfered_to_bank = true;
                    $receipt->bank_transfer_date = $r->get('bank_transfer_date');
                } else {
                    $receipt->transfered_to_bank = false;
                    $receipt->bank_transfer_date = null;
                }


                foreach ($r->get('transaction') as $t) {
                    $transaction = Transaction::find($t['id']);
                    $transaction->timestamps            = false;
                    $transaction->detail                =  $t['detail'];
                    $transaction->payment_date          = $receipt->payment_date;
                    $transaction->bank_transfer_date    =  $receipt->bank_transfer_date;
                    $transaction->save();
                }

                if( $r->get('bank_id') ) {
                    if( $receipt->bankTransaction ) {
                        // if bank transaction exited
                        $bt                 = $receipt->bankTransaction;
                        $bt->timestamps     = false;
                        $bt->transfer_date  = $receipt->bank_transfer_date;

                        if( $r->get('bank_id') != $receipt->bankTransaction->bank_id ) {
                            $bank   = new Bank;
                            $bank->updateBalance ( $bt->bank_id,$receipt->final_grand_total );
                            $bank->updateBalance ( $r->get('bank_id'),$receipt->final_grand_total * -1 );
                            $bt->bank_id = $r->get('bank_id');
                        }
                        $bt->save();
                    } else {
                        // create bank transaction
                        $bt     = new BankTransaction;
                        $bt->saveBankExpenseTransaction($receipt,$r->get('bank_id'));
                        $bank   = new Bank;
                        $bank->updateBalance ($r->get('bank_id'),$receipt->final_grand_total* -1);
                    }
                } else {
                    // remove old bank transaction
                    if( $receipt->bankTransaction ) {
                        $bt     = $receipt->bankTransaction;
                        $bank   = new Bank;
                        $bank->updateBalance ( $bt->bank_id,$receipt->final_grand_total );
                        $bt->delete();
                    }
                }

                if( $receipt->save() ) {
                    $r->session()->flash('class', 'success');
                    $r->session()->flash('message', "บันทึกข้อมูลใบสำคัญจ่ายเลขที่ ".$receipt->expense_no_label." เรียบร้อยแล้ว");
                    return redirect('root/admin/edit/expense');
                } else {
                    $r->session()->flash('class', 'warning');
                    $r->session()->flash('message', "เกิดความผิดพลาด ไม่สามารถบันทึกข้อมูลใบเสร็จรับเงินได้");
                    return redirect()->back()->withInput();
                }
            }
        }
    }

    public function validateDate ($dt) {
        if( $dt == date('Y-m-d H:i:s',strtotime($dt)) ) {
            // date is in fact in one of the above formats
            return true;
        } else {
            return false;
        }
    }
}
