<?php

namespace App\Http\Controllers\RootAdmin\MarketPlace\SOS;

use App\SOSPromotion;
use Auth;
use DB;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\ManagementGroup;
use App\PropertyUnit;
use App\Property;
use App\SOSOrderPaymentFile;


class SinghaOnlineShopController extends Controller
{

    protected $status_col = [
        'created_at','payment_at','approved_1_at'
        ,'approved_2_at','delivery_cut_off_at','delivery_at'
        ,'delivered_at','received_at'
    ];

    public function __construct () {
        if( Auth::check() && Auth::user()->role !== 0 ) {
            Redirect::to('feed')->send();
        }
        $this->middleware('auth');
    }

    public function adminBoard($status = 0, Request $r)
    {
        if( $r->get('order-status') ) $status = $r->get('order-status');

        $this->searchOrder($status, $r);
        $this->setDateColumn();

        if($r->ajax()) {
            return view('root-admin.market_place.SOS.singha_root_admin_board_page_body')->with(compact('orders','product_sum','from_date','to_date','property','d_group','r','status'));
        } else {

            $mg['-'] = 'กลุ่มผู้พัฒนา';
            $mg_ = ManagementGroup::lists('name', 'id');
            if( $mg_ ) {
                $mg += $mg_->toArray();
            }

            $p_list = ['' => 'ชื่อโครงการ'];
            $p_list_ = Property::where('is_demo', false);
            $p_list_ = $p_list_->whereHas('feature', function ($q) {
                $q->where('market_place_singha', true);
            });
            $p_list_ = $p_list_->lists('property_name_th','id');

            if( $p_list_ ) {
                $p_list += $p_list_->toArray();
            }
            return view('root-admin.market_place.SOS.singha_root_admin_board')->with(compact('orders','product_sum','p_list','mg','from_date','to_date','property','d_group','r','status'));
        }
    }

