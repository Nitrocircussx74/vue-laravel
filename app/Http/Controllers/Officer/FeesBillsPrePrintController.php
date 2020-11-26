<?php

namespace App\Http\Controllers\Officer;

use Request;
use App\Http\Controllers\Controller;
use DB;
use App\Invoice;
use App\Transaction;

class FeesBillsPrePrintController extends Controller
{
    public function printBills(){
        $feature = PropertyFeature::where('property_id',Auth::user()->property_id)->first();
        $ids = explode(",", Request::get('list-bill'));
        $print_type = 3; // print both
        if(Request::get('original-print') == "true" && Request::get('copy-print') == "false"){
            $print_type = 1; // print just original
        }elseif(Request::get('original-print') == "false" && Request::get('copy-print') == "true"){
            $print_type = 2; // print just copy
        }
        $bills = Invoice::with(array('instalmentLog' => function ($q) {
            return $q->orderBy('created_at', 'ASC');
        }, 'property', 'transaction', 'invoiceFile', 'commonFeesRef'))->whereIn('id',$ids)->orderBy('invoice_no_label','desc')->get();

        return view('feesbills.print-view.preprint.admin-invoice-print')->with(compact('bills','print_type','feature'));
    }

    public function printReceipts(){
        $feature = PropertyFeature::where('property_id',Auth::user()->property_id)->first();
        $ids = explode(",", Request::get('list-bill'));
        $print_type = 3; // print both
        if(Request::get('original-print') == "true" && Request::get('copy-print') == "false"){
            $print_type = 1; // print just original
        }elseif(Request::get('original-print') == "false" && Request::get('copy-print') == "true"){
            $print_type = 2; // print just copy
        }

        $bills = Invoice::with(array('instalmentLog' => function ($q) {
            return $q->orderBy('created_at', 'ASC');
        }, 'property', 'transaction', 'invoiceFile'))->whereIn('payment_status',[2,5])->whereIn('id',$ids)->orderBy('invoice_no_label','desc')->get();
        return view('feesbills.print-view.preprint.admin-receipt-print')->with(compact('bills','print_type','feature'));
    }
}
