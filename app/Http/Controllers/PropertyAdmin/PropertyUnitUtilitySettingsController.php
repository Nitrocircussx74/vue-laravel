<?php namespace App\Http\Controllers\PropertyAdmin;
use App\Property;
use League\Flysystem\Exception;
use Maatwebsite\Excel\Exceptions\LaravelExcelException;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use Redirect;
use Storage;
use DB;
use File;
use Excel;
use View;
use Carbon\Carbon;
# Model
use App\PropertyUnit;
use App\User;
use App\Tenant;
use App\Keycard;
use App\BillWater;
use App\BillElectric;
class PropertyUnitUtilitySettingsController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_utility');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function unitList () {
		$units 		= PropertyUnit::with('home_tenant','home_keycard')->where('property_id',Auth::user()->property_id)->where('active',true);

		$unit_list = array(''=> trans('messages.unit_no') );
		$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();

		if(Request::ajax()) {

			if(Request::get('unit_id')) {
				$units->where('id',Request::get('unit_id'));
			}

			$units = $units->orderBy(DB::raw('natsortInt(unit_number)'))->paginate(50);
			return view('property_officer.utility_settings.unit-list-page')->with(compact('units','unit_list'));
		}
		else{
			$units = $units->orderBy(DB::raw('natsortInt(unit_number)'))->paginate(50);
			return view('property_officer.utility_settings.unit-list')->with(compact('units','unit_list'));
		}
	}

	public function editForm () {
		$unit = PropertyUnit::find(Request::get('unit_id'));
		return view('property_officer.utility_settings.get-unit-edit-form')->with(compact('unit'));
	}

	public function edit () {
		$unit = PropertyUnit::find(Request::get('id'));
        $unit->fill( Request::all() );
		$unit->property_id = Auth::user()->property_id;

        if(!$unit->waste_water_treatment) $unit->waste_water_treatment = 0;

		// rate water
        $unit->water_billing_rate = str_replace(',', "", Request::get('water_billing_rate'));
        if(!$unit->water_billing_rate) $unit->water_billing_rate = 0;

        $unit->water_meter_rate = str_replace(',', "", Request::get('water_meter_rate'));
        if(!$unit->water_meter_rate) $unit->water_meter_rate = 0;

        // rate electric
        $unit->electric_billing_rate = str_replace(',', "", Request::get('electric_billing_rate'));
        if(!$unit->electric_billing_rate) $unit->electric_billing_rate = 0;

        $unit->electric_meter_rate = str_replace(',', "", Request::get('electric_meter_rate'));
        if(!$unit->electric_meter_rate) $unit->electric_meter_rate = 0;
		$unit->save();

		return response()->json(['r' => true]);
		//return redirect('admin/util/units');
	}

	public function reportUtility () {
        return view('property_officer.utility_settings.admin-utility-report')->with(compact('property'));
    }

    public function utilityReportResult () {
        $date 	= Request::get('year')."-".Request::get('month');

        $dt = Carbon::createFromDate(Request::get('year'), Request::get('month'),1);
        //$date_period = $dt->firstOfMonth()->format('Y-m');
        $date_old_period = $dt->firstOfMonth()->subMonth()->format('Y-m');

        $r = Request::all();

        $utility_data = BillWater::where('bill_date_period', $date)->where('property_id', Auth::user()->property_id)->get();

        $property_unit_building_counter = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->where('building',null)->count();
        if($property_unit_building_counter == 0){
            $property_unit = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy('building')->orderBy(DB::raw('natsortInt(unit_number)'))->get();
        }else{
            $property_unit = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->get();
        }

        $bill_this_month = BillWater::where('property_id',Auth::user()->property_id)->where('bill_date_period',$date)->where('is_service_charge',false)->get();
        $bill_before_this_month = BillWater::where('property_id',Auth::user()->property_id)->where('bill_date_period',$date_old_period)->where('is_service_charge',false)->get();

        foreach($bill_this_month as $item_bill){
            //$billing_new_array[$item_bill->property_unit_id] = $item_bill->unit;
            $billing_new_array[$item_bill->property_unit_id] = [
                'unit' => $item_bill->unit,
                'net_unit' => $item_bill->net_unit
            ];
        }

        foreach($bill_before_this_month as $item_bill){
            //$billing_old_array[$item_bill->property_unit_id] = $item_bill->unit;
            $billing_old_array[$item_bill->property_unit_id] = [
                'unit' => $item_bill->unit,
                'net_unit' => $item_bill->net_unit
            ];
        }

        foreach ($property_unit as $unit_item){
            $property_unit_array[] = [
                'id' => $unit_item->id,
                'property_id' => $unit_item->property_id,
                'property_unit_name' => $unit_item->unit_number,
                'property_unit_floor' => $unit_item->unit_floor,
                'old_unit' => isset($billing_old_array[$unit_item->id]) ? $billing_old_array[$unit_item->id]['unit'] : 0,
                'unit' => isset($billing_new_array[$unit_item->id]) ? $billing_new_array[$unit_item->id]['unit'] : 0,
                'net_unit' => isset($billing_new_array[$unit_item->id]) ? $billing_new_array[$unit_item->id]['net_unit'] : 0,
            ];
        }
        $aa = "";
        //dd($utility_data->toArray());
        //dd($property_unit_array);

        return view('property_officer.utility_settings.utility-report')->with(compact('utility_data','r','property_unit_array'));
    }

    function utilityReportExport (){
        if(Request::isMethod('post')) {

            $date 	= Request::get('year-export')."-".Request::get('month-export');

            $dt = Carbon::createFromDate(Request::get('year-export'), Request::get('month-export'),1);
            //$date_period = $dt->firstOfMonth()->format('Y-m');
            $date_old_period = $dt->firstOfMonth()->subMonth()->format('Y-m');



            $r = Request::all();

            $utility_data = BillWater::where('bill_date_period', $date)->where('property_id', Auth::user()->property_id)->get();

            $property_unit_building_counter = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->where('building',null)->count();
            if($property_unit_building_counter == 0){
                $property_unit = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy('building')->orderBy(DB::raw('natsortInt(unit_number)'))->get();
            }else{
                $property_unit = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->get();
            }

            $bill_this_month = BillWater::where('property_id',Auth::user()->property_id)->where('bill_date_period',$date)->where('is_service_charge',false)->get();
            $bill_before_this_month = BillWater::where('property_id',Auth::user()->property_id)->where('bill_date_period',$date_old_period)->where('is_service_charge',false)->get();

            foreach($bill_this_month as $item_bill){
                //$billing_new_array[$item_bill->property_unit_id] = $item_bill->unit;
                $billing_new_array[$item_bill->property_unit_id] = [
                    'unit' => $item_bill->unit,
                    'net_unit' => $item_bill->net_unit
                ];
            }

            foreach($bill_before_this_month as $item_bill){
                //$billing_old_array[$item_bill->property_unit_id] = $item_bill->unit;
                $billing_old_array[$item_bill->property_unit_id] = [
                    'unit' => $item_bill->unit,
                    'net_unit' => $item_bill->net_unit
                ];
            }

            foreach ($property_unit as $unit_item){
                $property_unit_array[] = [
                    'id' => $unit_item->id,
                    'property_id' => $unit_item->property_id,
                    'property_unit_name' => $unit_item->unit_number,
                    'property_unit_floor' => $unit_item->unit_floor,
                    'old_unit' => isset($billing_old_array[$unit_item->id]) ? $billing_old_array[$unit_item->id]['unit'] : 0,
                    'unit' => isset($billing_new_array[$unit_item->id]) ? $billing_new_array[$unit_item->id]['unit'] : 0,
                    'net_unit' => isset($billing_new_array[$unit_item->id]) ? $billing_new_array[$unit_item->id]['net_unit'] : 0,
                ];
            }

            $filename = trans('messages.Meter.meter_report_head').trans('messages.dateMonth.'.$r['month-export'])." ".localYear($r['year-export']);
            $property_name = Property::with('has_province')->find(Auth::user()->property_id);
            return view('property_officer.utility_settings.utility-report-export')->with(compact('filename','property_name','utility_data','r','property_unit_array'));
        }
    }

    public function exportPropertyUnitSetting(){
        $filename = "property_unit_data_2017_04_10";
        $property = Property::find(Auth::user()->property_id);
        $units 		= PropertyUnit::with('home_tenant','home_keycard')->where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->get();

        try {
            Excel::create($filename, function ($excel) use ($units, $filename, $property) {
                $excel->sheet("keycard", function ($sheet) use ($units, $property) {
                    $sheet->setWidth(array(
                        'A' => 20,
                        'B' => 20,
                        'C' => 10,
                        'D' => 20
                    ));
                    $sheet->loadView('property_units.data-export-keycard')->with(compact('units', 'property'));
                });

                $excel->sheet("property unit", function ($sheet) use ($units, $property) {
                    $sheet->setWidth(array(
                        'A' => 20,
                        'B' => 10,
                        'C' => 30,
                        'D' => 15,
                        'E' => 15,
                        'F' => 15,
                        'G' => 15,
                        'H' => 15,
                        'I' => 15,
                        'J' => 15,
                        'K' => 15,
                        'L' => 20,
                        'M' => 35,
                        'N' => 20,
                        'O' => 15,
                        'P' => 20
                    ));
                    $sheet->loadView('property_units.data-export')->with(compact('units', 'property'));
                });

                $excel->setCreator('Nabour Application');
                $excel->setKeywords('Nabour PropertyUnit');
            })->export('xls');
        }catch (LaravelExcelException $ex){
            $error= $ex;
        }
    }
}
