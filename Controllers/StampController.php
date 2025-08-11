<?php

namespace App\Controllers;

use App\Providers\View;
use App\Models\Database;

class StampController
{
    private $pdo;

    public function __construct()
    {
       
        $this->pdo = Database::getConnection();
    }

    /**
     * Affiche la page de catalogue des produits (timbres)
     */
    public function catalogue()
    {
        session_start();

        
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            $_SESSION['flash'] = "Veuillez vous connecter pour accéder au catalogue.";
            header("Location: /login");
            exit;
        }

       
        return View::render('pages/catalogueProduit', [
            'session' => $_SESSION,
            'asset' => ASSET
        ]);
    }

    
    public function ficheProduit()
    {
        session_start();

        // Vérification de l'utilisateur connecté
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            $_SESSION['flash'] = "Connexion requise pour voir ce timbre.";
            header("Location: /login");
            exit;
        }

        // Récupérer l’ID depuis l’URL
        $productId = $_GET['id'] ?? null;

        if (!$productId) {
            echo "Aucun produit sélectionné.";
            return;
        }

        // Requête pour récupérer les données du timbre
        $stmt = $this->pdo->prepare("SELECT * FROM stamp WHERE id = :id");
        $stmt->execute(['id' => $productId]);
        $produit = $stmt->fetch();

        if (!$produit) {
            echo "Produit non trouvé.";
            return;
        }

        // Rendu de la fiche produit
        return View::render('pages/fiche-produit', [
            'produit' => $produit,
            'asset' => ASSET,
            'session' => $_SESSION
        ]);
    }
}
