<?php

namespace App\Http\Controllers\AdminNabour\RootAdmin;

use App\Http\Controllers\Controller;

use Request;
use Auth;
use Redirect;

use App\Province;
use App\PropertyContract;
use App\BackendModel\service_quotation;
use App\BackendModel\Quotation;
use App\BackendModel\Customer;
use App\BackendModel\Contract;
use App\BackendModel\User as BackendUser;
use App\BackendModel\QuotationTransaction;
use App\BackendModel\Products;
use App\BackendModel\User;
use App\BackendModel\Property;
use DB;
use Excel;
use View;
use App;
use PHPExcel_Style_Border;

class QuotationreportController extends Controller
{

    public function __construct () {
        $this->middleware('admin');
    }
    
    public function index()
    {
        $p_rows = new Customer;

        if (Request::ajax()) {
            if (Request::get('name')) {
                $p_rows = $p_rows->where('firstname', 'like', "%" . Request::get('name') . "%")
                    ->orWhere('lastname', 'like', "%" . Request::get('name') . "%");
            }

            if (Request::get('sale_id')) {
                $p_rows = $p_rows->where('sale_id', Request::get('sale_id'));
            }

        }

        $p_rows = $p_rows->where('role', '=', 0);
        //$p_rows = $p_rows->with('quotation')->groupBy('id')->count('*','as','count');
        $p_rows = $p_rows->orderBy('created_at', 'desc')->paginate(50);

        $sale = new User;
        $sale = $sale->where('role', '=', 2);
        $sale = $sale->get();


        //dd($count);

        if (Request::ajax()) {
            return view('Backend.report_quotation.report_list_element')->with(compact('p_rows', 'sale'));

        } else {
            return view('Backend.report_quotation.report_list')->with(compact('p_rows', 'sale'));
        }
    }

    public function view($id = null)
    {
        $quotation = new quotation;
        $quotation = $quotation->where('lead_id', '=', $id)->get();

        return view('Backend.report_quotation.detail_quotation')->with(compact('quotation'));
    }

    public function detail()
    {
        if (Request::isMethod('post')) {

            $quotation = new Quotation;
            $quotation = $quotation->where('quotation_code', Request::get('id'));
            $quotation = $quotation->first();

            $quotation_service = new QuotationTransaction;
            $quotation_service = $quotation_service->where('quotation_code', Request::get('id'));
            $quotation_service = $quotation_service->get();

            return view('Backend.report_quotation.quotation_detail')->with(compact('quotation', 'quotation_service'));
        }
    }

    public function report()
    {

        $p_rows = Quotation::selectRaw('lead_id,SUM(product_price_with_vat) as sum,SUM(grand_total_price) as sum_total,SUM(product_vat) as sum_vat,count(quotation_code) as count')
            ->groupBy('lead_id')->paginate(50);

        if (Request::ajax()) {
            return view('Backend.report_quotation.report_quotation_detail_element')->with(compact('p_rows'));
        } else {
            return view('Backend.report_quotation.report_quotation_detail')->with(compact('p_rows'));
        }

    }

    public function excel()
    {

        $quotation = Quotation::selectRaw('lead_id,SUM(product_price_with_vat) as sum,SUM(grand_total_price) as sum_total,SUM(product_vat) as sum_vat,count(quotation_code) as count')
            ->groupBy('lead_id')->get();
            // ->where('status', '=', 1)
        $filename = "รายงานใบเสนอราคาที่ออกจากระบบ";

        // dd( $quotation);
        Excel::create($filename, function ($excel) use ( $quotation) {
            $excel->sheet("Quotation_Output", function ($sheet) use ($quotation) {
                $sheet->setWidth(array(
                    'A' => 10,
                    'B' => 40,
                    'C' => 20,
                    'D' => 30,
                    'E' => 30,
                    'F' => 30,
                ));

                $sheet->loadView('Backend.report_quotation.report_quotation_excel')->with(compact('quotation'));
            });
        })->download('xlsx');

    }

    public function ratio()
    {

        $p_rows = new Customer;

        if (Request::ajax()) {
            $from = str_replace('/', '-', Request::get('from-date'));
            $to = str_replace('/', '-', Request::get('to-date'));
            $channel = Request::get('channel_id');
            $type = Request::get('type_id');


            if (Request::get('from-date')) {
                $p_rows = $p_rows->where('created_at','>=', $from . " 00:00:00");
            }

            if (Request::get('to-date')) {
                $p_rows = $p_rows->where('created_at', "<=", $to . " 00:00:00");
            }


            if (Request::get('channel_id') != null) {
                $p_rows = $p_rows->where('channel', '=', Request::get('channel_id'));
            }

            if (Request::get('type_id') != null) {
                $p_rows = $p_rows->where('type', '=', Request::get('type_id'));
            }
        }

        $p_rows = $p_rows->orderby('created_at','desc')->paginate(50);


        if (!Request::ajax()) {
            $from = null;
            $to = null;
            $channel = null;
            $type = null;
            return view('Backend.report_quotation.report_quotation_ratio_list')->with(compact('p_rows', 'from', 'to', 'channel', 'type'));
        } else {
            //dump($p_rows->toArray());
            return view('Backend.report_quotation.report_quotation_ratio_list_element')->with(compact('p_rows', 'from', 'to', 'channel', 'type'));
        }
    }

