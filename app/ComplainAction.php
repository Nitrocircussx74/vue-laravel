<?php

namespace App;
use App\GeneralModel;
class ComplainAction extends GeneralModel
{
    protected $table = 'complain_action_stamp';
    public $timestamps = false;
    protected $fillable = ['complain_id','from_staus','to_status'];

    public function saveAction ($complain,$status) {
            $ca = new ComplainAction;
            $ca->from_status    = $complain->complain_status;
            $ca->from_date      = $complain->updated_at;
            $ca->to_status      = $status;
            $ca->complain_id    = $complain->id;
            $ca->action_date    = date('Y-m-d H:i:s');
            $perate             = calOperatingDays($ca->from_date, $ca->action_date);
            $ca->working_day    = $perate[0];
            $ca->working_hour   = $perate[1];
            $ca->save();
    }
}