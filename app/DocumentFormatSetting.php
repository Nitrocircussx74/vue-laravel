<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class DocumentFormatSetting extends Model
{
    protected $table = 'document_format_setting';
    protected $fillable = ['property_id','type'];
    public     $timestamps = true;
}
