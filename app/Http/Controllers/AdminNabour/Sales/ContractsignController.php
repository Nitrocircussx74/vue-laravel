<?php

namespace App\Http\Controllers\AdminNabour\Sales;

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
use App\BackendModel\Property as BackendProperty;
use App\Property;
use App\BackendModel\ContractTransaction;

class ContractsignController extends Controller
{
    public function __construct () {
        $this->middleware('sales');
    }

    public function index($id = null)
    {
        $quotation = new Contract;
        //$quotation = $quotation->where('lead_id',$lead_id);
        $quotation = $quotation->where('id',$id);
        $quotation = $quotation->first();



        //dd($quotation_code);
        $package = new Products;
        $package = $package->where('status', '1');
        $package = $package->get();

        $quotation_service = new QuotationTransaction;
        $quotation_service = $quotation_service->where('quotation_id', $quotation->id);
        $quotation_service = $quotation_service->get();

        $contract_property = new ContractTransaction;
        $contract_property = $contract_property->where('contract_id', $quotation->contract_code);
        $contract_property = $contract_property->get();

        $type_array = array();
        foreach ($contract_property as $row){
            //dump($row->property_id)  ;
            $type = property::find($row->property_id);
            $type = $type->toArray();

            $type_array[] = $type;

        }

        $p = new Province;
        $provinces = $p->getProvince();

        return view('Backend.contract.contractdocument')->with(compact('quotation','provinces','quotation_service','package','type_array','contract_property'));
    }


    public function create($quotation_id = null,$id = null)
    {
        $search = new Contract;
        $search = $search->where('quotation_id', $quotation_id);
        $search = $search->where('id', $id);
        $search = $search->first();

        //dd($id);

        if(!empty($search)){
            $quotation1 = new Quotation;
            $quotation1 = $quotation1->where('id', $quotation_id);
            $quotation1 = $quotation1->first();

            $contract = new Contract;
            $contract = $contract->where('quotation_id', $quotation_id);
            $contract = $contract->where('id', $id);
            $contract = $contract->first();

            $contract_property = new ContractTransaction;
            $contract_property = $contract_property->where('contract_id', $contract->contract_code);
            $contract_property = $contract_property->get();

            $count = new Contract;
            $count = $count->where('quotation_id', $quotation_id)->where('status','=',1);
            $count = $count->where('id', $id);
            $count = $count->count();

            $count_ = new Contract;
            $count_ = $count_->where('customer_id', $quotation1->customer_id)->where('status','=',1);
            $count_ = $count_->where('id', $id);
            $count_ = $count_->count();

            $quotation = new Quotation;
            $quotation = $quotation->where('id', $quotation_id);
            $quotation = $quotation->first();

            $quotation_service = new QuotationTransaction;
            $quotation_service = $quotation_service->where('quotation_id', $quotation_id);
            $quotation_service = $quotation_service->get();

            $property = new Property;
            $property = $property->get();

            //dd($count_);

            return view('Backend.contract.contract_update')->with(compact('quotation1','quo_id','contract','search','count','count_','quotation','quotation_service','property','contract_property','id','customer_id'));

        }else{
            $quotation1 = new Quotation;
            $quotation1 = $quotation1->where('id', $quotation_id);
            $quotation1 = $quotation1->first();


            $contract = new Contract;


            $date=date("Y-m-d");
            $cut_date_now=explode("-",$date);

            $singg = contract::whereYear('created_at', '=', $cut_date_now[0])
                ->whereMonth('created_at', '=', $cut_date_now[1])
                ->get();
            $sing=$singg->max('contract_code');

            $property = new Property;
            $property = $property->get();

            //dd($quotation1);
            return view('Backend.contract.contract_form')->with(compact('quotation1','sing','quo_id','contract','property'));
        }


    }


    public function save()
    {
        $contract = new Contract;
        $contract->contract_code        = Request::get('contract_code');
        $contract->grand_total_price    = Request::get('price');
        $contract->sales_id             = Request::get('sales_id');
        $contract->customer_id          = Request::get('customer_id');
        $contract->payment_term_type    = Request::get('payment_term_type');
        $contract->contract_status      = 0;
        $contract->quotation_id         = Request::get('quotation_id1');
        $contract->person_name          = empty(Request::get('person_name'))?null:Request::get('person_name');
        $contract->type_service         = Request::get('type_service');
        $contract->save();
        //dump($contract);

        if(!empty(Request::get('property_id'))){
            $count = count(Request::get('property_id'));
            for ($i=0;$i<$count;$i++){
                //contract_id','property_name','property_id
                $property_id = explode("|",Request::get('property_id')[$i]);

                $ContractTransaction = new ContractTransaction;
                $ContractTransaction->contract_id          = Request::get('contract_code');
                $ContractTransaction->property_name        = Request::get('property_name')[$i];
                $ContractTransaction->property_id          = $property_id[0];
                $ContractTransaction->start_date           = Request::get('start_date')[$i];
                $ContractTransaction->end_date             = Request::get('end_date')[$i];
                $ContractTransaction->save();
                //dump($ContractTransaction);
            }
        }
        if(Auth::user()->role !=2){
            return redirect('customer/service/quotation/add/'.Request::get('customer_id'));
        }else{
            return redirect('customer/service/sales/quotation/add/'.Request::get('customer_id'));
        }
    }


