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
Route::get('init', 'Api\AppController@init');
Route::post('login', 'Api\LoginController@login');
Route::get('current', 'Api\LoginController@getUser');

Route::post('register', 'Api\AppController@register');
Route::post('logout', 'Api\AppController@logout');
