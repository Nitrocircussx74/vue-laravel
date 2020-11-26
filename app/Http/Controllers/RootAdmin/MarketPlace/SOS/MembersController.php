<?php

namespace App\Http\Controllers\RootAdmin\MarketPlace\SOS;
use DB;
use App\ManagementGroup;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\User;
use App\Property;
use App\UserMarketPlaceData;


class MembersController extends Controller
{
    public function __construct () {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function membersList(Request $r)
    {
        //$this->create();
        $members = User::where('role',2)->whereHas('market_place', function ($q){
            $q->where('market_place','sos');
        });

        if($r->get('member-no')) {
            //$members = $members->where('')
        }

        if($r->get('property')) {
            $members = $members->where('property_id',$r->get('property'));
            $property = Property::find($r->get('property'));

        } else if($r->get('developer_group') && $r->get('developer_group') != '-') {
            $members = $members->whereHas('property', function ($q) use ($r) {
                $q->where('developer_group_id',$r->get('developer_group'));
            });
        }

        $members = $members->paginate(50);
        if($r->isMethod('post')) {

            return view('root-admin.market_place.SOS.member-list-page')->with(compact('members'));
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

            return view('root-admin.market_place.SOS.member-list')->with(compact('members','mg','p_list'));
        }

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //$p = 'e2ee746a-f654-4dae-9371-0b9068add4c1';
        $p = '62a92f34-cdd4-445f-8d2b-29617f4593bb';
        $u = User::where('property_id',$p)->where('role',2)->get();
        $u = $u->toArray();
        //dd($u->toArray());
        $l = count($u);
        for( $i = 0; $i < $l; $i++) {
            $umpd = new UserMarketPlaceData;
            $umpd->user_id = $u[$i]['id'];
            $umpd->market_place = 'sos';
            $umpd->save();
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
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
}
