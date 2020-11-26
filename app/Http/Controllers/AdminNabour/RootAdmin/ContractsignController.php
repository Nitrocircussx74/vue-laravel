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
use App\BackendModel\ContractDetail;

class ContractsignController extends Controller
{
    public function __construct () {
        $this->middleware('admin');
    }

    public function index($id = null)
    {
        $quotation = new Contract;
        //$quotation = $quotation->where('lead_id',$lead_id);
        $quotation = $quotation->where('id',$id);
        $quotation = $quotation->first();
        // dd($quotation->quotation_id);


        //dd($quotation_code);
        $package = new Products;
        $package = $package->where('status', '1');
        $package = $package->get();
        // dd( $package);

        $quotation_service = new QuotationTransaction;
        $quotation_service = $quotation_service->where('quotation_id', $quotation->quotation_id);
        $quotation_service = $quotation_service->get();
        // dd($quotation_service);
        $contract_property = new ContractTransaction;
        $contract_property = $contract_property->where('contract_id', $quotation->contract_code);
        $contract_property = $contract_property->get();

        $type_array = array();
        foreach ($contract_property as $row){
            //dump($row->property_id)  ;
            $type = property_db::find($row->property_id);
            $type = $type->toArray();

            $type_array[] = $type;

        }
        //dump($type_array);
        $p = new Province;
        $provinces = $p->getProvince();

        return view('Backend.contract.contractdocument')->with(compact('quotation','provinces','quotation_service','package','type_array','contract_property'));
    }
// ----------------

 public function view_contract($contract_code = null,Request $request)
 {
   if($contract_code){
        $search = new Contract;
        $search = $search->where('contract_code', $contract_code);
        $search = $search->first();
        // dd($search);
        if(!empty($search)){
            $quotation1 = new Quotation;
            $quotation1 = $quotation1->where('id',$search->quotation_id);
            $quotation1 = $quotation1->first();
        
            $contract = new Contract;
            $contract = $contract->where('quotation_id', $quotation1->id);
            $contract = $contract->where('id', $search->id);
            $contract = $contract->first();
            $count = $contract->where('quotation_id', $quotation1->id)->where('id', $search->id)->where('status','=',1)->count();
            $count_ = $contract->where('customer_id', $quotation1->customer_id)->where('id', $search->id)->where('status','=',1)->count();
            
    
            $contract_property = new ContractTransaction;
            $contract_property = $contract_property->where('contract_id', $contract->contract_code);
            $contract_property = $contract_property->get();
        
            // dd($contract_property);
            $contract_detail = new ContractDetail();
            $contract_detail = $contract_detail->where('contract_code', $contract->contract_code);
            $contract_detail = $contract_detail->get();
        
            $quotation = new Quotation;
            $quotation = $quotation->where('id', $search->quotation_id);
            $quotation = $quotation->first();

            $quotation_service = new QuotationTransaction;
            $quotation_service = $quotation_service->where('quotation_id', $quotation1->id);
            $quotation_service = $quotation_service->get();
            // dd($quotation_service);

            $property = new Property;
            $property = $property->get();
            return view('Backend.contract.contract_update')->with(compact('quotation1','contract','search','count','count_','quotation','quotation_service','property','contract_property','contract_detail'));

            // dd($quotation_service);
        } else {
            $quotation1 = new Quotation;
            $quotation1 = $quotation1->where('id', $search->quotation_id);
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
                return view('Backend.contract.contract_form')->with(compact('quotation1','sing','contract','property'));
        }
    }
}

    public function create($quotation_id = null,$id = null)
    {
        $search = new Contract;
        $search = $search->find($id);
        if(!empty($search)){
            $quotation1 = Quotation::find($search->quotation_id);
            
            $contract = new Contract;
            $contract = $contract->where('quotation_id', $quotation_id);
            $contract = $contract->where('id', $id);
            $contract = $contract->first();

            $contract_property = new ContractTransaction;
            $contract_property = $contract_property->where('contract_id', $contract->contract_code);
            $contract_property = $contract_property->get();

            $contract_detail = new ContractDetail();
            $contract_detail = $contract_detail->where('contract_code', $contract->contract_code);
            $contract_detail = $contract_detail->get();

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
            return view('Backend.contract.contract_update')->with(compact('quotation1','contract','search','count','count_','quotation','quotation_service','property','contract_property','id','contract_detail'));

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

            return view('Backend.contract.contract_form')->with(compact('quotation1','sing','contract','property'));
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

        if(!empty(Request::get('property_id'))){
            $count = count(Request::get('property_id'));
            for ($i=0;$i<$count;$i++){
                $property_id = explode("|",Request::get('property_id')[$i]);

                $ContractTransaction = new ContractTransaction;
                $ContractTransaction->contract_id          = Request::get('contract_code');
                $ContractTransaction->property_name        = Request::get('property_name')[$i];
                $ContractTransaction->property_id          = $property_id[0];
                $ContractTransaction->start_date           = Request::get('start_date')[$i];
                $ContractTransaction->end_date             = Request::get('end_date')[$i];
                $ContractTransaction->product              = Request::get('product')[$i];
                $ContractTransaction->product_detail       = Request::get('product_detail')[$i];
                $ContractTransaction->save();
            }
        }

        return redirect('customer/service/quotation/add/'.Request::get('customer_id'));
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
//        $contract->detail_service       = Request::get('detail_service');
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
                $ContractTransaction->product              = Request::get('product')[$i];
                $ContractTransaction->product_detail       = Request::get('product_detail')[$i];
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
                $ContractTransaction->product              = Request::get('product_update')[$i];
                $ContractTransaction->product_detail       = Request::get('product_detail_update')[$i];
                $ContractTransaction->save();
                //dump($ContractTransaction);
            }
        }
        return redirect('customer/service/quotation/add/'.Request::get('customer_id'));
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

        //dd($customer);
        return redirect('contract/list');
    }

    public function contractList () {
        $contracts = new Contract;

        if( Request::get('c_no') ) {
            $contracts = $contracts->where('contract_code','like','%'.Request::get('c_no').'%');
        }


        if(Request::get('c_id')) {
            $contracts = $contracts->whereHas('customer', function ($q) {
                $q ->where('company_name','like',"%".Request::get('c_id')."%");
            });
        }

        if( Request::get('sale_id') ) {
            $contracts = $contracts->where('sales_id',Request::get('sale_id'));
        }

        $contracts = $contracts->orderBy('contract_code','desc')->paginate(500);

        if( Request::ajax() ) {
            return view('Backend.contract.list-element')->with(compact('contracts'));

        } else {
            $sales      = BackendUser::where('role',4)->lists('name','id')->toArray();
            $customers = Customer::where('role',0)->lists('company_name','id')->toArray();
            return view('Backend.contract.list')->with(compact('contracts','customers','sales'));
        }
    }

    public function delete_property(){
        $delete_property = ContractTransaction::find(Request::get('id_property'));
        $delete_property->delete();

        return redirect('customer/service/contract/sign/form/'.Request::get('id_quotation').'/'.Request::get('id_customer'));
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

    public function delete_contract(){
        $contract = contract::find(Request::get('id2'));
        $contract->delete();

        $ContractTransaction=ContractTransaction::where('contract_id',Request::get('id_contract'));
        $ContractTransaction->delete();

        return redirect('/contract/list');

    }

    public function delete_detail_contract(){
        $detail = ContractDetail::find(Request::get('id'));
        $detail->delete();

        return redirect('customer/service/contract/sign/form/'.Request::get('id_quotation').'/'.Request::get('id_customer'));
    }
}
