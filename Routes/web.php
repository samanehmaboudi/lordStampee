<?php

use App\Routes\Route;

// Auth
Route::get('login',    'AuthController@login');
Route::post('login',   'AuthController@login');
Route::get('register', 'AuthController@register');
Route::post('register','AuthController@register');
Route::get('logout',   'AuthController@logout');

// Users (liste + création)
// Users
Route::get('users/create',  'UserController@create');
Route::post('users/create', 'UserController@store');
Route::get('users',         'UserController@index');



// Profil (mon compte via la session)
Route::get('profil',                 'ProfileController@show');            // afficher le profil
Route::post('profil',                'ProfileController@update');          // maj nom/email
Route::post('profil/mot-de-passe',   'ProfileController@updatePassword');  // maj mot de passe

// Accueil
Route::get('/',   'HomeController@index');
Route::get('home','HomeController@index');
Route::get('','HomeController@index');



// Timbres
Route::get('stamps',         'StampController@index');      // liste
Route::get('stamps/create',  'StampController@create');     // form ajout
Route::post('stamps/create', 'StampController@store');      // submit ajout

Route::get('stamps/edit',    'StampController@edit');       
Route::post('stamps/update', 'StampController@update');     
Route::post('stamps/delete', 'StampController@destroy');    

// Catalogue public
Route::get('catalogue',      'StampController@indexPublic');  // liste
Route::get('fiche-produit',  'StampController@showPublic');   // détail
