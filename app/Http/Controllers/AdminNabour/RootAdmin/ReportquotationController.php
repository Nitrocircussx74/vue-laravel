<?php

namespace App\Http\Controllers\AdminNabour\RootAdmin;

use Illuminate\Http\Request;
use Auth;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Property;
use App\PropertyUnit;
use App\User;
use App\Province;
use App\PropertyFeature;
use App\BillWater;
use App\BillElectric;
use App\PropertyContract;
use App\UserPropertyFeature;
use App\ManagementGroup;
use App\SalePropertyDemo;
use App\BackendModel\Package;
use App\service_quotation;
use App\BackendModel\Quotation;


class ReportquotationController extends Controller
{
    public function __construct () {
        $this->middleware('admin');
    }
    
    public function print_report($id)
    {
        $p = new Province;
        $provinces = $p->getProvince();
        //$property = PropertyContract::find($id);
        $property = Property::find($id);

        $property1 = PropertyContract::find($id);

        //dd($property1);

        $service = new package;
        $service = $service->where('status','2');
        $service = $service->get();

        $package = new package;
        $package = $package->where('status','1');
        $package = $package->get();

        $sale = User::where('id','!=',Auth::user()->id)
            ->where('role','=',4)
            ->orderBy('created_at','DESC')
            ->paginate(30);

        $service_quo = new service_quotation;
        $service_quo = $service_quo->where('property_id',$id);
        $service_quo = $service_quo->get();

        //dd($service_quo);

        $quotation = new quotation;
        $quotation = $quotation->where('property_id',$id);
        $quotation = $quotation->first();

        return view('Backend.property.report_quotation')->with(compact('provinces','property','property1','service','package','sale','service_quo','quotation'));
    }

    public function quotation_from($id)
    {

        $search = quotation::find($id);

        if(empty($search)){
            $p = new Province;
            $provinces = $p->getProvince();
            $property = PropertyContract::find($id);
            $property = Property::find($id);

            $property1 = PropertyContract::find($id);

            //dd($property1);

            $service = new package;
            $service = $service->where('status','2');
            $service = $service->get();

            $package = new package;
            $package = $package->where('status','1');
            $package = $package->get();

            $max_cus = new quotation;
            $max_cus = $max_cus->max('quotation_number');

            //return view('Backend.property.quotation',['property'=>$property,'provinces'=>$provinces,'service'=>$service,'package'=>$package,'id'=>$id,'property1'=>$property1]);
            return view('Backend.property.quotation')->with(compact('property','provinces','service','package','id','property1','max_cus'));
        }else{
            return redirect('root/admin/list_quotation');
        }

    }

}
