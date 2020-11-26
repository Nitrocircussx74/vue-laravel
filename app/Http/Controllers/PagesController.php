<?php namespace App\Http\Controllers;
use Request;
use Illuminate\Routing\Controller;

# Model
use App\Page;

class PagesController extends Controller {

public function viewHelps($alias) {
        $page = Page::where('alias',$alias)->first();
        if($alias == "helps-property") {
            return view('home.smart.help_property')->with(compact('page'));
        } else {
            return view('home.smart.help')->with(compact('page'));
        }
        
    }
}