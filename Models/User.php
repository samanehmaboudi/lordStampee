<?php

namespace App\Models;

use App\Models\CRUD;
use App\Models\Database;
use PDO;

class User extends CRUD
{
    protected string $table = 'user';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email', 'password_hash'];

    public function __construct()
    {
        parent::__construct(Database::getConnection(), $this->table);
    }

    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** Vérifie identifiants (email + mot de passe) */
    public function checkUser(string $email, string $password): bool
    {
        $user = $this->findByEmail($email);
        if (!$user) return false;

        return password_verify($password, $user['password_hash']);
    }

    /** Récupère un utilisateur par email */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT u.id, u.name, u.email, u.role, u.password_hash
                FROM `user` u
                WHERE u.email = :email
                LIMIT 1";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT u.id, u.name, u.email, u.role, u.password_hash, u.creationDate
                FROM `user` u
                WHERE u.id = :id
                LIMIT 1";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function exists(string $email): bool
    {
        $sql = "SELECT 1 FROM `user` WHERE email = :email LIMIT 1";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([':email' => strtolower(trim($email))]);
        return (bool)$stmt->fetchColumn();
    }

    public function getAll(): array
    {
        $sql = "SELECT id, name, email, creationDate
                FROM `user`
                ORDER BY id DESC";
        $stmt = Database::getConnection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Création : stocke le hash dans password_hash */
    public function create(array $data): bool
    {
        $name  = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $pass  = (string)($data['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
            return false;
        }

        // parent::create() utilise $fillable -> name, email, password_hash
        return parent::create([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => $this->hashPassword($pass),
        ]);
    }

    public function updateNameEmail(int $id, string $name, string $email): bool
    {
        $sql = "UPDATE `user` SET name = :n, email = :e WHERE id = :id";
        $stmt = Database::getConnection()->prepare($sql);
        return $stmt->execute([':n' => $name, ':e' => $email, ':id' => $id]);
    }

    /** Mise à jour du mot de passe (hashé) */
    public function updatePassword(int $id, string $newPassword): bool
    {
        $sql = "UPDATE `user` SET password_hash = :p WHERE id = :id";
        $stmt = Database::getConnection()->prepare($sql);
        return $stmt->execute([
            ':p'  => $this->hashPassword($newPassword),
            ':id' => $id
        ]);
    }
}
