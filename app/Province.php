<?php
namespace App;
use App\GeneralModel;
use Illuminate\Foundation\Application;
class Province extends GeneralModel
{
    protected $table = 'province';
	public $timestamps = false;

    public function getProvince()
    {	
    	/*$lang = session()->get('lang');
        $provinces = array(''=> trans('messages.AboutProp.province') );
        return $provinces += $this->orderBy('name_'.$lang, 'ASC')->lists('name_'.$lang,'code')->toArray();*/

        $lang = session()->get('lang');
        $provinces = $this->orderBy('name_'.$lang, 'ASC')->lists('name_'.$lang,'code')->toArray();
        asort($provinces);
        $provinceFirst = array(''=> trans('messages.AboutProp.province'));
        $provincesAll = $provinceFirst + $provinces;
        return $provincesAll;
    }
}
