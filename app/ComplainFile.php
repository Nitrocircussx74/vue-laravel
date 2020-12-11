<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class ComplainFile extends GeneralModel
{
    protected $table = 'complain_file';
    protected $fillable = ['name','complain_id','file_type','url','path','is_image','original_name'];
    public $timestamps = true;
}
