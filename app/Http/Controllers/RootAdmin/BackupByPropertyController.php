<?php namespace App\Http\Controllers\RootAdmin;
use Request;
use Auth;
use Redirect;
use Illuminate\Routing\Controller;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App;
use App\Http\Controllers\Officer\AccountController;
use File;
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
use App\PostReport;
# Complain
use App\Complain;

class BackupByPropertyController extends controller {

    public function __construct () {
		$this->middleware('auth',['except' => ['login']]);
		if( Auth::check() && Auth::user()->role !== 0 ) {
			Redirect::to('feed')->send();
		}
	}

	public function backupByProperty($property_id = null){
        $property_id = Request::get('id');
        $property = Property::find($property_id);

        // 01. Post Report Table
        DB::table('post_report')->where('property_id',$property_id)->orderBy('created_at')->chunk(1000, function ($post_reports) {
            /*foreach ($post_reports as $post_report_item){

            }*/
        });
        $contents = "COPY province (code, name_th, name_en, geo_id) FROM stdin;
10	กรุงเทพมหานคร   	Bangkok	2
11	สมุทรปราการ   	Samut Prakan	2
12	นนทบุรี   	Nonthaburi	2
13	ปทุมธานี   	Pathum Thani	2
14	พระนครศรีอยุธยา   	Phra Nakhon Si Ayutthaya	2
15	อ่างทอง   	Ang Thong	2
16	ลพบุรี   	Loburi	2
17	สิงห์บุรี   	Sing Buri	2
18	ชัยนาท   	Chai Nat	2
19	สระบุรี	Saraburi	2
20	ชลบุรี   	Chon Buri	5
21	ระยอง   	Rayong	5
22	จันทบุรี   	Chanthaburi	5
23	ตราด   	Trat	5
24	ฉะเชิงเทรา   	Chachoengsao	5
25	ปราจีนบุรี   	Prachin Buri	5
26	นครนายก   	Nakhon Nayok	2
27	สระแก้ว   	Sa Kaeo	5
30	นครราชสีมา   	Nakhon Ratchasima	3
31	บุรีรัมย์   	Buri Ram	3
32	สุรินทร์   	Surin	3
33	ศรีสะเกษ   	Si Sa Ket	3
34	อุบลราชธานี   	Ubon Ratchathani	3
35	ยโสธร   	Yasothon	3
36	ชัยภูมิ   	Chaiyaphum	3
37	อำนาจเจริญ   	Amnat Charoen	3
39	หนองบัวลำภู   	Nong Bua Lam Phu	3
40	ขอนแก่น   	Khon Kaen	3
41	อุดรธานี   	Udon Thani	3
42	เลย   	Loei	3
43	หนองคาย   	Nong Khai	3
44	มหาสารคาม   	Maha Sarakham	3
45	ร้อยเอ็ด   	Roi Et	3
46	กาฬสินธุ์   	Kalasin	3
47	สกลนคร   	Sakon Nakhon	3
48	นครพนม   	Nakhon Phanom	3
49	มุกดาหาร   	Mukdahan	3
50	เชียงใหม่   	Chiang Mai	1
51	ลำพูน   	Lamphun	1
52	ลำปาง   	Lampang	1
53	อุตรดิตถ์   	Uttaradit	1
54	แพร่   	Phrae	1
55	น่าน   	Nan	1
56	พะเยา   	Phayao	1
57	เชียงราย   	Chiang Rai	1
58	แม่ฮ่องสอน   	Mae Hong Son	1
60	นครสวรรค์   	Nakhon Sawan	2
61	อุทัยธานี   	Uthai Thani	2
62	กำแพงเพชร   	Kamphaeng Phet	2
63	ตาก   	Tak	4
64	สุโขทัย   	Sukhothai	2
65	พิษณุโลก   	Phitsanulok	2
66	พิจิตร   	Phichit	2
67	เพชรบูรณ์   	Phetchabun	2
70	ราชบุรี   	Ratchaburi	4
71	กาญจนบุรี   	Kanchanaburi	4
72	สุพรรณบุรี   	Suphan Buri	2
73	นครปฐม   	Nakhon Pathom	2
74	สมุทรสาคร   	Samut Sakhon	2
75	สมุทรสงคราม   	Samut Songkhram	2
76	เพชรบุรี   	Phetchaburi	4
77	ประจวบคีรีขันธ์   	Prachuap Khiri Khan	4
80	นครศรีธรรมราช   	Nakhon Si Thammarat	6
81	กระบี่   	Krabi	6
82	พังงา   	Phangnga	6
83	ภูเก็ต   	Phuket	6
84	สุราษฎร์ธานี   	Surat Thani	6
85	ระนอง   	Ranong	6
86	ชุมพร   	Chumphon	6
90	สงขลา   	Songkhla	6
91	สตูล   	Satun	6
92	ตรัง   	Trang	6
93	พัทลุง   	Phatthalung	6
94	ปัตตานี   	Pattani	6
95	ยะลา   	Yala	6
96	นราธิวาส   	Narathiwat	6
97	บึงกาฬ	Buogkan	3
\.";
        Storage::disk('local')->put('file.sql', $contents);
        return "true";
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
}
