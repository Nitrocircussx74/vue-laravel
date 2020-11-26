<?php namespace App\Http\Controllers\API;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use JWTAuth;
use Auth;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
# Model
use App\Property;
use App\PropertyFile;
use App\PropertyFeature;
use App\Installation;
use App\UserPropertyFeature;
class AboutPropertyController extends Controller {

	public function __construct () {

	}

	public function about () {
        $property = Property::find(Auth::user()->property_id);
        // Min and Max price
        $price = [
            1 => '1,000,000 - 3,000,000 '.trans('messages.Report.baht',[],null,Auth::user()->lang),
            2 => '3,000,001 - 5,000,000 '.trans('messages.Report.baht',[],null,Auth::user()->lang),
            3 => '5,000,001 - 10,000,000 '.trans('messages.Report.baht',[],null,Auth::user()->lang),
            4 => '10,000,001+'
        ];
        if( $property->min_price ) {
            $property->min_price = $price[$property->min_price];
        } else {
            $property->min_price = "-";
        }
        if( $property->max_price ) {
            $property->max_price = $price[$property->max_price];
        } else {
            $property->max_price = "-";
        }

        if( $property->project_banner ) {
            $property->project_banner = env('URL_S3')."/property-file".$property->project_banner;
        }
        
        return response()->json($property);
	}

	public function plan () {
		$propertyFile = PropertyFile::where('property_id',Auth::user()->property_id)->get();
        foreach($propertyFile as &$value)
        {
            $splitType = explode(".",$value['name']);
            $value['file_type'] = end($splitType);
        }
        return response()->json($propertyFile);
	}

    public function getAttach ($id) {
        $file = PropertyFile::find($id);
        $folder = str_replace('/', DIRECTORY_SEPARATOR, $file->url);
        $file_path = 'property-file'.DIRECTORY_SEPARATOR.$folder.$file->name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            $response = response(Storage::disk('s3')->get($file_path), 200, [
                'Content-Type' => $file->file_type,
                'Content-Length' => Storage::disk('s3')->size($file_path),
                'Content-Description' => 'File Transfer',
                'Content-Disposition' => "attachment; filename={$file->original_name}",
                'Content-Transfer-Encoding' => 'binary',
            ]);

            ob_end_clean();

            return $response;
        }
    }

    public function featureProperty() {
	    $user = JWTAuth::parseToken()->toUser();
	    if($user->active == false){
            /*$installation = Installation::where('user_id', '=', $user->id)->get();
            foreach ($installation as $itemInstallation){
                if(isset($device_uuid) && isset($device_token)){
                    $this->deleteDeviceInstallation($device_token,$device_uuid);
                }else{
                    if(isset($device_token)) {
                        $this->deleteDeviceInstallationByTokenId($device_token);
                    }
                }
            }*/
            JWTAuth::parseToken()->invalidate();
        }
        
        $feature = PropertyFeature::where('property_id',Auth::user()->property_id)->first();
        $property = Property::find(Auth::user()->property_id);
       
        if(isset($feature)){
            $results = array(
                "menu_committee_room" => $feature->menu_committee_room,
                "menu_event" => $feature->menu_event,
                'menu_vote' => $feature->menu_vote,
                'menu_common_fee' => $feature->menu_common_fee,
                'menu_complain' => $feature->menu_complain,
                'menu_parcel' => $feature->menu_parcel,
                'menu_message' => $feature->menu_message,
                'menu_finance_group' => $feature->menu_finance_group,
                'market_place_singha' => $feature->market_place_singha,
                'menu_beneat' => $feature->menu_beneat,
                'allow_user' => [
                    'allow_user_add_event' => $property->allow_user_add_event,
                    'allow_user_add_vote' => $property->allow_user_add_vote,
                    'allow_user_view_cf_report' => $property->allow_user_view_cf_report,
                    'view_cf_report_type' => $property->view_cf_report_type,
                    'view_overdue_debt' => $property->view_overdue_debt,
                    
                ]
            );
        }else{
            $results = array(
                "menu_committee_room" => true,
                "menu_event" => true,
                'menu_vote' => true,
                'menu_common_fee' => true,
                'menu_complain' => true,
                'menu_parcel' => true,
                'menu_message' => true,
                'menu_finance_group' => true,
                'market_place_singha' => false,
                'menu_beneat' => false,
                'allow_user' => [
                    'allow_user_add_event' => true,
                    'allow_user_add_vote' => true,
                    'allow_user_view_cf_report' => true,
                    'view_overdue_debt' => true,
                    'view_cf_report_type' => 1
                ]
            );
        }

        return response()->json($results);
    }

    public function deleteDeviceInstallation ($device_token,$device_uuid) {
        try {
            $device = Installation::where('device_token', $device_token)->where('device_uuid',$device_uuid);

            if ($device->count() > 0) {
                $device->delete();
                return true;
            }else{
                return false;
            }
        } catch(Exception $ex){
            return false;
        }
    }

    public function deleteDeviceInstallationByTokenId ($device_token) {
        try {
            $device = Installation::where('device_token', $device_token);
            if (isset($device)) {
                $device->delete();
                return true;
            }else{
                return false;
            }
        } catch(Exception $ex){
            return false;
        }
    }
}
