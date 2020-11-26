<?php

namespace App\Http\Controllers\API;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Request;
//use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;

use JWTAuth;
use Storage;
use Auth;
use League\Flysystem\AwsS3v2\AwsS3Adapter;

# Model
use DB;
use App\Model\Engagement;

class EngagementController extends Controller
{

    public function __construct()
    { }

    public function add()
    {
                $engagement = new Engagement;
                $engagement->user_id = Auth::user()->id;
                if( Request::get('property_id') ) {
                    $engagement->property_id = Request::get('property_id');
                }

                if( Request::get('property_unit_id') ) {
                    $engagement->property_unit_id = Request::get('property_unit_id');
                }

                $engagement->content_menu = Request::get('content_menu');
                if( Request::get('content_id') ) {
                    $engagement->content_id = Request::get('content_id');
                }
                $engagement->device = Request::get('device');
                $engagement->device_version = Request::get('device_version');
                $engagement->app_version = Request::get('app_version');
                $engagement->save();

                $results_all = array(
                    "status" => true,
                    "message" => "success"
                );
                return response()->json($results_all);
 
    }
    public function category()
    {
        $cate = [
            'menu_news',
            'menu_complain',
            'menu_committee',
            'menu_notification',
            'menu_parcel',
            'menu_utility_bill',
            'menu_event',
            'menu_survey',
            'menu_message',
            'menu_manual',
            'menu_setting_profile',
            'menu_setting_property',
            'menu_logout',
            'menu_beneat',
        ];

        $results_all = array(
            "status" => true,
            "message" => "success",
            "data" => [
                'category' => $cate
            ]
        );
        return response()->json($results_all);
    }
}
