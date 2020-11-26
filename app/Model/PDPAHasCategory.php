<?php namespace App\Model;

use App\GeneralModel;

class PDPAHasCategory extends GeneralModel
{

    protected $table = 'pdpa_has_category';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pdpa_id',
        'pdpa_category_id',
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
    public function pdpa_cate()
    {
        return $this->hasOne('App\Model\PDPACategory','id','pdpa_category_id');
    }

  
}
