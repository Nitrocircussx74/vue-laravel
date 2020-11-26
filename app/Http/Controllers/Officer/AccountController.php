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
use App\Bank;
use App\BankTransaction;
use App\PettyCash;
use App\PropertyFund;
use App\PropertyUnitPrepaid;
use App\MonthlyCounterDoc;
use App\CashBoxDepositeLog;
use App\CashBoxDepositeLogFile;
class AccountController extends Controller {

    public function __construct () {
        
    }
    
    

    public function index(){
        $list_property = SalePropertyDemo::with('property')->where('sale_id','=',Auth::user()->id)->get();

        /*foreach ($list_property as &$item){
            if($item->trial_expire != null) {
                $expire = Carbon::createFromFormat('Y-m-d H:i:s', $item->trial_expire);
                $datetime_now = Carbon::now();
                $result_cal_trial = $datetime_now->diffInDays($expire, false);

                if ($result_cal_trial >= 0) {
                    $item['isExpire'] = 0;
                } else {
                    $item['isExpire'] = 1;
                }
            }else{
                $item['isExpire'] = 0;
            }
        }*/

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
        //$property_demo->trial_expire = null;
        $property_demo->email_contact = null;
        $property_demo->property_test_name = null;
        $property_demo->contact_name = null;
        $property_demo->save();

        // TODO: reset password account -> user table
        $property_id = $property_demo->property_id;
        $this->clearUserAccountForDemo($property_id);
        $this->clearPostReport($property_id);
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
        $this->clearVehicle($property_id);

        // TODO: reset data in other table

        $data = [
            'msg' => 'success'
        ];
        return $data;
    }
    
    function clearPropertyData($id){
        $property_demo = SalePropertyDemo::find($id);
        $property_demo->status = 0;
        //$property_demo->trial_expire = null;
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
            $item->password = bcrypt("");

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
                $complain->complainAction()->delete();
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
            $invoice->commonFeesRef()->delete();
            $invoice->instalmentLog()->delete();
            $invoice->invoiceLog()->delete();
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

    public function resetBankBalance ($property_id) {
        $banks = Bank::where('property_id',$property_id)->get();
        foreach ($banks as $bank) {
           $bank->balance = 0;
           $bank->transactionLog()->delete();
           $bank->save();
        }
    }

    public function resetPropertyUnitBalance ($property_id) {
        $units = PropertyUnit::where('property_id',$property_id)->get();
        foreach ($units as $unit) {
           $unit->balance = 0;
           $unit->cf_balance = 0;
           $unit->balanceLog()->delete();

           $prepaidBill = PropertyUnitPrepaid::with('prepaidFile')->where('property_id',$property_id)->get();
           foreach ($prepaidBill as $p_bill) {
                if($p_bill->prepaidFile->count()) {
                    foreach ($p_bill->prepaidFile as $p_file) {
                        $this->removeFile($p_file->name,'prepaid');
                        $p_file->delete();
                    }
               }
               $p_bill->delete();
           }
           $unit->save();
        }
    }
    
    public function clearPropertyPettyCash ($property_id) {
        $pts = PettyCash::where('property_id',$property_id)->get();
        foreach ($pts as $pt) {
           $pt->editLog()->delete();
            if(!$pt->pettyCashFile->isEmpty()) {
                foreach ($pt->pettyCashFile as $file) {
                    $this->removeFile($file->name,'bills');
                }
                $pt->pettyCashFile()->delete();
            }
           $pt->delete();
        }
    }

    public function clearPropertyFund ($property_id) {
        $fs = PropertyFund::where('property_id',$property_id)->get();
        foreach ($fs as $f) {
           $f->editLog()->delete();
           $f->delete();
        }
    }

    public function clearPropertyCashBox ($property_id) {
        $cb = CashBoxDepositeLog::where('property_id',$property_id)->get();
        foreach ($cb as $item) {
            if(!$item->depositLogFile->isEmpty()) {
                foreach ($item->depositLogFile as $file) {
                    $this->removeFile($file->name,'cash-box');
                }
                $item->depositLogFile()->delete();
            }
            $item->delete();
        }
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
        $property_demo->status = 1;
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

    public function createAccount(){
        if ( Request::isMethod('post') ) {
            //$aaa = Request::all();
            //$aaa = Request::get('field_name');
            $app_key = Request::get('app_key');
            if($app_key == $_ENV['APP_KEY']) {
                $user = Request::get('user');
                $officer_id = $this->createUserOfficer($user);
                for ($i = 1; $i <= 3; $i++) {
                    $default_password = $this->generatePassword();
                    $property_id = $this->createProperty($default_password);
                    $this->createSaleDemoProperty($officer_id, $property_id, $default_password);
                }
            }
        }
    }
    
    function createUserOfficer($user){
        try {
            /*$user_create = new User;
            $post->user_id      = Auth::user()->id;*/
            $user_create = User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'password' => $user['password'],
                'role' => 4
            ]);
            return $user_create->id;

        }catch(Exception $ex){
            return false;
        }
    }