    public function adminOrderPage(Request $r)
    {
        $orders = Order::with('order_product','property_unit');
        $status = $r->get('order-status');
        if( $status == 0) {
            $target_date_col_1  = $this->status_col[0];
        } else {
            $target_date_col_1  = $this->status_col[$status - 1];
            $target_date_col_2  = $this->status_col[$status];
        }

        if( $r->get('order-no') ) {
            $orders = $orders->where('order_number','like',"%".$r->get('order-no')."%");
        }

        if($r->get('property')) {
            $orders = $orders->where('property_id',$r->get('property'));

        } else if($r->get('developer_group') && $r->get('developer_group') != '-') {
            $orders = $orders->where('developer_group',$r->get('developer_group'));
        }

        if( $r->get('start-order-date-1') ) {
            $orders = $orders->where($target_date_col_1,'>=',$r->get('start-order-date-1')." 00:00:00");
        }

        if( $r->get('end-order-date-1') ) {
            $orders = $orders->where($target_date_col_1,'<=',$r->get('end-order-date-1')." 23:59:59");
        }

        if( $r->get('start-order-date-2') ) {
            $orders = $orders->where($target_date_col_2,'>=',$r->get('start-order-date-2')." 00:00:00");
        }

        if( $r->get('end-order-date-2') ) {
            $orders = $orders->where($target_date_col_2,'<=',$r->get('end-order-date-2')." 23:59:59");
        }

        if( $r->get('order-status') != '-' && $r->get('order-status') != null) {
            $orders = $orders->where('status',$r->get('order-status'));
        }

        if( $r->get('payment_type') ) {
            $orders = $orders->where('payment_type',$r->get('payment_type'));
        }

        if( $r->get('promo_code') ) {
            $orders = $orders->where('promotion_id',$r->get('promo_code'));
        }

        if( $r->get('receipt_status') ) {
            if( $r->get('receipt_status') == 1 ) {
                $orders = $orders->whereNull('receipt_date');
            } else {
                if( $r->get('receipt_date') ) {
                    $receipt_date = str_replace('/','-',$r->get('receipt_date'));
                    $orders = $orders->whereBetween('receipt_date', [$receipt_date." 00:00:00",$receipt_date." 23:59:59"]);
                } else {
                    $orders = $orders->whereNotnull('receipt_date');
                }
            }
        }

        if( $r->get('order_name') ) {
            $orders = $orders->whereHas('order_by', function ($q) use ($r) {
                $q->where('name','like','%'.$r->get('order_name').'%');
            });
        }

        if( $r->get('phone') ) {
            $orders = $orders->whereHas('order_by', function ($q) use ($r) {
                $q->where('phone','like','%'.$r->get('phone').'%');
            });
        }

        $orders = $orders->paginate(30);
        $this->setDateColumn();

        if( $r->get('order-status') != '-' && $r->get('order-status') != null) {
            $status = $r->get('order-status');
        }

        return view('root-admin.market_place.SOS.admin-order-body')->with(compact('orders','status'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function setDateColumn ()
    {
        $col_date = [
            'created_at','payment_at','approved_1_at'
            ,'approved_2_at','delivery_cut_off_at','delivery_at'
            ,'delivered_at','received_at'
        ];
        $p_code = ['0' => 'การสั่งซื้อทั้งหมด'];
        $promo = SOSPromotion::lists('name', 'id');
        if( $promo ) {
            $p_code += $promo->toArray();
        }
        view()->share(compact('p_code'));
        view()->share(compact('col_date'));
    }


    public function printOrder(Request $r)
    {
        $status = $r->get('order-status');
        $this->searchOrder($status, $r);
        $this->setDateColumn();
        return view('root-admin.market_place.SOS.singha_root_admin_order_print')->with(compact('status'));
    }


    public function searchOrder ($status, $r) {

        $orders = Order::with('order_product','property_unit')->where('status',$status);
        $product_sum = new OrderProduct;

        if( $status == 0) {
            $target_date_col_1  = $this->status_col[0];
        } else {
            $target_date_col_1  = $this->status_col[$status - 1];
            $target_date_col_2  = $this->status_col[$status];
        }


        $from_date = $to_date = $from_date_2 = $to_date_2 = $d_group = $property = "";
        if( $r->get('order-no') ) {
            $orders = $orders->where('order_number','like',"%".$r->get('order-no')."%");
            $product_sum = $product_sum->whereHas('in_order', function ($q) use ($r) {
                $q->where('order_number','like',"%".$r->get('order-no')."%");
            });
        }

        if($r->get('property')) {
            $orders = $orders->where('property_id',$r->get('property'));
            $product_sum = $product_sum->where('property_id',$r->get('property'));

            $property = Property::find($r->get('property'));
            if($property && $property->developer_group_id)
                $d_group = ManagementGroup::find($property->developer_group_id);

        } else if($r->get('developer_group') && $r->get('developer_group') != '-') {
            $d_group = ManagementGroup::find($r->get('developer_group'));
            $orders = $orders->where('developer_group', $r->get('developer_group'));
            $product_sum = $product_sum->where('developer_group', $r->get('developer_group'));
        }

        if( $r->get('start-order-date-1') ) {
            $from_date = str_replace('/','-',$r->get('start-order-date-1'));
            $orders = $orders->where($target_date_col_1, '>=', $from_date." 00:00:00");
            $product_sum = $product_sum->whereHas('in_order', function ($q) use ($target_date_col_1, $from_date) {
                $q->where($target_date_col_1, '>=', $from_date." 00:00:00");
            });
        }

        if( $r->get('end-order-date-1') ) {
            $to_date = str_replace('/','-',$r->get('end-order-date-1'));
            $orders = $orders->where($target_date_col_1, '<=', $to_date." 23:59:59");
            $product_sum = $product_sum->whereHas('in_order', function ($q) use ($target_date_col_1, $to_date) {
                $q->where($target_date_col_1, '<=', $to_date." 23:59:59");
            });
        }

        if( $r->get('start-order-date-2') ) {
            $from_date_2 = str_replace('/','-',$r->get('start-order-date-2'));
            $orders = $orders->where($target_date_col_2, '>=', $from_date_2." 00:00:00");
            $product_sum = $product_sum->whereHas('in_order', function ($q) use ($target_date_col_2, $from_date_2) {
                $q->where($target_date_col_2, '>=', $from_date_2." 00:00:00");
            });
        }

        if( $r->get('end-order-date-2') ) {
            $to_date_2 = str_replace('/','-',$r->get('end-order-date-2'));
            $orders = $orders->where($target_date_col_2,'<=', $to_date_2." 23:59:59");
            $product_sum = $product_sum->whereHas('in_order', function ($q) use ($target_date_col_2, $to_date_2) {
                $q->where($target_date_col_2, '<=', $to_date_2." 23:59:59");
            });
        }

        if( $r->get('order-status') != '-' && $r->get('order-status') != null) {
            $orders = $orders->where('status',$r->get('order-status'));
            $product_sum = $product_sum->where('status',$r->get('order-status'));
        }

        if( $r->get('payment_type') ) {
            $pt = $r->get('payment_type');
            $orders = $orders->where('payment_type',$r->get('payment_type'));
            $product_sum = $product_sum->whereHas('in_order', function ($q) use ($pt) {
                $q->where('payment_type',$pt);
            });
        }

        if( $r->get('promo_code') ) {
            $pr = $r->get('promo_code');
            $orders = $orders->where('promotion_id',$r->get('promo_code'));
            $product_sum = $product_sum->whereHas('in_order', function ($q) use ($pr) {
                $q->where('promotion_id',$pr);
            });
        }

        if( $r->get('receipt_status') ) {
            if( $r->get('receipt_status') == 1 ) {
                $orders = $orders->whereNull('receipt_date');
                $product_sum = $product_sum->whereHas('in_order', function ($q) {
                    $q->whereNull('receipt_date');
                });
            } else {
                if( $r->get('receipt_date') ) {
                    $receipt_date = str_replace('/','-',$r->get('receipt_date'));
                    $orders = $orders->whereBetween('receipt_date', [$receipt_date." 00:00:00",$receipt_date." 23:59:59"]);
                    $product_sum = $product_sum->whereHas('in_order', function ($q) use ($receipt_date) {
                        $q->whereBetween('receipt_date', [$receipt_date." 00:00:00",$receipt_date." 23:59:59"]);
                    });
                } else {
                    $orders = $orders->whereNotnull('receipt_date');
                    $product_sum = $product_sum->whereHas('in_order', function ($q) {
                        $q->whereNotnull('receipt_date');
                    });
                }
            }
        }

        if( $r->get('order_name') ) {
            $orders = $orders->whereHas('order_by', function ($q) use ($r) {
                $q->where('name','like','%'.$r->get('order_name').'%');
            });
            $product_sum = $product_sum->whereHas('in_order.order_by', function ($q) use ($r) {
                $q->where('name','like','%'.$r->get('order_name').'%');
            });
        }

        if( $r->get('phone') ) {
            $orders = $orders->whereHas('order_by', function ($q) use ($r) {
                $q->where('phone','like','%'.$r->get('phone').'%');
            });
            $product_sum = $product_sum->whereHas('in_order.order_by', function ($q) use ($r) {
                $q->where('phone','like','%'.$r->get('phone').'%');
            });
        }

        if( !$r->get('no-paginate'))
            $orders         = $orders->paginate(30);
        else
            $orders         = $orders->get();

        $product_sum    = $product_sum->select(DB::raw('product_name, unit, price, SUM(total) as total_sales,SUM(quantity) as quantity_sales'))
            ->groupBy('sos_product_id','product_name','unit','price')
            ->whereHas('in_order', function ($q) use ($status) {
                $q->where('status',$status);
            })
            ->get();

        view()->share(compact('orders','product_sum','from_date','to_date','d_group','property','r','from_date_2','to_date_2'));
    }

    public function generateMockupOrder () {
        $loop_order = 60;

        $promotion = SOSPromotion::get();

        $promo_available = $promotion->count();
        if( $promo_available ) { $promo_array = $promotion->toArray(); }

        $property_id =  '62a92f34-cdd4-445f-8d2b-29617f4593bb'; //หมู่บ้านจัดสรร เดอะ แกรนด์ วงแหวน - ประชาอุทิศ // group 5
        //$property_id =  '64b94e67-d515-4007-af1e-220fc8211c35'; //นิติบุคคลอาคารชุดเลควิวคอนโดมิเนียมอาคารริเวียร่า // group 5
        //$property_id =  'ef26b787-28b5-4a51-b374-ba8e6818bf04'; //นหมู่บ้านจัดสรร เออร์เบิ้น สาธร มาร์โคโปโล// group 4
        //$property_id =  '9c3f1af5-42a3-40cb-a550-423449bbbb05'; //อาคารชุด ศุภาลัย วิสต้า แยกติวานนท์// group CP
        $property = Property::find($property_id);

        $product = [
            0 => [
                'name'  => 'น้ำดื่มสิงห์ขวดเพ็ท ขนาด 1,500 ซีซี.',
                'price' => 60.00,
                'unit'  => 'แพ็ค',
                'id'    => 1
            ],
            1 => [
                'name' => 'ข้าวขาว 100% พันดี 5 Kg (น้ำเงิน)',
                'price' => 94.00,
                'unit'  => 'ถุง',
                'id'    => 2
            ],
            2 => [
                'name' => 'น้ำแร่เพอร์ร่า 600 มล.',
                'price' => 102.00,
                'unit'  => 'แพ็ค',
                'id'    => 3
            ],
            3 => [
                'name' => 'ข้าวหอมมะลิพันดี 5 Kg (ชมพู)',
                'price' => 195.00,
                'unit'  => 'ถุง',
                'id'    => 4
            ]
        ];

        for ($i = 0; $i < $loop_order; $i++ ) {

            $property_unit = PropertyUnit::whereHas('normalUser', function ($q) {
                $q->where('role', 2);
            })->where('property_id', $property_id)->orderBy(DB::raw('random()'))->first();

            $order = new Order;
            $order->timestamps = false;
            $create = rand(10, date('t'));
            $order->order_number = "NBSO" . rand(100000, 999999);
            $order->sos_user_id = "";
            $order->user_id = $property_unit->normalUser->first()->id;
            $order->property_unit_id = $property_unit->id;
            $order->property_id = $property->id;
            $order->developer_group = $property->developer_group_id;
            $order->zone_id = $property->sos_zone_id;
            $order->vat = 0;
            $order->status = rand(0, 7);
            $order->created_at = date('Y-05-' . $create);
            $order->updated_at = $order->created_at;
            $to_time_create = strtotime($order->created_at);

            // rand payment method
            $chance = rand(0, 4);
            if ($chance > 3) {
                $order->payment_type = 1;
            } else {
                $order->payment_type = 2;
            }

            if ($order->status > 0) {

                $order->payment_at = date('Y-m-d', strtotime('+1 days', $to_time_create));
                if ($order->status >= 2) {
                    $order->approved_1_at = date('Y-m-d', strtotime('+2 days', $to_time_create));
                }
                if ($order->status >= 3) {
                    $order->approved_2_at = date('Y-m-d', strtotime('+3 days', $to_time_create));
                }
                if ($order->status >= 4) {
                    $order->delivery_cut_off_at = date('Y-m-d', strtotime('+4 days', $to_time_create));
                }
                if ($order->status >= 5) {
                    $order->delivery_at = date('Y-m-d', strtotime('+5 days', $to_time_create));
                }
                if ($order->status >= 6) {
                    $order->delivered_at = date('Y-m-d', strtotime('+6 days', $to_time_create));
                }
                if ($order->status == 7) {
                    $order->received_at = date('Y-m-d', strtotime('+7 days', $to_time_create));
                }
            }
            $order->save();
            $order->total = 0;

            $loop_product = rand(1, 4);
            $added_product = [];

            for ($j = 0; $j < $loop_product; $j++) {

                // random product amount in order
                $rand_product = rand(0, 3);
                while (in_array($rand_product, $added_product)) {
                    $rand_product = rand(0, 3);
                }

                $added_product[] = $rand_product;
                $product_target = $product[$rand_product];

                $order_p = new OrderProduct;
                $order_p->timestamps = false;
                // random quantity
                $quantity = rand(1, 4);

                $order_p->order_id = $order->id;
                $order_p->sos_product_id = "";
                $order_p->product_category = "";
                $order_p->sos_user_id = "";
                $order_p->quantity = $quantity;
                $order_p->price = $product_target['price'];
                $order_p->total = $quantity * $product_target['price'];
                $order_p->user_id = $order->user_id;
                $order_p->property_unit_id = $order->property_unit_id;
                $order_p->property_id = $order->property_id;
                $order_p->developer_group = $order->developer_group;
                $order_p->zone_id = $order->zone_id;
                $order_p->ordering = $i;
                $order_p->status = $order->status;
                $order_p->product_name = $product_target['name'];
                $order_p->unit = $product_target['unit'];
                $order_p->sos_product_id = $product_target['id'];
                $order_p->created_at = $order_p->updated_at = $order->created_at;
                $order_p->save();

                $order->total += $order_p->total;
            }

            $order->grand_total = $order->total;

            if ($promo_available) {
                $chance = rand(0, 4);
                // chance to use promotion is 50% off all orders
                if ($chance >= 3) {
                    // random promotion
                    $promotion_index = rand(0, $promo_available - 1);

                    $promo = $promo_array[$promotion_index];
                    $order->promotion_id = $promo['id'];

                    if ($promo['discount_type'] == 'A') {
                        $order->grand_total -= $promo['discount_value'];
                        $order->discount = $promo['discount_value'];
                    } else {
                        $dc = ($promo['discount_value'] * $order->grand_total / 100);
                        $order->grand_total -= $dc;
                        $order->discount = $dc;
                    }
                }
            }

            if ($order->status > 0) {
                if ($order->payment_type == 1) {
                    $file = new SOSOrderPaymentFile;
                    $file->order_id = $order->id;
                    $file->file_type = 'image/jpeg';
                    $file->url = '3f/';
                    $file->name = '3f1f865ff24fdad451ed9e069569771c.jpg';
                    $file->save();
                } else {
                    $order->payment_ref_no = "RE-" . rand(10000, 99999);
                }
            }
            $order->save();
        }
    }
}
