<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\User;

class AppController extends Controller
{
    public function init()
    {

        $user = Auth::user();
        return response()->json($user, 200);
    }
    public function login(Request $r)
    {
        if (Auth::attempt(['email' => $r->email, 'password' => $r->password], true)) {
            return response()->json(Auth::user(), 200);
        } else {
            return response()->json(['error' => 'Could not log you in.'], 401);
        }
    }
    public function register(Request $r)
    {
        $user = User::where('email', $r->email)->first();

        if (isset($user->id)) {
            return response()->json(['error' => 'Username already exits'], 401);
        }

        $user = new User();
        $user->email = $r->email;
        $user->password = bcrypt($r->password);
        $user->save();
    }
    public function logout()
    {
        Auth::logout();
    }
}
