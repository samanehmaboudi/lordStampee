<?php
namespace App\Models;

use App\Models\Database;
use PDO;

class Auction
{
    private PDO $db;

    public function __construct()
    {
        
        $this->db = Database::getConnection();
    }

    /** Crée une enchère, Retourne l'ID créé. */
    public function create(array $d): int
    {
        $sql = "INSERT INTO Auction
                   (start_date, end_date, starting_price, current_price, is_lord_favorite, Stamp_id)
                VALUES
                   (:sd, :ed, :sp, :cp, :fav, :sid)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':sd'  => $d['start_date'],                 // 'Y-m-d H:i:s'
            ':ed'  => $d['end_date'],                   // 'Y-m-d H:i:s'
            ':sp'  => (float)$d['starting_price'],
            ':cp'  => (float)$d['current_price'],       // = starting_price au départ
            ':fav' => !empty($d['is_lord_favorite']) ? 1 : 0,
            ':sid' => (int)$d['Stamp_id'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** Récupère l’enchère d’un timbre (la plus récente si plusieurs). */
    public function findLatestByStampId(int $stampId): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM Auction
             WHERE Stamp_id=?
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->execute([$stampId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** True si l’enchère est en cours maintenant (selon les dates). */
    public function isActive(array $auction): bool
    {
        $now = new \DateTimeImmutable('now');
        return ($now >= new \DateTimeImmutable($auction['start_date']))
            && ($now <= new \DateTimeImmutable($auction['end_date']));
    }
}
