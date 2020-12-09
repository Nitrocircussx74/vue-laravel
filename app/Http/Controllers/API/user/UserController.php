<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct()
    {
        // $this->middleware('jwt', ['except' => ['index','show']]);
        $this->middleware('auth:api');
    }
    public function index()
    {
        // dd(Auth::user());
        $user = User::where('id', '!=', Auth::user()->id)
            //
            ->whereIn('role', [5, 7])
            ->orderBy('active', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->get();

        foreach ($user as $key => $row) {
            if ($row->phone) {
                $row->phone = $row->phone;
            } else {
                $row->phone = "-";
            }
            if ($row->active) {
                $row->active = "active";
            } else {
                $row->active = "inactive";
            }
        }
        return response()->json(['user_list' => $user]);
    }
    public function save(Request $r)
    {
        if ($r->fac == "add") {
            $officer = $r->all();
            $new_officer = new User();
            $officer['email'] = strtolower(trim($officer['email']));
            $vu = $new_officer->validate($officer);

            if ($vu->fails()) {

                return response()->json(['status' => false]);
            } else {
                $this->createAccount($officer['name'], $officer['email'], $officer['phone'], bcrypt($officer['password']), $officer['role']);
                return response()->json(['status' => true]);
            }
        } else {
            if ($r->get('id')) {
                $officer = User::find($r->get('id'));
                // $res = $r->all();
                $res = $r->except('email');

                if (empty($r->password)) {
                    unset($officer->rules['email']);
                    unset($officer->rules['password']);
                    unset($officer->rules['password_confirm']);
                }
                $vu = $officer->validate($res);
                if ($vu->fails()) {
                    return response()->json(['status' => false]);
                } else {
                    $officer->name = $r->name;
                    $officer->role = $r->role;
                    $officer->phone = $r->phone;
                    if (!empty($r->password)) {
                        $officer->password = bcrypt($r->password);
                    }
                    $officer->save();
                    return response()->json(['status' => true]);
                }
            }
        }
    }
    public function createAccount($name, $email, $phone, $password, $role)
    {
        try {
            $user_create = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'role' => $role
            ]);

            return true;
        } catch (Exception $ex) {
            return false;
        }
    }

    public function del(Request $r)
    {
        try {
            if ($r->get('id')) {
                $user = User::find($r->get('id'));
                if ($user->active == false) {
                    $user->active = true;
                } else {
                    $user->active = false;
                }
                $user->save();
                return response()->json(['status' => true]);
            } else {
                return response()->json(['status' => false]);
            }
        } catch (Exception $ex) {
            return response()->json(['status' => false]);
        }
    }
}
