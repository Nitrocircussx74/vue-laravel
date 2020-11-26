<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{   
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    protected $table = 'notification';
    protected $fillable = ['title','description','notification_type','subject_key','from_user_id','to_user_id','read_status'];

    public function sender()
    {
        return $this->hasOne('App\User','id','from_user_id');
    }
}
