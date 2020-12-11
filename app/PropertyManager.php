<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PropertyManager extends GeneralModel
{
    protected $table = 'property_manager';
    protected $fillable = ['property_id','user_id','name','email'];
	public $timestamps = true;
}
