<?php namespace App\Model;

// use App\GeneralModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class PDPAUserAccept extends Model
{
    use SoftDeletes;


    protected $dates = ['deleted_at'];
    
    protected $table = 'pdpa_user_accept';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pdpa_id',
        'user_id',
        'accept_choice'
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

    public function PDPA()
    {
        return $this->hasMany('App\Model\PDPA','id','pdpa_id');
    }
  
}
