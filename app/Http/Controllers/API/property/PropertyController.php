<?php

namespace App\Http\Controllers\API\property;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Property;
use Illuminate\Support\Facades\Auth;
use App\Province;

class PropertyController extends Controller
{

    public function __construct()
    {
        // $this->middleware('auth');
        $this->middleware('auth:api');
    }
    public function index()
    {

        $props = new Property;
        $props = $props->where('is_demo', false);
        $p_rows = $props->orderBy('created_at', 'desc')->get();

        $province = Province::orderBy('name_th', 'asc')->pluck('name_th', 'code')->toArray();
        // dd($province);

        foreach ($p_rows as $key => $row) {
            $row->property_name = $row->property_name_th . " / " . $row->property_name_en;
            $row->province = $province[$row->province];
            if ($row->active_status == true) {
                $row->active_status = "เปิดใช้งาน";
            } else {
                $row->active_status = "ไม่เปิดใช้งาน";
            }
            if ($row->smartbill) {
                if ($row->smartbill->activated == true) {
                    $row->smart_bill = "เปิดใช้งาน";
                } else {
                    $row->smart_bill = "ไม่เปิดใช้งาน";
                }
            } else {
                $row->smart_bill = "ไม่เปิดใช้งาน";
            }
            // dd($row->userCount->first());
            if (count($row->userCount)) {
                $row->count_user = $row->userCount->first()->count;
            } else {
                $row->count_user  = 0;
            }
        }
        // dd($p_rows->toArray());
        // ->paginate(50);
        return response()->json(['property_list' => $p_rows]);
    }
}
