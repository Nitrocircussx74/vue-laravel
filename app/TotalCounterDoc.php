<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class TotalCounterDoc extends Model
{
    protected $table = 'total_counter_doc';
    protected $fillable = ['property_id','invoice_counter','receipt_counter','expense_counter','payee_counter','withdrawal_counter','pe_slip_counter'];
    public     $timestamps = true;
}
