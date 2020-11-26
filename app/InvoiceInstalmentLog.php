<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class InvoiceInstalmentLog extends GeneralModel
{
    protected $table = 'invoice_instalment_log';
    protected $fillable = ['invoice_id','to_receipt_id','title','amount','from_date','to_date'];
	public $timestamps = true;

	function fromInvoice () {
	    return $this->belongsTo('App\Invoice','invoice_id','id');
    }

    function toReceipt () {
        return $this->belongsTo('App\Invoice','to_receipt_id','id');
    }
}
