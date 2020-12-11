<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StatementOfCashFlow extends Model
{
    protected $table = 'statement_of_cash_flow';
    protected $fillable = [
        'name',
        'header_1',
        'header_2',
        'header_3'
    ];

    public function details()
    {
        return $this->hasMany('App\StatementOfCashFlowDetails','statement_id','id')->orderBy('ordering','asc');
    }

    public function createdBy () {
        return $this->hasOne('App\User','id','created_by');
    }
}
