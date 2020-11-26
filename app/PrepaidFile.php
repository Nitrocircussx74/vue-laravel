<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PrepaidFile extends GeneralModel
{
    protected $table = 'prepaid_file';
    protected $fillable = ['name','prepiad_id','file_type','url','path','is_image','original_name'];
}