    public function excel_ratio()
    {
        $p_rows = new Customer;

        if (Request::isMethod('post')) {

            $from = str_replace('/', '-', Request::get('from-date'));
            $to = str_replace('/', '-', Request::get('to-date'));
            $channel = Request::get('channel_id');
            $type = Request::get('type_id');

            if (Request::get('from-date')) {
                $p_rows = $p_rows->where('created_at','>=', $from . " 00:00:00");
            }

            if (Request::get('to-date')) {
                $p_rows = $p_rows->where('created_at', "<=", $to . " 00:00:00");
            }

            if (Request::get('channel_id') != null) {
                $p_rows = $p_rows->where('channel', '=', Request::get('channel_id'));
            }

            if (Request::get('type_id') != null) {
                $p_rows = $p_rows->where('type', '=', Request::get('type_id'));
            }
        }
        $p_rows = $p_rows->orderby('created_at','desc')->get();

        $filename = "สถิติการเปลี่ยนจาก Leads เป็นลูกค้า";
        

        Excel::create($filename, function ($excel) use ($filename, $p_rows, $from, $to, $channel, $type) {
            $excel->sheet("Quotation_ration", function ($sheet) use ($filename,$p_rows, $from, $to, $channel, $type) {
                $sheet->setWidth(array(
                    'B' => 20,
                    'C' => 50,
                    'D' => 20,
                    'E' => 30,
                ));

                $sheet->loadView('Backend.report_quotation.report_quotation_ratio_excel')->with(compact('p_rows', 'filename', 'from', 'to', 'channel', 'type'));
            });
        })->download('xlsx');

    }


    public function chart_form()
    {
        $c_year = date('Y');
        $year = array('' => trans_choice('',1) );
        $plus = (App::getLocale() == 'th')?543:0;
        for ($i = $c_year+1; $i >= 1900; $i--) {
            $year += array($i=>$i+$plus);
        }

        $p_rows = new Customer;
        $p_rows = $p_rows->get();

        $sale = new User;
        $sale = $sale->where('role','=',2);
        $sale = $sale->get();

        return view('Backend.report_chart.report_chart_form')->with(compact('year','p_rows','sale'));
    }

    public function destroy($id)
    {
        //
    }

