<?php
namespace App\Models;

use PDO;

class Favorite
{
    private PDO $pdo;

    private const TBL = 'favorite';
    private const COL_USER = 'user_id';
    private const COL_STAMP = 'stamp_id';

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function exists(int $userId, int $stampId): bool {
        $sql = "SELECT 1
                FROM `".self::TBL."`
                WHERE `".self::COL_USER."` = ? AND `".self::COL_STAMP."` = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$userId, $stampId]);
        return (bool)$st->fetchColumn();
    }

    public function add(int $userId, int $stampId): bool {
        $sql = "INSERT IGNORE INTO `".self::TBL."` (`".self::COL_USER."`, `".self::COL_STAMP."`)
                VALUES (?, ?)";
        $st = $this->pdo->prepare($sql);
        return $st->execute([$userId, $stampId]);
    }

    public function remove(int $userId, int $stampId): bool {
        $sql = "DELETE FROM `".self::TBL."`
                WHERE `".self::COL_USER."` = ? AND `".self::COL_STAMP."` = ?";
        $st = $this->pdo->prepare($sql);
        return $st->execute([$userId, $stampId]);
    }

    /** Retourne true si maintenant favori, false si retiré */
    public function toggle(int $userId, int $stampId): bool {
        if ($this->exists($userId, $stampId)) {
            $this->remove($userId, $stampId);
            return false;
        }
        $this->add($userId, $stampId);
        return true;
    }

    public function listByUser(int $userId): array {
        $sql = "SELECT s.*
                FROM `".self::TBL."` f
                JOIN `stamp` s ON s.id = f.`".self::COL_STAMP."`
                WHERE f.`".self::COL_USER."` = ?
                ORDER BY f.created_at DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
