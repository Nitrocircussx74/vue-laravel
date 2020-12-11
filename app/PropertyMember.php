<?php
namespace App;
use App\GeneralModel;
class PropertyMember extends GeneralModel
{
    protected $table = 'users';

    public function unit () {
        return $this->hasOne('App\PropertyUnit','id','property_unit_id');
    }
}
