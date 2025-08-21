<?php
namespace App\Routes;

final class Route
{
    private static array $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    public static function get(string $uri, $action): void
    {
        self::$routes['GET'][self::normalize($uri)] = $action;
    }

    public static function post(string $uri, $action): void
    {
        self::$routes['POST'][self::normalize($uri)] = $action;
    }

    private static function normalize(?string $uri): string
    {
        $uri = (string)$uri;
        // retire query string si présent
        if (strpos($uri, '?') !== false) {
            $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        }
        // force un seul slash de tête + retire les slashes de fin
        $uri = '/' . ltrim($uri, '/');
        $uri = rtrim($uri, '/');
        if ($uri === '') $uri = '/';
        return $uri;
    }

    public static function resolve(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // chemin demandé (sans query)
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // base path = sous-dossier où se trouve index.php (ex: /Stampee-new/Projet-web1-sprint1)
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base && strpos($requestPath, $base) === 0) {
            $requestPath = substr($requestPath, strlen($base));
        }

        $uri = self::normalize($requestPath);  // IMPORTANT: '/' pour la racine

        $action = self::$routes[$method][$uri] ?? null;
        if (!$action && $uri !== '/') {
            // tente aussi sans slash final (sécurité)
            $alt = rtrim($uri, '/');
            $action = self::$routes[$method][$alt === '' ? '/' : $alt] ?? null;
        }

        if (!$action) {
            http_response_code(404);
            echo "404 - Page non trouvée.";
            return;
        }

        // action: "HomeController@index" ou [ClassName::class, 'method'] ou Closure
        if (is_string($action) && strpos($action, '@') !== false) {
            [$ctrl, $meth] = explode('@', $action, 2);
            $fqcn = '\\App\\Controllers\\' . ltrim($ctrl, '\\');
            (new $fqcn())->{$meth}();
            return;
        }
        if (is_array($action)) {
            [$fqcn, $meth] = $action;
            (new $fqcn())->{$meth}();
            return;
        }
        if ($action instanceof \Closure) {
            $action();
            return;
        }

        http_response_code(500);
        echo "500 - Action de route invalide.";
    }
}
