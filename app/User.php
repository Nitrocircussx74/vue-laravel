<?php

namespace App;

use App\Http\Controllers\Notifications\MailResetPasswordNotification;
use App\Http\Controllers\Notifications\VerifyApiEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Validator;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'email', 'password', 'property_id', 'property_unit_id', 'role', 'verification_code', 'profile_pic_name', 'profile_pic_path', 'dob', 'phone', 'gender', 'is_chief', 'is_subscribed_newsletter'];

    public $rules = [
        'name' => 'required',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'alpha_num|min:4|required',
        'password_confirm' => 'alpha_num|min:4|required|same:password'
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

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Send the password reset notification.
     * @note: This override Authenticatable methodology
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new MailResetPasswordNotification($token));
    }

    public function validate($data)
    {
        Validator::extend('not_empty', function ($attribute, $value, $parameters) {
            return !empty($value);
        });

        $v = Validator::make($data, $this->rules);
        return $v;
    }
}
