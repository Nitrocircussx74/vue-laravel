<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class WithdrawalRequester extends GeneralModel
{
    protected $table = 'withdrawal_requester';
    protected $fillable = ['user_id','invoice_id'];

    public function user () {
    	return $this->hasOne('App\User','id','user_id');
    }
}
