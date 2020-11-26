<?php namespace App\Http\Controllers;
use Request;
# Model
use App\VehicleMake;
class VehicleMakeController extends Controller {

	public function getMake() {
        $make = new VehicleMake;
        if(Request::get('type') == 1 ) {
            $make = $make->getCar();
        } else {
            $make = $make->getMotocycle();
        }
        return response()->json($make);
    }
}
