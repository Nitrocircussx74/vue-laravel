<?php

namespace App;
//use App\GeneralModel;
use Request;
use Auth;
class SaleProspectAppointmentDate extends GeneralModel
{
    protected $table = 'sale_prospect_appointment_date';
    protected $fillable = ['id','sale_prospect_id', 'appointment_date', 'status', 'note'];
    public $timestamps = true;
}
