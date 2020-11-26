<?php

namespace App;
use App\GeneralModel;
class Complain extends GeneralModel
{
    protected $table = 'complain';
    public $timestamps = true;
    protected $fillable = ['property_id','property_unit_id','complain_category_id','user_id','title','detail',
        'complain_status','is_appointment','is_deposit_key','user_appointment_note','appointment_date','is_missing_appointment',
        'missing_appointment_note','review_rate','review_comment','attachment_result','comment_result','technician_name'];
    public function category () {
    	return $this->hasOne('App\ComplainCategory','id','complain_category_id');
    }
    public function owner () {
    	return $this->hasOne('App\User','id','user_id');
    }
    public function property_unit () {
    	return $this->hasOne('App\PropertyUnit','id','property_unit_id');
    }
    public function complainFile () {
    	return $this->hasMany('App\ComplainFile');
    }
    public function comments () {
        return $this->hasMany('App\ComplainComment');
    }
    public function complainAction () {
        return $this->hasMany('App\ComplainAction','complain_id','id')->orderBy('action_date','asc');
    }
}