    public function date()
    {
        $p_rows = new Customer;

        if (Request::ajax()) {

            $from = str_replace('/', '-', Request::get('from-date'));
            $to = str_replace('/', '-', Request::get('to-date'));

            $channel = Request::get('channel_id');
            $type = Request::get('type_id');

            if (Request::get('from-date')) {

                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
                foreach ($month as $key => $value){
                    $date = array($from . " 00:00:00", $to . " 00:00:00");

                    $data = $p_rows
                        ->select(DB::raw('COUNT(*) as count'))
                        ->whereBetween('created_at', $date)
                        ->whereMonth('created_at','=',$key)
                        ->where('role','=','0')
                        ->get();
                    $data = $data->toArray();

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(*) as count'))
                        ->whereBetween('created_at', $date)
                        ->whereMonth('created_at','=',$key)
                        ->where('role','=','1')
                        ->get();

                    $_data = $_data->toArray();

                    if($_data[0]['count'] <= 0){
                        $information["leads"][] = 0;
                    }else{
                        $information["leads"][] = $_data[0]['count'];
                    }

                    if($data[0]['count'] <= 0){
                        $information["customer"][] = 0;
                    }else{
                        $information["customer"][] = $data[0]['count'];
                    }

                }
           }

            if (Request::get('channel_id') != null AND Request::get('year') != null) {
                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
                $date=date("Y-m-d");
                $cut_year=explode("-",$date);
                //dd($cut_year[0]);
                foreach ($month as $key => $value){

                    if(Request::get('channel_id') == 'all'){
                        $data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            //->where('channel','=',Request::get('channel_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',Request::get('year')-543)
                            ->where('role','=','0')
                            ->get();
                        $data = $data->toArray();

                        $_data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            //->where('channel','=',Request::get('channel_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',Request::get('year')-543)
                            ->where('role','=','1')
                            ->get();

                        $_data = $_data->toArray();
                    }else{
                        $data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            ->where('channel','=',Request::get('channel_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',Request::get('year')-543)
                            ->where('role','=','0')
                            ->get();
                        $data = $data->toArray();

                        $_data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            ->where('channel','=',Request::get('channel_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',Request::get('year')-543)
                            ->where('role','=','1')
                            ->get();

                        $_data = $_data->toArray();
                    }


                    if($_data[0]['count'] <= 0){
                        $information["leads"][] = 0;
                    }else{
                        $information["leads"][] = $_data[0]['count'];
                    }

                    if($data[0]['count'] <= 0){
                        $information["customer"][] = 0;
                    }else{
                        $information["customer"][] = $data[0]['count'];
                    }

                }
                //dd('ffff');
            }

            if (Request::get('channel_id') != null AND Request::get('year') == null) {
                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
                $date=date("Y-m-d");
                $cut_year=explode("-",$date);
                //dd($cut_year[0]);
                foreach ($month as $key => $value){

                    if(Request::get('channel_id') == 'all'){
                        $data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            //->where('channel','=',Request::get('channel_id'))
                            ->whereMonth('created_at','=',$key)
                            //->whereYear('created_at','=',$cut_year[0])
                            ->where('role','=','0')
                            ->get();
                        $data = $data->toArray();

                        $_data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            //->where('channel','=',Request::get('channel_id'))
                            ->whereMonth('created_at','=',$key)
                            //->whereYear('created_at','=',$cut_year[0])
                            ->where('role','=','1')
                            ->get();

                        $_data = $_data->toArray();
                    }else{
                        $data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            ->where('channel','=',Request::get('channel_id'))
                            ->whereMonth('created_at','=',$key)
                            //->whereYear('created_at','=',$cut_year[0])
                            ->where('role','=','0')
                            ->get();
                        $data = $data->toArray();

                        $_data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            ->where('channel','=',Request::get('channel_id'))
                            ->whereMonth('created_at','=',$key)
                            //->whereYear('created_at','=',$cut_year[0])
                            ->where('role','=','1')
                            ->get();

                        $_data = $_data->toArray();
                    }


                    if($_data[0]['count'] <= 0){
                        $information["leads"][] = 0;
                    }else{
                        $information["leads"][] = $_data[0]['count'];
                    }

                    if($data[0]['count'] <= 0){
                        $information["customer"][] = 0;
                    }else{
                        $information["customer"][] = $data[0]['count'];
                    }
                }
            }

            if (Request::get('type_id') != null AND Request::get('year') != null) {
                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');

                $date=date("Y-m-d");
                $cut_year=explode("-",$date);

                foreach ($month as $key => $value){

                    if(Request::get('type_id') == 'all'){
                        $data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            //->where('type','=',Request::get('type_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',Request::get('year')-543)
                            ->where('role','=','0')
                            ->get();
                        $data = $data->toArray();

                        $_data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                           // ->where('type','=',Request::get('type_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',Request::get('year')-543)
                            ->where('role','=','1')
                            ->get();

                        $_data = $_data->toArray();
                    }else{
                        $data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            ->where('type','=',Request::get('type_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',Request::get('year')-543)
                            ->where('role','=','0')
                            ->get();
                        $data = $data->toArray();

                        $_data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            ->where('type','=',Request::get('type_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',Request::get('year')-543)
                            ->where('role','=','1')
                            ->get();

                        $_data = $_data->toArray();
                    }


                    if($_data[0]['count'] <= 0){
                        $information["leads"][] = 0;
                    }else{
                        $information["leads"][] = $_data[0]['count'];
                    }

                    if($data[0]['count'] <= 0){
                        $information["customer"][] = 0;
                    }else{
                        $information["customer"][] = $data[0]['count'];
                    }
                }
            }

            if (Request::get('type_id') != null AND Request::get('year') == null) {
                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');

                $date=date("Y-m-d");
                $cut_year=explode("-",$date);

                foreach ($month as $key => $value){

                    if(Request::get('type_id') == 'all'){
                        $data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            //->where('type','=',Request::get('type_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',$cut_year[0])
                            ->where('role','=','0')
                            ->get();
                        $data = $data->toArray();

                        $_data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            //->where('type','=',Request::get('type_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',$cut_year[0])
                            ->where('role','=','1')
                            ->get();

                        $_data = $_data->toArray();
                    }else{
                        $data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            ->where('type','=',Request::get('type_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',$cut_year[0])
                            ->where('role','=','0')
                            ->get();
                        $data = $data->toArray();

                        $_data = $p_rows
                            ->select(DB::raw('COUNT(*) as count'))
                            ->where('type','=',Request::get('type_id'))
                            ->whereMonth('created_at','=',$key)
                            ->whereYear('created_at','=',$cut_year[0])
                            ->where('role','=','1')
                            ->get();

                        $_data = $_data->toArray();
                    }


                    if($_data[0]['count'] <= 0){
                        $information["leads"][] = 0;
                    }else{
                        $information["leads"][] = $_data[0]['count'];
                    }

                    if($data[0]['count'] <= 0){
                        $information["customer"][] = 0;
                    }else{
                        $information["customer"][] = $data[0]['count'];
                    }
                }
            }

            if (Request::get('year') != null AND Request::get('channel_id') == null AND Request::get('type_id') == null) {
               // dd(Request::get('year'));
                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
                foreach ($month as $key => $value){

                    $data = $p_rows
                        ->select(DB::raw('COUNT(*) as count'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year')-543)
                        ->where('role','=','0')
                        ->get();
                    $data = $data->toArray();

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(*) as count'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year')-543)
                        ->where('role','=','1')
                        ->get();

                    $_data = $_data->toArray();

                    if($_data[0]['count'] <= 0){
                        $information["leads"][] = 0;
                    }else{
                        $information["leads"][] = $_data[0]['count'];
                    }

                    if($data[0]['count'] <= 0){
                        $information["customer"][] = 0;
                    }else{
                        $information["customer"][] = $data[0]['count'];
                    }
                }
            }

            //dd($information);
            $p_rows = $p_rows->where('active_status', '=', 't')->get();
            return response()->json( $information );
           // dd($p_rows);
        }
    }

    public function quotation(){
    $p_rows = new Quotation;

    if (Request::ajax()) {
        if (Request::get('name') != null AND Request::get('year1') != null) {

            $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
            foreach ($month as $key => $value){

                if(Request::get('name') == 'all'){
                    $data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
//                        ->select(DB::raw('SUM(product_price_with_vat) as sum,COUNT(id) as count'))
                        //->where('lead_id','=', Request::get('name'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year1')-543)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        //->where('lead_id','=', Request::get('name'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year1')-543)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved
                }else{
                    $data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
//                        ->select(DB::raw('SUM(product_price_with_vat) as sum,COUNT(id) as count'))
                        ->where('lead_id','=', Request::get('name'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year1')-543)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        ->where('lead_id','=', Request::get('name'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year1')-543)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved
                }


                if($data[0]['count'] <=0){
                    $information["approved"][] = 0;
                }else{
                    $information["approved"][] = $data[0]['count'];
                }

                if($_data[0]['count'] <= 0 ){
                    $information["_approved"][] = 0;
                }else{
                    $information["_approved"][] = $_data[0]['count'];
                }
            }
        }

        if (Request::get('name') != null AND Request::get('year1') == null) {

            $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
            foreach ($month as $key => $value){

                if(Request::get('name') == 'all'){
                    $data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
//                        ->select(DB::raw('SUM(product_price_with_vat) as sum,COUNT(id) as count'))
                       // ->where('lead_id','=', Request::get('name'))
                        ->whereMonth('created_at','=',$key)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        //->where('lead_id','=', Request::get('name'))
                        ->whereMonth('created_at','=',$key)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved
                }else{
                    $data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
//                        ->select(DB::raw('SUM(product_price_with_vat) as sum,COUNT(id) as count'))
                        ->where('lead_id','=', Request::get('name'))
                        ->whereMonth('created_at','=',$key)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        ->where('lead_id','=', Request::get('name'))
                        ->whereMonth('created_at','=',$key)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved
                }



                if($data[0]['count'] <=0){
                    $information["approved"][] = 0;
                }else{
                    $information["approved"][] = $data[0]['count'];
                }

                if($_data[0]['count'] <= 0 ){
                    $information["_approved"][] = 0;
                }else{
                    $information["_approved"][] = $_data[0]['count'];
                }
            }
        }

        if (Request::get('sale_id') != null AND Request::get('year1') !=null) {

            $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
            foreach ($month as $key => $value){

                if(Request::get('sale_id') == 'all'){
                    $data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        //->where('sales_id','=', Request::get('sale_id'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year1')-543)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        //->where('sales_id','=', Request::get('sale_id'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year1')-543)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved
                }else{
                    $data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        ->where('sales_id','=', Request::get('sale_id'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year1')-543)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        ->where('sales_id','=', Request::get('sale_id'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year1')-543)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved
                }


                if($data[0]['count'] <=0){
                    $information["approved"][] = 0;
                }else{
                    $information["approved"][] = $data[0]['count'];
                }

                if($_data[0]['count'] <= 0 ){
                    $information["_approved"][] = 0;
                }else{
                    $information["_approved"][] = $_data[0]['count'];
                }
            }
        }

        if (Request::get('sale_id') != null AND Request::get('year1') == null) {

            $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
            foreach ($month as $key => $value){

                if(Request::get('sale_id') == 'all'){
                    $data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        //->where('sales_id','=', Request::get('sale_id'))
                        ->whereMonth('created_at','=',$key)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        //->where('sales_id','=', Request::get('sale_id'))
                        ->whereMonth('created_at','=',$key)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved
                }else{
                    $data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        ->where('sales_id','=', Request::get('sale_id'))
                        ->whereMonth('created_at','=',$key)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('COUNT(id) as count'))
                        ->where('sales_id','=', Request::get('sale_id'))
                        ->whereMonth('created_at','=',$key)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved
                }



                if($data[0]['count'] <=0){
                    $information["approved"][] = 0;
                }else{
                    $information["approved"][] = $data[0]['count'];
                }

                if($_data[0]['count'] <= 0 ){
                    $information["_approved"][] = 0;
                }else{
                    $information["_approved"][] = $_data[0]['count'];
                }

            }
        }

        if (Request::get('year1') != null AND Request::get('name') == null AND Request::get('sale_id') == null) {
            $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
            foreach ($month as $key => $value){

                $data = $p_rows
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',Request::get('year1')-543)
                    ->where('status','=','1')
                    ->get();
                $data = $data->toArray();// quotation approved

                $_data = $p_rows
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',Request::get('year1')-543)
                    ->where('status','=','0')
                    ->get();

                $_data = $_data->toArray();// quotation none approved


                if($data[0]['count'] <=0){
                    $information["approved"][] = 0;
                }else{
                    $information["approved"][] = $data[0]['count'];
                }

                if($_data[0]['count'] <= 0 ){
                    $information["_approved"][] = 0;
                }else{
                    $information["_approved"][] = $_data[0]['count'];
                }
            }
            // dd($information);
        }
    }

    return response()->json( $information );

}

    public function sum(){
        $p_rows = new Quotation;

        if (Request::ajax()) {
            if (Request::get('name')) {

                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
                foreach ($month as $key => $value){

                    if(Request::get('name') == 'all'){
                        $data = $p_rows
                            ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                           // ->where('lead_id','=', Request::get('name'))
                            ->whereMonth('created_at','=',$key)
                            ->where('status','=','1')
                            ->get();
                        $data = $data->toArray();// quotation approved

                        $_data = $p_rows
                            ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                            //->where('lead_id','=', Request::get('name'))
                            ->whereMonth('created_at','=',$key)
                            ->where('status','=','0')
                            ->get();

                        $_data = $_data->toArray();// quotation none approved
                    }else{
                        $data = $p_rows
                            ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                            ->where('lead_id','=', Request::get('name'))
                            ->whereMonth('created_at','=',$key)
                            ->where('status','=','1')
                            ->get();
                        $data = $data->toArray();// quotation approved

                        $_data = $p_rows
                            ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                            ->where('lead_id','=', Request::get('name'))
                            ->whereMonth('created_at','=',$key)
                            ->where('status','=','0')
                            ->get();

                        $_data = $_data->toArray();// quotation none approved
                    }


                    if($data[0]['sum'] <=0){
                        $information["approved"][] = 0;
                    }else{
                        $information["approved"][] = $data[0]['sum'];
                    }

                    if($_data[0]['sum'] <=0){
                        $information["_approved"][] = 0;
                    }else{
                        $information["_approved"][] = $_data[0]['sum'];
                    }


                }
            }

            if (Request::get('sale_id')) {

                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
                foreach ($month as $key => $value){

                    if(Request::get('sale_id') == 'all'){
                        $data = $p_rows
                            ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                            //->where('sales_id','=', Request::get('sale_id'))
                            ->whereMonth('created_at','=',$key)
                            ->where('status','=','1')
                            ->get();
                        $data = $data->toArray();// quotation approved

                        $_data = $p_rows
                            ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                            //->where('sales_id','=', Request::get('sale_id'))
                            ->whereMonth('created_at','=',$key)
                            ->where('status','=','0')
                            ->get();

                        $_data = $_data->toArray();// quotation none approved
                    }else{
                        $data = $p_rows
                            ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                            ->where('sales_id','=', Request::get('sale_id'))
                            ->whereMonth('created_at','=',$key)
                            ->where('status','=','1')
                            ->get();
                        $data = $data->toArray();// quotation approved

                        $_data = $p_rows
                            ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                            ->where('sales_id','=', Request::get('sale_id'))
                            ->whereMonth('created_at','=',$key)
                            ->where('status','=','0')
                            ->get();

                        $_data = $_data->toArray();// quotation none approved
                        }


                    if($data[0]['sum'] <=0){
                        $information["approved"][] = 0;
                    }else{
                        $information["approved"][] = $data[0]['sum'];
                    }

                    if($_data[0]['sum'] <=0){
                        $information["_approved"][] = 0;
                    }else{
                        $information["_approved"][] = $_data[0]['sum'];
                    }


                }
            }

            if (Request::get('target') != null) {
                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
                foreach ($month as $key => $value){

                    $data = $p_rows
                        ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('target')-543)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('target')-543)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved

                    if($data[0]['sum'] <=0){
                        $information["approved"][] = 0;
                    }else{
                        $information["approved"][] = $data[0]['sum'];
                    }

                    if($_data[0]['sum'] <=0){
                        $information["_approved"][] = 0;
                    }else{
                        $information["_approved"][] = $_data[0]['sum'];
                    }

//
                }
                 //dd($information);
            }
        }

        return response()->json( $information );

    }

    public function budget(){
        $p_rows = new Quotation;

        if (Request::ajax()) {
            if (Request::get('target') != null AND Request::get('year_target') != null) {

                $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
                foreach ($month as $key => $value){
                    $budget = array(0,Request::get('target'));

                    $data = $p_rows
                        ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                        ->whereBetween('product_price_with_vat', $budget)
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year_target')-543)
                        ->where('status','=','1')
                        ->get();
                    $data = $data->toArray();// quotation approved

                    $_data = $p_rows
                        ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                        ->whereBetween('product_price_with_vat', $budget)
                        ->whereMonth('created_at','=',$key)
                        ->whereYear('created_at','=',Request::get('year_target')-543)
                        ->where('status','=','0')
                        ->get();

                    $_data = $_data->toArray();// quotation none approved

                    if($data[0]['sum'] <=0){
                        $information["approved"][] = 0;
                    }else{
                        $information["approved"][] = $data[0]['sum'];
                    }

                    if($_data[0]['sum'] <=0){
                        $information["_approved"][] = 0;
                    }else {
                        $information["_approved"][] = $_data[0]['sum'];
                    }


                    $information["_target"][][] = Request::get('target');
                }
                //dd($information);
            }
        }

        return response()->json( $information );

    }

    public function ratio_report(){
        $c_year = date('Y');
        $year = array('' => trans_choice('',1) );
        $plus = (App::getLocale() == 'th')?543:0;
        for ($i = $c_year+1; $i >= 1900; $i--) {
            $year += array($i=>$i+$plus);
        }

        return view('Backend.report_chart.report_chart_ratio')->with(compact('year'));
    }

    public function ratio_lead(){
        $p_rows = new Customer;

        if (Request::ajax()) {

            if (Request::get('from-date')) {
                $from = str_replace('/', '-', Request::get('from-date'));
                $to = str_replace('/', '-', Request::get('to-date'));
                $date = array($from . " 00:00:00", $to . " 00:00:00");
                $information = $this->query_leads_customer_date($date);
            }

            $year=Request::get('year')-543;

            if (Request::get('channel_id') != null AND Request::get('year') != null) {
                $information = $this->query_leads_customer_chanel(Request::get('channel_id'),Request::get('year')-543);
            }

            if (Request::get('type_id') != null AND Request::get('year') != null) {
                $information = $this->query_leads_customer_type(Request::get('type_id'),Request::get('year')-543);
            }

            if (Request::get('year') != null AND Request::get('channel_id') == null AND Request::get('type_id') == null) {
                $information = $this->query_leads_customer_year(Request::get('year'));
            }

            if (Request::get('target_ratio') != null AND Request::get('year_target') != null) {
                $information = $this->chart_target_quotation(Request::get('target_ratio'),Request::get('year_target') -543);
            }



            $p_rows = $p_rows->where('active_status', '=', 't')->get();
            //dd($information);
            return response()->json( $information );
            // dd($p_rows);
        }
    }

    public function query_leads_customer_date($date){
        $p_rows = new Customer;
        $quotation = new Quotation;
        $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
        foreach ($month as $key => $value){
            $data = $p_rows
                ->select(DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', $date)
                ->whereMonth('created_at','=',$key)
                ->where('role','=','0')
                ->get();
            $data = $data->toArray();

            $_data = $p_rows
                ->select(DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', $date)
                ->whereMonth('created_at','=',$key)
                ->where('role','=','1')
                ->get();

            $_data = $_data->toArray();

            if($_data[0]['count'] <= 0){
                $information["leads"][] = 0;
            }else{
                $information["leads"][] = $_data[0]['count'];
            }

            if($_data[0]['count'] <= 0){
                $information["customer"][] = 0;
            }else{
                $information["customer"][] = $data[0]['count'];
            }



            $data_quotation = $quotation
                ->select(DB::raw('COUNT(id) as count'))
                ->whereBetween('created_at', $date)
                //->where('lead_id','=', Request::get('name'))
                ->whereMonth('created_at','=',$key)
                ->where('status','=','1')
                ->get();
            $data_quotation = $data_quotation->toArray();// quotation approved

            $_data_quotation = $quotation
                ->select(DB::raw('COUNT(id) as count'))
                ->whereBetween('created_at', $date)
                //->where('lead_id','=', Request::get('name'))
                ->whereMonth('created_at','=',$key)
                ->where('status','=','0')
                ->get();

            $_data_quotation = $_data_quotation->toArray();// quotation none approved

            if($data_quotation[0]['count'] <= 0){
                $information["approved"][] = 0;
            }else{
                $information["approved"][] = $data_quotation[0]['count'];
            }

            if($data_quotation[0]['count'] <= 0){
                $information["_approved"][] = 0;
            }else{
                $information["_approved"][] = $_data_quotation[0]['count'];
            }


            $data_quotation = $quotation
                ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                ->whereBetween('created_at', $date)
                //->where('lead_id','=', Request::get('name'))
                ->whereMonth('created_at','=',$key)
                ->where('status','=','1')
                ->get();
            $data_quotation = $data_quotation->toArray();// quotation approved

            $_data_quotation = $quotation
                ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                ->whereBetween('created_at', $date)
                //->where('lead_id','=', Request::get('name'))
                ->whereMonth('created_at','=',$key)
                ->where('status','=','0')
                ->get();

            $_data_quotation = $_data_quotation->toArray();// quotation none approved

            if($data_quotation[0]['sum'] <= 0){
                $information["approved_sum"][] = 0;
            }else{
                $information["approved_sum"][] = $data_quotation[0]['sum'];
            }

            if($data_quotation[0]['sum'] <= 0){
                $information["_approved_sum"][] = 0;
            }else{
                $information["_approved_sum"][] = $_data_quotation[0]['sum'];
            }


        }
        return $information;
    }

    public function query_leads_customer_chanel($channel_id,$year){
        $p_rows = new Customer;
        $quotation = new Quotation;
        $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
        foreach ($month as $key => $value){

            if($channel_id == 'all'){
                $data = $p_rows
                    ->select(DB::raw('COUNT(*) as count'))
                    //->where('channel','=',$channel_id)
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('role','=','0')
                    ->get();
                $data = $data->toArray();

                $_data = $p_rows
                    ->select(DB::raw('COUNT(*) as count'))
                    //->where('channel','=',$channel_id)
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('role','=','1')
                    ->get();

                $_data = $_data->toArray();
            }else{
                $data = $p_rows
                    ->select(DB::raw('COUNT(*) as count'))
                    ->where('channel','=',$channel_id)
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('role','=','0')
                    ->get();
                $data = $data->toArray();

                $_data = $p_rows
                    ->select(DB::raw('COUNT(*) as count'))
                    ->where('channel','=',$channel_id)
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('role','=','1')
                    ->get();

                $_data = $_data->toArray();
            }


            if($_data[0]['count'] <= 0){
                $information["leads"][] = 0;
            }else{
                $information["leads"][] = $_data[0]['count'];
            }

            if($_data[0]['count'] <= 0){
                $information["customer"][] = 0;
            }else{
                $information["customer"][] = $data[0]['count'];
            }

            if($channel_id == 'all'){
                $data_quotation = $quotation
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','1')
                    ->get();
                $data_quotation = $data_quotation->toArray();// quotation approved

                $_data_quotation = $quotation
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','0')
                    ->get();

                $_data_quotation = $_data_quotation->toArray();// quotation none approved
            }else{
                $data_quotation = $quotation
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->whereHas('latest_lead', function ($q) use ($channel_id) {
                        $q->where('channel','=',$channel_id);
                    })
                    ->where('status','=','1')
                    ->get();
                $data_quotation = $data_quotation->toArray();// quotation approved

                $_data_quotation = $quotation
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->whereHas('latest_lead', function ($q) use ($channel_id) {
                        $q->where('channel','=',$channel_id);
                    })
                    ->where('status','=','0')
                    ->get();

                $_data_quotation = $_data_quotation->toArray();// quotation none approved
            }

            if($data_quotation[0]['count'] <= 0){
                $information["approved"][] = 0;
            }else{
                $information["approved"][] = $data_quotation[0]['count'];
            }

            if($data_quotation[0]['count'] <= 0){
                $information["_approved"][] = 0;
            }else{
                $information["_approved"][] = $_data_quotation[0]['count'];
            }

            if($channel_id == 'all'){
                $data_quotation = $quotation
                    ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','1')
                    ->get();
                $data_quotation = $data_quotation->toArray();// quotation approved

                $_data_quotation = $quotation
                    ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','0')
                    ->get();

                $_data_quotation = $_data_quotation->toArray();// quotation none approved

            }else{
                $data_quotation = $quotation
                    ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                    ->whereHas('latest_lead', function ($q) use ($channel_id) {
                        $q->where('channel','=',$channel_id);
                    })
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','1')
                    ->get();
                $data_quotation = $data_quotation->toArray();// quotation approved

                $_data_quotation = $quotation
                    ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                    ->whereHas('latest_lead', function ($q) use ($channel_id) {
                        $q->where('channel','=',$channel_id);
                    })
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','0')
                    ->get();

                $_data_quotation = $_data_quotation->toArray();// quotation none approved

            }

            if($data_quotation[0]['sum'] <= 0){
                $information["approved_sum"][] = 0;
            }else{
                $information["approved_sum"][] = $data_quotation[0]['sum'];
            }

            if($data_quotation[0]['sum'] <= 0){
                $information["_approved_sum"][] = 0;
            }else{
                $information["_approved_sum"][] = $_data_quotation[0]['sum'];
            }

        }
        return $information;
    }

    public function query_leads_customer_type($type_id,$year){
        $p_rows = new Customer;
        $quotation = new Quotation;
        $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
        foreach ($month as $key => $value){

            if($type_id == 'all'){
                $data = $p_rows
                    ->select(DB::raw('COUNT(*) as count'))
                    //->where('type','=',$type_id)
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('role','=','0')
                    ->get();
                $data = $data->toArray();

                $_data = $p_rows
                    ->select(DB::raw('COUNT(*) as count'))
                    //->where('type','=',$type_id)
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('role','=','1')
                    ->get();

                $_data = $_data->toArray();

                $data_quotation = $quotation
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','1')
                    ->get();
                $data_quotation = $data_quotation->toArray();// quotation approved

                $_data_quotation = $quotation
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','0')
                    ->get();

                $_data_quotation = $_data_quotation->toArray();// quotation none approved
            }else{
                $data = $p_rows
                    ->select(DB::raw('COUNT(*) as count'))
                    ->where('type','=',$type_id)
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('role','=','0')
                    ->get();
                $data = $data->toArray();

                $_data = $p_rows
                    ->select(DB::raw('COUNT(*) as count'))
                    ->where('type','=',$type_id)
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('role','=','1')
                    ->get();

                $_data = $_data->toArray();

                $data_quotation = $quotation
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->whereHas('latest_lead', function ($q) use ($type_id) {
                        $q->where('type','=',$type_id);
                    })
                    ->where('status','=','1')
                    ->get();
                $data_quotation = $data_quotation->toArray();// quotation approved

                $_data_quotation = $quotation
                    ->select(DB::raw('COUNT(id) as count'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->whereHas('latest_lead', function ($q) use ($type_id) {
                        $q->where('type','=',$type_id);
                    })
                    ->where('status','=','0')
                    ->get();

                $_data_quotation = $_data_quotation->toArray();// quotation none approved
                }


            if($data_quotation[0]['count'] <= 0){
                $information["approved"][] = 0;
            }else{
                $information["approved"][] = $data_quotation[0]['count'];
            }

            if($data_quotation[0]['count'] <= 0){
                $information["_approved"][] = 0;
            }else{
                $information["_approved"][] = $_data_quotation[0]['count'];
            }

            if($_data[0]['count'] <= 0){
                $information["leads"][] = 0;
            }else{
                $information["leads"][] = $_data[0]['count'];
            }

            if($_data[0]['count'] <= 0){
                $information["customer"][] = 0;
            }else{
                $information["customer"][] = $data[0]['count'];
            }

            if($type_id == 'all'){
                $data_quotation = $quotation
                    ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','1')
                    ->get();
                $data_quotation = $data_quotation->toArray();// quotation approved

                $_data_quotation = $quotation
                    ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','0')
                    ->get();

                $_data_quotation = $_data_quotation->toArray();// quotation none approved
            }else{
                $data_quotation = $quotation
                    ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                    ->whereHas('latest_lead', function ($q) use ($type_id) {
                        $q->where('type','=',$type_id);
                    })
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','1')
                    ->get();
                $data_quotation = $data_quotation->toArray();// quotation approved

                $_data_quotation = $quotation
                    ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                    ->whereHas('latest_lead', function ($q) use ($type_id) {
                        $q->where('type','=',$type_id);
                    })
                    ->whereMonth('created_at','=',$key)
                    ->whereYear('created_at','=',$year)
                    ->where('status','=','0')
                    ->get();

                $_data_quotation = $_data_quotation->toArray();// quotation none approved
            }


            if($data_quotation[0]['sum'] <= 0){
                $information["approved_sum"][] = 0;
            }else{
                $information["approved_sum"][] = $data_quotation[0]['sum'];
            }

            if($data_quotation[0]['sum'] <= 0){
                $information["_approved_sum"][] = 0;
            }else{
                $information["_approved_sum"][] = $_data_quotation[0]['sum'];
            }
        }
        return $information;
    }

    public function query_leads_customer_year($year){
        $p_rows = new Customer;
        $quotation = new Quotation;
        $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
        foreach ($month as $key => $value){

            $data = $p_rows
                ->select(DB::raw('COUNT(*) as count'))
                ->whereMonth('created_at','=',$key)
                ->whereYear('created_at','=',$year-543)
                ->where('role','=','0')
                ->get();
            $data = $data->toArray();

            $_data = $p_rows
                ->select(DB::raw('COUNT(*) as count'))
                ->whereMonth('created_at','=',$key)
                ->whereYear('created_at','=',$year-543)
                ->where('role','=','1')
                ->get();

            $_data = $_data->toArray();

            $data_quotation = $quotation
                ->select(DB::raw('COUNT(id) as count'))
                ->whereMonth('created_at','=',$key)
                ->whereYear('created_at','=',$year-543)
                ->where('status','=','1')
                ->get();
            $data_quotation = $data_quotation->toArray();// quotation approved

            $_data_quotation = $quotation
                ->select(DB::raw('COUNT(id) as count'))
                ->whereMonth('created_at','=',$key)
                ->whereYear('created_at','=',$year-543)
                ->where('status','=','0')
                ->get();

            $_data_quotation = $_data_quotation->toArray();// quotation none approved

            if($data_quotation[0]['count'] <= 0){
                $information["approved"][] = 0;
            }else{
                $information["approved"][] = $data_quotation[0]['count'];
            }

            if($data_quotation[0]['count'] <= 0){
                $information["_approved"][] = 0;
            }else{
                $information["_approved"][] = $_data_quotation[0]['count'];
            }

            if($_data[0]['count'] <= 0){
                $information["leads"][] = 0;
            }else{
                $information["leads"][] = $_data[0]['count'];
            }

            if($_data[0]['count'] <= 0){
                $information["customer"][] = 0;
            }else{
                $information["customer"][] = $data[0]['count'];
            }

            $data_quotation = $quotation
                ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                ->whereMonth('created_at','=',$key)
                ->whereYear('created_at','=',$year-543)
                ->where('status','=','1')
                ->get();
            $data_quotation = $data_quotation->toArray();// quotation approved

            $_data_quotation = $quotation
                ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                ->whereMonth('created_at','=',$key)
                ->whereYear('created_at','=',$year-543)
                ->where('status','=','0')
                ->get();

            $_data_quotation = $_data_quotation->toArray();// quotation none approved

            if($data_quotation[0]['sum'] <= 0){
                $information["approved_sum"][] = 0;
            }else{
                $information["approved_sum"][] = $data_quotation[0]['sum'];
            }

            if($data_quotation[0]['sum'] <= 0){
                $information["_approved_sum"][] = 0;
            }else{
                $information["_approved_sum"][] = $_data_quotation[0]['sum'];
            }
        }
        return $information;
    }

    public function chart_target_quotation($target,$year){
        $budget = array(0,$target);
        $p_rows = new Quotation;
        $month = array('1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec');
        foreach ($month as $key => $value) {
            $data = $p_rows
                ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                ->whereBetween('product_price_with_vat', $budget)
                ->whereMonth('created_at','=',$key)
                ->whereYear('created_at','=',$year)
                //->whereYear('created_at','=',Request::get('year_target')-543)
                ->where('status','=','1')
                ->get();
            $data = $data->toArray();// quotation approved

            $_data = $p_rows
                ->select(DB::raw('SUM(product_price_with_vat) as sum'))
                ->whereBetween('product_price_with_vat', $budget)
                ->whereMonth('created_at','=',$key)
                ->whereYear('created_at','=',$year)
                //->whereYear('created_at','=',Request::get('year_target')-543)
                ->where('status','=','0')
                ->get();

            $_data = $_data->toArray();// quotation none approved

            if($data[0]['sum'] <= 0){
                $information["approved"][] = 0;
            }else{
                $information["approved"][] = $data[0]['sum'];
            }

            if($_data[0]['sum'] <= 0){
                $information["_approved"][] = 0;
            }else{
                $information["_approved"][] = $_data[0]['sum'];
            }
            $information["_target"][][] = $target;
        }
        return $information;
    }
}
