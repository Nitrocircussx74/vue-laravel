<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SOSZone extends Model
{
    //
    protected $table = 'zone';
    protected $fillable = [
        'name',
        'detail',
        'delivery_on_mon',
        'delivery_on_tue',
        'delivery_on_wed',
        'delivery_on_thu',
        'delivery_on_fri',
        'delivery_on_sat',
        'delivery_on_sun'
    ];
    public $timestamps = true;

    public function property()
    {
        return $this->hasMany('App\Property','sos_zone_id','id')->select('sos_zone_id','property_name_th','property_name_en','id');
    }
}
