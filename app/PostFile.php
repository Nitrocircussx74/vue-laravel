<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PostFile extends GeneralModel
{
    protected $table = 'post_file';
    protected $fillable = ['name','post_id','file_type','url','path','is_image','original_name','flag_cover'];
	public $timestamps = true;
}
