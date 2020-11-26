<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class CheckFirstOrderSinghaOnline extends GeneralModel
{
    protected $table = 'check_first_order_singha_online';
    protected $fillable = ['user_id'];
	public $timestamps = true;
}
