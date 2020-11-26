<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $fillable = [
        'order_number',
        'sos_user_id',
        'user_id',
        'property_unit_id',
        'property_id',
        'developer_group',
        'zone_id',
        'vat',
        'total',
        'grand_total',
        'discount',
        'coupon_code',
        'status',
        'received_at',
        'order_running_no',
        'payment_at',
        'payment_type',
        'expires_at',
        'cart_id'
    ];
    public $timestamps = true;

    public function order_product () {
        return $this->hasMany('App\OrderProduct','order_id','id');
    }

    public function property_unit () {
        return $this->hasOne('App\PropertyUnit','id','property_unit_id');
    }

    public function property () {
        return $this->hasOne('App\Property','id','property_id');
    }

    public function order_by () {
        return $this->hasOne('App\User','id','user_id');
    }

    public function payment_file () {
        return $this->hasOne('App\SOSOrderPaymentFile','order_id','id');
    }

    public function orderPaymentFile () {
        return $this->hasMany('App\SOSOrderPaymentFile');
    }
}
