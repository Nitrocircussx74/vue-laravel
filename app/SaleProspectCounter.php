<?php

namespace App;
//use App\GeneralModel;
use Request;
use Auth;
class SaleProspectCounter extends GeneralModel
{
    protected $table = 'sale_prospect_counter';
    protected $fillable = [
        'number_prospect',
        'number_customer'
    ];
    public $timestamps = false;
}
