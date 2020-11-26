<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Auth;
use Redirect;
use DB;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;
use Carbon\Carbon;
# Model
use App\Property;
use App\PropertyUnit;
use App\User;
use App\BillWater;
use App\BillElectric;
use App\ManagementGroup;
class BillWaterController extends Controller {

	protected $app;

	public function __construct () {
		$this->middleware('auth:menu_utility');
		if( Auth::check() && Auth::user()->role !== 1) {
		    if(Auth::user()->role == 3){
                // Nothing
            }else {
                Redirect::to('feed')->send();
            }
		}
	}

    public function list_()
    {
        $buildings = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->whereNotNull('building')->where('building','!=','')->select('building')->groupBy('building')->orderBy(DB::raw('natsortInt(building)'))->get();
        $bd = [];
        if( $buildings ) {
            foreach($buildings as $building) {
                $bd[$building->building] = $building->building;
            }
        }
        $property_unit = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true);

        if(Request::get('building')) {
            $property_unit = $property_unit->where('building',Request::get('building'));
        }
        $property_unit = $property_unit->orderBy(DB::raw('natsortInt(unit_number)'))->get();
        $list_month = [];
        $counter = 0;
        $property = Property::find(Auth::user()->property_id);
        if($property->developer_group_id == 'f1c70c85-4799-41d5-af0b-3340be04718b'){
            $developer_group = 'msm';
        }else{
            $developer_group = 'other';
        }
        while($counter < 12) {
            if($developer_group == 'msm') {
                $list_month[Carbon::now()->firstOfMonth()->addMonth(1)->subMonth($counter)->format('Y-m')] = getMonthYearText(Carbon::now()->firstOfMonth()->addMonth(1)->subMonth($counter)->format('Y-m'));
            }else{
                $list_month[Carbon::now()->firstOfMonth()->subMonth($counter)->format('Y-m')] = getMonthYearText(Carbon::now()->firstOfMonth()->subMonth($counter)->format('Y-m'));
            }
            $counter++;
        }

        if(Request::get('period_select')) {
            $select_period_arr = explode("-", Request::get('period_select'));
            $dt = Carbon::createFromDate($select_period_arr[0], $select_period_arr[1],1);
            $date_period = $dt->firstOfMonth()->format('Y-m');
            $date_old_period = $dt->firstOfMonth()->subMonth()->format('Y-m');
        }else{
            if($developer_group == 'msm') {
                $date_period = Carbon::now()->firstOfMonth()->addMonth(1)->format('Y-m');
                $date_old_period = Carbon::now()->firstOfMonth()->addMonth(1)->subMonth()->format('Y-m');
            }else{
                $date_period = Carbon::now()->firstOfMonth()->format('Y-m');
                $date_old_period = Carbon::now()->firstOfMonth()->subMonth()->format('Y-m');
            }
        }
        