    public function update()
    {
        $contract = contract::find(Request::get('contract_id'));
        $contract->contract_code        = Request::get('contract_code');
        $contract->grand_total_price    = Request::get('price');
        $contract->sales_id             = Request::get('sales_id');
        $contract->customer_id          = Request::get('customer_id');
        $contract->payment_term_type    = Request::get('payment_term_type');
        $contract->contract_status      = 0;
        $contract->quotation_id         = Request::get('quotation_id1');
        $contract->person_name          = empty(Request::get('person_name'))?null:Request::get('person_name');
        $contract->type_service         = Request::get('type_service');
        $contract->save();

        if(!empty(Request::get('property_id'))){
            $count = count(Request::get('property_id'));
            for ($i=0;$i<$count;$i++){
                $property_id = explode("|",Request::get('property_id')[$i]);
                $ContractTransaction = ContractTransaction::find(Request::get('id')[$i]);
                $ContractTransaction->contract_id          = Request::get('contract_code');
                $ContractTransaction->property_name        = Request::get('property_name')[$i];
                $ContractTransaction->start_date           = Request::get('start_date')[$i];
                $ContractTransaction->end_date        = Request::get('end_date')[$i];
                $ContractTransaction->property_id          = $property_id[0];
                $ContractTransaction->save();
                //dump($ContractTransaction);
            }
        }

        if(!empty(Request::get('property_id_update'))){
            $count = count(Request::get('property_id_update'));
            for ($i=0;$i<$count;$i++){
                //contract_id','property_name','property_id
                $property_id = explode("|",Request::get('property_id_update')[$i]);

                $ContractTransaction = new ContractTransaction;
                $ContractTransaction->contract_id          = Request::get('contract_code');
                $ContractTransaction->property_name        = Request::get('property_name_update')[$i];
                $ContractTransaction->start_date           = Request::get('start_date_update')[$i];
                $ContractTransaction->end_date             = Request::get('end_date_update')[$i];
                $ContractTransaction->property_id          = $property_id[0];
                $ContractTransaction->save();
                //dump($ContractTransaction);
            }
        }


        if(Auth::user()->role !=2){
            return redirect('customer/service/quotation/add/'.Request::get('customer_id'));
        }else{
            return redirect('customer/service/sales/quotation/add/'.Request::get('customer_id'));
        }
    }


    public function approved()
    {
        $contract = contract::find(Request::get('id'));
        $contract->status = 1;
        $contract->save();

        $quotation = Quotation::find(Request::get('quo_id'));
        $quotation->status = 1;
        $quotation->save();

        $date =date('Y-m-d');
        $customer = Customer::find(Request::get('customer_id'));
        $customer->role = 0;
        $customer->convert_date = $date;
        $customer->save();

        return redirect('contract/list');
    }

    public function contractList () {
        $contracts = contract::where('sales_id',Auth::user()->id);

        if( Request::get('c_no') ) {
            $contracts = $contracts->where('contract_code','like','%'.Request::get('c_no').'%');
        }

        if(Request::get('c_id')) {
            $contracts = $contracts->whereHas('customer', function ($q) {
                $q ->where('company_name','like',"%".Request::get('c_id')."%");
            });
        }

        $contracts = $contracts->orderBy('contract_code','desc')->paginate(25);
        //dd($contracts->toArray());
        if( Request::ajax() ) {
            return view('Backend.contract.list-element-sales')->with(compact('contracts'));

        } else {
            $sales      = BackendUser::whereIn('role',[1,2])->pluck('name','id');
            $customers = Customer::where('role',0)->pluck('company_name','id');
            return view('Backend.contract.list-sales')->with(compact('contracts','customers','sales'));
        }
    }

    public function delete_property(){
        $delete_property = ContractTransaction::find(Request::get('id_property'));
        $delete_property->delete();

        return redirect('customer/service/sales/contract/sign/form/'.Request::get('id_quotation').'/'.Request::get('id_customer'));
    }

    public function per($id = null){
        $quotation1 = new Quotation;
        $quotation1 = $quotation1->where('id', $id);
        $quotation1 = $quotation1->first();

//        $lead = new Customer;
//        $lead = $lead->where('id', $id);
//        $lead = $lead->first();

        $contract = new Contract;


        $date=date("Y-m-d");
        $cut_date_now=explode("-",$date);

        $singg = contract::whereYear('created_at', '=', $cut_date_now[0])
            ->whereMonth('created_at', '=', $cut_date_now[1])
            ->get();
        $sing=$singg->max('contract_code');

        $property = new Property;
        $property = $property->get();

        //dd($quotation1);
        return view('Backend.contract.contract_form')->with(compact('quotation1','sing','quo_id','contract','property'));
        //dd($id);
    }
}
