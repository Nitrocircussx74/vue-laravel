<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
use App;
use Illuminate\Database\Eloquent\SoftDeletes;
class PaymentNotificationLog extends GeneralModel
{
    use SoftDeletes;

    protected $table = 'payment_notification_log';
    protected $fillable = ['billerId','transDate','transTime','termType','amount','reference1','reference2','reference3','fromBank','fromBranch','fromName','txnType','retryFlag','bankRef','approvalCode','url','request_ref','transmit_date_time'];
	public $timestamps = true;
}
