<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use DB;
use App;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
#use App\Http\Controllers\PushNotificationController;
# Model
use App\PropertyUnit;
use App\Property;
use App\Notification;
use App\PropertyUnitBalanceLog;
use App\PropertyUnitPrepaid;
use App\PrepaidFile;
use App\BankTransaction;
use App\Bank;
use App\Transaction;

class PropertyUnitPrepaidController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_prepaid');
		view()->share('active_menu', 'bill');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function prepaidList (Request $form) {
		$pp_slips = PropertyUnitPrepaid::with('property_unit')->where('property_id','=',Auth::user()->property_id);
		if($form::isMethod('post')) {

			if(!empty($form::get('invoice-no')) && intval($form::get('invoice-no')) != 0) {
				$pp_slips->where('pe_slip_no',intval($form::get('invoice-no')));
			}

			if(!empty($form::get('property_unit_id')) && ($form::get('property_unit_id') != "-")) {
				$pp_slips->where('property_unit_id',$form::get('property_unit_id'));
			}

			if(!empty($form::get('start-pay-date'))) {
				$pp_slips->where('created_at','>=',$form::get('start-pay-date'));
			}

			if(!empty($form::get('end-pay-date'))) {
				$pp_slips->where('created_at','<=',$form::get('end-pay-date'));
			}
		}
		$pp_slips = $pp_slips->orderBy('pe_slip_no','desc')->paginate(50);
		if(!$form::ajax()) {
			$bank = new Bank;
		    $bank_list = $bank->getBankList();
			$unit_list = array('-'=> trans('messages.unit_no'));
			$unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
			return view('prepaid.admin-prepaid-list')->with(compact('pp_slips','unit_list','bank_list'));
		} else {
			return view('prepaid.admin-prepaid-list-element')->with(compact('pp_slips'));
		}
	}

	function savePrepaid () {

		if(Request::isMethod('post')) {
			$property 	= Property::find(Auth::user()->property_id);
			$pp_slip = new PropertyUnitPrepaid;
			$pp_slip->fill(Request::all());
			$pp_slip->property_id 		= Auth::user()->property_id;
			$pp_slip->pe_slip_no 		= ++$property->prepaid_slip_counter;
			$pp_slip->amount 			= str_replace(',', '', Request::get('amount'));
			$pp_slip->depositary		= Auth::user()->id;
			$pp_slip->property_unit_id 	= Request::get('property_unit');

			if($pp_slip->payment_type == 1) 
			$pp_slip->payment_date 		= date('Y-m-d H:i:s');

			$pp_slip->save();
			// Save Counter
			$property->save();
			$log = new PropertyUnitBalanceLog;
			$log->balance 			= $pp_slip->amount;
			$log->property_id 		= Auth::user()->property_id;
			$log->property_unit_id 	= Request::get('property_unit');
			$log->prepaid_id		= $pp_slip->id;
			$log->save();

			$property_unit = PropertyUnit::find( Request::get('property_unit') );
			$property_unit->balance += $pp_slip->amount;
			$property_unit->save();

			// Save attachments
			if(!empty(Request::get('attachment'))) {
				$attach = [];
				foreach (Request::get('attachment') as $key => $file) {
					//Move Image
					$path = $this->createLoadBalanceDir($file['name']);
					$attach[] = new PrepaidFile([
							'name' => $file['name'],
							'url' => $path,
							'file_type' => $file['mime'],
							'is_image'	=> $file['isImage'],
							'original_name'	=> $file['originalName']
					]);
				}
				$pp_slip->prepaidFile()->saveMany($attach);
			}

			// Save Bank transfer transaction
			if($pp_slip->payment_type == 2) {
				$bt = new BankTransaction;
				$bt->saveBankPrepaidTransaction($pp_slip,Request::get('bank_id'));
				$bank = new Bank;
				$bank->updateBalance (Request::get('bank_id'),$pp_slip->amount);
			}

			return redirect('admin/prepaid');
		}
	}

	function viewPrepaid ($id) {
        $pp_slip = PropertyUnitPrepaid::with('property', 'property_unit', 'prepaidFile')->find($id);
		return view('prepaid.prepaid-view')->with(compact('pp_slip'));
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'prepaid/'.$folder;
        $directories = Storage::disk('s3')->directories('prepaid'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function removeFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'prepaid/'.$folder."/".$name;
		if(Storage::disk('s3')->has($file_path)) {
			Storage::disk('s3')->delete($file_path);
		}
	}

	/***
	** Called by feesBills controller after change invoice status to paid
	** params: 	amount 	as $amount
				bill 	as $bill
	** return: status
	****/
	function saveBillBalance ($amount,$bill) {
        $amount = round($amount,3);
		$property_unit 	= PropertyUnit::find( $bill->property_unit_id );
		$log 			= new PropertyUnitBalanceLog;
		
		if($bill->is_common_fee_bill) { // only common fee bill

			$name = trans('messages.feesBills.paid_over_common_fee');
			$log->cf_balance 			= $amount;
			$property_unit->cf_balance 	+= $amount;

			$log->property_id 		= $bill->property_id;
			$log->property_unit_id 	= $bill->property_unit_id;
			$log->invoice_id		= $bill->id;
			$log->save();
			$property_unit->save();
		} else {
			$name = trans('messages.feesBills.paid_over_other');
			$property_unit->balance 	+= $amount;
			$property_unit->save();
		}
		$bill->total 				+= ($amount - $bill->discount);
		$bill->final_grand_total 	= $bill->total - $bill->sub_from_balance;
		$bill->grand_total 			= $bill->final_grand_total ;
		$bill->save();

		// save paid over transaction
		$trans = new Transaction;
		$trans->price 				= $amount;
		$trans->quantity 			= 1;
		$trans->total 				= $amount;
		$trans->invoice_id 			= $bill->id;
		$trans->detail 				= $name; 
		$trans->transaction_type 	= 1;
		$trans->property_id 		= $bill->property_id;
		$trans->property_unit_id 	= $bill->property_unit_id;
		$trans->category 			= 19;
		$trans->due_date			= $bill->due_date;
		$trans->payment_date		= $bill->payment_date;
		$trans->submit_date			= $bill->submit_date;
		$trans->payment_status	 	= true;
		$trans->bank_transfer_date	= $bill->bank_transfer_date;
		$trans->save();
		return $bill;
	}

    function saveBillShortPaid ($amount,$bill,$cf_transaction,$save_balance_flag = true)
    {
        $amount = round($amount,3);
        $property_unit = PropertyUnit::find($bill->property_unit_id);
        $log = new PropertyUnitBalanceLog;

        if ($bill->is_common_fee_bill) { // only common fee bill

            $log->cf_balance        = $amount;
            $log->property_id       = $bill->property_id;
            $log->property_unit_id  = $bill->property_unit_id;
            $log->invoice_id        = $bill->id;
            $log->p_unit_balance_added        = $save_balance_flag;
            $log->save();
            if($save_balance_flag) {
                $property_unit->cf_balance += $amount;
                $property_unit->save();
            }
        }

        $bill->total 				+= ($amount - $bill->discount);
        $bill->final_grand_total 	= $bill->total - $bill->sub_from_balance;
        $bill->grand_total 			= $bill->final_grand_total ;

        if($cf_transaction) {
            $cf_transaction->total      += $amount;
            $cf_transaction->price      =  $cf_transaction->total;
            $cf_transaction->quantity   =  1;
            $cf_transaction->save();
        }

        return $bill;
    }
}