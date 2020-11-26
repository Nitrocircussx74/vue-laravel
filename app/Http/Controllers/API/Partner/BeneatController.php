<?php
namespace App\Http\Controllers\API\Partner;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use DB;
use App\Model\Partner\BeneatOrder;

class BeneatController extends Controller
{
    
    public function updateStatus (Request $request)  {
        
        $order_data = json_decode($request->get('order'),true);
        $promocode_data = json_decode($request->get('promo_code_usage'),true);
        
        if( !empty($order_data) ) {

            $order = BeneatOrder::find($order_data['id']);

            if( !$order ) {
                $order = new BeneatOrder;
            }

            $order->fill($order_data);

            $order->province_name_th = $order_data['user_place']['address_province']['name_th'];
            $order->province_name_en = $order_data['user_place']['address_province']['name_en'];

            if( !empty( $order_data['professional'] ) ) {
                $order->professional_full_name = $order_data['professional']['full_name'];
            }

            if( !empty( $promocode_data ) ) {
                $order->promo_code = $promocode_data['promo_code']['code'];
            }

            if( !empty( $order_data['partner_project'] ) ) {
                $order->property_ref = $order_data['partner_project']['referral_code'];
            }

            $order->full_data = json_encode($request->all());
            $order->system_recorded_date = date('Y-m-d H:i:s');
            $order->save();

            return response()->json([
                'status'    => 'success',
                'code'      => '000',
                'message'   => 'Data saved.'
            ]);

        } else {
            return response()->json([
                'status'    => 'fail',
                'code'      => '100',
                'message'   => 'Data not provided.'
            ]);
        }
    }
}