    function createProperty($default_password){
        try {
            $last_property = Property::where('is_demo', '=', 'false')->orderBy('prefix_code', 'desc')->first();
            $prefix = $last_property->toArray()['prefix_code'];
            $next_prefix = "00" . strval($prefix + 1);
            $name_new = "NB" . substr($next_prefix, -3, 3);

            $new_property = new Property();
            $new_property->fill($last_property->toArray());
            unset($new_property->id);
            $new_property->property_name_th = "หมู่บ้านทดลองใช้งาน " . $name_new;
            $new_property->property_name_en = "Demo Village " . $name_new;
            $new_property->prefix_code = $prefix + 1;
            $new_property->save();

            $this->createPropertyUnit($new_property->id,$name_new,$default_password);

            return $new_property->id;
        }catch(Exception $ex){
            return null;
        }
    }
    
    function createPropertyUnit($property_id, $property_name_new,$default_password){
        try{
            // Create Admin User
            $user_create = User::create([
                'name' => "ผู้จัดการนิติ " . $property_name_new,
                'email' => "admin_".strtolower($property_name_new)."@nabour.me",
                'password' => bcrypt($default_password),
                'property_id' => $property_id,
                'role' => 1
            ]);

            // Create Property Unit
            for ($i = 1; $i <= 4; $i++) {
                $unit = new PropertyUnit;
                $unit->property_id = $property_id;
                $unit->property_size = 150;
                $unit->unit_number = "17/".$i;
                $unit->save();

                $unit_id = $unit->id;

                // Create Member User
                if($i == 1){
                    $this->createCommitteeUser($property_id, $unit_id, $default_password, $property_name_new);
                    //$this->createMemberUser($i, $property_id, $unit_id, $default_password, $property_name_new);
                }else {
                    $this->createMemberUser($i, $property_id, $unit_id, $default_password, $property_name_new);
                }
            }
        }catch(Exception $ex){
            return null;
        }
    }

    function createCommitteeUser($property_id,$property_unit_id, $default_password, $property_name_new){
        try{
            // Committee Member
            $user_create = User::create([
                'name' => "กรรมการนิติ " . $property_name_new,
                'email' => "com_".strtolower($property_name_new)."@nabour.me",
                'password' => bcrypt($default_password),
                'property_id' => $property_id,
                'property_unit_id' => $property_unit_id,
                'role' => 2,
                'is_chief' => true
            ]);
        }catch (Exception $ex){
            return null;
        }
    }

    function createMemberUser($i, $property_id, $property_unit_id, $default_password, $property_name_new){
        try{
            // Member
            $user_create = User::create([
                'name' => "ลูกบ้านคนที่".$i." ". $property_name_new,
                'email' => "user".$i."_".strtolower($property_name_new)."@nabour.me",
                'password' => bcrypt($default_password),
                'property_id' => $property_id,
                'property_unit_id' => $property_unit_id,
                'role' => 2
            ]);
        }catch (Exception $ex){
            return null;
        }
    }

    function createSaleDemoProperty($officer_id, $property_id, $default_password){
        $user_create = SalePropertyDemo::create([
            'sale_id' => $officer_id,
            'property_id' => $property_id,
            'default_password' => $default_password
        ]);
    }

    public function changeStatusOfficer(){
        if ( Request::isMethod('post') ) {
            $app_key = Request::get('app_key');
            if($app_key == $_ENV['APP_KEY']) {
                $user = Request::get('user');
                $officer = User::where('email',$user['email'])->first();
                if($officer) {
                    $officer->active = ($user['status'] == "1") ? true : false;
                    $officer->save();
                }
            }
        }
    }

    public function deleteOfficer(){
        if ( Request::isMethod('post') ) {
            $app_key = Request::get('app_key');
            if($app_key == $_ENV['APP_KEY']) {
                $user = Request::get('user');
                $officer = User::where('email',$user['email'])->first();
                if($officer) {
                    $sale_id = $officer->id;
                    $list_property = SalePropertyDemo::with('property')->where('sale_id','=',$sale_id)->get();



                    foreach ($list_property as $item){
                        $property_id = $item->property_id;

                        $this->clearUserAccountForDemo($property_id);
                        $this->clearPostReport($property_id);
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
                        $this->clearVehicle($property_id);

                        $item->delete();

                        $this->deleteUser($property_id);
                        $this->deleteProperty($property_id);
                    }

                    $officer->delete();
                }
            }
        }
    }

    function deleteProperty($id){
        $property = Property::with('property_unit')->find($id);
        if($property) {
            $property->property_unit()->delete();
            $property->delete();
        }
    }

    function deleteUser($property_id){
        $user_demo = User::where('property_id',$property_id)->get();
        foreach ($user_demo as $item){
            $item->delete();
        }
    }
    
    public function createProperty_test(){
        try {
            $last_property = Property::where('is_demo', '=', 'false')->orderBy('prefix_code', 'desc')->first();
            $prefix = $last_property->toArray()['prefix_code'];
            $next_prefix = "00" . strval($prefix + 1);
            $name_new = "NB" . substr($next_prefix, -3, 3);

            $new_property = new Property();
            $new_property->fill($last_property->toArray());
            unset($new_property->id);
            $new_property->property_name_th = "หมู่บ้านทดลองใช้งาน " . $name_new;
            $new_property->property_name_en = "Demo Village " . $name_new;
            $new_property->prefix_code = $prefix + 1;
            $new_property->save();

            return $new_property->id;
        }catch(Exception $ex){
            return null;
        }
    }

    public function clearPropertyInvoiceCounter ($property_id) {
        $counter = MonthlyCounterDoc::where('property_id',$property_id)->get();
        foreach ($counter as $item){
            $item->delete();
        }
    }
}
