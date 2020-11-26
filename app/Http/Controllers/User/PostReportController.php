<?php namespace App\Http\Controllers\User;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use File;
use Redirect;
# Model
use App\PostReport;
use App\PostReportDetail;
use App\Post;
use App\Event;
use App\Vote;
use App\Discussion;
class PostReportController extends Controller {

	public function __construct () {
		$this->middleware('auth');
	}

	public function reportCheck () {
		if(Request::ajax()) {
			$old_report = PostReportDetail::where('post_id', Request::get('id'))
							->where('report_by', Auth::user()->id)
							->where('post_type', Request::get('type'))
							->get();
			if($old_report->isEmpty()) {
				return response()->json(['status'=>true]);
			} else {
				return response()->json(['status'=>false,'msg'=>trans('messages.Post.reporte_dup')]);
			}
		}
	}
	public function report () {
		if(Request::ajax()) {
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
				return response()->json(['status'=>true,'msg'=>trans('messages.Post.reported')]);
			}
		} else {
			return response()->json(['status'=>false]);
		}
	}
}
