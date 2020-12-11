<?php

namespace App;
use App\GeneralModel;
class PropertyForm extends GeneralModel
{
    protected $table = 'property_form';
    public $timestamps = true;
    protected $fillable = ['form_code','detail','status','name','province','email','property_name'];
}
