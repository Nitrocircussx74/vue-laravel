<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashBoxDepositeLogFile extends Model
{
    protected $table    = 'cash_box_deposite_log_file';
    protected $fillable = ['name','cash_box_log_id','file_type','url','path','is_image','original_name'];
}
