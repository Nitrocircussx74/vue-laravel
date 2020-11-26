<?php namespace App\Model;

use App\GeneralModel;

class PDPACategory extends GeneralModel
{

    protected $table = 'pdpa_category';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'pdpa_id',
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

    public function created_user()
    {
        return $this->hasOne('App\User', 'id', 'created_by');
    }

    public function updated_user()
    {
        return $this->hasOne('App\User', 'id', 'updated_by');
    }

  
}
