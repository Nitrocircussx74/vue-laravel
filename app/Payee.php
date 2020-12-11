<?php

namespace App;
use App\GeneralModel;
class Payee extends GeneralModel
{
    protected $table = 'payee';
    protected $fillable = ['name','address','phone','tax','fax','email','note','payee_no'];
    public $timestamps = true;
}
