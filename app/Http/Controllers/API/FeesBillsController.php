<?php namespace App\Http\Controllers\API;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use Vinkla\Pusher\Facades\Pusher;
use App\Http\Controllers\GeneralFeesBillsController;
# Model
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\Bank;
use App\CommonFeesRef;
use App\Property;
use App\PropertyUnit;
use Auth;
use File;
use DB;
use App\User;
use App\Notification;
use App\PropertySettings;

class FeesBillsController extends GeneralFeesBillsController {

    public function __construct () {
        //$this->middleware('jwt.feature_menu:menu_finance_group');
    }

    /*
     * payment_status
     *   0 = waiting
     *   1 = paid/submit
     *   2 = success/confirm
     * */

    public function invoiceList () {

        $menu = Auth::user()->user_property_menu()->get();
        if($menu) {
            $is_allow = $menu->first()->menu_finance_group;
        }else{
            $is_allow = false;
        }

        $bills = Invoice::where('property_unit_id','=',Auth::user()->property_unit_id);

        $bills->whereNotIn('payment_status', ['2','3','4']); // Not show payment_status 2, 3, 4

        $resultBill = $bills->orderBy('due_date','asc')->get();

        $results_arr = $resultBill->toArray();

        if($is_allow) {
            $results = [
                'invoice' => $results_arr
            ];
        }else{
            $results = [
                'invoice' => []
            ];
        }

        return response()->json($results);
    }

    public function invoiceOverDue(){
        $bills = Invoice::where('property_unit_id','=',Auth::user()->property_unit_id);
        $dateNow = date('d M Y');
        $bills->where('due_date','<=', $dateNow)->whereNotIn('payment_status', ['2','3','4']);

        $resultBill = $bills->orderBy('invoice_no_label','desc')->get();

        $results_arr = $resultBill->toArray();

        $results = [
            'invoice' => $results_arr
        ];

        return response()->json($results);
    }

    public function receiptHistoryAll(){

        $menu = Auth::user()->user_property_menu()->get();
        if($menu) {
            $is_allow = $menu->first()->menu_finance_group;
        }else{
            $is_allow = false;
        }

        $bills = Invoice::where('property_unit_id','=',Auth::user()->property_unit_id);
        $bills->where('payment_status', '=', '2');
        $resultBill = $bills->orderBy('receipt_no_label','desc')->orderBy('receipt_no','desc')->get();

        $results_arr = $resultBill->toArray();

        if($is_allow) {
            $results = [
                'bills' => $results_arr
            ];
        }else{
            $results = [
                'bills' => []
            ];
        }

        return response()->json($results);
    }

    public function receiptHistoryByMonthTotal(){
        $yearTemp = date('Y');
        if(Request::get('page') != null){
            $numYear = Request::get('page') - 1;
            $yearTemp -=$numYear;
        }

        $firstDate = date('d M Y', strtotime('first day of January '.$yearTemp ));
        $lastDate = date('d M Y', strtotime('last day of December '.$yearTemp ));

        $bills = Invoice::where('property_unit_id','=',Auth::user()->property_unit_id);

        $bills->where('due_date','<=', $lastDate)
            ->where('due_date', '>=', $firstDate)
            ->where('payment_status', '=', '2');

        $users = DB::table('invoice')->where('property_unit_id','=',Auth::user()->property_unit_id)
                ->where('due_date','<=', $lastDate)
                ->where('due_date', '>=', $firstDate)
                ->where('payment_status', '=', '2')
                //->select(DB::raw('month(monthstamp) as month'))
                ->select(DB::raw('SUM(grand_total) as total_sales'))
            //->groupBy('department')
            //->havingRaw('SUM(price) > 2500')
            ->get();

        $resultBill = $bills->orderBy('invoice_no','desc')->get();

        $results_arr = $resultBill->toArray();

        $results = [
            'bills' => $results_arr
        ];

        return response()->json($results);
    }

