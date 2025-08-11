<?php
// Activer les erreurs PHP
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Autoload Composer (chargement automatique des classes)
require_once __DIR__ . '/vendor/autoload.php';

// Configuration globale (constantes comme BASE, ASSET, etc.)
require_once __DIR__ . '/config.php';

// Déclaration des routes
require_once __DIR__ . '/Routes/web.php';

// Exécuter la résolution de la route
\App\Routes\Route::resolve();

