<?php

namespace App;
use App\GeneralModel;
class Page extends GeneralModel
{
    protected $table = 'pages';
    protected $fillable = ['content_th','content_en'];
	public $timestamps = true;
}
