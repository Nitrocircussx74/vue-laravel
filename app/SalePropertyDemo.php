<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class SalePropertyDemo extends GeneralModel
{
    protected $table = 'sale_property_demo';
    protected $fillable = ['id','sale_id','property_id','trial_expire','status','email_contact','property_test_name','contact_name','default_password','lasted_login_at','login_counter'];
    public $timestamps = true;
    public function property()
    {
        return $this->hasOne('App\Property','id','property_id');
    }
}
