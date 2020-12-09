<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('{any?}', function () {
    return view('welcome');
});
Route::get('/front/{any?}', function () {
    return view('welcome');
})->where('any','.*');

// Auth::routes();

Route::get('/app', 'HomeController@index')->name('home');
// Route::get('user/all', 'api\user\UserController@index');
Route::group(['middleware' => 'api'], function () {
    Route::resource('front/admin/property/units', 'PropertyAdmin\PropertyUnitController@unitList');
    Route::post('front/admin/property/units/getUnit', 'PropertyAdmin\PropertyUnitController@getUnit');
    Route::post('front/admin/property/units/edit/form', 'PropertyAdmin\PropertyUnitController@editForm');
    Route::post('front/admin/property/units/edit-tenant/form', 'PropertyAdmin\PropertyUnitController@editTenantForm');
    Route::post('front/admin/property/units/add', 'PropertyAdmin\PropertyUnitController@add');
    Route::post('front/admin/property/units/edit', 'PropertyAdmin\PropertyUnitController@edit');
    Route::post('front/admin/property/units/edit-tenant', 'PropertyAdmin\PropertyUnitController@editTenant');
    Route::post('front/admin/property/units/clear', 'PropertyAdmin\PropertyUnitController@clearUnit');
    Route::post('front/admin/property/unit/check-balance', 'PropertyAdmin\PropertyUnitController@checkBalance');
    Route::post('front/admin/property/units/delete-tenant', 'PropertyAdmin\PropertyUnitController@deleteTenant');

    Route::resource('front/admin/property/units-invite', 'PropertyAdmin\PropertyUnitController@inviteCodeList');
    Route::post('front/admin/property/units/export', 'PropertyAdmin\PropertyUnitController@exportPropertyUnit');
});
