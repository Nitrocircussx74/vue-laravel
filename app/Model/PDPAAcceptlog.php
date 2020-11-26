<?php namespace App\Model;

use App\GeneralModel;

class PDPAAcceptlog extends GeneralModel
{

    protected $table = 'pdpa_edit_accept_log';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'by_user',
        'pdpa_id',
        'user_accept_log'
    ];

    protected $messages = [
        'required',
        'unique'
    ];
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public $timestamps = true;

    public function editor () {
        return $this->hasOne('App\User','id','by_user');
    }

  
}
