<?php

namespace App\Models;

use PDO;

class CRUD
{
    private $pdo;
    private $table;

    /**
     * Constructeur : initialise la connexion PDO et le nom de la table
     */
    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }


    public function unique(string $column, mixed $value): array|false
{
    $sql = "SELECT * FROM {$this->table} WHERE {$column} = :value LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['value' => $value]);
    return $stmt->fetch(\PDO::FETCH_ASSOC);
}


    /**
     * Récupère tous les enregistrements de la table
     */
    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un enregistrement par son ID
     */
    public function find(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouvel enregistrement dans la table
     */
    public function create(array $data): bool
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Met à jour un enregistrement existant par son ID
     */
    public function update(int $id, array $data): bool
    {
        $set = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($data)));

        // On ajoute l'ID à la fin du tableau
        $data['id'] = $id;

        $sql = "UPDATE {$this->table} SET $set WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Supprime un enregistrement par son ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
