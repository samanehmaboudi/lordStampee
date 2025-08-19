<?php

namespace App\Models;

use PDO;
use PDOException;

class Database
{
    // Connexion PDO unique
    private static ?PDO $pdo = null;

    /**
     * Retourne une connexion PDO à la base de données
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            try {
                
                $dsn  = 'mysql:host=localhost;dbname=Stampee;port=3307;charset=utf8mb4';
                $user = 'root';
                $pass = 'root';

                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, 
            
                ]);

            } catch (PDOException $e) {
                die("Erreur de connexion à la base de données : " . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}
