<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class DefaultBanner extends GeneralModel
{
    protected $table = 'default_banner';
    public $timestamps = true;
}
