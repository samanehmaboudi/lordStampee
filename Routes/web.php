<?php

use App\Routes\Route;
// Auth
Route::get('login',  'AuthController@login');
Route::post('login', 'AuthController@login');
Route::get('register','AuthController@register');
Route::post('register','AuthController@register');
Route::get('logout', 'AuthController@logout');


// Users (liste + création + profil)
Route::get('users',        'UserController@index');
Route::get('user/create',  'UserController@create');
Route::post('user/create', 'UserController@store');
Route::get('profil',       'UserController@profil');
Route::post('update-user', 'UserController@update');

// Accueil
Route::get('/', 'HomeController@index');
Route::get('home', 'HomeController@index');

