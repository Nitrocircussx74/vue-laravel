<?php

namespace App;
use App\GeneralModel;
class PropertyUnit extends GeneralModel
{
    protected $table = 'property_unit';
    protected $fillable = ['property_id','property_type','property_size','price','unit_number','address',
        'street','province','postcode','resident_count','electricity_meter_serial','water_meter_serial',
        'pet','vehicle','phone','is_land','property_unit_unique_id','owner_name_th','owner_name_en',
        'delivery_address','transferred_date','insurance_expire_date','invite_code','building','unit_floor',
        'unit_soi','type','extra_cf_charge','contact_lang','is_billing_water','is_billing_electric','email',
        'ownership_ratio','public_utility_fee','garbage_collection_fee','waste_water_treatment','utility_discount','sub_type'];
    // Close timestamp
	public $timestamps = false;

	public $rules = array(
        'unit_number' => 'not_zero',
        'province'    => 'required',
        'property_id' => 'not_zero'
    );

    protected $messages = array();

    public function property_admin()
    {
        return $this->hasOne('App\User');
    }

    public function home_pet()
    {
        return $this->hasMany('App\Pet');
    }

    public function home_vehicle()
    {
        return $this->hasMany('App\Vehicle');
    }

    public function home_tenant()
    {
        return $this->hasMany('App\Tenant');
    }

    public function property () {
        return $this->belongsTo('App\Property','property_id','id');
    }

    public function balanceLog()
    {
        return $this->hasMany('App\PropertyUnitBalanceLog','property_unit_id','id');
    }

    public function prepaidBill()
    {
        return $this->hasMany('App\PropertyUnitPrepaid','property_unit_id','id');
    }

    public function debtBill()
    {
        return $this->hasMany('App\Invoice','property_unit_id','id');
    }

    public function home_keycard()
    {
        return $this->hasMany('App\Keycard');
    }

    public function eBill()
    {
        return $this->hasOne('App\BillElectric','property_unit_id','id');
    }

    public function wBill()
    {
        return $this->hasOne('App\BillWater','property_unit_id','id');
    }

    public function eBillFee()
    {
        return $this->hasOne('App\BillElectric','property_unit_id','id');
    }

    public function wBillFee()
    {
        return $this->hasOne('App\BillWater','property_unit_id','id');
    }

    public function normalUser () {
        return $this->hasMany('App\User');
    }

    public function invoice () {
        return $this->hasMany('App\Invoice','property_unit_id','id')->orderBy('invoice_no_label','asc');;
    }

    public function overDueInvoice () {
        return $this->invoice()->where('is_retroactive_record',false)->where(function ($q) {
            $q->orWhere(function ($q_) {
                $q_->where('payment_status',0)->where('due_date','<',date('Y-m-d'));
            })->orWhere(function ($q_) {
                $q_->where('payment_status',1)->whereRaw('submit_date::date > due_date::date');
            });
        });
    }
}
