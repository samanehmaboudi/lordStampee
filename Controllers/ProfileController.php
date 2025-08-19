<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Providers\View;
use PDO;

class ProfileController
{
    // --- Helpers minimes ---
    private function id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }
    private function need(): void
    {
        if (empty($_SESSION['loggedin']) || !$this->id()) $this->go('/login');
    }
    private function base(): string
    {
        if (defined('BASE_URL')) return rtrim((string)BASE, '/');
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $dir === '' ? '/' : $dir;
    }
    private function go(string $p): void
    {
        header('Location: ' . $this->base() . $p);
        exit;
    }

    // Connexion PDO (crée 1 seule instance)
    private function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $dsn = 'mysql:host=localhost;port=3307;dbname=Stampee;charset=utf8mb4';
        $pdo = new PDO($dsn, 'root', 'root', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    // --- Actions ---
    // GET /profil
    public function show(): void
    {
        $this->need();
        $st = $this->db()->prepare(
            "SELECT `id`, `name`, `email`, `role` FROM `user` WHERE `id` = ?"
        );
        $st->execute([$this->id()]);
        $user = $st->fetch() ?: [];

        View::render('users/profil', [
            'user' => $user,
            'base' => $this->base(),
        ]);
    }

    // POST /profil  (maj nom/email)
    public function update(): void
    {
        $this->need();
        $name  = trim($_POST['name']  ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_err'] = 'Nom ou email invalide.';
            $this->go('/profil');
        }

        $db = $this->db();
        $id = $this->id();
        // Email déjà pris par un autre
        $c = $db->prepare("SELECT 1 FROM `user` WHERE email=? AND id<>?");
        $c->execute([$email, $id]);
        if ($c->fetch()) {
            $_SESSION['flash_err'] = 'Email déjà utilisé.';
            $this->go('/profil');
        }

        $db->prepare("UPDATE `user` SET name=?, email=? WHERE id=?")->execute([$name, $email, $id]);
        $_SESSION['username'] = $name;
        $_SESSION['flash_ok'] = 'Profil mis à jour.';
        $this->go('/profil');
    }

    // POST /profil/mot-de-passe
    public function updatePassword(): void
    {
        $this->need();
        $cur = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $cfm = (string)($_POST['confirm_password'] ?? '');

        if ($new !== $cfm || strlen($new) < 8) {
            $_SESSION['flash_err'] = 'Mot de passe invalide.';
            $this->go('/profil');
        }

        $db = $this->db();
        $id = $this->id();
        $st = $db->prepare("SELECT password_hash FROM `user` WHERE id=?");
        $st->execute([$id]);
        $hash = (string)$st->fetchColumn();

        if (!$hash || !password_verify($cur, $hash)) {
            $_SESSION['flash_err'] = 'Mot de passe actuel incorrect.';
            $this->go('/profil');
        }
        if (password_verify($new, $hash)) {
            $_SESSION['flash_err'] = 'Choisissez un mot de passe différent.';
            $this->go('/profil');
        }

        $db->prepare("UPDATE `user` SET password_hash=? WHERE id=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $id]);

        $_SESSION['flash_ok'] = 'Mot de passe modifié.';
        $this->go('/profil');
    }

    
}
