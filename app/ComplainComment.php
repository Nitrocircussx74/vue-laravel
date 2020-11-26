<?php

namespace App;
use App\GeneralModel;
class ComplainComment extends GeneralModel
{
    protected $table = 'complain_comments';
    protected $fillable = ['description','user_id','complain_id','is_reject'];
    // Close timestamp
	public $timestamps = true;

	protected $rules = array(
    );

    protected $messages = array(
    );

    public function owner () {
    	return $this->hasOne('App\User','id','user_id'); 
    }

    public function complain () {
        return $this->belongsTo('App\Complain'); 
    }
}
