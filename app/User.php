<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;
use App\Notifications\MailResetPasswordToken;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{

    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'phone', 'active', 'line_user'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public $rules = [
        'fname' => 'required',
        'lname' => 'required',
        'phone' => 'max:10|required',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'alpha_num|min:4|required',
        'password_confirm' => 'alpha_num|min:4|required|same:password',
    ];

    protected $keyType = 'string';

    public function validate($data)
    {
        Validator::extend('not_empty', function ($attribute, $value, $parameters) {
            return !empty($value);
        });

        $v = Validator::make($data, $this->rules);
        return $v;
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new MailResetPasswordToken($token));
    }

    public function officialAccount()
    {
        return $this->hasMany('App\Model\AdminOfficialAccount', 'user_id', 'id');
    }
}
