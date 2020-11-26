<?php

namespace App\Http\Controllers\AdminNabour\RootAdmin;

use Request;
use Auth;
use Redirect;
use App\Http\Controllers\Controller;
use App\BackendModel\User;
use App\Province;
use App\BackendModel\Products;
class PackageController extends Controller
{

    public function __construct () {
        $this->middleware('admin');
    }

    public function index()
    {
        $p_rows = new Products;
        //$package = $package->where('status','1');
        $p_rows = $p_rows->orderBy('product_code','DESC')->paginate(50);

        $max_cus = new Products;
        $max_cus = $max_cus->max('product_code');

        //dd($package);
        return view('Backend.product.package')->with(compact('p_rows','max_cus'));
    }

    public function index_list()
    {

        $property = new quotation;


        if (Request::get('name')) {
            $property = $property->whereHas('lastest_quotation_property', function($q) {
                $q->where('property_name_th', 'like', "%" .Request::get('name'). "%");
            });
            //dd($property->get('lastest_quotation_property'));
        }
        if(Request::get('package')){
            $property = $property->whereHas('lastest_package', function ($q) {
                $q ->where('project_package','=',Request::get('package'));
            });
        }
        if(Request::get('sale')){
            $property = $property->whereHas('lastest_sale', function ($q) {
                $q ->where('sale_id','=',Request::get('sale'));
            });
        }

        if(Request::get('quotation_number')) {
            $property = $property->where('quotation_number','=',Request::get('quotation_number'));
        }

        $property = $property->get();

        $package = new package;
        $package = $package->where('status','1');
        $package = $package->get();

        $service = new package;
        $service = $service->where('status','2');
        $service = $service->get();

        $sale = User::where('id','!=',Auth::user()->id)
            ->where('role','=',2)
            ->orderBy('created_at','DESC')
            ->paginate(30);

        if(Request::isMethod('post')) {
            return view('Backend.property.report_quotation_list')->with(compact('property','package','service','sale'));
        } else {
            return view('Backend.property.list_quotation')->with(compact('property','package','service','sale'));
        }

    }

    public function index_service()
    {
        $package = new Products;
        //$package = $package->where('status','2');
        $package = $package->get();

        $max_cus = new Products;
        $max_cus = $max_cus->max('product_code');

        //dd($package);
        //dump($package->toArray());
        return view('Backend.product.service')->with(compact('package','max_cus'));
    }

    public function add()
    {
        if(Request::get('vat1') ==1){
            $product = new Products;
            //$package->fill(Request::all());
            $product->name              = Request::get('name');
            $product->description       = Request::get('description');
            $product->price             = Request::get('price');
            $product->price_with_vat    = Request::get('vat_total');
            $product->status            = Request::get('status');
            $product->vat               = Request::get('vat_value');
            $product->is_delete         = Request::get('is_delete');
            $product->product_code      = Request::get('product_code');
            $product->free                  = empty(Request::get('free'))?'f':'t';
            $product->save();
        }else{
            $product = new Products;
            //$package->fill(Request::all());
            $product->name              = Request::get('name');
            $product->description       = Request::get('description');
            $product->price             = Request::get('price');
            $product->status            = Request::get('status');
            $product->is_delete         = Request::get('is_delete');
            $product->product_code      = Request::get('product_code');
            $product->free                  = empty(Request::get('free'))?'f':'t';
            $product->save();
        }

        //dd(Request::all());
        //dump($product->toArray());
        return redirect('service/package/add');
    }

    public function update()
    {

        if(Request::get('vat') != null){
            $id = Request::input('id');
            $product = Products::find($id);
            $product->name              = Request::get('name');
            $product->description       = Request::get('description');
            $product->price             = Request::get('price');
            $product->price_with_vat    = Request::get('vat_total');
            $product->status            = Request::get('status');
            $product->vat               = Request::get('vat_value');
            $product->is_delete         = Request::get('is_delete');
            $product->free                  = empty(Request::get('free'))?'f':'t';
            $product->save();
            //dump($product->toArray());
        }else{
            $id = Request::input('id');
            $product = Products::find($id);
            $product->name                  = Request::get('name');
            $product->description           = Request::get('description');
            if(Request::get('vat') == null){
                $product->price             = Request::get('price');
                $product->price_with_vat    = "0";
                $product->vat               = "0";
            }else{
                $product->price             = Request::get('price');
            }

            $product->status                = Request::get('status');
            $product->is_delete             = Request::get('is_delete');
            $product->free                  = empty(Request::get('free'))?'f':'t';
            //dd($product);
            $product->save();
            //dump($product->toArray());
        }
        //dump($product->toArray());
        //dd(Request::all());
        //dump(Request::get('vat'));
        return redirect('service/package/add');
    }

    public function delete()
    {
        $package = Products::find(Request::get('id2'));
        $package->is_delete ='1';
        $package->save();
        return redirect('service/package/add');
    }

    public function delete_open()
    {
        $package = Products::find(Request::get('id3'));
        $package->is_delete ='0';
        $package->save();
        return redirect('service/package/add');
    }

