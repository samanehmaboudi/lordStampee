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
        if (!$a) { $_SESSION['error'] = "Enchère introuvable."; return View::redirect('stamps'); }

        // 2) Prix courant (sans vue SQL)
        $st = $db->prepare("SELECT COALESCE(MAX(amount),0) AS top_bid, COUNT(*) AS bids_count
                            FROM bid WHERE auction_id = ?");
        $st->execute([$id]);
        $agg = $st->fetch(\PDO::FETCH_ASSOC) ?: ['top_bid'=>0,'bids_count'=>0];
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
    public function place(int $id)
    {
    

        $db        = Database::getConnection();
        $auctionId = (int)($_POST['auction_id'] ?? 0);
        $amount    = (float)($_POST['amount'] ?? 0);
        $userId    = (int)($_SESSION['user']['id'] ?? 0);

        if ($auctionId !== $id || $userId <= 0) {
            $_SESSION['error'] = "Requête invalide.";
            return View::redirect('auctions/'.$id);
        }

        try {
            
            $call = $db->prepare("CALL sp_place_bid(?, ?, ?)");
            $call->execute([$id, $userId, $amount]);

            $_SESSION['success'] = "Mise placée.";
        } catch (\PDOException $e) {
            
            $_SESSION['error'] = $e->getMessage();
        } catch (\Throwable $e) {
            $_SESSION['error'] = "Erreur lors de la mise.";
        }

        return View::redirect('auctions/'.$id);
    }
}
