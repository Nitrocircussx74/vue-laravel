<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StatementOfCashFlowDetails extends Model
{
    public $timestamps = false;
    protected $table = 'statement_of_cash_flow_details';
    protected $fillable = [
        'detail',
        'type',
        'ordering'
    ];
}