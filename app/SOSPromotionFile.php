<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class SOSPromotionFile extends GeneralModel
{
    protected $table = 'promotion_file';
    protected $fillable = ['name','promotion_id','file_type','url','path','is_image','original_name'];
	public $timestamps = false;
}
