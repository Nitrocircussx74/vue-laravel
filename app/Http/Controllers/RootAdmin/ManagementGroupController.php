<?php

namespace App\Http\Controllers\RootAdmin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Property;
use App\ManagementGroup;

class ManagementGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct () {
        $this->middleware('super-admin');
    }
    
    public function groupList(Request $request)
    {
        $pmg = ManagementGroup::with('property');

        if( $request->get('p-name')) {
            $pmg = $pmg->whereHas('property', function ($q) use ($request) {
                $q  ->where('property_name_th','like',"%".$request->get('p-name')."%")
                    ->orWhere('property_name_en','like',"%".$request->get('p-name')."%");
            });
        }

        if($request->get('mg-name')) {
            $pmg = $pmg->where('name','like',"%".$request->get('mg-name')."%");
        }

        $pmg = $pmg->orderBy('created_at','desc')->paginate(5);

        if( $request->ajax() ) {
            return view('root-admin.management_group.group-list-page')->with(compact('pmg'));
        } else {
            return view('root-admin.management_group.group-list')->with(compact('pmg'));
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
                $pmg = ManagementGroup::find( $request->get('id') );
                $p = new Property;
                $p->timestamps        = false;
                $p->where('developer_group_id', $pmg->id)->update(['developer_group_id' => null ]);
            } else {
                $pmg = new ManagementGroup;
            }

            $pmg->fill( $request->all() );
            $pmg->save();

            if( $request->get('p') ) {
                $p = new Property;
                $p->timestamps        = false;
                $p->whereIn('id', $request->get('p'))->update(['developer_group_id' => $pmg->id ]);
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
        $pmg = ManagementGroup::with('property')->find( $request->get('id') );
        return response()->json(['result' => true,'data' => $pmg->toArray()]);
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
            $p_list = $p_list->whereNull('developer_group_id')->select('id','property_name_th')->get();
            if( $p_list->count() ) $_r = true;
            else $_r = false;
            $result = ['result' => $_r,'data' => $p_list->toArray() ];
            return response()->json( $result );
        }
    }
}
