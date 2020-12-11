<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class YearlyCounterDoc extends Model
{
    protected $table = 'yearly_counter_doc';
    protected $fillable = ['property_id','year_period','invoice_counter','receipt_counter','expense_counter','payee_counter','withdrawal_counter','pe_slip_counter'];
    public     $timestamps = true;
}
