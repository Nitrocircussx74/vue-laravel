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
use App\Model\PDPA;
use App\Model\PDPACategory;
use App\Model\PDPAEditlog;
use App\Model\PDPAHasCategory;
use App\Model\PDPAUserAccept;
use App\Property;
use App\User;
use App\UserProperty;
use App\Notification;

class PDPAController extends Controller
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
        $this->imgFolder = 'pdpa';
        if( Auth::check() && Auth::user()->role !== 0 ) {
          
                Redirect::to('feed')->send();
        }
    }

    public function index(Request $r) {

        $pdpa = $this->MakeQuery($r);
        $pdpa = $pdpa->orderBy('ordering','asc')->paginate(10);
        if( $r->ajax() ) {
            return view('pdpa.element-lists')->with(compact('pdpa'));
        } else {
            $item = [];
            return view('pdpa.index')->with(compact('pdpa'));
        }
    }
    public function add(){
        $this->getCategory();
        $pdpa = new PDPA;
        $existed_cate =[];
        return view('pdpa.add')->with(compact('pdpa','existed_cate'));
    }

    public function edit($id){
        $this->getCategory();
        $pdpa = PDPA::with('pdpa_has_cate','created_user','updated_user')->find($id);
        $existed_cate =[];
        if($pdpa->pdpa_has_cate) {
            foreach ($pdpa->pdpa_has_cate as $p) {
                $existed_cate[] = $p->pdpa_category_id;
            }
        }
        return view('pdpa.edit')->with(compact('pdpa','existed_cate'));
    }

    public function view ( $id ) {
        $pdpa  = PDPA::with('pdpa_has_cate','created_user','updated_user')->find($id);
        return view('pdpa.view')->with(compact('pdpa'));
    }

  


    function delete (Request $request) {
        if( $request->ajax() ) {
            $pdpa_id = $request->get('id');
            $pdpa = PDPA::find($request->get('id'));
            $pdpa->deleted_by = Auth::user()->id;   
            $pdpa->save();
            if ($pdpa->cover_image) {
            $this->removeFile($pdpa->cover_image);
            }
            $check_cate = PDPAHasCategory::where('pdpa_id',$pdpa_id)->get();
            $check_cate_con =  $check_cate->isEmpty();
                if($check_cate_con != true){
                  $cc = PDPAHasCategory::where('pdpa_id',$request->get('id'))->delete();
                }
           
            $pdpa->delete();
            return response()->json(['status'=>true]);
        }
    }

    public function getCategory()
    {
        $cate = PDPACategory::all()->pluck('title','id');
            if($cate)
            {
                $cate =  $cate->toArray();
            }else
            {
                $cate =[];
            }
        view()->share(compact('cate'));

    }
    public function getEditLogList (Request $request) {
        $pdpa = PDPA::with('log')->find($request->get('id'));
        return view('pdpa.log-list')->with(compact('pdpa'));

    }
    public function getEditingLog (Request $request) {
        $log = PDPAEditlog::find($request->get('id'));
        $content_log = json_decode($log->content_log);     
        return view('pdpa.log-view')->with(compact('content_log'));
    }

    public function save( Request $request )
    {
        if( $request->isMethod('post') ) {
            if ($request->get('id')) {
                // dd($request->all());
                $pdpa = PDPA::find($request->get('id'));
                $content_log['original'] = $pdpa->toArray();
                $flag_log = true;
                $pdpa->updated_by = Auth::user()->id;
                $check = PDPA::where('ordering',$request->get('ordering'));
                $pdpa_or = PDPA::where('ordering',$request->get('ordering'))->get();
            if($check)
              {
                $check->update(['ordering' => $pdpa->ordering]);
                $pdpa->ordering = $request->get('ordering');
                $pdpa->save();

              }else{
                $pdpa->ordering = $request->get('ordering');
                $pdpa->save();
              }
              $check_cate = PDPAHasCategory::where('pdpa_id',$request->get('id'))->get();
            
              $check_cate_con =  $check_cate->isEmpty();
          
              if($check_cate_con != true){
              
                $cc = PDPAHasCategory::where('pdpa_id',$request->get('id'))->delete();  
              }
            } else {
                $pdpa = new PDPA;
                $flag_log = false;
                $pdpa->created_by = Auth::user()->id;
                $pdpa->title_th = $request->get('title_th');
                $pdpa->title_en = $request->get('title_en');
                $pdpa->detail_th = $request->get('detail_th');
                $pdpa->detail_en = $request->get('detail_en');
                $pdpa->target_organization = $request->get('target_organization');
                $pdpa->target_recipient = $request->get('target_recipient');
                if ($request->get('ordering')) {

                    $order =$request->get('ordering');
    
                    $check = PDPA::where('ordering',$order)->get();
                    
                    $check_con = PDPA::all();
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
                        $pdpa->ordering = $order;
                        $pdpa->save();
                    }else
                    {
                        $pdpa->ordering = $request->get('ordering');
                    }
                        
                } 
              
            } 
            $pdpa->title_th = $request->get('title_th');
            $pdpa->title_en = $request->get('title_en');
            $pdpa->detail_th = $request->get('detail_th');
            $pdpa->detail_en = $request->get('detail_en');
            $pdpa->publish_status = $request->get('publish_status');
            $pdpa->target_organization = $request->get('target_organization');
            $pdpa->target_recipient = $request->get('target_recipient');

            if (!$request->get('publish_status') ) {
                $pdpa->publish_status = false;
                $pdpa->publish_date =  date('Y-m-d',strtotime($request->get('publish_date')))." ".date('H:i:s',strtotime($request->get('start_time')));
            }else{
                if( !$request->get('publish_date') ) {
                    $pdpa->publish_date =date('Y-m-d',strtotime($request->get('publish_date')))." ".date('H:i:s',strtotime($request->get('start_time')));
                } else {
                    $pdpa->publish_date = date('Y-m-d',strtotime($request->get('publish_date')))." ".date('H:i:s',strtotime($request->get('start_time')));
                }
            }
         
            // remove old image first
            if ($request->get('remove-banner-flag') && $pdpa->cover_image) {
                $this->removeFile($pdpa->cover_image);
                $pdpa->cover_image = null;
            }
            // Add new image
            if (!empty($request->get('img_post_banner'))) {
                $file = $request->get('img_post_banner');
                $name 	= $file['name'];
                $x 		= $request->get('img-x');
                $y 		= $request->get('img-y');
                $w 		= $request->get('img-w');
                $h 		= $request->get('img-h');
                cropBannerImg ($name,$x,$y,$w,$h);
                $path = $this->createLoadBalanceDir($file['name']);
                $tempUrl = "/%s%s";
                $pdpa->cover_image = sprintf($tempUrl, $path, $file['name']);
            }
            $pdpa->save();
            if($flag_log) {
                    $content_log['new'] = $pdpa->toArray();
                    $log = new PDPAEditlog();
                    $log->content_log = json_encode($content_log);
                    $log->pdpa_id = $pdpa->id;
                    $log->by_user = Auth::user()->id;
                    $log->save();
            }
            if ($request->get('cate') ) {
                foreach ($request->get('cate') as $row) {
                    $check = $this->isValidUuid($row);                
                    if($check == true)
                    {
                        $pp = PDPAHasCategory::where('pdpa_id',$pdpa->id)->where('pdpa_category_id',$row)->get();
                 
                        $check_pp = $pp->isEmpty();
                    
                            if($check_pp == true )
                            {
                                $ph = new PDPAHasCategory();
                                $ph->pdpa_id = $pdpa->id;
                                $ph->pdpa_category_id =$row;
                                $ph->save();
                            }
                    }else
                    {
                        $pp = new PDPACategory();
                        $pp->title = $row;
                        $pp->save();
                        $ph = new PDPAHasCategory();
                        $ph->pdpa_id = $pdpa->id;
                        $ph->pdpa_category_id =$pp->id;
                        $ph->save();
                    }
                }
            }
            return redirect('root/admin/pdpa/list');
        }
    }

    function isValidUuid( $uuid ) {
    
        if (!is_string($uuid) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1)) {
            return false;
        }
    
        return true;
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
    

   

    public function export (Request $r) {

        $results = $this->MakeQuery($r,1);
        $results = $results->orderBy('created_at','desc')->get();
        $filename = trans('messages.privilege.page_head');

        Excel::create($filename, function ($excel) use ($results) {
            $excel->sheet("PDPA", function ($sheet) use ($results) {
                $sheet->setWidth(array(
                    'A' => 10,
                    'B' => 30,
                    'C' => 60,
                    'D' => 20,
                    'E' => 25,
                    'F' => 15,
                    'G' => 25,
                    'H' => 25,
                    'I' => 30,
                    'J' => 15,
                    'K' => 15,
                ));
                $sheet->loadView('privilege.export')->with(compact('results'));
            });
        })->export('xlsx');
    }

    private function MakeQuery ($request,$export = 0 ) {
            
        $publish = $request->get('publish_status');
        $created = $request->has('created_at') ? $request->get('created_at') : null ;
        $keyword = $request->has('keyword') ? $request->get('keyword') : null ;
   
        $item = new PDPA;
        if($export) {
            $item = $item->with('trackingCount');
        }
        // if(!empty($pm)) {
        //     $item = $item->where('property_applying',$pm);
        // }
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
        // if(!empty($category)) {
        //     $item = $item->where('category',$category);
        // }
        // if (!empty($target_recipient)) {
        //     $item = $item->where('target_recipient',$target_recipient);
        // }
        
        return $item;
    }

    // public function clearNotification ($subject_id) {
    //     $notis = Notification::where('subject_key',$subject_id)->get();
    //     if($notis->count()) {
    //         foreach ($notis as $noti) {
    //             $noti->delete();
    //         }
    //     }
    //     return true;
    // }

  

}
