<?php

namespace App;
use App\GeneralModel;
use Request;
use Auth;
class VehicleMake extends GeneralModel
{
    protected $table = 'vehicle_make';
	public $timestamps = false;

	public function getMotocycle()
    {
        $vehicles = array('0'=> trans('messages.Prop_unit.veh_brand') );
        return $vehicles += $this->where('type','M')->orderBy('name', 'ASC')->lists('name','name')->toArray();
    }

    public function getCar()
    {
        $vehicles = array('0'=> trans('messages.Prop_unit.veh_brand') );
        return $vehicles += $this->where('type','C')->orderBy('name', 'ASC')->lists('name','name')->toArray();
    }
}
