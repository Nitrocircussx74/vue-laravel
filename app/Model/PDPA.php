<?php namespace App\Model;

use App\GeneralModel;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class PDPA extends Model
{

    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'pdpa_consent';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'title_th',
        'title_en',
        'detail_th',
        'detail_en',
        'cover_image',
        'created_by',
        'updated_by',
        'deleted_by',
        'ordering',
        'publish_status',
        'target_recipient',
        'target_organization',
        'publish_date',
      
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
    public static function boot()
    {
        // call parent boot
        parent::boot();

        static::creating(function ($model) {
            $order = intval($model->ordering);
            $model->ordering = $order==0?null:$order; 
        });
        static::updating(function ($model) {
            $order = intval($model->ordering);
            $model->ordering = $order==0?null:$order;
        });
    }
    public $timestamps = true;

    public function created_user()
    {
        return $this->hasOne('App\User', 'id', 'created_by');
    }

    public function updated_user()
    {
        return $this->hasOne('App\User', 'id', 'updated_by');
    }

    public function pdpa_has_cate()
    {
        return $this->hasMany('App\Model\PDPAHasCategory','pdpa_id','id');
    }
    public function PDPAUserAccept()
    {
        return $this->hasOne('App\Model\PDPAUserAccept','pdpa_id','id');
    }

    public function log () {
        return $this->hasMany('App\Model\PDPAEditlog','pdpa_id','id');
    }
  
}
