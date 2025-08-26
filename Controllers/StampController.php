<?php

namespace App\Controllers;

use App\Providers\View;
use App\Models\Stamp;
use App\Models\Image;
use App\Models\Country;
use App\Models\Category;
use App\Models\StampCondition;
use App\Models\Color;
use App\Models\Auction;
use App\Models\Database;

class StampController
{
    /* ==================== Helpers ==================== */

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

    /** Dossier absolu des uploads du mois courant, ex: /.../public/uploads/2025/08 */
    private function currentUploadsDir(): string
    {
        $subdir = date('Y/m');
        $base   = __DIR__ . '/../../public/uploads/' . $subdir;
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        return $base;
    }

    /** Chemin relatif à stocker en BD (uploads/YYYY/MM/filename.ext) */
    private function makeRelPath(string $filename): string
    {
        return 'uploads/' . date('Y/m') . '/' . $filename;
    }

    /** Sauve 1 fichier uploadé tel quel, renvoie le chemin relatif BD ou null (avec logs en cas d’échec) */
    private function moveOneUpload(string $tmp, string $originalName): ?string
    {
        $dir      = $this->currentUploadsDir();
        $ext      = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest     = $dir . '/' . $filename;

        if (!is_uploaded_file($tmp)) {
            error_log("UPLOAD ERROR: not an uploaded file: $tmp");
            return null;
        }

        if (move_uploaded_file($tmp, $dest)) {
            if (!file_exists($dest)) {
                error_log("UPLOAD MOVED BUT NOT FOUND: $dest");
            }
            return $this->makeRelPath($filename); // ex: uploads/2025/08/ab12cd34.jpg
        }

        error_log("MOVE FAILED: $tmp -> $dest");
        return null;
    }

    /** Sauve plusieurs fichiers uploadés; crée les lignes Image (Main pour primaryIndex) */
    private function saveManyUploads(array $files, int $stampId, int $primaryIndex = 0): void
    {
        $maxSize = 5 * 1024 * 1024; // 5 Mo

        // Construire la liste des index réellement valides
        $valid = [];
        $count = isset($files['name']) ? count($files['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if (!empty($files['name'][$i]) && $err === UPLOAD_ERR_OK) {
                $valid[] = $i;
            } else {
                if ($err !== UPLOAD_ERR_NO_FILE) {
                    error_log("UPLOAD ERROR index=$i code=$err");
                }
            }
        }
        if (empty($valid)) return;

        // Si l'index choisi n'est pas dans les valides, on prend le premier valide
        $effectivePrimary = in_array($primaryIndex, $valid, true) ? $primaryIndex : $valid[0];

        // Traiter tous les fichiers valides
        foreach ($valid as $i) {
            $size = (int)($files['size'][$i] ?? 0);
            if ($size <= 0 || $size > $maxSize) {
                error_log("UPLOAD SKIP (size) index=$i size=$size");
                continue;
            }

            $rel = $this->moveOneUpload($files['tmp_name'][$i], $files['name'][$i]);
            if ($rel) {
                $role = ($i === $effectivePrimary) ? 'Main' : 'Additional'; // ← comparaison avec $i (index original)
                (new \App\Models\Image())->create($stampId, $rel, $role);
            } else {
                error_log("UPLOAD MOVE FAILED index=$i name=" . ($files['name'][$i] ?? ''));
            }
        }
    }

    /* ==================== Vues ==================== */

    public function create()
    {
        $this->requireLogin();

        $data = [
            'countries'  => Country::getAll(),
            'categories' => Category::getAll(),
            'conditions' => StampCondition::getAll(),
            'colors'     => Color::getAll(),
            'error'      => $_SESSION['error']  ?? '',
            'success'    => $_SESSION['success'] ?? '',
        ];
        unset($_SESSION['error'], $_SESSION['success']);

        return View::render('stamps/create', $data);
    }

    /* ==================== (store) ==================== */

