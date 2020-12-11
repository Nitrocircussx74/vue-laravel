<?php

namespace App\Http\Controllers\PropertyAdmin\MarketPlace;

use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Http\Controllers\PushNotificationController;

use App\PropertyUnit;
use App\Order;
use App\OrderProduct;
use App\Notification;


class SinghaOnlineController extends Controller
{
    public function __construct () {
        $this->middleware('auth');
        view()->share('active_menu', 'market_place_singha');
    }

    public function orderList(Request $r)
    {

        $this->searchOrder($r,5,true);

        if($r->ajax()) {
            return view('market_place.SOS.property_admin.delivering_order.admin_board_page_body')->with(compact('r'));
        } else {
            $unit_list = array('-'=> trans('messages.unit_no'));
            $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
            return view('market_place.SOS.property_admin.delivering_order.admin_board')->with(compact('r','unit_list'));
        }
    }

    public function orderListPage (Request $r)
    {
        $this->searchOrderList($r, 5);
        return view('market_place.SOS.property_admin.delivering_order.order-body');
    }

    public function orderDeliveringPrint (Request $r)
    {
        $this->searchOrder($r,5);
        return view('market_place.SOS.property_admin.delivering_order.order_print');
    }

    public function orderArrivedList (Request $r)
    {

        $this->searchOrder($r,6,true);

        if($r->ajax()) {
            return view('market_place.SOS.property_admin.arrived_order.admin_board_page_body')->with(compact('r'));
        } else {
            $unit_list = array('-'=> trans('messages.unit_no'));
            $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
            return view('market_place.SOS.property_admin.arrived_order.admin_board')->with(compact('r','unit_list'));
        }
    }

    public function orderArrivedPage (Request $r)
    {
        $this->searchOrderList($r, 6);
        return view('market_place.SOS.property_admin.arrived_order.order-body');
    }

    public function orderArrivedPrint (Request $r)
    {
        $this->searchOrder($r,6);
        return view('market_place.SOS.property_admin.arrived_order.order_print')->with(compact('unit_list','orders','product_sum','from_date','to_date'));

    }

    public function orderReceivedList (Request $r)
    {
        $this->searchOrder($r,7,true);
        if($r->ajax()) {
            return view('market_place.SOS.property_admin.received_order.admin_board_page_body')->with(compact('r'));
        } else {
            $unit_list = array('-'=> trans('messages.unit_no'));
            $unit_list += PropertyUnit::where('property_id',Auth::user()->property_id)->where('active',true)->orderBy(DB::raw('natsortInt(unit_number)'))->lists('unit_number','id')->toArray();
            return view('market_place.SOS.property_admin.received_order.admin_board')->with(compact('r','unit_list'));
        }
    }

    public function orderReceivedPage (Request $r)
    {
        $this->searchOrderList($r, 7);
        return view('market_place.SOS.property_admin.received_order.order-body');
    }

    public function receivedOrder (Request $r) {
        if( $r->isMethod('post') ) {

            $order = Order::find($r->get('oid'));
            if($order) {
                $order->status = 7;
                $order->received_at = $r->get('receive_date');
                $order->save();
                $order->order_product()->update(array('status' => 7));
                $this->sendReminderNotification($order->user_id,$order->id,'status_change', 7, $order->order_number);
                $result = true;
            } else {
                $result = false;
            }
            return response()->json(['result' => $result]);
        }
    }

    public function receivedOrderPrint (Request $r) {
        $this->searchOrder($r,7);
        return view('market_place.SOS.property_admin.received_order.order_print');
    }

