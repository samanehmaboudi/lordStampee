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

    /** Dossier absolu des uploads du mois courant, ex: /.../public/assets/images/uploads/2025/08 */
    private function currentUploadsDir(): string
    {
        $subdir = date('Y/m');
        $base   = __DIR__ . '/../../public/assets/images/uploads/' . $subdir;
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        return $base;
    }

    /** Renvoie le chemin relatif à stocker en BD (uploads/YYYY/MM/filename.ext) */
    private function makeRelPath(string $filename): string
    {
        return 'uploads/' . date('Y/m') . '/' . $filename;
    }

    /** Sauve 1 fichier uploadé tel quel, renvoie le chemin relatif BD ou null */
    private function moveOneUpload(string $tmp, string $originalName): ?string
    {
        $dir      = $this->currentUploadsDir();
        $ext      = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
            $ext = 'jpg';
        }
        
        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest     = $dir . '/' . $filename;

        if (move_uploaded_file($tmp, $dest)) {
            return $this->makeRelPath($filename); // ex: uploads/2025/08/ab12cd34.jpg
        }
        return null;
    }

    /** Sauve plusieurs fichiers uploadés; crée les lignes Image (Main pour i=0) */
    private function saveManyUploads(array $files, int $stampId, int $primaryIndex = 0): void
    {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $rel = $this->moveOneUpload($files['tmp_name'][$i], $files['name'][$i]);
            if ($rel) {
                $role = ($i === $primaryIndex) ? 'Main' : 'Additional';
                (new Image())->create($stampId, $rel, $role);
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

        // 1) Créer le timbre
        $mStamp = new Stamp();
        $stampId = $mStamp->create([
            'name'         => trim($_POST['name'] ?? ''),
            'creationDate' => $_POST['creationDate'] ?? date('Y-m-d'),
            'User_id'      => $this->userId(),
            'condition_id' => (int)($_POST['condition_id'] ?? 1),
            'country_id'   => (int)($_POST['country_id'] ?? 1),
            'category_id'  => (int)($_POST['category_id'] ?? 1),
            'color_id'     => !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null,
        ]);

        // 2) Images (multiple)
        if (!empty($_FILES['images']['name'][0])) {
            $primaryIndex = isset($_POST['primary_index']) ? (int)$_POST['primary_index'] : 0;
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

        $data = [
            'stamp'       => $stamp,
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

        // 1) Mettre à jour les champs texte
        $mStamp = new Stamp();
        $ok = $mStamp->updateForOwner($id, $this->userId(), [
            'name'         => trim($_POST['name'] ?? ''),
            'creationDate' => $_POST['creationDate'] ?? date('Y-m-d'),
            'country_id'   => (int)($_POST['country_id'] ?? 1),
            'category_id'  => (int)($_POST['category_id'] ?? 1),
            'condition_id' => (int)($_POST['condition_id'] ?? 1),
            'color_id'     => !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null,
        ]);
        if (!$ok) {
            $_SESSION['error'] = 'Non autorisé ou aucune modification.';
            return View::redirect('stamps/edit?id=' . $id);
        }

        // 2) Changer l’image principale parmi celles EXISTANTES
        if (!empty($_POST['make_main_id'])) {
            $makeMainId = (int)$_POST['make_main_id'];
            (new Image())->setMain($id, $makeMainId, $this->userId());
        }

        // 3) Ajouter de NOUVELLES images 
        if (!empty($_FILES['images']['name'][0])) {
            $primaryIndex = isset($_POST['primary_index']) ? (int)$_POST['primary_index'] : -1; // -1 => ne pas toucher la Main
            $files = $_FILES['images'];
            $count = count($files['name']);

            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $rel = $this->moveOneUpload($files['tmp_name'][$i], $files['name'][$i]);
                if ($rel) {
                    $role = ($i === $primaryIndex) ? 'Main' : 'Additional';
                    (new Image())->create($id, $rel, $role);
                    // si on vient de désigner une nouvelle Main, setMain s’occupera de rétrograder les autres
                    if ($role === 'Main') {
                        // récupère l’id de l’insert si besoin dans ton modèle Image->create (sinon on laisse comme ça)
                        // (new Image())->setMain($id, $newImageId, $this->userId());
                    }
                }
            }
        }

        $_SESSION['success'] = 'Timbre modifié.';
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
                $file = __DIR__ . '/../../public/assets/images/' . $rel;
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
        if (session_status() === PHP_SESSION_NONE) session_start();
        $loggedin = !empty($_SESSION['user_id']);

        $db = Database::getConnection();
        $sql = "
            SELECT
                s.id, s.name, s.creationDate,
                c.name AS country,
                (SELECT i.image_url
                   FROM Image i
                  WHERE i.Stamp_id = s.id AND i.image_type = 'Main'
                  ORDER BY i.id ASC LIMIT 1) AS main_image,
                (SELECT a.current_price
                   FROM Auction a
                  WHERE a.Stamp_id = s.id
                  ORDER BY a.id DESC LIMIT 1) AS price
            FROM Stamp s
            LEFT JOIN Country c ON c.id = s.country_id
            ORDER BY s.id DESC";
        $stamps = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return View::render('pages/catalogueProduit', [
            'stamps'   => $stamps,
            'loggedin' => $loggedin,
            'asset'    => $GLOBALS['asset'] ?? '',
            'base'     => $GLOBALS['base']  ?? '',
        ]);
    }

   /* ====================catologueProduit ==================== */
    public function showPublic($id = null)
    {
        if ($id === null && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
        }
        $id = (int)$id;

        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT s.*,
                   c.name  AS country,
                   cat.name AS category,
                   sc.name  AS cond,
                   col.name AS color
            FROM Stamp s
            LEFT JOIN Country         c   ON c.id  = s.country_id
            LEFT JOIN Category        cat ON cat.id= s.category_id
            LEFT JOIN Stamp_Condition sc  ON sc.id = s.condition_id
            LEFT JOIN Color           col ON col.id= s.color_id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $stamp = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$stamp) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['error'] = "Timbre introuvable.";
            return View::redirect('catalogue');
        }

        $imgs = $db->prepare("
            SELECT * FROM Image
            WHERE Stamp_id = ?
            ORDER BY image_type = 'Main' DESC, id ASC
        ");
        $imgs->execute([$id]);
        $images = $imgs->fetchAll(\PDO::FETCH_ASSOC);

        return View::render('pages/fichierProduit', [
            'stamp'  => $stamp,
            'images' => $images,
            'asset'  => $GLOBALS['asset'] ?? '',
            'base'   => $GLOBALS['base']  ?? '',
        ]);
    }

    
}
