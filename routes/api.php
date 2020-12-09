<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
// Route::any('logout', 'Api\LoginController@logout');


Route::post('login', 'api\LoginController@login');
Route::group([
    'middleware' => 'api',
], function ($router) {
    Route::post('logout', 'api\LoginController@logout');
    Route::get('getuser', 'api\LoginController@getUser');
    Route::post('register', 'api\LoginController@register');
    Route::get('admin/all', 'api\user\UserController@index');
    Route::post('admin/save', 'api\user\UserController@save');
    Route::post('admin/del', 'api\user\UserController@del');
    Route::get('property/all', 'api\property\PropertyController@index');
});


// Route::middleware('auth:api')->get('/property/all', 'api\property\PropertyController@index');
// Route::group(['middleware' => 'auth:api'], function () {
//     Route::get('user/all', 'api\user\UserController@index');
//     Route::get('property/all', 'api\property\PropertyController@index');
// });




// Route::group(['middleware' => ['before' => 'jwt.auth']], function () {
//     // Route::get('user-profile', 'api\LoginController@userProfile');

    
// });
