<?php namespace App\Http\Controllers\RootAdmin;
use Request;
use Auth;
use Redirect;
use Illuminate\Routing\Controller;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App;
use App\Http\Controllers\Officer\AccountController;
# Model
use DB;
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\PropertyUnit;
use App\Property;
use App\Notification;
use App\Vehicle;
use App\User;
use App\InvoiceRevision;
use App\CommonFeesRef;
# Complain
use App\Complain;
use Carbon\Carbon;

class ClearController extends controller {

    public function __construct () {
		$this->middleware('auth',['except' => ['login']]);
		if( Auth::check() && Auth::user()->role !== 0 ) {
			Redirect::to('feed')->send();
		}
	}

    public function clearbills ($pid) {
        //dd('aaa');
        $bills = Invoice::with('invoiceFile')->where('property_id',$pid)->get();
		if($bills->count()) {
			foreach ($bills as $bill) {
                if(!$bill->invoiceFile->isEmpty()) {
    				foreach ($bill->invoiceFile as $file) {
    					$this->removeFile($file->name);
    				}
    				$bill->invoiceFile()->delete();
    			}
    			$bill->transaction()->delete();
    			//delete revision
    			$bill->invoiceRevision()->delete();
    			// reset vehicle bill
    			$vehicle = Vehicle::where('invoice_id',$bill->id)->first();
    			if(isset($vehicle)) {
    				$vehicle->sticker_status = 1;
    				$vehicle->invoice_id = null;
    				$vehicle->save();
    			}

    			// delete common fee ref. table
    			if( $bill->is_common_fee_bill )
    				$bill->commonFeesRef()->delete();

    			$bill->delete();
			}
            return redirect('root/admin/property/list');
		}
        echo "empty";
    }

    public function clearNotis ($pid) {
        $users = User::where('property_id',$pid)->get();
        if($users->count()) {
            foreach ($users as $user) {
                $notis = Notification::where('to_user_id',$user->id)->get();
                if($notis->count()) {
                    foreach ($notis as $noti) {
                        $noti->delete();
                    }
                }
            }
            return redirect('root/admin/property/list');
        }
        echo "empty";
    }

    public function clearComplains ($pid) {
		$complains = Complain::with('complainFile')->where('property_id',$pid)->get();
        if($complains->count()) {
            foreach ($complains as $complain) {
    			$complain->comments()->delete();
    			if(!$complain->complainFile->isEmpty()) {
    				foreach ($complain->complainFile as $file) {
    					$this->removeComplainFile($file->name);
    				}
    				$complain->complainFile()->delete();
    			}
    			$complain->delete();
            }
            return redirect('root/admin/property/list');
        }
        echo "empty";
	}

