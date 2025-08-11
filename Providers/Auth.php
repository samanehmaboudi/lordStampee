<?php 
namespace App\Providers;

use App\Providers\View;

class Auth
{
    /**
     * Vérifie la session en comparant le fingerprint (agent + IP)
     */
    public static function session()
    {
        if (isset($_SESSION['fingerPrint']) &&
            $_SESSION['fingerPrint'] === md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR'])) {
            return true;
        } else {
            return View::redirect('login');
            exit();
        }
    }  
}
