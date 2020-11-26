<?php

namespace App\Http\Controllers\AdminNabour\RootAdmin;

use Request;
use Auth;
use Redirect;

use App\Http\Controllers\Controller;
use App\Province;
use App\PropertyContract;
use App\BackendModel\service_quotation;
use App\BackendModel\Quotation;
use App\BackendModel\Customer;
use App\BackendModel\Contract;
use App\BackendModel\User as BackendUser;
use App\BackendModel\QuotationTransaction;
use App\BackendModel\Products;
use App\Property as property_db;
use App\BackendModel\Property;
use App\BackendModel\ContractTransaction;
use DB;

class ReportsummaryController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index(){

        if( Request::ajax() ) {
            $contracts = new Contract;
            if( Request::get('c_no') ) {
                $contracts = $contracts->where('contract_code','like','%'.Request::get('c_no').'%');
            }


            if( Request::get('customer_id') ) {
                $contracts = $contracts->where('customer_id',Request::get('customer_id'));
            }

            $c_no = Request::get('c_no');
            $customer_id = Request::get('customer_id');
            $contracts = $contracts->where('status','=','1')->orderBy('contract_code','desc')->paginate(50);

            return view('Backend.report_summary.list_contract_property_element')->with(compact('contracts','c_no','customer_id'));

        } else {
            $c_no ='';
            $customer_id = '';
            $p_rows = new ContractTransaction;
            $p_rows = $p_rows->orderBy('start_date','ASC')->paginate(50);

            $sales      = BackendUser::whereIn('role',[1,2])->pluck('name','id');
            $customer = new Customer;
            $customer = $customer->where('role','=',0);
            $customer = $customer->orderBy('created_at','desc')->get();

            $customers = Customer::where('role',0)->pluck('company_name','id');

            $contracts = new Contract;
            $contracts = $contracts->where('status','=','1')->orderBy('contract_code','desc')->paginate(50);

            return view('Backend.report_summary.list_contract_property')->with(compact('contracts','customers','sales','customer','p_rows','c_no','customer_id'));
        }
        //return view('Backend.report.report_summary');
    }

    public function report(){

        $contracts = new Contract;
        if( Request::get('c_no') ) {
            $contracts = $contracts->where('contract_code','like','%'.Request::get('c_no').'%');
        }


        if(Request::get('c_id')) {
            $contracts = $contracts->whereHas('customer', function ($q) {
                $q ->where('company_name','like',"%".Request::get('c_id')."%");
            });
        }

        if( Request::get('customer_id') ) {
            $contracts = $contracts->where('customer_id',Request::get('customer_id'));
        }

        $contracts = $contracts->where('status','=','1')->orderBy('contract_code','desc')->paginate(50);

        foreach ($contracts as $row){
            $contract_s = ContractTransaction::where('contract_id','=',$row['contract_code'])->get();
        }


        return view('Backend.report.report_summary')->with(compact('contracts','contract_s'));

    }

}
