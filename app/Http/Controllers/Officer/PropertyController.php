<?php namespace App\Http\Controllers\Officer;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use Redirect;
use Mail;
use File;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use Carbon\Carbon;
# Model
use App\PropertyForm;
use App\Province;
use App\User;
use App\Property;
use App\PropertyUnit;
use App\SalePropertyDemo;
use App\Notification;
use App\PostReport;
use App\PostReportDetail;
use App\Post;
use App\Event;
use App\Vote;
use App\Discussion;
use App\Complain;
use App\Transaction;
use App\PostParcel;
use App\Message;
use App\Invoice;
use App\Payee;
use App\Vehicle;
use App\CommonFeesRef;
class PropertyController extends Controller {

    public function __construct () {
        $this->middleware('auth',['except' => ['login']]);
        if( Auth::check() && Auth::user()->role !== 4 ) {
            Redirect::to('officer/property-list')->send();
        }
    }

    public function index(){
        $list_property_data = SalePropertyDemo::with('property')->where('sale_id','=',Auth::user()->id)->get();

        foreach ($list_property_data as &$item){
            if($item->trial_expire != null) {
                $expire = Carbon::createFromFormat('Y-m-d H:i:s', $item->trial_expire);
                $datetime_now = Carbon::now();
                $result_cal_trial = $datetime_now->diffInDays($expire, false);
                $temp_name = $item->property->property_name_en;
                $item['property_name'] = $temp_name;

                if ($result_cal_trial >= 0) {
                    $item['isExpire'] = 0;
                } else {
                    $item['isExpire'] = 1;
                }
            }else{
                $temp_name = $item->property->property_name_en;
                $item['property_name'] = $temp_name;
                $item['isExpire'] = 0;
            }
        }

        $list_property = $this->subval_sort($list_property_data,'property_name');

        return view('property_sale_demo.form-list')->with(compact('list_property'));
    }

    public function view ($id) {
        $p = new Province;
        $provinces = $p->getProvince();

        $property_demo = SalePropertyDemo::find($id);
        $property_data = Property::find($property_demo->property->id);
        //$user = $property->property_admin;
        $property = $property_data->toArray();
        //$demo_data = $property_demo->toArray();
        $property['demo_data'] = $property_demo->toArray();
        return view('property_sale_demo.view')->with(compact('property','provinces'));
    }

    public function sendDemoAccout(){

    }

