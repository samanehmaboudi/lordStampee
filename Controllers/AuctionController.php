<?php
namespace App\Controllers;

use App\Providers\View;
use App\Models\Database;
use App\Models\Image;

class AuctionController
{
    // GET /auctions/{id}  (tu peux appeler avec ?id=… si ton routeur ne gère pas {id})
    public function show(int $id)
    {
        $db = Database::getConnection();

        // Enchère
        $st = $db->prepare("SELECT * FROM auction WHERE id = ?");
        $st->execute([$id]);
        $a = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$a) {
            $_SESSION['error'] = "Enchère introuvable.";
            return View::redirect('stamps');
        }

        // Prix courant + nb mises
        $st = $db->prepare("SELECT COALESCE(MAX(amount),0) AS top_bid, COUNT(*) AS bids_count
                            FROM bid WHERE auction_id = ?");
        $st->execute([$id]);
        $agg = $st->fetch(\PDO::FETCH_ASSOC) ?: ['top_bid' => 0, 'bids_count' => 0];
        $current = max((float)$agg['top_bid'], (float)$a['starting_price']);

        // Historique
        $st = $db->prepare(
            "SELECT b.amount, b.created_at, u.name AS bidder_name
             FROM bid b
             JOIN user u ON u.id = b.bidder_id
             WHERE b.auction_id = ?
             ORDER BY b.created_at DESC
             LIMIT 50"
        );
        $st->execute([$id]);
        $bids = $st->fetchAll(\PDO::FETCH_ASSOC);

        // Timbre + images
        $stampSt = $db->prepare("SELECT * FROM stamp WHERE id = ?");
        $stampSt->execute([(int)$a['stamp_id']]);
        $stamp = $stampSt->fetch(\PDO::FETCH_ASSOC);
        $images = (new Image())->getByStampId((int)$a['stamp_id']);

        return View::render('pages/fichierProduit', [
            'a'      => $a,
            'stamp'  => $stamp,
            'images' => $images,
            'bids'   => $bids,
            'stats'  => ['current_price' => $current],
            'base'   => BASE,
            'asset'  => ASSET,   // ✅ important
        ]);
    }

    // POST /bids/place  (auction_id, amount)
    public function place()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            $_SESSION['error'] = "Connexion requise.";
            return View::redirect('login');
        }

        $auctionId = (int)($_POST['auction_id'] ?? 0);
        $amount    = (float)($_POST['amount'] ?? 0);
        if ($auctionId <= 0 || $amount <= 0) {
            $_SESSION['error'] = "Requête invalide.";
            return View::redirect('catalogue');
        }

        $db = Database::getConnection();

        // Lire l’enchère
        $st = $db->prepare("
            SELECT id, stamp_id, seller_id, starting_price, min_increment, start_at, end_at, status
            FROM auction WHERE id = ?
        ");
        $st->execute([$auctionId]);
        $a = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$a) {
            $_SESSION['error'] = "Enchère introuvable.";
            return View::redirect('catalogue');
        }

        // Interdire la mise du vendeur
        if (!empty($a['seller_id']) && (int)$a['seller_id'] === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = "Vous ne pouvez pas miser sur votre propre enchère.";
            return View::redirect('fiche-produit?id='.(int)$a['stamp_id']);
        }

        // Fenêtre active
        if ((!empty($a['start_at']) && strtotime($a['start_at']) > time()) ||
            (!empty($a['end_at'])   && strtotime($a['end_at'])   < time()) ||
            ($a['status'] ?? '') === 'cancelled'
        ) {
            $_SESSION['error'] = "Enchère non active.";
            return View::redirect('fiche-produit?id='.(int)$a['stamp_id']);
        }

        // Top bid + min requis (petit verrou via transaction)
        try {
            $db->beginTransaction();

            $stTop = $db->prepare("SELECT COALESCE(MAX(amount),0) FROM bid WHERE auction_id = ? FOR UPDATE");
            $stTop->execute([$auctionId]);
            $top = (float)$stTop->fetchColumn();

            $minInc = (float)($a['min_increment'] ?? 0.50);
            $minAsk = max((float)$a['starting_price'], $top) + $minInc;

            if ($amount < $minAsk) {
                $db->rollBack();
                $_SESSION['error'] = "Offre trop basse. Minimum : ".number_format($minAsk, 2, '.', ' ');
                return View::redirect('fiche-produit?id='.(int)$a['stamp_id']);
            }

            $ins = $db->prepare("INSERT INTO bid (auction_id, bidder_id, amount) VALUES (?, ?, ?)");
            $ins->execute([$auctionId, (int)$_SESSION['user_id'], $amount]);

            $db->commit();
            $_SESSION['success'] = "Mise placée.";
        } catch (\Throwable $e) {
            $db->rollBack();
            $_SESSION['error'] = "Erreur lors de l’enregistrement de votre mise.";
        }

        return View::redirect('fiche-produit?id='.(int)$a['stamp_id']);
    }

    // (facultatif) POST pour créer une enchère sur un timbre
    public function createForStamp()
    {
        // … ta version actuelle est OK …
    }
}
