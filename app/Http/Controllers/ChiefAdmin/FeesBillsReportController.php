<?php namespace App\Http\Controllers\ChiefAdmin;
use Request;
use Illuminate\Routing\Controller;
use Auth;
use Redirect;
use App;
use Excel;
use App\Http\Controllers\GeneralFeesBillsReportController;
use DB;
# Model
use App\Invoice;
use App\User;
use App\Property;
use App\PropertyUnit;
use App\Bank;
use App\BankTransaction;

class FeesBillsReportController extends Controller {

	public function __construct () {
		$this->middleware('auth');
        if(Auth::check() && Auth::user()->role == 3){
            $this->middleware('auth:menu_finance_group');
        }
		view()->share('active_menu', 'bill-report');
		if(Auth::check() && (Auth::user()->role == 2 && !Auth::user()->is_chief)) Redirect::to('feed')->send();
	}

	public function incomeExpenseReport () {
		$property = Property::find(Auth::user()->property_id);
		$banks 	  = Bank::where('property_id',Auth::user()->property_id)->get();
		$officers = User::where('property_id',Auth::user()->property_id)->whereIn('role',[1,3])->lists('name','id')->toArray();

		$unit_list = array('-'=> trans('messages.feesBills.all_unit'));
		$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();

		return view('feesbills.admin-report')->with(compact('property','banks','unit_list','officers'));
	}

	public function reportMonth () {
		if(Request::ajax()) {
			$date 	 = Request::get('year')."-".Request::get('month');
			$l_date  = date('Y-m-t 23:59:59',strtotime($date));
			$date_bw = array($date."-01 00:00:00", $l_date);
			$report_controller = new GeneralFeesBillsReportController;
			$trans 	 = $report_controller->getActiveTransaction($date_bw);
			$result  = $report_controller->returnReport($trans);
			$result += $this->calTotalBalance($date_bw);
			$result += $report_controller->fundReport ($date_bw);
			$result += $this->getBankBalance($date_bw);
			$result += $this->getCashOnHand ($date_bw);
			return response()->json( $result );
		}
	}

	public function reportYear () {
		if(Request::ajax()) {
		    $from = str_replace('/','-',Request::get('from-date'));
		    $to = str_replace('/','-',Request::get('to-date'));
			$date_bw = array($from." 00:00:00", $to." 23:59:59");
			$report_controller = new GeneralFeesBillsReportController;
			$trans 	 = $report_controller->getActiveTransaction($date_bw);
			$result  = $report_controller->returnReport($trans);
			$result += $this->calTotalBalance($date_bw);
			$result += $report_controller->fundReport ($date_bw);
			$result += $this->getBankBalance($date_bw);
			$result += $this->getCashOnHand ($date_bw);
			return response()->json( $result );
		}
	}

	public function calTotalBalance ( $date_bw ) {
		$result = array('real_balances' => array('real_income' => 0,'paid' => 0, 'unpaid' => 0 ,'ext_invoice' => 0, 'int_invoice' => 0, 'ext_unpaid' => 0, 'int_unpaid' => 0),'real_balance_result' => 0);
		$invoices = new Invoice;
		$invoices->where( function ($q) use( $date_bw ) {
			$q->orWhere( function ($r) use( $date_bw ) {
				$r->whereNotNull('bank_transfer_date')
				->where('bank_transfer_date','>=',$date_bw[0])
    			->where('bank_transfer_date','<=',$date_bw[1]);
			});
			$q->orWhere( function ($r) use( $date_bw ) {
				$r->whereNull('bank_transfer_date')
				->where('payment_date','>=',$date_bw[0])
    			->where('payment_date','<=',$date_bw[1]);
			});
			$q->orWhere( function ($r) use( $date_bw ) {
				$r->whereNull('payment_date')
				->where('due_date','>=',$date_bw[0])
    			->where('due_date','<=',$date_bw[1]);
			});
		})->where('property_id',Auth::user()->property_id)->where('type',1)->whereNotIn('payment_status',array(3,4,5))->chunk(300,
			function($invoices) use (&$result) {
				$result['real_balance_result'] = 1;
				foreach( $invoices as $invoice ) {
					$total = $invoice->final_grand_total - $invoice->instalment_balance;
					$result['real_balances']['real_income'] += $invoice->final_grand_total;
					if( $invoice->payment_status == 2 || $invoice->payment_status == 1) {
						$result['real_balances']['paid'] += $invoice->final_grand_total;
					} else {
						$result['real_balances']['unpaid'] += $total;
					}
					
					if( $invoice->payment_status == 0 && $invoice->instalment_balance > 0 ) {
						// substract duble invoice income that come from instalment invoice
						$result['real_balances']['real_income'] -= $invoice->instalment_balance;
					}
					
					if($invoice->payment_status != 2) {
						if($invoice->for_external_payer) {
							$result['real_balances']['ext_invoice']++;
							$result['real_balances']['ext_unpaid'] += $total;
						} else {
							$result['real_balances']['int_invoice']++;
							$result['real_balances']['int_unpaid'] += $total;
						}
					}
				}
			});//->get();
		
		return $result;
	}

