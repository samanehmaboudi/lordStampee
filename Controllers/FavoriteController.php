<?php

namespace App\Controllers;

use App\Providers\View;
use App\Models\Database;
use App\Models\Favorite;

class FavoriteController
{
    /* ===== Helpers (mêmes patterns que StampController) ===== */

    private function requireLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            $_SESSION['error'] = "Connexion requise.";
            View::redirect('login');
            exit;
        }
    }

    private function userId(): int
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return (int)($_SESSION['user_id'] ?? 0);
    }

    private function redirectBack(): void
    {
        $back = $_SERVER['HTTP_REFERER'] ?? 'catalogue';
        // On ne passe pas par View::redirect pour un URL complet
        header('Location: '.$back);
        exit;
    }

    /* ===== Actions ===== */

    /**
     * POST /favoris/toggle
     * Champs attendus: stamp_id
     * Répond JSON si requête AJAX, sinon redirige vers la page précédente.
     */
    public function toggle(): void
    {
        $this->requireLogin();
        $userId  = $this->userId();
        $stampId = (int)($_POST['stamp_id'] ?? 0);

        if ($stampId <= 0) {
            $_SESSION['error'] = 'Timbre invalide.';
            $this->redirectBack();
        }

        $pdo = Database::getConnection();
        $fav = new Favorite($pdo);

        // doit retourner true si "ajouté", false si "retiré"
        $nowOn = $fav->toggle($userId, $stampId);

        // Réponse AJAX ?
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok'       => true,
                'favorite' => (bool)$nowOn,
                'message'  => $nowOn ? 'Ajouté aux favoris.' : 'Retiré des favoris.',
            ]);
            return;
        }

        $_SESSION['success'] = $nowOn ? 'Ajouté aux favoris.' : 'Retiré des favoris.';
        $this->redirectBack();
    }

    /**
     * GET /mes-favoris
     */
    public function myFavorites(): void
    {
        $this->requireLogin();
        $userId = $this->userId();

        $pdo    = Database::getConnection();
        $fav    = new Favorite($pdo);
        $stamps = $fav->listByUser($userId);

        // Adapte le chemin de vue à ton arborescence (pages/favorites ou favorites/index)
        echo View::render('pages/favorites', [
            'title'  => 'Mes favoris',
            'stamps' => $stamps,
            'error'   => $_SESSION['error']  ?? '',
            'success' => $_SESSION['success'] ?? '',
        ]);
        unset($_SESSION['error'], $_SESSION['success']);
    }
}
