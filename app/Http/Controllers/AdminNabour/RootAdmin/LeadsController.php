<?php

namespace App\Http\Controllers\AdminNabour\RootAdmin;

use App\Http\Controllers\Controller;
use Request;
use Auth;
use Redirect;

//Model
use App\BackendModel\User;
use App\Province;
use App\BackendModel\LeadTable;
use App\BackendModel\Customer;
use App\BackendModel\Property as BackendProperty;
use App\SalePropertyDemo;
use App\Property;

class LeadsController extends Controller
{
    public function __construct () {
        $this->middleware('admin');
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

            if (Request::get('status_leads')) {
                $p_rows = $p_rows->where('status_leads', '=', Request::get('status_leads'));
            }
            //dd(Request::get('channel_id'));
        }

        $p = new Province;
        $provinces = $p->get();

        $sale = new User;
        $sale = $sale->where('role','=',2);
        $sale = $sale->get();



        $p_rows = $p_rows->where('role','=',1);
        $p_rows = $p_rows->orderBy('created_at','desc')->paginate(50);

        $property = new BackendProperty;
        $property = $property->get();


        $property_demo = new SalePropertyDemo;
        $property_demo = $property_demo->whereHas('property', function ($q) {
            $q ->where('status','=',0);
        });
        $property_demo = $property_demo->get();


        if(!Request::ajax()) {
            return view('Backend.lead.list_lead')->with(compact('provinces', 'sale','p_rows','property','property_demo'));
        }else{
            return view('Backend.lead.list_lead_element')->with(compact('provinces', 'sale','p_rows','property','property_demo'));
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
            $lead->sale_id          =Request::get('sale_id');
            $lead->company_name     =Request::get('company_name');
            $lead->tax_id           =Request::get('tax_id');
            $lead->role             =1;
            $lead->property_name    = Request::get('property_name');
            $lead->save();
            //dump($lead->toArray());
        }
        $_lead = new Customer;
        $_lead = $_lead->where('role','=',1);
        $_lead->get();

        return redirect('customer/leads/list')->with(compact('_lead'));

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
            $lead->type         =empty(Request::get('type'))?null:Request::get('type');
            $lead->tax_id           =Request::get('tax_id');
            $lead->property_name    = Request::get('property_name');
            $lead->save();
            //dump($lead->toArray());
        }

        return redirect('customer/leads/list');
    }

    public function destroy()
    {
        $id = Request::input('id2');
        $lead = Customer::find($id);
        $lead->delete();
        //dd($id);
        return redirect('customer/Lead_form/add/list');
    }

    public function note(){
        //dd(Request::get('note_id'));
        $note = Customer::find(Request::input('note_id'));
        $note->note = Request::get('note_detail');
        $note->save();
        return redirect('customer/Lead_form/add/list');
        //dd($note);
    }

    public function importCSV(){
        return view('Backend.lead.import_leads');
    }

    public function importCSVdata(){

        //dump(Request::input('data_import'));

        $data_array=array();
        $data = explode(PHP_EOL,Request::get('data_import'));
        foreach ($data as $datas){
            $data_array[] = str_getcsv($datas);
        }

        $result = $this->checkUnitFormat($data_array);

        if($result['result'] == true){
            foreach ($data_array as $row){
                $rows = new Customer;
                $rows->firstname = $row[0];
                $rows->lastname  = $row[1];
                $rows->phone    = $row[2];
                $rows->email    = $row[3];
                $rows->address  = $row[4];
                $rows->province = $row[5];
                $rows->postcode = $row[6];
                $rows->sale_id  = $row[7];
                $rows->role     = 1;

                $rows->save();
            }
        }else{
            $msg=$result['messages'];
            return view('Backend.lead.import_leads')->with(compact('msg'));
        }

        //dump($rows);
        //dump($data_array);
        return redirect('customer/Lead_form/add/list');
    }

    private function checkUnitFormat ($data) {
        $valid = true;
        $msg = "";
        if(!empty($data)) {
            foreach($data as $row => $datas) {
                if(count($datas) != 8) {
                    $valid = false;
                    $msg .= 'ข้อมูลบรรทัดที่ '.($row +1).' จำนวนข้อมูลไม่ถูกต้อง<br/>';
                }
            }
            //return true;
        } else {
            $valid =  false;
            $msg = 'ไม่มีข้อมูล';
        }
        return array('result'=> $valid, 'messages' => $msg);
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
