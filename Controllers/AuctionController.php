<?php

namespace App\Controllers;

use App\Providers\View;                 // adapte si ton View est ailleurs
use App\Models\Database;           // PDO singleton (comme chez toi)
use App\Models\Image;

class AuctionController
{
    // GET /auctions/{id}
    public function show(int $id)
    {
        $db = Database::getConnection();

        // 1) Enchère
        $st = $db->prepare("SELECT * FROM auction WHERE id = ?");
        $st->execute([$id]);
        $a = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$a) {
            $_SESSION['error'] = "Enchère introuvable.";
            return View::redirect('stamps');
        }

        // 2) Prix courant (sans vue SQL)
        $st = $db->prepare("SELECT COALESCE(MAX(amount),0) AS top_bid, COUNT(*) AS bids_count
                            FROM bid WHERE auction_id = ?");
        $st->execute([$id]);
        $agg = $st->fetch(\PDO::FETCH_ASSOC) ?: ['top_bid' => 0, 'bids_count' => 0];
        $current = max((float)$agg['top_bid'], (float)$a['starting_price']);

        // 3) Historique
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

        // 4) Timbre + images (affichage)
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
            'asset'  => BASE,
        ]);
    }

    // POST /auctions/{id}/bid
    public function place()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            $_SESSION['error'] = "Connexion requise.";
            return \App\Providers\View::redirect('login');
        }

        $auctionId = (int)($_POST['auction_id'] ?? 0);
        $amount    = (float)($_POST['amount'] ?? 0);
        if ($auctionId <= 0 || $amount <= 0) {
            $_SESSION['error'] = "Requête invalide.";
            return \App\Providers\View::redirect('catalogue');
        }

        $db = \App\Models\Database::getConnection();

        // 1) lire l'enchère
        $st = $db->prepare("
        SELECT id, stamp_id, seller_id, starting_price, min_increment, start_at, end_at, status
        FROM auction
        WHERE id = ?
    ");
        $st->execute([$auctionId]);
        $a = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$a) {
            $_SESSION['error'] = "Enchère introuvable.";
            return \App\Providers\View::redirect('catalogue');
        }

        // (optionnel) bloquer la mise du vendeur sur sa propre enchère
        if (!empty($a['seller_id']) && (int)$a['seller_id'] === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = "Vous ne pouvez pas miser sur votre propre enchère.";
            return \App\Providers\View::redirect('fiche-produit?id=' . (int)$a['stamp_id']);
        }

        // 2) vérifier fenêtre temporelle (adapte si tu gères un code de status)
        if ((!empty($a['start_at']) && strtotime($a['start_at']) > time()) ||
            (!empty($a['end_at'])   && strtotime($a['end_at'])   < time())
        ) {
            $_SESSION['error'] = "Enchère fermée.";
            return \App\Providers\View::redirect('fiche-produit?id=' . (int)$a['stamp_id']);
        }

        // 3) top bid
        $st = $db->prepare("SELECT COALESCE(MAX(amount),0) FROM bid WHERE auction_id = ?");
        $st->execute([$auctionId]);
        $top = (float)($st->fetchColumn() ?: 0);

        // 4) minimum requis
        $minInc    = (float)($a['min_increment'] ?? 0.50);
        $minAccept = max((float)$a['starting_price'], $top) + $minInc;
        if ($amount < $minAccept) {
            $_SESSION['error'] = "Offre trop basse. Minimum requis : " . number_format($minAccept, 2, '.', ' ');
            return \App\Providers\View::redirect('fiche-produit?id=' . (int)$a['stamp_id']);
        }

        // 5) insérer la mise (NB: created_at a un défaut CURRENT_TIMESTAMP)
        $ins = $db->prepare("
        INSERT INTO bid (auction_id, bidder_id, amount)
        VALUES (?, ?, ?)
    ");
        $ins->execute([$auctionId, (int)$_SESSION['user_id'], $amount]);

        $_SESSION['success'] = "Mise placée.";
        return \App\Providers\View::redirect('fiche-produit?id=' . (int)$a['stamp_id']);
    }




    public function createForStamp()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            $_SESSION['error'] = "Connexion requise.";
            return \App\Providers\View::redirect('login');
        }

        $stampId  = (int)($_POST['stamp_id'] ?? 0);
        $starting = (float)($_POST['starting_price'] ?? 10.00);
        $inc      = (float)($_POST['min_increment'] ?? 0.50);
        $days     = (int)($_POST['duration_days'] ?? 14);

        if ($stampId <= 0 || $starting < 0 || $inc <= 0 || $days <= 0) {
            $_SESSION['error'] = "Données invalides.";
            return \App\Providers\View::redirect('fiche-produit?id=' . $stampId);
        }

        $db = \App\Models\Database::getConnection();

        // (sécurité) vérifier que le timbre existe et (optionnel) appartient à l’utilisateur
        $st = $db->prepare("SELECT id, User_id FROM Stamp WHERE id = ?");
        $st->execute([$stampId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['error'] = "Timbre introuvable.";
            return \App\Providers\View::redirect('catalogue');
        }
        // décommente si tu veux restreindre au propriétaire :
        // if ((int)$row['User_id'] !== (int)$_SESSION['user_id']) {
        //     $_SESSION['error'] = "Non autorisé.";
        //     return \App\Providers\View::redirect('fiche-produit?id='.$stampId);
        // }

        // éviter le doublon d’enchère
        $chk = $db->prepare("SELECT id FROM auction WHERE stamp_id = ?");
        $chk->execute([$stampId]);
        if ($chk->fetchColumn()) {
            $_SESSION['error'] = "Ce timbre est déjà en enchère.";
            return \App\Providers\View::redirect('fiche-produit?id=' . $stampId);
        }

        // créer l’enchère : ta table = start_at / end_at (pas current_price)
        $ins = $db->prepare("
      INSERT INTO auction
        (stamp_id, seller_id, starting_price, min_increment, reserve_price, status, start_at, end_at, created_at, updated_at)
      VALUES
        (?, ?, ?, ?, NULL, 'open', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), NOW(), NOW())
    ");
        $ins->execute([
            $stampId,
            (int)$_SESSION['user_id'],
            $starting,
            $inc,
            $days
        ]);

        $_SESSION['success'] = "Timbre mis aux enchères.";
        return \App\Providers\View::redirect('fiche-produit?id=' . $stampId);
    }
}
