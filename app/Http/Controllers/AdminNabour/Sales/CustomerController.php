<?php

namespace App\Http\Controllers\AdminNabour\Sales;

use Request;
use Auth;
use Redirect;

use App\Http\Controllers\Controller;
use App\Province;
use App\PropertyContract;
use App\BackendModel\service_quotation;
use App\BackendModel\User;
use App\BackendModel\Customer;
use App\BackendModel\Contract;
use App\BackendModel\User_company;

use Validator;
use DB;

class CustomerController extends Controller
{
    public function __construct () {
        $this->middleware('sales');
    }

    public function index()
    {
        $p_rows = new Customer;

        if(Request::ajax()) {
            if (Request::get('name')) {
                $p_rows = $p_rows->where('firstname', 'like', "%" . Request::get('name') . "%")
                    ->orWhere('lastname', 'like', "%" . Request::get('name') . "%");
            }

            if (Request::get('company_name')) {
                $p_rows = $p_rows->where('company_name', '=', Request::get('company_name'));
            }

            if (Request::get('sale_id')) {
                $p_rows = $p_rows->where('sale_id', '=', Request::get('sale_id'));
            }

            if (Request::get('province')) {
                $p_rows = $p_rows->where('province', '=', Request::get('province'));
            }

            if (Request::get('channel_id')) {
                $p_rows = $p_rows->where('channel', '=', Request::get('channel_id'));
            }

            if (Request::get('type_id')) {
                $p_rows = $p_rows->where('type', '=', Request::get('type_id'));
            }
        }


        $p = new Province;
        $provinces = $p->getProvince();

        $sale = new User;
        $sale = $sale->where('role','=',2);
        $sale = $sale->get();


        //$p_rows = new Customer;
        $p_rows = $p_rows->where('role','=',0)->where('sale_id','=',Auth::user()->id);
        $p_rows = $p_rows->orderBy('created_at','desc')->paginate(50);



       // dd($p_rows);
        //dd($p_rows);

        //dump($customer->toArray());

        if(!Request::ajax()) {
            return view('Backend.customer.list_customer')->with(compact('customer','provinces','sale','p_rows'));
        }else{
            return view('Backend.customer.list_customer_element')->with(compact('customer','provinces','sale','p_rows'));
        }

    }

    public function create()
    {
        if(Request::isMethod('post')) {
            $customer = new Customer;
            $customer->firstname        = Request::get('firstname');
            $customer->lastname         = Request::get('lastname');
            $customer->phone            = Request::get('phone');
            $customer->email            = Request::get('email');
            $customer->address          = Request::get('address');
            $customer->province         = Request::get('province');
            $customer->postcode         = Request::get('postcode');
            $customer->company_name     = Request::get('company_name');
            $customer->channel          = Request::get('channel');
            $customer->type             = Request::get('type');
            $customer->active_status    = 'f';
            $customer->status           = 0;
            $customer->sale_id          = Auth::user()->id;
            $customer->role             =0;
            $customer->tax_id           = Request::get('tax_id');
            $customer->save();
            //dump($customer->toArray());
        }
        if(Auth::user()->role !=2) {
            return redirect('customer/customer/list');
        }else{
            return redirect('customer/sales/customer/list');
        }

    }

    public function store(Request $request)
    {
        //
    }


    public function show($id)
    {
        //
    }


    public function edit($id)
    {
        $customer = Customer::find($id);

        //dump($customer->user_company->count());

        $p = new Province;
        $provinces = $p->get();

        $sale = new User;
        $sale = $sale->where('role','=',2);
        $sale = $sale->get();

        $_p = new Province;
        $_provinces = $_p->getProvince();

        return view('Backend.customer.edit_customer')->with(compact('customer','provinces','sale','_provinces'));
    }


