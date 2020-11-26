<?php namespace App\Http\Controllers\RootAdmin;

use Auth;
use Carbon\Carbon;
use File;
use Storage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Http\Controllers\PushNotificationController;
use Redirect;
use Excel;
use App;

# Model
use DB;
use App\Model\PromotionPopup;
use App\User;
use App\UserProperty;
use App\Notification;

class PromotionPopupController extends Controller
{

    /**
     * @var image folder
     */
    private $imgFolder;

    /**
     * @var image folder
     */
    

    public function __construct () {
        $this->middleware('auth');
        $this->imgFolder = 'promotion-file';
        if( Auth::check() && Auth::user()->role !== 0 ) {
          
                Redirect::to('feed')->send();
        }
    }
    public function index(Request $r) {

        $promotion = $this->MakeQuery($r);
        $promotion = $promotion->orderBy('ordering','asc')->paginate(10);
        if( $r->ajax() ) {
            return view('promotion_popup.element-lists')->with(compact('promotion'));
        } else {
            $item = [];
            return view('promotion_popup.index')->with(compact('promotion'));
        }
    }
    public function add(){
        $promotion = new PromotionPopup;
        return view('promotion_popup.add')->with(compact('promotion'));
    }

    public function edit($id){
        $promotion = PromotionPopup::find($id);
        return view('promotion_popup.edit')->with(compact('promotion'));
    }

    public function view ( $id ) {
        $promotion  = PromotionPopup::find($id);
        return view('promotion_popup.view')->with(compact('promotion'));
    }

  
    function delete (Request $request) {
        if( $request->ajax() ) {
            $id = $request->get('id');
            $promotion = PromotionPopup::find($id);
            if ($promotion->cover_image) {
            $this->removeFile($promotion->cover_image);
            }
            $promotion->delete();
            return response()->json(['status'=>true]);
        }
    }


    public function save( Request $request )
    {
        if( $request->isMethod('post') ) {
            if ($request->get('id')) {
                $promotion = PromotionPopup::find($request->get('id'));
                $promotion->updated_by = Auth::user()->id;
                $check = PromotionPopup::where('ordering',$request->get('ordering'));
            if($check)
              {
                $check->update(['ordering' => $promotion->ordering]);
                $promotion->ordering = $request->get('ordering');
                $promotion->save();

              }else{
                $promotion->ordering = $request->get('ordering');
                $promotion->save();
              }
            } else {
                $promotion = new PromotionPopup;
                $promotion->created_by = Auth::user()->id;
                if ($request->get('ordering')) {

                    $order =$request->get('ordering');
    
                    $check = PromotionPopup::where('ordering',$order)->get();
                    
                    $check_con = PromotionPopup::all();
                    $check_con_order =  $check->isEmpty();
                    // dd($check_con_order);
    
                    if($check_con_order == false){
                    foreach($check_con as $row)
                    {
                        if($row->ordering ==  $order){
    
                            $order++;
                        }
                        foreach($check_con as $row){
                            if($row->ordering ==  $order){
                                $order++;
                            }
                        } 
                    }
                        $promotion->ordering = $order;
                        $promotion->save();
                    }else
                    {
                        $promotion->ordering = $request->get('ordering');
                    }
                        
                } 
              
            } 
            $promotion->title_th = $request->get('title_th');
            $promotion->title_en = $request->get('title_en');
            $promotion->detail_th = $request->get('detail_th');
            $promotion->detail_en = $request->get('detail_en');
            $promotion->ratio = $request->get('ratio');
            $promotion->alert_type = $request->get('alert_type');
            $promotion->publish_status = $request->get('publish_status');

                if($request->get('publish_start_date') ) {
                    $promotion->publish_start_date =date('Y-m-d',strtotime($request->get('publish_start_date')))." ".date('H:i:s',strtotime($request->get('publish_start_time')));
                    $promotion->publish_end_date =date('Y-m-d',strtotime($request->get('publish_end_date')))." ".date('H:i:s',strtotime($request->get('publish_end_time')));
                } 
                if($request->get('promotion_start_date') ) {
                    $promotion->promotion_start_date =date('Y-m-d',strtotime($request->get('promotion_start_date')))." ".date('H:i:s',strtotime($request->get('promotion_start_time')));
                    
                    $promotion->promotion_end_date =date('Y-m-d',strtotime($request->get('promotion_end_date')))." ".date('H:i:s',strtotime($request->get('promotion_end_time')));
                } 
            $promotion->save();
            // remove old image first
            if ($request->get('remove-banner-flag') && $promotion->cover_image) {
                $this->removeFile($promotion->cover_image);
                $promotion->cover_image = null;
            }
            // Add new image
            // if (!empty($request->get('img_post_banner'))) {
            //     $file = $request->get('img_post_banner');
            //     $name 	= $file['name'];
            //     $x 		= $request->get('img-x');
            //     $y 		= $request->get('img-y');
            //     $w 		= $request->get('img-w');
            //     $h 		= $request->get('img-h');
            //     cropBannerImg ($name,$x,$y,$w,$h);
            //     $path = $this->createLoadBalanceDir($file['name']);
            //     $tempUrl = "/%s%s";
            //     $promotion->cover_image = sprintf($tempUrl, $path, $file['name']);
            // }


            if (!empty($request->get('img_post_banner'))) {
                $file = $request->get('img_post_banner');
                $path = $this->createLoadBalanceDir($file['name']);
                $tempUrl = "/%s%s";
                $promotion->cover_image = sprintf($tempUrl, $path, strtolower($file['name']));
            }
            $promotion->save();
            return redirect('root/admin/promotion-popup/list');
        }
    }

    public function removeFile ($name) {
        $file_path  = $this->imgFolder."/".$name;
        $exists     = Storage::disk('s3')->has($file_path);
        if ($exists) {
            Storage::disk('s3')->delete($file_path);
        }
    }

    

    public function createLoadBalanceDir( $name ) {
        $targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
        $folder = substr($name, 0, 2);
        $picFolder = $this->imgFolder.'/'.$folder;
        $directories = Storage::disk('s3')->directories($this->imgFolder);

        if (!in_array($picFolder, $directories)) {
            Storage::disk('s3')->makeDirectory($picFolder);
        }

        $fullPathUpload = $picFolder."/".strtolower($name);
        Storage::disk('s3')->put($fullPathUpload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
    
        return $folder."/";
    }
    private function MakeQuery ($request,$export = 0 ) {
            
        $publish = $request->get('publish_status');
        $created = $request->has('created_at') ? $request->get('created_at') : null ;
        $keyword = $request->has('keyword') ? $request->get('keyword') : null ;
   
        $item = new PromotionPopup;
        if (!empty($keyword)) {

            $item = $item->where(function ($q) use ($keyword) {
                $q->where('title_th','like',"%".$keyword."%");
                $q->orWhere('title_en','like',"%".$keyword."%");
                $q->orWhere('detail_th','like',"%".$keyword."%");
                $q->orWhere('detail_en','like',"%".$keyword."%");
            });
        }
        if (!empty($created)) {
            $item = $item->whereRaw(DB::raw("DATE(created_at) = '".str_replace('/','-',$created)."'"));
        }
       

        if( $request->get('publish_status') && $request->get('publish_status') != "-") {
            $item = $item->where('publish_status',$request->get('publish_status'));
        }
        return $item;
    }
}