    public function store()
    {
        $this->requireLogin();

        // Harmonisation avec le champ du formulaire: creationYear → creationDate (si ton modèle attend une date)
        $creationYear = isset($_POST['creationYear']) ? (int)$_POST['creationYear'] : (int)date('Y');
        $creationYear = max(1800, min($creationYear, 2030));
        $creationDate = sprintf('%04d-01-01', $creationYear);

        // 1) Créer le timbre
        $mStamp = new Stamp();
        $stampId = $mStamp->create([
            'name'         => trim($_POST['name'] ?? ''),
            'creationDate' => $creationDate,
            'User_id'      => $this->userId(),
            'condition_id' => (int)($_POST['condition_id'] ?? 1),
            'country_id'   => (int)($_POST['country_id'] ?? 1),
            'category_id'  => (int)($_POST['category_id'] ?? 1),
            'color_id'     => !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null,
        ]);

        // 2) Images multiples
        if (!empty($_FILES['images']['name']) && count(array_filter($_FILES['images']['name'])) > 0) {

            // index reçu depuis le radio (peut être absent si l'utilisateur a "décoché")
            $primaryIndex = (isset($_POST['primary_index']) && $_POST['primary_index'] !== '')
                ? (int)$_POST['primary_index']
                : 0; // fallback sûr

            $this->saveManyUploads($_FILES['images'], $stampId, $primaryIndex);
        }

        $_SESSION['success'] = 'Timbre créé avec ses images.';
        return View::redirect('stamps');
    }


    /* ==================== MES TIMBRES ==================== */

    public function index()
    {
        $this->requireLogin();
        $m = new Stamp();
        $data = [
            'stamps'  => $m->getAllByUserId($this->userId()),
            'error'   => $_SESSION['error']  ?? '',
            'success' => $_SESSION['success'] ?? '',
        ];
        unset($_SESSION['error'], $_SESSION['success']);

        return View::render('stamps/index', $data);
    }

    /* ==================== update==================== */

    public function edit()
    {
        $this->requireLogin();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = "ID manquant.";
            return View::redirect('stamps');
        }

        $m = new Stamp();
        $stamp = $m->findByIdForOwner($id, $this->userId());
        if (!$stamp) {
            $_SESSION['error'] = "Non autorisé ou timbre inexistant.";
            return View::redirect('stamps');
        }

        // ➜ Charger les images du timbre (Main d’abord grâce à ORDER dans le modèle)
        $images = (new Image())->getByStampId($id);
        // (facultatif) petit log pour diagnostiquer si besoin
        // error_log('[edit] stamp '.$id.' images='.count($images));

        $data = [
            'stamp'       => $stamp,
            'images'      => $images,             // ← IMPORTANT : on passe la liste à la vue
            'countries'   => Country::getAll(),
            'categories'  => Category::getAll(),
            'conditions'  => StampCondition::getAll(),
            'colors'      => Color::getAll(),
            'error'       => $_SESSION['error']  ?? '',
            'success'     => $_SESSION['success'] ?? '',
        ];
        unset($_SESSION['error'], $_SESSION['success']);

