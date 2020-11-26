<?php

namespace App\Http\Controllers\AdminNabour;

class HomeController extends Controller
{
    /**rootAdminHome
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('redirect-user');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('Backend.home');
    }

    public function rootAdminHome () {
        return view('Backend.home');
    }

    public function nabourAdminHome () {
        return view('Backend.home');
    }

    public function SalesHome () {
        return view('Backend.home');
    }

    public function login()
    {
        return view('Backend.auth.login');
    }
}
