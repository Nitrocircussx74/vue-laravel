<?php

namespace App\Http\Controllers\PropertyAdmin;

use Carbon\Carbon;
use Request;
use Illuminate\Routing\Controller;
use Auth;
use DB;

use App\Property;
use App\PropertyUnit;

class BillCollectorController extends Controller
{
    public function __construct () {
        if(Auth::check() && Auth::user()->role == 3){
            $this->middleware('auth:menu_finance_group');
        }
        $this->middleware('auth');
        view()->share('active_menu', 'bill');
        if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $form)
    {
        $p_lists = PropertyUnit::with(['overDueInvoice' => function ($q)  use ($form) {
            if( !empty($form) && $form::get('overdue_day')) {
                $od = abs(intval($form::get('overdue_day')));
                if ( $od > 1) {
                    $day = Carbon::now()->subDay($od);
                    $q->where('due_date','<=',$day->format('Y-m-d'));
                }
            }
        }])->whereHas('invoice', function ($q) use ($form) {

            $q->where('is_retroactive_record',false)->where(function ($q) {
                $q->orWhere(function ($q) {
                    $q->where('payment_status',0)->where('due_date','<',date('Y-m-d'));
                })->orWhere(function ($q) {
                    $q->where('payment_status',1)->whereRaw('submit_date::date > due_date::date');
                });
            })->where('type',1);

            if( !empty($form) && $form::get('invoice-no') != "-") {
                $q->where('invoice_no_label','like','%'.$form::get('invoice-no').'%');
            }

            if( !empty($form) && $form::get('overdue_day')) {
                $od = abs(intval($form::get('overdue_day')));
                if ( $od > 1) {
                    $day = Carbon::now()->subDay($od);
                    $q->where('due_date','<=',$day->format('Y-m-d'));
                }
            }

        })->where('property_id','=',Auth::user()->property_id);

        if( !empty($form::all()) && $form::get('invoice-unit_id') != "-") {
            $p_lists = $p_lists->where('id',$form::get('invoice-unit_id'));
        }

        $p_lists = $p_lists->orderBy(DB::raw('natsortInt(unit_number)'))->paginate(50);

        if(!$form::ajax()) {
            $property = Property::find(Auth::user()->property_id);
            $unit_list = array('-'=> trans('messages.unit_no'));
            $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
            return view('feesbills.bill-collector.admin-invoice-overdue-list')->with(compact('bills','unit_list','property','p_lists'));
        } else {
            return view('feesbills.bill-collector.admin-invoice-overdue-list-element')->with(compact('bills','p_lists'));
        }
    }

    public function createNotice (Request $r) {

        $property = Property::find(Auth::user()->property_id);
        $property_unit_bills = PropertyUnit::with(['overDueInvoice'  => function ($q)  use ($r) {
            if( !empty($r) && $r::get('overdue_day')) {
                $od = abs(intval($r::get('overdue_day')));
                if ( $od > 1) {
                    $day = Carbon::now()->subDay($od);
                    $q->where('due_date','<=',$day->format('Y-m-d'));
                }
            }
        },'overDueInvoice.transaction'])->whereIn('id',$r::get('p_list'))->get();
        return view('feesbills.bill-collector.admin-invoice-remind')->with(compact('property_unit_bills','property','r'));
    }
}