        $bill_this_month = BillWater::where('property_id',Auth::user()->property_id)->where('bill_date_period',$date_period)->where('is_service_charge',false)->get();
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
                'bill_date_period' => $date_period
            ];
        }

        $month_label = $list_month[$date_period];

        if(!Request::ajax()) {
            return view('property_officer.list-bill-water')->with(compact('property_unit_array','list_month','month_label','bd'));
        } else {
            return view('property_officer.list-element-water')->with(compact('property_unit_array','list_month','month_label'));
        }
    }

    public function editForm () {
        $select_period_arr = explode("-", Request::get('period_month'));
        $dt = Carbon::createFromDate($select_period_arr[0], $select_period_arr[1],1);
        $date_period = $dt->firstOfMonth()->format('Y-m');
        $date_old_period = $dt->firstOfMonth()->subMonth()->format('Y-m');
        $bill_this_month = BillWater::where('property_id',Auth::user()->property_id)->where('property_unit_id',Request::get('unit_id'))->where('bill_date_period',$date_period)->where('is_service_charge',false)->get();
        $bill_before_this_month = BillWater::where('property_id',Auth::user()->property_id)->where('property_unit_id',Request::get('unit_id'))->where('bill_date_period',$date_old_period)->where('is_service_charge',false)->get();

        $property = Property::find(Auth::user()->property_id);
        if($property->developer_group_id == 'f1c70c85-4799-41d5-af0b-3340be04718b'){
            $developer_group = 'msm';
        }else{
            $developer_group = 'other';
        }

        $property_unit = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true);

        if(Request::get('building')) {
            $property_unit = $property_unit->where('building',Request::get('building'));
        }
        $property_unit = $property_unit->orderBy(DB::raw('natsortInt(unit_number)'))->get();
        
        $property_unit_arr = [];
        foreach ($property_unit as $item){
            $property_unit_arr[$item->id] = [
                'id' => $item->id,
                'unit_num' => $item->unit_number,
                'unit_floor' => $item->unit_floor
            ];
        }

        $keys = array_keys($property_unit_arr);
        $current_unit_id = $property_unit_arr[$keys[array_search(Request::get('unit_id'), $keys)]];

        // Get Previous PropertyUnit
        if(array_search(Request::get('unit_id'), $keys)-1 >= 0){
            $prev_unit_id = $property_unit_arr[$keys[array_search(Request::get('unit_id'), $keys)-1]];
        }else{
            $prev_unit_id = [
                'id' => '',
                'unit' => ''
            ];
        }
        // Get Next PropertyUnit
        if(array_search(Request::get('unit_id'), $keys)+1 < count($property_unit_arr)){
            $next_unit_id = $property_unit_arr[$keys[array_search(Request::get('unit_id'), $keys)+1]];
        }else{
            $next_unit_id = [
                'id' => '',
                'unit' => ''
            ];
        }

        $list_month = [];
        $counter = 0;
        while($counter < 12) {
            if($developer_group == 'msm') {
                $list_month[Carbon::now()->firstOfMonth()->addMonth(1)->subMonth($counter)->format('Y-m')] = getMonthYearText(Carbon::now()->firstOfMonth()->addMonth(1)->subMonth($counter)->format('Y-m'));
            }else{
                $list_month[Carbon::now()->firstOfMonth()->subMonth($counter)->format('Y-m')] = getMonthYearText(Carbon::now()->firstOfMonth()->subMonth($counter)->format('Y-m'));
            }
            $counter++;
        }

        $old_unit = ($bill_before_this_month->count()>0) ? $bill_before_this_month->first()->unit : 0;

        $bill_data = [
            'id' => ($bill_this_month->count()>0) ? $bill_this_month->first()->id : "",
            'property_unit_id' => Request::get('unit_id'),
            'property_unit_number' => $current_unit_id['unit_num'],
            'property_unit_floor' => $current_unit_id['unit_floor'],
            'date_period' => $date_period,
            'date_period_text' => $list_month[$date_period],
            'unit' => ($bill_this_month->count()>0) ? $bill_this_month->first()->unit : $old_unit,
            'old_unit' => $old_unit,
            'prev_prop_unit_id' => $prev_unit_id['id'],
            'next_prop_unit_id' => $next_unit_id['id']
        ];

        unset($property_unit_arr);

        return view('property_officer.get-unit-edit-water-form')->with(compact('bill_data','list_month'));
    }

    public function save(){
        $results_water = "";
        if(Request::get('id') != ""){
            $bill_water = BillWater::find(Request::get('id'));
            if(isset($bill_water)) {
                if(!$bill_water->is_service_charge) {
                    $net_unit = (float)Request::get('unit') - (float)Request::get('old_unit');

                    $bill_water->unit = (float)Request::get('unit');
                    $bill_water->net_unit = ($net_unit >= 0) ? $net_unit : 0;
                    $bill_water->save();

                    $results_water = $bill_water;
                }
            }
        }else{
                $net_unit = (float)Request::get('unit') - (float)Request::get('old_unit');
                $bill_new = new BillWater();
                $bill_new->property_unit_id = Request::get('property_unit_id');
                $bill_new->property_id = Auth::user()->property_id;
                $bill_new->bill_date_period = Request::get('bill_date_period');
                $bill_new->unit = (float)Request::get('unit');
                $bill_new->net_unit = ($net_unit >= 0)? (float)$net_unit : (float)0;
                $bill_new->save();
                $results_water = $bill_new;
        }

        $results = [
            'id' => Request::get('property_unit_id'),
            'message' => 'true',
            'value' => floatval($results_water->unit),
            'net_value' => floatval($results_water->net_unit),
        ];

        return $results;
    }
}