    function getTotalInMonth($year)
    {
        for ($i = 1; $i <= 12; $i++) {

            $monthFull = date("F", mktime(0, 0, 0, $i, 10));
            $firstDate = date('d M Y', strtotime('first day of' . $monthFull . ' ' . $year));
            $lastDate = date('d M Y', strtotime('last day of' . $monthFull . ' ' . $year));

            //$firstDate = date('d M Y', strtotime('first day of'.$month. ' ' .$year));
            //$lastDate = date('d M Y', strtotime('last day of January '.$year ));

            $users = DB::table('invoice')->where('property_unit_id', '=', Auth::user()->property_unit_id)
                ->where('due_date', '<=', $lastDate)
                ->where('due_date', '>=', $firstDate)
                ->where('payment_status', '=', '2')
                //->select(DB::raw('month(monthstamp) as month'))
                ->select(DB::raw('SUM(grand_total) as total_sales'))
                //->groupBy('department')
                //->havingRaw('SUM(price) > 2500')
                ->get();
        }
    }

    public function receiptHistoryByMonth(){
        $bills = Invoice::where('property_unit_id','=',Auth::user()->property_unit_id);
        $bills->where('payment_status', '=', '2');
        $resultBill = $bills->orderBy('invoice_no','desc')->paginate(15);

        $results_arr = $resultBill->toArray();

        $results = [
            'bills' => $results_arr
        ];

        return response()->json($results);
    }

    public function receiptList (Request $form) { // Test
        $bills = Invoice::where('property_unit_id','=',Auth::user()->property_unit_id);
        if($form::isMethod('post')) {
            if(!empty($form::get('invoice-type')) && $form::get('invoice-type') != '-') {
                $bills->where('type',$form::get('invoice-type'));
            }

            if(!empty($form::get('start-due-date'))) {
                $bills->where('due_date','>=',$form::get('start-due-date'));
            }

            if(!empty($form::get('end-due-date'))) {
                $bills->where('due_date','<=',$form::get('end-due-date'));
            }
        }
        $bills = $bills->where('payment_status',2)->get();
        return view('feesbills.user-receipt-list')->with(compact('bills','form'));
    }

    public function viewbill ($id) {

        $bill_check_type = Invoice::find($id);
        if(isset($bill_check_type->property_unit_id)) {
            $bill = Invoice::with( array('instalmentLog' => function($q) {
                return $q->orderBy('created_at', 'ASC');
            }, 'invoiceFile', 'transaction' => function ($query) { $query->orderBy('transaction_type','asc'); }))->find($id);
        }else{
            $bill = Invoice::with(['transaction' => function ($query) { $query->orderBy('transaction_type','asc'); }, 'invoiceFile'])->find($id);
        }
        
        if($bill->invoice_read_status != true) {
            $bill->invoice_read_status = 1;
            $bill->timestamps = false;
            $bill->save();
        }

        

        $results = $bill->toArray();

        //Property Setting
        $p_setting = PropertySettings::where('property_id',$bill->property_id)->first();
        if( $p_setting ) {
            if( $p_setting->qr_code ) {
                $p_setting->qr_code = env('URL_S3')."/property-file/".$p_setting->qr_code;
            }
            $results['property_settings'] = $p_setting->toArray();
        } else {
            $results['property_settings'] = null;
        }

        //Smart bill payment
        $smart_bill_menu = \App\PropertyFeature::where('property_id',$bill->property_id)->first();
        if( $smart_bill_menu && $smart_bill_menu->smart_bill_payment) {
            $smart_bill_feature = DB::table('smart_bill_payment_setting')->where('property_id',$bill->property_id)->where('activated',true)->first();
        } else {
            $smart_bill_feature = false;
        }
        
        if( $smart_bill_feature ) {
            // check ref code
            if(!$bill->smart_bill_ref_code) {
                $property_code = $this->getPropertyCode($bill->property);
				$invoice_no = $this->getInvoiceNo ($bill);
                $bill->smart_bill_ref_code = generateQrRefCode($property_code,$invoice_no);
                $bill->timestamps = false;
                $bill->save();
            }
            $results['smart_bill_payment']['enabled'] = true;
            $results['smart_bill_payment']['qr_string'] = getQrString((array) $smart_bill_feature, $bill->final_grand_total,$bill->smart_bill_ref_code, $bill->property_unit->unit_number, $bill->due_date); 
            $results['smart_bill_payment']['merchant_name_th'] = $smart_bill_feature->merchant_name_th; 
            $results['smart_bill_payment']['merchant_name_en'] = $smart_bill_feature->merchant_name_en; 
        } else {
            $results['smart_bill_payment']['enabled'] = false; 
        }

        return response()->json($results);
    }

