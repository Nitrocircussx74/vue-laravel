<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class PropertyFund extends Model
{
    protected $table = 'property_fund_log';
    protected $fillable = ['detail','property_id','creator','payment_date','ref_no'];

    public function createdBy()
    {
        return $this->belongsTo('App\User','creator','id');
    }

    public function expenseTo ()
    {
        return $this->hasOne('App\Invoice','invoice_id','id');
    }

    public function editLog ()
    {
        return $this->hasMany('App\PropertyFundEditLog','fund_log_id','id');
    }
}
