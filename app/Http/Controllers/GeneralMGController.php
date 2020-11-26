<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\ManagementGroup;
use App\Property;

class GeneralMGController extends Controller
{
    public function getPropertyInMg (Request $r) {
        $p_list = Property::where('is_demo', false);

        if($r->get('developer_group') != "-") {
            $p_list = $p_list->where('developer_group_id', $r->get('developer_group'));
        }

        $p_list = $p_list->whereHas('feature', function ($q) {
            $q->where('market_place_singha', true);
        });

        $p_list = $p_list->lists('property_name_th','id')->toArray();

        return response()->json($p_list);
    }
}
