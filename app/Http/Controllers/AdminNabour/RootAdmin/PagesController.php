<?php namespace App\Http\Controllers\AdminNabour\RootAdmin;
use Request;
use Auth;
use Redirect;
use Illuminate\Routing\Controller;
use App;
use App\Http\Controllers\AdminNabour\Officer\AccountController;
# Model
use DB;
use App\Page;
class PagesController extends controller {

    public function __construct () {
		$this->middleware('auth',['except' => ['login']]);
		if( Auth::check() && Auth::user()->role !== 0 ) {
            if(Auth::user()->role !== 5) {
                Redirect::to('feed')->send();
            }
		}
	}

    public function editPage($alias) {
        @session_start();
        $_SESSION['allow_upload_kc'] = true;
        $page = Page::where('alias',$alias)->first();
        return view('Backend.pages.page_helps_edit')->with(compact('page'));
    }

    public function edit () {
        if(Request::isMethod('post')) {
            $page = Page::find(Request::get('id'));
            $page->content = Request::get('content');
            $page->save();
            return redirect()->back()->withInput();
        }
    }
}