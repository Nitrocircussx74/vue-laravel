<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\User;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
    public function login(Request $r)
    {

        // $validator = Validator::make($r->all(), [
        //     'email' => 'required|string|email|max:255',
        //     'password' => 'required|string|min:6|confirmed',
        // ]);
        // if ($validator->fails()) {
        //     return response(['errors' => $validator->errors()->all()], 422);
        // }
        $user = User::where('email', $r->email)->first();
        if ($user) {
            if (Hash::check($r->password, $user->password)) {
                $token = $user->createToken('Laravel Password Grant Client')->accessToken;
                $response = ['user' => $user, 'token' => $token];
                return response($response, 200);
            } else {
                $response = ["message" => "Password mismatch"];
                return response($response, 422);
            }
        } else {
            $response = ["message" => 'User does not exist'];
            return response($response, 422);
        }

        // if (Auth::attempt(['email' => $r->email, 'password' => $r->password], true)) {
        //     return response()->json(Auth::user(), 200);
        // } else {
        //     return response()->json(['error' => 'Could not log you in.'], 401);
        // }
    }
    public function getUser()
    {
        $user = Auth::user();
        return response()->json(['user' => $user, 200]);
    }
    public function logout()
    {
        Auth::logout();
        // $this->guard()->logout();
        // $request->session()->flush();
        // $request->session()->regenerate();
        // return redirect('/');
    }
}