    public function removeFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'bills/'.$folder."/".$name;
		if(Storage::disk('s3')->has($file_path)) {
			Storage::disk('s3')->delete($file_path);
		}
	}

    public function removeComplainFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'complain-file'.'/'.$folder.'/'.$name;
        Storage::disk('s3')->delete($file_path);
	}

	public function clearDbTestMaster(){
		if(Request::isMethod('post')){
			//if(Request::route()->parameter('subdomain') == 'dev') {
				$property_id = Request::get('id');
				$property = Property::find($property_id);

				$account = new AccountController();

				if(isset($property)){
					
					if(Request::get('delete_announcement')) {
						$account->clearPostReport($property_id);
						$account->clearPost($property_id);
						$account->clearEvent($property_id);
						$account->clearVote($property_id);
					}

					if(Request::get('delete_complain')) {
						$account->clearComplain($property_id);
					}

					if(Request::get('delete_postparcel')) {
						$account->clearPostParcel($property_id);
						$property->post_parcel_counter = 0;
					}

					if(Request::get('delete_disscussion')) {
						$account->clearDiscussion($property_id);
						$account->clearMessage($property_id);
					}

					if(Request::get('delete_finace')) {
						$property->fund_balance = 0;
						$property->prepaid_slip_counter = 0;
						$property->invoice_counter = 0;
						$property->receipt_counter = 0;
						$property->expense_counter = 0;
						
						$property->withdrawal_slip_counter = 0;
						$property->petty_cash_balance = 0;

						$account->clearCommonFeeRef($property_id);
						$account->clearTransaction($property_id);
						$account->clearInvoice($property_id);
						$account->resetBankBalance($property_id);
						$account->resetPropertyUnitBalance($property_id);
						$account->clearPropertyPettyCash($property_id);
						$account->clearPropertyFund($property_id);
						$account->clearPropertyCashBox($property_id);
						$account->clearPropertyInvoiceCounter($property_id);
					}

					if(Request::get('delete_payee')) {
						$property->payee_counter = 0;
						$account->clearPayee($property_id);
					}

					if(Request::get('delete_vehicle')) {
						$account->clearVehicle($property_id);
					}

					$property->save();
					
					// Clear Notification
					$user_demo = User::where('property_id', $property_id)->get();
					foreach ($user_demo as $item) {
						$this->clearNotificationForTest($item->id);
						if(Request::get('delete_user')) {
							if($item->role != 1) {
								$item->delete();
							}
						}
					}
					$data = [
						'msg' => 'success'
					];
				}else{
					$data = [
						'msg' => 'fail'
					];
				}
			//}
			return $data;
		}else{
			return view('officer.clear-property-db-form');
		}
	}

	function clearNotificationForTest($user_id){
		$notification_list_to_user = Notification::where('to_user_id',$user_id)->get();
		foreach ($notification_list_to_user as $item_to){
			$item_to->delete();
		}

		$notification_list_from_user = Notification::where('from_user_id',$user_id)->get();
		foreach ($notification_list_from_user as $item_from){
			$item_from->delete();
		}

		return true;
	}

	function restoreInvoice () {

	    $bill = Invoice::with('commonFeesRef')->where('type',1)->where('payment_status',0)
            ->where('property_id','d2afffd1-c6a1-4564-afbb-45f93060c8c3')->where('invoice_no','>=',236)
            ->where('is_common_fee_bill',true)->get();
	    //dd($bill->toArray());
	    foreach ($bill as $iv) {
	        if(!$iv->commonFeesRef) {
                $b_p_unit = PropertyUnit::find($iv->property_unit_id);
                $crf = new CommonFeesRef;
                $crf->invoice_id				= $iv->id;
                $crf->property_id				= $iv->property_id;
                $crf->property_unit_id 			= $b_p_unit->id;
                $crf->property_unit_unique_id 	= $b_p_unit->property_unit_unique_id;
                $crf->from_date					= date('Y-m-01 00:00:00',strtotime($iv->due_date));
                $crf->to_date 					= date('Y-m-t 00:00:00' ,strtotime($iv->due_date));
                $crf->payment_status			= false;
                $crf->range_type 				= 1;
                $crf->created_at = $crf->updated_at = $iv->created_at;
                $crf->timestamps                = false;
                $crf->save();
            } else {
                $iv->commonFeesRef->property_unit_id = $iv->property_unit_id;
                $iv->commonFeesRef->timestamps = false;
                $iv->commonFeesRef->save();
                //$iv->commonFeesRef->delete();
            }
        }
    }

    // สำหรับ update invoice_no_label ให้เหมือนกับ invoice_no ที่เกิดเนื่องจากbugของ generate invoice_no_label function
    function forceUpdateRunningNumber20171206(){
        $property_id = '1518183e-8e96-463d-83f3-bd8c876df1e1'; // หมู่บ้านเนนทีคอนโด
        //$invoice = Invoice::where('property_id',$property_id)->whereNotNull('invoice_no')->whereNull('invoice_no_label')->get();
        $invoice = Invoice::where('property_id',$property_id)->whereNotNull('invoice_no')->get();
        foreach ($invoice as $item){
            $invoice_edit = Invoice::find($item->id);
            $running = $invoice_edit->invoice_no;
            $invoice_edit->invoice_no_label = $custom_label = NB_INVOICE.str_pad($running, 8, '0', STR_PAD_LEFT);
            $invoice_edit->save();
        }
        return "true";
    }

    // script แก้ไข ให้ add invoice_no_label สำหรับอันที่ไม่มี ของหมู่บ้านเนเบอร์โฮม
    function forceUpdateRunningNumberNabourHome20171206(){
        $property_id = 'ff4055ef-b800-43fb-8f63-a0087fa9ec04'; // หมู่บ้านเนเบอร์โฮม
        $invoice = Invoice::where('property_id',$property_id)->whereNotNull('invoice_no')->whereNull('invoice_no_label')->orderBy('invoice_no', 'asc')->get();
        $counter = 1575;
        foreach ($invoice as $item){
            $counter++;
            $invoice_edit = Invoice::find($item->id);
            //$running = $invoice_edit->invoice_no;
            $invoice_edit->invoice_no_label = $custom_label = "NBH.IV"."60"."11".str_pad($counter, 5, '0', STR_PAD_LEFT);
            $invoice_edit->save();
        }
        return "true";
    }

    function forceEditCreatedAtNabourHome20171206(){
        $property_id = 'ff4055ef-b800-43fb-8f63-a0087fa9ec04'; // หมู่บ้านเนเบอร์โฮม
        $invoice = Invoice::where('property_id',$property_id)->where('created_at','>=','2017-12-06')->get();

        foreach ($invoice as $item){
            $invoice_edit = Invoice::find($item->id);
            //Carbon::createFromFormat('Y-m-d H:i:s', $user->expire_trial);
            $create_date = Carbon::parse($invoice_edit->created_at);
            $date = $create_date->format('Y-m-d');
            $time = $create_date->format('H:i:s');
            $new_datetime = Carbon::createFromFormat('Y-m-d H:i:s', '2017-11-21'." ".$time)->toDateTimeString();

            $invoice_edit->created_at = $new_datetime;
            $invoice_edit->save();
        }

        return "true";

    }

    function forceEditDuedateForProperty(){
        $property_id = '1cb20a42-0580-4379-b4a6-b26df71ac2ad'; // คอนโดดรีม นครสวรรค์
        $invoice = Invoice::where('property_id',$property_id)->where('due_date','=','2018-03-09')->get();
        $due_date = '2018-04-09';
        foreach ($invoice as $item){
            // Invoice Table
            $invoice_edit = Invoice::find($item->id);
            $invoice_edit->due_date = $due_date;
            $invoice_edit->save();

            // Transaction Table
            $transaction = Transaction::where('invoice_id',$item->id)->get();
            if(isset($transaction)){
                foreach ($transaction as $item_transaction){
                    $transaction_edit = Transaction::find($item_transaction->id);
                    $transaction_edit->due_date = $due_date;
                    $transaction_edit->save();
                }
            }

        }

        return "true";

    }
}