    public function reset(){
        // reset sale_property_demo table data
        $id = Request::get('id');
        $property_demo = SalePropertyDemo::find($id);
        $property_demo->status = 0;
        $property_demo->trial_expire = null;
        $property_demo->email_contact = null;
        $property_demo->property_test_name = null;
        $property_demo->contact_name = null;
        $property_demo->tel_contact = null;
        $property_demo->default_password = "demo1234";
        $property_demo->save();

        // TODO: reset password account -> user table
        $property_id = $property_demo->property_id;
        $property = Property::find($property_id);
        $property->invoice_counter = 0;
        $property->post_parcel_counter = 0;
        $property->receipt_counter = 0;
        $property->expense_counter = 0;
        $property->payee_counter = 0;
        $property->withdrawal_slip_counter = 0;
        $property->petty_cash_balance = 0;
        $property->prepaid_slip_counter = 0;
        $property->fund_balance = 0;



        // Restore Basic Data
        $user_admin_demo = User::where('property_id',$property_id)->first();
        //admin_nb029@nabour.me
        $email_admin_demo = $user_admin_demo->email;
        $prefix_email = explode("@",$email_admin_demo);
        $prefix_property = explode("_",$prefix_email[0]);
        $prefix_demo_name = strtoupper($prefix_property[1]);

        $property->property_name_th = "หมู่บ้าน " . $prefix_demo_name;
        $property->property_name_en = "Demo " . $prefix_demo_name;
        $property->juristic_person_name_th = "หมู่บ้าน " . $prefix_demo_name;
        $property->juristic_person_name_en = "Demo " . $prefix_demo_name;
        $property->area_size = "300";
        $property->unit_size = 20;
        $property->construction_by = "Nabour Construction";
        $property->address_th = "ต.สุเทพ อ.เมือง";
        $property->street_th = "ถนนศิริมังคลาจารย์ ซอย 7";
        $property->province = 50;
        $property->postcode = "50200";
        $property->lat = "18.795263983660067";
        $property->lng = "98.97235203995058";
        $property->address_en = "T.Suthep A.Mueang";
        $property->street_en = "Siri Mangkalajarn Road Soi 7";
        $property->property_type = "1";
        $property->address_no = "17/1";

        $property->save();

        $this->clearUserAccountForDemo($property_id);

        $account = new AccountController();

        $account->clearPostReport($property_id);
        $account->clearPost($property_id);
        $account->clearEvent($property_id);
        $account->clearVote($property_id);
        $account->clearDiscussion($property_id);
        $account->clearComplain($property_id);
        $account->clearCommonFeeRef($property_id);
        $account->clearTransaction($property_id);
        $account->clearPostParcel($property_id);
        $account->clearMessage($property_id);
        $account->clearInvoice($property_id);
        //$account->clearPayee($property_id);
        $account->clearVehicle($property_id);
        $account->resetBankBalance($property_id);
        $account->resetPropertyUnitBalance($property_id);
        $account->clearPropertyPettyCash($property_id);
        $account->clearPropertyFund($property_id);

        /*$this->clearPostReport($property_id);
        $this->clearPost($property_id);
        $this->clearEvent($property_id);
        $this->clearVote($property_id);
        $this->clearDiscussion($property_id);
        $this->clearComplain($property_id);
        $this->clearCommonFeeRef($property_id);
        $this->clearTransaction($property_id);
        $this->clearPostParcel($property_id);
        $this->clearMessage($property_id);
        $this->clearInvoice($property_id);
        $this->clearPayee($property_id);
        $this->clearVehicle($property_id);*/

        // TODO: reset data in other table

        $data = [
            'msg' => 'success'
        ];
        return $data;
    }
    
    function clearPropertyData($id){
        $property_demo = SalePropertyDemo::find($id);
        $property_demo->status = 0;
        $property_demo->trial_expire = null;
        $property_demo->email_contact = null;
        $property_demo->property_test_name = null;
        $property_demo->contact_name = null;
        $property_demo->save();
        
        return true;
    }

    function clearUserAccountForDemo($property_id){
        $user_demo = User::where('property_id',$property_id)->get();
        foreach ($user_demo as $item){
            //$item->password = null;
            $item->remember_token = null;
            //$item->expire_trial = null;
            $item->active = true;
            $item->password = bcrypt("demo1234");

            // Delete notification
            $this->clearNotificationForDemo($item->id);

            $item->save();
        }

        return true;
    }

    function clearPostReport($property_id){
        $report_list = PostReport::where('property_id',$property_id)->get();
        foreach ($report_list as $report) {
            if ($report->reportList()->count()) {
                $report->reportList()->delete();
            }

            $report->delete();
        }
        return true;
    }

    function clearNotificationForDemo($user_id){
        $notification_list = Notification::where('to_user_id',$user_id)->get();
        foreach ($notification_list as $item){
            $item->delete();
        }

        return true;
    }

    function clearPost($property_id){
        $post_list = Post::where('property_id',$property_id)->get();
        foreach ($post_list as $post) {
            if(!$post->postFile->isEmpty()) {
                foreach ($post->postFile as $file) {
                    $this->removeFile($file->name,'post-file');
                }
                $post->postFile()->delete();
            }
            $post->comments()->delete();
            $post->likes()->delete();
            $post->delete();
        }

        return true;
    }

    function clearEvent($property_id){
        $event_list = Event::where('property_id',$property_id)->get();
        foreach ($event_list as $event) {
            if(!$event->eventFile->isEmpty()) {
                foreach ($event->eventFile as $file) {
                    $this->removeFile($file->name,'event-file');
                }
                $event->eventFile()->delete();
            }
            $event->confirmation()->delete();
            $event->delete();
        }

        return true;
    }

