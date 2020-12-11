<?php

namespace App;
use App\GeneralModel;
class Comment extends GeneralModel
{
    protected $table = 'comments';
    protected $fillable = ['description','user_id','post_id'];
    // Close timestamp
	public $timestamps = true;

	protected $rules = array(
    );

    protected $messages = array(
    );

    public function owner () {
    	return $this->hasOne('App\User','id','user_id'); 
    }

    public function post () {
        return $this->belongsTo('App\Post'); 
    }
}
