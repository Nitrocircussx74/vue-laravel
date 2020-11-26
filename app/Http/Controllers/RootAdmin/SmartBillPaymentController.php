<?php

namespace App\Http\Controllers\RootAdmin;

use Illuminate\Http\Request;
use Auth;

use App\Http\Controllers\Controller;
use App\SmartBill;
use App\Property;

use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use CodeItNow\BarcodeBundle\Utils\QrCode;

class SmartBillPaymentController extends Controller
{
    
    public function __construct () {
        $this->middleware('auth',['except' => ['login']]);
        if( Auth::check() && Auth::user()->role !== 0 ) {
            if(Auth::user()->role !== 5) {
                Redirect::to('feed')->send();
            }
        }
    }

    public function smartBillFormGetQr (Request $r) {

        $setting = $r->all();
        $property = Property::find($r->get('property_id'));
        if(  $property->property_code ) {
            $ref = generateQrRefCode($property->property_code,1,true);
        } else {
            $ref = "0001000000001";
        }
        $bar_data = getQrString($setting,200,$ref);
        $qrCode = new QrCode();
        return response()->json(
            array(
                'status'    => true,
                'qr'        => '<img src="data:image/png;base64,'.getQRCode($bar_data).'" />',
                'bar'       => '<img  style="max-width: 100%;" src="data:image/png;base64,'.barcode($bar_data).'" />'
            )
        );
     }
 
     public function smartBillFormDownloadQr (Request $r) {
        $setting = $r->all();
        $property = Property::find($r->get('property_id'));
        if(  $property->property_code ) {
            $ref = generateQrRefCode($property->property_code,1,true);
        } else {
            $ref = "0001000000001";
        }
        $bar_data = getQrString($setting,200,$ref);
        $base64 = getQRCode($bar_data);
        $name = uniqid().'.png';
        \Storage::disk('local')->put($name,base64_decode($base64)); 
        return response()->download(storage_path('app/'.$name))->deleteFileAfterSend(true);
     }
}
