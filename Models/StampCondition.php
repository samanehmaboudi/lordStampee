<?php
namespace App\Models;

use PDO;
use App\Models\Database;

class StampCondition
{
    private static function pdo(): PDO
    {
        return Database::getConnection();
    }

    public static function getAll(): array
    {
        $sql = "SELECT id, name FROM Stamp_Condition ORDER BY name";
        return self::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findName(int $id): ?string
    {
        $st = self::pdo()->prepare("SELECT name FROM Stamp_Condition WHERE id = ?");
        $st->execute([$id]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string)$v;
    }
}
