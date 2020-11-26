<?php namespace App\Http\Controllers\API;
use App\OrderProduct;
use Request;
use Illuminate\Routing\Controller;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use Carbon\Carbon;
# Model
use App\Notification;
use App\User;
use App\Order;
use App\CheckFirstOrderSinghaOnline;
use App\SOSPromotion;
use App\SOSPromotionProperty;
use App\SOSOrderPaymentFile;

use Auth;
use File;
use View;
class SinghaOnlineController extends Controller {

    public function __construct () {
        //$this->middleware('jwt.feature_menu:market_place_singha');
    }
    public function index()
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', 'https://api.github.com/repos/guzzle/guzzle');
        echo $res->getStatusCode();

        $results = [
            'results' => $res->getStatusCode()
        ];

        return response()->json($results);
    }

    /**
     * @api {post} /singha/login 01.Login Singha User
     * @apiName loginSinghaUser
     * @apiGroup User
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token from NABOUR login}
     *
     * @apiParam {string} email Email Users
     * @apiParam {string} password Password Users
     *
     * @apiSuccess {boolean} status Status Boolean
     * @apiSuccess {string} msg  Return Message
     * @apiSuccessExample {json} Success-Response:
     *      {
     *          "status": true,
     *          "msg": "Login Success"
     *      }
     *      หรือ
     *      {
     *          "status": false,
     *          "msg": "Invalid username or password"
     *      }
     */
    public function login()
    {
        try {
            if (Request::isMethod('post')) {
                $email = Request::get('email');
                $password = Request::get('password');

                $client = new \GuzzleHttp\Client();
                $function = "login";
                $url = env('SINGHA_ONLINE_URL').$function."&email=".$email."&password=".$password;
                $res = $client->request('GET', $url);
                $body = $res->getBody();
                $obj = json_decode($body);

                if($obj->result){
                    $user = User::find(Auth::user()->id);

                    $user->sos_user_id = $obj->id_customer;
                    $user->save();
                    Auth::loginUsingId($user->id);
                    Request::session()->put('sos_user_id', Auth::user()->sos_user_id);

                    $results = [
                        'status'=>true,
                        'msg' => $obj->message
                    ];
                }else{
                    $results = [
                        'status'=>false,
                        'msg' => $obj->message
                    ];
                }
            }else{
                return response()->json(['status' => false, 'msg'=> 'Method is Wrong']);
            }
            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    /**
     * @api {post} /singha/register 02.Register Singha User
     * @apiName registerSinghaUser
     * @apiGroup User
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token from NABOUR login}
     *
     * @apiParam {string} email Email Users
     * @apiParam {string} password Password Users
     * @apiParam {string} first_name Firstname Users
     * @apiParam {string} last_name Lastname Users
     * @apiParam {string} birthdate Birthdate Users (รูปแบบเป็น “1998-12-24”)
     * @apiParam {string} phone_mobile Phone Mobile Users
     *
     * @apiSuccess {boolean} status Status Boolean
     * @apiSuccess {string} msg  Return Message
     * @apiSuccessExample {json} Success-Response:
     *      {
     *          "status": true,
     *          "msg": "Login Success"
     *      }
     *      หรือ
     *      {
     *          "status": false,
     *          "msg": "E-mail already exist."
     *      }
     */
    public function register()
    {
        try{
            if (Request::isMethod('post')) {
                // email,password,first_name,last_name,birthdate,phone_mobile
                $email = Request::get('email');
                $password_prm = Request::get('password');
                $firstname = Request::get('first_name');
                $lastname = Request::get('last_name');
                $birthday = Request::get('birthdate');
                $phone = ""; //optional
                $phone_mobile = Request::get('phone_mobile');
                $alias = ""; //optional
                $company = ""; //optional
                $postcode = Auth::user()->property->postcode;
                $other = ""; //optional
                $latitude = "";
                $longitude = "";
                $province_id = Auth::user()->property->province;
                $district1_id = Auth::user()->property->amphoe_id;
                $district2_id = Auth::user()->property->tambon_id;

                $address = "";
                if(Auth::user()->property_unit->building){
                    $building = " ตึก ".Auth::user()->property_unit->building." ";
                }else{
                    $building = " ";
                }

                //Soi
                if(Auth::user()->property_unit->unit_soi != null || Auth::user()->property_unit->unit_soi != ""){
                    $soi = " ซอย ".Auth::user()->property_unit->unit_soi;
                }else{
                    $soi = " ";
                }

                $address = "เลขที่ ".Auth::user()->property_unit->unit_number.$building.$soi.Auth::user()->property->property_name_th;

                $client = new \GuzzleHttp\Client();
                $function = "register_customer";
                //email=suttipong9@hexacoda.com&password_prm=123456&firstname=man&lastname=testlastname&birthday=1988-12-14&phone=&phone_mobile=0884339217&alias=&firstname=suttipong1&lastname=jinadech2&company=&address1=123 หมู่7&postcode=51130&other=&latitude=1.234&longitude=0.55&province_id=51&district1_id=5103&district2_id=510301
                $url = env('SINGHA_ONLINE_URL').$function.
                    "&email=".$email.
                    "&password_prm=".$password_prm.
                    "&firstname=".$firstname.
                    "&lastname=".$lastname.
                    "&birthday=".$birthday.
                    "&phone=".$phone.
                    "&phone_mobile=".$phone_mobile.
                    "&alias=".$alias.
                    "&firstname=".$firstname.
                    "&lastname=".$lastname.
                    "&company=".$company.
                    "&address1=".$address.
                    "&postcode=".$postcode.
                    "&other=".$other.
                    "&latitude=".$latitude.
                    "longitude=".$longitude.
                    "&province_id=".$province_id.
                    "&district1_id=".$district1_id.
                    "&district2_id=".$district2_id;

                $res = $client->request('GET', $url);
                $body = $res->getBody();
                $obj = json_decode($body);

                if($obj->result_Action == "1"){
                    $client = new \GuzzleHttp\Client();
                    $function = "login";
                    $url = env('SINGHA_ONLINE_URL').$function."&email=".$email."&password=".$password_prm;
                    $res = $client->request('GET', $url);
                    $body = $res->getBody();
                    $obj = json_decode($body);

                    $user = User::find(Auth::user()->id);

                    $user->sos_user_id = $obj->id_customer;
                    $user->save();
                    Auth::loginUsingId($user->id);
                    Request::session()->put('sos_user_id', Auth::user()->sos_user_id);

                    $results = [
                        'status'=>true,
                        'msg' => $obj->message
                    ];
                }else{
                    $results = [
                        'status'=>false,
                        'msg' => $obj->message
                    ];
                }

            }else{
                return response()->json(['status' => false, 'msg'=> 'Method is Wrong']);
            }

            return response()->json($results);
        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    //############################### PRODUCT ################################################
    public function getAllCategory()
    {
        try {
            $client = new \GuzzleHttp\Client();
            // category_level1
            $function = "category_level1";
            $url = env('SINGHA_ONLINE_URL') . $function;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_category_level1 = json_decode($body);

            $category_id_arr = "";
            foreach ($obj_category_level1 as $item) {
                if (!in_array($item->id_category, [4, 79])) {
                    $category_id_arr[] = $item->id_category;
                }
            }

            $results = [
                'results' => $category_id_arr
            ];

            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    public function getAllSosCategory()
    {
        try {
            $client = new \GuzzleHttp\Client();
            // category_level1
            $function = "category_level1";
            $url = env('SINGHA_ONLINE_URL') . $function;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_category_level1 = json_decode($body);

            $category_id_arr = [];
            foreach ($obj_category_level1 as $item) {
                if (!in_array($item->id_category, [4, 79])) {
                    $category_id_arr[] = $item;
                }
            }

            $results = [
                'results' => $category_id_arr
            ];

            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    public function getAllSosBrandByCategory()
    {
        try {
            $client = new \GuzzleHttp\Client();

            // category_level2
            //http://demo.singhaonlineshop.com/2015/api/standard?Authorization=b4449e83beabfc35120df730e93ea740&call_func=category_level2&id_category=11
            $function_2 = "category_level2";

            $all_brand = [];
            $cate_id = Request::get('category_id');

            //$client = new \GuzzleHttp\Client();
            $condition = "&id_category=" . $cate_id;
            $url = env('SINGHA_ONLINE_URL') . $function_2 . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_category_level2 = json_decode($body);
            //$all_category[] = $obj_category_level2;
            foreach ($obj_category_level2 as $product_category) {
                $all_brand[] = $product_category;
            }

            $results = [
                'results' => $all_brand
            ];

            return response()->json($results);
        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    public function getAllSosProductByBrandCategoryId()
    {
        try {
            $client = new \GuzzleHttp\Client();

            $all_product = [];
            $id_cate = Request::get('brand_cate_id'); //GET


            $function_3 = "product_list";
            $condition = "&id_category=" . $id_cate;
            $url = env('SINGHA_ONLINE_URL') . $function_3 . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);
            foreach ($obj_product as $product) {
                $all_product[] = $product;
            }

            $results = [
                'results' => $all_product
            ];

            return response()->json($results);
        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    public function getAllBrand()
    {
        try {
            $client = new \GuzzleHttp\Client();

            $function = "category_level1";
            $url = env('SINGHA_ONLINE_URL') . $function;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_category_level1 = json_decode($body);

            $category_id_arr = "";
            foreach ($obj_category_level1 as $item) {
                if (!in_array($item->id_category, [4, 79])) {
                    $category_id_arr[] = $item->id_category;
                }
            }

            // category_level2
            //http://demo.singhaonlineshop.com/2015/api/standard?Authorization=b4449e83beabfc35120df730e93ea740&call_func=category_level2&id_category=11
            $function_2 = "category_level2";

            $all_brand = "";
            foreach ($category_id_arr as $cate_id) {
                //$client = new \GuzzleHttp\Client();
                $condition = "&id_category=" . $cate_id;
                $url = env('SINGHA_ONLINE_URL') . $function_2 . $condition;
                $res = $client->request('GET', $url);
                $body = $res->getBody();
                $obj_category_level2 = json_decode($body);
                //$all_category[] = $obj_category_level2;
                foreach ($obj_category_level2 as $product_category) {
                    if (!in_array($product_category->id_category, [17])) {
                        $all_brand[] = $product_category->id_category;
                    }
                }
            }

            $results = [
                'results' => $all_brand
            ];

            return response()->json($results);
        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    public function getAllProduct()
    {
        try{
            // category_level1
            $client = new \GuzzleHttp\Client();
            $function = "category_level1";
            $url = env('SINGHA_ONLINE_URL') . $function;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_category_level1 = json_decode($body);

            $category_id_arr = "";
            foreach ($obj_category_level1 as $item) {
                if (!in_array($item->id_category, [4, 79])) {
                    $category_id_arr[] = $item->id_category;
                }
            }
            //$category_id_arr = [11,5,6,14,70];

            // category_level2
            //http://demo.singhaonlineshop.com/2015/api/standard?Authorization=b4449e83beabfc35120df730e93ea740&call_func=category_level2&id_category=11
            $function_2 = "category_level2";

            foreach ($category_id_arr as $cate_id) {
                //$client = new \GuzzleHttp\Client();
                $condition = "&id_category=" . $cate_id;
                $url = env('SINGHA_ONLINE_URL') . $function_2 . $condition;
                $res = $client->request('GET', $url);
                $body = $res->getBody();
                $obj_category_level2 = json_decode($body);
                //$all_category[] = $obj_category_level2;
                foreach ($obj_category_level2 as $product_category) {
                    $all_category[] = $product_category->id_category;
                }
                $aaaa = "";
            }

            // Product_list
            ////http://demo.singhaonlineshop.com/2015/api/standard?Authorization=b4449e83beabfc35120df730e93ea740&call_func=product_list&id_category=11
            foreach ($all_category as $id_cate) {
                $function_3 = "product_list";
                $condition = "&id_category=" . $id_cate;
                $url = env('SINGHA_ONLINE_URL') . $function_3 . $condition;
                $res = $client->request('GET', $url);
                $body = $res->getBody();
                $obj_product = json_decode($body);
                foreach ($obj_product as $product) {
                    $all_product[] = $product;
                }
            }
            $results = [
                'results' => $all_product
            ];
            return response()->json($results);
            $adasd = "";
        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    public function getProductFirstFeed()
    {
        try{
            $client = new \GuzzleHttp\Client();
            $cate_id = 17; // Singha Water Drink

            // Product_list
            ////http://demo.singhaonlineshop.com/2015/api/standard?Authorization=b4449e83beabfc35120df730e93ea740&call_func=product_list&id_category=11
            $all_product = "";

            $function_3 = "product_list";
            $condition = "&id_category=" . $cate_id;
            $url = env('SINGHA_ONLINE_URL') . $function_3 . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);

            foreach ($obj_product as $product) {
                $all_product[] = $product;
            }

            $results = [
                'results' => $all_product
            ];
            return response()->json($results);
        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    /**
     * @api {get} /singha/product-feed 01.Get Product List
     * @apiName getProductSinghaOnline
     * @apiGroup Product
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token from NABOUR login}
     *
     * @apiSuccess {string} results Return Product List
     * @apiSuccessExample {json} Success-Response:
      {
        "results": [
            {
                "id_product": 82,
                "id_attribute": 163,
                "name": "น้ำดื่มเพ็ทเล็ก 330 ซีซี.",
                "url_img": "http://demo.singhaonlineshop.com/2015/assets/images/products/82-656-thickbox.png",
                "price": "48",
                "org_price": "48",
                "specific_limit_quantity": 0,
                "size": "1x12",
                "show_size": 1,
                "stock": 1283,
                "vat": 0,
                "alertmessage": ""
            },
            {
                "id_product": 694,
                "id_attribute": 1136,
                "name": "น้ำดื่มสิงห์ขวดเพ็ท ขนาด 600 ซีซี.",
                "url_img": "http://demo.singhaonlineshop.com/2015/assets/images/products/694-1299-thickbox.png",
                "price": "60",
                "org_price": "60",
                "specific_limit_quantity": 0,
                "size": "1x12",
                "show_size": 1,
                "stock": 5563,
                "vat": 0,
                "alertmessage": ""
            },
        ]
      }
    */
    public function getFeedProduct()
    {
        try{
            // category_level1
            $client = new \GuzzleHttp\Client();
            $function = "category_level1";
            $url = env('SINGHA_ONLINE_URL') . $function;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_category_level1 = json_decode($body);

            $category_id_arr = "";
            foreach ($obj_category_level1 as $item) {
                if (!in_array($item->id_category, [4, 5, 6, 70, 79])) {
                    $category_id_arr[] = $item->id_category;
                }
            }
            //$category_id_arr = [11,5,6,14,70];

            // category_level2
            //http://demo.singhaonlineshop.com/2015/api/standard?Authorization=b4449e83beabfc35120df730e93ea740&call_func=category_level2&id_category=11
            $function_2 = "category_level2";
            $all_category = array();
            foreach ($category_id_arr as $cate_id) {
                //$client = new \GuzzleHttp\Client();
                $condition = "&id_category=" . $cate_id;
                $url = env('SINGHA_ONLINE_URL') . $function_2 . $condition;
                $res = $client->request('GET', $url);
                $body = $res->getBody();
                $obj_category_level2 = json_decode($body);
                //$all_category[] = $obj_category_level2;
                foreach ($obj_category_level2 as $product_category) {
                    if (!in_array($product_category->id_category, [17,43,89])) {
                        $all_category[] += $product_category->id_category;
                    }
                }
            }
            array_unshift($all_category,17);

            $all_product = "";
            // Product_list
            ////http://demo.singhaonlineshop.com/2015/api/standard?Authorization=b4449e83beabfc35120df730e93ea740&call_func=product_list&id_category=11
            foreach ($all_category as $id_cate) {
                $function_3 = "product_list";
                $condition = "&id_category=" . $id_cate;
                $url = env('SINGHA_ONLINE_URL') . $function_3 . $condition;
                $res = $client->request('GET', $url);
                $body = $res->getBody();
                $obj_product = json_decode($body);
                foreach ($obj_product as $product) {
                    if($id_cate == 17){
                        if(!in_array($product->id_product, [398, 379, 394])){
                            $all_product[] = $product;
                        }
                    }else{
                        $all_product[] = $product;
                    }
                }
            }
            $results = [
                'results' => $all_product
            ];
            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    public function getProductByCategory($id)
    {
        try{
            $client = new \GuzzleHttp\Client();
            $cate_id = $id;

            // Product_list
            ////http://demo.singhaonlineshop.com/2015/api/standard?Authorization=b4449e83beabfc35120df730e93ea740&call_func=product_list&id_category=11
            $all_product = "";

            $function_3 = "product_list";
            $condition = "&id_category=" . $cate_id;
            $url = env('SINGHA_ONLINE_URL') . $function_3 . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);

            foreach ($obj_product as $product) {
                $all_product[] = $product;
            }

            $results = [
                'results' => $all_product
            ];
            return response()->json($results);
        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    /**
     * @api {post} /singha/product-detail 02.Get Product Details
     * @apiName getProductDetails
     * @apiGroup Product
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token from NABOUR login}
     *
     * @apiParam {string} id_product id_product (ได้มาจาก API ของ SinghaOnline Shop)
     * @apiParam {string} id_attribute id_attribute (ได้มาจาก API ของ SinghaOnline Shop)
     *
     * @apiSuccess {string} results  Return Product Detail
     * @apiSuccessExample {json} Success-Response:
     * {
        "results": {
            "id_product": 694,
            "id_attribute": 1136,
            "br_product_id": "1001270",
            "name": "น้ำดื่มสิงห์ขวดเพ็ท ขนาด 600 ซีซี.",
            "description": "น้ำดื่มบรรจุขวด PETขนาด 600 cc. บรรจุ 1 pack มี 12 ขวด\"ราคาพิเศษสำหรับวันนี้ ขอสงวนสิทธิ์เปลี่ยนแปลงราคาโดยไม่ต้องแจ้งให้ทราบล่วงหน้า\"",
            "url_img": "http://demo.singhaonlineshop.com/2015/assets/images/products/694-1299-thickbox.png",
            "price": "60",
            "org_price": "60",
            "specific_limit_quantity": 0,
            "size": "1x12",
            "show_size": 1,
            "stock": 5563,
            "vat": "Inc",
            "alertmessage": ""
        }
       }
     */
    public function getProductDetails(){
        try{
            $id_product = Request::get('id_product');
            $id_attribute = Request::get('id_attribute');
            $client = new \GuzzleHttp\Client();
            $function = "product_detail";
            $condition = "&id_product=".$id_product."&id_attribute=".$id_attribute;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);

            $str_remove_html = strip_tags($obj_product->description);
            //$str_backslash = stripslashes($str_remove_html);

            $string = preg_replace("/\s|&nbsp;/",' ',$str_remove_html);

            $obj_product->description = strip_tags($string);

            $results = [
                'results' => $obj_product
            ];

            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }
    //###############################################################################

    //############################## COUPON #################################################
    public function allCoupon(){
        try{
            //$id_discount = "";
            $client = new \GuzzleHttp\Client();
            $function = "coupon_discount";
            $condition = "&id_discount=";
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);
            $results = [
                'results' => $obj_product
            ];

            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    public function couponDetail(){
        try{
            $id_discount = Request::get('id_discount');
            $client = new \GuzzleHttp\Client();
            $function = "coupon_discount";
            $condition = "&id_discount=".$id_discount;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);
            $results = [
                'results' => $obj_product
            ];

            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }
    //###############################################################################

    // ############################ CREATE NEW CART #################################
    /**
     * @api {post} /singha/checkout-premium 03.Checkout Premium & First Order Discount
     * @apiName createCartAndCheckPromotion
     * @apiGroup Product
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token from NABOUR login}
     *
     * @apiParam {string} list_product ข้อมูลส่งมาในรูปแบบนี้ {ลำดับ}:{id_product}:{id_attribute}:{จำนวนที่สั่ง}:{ราคาต่อชิ้น}:{ราคารวม} เช่น 1:82:163:2:50:100;2:28:58:7:25:175;
     *
     * @apiSuccess {string} results ส่วนลดและของแถม
     * @apiSuccessExample {json} Success-Response:
        {
            "results": [
                {
                    "id_promo_product": 1498,
                    "id_promo": 1109,
                    "name_promo_product": "น้ำดื่มสิงห์ Pet 750 ซีซี ทุกๆ 5 แพ็ค แถม 1 แพ็ค",
                    "qty": 1,
                    "unit": "แพ็ค"
                }
            ],
            "discount": 40
        }
     */
    public function createCartAndCheckPromotion(){
        try{
            //$id_customer = Request::get('id_customer');
            $id_customer = Auth::user()->sos_user_id;
            $payment_type = Request::get('payment_type');
            $payment_status = Request::get('payment_status');
            /*$list_product = Request::get('list_product');*/

            $order_request = Request::get('list_product');
            $order_request_array = explode(";",rtrim($order_request, ";"));

            $list_product = "";
            foreach ($order_request_array as $order_for_check_premium) {
                $order_for_check_premium_arr_data = explode(":", $order_for_check_premium);
                $list_product .= $order_for_check_premium_arr_data[0].":".$order_for_check_premium_arr_data[1].":".$order_for_check_premium_arr_data[2].":".$order_for_check_premium_arr_data[3].";";
            }

            // CreateCart
            $client = new \GuzzleHttp\Client();
            $function = "new_cart";
            $condition = "&id_customer=".$id_customer."&payment_type="."&payment_status="."&list_product=".$list_product;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);

            $id_cart = $obj_product->id_cart;
            $cust_group = Request::get('cust_group') != "" ? Request::get('cust_group') : 1;

            // Promotion
            $client = new \GuzzleHttp\Client();
            $function = "promotion";
            $condition = "&id_customer=".$id_customer."&id_cart=".$id_cart."&cust_group=".$cust_group;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $promotion = json_decode($body);

            // Coupon Code First Order
            $check_first_order = CheckFirstOrderSinghaOnline::where('user_id',Auth::user()->id)->count();
            if($check_first_order > 0){
                $discount = 0;
            }else{
                $discount = 40;
            }

            $results = [
                'status' => true,
                'results' => $promotion,
                'discount' => $discount
            ];

            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }
    //###############################################################################

    // ############################ CHECK PROMOTION #################################
    public function checkPromotion(){
        try{
            $id_customer = Request::get('id_customer');
            $id_cart = Request::get('id_cart');
            $cust_group = Request::get('cust_group');

            // Promotion
            $client = new \GuzzleHttp\Client();
            $function = "promotion";
            $condition = "&id_customer=".$id_customer."&id_cart=".$id_cart."&cust_group=".$cust_group;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);

            // Coupon Code First Order
            $check_first_order = CheckFirstOrderSinghaOnline::where('user_id',Auth::user()->id)->count();
            if($check_first_order > 0){
                $discount = 0;
            }else{
                $discount = 40;
            }

            $results = [
                'status' => true,
                'results' => $obj_product,
                'discount' => $discount
            ];

            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    public function checkDiscountCoupon(){
        try{
            $coupon_str = Request::get('coupon');
            $price_total = Request::get('price_total');
            $promotion = SOSPromotion::where('code',$coupon_str)->get();
            $use_flag = false;
            $discount = 0;
            if($promotion->status == "A") {
                if (strtotime(date('Y-m-d')) > strtotime($promotion->expire_date)) {
                    // already expired
                } else {
                    if ($promotion->counter <= $promotion->limit) {
                        if($promotion->property_participation == "S"){ //ใช้บางโครงการ
                            $property_promotion = SOSPromotionProperty::where('promotion_id',$promotion->id)->where('property_id',Auth::user()->property_id)->count();
                            if($property_promotion > 0){
                                $use_flag = true;
                            }
                        }elseif($promotion->property_participation == "AE"){
                            $property_promotion = SOSPromotionProperty::where('promotion_id',$promotion->id)->where('property_id',Auth::user()->property_id)->count();
                            if($property_promotion == 0){
                                $use_flag = true;
                            }
                        }else{
                            $use_flag = true;
                        }
                    }
                }

                if($use_flag){
                    if($promotion->discount_type == "A"){
                        $discount = $price_total*($promotion->discount_value/100);
                    }else{
                        $discount = $promotion->discount_value;
                    }
                }
            }

            $results = [
                'discount' => $discount
            ];

            return response()->json($results);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }
    //###############################################################################

    // ############################ CREATE ORDER ####################################
    /**
     * @api {post} /singha/order 01.Order
     * @apiName createOrder
     * @apiGroup Order
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token from NABOUR login}
     *
     * @apiParam {string} list_product ข้อมูลส่งมาในรูปแบบนี้ {ลำดับ}:{id_product}:{id_attribute}:{จำนวนที่สั่ง}:{ราคาต่อชิ้น}:{ราคารวม} เช่น 1:82:163:2:50:100;2:28:58:7:25:175;
     * @apiParam {string} total ราคารวมของสินค้าที่สั่งซื้อทั้งหมด
     * @apiParam {string} discount ส่วนลด
     * @apiParam {string} grand_total ราคารวมสุทธิหลังหักส่วนลด
     *
     * @apiSuccess {string} results สถานะการสั่งซื้อ
     * @apiSuccessExample {json} Success-Response:
        {
        "status": true,
        "msg": "Order Success",
        "order_id": {order_id}
        }
     */
    public function createOrder(){
        try{

            $order_number = $this->getLastOrderNum() + 1;
            $order_running_no_str = $this->generateRunning($order_number);
            $order = new Order();
            $order->order_running_no = $order_number;
            $order->order_number = $order_running_no_str;
            $order->user_id = Auth::user()->id;
            $order->property_unit_id = Auth::user()->property_unit_id;
            $order->property_id = Auth::user()->property_id;
            $order->developer_group = Auth::user()->property->developer_group;
            $order->zone_id = Auth::user()->property->zone_id;
            $order->sos_user_id = Auth::user()->sos_user_id;
            $order->total = Request::get('total');
            $order->grand_total = Request::get('grand_total');
            //$order->coupon_code = Request::get('coupon_code');
            $order->discount = Request::get('discount');
            $order->status = 0; //New order (รอการชำระเงิน)
            $order->payment_type = 0;
            $order->vat = 0;
            $order->expires_at = Carbon::now()->addDays(3);
            $order->save();

            //$order_request = "1:82:163:2:50:100;2:28:58:7:25:175;" {ลำดับ}:{id_product}:{id_attribute}:{จำนวนที่สั่ง}:{ราคาต่อชิ้น}:{ราคารวม}
            $order_request = Request::get('list_product');
            $order_request_array = explode(";",rtrim($order_request, ";"));

            $list_product = "";
            foreach ($order_request_array as $order_for_check_premium) {
                $order_for_check_premium_arr_data = explode(":", $order_for_check_premium);
                $list_product .= $order_for_check_premium_arr_data[0].":".$order_for_check_premium_arr_data[1].":".$order_for_check_premium_arr_data[2].":".$order_for_check_premium_arr_data[3].";";
            }
            // CreateCart
            $client = new \GuzzleHttp\Client();
            $function = "new_cart";
            $condition = "&id_customer=" . Auth::user()->sos_user_id . "&payment_type=" . "&payment_status=" . "&list_product=" . $list_product;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);

            $id_cart = $obj_product->id_cart;
            $order->cart_id = $id_cart;
            $order->save();
            $cust_group = Request::get('cust_group') != "" ? Request::get('cust_group') : 1;

            // Promotion
            $client = new \GuzzleHttp\Client();
            $function = "promotion";
            $condition = "&id_customer=" . Auth::user()->sos_user_id . "&id_cart=" . $id_cart . "&cust_group=" . $cust_group;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $promotion = json_decode($body);

            $order_index = 0;

            foreach ($order_request_array as $order_item) {
                /*
                    id
                    order_id /
                    sos_product_id /
                    product_category
                    sos_user_id /
                    quantity /
                    price /
                    total /
                    user_id /
                    property_unit_id /
                    property_id /
                    developer_group /
                    zone_id /
                    ordering /
                    status /
                    created_at /
                    updated_at /
                    product_name /
                    unit
                    received_at
                    sos_attribute_id /
                */

                $order_arr_data = explode(":",$order_item);
                $sos_product_id = $order_arr_data[1];
                $sos_attribute_id = $order_arr_data[2];
                //$product_name = $this->getProductName($sos_product_id,$sos_attribute_id);
                $product_data = $this->getProductData($sos_product_id,$sos_attribute_id);
                $product_name = $product_data->name;
                $br_product_id = $product_data->br_product_id;

                $order_product = new OrderProduct();
                $order_product->order_id = $order->id;
                $order_product->sos_user_id = Auth::user()->sos_user_id;
                $order_product->sos_product_id = $order_arr_data[1];
                $order_product->sos_attribute_id = $order_arr_data[2];
                $order_product->quantity = $order_arr_data[3];
                $order_product->price = $order_arr_data[4];
                $order_product->product_name = $product_name;
                $order_product->ordering = $order_arr_data[0];
                $order_product->status =  0; //New order (รอการชำระเงิน)
                $order_product->total = $order_arr_data[5];
                $order_product->user_id = Auth::user()->id;
                $order_product->property_unit_id = Auth::user()->property_unit_id;
                $order_product->property_id = Auth::user()->property_id;
                $order_product->developer_group = Auth::user()->property->developer_group_id;
                $order_product->zone_id = Auth::user()->property->sos_zone_id;
                $order_product->is_promotion = false;
                $order_product->br_product_id = $br_product_id;
                $order_product->vat = ($order_arr_data[6] == 0) ? 0 : 7;

                $order_index = $order_arr_data[0];

                $order_product->save();
            }

            foreach ($promotion as $item_promotion){
                $order_index++;
                $order_product = new OrderProduct();
                $order_product->order_id = $order->id;
                $order_product->sos_user_id = Auth::user()->sos_user_id;
                $order_product->sos_product_id = null;
                $order_product->sos_attribute_id = null;
                $order_product->quantity = $item_promotion->qty;
                $order_product->price = 0;
                $order_product->product_name = $item_promotion->name_promo_product;
                $order_product->ordering = $order_index;
                $order_product->status =  0;
                $order_product->total = 0;
                $order_product->user_id = Auth::user()->id;
                $order_product->property_unit_id = Auth::user()->property_unit_id;
                $order_product->property_id = Auth::user()->property_id;
                $order_product->developer_group = Auth::user()->property->developer_group_id;
                $order_product->zone_id = Auth::user()->property->sos_zone_id;
                $order_product->is_promotion = true;

                $order_product->save();
            }

            return response()->json(['status' => true, 'msg'=> "Order Success", 'order_id'=>$order->id, 'cart_id'=>$id_cart]);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    // ############################ PAYMENT ORDER ####################################
    /**
     * @api {post} /singha/payment 04.Payment
     * @apiName paymentOrder
     * @apiGroup Order
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token from NABOUR login}
     *
     * @apiParam {string} order_id id การสั่งซื้อ
     * @apiParam {string} payment_type 1=> โอนผ่านธนาคาร , 2=>ชำระผ่านบัตรเครดิต
     * @apiParam {string} payment_ref_no ใช้ในกรณีชำระผ่านบัตรเครดิต , ถ้าโอนผ่านธนาคารใส่เป็นช่องว่าง
     * @apiParam {file} attachment[] ไฟล์หลักฐานที่แนบมา (ใช้ในกรณีโอนผ่านธนาคาร)
     *
     * @apiSuccess {string} results สถานะการสั่งซื้อ
     * @apiSuccessExample {json} Success-Response:
    {
    "status": true,
    "msg": "Payment Success"
    }
     */
    public function paymentOrder(){
        try{
            $order = Order::find(Request::get('order_id'));
            $order->payment_at = Carbon::now();
            $order->payment_type = Request::get('payment_type'); // 1:โอน, 2:ชำระผ่านบัตรออนไลน์
            $order->payment_ref_no = Request::get('payment_ref_no') != "" ? Request::get('payment_ref_no') : null; // ref_no กรณีจ่ายเงินผ่านบัตรเครดิต
            $order->status = 1; //Pending order (ยืนยันการชำระเงินแล้ว)
            $order->save();

            if($order->discount != null && $order->promotion_id == null){
                // used FirstOrder Discount Already
                $first_order = new CheckFirstOrderSinghaOnline();
                $first_order->user_id = Auth::user()->id;
                $first_order->save();

                $other_order = Order::whereNotNull('discount')->whereNull('promotion_id')->where('sos_user_id',Auth::user()->sos_user_id)->where('user_id',Auth::user()->id)->get();
                foreach ($other_order as $item_other_order){
                    $other_other_update = Order::find($item_other_order->id);
                    $other_other_update->grand_total = $other_other_update->total;
                    $other_other_update->discount = null;
                    $other_other_update->save();
                }
            }

            $order_product = OrderProduct::where('order_id', $order->id)->get();

            foreach ($order_product as $item) {
                $order_product = OrderProduct::find($item->id);
                $order_product->status =  1; //Pending order (ยืนยันการชำระเงินแล้ว)
                $order_product->save();
            }

            // order_payment_file
            $attach = [];

            /* Attachment Function */
            if (count(Request::file('attachment'))) {
                foreach (Request::file('attachment') as $key => $file) {
                    $name = md5($file->getFilename());//getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $targetName = $name . "." . $extension;

                    $path = $this->createLoadBalanceDir($file);

                    $attach[] = new SOSOrderPaymentFile([
                        'name' => $targetName,
                        'url' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'original_name' => $file->getClientOriginalName()
                    ]);
                }
                $order->save();
                $order->payment_file()->saveMany($attach);
            }

            $id_customer = $order->sos_user_id;
            $id_cart = Request::get('cart_id');
            $id_address = Request::get('id_address');
            $id_address_inv = Request::get('id_address_inv');
            $payment_type = "";
            // 1:โอน, 2:ชำระผ่านบัตรออนไลน์
            if($order->payment_type == 1){
                $payment_type = "TR";
            }

            if($order->payment_type == 2){
                $payment_type = "CR";
            }

            $client = new \GuzzleHttp\Client();
            $function = "checkout";
            $condition = "&id_customer=".$id_customer."&id_cart=".$id_cart."&payment_type=".$payment_type."&id_address=".$id_address."&id_address_inv=".$id_address_inv."&Comment="."&need_inv=Y"."&cust_group=1"."&order_chanel=NB";
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);

            return response()->json(['status' => true, 'msg'=> "Payment Success"]);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }
    //###############################################################################

    // ############################ HISTORY ORDER ####################################
    /**
     * @api {get} /singha/order-history 02.Order History
     * @apiName historyOrderList
     * @apiGroup Order
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token from NABOUR login}
     *
     * @apiSuccess {string} results รายการสั่งซื้อทั้งหมด
     * @apiSuccessExample {json} Success-Response:
    {
        "status": true,
        "results": [
            {
                "id": "0011c3a6-361d-4d40-9999-d3556f498708",
                "order_number": "NBSO20180600000012",
                "sos_user_id": "50261",
                "user_id": "5c87e1ad-ba82-4675-9e93-5f59e41a59c9",
                "property_unit_id": "e52033cd-843b-452a-b763-df0faf3aba4a",
                "property_id": "1518183e-8e96-463d-83f3-bd8c876df1e1",
                "developer_group": null,
                "zone_id": null,
                "vat": null,
                "total": "275",
                "grand_total": "235",
                "discount": "40",
                "promotion_id": null,
                "status": 1,
                "updated_at": "2018-06-07 20:50:19",
                "created_at": "2018-06-07 20:50:19",
                "payment_at": "2018-06-07 20:50:19",
                "approved_1_at": null,
                "approved_2_at": null,
                "delivery_cut_off_at": null,
                "delivery_at": null,
                "delivered_at": null,
                "received_at": null,
                "payment_type": 1,
                "payment_ref_no": null,
                "order_running_no": 12,
                "counter_product": 2,
                "order_product": [
                    {
                        "id": "db7a2694-cc20-453e-8dde-6e38cedaea9b",
                        "order_id": "0011c3a6-361d-4d40-9999-d3556f498708",
                        "sos_product_id": "82",
                        "product_category": null,
                        "sos_user_id": "50261",
                        "quantity": "2",
                        "price": "50",
                        "total": "100",
                        "user_id": "5c87e1ad-ba82-4675-9e93-5f59e41a59c9",
                        "property_unit_id": "e52033cd-843b-452a-b763-df0faf3aba4a",
                        "property_id": "1518183e-8e96-463d-83f3-bd8c876df1e1",
                        "developer_group": null,
                        "zone_id": "6e9556ba-9df4-4e43-975d-21566b7d079f",
                        "ordering": 1,
                        "status": 1,
                        "created_at": "2018-06-07 20:50:20",
                        "updated_at": "2018-06-07 20:50:20",
                        "product_name": "น้ำดื่มเพ็ทเล็ก 330 ซีซี.",
                        "unit": null,
                        "received_at": null,
                        "sos_attribute_id": "163"
                    },
                    {
                        "id": "c9dc91c9-7279-4236-ad26-c7496caa981c",
                        "order_id": "0011c3a6-361d-4d40-9999-d3556f498708",
                        "sos_product_id": "28",
                        "product_category": null,
                        "sos_user_id": "50261",
                        "quantity": "7",
                        "price": "25",
                        "total": "175",
                        "user_id": "5c87e1ad-ba82-4675-9e93-5f59e41a59c9",
                        "property_unit_id": "e52033cd-843b-452a-b763-df0faf3aba4a",
                        "property_id": "1518183e-8e96-463d-83f3-bd8c876df1e1",
                        "developer_group": null,
                        "zone_id": "6e9556ba-9df4-4e43-975d-21566b7d079f",
                        "ordering": 2,
                        "status": 1,
                        "created_at": "2018-06-07 20:50:20",
                        "updated_at": "2018-06-07 20:50:20",
                        "product_name": "น้ำดื่มสิงห์ขวดเพ็ท ขนาด 750 ซีซี.",
                        "unit": null,
                        "received_at": null,
                        "sos_attribute_id": "58"
                    }
                ]
            }
        ]
    }
     */
    public function historyOrderList(){
        try{
            $order_history = Order::with('order_product')->where('sos_user_id',Auth::user()->sos_user_id)->where('user_id',Auth::user()->id)->orderBy('created_at','desc')->get();

            $results = [];
            foreach ($order_history as &$order_item){
                $order_item['counter_product'] = $order_item->order_product->count();
                $results[] = $order_item;
            }

            return response()->json(['status' => true, 'results'=> $results]);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }

    /**
     * @api {get} /singha/order-detail/{id} 03.Order Detail
     * @apiName historyOrderDetail
     * @apiGroup Order
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token from NABOUR login}
     *
     * @apiParam {string} id id การสั่งซื้อ
     *
     * @apiSuccess {string} results รายละเอียดการสั่งซื้อ
     * @apiSuccessExample {json} Success-Response:
    {
        "status": true,
        "results": {
        "order": {
            "id": "ab93ab17-d4c2-488f-8f6e-8b8fc5be8f14",
            "order_number": "NBSO20180600000005",
            "sos_user_id": "50261",
            "user_id": "5c87e1ad-ba82-4675-9e93-5f59e41a59c9",
            "property_unit_id": "e52033cd-843b-452a-b763-df0faf3aba4a",
            "property_id": "1518183e-8e96-463d-83f3-bd8c876df1e1",
            "developer_group": null,
            "zone_id": null,
            "vat": "0",
            "total": "100",
            "grand_total": "100",
            "discount": null,
            "promotion_id": null,
            "status": 1,
            "updated_at": "2018-06-08 02:04:21",
            "created_at": "2018-06-08 00:42:29",
            "payment_at": "2018-06-08 02:04:21",
            "approved_1_at": null,
            "approved_2_at": null,
            "delivery_cut_off_at": null,
            "delivery_at": null,
            "delivered_at": null,
            "received_at": null,
            "payment_type": 1,
            "payment_ref_no": null,
            "order_running_no": 5,
            "order_product": [
                {
                "id": "01445fce-5ae9-4c79-b4d2-e3ddb353cedd",
                "order_id": "ab93ab17-d4c2-488f-8f6e-8b8fc5be8f14",
                "sos_product_id": "82",
                "product_category": null,
                "sos_user_id": "50261",
                "quantity": "2",
                "price": "50",
                "total": "100",
                "user_id": "5c87e1ad-ba82-4675-9e93-5f59e41a59c9",
                "property_unit_id": "e52033cd-843b-452a-b763-df0faf3aba4a",
                "property_id": "1518183e-8e96-463d-83f3-bd8c876df1e1",
                "developer_group": null,
                "zone_id": "6e9556ba-9df4-4e43-975d-21566b7d079f",
                "ordering": 1,
                "status": 1,
                "created_at": "2018-06-08 00:42:29",
                "updated_at": "2018-06-08 02:00:04",
                "product_name": "น้ำดื่มเพ็ทเล็ก 330 ซีซี.",
                "unit": null,
                "received_at": null,
                "sos_attribute_id": "163",
                "url_img": "http://demo.singhaonlineshop.com/2015/assets/images/products/82-656-thickbox.png",
                "description": "น้ำดื่มบรรจุขวด PETขนาดจิ๋ว 330 cc. บรรจุ 1 pack มี 12 ขวด\"ราคาพิเศษสำหรับวันนี้ ขอสงวนสิทธิ์เปลี่ยนแปลงราคาโดยไม่ต้องแจ้งให้ทราบล่วงหน้า\""
                }
            ]
            }
        }
    }
     */
    public function historyOrderDetail($id){
        try{
            $order_history = Order::find($id);

            $results = [];
            $order_detail = [];
            foreach ($order_history->order_product as &$order_item){
                //$order_item['counter_product'] = $order_item->order_product->count();
                if($order_item->sos_product_id != null & $order_item->sos_attribute_id != null) {
                    $product_data = $this->getProductData($order_item->sos_product_id, $order_item->sos_attribute_id);

                    $str_remove_html = strip_tags($product_data->description);

                    $description_str = preg_replace("/\s|&nbsp;/", ' ', $str_remove_html);

                    $order_item['url_img'] = $product_data->url_img;
                    $order_item['description'] = $description_str;

                }else{
                    $order_item['url_img'] = "";
                    $order_item['description'] = "";
                }
                $order_detail[] = $order_item;
            }

            $results['order'] = $order_history;

            return response()->json(['status' => true, 'results'=> $results]);

        }catch(Exception $ex){
            return response()->json(['status' => false, 'msg'=> $ex->getMessage()]);
        }
    }
    //###############################################################################

    function getLastOrderNum(){
        $order_running_no = Order::whereNotNull('order_running_no')->orderBy('order_running_no', 'desc')->first(); // gets the whole row

        if($order_running_no){
            $maxValue = $order_running_no->order_running_no;
        }else{
            $maxValue = 0;
        }

        return $maxValue;
    }

    function generateRunning($no){
        $date_period = Carbon::now()->year . str_pad(Carbon::now()->month, 2, '0', STR_PAD_LEFT);
        $result = "NBSO".$date_period.str_pad($no, 5, '0', STR_PAD_LEFT);

        return $result;
    }

    function getProductName($id_product,$id_attribute){
        try{
            $client = new \GuzzleHttp\Client();
            $function = "product_detail";
            $condition = "&id_product=".$id_product."&id_attribute=".$id_attribute;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);

            return $obj_product->name;

        }catch(Exception $ex){
            return false;
        }
    }

    function getProductData($id_product,$id_attribute){
        try{
            $client = new \GuzzleHttp\Client();
            $function = "product_detail";
            $condition = "&id_product=".$id_product."&id_attribute=".$id_attribute;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj_product = json_decode($body);

            return $obj_product;

        }catch(Exception $ex){
            return false;
        }
    }

    public function createLoadBalanceDir ($imageFile) {
        $name =  md5($imageFile->getFilename());//getClientOriginalName();
        $extension = $imageFile->getClientOriginalExtension();
        $targetName = $name.".".$extension;

        $folder = substr($name, 0,2);

        $pic_folder = 'singha-online-shop-file'.DIRECTORY_SEPARATOR.$folder;
        $directories = Storage::disk('s3')->directories('singha-online-shop-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }

        $full_path_upload = $pic_folder.DIRECTORY_SEPARATOR.$targetName;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($imageFile), 'public');// public set in photo upload
        if($upload){
            // Success
        }

        return $folder."/";
    }

    public function logout(){
        try{
            $user = User::find(Auth::user()->id);
            $user->sos_user_id = null;
            $user->save();

            return response()->json(['status' => true, 'msg'=> "Logout Success"]);

        }catch(Exception $ex){
            return false;
        }
    }

    public function listAllAddress(){
        try{
            $id_customer = Auth::user()->sos_user_id;
            $client = new \GuzzleHttp\Client();
            $function = "address";
            $condition = "&id_customer=".$id_customer."&id_address=";
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $list_address = json_decode($body);

            $results_all = array(
                "status" => true,
                "result" => $list_address
            );

            return response()->json($results_all);

        }catch(Exception $ex){
            return response()->json(['status'=>false, 'result'=>[]]);
        }
    }

    public function getAddress(){
        try{
            $id_customer = Auth::user()->sos_user_id;
            $id_address = Request::get('id_address');
            $client = new \GuzzleHttp\Client();
            $function = "address";
            $condition = "&id_customer=".$id_customer."&id_address=".$id_address;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $list_address = json_decode($body);

            $results_all = array(
                "status" => true,
                "result" => $list_address
            );

            return response()->json($results_all);

        }catch(Exception $ex){
            return response()->json(['status'=>false, 'result'=>[]]);
        }
    }

    public function getProvince(){
        try{
            $client = new \GuzzleHttp\Client();
            $function = "province";
            $condition = "";
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $list_province = json_decode($body);

            $results_all = array(
                "status" => true,
                "result" => $list_province
            );

            return response()->json($results_all);

        }catch(Exception $ex){
            return response()->json(['status'=>false, 'result'=>[]]);
        }
    }

    public function getDistrict(){
        try{
            $id_province = Request::get('id_province');
            $client = new \GuzzleHttp\Client();
            $function = "district1";
            $condition = "&id_province=".$id_province;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $list_address = json_decode($body);

            $results_all = array(
                "status" => true,
                "result" => $list_address
            );

            return response()->json($results_all);

        }catch(Exception $ex){
            return response()->json(['status'=>false, 'result'=>[]]);
        }
    }

    public function getSubDistrict(){
        try{
            $id_province = Request::get('id_province');
            $id_district = Request::get('id_district');
            $client = new \GuzzleHttp\Client();
            $function = "district2";
            $condition = "&id_province=".$id_province."&id_district1=".$id_district;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $list_address = json_decode($body);

            $results_all = array(
                "status" => true,
                "result" => $list_address
            );

            return response()->json($results_all);

        }catch(Exception $ex){
            return response()->json(['status'=>false, 'result'=>[]]);
        }
    }

    public function addAddress(){
        try{
            $id_customer = Auth::user()->sos_user_id;
            $firstname = Request::get('firstname');
            $lastname = Request::get('lastname');
            $phone = Request::get('phone');
            $phone_mobile = Request::get('phone_mobile');
            $alias = Request::get('alias');
            $company = Request::get('company');
            $address = Request::get('address');
            $postcode = Request::get('postcode');
            $other = Request::get('other');
            $latitude = Request::get('latitude');
            $longitude = Request::get('longitude');
            $province_id = Request::get('province_id');
            $district1_id = Request::get('district_id');
            $district2_id = Request::get('sub_district_id');

            $property_type = Auth::user()->property->property_type;
            $id_property_sos = 99;
            if(isset($property_type)){
                if($property_type == "1"){
                    $id_property_sos = 1;
                }

                if($property_type == "3"){
                    $id_property_sos = 4;
                }
            }

            $client = new \GuzzleHttp\Client();
            $function = "new_address";
            //email=suttipong9@hexacoda.com&password_prm=123456&firstname=man&lastname=testlastname&birthday=1988-12-14&phone=&phone_mobile=0884339217&alias=&firstname=suttipong1&lastname=jinadech2&company=&address1=123 หมู่7&postcode=51130&other=&latitude=1.234&longitude=0.55&province_id=51&district1_id=5103&district2_id=510301
            $url = env('SINGHA_ONLINE_URL').$function.
                "&id_property=".$id_property_sos.
                "&id_customer=".$id_customer.
                "&firstname=".$firstname.
                "&lastname=".$lastname.
                "&phone=".$phone.
                "&phone_mobile=".$phone_mobile.
                "&alias=".$alias.
                "&company=".$company.
                "&address1=".$address.
                "&postcode=".$postcode.
                "&other=".$other.
                "&latitude=".$latitude.
                "&longitude=".$longitude.
                "&province_id=".$province_id.
                "&district1_id=".$district1_id.
                "&district2_id=".$district2_id;


            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj = json_decode($body);

            return response()->json(['status' => true, 'results'=> 'Success']);
        }catch(Exception $ex){
            return false;
        }
    }

    public function editAddress(){
        try{
            $id_address = Request::get('id_address');
            $firstname = Request::get('firstname');
            $lastname = Request::get('lastname');
            $phone = Request::get('phone');
            $phone_mobile = Request::get('phone_mobile');
            $alias = Request::get('alias');
            $company = Request::get('company');
            $address = Request::get('address');
            $postcode = Request::get('postcode');
            $other = Request::get('other');
            $latitude = Request::get('latitude');
            $longitude = Request::get('longitude');
            $province_id = Request::get('province_id');
            $district1_id = Request::get('district_id');
            $district2_id = Request::get('sub_district_id');

            $property_type = Auth::user()->property->property_type;
            $id_property_sos = 99;
            if(isset($property_type)){
                if($property_type == "1"){
                    $id_property_sos = 1;
                }

                if($property_type == "3"){
                    $id_property_sos = 4;
                }
            }

            $client = new \GuzzleHttp\Client();
            $function = "edit_address";
            //email=suttipong9@hexacoda.com&password_prm=123456&firstname=man&lastname=testlastname&birthday=1988-12-14&phone=&phone_mobile=0884339217&alias=&firstname=suttipong1&lastname=jinadech2&company=&address1=123 หมู่7&postcode=51130&other=&latitude=1.234&longitude=0.55&province_id=51&district1_id=5103&district2_id=510301
            $url = env('SINGHA_ONLINE_URL').$function.
                "&id_property=".$id_property_sos.
                "&id_address=".$id_address.
                "&firstname=".$firstname.
                "&lastname=".$lastname.
                "&phone=".$phone.
                "&phone_mobile=".$phone_mobile.
                "&alias=".$alias.
                "&company=".$company.
                "&address1=".$address.
                "&postcode=".$postcode.
                "&other=".$other.
                "&latitude=".$latitude.
                "&longitude=".$longitude.
                "&province_id=".$province_id.
                "&district1_id=".$district1_id.
                "&district2_id=".$district2_id;


            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $obj = json_decode($body);

            return response()->json(['status' => true, 'results'=> 'Success']);
        }catch(Exception $ex){
            return false;
        }
    }

    public function deleteAddress(){
        try{
            $id_customer = Auth::user()->sos_user_id;
            $id_address = Request::get('id_address');
            $client = new \GuzzleHttp\Client();
            $function = "delete_address";
            $condition = "&id_customer=".$id_customer."&id_address=".$id_address;
            $url = env('SINGHA_ONLINE_URL') . $function . $condition;
            $res = $client->request('GET', $url);
            $body = $res->getBody();
            $list_address = json_decode($body);

            return response()->json(['status' => true, 'results'=> 'Success']);

        }catch(Exception $ex){
            return false;
        }
    }
}
