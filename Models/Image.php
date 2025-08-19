<?php
namespace App\Models;

use App\Models\Database;
use PDO;

class Image
{
    private PDO $db;

    public function __construct()
    {
        
        $this->db = Database::getConnection();
    }

    /** Ajoute une image à un timbre. $type = 'Main' ou 'Additional'. Retourne l'ID créé. */
    public function create(int $stampId, string $url, string $type = 'Additional'): int
    {
        $type = ($type === 'Main') ? 'Main' : 'Additional';
        $sql  = "INSERT INTO Image (image_url, image_type, Stamp_id)
                 VALUES (:u, :t, :sid)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':u'   => $url,
            ':t'   => $type,
            ':sid' => $stampId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** Liste des images d’un timbre (Main d’abord). */
    public function getByStampId(int $stampId): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM Image
             WHERE Stamp_id=?
             ORDER BY image_type='Main' DESC, id ASC"
        );
        $st->execute([$stampId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Supprime UNE image si elle appartient au timbre d’un user donné. */
    public function deleteByIdForOwner(int $imageId, int $ownerUserId): bool
    {
        $sql = "DELETE i FROM Image i
                JOIN Stamp s ON s.id = i.Stamp_id
                WHERE i.id = :iid AND s.User_id = :uid";
        $st = $this->db->prepare($sql);
        $st->execute([':iid'=>$imageId, ':uid'=>$ownerUserId]);
        return $st->rowCount() > 0;
    }

    /** Définit une image comme 'Main' et met les autres en 'Additional'. */
    public function setMain(int $stampId, int $imageId, int $ownerUserId): bool
    {
        $this->db->beginTransaction();
        try {
            // vérif propriété
            $chk = $this->db->prepare(
                "SELECT COUNT(*) FROM Image i
                 JOIN Stamp s ON s.id=i.Stamp_id
                 WHERE i.id=:iid AND i.Stamp_id=:sid AND s.User_id=:uid"
            );
            $chk->execute([':iid'=>$imageId, ':sid'=>$stampId, ':uid'=>$ownerUserId]);
            if ((int)$chk->fetchColumn() === 0) { $this->db->rollBack(); return false; }

            // reset
            $st1 = $this->db->prepare(
                "UPDATE Image i
                 JOIN Stamp s ON s.id=i.Stamp_id
                 SET i.image_type='Additional'
                 WHERE i.Stamp_id=:sid AND s.User_id=:uid"
            );
            $st1->execute([':sid'=>$stampId, ':uid'=>$ownerUserId]);

            // set main
            $st2 = $this->db->prepare(
                "UPDATE Image i
                 JOIN Stamp s ON s.id=i.Stamp_id
                 SET i.image_type='Main'
                 WHERE i.id=:iid AND i.Stamp_id=:sid AND s.User_id=:uid"
            );
            $st2->execute([':iid'=>$imageId, ':sid'=>$stampId, ':uid'=>$ownerUserId]);

            $this->db->commit();
            return $st2->rowCount() > 0;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
