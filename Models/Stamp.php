<?php
namespace App\Models;

use App\Models\Database; // adapte si ton namespace diffère
use PDO;

class Stamp
{
    private PDO $db;

    public function __construct()
    {
        // ⬇️ Adapte cette ligne si ta classe Database a un autre nom de méthode
        // (ex: Database::connect() ou Database::getInstance()->getConnection())
        $this->db = Database::getConnection();
    }

    /* === Listes pour le formulaire === */
    public function getCountries(): array {
        $st = $this->db->query("SELECT id, name FROM Country ORDER BY name");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories(): array {
        $st = $this->db->query("SELECT id, name FROM Category ORDER BY name");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConditions(): array {
        $st = $this->db->query("SELECT id, name FROM Stamp_Condition ORDER BY name");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getColors(): array {
        $st = $this->db->query("SELECT id, name FROM Color ORDER BY name");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /* === Création === */
    public function create(array $d): int
    {
        $sql = "INSERT INTO Stamp
                   (name, creationDate, User_id, condition_id, country_id, category_id, color_id)
                VALUES
                   (:name, :cd, :uid, :cond, :country, :cat, :color)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':name'   => $d['name'],
            ':cd'     => $d['creationDate'],
            ':uid'    => (int)$d['User_id'],
            ':cond'   => (int)$d['condition_id'],
            ':country'=> (int)$d['country_id'],
            ':cat'    => (int)$d['category_id'],
            ':color'  => ($d['color_id'] !== '' ? (int)$d['color_id'] : null),
        ]);
        return (int)$this->db->lastInsertId();
    }

    /* === Lecture : liste par propriétaire === */
    public function getAllByUserId(int $userId): array
    {
        $sql = "SELECT s.id, s.name, s.creationDate,
                       c.name  AS country,
                       cat.name AS category
                FROM Stamp s
                LEFT JOIN Country  c   ON c.id=s.country_id
                LEFT JOIN Category cat ON cat.id=s.category_id
                WHERE s.User_id = :uid
                ORDER BY s.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':uid'=>$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /* === Lecture : un timbre si je suis propriétaire === */
    public function findByIdForOwner(int $id, int $userId): ?array
    {
        $st = $this->db->prepare("SELECT * FROM Stamp WHERE id=? AND User_id=?");
        $st->execute([$id, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* === Mise à jour (protégée par auteur) === */
    public function updateForOwner(int $id, int $userId, array $d): bool
    {
        $sql = "UPDATE Stamp
                   SET name=:name,
                       creationDate=:cd,
                       country_id=:country,
                       category_id=:cat,
                       condition_id=:cond,
                       color_id=:color
                 WHERE id=:id AND User_id=:uid";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':name'   => $d['name'],
            ':cd'     => $d['creationDate'],
            ':country'=> (int)$d['country_id'],
            ':cat'    => (int)$d['category_id'],
            ':cond'   => (int)$d['condition_id'],
            ':color'  => ($d['color_id'] !== '' ? (int)$d['color_id'] : null),
            ':id'     => $id,
            ':uid'    => $userId,
        ]);
        return $st->rowCount() > 0;
    }

    /* === Suppression (protégée par auteur) === */
    public function deleteForOwner(int $id, int $userId): bool
    {
        $st = $this->db->prepare("DELETE FROM Stamp WHERE id=? AND User_id=?");
        $st->execute([$id, $userId]);
        return $st->rowCount() > 0;
    }
}
