<?php namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use Vinkla\Pusher\Facades\Pusher;
use App\Http\Controllers\GeneralFeesBillsController;
# Model
use App\PaymentNotificationLog;

use Auth;
use File;
use App\Invoice;
use App\Property;
use App\Vehicle;
use App\BankTransaction;
use App\Bank;
use App\User;
use App\Transaction;
use App\InvoiceInstalmentLog;
use App\PropertyUnit;
use App\CommonFeesRef;
use App\SmartBill;
use App\Notification;
use App\UserPropertyFeature;
use Carbon\Carbon;
use DB;
use App\Http\Controllers\DirectPushNotificationController;
class BblConnectController extends GeneralFeesBillsController {

    public function __construct () {

    }

    public function paymentNotification (Request $request) {

        // Save log
        DB::table('system_notification_log')->insert(
            [
                'data'          => json_encode($request->all()), 
                'module'        => 'bbl',
                'created_at'    => date('Y-m-d H:i:s')
            ]
        );

        $username = $request->getUser();
        $password = $request->getPassword();

        $transmit_date_time = $_SERVER['HTTP_TRANSMIT_DATE_TIME'];
        $request_ref = $_SERVER['HTTP_REQUEST_REF'];
        if($username == 'oneroofBBL' && $password == '1RoofBBL@test'){

            //find existed
            $noti = PaymentNotificationLog::where('reference1',strtolower($request->get('reference1')))->get();
            if( $noti ) {
                foreach( $noti as $n) {
                    $n->delete();
                }
            }

            $payment_notification_log = new PaymentNotificationLog;
            $payment_notification_log->fill($request->all());
            $payment_notification_log->reference1 = strtolower($payment_notification_log->reference1);
            $payment_notification_log->url 	= 'ONEROOF1';
            $payment_notification_log->request_ref 	= $request_ref;
            $payment_notification_log->transmit_date_time 	= $transmit_date_time;
            $payment_notification_log->save();

            $this->updateInvoice ($payment_notification_log);

            return response()->json(['responseCode'=>'000','responseMesg'=>'success'])
                ->header('Request-Ref', $request_ref)
                ->header('Transmit-Date-Time',$transmit_date_time);
        }else{
            return response()->json(['responseCode'=>'500','message' => 'Unauthorized'], 401)
                ->header('Request-Ref', $request_ref)
                ->header('Transmit-Date-Time',$transmit_date_time);
        }
    }

    public function paymentNotification2 (Request $request) {
        // Save log
        DB::table('system_notification_log')->insert(
            [
                'data'          => json_encode($request->all()), 
                'module'        => 'bbl',
                'created_at'    => date('Y-m-d H:i:s')
            ]
        );
        $username = $request->getUser();
        $password = $request->getPassword();

        $transmit_date_time = $_SERVER['HTTP_TRANSMIT_DATE_TIME'];
        $request_ref = $_SERVER['HTTP_REQUEST_REF'];

        if($username == 'oneroofBBL' && $password == '1RoofBBL@test'){
            //find existed
            $noti = PaymentNotificationLog::where('reference1',strtolower($request->get('reference1')))->get();
            if( $noti ) {
                foreach( $noti as $n) {
                    $n->delete();
                }
            }

            $payment_notification_log = new PaymentNotificationLog;
            $payment_notification_log->fill($request->all());
            $payment_notification_log->reference1 = strtolower($payment_notification_log->reference1);
            $payment_notification_log->url 	= 'ONEROOF2';
            $payment_notification_log->request_ref 	= $request_ref;
            $payment_notification_log->transmit_date_time 	= $transmit_date_time;
            $payment_notification_log->save();

            $this->updateInvoice ($payment_notification_log);

            return response()->json(['responseCode'=>'000','responseMesg'=>'success'])
                ->header('Request-Ref', $request_ref)
                ->header('Transmit-Date-Time',$transmit_date_time);
        }else{
            return response()->json(['responseCode'=>'500','message' => 'Unauthorized'], 401)
                ->header('Request-Ref', $request_ref)
                ->header('Transmit-Date-Time',$transmit_date_time);
        }
    }

    function updateInvoice ($noti_log) {
        
        $bill = Invoice::with('transaction','instalmentLog')->where('smart_bill_ref_code',strtolower($noti_log->reference1))->where('payment_status',0)->first();
        if( $bill ) {
            $bill->payment_status 	= 1;
            if(!$bill->submit_date) {
                $bill->submit_date = date('Y-m-d h:i:s');
            }
            $bill->save();
            $this->sendAdminInvoiceNotification($bill->id,$bill->invoice_no_label, $bill->property_unit->unit_number,$bill->property_id,$bill->property_unit_id);
        }

        return true;
    }

    public function sendAdminInvoiceNotification ($invoice_id,$invoice_no, $unit_no,$property_id,$property_unit_id) {
		$title = json_encode( ['invoice_no' => $invoice_no,'unit_no' => $unit_no] );
		$admin_users = User::where('property_id', $property_id)->where( function ($q) {
		    $q->where( 'role', 1 )->orWhere(function ($q_) {
		        $q_->where( 'role', 3 )->whereHas('position', function ($query) {
                    $query->where('menu_finance_group', true);
                });
            });
        })->get();
        $sender = User::where('role',2)->where('property_unit_id',$property_unit_id)->first();
        if( $sender && $admin_users) {
            foreach ($admin_users as $user) {
                $notification = Notification::create([
                    'title'				=> $title,
                    'notification_type' => '13',
                    'from_user_id'		=> $sender->id,
                    'to_user_id'		=> $user->id,
                    'subject_key'		=> $invoice_id
                ]);
    
                $textNoti = $this->convertTitleTolongTxt($notification);
    
                $dataPusher = [
                    'title'			=> $textNoti,
                    'notification'  => $notification
                ];
                Pusher::trigger( $property_id."_".$user->id, 'notification_event', $dataPusher);
            }

            $this->NotifyPayementToUser($invoice_id, $property_id, $property_unit_id,$invoice_no,$user->id);
        }
	}

    function convertTitleTolongTxt($notification){
        $data = json_decode($notification->title,true);
        return $notification->sender->name." ".trans('messages.Notification.invoice_paid',['in_no'=> $data['invoice_no'],'unit_no'=> $data['unit_no']]);
    }

    function NotifyPayementToUser ($subject_id, $property_id, $property_unit_id, $invoice_no, $from_user_id) {
        $title = json_encode( ['type' => 'payment_notification','invoice_no' => $invoice_no] );
		$user_property_feature = UserPropertyFeature::where('property_id',$property_id)->first();

		if($property_unit_id != null) {
            $users = User::where('property_unit_id', $property_unit_id)->whereNull('verification_code')->get();
            if($user_property_feature){
	            if ($user_property_feature->menu_finance_group == true) {
	                foreach ($users as $user) {
	                    $notification = Notification::create([
	                        'title' => $title,
	                        'notification_type' => '3',
	                        'from_user_id' => $from_user_id,
	                        'to_user_id' => $user->id,
	                        'subject_key' => $subject_id
	                    ]);
	                    $controller_push_noti = new DirectPushNotificationController();
	                    $controller_push_noti->pushNotification($notification->id);
	                }
	            }
	        }
        }
    }
    
}
