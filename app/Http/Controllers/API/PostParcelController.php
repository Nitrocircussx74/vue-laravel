<?php namespace App\Http\Controllers\API;
use Request;
use Illuminate\Routing\Controller;
use Auth;
# Model
use App\PostParcel;

class PostParcelController extends Controller {

    public function __construct () {
        $this->middleware('jwt.feature_menu:menu_parcel');
    }

    public function newPostParcelList()
    {
        $post_parcels = PostParcel::where('property_unit_id',Auth::user()->property_unit_id)->where('status',false)->orderBy('date_received','desc')->paginate(30);
        foreach ($post_parcels as &$post_parcels_item) {
            $temp_post_parcels_item = receivedNumber($post_parcels_item['receive_code']);
            $post_parcels_item['receive_code'] = $temp_post_parcels_item;
        }

        $result = [
            'post_parcels' => $post_parcels->toArray()
        ];
        return response()->json($result);
    }

    public function historyPostParcelList()
    {
        $post_parcels = PostParcel::where('property_unit_id',Auth::user()->property_unit_id)->where('status',true)->orderBy('date_received','desc')->paginate(30);
        foreach ($post_parcels as &$post_parcels_item) {
            $temp_post_parcels_item = receivedNumber($post_parcels_item['receive_code']);
            $post_parcels_item['receive_code'] = $temp_post_parcels_item;
        }

        $result = [
            'post_parcels' => $post_parcels->toArray()
        ];
        return response()->json($result);
    }

    public function viewPostParcel () {
        $post_parcel = PostParcel::find(Request::get('id'));
        $temp_post_parcels_item = receivedNumber($post_parcel['receive_code']);
        $post_parcel['receive_code'] = $temp_post_parcels_item;

        return response()->json(compact('post_parcel'));
    }
}