    public function payBill () {
        try {
            if (Request::isMethod('post')) {
                $bill = Invoice::with('transaction','property_unit')->find(Request::get('invoice_id'));
                $bill->payment_status = 1;
                //$bill->payment_type = Request::get('payment_type'); // Disable by Suttipong J.
                $bill->payment_type = 0;
                $bill->submit_date = date('Y-m-d h:i:s');
                $bill->payer_name 	= Auth::user()->name;
                $bill->save();
                foreach ($bill->transaction as $tr) {
                    $tr->submit_date	= date('Y-m-d h:i:s');
                    $tr->save();
                }
                $attach = [];

                /* New Function */
                if (count(Request::file('attachment'))) {
                    foreach (Request::file('attachment') as $key => $file) {
                        $name = md5($file->getFilename());//getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        $targetName = $name . "." . $extension;

                        $path = $this->createLoadBalanceDir($file);

                        $isImage = 0;
                        if (in_array($extension, ['jpeg', 'jpg', 'gif', 'png'])) {
                            $isImage = 1;
                        }

                        $attach[] = new InvoiceFile([
                            'name' => $targetName,
                            'url' => $path,
                            'file_type' => $file->getClientMimeType(),
                            'is_image' => $isImage,
                            'original_name' => $file->getClientOriginalName()
                        ]);
                    }
                    $bill->save();
                    $bill->invoiceFile()->saveMany($attach);
                }
                //$this->sendAdminInvoiceNotification($bill->id,$bill->invoice_no, $bill->property_unit->unit_number); // comment for change to invoice_no_label
                $this->sendAdminInvoiceNotification($bill->id,$bill->invoice_no_label, $bill->property_unit->unit_number);
                return response()->json(['success' => 'true']);
            }
        } catch(Exception $ex){
            return response()->json(['success' =>'false']);
        }
    }

    public function createLoadBalanceDir ($imageFile) {
        $name =  md5($imageFile->getFilename());//getClientOriginalName();
        $extension = $imageFile->getClientOriginalExtension();
        $targetName = $name.".".$extension;

        $folder = substr($name, 0,2);

        $pic_folder = 'bills'.DIRECTORY_SEPARATOR.$folder;
        $directories = Storage::disk('s3')->directories('bills'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }

        $full_path_upload = $pic_folder.DIRECTORY_SEPARATOR.$targetName;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($imageFile), 'public');// public set in photo upload
        if($upload){
            // Success
        }