    public function update()
    {
        if(Request::isMethod('post')) {
            $customer = Customer::find(Request::get('customer_id'));
            $customer->firstname = Request::get('firstname');
            $customer->lastname = Request::get('lastname');
            $customer->phone = Request::get('phone');
            $customer->email = Request::get('email');
            $customer->address = Request::get('address');
            $customer->province = Request::get('province');
            $customer->postcode = Request::get('postcode');
            $customer->company_name = Request::get('company_name');
            $customer->channel = Request::get('channel');
            $customer->type = Request::get('type');
            $customer->active_status = 'f';
            $customer->status = Request::get('status');
            $customer->sale_id = Request::get('sale_id');
            $customer->tax_id = Request::get('tax_id');
            $customer->save();
            //dd($customer);

//            $customer_company = User_company::find(Request::get('customer_id'));
//
//            if($customer_company){
//                $user_company = User_company::find(Request::get('customer_id'));
//                $user_company->company_name_en      =  Request::get('company_name_en');
//                $user_company->customer_id          =  Request::get('customer_id');
//                $user_company->tax_id               =  Request::get('tax_id');
//                $user_company->date_register        =  Request::get('date_register');
//                $user_company->registered_capital   =  Request::get('registered_capital');
//                $user_company->type_company         =  Request::get('type_company');
//                $user_company->address_no           =  Request::get('address_no');
//                $user_company->street_th            =  Request::get('street_th');
//                $user_company->address_th           =  Request::get('address_th');
//                $user_company->province_company     =  Request::get('province_company');
//                $user_company->postcode_company     =  Request::get('postcode_company');
//                $user_company->tel_company          =  Request::get('tel_company');
//                $user_company->fax_company          =  Request::get('fax_company');
//                $user_company->phone_company        =  Request::get('phone_company');
//                $user_company->mail_company         =  Request::get('mail_company');
//
//                if(!empty(Request::get('directer_company'))){
//                    $directer[] = Request::get('directer_company');
//
//                    $count = count($directer);
//
//                    for ($i = 0; $i < $count; $i++) {
//                        $cut_directer = implode(",", $directer[$i]);
//                    }
//                }
//
//                $user_company->directer_company     =  empty($cut_directer)?null:$cut_directer;;
//                $user_company->save();
//               //dd($user_company);
//            }else{
//                if(!empty(Request::get('company_name_en'))){
//                    $user_company = new User_company;
//                    $user_company->company_name_en      =  Request::get('company_name_en');
//                    $user_company->customer_id          =  Request::get('customer_id');
//                    $user_company->tax_id               =  Request::get('tax_id');
//                    $user_company->date_register        =  Request::get('date_register');
//                    $user_company->registered_capital   =  Request::get('registered_capital');
//                    $user_company->type_company         =  Request::get('type_company');
//                    $user_company->address_no           =  Request::get('address_no');
//                    $user_company->street_th            =  Request::get('street_th');
//                    $user_company->address_th           =  Request::get('address_th');
//                    $user_company->province_company     =  Request::get('province_company');
//                    $user_company->postcode_company     =  Request::get('postcode_company');
//                    $user_company->tel_company          =  Request::get('tel_company');
//                    $user_company->fax_company          =  Request::get('fax_company');
//                    $user_company->phone_company        =  Request::get('phone_company');
//                    $user_company->mail_company         =  Request::get('mail_company');
//                    if(!empty(Request::get('directer_company'))){
//                        $directer[] = Request::get('directer_company');
//
//                        $count = count($directer);
//
//                        for ($i = 0; $i < $count; $i++) {
//                            $cut_directer = implode(",", $directer[$i]);
//                        }
//                    }
//                    $user_company->directer_company     =  empty($cut_directer)?null:$cut_directer;;
//
//                    //dd($user_company);
//                    $user_company->save();
//                }
//            }
        }

            //dd($user_company);
            //dump($customer->toArray());
        if(Auth::user()->role !=2) {
            return redirect('customer/customer/list');
        }else{
            return redirect('customer/sales/customer/list');
        }
    }

    public function destroy()
    {
        if(Request::isMethod('post')) {
            $customer = Customer::find(Request::get('id2'));
            $customer->status       = 't';
            $customer->save();
            //dump($customer->toArray());
        }
        if(Auth::user()->role !=2) {
            return redirect('customer/customer/list');
        }else{
            return redirect('customer/sales/customer/list');
        }
    }

    public function check()
    {
        if(Request::isMethod('post')) {
            $customer = Customer::find(Request::get('id3'));
            $customer->status       = 'f';
            $customer->save();
            //dump($customer->toArray());
        }
        if(Auth::user()->role !=2) {
            return redirect('customer/customer/list');
        }else{
            return redirect('customer/sales/customer/list');
        }
    }

    public function print_report_book_bank($id = null){

        $customer = Customer::find($id);

        $p = new Province;
        $provinces = $p->getProvince();

        $contract = new Contract;
        $contract = $contract->where('customer_id','=',$id);
        $contract = $contract->get();

        // dump($contract);


        return view('Backend.customer.report_open_book_bank')->with(compact('customer','provinces','contract'));
    }
}
