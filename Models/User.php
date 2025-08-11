<?php

namespace App\Models;

use App\Models\CRUD;
use App\Models\Database;
use PDO;

class User extends CRUD
{
    protected string $table = 'user'; 
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email', 'password'];

    public function __construct()
    {
        parent::__construct(Database::getConnection(), $this->table);
    }

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    // Connexion simple : vérifie et remplit la session
    public function checkUser(string $email, string $password): bool
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $user = $this->findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['loggedin'] = true;
            $_SESSION['id']       = $user['id'];
            $_SESSION['username'] = $user['name'];
            $_SESSION['fingerPrint'] = md5(($_SERVER['HTTP_USER_AGENT'] ?? '').($_SERVER['REMOTE_ADDR'] ?? ''));
            return true;
        }

        return false;
    }

    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT u.id, u.name, u.email, u.password
                FROM `user` u
                WHERE u.email = :email
                LIMIT 1";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT u.id, u.name, u.email, u.password, u.creationDate
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
        $stmt->execute([':email' => $email]);
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

    public function create(array $data): bool
    {
        // parent::create() utilise $fillable => name, email, password
        $data['password'] = $this->hashPassword($data['password']);
        return parent::create($data);
    }

    public function updateNameEmail(int $id, string $name, string $email): bool
    {
        $sql = "UPDATE `user` SET name = :n, email = :e WHERE id = :id";
        $stmt = Database::getConnection()->prepare($sql);
        return $stmt->execute([':n' => $name, ':e' => $email, ':id' => $id]);
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        $sql = "UPDATE `user` SET password = :p WHERE id = :id";
        $stmt = Database::getConnection()->prepare($sql);
        return $stmt->execute([
            ':p'  => $this->hashPassword($newPassword),
            ':id' => $id
        ]);
    }
}
