<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class PropertyFundEditLog extends Model
{
    protected $table = 'property_fund_edit_log';
    public function logEditor()
    {
        return $this->belongsTo('App\User','editor','id');
    }
}
