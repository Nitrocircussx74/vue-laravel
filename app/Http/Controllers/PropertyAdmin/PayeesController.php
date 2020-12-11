<?php namespace App\Http\Controllers\PropertyAdmin;
use Auth;
use Request;
use Illuminate\Routing\Controller;
# Model
use App\Payee;
use App\Property;

class PayeesController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu', 'expenses');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function payeeList ()
	{
		if(Request::isMethod('post')) {
			$payees = Payee::where('property_id',Auth::user()->property_id);
			$payees = $payees->paginate(30);
			return view('payee.payee-list-element')->with(compact('payees'));
		} else {

			$payees = Payee::where('property_id',Auth::user()->property_id)->orderBy('payee_no', 'asc')->paginate(30);
			return view('payee.list')->with(compact('payees','unit_list'));
		}
	}

	public function add () {
		if(Request::isMethod('post')) {
			$property 	= Property::find(Auth::user()->property_id);

			$payee = new Payee;
			$payee->fill(Request::all());
			$payee->property_id = Auth::user()->property_id;
			$payee->payee_no = ++$property->payee_counter;
			$payee->save();
			$property->save();
		}
		return redirect('admin/expenses/payee');
	}

	public function edit () {
		$payee = Payee::find(Request::get('id'));
		if(Request::ajax()) {
			return view('payee.payee-form-element')->with(compact('payee'));
		} else if(Request::isMethod('post')) {

			$payee->fill(Request::all());
			$payee->property_id = Auth::user()->property_id;
			$payee->save();
			return redirect('admin/expenses/payee');
		}
	}
}
