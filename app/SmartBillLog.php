<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class SmartBillLog extends GeneralModel
{
    protected $table = 'smart_bill_payment_setting_log';
    protected $fillable = ['smart_bill_payment_setting_id','by_user','content_log'];
	public $timestamps = true;


	
    public function users()
    {
        return $this->hasMany('App\User','id','by_user');
    }
}