	public function exportYearReport () {
        $from = str_replace('/','-',Request::get('from-date'));
        $to = str_replace('/','-',Request::get('to-date'));
        $date_bw = array($from." 00:00:00", $to." 23:59:59");
		$report_controller = new GeneralFeesBillsReportController;
		$trans 	 = $report_controller->getActiveTransaction($date_bw);
		if(count($trans)) {

			$result = $this->exportResult($trans);
			$result += $this->getBankTransaction($date_bw);
			$result += $this->getCashOnHandListForReport ($date_bw);
			$information['from']    = localDate($from);
			$information['to']      = localDate($to);
			$property = Property::with('has_province')->find(Auth::user()->property_id);
			$information['property']  = $property->toArray();
			$filename = "รายงานรายรับ-รายจ่าย";
            return view('feesbills.report-year-download')->with(compact('result','information','filename'));
		}
	}

    public function exportInvoiceReport () {
        $date_bw = [];
	    if( Request::get('from-date') ) {
            $from = str_replace('/','-',Request::get('from-date'));
            $date_bw[0] = $from." 00:00:00";
            $information['from'] = localDate($from);
        }

        if( Request::get('to-date') ) {
            $to = str_replace('/','-',Request::get('to-date'));
            $date_bw[1] = $to." 23:59:59";
            $information['to'] = localDate($to);
        }

        if( Request::get('start-created-date') ) {
            $information['created_from']    = localDate( Request::get('start-created-date') );

        }
        if( Request::get('end-created-date') ) {
            $information['created_to']      = localDate( Request::get('end-created-date') );
        }


        $report_controller = new GeneralFeesBillsReportController;
        $trans 	 = $report_controller->getAllInvoiceActiveTransaction($date_bw);
        if(count($trans)) {
            $result = $this->exportInvoiceResult($trans);
            $property = Property::with('has_province')->find(Auth::user()->property_id);
            $information['property']  = $property->toArray();
            $filename = "รายงานใบแจ้งหนี้";
            return view('feesbills.report-invoice-download')->with(compact('result','information','filename'));
        }
    }

	public function exportMonthReport () {
		$month 	 = Request::get('month');
		$year 	 = Request::get('year');
		$end_of_month = date("Y-m-t",strtotime($year."-".$month."-01"));
		$date_bw = array($year."-".$month."-01", $end_of_month);
		$report_controller = new GeneralFeesBillsReportController;
		$trans 	 = $report_controller->getActiveTransaction($date_bw);
		if(count($trans)) {

			$result = $this->exportResult($trans);
			$result += $this->getBankTransaction($date_bw);
			$result += $this->getCashOnHandListForReport ($date_bw);
			$information['month'] = trans('messages.dateMonth.'.$month);
			$information['year'] = localYear(Request::get('year'));
			$property = Property::with('has_province')->find(Auth::user()->property_id);
			$information['property']  = $property->toArray();
			$filename = "report_".$information['month']."-".$information['year'];
            return view('feesbills.report-month-download')->with(compact('result','information','filename'));
		}
	}

    public function exportCashFlow () {
        $from = str_replace('/','-',Request::get('from-date'));
        $to = str_replace('/','-',Request::get('to-date'));
        $date_bw = array($from." 00:00:00", $to." 23:59:59");
        $report_controller = new GeneralFeesBillsReportController;
        $trans 	 = $report_controller->getActiveTransaction($date_bw);

        $banks 	= Bank::where('property_id',Auth::user()->property_id)
            ->where('active',true)
            ->orderBy('created_at','ASC')->get();

        if(count($trans)) {

            $result = $this->exportResultCashFlow($trans);
            //$information['year'] = localYear(Request::get('year'));
            $property = Property::with('has_province')->find(Auth::user()->property_id);
            $information['property']  = $property->toArray();
            $information['from']    = localDate($from);
            $information['to']      = localDate($to);
            //$filename = "report_".$information['year'];
            $filename = "Cash flow report";

            /*Excel::create($filename, function($excel) use ($result,$information) {
				$excel->sheet('sheet 1', function($sheet) use ($result,$information){
					$sheet->setWidth(array(
						'A'     =>  40,
						'D'     =>  40
					));
					$sheet->loadView('feesbills.report.report-cash-flow-download')->with(compact('result','information'));
				})->export('xlsx');
			}); */

            return view('feesbills.report.report-cash-flow-download')->with(compact('result','filename','information','banks'));
        }
    }

