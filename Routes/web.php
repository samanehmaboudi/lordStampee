<?php

use App\Routes\Route;
use App\Controllers\AuctionController;

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
Route::get('fiche-produit',  'StampController@showPublic');  // détail

Route::get('fichierProduit', 'StampController@showPublic');

// placer une mise
Route::post('auctions/bid',  'AuctionController@place');

// Enchères
Route::post('auctions/create', 'AuctionController@createForStamp');

Route::get('auctions',                 'AuctionController@index');   // (optionnel) liste
Route::get('auctions/create',          'AuctionController@create');  // (optionnel) form vendeur
Route::post('auctions/store',          'AuctionController@store');   // (optionnel) POST créer



