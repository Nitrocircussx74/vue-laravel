<?php

namespace App\Http\Controllers\api\property;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Property;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
   
    public function __construct () {
        // $this->middleware('auth');
        $this->middleware('auth:api');
       

	}
    public function index()
    {
        $property = Property::all();

        return response()->json(['property_list' => $property]);
    }
}
