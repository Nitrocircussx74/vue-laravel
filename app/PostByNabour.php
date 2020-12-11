<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PostByNabour extends GeneralModel
{
    protected  $table = 'post_by_nabour_stat';
    //protected $fillable = ['name','email','province','property_name'];
    // Close timestamp
	public     $timestamps = true;
	protected  $rules = array();
    protected  $messages = array();

    /*public function owner()
    {
        return $this->hasOne('App\User','id','user_id');
    }*/

    public function postFile()
    {
        return $this->hasMany('App\PostByNabourFile');
    }

    /*public function savePost($post) {
        $post->user_id      = Auth::user()->id;
        $post->title  = Request::get('title');
        $post->description  = Request::get('description');
        return $post->save();
    }*/
}
