<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SOSPromotionProperty extends Model
{
    //
    protected $table = 'promotion_property';
    public $timestamps = true;

    public function property()
    {
        return $this->hasOne('App\Property','id','property_id')->select('id','property_name_th','property_name_en');
    }
}
