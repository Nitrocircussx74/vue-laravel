<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class Event extends Model
{
    protected $table = 'event';
    protected $fillable = ['property_id','user_id','title','description','join_count','maybe_count','cantgo_count','location','start_date_time','end_date_time'];

    public function creator()
    {
        return $this->belongsTo('App\User','user_id','id');
    }

    public function confirmation()
    {
        return $this->hasOne('App\EventConfirmation');
    }

    public function confirmationAll()
    {
        return $this->hasMany('App\EventConfirmation');
    }

    public function eventFile()
    {
        return $this->hasMany('App\EventFile');
    }
}
