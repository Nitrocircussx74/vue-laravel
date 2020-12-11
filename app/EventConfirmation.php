<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class EventConfirmation extends Model
{
    protected $table = 'user_event_confirmation';
    protected $fillable = ['user_id','event_id','confirm_status'];
}
