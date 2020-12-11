<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class MonthlyCounterDoc extends Model
{
    protected $table = 'monthly_counter_doc';
    protected $fillable = ['property_id','date_period','invoice_counter','receipt_counter','expense_counter','payee_counter','withdrawal_counter','pe_slip_counter'];
    public     $timestamps = true;
}
