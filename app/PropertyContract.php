<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PropertyContract extends GeneralModel
{
    protected $table = 'property_nb_contract';
    protected $fillable = ['contract_sign_no', 'contract_date', 'contract_length', 'free', 'contract_end_date','remark', 'info_delivery_date'];
	public $timestamps = true;

    protected $rules = array(
        'contract_sign_no'      => 'required',
        'contract_date'         => 'required',
        'contract_end_date'     => 'required',
        'info_delivery_date'    => 'required'
    );
}
