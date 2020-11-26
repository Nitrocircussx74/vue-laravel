<?php

namespace App\Http\Controllers\API\PersonalData;

use Illuminate\Http\Request;
use Storage;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use Auth;
use App\User;
use JWTAuth;
use Carbon\Carbon;
use DB;
use App\Model\PDPA;
use App\Model\PDPAUserAccept;
use App\Model\PDPAAcceptlog;


class PersonalController extends Controller
{
    function contentList () {
      
        $dt_date = Carbon::today();
        $format = date('Y-m-d H:i:s');   

        $consent_db = PDPA::with('pdpa_has_cate')
            ->select('pdpa_consent.id', 'pdpa_user_accept.pdpa_id','pdpa_consent.title_th','pdpa_consent.title_en','pdpa_consent.detail_th','pdpa_consent.detail_en','pdpa_consent.cover_image','pdpa_consent.ordering','pdpa_consent.target_recipient','pdpa_consent.target_organization','pdpa_consent.publish_status','pdpa_consent.publish_date')
            ->where('pdpa_consent.publish_status','=',true)
            ->whereNotNull('pdpa_consent.publish_date')
            ->where('pdpa_consent.publish_date','<=',Carbon::now())

            ->leftJoin('pdpa_user_accept', function($q){
                $q->on('pdpa_consent.id', '=','pdpa_user_accept.pdpa_id')
               ->where('pdpa_user_accept.user_id','=',Auth::user()->id)
                ->whereNull('pdpa_user_accept.deleted_at');
            })
           
            ->orderBy('pdpa_consent.ordering')
            ->orderBy('pdpa_consent.created_at','desc')
            ->whereNull('pdpa_user_accept.pdpa_id')
            ->get();

           
            if(count($consent_db) > 0) { 
                $data = [];
                    foreach($consent_db as $key =>$val){

                            $data[$key]['id'] = $val->id;
                            // $data[$key]['accept_id'] = $val->pdpa_id;
                            $data[$key]['title_th'] = $val->title_th;
                            $data[$key]['title_en'] = $val->title_en;
                            $data[$key]['detail_th'] = $val->detail_th;
                            $data[$key]['detail_en'] = $val->detail_en;

                            if($val->cover_image){
                                $data[$key]['cover_image'] = env('URL_S3')."/pdpa".$val->cover_image;
                            }else{
                                $data[$key]['cover_image'] = $val->cover_image;
                            }
                            
                            $data[$key]['ordering'] = $val->ordering;
                            $data[$key]['target_recipient '] = $val->target_recipient;
                            $data[$key]['target_organization '] = $val->target_organization;

                            $data[$key]['publish_status'] = $val->publish_status;
                            $data[$key]['publish_date'] = $val->publish_date;

                            if($val->pdpa_has_cate){
                                $cate = [];
                                  foreach($val->pdpa_has_cate as $r){
                                     $cate[] =['cate_id' => $r->pdpa_category_id]+['cate_title' => $r->pdpa_cate->title];
                                  }
                            }else{
                                $cate = [];
                            }
                            $data[$key]['category'] = $cate;
                    }
                
                $results = ['status' => true,'message' => 'success','rescode' => 000,'data' =>  $data];
            }else{
                $data = [];
                $results = ['status' => true,'message' => 'success','rescode' => 000,'data' =>  $data];
            }
            return response()->json($results); 
    }



    function  acceptContent (Request $request){

        
        if(empty($request->get('accept_choice'))){
            $results = ['status' => false,'message' => 'error','rescode' => 405,'error' => 'invalid_accept_choice'
                ];
        }else if(empty($request->get('id'))){
            $results = ['status' => false,'message' => 'error','rescode' => 405,'error' => 'invalid_id'
                ];
        }else if(empty($request->get('user_id'))){
             $results = ['status' => false,'message' => 'error','rescode' => 405,'error' => 'invalid_user_id'
                ];
        }else{

            $pdpa_user_accept  =  PDPAUserAccept::where('pdpa_id',$request->get('id'))->where('user_id',$request->get('user_id'))->get();
                if(count($pdpa_user_accept) > 0){

                    foreach ($pdpa_user_accept as $key => $value) {
                        $accept_id = $value->id; 
                    }
                    
                    $accept = PDPAUserAccept::find($accept_id);
                    $accept_log['original'] = $accept->toArray();

                    $accept->update(['accept_choice' => $request->get('accept_choice')]);
                    $accept_log['new'] = $accept->toArray();
                    
                    $user_accept_log =  new PDPAAcceptlog;
                    $user_accept_log->pdpa_id = $request->get('id');
                    $user_accept_log->by_user =$request->get('user_id');
                    $user_accept_log->user_accept_log = json_encode($accept_log);
                   
                    $user_accept_log->save();

                    $results = ['status' => true,'message' => 'success','rescode' => 000];

                }else{
                    $user_accept =  new PDPAUserAccept;
                    $user_accept->pdpa_id = $request->get('id');
                    $user_accept->user_id = $request->get('user_id');
                    $user_accept->accept_choice = $request->get('accept_choice');
                    $user_accept->save();

                $results = ['status' => true,'message' => 'success','rescode' => 000];
            } 
        }

        return response()->json($results);
    }



    function acceptList (Request $request){

        $dt_date = Carbon::today();
        $format = date('Y-m-d H:i:s');
       
        if(empty($request->get('user_id'))){
             $results = ['status' => false,'message' => 'error','rescode' => 405,'error' => 'invalid_user_id'
                ];
        }else{

            $user_accept_list = PDPAUserAccept::with('PDPA')->where('user_id',$request->get('user_id'))->whereNull('deleted_at')->get();
        // dd($user_accept_list);
            if(count($user_accept_list) > 0){
            $data = [];
            foreach ($user_accept_list as $key => $value) {

                $data[$key]['pdpa_id'] = $value->pdpa_id;

                foreach ($value->PDPA as $k => $v) {
                    $data[$key]['title_th'] =$v->title_th;
                    $data[$key]['title_en'] =$v->title_en;
                }

                $data[$key]['accept_choice'] = $value->accept_choice;
                $data[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
                $data[$key]['updated_at'] = $value->updated_at->format('Y-m-d H:i:s');
            }
            $results = ['status' => true,'message' => 'success','rescode' => 000,'data' =>  $data];

            }else{
                $data = [];
                $results = ['status' => false,'message' => 'error','rescode' => 405,'error' => 'invalid_credentials'
                ];
            }
        }
        
        
    return response()->json($results);
 }
  
}
