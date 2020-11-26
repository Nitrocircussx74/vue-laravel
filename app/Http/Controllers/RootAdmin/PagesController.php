<?php namespace App\Http\Controllers\RootAdmin;
use Request;
use Auth;
use Redirect;
use Illuminate\Routing\Controller;
use App;
use App\Http\Controllers\Officer\AccountController;
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
        $page = Page::where('alias',$alias)->first();
        if($alias == 'beneat-terms')
        {
            $page_name = trans('messages.edit').trans('messages.terms_con_beneat');
        }else if($alias == 'nabour-beneat-terms')
        {
            $page_name = trans('messages.edit').trans('messages.terms_con_nabour');
        }
        else if($alias == 'helps-resident')
        {
            $page_name = trans('messages.edit_page').trans('messages.property');
        }
        else if($alias == 'helps-property')
        {
            $page_name = trans('messages.edit_page').trans('messages.resident');
        }
        
        else{
            $page_name = trans('messages.edit').trans('messages.faq');
        }
        return view('pages.page_edit')->with(compact('page','page_name','alias'));
    }

    public function edit () {
        if(Request::isMethod('post')) {
            // dd(Request::all());
            $page = Page::find(Request::get('id'));
            if($page){
                $page = Page::find(Request::get('id'));
            }else{
                $page = new Page;
            }
            $page->content_en = Request::get('content_en');
            $page->content_th = Request::get('content_th');
            $page->alias = Request::get('alias');
            $page->save();
            if($page->alias != 'beneat-terms' || $page->alias != 'nabour-beneat-terms'  ){
                return redirect('root/admin/pages/'.$page->alias);
            }else{
                return redirect('root/admin/page/'.$page->alias);
            }
          
        }
    }
}