    public function searchOrder ($r, $status, $paginate = false) {
        $from_date = $to_date = "";
        $orders = Order::with('order_product','property_unit')->where('property_id', Auth::user()->property_id)->where('status',$status);
        $product_sum = OrderProduct::where('property_id', Auth::user()->property_id)->where('status',$status);
        if( $r->get('order-no') ) {
            $orders = $orders->where('order_number','like',"%".$r->get('order-no')."%");
            $product_sum = $product_sum->whereHas('in_order', function ($q) use ($r) {
                $q->where('order_number','like',"%".$r->get('order-no')."%");
            });
        }

        if( $r->get('start-order-date') ) {
            $from_date = str_replace('/','-',$r->get('start-order-date'));
            $orders = $orders->where('created_at','>=',$r->get('start-order-date')." 00:00:00");
            $product_sum = $product_sum->where('created_at','>=',$r->get('start-order-date')." 00:00:00");
        }

        if( $r->get('end-order-date') ) {
            $to_date = str_replace('/','-',$r->get('end-order-date'));
            $orders = $orders->where('created_at','<=',$r->get('end-order-date')." 23:59:59");
            $product_sum = $product_sum->where('created_at','<=',$r->get('end-order-date')." 23:59:59");
        }

        if( $r->get('unit_id') != '-' && $r->get('unit_id') != "") {
            $orders = $orders->where('property_unit_id',$r->get('unit_id'));
            $product_sum = $product_sum->where('property_unit_id',$r->get('unit_id'));
        }

        if( $paginate ) {
            $orders = $orders->paginate(50);
        } else {
            $orders = $orders->get();
        }


        $product_sum = $product_sum->select(DB::raw('product_name, unit, price, SUM(total) as total_sales,SUM(quantity) as quantity_sales'))
            ->groupBy('sos_product_id','product_name','unit','price')->get();

        view()->share(compact('orders','product_sum','from_date','to_date'));
    }

    public function searchOrderList ($r, $status) {

        $orders = Order::with('order_product','property_unit')->where('property_id', Auth::user()->property_id)->where('status',$status);

        if( $r->get('order-no') ) {
            $orders = $orders->where('order_number','like',"%".$r->get('order-no')."%");
        }

        if( $r->get('start-order-date') ) {
            $orders = $orders->where('created_at','>=',$r->get('start-order-date')." 00:00:00");
        }

        if( $r->get('end-order-date') ) {
            $orders = $orders->where('created_at','<=',$r->get('end-order-date')." 23:59:59");
        }

        if( $r->get('order-status') != '-' && $r->get('order-status') != null) {
            $orders = $orders->where('status',$r->get('order-status'));
        }

        if( $r->get('unit_id') != '-' && $r->get('unit_id') != "") {
            $orders = $orders->where('property_unit_id',$r->get('unit_id'));
        }

        $orders = $orders->paginate(50);

        view()->share(compact('orders'));
    }

    public function printOrderItem ($id) {
        $order = Order::find($id);
        return view('market_place.SOS.property_admin.arrived_order.order-item-print')->with(compact('order'));
    }

    public function sendArrivedReminder (Request $r) {
        if( $r->ajax()) {
            if( $r->get('checked_orders') ) {
                $orders = Order::whereIn('id',$r->get('checked_orders'))->get();

                if( $orders->count() ) {

                    foreach ($orders as $order) {

                        $order->status = 6;
                        $order->delivered_at = date('Y-m-d');

                        $this->sendReminderNotification($order->user_id,$order->id,'order_arrived', null, $order->order_number);
                        $order->status = 6;
                        $order->save();

                        $order->order_product()->update(array('status' => 6));
                    }
                }
            }
        }
    }

    public function sendReminderNotification ($user_id, $subject_id, $type, $status, $order_no) {
        $title = json_encode( ['type' => $type, 'market'=>'sos', 'status' => $status,'order_no' => $order_no] );
        $notification = Notification::create([
            'title' => $title,
            'notification_type' => '14',
            'from_user_id' => Auth::user()->id,
            'to_user_id' => $user_id,
            'subject_key' => $subject_id
        ]);
        $controller_push_noti = new PushNotificationController();
        $controller_push_noti->pushNotification($notification->id);
    }
}
