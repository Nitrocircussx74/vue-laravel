<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Facades\Validator;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'email', 'password','property_id','property_unit_id','role','verification_code','profile_pic_name','profile_pic_path','dob','phone','gender','is_chief','is_subscribed_newsletter'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    public $rules = [
        'fname' => 'required',
        'lname' => 'required',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'alpha_num|min:4|required',
        'password_confirm' => 'alpha_num|min:4|required|same:password'
    ];

    /*public function validate($data)
    {
        $v = Validator::make($data, $this->rules);
        return $v;
    }*/

    public function property()
    {
        return $this->hasOne('App\Property','id','property_id');
    }

    public function property_menu()
    {
        return $this->hasOne('App\PropertyFeature','property_id','property_id');
    }

    public function user_property_menu()
    {
        return $this->hasOne('App\UserPropertyFeature','property_id','property_id');
    }

    public function property_unit()
    {
        return $this->hasOne('App\PropertyUnit','id','property_unit_id');
    }

    public function installation()
    {
        return $this->hasMany('App\Installation','user_id','id');
    }

    public function position()
    {
        return $this->hasOne('App\OfficerRoleAccess','user_id','id');
    }

    public function validate($data)
    {
        Validator::extend('not_empty', function($attribute, $value, $parameters) {
            return !empty($value);
        });

        $v = Validator::make($data, $this->rules);
        return $v;
    }

    public function market_place () {
        return $this->hasMany('App\UserMarketPlaceData','user_id','id');
    }
}
