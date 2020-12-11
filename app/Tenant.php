<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class Tenant extends GeneralModel
{
    protected $table = 'tenant';
    protected $fillable = ['property_id','property_unit_id','name','phone','email'];
	public $timestamps = true;

    public function property()
    {
        return $this->hasOne('App\Property','id','property_id');
    }

    public function property_unit()
    {
        return $this->hasOne('App\PropertyUnit','id','property_unit_id');
    }
}