	public function exportResult($trans) {

		$income 	= "income";
		$expense 	= 'expense';
		$discount 	= 'discount';
		$vat_discount 	= trans('messages.feesBills.vat_discount');
		$vat_expense 	= trans('messages.feesBills.vat_expense');
		$w_tax 		= trans('messages.feesBills.withholding_tax');
		$result 	= array('total'=> array( $income => 0, $expense => 0, $discount => 0, 'cash_on_hand' => 0) );
		$in_cate 	= unserialize(constant('INVOICE_INCOME_CATE_'.strtoupper(App::getLocale())));
		$ex_cate 	= unserialize(constant('INVOICE_EXPENSE_CATE_'.strtoupper(App::getLocale())));
		$bank_name 	= unserialize(constant('BANK_LIST_'.strtoupper(App::getLocale())));

		foreach ($trans as $tran) {

			$t_i = Invoice::select('expense_no','expense_no_label', 'receipt_no','receipt_no_label')->find($tran->invoice_id);
			$array_detail = array(
				'detail'		=> $tran->detail,
				'value' 		=> $tran->total,
				'date'			=> $tran->payment_date,
				'type'			=> $tran->transaction_type,
				'bank_transfer_date' => $tran->bank_transfer_date	
			);

			if($tran->property_unit_id) {
				$property_unit = PropertyUnit::find($tran->property_unit_id);
			}	
			
			switch ($tran->transaction_type) {
				case 1:
					// Is income
					//if($tran->bank_transfer_date) {
						$index = 'income';
						$t_total = ($tran->total - floatval($tran->sub_from_balance));
						$result['total'][$income] += $t_total;
					//} 
					if(empty($result[$index][$in_cate[$tran->category]]))
					$result[$index][$in_cate[$tran->category]]['total'] 		= 0;
					$result[$index][$in_cate[$tran->category]]['total'] 		+= $t_total;
					$array_detail['value'] 										= $tran->total;
					$array_detail['sub_from_balance'] 							= $tran->sub_from_balance;
					$array_detail['sub_from_discount'] 							= $tran->sub_from_discount;
					$array_detail['total'] 										= $t_total;
					$array_detail['no'] 										= $t_i->receipt_no;
					$array_detail['no_label'] 									= $t_i->receipt_no_label;
					$array_detail['p_u_no']	= ($tran->property_unit_id)?$property_unit->unit_number:'-';
					if($tran->category == 1)
					$array_detail['p_u_size']	= ($tran->property_unit_id)?$property_unit->property_size:'-';	
					$result[$index][$in_cate[$tran->category]]['details'][] 	= $array_detail;
					break;
				case 2:
				// Is expense
					if(empty($result['expense'][$ex_cate[$tran->category]]))
					$result['expense'][$ex_cate[$tran->category]]['total'] 		= 0;
                    $total = $tran->total;// - ($tran->w_tax * $tran->total/100);
					$result['expense'][$ex_cate[$tran->category]]['total'] 		+= $total;
					$result['total'][$expense] 									+= $total;
					$array_detail['no'] 										= $t_i->expense_no;
					$array_detail['no_label'] 									= $t_i->expense_no_label;
                    $array_detail['total'] = $array_detail['value']             = $total;
					$result['expense'][$ex_cate[$tran->category]]['details'][] 	= $array_detail;
					break;
				case 3:
					// Is discount
					if(empty($result[$discount]['total']))
					$result[$discount]['total']					= 0;
					$result[$discount]['total'] 				+= $tran->total;
					$result['total'][$discount] 				+= $tran->total;
					$array_detail['no'] 						= $t_i->receipt_no;
					$array_detail['no_label'] 						= $t_i->receipt_no_label;
					$result[$discount]['details'][] 			= $array_detail;
					break;
				case 4:
					// Is vat income
					if(empty($result['income'][$vat_discount]))
					$result['income'][$vat_discount]['total']		= 0;
					$result['income'][$vat_discount]['total'] 		+= $tran->total;
					$result['total'][$income] 						+= $tran->total;
					$array_detail['no'] 							= $t_i->receipt_no;
					$array_detail['no_label'] 							= $t_i->receipt_no_label;
					$result['income'][$vat_discount]['details'][] 	= $array_detail;
					break;
				case 5:
					// Is vat expense
					if(empty($result['expense'][$vat_expense]))
					$result['expense'][$vat_expense]['total'] 		= 0;
					$result['expense'][$vat_expense]['total'] 		+= $tran->total;
					$result['total'][$expense] 						+= $tran->total;
					$array_detail['no'] 							= $t_i->expense_no;
					$array_detail['no_label'] 							= $t_i->expense_no_label;
					$result['expense'][$vat_expense]['details'][] 	= $array_detail;
					break;
				default:
					// Is withholding tax
					if(empty($result['expense'][$w_tax]))
					$result['expense'][$w_tax]['total'] 	= 0;
					$result['expense'][$w_tax]['total'] 	+= $tran->total;
					$result['total'][$expense] 				-= $tran->total;
					$array_detail['no'] 					= $t_i->expense_no;
					$array_detail['no_label'] 				= $t_i->expense_no_label;
					$result['expense'][$w_tax]['details'][] = $array_detail;
					break;
			}
		}
		return $result;
	}

