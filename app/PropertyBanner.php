<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PropertyBanner extends GeneralModel
{
    protected $table = 'property_banner';
    protected $fillable = ['banner_id','property_id','counter','ordering'];
    public $timestamps = true;
}
