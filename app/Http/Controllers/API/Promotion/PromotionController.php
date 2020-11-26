<?php

namespace App\Http\Controllers\API\Promotion;

use Illuminate\Http\Request;
use Storage;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Model\PromotionPopup;
use Auth;
use App\User;
use JWTAuth;
use Carbon\Carbon;
use DB;


class PromotionController extends Controller {

    public function promotionList () {
        
        $pro = PromotionPopup::where('publish_status',true)->where('publish_start_date','<=',date('Y-m-d H:i:s'))->where('publish_end_date','>',date('Y-m-d H:i:s'))->first();
       
            if( $pro ) {
                    if( $pro->cover_image ) {
                        $pro->cover_image = env('URL_S3')."/promotion-file".$pro->cover_image;
                        $flag_image = true;
                    } else {
                        $flag_image = false;
                     }

                     if( $pro->detail_th || $pro->detail_en ) {
                            $flag_content = true;
                        } else {
                            $flag_content = false;
                       }
                    // check content type
                       if( $flag_image && $flag_content ) {
                         $pro->content_type = 3;
                        } else {
                            if( $flag_image ) {
                            $pro->content_type = 1;
                            } else {
                            $pro->content_type = 2;
                            }
                        }
                    $status = true;
                    $message = "success";
                    $data = $pro->toArray();
                return response()->json(['status'    => $status,'message'=> $message,'data' => $data]);
                }else{
                    $status = false;
                    $message = "no promotion";
                    return response()->json(['status'    => $status,'message'   => $message,]);
                 }
              
     }
}