    public function exportInvoiceResult($trans) {

        $income 	= "income";
        $discount 	= 'discount';
        $vat_discount 	= trans('messages.feesBills.vat_discount');
        $result 	= array('total'=> array( $income => 0, $discount => 0) );
        $in_cate 	= unserialize(constant('INVOICE_INCOME_CATE_'.strtoupper(App::getLocale())));

        foreach ($trans as $tran) {

            $t_i = Invoice::select('invoice_no','invoice_no_label')->find($tran->invoice_id);
            $array_detail = array(
                'detail'		=> $tran->detail,
                'value' 		=> $tran->total,
                'date'			=> $tran->created_at,
                'due_date'		=> $tran->due_date,
                'type'			=> $tran->transaction_type,
                'bank_transfer_date' => $tran->bank_transfer_date
            );

            if($tran->property_unit_id) {
                $property_unit = PropertyUnit::find($tran->property_unit_id);
            }

            switch ($tran->transaction_type) {
                case 1:
                    // Is income
                    //if($tran->bank_transfer_date) {
                    $index = 'income';
                    $t_total = ($tran->total - floatval($tran->sub_from_balance));
                    $result['total'][$income] += $t_total;
                    //}
                    if(empty($result[$index][$in_cate[$tran->category]]))
                        $result[$index][$in_cate[$tran->category]]['total'] 		= 0;
                    $result[$index][$in_cate[$tran->category]]['total'] 		+= $t_total;
                    $array_detail['value'] 										= $tran->total;
                    $array_detail['sub_from_balance'] 							= $tran->sub_from_balance;
                    $array_detail['sub_from_discount'] 							= $tran->sub_from_discount;
                    $array_detail['total'] 										= $t_total;
                    $array_detail['no'] 										= $t_i->invoice_no;
                    $array_detail['no_label'] 									= $t_i->invoice_no_label;
                    $array_detail['p_u_no']	= ($tran->property_unit_id)?$property_unit->unit_number:'-';
                    $array_detail['sub_type']	= ($tran->property_unit_id)?$property_unit->sub_type:'-';
                    if($tran->category == 1)
                        $array_detail['p_u_size']	= ($tran->property_unit_id)?$property_unit->property_size:'-';
                    $result[$index][$in_cate[$tran->category]]['details'][] 	= $array_detail;
                    break;
                case 3:
                    // Is discount
                    if(empty($result[$discount]['total']))
                        $result[$discount]['total']					= 0;
                    $result[$discount]['total'] 				+= $tran->total;
                    $result['total'][$discount] 				+= $tran->total;
                    $array_detail['no'] 						= $t_i->invoice_no;
                    $array_detail['no_label'] 						= $t_i->invoice_no_label;
                    $result[$discount]['details'][] 			= $array_detail;
                    break;
                case 4:
                    // Is vat income
                    if(empty($result['income'][$vat_discount]))
                        $result['income'][$vat_discount]['total']		= 0;
                    $result['income'][$vat_discount]['total'] 		+= $tran->total;
                    $result['total'][$income] 						+= $tran->total;
                    $array_detail['no'] 							= $t_i->invoice_no;
                    $array_detail['no_label'] 							= $t_i->invoice_no_label;
                    $result['income'][$vat_discount]['details'][] 	= $array_detail;
                    break;
            }
        }

        foreach ($result['income'] as &$value){
            $detail = $value['details'];

            usort($detail, function($a, $b) {
                return $a['no_label'] <=> $b['no_label'];
            });

            $value['details'] = $detail;
        }
        return $result;
    }

	function calBringForward ($date_bw) {
		$invoice = new Invoice;
		$total_income = $total_expense = 0;
		$invoice->whereBetween('payment_date', $date_bw)->where('property_id',Auth::user()->property_id)
		->where( function ($q) {
			$q->orWhere( function ($r){
				$r->where('type',1)->where('payment_status',2);
			});
			$q->orWhere( function ($r){
				$r->where('type',2)->where('payment_status',1);
			});
		})->chunk(100,
			function($invoice) use (&$total_income,&$total_expense) {
				foreach ($invoice as $slip) {
					if($slip->type == 1)
						$total_income += $slip->grand_total;
					else $total_expense += $slip->grand_total;
				}
			}
		);
		return ['bring_forward'=>($total_income-$total_expense)];
	}

