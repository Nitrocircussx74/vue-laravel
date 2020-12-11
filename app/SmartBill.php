<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class SmartBill extends GeneralModel
{
    protected $table = 'smart_bill_payment_setting';
    protected $fillable = ['property_id','tax_id','acc_no','activated','merchant_name_th','merchant_name_en','acc_suffix','property_bank_id','biller_id','service_code'];
    public $timestamps = true;
    
    public function hasLog () {
        return $this->hasMany('App\SmartBillLog', 'smart_bill_payment_setting_id', 'id');
    }

    public function property () {
        return $this->belongsTo('App\Property','property_id','id');
    }
}