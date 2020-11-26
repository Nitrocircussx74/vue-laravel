<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
use App;
class Bank extends GeneralModel
{
    protected $table = 'property_bank_account';
    protected $fillable = ['property_id','bank','account_name','account_number','account_type','account_branch','is_fund_account'];
	public $timestamps = true;

	public function transactionLog ()
    {
        return $this->hasMany('App\BankTransaction','bank_id','id');
    }

	public function getBankList ($isExpense = false) {
		if( $isExpense ) $bank_list[''] = trans('messages.feesBills.expense_transfered_account');
		else $bank_list[''] = trans('messages.feesBills.transfered_account');
		$bank_name 	= unserialize(constant('BANK_LIST_'.strtoupper(App::getLocale())));
	    $banks 	= Bank::where('property_id',Auth::user()->property_id)->where('active',true)->select('account_name','account_number','account_branch','id','bank')->orderBy('created_at','ASC')->get();
	    foreach ($banks as $bank) {
	    	$bank_list[$bank->id] = $bank_name[$bank->bank]." (".$bank->account_branch.") ".": ".$bank->account_name.' ('.$bank->account_number.')';
	    }

	    return $bank_list;
	}

	public function updateBalance ($bank,$balance) {
		$bank = Bank::find($bank);
		if($bank) {
			$bank->balance += $balance;
			$bank->save();
		}
	}
}
