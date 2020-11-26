<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DirectPushNotificationController;

use App\MonthlyCounterDoc;
use App\YearlyCounterDoc;
use App\TotalCounterDoc;
use Carbon\Carbon;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use File;

use App\UserPropertyFeature;
use App\User;
use App\Notification;
use App\Property;
use App\Transaction;

class GeneralFeesBillsController extends Controller
{
    function getMonthlyCounterDoc($date_period,$property_id){
        $date_period_arr = str_split($date_period,4);
        $date_period_format = $date_period_arr[0].str_pad($date_period_arr[1], 2, '0', STR_PAD_LEFT);

        // Monthly counter
        $monthly_counter_doc_count = MonthlyCounterDoc::where('property_id',$property_id)->where('date_period',$date_period_format)->count();
        if($monthly_counter_doc_count > 0){
            //$monthly_counter_doc = MonthlyCounterDoc::where('property_id',$property_id)->where('date_period',$date_period_format)->get();
        }else{
            $new_monthly_counter = new MonthlyCounterDoc();
            $new_monthly_counter->property_id = $property_id;
            $new_monthly_counter->date_period = $date_period_format;
            $new_monthly_counter->save();
        }

        // Yearly counter
        $yearly_counter_doc_count = YearlyCounterDoc::where('property_id',$property_id)->where('year_period',$date_period_arr[0])->count();
        if($yearly_counter_doc_count > 0){
            ///$yearly_counter_doc = YearlyCounterDoc::where('property_id',$property_id)->where('date_period',$date_period_format)->get();
        }else{
            $new_yearly_counter = new YearlyCounterDoc();
            $new_yearly_counter->property_id = $property_id;
            $new_yearly_counter->year_period = $date_period_arr[0];
            $new_yearly_counter->save();
        }

        // Total counter
        $total_counter_doc_count = TotalCounterDoc::where('property_id',$property_id)->count();
        if($total_counter_doc_count > 0){
            //$total_counter_doc = TotalCounterDoc::where('property_id',$property_id)->where('date_period',$date_period_format)->get();
        }else{
            $new_total_counter = new TotalCounterDoc();
            $new_total_counter->property_id = $property_id;
            $new_total_counter->save();
        }

        return true;
    }

