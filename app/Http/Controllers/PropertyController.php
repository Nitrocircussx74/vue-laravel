<?php namespace App\Http\Controllers;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;
# Model
use App\Property;
use App\User;
use Auth;
class PropertyController extends Controller {
	public function __construct (Property $Property) {
		$this->Property = $Property;
		//$this->middleware('jwt.auth', ['except' => ['authenticate']]);
		//$this->middleware('auth',['except' => ['login']]);
	}
	public function add () {
		$property = new Property();//Property::find('ca1c4638-2723-4871-a4f5-6129aaf7c9a7');
		if (Request::isMethod('post'))
		{
			$property = Request::all();
			$new_prop = new Property();
			$vp = $new_prop->validate($property);

			$new_user = new User();
			$vu = $new_user->validate($property['user']);

			if($vp->fails() or $vu->fails()) {
				$v = array_merge_recursive($vp->messages()->toArray(), $vu->messages()->toArray());
				return redirect()->back()->withInput()->withErrors($v);
			} else {
				$new_prop->fill($property)->save();
				User::create([
		            'name' => $property['user']['name'],
		            'email' => $property['user']['email'],
		            'password' => bcrypt($property['user']['password']),
		            'property_id' => $new_prop->id,
		            'role' => 1
		        ]);
		        return redirect('/Notification');
			}
		}
		return view('property.add')->with(compact('property'));
	}

	public function edit ($id) {
		if (Request::isMethod('post'))
		{
			$property = Request::all();
			$prop = new Property();
			$vp = $prop->validate($property);

			$user = new User();
			$user->rules['email'] = 'required|email|max:255';
			$vu = $user->validate($property['user']);

			if($vp->fails() or $vu->fails()) {
				$v = array_merge_recursive($vp->messages()->toArray(), $vu->messages()->toArray());
				return redirect()->back()->withInput()->withErrors($v);
			} else {
				$prop = Property::find($property['id']);
				$prop->fill($property);
				$prop->save();

				$user = User::find($property['user']['id']);
				$user->fill($property['user']);
				$user->save();
		        return redirect('/property/list');
			}
		}
		else {
			$property = Property::whereHas('property_admin',function ($query) {
				$query->where('role', '=', '1');
			})->find($id);
			$user = $property->property_admin;
			$property = $property->toArray();
			$property['user'] = $user->toArray();
			return view('property.edit')->with(compact('property'));
		}
		
	}

	public function index ()  {
		$p_rows = Property::all();
		return view('property.list')->with(compact('p_rows'));
	}

}
