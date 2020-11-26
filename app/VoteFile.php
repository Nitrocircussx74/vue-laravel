<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class VoteFile extends GeneralModel
{
    protected $table = 'vote_file';
    protected $fillable = ['name','vote_id','file_type','url','path','is_image','original_name'];
    public $timestamps = true;
}
