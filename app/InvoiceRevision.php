<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class InvoiceRevision extends GeneralModel
{
    protected $table = 'invoice_revision';
    protected $fillable = ['invoice_id','details','created_by','revision_no'];
	public $timestamps = true;

    public function by () {
        return $this->hasOne('App\User','id','created_by');
    }

    public function invoice () {
        return $this->hasOne('App\Invoice','id','invoice_id');
    }
}
