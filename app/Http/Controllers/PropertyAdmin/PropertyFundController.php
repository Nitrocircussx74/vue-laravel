<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use App;
use Auth;
use File;
use Redirect;
# Model
use App\PropertyFund;
use App\Property;
use App\PropertyFundEditLog;

class PropertyFundController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_fund');
		view()->share('active_menu', 'finance');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function fundList (Request $form) {

		$fund_lists = PropertyFund::with('createdBy')->where('property_id',Auth::user()->property_id);
		$property 	= Property::find(Auth::user()->property_id);
		if(!Request::ajax()) {
			$date_bw = array(date('Y-01-01 00:00:00'), date('Y-12-31 23:59:59'));
			$fund_lists = $fund_lists->whereBetween('created_at', $date_bw)->orderBy('created_at','desc')->paginate(50);
			return view('fund.admin-fund-log-list')->with(compact('fund_lists','property'));
		} else {
			$y 	 = Request::get('year');
			$date_bw = array($y."-01-01 00:00:00",$y."-12-31 23:59:59");
			$fund_lists = $fund_lists->whereBetween('created_at', $date_bw)->orderBy('created_at','desc')->paginate(50);
			return view('fund.admin-fund-log-list-element')->with(compact('fund_lists'));
		}
	}

	function saveFundLog () {
		if(Request::isMethod('post')) {
			$property 					= Property::find(Auth::user()->property_id);
			$fund_list 					= new PropertyFund;
			$fund_list->fill(Request::all());
			$fund_list->property_id 	= Auth::user()->property_id;
			$fund_list->creator 		= Auth::user()->id;
			$amount						= str_replace(',', '', Request::get('amount'));
			if(Request::get('type') == 1) {
				$fund_list->get 		= $amount;
				$property->fund_balance	+= $amount;
			} else {
				$fund_list->pay 		= $amount;
				$property->fund_balance	-= $amount;
			}
			$fund_list->save();
			// Save Counter
			$property->save();
			return redirect('admin/finance/fund');
		}
	}

	function editFundLog () {

		if(Request::isMethod('post')) {
			$property 					= Property::find(Auth::user()->property_id);
			$fund_list 					= PropertyFund::find(Request::get('id'));
			// Save previous data to log
			$log = new PropertyFundEditLog;
			$log->fund_log_id 	= $fund_list->id;
			$log->editor 		= Auth::user()->id;
			$log->content 		= json_encode( $fund_list->toArray() );
			$log->save();

			// reset to old balance
			if( $fund_list->get > 0 ) {
				$property->fund_balance -= $fund_list->get;
			} else {
				$property->fund_balance += $fund_list->pay;
			}

			$fund_list->fill(Request::all());
			$amount						= str_replace(',', '', Request::get('amount'));

			if(Request::get('type') == 1) {
				$fund_list->get 		= $amount;
				$property->fund_balance += $amount;
				$fund_list->pay  		= 0;
			} else {
				$fund_list->pay 		= $amount;
				$property->fund_balance -= $amount;
				$fund_list->get 		= 0; 
			}
			$fund_list->save();
			// Save Balance
			$property->save();
			return redirect('admin/finance/fund');
		}
	}

	function deleteFundLog () {
		if(Request::isMethod('post')) {
			$property 					= Property::find(Auth::user()->property_id);
			$fund 						= PropertyFund::find(Request::get('id'));
			if($fund->get > 0) {
				$property->fund_balance -= $fund->get;
			} else {
				$property->fund_balance += $fund->pay;
			}
			$fund->editLog()->delete();
			$fund->delete();
			$property->save();
			return redirect('admin/finance/fund');
		}
	}

	function viewFundLog () {
        $fund = PropertyFund::with('createdBy')->find(Request::get('id'));
		return view('fund.fund-view')->with(compact('fund'));
	}

	function getFundEditLog () {
        $fe = PropertyFundEditLog::with('logEditor')->where('fund_log_id',Request::get('id'))->orderBy('created_at','asc')->get();
		return view('fund.fund-edit-log-view')->with(compact('fe'));
	}

	function getFundLog () {
        $fund = PropertyFund::select('id','detail','get','pay','payment_date','ref_no')->find(Request::get('id'));
        $fund->payment_date = date('Y/m/d',strtotime($fund->payment_date));
        if($fund->get > 0) {
        	$fund->amount 	= number_format($fund->get,2);
        	$fund->type 	= 1;
        } else {
        	$fund->amount 	= number_format($fund->pay,2);
        	$fund->type 	= 2;
        }
		//return view('fund.fund-edit')->with(compact('fund'));
		return response()->json($fund->toArray());
	}

	public function fundPrint () {
		
		if(Request::isMethod('post')) {
			$property 	= Property::find(Auth::user()->property_id);
			$fund_list = PropertyFund::with('createdBy')->where('property_id',Auth::user()->property_id);
			$y 	 = Request::get('year');
			$date_bw = array($y."-01-01 00:00:00",$y."-12-01 23:59:59");
			$year = localYear(Request::get('year'));
			$fund_list = $fund_list->whereBetween('created_at', $date_bw)->get();
			return view('fund.admin-fund-log-print')->with(compact('fund_list','property','year'));
		}
	}
}