<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Auth;
use Redirect;
use DB;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;
use Carbon\Carbon;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\GeneralFeesBillsController;
# Model
use App\Property;
use App\PropertyUnit;
use App\User;
use App\BillWater;
use App\BillElectric;
use App\Invoice;
use App\Transaction;
use App\PropertyUnitBalanceLog;
use App\Notification;
use App\PropertySettings;
class UtilitiesInvoiceController extends GeneralFeesBillsController {

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

    public function list_ () {
        $prop_setting = PropertySettings::where('property_id',Auth::user()->property_id)->first();
        if(Request::get('period_select')) {
            $date_period = Request::get('period_select');
        }else{
            $date_period = Carbon::now()->format('Y-m');
        }

        $util_list = PropertyUnit::with([
            'eBill' => function ($q) use ($date_period) {
                $q->where( 'bill_date_period',$date_period )->where('is_service_charge',false);
            },
            'eBillFee' => function ($q) use ($date_period) {
                $q->where( 'bill_date_period',$date_period )->where('status',1);
            },
            'wBill' => function ($q) use ($date_period) {
                $q->where('bill_date_period',$date_period)->where('is_service_charge',false);
            },
            'wBillFee' => function ($q) use ($date_period) {
                $q->where( 'bill_date_period',$date_period )->where('status',1);
            }
        ])->where('property_id',Auth::user()->property_id)->where('active',true);

        if(Request::get('unit_id')) {
            $util_list->whereIn('id',Request::get('unit_id'));
        }

        $util_list = $util_list->select('id','unit_number','is_billing_water','is_billing_electric')->orderBy(DB::raw('natsortInt(unit_number)'))->get();

        // Old Unit List
        $select_period_arr = explode("-", $date_period);
        $dt = Carbon::createFromDate($select_period_arr[0], $select_period_arr[1],1);
        $date_old_period = $dt->firstOfMonth()->subMonth()->format('Y-m');
        $util_list_old = PropertyUnit::with([
            'eBill' => function ($q) use ($date_old_period) {
                $q->where( 'bill_date_period',$date_old_period )->where('is_service_charge',false);
            },
            'eBillFee' => function ($q) use ($date_old_period) {
                $q->where( 'bill_date_period',$date_old_period )->where('status',1);
            },
            'wBill' => function ($q) use ($date_old_period) {
                $q->where('bill_date_period',$date_old_period)->where('is_service_charge',false);
            },
            'wBillFee' => function ($q) use ($date_old_period) {
                $q->where( 'bill_date_period',$date_old_period )->where('status',1);
            }
        ])->where('property_id',Auth::user()->property_id)->where('active',true);

        if(Request::get('unit_id')) {
            $util_list_old->whereIn('id',Request::get('unit_id'));
        }

        $util_list_old = $util_list_old->select('id','unit_number','is_billing_water','is_billing_electric')->orderBy(DB::raw('natsortInt(unit_number)'))->get();
        $util_list_old = $util_list_old->toArray();

        $list_month = array( 0 => trans('messages.Meter.period') );

        $counter = 0;
        while($counter < 12) {
            $prevmonth                      = strtotime('first day of -'.$counter.' months');
            $prevmonth_label                = date('Y-m', $prevmonth);
            $list_month[$prevmonth_label]   = getMonthYearText($prevmonth_label);
            $counter++;
        }

        $month_label = $list_month[$date_period];
        if(Request::ajax()) {
            return view('property_officer.util-list-element')->with(compact('util_list','month_label','prop_setting','util_list_old'));
            
        } else {

            $unit_list = [];
		    $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
            $remark = Auth::user()->property->settings->invoice_remark;
            return view('property_officer.util-list')->with(compact('util_list','list_month','month_label','unit_list','prop_setting','util_list_old','remark'));
        }
        
    }

