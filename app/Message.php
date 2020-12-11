<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class Message extends GeneralModel
{
    protected $table = 'message';
    protected $fillable = ['user_id','property_id','flag_from_user','flag_from_admin','last_user_message_date'];
	public $timestamps = true;

    public function owner() {
        return $this->hasOne('App\User','id','user_id');
    }

    public function hasText() {
        return $this->hasMany('App\MessageText');
    }
}
