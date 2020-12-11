<?php

namespace App;
use App\GeneralModel;
class Discussion extends GeneralModel
{
    protected $table = 'discussion';
    public $timestamps = true;
    protected $fillable = ['property_id','user_id','title','detail'];
    public function owner () {
    	return $this->hasOne('App\User','id','user_id');
    }
    public function discussionFile () {
    	return $this->hasMany('App\DiscussionFile');
    }
    public function comments () {
        return $this->hasMany('App\DiscussionComment');
    }
}