    public function generateInvoiceServiceChargeOnly() {

        if(Request::isMethod('post')) {
            $property = Property::find(Auth::user()->property_id);
            $prop_setting = PropertySettings::where('property_id', Auth::user()->property_id)->first();
            $date_period = Request::get('date_period');
            
            $property_unit = PropertyUnit::where('property_id', Auth::user()->property_id)->where('active',true)->get();
            $total_util =[];
            if ($prop_setting->water_meter_maintenance_fee > 0 || $prop_setting->electric_meter_maintenance_fee > 0){
                foreach ($property_unit as $item) {
                    $total_util[] = [
                        'prop_data'=>$item,
                        'eBill' => BillElectric::where('bill_date_period',$date_period)->where('property_unit_id',$item->id)->first(),
                        'wBill' => BillWater::where('bill_date_period',$date_period)->where('property_unit_id',$item->id)->first()
                    ];
                }
                $prop_id =[];
                foreach ($total_util as $util) {
                    $trans = [];
                    $open_invoice = 0;
                    $total = 0;
                    $grand_total = 0;
                    $final_grand_total = 0;
                    if ($util['prop_data']->type != "3") {
                        $prop_id[] = $util['prop_data']->unit_number;

                        // Water Service Charge
                        if ($util['wBill'] && $util['wBill']->net_unit != 0) {
                            // มีการใช้น้ำ ไม่ต้องออกค่ารักษามิเตอร์ตอนนี้ (เพราะยังไงออกใบปกติก็ได้ออก)
                            $dasdsaas = "";
                        } else {
                            if($prop_setting->water_meter_maintenance_fee > 0 && $util['prop_data']->is_billing_water) {
                                $open_invoice = $open_invoice + 1;
                                $trans[] = new Transaction ([
                                    'detail' => trans('messages.Meter.water_maintain_fee', array(), null, $util['prop_data']->contact_lang),
                                    'quantity' => 1,
                                    'price' => $prop_setting->water_meter_maintenance_fee,
                                    'total' => $prop_setting->water_meter_maintenance_fee,
                                    'transaction_type' => 1,
                                    'property_id' => $property->id,
                                    'property_unit_id' => $util['prop_data']->id,
                                    'for_external_payer' => false,
                                    'category' => 8,
                                    'due_date' => Request::get('due_date')
                                ]);
                                $total += $prop_setting->water_meter_maintenance_fee;
                                $grand_total += $prop_setting->water_meter_maintenance_fee;
                                $final_grand_total += $prop_setting->water_meter_maintenance_fee;
                            }
                        }


                        //
                        // Electrice Service Charge
                        if ($util['eBill'] && $util['eBill']->net_unit != 0) {
                            // มีการใช้ไฟฟ้า ไม่ต้องออกค่ารักษามิเตอร์ตอนนี้ (เพราะยังไงออกใบปกติก็ได้ออก)
                            $dasdsaas = "";
                        } else {
                            if($prop_setting->electric_meter_maintenance_fee > 0 && $util['prop_data']->is_billing_electric) {
                                $open_invoice = $open_invoice + 1;
                                $trans[] = new Transaction ([
                                    'detail' => trans('messages.Meter.electric_maintain_fee', array(), null, $util['prop_data']->contact_lang),
                                    'quantity' => 1,
                                    'price' => $prop_setting->electric_meter_maintenance_fee,
                                    'total' => $prop_setting->electric_meter_maintenance_fee,
                                    'transaction_type' => 1,
                                    'property_id' => $property->id,
                                    'property_unit_id' => $util['prop_data']->id,
                                    'for_external_payer' => false,
                                    'category' => 9,
                                    'due_date' => Request::get('due_date')
                                ]);
                                $total += $prop_setting->electric_meter_maintenance_fee;
                                $grand_total += $prop_setting->electric_meter_maintenance_fee;
                                $final_grand_total += $prop_setting->electric_meter_maintenance_fee;
                            }
                        }


                        if ($open_invoice > 0) {
                            $invoice = new Invoice;
                            $invoice->fill(Request::all());
                            //$invoice->tax 				= $tax;
                            $invoice->type = 1;
                            $invoice->property_id = $property->id;
                            $invoice->invoice_no = ++$property->invoice_counter;
                            $invoice->transfer_only = (Request::get('transfer_only')) ? true : false;
                            $invoice->property_unit_id = $util['prop_data']->id;
                            $invoice->save();

                            $month = Carbon::now()->month;
                            $year = Carbon::now()->year;
                            $date_period_format = $year . $month;

                            $this->getMonthlyCounterDoc($date_period_format, Auth::user()->property_id);
                            $invoice_no_label = $this->generateRunningLabel('INVOICE', $invoice, $invoice->invoice_no, Auth::user()->property_id);
                            $invoice->invoice_no_label = $invoice_no_label;

                            // Increase monthlyCounterDoc
                            $this->increaseMonthlyCounterDocByPeriod($date_period_format, 'INVOICE', Auth::user()->property_id);

                            $invoice->total = $total;
                            $invoice->grand_total = $grand_total;
                            $invoice->final_grand_total = $final_grand_total;
                            //$invoice->save();

                            $invoice->transaction()->saveMany($trans);
                            $invoice->save();

                            //reget invoice
                            $invoice 		    = Invoice::with('transaction')->find($invoice->id);
                            $util = PropertyUnit::find($util['prop_data']->id);
                            $cal_balance_flag   = ( $util->balance > 0 && !Request::get('transfer_only'));
                            $remaining          = $invoice->final_grand_total;
                            if($cal_balance_flag) {

                                $invoice->balance_before = $util->balance;
                                $sum_sub = 0;
                                $current_balance = $util->balance;

                                foreach ($invoice->transaction as $tr) {
                                    //chaeck calulate balance
                                    if( $tr->transaction_type == 1 && $cal_balance_flag) {
                                        if($current_balance > 0 ) {
                                            $balance 				= $this->calTransactionBalance ($util->balance,$tr->total);
                                            $sum_sub               += $balance['sub_to_balance'];
                                            $tr->sub_from_balance 	= $balance['sub_to_balance'];
                                            $util->balance = $balance['calculated_balance'];
                                            $tr->save();
                                        }
                                    }
                                }

                                $remaining = $invoice->grand_total-$sum_sub;
                                if($remaining > 0)
                                    $invoice->final_grand_total = $remaining;
                                $invoice->sub_from_balance 		= $sum_sub;
                                $util->save();
                                $log = new PropertyUnitBalanceLog;
                                $log->balance = "-".$sum_sub;
                                $log->property_id = $invoice->property_id;
                                $log->property_unit_id = $invoice->property_unit_id;
                                $log->invoice_id = $invoice->id;
                                $log->save();
                                //set pyment status to true if balance enough for invoice payment
                                if($current_balance >= $invoice->grand_total) {
                                    $pd = date('Y-m-d');
                                    // Change transaction and payment status
                                    foreach ($invoice->transaction as $tr) {
                                        $tr->payment_status = true;
                                        $tr->payment_date = $tr->submit_date = $pd;
                                        $tr->save();
                                    }

                                    $month = Carbon::now()->month;
                                    $year = Carbon::now()->year;
                                    $date_period_format = $year . $month;

                                    $this->getMonthlyCounterDoc($date_period_format, Auth::user()->property_id);
                                    $receipt_no_label = $this->generateRunningLabel('RECEIPT', null, null, Auth::user()->property_id);

                                    $invoice->receipt_no_label = $receipt_no_label;
                                    $invoice->save();

                                    // Increase monthlyCounterDoc
                                    $this->increaseMonthlyCounterDocByPeriod($date_period_format, 'RECEIPT', Auth::user()->property_id);
                                    // End Generate Running Number

                                    // change invoice status and set it to become a receipt
                                    $invoice->receipt_no 	=  ++$property->receipt_counter;
                                    $invoice->payment_status = 2;
                                    $invoice->payment_date = $pd;
                                    $invoice->payment_type = 3;

                                }
                            }

                            if( $remaining > 0) {
                                $this->sendInvoiceNotification ($util->id, $invoice->name, $invoice->id);
                            } else {
                                $invoice->save();
                            }

                            // Save Counter
                            $property->save();
                        }
                    }
                }
            }
        }

        return redirect('admin/fees-bills/invoice');
    }

