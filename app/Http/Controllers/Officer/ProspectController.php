<?php namespace App\Http\Controllers\Officer;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use Redirect;
use Mail;
# Model
use App\User;
use App\SaleProspect;
use App\SaleProspectCounter;
use App\SaleProspectAppointmentDate;
use Carbon\Carbon;
use Excel;

class ProspectController extends Controller {

    public function __construct () {
        $this->middleware('auth',['except' => ['login']]);
        if( Auth::check() && Auth::user()->role !== 4 ) {
            Redirect::to('feed')->send();
        }
    }

    public function index () {
        $_prospect = new SaleProspect;
        $viewToday = false;
        $viewStatus = '-';
        $appointment_date_from = $appointment_date_to = null;
        if(Request::get('today')){
            $viewToday = true;
            $appointment_date_from = date('Y/m/d');
            $appointment_date_to = date('Y/m/d');
        }
        if(Request::has('status')){
            $viewStatus = Request::get('status') ;
        }
        if(Request::ajax() || Request::has('export')) {
            if(Request::get('prospect_name')) {
                $_prospect = $_prospect->where('prospect_name','like',"%".Request::get('prospect_name')."%");
            }

            if(trim(Request::get('status')) != '-' ) {
                $_prospect = $_prospect->where('status',Request::get('status'));
            }

            if(Request::get('prospect_phone_number')) {
                $_prospect = $_prospect->where('prospect_phone_number','like',"%".Request::get('prospect_phone_number')."%");
            }

            if(Request::get('prospect_email')) {
                $_prospect = $_prospect->where('prospect_email','like',"%".Request::get('prospect_email')."%");
            }

            if(Request::get('appointment_date_from') && Request::get('appointment_date_to')) {
                $appointment_date_from = date("Y-m-d", strtotime(Request::get('appointment_date_from')));
                $appointment_date_to = date("Y-m-d", strtotime(Request::get('appointment_date_to')));
                $dateBw = array($appointment_date_from.' 00:00:00', $appointment_date_to.' 23:59:59');
                $_prospect = $_prospect->whereHas('appointment_date', function ($query) use ($dateBw){
                    $query->whereBetween('appointment_date', $dateBw);
                });
            } elseif (Request::get('appointment_date_from')){
                $appointment_date_from = date("Y-m-d", strtotime(Request::get('appointment_date_from')));
                $fromDate = $appointment_date_from.' 00:00:00';
                $_prospect = $_prospect->whereHas('appointment_date', function ($query) use ($fromDate){
                    $query->where('appointment_date', '>=', $fromDate);
                });
            } elseif (Request::get('appointment_date_to')){
                $appointment_date_to = date("Y-m-d", strtotime(Request::get('appointment_date_to')));
                $toDate = $appointment_date_to.' 23:59:59';
                $_prospect = $_prospect->whereHas('appointment_date', function ($query) use ($toDate){
                    $query->where('appointment_date', '<=', $toDate);
                });
            } else {
                $_prospect = $_prospect->with('appointment_date');
            }
        } else {
            $_prospect = $_prospect->with('appointment_date');
        }
        if(Request::get('export') && Request::get('export') == '1'){
            $_prospect = $_prospect->where('sale_id', '=', Auth::user()->id)->orderBy('created_at', 'desc')->get();
            $dateNow = new Carbon('now');
            $dateTimeNow = $dateNow->format('Y-m-d_His');
            $excel = Excel::create('Prospect-'.$dateTimeNow, function($excel) use ($_prospect, $dateTimeNow) {
                $excel->sheet('', function($sheet) use ($_prospect, $dateTimeNow) {
                    $sheet->loadView('prospect.export.excel', compact('_prospect', 'dateTimeNow'));
                });
            });
            return $excel->export('xlsx');
        } else {
            $_prospect = $_prospect->where('sale_id', '=', Auth::user()->id)->orderBy('created_at', 'desc')->paginate(30);
        }
        if(Request::ajax()) {
            return view('prospect.list-page')->with(compact('_prospect'));
        } else {
            return view('prospect.list')->with(compact('_prospect', 'viewToday', 'viewStatus', 'appointment_date_from', 'appointment_date_to'));
        }
    }

