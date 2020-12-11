<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PostReport extends GeneralModel
{
	public $timestamps = true;
    protected $table = 'post_report';
    protected $fillable = ['post_id','property_id','post_type'];

    public function reportList()
    {
        return $this->hasMany('App\PostReportDetail');
    }

   	public function post()
    {
        return $this->belongsTo('App\Post','post_id','id');
    }

    public function event()
    {
        return $this->belongsTo('App\Event','post_id','id');
    }

    public function vote()
    {
        return $this->belongsTo('App\Vote','post_id','id');
    }

	public function discussion()
    {
        return $this->belongsTo('App\Discussion','post_id','id');
    }
}
