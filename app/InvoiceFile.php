<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class InvoiceFile extends GeneralModel
{
    protected $table = 'invoice_file';
    protected $fillable = ['name','complain_id','file_type','url','path','is_image','original_name'];
}
