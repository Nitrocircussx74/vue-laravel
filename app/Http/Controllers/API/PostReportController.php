<?php namespace App\Http\Controllers\API;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use File;
use Redirect;
use JWTAuth;
# Model
use App\PostReport;
use App\PostReportDetail;
use App\Post;
use App\Event;
use App\Vote;
use App\Discussion;
class PostReportController extends Controller {

    public function __construct () {

    }

    public function report () {
        try {
            // Check Already Report
            $old_report = PostReportDetail::where('post_id', Request::get('id'))
                ->where('report_by', Auth::user()->id)
                ->where('post_type', Request::get('type'))
                ->get();
            if($old_report->isEmpty()) {
                // 1=Post, 2=Event, 3=Vote
                switch (Request::get('type')) {
                    case '1':
                        $obj = Post::find(Request::get('id'));
                        break;
                    case '2':
                        $obj = Event::find(Request::get('id'));
                        break;
                    case '3':
                        $obj = Vote::find(Request::get('id'));
                        break;
                    case '4':
                        $obj = Discussion::find(Request::get('id'));
                        break;
                    default:
                        $obj = null;
                        break;
                }
                if($obj) {
                    $report = PostReport::firstOrCreate(array('post_id' => Request::get('id'), 'property_id' => $obj->property_id,'post_type'=>Request::get('type')));
                    $report_detail = new PostReportDetail;
                    $report_detail->post_report_id 	= $report->id;
                    $report_detail->post_id 		= $obj->id;
                    $report_detail->report_by 		= Auth::user()->id;
                    $report_detail->reason 			= Request::get('reason');
                    $report_detail->post_type		= Request::get('type');
                    $report_detail->save();
                    $report->updated_at = time();
                    $report->save();

                    $results = array(
                        "status" => true,
                        "data" => null,
                        "message" => "Success"/*,
                        "token" => $this->newToken()*/
                    );

                }else{
                    $results = array(
                        "status" => false,
                        "data" => null,
                        "message" => "Object ID not found"/*,
                        "token" => $this->newToken()*/
                    );
                }
            } else {
                $results = array(
                    "status" => false,
                    "data" => null,
                    "message" => trans('messages.Post.reporte_dup')/*,
                    "token" => $this->newToken()*/
                );
            }

            return response()->json($results);

        } catch(Exception $ex) {
            $results = array(
                "status" => false,
                "data" => null,
                "message" => $ex->getMessage()/*,
                "token" => $this->newToken()*/
            );
            return response()->json($results);
        }
    }
}
