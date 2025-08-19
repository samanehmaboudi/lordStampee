<?php
namespace App\Models;

use PDO;
use App\Models\Database;

class Country
{
    private static function pdo(): PDO
    {
        return Database::getConnection();
    }

    public static function getAll(): array
    {
        $sql = "SELECT id, name FROM Country ORDER BY name";
        return self::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findName(int $id): ?string
    {
        $st = self::pdo()->prepare("SELECT name FROM Country WHERE id = ?");
        $st->execute([$id]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string)$v;
    }
}
