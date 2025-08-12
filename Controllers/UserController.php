<?php

namespace App\Controllers;

use App\Models\User;
use App\Providers\View;

class UserController
{
    private function requireLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
            View::redirect('login');
            exit;
        }
    }

    // GET /users — liste
    public function index()
    {
        $this->requireLogin();
        $model = new User();
        $users = $model->getAll();
        return View::render('users/index', ['users' => $users]);
    }

    // GET /user/create — afficher form
    public function create()
    {
        $this->requireLogin();
        return View::render('users/create', [
            'name' => '',
            'email' => '',
            'name_err' => '',
            'email_err' => '',
            'password_err' => '',
            'exists_err' => ''
        ]);
    }

    // POST /user/create — traiter form
    public function store()
    {
        $this->requireLogin();

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $name_err     = $name === '' ? "Nom requis." : '';
        $email_err    = $email === '' ? "Email requis." : (!filter_var($email, FILTER_VALIDATE_EMAIL) ? "Email invalide." : '');
        $password_err = $password === '' ? "Mot de passe requis." : '';

        if ($name_err || $email_err || $password_err) {
            return View::render('users/create', compact('name', 'email', 'name_err', 'email_err', 'password_err') + ['exists_err' => '']);
        }

        $model = new User();
        if ($model->exists($email)) {
            return View::render('users/create', compact('name', 'email') + [
                'name_err' => '',
                'email_err' => '',
                'password_err' => '',
                'exists_err' => "Cet email existe déjà."
            ]);
        }

        // IMPORTANT : que User::create() hash bien le mot de passe.
        $ok = $model->create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
        ]);

        if ($ok) {
            $_SESSION['flash_ok'] = 'Utilisateur créé avec succès.';
            return View::redirect('users');
        }

        return View::render('users/create', compact('name', 'email') + [
            'name_err' => '',
            'email_err' => '',
            'password_err' => '',
            'exists_err' => "Erreur d’enregistrement."
        ]);
    }
}
