<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class Transaction extends Model
{
    protected $table = 'transaction';
    protected $fillable = ['title','detail','transaction_type','total','invoice_id','property_id','payment_status','property_unit_id','quantity','price','type','category','payment_date','due_date','for_external_payer','final_total','bank_transfer_date','vat','submit_date','ordering','w_tax','sub_from_discount','sub_from_balance'];

    public function bill () {
    	return $this->belongsTo('App\Invoice', 'invoice_id','id');
    }
}
