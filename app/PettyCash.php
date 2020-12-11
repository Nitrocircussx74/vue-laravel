<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class PettyCash extends Model
{
    protected $table = 'property_petty_cash_log';
    protected $fillable = ['detail','get','pay','property_id','invoice_id','creator','payment_date','ref_no'];

    public function createdBy()
    {
        return $this->belongsTo('App\User','creator','id');
    }

    public function savedFrom ()
    {
        return $this->belongsTo('App\Invoice','invoice_id','id');
    }

    public function editLog ()
    {
        return $this->hasMany('App\PettyCashEditLog','pc_log_id','id');
    }

    public function pettyCashFile()
    {
        return $this->hasMany('App\PettyCashLogFile','petty_cash_log_id','id');
    }

    public function refInvoice ()
    {
        return $this->hasOne('App\Invoice','id','invoice_id');
    }
}
