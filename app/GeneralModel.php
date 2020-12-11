<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
class GeneralModel extends Model
{
	public $timestamps;
    public function validate($data)
    {
    	Validator::extend('not_zero', function($attribute, $value, $parameters) {
            return $value>0;
        });

        $v = Validator::make($data, $this->rules, $this->messages);
        return $v;
    }
}
