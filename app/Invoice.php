<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class Invoice extends Model
{
    protected $table = 'invoice';
    protected $fillable = ['type','name','due_date','grand_total','total','tax','property_id','property_unit_id','payment_type','payment_date','payment_status','ref_no','receiver_address','payee_id','discount','transfer_only','is_retroactive_record','is_common_fee_bill','remark','final_grand_total','sub_from_balance','balance_before','invoice_no_label','invoice_no'];

    public function property () {
    	return $this->belongsTo('App\Property');
    }

    public function property_unit () {
    	return $this->belongsTo('App\PropertyUnit');
    }

    public function transaction () {
    	return $this->hasMany('App\Transaction')->orderBy('created_at','asc')->orderBy('ordering','asc');
    }

    public function invoiceFile () {
    	return $this->hasMany('App\InvoiceFile')->where('status_delete',0);
    }

    public function payee () {
        return $this->belongsTo('App\Payee');
    }

    public function invoiceRevision () {
    	return $this->hasMany('App\InvoiceRevision');
    }

    public function commonFeesRef () {
    	return $this->hasOne('App\CommonFeesRef');
    }

    public function requester () {
    	return $this->hasOne('App\WithdrawalRequester','invoice_id','id');
    }

    public function instalmentLog () {
        return $this->hasMany('App\InvoiceInstalmentLog','invoice_id','id');
    }

    public function bankTransaction () {
        return $this->hasOne('App\BankTransaction','invoice_id','id');
    }

    public function invoiceLog () {
        return $this->hasOne('App\ReceiptInvoiceLog','receipt_id','id');
    }

    public function receiptInvoiceInstalmentLog () {
        return $this->hasMany('App\InvoiceInstalmentLog','to_receipt_id','id');
    }

    public function cancelledBy () {
        return $this->hasOne('App\User','id','cancelled_by');
    }

    public function unitBalanceLog () {
        return $this->hasOne('App\PropertyUnitBalanceLog','invoice_id','id');
    }

    public function receiptInvoiceAggregate () {
        return $this->hasMany('App\ReceiptInvoiceAggregate','receipt_id','id');
    }

    public function InvoiceToAggregatedReceipt () {
        return $this->hasOne('App\ReceiptInvoiceAggregate','invoice_id','id');
    }

    public function transactionInstalmentLog () {
        return $this->hasMany('App\TransactionRef','receipt_id','id');
    }

    public function createdBy () {
        return $this->hasOne('App\User','id','created_by');
    }

    public function approvedBy () {
        return $this->hasOne('App\User','id','approved_by');
    }

    public function smartPaymentLog () {
        return $this->hasOne('App\PaymentNotificationLog','reference1','smart_bill_ref_code');
    }
}
