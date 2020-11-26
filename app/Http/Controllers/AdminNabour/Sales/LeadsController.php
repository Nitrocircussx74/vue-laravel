<?php

namespace App\Http\Controllers\AdminNabour\Sales;

use App\Http\Controllers\Controller;
use Request;
use Auth;
use Redirect;

//Model
use App\BackendModel\User;
use App\Province;
use App\BackendModel\Customer;
use App\Property;
use App\SalePropertyDemo;

class LeadsController extends Controller
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

            if (Request::get('sale_id')) {
                $p_rows = $p_rows->where('sale_id', '=', Request::get('sale_id'));
            }

            if (Request::get('channel_id')) {
                $p_rows = $p_rows->where('channel', '=', Request::get('channel_id'));
            }

            if (Request::get('type_property')) {
                $p_rows = $p_rows->where('type', '=', Request::get('type_property'));
            }
            //dd(Request::get('channel_id'));
        }

        $p = new Province;
        $provinces = $p->get();

        $sale = new User;
        $sale = $sale->where('role','=',2);
        $sale = $sale->get();


        $p_rows = $p_rows->where('role','=',1)->where('sale_id','=',Auth::user()->id);
        $p_rows = $p_rows->orderBy('created_at','desc')->paginate(50);

        $property = SalePropertyDemo::with('property')->where('sale_id','=',Auth::user()->id)->where('status','=',0)->get();

        if(!Request::ajax()) {
            return view('Backend.lead.list_lead')->with(compact('provinces', 'sale', '_lead','p_rows','property'));
        }else{
            return view('Backend.lead.list_lead_element')->with(compact('provinces', 'sale', '_lead','p_rows','property'));
        }
    }

    public function create()
    {
        if(Request::isMethod('post')) {
            $lead = new Customer;
            $lead->firstname        =Request::get('firstname');
            $lead->lastname         =Request::get('lastname');
            $lead->phone            =Request::get('phone');
            $lead->email            =Request::get('email');
            $lead->address          =Request::get('address');
            $lead->province         =Request::get('province');
            $lead->postcode         =Request::get('postcode');
            $lead->channel          =Request::get('channel');
            $lead->type             =empty(Request::get('type'))?null:Request::get('type');
            $lead->sales_status     =Request::get('sales_status');
            $lead->sale_id          =Auth::user()->id;
            $lead->company_name     =Request::get('company_name');
            $lead->tax_id           =Request::get('tax_id');
            $lead->role             =1;
            $lead->save();
            //dump($lead->toArray());
        }
        $_lead = new Customer;
        $_lead = $_lead->where('role','=',1);
        $_lead->get();

        if(Auth::user()->role !=2){
            return redirect('customer/leads/list')->with(compact('_lead'));
        }else{
            return redirect('customer/sales/leads/list')->with(compact('_lead'));
        }


    }


    public function edit()
    {
        if(Request::isMethod('post')) {
            $_lead = Customer::find(Request::get('id'));

            //dump($_lead->toArray());

            $p = new Province;
            $provinces = $p->get();

            $sale = new User;
            $sale = $sale->where('role','=',2);
            $sale = $sale->get();

            return view('Backend.lead.lead_update')->with(compact('_lead','provinces','sale'));
        }
    }

    public function update()
    {
        if(Request::isMethod('post')) {

            $id = Request::input('lead_id');
            $lead = Customer::find($id);
            $lead->fill(Request::all());
            $lead->status_leads = Request::get('status_leads');
            $lead->type             =empty(Request::get('type'))?null:Request::get('type');
            $lead->tax_id           =Request::get('tax_id');
            $lead->save();
            //dump($lead->toArray());
        }

        if(Auth::user()->role !=2) {
            return redirect('customer/leads/list');
        }else{
            return redirect('customer/sales/leads/list');
        }
    }

    public function destroy()
    {
        $id = Request::input('id2');
        $lead = Customer::find($id);
        $lead->delete();
        //dd($id);

        if(Auth::user()->role !=2) {
            return redirect('customer/Lead_form/add/list');
        }else{
            return redirect('customer/sales/Lead_form/add/list');
        }

    }

    public function note(){
        //dd(Request::get('note_id'));
        $note = Customer::find(Request::input('note_id'));
        $note->note = Request::get('note_detail');
        $note->save();

        if(Auth::user()->role !=2) {
            return redirect('customer/Lead_form/add/list');
        }else{
            return redirect('customer/sales/Lead_form/add/list');
        }
    }

    public function view_data(){
        if(Request::isMethod('post')) {
            $_lead = Customer::find(Request::get('id'));

            //dump($_lead->toArray());

            $p = new Province;
            $provinces = $p->getProvince();

            $sale = new User;
            $sale = $sale->where('role','=',2);
            $sale = $sale->get();

            return view('Backend.lead.lead_view')->with(compact('_lead','provinces','sale'));
        }
    }
}
