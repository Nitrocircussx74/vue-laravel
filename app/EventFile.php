<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class EventFile extends GeneralModel
{
    protected $table = 'event_file';
    protected $fillable = ['name','event_id','file_type','url','path','is_image','original_name'];
	public $timestamps = false;
}
