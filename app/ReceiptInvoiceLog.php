<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReceiptInvoiceLog extends Model
{
    protected $table = 'receipt_invoice_log';
    protected $fillable = ['data','receipt_id'];
}
