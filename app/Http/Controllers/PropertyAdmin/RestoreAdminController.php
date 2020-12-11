<?php namespace App\Http\Controllers\PropertyAdmin;
use Illuminate\Routing\Controller;
use Request;
use Auth;
use Redirect;

class RestoreAdminController extends Controller {
    public function __construct () {
		$this->middleware('auth');
		if(!Request::session()->has('auth.root_admin')) Redirect::to('feed')->send();
	}

    public function restoreAdmin () {
        Auth::login(Request::session()->pull('auth.root_admin'));
        return redirect('root/admin/property/list');
    }
}