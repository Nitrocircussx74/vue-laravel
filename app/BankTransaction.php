<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
use App\Bank;
class BankTransaction extends GeneralModel
{
    protected $table = 'property_bank_transaction';
	public $timestamps = true;

	public function getBank ()
    {
        return $this->belongsTo('App\Bank','bank_id','id');
    }

	public function getInvoice ()
    {
        return $this->belongsTo('App\Invoice','invoice_id','id')->select(array('id','invoice_no','receipt_no','expense_no','invoice_no_label','receipt_no_label','expense_no_label','name','type'));
    }

	function saveBankBillTransaction ($invoice,$bank_id) {
		if($this->checkValidBank ($bank_id)) {
			$bt = new BankTransaction;
			$bt->property_id 		= $invoice->property_id;
			$bt->property_unit_id 	= $invoice->property_unit_id;
			$bt->invoice_id 		= $invoice->id;
			$bt->bank_id 			= $bank_id;
			/// if is mixed payment and this bill is come from cashboxController action
			if( $invoice->mixed_payment && $invoice->cash_on_hand_transfered )
				$bt->get 			= $invoice->sub_from_balance;
			else
				$bt->get 			= $invoice->final_grand_total;
			$bt->transfer_date 		= $invoice->bank_transfer_date;
			$bt->bill_type			= 'b';
			$bt->save();
		}
	}

	function saveBankRevenueTransaction ($rr_bill,$bank_id) {
		if($this->checkValidBank ($bank_id)) {
			$bt = new BankTransaction;
			$bt->property_id 		= $rr_bill->property_id;
			$bt->property_unit_id 	= $rr_bill->property_unit_id;
			$bt->invoice_id 		= $rr_bill->id;
			$bt->bank_id 			= $bank_id;
			$bt->get 				= $rr_bill->final_grand_total;
			$bt->transfer_date 		= $rr_bill->bank_transfer_date;
			$bt->bill_type			= 'r';
			$bt->save();
		}
	}

	function saveBankPrepaidTransaction ($pp_bill,$bank_id) {
		if($this->checkValidBank ($bank_id)) {
			$bt = new BankTransaction;
			$bt->property_id 		= $pp_bill->property_id;
			$bt->property_unit_id 	= $pp_bill->property_unit_id;
			$bt->prepaid_id 		= $pp_bill->id;
			$bt->bank_id 			= $bank_id;
			$bt->get 				= $pp_bill->amount;
			$bt->transfer_date 		= $pp_bill->bank_transfer_date;
			$bt->bill_type			= 'p';
			$bt->save();
		}
	}

	function saveBankExpenseTransaction ($bill,$bank_id) {
		if($this->checkValidBank ($bank_id)) {
			$bt = new BankTransaction;
			$bt->property_id 		= $bill->property_id;
			$bt->property_unit_id 	= $bill->property_unit_id;
			$bt->invoice_id 		= $bill->id;
			$bt->bank_id 			= $bank_id;
			$bt->pay 				= $bill->final_grand_total;
			$bt->transfer_date 		= $bill->bank_transfer_date;
			$bt->bill_type			= 'e';
			$bt->save();
		}
	}

	function checkValidBank ($bank_id) {
		return Bank::find($bank_id);
	}
}