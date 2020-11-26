<?php
namespace App\Http\Controllers\AdminNabour;
use Request;
use Auth;
use Redirect;
use Mail;

//Model
use App\SalePropertyDemo;
use App\Province;
use App\PropertyContract;
use App\Property;
use App\PropertyForm;

class PropertyFormController extends Controller {

	public function __construct () {
		$this->middleware('auth');
	}

	public function code () {
		$fail = false;
		$p = new Province;
		$provinces = $p->getProvince();
		if(Request::ajax()) {
			$_form = PropertyForm::where('form_code',trim(Request::get('code')))->first();
			if($_form) {
				if($_form->status == 0) {
					Request::session()->put('form_code', Request::get('code'));
					 return response()->json(array('status' => '1'));
				} else {
					return response()->json(array('status' => '2'));
				}
			} else {
				return response()->json(array('status' => '0'));
			}
		}
        return view('Backend.property_sale_demo.form-code')->with(compact('provinces','fail'));
	}

	public function form () {
		$p = new Province;
		$provinces = $p->getProvince();
		if(Request::isMethod('post')) {
			$data = Request::except('id','_token');
			$_form = PropertyForm::find(Request::get('id'));
			$data['unit_size'] = count($data['unit']);
			$_form->detail = json_encode($data);
			$_form->status = ($data['save-type'] == "draft") ? 0 : 1;
			$_form->save();
			if(!$_form->status)
				return redirect()->back()->withInput();
			else return redirect('home/code');
		} else {
			if(Request::session()->get('form_code')) {
				$_form = PropertyForm::where('form_code',Request::session()->get('form_code'))->first();
				if($_form || $_form->status == 0) {
					$id = $_form->id;
					$p  = $_form->province;
					$property_name  = $_form->property_name;
					if($_form->detail){
						$_form = $_form->toArray();
						$_form = json_decode($_form['detail'],true);
						$_form['id'] = $id;
					}
					else {
						$_form = array();
						$_form['id'] = $id;
						$_form['province'] = $p;
						$_form['property_name_th'] = $property_name;
					}
                    return view('Backend.property_sale_demo.property-form')->with(compact('_form','provinces'));
				} else {
					return redirect('home/code');
				}
			} else {
				return redirect('home/code');
			}
		}

	}
}
