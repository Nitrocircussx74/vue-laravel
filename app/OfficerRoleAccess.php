<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class OfficerRoleAccess extends GeneralModel
{
    protected $table = 'officer_role_access';
    protected $fillable = ['property_id','user_id','menu_committee_room','menu_event','menu_vote','menu_tenant','menu_vehicle',
        'menu_prepaid','menu_revenue_record','menu_retroactive_receipt','menu_common_fee','menu_cash_on_hand',
        'menu_pettycash','menu_fund','menu_complain','menu_parcel','menu_message','position','menu_finance_group','menu_property_setting','menu_property_member'];
	public $timestamps = true;
}