	function increaseMonthlyCounterDocByPeriod($date_period, $type,$property_id){
        $date_period_arr = str_split($date_period,4);

        // Monthly Increase
        $date_period_format_monthly = $date_period_arr[0].str_pad($date_period_arr[1], 2, '0', STR_PAD_LEFT);
	    $monthly_counter = MonthlyCounterDoc::where('property_id',$property_id)->where('date_period',$date_period_format_monthly)->first();

	    if(isset($monthly_counter)){
            $monthly_counter_update = MonthlyCounterDoc::find($monthly_counter->id);

            if($type == 'INVOICE'){
                $monthly_counter_update->invoice_counter = $monthly_counter->invoice_counter + 1;
            }
            if($type == 'RECEIPT'){
                $monthly_counter_update->receipt_counter = $monthly_counter->receipt_counter + 1;
            }
            if($type == 'EXPENSE'){
                $monthly_counter_update->expense_counter = $monthly_counter->expense_counter + 1;
            }
            if($type == 'PAYEE'){
                $monthly_counter_update->payee_counter = $monthly_counter->payee_counter + 1;
            }
            if($type == 'WITHDRAWAL'){
                $monthly_counter_update->withdrawal_counter = $monthly_counter->withdrawal_counter + 1;
            }
            if($type == 'PREPAID'){
                $monthly_counter_update->prepaid_counter = $monthly_counter->prepaid_counter + 1;
            }

            $monthly_counter_update->save();
        }

        // Yearly Increase
        $date_period_format_yearly = $date_period_arr[0];
        $yearly_counter = YearlyCounterDoc::where('property_id',$property_id)->where('year_period',$date_period_format_yearly)->first();

        if(isset($yearly_counter)){
            $yearly_counter_update = YearlyCounterDoc::find($yearly_counter->id);

            if($type == 'INVOICE'){
                $yearly_counter_update->invoice_counter = $yearly_counter->invoice_counter + 1;
            }
            if($type == 'RECEIPT'){
                $yearly_counter_update->receipt_counter = $yearly_counter->receipt_counter + 1;
            }
            if($type == 'EXPENSE'){
                $yearly_counter_update->expense_counter = $yearly_counter->expense_counter + 1;
            }
            if($type == 'PAYEE'){
                $yearly_counter_update->payee_counter = $yearly_counter->payee_counter + 1;
            }
            if($type == 'WITHDRAWAL'){
                $yearly_counter_update->withdrawal_counter = $yearly_counter->withdrawal_counter + 1;
            }
            if($type == 'PREPAID'){
                $yearly_counter_update->prepaid_counter = $yearly_counter->prepaid_counter + 1;
            }

            $yearly_counter_update->save();
        }

        // Total Increase
        $total_counter = TotalCounterDoc::where('property_id',$property_id)->first();

        if(isset($total_counter)){
            $total_counter_update = TotalCounterDoc::find($total_counter->id);

            if($type == 'INVOICE'){
                $total_counter_update->invoice_counter = $total_counter->invoice_counter + 1;
            }
            if($type == 'RECEIPT'){
                $total_counter_update->receipt_counter = $total_counter->receipt_counter + 1;
            }
            if($type == 'EXPENSE'){
                $total_counter_update->expense_counter = $total_counter->expense_counter + 1;
            }
            if($type == 'PAYEE'){
                $total_counter_update->payee_counter = $total_counter->payee_counter + 1;
            }
            if($type == 'WITHDRAWAL'){
                $total_counter_update->withdrawal_counter = $total_counter->withdrawal_counter + 1;
            }
            if($type == 'PREPAID'){
                $total_counter_update->prepaid_counter = $total_counter->prepaid_counter + 1;
            }

            $total_counter_update->save();
        }

        return true;
    }

