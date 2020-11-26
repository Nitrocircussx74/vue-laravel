<?php

namespace App;
//use App\GeneralModel;
use Request;
use Auth;
class SaleProspect extends GeneralModel
{
    protected $table = 'sale_prospect';
    protected $fillable = [
        'id',
        'sale_id',
        'prospect_name',
        'prospect_phone_number',
        'prospect_email',
        'property_name',
        'unit_size',
        'note',
        'status',
        'prospect_code',
        'customer_code',
    ];
    public $timestamps = true;
    public function appointment_date () {
        return $this->hasMany('App\SaleProspectAppointmentDate', 'sale_prospect_id', 'id');
    }
}
