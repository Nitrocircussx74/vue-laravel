<?php

namespace App\Http\Controllers\AdminNabour\RootAdmin;

use Request;
use Auth;
use Redirect;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;
# Model
use App\Property;
use App\PropertyUnit;
use App\User;
use App\Province;
use App\PropertyFeature;
use App\BillWater;
use App\BillElectric;
use App\PropertyContract;
use DB;
use Dompdf\Dompdf;

class PrintpropertyController extends Controller
{ 
    public function __construct () {
        $this->middleware('admin');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function print_report($id)
    {

//        $users=DB::select("SELECT property_id,contract_length,free,contract_date,remark,contract_sign_no,info_delivery_date,package,info_used_date,person_name,juristic_person_name_th,area_size,unit_size,construction_by,address_th,street_th,postcode,property_name_th FROM property_nb_contract
//                            INNER JOIN property ON property_nb_contract.property_id = property.id WHERE property_id='$id'");
//        $province=DB::select("SELECT province,name_th FROM property
//                            INNER JOIN province ON property.province::varchar = province.code
//                            WHERE id='$id'");
        $p = new Province;
        $provinces = $p->getProvince();
        $property_contract_data = PropertyContract::find($id);
        $property = Property::find($id);
        //dd($property);
        return view ('property.reportproperty',['property'=>$property,'property_contract_data'=>$property_contract_data,'provinces'=>$provinces]);
        // return ($id);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
