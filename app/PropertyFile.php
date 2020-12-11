<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PropertyFile extends GeneralModel
{
    protected $table = 'property_file';
    protected $fillable = ['name','property_id','file_type','url','path','is_image','original_name'];
    public $timestamps = true;
}
