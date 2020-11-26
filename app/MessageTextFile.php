<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class MessageTextFile extends GeneralModel
{
    protected $table = 'message_text_file';
    protected $fillable = ['name','message_text_id','file_type','url','path','is_image','original_name'];
    public $timestamps = true;
}
