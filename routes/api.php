<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
// Route::any('logout', 'Api\LoginController@logout');

Route::prefix('/user')->group(function () {

    Route::get('init', 'api\AppController@init');
    Route::post('login', 'api\LoginController@login');
    Route::get('current', 'api\LoginController@getUser');
    Route::post('register', 'api\AppController@register');
    Route::post('logout', 'api\AppController@logout');

    Route::middleware('auth:api')->get('/all', 'api\user\UserController@index');
});

// Route::get('init', 'api\AppController@init');
// Route::post('login', 'api\LoginController@login');
// Route::get('current', 'api\LoginController@getUser');

// Route::post('register', 'api\AppController@register');
// Route::post('logout', 'api\AppController@logout');