        return View::render('stamps/edit', $data);
    }


    public function update()
    {
        $this->requireLogin();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = "ID manquant.";
            return View::redirect('stamps');
        }

        // Vérifier l'autorisation une fois pour toutes
        $stampRow = (new Stamp())->findByIdForOwner($id, $this->userId());
        if (!$stampRow) {
            $_SESSION['error'] = "Non autorisé ou timbre inexistant.";
            return View::redirect('stamps');
        }

        $imgModel = new Image();
        $changed  = false;

        // 1) MAJ des champs texte (OK même si aucune ligne n'est modifiée)
        $ok = (new Stamp())->updateForOwner($id, $this->userId(), [
            'name'         => trim($_POST['name'] ?? ''),
            'creationDate' => $_POST['creationDate'] ?? date('Y-m-d'),
            'country_id'   => (int)($_POST['country_id'] ?? 1),
            'category_id'  => (int)($_POST['category_id'] ?? 1),
            'condition_id' => (int)($_POST['condition_id'] ?? 1),
            'color_id'     => !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null,
        ]);
        if ($ok) {
            $changed = true;
        } // si pas modifié, on continue quand même

        // 2) Suppression d’images cochées
        $before = $imgModel->getByStampId($id);
        $byId   = [];
        foreach ($before as $im) {
            $byId[(int)$im['id']] = $im;
        }

        if (!empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
            foreach ($_POST['delete_ids'] as $imgIdRaw) {
                $imgId = (int)$imgIdRaw;
                if (isset($byId[$imgId])) {
                    $full = dirname(__DIR__, 2) . '/public/assets/images/' . ltrim($byId[$imgId]['image_url'], '/');
                    if (is_file($full)) @unlink($full);
                    if ($imgModel->deleteByIdForOwner($imgId, $this->userId())) {
                        $changed = true;
                    }
                }
            }
        }

        // 3) Choix de la principale
        $mainChoice = $_POST['main_choice'] ?? 'keep'; // keep | existing | new

        // 3.a) Nouvelles images (taille seulement) + éventuelle nouvelle "Main"
        if (!empty($_FILES['images']['name'][0])) {
            $files   = $_FILES['images'];
            $count   = count($files['name']);
            $maxSize = 5 * 1024 * 1024; // 5 Mo

            $primaryIndex = -1;
            if ($mainChoice === 'new' && isset($_POST['primary_index']) && $_POST['primary_index'] !== '') {
                $primaryIndex = (int)$_POST['primary_index'];
            }

            $newMainId = null;
            $validPos  = 0;

            for ($i = 0; $i < $count; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $size = (int)($files['size'][$i] ?? 0);
                if ($size <= 0 || $size > $maxSize) continue;

                $rel = $this->moveOneUpload($files['tmp_name'][$i], $files['name'][$i]); // retourne "uploads/AAAA/MM/xxx.jpg"
                if (!$rel) continue;

                $insertedId = $imgModel->create($id, $rel, 'Additional');
                $changed = true;

                if ($mainChoice === 'new' && $primaryIndex === $validPos) {
                    $newMainId = $insertedId;
                }
                $validPos++;
            }

            if ($mainChoice === 'new' && $newMainId) {
                if ($imgModel->setMain($id, $newMainId, $this->userId())) {
                    $changed = true;
                }
            }
        }

        // 3.b) Principale EXISTANTE
        if ($mainChoice === 'existing' && !empty($_POST['primary_existing_id']) && ctype_digit((string)$_POST['primary_existing_id'])) {
            if ($imgModel->setMain($id, (int)$_POST['primary_existing_id'], $this->userId())) {
                $changed = true;
            }
        }

        // Message de fin
        if ($changed) {
            $_SESSION['success'] = 'Timbre modifié.';
        } else {
            $_SESSION['success'] = 'Aucune modification.';
        }
        return View::redirect('stamps');
    }

    /* ==================== supprime ==================== */

    public function destroy()
    {
        $this->requireLogin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = "ID manquant.";
            return View::redirect('stamps');
        }

        $mStamp = new Stamp();
        $mImg   = new Image();

        $stamp = $mStamp->findByIdForOwner($id, $this->userId());
        if (!$stamp) {
            $_SESSION['error'] = "Non autorisé ou timbre inexistant.";
            return View::redirect('stamps');
        }

        // supprimer fichiers physiques après delete BD
        $paths = array_column($mImg->getByStampId($id), 'image_url');

        $ok = $mStamp->deleteForOwner($id, $this->userId());
        if ($ok) {
            foreach ($paths as $rel) {
                $file = __DIR__ . '/../../public/' . $rel;
                if (is_file($file)) @unlink($file);
            }
            $_SESSION['success'] = "Timbre supprimé.";
        } else {
            $_SESSION['error'] = "Erreur de suppression.";
        }
        return View::redirect('stamps');
    }

    /* ==================== PUBLIC ==================== */


    public function indexPublic()
    {
        $db = \App\Models\Database::getConnection();

        // Liste des timbres avec: pays, image principale, prix actuel, nb mises
        $sql = "
      SELECT
        s.id,
        s.name,
        s.creationDate,
        co.name  AS country_name,

        -- image principale si marquée 'Main', sinon n'importe laquelle
        COALESCE(img_main.image_url, img_any.image_url) AS main_image,

        -- stats d'enchère
        COALESCE(auc.curr_price, 0.00)  AS current_price,
        COALESCE(auc.bids_count, 0)     AS bids_count
      FROM Stamp s
      LEFT JOIN Country co ON co.id = s.country_id

      -- image principale
      LEFT JOIN Image img_main
             ON img_main.Stamp_id = s.id AND img_main.image_type = 'Main'
      -- image fallback si pas de 'Main'
      LEFT JOIN (
          SELECT i2.Stamp_id, MIN(i2.image_url) AS image_url
          FROM Image i2
          GROUP BY i2.Stamp_id
      ) AS img_any ON img_any.Stamp_id = s.id

      -- stats enchères : prix courant = MAX(b.amount), nb mises = COUNT(b.id)
      LEFT JOIN (
          SELECT a.stamp_id,
                 MAX(b.amount) AS curr_price,
                 COUNT(b.id)   AS bids_count
          FROM auction a
          LEFT JOIN bid b ON b.auction_id = a.id
          GROUP BY a.stamp_id
      ) AS auc ON auc.stamp_id = s.id

      ORDER BY s.id DESC
    ";

        $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return \App\Providers\View::render('pages/catalogueProduit', [
            'stamps' => $rows,
            'base'   => defined('BASE')  ? BASE  : '',
            'asset'  => defined('ASSET') ? ASSET : '',
        ]);
    }


    /* ==================== CatologueProduit ==================== */
    public function showPublic($id = null)
    {
        // 1) Résoudre l'ID depuis l'URL si besoin
        if ($id === null && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
        }
        $id = (int) $id;

        $db = \App\Models\Database::getConnection();

        // 2) Timbre + méta (noms pour la vue)
        $stmt = $db->prepare("
        SELECT s.*,
               c.name   AS country_name,
               cat.name AS category_name,
               sc.name  AS condition_name,
               col.name AS color_name
        FROM Stamp s
        LEFT JOIN Country         c   ON c.id   = s.country_id
        LEFT JOIN Category        cat ON cat.id = s.category_id
        LEFT JOIN Stamp_Condition sc  ON sc.id  = s.condition_id
        LEFT JOIN Color           col ON col.id = s.color_id
        WHERE s.id = ?
    ");
        $stmt->execute([$id]);
        $stamp = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$stamp) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['error'] = "Timbre introuvable.";
            return \App\Providers\View::redirect('catalogue');
        }

        // 3) Images du timbre (Main d'abord)
        $imgs = $db->prepare("
        SELECT *
        FROM Image
        WHERE Stamp_id = ?
        ORDER BY image_type = 'Main' DESC, id ASC
    ");
        $imgs->execute([$id]);
        $images = $imgs->fetchAll(\PDO::FETCH_ASSOC);

        // 4) Enchère "ouverte" (fenêtre de dates) la plus récente pour ce timbre
        $stAuc = $db->prepare("
        SELECT *
        FROM auction
        WHERE stamp_id = ?
          AND (start_at IS NULL OR start_at <= NOW())
          AND (end_at   IS NULL OR end_at   >= NOW())
        ORDER BY id DESC
        LIMIT 1
    ");
        $stAuc->execute([$id]);
        $a = $stAuc->fetch(\PDO::FETCH_ASSOC) ?: null;

        // 5) Prix courant + historique des mises (JOIN sur bidder_id)
        $stats = ['current_price' => 0.0, 'bids_count' => 0];
        $bids  = [];

        if ($a) {
            // top bid + count
            $stTop = $db->prepare("SELECT COALESCE(MAX(amount),0) AS top_bid,
                                      COUNT(*) AS bids_count
                               FROM bid
                               WHERE auction_id = ?");
            $stTop->execute([(int)$a['id']]);
            $agg = $stTop->fetch(\PDO::FETCH_ASSOC) ?: ['top_bid' => 0, 'bids_count' => 0];

            $current = max((float)$agg['top_bid'], (float)$a['starting_price']);
            $stats   = ['current_price' => $current, 'bids_count' => (int)$agg['bids_count']];

            // historique (username via user.id = bid.bidder_id)
            $stHist = $db->prepare("
            SELECT b.amount, b.created_at, u.name AS bidder_name
            FROM bid b
            LEFT JOIN user u ON u.id = b.bidder_id
            WHERE b.auction_id = ?
            ORDER BY b.created_at DESC
            LIMIT 50
        ");
            $stHist->execute([(int)$a['id']]);
            $bids = $stHist->fetchAll(\PDO::FETCH_ASSOC);
        }

        // 6) Flash + info connexion
        if (session_status() === PHP_SESSION_NONE) session_start();
        $error    = $_SESSION['error']   ?? '';
        $success  = $_SESSION['success'] ?? '';
        $loggedin = !empty($_SESSION['user_id']);
        unset($_SESSION['error'], $_SESSION['success']);

        // 7) Rendu
        return \App\Providers\View::render('pages/fichierProduit', [
            'stamp'    => $stamp,
            'images'   => $images,
            'a'        => $a,                 // enchère (null => pas de formulaire de mise)
            'stats'    => $stats,             // current_price, bids_count
            'bids'     => $bids,              // historique
            'error'    => $error,
            'success'  => $success,
            'loggedin' => $loggedin,
            'base'     => defined('BASE')  ? BASE  : '',
            'asset'    => defined('ASSET') ? ASSET : '',
        ]);
    }
}
