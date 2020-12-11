<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use App;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use Auth;
use File;
use Redirect;
use Storage;
# Model
use App\Property;
use App\PropertyFile;
use App\Bank;
use App\Province;
use App\PropertySettings;
use App\DocumentFormatSetting;
use App\DocumentPrefixSetting;
use Carbon\Carbon;

class AboutPropertyController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu','about');
		if(Auth::check() && Auth::user()->role  == 2) Redirect::to('feed')->send();
	}

	public function about () {
		@session_start();
		$_SESSION['juristic'] = Auth::user()->toArray();
		$_SESSION['url'] = url();
		$property = Property::find(Auth::user()->property_id);
		$p = new Province;
		$provinces = $p->getProvince();

		if(Request::isMethod('post')) {
			if(Auth::user()->role  == 1) {
				$tempProperty = Request::all();
				foreach ($tempProperty as &$str) {
					if ($str == "") {
						$str = "-";
					}
				}
				$property->fill($tempProperty);
				$property->min_price = str_replace(',', '', $property->min_price);
				$property->max_price = str_replace(',', '', $property->max_price);
				// if (!empty(Request::get('pic_name'))) {
				// 	if (!empty($property->logo_pic_name)) {
				// 		$this->removeFile($property->logo_pic_name);
				// 	}
				// 	$path = $this->createLoadBalanceDir(Request::get('pic_name'));
				// 	$property->logo_pic_name = Request::get('pic_name');
				// 	$property->logo_pic_path = $path;
                // }
                
                if (Request::get('remove-logo-flag') && $property->logo_pic_name) {
                    $this->removeFile($property->logo_pic_name);
                    $property->logo_pic_name = null;
                }
                if (!empty(Request::get('attachment'))) {
                    $file = Request::get('attachment');
                    $path = $this->createLoadBalanceDir($file['name']);
                    $property->logo_pic_path = $path;
                    $property->logo_pic_name = strtolower($file['name']);
                }
                if(Request::get('remove-banner-flag') && $property->project_banner) {
                    $this->removeFile($property->project_banner);
                    $property->project_banner = null;
                    $property->save();
                }
                if (!empty(Request::get('img_post_banner'))) {
                    $file = Request::get('img_post_banner');
                    $name 	= $file['name'];
                    $x 		= Request::get('img-x');
                    $y 		= Request::get('img-y');
                    $w 		= Request::get('img-w');
                    $h 		= Request::get('img-h');
                    cropBannerImg ($name,$x,$y,$w,$h);
                    $path = $this->createLoadBalanceDir($file['name']);
                    $tempUrl = "/%s%s";
                    $property->project_banner = sprintf($tempUrl, $path, strtolower($file['name']));
                } 
				$property->save();
			}
			return redirect('admin/property/about');
		}
		return view('about_property.admin-about')->with(compact('property','provinces'));
	}

	public function settings () {
		$property = Property::with('settings')->find(Auth::user()->property_id);
		if(Request::isMethod('post')) {
			if(Auth::user()->role  == 1) {
                $property->fill(Request::all());
    
				$property->common_area_fee_rate = str_replace(',', "", $property->common_area_fee_rate);
				$property->common_area_fee_rate = ($property->common_area_fee_rate > 0) ? $property->common_area_fee_rate : 0;
				$property->common_area_fee_land_rate = str_replace(',', "", $property->common_area_fee_land_rate);
				$property->common_area_fee_land_rate = ($property->common_area_fee_land_rate > 0) ? $property->common_area_fee_land_rate : 0;
				$property->save();
				//Save settings
				$settings = PropertySettings::firstOrNew(array('property_id' => $property->id ));
                $settings->fill(Request::get('settings'));
                if(Request::get('attachment')){
                foreach (Request::get('attachment') as $key => $file) {
                    //Move Image
                    if (!empty($settings->qr_code)) {
                        $this->removeFile($settings->qr_code);
                        }
                        $name = $file['name'];
                            $path = $this->createLoadBalanceDir($name);
                            $s =$path."".$name;
                            $settings->qr_code = $path."".$name;
                }
            }
                $settings->water_meter_maintenance_fee = 0;
                $settings->electric_meter_maintenance_fee = 0;
                switch ($settings->water_billing_type) {
                    case 1:
                        // เหมาจ่าย
                        $settings->water_billing_type = 1;
                        $settings->water_billing_rate = Request::get('settings')['water_billing_rate1'];
                        $settings->water_meter_maintenance_fee = Request::get('settings')['water_meter_maintenance_fee1'];
                        $settings->water_billing_minimum_price = null;
                        $settings->water_billing_minimum_unit = null;
                        $settings->water_progressive_rate = null;
                        break;
                    case 2:
                        // เหมาจ่ายรายหัว
                        $settings->water_billing_type = 2;
                        $settings->water_billing_rate = Request::get('settings')['water_billing_rate2'];
                        $settings->water_meter_maintenance_fee = Request::get('settings')['water_meter_maintenance_fee2'];
                        $settings->water_billing_minimum_price = null;
                        $settings->water_billing_minimum_unit = null;
                        $settings->water_progressive_rate = null;
                        break;
                    case 3:
                        // คิดตามจริง
                        $settings->water_billing_type = 3;
                        $settings->water_billing_rate = Request::get('settings')['water_billing_rate3'];
                        $settings->water_meter_maintenance_fee = Request::get('settings')['water_meter_maintenance_fee3'];
                        $settings->water_billing_minimum_price = null;
                        $settings->water_billing_minimum_unit = null;
                        $settings->water_progressive_rate = null;
                        break;
                    case 4:
                        // คิดตามจริงมีขั้นต่ำเป็นจำนวนเงิน
                        $settings->water_billing_type = 4;
                        $settings->water_billing_rate = Request::get('settings')['water_billing_rate4'];
                        $settings->water_meter_maintenance_fee = Request::get('settings')['water_meter_maintenance_fee4'];
                        $settings->water_billing_minimum_price = Request::get('settings')['water_billing_minimum_price4'];
                        $settings->water_billing_minimum_unit = null;
                        $settings->water_progressive_rate = null;
                        break;
                    case 5:
                        // คิดตามจริงมีขั้นต่ำเป็นจำนวนเงิน
                        $settings->water_billing_type = 5;
                        $settings->water_billing_rate = Request::get('settings')['water_billing_rate5'];
                        $settings->water_meter_maintenance_fee = Request::get('settings')['water_meter_maintenance_fee5'];
                        $settings->water_billing_minimum_price = Request::get('settings')['water_billing_minimum_price5'];
                        $settings->water_billing_minimum_unit = Request::get('settings')['water_billing_minimum_unit5'];
                        $settings->water_progressive_rate = null;
                        break;
                    case 6:
                        // คิดแบบขั้นบันได
                        if(Request::get('water_progressive_rate') == null){
                            $settings->water_billing_type = 0;
                            $settings->water_billing_rate = null;
                            $settings->water_billing_minimum_price = null;
                            $settings->water_billing_minimum_unit = null;
                            $settings->water_progressive_rate = null;
                        }else {
                            $settings->water_billing_type = 6;
                            $settings->water_billing_rate = null;
                            $settings->water_billing_minimum_price = null;
                            $settings->water_billing_minimum_unit = null;
                            $settings_water_progressive_json = json_encode(Request::get('water_progressive_rate'));
                            $settings->water_meter_maintenance_fee = Request::get('water_meter_maintenance_fee');
                            $settings->water_progressive_rate = $settings_water_progressive_json;
                        }
                        break;
                    default :
                        $settings->water_billing_type = 0;
                        $settings->water_billing_rate = null;
                        $settings->water_billing_minimum_price = null;
                        $settings->water_billing_minimum_unit = null;
                        $settings->water_progressive_rate = null;
                        break;
                }

                switch ($settings->electric_billing_type) {
                    case 1:
                        // เหมาจ่าย
                        $settings->electric_billing_type = 1;
                        $settings->electric_billing_rate = Request::get('settings')['electric_billing_rate1'];
                        $settings->electric_meter_maintenance_fee = Request::get('settings')['electric_meter_maintenance_fee1'];
                        $settings->electric_billing_minimum_price = null;
                        $settings->electric_billing_minimum_unit = null;
                        $settings->electric_progressive_rate = null;
                        break;
                    case 2:
                        // เหมาจ่ายรายหัว
                        $settings->electric_billing_type = 2;
                        $settings->electric_billing_rate = Request::get('settings')['electric_billing_rate2'];
                        $settings->electric_meter_maintenance_fee = Request::get('settings')['electric_meter_maintenance_fee2'];
                        $settings->electric_billing_minimum_price = null;
                        $settings->electric_billing_minimum_unit = null;
                        $settings->electric_progressive_rate = null;
                        break;
                    case 3:
                        // คิดตามจริง
                        $settings->electric_billing_type = 3;
                        $settings->electric_billing_rate = Request::get('settings')['electric_billing_rate3'];
                        $settings->electric_meter_maintenance_fee = Request::get('settings')['electric_meter_maintenance_fee3'];
                        $settings->electric_billing_minimum_price = null;
                        $settings->electric_billing_minimum_unit = null;
                        $settings->electric_progressive_rate = null;
                        break;
                    case 4:
                        // คิดตามจริงมีขั้นต่ำเป็นจำนวนเงิน
                        $settings->electric_billing_type = 4;
                        $settings->electric_billing_rate = Request::get('settings')['electric_billing_rate4'];
                        $settings->electric_meter_maintenance_fee = Request::get('settings')['electric_meter_maintenance_fee4'];
                        $settings->electric_billing_minimum_price = Request::get('settings')['electric_billing_minimum_price4'];
                        $settings->electric_billing_minimum_unit = null;
                        $settings->electric_progressive_rate = null;
                        break;
                    case 5:
                        // คิดตามจริงมีขั้นต่ำเป็นจำนวนเงิน
                        $settings->electric_billing_type = 5;
                        $settings->electric_billing_rate = Request::get('settings')['electric_billing_rate5'];
                        $settings->electric_meter_maintenance_fee = Request::get('settings')['electric_meter_maintenance_fee5'];
                        $settings->electric_billing_minimum_price = Request::get('settings')['electric_billing_minimum_price5'];
                        $settings->electric_billing_minimum_unit = Request::get('settings')['electric_billing_minimum_unit5'];
                        $settings->electric_progressive_rate = null;
                        break;
                    case 6:
                        // คิดแบบขั้นบันได
                        if(Request::get('electric_progressive_rate') == null){
                            $settings->electric_billing_type = 0;
                            $settings->electric_billing_rate = null;
                            $settings->electric_billing_minimum_price = null;
                            $settings->electric_billing_minimum_unit = null;
                            $settings->electric_progressive_rate = null;
                        }else {
                            $settings->electric_billing_type = 6;
                            $settings->electric_billing_rate = null;
                            $settings->electric_billing_minimum_price = null;
                            $settings->electric_billing_minimum_unit = null;
                            $settings_electric_progressive_json = json_encode(Request::get('electric_progressive_rate'));
                            $settings->electric_meter_maintenance_fee = Request::get('electric_meter_maintenance_fee');
                            $settings->electric_progressive_rate = $settings_electric_progressive_json;
                        }
                        break;
                    default:
                        $settings->electric_billing_type = 0;
                        $settings->electric_billing_rate = null;
                        $settings->electric_billing_minimum_price = null;
                        $settings->electric_billing_minimum_unit = null;
                        $settings->electric_progressive_rate = null;
                        break;

                }
				$settings->save();
			}
			return redirect('admin/property/settings');
		}
		return view('about_property.admin-property-settings')->with(compact('property'));
	}
	public function plan () {
		if(Request::isMethod('post')) {
			if(!empty(Request::get('attachment'))) {
				if(Auth::user()->role  == 1) {
					$property = Property::find(Auth::user()->property_id);
					$attach = [];
					foreach (Request::get('attachment') as $key => $file) {
						//Move Image
						$path = $this->createLoadBalanceDir($file['name']);
						$attach[] = new PropertyFile([
							'name' => $file['name'],
							'url' => $path,
							'file_type' => $file['mime'],
							'is_image' => $file['isImage'],
							'original_name' => $file['originalName']
						]);
					}
					$property->propertyFile()->saveMany($attach);
				}
			}
			return redirect('admin/property/plan');
		}
		
		$propertyFile = PropertyFile::where('property_id',Auth::user()->property_id)->get();
		return view('about_property.admin-plan')->with(compact('propertyFile'));
	}

	public function bank () {
		if(Request::isMethod('post')) {
			if(Auth::user()->role  == 1) {
				if(Request::get('id')) {
					$bank = Bank::find(Request::get('id'));
				}
				else {
					$bank = new Bank;
				}
				$bank->fill(Request::all());
				$bank->property_id = Auth::user()->property_id;
                $bank->is_fund_account = (Request::get('is_fund_account'))?true:false;
				$bank->save();
			}
			return redirect('admin/property/bank');
		} else {
			$banks = Bank::where('property_id',Auth::user()->property_id)->where('active',true)->get();
			$bank_obj = New Bank;
			return view('about_property.admin-bank')->with(compact('banks','bank_obj'));
		}

	}

	public function posting () {
		$property = Property::find(Auth::user()->property_id);
		if(Request::isMethod('post')) {
        
			if(Auth::user()->role  == 1) {
				$property->allow_user_add_event = Request::get('allow_user_add_event') ? true : false;
				$property->allow_user_add_vote = Request::get('allow_user_add_vote') ? true : false;
                $property->allow_user_view_cf_report = Request::get('allow_user_view_cf_report') ? true : false;
                $property->view_overdue_debt = Request::get('view_overdue_debt') ? true : false;
                
                if($property->allow_user_view_cf_report == true )
                {
                    $property->view_cf_report_type = Request::get('view_cf_report_type');
                }else
                {
                    $property->view_cf_report_type = 1;
                }
				$property->save();
			}
			return redirect('admin/property/posting');
		}else {
			return view('about_property.admin-posting')->with(compact('property'));
		}
	}

    public function printSetting () {
        $property = Property::find(Auth::user()->property_id);
        if(Request::isMethod('post')) {
            if(Auth::user()->role  == 1) {
                $property->document_print_type = Request::get('document_print_type');
                $property->save();
            }
            return redirect('admin/property/print-setting');
        }else {
            return view('about_property.admin-print-setting')->with(compact('property'));
        }
    }

    public function settingRunningDocumentType(){
        $property = Property::find(Auth::user()->property_id);
        $document_format_setting = DocumentFormatSetting::where('property_id',Auth::user()->property_id)->first();
        if(Request::isMethod('post')) {
            $type_format = Request::get('format_type');
            if(Auth::user()->role  == 1) {
                if(isset($document_format_setting)){
                    $document_format_setting_edit = DocumentFormatSetting::find($document_format_setting->id);
                    $document_format_setting_edit->type = $type_format;
                    $document_format_setting_edit->save();
                }else{
                    $new_document_format_setting = new DocumentFormatSetting;
                    $new_document_format_setting->type = $type_format;
                    $new_document_format_setting->property_id = Auth::user()->property_id;
                    $new_document_format_setting->save();
                }

                // Relate Setting Prefix
                $all_type = ['INVOICE','RECEIPT','EXPENSE','PREPAID','WITHDRAWAL','PAYEE'];
                foreach ($all_type as $item) {
                    $document_prefix_setting = DocumentPrefixSetting::where('property_id',Auth::user()->property_id)->where('document_type',$item)->first();
                    if(isset($document_prefix_setting)){
                        $document_prefix_setting_edit = DocumentPrefixSetting::find($document_prefix_setting->id);
                        $example = $this->getExampleRunning($document_prefix_setting_edit->prefix,$property->document_format_setting->type,$document_prefix_setting_edit->is_ce,$document_prefix_setting_edit->year_digit);
                        if($type_format == 0){
                            $document_prefix_setting_edit->running_digit = 8;
                        }else{
                            $document_prefix_setting_edit->running_digit = 5;
                        }
                        $document_prefix_setting_edit->example = $example;
                        $document_prefix_setting_edit->save();
                    }else{
                        $new_document_format_setting = new DocumentPrefixSetting;
                        $year = Carbon::now()->year;
                        $month = str_pad(Carbon::now()->month, 2, '0', STR_PAD_LEFT);
                        $new_document_format_setting->property_id = Auth::user()->property_id;
                        $new_document_format_setting->document_type = $item;
                        $new_document_format_setting->year_start = $year;
                        $new_document_format_setting->month_start = $month;
                        $new_document_format_setting->running_start = 1;
                        if($type_format == 0){
                            $new_document_format_setting->running_digit = 8;
                        }else{
                            $new_document_format_setting->running_digit = 5;
                        }

                        if($item != 'PAYEE'){
                            $new_document_format_setting->prefix = 'NB'.$item[0];
                        }else{
                            $new_document_format_setting->prefix = 'NB'.$item[0].$item[1];
                        }

                        $new_document_format_setting->is_ce = true;
                        $new_document_format_setting->year_digit = 4;
                        $example = $this->getExampleRunning($new_document_format_setting->prefix,$property->document_format_setting->type,$new_document_format_setting->is_ce,$new_document_format_setting->year_digit);
                        $new_document_format_setting->example = $example;
                        $new_document_format_setting->save();
                    }
                }

            }
            return redirect('admin/property/running');
        }else {
            if(isset($document_format_setting) && $property->document_prefix_setting->count()>0){
                foreach ($property->document_prefix_setting as $item){
                    $prefix_setting[$item->document_type] = $item;
                }
                $document_format_setting_type = $document_format_setting->type;
            }else{
                $prefix_setting = null;
                $document_format_setting_type = 0;
            }
            return view('about_property.admin-running-doc-setting')->with(compact('property','prefix_setting','document_format_setting_type'));
        }
    }

    public function settingRunningDocumentFormat(){
        $property = Property::find(Auth::user()->property_id);
        if(Request::isMethod('post')) {

            if(Auth::user()->role  == 1) {
                $document_format_setting = DocumentFormatSetting::where('property_id',Auth::user()->property_id)->first();
                $document_prefix_setting = DocumentPrefixSetting::where('property_id',Auth::user()->property_id)->where('document_type',Request::get('type'))->first();
                if(isset($document_prefix_setting)){
                    $document_prefix_setting_edit = DocumentPrefixSetting::find($document_prefix_setting->id);
                    $document_prefix_setting_edit->prefix = Request::get('prefix');
                    $document_prefix_setting_edit->is_ce = (Request::get('is_ce') == "1")? true : false;
                    $document_prefix_setting_edit->year_digit = Request::get('digit');
                    if($document_format_setting->type == 0){
                        $document_prefix_setting_edit->running_digit = 8;
                    }else{
                        $document_prefix_setting_edit->running_digit = 5;
                    }
                    $example = $this->getExampleRunning($document_prefix_setting_edit->prefix,$property->document_format_setting->type,$document_prefix_setting_edit->is_ce,$document_prefix_setting_edit->year_digit);

                    $document_prefix_setting_edit->example = $example;
                    $document_prefix_setting_edit->save();
                }else{
                    $new_document_prefix_setting = new DocumentPrefixSetting;
                    $year = Carbon::now()->year;
                    $month = str_pad(Carbon::now()->month, 2, '0', STR_PAD_LEFT);
                    $new_document_prefix_setting->property_id = Auth::user()->property_id;
                    $new_document_prefix_setting->document_type = Request::get('type');
                    $new_document_prefix_setting->year_start = $year;
                    $new_document_prefix_setting->month_start = $month;
                    $new_document_prefix_setting->running_start = 1;
                    if($document_format_setting->type == 0){
                        $new_document_prefix_setting->running_digit = 8;
                    }else{
                        $new_document_prefix_setting->running_digit = 5;
                    }
                    $new_document_prefix_setting->prefix = Request::get('prefix');
                    $new_document_prefix_setting->is_ce = (Request::get('is_ce') == "1")? true : false;
                    $new_document_prefix_setting->year_digit = Request::get('digit');
                    $example = $this->getExampleRunning($new_document_prefix_setting->prefix,$property->document_format_setting->type,$new_document_prefix_setting->is_ce,$new_document_prefix_setting->year_digit);
                    $new_document_prefix_setting->example = $example;
                    $new_document_prefix_setting->save();
                }
            }
            return redirect('admin/property/running');
        }else {
            return view('about_property.admin-running-doc-setting')->with(compact('property'));
        }
    }

    public function getExampleRunning($prefix,$type,$is_ce,$year_digit){
        if($is_ce) {
            $year_full = Carbon::now()->year;
        }else{
            $year_full = Carbon::now()->year + 543;
        }

        $year_str_arr = str_split($year_full,2);
        if($year_digit == 2){
            $year = $year_str_arr[1];
        }else{
            $year = $year_str_arr[0].$year_str_arr[1];
        }

        if($type == 1) {
            $result = $prefix . $year . str_pad(1, 5, '0', STR_PAD_LEFT);
        }elseif($type == 2){
            $month = str_pad(Carbon::now()->month, 2, '0', STR_PAD_LEFT);
            $result = $prefix . $year . $month . str_pad(1, 5, '0', STR_PAD_LEFT);
        }else{
            $result = $prefix.str_pad(1, 8, '0', STR_PAD_LEFT);
        }

        return $result;
    }


	public function getform () {
		if(Request::isMethod('post')) {
			$bank_obj = Bank::find(Request::get('bid'));
			return view('about_property.admin-bank-form')->with(compact('bank_obj'));
		}

	}

	public function deleteBank () {
		if(Request::isMethod('post')) {
			if(Auth::user()->role  == 1) {
				$bank = Bank::with('transactionLog')->find(Request::get('bid'));
				if($bank->transactionLog->count()) {
					$bank->active = false;
					$bank->save();
				} else {
					$bank->delete();
				}
				
				return response()->json(['result' => true]);
			}else{
				return response()->json(['result' => false]);
			}
		}
	}


	public function deleteFile () {
		if(Request::isMethod('post')) {
			if(Auth::user()->role  == 1) {
				$file = PropertyFile::find(Request::get('fid'));
				$this->removeFile($file->name);
				$file->delete();
				return response()->json(['result' => true]);
			}else{
				return response()->json(['result' => false]);
			}
		}
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'property-file/'.$folder;
        $directories = Storage::disk('s3')->directories('property-file'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
      Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}

	public function removeFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'property-file/'.$folder."/".$name;
		if(Storage::disk('s3')->has($file_path)) {
			Storage::disk('s3')->delete($file_path);
		}
	}
}