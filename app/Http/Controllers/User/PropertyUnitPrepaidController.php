<?php namespace App\Http\Controllers\User;
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

class PropertyUnitPrepaidController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_prepaid');
		view()->share('active_menu', 'bill');
		//if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function prepaidList (Request $form) {
		$pp_slips = PropertyUnitPrepaid::with('property_unit')->where('property_unit_id',Auth::user()->property_unit_id);
		$pp_slips = $pp_slips->orderBy('pe_slip_no','desc')->paginate(50);
		if(!$form::ajax()) {
			return view('prepaid.user-prepaid-list')->with(compact('pp_slips'));
		} else {
			return view('prepaid.user-prepaid-list-element')->with(compact('pp_slips'));
		}
	}

	function viewPrepaid ($id) {
        $pp_slip = PropertyUnitPrepaid::with('property', 'property_unit', 'prepaidFile')->find($id);
		return view('prepaid.prepaid-view')->with(compact('pp_slip'));
	}

	public function getAttach ($id) {
		$file = InvoiceFile::find($id);
        $file_path = 'bills'.'/'.$file->url.$file->name;
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
}