	function getBankBalance ($date_bw) {
		$banks = Bank::where('property_id',Auth::user()->property_id)->where('active',true)->get();
		if($banks) {
			$balance_result['bank_balance_result']['result'] = true;

			foreach ( $banks as $bank ) {
				$balance_result['bank_balance_result']['bank'][$bank->id]['balance'] = $bank->balance;

				$balance_result['bank_balance_result']['bank'][$bank->id]['get']  = BankTransaction::where('bank_id',$bank->id)
				->where('transfer_date','>=',$date_bw[0])
            	->where('transfer_date','<=',$date_bw[1])->sum('get');

				$balance_result['bank_balance_result']['bank'][$bank->id]['pay'] = BankTransaction::where('bank_id',$bank->id)
				->where('transfer_date','>=',$date_bw[0])
            	->where('transfer_date','<=',$date_bw[1])->sum('pay');
			}

		} else {
			$balance_result['bank_balance_result']['result'] = false;
		}
		return $balance_result;
	}

	function getCashOnHand ($date_bw) {
		$r = $this->getCashOnHandList($date_bw);
		$sum_cash = 0;
		foreach( $r as $b ) {
			if( $b->mixed_payment ) $sum_cash += $b->sub_from_balance;
			else $sum_cash += $b->final_grand_total;
		}

        return array('cash_on_hand' => array('count' => $r->count(),'balance' => $sum_cash));
	}

	function getBankTransaction ($date_bw) {
		$banks = Bank::where('property_id',Auth::user()->property_id)->where('active',true)->get();
		$result['bank_transaction'] = array();
		if($banks) {
			foreach ( $banks as $key => $bank ) {
				$result['bank_transaction']['bank'][$key] = $bank->toArray();
				$result['bank_transaction']['bank'][$key]['transactions'] = 
				BankTransaction::with(['getInvoice'])->where('bank_id',$bank->id)
				->where('transfer_date','>=',$date_bw[0])
            	->where('transfer_date','<=',$date_bw[1])
				->select(array('invoice_id','get','pay','transfer_date'))->orderBy('transfer_date','asc')->get()->toArray();
			}
		}

		return $result;
	}

	function getCashOnHandList ($date_bw) {
		return Invoice::
        /*with(['property_unit'=>function ($q) {
			$q->select('id','unit_number');
		}])-> */where('property_id','=',Auth::user()->property_id)->where('type', 1)->where('payment_status',2)->where( function ($q) {
			$q->orWhere('payment_type',1);
			$q->orWhere('payment_type',3);
			$q->orWhere( function ($q_) {
				$q_->where('mixed_payment',true)->where('cash_on_hand_transfered',false);
			});
		})
		->where('transfered_to_bank', false)
		->where('payment_date','>=',$date_bw[0])
        ->where('payment_date','<=',$date_bw[1])->select('property_unit_id','grand_total','final_grand_total','sub_from_balance','mixed_payment','name','payment_date','receipt_no','receipt_no_label')->orderBy('payment_date','asc')->orderBy('receipt_no','desc')->get();
	}

	function getCashOnHandListForReport ($date_bw) {
		$cashes = $this->getCashOnHandList ($date_bw);
		$cashOnHand = array('cash_on_hand' => array('total' => 0));
		foreach( $cashes as $key => $cash ) {
			if( $cash->mixed_payment ) {
				$cashOnHand['cash_on_hand']['total'] += $cash->sub_from_balance;
				$cashes[$key]->total = $cash->sub_from_balance;
			} else {
				$cashOnHand['cash_on_hand']['total'] += $cash->final_grand_total;
				$cashes[$key]->total = $cash->final_grand_total;
			}
		}
		$cashOnHand['cash_on_hand']['details'] = $cashes->toArray();

		// prevent error from cash on hand invoice from external user
		foreach ($cashOnHand['cash_on_hand']['details'] as $key => $detail) {
		    if($detail['property_unit_id']) {
                $cashOnHand['cash_on_hand']['details'][$key]['property_unit'] = PropertyUnit::select('unit_number')->find($detail['property_unit_id'])->toArray();
            } else {
                $cashOnHand['cash_on_hand']['details'][$key]['property_unit'] = null;
            }
        }
		return $cashOnHand;
	}

	function getDebtor ( ) {
		if(Request::ajax()) {
			$r = Request::all();
			$debtors = $this->debtorList();
			return view('feesbills.debtor-report')->with(compact('debtors','r'));
		}
	}

