<?php namespace App\Http\Controllers\PropertyAdmin;
use Auth;
use Request;
use Illuminate\Routing\Controller;
use App\Http\Controllers\PushNotificationController;
# Model
use DB;
use App\Vehicle;
use App\Invoice;
use App\Transaction;
use App\PropertyUnit;
use App\Notification;
use App\User;
use App\Property;

class VehicleController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_vehicle');
		view()->share('active_menu', 'vehicle');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function vehicleList()
	{
		if(Request::isMethod('post')) {
			$vehicles = Vehicle::where('property_id',Auth::user()->property_id);

			if(!empty(Request::get('unit_id')) && Request::get('unit_id') != "-") {
				$vehicles->where('property_unit_id',Request::get('unit_id'));
			}

			if(Request::get('type')) {
				$vehicles = $vehicles->where('type',Request::get('type'));
				if(in_array(Request::get('type'), [1,2])) {
					if(Request::get('brand')) {
						$vehicles = $vehicles->where('brand',Request::get('brand'));
					}
				}
			}

			if(Request::get('plate')){
				$vehicles->Where('lisence_plate', 'LIKE', '%'.Request::get('plate').'%');
			}

			$vehicles = $vehicles->paginate(30);
			return view('vehicle.vehicle-list-element')->with(compact('vehicles'));
		} else {
			$unit_list = array('-'=> trans('messages.unit_no'));
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
			$vehicles = Vehicle::where('property_id',Auth::user()->property_id)->paginate(30);
			return view('vehicle.list')->with(compact('vehicles','unit_list'));
		}
	}

	public function createInvoice () {
		$vehicle = Vehicle::find(Request::get('id'));
		if($vehicle->count()) {

			$price 		= str_replace(',', '', Request::get('amount'));
			$invoice 	= new Invoice;
			$property 	= Property::find(Auth::user()->property_id);
			$invoice->grand_total = $invoice->final_grand_total = $invoice->total = $price;
			$invoice->name = Request::get('name');
			$invoice->due_date = Request::get('due_date');
			$invoice->tax = 0;
			$invoice->type = 1;
			$invoice->property_id = $vehicle->property_id;
			$invoice->property_unit_id = $vehicle->property_unit_id;
			$invoice->invoice_no = $property->invoice_counter+1;
			$invoice->save();
			$trans[] = new Transaction([
				'detail' 	=> Request::get('name'),
				'quantity' 	=> 1,
				'price' 	=> $price,
				'total' 	=> $price,
				'transaction_type' => 1,
				'property_id' => $vehicle->property_id,
				'property_unit_id' => $vehicle->property_unit_id,
				'category' 	=> 2, // Sticker category
				'due_date'	=> Request::get('due_date')
			]);

			$invoice->transaction()->saveMany($trans);
			$this->sendInvoiceNotification ($vehicle->property_unit_id, $invoice->name,$invoice->id);

			$vehicle->sticker_status = 2;
			$vehicle->invoice_id = $invoice->id;
			if(Request::get('no_exp_flag')) {
				$vehicle->not_expired = true;
			} else {
				$vehicle->sticker_expire_date = Request::get('expired_date');
			}
			$vehicle->save();
			$property->increment('invoice_counter');
		}
		return redirect('admin/vehicle');
	}

	public function add () {
		if(Request::isMethod('post')) {
			$vehicle = new Vehicle;
			$vehicle->fill(Request::all());
			if(Request::get('type') == 1 || Request::get('type') == 2 ) {
				$vehicle->brand = Request::get('s_brand');
			} else {
				$vehicle->brand = Request::get('o_brand');
			}
			$vehicle->property_id = Auth::user()->property_id;
			if(Request::get('sticker_status') == 3) {
				if(Request::get('no_exp_flag')) $vehicle->not_expired = true;
				else $vehicle->sticker_expire_date = Request::get('expired_date');
			}
			$vehicle->save();
		}
		return redirect('admin/vehicle');
	}

	public function delete () {
		
        $vehicle = Vehicle::find(Request::get('id'));
        if( $vehicle ) {
            $vehicle->delete();
        }
        return redirect('admin/vehicle');
	}

	public function sendInvoiceNotification ($unit_id, $title, $subject_id) {
			$title = json_encode( ['type' => 'invoice_created','title' => $title] );
			$users = User::where('property_unit_id',$unit_id)->whereNull('verification_code')->get();
			foreach ($users as $user) {
				$notification = Notification::create([
					'title'				=> $title,
					'notification_type' => '3',
					'from_user_id'		=> Auth::user()->id,
					'to_user_id'		=> $user->id,
					'subject_key'		=> $subject_id
				]);
				$controller_push_noti = new PushNotificationController();
				$controller_push_noti->pushNotification($notification->id);
			}
	}
}
