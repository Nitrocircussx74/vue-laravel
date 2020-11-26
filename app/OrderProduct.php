<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    protected $table = 'order_products';
    protected $fillable = [
        'order_id',
        'sos_product_id',
        'product_category',
        'sos_user_id',
        'quantity',
        'price',
        'total',
        'user_id',
        'property_unit_id',
        'property_id',
        'developer_group',
        'zone_id',
        'ordering',
        'status',
        'product_name',
        'unit',
        'received_at',
        'is_promotion',
        'br_product_id',
        'vat'
    ];
    public $timestamps = true;

    public function property_unit () {
        return $this->hasOne('App\PropertyUnit','id','property_unit_id');
    }
    public function property () {
        return $this->hasOne('App\Property','id','property_id');
    }
    public function in_order () {
        return $this->hasOne('App\Order','id','order_id');
    }
}
