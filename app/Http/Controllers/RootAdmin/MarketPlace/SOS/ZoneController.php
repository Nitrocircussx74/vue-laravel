<?php

namespace App\Http\Controllers\RootAdmin\MarketPlace\SOS;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Property;
use App\SOSZone;

class ZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function zoneList(Request $request)
    {
        $zone = SOSZone::with('property');

        if( $request->get('p-name')) {
            $zone = $zone->whereHas('property', function ($q) use ($request) {
                $q  ->where('property_name_th','like',"%".$request->get('p-name')."%")
                    ->orWhere('property_name_en','like',"%".$request->get('p-name')."%");
            });
        }

        if($request->get('mg-name')) {
            $zone = $zone->where('name','like',"%".$request->get('mg-name')."%");
        }

        $zone = $zone->orderBy('created_at','desc')->paginate(5);

        if( $request->ajax() ) {
            return view('root-admin.market_place.SOS.zone-list-page')->with(compact('zone'));
        } else {
            return view('root-admin.market_place.SOS.zone-list')->with(compact('zone'));
        }

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function save( Request $request )
    {
        if ( $request->ajax() ) {
            if( $request->get('id') ) {
                $zone = SOSZone::find( $request->get('id') );
                $p = new Property;
                $p->timestamps        = false;
                $p->where('sos_zone_id', $zone->id)->update(['sos_zone_id' => null ]);
            } else {
                $zone = new SOSZone;
            }

            $zone->delivery_on_mon = $zone->delivery_on_tue = $zone->delivery_on_wed = $zone->delivery_on_thu =
            $zone->delivery_on_fri = $zone->delivery_on_sat = $zone->delivery_on_sun = false;
            $zone->fill( $request->all() );
            $zone->save();

            if( $request->get('p') ) {
                $p = new Property;
                $p->timestamps        = false;
                $p->whereIn('id', $request->get('p'))->update(['sos_zone_id' => $zone->id ]);
            }

            return response()->json(['result' => true]);
        }
    }

    /**
    * Get the specified resource from storage.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function get(Request $request)
    {
        $zone = SOSZone::with('property')->find( $request->get('id') );
        return response()->json(['result' => true,'data' => $zone->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function  propertyList (Request $request) {
        if ( $request->ajax() ) {

            $p_list = Property::where('is_demo', false);

            if( $request->get('p') ) {
                $p_list = $p_list->whereNotIn('id',$request->get('p'));
            }
            if( $request->get('name') ) {
                $p_list = $p_list->where(function ($q) use ($request) {
                    $q->where('property_name_th','like',"%".$request->get('name')."%")
                        ->orWhere('property_name_en','like',"%".$request->get('name')."%");
                });
            }
            $p_list = $p_list->whereNull('sos_zone_id')->whereHas('feature', function ($q) {
                $q->where('market_place_singha', true);
            })->select('id','property_name_th')->get();

            if( $p_list->count() ) $_r = true;
            else $_r = false;
            $result = ['result' => $_r,'data' => $p_list->toArray() ];
            return response()->json( $result );
        }
    }
}
