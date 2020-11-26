<?php namespace App\Http\Controllers\User\MarketPlace;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
# Model
use App\Order;
use App\CheckFirstOrderSinghaOnline;

class SOSOrderController extends Controller {

	public function orderPayment ($id) {
        $order = Order::find($id);
        if($order) {
            $encrypt_key = $this->encrypt_decrypt('encrypt',$order->order_number);
            return view('market_place.SOS.order_payment.payment_form')->with(compact('order','encrypt_key'));
        } else {
            return view('market_place.SOS.order_payment.payment_cancel');
        }

	}

	public function orderSuccess (Request $r) {
        //$ref = Request::get('Ref');
        $ref = $this->encrypt_decrypt('decrypt', $r->get('Ref1'));
        $order = Order::where('order_number', $ref)->first();
        if( $order ) {
            $order->payment_at   = date('Y-m-d H:i:s');
            $order->status       = 1;
            $order->payment_type = 2;
            $order->save();
            $order->order_product()->update(['status' => 1]);

            if($order->discount != null && $order->promotion_id == null){
                // used FirstOrder Discount Already
                $first_order = new CheckFirstOrderSinghaOnline();
                $first_order->user_id = $order->id;
                $first_order->save();

                $other_order = Order::whereNotNull('discount')->whereNull('promotion_id')->where('sos_user_id',$order->sos_user_id)->where('user_id',$order->id)->get();
                foreach ($other_order as $item_other_order){
                    $other_other_update = Order::find($item_other_order->id);
                    $other_other_update->grand_total = $other_other_update->total;
                    $other_other_update->discount = null;
                    $other_other_update->save();
                }
            }
            return view('market_place.SOS.order_payment.payment_success')->with(compact('order'));
        } else {
            return view('market_place.SOS.order_payment.payment_fail');
        }
    }

    public function dataFeedCallback (Request $r)
    {
        $HTTP_POST_VARS = $r->all();//$_POST;
        $successCode = $HTTP_POST_VARS['successcode'];

        $payRef = $HTTP_POST_VARS['PayRef'];
        $order_no_ref = $HTTP_POST_VARS['Ref'];

        ## เขียนคำสั่ง Print คำว่า 'OK' เพื่อบอกให้เราทราบว่าทางร้านค้าได ้รับ Datafeed แล้ว
        echo "OK";

        if ($successCode == "0") {
            $order = Order::where('order_number', $order_no_ref)->first();
            if( $order ) {

                // Transaction Accepted
                // Add the Security Control here, to check the currency, amount with the
                // merchant’s order reference from your database, if the order exist then
                // accepted otherwise rejected the transaction.
                // Update your database for Transaction Accepted and send email or notify your
                // customer.

                $order->payment_at      = date('Y-m-d H:i:s');
                $order->status          = 1;
                $order->payment_type    = 2;
                $order->payment_ref_no  = $payRef;
                $order->save();
                $order->order_product()->update(['status' => 1]);

                // Call API checkout (SOS)
                $id_customer = $order->sos_user_id;
                $id_cart = $order->cart_id;

                // FIND ADDRESS
                $id_customer = $order->sos_user_id;
                $client = new \GuzzleHttp\Client();
                $function = "address";
                $condition = "&id_customer=".$id_customer."&id_address=";
                $url = env('SINGHA_ONLINE_URL') . $function . $condition;
                $res = $client->request('GET', $url);
                $body = $res->getBody();
                $list_address = json_decode($body);

                $address_a = $list_address[0];
                $address_b = $list_address[1];
                $address_a = $address_a->id_address;
                $address_b = $address_b->id_address;

                if($address_a<$address_b){
                    $id_address = $address_a;
                    $id_address_inv = $address_b;
                }else{
                    $id_address = $address_b;
                    $id_address_inv = $address_a;
                }

                $client = new \GuzzleHttp\Client();
                $function = "checkout";
                $condition = "&id_customer=".$id_customer."&id_cart=".$id_cart."&payment_type=CR"."&id_address=".$id_address."&id_address_inv=".$id_address_inv."&Comment="."&need_inv=Y"."&cust_group=1"."&order_chanel=NB";
                $url = env('SINGHA_ONLINE_URL') . $function . $condition;
                $res = $client->request('GET', $url);
                $body = $res->getBody();
                $obj_product = json_decode($body);

                if ($order->discount != null && $order->promotion_id == null) {
                    // used FirstOrder Discount Already
                    $first_order = new CheckFirstOrderSinghaOnline();
                    $first_order->user_id = $order->user_id;
                    $first_order->save();

                    $other_order = Order::whereNotNull('discount')->whereNull('promotion_id')->where('sos_user_id', $order->sos_user_id)->where('user_id', $order->user_id)->get();
                    foreach ($other_order as $item_other_order) {
                        $other_other_update = Order::find($item_other_order->id);
                        $other_other_update->grand_total = $other_other_update->total;
                        $other_other_update->discount = null;
                        $other_other_update->save();
                    }
                }
            } else {
                // No order was found do nothing
                ///echo 'No Order';
            }

            //In case if your database or your system got problem, you can send a void
            //transaction request. See API guide for more details
        } else {
            // Transaction
        }
    }

    function encrypt_decrypt($action, $string) {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        $secret_key = 'SOS-encrypt-key-852369741';
        $secret_iv = 'SOS-encrypt-value-369741258';
        // hash
        $key = hash('sha256', $secret_key);

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ( $action == 'encrypt' ) {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } else if( $action == 'decrypt' ) {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }
}
