<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashBoxDepositeLog extends Model
{
    protected $table = 'cash_box_deposite_log';
    public $timestamps = true;

    public function depositLogFile()
    {
        return $this->hasMany('App\CashBoxDepositeLogFile','cash_box_log_id','id');
    }

    public function bank()
    {
        return $this->hasOne('App\Bank','id','bank_id');
    }
}
