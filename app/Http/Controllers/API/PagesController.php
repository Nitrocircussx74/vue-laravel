<?php namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Auth;
use Redirect;
use Illuminate\Routing\Controller;
use App;
use App\Http\Controllers\Officer\AccountController;
# Model
use DB;
use App\Page;



class PagesController extends Controller {

 

	public function __construct () {

	}

    public function getPage(Request $r){
     
        $page = Page::where('alias',$r->get('pages'))->first();
        $page->content_th =  html_entity_decode($page->content_th);
        $page->content_en =  html_entity_decode($page->content_en);
         $page = $page->toArray();
        $results = [
            'status' => true,
            'message' => "success",
            'data' => $page
        ];

        return response()->json($results);
    }

  
}
