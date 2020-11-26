<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class Choice extends Model
{
    protected $table = 'choice';
    protected $fillable = ['title','vote_id','order_choice'];
}
