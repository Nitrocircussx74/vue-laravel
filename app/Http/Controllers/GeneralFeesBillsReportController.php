<?php namespace App\Http\Controllers;
use Request;
use Illuminate\Routing\Controller;
use Auth;
use Redirect;
use App;
# Model
use App\Transaction;
use App\PropertyFund;

class GeneralFeesBillsReportController extends Controller {

	function getActiveTransaction ($date_bw) {
		$result = array();
		$trs = new Transaction;
		$trs->where( function ($q) use( $date_bw ) {
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
				})->where('property_id',Auth::user()->property_id)->where('payment_status',true)->where('is_rejected',false)->orderBy('category','asc')->orderBy('payment_date', 'asc')
				->chunk(1000, function ($trs) use (&$result){
					foreach($trs as $tr) {
						$result[] = $tr;
					}
				});
		return $result;
	}

    function getAllInvoiceActiveTransaction ($date_bw) {

        $result = array();
        $trs    = new Transaction;
        $trs    = $trs->where('property_id',Auth::user()->property_id)->where(function ($q) {
            $q  ->orWhere('is_rejected',false)
                ->orWhere(function ($q) {
                    $q->where('is_rejected',true)->whereHas('bill',function ($q) {
                        $q->where('payment_status',6);
                    });
                })
                ->orWhere(function ($q) {
                    $q->where('is_rejected',true);
                    $q->whereHas('bill',function ($q) {
                        $q->whereRaw('instalment_balance >= final_grand_total');
                    });
                });
        })
            ->whereHas('bill', function ($q)  {
                $q->where('is_retroactive_record',false);
                $q->where('is_revenue_record',false);
                $q->where('is_aggregate_receipt',false);
                $q->where('type',1);
                // is not receipt that created by system
                $q->whereNotNull('invoice_no_label');
            });
        //->whereRaw('created_at != updated_at');
        // is not added transaction by system (fine etc.)

        $invoice_no = Request::get('invoice-no');
        if($invoice_no != ""){
            $trs = $trs->whereHas('bill', function ($q) use ($invoice_no) {
                $q->where('invoice_no_label', 'like', '%' . trim($invoice_no) . '%');
            });
        }

        if( Request::get('start-created-date') ) {
            $trs = $trs->whereHas('bill', function ($q) {
                $q->where('created_at', '>=', Request::get('start-created-date')." 00:00:00");
            });
        }

        if( Request::get('end-created-date') ) {
            $trs = $trs->whereHas('bill', function ($q) {
                $q->where('created_at', '<=', Request::get('end-created-date')." 23:59:59");
            });
        }

        if(!empty(Request::get('invoice-unit_id')) && (Request::get('invoice-unit_id') != "-")) {
            $trs = $trs->where('property_unit_id',Request::get('invoice-unit_id'));
        } elseif(Request::get('invoice-unit_id') != "-") {
            $trs = $trs->whereNull('property_unit_id');
        }

        if(!empty($date_bw[0])) {
            $trs = $trs->where('due_date','>=',$date_bw[0]);
        }

        if(!empty($date_bw[1])) {
            $trs = $trs->where('due_date','<=',$date_bw[1]);
        }

        if( Request::get('payer') == 1) {
            $trs = $trs->where('for_external_payer', true);
        }

        $trs->chunk(1000, function ($trs) use (&$result){
            foreach($trs as $tr) {
                $result[] = $tr;
            }
        });
        return $result;
    }

	function fundReport ($date_bw) {

		$result['fund'] = array();
		$fund_list = PropertyFund::where('property_id',Auth::user()->property_id)->whereBetween('payment_date', $date_bw)->get();
		if( $fund_list ) {
			$result['fund']['result'] = 1;
			$result['fund']['get'] = $result['fund']['pay'] = 0;
			foreach ($fund_list as $fund) {
				$result['fund']['get'] += $fund->get;
				$result['fund']['pay'] += $fund->pay;
			}
		} else {
			$result['fund']['result'] = 0;
		}
		return $result;
	}

