<?php

namespace App\Http\Controllers\RootAdmin\MarketPlace\SOS;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use File;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;

use App\Property;
use App\SOSPromotion;
use App\SOSPromotionProperty;
use App\SOSPromotionFile;


class PromotionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function promotionList(Request $request)
    {
        $promotion = SOSPromotion::with('promotion_property');

        if( $request->get('pm-p-name')) {
            $name = $request->get('pm-p-name');
            $promotion = $promotion->whereHas('promotion_property.property', function ($q) use ($name) {
                $q  ->where('property_name_th','like',"%".$name."%")
                    ->orWhere('property_name_en','like',"%".$name."%");
            });
        }

        if($request->get('pm-name')) {
            $promotion = $promotion->where('name','like',"%".$request->get('pm-name')."%");
        }

        if($request->get('pm-p-par')) {
            $promotion = $promotion->where('property_participation', $request->get('pm-p-par'));
        }

        if($request->get('pm-status')) {
            $promotion = $promotion->where('status',$request->get('pm-status'));
        }
        //echo $promotion->toSql();
        $promotion = $promotion->orderBy('created_at','desc')->paginate(5);
        //dd($promotion->toArray());

        if( $request->ajax() ) {
            return view('root-admin.market_place.SOS.promotion-list-page')->with(compact('promotion'));
        } else {
            return view('root-admin.market_place.SOS.promotion-list')->with(compact('promotion'));
        }

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function save( Request $request )
    {
        if ( $request->ajax() ) {
            if( $request->get('id') ) {
                $promotion = SOSPromotion::find( $request->get('id') );
                $promotion->promotion_property()->delete();
            } else {
                $promotion = new SOSPromotion;
            }

            $promotion->fill( $request->all() );
            if( !$promotion->limit ) $promotion->limit = 0;
            if( empty($request->get('expire_date')) ) {
                $promotion->expire_date = null;
            }
            $promotion->save();

            if( $request->get('p') && $promotion->property_participation != 'A') {
                foreach ( $request->get('p') as $p_id ) {
                    $pp = new SOSPromotionProperty();
                    $pp->promotion_id = $promotion->id;
                    $pp->property_id = $p_id;
                    $pp->save();
                }
            }

            if(!empty($request->get('attachment'))) {
                foreach ($request->get('attachment') as $img) {
                    //Move Image
                    $path = $this->createLoadBalanceDir($img['name']);
                    $promo_img[] = new SOSPromotionFile([
                        'name' => $img['name'],
                        'url' => $path,
                        'file_type' => $img['mime'],
                        'is_image'	=> $img['isImage'],
                        'original_name'	=> $img['originalName']
                    ]);
                }
                $promotion->promotionFile()->saveMany($promo_img);
            }

            if(!empty($request->get('remove'))) {
                $remove = $request->get('remove');
                // Remove old files
                if(!empty($remove['promotion-file']))
                    foreach ($remove['promotion-file'] as $file) {
                        $file = SOSPromotionFile::find($file);
                        $this->removeFile($file->name);
                        $file->delete();
                    }
            }
            return response()->json(['result' => true]);
        }
    }

    /**
    * Get the specified resource from storage.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function get(Request $request)
    {
        $promotion = SOSPromotion::with('promotion_property.property','promotionFile')->find( $request->get('id') );
        if( $promotion->expire_date ) {
            $promotion->expire_date = date('Y/m/d',strtotime($promotion->expire_date));
        }
        return response()->json(['result' => true,'data' => $promotion->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function  propertyList (Request $request) {
        if ( $request->ajax() ) {

            $p_list = Property::with('feature')->where('is_demo', false);

            if( $request->get('p') ) {
                $p_list = $p_list->whereNotIn('id',$request->get('p'));
            }
            if( $request->get('name') ) {
                $p_list = $p_list->where(function ($q) use ($request) {
                    $q->where('property_name_th','like',"%".$request->get('name')."%")
                        ->orWhere('property_name_en','like',"%".$request->get('name')."%");
                });
            }
            $p_list = $p_list->whereHas('feature', function ($q) {
                $q->where('market_place_singha', true);
            })->select('id','property_name_th')->get();

            if( $p_list->count() ) $_r = true;
            else $_r = false;
            $result = ['result' => $_r,'data' => $p_list->toArray() ];
            return response()->json( $result );
        }
    }

    public function createLoadBalanceDir ($name) {
        $targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
        $folder = substr($name, 0,2);
        $pic_folder = 'event-file/'.$folder;
        $directories = Storage::disk('s3')->directories('promotion-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
        return $folder."/";
    }

    public function removeFile ($name) {
        $folder = substr($name, 0,2);
        $file_path = 'promotion-file/'.$folder."/".$name;
        if(Storage::disk('s3')->has($file_path)) {
            Storage::disk('s3')->delete($file_path);
        }
    }
}
