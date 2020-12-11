<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
use File;
use Storage;
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
		view()->share('active_menu','report');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function postReportlist () {
		$reports = PostReport::where('property_id',Auth::user()->property_id)->where('post_type',1)->orderBy('updated_at','DESC')->paginate(30);
		if(Request::ajax()) {
			return view('report.report-post-list-element')->with(compact('reports'));
		} else return view('report.report-post-list')->with(compact('reports'));
	}

	public function eventReportlist () {
		$reports = PostReport::where('property_id',Auth::user()->property_id)->where('post_type',2)->orderBy('updated_at','DESC')->paginate(30);
		if(Request::ajax()) {
			return view('report.report-event-list-element')->with(compact('reports'));
		} else return view('report.report-event-list')->with(compact('reports'));
	}

	public function voteReportlist () {
		$reports = PostReport::where('property_id',Auth::user()->property_id)->where('post_type',3)->orderBy('updated_at','DESC')->paginate(30);
		if(Request::ajax()) {
			return view('report.report-vote-list-element')->with(compact('reports'));
		} else return view('report.report-vote-list')->with(compact('reports'));
	}

	public function discussionReportlist () {
		$reports = PostReport::where('property_id',Auth::user()->property_id)->where('post_type',4)->orderBy('updated_at','DESC')->paginate(30);
		if(Request::ajax()) {
			return view('report.report-discussion-list-element')->with(compact('reports'));
		} else return view('report.report-discussion-list')->with(compact('reports'));
	}


	public function getReport () {
		$reports = PostReportDetail::where('post_report_id',Request::get('rid'))->orderBy('created_at','DESC')->get();
		return view('report.user-list')->with(compact('reports'));
	}

	public function reportRemove ($id = "") {
		$report = PostReport::find($id);
		if($report->reportList()->count()) {
			$report->reportList()->delete();
		}
		$report->delete();
		if($report->post_type == 1) {
			$post = Post::find($report->post_id);
			if($post->count()) {
				if(!$post->postFile->isEmpty()) {
					foreach ($post->postFile as $file) {
						$this->removeFile($file->name,'post-file');
					}
					$post->postFile()->delete();
				}
				$post->comments()->delete();
				$post->likes()->delete();
				$post->delete();
			}
			return redirect('admin/report/post');
		} else if($report->post_type == 2) {
			$event = Event::find($report->post_id);
			if($event->count()) {
				if(!$event->eventFile->isEmpty()) {
					foreach ($event->eventFile as $file) {
						$this->removeFile($file->name,'event-file');
					}
					$event->eventFile()->delete();
				}
				$event->confirmation()->delete();
				$event->delete();
			}
			return redirect('admin/report/event');
		} else  if($report->post_type == 3) {
			$vote = Vote::find($report->post_id);
			if($vote->count()) {
				if(!$vote->voteFile->isEmpty()) {
					foreach ($vote->voteFile as $file) {
						$this->removeFile($file->name,'vote-file');
					}
					$vote->voteFile()->delete();
				}
				$vote->userChoose()->delete();
				$vote->voteChoice()->delete();
				$vote->delete();
			}
			return redirect('admin/report/vote');
		} else  {
			$discussion = Discussion::find($report->post_id);
			if($discussion->count()) {
				$discussion->comments()->delete();
				if(!$discussion->discussionFile->isEmpty()) {
					foreach ($discussion->discussionFile as $file) {
						$this->removeFile($file->name,'discussion-file');
					}
					$discussion->discussionFile()->delete();
				}
				$discussion->delete();
			}
			return redirect('admin/report/discussion');
		}
	}

	public function viewEvent () {
		if(Request::ajax()) {
			$event = Event::with(['creator','eventFile','confirmation' => function($query){
							    $query->where('user_id', '=', Auth::user()->id);
							}])
							->find(Request::get('eid'));

			$detail = view('report.event-detail')->with(compact('event'))->render();
			return response()->json(['status' => true, 'detail' => $detail,'head'=>e($event->title)]);
		}
	}

	public function removeFile ($name,$dir) {
		$folder = substr($name, 0,2);
		$file_path = $dir.'/'.$folder."/".$name;
		if(Storage::disk('s3')->has($file_path)) {
			Storage::disk('s3')->delete($file_path);
		}
	}
}
