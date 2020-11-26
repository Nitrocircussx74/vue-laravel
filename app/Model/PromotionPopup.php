<?php

namespace App\Model;
use App\GeneralModel;

class PromotionPopup extends GeneralModel
{
    protected  $table = 'promotion_popup';
    protected $fillable = ['title_th','title_en','detail_th','detail_en',
        'cover_image','ordering','ordering',
        'updated_by','publish_start_date','publish_end_date','promotion_start_date','promotion_end_date',
        'publish_status','ratio','alert_type'
        ];
    // Close timestamp
	public     $timestamps = true;
	protected  $rules = array();
    protected  $messages = array();

    public function created_user()
    {
        return $this->hasOne('App\User', 'id', 'created_by');
    }

    public function updated_user()
    {
        return $this->hasOne('App\User', 'id', 'updated_by');
    }
}