    public function add () {
        if( Request::isMethod('post') ) {
            $saleProspectCounter = SaleProspectCounter::first();
            $saleProspectCounter->increment('number_prospect');
            $saleProspectCounter->save();
            $numberAllPros = $saleProspectCounter->number_prospect;
            $prospectCode = 'PROS'.date('my').str_pad(($numberAllPros+1), 6, '0', STR_PAD_LEFT);
            $p = new SaleProspect();
            $p->fill(Request::all());
            $p->unit_size = (int)Request::get('unit_size');
            $p->prospect_code = $prospectCode;
            $p->status 		= 0;
            $p->sale_id = Auth::user()->id;
            $p->save();
            $prospectId = $p->id;
            $appointment_date_format = date("Y-m-d", strtotime(Request::get('appointment_date')));
            $appointment_time_format = date("H:i:s", strtotime(Request::get('appointment_time')));
            $pa = new SaleProspectAppointmentDate();
            $pa->appointment_date = $appointment_date_format.' '.$appointment_time_format;
            $pa->status = 0;
            $pa->sale_prospect_id = $prospectId;
            $pa->save();
        }
        return redirect('officer/prospect');
    }

    public function edit () {
        $p = SaleProspect::find(Request::get('id'));
        if($p && Request::isMethod('post')) {
            $p->fill(Request::all());
            $p->unit_size = (int)Request::get('unit_size');
            $p->save();
            Request::session()->flash('message', 'อัพเดทข้อมูลเรียบร้อยแล้ว' );
            return 'success';
        }
        return view('prospect.edit')->with(compact('p'));
    }

    public function viewAppointment () {
        if( Request::isMethod('post') ) {
            $_appointmentDate = SaleProspectAppointmentDate::where('sale_prospect_id', Request::get('id'))
                ->orderBy('appointment_date', 'ASC')
                ->get();
            return view('prospect.view-appointment')->with(compact('_appointmentDate'));
        }
        return redirect('officer/prospect');
    }

    public function saveAppointment () {
        if( Request::isMethod('post') ) {
            $ids = Request::get('id');
            $prospectId = Request::get('appointment_prospect_id');
            $appointmentTime = Request::get('appointment_time');
            $note = Request::get('note');
            foreach(Request::get('appointment_date') as $_id => $_appointment){
                $appointmentDate = new SaleProspectAppointmentDate();
                if(isset($ids[$_id])){
                    $appointmentDate = $appointmentDate->find($_id);
                } else {
                    $appointmentDate->sale_prospect_id = $prospectId;
                }
                $appointment_date_format = date("Y-m-d", strtotime($_appointment));
                $appointment_time_format = date("H:i:s", strtotime($appointmentTime[$_id]));
                $appointmentDate->appointment_date = $appointment_date_format.' '.$appointment_time_format;
                $appointmentDate->status = 0;
                $appointmentDate->note = $note[$_id];
                $appointmentDate->save();
            }
            return 'success';
        }
        return redirect('officer/prospect');
    }

    public function approveAppointment () {
        if( Request::isMethod('post') ) {
            $saleProspectCounter = SaleProspectCounter::first();
            $saleProspectCounter->increment('number_customer');
            $saleProspectCounter->save();
            $nextCusIncrement = $saleProspectCounter->number_customer;
            $customerCode = 'CUS'.date('my').str_pad($nextCusIncrement, 6, '0', STR_PAD_LEFT);
            $prospectId = Request::get('appointment_prospect_id');
            $p = SaleProspect::find($prospectId);
            $p->customer_code = $customerCode;
            $p->status 		= 1;
            $p->save();
            Request::session()->flash('class', 'success');
            Request::session()->flash('message', trans('messages.Prospect.approve_success') );
            return 'success';
        }
        return redirect('officer/prospect');
    }

    function delete () {
        $prospect = SaleProspect::find(Request::get('id'));
        if($prospect) {
            $prospect->appointment_date()->delete();
            $prospect->delete();
            Request::session()->flash('class', 'success');
            Request::session()->flash('message', trans('messages.Prospect.delete_success') );
        } else {
            Request::session()->flash('class', 'danger');
            Request::session()->flash('message', trans('messages.Prospect.delete_error') );
        }
        return redirect('officer/prospect');
    }

    public function dashboard () {
        $prospects = new SaleProspect;
        $prospects = $prospects->with('appointment_date');
        $prospects = $prospects->where('sale_id','=',Auth::user()->id)->orderBy('created_at','desc')->get();

        $todayDate = date('Y-m-d');
        $dateBw = array($todayDate.' 00:00:00', $todayDate.' 23:59:59');
        $numTodayProspect = SaleProspect::where('sale_id','=',Auth::user()->id)->whereHas('appointment_date', function ($query) use ($dateBw){
            $query->whereBetween('appointment_date', $dateBw);
        })->get()->count();

        $numNotApprove = $numApprove = 0;
        foreach($prospects as $p):
            if($p->status == '1'):
                $numApprove++;
            else:
                $numNotApprove++;
            endif;
        endforeach;

        return view('prospect.dashboard')->with(compact('prospects','numApprove','numNotApprove', 'numTodayProspect'));
    }
}
