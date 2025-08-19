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
use App\Models\Database; // pour la transaction éventuelle
use PDO;

class StampController
{
    /* ----------------- Helpers session/auth ----------------- */
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

    /* ----------------- AJOUT ----------------- */
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




    public function store()
    {
        $this->requireLogin();

        /* ====== PARAMÈTRES IMAGE (ajuste selon tes besoins) ====== */
        $MAX_FILES     = 10;                 // nombre max d'images à la fois
        $MIN_BYTES     = 4 * 1024;           // 4 Ko
        $MAX_BYTES     = 6 * 1024 * 1024;    // 6 Mo
        $MIN_W         = 300;                // px
        $MIN_H         = 300;                // px
        $MAX_W         = 6000;               // px
        $MAX_H         = 6000;               // px
        $MIN_RATIO     = 0.25;               // w/h
        $MAX_RATIO     = 4.0;                // w/h
        $TARGET_MAX    = 1600;               // plus grand côté après resize
        $JPEG_QUALITY  = 85;
        $WEBP_QUALITY  = 82;
        $PNG_COMPR     = 6;

        // validations simples
        $required = ['name', 'creationDate', 'country_id', 'category_id', 'condition_id'];
        foreach ($required as $r) {
            if (empty($_POST[$r])) {
                $_SESSION['error'] = "Champ $r requis.";
                return View::redirect('stamps/create');
            }
        }
        if (empty($_FILES['images']['name'][0])) {
            $_SESSION['error'] = "Au moins une image est requise.";
            return View::redirect('stamps/create');
        }

        $f = $_FILES['images'];
        if (count($f['name']) > $MAX_FILES) {
            $_SESSION['error'] = "Trop d’images (max {$MAX_FILES}).";
            return View::redirect('stamps/create');
        }

        $mStamp = new Stamp();
        $mImage = new Image();
        $mAuc   = new Auction();

        // 1) créer le timbre (propriétaire = user connecté)
        $stampId = $mStamp->create([
            'name'         => trim($_POST['name']),
            'creationDate' => $_POST['creationDate'],
            'User_id'      => $this->userId(),
            'condition_id' => (int)$_POST['condition_id'],
            'country_id'   => (int)$_POST['country_id'],
            'category_id'  => (int)$_POST['category_id'],
            'color_id'     => ($_POST['color_id'] !== '' ? (int)$_POST['color_id'] : null),
        ]);

        // 2) enregistrer les images (Main + Additional)
        $primaryIndex = isset($_POST['primary_index']) ? (int)$_POST['primary_index'] : 0;
        $countFiles   = count($f['name']);
        if ($primaryIndex < 0 || $primaryIndex >= $countFiles) {
            $primaryIndex = 0;
        }

        $allowed = [
            'image/jpeg' => '.jpg',
            'image/jpg'  => '.jpg',   // normalisé ensuite
            'image/pjpeg' => '.jpg',   // IE/IIS
            'image/png'  => '.png',
            'image/webp' => '.webp',
        ];

        $subdir  = date('Y/m');
        $baseDir = __DIR__ . '/../../public/assets/images/uploads/' . $subdir;
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $createdFiles = []; // pour cleanup si erreur

        // (optionnel) transaction BD
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            for ($i = 0; $i < $countFiles; $i++) {
                if ($f['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException("Erreur upload image (index {$i}).");
                }

                // 2.1) taille de fichier
                $size = (int)$f['size'][$i];
                if ($size < $MIN_BYTES || $size > $MAX_BYTES) {
                    throw new \RuntimeException("Taille d'image invalide (min 4 Ko, max 6 Mo).");
                }

                // 2.2) MIME robuste
                $tmp  = $f['tmp_name'][$i];
                $mime = '';
                if (\function_exists('mime_content_type')) {
                    $mime = \mime_content_type($tmp);
                } elseif (\function_exists('finfo_open')) {
                    $fi = \finfo_open(FILEINFO_MIME_TYPE);
                    $mime = \finfo_file($fi, $tmp);
                    \finfo_close($fi);
                } else {
                    $info = \getimagesize($tmp);
                    $mime = $info['mime'] ?? '';
                }
                if ($mime === 'image/jpg' || $mime === 'image/pjpeg') {
                    $mime = 'image/jpeg';
                }
                if (!isset($allowed[$mime])) {
                    throw new \RuntimeException("Type d'image non autorisé ({$mime}).");
                }

                // 2.3) dimensions + ratio
                $g = \getimagesize($tmp);
                if (!$g) {
                    throw new \RuntimeException("Fichier image invalide.");
                }
                [$w, $h] = $g;
                if ($w > $MAX_W || $h > $MAX_H) {
                    throw new \RuntimeException("Dimensions trop grandes ({$w}×{$h}).");
                }
                $ratio = $h > 0 ? $w / $h : 0;
                if ($ratio < $MIN_RATIO || $ratio > $MAX_RATIO) {
                    throw new \RuntimeException("Ratio largeur/hauteur anormal.");
                }

                // 2.4) charger l'image source (GD)
                $src = null;
                if ($mime === 'image/jpeg') {
                    $src = \imagecreatefromjpeg($tmp);
                } elseif ($mime === 'image/png') {
                    $src = \imagecreatefrompng($tmp);
                } elseif ($mime === 'image/webp') {
                    if (\function_exists('imagecreatefromwebp')) {
                        $src = \imagecreatefromwebp($tmp);
                    } else {
                        throw new \RuntimeException("WebP non supporté par GD.");
                    }
                }
                if (!$src) {
                    throw new \RuntimeException("Impossible de lire l'image.");
                }

                // 2.5) corriger orientation EXIF (JPEG)
                if ($mime === 'image/jpeg' && \function_exists('exif_read_data')) {
                    try {
                        $exif = @\exif_read_data($tmp);
                        $ori  = isset($exif['Orientation']) ? (int)$exif['Orientation'] : 1;
                        if ($ori === 3) {
                            $src = \imagerotate($src, 180, 0);
                        } elseif ($ori === 6) {
                            $src = \imagerotate($src, -90, 0);
                        } elseif ($ori === 8) {
                            $src = \imagerotate($src, 90, 0);
                        }
                    } catch (\Throwable $e) {
                        // ignore orientation errors
                    }
                }

                // 2.6) redimensionnement si nécessaire
                $maxSide = max($w, $h);
                $dst = $src;
                $outW = $w;
                $outH = $h;
                if ($maxSide > $TARGET_MAX) {
                    $scale = $TARGET_MAX / $maxSide;
                    $outW  = (int)\round($w * $scale);
                    $outH  = (int)\round($h * $scale);
                    $dst   = \imagecreatetruecolor($outW, $outH);

                    // Transparence pour PNG/WebP
                    if ($mime !== 'image/jpeg') {
                        \imagealphablending($dst, false);
                        \imagesavealpha($dst, true);
                        $trans = \imagecolorallocatealpha($dst, 0, 0, 0, 127);
                        \imagefilledrectangle($dst, 0, 0, $outW, $outH, $trans);
                    }
                    \imagecopyresampled($dst, $src, 0, 0, 0, 0, $outW, $outH, $w, $h);
                }

                // 2.7) construire chemin et sauvegarder
                $ext  = $allowed[$mime];
                $rand = \bin2hex(\random_bytes(8));
                $rel  = 'uploads/' . $subdir . '/' . $rand . $ext;                 // chemin relatif
                $dstF = __DIR__ . '/../../public/assets/images/' . $rel;            // fichier cible

                $okSave = false;
                if ($mime === 'image/jpeg') {
                    $okSave = \imagejpeg($dst, $dstF, $JPEG_QUALITY);
                } elseif ($mime === 'image/png') {
                    $okSave = \imagepng($dst, $dstF, $PNG_COMPR);
                } elseif ($mime === 'image/webp' && \function_exists('imagewebp')) {
                    $okSave = \imagewebp($dst, $dstF, $WEBP_QUALITY);
                } else {
                    // dernier recours : move (ne devrait pas arriver)
                    $okSave = \move_uploaded_file($tmp, $dstF);
                }

                if ($dst !== $src) {
                    \imagedestroy($dst);
                }
                \imagedestroy($src);

                if (!$okSave) {
                    throw new \RuntimeException("Erreur lors de l’enregistrement de l’image.");
                }

                $createdFiles[] = $dstF;

                // 2.8) enregistrement BD
                $type = ($i === $primaryIndex) ? 'Main' : 'Additional';
                $mImage->create($stampId, $rel, $type);
            }

            // 3) enchère (option) en BD
            if (!empty($_POST['auction_enable'])) {
                $start = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d H:i:s');
                $end   = !empty($_POST['end_date'])   ? $_POST['end_date']   : date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
                $sp    = isset($_POST['starting_price']) ? (float)$_POST['starting_price'] : 0.00;
                $fav   = !empty($_POST['is_lord_favorite']) ? 1 : 0;

                $mAuc->create([
                    'start_date'       => $start,
                    'end_date'         => $end,
                    'starting_price'   => $sp,
                    'current_price'    => $sp,
                    'is_lord_favorite' => $fav,
                    'Stamp_id'         => $stampId,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            // rollback BD + supprimer fichiers déjà écrits
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($createdFiles as $file) {
                if (\is_file($file)) {
                    @\unlink($file);
                }
            }
            $_SESSION['error'] = "Erreur image : " . $e->getMessage();
            return View::redirect('stamps/create');
        }

        $_SESSION['success'] = "Timbre créé.";
        return View::redirect('stamps'); // page de gestion
    }

    /* ----------------- GESTION (MES TIMBRES) ----------------- */
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

        // ====== PARAMÈTRES IMAGES (mêmes limites que store) ======
        $MAX_FILES     = 10;                 // nombre max d’images ajoutées à l’édition
        $MIN_BYTES     = 4 * 1024;           // 4 Ko
        $MAX_BYTES     = 6 * 1024 * 1024;    // 6 Mo
        $MIN_W         = 300;
        $MIN_H         = 300;
        $MAX_W         = 6000;
        $MAX_H         = 6000;
        $MIN_RATIO     = 0.25;
        $MAX_RATIO     = 4.0;
        $TARGET_MAX    = 1600;               // redimensionnement si plus grand
        $JPEG_QUALITY  = 85;
        $WEBP_QUALITY  = 82;
        $PNG_COMPR     = 6;

        $required = ['name', 'creationDate', 'country_id', 'category_id', 'condition_id'];
        foreach ($required as $r) {
            if (empty($_POST[$r])) {
                $_SESSION['error'] = "Champ $r requis.";
                return View::redirect('stamps/edit?id=' . $id);
            }
        }

        $mStamp = new Stamp();
        $mImage = new Image();

        // 1) mise à jour des champs de base
        $ok = $mStamp->updateForOwner($id, $this->userId(), [
            'name'         => \trim($_POST['name']),
            'creationDate' => $_POST['creationDate'],
            'country_id'   => (int)$_POST['country_id'],
            'category_id'  => (int)$_POST['category_id'],
            'condition_id' => (int)$_POST['condition_id'],
            'color_id'     => ($_POST['color_id'] !== '' ? (int)$_POST['color_id'] : null),
        ]);
        if (!$ok) {
            $_SESSION['error'] = 'Non autorisé ou aucune modification.';
            return View::redirect('stamps/edit?id=' . $id);
        }

        // 2) éventuel changement d’image principale parmi les images EXISTANTES
        //    (si tu ajoutes un radio bouton/hidden 'make_main_id' dans le formulaire)
        if (!empty($_POST['make_main_id'])) {
            $makeMainId = (int)$_POST['make_main_id'];
            $mImage->setMain($id, $makeMainId, $this->userId()); // ignore échec silencieusement
        }

        // 3) ajout d’images (facultatif) : mêmes validations que store()
        $hasNewImages = isset($_FILES['images']) && !empty($_FILES['images']['name'][0]);
        if ($hasNewImages) {
            $f = $_FILES['images'];
            if (\count($f['name']) > $MAX_FILES) {
                $_SESSION['error'] = "Trop d’images (max {$MAX_FILES}).";
                return View::redirect('stamps/edit?id=' . $id);
            }

            $allowed = [
                'image/jpeg' => '.jpg',
                'image/jpg'  => '.jpg',
                'image/pjpeg' => '.jpg',
                'image/png'  => '.png',
                'image/webp' => '.webp',
            ];

            $primaryIndex = isset($_POST['primary_index']) ? (int)$_POST['primary_index'] : -1; // -1 = ne pas toucher la Main
            $countFiles   = \count($f['name']);

            $subdir  = \date('Y/m');
            $baseDir = __DIR__ . '/../../public/assets/images/uploads/' . $subdir;
            if (!\is_dir($baseDir)) {
                @\mkdir($baseDir, 0775, true);
            }

            $createdFiles = [];
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            try {
                for ($i = 0; $i < $countFiles; $i++) {
                    if ($f['error'][$i] !== UPLOAD_ERR_OK) {
                        throw new \RuntimeException("Erreur upload image (index {$i}).");
                    }

                    $size = (int)$f['size'][$i];
                    if ($size < $MIN_BYTES || $size > $MAX_BYTES) {
                        throw new \RuntimeException("Taille d'image invalide (min 4 Ko, max 6 Mo).");
                    }

                    $tmp  = $f['tmp_name'][$i];
                    $mime = '';
                    if (\function_exists('mime_content_type')) {
                        $mime = \mime_content_type($tmp);
                    } elseif (\function_exists('finfo_open')) {
                        $fi = \finfo_open(FILEINFO_MIME_TYPE);
                        $mime = \finfo_file($fi, $tmp);
                        \finfo_close($fi);
                    } else {
                        $info = \getimagesize($tmp);
                        $mime = $info['mime'] ?? '';
                    }
                    if ($mime === 'image/jpg' || $mime === 'image/pjpeg') $mime = 'image/jpeg';
                    if (!isset($allowed[$mime])) {
                        throw new \RuntimeException("Type d'image non autorisé ({$mime}).");
                    }

                    $g = \getimagesize($tmp);
                    if (!$g) throw new \RuntimeException("Fichier image invalide.");
                    [$w, $h] = $g;
                    if ($w < $MIN_W || $h < $MIN_H || $w > $MAX_W || $h > $MAX_H) {
                        throw new \RuntimeException("Dimensions invalides ({$w}×{$h}).");
                    }
                    $ratio = $h > 0 ? $w / $h : 0;
                    if ($ratio < $MIN_RATIO || $ratio > $MAX_RATIO) {
                        throw new \RuntimeException("Ratio largeur/hauteur anormal.");
                    }

                    // charger
                    $src = null;
                    if ($mime === 'image/jpeg') {
                        $src = \imagecreatefromjpeg($tmp);
                    } elseif ($mime === 'image/png') {
                        $src = \imagecreatefrompng($tmp);
                    } elseif ($mime === 'image/webp') {
                        if (\function_exists('imagecreatefromwebp')) {
                            $src = \imagecreatefromwebp($tmp);
                        } else {
                            throw new \RuntimeException("WebP non supporté par GD.");
                        }
                    }
                    if (!$src) throw new \RuntimeException("Impossible de lire l'image.");

                    // orientation EXIF
                    if ($mime === 'image/jpeg' && \function_exists('exif_read_data')) {
                        try {
                            $exif = @\exif_read_data($tmp);
                            $ori  = isset($exif['Orientation']) ? (int)$exif['Orientation'] : 1;
                            if ($ori === 3) {
                                $src = \imagerotate($src, 180, 0);
                            } elseif ($ori === 6) {
                                $src = \imagerotate($src, -90, 0);
                            } elseif ($ori === 8) {
                                $src = \imagerotate($src, 90, 0);
                            }
                        } catch (\Throwable $e) {
                        }
                    }

                    // redimensionnement
                    $maxSide = \max($w, $h);
                    $dst = $src;
                    $outW = $w;
                    $outH = $h;
                    if ($maxSide > $TARGET_MAX) {
                        $scale = $TARGET_MAX / $maxSide;
                        $outW  = (int)\round($w * $scale);
                        $outH  = (int)\round($h * $scale);
                        $dst   = \imagecreatetruecolor($outW, $outH);
                        if ($mime !== 'image/jpeg') {
                            \imagealphablending($dst, false);
                            \imagesavealpha($dst, true);
                            $trans = \imagecolorallocatealpha($dst, 0, 0, 0, 127);
                            \imagefilledrectangle($dst, 0, 0, $outW, $outH, $trans);
                        }
                        \imagecopyresampled($dst, $src, 0, 0, 0, 0, $outW, $outH, $w, $h);
                    }

                    $ext  = $allowed[$mime];
                    $rand = \bin2hex(\random_bytes(8));
                    $rel  = 'uploads/' . $subdir . '/' . $rand . $ext;
                    $dstF = __DIR__ . '/../../public/assets/images/' . $rel;

                    $okSave = false;
                    if ($mime === 'image/jpeg') {
                        $okSave = \imagejpeg($dst, $dstF, $JPEG_QUALITY);
                    } elseif ($mime === 'image/png') {
                        $okSave = \imagepng($dst, $dstF, $PNG_COMPR);
                    } elseif ($mime === 'image/webp' && \function_exists('imagewebp')) {
                        $okSave = \imagewebp($dst, $dstF, $WEBP_QUALITY);
                    } else {
                        $okSave = \move_uploaded_file($tmp, $dstF);
                    }

                    if ($dst !== $src) {
                        \imagedestroy($dst);
                    }
                    \imagedestroy($src);

                    if (!$okSave) {
                        throw new \RuntimeException("Erreur lors de l’enregistrement de l’image.");
                    }

                    $createdFiles[] = $dstF;

                    // insérer l’image : si primary_index est défini et correspond à ce fichier, on le met en Main
                    $type = ($primaryIndex >= 0 && $i === $primaryIndex) ? 'Main' : 'Additional';
                    $mImage->create($id, $rel, $type);

                    // si on a créé une nouvelle Main, on remet les autres en Additional
                    if ($type === 'Main') {
                        $mImage->setMain($id, (int)Database::getConnection()->lastInsertId(), $this->userId());
                    }
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($createdFiles as $file) {
                    if (\is_file($file)) {
                        @\unlink($file);
                    }
                }
                $_SESSION['error'] = "Erreur image : " . $e->getMessage();
                return View::redirect('stamps/edit?id=' . $id);
            }
        }

        $_SESSION['success'] = 'Timbre modifié.';
        return View::redirect('stamps');
    }


    // id en POST hidden
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

        // sécurité : n'autoriser que les propres timbres
        $stamp = $mStamp->findByIdForOwner($id, $this->userId());
        if (!$stamp) {
            $_SESSION['error'] = "Non autorisé ou timbre inexistant.";
            return View::redirect('stamps');
        }

        // récupérer les chemins d'images pour supprimer les fichiers après la BD
        $paths = array_column($mImg->getByStampId($id), 'image_url');

        $ok = $mStamp->deleteForOwner($id, $this->userId());

        if ($ok) {
            foreach ($paths as $rel) {
                $file = __DIR__ . '/../../public/assets/images/' . $rel;
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            $_SESSION['success'] = "Timbre supprimé.";
        } else {
            $_SESSION['error'] = "Erreur de suppression.";
        }
        return View::redirect('stamps');
    }

    /* ----------------- PUBLIC : CATALOGUE + FICHE ----------------- */
    // /catalogue
    public function indexPublic()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $loggedin = !empty($_SESSION['user_id']);

        $db = \App\Models\Database::getConnection();
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

    // /fiche-produit 
    public function showPublic($id = null)
    {
        if ($id === null && isset($_GET['id'])) {
            $id = (int)$_GET['id'];  // supporte /fiche-produit&id=123
        }
        $id = (int)$id;

        $db = \App\Models\Database::getConnection();

        // Timbre + libellés
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

        // Images (Main d'abord)
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
