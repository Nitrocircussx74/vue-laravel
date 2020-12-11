<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class Keycard extends GeneralModel
{
    protected $table = 'keycard';
    protected $fillable = ['property_id','property_unit_id','serial_number','status'];
	public $timestamps = true;

	public function property_unit()
    {
        return $this->belongsTo('App\PropertyUnit','property_unit_id','id');
    }
}