	function exportDebtor (){
		if(Request::isMethod('post')) {
			$r = Request::all();
			$debtors = $this->debtorList();
            $filename = trans('messages.Report.remain_debt_label');
            $property_name = Property::with('has_province')->find(Auth::user()->property_id);
			return view('feesbills.debtor-report-export')->with(compact('debtors','filename','property_name','r'));
			/*Excel::create('debtor_list', function($excel) use ($debtors) {
				$excel->sheet('debtor_list', function($sheet) use ($debtors){
					$sheet->setWidth(array(
						'A'     =>  15,
						'B'     =>  13,
						'C'     =>  40,
						'D'     =>  23,
						'E'     =>  20,
						'F'     =>  20
					));
					$sheet->loadView('feesbills.debtor-report-export')->with(compact('debtors'));
				})->export('xls');
			});*/
		}
	}

	function debtorList () {
        $r = Request::all();
		$unit = Request::get('unit_id');
		$debtors = PropertyUnit::with(['debtBill' => function ($q) use($r) {
			$q->where('type', 1)
				->where('payment_status',0)->orderBy('invoice_no','asc');

            if( !empty($r['from-date']) ) {
                $q->where('due_date','>=', $r['from-date']);
            }
            if( !empty($r['to-date']) ) {
                $q->where('due_date','<=', $r['to-date']);
            }

		}])->whereHas('debtBill', function ($q) use ($r) {
			$q->where('type', 1)->where('payment_status',0);

            if( !empty($r['from-date']) ) {
                $q->where('due_date','>=', $r['from-date']);
            }
            if( !empty($r['to-date']) ) {
                $q->where('due_date','<=', $r['to-date']);
            }

		})->where('property_id', Auth::user()->property_id);
		if($unit != '-') {
			$debtors = $debtors->where('id',$unit);
		}

		$debtors = $debtors->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->get();
        $now = time();
        foreach ($debtors as $debtor) {
            foreach ($debtor->debtBill as $bill) {
                $bill->is_overdue_invoice = false;
                $bill->age_level = 0;
                if( $now > strtotime($bill->due_date) ) {
                    $age_day = calOverdueDays($bill->due_date);
                    $bill->is_overdue_invoice = true;
                    if($age_day <= 30)
                        $bill->age_level = 1;
                    else if($age_day < 60) $bill->age_level = 2;
                    else if($age_day < 90) $bill->age_level = 3;
                    else if($age_day < 120) $bill->age_level = 4;
                    else $bill->age_level = 5;
                }
            }
        }
		return $debtors;
	}

    function invoiceReceipt () {
        if(Request::ajax()) {
            $r = Request::all();
            $invoices = $this->invoiceList();
            return view('feesbills.report.invoice-receipt-list')->with(compact('invoices','r'));
        }
    }

    function exportInvoiceReceipt (){
        if(Request::isMethod('post')) {
            $invoices = $this->invoiceList();
            $r = Request::all();
            $filename = trans('messages.Report.invoice_receipt_report');
            $property_name = Property::select('property_name_th','property_name_en')->find(Auth::user()->property_id);
            return view('feesbills.report.invoice-receipt-report-export')->with(compact('invoices','filename','r','property_name'));
        }
    }

    function invoiceList () {
        $bills = Invoice::with('invoiceLog','instalmentLog','receiptInvoiceInstalmentLog','createdBy')
            ->where('property_id','=',Auth::user()->property_id)
            ->where('is_retroactive_record',false)
            ->where('is_revenue_record',false)
            ->where('is_aggregate_receipt',false);
        $bills->where('type',1)->where( function ($q) {
            // get all invoice
            $q->orWhere( function ($r) {
                $r->where('payment_status','!=',2)->doesntHave('receiptInvoiceInstalmentLog');
            });
            // Canceled invoice that have instalment log
            $q->orWhere( function ($r) {
                $r->where('payment_status',3)->has('instalmentLog');
            });
            // get invoice from receipt that come from 1 invoice 1 receipt
            $q->orWhere( function ($r) {
                $r->where('payment_status',2)->doesntHave('receiptInvoiceInstalmentLog');
            });
            // Keep This!!!!!!!!!!//
           /* // get all invoice
            $q->orWhere( function ($r) {
                $r->where('payment_status','!=',2)->doesntHave('instalmentLog');
            });
            // get invoice from receipt that come from invoice instalment
            // for old version need to check from instalment log
            $q->orWhere( function ($r) {
                $r->where('payment_status',2)->has('instalmentLog');
            });
            // get invoice from receipt that come from 1 invoice 1 receipt
            $q->orWhere( function ($r) {
                $r->where('payment_status',2)->doesntHave('receiptInvoiceInstalmentLog');
            });*/

        });
        $invoice_no = Request::get('invoice-no');
        if($invoice_no != ""){
            $bills->where('invoice_no_label','like','%'.trim($invoice_no).'%');
        }

        if(!empty(Request::get('invoice-unit_id')) && (Request::get('invoice-unit_id') != "-")) {
            $bills->where('property_unit_id',Request::get('invoice-unit_id'));
        } elseif(Request::get('invoice-unit_id') != "-") {
            $bills->whereNull('property_unit_id');
        }

        if(!empty(Request::get('start-due-date'))) {
            $bills->where('due_date','>=',Request::get('start-due-date'));
        }

        if(!empty(Request::get('end-due-date'))) {
            $bills->where('due_date','<=',Request::get('end-due-date'));
        }

        if(!empty(Request::get('start-created-date'))) {
            $bills->where('created_at','>=',Request::get('start-created-date')." 00:00:00");
        }

        if(!empty(Request::get('end-created-date'))) {
            $bills->where('created_at','<=',Request::get('end-created-date')." 23:59:59");
        }

        if( Request::get('payment-method') == 1) {
            $bills->where('transfer_only', true);
        }

        if( Request::get('payer') == 1) {
            $bills->where('for_external_payer', true);
        }

        if(Request::get('status')) {
            $bills->where('payment_status',Request::get('status'));
        }

        if(Request::get('invoice_by')) {
            $bills->where('created_by',Request::get('invoice_by'));
        }

        $bills = $bills->where('type',1)->orderBy('created_at','desc')->orderBy('invoice_no_label','desc')->get();

        return $bills;
    }