    function clearVote($property_id){
        $vote_list = Vote::where('property_id',$property_id)->get();
        foreach ($vote_list as $vote) {
            if(!$vote->voteFile->isEmpty()) {
                foreach ($vote->voteFile as $file) {
                    $this->removeFile($file->name,'vote-file');
                }
                $vote->voteFile()->delete();
            }
            $vote->userChoose()->delete();
            $vote->voteChoice()->delete();
            $vote->delete();
        }

        return true;
    }

    function clearDiscussion($property_id){
        $discussion_list = Discussion::where('property_id',$property_id)->get();
        foreach ($discussion_list as $discussion) {
            $discussion->comments()->delete();
            if(!$discussion->discussionFile->isEmpty()) {
                foreach ($discussion->discussionFile as $file) {
                    $this->removeFile($file->name,'discussion-file');
                }
                $discussion->discussionFile()->delete();
            }
            $discussion->delete();
        }
        return true;
    }

    function clearComplain($property_id){
        $complain_list = Complain::where('property_id',$property_id)->get();
        foreach ($complain_list as $complain) {
            $complain->comments()->delete();
            if(!$complain->complainFile->isEmpty()) {
                foreach ($complain->complainFile as $file) {
                    $this->removeFile($file->name,'complain-file');
                }
                $complain->complainFile()->delete();
            }
            $complain->delete();
        }
        return true;
    }

    function clearTransaction($property_id){
        $transaction = Transaction::where('property_id',$property_id)->get();
        foreach ($transaction as $item){
            $item->delete();
        }

        return true;
    }

    function clearPostParcel($property_id){
        $post_parcel = PostParcel::where('property_id',$property_id)->get();
        foreach ($post_parcel as $item){
            $item->delete();
        }

        return true;
    }

    function clearMessage($property_id){
        $message = Message::where('property_id',$property_id)->get();
        foreach ($message as $item){
            $item->hasText()->delete();
            $item->delete();
        }

        return true;
    }

    function clearCommonFeeRef($property_id){
        $common_fee_ref = CommonFeesRef::where('property_id',$property_id)->get();
        foreach ($common_fee_ref as $item){
            $item->delete();
        }

        return true;
    }

    function clearInvoice($property_id){
        $invoice_list = Invoice::where('property_id',$property_id)->get();
        foreach ($invoice_list as $invoice){
            if(!$invoice->invoiceFile->isEmpty()) {
                foreach ($invoice->invoiceFile as $file) {
                    $this->removeFile($file->name,'invoice-file');
                }
                $invoice->invoiceFile()->delete();
            }
            $invoice->invoiceRevision()->delete();
            $invoice->delete();
        }

        return true;
    }

    function clearPayee($property_id){
        $payee = Payee::where('property_id',$property_id)->get();
        foreach ($payee as $item){
            $item->delete();
        }

        return true;
    }

    function clearVehicle($property_id){
        $vehicle = Vehicle::where('property_id',$property_id)->get();
        foreach ($vehicle as $item){
            $item->delete();
        }

        return true;
    }

    public function disablePropertyDemo(){
        $id = Request::get('id');
        $property_demo = SalePropertyDemo::find($id);
        $property_demo->status = 3;
        $property_demo->save();

        $data = [
            'msg' => 'success'
        ];
        return $data;
    }

    public function enablePropertyDemo(){
        $id = Request::get('id');
        $property_demo = SalePropertyDemo::find($id);
        if($property_demo->contact_name != null) {
            $property_demo->status = 1;
        }else{
            $property_demo->status = 0;
        }
        $property_demo->save();

        $data = [
            'msg' => 'success'
        ];
        return $data;
    }

    public function assignDemoProperty(){
        $data = Request::all();
        $id = $data['property_assign_id'];
        $new_password = $this->generatePassword();
        $property_demo = SalePropertyDemo::find($id);
        $property_demo->status = 1;
        //$property_demo->trial_expire = Carbon::today()->addDays(7)->toDateTimeString();
        $property_demo->contact_name = $data['name'];
        $property_demo->email_contact = $data['email'];
        $property_demo->tel_contact = $data['tel'];
        $property_demo->property_test_name = $data['property_name'];
        $property_demo->default_password = $new_password;
        $property_demo->save();
        
        $this->setUpUserAccountForDemo($property_demo->property_id,$new_password);
        $this->mail_assign_demo_property($id,$property_demo->default_password);

        return redirect('officer/property-list');
    }

