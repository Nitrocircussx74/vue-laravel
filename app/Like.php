<?php

namespace App;
use App\GeneralModel;
class Like extends GeneralModel
{
    protected $table = 'likes';
    protected $fillable = ['user_id','post_id'];
    // Close timestamp
	public $timestamps = false;
	protected $rules = array();
    protected $messages = array();

    public static function boot()
    {
        static::creating(function($model)
        {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function owner () {
    	return $this->hasOne('App\User','id','user_id'); 
    }
}
