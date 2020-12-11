<?php

namespace App;
use App\GeneralModel;
class PropertySettings extends GeneralModel
{
    protected $table = 'property_settings';
    public $timestamps = true;
    protected $fillable = [
        'property_id',
        'condo_first_fine_rate',
        'condo_second_fine_rate',
        'common_fee_footer',
        'housing_estate_fine_type',
        'housing_estate_fine_rate',
        'condo_start_fine_month',
        'electric_billing_type',
        'electric_billing_rate',
        'electric_billing_minimum_price',
        'electric_billing_minimum_unit',
        'water_billing_type',
        'water_billing_rate',
        'water_billing_minimum_price',
        'water_billing_minimum_unit',
        'progressive_rate',
        'water_progressive_rate',
        'electric_progressive_rate',
        'water_meter_maintenance_fee',
        'electric_meter_maintenance_fee',
        'include_fixed_cost_to_cf_bill',
        'fine_multiplyer_type',
        'bill_collector_msg',
        'bill_condition_msg',
        'qr_code'
    ];
}
