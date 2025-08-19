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
        // 1) Récupère le chemin sans la query string
        $uri = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // 2) Retire le prefix BASE si présent (ex: /Stampee-new/Projet-web1-sprint1)
        $base = defined('BASE') ? rtrim(BASE, '/') : '';
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        // 3) Normalise
        $page = trim($uri, '/');
        if ($page === '') {
            $page = 'accueil';
        }

        // 4) Essaie sans/avec slash final
        if (!isset(self::$routes[$page])) {
            $alt = rtrim($page, '/');
            if (isset(self::$routes[$alt])) {
                $page = $alt;
            }
        }

        // 5) Résolution
        if (isset(self::$routes[$page])) {
            [$controller, $method] = explode('@', self::$routes[$page], 2);
            $controllerClass = "App\\Controllers\\$controller";
            if (class_exists($controllerClass) && method_exists($controllerClass, $method)) {
                $instance = new $controllerClass;
                $instance->$method();
                return;
            }
            echo "Erreur : méthode ou contrôleur introuvable.";
        } else {
            http_response_code(404);
            echo "404 - Page non trouvée.";
        }
    }
}
