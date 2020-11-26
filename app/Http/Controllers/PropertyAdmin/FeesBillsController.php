<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use App\Http\Controllers\GeneralFeesBillsController;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\DirectPushNotificationController;
use App;
//use DateTime;
# Model
use DB;
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\PropertyUnit;
use App\Property;
use App\Vehicle;
use App\Notification;
use App\User;
use App\InvoiceRevision;
use App\CommonFeesRef;
use App\PropertyUnitBalanceLog;
use App\InvoiceInstalmentLog;
use App\Bank;
use App\BankTransaction;
use App\BillElectric;
use App\BillWater;
use App\MonthlyCounterDoc;
use App\YearlyCounterDoc;
use App\TotalCounterDoc;
use Carbon\Carbon;
use App\ReceiptInvoiceLog;
use App\ReceiptInvoiceAggregate;
use App\PropertyFeature;
use App\UserPropertyFeature;
use App\TransactionRef;

class FeesBillsController extends GeneralFeesBillsController {

	public function __construct () {
        if(Auth::check() && Auth::user()->role == 3){
            $this->middleware('auth:menu_finance_group');
        }
		$this->middleware('auth');
		view()->share('active_menu', 'bill');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function invoiceList (Request $form) {
		$show_debt = false;
		$bills = Invoice::where('property_id','=',Auth::user()->property_id)
            ->where('is_retroactive_record',false)
            ->where('is_revenue_record',false)->whereNotIn('payment_status',[2,5,6]);

		if( $form::get('invoice-status') != "") {
			$bills->where('payment_status',$form::get('invoice-status'));
		} elseif( empty($form::get('invoice-no')) ) {
			$bills->whereIn('payment_status',[0,1]);
		}

		if($form::isMethod('post')) {
			$debt = 0;
            $invoice_no = $form::get('invoice-no');
            if($invoice_no != ""){
                $bills->where('invoice_no_label','like','%'.trim($invoice_no).'%');
            }

			if(!empty($form::get('invoice-unit_id')) && ($form::get('invoice-unit_id') != "-")) {
                $bid = $form::get('invoice-unit_id');
                $bills->whereHas('property_unit', function ($query) use ($bid) {
                    $query->where('property_unit_unique_id', $bid);
                });

				$show_debt = true;
				$debt = $this->getPropertyUnitDebt($form::get('invoice-unit_id'));
			} elseif($form::get('invoice-unit_id') != "-") {
				$bills->whereNull('property_unit_id');
			}

            if(!empty($form::get('owner_name'))) {
                $name = $form::get('owner_name');
                $bills->whereHas('property_unit', function ($query) use ($name) {
                    $query->where('owner_name_th', 'like', "%".$name."%");
                    $query->where('owner_name_en', 'like', "%".$name."%");
                });
            }

            if(!empty($form::get('floor'))) {
                $floor = $form::get('floor');
                $bills->whereHas('property_unit', function ($query) use ($floor) {
                    $query->where('unit_floor', $floor);
                });
            }

            if(!empty($form::get('start-due-date'))) {
				$bills->where('due_date','>=',$form::get('start-due-date'));
			}

			if(!empty($form::get('end-due-date'))) {
				$bills->where('due_date','<=',$form::get('end-due-date'));
			}

			if( $form::get('payment-method') == 1) {
				$bills->where('transfer_only', true);
			}

			if( $form::get('payer') == 1) {
				$bills->where('for_external_payer', true);
			}
		}

        $bills = $bills->where('type',1)->orderBy('invoice_no_label','desc')->paginate(50);
		if(!$form::ajax()) {
			$unit_list = array('-'=> trans('messages.unit_no'));
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','property_unit_unique_id')->toArray();
			return view('feesbills.admin-invoice-list')->with(compact('bills','unit_list','show_debt'));
		} else {
			return view('feesbills.admin-invoice-list-element')->with(compact('bills','show_debt','debt'));
		}

	}

	public function receiptList (Request $form) {
		$bills = Invoice::where('property_id','=',Auth::user()->property_id)->where('is_revenue_record',false);
		if($form::isMethod('post')) {
		    $invoice_no = $form::get('invoice-no');
		    if($invoice_no != ""){
                $bills->where('receipt_no_label','like','%'.trim($invoice_no).'%');
            }

			if(!empty($form::get('invoice-unit_id'))) {
						$bid = $form::get('invoice-unit_id');
				$bills->whereHas('property_unit', function ($query) use ($bid) {
				   $query->where('property_unit_unique_id', $bid);
				});
			}

            if(!empty($form::get('owner_name'))) {
                $name = $form::get('owner_name');
                $bills->whereHas('property_unit', function ($query) use ($name) {
                    $query->where('owner_name_th', 'like', "%".$name."%");
                    $query->where('owner_name_en', 'like', "%".$name."%");
                });
            }

            if(!empty($form::get('floor'))) {
                $floor = $form::get('floor');
                $bills->whereHas('property_unit', function ($query) use ($floor) {
                    $query->where('unit_floor', $floor);
                });
            }

			if(!empty($form::get('start-due-date'))) {
				$bills->where('payment_date','>=',$form::get('start-due-date'));
			}

			if(!empty($form::get('end-due-date'))) {
				$bills->where('payment_date','<=',$form::get('end-due-date'));
			}

			if( $form::get('payer') == 1) {
				$bills->where('for_external_payer', true);
			} elseif( $form::get('payer') == 2) {
				$bills->where('is_petty_cash_bill', true);
			}
		}

        $bills = $bills->where('type',1)->whereIn('payment_status',[2,5])->orderBy('from_imported','asc')->orderBy('receipt_no_label','desc')->orderBy('receipt_no','desc')->paginate(50);

		if(!$form::ajax()) {
			$unit_list = array(''=> trans('messages.unit_no') );
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','property_unit_unique_id')->toArray();
			return view('feesbills.admin-receipt-list')->with(compact('bills','unit_list'));
		} else {
			return view('feesbills.admin-receipt-list-element')->with(compact('bills'));
		}


	}

    public function viewbill ($id,$print=false,$is_copy_invoice=false)
    {
        $bill_check_type = Invoice::find($id);
        if (isset($bill_check_type->property_unit_id)) {

            $bill = Invoice::with(array('instalmentLog' => function ($q) {
                return $q->orderBy('created_at', 'ASC');
            }, 'property', 'property.settings', 'property_unit', 'transaction', 'invoiceFile', 'commonFeesRef',
                'receiptInvoiceAggregate' => function ($q) {
                    $q  ->join('invoice', 'invoice_id', '=', 'invoice.id')
                        ->orderBy('invoice.invoice_no_label', 'ASC');
                }))->find($id);

        } else {
            $bill = Invoice::with('property', 'transaction', 'invoiceFile', 'commonFeesRef')->find($id);
        }

        if($bill->payment_status == 2 || $bill->payment_status == 5) {
            return view('feesbills.admin-receipt-view')->with(compact('bill'));
        } else {
            //check overdue invoice
            $is_overdue_invoice = false;
            if(!$bill->submit_date) {
                if( strtotime(date('Y-m-d')) > strtotime($bill->due_date) )
                    $is_overdue_invoice = true;
            } else {
                $day_submit = date('Y-m-d',strtotime($bill->submit_date));
                if ( strtotime( $day_submit ) > strtotime( $bill->due_date ) )
                    $is_overdue_invoice = true;
            }

            $cal_cf_fine_flag = $cal_cf_house_fine_flag =
            $cal_normal_bill_fine_flag = false;

            /// generate ref code
            if( !$bill->smart_bill_ref_code ) {
				$property_code = $this->getPropertyCode($bill->property);
				$invoice_no = $this->getInvoiceNo ($bill);
                $bill->smart_bill_ref_code = generateQrRefCode($property_code,$invoice_no);
                $bill->timestamps = false;
                $bill->save();
            }

            return view('feesbills.admin-invoice-view-to-receipt')->with(compact('bill','is_overdue_invoice','cal_cf_fine_flag','cal_cf_house_fine_flag','cal_normal_bill_fine_flag'));
        }


    }

    public function createReceipt ($id,$print=false,$is_copy_invoice=false) {
		$bill_check_type = Invoice::find($id);
        if(isset($bill_check_type->property_unit_id)) {

            $bill = Invoice::with( array('instalmentLog' => function($q) {
				    return $q->orderBy('created_at', 'ASC');
				},'property', 'property.settings', 'property_unit', 'transaction', 'invoiceFile','commonFeesRef') )->find($id);

				$unit_cf_rate = $this->getCfUnitRate ($bill->property,$bill->property_unit);

        }else{
            $bill = Invoice::with('property', 'transaction', 'invoiceFile','commonFeesRef')->find($id);
			$unit_cf_rate = 0;
        }

		if($bill->payment_status == 2 && $is_copy_invoice != true)
		    if($print){
                return compact('bill');
            }else {
                return view('feesbills.admin-receipt-view')->with(compact('bill'));
            }
		else {
			// get month range list
			$range = unserialize(constant('CF_RATE_RANGE_'.strtoupper(App::getLocale())));
			$t = array(); $r = count($range); $i = 1;
			foreach ($range as $k => $v)
			{
			    $t[$i] = $range[$r-$k+1];
			    $i++;
			}
			$range = $t;
			//check overdue invoice
		    $is_overdue_invoice = false;
		    if(!$bill->submit_date) {
		        if( strtotime(date('Y-m-d')) > strtotime($bill->due_date) )
		        $is_overdue_invoice = true;
		    } else {
		        $day_submit = date('Y-m-d',strtotime($bill->submit_date));
		        if ( strtotime( $day_submit ) > strtotime( $bill->due_date ) )
		        $is_overdue_invoice = true;
		    }

			
		    //check is bi fund invoice 
		    $is_building_insurance_invoice = false;
		    $building_insurance_amount  = $cf_amount = $paid_cf= 0;


			foreach ($bill->instalmentLog as $log) {
	    		//get paid transaction
	    		//$paid_building_insurance += Transaction::where('invoice_id',$log->to_receipt_id)->where('category',16)->sum('total');
	    		$paid_cf += Transaction::where('invoice_id',$log->to_receipt_id)->where('category',1)->sum('total');
            }

			
			$paid_cf += $bill->discount + $bill->sub_from_balance;

		    // get amount of common fee charge and bi fund
		    foreach ($bill->transaction as $t) {
		    	if($t->category ==  16) {
		    		$is_building_insurance_invoice = true;
		    	}

		    	if($t->category ==  1) {
		    		$cf_amount = $t->total;
		    	}
		    }
			$cf_amount_start = $cf_amount;
			$cf_amount -= $paid_cf;

		    $cal_cf_fine_flag = $cal_cf_house_fine_flag = $cal_normal_bill_fine_flag = false;
		    $total_fine = $day_in_year = $fine_rate = $fine_amount = $fine_multiply = $overdue_ms = $t_paid_fine_total = 0;

		    $is_rejected_invoice = ($bill->payment_status == 3 || $bill->payment_status == 4);
		    
		    // if invoice is overdue invoice
		    if($is_overdue_invoice && !$is_rejected_invoice) {
		    	// if property type is condominium
		        if($bill->property->property_type == 3) {
		        	// if invoice is common fee invoice
		            if($bill->is_common_fee_bill) {
		                //$cal_normal_bill_fine_flag = false;
		                $overdue_ms = calOverdueMonth($bill->due_date,null,false);
		                //if overdue month is over three months
						$o_month = $bill->property->settings->condo_start_fine_month;
		                if($overdue_ms >= $o_month) {
		                    $cal_cf_fine_flag = true;

		                    if($overdue_ms < 7) {
		                        $fine_rate = $bill->property->settings->condo_first_fine_rate;
		                    } else {
		                        $fine_rate = $bill->property->settings->condo_second_fine_rate;
		                    }
                            $fine_rate = ($fine_rate)?$fine_rate:0;
                            $fine_amount = ($fine_rate/100)*$cf_amount;


		                    if($bill->property->settings->fine_multiplyer_type == 'd') {

                                $fine_multiply = calOverdueDays($bill->due_date, $bill->submit_date);
                                $timestamp = strtotime(date('Y-12-31', strtotime($bill->due_date)));
                                $day_in_year = date("z", $timestamp) + 1;
                                $fine_amount = round(($fine_amount/$day_in_year),2);
                                $total_fine = $fine_multiply * $fine_amount;

                            } else {
                                $fine_multiply = calOverdueMonth($bill->due_date, $bill->submit_date);
                                $fine_amount = round(($fine_amount/12),2);
                                $total_fine = $fine_multiply * $fine_amount;
                            }

                            //$fine_rate = $fine_amount;
                            
                            $total_fine = round($total_fine, 2);
		                    //$total_fine = $fine_amount;
		                }
		            } else {
		                $cal_normal_bill_fine_flag = true;
		            }

		        } 
		        elseif($bill->property->property_type == 1) {
                    // if invoice is common fee invoice for housing estate and housing estate fine was set.
                    if (isset($bill->property->settings)) {
                        if ($bill->is_common_fee_bill && $bill->property->settings->housing_estate_fine_type) {
                            $cal_cf_house_fine_flag = true;
                            if ($bill->property->settings->housing_estate_fine_type == 1) {
                                $fine_rate = ($bill->property->settings->housing_estate_fine_rate / 100) * $cf_amount;
                                $fine_rate = round($fine_rate, 2);
                                $total_fine = $fine_rate;
                                $fine_amount = 1;
                            } else {
                                $fine_amount = calOverdueDays($bill->due_date, $bill->submit_date);
                                $timestamp = strtotime(date('Y-12-31', strtotime($bill->due_date)));
                                //dd($fine_amount);
                                $day_in_year = date("z", $timestamp) + 1;
                                $fine_rate = (($bill->property->settings->housing_estate_fine_rate / 100) * $cf_amount) / $day_in_year;
                                $fine_rate = round($fine_rate, 2);
                                $total_fine = $fine_rate * $fine_amount;
                            }
                        } else {
                            $cal_normal_bill_fine_flag = true;
                        }
                    }else{
                        $cal_normal_bill_fine_flag = true;
                    }
		        }
		        else {
		            $cal_normal_bill_fine_flag = true;
		        }
		    }

		    // cal max date length if it can paid by instalment
		    // can cal instalment if invoice is common fee bill and hasn't fine included and isn't rejected invoice
		    $is_fine_invoice = ( ($cal_cf_fine_flag || $cal_cf_house_fine_flag || $cal_normal_bill_fine_flag) && $cf_amount > 0 );
		    //$can_instalment = ( $bill->is_common_fee_bill && !$is_fine_invoice && !$is_rejected_invoice);
			$can_instalment = ( $bill->is_common_fee_bill && !$is_rejected_invoice);
			
		    if($can_instalment) {
		        if($bill->commonFeesRef) {
		        	$maxlengthMonth = $bill->commonFeesRef->range_type;
		            if(!$bill->instalmentLog->count()) {
		                $m_reverse = $maxlengthMonth;
		            } else {
		                $sum_month = 0;
		                foreach ($bill->instalmentLog as $log) {
		                    $sum_month += $log->range_type;
		                }
		                $m_reverse = $maxlengthMonth - ($sum_month);
		            }
		            if($m_reverse > 0) {
		            	$range = array_slice($range, 0, $m_reverse, true);
		            }
		        } 
		    }
			
		    $bank = new Bank;
		    $bank_list = $bank->getBankList();
			$t_fine_total = 0;
            $t_fine = "";
            $t_cf = "";
			$v = 'feesbills.admin-invoice-view';
			if( $bill->added_fine_flag ) {
				$v = 'feesbills.admin-invoice-fine-view';
				$t_fine = Transaction::where('invoice_id',$bill->id)->where('is_system_fine',true)->first();
				$t_paid_fine_total = $t_fine->instalment_balance ;

				$t_cf = Transaction::where('invoice_id',$bill->id)->where('category',1)->first();

				view()->share(compact('t_fine','t_cf'));
			}

			if($print){
                return compact('bill', 'fine_rate', 'fine_amount', 'total_fine', 'cal_cf_fine_flag', 'cal_normal_bill_fine_flag', 'is_overdue_invoice', 'overdue_ms', 'bank_list', 'can_instalment', 'range', 'is_building_insurance_invoice', 'cf_amount', 'cf_amount_start', 'paid_cf', 'cal_cf_house_fine_flag', 'day_in_year', 'is_fine_invoice', 't_paid_fine_total', 'unit_cf_rate','t_fine','t_cf','fine_multiply');
            }else {
                return view($v)->with(compact('bill','fine_rate','fine_amount','total_fine', 'cal_cf_fine_flag', 'cal_normal_bill_fine_flag', 'is_overdue_invoice', 'overdue_ms', 'bank_list', 'can_instalment', 'range', 'is_building_insurance_invoice', 'cf_amount', 'cf_amount_start', 'paid_cf', 'cal_cf_house_fine_flag', 'day_in_year', 'is_fine_invoice', 't_paid_fine_total', 'unit_cf_rate','t_fine','t_cf','fine_multiply'));
            }
		}
	}

	public function printBills(Request $r){

        $ids = explode(",", $r::get('list-bill'));
        $print_type = 3; // print both
        if( $r::get('original-print') == "true" &&  $r::get('copy-print') == "false"){
            $print_type = 1; // print just original
        }elseif( $r::get('original-print') == "false" &&  $r::get('copy-print') == "true"){
            $print_type = 2; // print just copy
        }
        $bills = Invoice::with(array('instalmentLog' => function ($q) {
            return $q->orderBy('created_at', 'ASC');
		}, 'property', 'transaction', 'invoiceFile', 'commonFeesRef'))->whereIn('id',$ids)->orderBy('invoice_no_label','desc')->get();
		
        foreach ($bills as &$bill ) {
            // check ref code
            if(!$bill->smart_bill_ref_code) {
				$property_code = $this->getPropertyCode($bill->property);
				$invoice_no = $this->getInvoiceNo ($bill);
                $bill->smart_bill_ref_code = generateQrRefCode($property_code,$invoice_no);
                $bill->timestamps = false;
                $bill->save();
            }

			$bill->otherInvoice = null;
			if( Auth::user()->property->view_overdue_debt ) {
				$bill->otherInvoice = Invoice::where('property_unit_id',$bill->property_unit_id)->where('id', '!=', $bill->id)->where('payment_status',0)->get();
			}
		}

        $feature = PropertyFeature::where('property_id',Auth::user()->property_id)->first();
        if($feature->preprint_invoice) {
            return view('feesbills.print-view.preprint.admin-invoice-print')->with(compact('bills','print_type','feature'));
        } else {
            return view('feesbills.print-view.admin-invoice-print')->with(compact('bills','print_type'));
        }
    }

    public function printCopyBillsFromReceipt(){
        $ids            = explode(",", Request::get('list-bill'));
        $bills          = array();
        $target_bills   = Invoice::with(
            [
                'invoiceLog',
                'receiptInvoiceAggregate' => function ($q) {
                    $q  ->join('invoice', 'invoice_id', '=', 'invoice.id')
                        ->orderBy('invoice.invoice_no_label', 'ASC');
                }
            ]
        )
            ->whereIn('id',$ids)
            ->where('is_retroactive_record',false)
            ->where('is_revenue_record',false)->get();
        $print_type     = 2;
        $property       = Property::find(Auth::user()->property_id);
        $index = 0;

        foreach ( $target_bills as  $bill ) {
            if( $bill->invoiceLog ) {
                $property_unit = PropertyUnit::find($bill->property_unit_id);
                $bill               = json_decode($bill->invoiceLog->data);
                $bills[$index]['bill']    = $bill;
                $bills[$index]['has_log'] = true;
                $bills[$index]['property_unit'] = $property_unit;
            } else {
                if ( $bill->is_aggregate_receipt ) {

                    foreach ( $bill->receiptInvoiceAggregate as $invoice) {
                        $bills[$index]['has_log'] = false;
                        $bills[$index]['bill'] = Invoice::find($invoice->id);
                        $index++;
                    }
                } else {
                    $bills[$index]['has_log'] = false;
                    $bills[$index]['bill']    = $bill;
                }
            }
            $index++;
        }
        return view('feesbills.print-view.admin-invoice-log-print')->with(compact('bills','property','print_type'));
    }

    public function printReceipts(){
        $ids = explode(",", Request::get('list-bill'));
        $print_type = 3; // print both
        if(Request::get('original-print') == "true" && Request::get('copy-print') == "false"){
            $print_type = 1; // print just original
        }elseif(Request::get('original-print') == "false" && Request::get('copy-print') == "true"){
            $print_type = 2; // print just copy
        }

        $bills = Invoice::with(array('instalmentLog' => function ($q) {
            return $q->orderBy('created_at', 'ASC');
        }, 'property', 'transaction', 'invoiceFile'))->whereIn('payment_status',[2,5])->whereIn('id',$ids)->orderBy('invoice_no_label','desc')->get();

        $feature = PropertyFeature::where('property_id',Auth::user()->property_id)->first();
        if($feature->preprint_receipt) {
            return view('feesbills.print-view.preprint.admin-receipt-print')->with(compact('bills','print_type','feature'));
        } else {
            return view('feesbills.print-view.admin-receipt-print')->with(compact('bills','print_type'));
        }
    }

	public function getCfUnitRate ($property,$unit) {
        if( $unit->static_cf_rate > 0) {
            $cf_rate = $unit->static_cf_rate;
        } else {
            $rate_home = $property->common_area_fee_rate;
            $rate_land = $property->common_area_fee_land_rate;

            if($unit->extra_cf_charge > 0) {
                $rate_home += $unit->extra_cf_charge;
                $rate_land += $unit->extra_cf_charge;
            }

            if($unit->type == 1) {
                if($property->common_area_fee_type == 1) {
                    $cf_rate = $unit->property_size * $rate_home;
                } else {
                    $cf_rate = $rate_home;
                }
            } else {
                // Land
                if($property->common_area_fee_land_type == 1) {
                    $cf_rate = $unit->property_size * $rate_land;
                } else {
                    $cf_rate = $rate_land;
                }
            }
        }
		return $cf_rate;
	}

	public function status () {
		if(Request::isMethod('post')) {
		    //dd(Request::all());
            $bill = Invoice::with('transaction','instalmentLog')->find(Request::get('bid'));
            if( $bill->payment_status == 2) {
                // check invoice status before change it
                return redirect('admin/fees-bills/invoice');
            }
            // success status
            if( Request::get('status') == 2 && $bill ) {
                $temp_invoice = $bill->toArray();
				$property 	= Property::find(Auth::user()->property_id);
    			$bill->payment_status 	= Request::get('status');
    			if($bill->for_external_payer) {
    				$bill->payment_type = Request::get('payment_type');
    			}
    			if(!$bill->submit_date) {
    				$bill->submit_date = date('Y-m-d h:i:s');
    			}
				$bill->receipt_no 	=  ++$property->receipt_counter;
                $bill->remark       = Request::get('remark');
    			// Generate Running Number
                $month = Carbon::now()->month;
                $year = Carbon::now()->year;
                $date_period = $year.$month;

                $this->getMonthlyCounterDoc($date_period,Auth::user()->property_id);
                $receipt_no_label = $this->generateRunningLabel('RECEIPT',null,null,Auth::user()->property_id);

                $bill->receipt_no_label = $receipt_no_label;

                // Increase monthlyCounterDoc
                $this->increaseMonthlyCounterDocByPeriod($date_period,'RECEIPT',Auth::user()->property_id);
                // End Generate Running Number

				if( $bill->smartPaymentLog ) {
					$bill->payment_type = 2;
					$bill->payment_date = $bill->smartPaymentLog->transDate;
				} else {
					$bill->payment_date = Request::get('payment_date_confirm');
					$payment_type = Request::get('payment_type');
					if($payment_type != null){
						$bill->payment_type = $payment_type;
					} else {
						$bill->payment_type = 1;
					}
				}
				
				if($bill->payment_type == 2) {
					$bill->bank_transfer_date = $bill->payment_date;

					if( $bill->sub_from_balance == 0 ) {
						$bill->transfered_to_bank = true;
					} else {
						if( !$bill->is_common_fee_bill ) {
							$bill->cash_on_hand_transfered = false;
							$bill->mixed_payment = true;
						} else {
                            $bill->transfered_to_bank = true;
                        }
					}
				}

                $bill->approved_by = Auth::user()->id;
    			$bill->save();
				$property->save();
				$cf_transaction = null;
				$t_order = 0;
    			foreach ($bill->transaction as $tr) {
					if(!$tr->submit_date) {
	    				$tr->submit_date = $bill->submit_date;
	    			}
					$tr->payment_date 		= $bill->payment_date;
					$tr->bank_transfer_date = $bill->bank_transfer_date;
    				$tr->payment_status 	= true;
    				$tr->save();
                    $t_order++;

    				if($tr->category == 1) {
                        $cf_transaction = $tr;
                    }
    			}

    			// Check if sticker
    			$vehicle = Vehicle::where('invoice_id',$bill->id)->first();
    			if($vehicle) {
    				$vehicle->sticker_status = 3;
    				$vehicle->invoice_id = null;
    				$vehicle->save();
    				//$this->sendStickerNotification($vehicle->property_unit_id,$vehicle->lisence_plate);
    			}

    			// save common fee ref.
    			if($bill->commonFeesRef) {
    				$bill->commonFeesRef->payment_status = true;
    				$bill->commonFeesRef->save();
    			}

				// Save attachments
				if(!empty(Request::get('attachment'))) {
					$this->saveAttachmentBill ($bill);
				}

                // Savelog flag
                $save_log_flag = false;

    			//Check overdue fine
    			if(Request::get('fine_amount')) {	
					$bill = $this->addFine($bill);
                    $save_log_flag = true;
				}

				if(Request::get('cf_month_over') && $bill->is_common_fee_bill) {
					// month over
					$mo = Request::get('cf_month_over');
					// common fee rate
					$cfr = Request::get('unit_cf_rate');
					// balance over
					$bo = Request::get('balance_remaining');

					$bill = $this->expandCommonfee($bill,$mo,$cfr,$bo);
					// call new balance remaining
					$rb = $bo - ($cfr * $mo);

                    $save_log_flag = true;
				} else {
					$rb = Request::get('balance_remaining');
				}
    			// Check paid over and save to property unit balance
    			if($rb > 0) {
    				$balance_ctl = new PropertyUnitPrepaidController;
					$bill = $balance_ctl->saveBillBalance($rb,$bill);
                    $save_log_flag = true;
    			} else {
					$bill->final_grand_total 	= $bill->total - $bill->discount - $bill->sub_from_balance;
					$bill->grand_total 			= $bill->final_grand_total ;
                    // Save insufficient common fee payment
					if($rb < 0 && $bill->is_common_fee_bill) {
                        $balance_ctl = new PropertyUnitPrepaidController;
                        $bill = $balance_ctl->saveBillShortPaid($rb, $bill, $cf_transaction,Request::get('save_short_payment'));
                        $save_log_flag = true;
                    }
                    $bill->save();
    			}
                // save new transaction
    			if( Request::get('transaction') ) {
                    foreach (Request::get('transaction') as $t) {
                        $trans = new Transaction;
                        $trans->detail              = $t['detail'];
                        $trans->quantity            = str_replace(',', '', $t['quantity']);
                        $trans->price               = str_replace(',', '', $t['price']);
                        $trans->total               = $t['total'];
                        $trans->transaction_type    = $bill->type;
                        $trans->property_id         = $bill->property_id;
                        $trans->property_unit_id    = $bill->property_unit_id;
                        $trans->for_external_payer  = $bill->for_external_payer;
                        $trans->category            = $t['category'];
                        $trans->due_date            = $bill->due_date;
                        $trans->invoice_id          = $bill->id;
                        $trans->ordering            = $t_order++;
                        $trans->payment_date 		= $bill->payment_date;
                        $trans->bank_transfer_date  = $bill->bank_transfer_date;
                        $trans->payment_status 	    = true;
                        $trans->save();
                        $bill->total                += $trans->total;
                        $bill->grand_total          += $trans->total;
                        $bill->final_grand_total    += $trans->total;
                    }
                    $save_log_flag = true;
                    $bill->save();
                }

				// Save Bank transfer transaction
    			if($bill->payment_type == 2) {
					$bt = new BankTransaction;
					if( $bill->smartPaymentLog ) {
						$bank_id = $bill->smartPaymentLog->property_bank_id;
					} else {
						$bank_id = Request::get('payment_bank');
					}
					if( $bank_id ) {
						$bt->saveBankBillTransaction($bill,$bank_id);
						$bank = new Bank;
						$bank->updateBalance ($bank_id,$bill->final_grand_total);
					}
    			}

    			// TODO: notification success to mobile
                if(!$bill->for_external_payer){
                    $this->sendTransactionCompleteNotification($bill->property_id, $bill->property_unit_id,$bill->name,$bill->id, Auth::user()->id);
                }

                if( $save_log_flag ) {
                    //Save invoice log
                    $log = new ReceiptInvoiceLog;
                    $log->receipt_id = $bill->id;
                    $log->data = json_encode($temp_invoice);
                    $log->save();
                }


    			return redirect('admin/fees-bills/view/'.$bill->id);
            } else if(Request::get('status') == 3) {
				$this->rejectBill($bill);
                return redirect('admin/fees-bills/invoice');
            }
        }
	}

    public function rejectBill($bill) {
        if($bill) {

            $bill->payment_status = 0;
            $bill->submit_date = null;
			$bill->save();
			
			if($bill->invoiceFile->count()) {
				$bill->invoiceFile()->delete();
			}

			foreach ($bill->transaction as $t) {
				$t->is_rejected = false;
				$t->save();
			}

			if( $bill->smartPaymentLog ) {
				$bill->smartPaymentLog->delete();
			}

            /*$property 	= Property::find(Auth::user()->property_id);
            $bill_array = $bill->toArray();
            $bill->payment_status = 3;
            $bill->save();
            $invoice = new Invoice;
            $invoice->fill($bill_array);
            $invoice->payment_status    = 0;
            $invoice->payment_date      = $invoice->payment_type = $invoice->submit_date = null;
            $invoice->invoice_no        = ++$property->invoice_counter;
            $invoice->instalment_balance	= $bill->instalment_balance;

            // Generate Running Number
            $month = Carbon::now()->month;
            $year = Carbon::now()->year;
            $date_period = $year.$month;

            $this->getMonthlyCounterDoc($date_period,Auth::user()->property_id);
            $invoice_no_label = $this->generateRunningLabel('INVOICE',null,null,Auth::user()->property_id);

            $invoice->invoice_no_label = $invoice_no_label;

            // Increase monthlyCounterDoc
            $this->increaseMonthlyCounterDocByPeriod($date_period,'INVOICE',Auth::user()->property_id);
            // End Generate Running Number

            $invoice->remark = "";
            if(Request::get('reject-remark')) {
                $invoice->remark = Request::get('reject-remark')."\r\n *";
            }
            $invoice->remark .= trans('messages.feesBills.rejected_defaut_label')." ".$bill->invoice_no_label;
            $invoice->save();
            $property->save();
            foreach ( $bill->transaction as $t) {
				//new transaction
                $tran = new Transaction;
                $tran->fill($t->toArray());
                $tran->payment_date     =  null;
                $tran->submit_date     =  null;
                $tran->invoice_id       = $invoice->id;
                $tran->instalment_balance = $t->instalment_balance;
                $tran->save();
				//reject old transaction
                $t->is_rejected = true;
                $t->save();
            }
            //check common fee bill
            if($invoice->is_common_fee_bill) {
                //swap cfc ref id
				$crf = CommonFeesRef::where('invoice_id',$bill->id)->first();
				$crf->invoice_id = $invoice->id;
				$crf->save();
            }
            //check water&electric bill
            $bill_water = BillWater::where('invoice_id',$bill->id)->first();
            if($bill_water) {
                $bill_water->invoice_id = $invoice->id;
                $bill_water->save();
            }

            $bill_electric = BillElectric::where('invoice_id',$bill->id)->first();
            if($bill_electric) {
                $bill_electric->invoice_id = $invoice->id;
                $bill_electric->save();
            }

			//check vehicle sticker
			$vehicle = Vehicle::where('invoice_id',$bill->id)->first();
			if($vehicle) {
				$vehicle->invoice_id = $invoice->id;
				$vehicle->save();
			}

			// remove payment notification log
			if( $bill->smartPaymentLog ) {
				$bill->smartPaymentLog->delete();
			}

            // send notification
            $this->sendInvoiceNotification ($invoice->property_unit_id, $invoice->name, $invoice->id); */
        }
    }

	public function create () {
		if(Request::isMethod('post')) {
			$tax = Request::get('tax')?Request::get('tax'):0;
			$for_external_payer = false;
			if(Request::get('for') == 0) {
				$unit = Request::get('unit_id');
			} else {
				$for_external_payer = true;
			}
			$property 		= Property::find(Auth::user()->property_id);
			$property_code  = $this->getPropertyCode($property);
			$discount_payment_status = false;

			$invoice = new Invoice;
			$invoice->fill(Request::all());
			$invoice->tax 				= $tax;
			$invoice->type 				= 1;
			$invoice->property_id 		= Auth::user()->property_id;
			$invoice->invoice_no 		= ++$property->invoice_counter;
			$invoice->transfer_only 	= (Request::get('transfer_only'))?true:false;
			$invoice->final_grand_total = $invoice->grand_total;
			$invoice->balance_before 	= $property->balance;

			if($for_external_payer) {
					$invoice->for_external_payer 	= true;
					$invoice->payer_name 			= Request::get('payer_name');
					$invoice->save();
			} else {
					$invoice->property_unit_id 	= $unit;
					$invoice->save();
			}
			// Save transaction
            $c = 0;
			foreach (Request::get('transaction') as $t) {
				$trans = new Transaction;
				$trans->detail 				= $t['detail'];
				$trans->quantity 			= str_replace(',', '', $t['quantity']);
				$trans->price 				= str_replace(',', '', $t['price']);
				$trans->total 				= $t['total'];
				$trans->transaction_type 	= $invoice->type;
				$trans->property_id 		= $invoice->property_id;
				$trans->property_unit_id 	= $invoice->property_unit_id;
				$trans->for_external_payer 	= $for_external_payer;
				$trans->category 			= $t['category'];
				$trans->due_date			= Request::get('due_date');
				$trans->invoice_id			= $invoice->id;
				$trans->ordering            = $c++;
				$trans->save();
			}
			// calculate balance
			if(!$for_external_payer) {
				//reget invoice
				$invoice 		= Invoice::with('transaction')->find($invoice->id);
				$property_unit 	= PropertyUnit::find($invoice->property_unit_id);
				// Substract from property unit balance
				$cal_balance_flag 	= ( $property_unit->balance > 0 );
				$remaining          = $invoice->final_grand_total;
			    if($cal_balance_flag) {

					$invoice->balance_before = $property_unit->balance;
					$sum_sub = 0;
					$current_balance = $property_unit->balance;
					//$discount = $invoice->discount;

					foreach ($invoice->transaction as $tr) {
						//chaeck calulate balance
						if( $tr->transaction_type == 1 && $cal_balance_flag) {
							if($property_unit->balance > 0 ) {
								$balance 				= $this->calTransactionBalance ($property_unit->balance,$tr->total);
								$sum_sub 			   += $balance['sub_to_balance'];
								$tr->sub_from_balance 	= $balance['sub_to_balance'];
								$property_unit->balance = $balance['calculated_balance'];
								$tr->save();
							} 
						}
					}
					$remaining = $invoice->grand_total-$sum_sub;
					if($remaining > 0)
			        	$invoice->final_grand_total = $remaining;
			        $invoice->sub_from_balance 		= $sum_sub;
			        $property_unit->save();
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
						// change invoice status and set it to become a receipt
						$invoice->receipt_no 	=  ++$property->receipt_counter;

                        // Generate Running Number RECEIPT
                        $month = Carbon::now()->month;
                        $year = Carbon::now()->year;
                        $date_period = $year.$month;

                        $this->getMonthlyCounterDoc($date_period,Auth::user()->property_id);
                        $receipt_no_label = $this->generateRunningLabel('RECEIPT',null,null,Auth::user()->property_id);

                        $invoice->receipt_no_label  = $receipt_no_label;
                        $invoice->approved_by       = Auth::user()->id;
                        $invoice->save();

                        // Increase monthlyCounterDoc
                        $this->increaseMonthlyCounterDocByPeriod($date_period,'RECEIPT',Auth::user()->property_id);
                        // End Generate Running Number

						$invoice->payment_status = 2;
						$invoice->payment_date = $pd;
						$invoice->payment_type = 3;
						$discount_payment_status = true;
					}
			    }
				
				if( $remaining > 0) {
					$this->sendInvoiceNotification ($unit, $invoice->name, $invoice->id);
				} else {
					$invoice->payment_date = $invoice->submit_date = date('Y-m-d H:i:s');
					$invoice->save();
				}
			}

			// Generate Running Number
            $month = $invoice->created_at->month;
            $year = $invoice->created_at->year;
            $date_period = $year.$month;

            $this->getMonthlyCounterDoc($date_period,Auth::user()->property_id);
            $invoice_no_label = $this->generateRunningLabel('INVOICE',$invoice,null,Auth::user()->property_id);

			$invoice->invoice_no_label = $invoice_no_label;
			$invoice->smart_bill_ref_code = generateQrRefCode($property_code,$invoice->invoice_no,true);
            $invoice->created_by = Auth::user()->id;
            $invoice->save();

            // Increase monthlyCounterDoc
            $this->increaseMonthlyCounterDocByPeriod($date_period,'INVOICE',Auth::user()->property_id);
            // End Generate Running Number

			// Save Counter
			$property->save();
			return redirect('admin/fees-bills/invoice');
		}
		$unit_list = ['0' => trans('messages.unit_no')];
		$remark = Auth::user()->property->settings->invoice_remark;
		$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
		return view('feesbills.admin-create-invoice')->with(compact('unit_list','remark'));
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
	
	public function generateCfInvoice (Request $r) {
		if($r::isMethod('post')) {
            $msg = "";
			ini_set('max_execution_time', 180);
			if($r::get('for') == 0) {
				$units = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->where('type',"!=",3)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('id')->toArray();
			} else {
				$units = $r::get('unit_id');
			}

			$discount = str_replace(',', '', $r::get('discount'));
			if(!$discount) $discount = 0;

			$property 	= Property::find(Auth::user()->property_id);
			$property_code = $this->getPropertyCode($property);
			// check adjust cf rate setting
            $adjust_rate = ($property->settings && $property->settings->adjust_common_fee_cost)?true:false;

            // flag include fixed cost to cf invoice
            $include_fixed_cost = ($property->settings && $property->settings->include_fixed_cost_to_cf_bill)?true:false;
            if( $include_fixed_cost ) {
                $water_meter_cost = $property->settings->water_meter_maintenance_fee;
                $electric_meter_cost = $property->settings->electric_meter_maintenance_fee;
            }

			// check bi fund
			$bi = $sum_unit_area = $r_fund_rate = 0;
			if($r::get('building_insurance')) {
				$rf = str_replace(',', '', $r::get('building_insurance_rate'));
				if($rf > 0) {
					$bi = $rf;
				}
			}

			if($bi) {
				$unit_data_result = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->where('type',"!=",3)->selectRaw('SUM(property_size) AS sum_unit_area, COUNT(id) AS count')->first();
				$sum_unit_area = $unit_data_result->sum_unit_area;
				if($sum_unit_area == 0)
				    return redirect()->back()->with('fail-create-bi', trans('messages.feesBills.cannot_calculate_bi_msg'))->withInput();
                $r_fund_rate = $bi;
			}

			$is_auto_method = ($r::get('cal_method') == 'auto');
			//check date Range
			$mul 			= intval(Request::get('range'));
			$from_date 		= Request::get('from_year')."-".Request::get('from_month').'-01';
			$time_start 	= strtotime($from_date);
			$to_date 		= date('Y-m-t',strtotime(Request::get('to_date')));

			if( $is_auto_method ) {
				// set default quantity and price for property unit
				if( $property->common_area_fee_type == 0 ) {
					$price 		= $property->common_area_fee_rate;
				}
				// set default quantity and price for land
				if( $property->common_area_fee_land_type == 0 ) {
					$price_land 	= $property->common_area_fee_land_rate;
				}
			}

			$data_transaction = $r::get('transaction');
			$common_fee_transaction = $data_transaction[0];
			unset($data_transaction[0]);

			foreach ($units as $unit) {
			    $transaction_ordering = 1;
			    $all_total = 0;
				$unit_ 		= PropertyUnit::find($unit);

				$no_cf_duplicate = $this->checkUnitCfDuplicate($unit_,$time_start,$to_date);

				if($no_cf_duplicate['no_duplicate']) {

				    if(!empty($no_cf_duplicate['duplicate_month']) && $no_cf_duplicate['duplicate_month'] != 0) {

                        $mount_range        = $no_cf_duplicate['duplicate_month'];
                        $month_time_start   = $no_cf_duplicate['month_start_timestamp'];
                        $crf_from_date		= date('Y-m-d',$month_time_start);
                        $crf_range_type 	= $mount_range;
                    } else {
                        $mount_range        = $mul;
                        $month_time_start   = $time_start;
                        $crf_from_date		= $from_date;
                        $crf_range_type 	= intval(Request::get('range'));
                    }

                    $t_cf_name = $invoice_name = $this->setCfDetail ($mount_range, $month_time_start, $to_date, $unit_->contact_lang);
                    if($bi) {
                        $invoice_name .= " ".trans('messages.feesBills.and_building_insurance', [], "", $unit_->contact_lang);
                    }

                    $invoice 	= new Invoice;
                    $invoice->fill($r::all());

                    //set invoice data
                    $invoice->name				= $invoice_name;
                    $invoice->tax 				= 0;
                    $invoice->type 				= 1;
                    $invoice->property_id 		= $property->id;
                    $invoice->property_unit_id 	= $unit;
                    $invoice->invoice_no 		= ++$property->invoice_counter;
                    $invoice->transfer_only 	= (Request::get('transfer_only'))?true:false;
                    $invoice->is_common_fee_bill = true;
                    $invoice->created_by        = Auth::user()->id;
                    // Create transaction
                    $trans 		                = new Transaction;
                    $trans->detail 				= $t_cf_name;
                    if($unit_->static_cf_rate > 0 && $is_auto_method) {
                        // if static cf rate is set;
                        $quantity = $mount_range;
                        $total = $unit_->static_cf_rate * $mount_range;
                        //set invoice data
                        $invoice->grand_total 		= $invoice->total = $total;
                        //set transaction data
                        $trans->quantity 			= $quantity;
                        $trans->price 				= $unit_->static_cf_rate;
                        $trans->total 				= $total;

                    } else {

                        if($is_auto_method) {
                            ////////////////// if auto method
                            if($unit_->type == 1) {

                                if($property->common_area_fee_type == 1) {
                                    $price = $property->common_area_fee_rate * $unit_->property_size;
                                } elseif($property->common_area_fee_type == 2) {
                                    $price = $property->common_area_fee_rate * $unit_->ownership_ratio;
                                }
                                $quantity = $mount_range;
                                $total = $price * $mount_range;
                                //set invoice data
                                $invoice->grand_total 		= $invoice->total = $total;
                                //set transaction data
                                $trans->quantity 			= $quantity;
                                $trans->price 				= $price;
                                $trans->total 				= $total;

                            } else {
                                // Land
                                if($property->common_area_fee_land_type == 1) {
                                    $price_land = $property->common_area_fee_land_rate * $unit_->property_size;
                                } elseif ($property->common_area_fee_type == 2) {
                                    $price_land = $property->common_area_fee_land_rate * $unit_->ownership_ratio;

                                }
                                $total_land = $price_land * $mount_range;
                                $quantity_land = $mount_range;
                                //set invoice data
                                $invoice->grand_total 		= $invoice->total = $total_land;
                                //set transaction data
                                $trans->quantity 			= $quantity_land;
                                $trans->price 				= $price_land;
                                $trans->total 				= $total_land;
                            }

                            if($unit_->extra_cf_charge > 0) {
                                //if set extra charge
                                //add to invoice grand total
                                $extra_charge 				=  $trans->quantity * $unit_->extra_cf_charge;
                                $invoice->total 			+= $extra_charge;
                                $invoice->grand_total 		=  $invoice->total;
                                //set transaction data
                                $trans->total 				+= $extra_charge;
                                $trans->price 				=  $trans->price + $unit_->extra_cf_charge;
                            }
                            // adjust common fee rate
                            if($adjust_rate && $adjust_rate && $unit_->cf_adjust_key) {
                                $trans->quantity 			 = 1;
                                $trans->total               += $unit_->cf_adjust_key;
                                $trans->price                = $trans->total;
                                $invoice->total 		    += $unit_->cf_adjust_key;
                                $invoice->grand_total 		+= $unit_->cf_adjust_key;
                                $invoice->final_grand_total += $unit_->cf_adjust_key;
                            }
                            // set auto detail to transaction
                            $trans->detail 				= $t_cf_name;

                        } else {
                            ////////////////// is manual method
                            $trans->quantity 			= str_replace(',', '', $common_fee_transaction['quantity']);
                            $trans->price 				= str_replace(',', '', $common_fee_transaction['price']);
                            $trans->total 				= $common_fee_transaction['total'];
                            $trans->detail 				= $common_fee_transaction['detail'];
                            $invoice->grand_total 		= $invoice->total = $trans->total;
                        }

                    }

                    // check bi fund
                    if($bi && $unit_->property_size > 0) {
                        $rf_unit_amount = round($r_fund_rate*$unit_->property_size,3);
                        // Add bi fund
                        $invoice->grand_total 		+= $rf_unit_amount;
                        $invoice->total 			+= $rf_unit_amount;
                    } else {
                        $rf_unit_amount = 0;
                    }

                    // check discount
                    if($discount > 0) {
                        if( $discount > $invoice->total ) {
                            $discount = $invoice->total;
                            $invoice->discount 			= $discount;
                        }
                        // save transaction discount substraction
                        $trans->sub_from_discount	= $discount;

                        //set discount for invoice
                        $invoice->grand_total -= $discount;
                    }

                    $invoice->final_grand_total = $invoice->grand_total;

                    // check unit balance
                    if($unit_->cf_balance > 0) {
                        $real_cf_total = $trans->total -  $trans->sub_from_discount;
                        $sub_balance = ( $unit_->cf_balance > $real_cf_total )?$real_cf_total:$unit_->cf_balance;
                        $invoice->sub_from_balance		 = $sub_balance;
                        $invoice->final_grand_total		-= $sub_balance;
                        $unit_->cf_balance 				-= $sub_balance;
                        $unit_->save();
                        // set transaction sub from balance
                        $trans->sub_from_balance 		= $sub_balance;
                    } else if($unit_->cf_balance < 0) {
                        $remaining_amount = abs($unit_->cf_balance);
                        $trans_rm = new Transaction();

                        $trans_rm->detail 			= trans('messages.feesBills.remaining_cf');
                        $trans_rm->quantity 		= 1;
                        $trans_rm->price 			= $remaining_amount;
                        $trans_rm->total 			= $remaining_amount;
                        $trans_rm->category 		= 20;
                        $trans_rm->transaction_type = 1;
                        $trans_rm->property_id 		= $property->id;
                        $trans_rm->property_unit_id = $unit;
                        $trans_rm->due_date			= $invoice->due_date;
                        $trans_rm->ordering         = $transaction_ordering++;

                        $invoice->final_grand_total += $remaining_amount;
                        $invoice->total = $invoice->grand_total = $invoice->final_grand_total;
                    }
                    // get total for calculate discount
                    $all_total += $trans->total - $trans->sub_from_balance - $trans->sub_from_discount;
                    // check other transaction
                    if(!empty($data_transaction)) {
                        $added_total 	= 0;
                        $arr_trans 		= array();
                        foreach($data_transaction as $tran) {
                            $added_total += $tran['total'];
                            $arr_trans[] = new Transaction ([
                                'detail' 			=> $tran['detail'],
                                'quantity' 			=> str_replace(',', '',$tran['quantity']),
                                'price' 			=> str_replace(',', '',$tran['price']),
                                'total'				=> $tran['total'],
                                'category'			=> $tran['category'],
                                'transaction_type'	=> 1,
                                'property_id'		=> $property->id,
                                'property_unit_id'	=> $unit,
                                'ordering'          => $transaction_ordering++,
                                'due_date'			=> $r::get('due_date')
                            ]);
                        }
                        $invoice->total 		+= $added_total;
                        $invoice->grand_total 	+= $added_total;
                        $invoice->final_grand_total += $added_total;
                        $invoice->save();
                        $invoice->transaction()->saveMany($arr_trans);
                    } else {
                        $invoice->save();
                    }

                    // save insufficient transaction
                    if($unit_->cf_balance < 0) {
                        $unit_->cf_balance 		= 0;
                        $unit_->save();
                        $trans_rm->invoice_id 	= $invoice->id;
                        $trans_rm->save();
                    }

                    // Save bi fund transaction
                    if($bi  && $unit_->property_size > 0) {
                        $trans_r = new Transaction();
                        $trans_r->invoice_id 		= $invoice->id;
                        $trans_r->detail 			= trans('messages.feesBills.building_insurance', [], "", $unit_->contact_lang);
                        $trans_r->quantity 			= $unit_->property_size;
                        $trans_r->price 			= $r_fund_rate;
                        $trans_r->total 			= $rf_unit_amount;
                        $trans_r->category 			= 16;
                        $trans_r->transaction_type 	= 1;
                        $trans_r->property_id 		= Auth::user()->property_id;
                        $trans_r->property_unit_id 	= $unit;
                        $trans_r->due_date			= $invoice->due_date;
                        $trans_r->ordering          = $transaction_ordering++;
                        $trans_r->save();

                    }

                    /// Add public utility fees
                    if($unit_->public_utility_fee > 0 ) {
                        $trans_puf = new Transaction();
                        $trans_puf->invoice_id 		= $invoice->id;
                        $trans_puf->detail 			= trans('messages.Prop_unit.public_utility', [], "", $unit_->contact_lang);
                        $trans_puf->quantity 			= $trans->quantity;
                        $trans_puf->price 			    = $unit_->public_utility_fee;
                        $trans_puf->total 			    = $trans->quantity * $unit_->public_utility_fee;
                        $trans_puf->category 			= 24;
                        $trans_puf->transaction_type 	= 1;
                        $trans_puf->property_id 		= Auth::user()->property_id;
                        $trans_puf->property_unit_id 	= $unit;
                        $trans_puf->due_date			= $invoice->due_date;
                        $trans_puf->ordering            = $transaction_ordering++;
                        $trans_puf->save();
                        $invoice->total 		+= $trans_puf->total;
                        $invoice->grand_total 	+= $trans_puf->total;
                        $invoice->final_grand_total += $trans_puf->total;
                        $all_total += $trans_puf->total;
                    }

                    /// Add public utility fees
                    if($unit_->garbage_collection_fee > 0 ) {
                        $trans_gc = new Transaction();
                        $trans_gc->invoice_id 		= $invoice->id;
                        $trans_gc->detail 			= trans('messages.Prop_unit.garbage_collection', [], "", $unit_->contact_lang);
                        $trans_gc->quantity 		= $trans->quantity;
                        $trans_gc->price 			= $unit_->garbage_collection_fee;
                        $trans_gc->total 			= $trans->quantity * $unit_->garbage_collection_fee;
                        $trans_gc->category 		= 33;
                        $trans_gc->transaction_type = 1;
                        $trans_gc->property_id 		= $unit_->property_id;
                        $trans_gc->property_unit_id = $unit;
                        $trans_gc->due_date			= $invoice->due_date;
                        $trans_gc->ordering         = $transaction_ordering++;
                        $trans_gc->save();
                        $invoice->total 		+= $trans_gc->total;
                        $invoice->grand_total 	+= $trans_gc->total;
                        $invoice->final_grand_total += $trans_gc->total;
                        //$all_total += $trans_gc->total;
                    }

                    // check include fixed cost to cf invoice
                    if( $include_fixed_cost ) {

                        if( $unit_->is_billing_water && ($water_meter_cost != 0 || $unit_->water_meter_rate != 0 ) ) {
                            $meter_rate = ($unit_->water_meter_rate != 0)?$unit_->water_meter_rate:$water_meter_cost;
                            $trans_wm = new Transaction();
                            $trans_wm->invoice_id 		= $invoice->id;
                            $trans_wm->detail 			= trans('messages.Meter.water_maintain_fee', [], "", $unit_->contact_lang);
                            $trans_wm->quantity 	    = $trans->quantity;
                            $trans_wm->price 			= $meter_rate;
                            $trans_wm->total 			= $meter_rate * $trans->quantity;
                            $trans_wm->category 		= 31;
                            $trans_wm->transaction_type = 1;
                            $trans_wm->property_id 		= $unit_->property_id;
                            $trans_wm->property_unit_id = $unit;
                            $trans_wm->due_date			= $invoice->due_date;
                            $trans_wm->ordering         = $transaction_ordering++;
                            $trans_wm->save();
                            $invoice->total 		+= $trans_wm->total;
                            $invoice->grand_total 	+= $trans_wm->total;
                            $invoice->final_grand_total += $trans_wm->total;
                            $all_total += $trans_wm->total;
                        }

                        if( $unit_->is_billing_electric && ($electric_meter_cost !=0 || $unit_->electric_meter_rate != 0) ) {
                            $meter_rate = ($unit_->electric_meter_rate != 0)?$unit_->electric_meter_rate:$electric_meter_cost;
                            $trans_em = new Transaction();
                            $trans_em->invoice_id 		= $invoice->id;
                            $trans_em->detail 			= trans('messages.Meter.electric_maintain_fee', [], "", $unit_->contact_lang);
                            $trans_em->quantity 		= $trans->quantity;
                            $trans_em->price 			= $meter_rate;
                            $trans_em->total 			= $meter_rate * $trans->quantity;
                            $trans_em->category 		= 39;
                            $trans_em->transaction_type = 1;
                            $trans_em->property_id 		= $unit_->property_id;
                            $trans_em->property_unit_id = $unit;
                            $trans_em->due_date			= $invoice->due_date;
                            $trans_em->ordering         = $transaction_ordering++;
                            $trans_em->save();
                            $invoice->total 		+= $trans_em->total;
                            $invoice->grand_total 	+= $trans_em->total;
                            $invoice->final_grand_total += $trans_em->total;
                            $all_total += $trans_em->total;
                        }

                    }
                    // add discount
                    if($unit_->utility_discount > 0 ) {
                        $dc = ($all_total * $unit_->utility_discount/100);
                        $trans_ud = new Transaction();
                        $trans_ud->invoice_id 		= $invoice->id;
                        $trans_ud->detail 			= trans('messages.Prop_unit.unit_utility_discount', [], "", $unit_->contact_lang). " (". $unit_->utility_discount."%)";
                        $trans_ud->quantity 		= 1;
                        $trans_ud->price 			= -$dc;
                        $trans_ud->total 			= -$dc;
                        $trans_ud->category 		= 30;
                        $trans_ud->transaction_type = 1;
                        $trans_ud->property_id 		= $unit_->property_id;
                        $trans_ud->property_unit_id = $unit;
                        $trans_ud->due_date			= $invoice->due_date;
                        $trans_ud->ordering         = $transaction_ordering++;
                        $trans_ud->save();
                        $invoice->total 		-= $dc;
                        $invoice->grand_total 	-= $dc;
                        $invoice->final_grand_total -= $dc;
                    }

                    //set transaction data
                    $trans->invoice_id 			= $invoice->id;
                    //$trans->detail 				= $t_cf_name;
                    $trans->transaction_type 	= 1;
                    $trans->property_id 		= $property->id;
                    $trans->property_unit_id 	= $unit;
                    $trans->category 			= 1;
                    $trans->due_date			= $r::get('due_date');
                    $trans->ordering            = 0;
                    $trans->save();
                    //save common fee reference table
                    $crf = new CommonFeesRef();
                    $crf->invoice_id				= $invoice->id;
                    $crf->property_id				= $property->id;
                    $crf->property_unit_id 			= $unit;
                    $crf->property_unit_unique_id 	= $unit_->property_unit_unique_id;
                    $crf->from_date					= $crf_from_date;
                    $crf->to_date 					= $to_date;
                    $crf->range_type 				= $crf_range_type;
                    $crf->save();

                    // Save discount transaction
                    if($invoice->discount > 0) {
                        $trans_d = new Transaction();
                        $trans_d->invoice_id 		= $invoice->id;
                        $trans_d->detail 			= 'discount';
                        $trans_d->quantity 			= 1;
                        $trans_d->price 			= $discount;
                        $trans_d->total 			= $discount;
                        $trans_d->transaction_type 	= 3;
                        $trans_d->property_id 		= Auth::user()->property_id;
                        $trans_d->property_unit_id 	= $unit;
                        $trans_d->due_date			= $invoice->due_date;
                        $trans_d->category			= 0;
                        $trans_d->save();
                    }

                    // Generate Running Number
                    $month = $invoice->created_at->month;
                    $year = $invoice->created_at->year;
                    $date_period = $year.$month;

                    $invoice->save();

                    $this->getMonthlyCounterDoc($date_period,Auth::user()->property_id);
                    $invoice_no_label = $this->generateRunningLabel('INVOICE',$invoice,$invoice->invoice_no,Auth::user()->property_id);

					$invoice->invoice_no_label = $invoice_no_label;
					$invoice->smart_bill_ref_code = generateQrRefCode($property_code,$invoice->invoice_no,true);
                    $invoice->save();

                    // Increase monthlyCounterDoc
                    $this->increaseMonthlyCounterDocByPeriod($date_period,'INVOICE',Auth::user()->property_id);
                    $this->sendInvoiceNotification ($unit, $invoice->name, $invoice->id);
                } else {
                    $msg .= trans('messages.feesBills.cf_invoice_dup',['n' => $unit_->unit_number])."<br/>";
                }

			}
			$property->save();

			if($msg) {
                Request::session()->flash('class', 'danger');
                Request::session()->flash('message', $msg);
            }

			return redirect('admin/fees-bills/invoice');
		}

		$unit_list = array('-'=> trans('messages.unit_no'));
		$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
		return view('feesbills.admin-gencf-invoice')->with(compact('unit_list'));
	}

	public function cancel () {
		$id = Request::get('bid');
		$bill = Invoice::with('transaction')->find($id);
		if($bill && $bill->payment_status != 4) {
			$this->cancelBill($bill);
            $bill->fresh();
            $bill->cancelled_by = Auth::user()->id;
            $bill->cancel_reason = Request::get('reason');
            $bill->cancelled_at = date('Y-m-d H:i:s');
            $bill->timestamps = false;
            $bill->save();
		}
		return redirect('admin/fees-bills/invoice');
	}

	public function cancelSelected () {
		$bills = Request::get('bills');
		if(!empty($bills)) {
			foreach ($bills as $id) {
				$bill = Invoice::with('transaction')->find($id);
				if($bill && $bill->payment_status != 4 ) {
					$this->cancelBill($bill);
                    $bill->fresh();
                    $bill->cancelled_by = Auth::user()->id;
                    $bill->cancel_reason = Request::get('reason');
                    $bill->cancelled_at = date('Y-m-d H:i:s');
                    $bill->timestamps = false;
                    $bill->save();
				}
			}
		}
		echo "done";
	}

	public function cancelBill ($bill) {
		if(Auth::user()->role == 1 || Auth::user()->role == 3 && ($bill->payment_status == 0 || $bill->payment_status == 2) ) {
			$bill->payment_status = 4;
            $bill->save();

            $bill_electric = BillElectric::where('invoice_id',$bill->id)->get();
            foreach ($bill_electric as $item){
                if($item->is_service_charge){
                    // delete service charge
                    $item->delete();
                }else {
                    // clear invoice_id
                    $item->invoice_id = null;
                    $item->status = 0;
                    $item->save();
                }
            }

            $bill_water = BillWater::where('invoice_id',$bill->id)->get();
            foreach ($bill_water as $item){
                if($item->is_service_charge){
                    // delete service charge
                    $item->delete();
                }else {
                    // clear invoice_id
                    $item->invoice_id = null;
                    $item->status = 0;
                    $item->save();
                }
            }

			// Add balance back to property unit
			if($bill->sub_from_balance > 0) {
				$property_unit = PropertyUnit::find($bill->property_unit_id);
				if($bill->is_common_fee_bill)
				$property_unit->cf_balance += $bill->sub_from_balance;
				else
				$property_unit->balance += $bill->sub_from_balance;
				$property_unit->save();
			}

			foreach ($bill->transaction as $t) {
				$t->is_rejected = true;
				$t->save();
			}
			// reset vehicle bill
			$vehicle = Vehicle::where('invoice_id',$bill->id)->first();
			if(isset($vehicle)) {
				$vehicle->sticker_status = 0;
				$vehicle->invoice_id = null;
				$vehicle->save();
			}
		}
	}

	public function sendInvoiceNotification ($unit_id, $title, $subject_id) {
		$title = json_encode( ['type' => 'invoice_created','title' => $title] );
		$users = User::where('property_unit_id',$unit_id)->whereNull('verification_code')->get();
		$user_property_feature = UserPropertyFeature::where('property_id',Auth::user()->property_id)->first();

		if($user_property_feature) {
            if ($user_property_feature->menu_finance_group == true) {
                foreach ($users as $user) {
                    $notification = Notification::create([
                        'title' => $title,
                        'notification_type' => '3',
                        'from_user_id' => Auth::user()->id,
                        'to_user_id' => $user->id,
                        'subject_key' => $subject_id
                    ]);
                    $controller_push_noti = new PushNotificationController();
                    $controller_push_noti->pushNotification($notification->id);
                }
            }
        }
	}

	

	public function revision () {
		if(Request::isMethod('post')) {
			$revisions = InvoiceRevision::with('by')->where('invoice_id',Request::get('invoice_id'))->orderBy('revision_no','asc')->get();
			if($revisions->count()) {
				return view('feesbills.revision-list')->with(compact('revisions'));
			}
		}
	}

	public function viewRevision ($id) {
		$revision = $bill = InvoiceRevision::find($id);
		$bill = json_decode($bill['details']);
		if($bill->property_unit_id) {
			$property = PropertyUnit::with('property')->find($bill->property_unit_id);
		}
		return view('feesbills.view-revision')->with(compact('revision','bill','property'));
	}

	public function invoiceOverdueList (Request $form) {
		$bills = Invoice::where('property_id','=',Auth::user()->property_id)->where('is_retroactive_record',false)->where(function ($q) {
			$q->orWhere(function ($q__) {
				$q__->where('payment_status',0)->where('due_date','<',date('Y-m-d'));
			})->orWhere(function ($q__) {
				$q__->where('payment_status',1)->whereRaw('submit_date::date > due_date::date');
			});
		});
		if($form::isMethod('post')) {

			if(!empty($form::get('invoice-no')) && intval($form::get('invoice-no')) != 0) {
				$bills->where('invoice_no',intval($form::get('invoice-no')));
			}

			if(!empty($form::get('invoice-unit_id')) && ($form::get('invoice-unit_id') != "-")) {
				$bills->where('property_unit_id',$form::get('invoice-unit_id'));
			} elseif($form::get('invoice-unit_id') != "-") {
				$bills->whereNull('property_unit_id');
			}

			if(!empty($form::get('start-due-date'))) {
				$bills->where('due_date','>=',$form::get('start-due-date'));
			}

			if(!empty($form::get('end-due-date'))) {
				$bills->where('due_date','<=',$form::get('end-due-date'));
			}

			if( $form::get('invoice-status') != "") {
				$bills->where('payment_status',$form::get('invoice-status'));
			}

			if( $form::get('payment-method') == 1) {
				$bills->where('transfer_only', true);
			}

			if( $form::get('payer') == 1) {
				$bills->where('for_external_payer', true);
			}
		}
        $bills = $bills->where('type',1)->orderBy('invoice_no','desc')->paginate(50);

		if(!$form::ajax()) {
			$property = Property::find(Auth::user()->property_id);
			$unit_list = array('-'=> trans('messages.unit_no'));
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
			return view('feesbills.admin-invoice-overdue-list')->with(compact('bills','unit_list','property'));
		} else {
			return view('feesbills.admin-invoice-overdue-list-element')->with(compact('bills'));
		}
	}

	public function remindOverdue ($id) {
		$bill_check_type = Invoice::find($id);
        if(isset($bill_check_type->property_unit_id)) {
            $bill = Invoice::with('property', 'property_unit', 'transaction', 'invoiceFile')->find($id);
        }else{
            $bill = Invoice::with('property', 'transaction', 'invoiceFile')->find($id);
        }
			return view('feesbills.admin-invoice-remind')->with(compact('bill'));
	}

	public function getPropertyUnitDebt ($punit_id) {
		$total = 0;
		$invoice = Invoice::whereHas('property_unit', function ($query) use ($punit_id) {
            $query->where('property_unit_unique_id', $punit_id);
        })->where('is_retroactive_record',false)->where('type',1)->whereIn('payment_status',[0,1])->get();
		foreach ($invoice as $key => $iv) {
			$total += $iv->final_grand_total;
			$sum_instalment = InvoiceInstalmentLog::where('invoice_id',$iv->id)->sum('amount');
			$total -= $sum_instalment;
		}
		return $total;
	}

    public function instalment () {
		if(Request::isMethod('post')) {
			
			$property = Property::find(Auth::user()->property_id);
			$cur_invoice = Invoice::with('instalmentLog','transaction')->find(Request::get('bid'));
			$temp_invoice = $cur_invoice->toArray();
			$cur_invoice->load('invoiceFile','property_unit');
			
			if($cur_invoice) {
				//checkck date Range
				$range 			= Request::get('range');
				$from_date 		= Request::get('from_year')."-".Request::get('from_month').'-01';
				$_from_y		= date('Y',strtotime($from_date));
				$to_date 		= date('Y-m-t',strtotime(Request::get('to_date')));
				$amount 		= str_replace(',', '', Request::get('amount'));

				$transaction_cf_name = $this->setCfDetail ($range, strtotime($from_date), $to_date, $cur_invoice->property_unit->contact_lang);
				
				$new_bill = new Invoice;
				if(empty(Request::get('payment_type'))) {
					$new_bill->payment_type = $cur_invoice->payment_type;
				}

				$new_bill->discount 		= 0;
				$new_bill->name				= $transaction_cf_name;
				$new_bill->tax 				= 0;
				$new_bill->type 			= 1;
				$new_bill->property_id 		= $property->id;
				$new_bill->property_unit_id = $cur_invoice->property_unit_id;
				$new_bill->receipt_no 		= ++$property->receipt_counter;
                $new_bill->approved_by      = Auth::user()->id;

				//# Generate Running Number RECEIPT
                $month = Carbon::now()->month;
                $year = Carbon::now()->year;
                $date_period = $year.$month;

                $this->getMonthlyCounterDoc($date_period,Auth::user()->property_id);
                $receipt_no_label = $this->generateRunningLabel('RECEIPT',null,null,Auth::user()->property_id);

                $new_bill->receipt_no_label = $receipt_no_label;

                // Increase monthlyCounterDoc
                $this->increaseMonthlyCounterDocByPeriod($date_period,'RECEIPT',Auth::user()->property_id);
                //# End Generate Running Number

				$new_bill->transfer_only 	= $cur_invoice->transfer_only;
				$new_bill->payment_status	= 2;
				$new_bill->is_common_fee_bill = true;
				$new_bill->due_date			= $cur_invoice->due_date;
				$new_bill->submit_date		= date('Y-m-d');
				$new_bill->payment_type		= Request::get('payment_type');
				$new_bill->payment_date 	= Request::get('payment_date');
				if($new_bill->payment_type == 2) {
    				$new_bill->bank_transfer_date = $new_bill->payment_date;
					$new_bill->transfered_to_bank = true;
				}
                $new_bill->remark = Request::get('remark');
				$new_bill->save();

				$sum_instalment =  $paid_commonfee_over = 0;
				$instalment_cf 	= false;
				foreach(Request::get('t_instalment') as $t_i) {
					$inst_amount = str_replace(',', '', $t_i['amount']);
					if($inst_amount > 0) {
						$t = Transaction::find($t_i['id']);
						// check paid over of each transaction
						$real_total = $t->total - $t->sub_from_balance - $t->sub_from_discount - $t->instalment_balance;
						if( $inst_amount > $real_total  ) {
							$new_amount_total = $real_total;
						} else {
							$new_amount_total = $inst_amount;
						}
						$t->instalment_balance += $new_amount_total;

						$sum_instalment += $new_amount_total;
						// save new instalment transaction
						$t_new = new Transaction;
						$t_new->fill($t->toArray());
                        $t_new->sub_from_balance =
                        $t_new->sub_from_discount =
                        $t_new->instalment_balance = 0;
						$t_new->payment_date 		= $new_bill->payment_date;
						$t_new->payment_status 		= true;
						$t_new->quantity 			= 1;
						$t_new->price = $t_new->total 	= $new_amount_total;
						$t_new->submit_date 		= $new_bill->submit_date;
						$t_new->instalment_balance	= 0;
						$t_new->bank_transfer_date 	= $new_bill->bank_transfer_date;
						$t_new->invoice_id 			= $new_bill->id;
						$t_new->save();

                        $t_ref = new TransactionRef;
                        $t_ref->from_transaction    = $t->id;
                        $t_ref->to_transaction      = $t_new->id;
                        $t_ref->invoice_id          = $t->invoice_id;
                        $t_ref->receipt_id          = $t_new->invoice_id;
                        $t_ref->save();

						if($t->category == 1) {
							//check remaining amount of common fee
							$unit = PropertyUnit::find($new_bill->property_unit_id);
							//save common fee reference table
							$crf = new CommonFeesRef;
							$crf->invoice_id				= $new_bill->id;
							$crf->property_id				= $property->id;
							$crf->property_unit_id 			= $new_bill->property_unit_id;
							$crf->property_unit_unique_id 	= $unit->property_unit_unique_id;
							$crf->from_date					= $from_date;
							$crf->to_date 					= $to_date;
							$crf->payment_status			= true;
							$crf->range_type 				= $range;
							$crf->save();
							
							// Check paid over and save to property unit balance
							$instalment_cf = true;

							$t_new->detail = $transaction_cf_name;
							$t_new->save();
							$cf_instalment_amount = $new_amount_total;
						}
						// save old transaction instalment balance
						$t->save();
					}
				}

				// check fine
				$fine = Request::get('fine');
				if($fine && $fine['flag']) {
					$tf_amount = (empty($fine['amount']))?0:str_replace(',', '', $fine['amount']);
					$tf = new Transaction;

					if($cur_invoice->is_common_fee_bill)
						 $tf->detail = trans('messages.feesBills.cf_fine_text').$cur_invoice->name;
					else $tf->detail = trans('messages.feesBills.fine_text');

					$tf->quantity 				= 1;
					$tf->transaction_type 		= 1;
					$tf->category 				= 7;
					$tf->price = $tf->total 	= str_replace(',', '', $fine['total']);
					$tf->instalment_balance		= $tf_amount;
					$tf->invoice_id 			= $cur_invoice->id;
					$tf->property_id			= $property->id;
					$tf->property_unit_id 		= $cur_invoice->property_unit_id;
					$tf->due_date 				= $cur_invoice->due_date;					
					$tf->is_system_fine			= true;
					$tf->save();

					if($tf_amount > 0) {
						$t_fine_new = new Transaction;
						$t_fine_new->fill($tf->toArray());
						$t_fine_new->is_system_fine		= false;
						$t_fine_new->payment_date 		= $new_bill->payment_date;
						$t_fine_new->payment_status 	= true;
						$t_fine_new->quantity 			= 1;
						$t_fine_new->price = $t_fine_new->total 	= $tf_amount;
						$t_fine_new->submit_date 		= $new_bill->submit_date;
						$t_fine_new->instalment_balance	= 0;
						$t_fine_new->bank_transfer_date = $new_bill->bank_transfer_date;
						$t_fine_new->invoice_id 		= $new_bill->id;
                        $t_fine_new->save();
                        $cur_invoice->added_fine_flag  	=  true;
					}


					$sum_instalment += $tf->instalment_balance;
					// update invoice total
					$cur_invoice->total 			+= $tf->total;
					$cur_invoice->grand_total 		+= $tf->total;
					$cur_invoice->final_grand_total += $tf->total;
					if($instalment_cf) {
						$t_cf = Transaction::where('invoice_id',$cur_invoice->id)->where('category',1)->first();
						$t_cf->balance_before_fine = $t->total - ($t->sub_from_balance - $cf_instalment_amount) - $t->sub_from_discount - $t->instalment_balance; 
						$t_cf->save();
						$cur_invoice->last_instalment_cf_date_b4_fine = $new_bill->payment_date;
					}

				}
				// add instalment to invoice
				$cur_invoice->instalment_balance += $sum_instalment;

				$new_bill->total 				= 
				$new_bill->grand_total 			= 
				$new_bill->final_grand_total 	= $sum_instalment;
				$new_bill->save();

				if(Request::get('cf_month_over') && $new_bill->is_common_fee_bill) {
					// month over
					$mo = Request::get('cf_month_over');
					// common fee rate
					$cfr = Request::get('unit_cf_rate');
					// balance over
					$bo = Request::get('balance_remaining');
					$new_bill = $this->expandCommonfee($new_bill,$mo,$cfr,$bo);
				}
				$paid_over = Request::get('balance_remaining');
				if($paid_over > 0) {
					// get real amount because we get from overall amount
					$balance_ctl = new PropertyUnitPrepaidController;
					$new_bill = $balance_ctl->saveBillBalance($paid_over,$new_bill);
				} 

				// Save Bank transfer transaction
    			if($new_bill->payment_type == 2) {
    				$bt 	= new BankTransaction;
					$bt->saveBankBillTransaction($new_bill,Request::get('payment_bank'));
					$bank 	= new Bank;
					$bank->updateBalance (Request::get('payment_bank'),$new_bill->final_grand_total);
    			}

				// Save instalment log
				$ins = new InvoiceInstalmentLog;
				$ins->invoice_id 	= $cur_invoice->id;
				$ins->to_receipt_id = $new_bill->id;
				$ins->title 		= $new_bill->name;
				$ins->amount 		= $new_bill->final_grand_total;
				$ins->receipt_no 	= $new_bill->receipt_no;
				$ins->receipt_no_label 	= $new_bill->receipt_no_label;
				if( $instalment_cf ) {
					$ins->range_type 	= $range;
					$ins->from_date 	= $from_date;
					$ins->to_date 		= $to_date;
				} else {
					$ins->range_type 	= 0;
				}
				$ins->save();
				$cur_invoice->save();
				$property->save();

				/// refresh and check instalment is complete
				$old_invoice = Invoice::with('instalmentLog','transaction','invoiceFile')->find($cur_invoice->id);
				
				//check all paid
				$all_paid = true;
				foreach($old_invoice->transaction  as $t) {
					if( $t->transaction_type == 1 ) {
						if( $t->category == 1 ) {
							$real_total = $t->total - $t->sub_from_balance - $t->sub_from_discount;
							if($t->instalment_balance < $real_total) $all_paid = false;
						} else {
							if($t->instalment_balance < $t->total) $all_paid = false;
						}
					}
				}				
				// if complete instalment payment
				if($all_paid) { 
					// check reject if complete then change status to instalment completed invoice
					$old_invoice->payment_status = 6;
		            foreach ( $old_invoice->transaction as $t) {
						//reject old transaction
		                $t->is_rejected = true;
		                $t->save();
		            }
		            // remove cmf ref
					if( $old_invoice->commonFeesRef()->count() ) {
						$old_invoice->commonFeesRef()->delete();
					}
				} else {
					// reset status for next submit
					$old_invoice->payment_status = 0;
					$old_invoice->payment_type	= 0;
				}

				if(!empty(Request::get('attachment'))) {
					$this->saveAttachmentBill ($new_bill);
				}

				if($old_invoice->invoiceFile->count()) {
					foreach( $old_invoice->invoiceFile as $file) {
						$file->invoice_id = $new_bill->id;
						$file->save();
					}
				}

				$old_invoice->save();

                //Save invoice log
                $log = new ReceiptInvoiceLog;
                $log->receipt_id = $new_bill->id;
                $log->data = json_encode($temp_invoice);
				$log->save();
				
				if(!$cur_invoice->for_external_payer){
					$this->sendTransactionCompleteNotification($new_bill->property_id, $new_bill->property_unit_id,$new_bill->name,$new_bill->id, Auth::user()->id);
				}
			}
		}
		return redirect('admin/fees-bills/receipt');
	}

	public function printMultipleBill () {
		$ids = explode(",", Request::get('list-bill'));
		$bill = array();
		$print_type = 3; // print both
		if(Request::get('original-print') == "true" && Request::get('copy-print') == "false"){
            $print_type = 1; // print just original
        }elseif(Request::get('original-print') == "false" && Request::get('copy-print') == "true"){
            $print_type = 2; // print just copy
        }

		foreach ($ids as $id_item){
			//$id = $id_item['id'];
			$id = $id_item;
			$bill_check_type = Invoice::find($id);

			if(isset($bill_check_type->property_unit_id)) {
				//$bill = Invoice::with('property', 'instalmentLog', 'property.settings', 'property_unit', 'transaction', 'invoiceFile')->find($id);
				$bill_item = Invoice::with( array('instalmentLog' => function($q) {
					return $q->orderBy('created_at', 'ASC');
				},'property', 'property.settings', 'property_unit', 'transaction', 'invoiceFile','commonFeesRef') )->find($id);
			}else{
				$bill_item = Invoice::with('property', 'transaction', 'invoiceFile','commonFeesRef')->find($id);
			}

			if($bill_item->payment_status == 2) {
				//return view('feesbills.admin-receipt-view')->with(compact('bill'));
			}else {
				// get month range list
				$range = unserialize(constant('CF_RATE_RANGE_' . strtoupper(App::getLocale())));
				//check overdue invoice
				$is_overdue_invoice = false;
				if (!$bill_item->submit_date) {
					if (strtotime(date('Y-m-d')) > strtotime($bill_item->due_date))
						$is_overdue_invoice = true;
				} else {
					$day_submit = date('Y-m-d', strtotime($bill_item->submit_date));
					if (strtotime($day_submit) > strtotime($bill_item->due_date))
						$is_overdue_invoice = true;
				}
				$cal_cf_fine_flag = $cal_normal_bill_fine_flag = false;
				$total_fine = 0;
				// if invoice is overdue invoice
				if ($is_overdue_invoice) {
					// if property type is condominium
					if ($bill_item->property->property_type == 3) {
						// if invoice is common fee invoice
						if ($bill_item->is_common_fee_bill) {
							$cal_normal_bill_fine_flag = false;
							$overdue_ms = calOverdueMonth($bill_item->due_date, null, false);
							//if overdue month is over three months
							if ($overdue_ms > 3) {
								$cal_cf_fine_flag = true;
								if ($overdue_ms < 7) {
									$fine_rate = $bill_item->property->settings->condo_first_fine_rate;
									$fine = ($fine_rate / 100) * $bill_item->total;
								} else {
									$fine_rate = $bill_item->property->settings->condo_second_fine_rate;
									$fine = ($fine_rate / 100) * $bill_item->total;
								}
								$total_fine = $fine;
							}
						} else {
							$cal_normal_bill_fine_flag = true;
						}

					} else {
						$cal_normal_bill_fine_flag = true;
					}
				}

				// cal max date length if it can paid by instalment
				// can cal instalment if invoice is common fee bill and hasn't fine included and isn't rejected invoice
				$is_rejected_invoice = ($bill_item->payment_status == 3 || $bill_item->payment_status == 4);
				$can_instalment = ($bill_item->is_common_fee_bill && !($cal_cf_fine_flag || $cal_normal_bill_fine_flag) && !$is_rejected_invoice);

				if ($can_instalment) {
					if ($bill_item->commonFeesRef) {
						$maxlengthMonth = $bill_item->commonFeesRef->range_type;
						if (!$bill_item->instalmentLog->count()) {
							$m_reverse = 12 - $maxlengthMonth;
						} else {
							$sum_month = 0;
							foreach ($bill_item->instalmentLog as $log) {
								$sum_month += $log->range_type;
							}
							$m_reverse = $maxlengthMonth - ($sum_month);
						}
						if ($m_reverse > 0) {
							$range = array_slice($range, 0, $m_reverse, true);
						}
					}
				}

				$bank = new Bank;
				$bank_list = $bank->getBankList();
				$bills[] = $bill_item;
			}
		}
		return view('feesbills.admin-invoice-list-print-view')->with(compact('bills','fine_rate','fine','total_fine','cal_cf_fine_flag','cal_normal_bill_fine_flag','is_overdue_invoice','overdue_ms','bank_list','can_instalment','range','is_get_copy','print_type'));
	}

	public function getTotalText () {
		$_total = Request::get('total');
		$lang 	= App::getLocale();
		if( $lang == "en" )
            $t = convertIntToTextEng($_total);
        else
           	$t =convertIntToTextThai($_total);
        echo $t;              
	}

	public function InstalmentOverdue () {
		$invoice = Invoice::with(array('transaction','commonFeesRef','instalmentLog' => function($q) {
				    return $q->orderBy('created_at', 'ASC');
				}))->find(Request::get('bid'));
				

		$property = Property::find(Auth::user()->property_id);

		$maxlengthMonth 			= $invoice->commonFeesRef->range_type;
		$last_month_to_instalment 	= $invoice->commonFeesRef->to_date;
		$sum_month 					= 0;
		foreach ($invoice->instalmentLog as $log) {
			$sum_month += $log->range_type;
		}
		$remain_month 	= $maxlengthMonth - $sum_month;
		$ci 			= $invoice->instalmentLog->count();
		if( $ci ) {
			$start_month = $invoice->instalmentLog->toArray();
			$start_month = $start_month[$ci-1]['to_date'];
		} else {
			$start_month = $invoice->commonFeesRef->from_date;
		}

		$time_start 				= strtotime($start_month);
		// get start date of next month to start instalment by payment
		$next_month_to_instalment 	= date('Y-m-d',strtotime("+1 day",$time_start));
		
		/********
		Latest instalment month: date('Y-m-d',$time_start)
		Next to instalment: $next_month_to_instalment
		Last to instalment: $last_month_to_instalment
		******/

		//** update system added fine **/
		if(Request::get('fine_amount') && $invoice->added_fine_flag) {	
			$t_fine = Transaction::where('invoice_id',$invoice->id)->where('is_system_fine',true)->first();
			$new_fine = str_replace(',', '', Request::get('new_fine_total'));
			if($new_fine) {
				$fine_diff = $new_fine - $t_fine->total;
				if($fine_diff != 0) {
					$invoice->total 			+= $fine_diff;
					$invoice->grand_total 		+= $fine_diff;
					$invoice->final_grand_total += $fine_diff;
					
					$t_fine->quantity 	= 8;
					$t_fine->price 		= str_replace(',', '', Request::get('fine_price'));
					$t_fine->total 		= str_replace(',', '', Request::get('fine_total'));
					$t_fine->save();
					$invoice->save();
				} 
			}
		}

		$new_bill = new Invoice;	
		$new_bill->fill($invoice->toArray());
		$new_bill->fill(Request::all());
		$new_bill->receipt_no 		= ++$property->receipt_counter;

        // Generate Running Number RECEIPT
        $month = Carbon::now()->month;
        $year = Carbon::now()->year;
        $date_period = $year.$month;

        $this->getMonthlyCounterDoc($date_period,Auth::user()->property_id);
        $receipt_no_label = $this->generateRunningLabel('RECEIPT',null,null,Auth::user()->property_id);

        $new_bill->receipt_no_label = $receipt_no_label;

        // Increase monthlyCounterDoc
        $this->increaseMonthlyCounterDocByPeriod($date_period,'RECEIPT',Auth::user()->property_id);
        // End Generate Running Number

		$new_bill->payment_status	= 2;
		$new_bill->is_common_fee_bill = true;
		$new_bill->total 			= $invoice->total - $invoice->instalment_balance;
		$new_bill->grand_total 		= $invoice->grand_total - $invoice->instalment_balance;
		$new_bill->final_grand_total= $invoice->grand_total - $invoice->instalment_balance;
		$new_bill->due_date			= $invoice->due_date;
		$new_bill->submit_date		= date('Y-m-d h:i:s');
        $new_bill->approved_by      = Auth::user()->id;

		if( $invoice->smartPaymentLog ) {
			$new_bill->payment_type = 2;
			$new_bill->payment_date = $invoice->smartPaymentLog->transDate;
			$new_bill->smart_bill_ref_code = $invoice->smart_bill_ref_code;
		} else {
			$new_bill->payment_date		= Request::get('payment_date_confirm');
		}

		if($new_bill->payment_type == 2) {
			$new_bill->bank_transfer_date = $new_bill->payment_date;
			$new_bill->transfered_to_bank = true;
		}
		$new_bill->save();

		// save counter
		$property->save();

		$remaining_cf = 0;
		foreach( $invoice->transaction as $t_old) {
			
			$t = new Transaction;
			$t->fill($t_old->toArray());
			$remain_total 			= $t_old->total - $t_old->instalment_balance;

			if($t_old->category == 1) {
				//check remaining amount of common fee
				$remain_total = $remain_total - $t_old->sub_from_balance - $t_old->sub_from_discount;
				$remaining_cf = $remain_total;
				
			}

			if($remain_total > 0) {
				$t->payment_date 		= $new_bill->payment_date;
				$t->payment_status 		= true;
				$t->quantity 			= 1;
				$t->price = $t->total 	= $remain_total;
				$t->submit_date 		= $new_bill->submit_date;
				$t->instalment_balance	= 0;
				$t->bank_transfer_date 	= $new_bill->bank_transfer_date;
				$t->invoice_id 			= $new_bill->id;
				$t->save();
			}
			
			// reject transaction
			$t_old->is_rejected = true;
			$t_old->save();
		}

		//Check overdue fine
		if(Request::get('fine_amount') && !$invoice->added_fine_flag) {	
			$new_bill = $this->addFine($new_bill);
		}
		
		// Save instalment log
		$ins = new InvoiceInstalmentLog;
		$ins->invoice_id 	= $invoice->id;
		$ins->to_receipt_id = $new_bill->id;
		$ins->title 		= $new_bill->name;
		$ins->amount 		= $new_bill->final_grand_total;
		$ins->receipt_no 	= $new_bill->receipt_no;
		$ins->receipt_no_label 	= $new_bill->receipt_no_label;
		if($remaining_cf > 0 ) {
			$ins->range_type 	= $remain_month;
			$ins->from_date 	= $next_month_to_instalment;
			$ins->to_date 		= $last_month_to_instalment;

			$unit = PropertyUnit::find($new_bill->property_unit_id);

			//save common fee reference table
			$crf = new CommonFeesRef;
			$crf->invoice_id				= $new_bill->id;
			$crf->property_id				= $property->id;
			$crf->property_unit_id 			= $new_bill->property_unit_id;
			$crf->property_unit_unique_id 	= $unit->property_unit_unique_id;
			$crf->from_date					= $next_month_to_instalment;
			$crf->to_date 					= $last_month_to_instalment;
			$crf->payment_status			= true;
			$crf->range_type 				= $remain_month;
			$crf->save();

			// check for change bill name
			if( $invoice->is_common_fee_bill && $invoice->instalmentLog->count() ) {
				$new_bill = $this->changeStartCfMonthLabel ($new_bill);
			}

		} else {
			$ins->range_type 	= 0;
		}
		$ins->save();

		if(Request::get('cf_month_over') && $new_bill->is_common_fee_bill) {			
			// month over
			$mo = Request::get('cf_month_over');
			// common fee rate
			$cfr = Request::get('unit_cf_rate');
			// balance over
			$bo = Request::get('balance_remaining');

			// expand common fee when paid over again
			if($remaining_cf == 0) {
				$new_bill = $this->savePaidOverCF($new_bill, $mo, $cfr, $last_month_to_instalment);
			} else {
				$new_bill = $this->expandCommonfee($new_bill,$mo,$cfr,$bo);
			}
			// call new balance remaining
			$paid_over = $bo - ($cfr * $mo);
			
		} else {
			$paid_over = Request::get('balance_remaining');
		}

		// Check paid over and save to property unit balance
		if($paid_over > 0) {
			$balance_ctl = new PropertyUnitPrepaidController;
			$new_bill = $balance_ctl->saveBillBalance($paid_over,$new_bill);
		}

		// Save Bank transfer transaction

		if($new_bill->payment_type == 2) {
			$bt = new BankTransaction;
			if( $invoice->smartPaymentLog ) {
				$bank_id = $invoice->smartPaymentLog->property_bank_id;
			} else {
				$bank_id = Request::get('payment_bank');
			}
			if( $bank_id ) {
				$bt->saveBankBillTransaction($new_bill,$bank_id);
				$bank = new Bank;
				$bank->updateBalance ($bank_id,$new_bill->final_grand_total);
			}
		}

		/*if($new_bill->payment_type == 2) {
			$bt = new BankTransaction;
			$bt->saveBankBillTransaction($new_bill,Request::get('payment_bank'));
			$bank = new Bank;
			$bank->updateBalance (Request::get('payment_bank'),$new_bill->final_grand_total);
		}*/
		
		if(!empty(Request::get('attachment'))) {
			$this->saveAttachmentBill ($new_bill);
		}
		// transfer old invoice evidence to new receipt
		if($invoice->invoiceFile->count()) {
			foreach( $invoice->invoiceFile as $file) {
				$file->invoice_id = $new_bill->id;
				$file->save();
			}
		}

		// remove commom fee ref
		if( $invoice->commonFeesRef()->count() ) {
			$invoice->commonFeesRef()->delete();
		}

		//reject old invoice
		$invoice->payment_status = 4;
		$invoice->save();

		// TODO: notification success to mobile
		if(!$invoice->for_external_payer){
			$this->sendTransactionCompleteNotification($new_bill->property_id, $new_bill->property_unit_id,$new_bill->name,$new_bill->id, Auth::user()->id);
		}

		
		return redirect('admin/fees-bills/receipt');
	}

	function addFine ($bill) {
		//Check overdue fine
		if(Request::get('fine_total')) {	
			if(Request::get('fine_text')) {

				$t_title = Request::get('fine_text');
			} else {
				if($bill->is_common_fee_bill)
					$t_title = trans('messages.feesBills.cf_fine_text').$bill->name;
				else $t_title = trans('messages.feesBills.fine_text');
			}
			$total = Request::get('fine_total');
			$trans[] = new Transaction([
				'detail' 				=> $t_title,
				'quantity' 				=> Request::get('fine_amount'),
				'price' 				=> Request::get('fine_price'),
				'total' 				=> $total,
				'transaction_type' 		=> $bill->type,
				'property_id' 			=> $bill->property_id,
				'property_unit_id' 		=> $bill->property_unit_id,
				'for_external_payer' 	=> $bill->for_external_payer,
				'category' 				=> 7,
				'due_date'				=> $bill->due_date,
				'payment_date' 			=> $bill->payment_date,
				'bank_transfer_date' 	=> $bill->bank_transfer_date,
				'payment_status' 		=> true
			]);
			$bill->transaction()->saveMany($trans);

			$bill->total 				+= $total;
			$bill->grand_total 			+= $total;
			$bill->final_grand_total	+= $total;
			$bill->save();
		}

		return $bill;
	}

	public function saveAttachmentBill ($invoice) {
		$attach = [];
		foreach (Request::get('attachment') as $key => $file) {
			//Move Image
			$path = $this->createLoadBalanceDir($file['name']);
			$attach[] = new InvoiceFile([
				'name' 			=> $file['name'],
				'url' 			=> $path,
				'file_type' 	=> $file['mime'],
				'is_image'		=> $file['isImage'],
				'original_name'	=> $file['originalName']
			]);
		}
		$invoice->invoiceFile()->saveMany($attach);
	}

	public function expandCommonfee ($bill,$mo,$cfr,$bo) {
		
		$bill->load('commonFeesRef','property_unit');
		$next_mo_txt = "+".$mo." months";
		$cur_to_date = $bill->commonFeesRef->to_date;
		$cur_to_date = date('Y-m-01',strtotime($cur_to_date));
		$start_new_date = strtotime(date('Y-m-t',strtotime("+1 month", strtotime($cur_to_date))));
		$to_new_date = date('Y-m-t',strtotime($next_mo_txt, strtotime($cur_to_date)));

		$bill->commonFeesRef->to_date = $to_new_date;
		$bill->commonFeesRef->range_type += $mo;
		$bill->commonFeesRef->save();

		$tcf_name = $this->setCfDetail ($mo, $start_new_date, $to_new_date, $bill->property_unit->contact_lang);
		
		$trans = new Transaction;
		$trans->detail 				= $tcf_name;
		$trans->quantity 			= $mo;
		$trans->price 				= $cfr;
		$trans->total 				= $mo * $cfr;
		$trans->transaction_type 	= $bill->type;
		$trans->property_id 		= $bill->property_id;
		$trans->property_unit_id 	= $bill->property_unit_id;
		$trans->for_external_payer 	= false;
		$trans->category 			= 1;
		$trans->due_date			= $bill->due_date;
		$trans->invoice_id			= $bill->id;
		$trans->submit_date			= $bill->submit_date;
		$trans->payment_date		= $bill->payment_date;
		$trans->payment_status 		= true;
		$trans->bank_transfer_date  = $bill->bank_transfer_date;
		$trans->instalment_balance	= 0;
		$trans->created_at 			= date('Y-m-d 23:59:59');
		$trans->save();

		$r 		= $bill->commonFeesRef->range_type;
		$from 	= strtotime($bill->commonFeesRef->from_date);
		$to   	= $bill->commonFeesRef->to_date;

		$bill->name 				=  $this->setCfDetail($r, $from, $to, $bill->property_unit->contact_lang);
		$bill->total 				+= $trans->total;
		$bill->grand_total 			+= $trans->total;
		$bill->final_grand_total 	+= $trans->total;
		$bill->save();
		return $bill;
	}

	public function savePaidOverCF ($bill, $mo, $cfr, $end_cf_rate) {

		$bill->load('commonFeesRef','property_unit');
		$time_start = strtotime($end_cf_rate);
		$next_month_to_instalment 	= date('Y-m-d',strtotime("+1 day",$time_start));
		$time_start = strtotime($next_month_to_instalment);
		$end_cf   = strtotime("+".$mo." month", $time_start);
		$end_cf   = strtotime("-1 day", $end_cf);
		$last_month_to_instalment = date('Y-m-d',$end_cf);
		$unit = PropertyUnit::find($bill->property_unit_id);

		$tcf_name = $this->setCfDetail ($mo, $time_start, $last_month_to_instalment, $bill->property_unit->contact_lang);

		//save common fee reference table
		$crf = new CommonFeesRef;
		$crf->invoice_id				= $bill->id;
		$crf->property_id				= $unit->property_id;
		$crf->property_unit_id 			= $bill->property_unit_id;
		$crf->property_unit_unique_id 	= $unit->property_unit_unique_id;
		$crf->from_date					= $next_month_to_instalment;
		$crf->to_date 					= $last_month_to_instalment;
		$crf->payment_status			= true;
		$crf->range_type 				= $mo;
		$crf->save();

		$trans = new Transaction;
		$trans->detail 				= $tcf_name;
		$trans->quantity 			= $mo;
		$trans->price 				= $cfr;
		$trans->total 				= $mo * $cfr;
		$trans->transaction_type 	= $bill->type;
		$trans->property_id 		= $bill->property_id;
		$trans->property_unit_id 	= $bill->property_unit_id;
		$trans->for_external_payer 	= false;
		$trans->category 			= 1;
		$trans->due_date			= $bill->due_date;
		$trans->invoice_id			= $bill->id;
		$trans->submit_date			= $bill->submit_date;
		$trans->payment_date		= $bill->payment_date;
		$trans->payment_status 		= true;
		$trans->bank_transfer_date  = $bill->bank_transfer_date;
		$trans->instalment_balance	= 0;
		$trans->created_at 			= date('Y-m-d 23:59:59');
		$trans->save();

		$r 		= $bill->commonFeesRef->range_type + $mo;
		$from 	= strtotime($bill->commonFeesRef->from_date);
		$to   	= $last_month_to_instalment;
		
		$bill->name 				=  $this->setCfDetail($r, $from, $to, $bill->property_unit->contact_lang);
		$bill->total 				+= $trans->total;
		$bill->grand_total 			+= $trans->total;
		$bill->final_grand_total 	+= $trans->total;
		$bill->save();
		
		return $bill;
	}

    // Force function Duplicate Invoice_no to Invoice_no_label
    function forceDuplicateInvoiceNoLabel(){
        $property = Property::find('ff4055ef-b800-43fb-8f63-a0087fa9ec04');
        DB::table('invoice')->where('property_id',$property->id)->orderBy('created_at')->chunk(1000, function ($invoices) {
            foreach ($invoices as $invoice) {
                //
                $update_invoice = Invoice::find($invoice->id);
                $running_no = str_pad($update_invoice->invoice_no, 5, '0', STR_PAD_LEFT);
                $custom_label = "NBH.IV"."60"."10".$running_no;
                $update_invoice->invoice_no_label = $custom_label;

                if($update_invoice->receipt_no != null){
                    $running_no_receipt = str_pad($update_invoice->receipt_no, 5, '0', STR_PAD_LEFT);
                    $custom_label_receipt = "NBH.RE"."60"."10".$running_no_receipt;
                    $update_invoice->receipt_no_label = $custom_label_receipt;
                }
                $update_invoice->save();
            }
        });

        return "true";
    }

    function checkUnitCfDuplicate($unit_,$time_start,$to_date_invoice) {
        $from_date_invoice = date('Y-m-d',$time_start);

        $dup_cf = CommonFeesRef::where(function ($query) use ($from_date_invoice,$to_date_invoice,$unit_) {

            $query->whereBetween('from_date',[$from_date_invoice,$to_date_invoice])

                    ->whereHas('invoice',function ($q) {

                        $q->whereIn('payment_status',[0,1,2]);
                    }

            )->where('property_unit_id',$unit_->id);

            $query->orWhere(function ($p) use ($from_date_invoice,$to_date_invoice,$unit_) {

                $p->whereBetween('to_date',[$from_date_invoice,$to_date_invoice])

                        ->whereHas('invoice',function ($q) {

                            $q->whereIn('payment_status',[0,1,2]);

                        }
                )->where('property_unit_id',$unit_->id);

            });

            $query->orWhere(function ($p) use ($from_date_invoice,$to_date_invoice,$unit_) {

                $p  ->where('from_date','<=',$from_date_invoice)
                    ->where('to_date','>=',$to_date_invoice)
                    ->whereHas('invoice',function ($q) {

                        $q->whereIn('payment_status',[0,1,2]);
                    })
                    ->where('property_unit_id',$unit_->id);
            });

        })->orderBy('to_date','desc')->first();

        $result = [];
        if($dup_cf) {
            $end_date = Carbon::parse($to_date_invoice);
            $end_date_cf = Carbon::parse($dup_cf->to_date);

            if ($end_date_cf->greaterThanOrEqualTo($end_date)) {
                // no creating invoice
                $result['no_duplicate'] = false;
            } else {
                $diff_month = $end_date_cf->diffInMonths($end_date->addDay(),true);
                $result['no_duplicate']             = true;
                $result['duplicate_month']          = $diff_month;
                $result['month_start_timestamp']    = strtotime($end_date_cf->addDay());
            }
        } else {
            // create invoice with full selected month
            $result['no_duplicate']     = true;
        }
        return $result;
    }

    function checkIsFromInstalment () {
        $id = Request::get('bid');
        $receipt = Invoice::with('transaction','transactionInstalmentLog')->find($id);
        $msg = "";
        $can_cancel = true;
        $instalments = InvoiceInstalmentLog::with('fromInvoice')->where('to_receipt_id', $receipt->id)->first();
        if ( $instalments && !$receipt->transactionInstalmentLog->count() ) {
            $can_cancel = false;
            $msg = trans('messages.feesBills.cancel_receipt_contact_nabour');
        }
        return response()->json(['result' => $can_cancel, 'msg' => $msg]);
    }

    function rejectReceipt () {

        $id = Request::get('bid');
        $receipt = Invoice::with('transaction','transactionInstalmentLog')->find($id);

        if($receipt  && $receipt->payment_status != 5 ) {
            //$this->cloneInvoice ($receipt);
            $can_cancel = true;
            $instalments = InvoiceInstalmentLog::with('fromInvoice')->where('to_receipt_id', $receipt->id)->first();
            if ( $instalments && !$receipt->transactionInstalmentLog->count()) {
                $can_cancel = false;
            }
            // check instalment
            if ($can_cancel) {
                $receipt->timestamps = false;
                $this->cancelBill($receipt);
                // refresh
                $receipt->fresh();
                $receipt->payment_status = 5;
                $receipt->cancelled_by = Auth::user()->id;
                $receipt->cancel_reason = Request::get('reason');
                $receipt->cancelled_at = date('Y-m-d H:i:s');
                $receipt->timestamps  = false;
                //$receipt->invoice_no_label   = null;
                $receipt->save();

                if( $receipt->is_common_fee_bill ) {
                    $receipt->load('commonFeesRef');
                    $cf = $receipt->commonFeesRef;
                    if($cf) {
                        $cf->payment_status = false;
                        $cf->save();
                    }
                }

                if ($receipt->transfered_to_bank) {
                    // update bank balance
                    $bank_transaction = BankTransaction::with('getBank')->where('invoice_id', $receipt->id)->first();

                    if ($bank_transaction) {
                        $bank = $bank_transaction->getBank;
                        $bank->timestamps = false;
                        $bank->balance -= $receipt->final_grand_total;
                        $bank->save();
                        $bank_transaction->delete();
                    }

                }
                // if isn't retroactive, revenue, imported receipt or aggregate receipt
                if( !$receipt->is_retroactive_record && !$receipt->from_imported && !$receipt->is_revenue_record && !$receipt->is_aggregate_receipt ) {

                    //check paid over common fee
                    $paid_over = PropertyUnitBalanceLog::where('invoice_id', $receipt->id)->orderBy('created_at', 'desc')->first();
                    if ($paid_over) {
                        if( $paid_over->p_unit_balance_added ) {
                            $property_unit = PropertyUnit::find($receipt->property_unit_id);
                            $property_unit->cf_balance -= $paid_over->cf_balance;
                            $property_unit->save();
                        }
                        $paid_over->delete();
                    }
                    // Clone old invoice
                    if (!$instalments) {
                        $this->cloneInvoice($receipt);
                    } else {
                        $old_invoice = Invoice::with('transaction')->find($instalments->invoice_id);
                        $instalment_amount = 0;
                        foreach ($receipt->transaction as $t ) {
                            $t_ref = TransactionRef::where('to_transaction',$t->id)->first();
                            if( $t_ref ) {
                                $inv_t = Transaction::find($t_ref->from_transaction);
                                $inv_t->instalment_balance -= $t->total;
                                $inv_t->save();
                                $instalment_amount += $t->total;
                            }
                        }
                        $old_invoice->instalment_balance -= $instalment_amount;
                        // check if invoice has been canceled
                        if( $old_invoice->payment_status == 4 ) {
                            // restore status
                            $old_invoice->payment_status = 0;
                            foreach ($old_invoice->transaction as $t) {
                                $t->is_rejected = false;
                                $t->save();
                            }
                        }
                        $old_invoice->save();

                        $log = InvoiceInstalmentLog::where('to_receipt_id',$receipt->id)->first();
                        $log->delete();
                    }
                }

                // Check if receipt created by invoice aggregation
                if($receipt->is_aggregate_receipt) {
                    // restore invoice status
                    $ags = ReceiptInvoiceAggregate::with('invoice')->where('receipt_id',$receipt->id)->get();
                    foreach ($ags as $ag) {
                        $ag_invoice = $ag->invoice;
                        $ag_invoice->payment_status = 0;
                        $ag_invoice->save();
                        foreach ($ag_invoice->transaction as $t) {
                            $t->is_rejected = true;
                            $t->save();
                        }
                    }
                    $receipt->receiptInvoiceAggregate()->delete();
                }
                $result = true;
                $msg = '';
            } else {
                $result = false;
                $msg = trans('messages.feesBills.cancel_receipt_contact_nabour');
            }
        } else {
            $result = false;
            $msg = trans('messages.feesBills.no_receipt_found');
        }
        return response()->json(['result' => $result, 'msg' => $msg ]);
    }

    protected function cloneInvoice ($receipt) {

        if( $receipt->invoiceLog ) {
            $old_invoice = json_decode($receipt->invoiceLog->data,true);
        } else {
            $old_invoice = $receipt->toArray();
        }

        $re_invoice = new invoice;
        $re_invoice->timestamps = false;

        foreach ($old_invoice as $key => $val) {
            if( !in_array($key, ['id','transaction','instalment_log','invoice_log','common_fees_ref','transaction_instalment_log'])) {
                $re_invoice->{$key} = $val;
            }
        }
        $re_invoice->payment_type       = null;
        $re_invoice->payment_status     = 0;
        $re_invoice->payment_date       = null;
        $re_invoice->submit_date        = null;
        $re_invoice->bank_transfer_date = null;
        $re_invoice->transfered_to_bank = false;
        $re_invoice->mixed_payment      = false;
        $re_invoice->cash_on_hand_transfered            = false;
        $re_invoice->added_fine_flag                    = false;
        $re_invoice->cash_to_bank_transfered_date       = null;
        $re_invoice->last_instalment_cf_date_b4_fine    = null;
        $re_invoice->updated_at         = $re_invoice->created_at;
        $re_invoice->receipt_no_label   = null;
        $re_invoice->cancelled_by       = null;
        $re_invoice->cancelled_at       = null;
        $re_invoice->cancel_reason      = null;
        $re_invoice->approved_by        = null;
        $re_invoice->save();

        foreach ($old_invoice['transaction']  as $key => $t ) {
            $re_t = new Transaction;
            $re_t->timestamps = false;

            foreach ($t as $i => $v ) {
                if( !in_array($i, ['id','invoice_id'])) {
                    $re_t->{$i} = $v;
                }
            }
            $re_t->invoice_id           = $re_invoice->id;
            $re_t->payment_status       = false;
            $re_t->payment_date         = null;
            $re_t->bank_transfer_date   = null;
            $re_t->updated_at           = $re_t->created_at;
            $re_t->is_rejected          = false;
            $re_t->save();
        }

        if( $receipt->is_common_fee_bill ) {
            $cf                     = $receipt->commonFeesRef;
            $cf->invoice_id         = $re_invoice->id;
            $cf->payment_status     = false;
            $cf->save();
        }

        // reset vehicle bill
        $vehicle = Vehicle::where('invoice_id', $receipt->id)->first();
        if (isset($vehicle)) {
            $vehicle->invoice_id = $re_invoice->id;
            $vehicle->save();
        }
	}
}