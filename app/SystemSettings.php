<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class SystemSettings extends GeneralModel
{
    protected $table = 'system_settings';
    protected $fillable = ['login_with_line','login_with_apple_id'];
    public $timestamps = true;
    
   
}