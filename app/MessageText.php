<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class MessageText extends GeneralModel
{
    protected $table = 'message_text';
    protected $fillable = ['message_id','user_id','text','is_admin_reply 	'];
	public $timestamps = true;

    public function owner() {
        return $this->hasOne('App\User','id','user_id');
    }
    public function messageTextFile () {
        return $this->hasMany('App\MessageTextFile','message_text_id','id');
    }
}
