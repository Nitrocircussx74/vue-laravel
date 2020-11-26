<?php

namespace App;
use App\GeneralModel;
class DiscussionComment extends GeneralModel
{
    protected $table = 'discussion_comments';
    protected $fillable = ['description','user_id','discussion_id','is_reject'];
    // Close timestamp
	public $timestamps = true;

	protected $rules = array(
    );

    protected $messages = array(
    );

    public function owner () {
    	return $this->hasOne('App\User','id','user_id');
    }

    public function discussion () {
        return $this->belongsTo('App\Discussion');
    }
}
