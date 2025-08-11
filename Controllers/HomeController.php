<?php

namespace App\Controllers;

use App\Providers\View;

class HomeController
{
    public function index()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        

        return View::render('pages/pageAccueil', [
            'title' => 'Accueil',
        ]);
        
    }
}
