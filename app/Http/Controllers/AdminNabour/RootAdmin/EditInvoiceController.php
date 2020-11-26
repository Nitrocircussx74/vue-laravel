<?php

namespace App\Http\Controllers\AdminNabour\RootAdmin;

use Auth;
use Redirect;
use App\Bank;
use App\CommonFeesRef;
use Request;
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


class EditInvoiceController extends Controller
{

    public function __construct () {
        $this->middleware('admin');
    }
    
    public function index($id,$print=false,$is_copy_invoice=false)
    {

        if(Request::isMethod('post')) {
            //dd(Request::input('id'));
            $bill_check_type = Invoice::find(Request::input('id'));
            if (isset($bill_check_type->property_unit_id)) {

                $bill = Invoice::with(array('instalmentLog' => function ($q) {
                    return $q->orderBy('created_at', 'ASC');
                }, 'property', 'property.settings', 'property_unit', 'transaction', 'invoiceFile', 'commonFeesRef',
                    'receiptInvoiceAggregate' => function ($q) {
                        $q  ->join('invoice', 'invoice_id', '=', 'invoice.id')
                            ->orderBy('invoice.invoice_no_label', 'ASC');
                    }))->find(Request::input('id'));

            } else {
                $bill = Invoice::with('property', 'transaction', 'invoiceFile', 'commonFeesRef')->find(Request::input('id'));
            }

            if($bill->payment_status == 2 || $bill->payment_status == 5) {
                return view('Backend.feesbills.admin-receipt-view')->with(compact('bill'));
            } else {
                //check overdue invoice
                $is_overdue_invoice = false;
                if(!$bill->submit_date) {
                    if( strtotime(date('Y-m-d')) > strtotime($bill->due_date) )
                        $is_overdue_invoice = true;
                } else {
                    $day_submit = date('Y-m-d',strtotime($bill->submit_date));
                    if ( strtotime( $day_submit ) > strtotime( $bill->due_date ) )
                        $is_overdue_invoice = true;
                }

                $cal_cf_fine_flag = $cal_cf_house_fine_flag = $cal_normal_bill_fine_flag = false;

                return view('Backend.invoice.detail_invoice_element')->with(compact('bill','is_overdue_invoice','cal_cf_fine_flag','cal_cf_house_fine_flag','cal_normal_bill_fine_flag'));
            }
        } else {
            return view ('invoice.detail_invoice');
        }

    }


    public function create()
    {
        //
    }


    public function store(Request $request)
    {
        //
    }


    public function show($id)
    {
        //
    }


    public function edit($id)
    {
        //
    }


    public function update(Request $request, $id)
    {
        //
    }


    public function destroy()
    {
        $tran = Transaction::find(Request::input('id2'));

        $invoice = Invoice::find($tran->invoice_id);
        $invoice->added_fine_flag = false;
        $invoice->last_instalment_cf_date_b4_fine = null;
        $invoice->save();
        $tran->delete();

        //dd($tran->invoice_id);

        return redirect('root/admin/edit/invoice');
    }
}
