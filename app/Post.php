<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class Post extends GeneralModel
{
    protected  $table = 'post';
    //protected $fillable = ['name','email','province','property_name'];
    // Close timestamp
	public     $timestamps = true;
	protected  $rules = array();
    protected  $messages = array();

    public function owner()
    {
        return $this->hasOne('App\User','id','user_id');
    }

    public function comments()
    {
        return $this->hasMany('App\Comment');
    }

    public function likes()
    {
        return $this->hasMany('App\Like');
    }

    public function postFile()
    {
        return $this->hasMany('App\PostFile','post_id','id');
    }
    public function postImg()
    {
        return $this->hasMany('App\PostFile','post_id','id')->where('is_image','=','t');
    }

    public function postNabourFile()
    {
        return $this->hasMany('App\PostByNabourFile');
    }

    public function savePost($post) {
        $post->user_id      = Auth::user()->id;
        $post->property_id  = Auth::user()->property_id;
        $post->description  = Request::get('description');
        $post->like_count   = $post->post_type = $post->comment_count = 0;
        $post->description  = Request::get('description');
        return $post->save();
    }
}
