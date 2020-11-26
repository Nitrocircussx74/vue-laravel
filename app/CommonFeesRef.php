<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class CommonFeesRef extends GeneralModel
{
    protected $table = 'common_fee_ref';
    protected $fillable = ['invoice_id','property_id','property_unit_id','from_date','to_date','payment_status','property_unit_unique_id'];
	public $timestamps = true;
	public function property_unit()
    {
        return $this->belongsTo('App\PropertyUnit','property_unit_id','id');
    }
    public function invoice()
    {
        return $this->belongsTo('App\Invoice','invoice_id','id');
    }
}
