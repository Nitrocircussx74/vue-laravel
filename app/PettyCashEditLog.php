<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class PettyCashEditLog extends Model
{
    protected $table = 'property_pettycash_edit_log';
    public function logEditor()
    {
        return $this->belongsTo('App\User','editor','id');
    }
}
