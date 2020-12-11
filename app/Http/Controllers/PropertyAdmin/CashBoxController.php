<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use App;
use Auth;
use Redirect;
use DB;
use File;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
# Model
use App\Property;
use App\Invoice;
use App\Bank;
use App\BankTransaction;
use App\PropertyUnit;
use App\CashBoxDepositeLog;
use App\CashBoxDepositeLogFile;

class CashBoxController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_cash_on_hand');
		view()->share('active_menu', 'bill');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function cashList (Request $form) {
		$bills = Invoice::where('property_id','=',Auth::user()->property_id)->where('payment_status',2)->where( function ($q) {
			$q->orWhere('payment_type',1);
			$q->orWhere('payment_type',3);
			$q->orWhere( function ($q_) {
				$q_->where('mixed_payment',true)->where('cash_on_hand_transfered',false);
			});
			
		})->where('transfered_to_bank', false);
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
				$bills->where('payment_date','>=',$form::get('start-due-date'));
			}

			if(!empty($form::get('end-due-date'))) {
				$bills->where('payment_date','<=',$form::get('end-due-date'));
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
		$sum =  0;
		$bills = $bills->where('type',1)->orderBy('receipt_no','desc')->get();

		foreach( $bills as $bill) {
			if( $bill->mixed_payment ) $sum += $bill->sub_from_balance;
			else $sum += $bill->final_grand_total;
		}

		if(!$form::ajax()) {
			$bank = new Bank;
		    $bank_list = $bank->getBankList();
			$unit_list = array('-'=> trans('messages.unit_no'));
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
			$deposit_log = CashBoxDepositeLog::with('depositLogFile','bank')->where('property_id',Auth::user()->property_id)->orderBy('created_at','desc')->get();
			return view('cash_box.cash-list')->with(compact('bills','unit_list','sum','bank_list','deposit_log'));
		} else {
			return view('cash_box.cash-list-element')->with(compact('bills','sum'));
		}

	}

	public function deposit () {
		//dd(Request::all());
        if(Request::isMethod('post')) {
            $sum_balance = 0;
            foreach (Request::get('bills') as $bill_id) {
                $bill = Invoice::with('transaction')->find($bill_id);

                if ($bill->mixed_payment) { // cash then bank transfer
                    $bill->cash_to_bank_transfered_date = Request::get('transfer_date');
                    $bill->cash_on_hand_transfered = true;
                    $balance_to_add = $bill->sub_from_balance;
                } else {
                    $bill->bank_transfer_date = Request::get('transfer_date');
                    $bill->transfered_to_bank = true;
                    $detail_update = array('bank_transfer_date' => Request::get('transfer_date'));
                    $bill->transaction()->update($detail_update);
                    $balance_to_add = $bill->final_grand_total;
                }
                $bill->timestamps = false;
                $bill->save();


                //unset property unit id to prevent misunderstanding
                $bill->property_unit_id = null;
                $bt = new BankTransaction;
                $bt->saveBankBillTransaction($bill, Request::get('bank_id'));
                $bank = new Bank;
                $bank->updateBalance(Request::get('bank_id'), $balance_to_add);
                $sum_balance += $balance_to_add;
            }

            $log = new CashBoxDepositeLog;
            $log->receipt_json_id   = json_encode(Request::get('bills'));
            $log->property_id       = Auth::user()->property_id;
            $log->bank_id           = Request::get('bank_id');
            $log->amount            = $sum_balance;
            $log->deposit_date      = Request::get('transfer_date');
            $log->created_by        = Auth::user()->id;
            $log->save();

            if (!empty(Request::get('attachment'))) {
                $deposit_img = [];
                foreach (Request::get('attachment') as $img) {
                    //Move Image
                    $path = $this->createLoadBalanceDir($img['name']);
                    $deposit_img[] = new CashBoxDepositeLogFile([
                        'name' => $img['name'],
                        'url' => $path,
                        'file_type' => $img['mime'],
                        'is_image' => $img['isImage'],
                        'original_name' => $img['originalName']
                    ]);
                }
                $log->depositLogFile()->saveMany($deposit_img);
            }
        }
        return redirect('admin/cash-box/cash-list');
	}

    public function createLoadBalanceDir ($name) {
        $targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
        $folder = substr($name, 0,2);
        $pic_folder = 'cash-box/'.$folder;
        $directories = Storage::disk('s3')->directories('cash-box'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
        return $folder."/";
    }
}