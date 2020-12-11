<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SOSPromotion extends Model
{
    //
    protected $table = 'promotion';
    protected $fillable = ['name','detail','code','expire_date','counter','limit','status','property_participation','discount_type','discount_value'];
    public $timestamps = true;

    public function promotion_property()
    {
        return $this->hasMany('App\SOSPromotionProperty','promotion_id','id');
    }

    public function promotionFile()
    {
        return $this->hasMany('App\SOSPromotionFile','promotion_id','id');
    }
}
