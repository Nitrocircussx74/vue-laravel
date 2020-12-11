<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class UserChoice extends Model
{
    protected $table = 'users_has_choice';
    protected $fillable = ['choice_id','user_id','vote_id'];
    public $timestamps = false;
}