        return $folder."/";
    }

    public function getAttach ($id) {
        $file = InvoiceFile::find($id);
        $folder = str_replace('/', DIRECTORY_SEPARATOR, $file->url);
        $file_path = 'bills'.DIRECTORY_SEPARATOR.$folder.$file->name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            $response = response(Storage::disk('s3')->get($file_path), 200, [
                'Content-Type' => $file->file_type,
                'Content-Length' => Storage::disk('s3')->size($file_path),
                'Content-Description' => 'File Transfer',
                'Content-Disposition' => "attachment; filename={$file->original_name}",
                'Content-Transfer-Encoding' => 'binary',
            ]);

            ob_end_clean();

            return $response;
        }
    }

    public function getBankData(){
        $banks = Bank::where('property_id',Auth::user()->property_id)->where('is_fund_account',false)->where('active',true)->get();
        return response()->json($banks);
    }

    public function invoicePropertyList () {

        $bills = Invoice::where('property_id','=',Auth::user()->property_id);

        $bills->where('payment_status', '=', '0');

        $resultBill = $bills->orderBy('invoice_no','desc')->get();

        $results_arr = $resultBill->toArray();

        $results = [
            'invoice' => $results_arr
        ];

        return response()->json($results);
    }

    public function receiptPropertyList () {

        $bills = Invoice::where('property_id','=',Auth::user()->property_id);

        $bills->where('payment_status', '=', '2');

        $resultBill = $bills->orderBy('invoice_no','desc')->get();

        $results_arr = $resultBill->toArray();

        $results = [
            'invoice' => $results_arr
        ];

        return response()->json($results);
    }

    public function transactionPropertyList () {

        $menu = Auth::user()->user_property_menu()->get();
        if($menu) {
            $is_allow = $menu->first()->menu_finance_group;
        }else{
            $is_allow = false;
        }

        $transaction = Transaction::where('property_id','=',Auth::user()->property_id)->get();

        $transaction_arr = $transaction->toArray();

        if($is_allow) {
            $results = [
                'invoice' => $transaction_arr
            ];
        }else{
            $results = [
                'invoice' => []
            ];
        }



        return response()->json($results);
    }

    public function CommonFeeReport () {
        $menu = Auth::user()->user_property_menu()->get();
        if($menu) {
            $is_allow = $menu->first()->menu_finance_group;
        }else{
            $is_allow = false;
        }

        $paid = array();
        $date 	= Request::get('year')."-".Request::get('month');
        $cfr_count 	= CommonFeesRef::where('from_date','<=', $date."-15")->where('to_date','>=', $date."-15")->where('property_id',Auth::user()->property_id)->count();
        if($cfr_count) {
            $cfr 	= CommonFeesRef::where('from_date','<=', $date."-15")->where('to_date','>=', $date."-15")->where('property_id',Auth::user()->property_id)->where('payment_status',true)->get();
            if(!empty($cfr)) {
                foreach ($cfr as $pay) {
                    $paid[] = $pay->property_unit_unique_id;
                }
            }
            $paid = array_unique($paid);
        }
        $date = strtotime($date."-01");
        $property = Property::find(Auth::user()->property_id);
        $property_unit = PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->get();

        $results_data = array();
        $counter_paid = 0;
        $counter_not_paid = 0;
        if($cfr_count){
            foreach($property_unit as $p){
                if(in_array($p->property_unit_unique_id,$paid)){
                    $counter_paid++;
                    $results_data[] = [
                        'unit_number' => $p->unit_number,
                        'payment_status' => true
                    ];
                }else{
                    $counter_not_paid++;
                    $results_data[] = [
                        'unit_number' => $p->unit_number,
                        'payment_status' => false
                    ];
                }
            }
        }

        if($is_allow) {
            $results = [
                'data' => $results_data,
                'paid_count' => $counter_paid,
                'not_paid_count' => $counter_not_paid
            ];
        }else{
            $results = [
                'data' => [],
                'paid_count' => 0,
                'not_paid_count' => 0
            ];
        }



        return response()->json($results);
    }

    public function sendAdminInvoiceNotification ($invoice_id,$invoice_no, $unit_no) {
		//$title = json_encode( ['invoice_no' => NB_INVOICE.invoiceNumber($invoice_no),'unit_no' => $unit_no] ); // comment for change to invoice_no_label
		$title = json_encode( ['invoice_no' => $invoice_no,'unit_no' => $unit_no] );
		$users = User::where('property_id',Auth::user()->property_id)->where( function ($q) {
		    $q->where( 'role', 1 )->orWhere(function ($q_) {
		        $q_->where( 'role', 3 )->whereHas('position', function ($query) {
                    $query->where('menu_finance_group', true);
                });
            });
        })->get();
		foreach ($users as $user) {
			$notification = Notification::create([
				'title'				=> $title,
				'notification_type' => '13',
				'from_user_id'		=> Auth::user()->id,
				'to_user_id'		=> $user->id,
				'subject_key'		=> $invoice_id
			]);

            $textNoti = $this->convertTitleTolongTxt($notification);

            $dataPusher = [
                'title'			=> $textNoti,
                'notification'  => $notification
            ];

            Pusher::trigger(Auth::user()->property_id."_".$user->id, 'notification_event', $dataPusher);
		}
	}

    function convertTitleTolongTxt($notification){
        $data = json_decode($notification->title,true);
        return $notification->sender->name." ".trans('messages.Notification.invoice_paid',['in_no'=> $data['invoice_no'],'unit_no'=> $data['unit_no']]);
    }
}
