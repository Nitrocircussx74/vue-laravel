<?php

namespace App\Model;

use App\GeneralModel;

class Engagement extends GeneralModel
{
    protected $table = 'engagement';
    protected $fillable = ['property_id','user_id','property_unit_id','content_menu','content_id','device','device_version','app_version'];
    public $timestamps = true;
}
