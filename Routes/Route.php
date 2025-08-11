<?php

namespace App\Routes;

class Route
{
    public static array $routes = [];

    /**
     * Déclare une route GET
     */
    public static function get(string $page, string $callback): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            self::$routes[$page] = $callback;
        }
    }

    /**
     * Déclare une route POST
     */
    public static function post(string $page, string $callback): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::$routes[$page] = $callback;
        }
    }

    /**
     * Résout la route demandée
     */
    public static function resolve(): void
    {
        $uri = $_SERVER['PATH_INFO'] ?? '/';
        $path = explode("/",$uri)[1];
             // Enlève le slash initial s'il existe
        $page = !empty( $path)? $path :'accueil';

       
        if (isset(self::$routes[$page])) {
            [$controller, $method] = explode('@', self::$routes[$page]);
            $controllerClass = "App\\Controllers\\$controller";
    
            if (class_exists($controllerClass) && method_exists($controllerClass, $method)) {
                $instance = new $controllerClass;
                $instance->$method();
            } else {
                echo "Erreur : méthode ou contrôleur introuvable.";
            }
        } else {
            echo "404 - Page non trouvée.";
        }
    }
    
}