	public function returnReport ( $trans ) {
		$income 	= trans('messages.feesBills.income');
		$expense 	= trans('messages.feesBills.expense');
		$discount 	= trans('messages.feesBills.discount');
		$vat_discount 	= trans('messages.feesBills.vat_discount');
		$vat_expense 	= trans('messages.feesBills.vat_expense');
		$w_tax 		= trans('messages.feesBills.withholding_tax');
		$result 	= array( $discount => 0, 'discount' => 0, 'total'=> array( $income => 0, $expense => 0,'forward_balance' => 0) );
		$in_cate 	= unserialize(constant('INVOICE_INCOME_CATE_'.strtoupper(App::getLocale())));
		$ex_cate 	= unserialize(constant('INVOICE_EXPENSE_CATE_'.strtoupper(App::getLocale())));
		if(count($trans)) {
			$result['status'] = 1;
			foreach ($trans as $tran) {
				switch ($tran->transaction_type) {
					case 1:
						if(empty($result['income'][$in_cate[$tran->category]]))
						$result['income'][$in_cate[$tran->category]] = 0;
						$result['income'][$in_cate[$tran->category]] += $tran->total;
						$result['total'][$income] += $tran->total;
						if( $tran->category ==  13) $result['total']['forward_balance'] += $tran->total;
						if( $tran->category ==  1) {
                            $result['total'][$income] -= $tran->sub_from_balance;
                            $result['income'][$in_cate[1]] -= $tran->sub_from_balance;
						}
							
						break;
					case 2:
						if(empty($result['expense'][$ex_cate[$tran->category]])) 
						$result['expense'][$ex_cate[$tran->category]] = 0;
						//$total = $tran->total - ($tran->w_tax * $tran->total/100);
                        $total = $tran->total - ($tran->w_tax * $tran->total/100) + ($tran->vat * $tran->total/100);
						$result['expense'][$ex_cate[$tran->category]] += $total;
						$result['total'][$expense] += $total;
					break;
					case 3:
						$result[$discount] += $tran->total;
						break;
					case 4:
						if(empty($result['income'][$vat_discount]))
						$result['income'][$vat_discount] = 0;
						$result['income'][$vat_discount] += $tran->total;
						$result['total'][$income] += $tran->total;
						break;
					/*case 5:
						if(empty($result['expense'][$vat_expense]))
						$result['expense'][$vat_expense] = 0;
						$result['expense'][$vat_expense] += $tran->total;
						$result['total'][$expense] += $tran->total;
						break;
					default:
						if(empty($result['expense'][$w_tax]))
						$result['expense'][$w_tax] = 0;
						$result['expense'][$w_tax] += $tran->total;
						$result['total'][$expense] -= $tran->total;
						break;*/
				}
			}
			$result['expense']['total'] = $result['total'][$expense];
			$result['income']['total'] = $result['total'][$income];
			$result['discount'] = $result[$discount];
			$result['balances'] = $result['total'][$income] - $result['total'][$expense];
		} else {
			$result['status'] = 0;
		}
		return $result;
	}

	public function getMiniInfaReport () {
		$slm = date("Y-m-15", strtotime("last month"));
		$elm = date("Y-m-15");
		$date_bw = array($slm, $elm);
		$water = Transaction::where('transaction_type',2)->where('payment_status',1)->whereBetween('payment_date', $date_bw)->where('property_id',Auth::user()->property_id)->where('category',2)->get();
		$elect = Transaction::where('transaction_type',2)->where('payment_status',1)->whereBetween('payment_date', $date_bw)->where('property_id',Auth::user()->property_id)->where('category',7)->get();;
		$water_net = $elect_net = 0;
		$result = false;
		if($water->count()) {
			foreach($water as $wt) {
				$water_net += ($wt->total + ($wt->total * $wt->vat/100));
			}
			$result = true;
		}

		if($elect->count()) {
			foreach($elect as $et) {
				$elect_net += ($et->total + ($et->total * $et->vat/100));
			}
			$result = true;
		}

		return array('result' => $result, 'water' => number_format($water_net,2), 'elect' => number_format($elect_net,2));
	}
}