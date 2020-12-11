<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class PropertyFeature extends GeneralModel
{
    protected $table = 'property_menu_feature';
    protected $fillable = ['property_id','menu_committee_room','menu_event','menu_vote','menu_tenant','menu_vehicle',
        'menu_prepaid','menu_revenue_record','menu_retroactive_receipt','menu_common_fee','menu_cash_on_hand',
        'menu_pettycash','menu_fund','menu_complain','menu_parcel','menu_message','menu_message','menu_finance_group','market_place_singha'];
	public $timestamps = true;
}