    function setUpUserAccountForDemo($property_id,$default_password){
        $user_demo = User::where('property_id',$property_id)->get();
        $encode_pass = bcrypt($default_password);
        foreach ($user_demo as $item){
            $item->password = $encode_pass;
            $item->remember_token = null;
            //$item->expire_trial = Carbon::today()->addDays(7)->toDateTimeString();
            $item->active = true;

            // Delete notification
            $this->clearNotificationForDemo($item->id);

            $item->save();
        }

        return true;
    }

    function mail_assign_demo_property($id,$default_password){
        $property_demo = SalePropertyDemo::find($id);
        $property_data = Property::find($property_demo->property->id);
        //$user = $property->property_admin;
        $property = $property_data->toArray();
        $admin_demo = User::where('property_id',$property_demo->property->id)->where('role','=',1)->first();
        $committee_demo = User::where('property_id',$property_demo->property->id)->where('is_chief','=',true)->first();
        $user_demo = User::where('property_id',$property_demo->property->id)->where('is_chief','=',false)->where('role','=',2)->get()->toArray();

        $email = $property_demo->email_contact;
        Mail::send('emails.demo_property_assign', [
            'property_demo'	=> $property_demo,
            'property' => $property,
            'admin_demo'		=> $admin_demo,
            'committee_demo'		=> $committee_demo,
            'user_demo'		=> $user_demo,
            'password'		=> $default_password

        ], function ($message) use($email) {
            $message->subject('ทดลองใช้งานการใช้งานระบบเนเบอร์');
            $message->from(env('MAIL_USERNAME'),'Nabour');
            $message->bcc(env('MAIL_BCC'));
            $message->to($email);
        });

        return true;
    }
    
    function mail_account_created ($name,$property_name,$email,$password) {
        Mail::send('emails.property_account_created', [
            'name'			=> $name,
            'property_name' => $property_name,
            'username'		=> $email,
            'password'		=> $password

        ], function ($message) use($email) {
            $message->subject('บัญชีสำหรับนิติบุคคลได้ถูกสร้าง');
            $message->from(env('MAIL_USERNAME'),'Nabour');
            $message->to($email);
        });
    }

    function mail_form_created ($name,$email,$property_name,$code) {
        Mail::send('emails.property_form_created', [
            'name'			=> $name,
            'property_name' => $property_name,
            'code'		=> $code

        ], function ($message) use($email) {
            $message->subject('รหัสแบบฟอร์มสำหรับข้อมูลนิติบุคคล');
            $message->from(env('MAIL_USERNAME'),'Nabour');
            $message->to($email);
        });
    }

    function generateCode() {
        $chars = "abcdefghijkmnpqrstuvwxyz123456789";
        $i = 0;
        $pass = '' ;
        while ($i < 5) {
            $num = rand() % 33;
            $tmp = substr($chars, $num, 1);
            $pass = $pass . $tmp;
            $i++;
        }
        return $pass;
    }

    function generatePassword() {
        $chars = "abcdefghijkmnpqrstuvwxyz123456789";
        $i = 0;
        $pass = '' ;
        while ($i < 6) {
            $num = rand() % 33;
            $tmp = substr($chars, $num, 1);
            $pass = $pass . $tmp;
            $i++;
        }
        return $pass;
    }

    public function removeFile ($name,$dir) {
        $folder = substr($name, 0,2);
        $file_path = $dir.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            Storage::disk('s3')->delete($file_path);
        }
    }

    function subval_sort($a,$subkey) {
        foreach($a as $k=>$v) {
            $b[$k] = strtolower($v[$subkey]);
        }
        asort($b);
        foreach($b as $key=>$val) {
            $c[] = $a[$key];
        }
        return $c;
    }
}
