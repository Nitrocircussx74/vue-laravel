<?php

namespace App\Model\Partner;

use App\GeneralModel;

class BeneatOrder extends GeneralModel
{
    protected $table = 'partner_beneat_order';
    protected $fillable = [
        "id",
        "user_id",
        "professional_id",
        "service_id",
        "work_hour",
        "cleaning_date",
        "email",
        "name",
        "tel",
        "address",
        "province_id",
        "remark",
        "latitude",
        "longitude",
        "total_price",
        "discount_cost",
        "service_vat",
        "order_date",
        "confirm_cleaning_date",
        "cancel_cleaning_date",
        "status",
        "cancel",
        "selected_professional_id",
        "payment_method",
        "created_at",
        "updated_at",
        "professional_full_name",
        "province_name_th",
        "province_name_en",
        "promo_code",
        "full_data",
        "system_recorded_date"
    ];
    public $timestamps = false;
}