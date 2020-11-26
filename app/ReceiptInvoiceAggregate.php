<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReceiptInvoiceAggregate extends Model
{
    protected $table = 'receipt_invoice_aggregate';
    protected $fillable = ['invoice_id','receipt_id'];

    public function invoice () {
        return $this->belongsTo('App\Invoice','invoice_id');
    }

    public function receipt () {
        return $this->belongsTo('App\Invoice','receipt_id');
    }
}
