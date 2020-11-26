<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class BillElectric extends GeneralModel
{
    protected $table = 'bill_electric';
    protected $fillable = ['property_id','property_unit_id','status','bill_date_period','unit','net_unit','invoice_id','is_service_charge'];
	public $timestamps = true;

	public function property_unit()
    {
        return $this->belongsTo('App\PropertyUnit','property_unit_id','id');
    }
}
