<?php

namespace App;
class TransactionRef extends GeneralModel
{
    protected $table = 'transaction_instalment_ref';
    protected $fillable = ['from_transaction','to_transaction'];
}