    public function add_service()
    {
        if(Request::get('vat1') ==1){
            $product = new Products;
            //$package->fill(Request::all());
            $product->name              = Request::get('name');
            $product->description       = Request::get('description');
            $product->price             = Request::get('price');
            $product->price_with_vat    = Request::get('vat_total');
            $product->status            = Request::get('status');
            $product->vat               = Request::get('vat_value');
            $product->is_delete         = Request::get('is_delete');
            $product->product_code         = Request::get('product_code');
            //$product->created_at       ='2018-11-30 05:35:35';
            //$product->updated_at       ='2018-11-30 05:35:35';
            $product->save();
        }else{
            $product = new Products;
            //$package->fill(Request::all());
            $product->name              = Request::get('name');
            $product->description       = Request::get('description');
            $product->price             = Request::get('price');
            $product->status            = Request::get('status');
            $product->is_delete         = Request::get('is_delete');
            $product->product_code         = Request::get('product_code');
            //$product->created_at       ='2018-11-30 05:35:35';
            //$product->updated_at       ='2018-11-30 05:35:35';
            $product->save();
        }

        //dd(Request::all());
        //dump($product->toArray());
        //dd(Request::all());
        return redirect('service/package/service/add');
    }

    public function update_service()
    {
        if(Request::get('vat') != null){
            $id = Request::input('id');
            $product = Products::find($id);
            $product->name              = Request::get('name');
            $product->description       = Request::get('description');
            $product->price             = Request::get('price');
            $product->price_with_vat    = Request::get('vat_total');
            $product->status            = Request::get('status');
            $product->vat               = Request::get('vat_value');
            $product->is_delete         = Request::get('is_delete');
            $product->save();
        }else{
            $id = Request::input('id');
            $product = Products::find($id);
            $product->name                  = Request::get('name');
            $product->description           = Request::get('description');
            if(Request::get('vat') == null AND Request::get('price_vat') != Request::get('price')){
                $product->price             = Request::get('price_vat');
                $product->price_with_vat             = "0";
                $product->vat               = "0";
            }else{
                $product->price             = Request::get('price');
            }

            $product->status                = Request::get('status');
            $product->is_delete             = Request::get('is_delete');
            $product->save();
            //dump($product->toArray());
        }
        //dump($product->toArray());
        //dd(Request::all());
        return redirect('service/package/service/add');
    }

    public function delete_service()
    {
        $package = Products::find(Request::get('id2'));
        $package->is_delete ='1';
        $package->save();
        return redirect('service/package/service/add');
    }

    public function delete_service_open()
    {
        $package = Products::find(Request::get('id3'));
        $package->is_delete ='0';
        $package->save();
        return redirect('service/package/service/add');
    }

    public function insert_quotaion_detail(){
        if(Request::isMethod('post')) {
           // dd(Request::get('transaction'));

            if(empty(Request::get('sale_id'))){
                return redirect('root/admin/property/list');
            }else{
                foreach (Request::get('transaction') as $t) {
                    $trans = new service_quotation;
                    if(empty($t['service'])){
                        return redirect('root/admin/property/list');
                    }else{
                        $trans->service_id 			= $t['service'];
                        $trans->project 			= empty($t['project'])?'0':$t['project'];
                        $trans->month 				= empty($t['price'])?'0':$t['price'];
                        $trans->unit_price 			= empty($t['unit_price'])?'0':$t['unit_price'];
                        $trans->total 				= empty($t['total'])?'0':$t['total'];
                        $trans->property_id 		= Request::get('property_id');
                        $trans->save();
                    }

                    //dd($trans);
                    // dump($trans->toArray());
                }

                $quotation = new quotation;
                $quotation->project_package 	= Request::get('package_id');
                $quotation->month_package 		= Request::get('month_package');
                $quotation->unit_package 		= Request::get('unit_package');
                $quotation->total_package 		= Request::get('total_package');
                $quotation->discount 		    = Request::get('discount');
                $quotation->property_id 		= Request::get('property_id');
                $quotation->sale_id 			= Request::get('sale_id');
                $quotation->package_unit 		= Request::get('project_package');
                $quotation->quotation_number 	= Request::get('quotation_number');
                $quotation->save();

                return redirect('root/admin/list_quotation');
            }

        }

        //dd(Request::get('transaction'));

        $id = Request::input('property_id');

        $search = new quotation;
        $search = $search->where('property_id',$id);
        $search = $search->get();

        //$search =quotation::find($id);
        $num = count($search);
       // dd($num);

        $p = new Province;
        $provinces = $p->getProvince();
        //$property = PropertyContract::find($id);
        $property = Property::find($id);

        $property1 = PropertyContract::find($id);

        //dd($property1);

        $service = new package;
        $service = $service->where('status','2');
        $service = $service->get();

        $package = new package;
        $package = $package->where('status','1');
        $package = $package->get();

        $sale = User::where('id','!=',Auth::user()->id)
            ->where('role','=',2)
            ->orderBy('created_at','DESC')
            ->paginate(30);
        //dd($sale);

            return view('Backend.property.quotation_detail')->with(compact('provinces','property','property1','service','package','sale'));

    }

    public function package_detail()
    {

        if(Request::isMethod('post')) {
            $package = Products::find(Request::get('id'));
            //$package = $package->where('status','1');
            //$package = $package->get();

            return view('Backend.product.package_update')->with(compact('package'));
        }

       //return ('5555');service_detail

    }

    public function service_detail()
    {

        if(Request::isMethod('post')) {
            $service = Products::find(Request::get('id'));
            //$package = $package->where('status','1');
            //$package = $package->get();

            return view('Backend.product.service_update')->with(compact('service'));
        }

        //return ('5555');service_detail

    }

}
