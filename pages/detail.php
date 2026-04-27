<?php
// ========================================
// DETAIL.PHP - LITERASPACE
// Halaman Detail Buku
// ========================================

session_start();
require_once __DIR__ . '/../config/db.php';

$pdo = getDB();
$user_id = $_SESSION['user_id'] ?? null;
$cart_count = 0;
$wishlist_count = 0;
$book = null;
$reviews = [];
$avg_rating = 0;
$review_count = 0;
$user_review = null;
$error = null;

$id_buku = (int)($_GET['id'] ?? 0);

if ($id_buku <= 0) {
    header('Location: kategori.php');
    exit;
}

try {
    // Get book detail
    $stmt = $pdo->prepare("
        SELECT b.*, k.nama_kategori
        FROM buku b
        LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
        WHERE b.id_buku = ?
    ");
    $stmt->execute([$id_buku]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        header('Location: kategori.php');
        exit;
    }

    // Get reviews with user info
    $stmt = $pdo->prepare("
        SELECT r.id_review, r.rating, r.komentar, r.created_at,
               u.nama_depan, u.nama_belakang
        FROM review r
        JOIN users u ON r.id_user = u.id
        WHERE r.id_buku = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$id_buku]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get average rating and count
    $stmt = $pdo->prepare("
        SELECT COALESCE(ROUND(AVG(rating),1),0) AS avg_rating,
               COUNT(*) AS review_count
        FROM review
        WHERE id_buku = ?
    ");
    $stmt->execute([$id_buku]);
    $rating_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_rating = $rating_data['avg_rating'] ?? 0;
    $review_count = $rating_data['review_count'] ?? 0;

    // Get user's own review if logged in
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT id_review, rating, komentar
            FROM review
            WHERE id_user = ? AND id_buku = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $id_buku]);
        $user_review = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get cart and wishlist counts
        $sc = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?");
        $sc->execute([$user_id]);
        $cart_count = (int)$sc->fetchColumn();

        $sw = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ?");
        $sw->execute([$user_id]);
        $wishlist_count = (int)$sw->fetchColumn();
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

function starHtml($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= round($rating)
            ? '<i class="fas fa-star" style="color:#f59e0b;font-size:.75rem;"></i>'
            : '<i class="far fa-star" style="color:#d1d5db;font-size:.75rem;"></i>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($book['judul'] ?? 'Detail Buku') ?> — LiteraSpace</title>
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
            color: var(--gray-800);
        }

        /* Navbar */
        .navbar {
            position: sticky; top: 0; z-index: 50;
            background: var(--white);
            box-shadow: 0 2px 16px rgba(30,22,103,.09);
            border-bottom: 1.5px solid var(--gray-200);
        }

        .navbar-inner {
            max-width: 1280px; margin: 0 auto;
            padding: 0 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
            height: 68px; gap: 1rem;
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
        .logo-icon svg { width: 20px; height: 20px; fill: var(--white); }

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
            font-family: 'DM Sans', sans-serif; font-size: .9rem;
            outline: none; transition: border-color .2s, box-shadow .2s;
        }
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
        .dropdown-menu a { display: block; padding: .62rem 1rem; font-size: .86rem; color: var(--gray-800); text-decoration: none; transition: background .15s; }
        .dropdown-menu a:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-menu a:last-child { border-radius: 0 0 8px 8px; color: var(--error); }
        .dropdown-menu a:hover { background: rgba(30,22,103,.05); color: var(--indigo-light); }

        /* Page */
        .page-inner {
            max-width: 1280px; margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .breadcrumb {
            display: flex; gap: .5rem; margin-bottom: 2rem;
            font-size: .85rem; color: var(--gray-500);
        }
        .breadcrumb a { color: var(--indigo-light); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .detail-layout {
            display: grid; grid-template-columns: 300px 1fr;
            gap: 3rem; margin-bottom: 3rem;
        }
        @media (max-width: 768px) {
            .detail-layout { grid-template-columns: 1fr; gap: 2rem; }
        }

        /* Cover Section */
        .cover-section {
            display: flex; flex-direction: column; gap: 1.5rem;
        }

        .cover-img {
            width: 100%; aspect-ratio: 3/4;
            background: linear-gradient(135deg,#1e1667,#3b2ec0);
            border-radius: 12px;
            overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 12px 40px rgba(30,22,103,.15);
        }
        .cover-img img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .cover-img svg {
            width: 60px; height: 60px; fill: rgba(255,255,255,.3);
        }

        .btn-primary {
            padding: .75rem 1.5rem;
            background: var(--indigo-deep); color: var(--white);
            border: none; border-radius: 10px;
            font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 700;
            cursor: pointer; text-decoration: none;
            transition: background .2s, transform .1s;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
        }
        .btn-primary:hover { background: var(--indigo-light); }
        .btn-primary:active { transform: scale(.98); }
        .btn-primary:disabled { background: var(--gray-500); cursor: not-allowed; }

        .btn-secondary {
            padding: .75rem 1.5rem;
            background: var(--white); color: var(--indigo-deep);
            border: 1.5px solid var(--indigo-light); border-radius: 10px;
            font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 600;
            cursor: pointer; text-decoration: none;
            transition: background .2s;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
        }
        .btn-secondary:hover { background: var(--gray-50); }

        /* Info Section */
        .info-section h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem; color: var(--gray-800);
            margin-bottom: 1rem; line-height: 1.2;
        }

        .info-meta {
            display: flex; flex-direction: column; gap: .5rem;
            margin-bottom: 1.5rem; font-size: .95rem;
        }
        .info-meta-item {
            display: flex; align-items: center; gap: .5rem;
            color: var(--gray-500);
        }
        .info-meta-item strong { color: var(--gray-800); }

        .rating-section {
            display: flex; align-items: center; gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem; background: var(--gray-50);
            border-radius: 10px;
        }
        .rating-display {
            display: flex; flex-direction: column; align-items: center;
            gap: .3rem;
        }
        .rating-number {
            font-size: 2rem; font-weight: 700;
            color: var(--indigo-deep);
        }
        .rating-stars { display: flex; gap: .2rem; }
        .rating-count {
            font-size: .85rem; color: var(--gray-500);
            margin-left: .5rem; padding-left: .5rem;
            border-left: 1px solid var(--gray-200);
        }

        .price-section {
            font-size: 1.8rem; font-weight: 700;
            color: var(--indigo-deep); margin-bottom: 1.5rem;
        }

        .stok-badge {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .5rem 1rem; border-radius: 8px;
            font-size: .85rem; font-weight: 600; margin-bottom: 1.5rem;
        }
        .stok-badge.tersedia { background: rgba(29,184,125,.1); color: #1db87d; }
        .stok-badge.terbatas { background: rgba(212,146,10,.1); color: #d4920a; }
        .stok-badge.habis { background: rgba(224,60,60,.1); color: #e03c3c; }

        .btn-group {
            display: flex; gap: .75rem; flex-direction: column;
            margin-bottom: 2rem;
        }

        /* Synopsis */
        .synopsis-section {
            background: var(--white);
            border-radius: 12px;
            border: 1.5px solid var(--gray-200);
            padding: 2rem;
            margin-bottom: 3rem;
        }
        .synopsis-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem; color: var(--gray-800);
            margin-bottom: 1rem;
        }
        .synopsis-text {
            line-height: 1.8; color: var(--gray-500);
        }

        /* Reviews */
        .reviews-section {
            background: var(--white);
            border-radius: 12px;
            border: 1.5px solid var(--gray-200);
            padding: 2rem;
        }
        .reviews-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem; color: var(--gray-800);
            margin-bottom: 1.5rem;
        }

        .review-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-100);
        }
        .review-item:last-child { border-bottom: none; }

        .review-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: .75rem;
        }
        .review-user {
            font-weight: 600; color: var(--gray-800);
            font-size: .95rem;
        }
        .review-date {
            font-size: .8rem; color: var(--gray-500);
        }
        .review-stars { display: flex; gap: .2rem; }
        .review-text {
            color: var(--gray-500); line-height: 1.6;
            font-size: .9rem; margin-top: .5rem;
        }

        .no-reviews {
            text-align: center; padding: 2rem;
            color: var(--gray-500);
        }

        /* Toast */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999;
            padding: .7rem 1.1rem; border-radius: 10px;
            color: var(--white); font-size: .87rem;
            display: flex; align-items: center; gap: .5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            transform: translateY(80px); opacity: 0;
            transition: all .3s; pointer-events: none;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="../index.php" style="display:flex; align-items:center; gap:.6rem; text-decoration:none; flex-shrink:0;">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
            </div>
            <span class="logo-text" style="display:none;">LiteraSpace</span>
        </a>

        <div class="search-wrap">
            <form action="../pages/kategori.php" method="GET">
                <input type="search" name="q" placeholder="Cari judul, penulis, atau kategori..." class="search-input" />
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
            <a href="keranjang.php" class="nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?><span class="nav-badge"><?= min($cart_count, 99) ?></span><?php endif; ?>
            </a>
            <a href="wishlist.php" class="nav-icon" style="color:var(--gray-500);"
               onmouseover="this.style.color='#e03c3c'" onmouseout="this.style.color='var(--gray-500)'">
                <i class="far fa-heart"></i>
                <?php if ($wishlist_count > 0): ?><span class="nav-badge"><?= min($wishlist_count, 99) ?></span><?php endif; ?>
            </a>

            <?php if ($user_id): ?>
                <div class="dropdown-wrap">
                    <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                        <i class="fas fa-user-circle" style="font-size:1.45rem;"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="profile.php">Profil Saya</a>
                        <a href="pesanan.php">Pesanan Saya</a>
                        <a href="../auth/logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="../auth/login.php" class="btn-auth">Masuk / Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="page-inner">
    <?php if (!$book): ?>
        <div style="text-align:center; padding:4rem 2rem;">
            <i class="fas fa-exclamation-circle" style="font-size:3rem; color:var(--gray-200); margin-bottom:1rem; display:block;"></i>
            <p style="color:var(--gray-500); margin-bottom:1.5rem;">Buku tidak ditemukan</p>
            <a href="kategori.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Katalog
            </a>
        </div>
    <?php else: ?>
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../index.php">Beranda</a>
            <span>/</span>
            <a href="kategori.php">Katalog</a>
            <span>/</span>
            <span><?= htmlspecialchars($book['judul']) ?></span>
        </div>

        <!-- Detail Layout -->
        <div class="detail-layout">
            <!-- Cover Section -->
            <div class="cover-section">
                <div class="cover-img">
                    <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                        <img src="../assets/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['judul']) ?>" />
                    <?php else: ?>
                        <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                    <?php endif; ?>
                </div>
                <div class="btn-group">
                    <button class="btn-primary" onclick="tambahKeranjang(<?= $book['id_buku'] ?>)" <?= ($book['stok'] <= 0) ? 'disabled' : '' ?>>
                        <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
                    </button>
                    <button class="btn-secondary" onclick="tambahWishlist(<?= $book['id_buku'] ?>)">
                        <i class="far fa-heart"></i> Simpan ke Wishlist
                    </button>
                </div>
            </div>

            <!-- Info Section -->
            <div>
                <h1><?= htmlspecialchars($book['judul']) ?></h1>

                <div class="info-meta">
                    <div class="info-meta-item">
                        <i class="fas fa-user" style="width:1.2rem; color:var(--indigo-light);"></i>
                        <strong>Penulis:</strong> <?= htmlspecialchars($book['penulis'] ?? '—') ?>
                    </div>
                    <div class="info-meta-item">
                        <i class="fas fa-building" style="width:1.2rem; color:var(--indigo-light);"></i>
                        <strong>Penerbit:</strong> <?= htmlspecialchars($book['penerbit'] ?? '—') ?>
                    </div>
                    <?php if (!empty($book['isbn'])): ?>
                    <div class="info-meta-item">
                        <i class="fas fa-barcode" style="width:1.2rem; color:var(--indigo-light);"></i>
                        <strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($book['halaman'])): ?>
                    <div class="info-meta-item">
                        <i class="fas fa-book-open" style="width:1.2rem; color:var(--indigo-light);"></i>
                        <strong>Halaman:</strong> <?= htmlspecialchars($book['halaman']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($book['nama_kategori'])): ?>
                    <div class="info-meta-item">
                        <i class="fas fa-tag" style="width:1.2rem; color:var(--indigo-light);"></i>
                        <strong>Kategori:</strong> <?= htmlspecialchars($book['nama_kategori']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Rating -->
                <div class="rating-section">
                    <div class="rating-display">
                        <div class="rating-number"><?= $avg_rating ?></div>
                        <div class="rating-stars"><?= starHtml($avg_rating) ?></div>
                    </div>
                    <div class="rating-count"><?= $review_count ?> ulasan</div>
                </div>

                <!-- Price & Stock -->
                <div class="price-section">
                    <?= formatRupiah($book['harga']) ?>
                </div>

                <?php
                $stok_class = ($book['stok'] > 10) ? 'tersedia' : (($book['stok'] > 0) ? 'terbatas' : 'habis');
                $stok_label = ($book['stok'] > 10) ? 'Tersedia' : (($book['stok'] > 0) ? 'Terbatas' : 'Stok Habis');
                ?>
                <div class="stok-badge <?= $stok_class ?>">
                    <i class="fas fa-<?= ($book['stok'] > 0) ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?php if ($book['stok'] > 0): ?>
                        <?= $stok_label ?> (<?= $book['stok'] ?> stok)
                    <?php else: ?>
                        <?= $stok_label ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Synopsis -->
        <?php if (!empty($book['sinopsis'])): ?>
        <div class="synopsis-section">
            <div class="synopsis-title">Sinopsis</div>
            <div class="synopsis-text"><?= nl2br(htmlspecialchars($book['sinopsis'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- Reviews -->
        <div class="reviews-section">
            <div class="reviews-title">Ulasan Pembaca</div>

            <?php if (empty($reviews)): ?>
                <div class="no-reviews">
                    <i class="fas fa-comments" style="font-size:2rem; color:var(--gray-200); margin-bottom:1rem; display:block;"></i>
                    <p>Belum ada ulasan untuk buku ini</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <div>
                            <div class="review-user"><?= htmlspecialchars($review['nama_depan'] . ' ' . $review['nama_belakang']) ?></div>
                            <div class="review-date"><?= date('d M Y', strtotime($review['created_at'])) ?></div>
                        </div>
                        <div class="review-stars"><?= starHtml($review['rating']) ?></div>
                    </div>
                    <div class="review-text"><?= nl2br(htmlspecialchars($review['komentar'])) ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Toast -->
<div id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg">Berhasil!</span>
</div>

<style>
    @media (min-width: 600px) {
        .navbar-inner .logo-text { display: inline !important; }
    }
</style>

<script>
function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    t.style.background = ok ? '#1db87d' : '#e03c3c';
    t.style.transform = 'translateY(0)'; t.style.opacity = '1';
    setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 2800);
}

function tambahKeranjang(idBuku) {
    <?php if (!$user_id): ?>
        window.location.href = '../auth/login.php'; return;
    <?php endif; ?>
    
    const btn = event.target.closest('.btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    
    fetch('../api/keranjang/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty: 1 })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Buku ditambahkan ke keranjang!');
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Ditambahkan';
                btn.disabled = true;
            }, 500);
        } else {
            showToast(d.message || 'Gagal menambahkan.', false);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-cart-plus"></i> Tambah ke Keranjang';
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan.', false);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cart-plus"></i> Tambah ke Keranjang';
    });
}

function tambahWishlist(idBuku) {
    <?php if (!$user_id): ?>
        window.location.href = '../auth/login.php'; return;
    <?php endif; ?>
    
    const btn = event.target.closest('.btn-secondary');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    
    fetch('../api/wishlist/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Disimpan ke wishlist!');
            btn.innerHTML = '<i class="fas fa-heart"></i> Tersimpan';
            btn.disabled = true;
        } else {
            showToast(d.message || 'Gagal.', false);
            btn.disabled = false;
            btn.innerHTML = '<i class="far fa-heart"></i> Simpan ke Wishlist';
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan.', false);
        btn.disabled = false;
        btn.innerHTML = '<i class="far fa-heart"></i> Simpan ke Wishlist';
    });
}
</script>

</body>
</html>
