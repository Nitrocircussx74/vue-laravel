<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class SOSOrderPaymentFile extends GeneralModel
{
    protected $table = 'order_payment_file';
    protected $fillable = ['name','order_id','file_type','url','path'];
    public $timestamps = true;
}