    function generateRunningLabel($type,$bill=null,$running_bill=null,$property_id){
        $property = Property::find($property_id);
        if($property) {
            // Generate running number
            if($property->document_format_setting != null) {
                $prefix_arr_count = $property->document_format_setting->count();
            }else{
                $prefix_arr_count = 0;
            }

            if($bill != null) {
                $date_period = $bill->created_at->year . str_pad($bill->created_at->month, 2, '0', STR_PAD_LEFT);
                $year_full = $bill->created_at->year;
                $month = $bill->created_at->month;
            }else{
                $date_period = Carbon::now()->year . str_pad(Carbon::now()->month, 2, '0', STR_PAD_LEFT);
                $year_full = Carbon::now()->year;
                $month = Carbon::now()->month;
            }

            //if ($property_id == '1518183e-8e96-463d-83f3-bd8c876df1e1'){ // ###### HardCode ######
            if ($prefix_arr_count > 0){
                $doc_format_setting = $property->document_format_setting->type;
                $prefix_arr = $property->document_prefix_setting->toArray();
                $new_arr = [];
                foreach ($prefix_arr as $item){
                    $new_arr[$item['document_type']] = ['prefix'=>$item['prefix'],
                        'running_digit'=>$item['running_digit'],
                        'is_ce'=>$item['is_ce'],
                        'year_digit'=>$item['year_digit']
                    ];
                }

                if($doc_format_setting == 2) {
                    // Type 2 : ปีเดือน ตามด้วย running number
                    $monthly_counter_doc = MonthlyCounterDoc::where('property_id', $property_id)->where('date_period', $date_period)->first();
                    $counter = 0;
                    if ($type == 'INVOICE') {
                        $counter = $monthly_counter_doc->invoice_counter;
                    }
                    if ($type == 'RECEIPT') {
                        $counter = $monthly_counter_doc->receipt_counter;
                    }
                    if ($type == 'EXPENSE') {
                        $counter = $monthly_counter_doc->expense_counter;
                    }
                    if ($type == 'PAYEE') {
                        $counter = $monthly_counter_doc->payee_counter;
                    }
                    if ($type == 'WITHDRAWAL') {
                        $counter = $monthly_counter_doc->withdrawal_counter;
                    }
                    if ($type == 'PREPAID') {
                        $counter = $monthly_counter_doc->prepaid_counter;
                    }

                    if (!$new_arr[$type]['is_ce']) {
                        $year_format = $year_full + 543;
                    } else {
                        $year_format = $year_full;
                    }

                    $year_format = str_split($year_format,2);
                    if($new_arr[$type]['year_digit'] == 2){
                        $year = $year_format[1];
                    }else{
                        $year = $year_format[0].$year_format[1];
                    }

                    $running_no = str_pad($counter + 1, $new_arr[$type]['running_digit'], '0', STR_PAD_LEFT);
                    $custom_label = $new_arr[$type]['prefix'].$year.str_pad($month, 2, '0', STR_PAD_LEFT).$running_no;
                }elseif($doc_format_setting == 1){
                    // Type 3 : ปี ตามด้วย running number // ยังไม่ได้ทำ
                    $yearly_counter_doc = YearlyCounterDoc::where('property_id', $property_id)->where('year_period', $year_full)->first();
                    $counter = 0;
                    if ($type == 'INVOICE') {
                        $counter = $yearly_counter_doc->invoice_counter;
                    }
                    if ($type == 'RECEIPT') {
                        $counter = $yearly_counter_doc->receipt_counter;
                    }
                    if ($type == 'EXPENSE') {
                        $counter = $yearly_counter_doc->expense_counter;
                    }
                    if ($type == 'PAYEE') {
                        $counter = $yearly_counter_doc->payee_counter;
                    }
                    if ($type == 'WITHDRAWAL') {
                        $counter = $yearly_counter_doc->withdrawal_counter;
                    }
                    if ($type == 'PREPAID') {
                        $counter = $yearly_counter_doc->prepaid_counter;
                    }

                    if (!$new_arr[$type]['is_ce']) {
                        $year_format = $year_full + 543;
                    } else {
                        $year_format = $year_full;
                    }

                    $year_format = str_split($year_format,2);
                    if($new_arr[$type]['year_digit'] == 2){
                        $year = $year_format[1];
                    }else{
                        $year = $year_format[0].$year_format[1];
                    }

                    $running_no = str_pad($counter + 1, $new_arr[$type]['running_digit'], '0', STR_PAD_LEFT);
                    $custom_label = $new_arr[$type]['prefix'].$year.$running_no;
                }else{
                    // Type 1 : running number โดยใช้ default ของ Nabour
                    // 'INVOICE','RECEIPT','EXPENSE','PREPAID','WITHDRAWAL','PAYEE'
                    // ใช้ Total_counter_running
                    $counter = 0;
                    if ($type == 'INVOICE') {
                        $counter = $property->invoice_counter + 1;
                    }
                    if ($type == 'RECEIPT') {
                        $counter = $property->receipt_counter + 1;
                    }
                    if ($type == 'EXPENSE') {
                        $counter = $property->expense_counter + 1;
                    }
                    if ($type == 'PAYEE') {
                        $counter = $property->payee_counter + 1;
                    }
                    if ($type == 'WITHDRAWAL') {
                        $counter = $property->withdrawal_slip_counter + 1;
                    }
                    if ($type == 'PREPAID') {
                        $counter = $property->prepaid_slip_counter + 1;
                    }

                    if($running_bill != null){
                        $counter = $running_bill;
                    }

                    if(count($new_arr) > 0) { ///////
                        $custom_label = $new_arr[$type]['prefix'].str_pad($counter, $new_arr[$type]['running_digit'], '0', STR_PAD_LEFT);
                    }else{
                        $custom_label = NB_INVOICE . str_pad($counter, 8, '0', STR_PAD_LEFT);
                    }
                }
                $var_check ="";

            }else{
                $counter = 0;
                $custom_label = "";
                if ($type == 'INVOICE') {
                    $counter = $property->invoice_counter + 1;
                    if($running_bill != null){
                        $counter = $running_bill;
                    }
                    $custom_label = NB_INVOICE.str_pad($counter, 8, '0', STR_PAD_LEFT);
                }
                if ($type == 'RECEIPT') {
                    $counter = $property->receipt_counter + 1;
                    if($running_bill != null){
                        $counter = $running_bill;
                    }
                    $custom_label = NB_RECEIPT.str_pad($counter, 8, '0', STR_PAD_LEFT);
                }
                if ($type == 'EXPENSE') {
                    $counter = $property->expense_counter + 1;
                    if($running_bill != null){
                        $counter = $running_bill;
                    }
                    $custom_label = NB_EXPENSE.str_pad($counter, 8, '0', STR_PAD_LEFT);
                }
                if ($type == 'PAYEE') {
                    $counter = $property->payee_counter + 1;
                    if($running_bill != null){
                        $counter = $running_bill;
                    }
                    $custom_label = NB_PAYEE.str_pad($counter, 8, '0', STR_PAD_LEFT);
                }
                if ($type == 'WITHDRAWAL') {
                    $counter = $property->withdrawal_slip_counter + 1;
                    if($running_bill != null){
                        $counter = $running_bill;
                    }
                    $custom_label = NB_WITHDRAWAL.str_pad($counter, 8, '0', STR_PAD_LEFT);
                }
                if ($type == 'PREPAID') {
                    $counter = $property->prepaid_slip_counter + 1;
                    if($running_bill != null){
                        $counter = $running_bill;
                    }
                    $custom_label = NB_PREPAID.str_pad($counter, 8, '0', STR_PAD_LEFT);
                }
            }

            return $custom_label;
        } else {
            return "";
        }
        
    }

    
    function decreaseMonthlyCounterDoc($date_period, $type, $property_id){
        $date_period_arr = str_split($date_period,4);
        $date_period_format = $date_period_arr[0].str_pad($date_period_arr[1], 2, '0', STR_PAD_LEFT);
        
        $monthly_counter = MonthlyCounterDoc::where('property_id',$property_id)->where('date_period',$date_period_format)->first();

        if(isset($monthly_counter)){
            $monthly_counter_update = MonthlyCounterDoc::find($monthly_counter->id);

            if($type == 'INVOICE'){
                $monthly_counter_update->invoice_counter = $monthly_counter->invoice_counter - 1;
            }
            if($type == 'RECEIPT'){
                $monthly_counter_update->receipt_counter = $monthly_counter->receipt_counter - 1;
            }
            if($type == 'EXPENSE'){
                $monthly_counter_update->expense_counter = $monthly_counter->expense_counter - 1;
            }
            if($type == 'PAYEE'){
                $monthly_counter_update->payee_counter = $monthly_counter->payee_counter - 1;
            }
            if($type == 'WITHDRAWAL'){
                $monthly_counter_update->withdrawal_counter = $monthly_counter->withdrawal_counter - 1;
            }
            if($type == 'PREPAID'){
                $monthly_counter_update->prepaid_counter = $monthly_counter->prepaid_counter - 1;
            }

            $monthly_counter_update->save();
        }

        return true;
    }

