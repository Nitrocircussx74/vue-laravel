<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class Installation extends GeneralModel
{
    protected $table = 'installation';
    protected $fillable = ['user_id','device_token','device_type','device_uuid','created_at','updated_at'];
    public $timestamps = true;
    
    public function user () {
        return $this->belongsTo('App\User','user_id', 'id')->where('role',2);
    }
}
