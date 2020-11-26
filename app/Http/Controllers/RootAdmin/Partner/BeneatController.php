<?php

namespace App\Http\Controllers\RootAdmin\Partner;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Excel;

use DB;
use App\Model\Partner\BeneatOrder;
use App\Province;

use GuzzleHttp\Client as GuzzleClient;

class BeneatController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
       
        
        $orders = $this->MakeQuery($request);
        $orders = $orders->paginate(50);
        if( $request->ajax() ) {
            return view('partner.beneat.list-element')->with(compact('orders'));
        } else {
            $p = new Province;
            $provinces = $p->getProvince();
            $property_list = array(''=> trans('messages.Signup.select_property') );
            return view('partner.beneat.list')->with(compact('orders','provinces','property_list'));
        }
        
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function view($id)
    {
        $order = BeneatOrder::find($id);
        return view('partner.beneat.view')->with(compact('order'));
    }

    /**
     * Export the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        $orders = $this->MakeQuery($request);
        $orders = $orders->orderBy('created_at','desc')->get();
        $filename = "Beneat order report";

        Excel::create($filename, function ($excel) use ($orders) {
            $excel->sheet("Orders", function ($sheet) use ($orders) {
                $sheet->loadView('partner.beneat.export')->with(compact('orders'));
            });
        })->export('xlsx');
    }

     /**
     * Make query for data retriving.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return BeneatOrder oject
     */

    private function MakeQuery ($request) 
    {
        $name           = $request->get('name');
        $created        = $request->get('created_date');
        $status         = $request->get('status');
        $property_id    = $request->get('property_id');

        $item = new BeneatOrder;
       
        if (!empty($name)) {
            $item = $item->where('name','like',"%".$name."%");
        }
        if (!empty($created)) {
            $item = $item->whereRaw(DB::raw("DATE(created_at) = '".str_replace('/','-',$created)."'"));
        }
        
        if (!empty($status)) {
            if( $status == 'cancel' ) {
                $item = $item->where('cancel', '!=', 0);
            } else {
                $item = $item->where('cancel', 0);
            }
        }

        if (!empty($property_id)) {
            $item = $item->where('property_ref',$property_id);
        }

        return $item;
    }
}
