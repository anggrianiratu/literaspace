<?php
// ========================================
// WISHLIST.PHP - LITERASPACE
// Halaman Wishlist Pengguna
// ========================================

session_start();
require_once __DIR__ . '/../config/db.php';

$pdo            = getDB();
$user_id        = $_SESSION['user_id'] ?? null;
$cart_count     = 0;
$wishlist_count = 0;
$wishlist_books = [];
$error          = null;

// Redirect jika belum login
if (!$user_id) {
    header('Location: /literaspace/auth/login.php');
    exit;
}

// Handle aksi POST (hapus semua / pindah semua ke keranjang)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'hapus_semua') {
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id_user = ?");
            $stmt->execute([$user_id]);
            header('Location: wishlist.php?msg=deleted');
            exit;
        }

        if ($action === 'pindah_semua') {
            // Ambil semua buku di wishlist yang stok > 0
            $stmt = $pdo->prepare("
                SELECT w.id_buku FROM wishlist w
                JOIN buku b ON w.id_buku = b.id_buku
                WHERE w.id_user = ? AND b.stok > 0
            ");
            $stmt->execute([$user_id]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($ids as $id_buku) {
                // Cek apakah sudah ada di keranjang
                $cek = $pdo->prepare("SELECT id_keranjang, qty FROM keranjang WHERE id_user = ? AND id_buku = ?");
                $cek->execute([$user_id, $id_buku]);
                $existing = $cek->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $upd = $pdo->prepare("UPDATE keranjang SET qty = qty + 1 WHERE id_keranjang = ?");
                    $upd->execute([$existing['id_keranjang']]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO keranjang (id_user, id_buku, qty) VALUES (?, ?, 1)");
                    $ins->execute([$user_id, $id_buku]);
                }
            }
            header('Location: wishlist.php?msg=moved');
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

try {
    // Ambil buku wishlist beserta info lengkap
    $stmt = $pdo->prepare("
        SELECT b.id_buku, b.judul, b.penulis, b.harga, b.cover_image, b.stok,
               k.nama_kategori,
               COALESCE(ROUND(AVG(r.rating),1),0) AS avg_rating,
               COALESCE(COUNT(r.id_review),0) AS review_count,
               w.id_wishlist
        FROM wishlist w
        JOIN buku b ON w.id_buku = b.id_buku
        LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
        LEFT JOIN review r ON b.id_buku = r.id_buku
        WHERE w.id_user = ?
        GROUP BY b.id_buku, b.judul, b.penulis, b.harga, b.cover_image, b.stok, k.nama_kategori, w.id_wishlist
        ORDER BY w.id_wishlist DESC
    ");
    $stmt->execute([$user_id]);
    $wishlist_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count keranjang & wishlist
    $sc = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?"); $sc->execute([$user_id]); $cart_count = (int)$sc->fetchColumn();
    $wishlist_count = count($wishlist_books);

} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

function starHtml($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= round($rating)
            ? '<i class="fas fa-star" style="color:#f59e0b;font-size:.7rem;"></i>'
            : '<i class="far fa-star" style="color:#d1d5db;font-size:.7rem;"></i>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Wishlist Saya — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --indigo-deep:  #1e1667;
            --indigo-mid:   #2d2a8f;
            --indigo-light: #3b2ec0;
            --white:        #ffffff;
            --gray-50:      #f8f8fb;
            --gray-100:     #f0f0f7;
            --gray-200:     #e2e2ef;
            --gray-500:     #6b6b8a;
            --gray-800:     #1a1a2e;
            --error:        #e03c3c;
            --success:      #1db87d;
            --amber:        #d4920a;
            --radius:       14px;
            --shadow:       0 4px 24px rgba(30,22,103,.10);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--gray-50);
            min-height: 100vh;
            color: var(--gray-800);
        }

        /* ── Navbar ── */
        .navbar {
            position: sticky; top: 0; z-index: 50;
            background: var(--white);
            box-shadow: 0 2px 16px rgba(30,22,103,.09);
            border-bottom: 1.5px solid var(--gray-200);
        }

        .logo-icon {
            width: 40px; height: 40px;
            background: var(--indigo-deep);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s, transform .2s;
            flex-shrink: 0;
        }
        .logo-icon:hover { background: var(--indigo-light); transform: scale(1.05); }
        .logo-icon svg   { width: 20px; height: 20px; fill: var(--white); }

        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem; color: var(--gray-800); font-weight: 700;
        }

        .search-wrap { flex: 1; max-width: 420px; position: relative; }
        .search-input {
            width: 100%; padding: .6rem 2.2rem .6rem 1rem;
            background: var(--gray-50);
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .9rem; color: var(--gray-800);
            outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .search-input::placeholder { color: var(--gray-500); }
        .search-input:focus {
            border-color: var(--indigo-light);
            box-shadow: 0 0 0 3px rgba(59,46,192,.10);
            background: var(--white);
        }
        .search-btn {
            position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--gray-500); font-size: .85rem; transition: color .2s;
        }
        .search-btn:hover { color: var(--indigo-light); }

        .nav-icon {
            color: var(--gray-500); font-size: 1.15rem;
            text-decoration: none; position: relative; transition: color .2s;
        }
        .nav-icon:hover { color: var(--indigo-light); }
        .nav-badge {
            position: absolute; top: -7px; right: -7px;
            background: var(--error); color: var(--white);
            font-size: .62rem; font-weight: 700;
            width: 17px; height: 17px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }

        .btn-auth {
            padding: .42rem 1rem; background: var(--indigo-deep); color: var(--white);
            border: none; border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .86rem; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: background .2s;
        }
        .btn-auth:hover { background: var(--indigo-light); }

        /* Dropdown */
        .dropdown-wrap { position: relative; }
        .dropdown-menu {
            position: absolute; right: 0; top: calc(100% + 8px);
            width: 175px; background: var(--white);
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(30,22,103,.22), 0 2px 8px rgba(30,22,103,.10);
            border: 1.5px solid var(--gray-200);
            opacity: 0; visibility: hidden;
            transition: opacity .2s, visibility .2s; z-index: 100;
        }
        .dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a {
            display: block; padding: .62rem 1rem; font-size: .86rem; color: var(--gray-800);
            text-decoration: none; transition: background .15s, color .15s;
        }
        .dropdown-menu a:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-menu a:last-child  { border-radius: 0 0 8px 8px; color: var(--error); }
        .dropdown-menu a:hover       { background: rgba(30,22,103,.05); color: var(--indigo-light); }
        .dropdown-menu a:last-child:hover { background: #fdecea; color: var(--error); }
        .dropdown-menu hr { border-color: var(--gray-200); margin: .25rem 0; }

        /* ── Page layout ── */
        .page-inner { max-width: 1280px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ── Page header ── */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.6rem; flex-wrap: wrap; gap: .8rem;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem; color: var(--gray-800);
        }
        .page-subtitle { font-size: .85rem; color: var(--gray-500); margin-top: .2rem; }

        .header-actions { display: flex; align-items: center; gap: .6rem; }

        .btn-move-all {
            padding: .48rem 1rem;
            background: var(--indigo-deep); color: var(--white);
            border: none; border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .85rem; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: background .2s;
            display: flex; align-items: center; gap: .4rem;
        }
        .btn-move-all:hover { background: var(--indigo-light); }

        .btn-delete-all {
            padding: .48rem 1rem;
            background: transparent; color: var(--error);
            border: 1.5px solid var(--error); border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .85rem; font-weight: 600;
            cursor: pointer; transition: background .2s, color .2s;
            display: flex; align-items: center; gap: .4rem;
        }
        .btn-delete-all:hover { background: var(--error); color: var(--white); }

        /* ── Book card ── */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
        }

        .book-card {
            background: var(--white);
            border-radius: 12px;
            border: 1.5px solid var(--gray-200);
            overflow: hidden;
            transition: transform .3s, box-shadow .3s;
            position: relative;
        }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 16px 40px rgba(30,22,103,.12); }

        .cover-wrap { position: relative; }
        .cover-placeholder {
            width: 100%; aspect-ratio: 3/4;
            display: flex; align-items: center; justify-content: center;
        }
        .cover-placeholder svg { width: 36px; height: 36px; fill: rgba(255,255,255,.4); }

        /* Tombol hapus wishlist */
        .btn-remove-wish {
            position: absolute; top: 6px; right: 6px; z-index: 2;
            width: 28px; height: 28px;
            background: rgba(255,255,255,.92);
            border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--error);
            font-size: .8rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
            transition: background .2s, transform .2s;
        }
        .btn-remove-wish:hover { background: var(--error); color: var(--white); transform: scale(1.1); }

        /* Category badge */
        .cat-badge {
            position: absolute; top: 6px; left: 6px;
            background: rgba(255,255,255,.92);
            color: var(--indigo-deep);
            font-size: .68rem; font-weight: 700;
            padding: .15rem .5rem; border-radius: 9999px;
            letter-spacing: .03em;
        }

        /* Stok habis */
        .sold-out-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,.5);
            display: flex; align-items: center; justify-content: center;
        }
        .sold-out-badge {
            background: var(--error); color: var(--white);
            font-size: .72rem; font-weight: 700;
            padding: .25rem .8rem; border-radius: 9999px;
        }

        .book-info { padding: .75rem; }
        .book-title {
            font-size: .84rem; font-weight: 600; color: var(--gray-800);
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            margin-bottom: .15rem; transition: color .2s; text-decoration: none;
        }
        .book-title:hover { color: var(--indigo-light); }
        .book-author { font-size: .74rem; color: var(--gray-500); margin-bottom: .5rem; }

        .star-row { display: flex; align-items: center; gap: .3rem; margin-bottom: .5rem; }
        .star-count { font-size: .72rem; color: var(--gray-500); }

        .book-price { font-size: .9rem; font-weight: 700; color: var(--indigo-deep); }

        .btn-cart {
            width: 100%; padding: .48rem;
            background: var(--indigo-deep); color: var(--white);
            border: none; border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .8rem; font-weight: 600;
            cursor: pointer; transition: background .2s;
            display: flex; align-items: center; justify-content: center; gap: .35rem;
            margin-top: .6rem;
        }
        .btn-cart:hover    { background: var(--indigo-light); }
        .btn-cart:disabled {
            background: var(--gray-200); color: var(--gray-500);
            cursor: not-allowed; border: 1.5px solid var(--gray-200);
        }

        /* ── Empty state ── */
        .empty-state {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--gray-200);
            padding: 5rem 2rem;
            text-align: center;
            box-shadow: var(--shadow);
        }
        .empty-icon { font-size: 3rem; color: var(--gray-200); margin-bottom: 1rem; display: block; }
        .empty-title { font-family: 'Playfair Display', serif; font-size: 1.1rem; color: var(--gray-500); }
        .empty-sub   { font-size: .85rem; color: var(--gray-500); margin-top: .3rem; }

        .btn-empty {
            display: inline-block; margin-top: 1.2rem;
            padding: .6rem 1.4rem;
            background: var(--indigo-deep); color: var(--white);
            border-radius: 8px; text-decoration: none;
            font-size: .88rem; font-weight: 600;
            transition: background .2s;
        }
        .btn-empty:hover { background: var(--indigo-light); }

        /* ── Flash message ── */
        .flash-msg {
            padding: .7rem 1rem; border-radius: 8px;
            font-size: .88rem; margin-bottom: 1.2rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .flash-success { background: #e6f9f1; border: 1.5px solid #a7e8cb; color: #0f6b44; }
        .flash-error   { background: #fdecea; border: 1.5px solid #f5c6c6; color: var(--error); }

        /* ── Toast ── */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999;
            padding: .7rem 1.1rem; border-radius: 10px;
            color: var(--white); font-size: .87rem;
            display: flex; align-items: center; gap: .5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            transform: translateY(80px); opacity: 0;
            transition: all .3s; pointer-events: none;
        }

        /* ── Modal konfirmasi ── */
        .modal-overlay {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(10,8,40,.45);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden;
            transition: opacity .2s, visibility .2s;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-box {
            background: var(--white); border-radius: var(--radius);
            padding: 1.8rem 2rem; max-width: 380px; width: 90%;
            box-shadow: 0 16px 48px rgba(30,22,103,.25);
            border: 1.5px solid var(--gray-200);
            transform: translateY(12px) scale(.97);
            transition: transform .2s;
        }
        .modal-overlay.active .modal-box { transform: translateY(0) scale(1); }
        .modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem; color: var(--gray-800); margin-bottom: .5rem;
        }
        .modal-desc  { font-size: .87rem; color: var(--gray-500); margin-bottom: 1.3rem; }
        .modal-actions { display: flex; gap: .6rem; justify-content: flex-end; }
        .btn-cancel {
            padding: .48rem 1rem;
            background: var(--gray-100); color: var(--gray-800);
            border: 1.5px solid var(--gray-200); border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .86rem; font-weight: 600;
            cursor: pointer; transition: background .2s;
        }
        .btn-cancel:hover { background: var(--gray-200); }
        .btn-confirm-del {
            padding: .48rem 1rem;
            background: var(--error); color: var(--white);
            border: none; border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .86rem; font-weight: 600;
            cursor: pointer; transition: background .2s;
        }
        .btn-confirm-del:hover { background: #c0392b; }

        @media (max-width: 600px) {
            .books-grid { grid-template-columns: repeat(2, 1fr); }
            .header-actions { width: 100%; }
            .btn-move-all, .btn-delete-all { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- ════════════════════════════════
     NAVBAR
════════════════════════════════ -->
<nav class="navbar">
    <div style="max-width:1280px; margin:0 auto; padding:0 1.5rem;">
        <div style="display:flex; align-items:center; justify-content:space-between; height:68px; gap:1rem;">

            <!-- Logo -->
            <a href="/literaspace/index.php" style="display:flex; align-items:center; gap:.6rem; text-decoration:none; flex-shrink:0;">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                </div>
                <span class="logo-text" style="display:none;" id="logo-text-desktop">LiteraSpace</span>
            </a>

            <!-- Search -->
            <div class="search-wrap">
                <form action="/literaspace/pages/kategori.php" method="GET">
                    <input type="search" name="q" placeholder="Cari judul, penulis, atau kategori..."
                           class="search-input" />
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <!-- Right icons -->
            <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
                <a href="/literaspace/pages/keranjang.php" class="nav-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cart_count > 0): ?><span class="nav-badge"><?= min($cart_count,99) ?></span><?php endif; ?>
                </a>
                <a href="/wishlist.php" class="nav-icon" style="color:var(--error);">
                    <i class="fas fa-heart"></i>
                    <?php if ($wishlist_count > 0): ?><span class="nav-badge"><?= min($wishlist_count,99) ?></span><?php endif; ?>
                </a>

                <div class="dropdown-wrap">
                    <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                        <i class="fas fa-user-circle" style="font-size:1.45rem;"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="/literaspace/pages/profile.php"><i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya</a>
                        <a href="/literaspace/pages/pesanan.php"><i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya</a>
                        <hr />
                        <a href="/literaspace/auth/logout.php"><i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ════════════════════════════════
     PAGE CONTENT
════════════════════════════════ -->
<main class="page-inner">

    <!-- Flash messages -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'deleted'): ?>
            <div class="flash-msg flash-success">
                <i class="fas fa-check-circle"></i> Semua buku berhasil dihapus dari wishlist.
            </div>
        <?php elseif ($_GET['msg'] === 'moved'): ?>
            <div class="flash-msg flash-success">
                <i class="fas fa-check-circle"></i> Buku berhasil dipindahkan ke keranjang!
            </div>
        <?php elseif ($_GET['msg'] === 'removed'): ?>
            <div class="flash-msg flash-success">
                <i class="fas fa-check-circle"></i> Buku dihapus dari wishlist.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="flash-msg flash-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Wishlist Saya</h1>
            <p class="page-subtitle">
                <?php if ($wishlist_count > 0): ?>
                    <?= $wishlist_count ?> buku tersimpan
                <?php else: ?>
                    Belum ada buku yang disimpan
                <?php endif; ?>
            </p>
        </div>

        <?php if ($wishlist_count > 0): ?>
            <div class="header-actions">
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="action" value="pindah_semua">
                    <button type="submit" class="btn-move-all">
                        <i class="fas fa-cart-plus"></i> Pindah semua ke keranjang
                    </button>
                </form>
                <button type="button" class="btn-delete-all" onclick="openModalHapusSemua()">
                    <i class="fas fa-trash-alt"></i> Hapus semua
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Konten -->
    <?php if (empty($wishlist_books)): ?>
        <!-- Empty state -->
        <div class="empty-state">
            <i class="far fa-heart empty-icon"></i>
            <p class="empty-title">Wishlist kamu masih kosong</p>
            <p class="empty-sub">Simpan buku favorit kamu di sini</p>
            <a href="/literaspace/pages/kategori.php" class="btn-empty">
                <i class="fas fa-book-open" style="margin-right:.4rem;"></i> Lihat Katalog
            </a>
        </div>

    <?php else: ?>
        <!-- Grid buku -->
        <?php
        $cover_gradients = [
            'linear-gradient(135deg,#1e1667,#3b2ec0)',
            'linear-gradient(135deg,#0f4c75,#1b6ca8)',
            'linear-gradient(135deg,#2d6a4f,#52b788)',
            'linear-gradient(135deg,#7b2d8b,#c77dff)',
            'linear-gradient(135deg,#b5451b,#e76f51)',
            'linear-gradient(135deg,#1a1040,#4a2f9a)',
        ];
        ?>
        <div class="books-grid" id="books-grid">
            <?php foreach ($wishlist_books as $book):
                $ci = $book['id_buku'] % count($cover_gradients);
            ?>
            <div class="book-card" id="card-<?= $book['id_wishlist'] ?>">
                <div class="cover-wrap">

                    <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                        <img src="/assets/images/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                             alt="<?= htmlspecialchars($book['judul']) ?>"
                             style="width:100%; aspect-ratio:3/4; object-fit:cover; display:block;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                        <div class="cover-placeholder" style="display:none; background:<?= $cover_gradients[$ci] ?>;">
                            <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                        </div>
                    <?php else: ?>
                        <div class="cover-placeholder" style="background:<?= $cover_gradients[$ci] ?>;">
                            <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                        </div>
                    <?php endif; ?>

                    <!-- Tombol hapus dari wishlist -->
                    <button class="btn-remove-wish"
                            onclick="hapusDariWishlist(<?= $book['id_wishlist'] ?>, <?= $book['id_buku'] ?>, this)"
                            title="Hapus dari wishlist">
                        <i class="fas fa-heart"></i>
                    </button>

                    <?php if (!empty($book['nama_kategori'])): ?>
                        <span class="cat-badge"><?= htmlspecialchars($book['nama_kategori']) ?></span>
                    <?php endif; ?>

                    <?php if ((int)$book['stok'] === 0): ?>
                        <div class="sold-out-overlay">
                            <span class="sold-out-badge">Stok Habis</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="book-info">
                    <a href="detail.php?id=<?= $book['id_buku'] ?>" class="book-title">
                        <?= htmlspecialchars($book['judul']) ?>
                    </a>
                    <p class="book-author"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>

                    <div class="star-row">
                        <?= starHtml($book['avg_rating']) ?>
                        <span class="star-count">
                            <?= $book['avg_rating'] > 0 ? $book['avg_rating'] : '—' ?>
                            <?php if ($book['review_count'] > 0): ?> · <?= number_format($book['review_count']) ?><?php endif; ?>
                        </span>
                    </div>

                    <span class="book-price"><?= formatRupiah($book['harga']) ?></span>

                    <?php if ((int)$book['stok'] > 0): ?>
                        <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)">
                            <i class="fas fa-cart-plus"></i> + Keranjang
                        </button>
                    <?php else: ?>
                        <button class="btn-cart" disabled>
                            <i class="fas fa-cart-plus"></i> Stok Habis
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Toast -->
<div id="toast">
    <i class="fas fa-check-circle" id="toast-icon"></i>
    <span id="toast-msg">Berhasil!</span>
</div>

<!-- Modal konfirmasi hapus semua -->
<div class="modal-overlay" id="modal-hapus-semua">
    <div class="modal-box">
        <p class="modal-title">Hapus semua wishlist?</p>
        <p class="modal-desc">Semua buku akan dihapus dari wishlist kamu. Tindakan ini tidak bisa dibatalkan.</p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('modal-hapus-semua')">Batal</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="hapus_semua">
                <button type="submit" class="btn-confirm-del">
                    <i class="fas fa-trash-alt" style="margin-right:.3rem;"></i> Ya, Hapus Semua
                </button>
            </form>
        </div>
    </div>
</div>

<style>
    @media (min-width: 600px) {
        #logo-text-desktop { display: inline !important; }
    }
</style>

<script>
/* ── Toast ── */
function showToast(msg, ok = true) {
    const t    = document.getElementById('toast');
    const icon = document.getElementById('toast-icon');
    document.getElementById('toast-msg').textContent = msg;
    t.style.background = ok ? '#1db87d' : '#e03c3c';
    icon.className = ok ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    t.style.transform = 'translateY(0)'; t.style.opacity = '1';
    setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 2800);
}

/* ── Modal ── */
function openModalHapusSemua() { document.getElementById('modal-hapus-semua').classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.getElementById('modal-hapus-semua').addEventListener('click', function(e) {
    if (e.target === this) closeModal('modal-hapus-semua');
});

/* ── Hapus dari wishlist (AJAX) ── */
function hapusDariWishlist(idWishlist, idBuku, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.7rem;"></i>';

    fetch('/api/wishlist.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const card = document.getElementById('card-' + idWishlist);
            if (card) {
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity    = '0';
                card.style.transform  = 'scale(.95)';
                setTimeout(() => {
                    card.remove();
                    const remaining = document.querySelectorAll('.book-card').length;
                    updateWishlistCount(remaining);
                    if (remaining === 0) showEmptyState();
                }, 300);
            }
            showToast('Buku dihapus dari wishlist.');
        } else {
            showToast(d.message || 'Gagal menghapus.', false);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-heart"></i>';
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan.', false);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-heart"></i>';
    });
}

/* ── Update counter subtitle ── */
function updateWishlistCount(n) {
    const el = document.querySelector('.page-subtitle');
    if (el) el.textContent = n > 0 ? n + ' buku tersimpan' : 'Belum ada buku yang disimpan';

    const badge = document.querySelector('.nav-badge');
    if (badge) {
        if (n > 0) badge.textContent = Math.min(n, 99);
        else badge.remove();
    }

    // Sembunyikan tombol aksi header jika kosong
    if (n === 0) {
        const actions = document.querySelector('.header-actions');
        if (actions) actions.remove();
    }
}

/* ── Tampilkan empty state ── */
function showEmptyState() {
    const grid = document.getElementById('books-grid');
    if (grid) {
        grid.outerHTML = `
            <div class="empty-state">
                <i class="far fa-heart empty-icon"></i>
                <p class="empty-title">Wishlist kamu masih kosong</p>
                <p class="empty-sub">Simpan buku favorit kamu di sini</p>
                <a href="/kategori.php" class="btn-empty">
                    <i class="fas fa-book-open" style="margin-right:.4rem;"></i> Lihat Katalog
                </a>
            </div>
        `;
    }
}

/* ── Tambah ke keranjang ── */
function tambahKeranjang(idBuku, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menambahkan...';

    fetch('/api/keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty: 1 })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Buku ditambahkan ke keranjang!');
            btn.innerHTML = '<i class="fas fa-check"></i> Ditambahkan';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-cart-plus"></i> + Keranjang';
                btn.disabled = false;
            }, 2000);
        } else {
            showToast(d.message || 'Gagal menambahkan.', false);
            btn.innerHTML = '<i class="fas fa-cart-plus"></i> + Keranjang';
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan.', false);
        btn.innerHTML = '<i class="fas fa-cart-plus"></i> + Keranjang';
        btn.disabled = false;
    });
}

/* ── Auto-dismiss flash message ── */
const flash = document.querySelector('.flash-msg');
if (flash) setTimeout(() => { flash.style.transition = 'opacity .4s'; flash.style.opacity = '0'; setTimeout(() => flash.remove(), 400); }, 3500);
</script>
</body>
</html>