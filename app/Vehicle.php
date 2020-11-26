<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class Vehicle extends GeneralModel
{
    protected $table = 'vehicle';
    protected $fillable = ['brand','lisence_plate','model','color','type','property_id','property_unit_id','year','sticker_status'];
	public $timestamps = false;
	public function property_unit()
    {
        return $this->belongsTo('App\PropertyUnit','property_unit_id','id');
    }
}
