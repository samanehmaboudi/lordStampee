<?php

namespace App\Providers;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Twig\Extension\DebugExtension;

class View
{
  
    private static ?Environment $twig = null;
    private static string $baseDir;

    /**
     * Rend une vue Twig avec données + variables globales
     */
    public static function render(string $template, array $data = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (self::$twig === null) {
            // IMPORTANT: dossier "views" en minuscules
            self::$baseDir = realpath(__DIR__ . '/../views') ?: (__DIR__ . '/../views');
            $loader = new FilesystemLoader(self::$baseDir);

            self::$twig = new Environment($loader, [
                'cache' => false,
                'debug' => true,
                'auto_reload' => true,
            ]);
            self::$twig->addExtension(new DebugExtension());

            // Variables globales accessibles partout
            self::$twig->addGlobal('asset', defined('ASSET') ? ASSET : '');
            self::$twig->addGlobal('base',  defined('BASE')  ? BASE  : '');
            self::$twig->addGlobal('session', $_SESSION);

            // Déterminer si l'utilisateur est invité (guest)
            $guest = true;
            $fp = md5(($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? ''));
            if (!empty($_SESSION['fingerPrint']) && $_SESSION['fingerPrint'] === $fp) {
                $guest = false;
            }
            self::$twig->addGlobal('guest', $guest);
        } else {
            // mettre à jour la session globale si elle a changé
            self::$twig->addGlobal('session', $_SESSION);
        }

        $tpl = $template . '.twig';

       
        $fullTwig = self::$baseDir . DIRECTORY_SEPARATOR . $tpl;
        if (!is_file($fullTwig)) {
         
            $phpTpl = $template . '.php';
            $fullPhp = self::$baseDir . DIRECTORY_SEPARATOR . $phpTpl;
            if (is_file($fullPhp)) {
                $tpl = $phpTpl;
            }
        }

        echo self::$twig->render($tpl, $data);
    }

    /**
     * Redirige vers une autre page
     */
    public static function redirect(string $url): void
    {
        // BASE devrait être une URL relative 
        $base = defined('BASE') ? rtrim(BASE, '/') : '';
        $path = '/' . ltrim($url, '/');
        header('Location: ' . $base . $path);
        exit;
    }


    
}