    function ReceiptInvoice () {
        if(Request::ajax()) {
            $r = Request::all();
            $invoices = $this->receiptList();
            return view('feesbills.report.receipt-invoice-list')->with(compact('invoices','r'));
        }
    }

    function exportReceiptInvoice (){
        if(Request::isMethod('post')) {
            $invoices = $this->receiptList();
            $r = Request::all();
            $filename = trans('messages.Report.receipt_invoice_report');
            $property_name = Property::select('property_name_th','property_name_en')->find(Auth::user()->property_id);
            return view('feesbills.report.receipt-invoice-report-export')->with(compact('invoices','filename','r','property_name'));
        }
    }

    function receiptList () {
        $bills = Invoice::with('invoiceLog','instalmentLog','receiptInvoiceInstalmentLog','receiptInvoiceAggregate','receiptInvoiceAggregate.invoice','createdBy','approvedBy')->where('property_id','=',Auth::user()->property_id);
        $bills->whereIn('payment_status',[2,5])->where('type',1);

        $invoice_no = Request::get('receipt-no');
        if($invoice_no != ""){
            $bills->where('receipt_no_label','like','%'.trim($invoice_no).'%');
        }

        if(!empty(Request::get('receipt-unit_id')) && (Request::get('receipt-unit_id') != "-")) {
            $bills->where('property_unit_id',Request::get('receipt-unit_id'));
        } elseif(Request::get('receipt-unit_id') != "-") {
            $bills->whereNull('property_unit_id');
        }

        $date 	 = Request::get('start-payment-date');
        $l_date  = Request::get('end-payment-date');
        $date_bw = array($date, $l_date);

        $bills->where('payment_date','>=',$date_bw[0]);
        $bills->where('payment_date','<=',$date_bw[1]);

        if(Request::get('start-deposit-date'))
            $bills->where('bank_transfer_date','>=',Request::get('start-deposit-date'));
        if(Request::get('end-deposit-date'))
            $bills->where('bank_transfer_date','<=',Request::get('end-deposit-date'));

        if( Request::get('payment-method') == 1) {
            $bills->where('transfer_only', true);
        }

        if( Request::get('payer') == 1) {
            $bills->where('for_external_payer', true);
        }

        if( Request::get('start-created-date') ) {
            $bills->where('updated_at','>=', Request::get('start-created-date')." 00:00:00");
        }

        if( Request::get('end-created-date') ) {
            $bills->where('updated_at','<=', Request::get('end-created-date')." 23:59:59");
        }

        if(Request::get('status')) {
            $bills->where('payment_status',Request::get('status'));
        }

        if(Request::get('receipt_by')) {
            $bills->where('approved_by',Request::get('receipt_by'));
        }

        $bills = $bills->where('type',1)->orderBy('payment_date','desc')->orderBy('receipt_no_label','desc')->get();
        return $bills;
    }