    function decreaseMonthlyCounterDocById($id, $type){
        $monthly_counter_update = MonthlyCounterDoc::find($id);
        if($type == 'INVOICE'){
            $monthly_counter_update->invoice_counter = $monthly_counter_update->invoice_counter - 1;
        }
        if($type == 'RECEIPT'){
            $monthly_counter_update->receipt_counter = $monthly_counter_update->receipt_counter - 1;
        }
        if($type == 'EXPENSE'){
            $monthly_counter_update->expense_counter = $monthly_counter_update->expense_counter - 1;
        }
        if($type == 'PAYEE'){
            $monthly_counter_update->payee_counter = $monthly_counter_update->payee_counter - 1;
        }
        if($type == 'WITHDRAWAL'){
            $monthly_counter_update->withdrawal_counter = $monthly_counter_update->withdrawal_counter - 1;
        }
        if($type == 'PREPAID'){
            $monthly_counter_update->prepaid_counter = $monthly_counter_update->prepaid_counter - 1;
        }

        $monthly_counter_update->save();

        return true;
    }

    public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'bills/'.$folder;
        $directories = Storage::disk('s3')->directories('bills'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
    }
    
    public function sendTransactionCompleteNotification ($property_id, $unit_id, $title, $subject_id, $from_user_id) {
		$title = json_encode( ['type' => 'transaction_complete','title' => $title] );
		$user_property_feature = UserPropertyFeature::where('property_id',$property_id)->first();

		if($unit_id != null) {
            $users = User::where('property_unit_id', $unit_id)->whereNull('verification_code')->get();
            if($user_property_feature){
	            if ($user_property_feature->menu_finance_group == true) {
	                foreach ($users as $user) {
	                    $notification = Notification::create([
	                        'title' => $title,
	                        'notification_type' => '12',
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

    public function removeFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'bills/'.$folder."/".$name;
		if(Storage::disk('s3')->has($file_path)) {
			Storage::disk('s3')->delete($file_path);
		}
    }
    
    function changeStartCfMonthLabel ($bill) {
		$bill->load('commonFeesRef', 'property_unit');
		$new_cf_name = $this->setCfDetail ($bill->commonFeesRef->range_type, strtotime($bill->commonFeesRef->from_date), $bill->commonFeesRef->to_date, $bill->property_unit->contact_lang);
		$bill->name = $new_cf_name;
		$t_cf = Transaction::where('invoice_id',$bill->id)->where('category',1)->first();
		$t_cf->detail = $new_cf_name;
		$bill->save();
		$t_cf->save();
		return $bill;
    }
    
    public function setCfDetail ($mo,$time_start, $last_month_to_instalment,$lang = 'th') {
		$_from_y		= date('Y',$time_start);
		$_from_m		= date('m',$time_start);
		$to_date 		= $last_month_to_instalment;
		$tcf_name 		= trans('messages.feesBills.cf_generate_head', [], "", $lang)." ".trans('messages.dateMonth.'.$_from_m, [], "", $lang);

		if($mo != 1) {
			$_to_m = date('m',strtotime($to_date));
			$_to_y = date('Y',strtotime($to_date));
			if($_from_y != $_to_y) {
				$tcf_name  .= " ".localYear($_from_y, $lang);
			}
			$tcf_name  .= " - ".trans('messages.dateMonth.'.$_to_m, [], "", $lang). " ".localYear($_to_y, $lang);
		} else {
			$tcf_name .= " ".localYear($_from_y, $lang);
		}
		return $tcf_name;
    }
    
    public function getPropertyCode ($property) {
		if( !$property->property_code ) {
			$max = Property::max('property_code');
			if( !$max ) $max = 0;
			$property->property_code = ++$max;
			$property->timestamps = false;
			$property->save();
		}
		return $property->property_code;
	}

	public function getInvoiceNo ($bill) {
		if( !$bill->invoice_no ) {
			$property = $bill->property;
			$bill->invoice_no = ++$property->invoice_counter;
			$bill->timestamps = false;
			$bill->save();
			$property->timestamps = false;
			$property->save();
		}
		return $bill->invoice_no;
	}
}