    /*//////////////////
	/* function to substract invoice from property unit balance account
	/*	return array of
	/*	t_final_total 		=> transaction final total
	/*	sub_to_balance 		=> real substract value for balance
	/*	calculated_balance 	=> current calculated balance
	*/////////////////
	function calTransactionBalance ($balance,$total) {
		// add discount back because it will used as substractor again when exportings report
	    if( $balance >= $total )
	        return array('t_final_total' => 0, 'sub_to_balance' => $total, 'calculated_balance' => $balance-$total);
	    else {
	        $sub = $total-$balance;
	        return array('t_final_total' => $sub, 'sub_to_balance' => $balance, 'calculated_balance' => 0);
	    }
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

    public function generateInvoice() {

        if(Request::isMethod('post')) {
            $property 		= Property::find(Auth::user()->property_id);
            $date_period = Request::get('date_period');

            if(!empty(Request::get('units'))) {

                $prop_setting = PropertySettings::where('property_id',Auth::user()->property_id)->first();

                foreach(Request::get('units') as $unit) {
                    $u_id = $unit['unit_id'];
                    $util = PropertyUnit::with([
                        'eBill' => function ($q) use ($date_period) {
                            $q->where('bill_date_period', $date_period);
                        },
                        'wBill' => function ($q) use ($date_period) {
                            $q->where('bill_date_period', $date_period);
                        }
                    ])->where('property_id', Auth::user()->property_id)->find($u_id);

                    // all total for discount
                    $all_total = 0;

                    $invoice = null;
                    $trans = [];
                    $bill_water_new = null;
                    $bill_electric_new = null;
                    $sum_total = 0;
                    $sum_grand_total = 0;
                    $sum_final_grand_total = 0;
                    $ordering = 0;

                    // เช็ค service charge ของค่าน้ำ และต้องเช็คด้วยว่ามีการ checked ในหน้าออกใบแจ้งหนี้หรือไม่
                    if (!$prop_setting->include_fixed_cost_to_cf_bill
                        && $util->is_billing_water
                        && ( $prop_setting->water_meter_maintenance_fee != 0 || $util->water_meter_rate != 0 )
                        && !empty($unit['util']['water'])) {

                        $meter_rate = ($util->water_meter_rate != 0)?$util->water_meter_rate:$prop_setting->water_meter_maintenance_fee;

                        $trans[] = new Transaction ([
                            'detail' => trans('messages.Meter.water_maintain_fee', array(), null, $util->contact_lang),
                            'quantity' => 1,
                            'price' => $meter_rate,
                            'total' => $meter_rate,
                            'transaction_type' => 1,
                            'property_id' => $property->id,
                            'property_unit_id' => $util->id,
                            'for_external_payer' => false,
                            'category' => 31,
                            'due_date' => Request::get('due_date'),
                            'ordering' => ++$ordering
                        ]);

                        $bill_water_new = new BillWater();
                        $bill_water_new->property_unit_id = $util->id;
                        $bill_water_new->status = 1;
                        $bill_water_new->property_id = Auth::user()->property_id;
                        $bill_water_new->bill_date_period = $date_period;
                        $bill_water_new->unit = 0;
                        $bill_water_new->net_unit = 0;
                        $bill_water_new->is_service_charge = true;
                        //$bill_new->invoice_id = $invoice->id;
                        $bill_water_new->save();

                        $sum_total += $meter_rate;
                        $sum_grand_total += $meter_rate;
                        $sum_final_grand_total += $meter_rate;
                        // add all total for discount
                        $all_total += $meter_rate;
                    }

                    // เช็ค service charge ของค่าไฟ
                    if (!$prop_setting->include_fixed_cost_to_cf_bill
                        && $util->is_billing_electric
                        && ($prop_setting->electric_meter_maintenance_fee != 0 || $util->electric_meter_rate != 0)
                        && !empty($unit['util']['electric'])) {

                        $meter_rate = ($util->electric_meter_rate != 0)?$util->electric_meter_rate:$prop_setting->electric_meter_maintenance_fee;

                        $trans[] = new Transaction ([
                            'detail' => trans('messages.Meter.electric_maintain_fee', array(), null, $util->contact_lang),
                            'quantity' => 1,
                            'price' => $meter_rate,
                            'total' => $meter_rate,
                            'transaction_type' => 1,
                            'property_id' => $property->id,
                            'property_unit_id' => $util->id,
                            'for_external_payer' => false,
                            'category' => 39,
                            'due_date' => Request::get('due_date'),
                            'ordering' => ++$ordering
                        ]);

                        $bill_electric_new = new BillElectric();
                        $bill_electric_new->property_unit_id = $util->id;
                        $bill_electric_new->status = 1;
                        $bill_electric_new->property_id = Auth::user()->property_id;
                        $bill_electric_new->bill_date_period = $date_period;
                        $bill_electric_new->unit = 0;
                        $bill_electric_new->net_unit = 0;
                        $bill_electric_new->is_service_charge = true;
                        //$bill_new->invoice_id = $invoice->id;
                        $bill_electric_new->save();

                        $sum_total += $meter_rate;
                        $sum_grand_total += $meter_rate;
                        $sum_final_grand_total += $meter_rate;
                        // add all total for discount
                        $all_total += $meter_rate;
                    }

                    if (count($trans) > 0) {
                        $invoice = new Invoice;
                        $invoice->fill(Request::all());
                        $invoice->type = 1;
                        $invoice->property_id = $property->id;
                        $invoice->invoice_no = ++$property->invoice_counter;
                        $invoice->transfer_only = (Request::get('transfer_only')) ? true : false;
                        $invoice->property_unit_id = $util->id;
                        $invoice->total = $invoice->grand_total = $invoice->final_grand_total = 0;
                        $invoice->save();

                        $month = Carbon::now()->month;
                        $year = Carbon::now()->year;
                        $date_period_format = $year . $month;

                        $this->getMonthlyCounterDoc($date_period_format, Auth::user()->property_id);
                        $invoice_no_label = $this->generateRunningLabel('INVOICE', $invoice, $invoice->invoice_no, Auth::user()->property_id);
                        $invoice->invoice_no_label = $invoice_no_label;

                        // Increase monthlyCounterDoc
                        $this->increaseMonthlyCounterDocByPeriod($date_period_format, 'INVOICE', Auth::user()->property_id);
                        $invoice->save();

                        if ($bill_water_new != null) {
                            $bill_water_new->invoice_id = $invoice->id;
                            $bill_water_new->save();
                        }

                        if ($bill_electric_new != null) {
                            $bill_electric_new->invoice_id = $invoice->id;
                            $bill_electric_new->save();
                        }
                    }

                    // คำนวณค่าน้ำตามจำนวนยูนิต
                    if ($util->is_billing_water && $util->wBill && $util->wBill->net_unit > 0 && $prop_setting->water_billing_type != 0 && !empty($unit['util']['water'])) {
                        // สรัาง invoice กรณีที่ยังไม่มีการจัดเก็บ service charge
                        if ($invoice == null) {
                            $invoice = new Invoice;
                            $invoice->fill(Request::all());
                            $invoice->type = 1;
                            $invoice->property_id = $property->id;
                            $invoice->invoice_no = ++$property->invoice_counter;
                            $invoice->transfer_only = (Request::get('transfer_only')) ? true : false;
                            $invoice->property_unit_id = $util->id;
                            $invoice->total = $invoice->grand_total = $invoice->final_grand_total = 0;
                            $invoice->save();

                            $month = Carbon::now()->month;
                            $year = Carbon::now()->year;
                            $date_period_format = $year . $month;

                            $this->getMonthlyCounterDoc($date_period_format, Auth::user()->property_id);
                            $invoice_no_label = $this->generateRunningLabel('INVOICE', $invoice, $invoice->invoice_no, Auth::user()->property_id);
                            $invoice->invoice_no_label = $invoice_no_label;

                            // Increase monthlyCounterDoc
                            $this->increaseMonthlyCounterDocByPeriod($date_period_format, 'INVOICE', Auth::user()->property_id);
                            $invoice->save();
                        }

                        $water_rate = 0;
                        if($util->water_billing_rate > 0){
                            $water_rate = $util->water_billing_rate;
                        }else{
                            if($prop_setting->water_billing_rate != null){
                                $water_rate = $prop_setting->water_billing_rate;
                            }
                        }
                        $rate_water_data = [
                            'type' => $prop_setting->water_billing_type,
                            'num_user' => $util->resident_count != null ? $util->resident_count : 1,
                            //'rate' => $prop_setting->water_billing_rate != null ? $prop_setting->water_billing_rate : 0,
                            'rate' => $water_rate,
                            'minimum_price' => $prop_setting->water_billing_minimum_price != null ? $prop_setting->water_billing_minimum_price : 0,
                            'minimum_unit' => $prop_setting->water_billing_minimum_unit != null ? $prop_setting->water_billing_minimum_unit : 0,
                            'progressive_rate' => $prop_setting->water_progressive_rate
                        ];

                        $quantity = $util->wBill->net_unit;
                        $quantity_invoice = $quantity;
                        if ($prop_setting->water_billing_type == 6) { //แบบขั้นบันได
                            $price = rateWaterElectricPriceByProgressiveRate($rate_water_data, $quantity);
                        } else {
                            if ($prop_setting->water_billing_type == 1) { //แบบเหมาจ่าย quantity ใน invoice ต้องแสดง 1
                                $quantity_invoice = 1;
                            }
                            if ($prop_setting->water_billing_type == 2) { //แบบเหมาจ่ายรายคน quantity ใน invoice ต้องแสดงตามจำนวนคน
                                $quantity_invoice = $util->resident_count != null ? $util->resident_count : 1;
                            }
                            $price = $water_rate;
                        }

                        $total = calWaterElectricPriceByType($rate_water_data, $quantity);

                        if ($prop_setting->water_billing_type == 1 || $prop_setting->water_billing_type == 2) {
                            $trans[] = new Transaction ([
                                'detail' => trans('messages.Meter.water'),
                                'quantity' => $quantity_invoice,
                                'price' => $price,
                                'total' => $total,
                                'transaction_type' => 1,
                                'property_id' => $property->id,
                                'property_unit_id' => $util->id,
                                'for_external_payer' => false,
                                'category' => 8,
                                'due_date' => Request::get('due_date'),
                                'ordering' => ++$ordering
                            ]);
                        } else {
                            $old_meter = $util->wBill->unit - $quantity;
                            $old_meter_num = number_format((float)$old_meter, 2, '.', '');
                            $details = trans('messages.Meter.water', array(), null, $util->contact_lang) . " (" . trans('messages.Meter.old_meter', array(), null, $util->contact_lang) . ": " . $old_meter_num . ")" . " (" . trans('messages.Meter.last_meter', array(), null, $util->contact_lang) . ": " . $util->wBill->unit . ")";

                            $trans[] = new Transaction ([
                                'detail' => $details,
                                'quantity' => $quantity_invoice,
                                'price' => $price,
                                'total' => $total,
                                'transaction_type' => 1,
                                'property_id' => $property->id,
                                'property_unit_id' => $util->id,
                                'for_external_payer' => false,
                                'category' => 8,
                                'due_date' => Request::get('due_date'),
                                'ordering' => ++$ordering
                            ]);
                        }

                        if( $util->waste_water_treatment > 0 ) {
                            $waste_total = $quantity_invoice * $util->waste_water_treatment;
                            $trans[] = new Transaction ([
                                'detail' => trans('messages.Prop_unit.waste_water_treatment', array(), null, $util->contact_lang),
                                'quantity' => $quantity_invoice,
                                'price' => $util->waste_water_treatment,
                                'total' => $waste_total,
                                'transaction_type' => 1,
                                'property_id' => $property->id,
                                'property_unit_id' => $util->id,
                                'for_external_payer' => false,
                                'category' => 32,
                                'due_date' => Request::get('due_date'),
                                'ordering' => ++$ordering
                            ]);
                            $total += $waste_total;
                        }

                        $util->wBill->status = 1;
                        $util->wBill->invoice_id = $invoice->id;
                        $util->wBill->save();

                        $invoice->total += $total;
                        $invoice->grand_total += $total;
                        $invoice->final_grand_total += $total;
                    }

                    // คำนวณค่าไฟตามจำนวนยูนิต
                    if ($util->is_billing_electric && $util->eBill && $util->eBill->net_unit > 0 && $prop_setting->electric_billing_type != 0 && !empty($unit['util']['electric'])) {
                        if ($invoice == null) {
                            $invoice = new Invoice;
                            $invoice->fill(Request::all());
                            $invoice->type = 1;
                            $invoice->property_id = $property->id;
                            $invoice->invoice_no = ++$property->invoice_counter;
                            $invoice->transfer_only = (Request::get('transfer_only')) ? true : false;
                            $invoice->property_unit_id = $util->id;
                            $invoice->total = $invoice->grand_total = $invoice->final_grand_total = 0;
                            $invoice->save();

                            $month = Carbon::now()->month;
                            $year = Carbon::now()->year;
                            $date_period_format = $year . $month;

                            $this->getMonthlyCounterDoc($date_period_format, Auth::user()->property_id);
                            $invoice_no_label = $this->generateRunningLabel('INVOICE', $invoice, $invoice->invoice_no, Auth::user()->property_id);
                            $invoice->invoice_no_label = $invoice_no_label;

                            // Increase monthlyCounterDoc
                            $this->increaseMonthlyCounterDocByPeriod($date_period_format, 'INVOICE', Auth::user()->property_id);
                            $invoice->save();
                        }

                        $electric_rate = 0;
                        if($util->electric_billing_rate > 0){
                            $electric_rate = $util->electric_billing_rate;
                        }else{
                            if($prop_setting->electric_billing_rate != null){
                                $electric_rate = $prop_setting->electric_billing_rate;
                            }
                        }

                        $rate_electric_data = [
                            'type' => $prop_setting->electric_billing_type,
                            'num_user' => $util->resident_count != null ? $util->resident_count : 1,
                            //'rate' => $prop_setting->electric_billing_rate != null ? $prop_setting->electric_billing_rate : 0,
                            'rate' => $electric_rate,
                            'minimum_price' => $prop_setting->electric_billing_minimum_price != null ? $prop_setting->electric_billing_minimum_price : 0,
                            'minimum_unit' => $prop_setting->electric_billing_minimum_unit != null ? $prop_setting->electric_billing_minimum_unit : 0,
                            'progressive_rate' => $prop_setting->electric_progressive_rate
                        ];

                        $price = $electric_rate;
                        $quantity = $util->eBill->net_unit;
                        $quantity_invoice = $quantity;
                        if ($prop_setting->electric_billing_type == 6) { //แบบขั้นบันได
                            $price = rateWaterElectricPriceByProgressiveRate($rate_electric_data, $quantity);
                        } else {
                            if ($prop_setting->electric_billing_type == 1) { //แบบเหมาจ่าย quantity ใน invoice ต้องแสดง 1
                                $quantity_invoice = 1;
                            }
                            if ($prop_setting->electric_billing_type == 2) { //แบบเหมาจ่ายรายคน quantity ใน invoice ต้องแสดงตามจำนวนคน
                                $quantity_invoice = $util->resident_count != null ? $util->resident_count : 1;
                            }
                        }

                        $total = calWaterElectricPriceByType($rate_electric_data, $quantity);

                        if ($prop_setting->electric_billing_type == 1 || $prop_setting->electric_billing_type == 2) {
                            $trans[] = new Transaction ([
                                'detail' => trans('messages.Meter.electric'),
                                'quantity' => $quantity_invoice,
                                'price' => $price,
                                'total' => $total,
                                'transaction_type' => 1,
                                'property_id' => $property->id,
                                'property_unit_id' => $util->id,
                                'for_external_payer' => false,
                                'category' => 9,
                                'due_date' => Request::get('due_date'),
                                'ordering' => ++$ordering
                            ]);
                        } else {
                            $old_meter = $util->eBill->unit - $quantity;
                            $old_meter_num = number_format((float)$old_meter, 2, '.', '');
                            $details = trans('messages.Meter.electric', array(), null, $util->contact_lang) . " (" . trans('messages.Meter.old_meter', array(), null, $util->contact_lang) . ": " . $old_meter_num . ")" . " (" . trans('messages.Meter.last_meter', array(), null, $util->contact_lang) . ": " . $util->eBill->unit . ")";

                            $trans[] = new Transaction ([
                                'detail' => $details,
                                'quantity' => $quantity_invoice,
                                'price' => $price,
                                'total' => $total,
                                'transaction_type' => 1,
                                'property_id' => $property->id,
                                'property_unit_id' => $util->id,
                                'for_external_payer' => false,
                                'category' => 9,
                                'due_date' => Request::get('due_date'),
                                'ordering' => ++$ordering
                            ]);
                        }

                        $util->eBill->status = 1;
                        $util->eBill->invoice_id = $invoice->id;
                        $util->eBill->save();

                        $invoice->total += $total;
                        $invoice->grand_total += $total;
                        $invoice->final_grand_total += $total;
                    }

                    if (count($trans) > 0) {
                        $invoice->total += $sum_total;
                        $invoice->grand_total += $sum_grand_total;
                        $invoice->final_grand_total += $sum_final_grand_total;

                        $invoice->transaction()->saveMany($trans);

                        $invoice->save();

                        // add discount
                        if($util->utility_discount > 0 && $all_total > 0) {
                            $dc = ($all_total * $util->utility_discount/100);
                            $trans_ud = new Transaction();
                            $trans_ud->invoice_id 		= $invoice->id;
                            $trans_ud->detail 			= trans('messages.Prop_unit.unit_utility_discount', array(), null, $util->contact_lang). " (". $util->utility_discount."%)";
                            $trans_ud->quantity 		= 1;
                            $trans_ud->price 			= -$dc;
                            $trans_ud->total 			= -$dc;
                            $trans_ud->category 		= 30;
                            $trans_ud->transaction_type = 1;
                            $trans_ud->property_id 		= $util->property_id;
                            $trans_ud->property_unit_id = $util->id;
                            $trans_ud->due_date			= $invoice->due_date;
                            $trans_ud->ordering         = ++$ordering;
                            $trans_ud->save();
                            $invoice->total 		    -= $dc;
                            $invoice->grand_total 	    -= $dc;
                            $invoice->final_grand_total -= $dc;
                            $invoice->save();
                        }
                    }

                    //reget invoice => เช็คกรณีมีการฝากเงินล่วงหน้า
                    if ($invoice != null){
                        $invoice = Invoice::with('transaction')->find($invoice->id);
                        $cal_balance_flag = ($util->balance > 0 && !Request::get('transfer_only'));
                        $remaining = $invoice->final_grand_total;
                        if ($cal_balance_flag) {

                            $invoice->balance_before = $util->balance;
                            $sum_sub = 0;
                            $current_balance = $util->balance;

                            foreach ($invoice->transaction as $tr) {
                                //check calculate balance
                                if ($tr->transaction_type == 1 && $cal_balance_flag) {
                                    if ($current_balance > 0) {
                                        $balance = $this->calTransactionBalance($util->balance, $tr->total);
                                        $sum_sub += $balance['sub_to_balance'];
                                        $tr->sub_from_balance = $balance['sub_to_balance'];
                                        $util->balance = $balance['calculated_balance'];
                                        $tr->save();
                                    }
                                }
                            }

                            $remaining = $invoice->grand_total - $sum_sub;
                            if ($remaining > 0)
                                $invoice->final_grand_total = $remaining;
                            $invoice->sub_from_balance = $sum_sub;
                            $util->save();
                            $log = new PropertyUnitBalanceLog;
                            $log->balance = "-" . $sum_sub;
                            $log->property_id = $invoice->property_id;
                            $log->property_unit_id = $invoice->property_unit_id;
                            $log->invoice_id = $invoice->id;
                            $log->save();
                            //set pyment status to true if balance enough for invoice payment
                            if ($current_balance >= $invoice->grand_total) {
                                $pd = date('Y-m-d');
                                // Change transaction and payment status
                                foreach ($invoice->transaction as $tr) {
                                    $tr->payment_status = true;
                                    $tr->payment_date = $tr->submit_date = $pd;
                                    $tr->save();
                                }

                                $month = Carbon::now()->month;
                                $year = Carbon::now()->year;
                                $date_period_format = $year . $month;

                                $this->getMonthlyCounterDoc($date_period_format, Auth::user()->property_id);
                                $receipt_no_label = $this->generateRunningLabel('RECEIPT', null, null, Auth::user()->property_id);

                                $invoice->receipt_no_label = $receipt_no_label;
                                $invoice->save();

                                // Increase monthlyCounterDoc
                                $this->increaseMonthlyCounterDocByPeriod($date_period_format, 'RECEIPT', Auth::user()->property_id);
                                // End Generate Running Number

                                // change invoice status and set it to become a receipt
                                $invoice->receipt_no = ++$property->receipt_counter;
                                $invoice->payment_status = 2;
                                $invoice->payment_date = $pd;
                                $invoice->payment_type = 3;
                                // Save Counter
                                $property->save();
                            }
                            $invoice->save();
                        }

                        if ($remaining > 0) {
                            $this->sendInvoiceNotification($util->id, $invoice->name, $invoice->id);
                        } else {
                            $invoice->save();
                        }
                    }
                }
                // Save Counter
                $property->save();
            }
            return redirect('admin/fees-bills/invoice');
        }
    }
}