    public function exportResultCashFlow ($trans) {

        $income 	= "income";
        $expense 	= 'expense';
        $discount 	= 'discount';
        $vat_discount 	= trans('messages.feesBills.vat_discount');
        $vat_expense 	= trans('messages.feesBills.vat_expense');
        $w_tax 		= trans('messages.feesBills.withholding_tax');
        $result 	= array('total'=> array( $income => 0, $expense => 0, $discount => 0, 'cash_on_hand' => 0) );
        $in_cate 	= unserialize(constant('INVOICE_INCOME_CATE_'.strtoupper(App::getLocale())));
        $ex_cate 	= unserialize(constant('INVOICE_EXPENSE_CATE_'.strtoupper(App::getLocale())));
        $bank_name 	= unserialize(constant('BANK_LIST_'.strtoupper(App::getLocale())));

        foreach ($trans as $tran) {

            $t_i = Invoice::select('expense_no','expense_no_label', 'receipt_no','receipt_no_label')->find($tran->invoice_id);
            $array_detail = array(
                'detail'		=> $tran->detail,
                'value' 		=> $tran->total,
                'date'			=> $tran->payment_date,
                'type'			=> $tran->transaction_type,
                'bank_transfer_date' => $tran->bank_transfer_date
            );

            if($tran->property_unit_id) {
                $property_unit = PropertyUnit::find($tran->property_unit_id);
            }

            switch ($tran->transaction_type) {
                case 1:
                    // Is income
                    //if($tran->bank_transfer_date) {
                    $index = 'income';
                    $t_total = ($tran->total - floatval($tran->sub_from_balance));
                    $result['total'][$income] += $t_total;
                    //}
                    if(empty($result[$index][$in_cate[$tran->category]]))
                        $result[$index][$in_cate[$tran->category]]['total'] 		= 0;
                    $result[$index][$in_cate[$tran->category]]['total'] 		+= $t_total;
                    $array_detail['value'] 										= $tran->total;
                    $array_detail['sub_from_balance'] 							= $tran->sub_from_balance;
                    $array_detail['sub_from_discount'] 							= $tran->sub_from_discount;
                    $array_detail['total'] 										= $t_total;
                    $array_detail['no'] 										= $t_i->receipt_no;
                    $array_detail['no_label'] 									= $t_i->receipt_no_label;
                    $array_detail['p_u_no']	= ($tran->property_unit_id)?$property_unit->unit_number:'-';
                    if($tran->category == 1)
                        $array_detail['p_u_size']	= ($tran->property_unit_id)?$property_unit->property_size:'-';
                    $result[$index][$in_cate[$tran->category]]['details'][] 	= $array_detail;
                    break;
                case 2:
                    // Is expense
                    if(empty($result['expense'][$ex_cate[$tran->category]]))
                        $result['expense'][$ex_cate[$tran->category]]['total'] 		= 0;
                    $total = $tran->total - ($tran->w_tax * $tran->total/100) + ($tran->vat * $tran->total/100);
                    $result['expense'][$ex_cate[$tran->category]]['total'] 		+= $total;
                    $result['total'][$expense] 									+= $total;
                    $array_detail['no'] 										= $t_i->expense_no;
                    $array_detail['no_label'] 									= $t_i->expense_no_label;
                    $array_detail['total'] = $array_detail['value']             = $total;
                    $result['expense'][$ex_cate[$tran->category]]['details'][] 	= $array_detail;
                    break;
                case 3:
                    // Is discount
                    if(empty($result[$discount]['total']))
                        $result[$discount]['total']					= 0;
                    $result[$discount]['total'] 				+= $tran->total;
                    $result['total'][$discount] 				+= $tran->total;
                    $array_detail['no'] 						= $t_i->receipt_no;
                    $array_detail['no_label'] 						= $t_i->receipt_no_label;
                    $result[$discount]['details'][] 			= $array_detail;
                    break;
                case 4:
                    // Is vat income
                    if(empty($result['income'][$vat_discount]))
                        $result['income'][$vat_discount]['total']		= 0;
                    $result['income'][$vat_discount]['total'] 		+= $tran->total;
                    $result['total'][$income] 						+= $tran->total;
                    $array_detail['no'] 							= $t_i->receipt_no;
                    $array_detail['no_label'] 							= $t_i->receipt_no_label;
                    $result['income'][$vat_discount]['details'][] 	= $array_detail;
                    break;
                /*case 5:
                    // Is vat expense
                    if(empty($result['expense'][$vat_expense]))
                    $result['expense'][$vat_expense]['total'] 		= 0;
                    $result['expense'][$vat_expense]['total'] 		+= $tran->total;
                    $result['total'][$expense] 						+= $tran->total;
                    $array_detail['no'] 							= $t_i->expense_no;
                    $array_detail['no_label'] 							= $t_i->expense_no_label;
                    $result['expense'][$vat_expense]['details'][] 	= $array_detail;
                    break;
                default:
                    // Is withholding tax
                    if(empty($result['expense'][$w_tax]))
                    $result['expense'][$w_tax]['total'] 	= 0;
                    $result['expense'][$w_tax]['total'] 	+= $tran->total;
                    $result['total'][$expense] 				-= $tran->total;
                    $array_detail['no'] 					= $t_i->expense_no;
                    $array_detail['no_label'] 				= $t_i->expense_no_label;
                    $result['expense'][$w_tax]['details'][] = $array_detail;
                    break;*/
            }
        }
        return $result;
    }
}
