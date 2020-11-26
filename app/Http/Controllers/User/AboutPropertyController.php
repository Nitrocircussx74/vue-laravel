<?php namespace App\Http\Controllers\User;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use Auth;
# Model
use App\Property;
use App\PropertyFile;
use App\Province;
class AboutPropertyController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu','about');
	}

	public function about () {
		$property = Property::find(Auth::user()->property_id);
		$p = new Province;
		$provinces = $p->getProvince();
		return view('about_property.user-about')->with(compact('property','provinces'));
	}

	public function plan () {
		$propertyFile = PropertyFile::where('property_id',Auth::user()->property_id)->get();
		return view('about_property.user-plan')->with(compact('propertyFile'));
	}
}
