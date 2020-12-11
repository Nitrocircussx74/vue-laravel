<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class Pet extends GeneralModel
{
    protected $table = 'pet';
    protected $fillable = ['type','breed','quantity','property_id','property_unit_id'];
	public $timestamps = false;
}
