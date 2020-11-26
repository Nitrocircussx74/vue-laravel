<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PostByNabourFile extends GeneralModel
{
    protected $table = 'post_by_nabour_file';
    protected $fillable = ['name','post_nb_id','file_type','url','path','is_image','original_name','attach_nabour_post_key'];
	public $timestamps = true;
}
