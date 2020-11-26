<?php

namespace App;
use App\GeneralModel;

class SmartBillReceiptLog extends GeneralModel
{
    protected $table = 'smart_bill_payment_to_receipt_log';
    protected $fillable = ['property_id','by_user_id','invoice_ids'];
    public $timestamps = true;
    
    public function byUser()
    {
        return $this->hasOne('App\User','id','by_user_id');
    }
}
