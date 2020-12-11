<?php

namespace App;
use App\GeneralModel;
class RequestedProperty extends GeneralModel
{
    protected $table = 'requested_property';
    protected $fillable = ['name','email','province','property_name'];
    // Close timestamp
	public $timestamps = false;

    public static function boot()
    {
        static::creating(function($model)
        {
            $model->created_at = $model->freshTimestamp();
        });
    }

	public $rules = array(
        'fname' => 'required',
        'lname' => 'required',
        'email'    => 'required|email|max:255|unique:requested_property,email|unique:users,email',
        'province' => 'not_zero',
        'new_property_name' => 'required'
    );

    protected $messages = array();
}
