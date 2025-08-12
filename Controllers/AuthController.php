<?php

namespace App\Controllers;

use App\Models\User;
use App\Providers\View;

class AuthController
{
    // Affiche et traite le login
    public function login()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // Déjà connecté → liste des utilisateurs (ou 'home' si tu préfères)
        if (!empty($_SESSION['loggedin'])) {
            return View::redirect('users');
        }

        // Récupérer un éventuel message flash (ex: après register)
        $flash = $_SESSION['flash'] ?? '';
        unset($_SESSION['flash']);

        $data = [
            'email'          => '',
            'password'       => '',
            'email_err'      => '',
            'password_err'   => '',
            'login_err'      => '',
            'logout_success' => isset($_GET['logout']) ? "Vous êtes déconnecté." : '',
            'flash'          => $flash
        ];

        // Form envoyé
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data['email']    = trim($_POST['email'] ?? '');
            $data['password'] = trim($_POST['password'] ?? '');

            if ($data['email'] === '')    $data['email_err']    = "Veuillez entrer votre email.";
            if ($data['password'] === '') $data['password_err'] = "Veuillez entrer votre mot de passe.";

            if ($data['email_err'] === '' && $data['password_err'] === '') {
                $userModel = new User();

                // Vérifie et crée la session si OK
                if ($userModel->checkUser($data['email'], $data['password'])) {
                    if (session_status() === PHP_SESSION_NONE) session_start();
                    session_regenerate_id(true);

                    // Récupère l’utilisateur pour connaître son id/nom/rôle
                    $user = $userModel->findByEmail($data['email']); // <-- voir étape 2

                    // Pose la session (clé standard)
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id']  = (int)$user['id'];
                    $_SESSION['username'] = $user['name'] ?? '';
                    $_SESSION['privilege'] = $user['role'] ?? 'user';

                    // Compat si ailleurs tu utilises encore id_user
                    $_SESSION['id_user'] = $_SESSION['user_id'];

                    // Redirige direct vers le profil
                    return View::redirect('profil');
                }


                $data['login_err'] = "Email ou mot de passe incorrect.";
            }
        }

        return View::render('auth/login', $data);
    }

    // Inscription simple
    public function register()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $data = [
            'name'                  => '',
            'email'                 => '',
            'password'              => '',
            'confirm_password'      => '',
            'name_err'              => '',
            'email_err'             => '',
            'password_err'          => '',
            'confirm_password_err'  => '',
            'exists_err'            => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data['name']             = trim($_POST['name'] ?? '');
            $data['email']            = trim($_POST['email'] ?? '');
            $data['password']         = trim($_POST['password'] ?? '');
            $data['confirm_password'] = trim($_POST['confirm_password'] ?? '');

            if ($data['name'] === '') {
                $data['name_err'] = "Veuillez entrer un nom.";
            }

            if ($data['email'] === '') {
                $data['email_err'] = "Veuillez entrer un email.";
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $data['email_err'] = "Email invalide.";
            }

            if ($data['password'] === '') {
                $data['password_err'] = "Veuillez entrer un mot de passe.";
            }

            if ($data['password'] !== $data['confirm_password']) {
                $data['confirm_password_err'] = "Les mots de passe ne correspondent pas.";
            }

            if ($data['name_err'] === '' && $data['email_err'] === '' && $data['password_err'] === '' && $data['confirm_password_err'] === '') {
                $userModel = new User();

                if ($userModel->exists($data['email'])) {
                    $data['exists_err'] = "Cet email est déjà pris.";
                } else {
                    // User::create() doit hasher le mot de passe en interne
                    $ok = $userModel->create([
                        'name'     => $data['name'],
                        'email'    => $data['email'],
                        'password' => $data['password']
                    ]);

                    if ($ok) {
                        $_SESSION['flash'] = "Inscription réussie, vous pouvez vous connecter.";
                        return View::redirect('login');
                    }

                    $data['exists_err'] = "Erreur lors de l'inscription.";
                }
            }
        }

        return View::render('auth/register', $data);
    }

    // Déconnexion
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION = [];
        session_destroy();
        // Bonus: ?logout=1 pour afficher un message sur la page de login
        return View::redirect('login?logout=1');
    }
}
