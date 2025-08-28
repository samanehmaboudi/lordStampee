<?php
namespace App\Models;
use PDO;

class Bid {
  private PDO $db;
  public function __construct(){ $this->db = Database::getConnection(); }

  public function create(int $auctionId, int $userId, float $amount): int {
    $st = $this->db->prepare("INSERT INTO bid(auction_id,bidder_id,amount) VALUES(:a,:u,:m)");
    $st->execute([':a'=>$auctionId, ':u'=>$userId, ':m'=>$amount]);
    return (int)$this->db->lastInsertId();
  }

  public function topForAuction(int $auctionId): float {
    $st = $this->db->prepare("SELECT COALESCE(MAX(amount),0) FROM bid WHERE auction_id=:a");
    $st->execute([':a'=>$auctionId]);
    return (float)$st->fetchColumn();
  }

  public function listByStampId(int $stampId, int $limit=10, int $offset=0): array {
    $st = $this->db->prepare("
      SELECT b.amount, b.created_at, u.name AS bidder_name
      FROM bid b
      JOIN auction a ON a.id = b.auction_id
      JOIN user u ON u.id = b.bidder_id
      WHERE a.stamp_id = :sid
      ORDER BY b.created_at DESC
      LIMIT :lim OFFSET :off
    ");
    $st->bindValue(':sid',$stampId,PDO::PARAM_INT);
    $st->bindValue(':lim',$limit,PDO::PARAM_INT);
    $st->bindValue(':off',$offset,PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
