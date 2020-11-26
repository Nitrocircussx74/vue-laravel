<?php

namespace App\Http\Controllers\AdminNabour\RootAdmin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Auth;
use Redirect;
use Excel;
use DB;

use App\Property;
use App\PropertyUnit;
use App\User;
use App\Province;
use App\PropertyFeature;
use App\BillWater;
use App\BillElectric;
use App\PropertyContract;
use App\UserPropertyFeature;
use App\SalePropertyDemo;

class ReportexcelpropertyController extends Controller
{
    public function __construct () {
        $this->middleware('admin');
    }
    
    public function index()
    {
        $props = new Property;
        $props = $props->where('is_demo',false);
//        $p_rows = $props::all();
        $p_rows = $props->orderBy('created_at','desc')->get();
        //dump($p_rows->toArray());
        $filename = "รายงานนิติบุคคล";
        $demo=0;
        return view('Backend.property.report_excel_property')->with(compact('p_rows','filename','demo'));
    }

    public function index_demo()
    {
        $props = new Property;
        $props = $props->where('is_demo',true);
        $props = $props->whereHas('sale_property', function ($q) {
            $q->where('property_test_name', '!=','');
        });
        $props = $props->whereHas('sale_property', function ($q) {
            $q->where('contact_name', '!=','');
        });

        $p_rows = $props->orderBy('created_at','desc')->get();

        $demo=1;

        $sale = User::where('id','!=',Auth::user()->id)
            ->where('role','=',4)
            ->orderBy('created_at','DESC')
            ->paginate(30);

        $filename = "รายงานนิติบุคคลทดลองใช้";
        return view('Backend.property.report_excel_property')->with(compact('p_rows','filename','demo','sale'));
    }

}
