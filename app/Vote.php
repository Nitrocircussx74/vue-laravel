<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class Vote extends Model
{
    protected $table = 'vote';
    protected $fillable = ['property_id','user_id','title','description','start_date','end_date','start_time','end_time'];
    public function creator()
    {
        return $this->belongsTo('App\User','user_id','id');
    }

    public function voteFile () {
        return $this->hasMany('App\VoteFile');
    }

    public function voteChoice () {
        return $this->hasMany('App\Choice');
    }

    public function userChoose()
    {
        return $this->hasOne('App\UserChoice');
    }
}
