<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class DiscussionFile extends GeneralModel
{
    protected $table = 'discussion_file';
    protected $fillable = ['name','discussion_id','file_type','url','path','is_image','original_name'];
    public $timestamps = true;
}
