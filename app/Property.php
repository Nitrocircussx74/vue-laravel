<?php
namespace App;
use App\GeneralModel;
class Property extends GeneralModel
{
    protected $table = 'property';
    protected $fillable = ['id','property_name_th','property_name_en','area_size','unit_size','construction_by','address_th','address_en','street_th','street_en','province','postcode','lat','lng','about','tel','common_area_fee_type','common_area_fee_rate','min_price','max_price','property_type','tax_id','fax','address_no','juristic_person_name_th','juristic_person_name_en','common_area_fee_land_type','common_area_fee_land_rate','is_add_initial_water_meter','is_add_initial_electric_meter','document_print_type','project_banner','view_cf_report_type','view_overdue_debt'];
    // Close timestamp
	public $timestamps = true;

	protected $rules = array(
        'property_name_th'          => 'required',
        'property_name_en'          => 'required',
        'juristic_person_name_th'   => 'required',
        'juristic_person_name_en'   => 'required'
    );

    protected $messages = array(
         //'property_name_th.required' => 'ระบุชื่อภาษาไทย',
         //'property_name_en.required' => 'ระบุชื่อภาษาอังกฤษ'
     );

    public function property_admin()
    {
        return $this->hasOne('App\User');
    }

    public function banks()
    {
        return $this->hasMany('App\Bank');
    }

    public function propertyFile()
    {
        return $this->hasMany('App\PropertyFile');
    }

    public function property_unit()
    {
        return $this->hasMany('App\PropertyUnit');
    }

    public function property_banner()
    {
        return $this->hasMany('App\PropertyBanner')->orderBy('ordering','asc');
    }

    public function has_province ()
    {
        return $this->hasOne('App\Province','code','province');
    }

     public function settings()
    {
        return $this->hasOne('App\PropertySettings');
    }

    public function has_contract ()
    {
        return $this->hasMany('App\PropertyContract','property_id','id')->orderBy('created_at','asc');
    }

    public function lastest_contract ()
    {
        return $this->hasOne('App\PropertyContract','property_id','id')->orderBy('created_at','desc');
    }

    public function sale_property()
    {
        return $this->hasOne('App\SalePropertyDemo','property_id','id');
    }

    public function users()
    {
        return $this->hasMany('App\User','property_id','id');
    }

    public function manager()
    {
        return $this->hasOne('App\PropertyManager','property_id','id');
    }

    public function userCount()
    {
        return $this->users()
            ->selectRaw('property_id, count(*) as count')
            ->where('role','=',2)
            ->groupBy('property_id');
    }

    public function document_format_setting()
    {
        return $this->hasOne('App\DocumentFormatSetting','property_id','id');
    }

    public function document_prefix_setting()
    {
        return $this->hasMany('App\DocumentPrefixSetting','property_id','id');
    }

    public function monthly_counter_doc()
    {
        return $this->hasMany('App\MonthlyCounterDoc','property_id','id');
    }

    public function yearly_counter_doc()
    {
        return $this->hasMany('App\YearlyCounterDoc','property_id','id');
    }

    public function total_counter_doc()
    {
        return $this->hasMany('App\TotalCounterDoc','property_id','id');
    }

    public function feature()
    {
        return $this->hasOne('App\PropertyFeature','property_id','id');
    }
    public function smartbill()
    {
        return $this->hasOne('App\SmartBill','property_id','id');
    }

    public function activeSmartbill()
    {
        return $this->hasOne('App\SmartBill','property_id','id')->where('activated',true);
    }
}