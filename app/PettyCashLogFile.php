<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PettyCashLogFile extends GeneralModel
{
    protected $table    = 'property_petty_cash_log_file';
    protected $fillable = ['name','petty_cash_log_id','file_type','url','path','is_image','original_name'];
}
