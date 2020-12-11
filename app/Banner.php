<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class Banner extends GeneralModel
{
    protected $table = 'banner';
    protected $fillable = ['banner_name','detail','img_path','img_name','expired_at','counter_stat','file_type','url'];
    public $timestamps = true;
}
