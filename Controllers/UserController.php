<?php
namespace App\Controllers;

use App\Models\User;
use App\Providers\View;

class UserController
{
    private function requireLogin()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['loggedin'])) {
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
            'name' => '', 'email' => '',
            'name_err' => '', 'email_err' => '', 'password_err' => '', 'exists_err' => ''
        ]);
    }

    // POST /user/create — traiter form
    public function store()
    {
        $this->requireLogin();

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $name_err = $name === '' ? "Nom requis." : '';
        $email_err = $email === '' ? "Email requis." : (!filter_var($email, FILTER_VALIDATE_EMAIL) ? "Email invalide." : '');
        $password_err = $password === '' ? "Mot de passe requis." : '';

        if ($name_err || $email_err || $password_err) {
            return View::render('users/create', compact('name','email','name_err','email_err','password_err') + ['exists_err' => '']);
        }

        $model = new User();
        if ($model->exists($email)) {
            return View::render('users/create', compact('name','email') + [
                'name_err' => '', 'email_err' => '', 'password_err' => '', 'exists_err' => "Cet email existe déjà."
            ]);
        }

        $ok = $model->create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
        ]);

        if ($ok) {
            return View::redirect('users');
        }

        return View::render('users/create', compact('name','email') + [
            'name_err' => '', 'email_err' => '', 'password_err' => '', 'exists_err' => "Erreur d’enregistrement."
        ]);
    }

    // GET /profil — voir/éditer profil
    public function profil()
    {
        $this->requireLogin();
        $model = new User();
        $user = $model->findById($_SESSION['id']);
        return View::render('users/profil', ['user' => $user, 'err' => '', 'success' => '']);
    }

    // POST /update-user — mettre à jour nom/email du profil
    public function update()
    {
        $this->requireLogin();
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        $err = '';
        $success = '';

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = "Nom et email valides requis.";
        } else {
            $model = new User();
            if ($model->updateNameEmail($_SESSION['id'], $name, $email)) {
                $success = "Profil mis à jour.";
            } else {
                $err = "Une erreur est survenue lors de la mise à jour.";
            }
        }

        $model = new User();
        $user  = $model->findById($_SESSION['id']);
        return View::render('users/profil', ['user' => $user, 'err' => $err, 'success' => $success]);
    }
}
