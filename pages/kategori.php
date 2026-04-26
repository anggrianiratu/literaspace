<?php
// ========================================
// KATALOG.PHP - LITERASPACE
// Halaman Katalog Buku + Search + Filter
// ========================================

session_start();
require_once __DIR__ . '/../config/db.php';

$pdo            = getDB();
$user_id        = $_SESSION['user_id'] ?? null;
$cart_count     = 0;
$wishlist_count = 0;
$categories     = [];
$books          = [];
$total_books    = 0;
$error          = null;

$search      = trim($_GET['q']          ?? '');
$id_kategori = (int)($_GET['kategori']  ?? 0);
$sort        = $_GET['sort']            ?? 'terbaru';
$min_harga   = (int)($_GET['min_harga'] ?? 0);
$max_harga   = (int)($_GET['max_harga'] ?? 0);
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 12;
$offset      = ($page - 1) * $per_page;

$sort_map = [
    'terbaru'    => 'b.id_buku DESC',
    'harga_asc'  => 'b.harga ASC',
    'harga_desc' => 'b.harga DESC',
    'rating'     => 'avg_rating DESC',
    'az'         => 'b.judul ASC',
];
$order_by = $sort_map[$sort] ?? 'b.id_buku DESC';

try {
    $categories = $pdo->query("SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori")->fetchAll(PDO::FETCH_ASSOC);

    $where  = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(b.judul LIKE ? OR b.penulis LIKE ?)';
        $params[] = "%$search%"; $params[] = "%$search%";
    }
    if ($id_kategori > 0) { $where[] = 'b.id_kategori = ?'; $params[] = $id_kategori; }
    if ($min_harga > 0)   { $where[] = 'b.harga >= ?';      $params[] = $min_harga; }
    if ($max_harga > 0)   { $where[] = 'b.harga <= ?';      $params[] = $max_harga; }

    $where_sql   = implode(' AND ', $where);
    $stmt_count  = $pdo->prepare("SELECT COUNT(*) FROM buku b WHERE $where_sql");
    $stmt_count->execute($params);
    $total_books = (int)$stmt_count->fetchColumn();
    $total_pages = max(1, ceil($total_books / $per_page));

    $sql = "
        SELECT b.id_buku, b.judul, b.penulis, b.harga, b.cover_image, b.stok,
               k.nama_kategori,
               COALESCE(ROUND(AVG(r.rating),1),0) AS avg_rating,
               COALESCE(COUNT(r.id_review),0)     AS review_count
        FROM buku b
        LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
        LEFT JOIN review   r ON b.id_buku = r.id_buku
        WHERE $where_sql
        GROUP BY b.id_buku,b.judul,b.penulis,b.harga,b.cover_image,b.stok,k.nama_kategori
        ORDER BY $order_by
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($user_id) {
        $sc = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?"); $sc->execute([$user_id]); $cart_count = (int)$sc->fetchColumn();
        $sw = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ?");  $sw->execute([$user_id]); $wishlist_count = (int)$sw->fetchColumn();
    }
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

function buildUrl($overrides = []) {
    $params = array_merge([
        'q'         => $_GET['q']         ?? '',
        'kategori'  => $_GET['kategori']  ?? '',
        'sort'      => $_GET['sort']      ?? 'terbaru',
        'min_harga' => $_GET['min_harga'] ?? '',
        'max_harga' => $_GET['max_harga'] ?? '',
        'page'      => $_GET['page']      ?? 1,
    ], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== '0');
    return 'katalog.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Katalog Buku — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* ── Design tokens (sesuai login.php) ── */
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

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem; color: var(--gray-800);
        }
        .page-subtitle { font-size: .85rem; color: var(--gray-500); margin-top: .2rem; }

        /* ── Sidebar ── */
        .sidebar {
            width: 240px; flex-shrink: 0;
            background: var(--white);
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.2rem;
            position: sticky; top: 90px;
            align-self: flex-start;
            box-shadow: var(--shadow);
        }

        .sidebar-title {
            font-size: .92rem; font-weight: 600; color: var(--gray-800);
            display: flex; align-items: center; gap: .4rem; margin-bottom: 1rem;
        }
        .sidebar-title i { color: var(--indigo-light); }

        .reset-link { font-size: .75rem; color: var(--error); text-decoration: none; }
        .reset-link:hover { text-decoration: underline; }

        .filter-section-label {
            font-size: .78rem; font-weight: 600; color: var(--gray-800);
            letter-spacing: .04em; text-transform: uppercase; margin-bottom: .6rem;
        }

        .filter-radio {
            display: flex; align-items: center; gap: .5rem;
            cursor: pointer; padding: .25rem 0;
        }
        .filter-radio input[type="radio"] { accent-color: var(--indigo-light); }
        .filter-radio span { font-size: .84rem; color: var(--gray-500); transition: color .15s; }
        .filter-radio:hover span { color: var(--indigo-deep); }

        .btn-filter {
            width: 100%; padding: .65rem;
            background: var(--indigo-deep); color: var(--white);
            border: none; border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 600;
            cursor: pointer; transition: background .2s, transform .1s;
            margin-top: .5rem;
        }
        .btn-filter:hover  { background: var(--indigo-light); }
        .btn-filter:active { transform: scale(.98); }

        /* ── Sort toolbar ── */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.2rem;
        }
        .sort-label { font-size: .85rem; color: var(--gray-500); }
        .sort-select {
            font-size: .85rem;
            border: 1.5px solid var(--gray-200); border-radius: 8px;
            padding: .45rem 2rem .45rem .75rem;
            outline: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif; color: var(--gray-800);
            background: var(--white);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b6b8a' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right .5rem center; background-size: 16px;
            transition: border-color .2s, box-shadow .2s;
        }
        .sort-select:focus { border-color: var(--indigo-light); box-shadow: 0 0 0 3px rgba(59,46,192,.10); }

        /* ── Book card ── */
        .book-card {
            background: var(--white);
            border-radius: 12px;
            border: 1.5px solid var(--gray-200);
            overflow: hidden;
            transition: transform .3s, box-shadow .3s;
        }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 16px 40px rgba(30,22,103,.12); }

        .cover-wrap { position: relative; }
        .cover-placeholder {
            width: 100%; aspect-ratio: 3/4;
            display: flex; align-items: center; justify-content: center;
        }
        .cover-placeholder svg { width: 36px; height: 36px; fill: rgba(255,255,255,.4); }

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
            margin-bottom: .15rem; transition: color .2s; text-decoration: none; display: block;
        }
        .book-title:hover { color: var(--indigo-light); }
        .book-author { font-size: .74rem; color: var(--gray-500); margin-bottom: .5rem; }

        .star-row { display: flex; align-items: center; gap: .3rem; margin-bottom: .5rem; }
        .star-count { font-size: .72rem; color: var(--gray-500); }

        .book-price { font-size: .9rem; font-weight: 700; color: var(--indigo-deep); }

        .btn-cart {
            width: 32px; height: 32px;
            background: var(--indigo-deep); color: var(--white);
            border: none; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: .75rem;
            transition: background .2s;
        }
        .btn-cart:hover    { background: var(--indigo-light); }
        .btn-cart:disabled { background: var(--gray-200); color: var(--gray-500); cursor: not-allowed; }

        /* Empty state */
        .empty-state {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--gray-200);
            padding: 5rem 2rem;
            text-align: center;
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

        /* ── Pagination ── */
        .pagination { display: flex; gap: .3rem; justify-content: center; align-items: center; margin-top: 2rem; }
        .page-btn {
            padding: .45rem .75rem;
            font-size: .85rem; font-family: 'DM Sans', sans-serif;
            border: 1.5px solid var(--gray-200); border-radius: 8px;
            color: var(--gray-800); text-decoration: none; background: var(--white);
            transition: all .2s;
        }
        .page-btn:hover        { background: rgba(30,22,103,.06); border-color: rgba(30,22,103,.25); color: var(--indigo-deep); }
        .page-btn.active       { background: var(--indigo-deep); border-color: var(--indigo-deep); color: var(--white); }
        .page-btn.active:hover { background: var(--indigo-light); border-color: var(--indigo-light); }
        .page-ellipsis { padding: .45rem .4rem; color: var(--gray-500); font-size: .85rem; }

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

        /* Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
            gap: .9rem;
        }

        /* Layout main */
        .content-layout { display: flex; gap: 1.5rem; align-items: flex-start; }

        @media (max-width: 760px) {
            .sidebar { width: 100%; position: static; }
            .content-layout { flex-direction: column; }
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
                <form action="katalog.php" method="GET">
                    <?php if ($id_kategori): ?><input type="hidden" name="kategori" value="<?= $id_kategori ?>"><?php endif; ?>
                    <?php if ($sort !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
                    <input type="search" name="q" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Cari judul, penulis, atau kategori..."
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
                <a href="/literaspace/pages/wishlist.php" class="nav-icon" style="color:var(--gray-500);" onmouseover="this.style.color='#e03c3c'" onmouseout="this.style.color='var(--gray-500)'">
                    <i class="far fa-heart"></i>
                    <?php if ($wishlist_count > 0): ?><span class="nav-badge"><?= min($wishlist_count,99) ?></span><?php endif; ?>
                </a>

                <?php if ($user_id): ?>
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
                <?php else: ?>
                    <a href="/literaspace/auth/logout.php" class="btn-auth">Masuk / Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ════════════════════════════════
     PAGE CONTENT
════════════════════════════════ -->
<main class="page-inner">

    <?php if ($error): ?>
        <div style="background:#fdecea; border:1.5px solid #f5c6c6; color:var(--error); border-radius:8px; padding:.75rem 1rem; margin-bottom:1.2rem; font-size:.88rem;">
            <i class="fas fa-exclamation-circle" style="margin-right:.4rem;"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Page title -->
    <div style="margin-bottom:1.4rem;">
        <h1 class="page-title">
            <?php if ($search): ?>
                Hasil pencarian untuk "<span style="color:var(--indigo-light);"><?= htmlspecialchars($search) ?></span>"
            <?php elseif ($id_kategori): ?>
                <?php
                $cat_name = '';
                foreach ($categories as $c) {
                    if ((int)$c['id_kategori'] === $id_kategori) { $cat_name = $c['nama_kategori']; break; }
                }
                ?>
                Kategori: <span style="color:var(--indigo-light);"><?= htmlspecialchars($cat_name) ?></span>
            <?php else: ?>
                Katalog Buku
            <?php endif; ?>
        </h1>
        <p class="page-subtitle">
            Menampilkan <?= number_format($total_books) ?> buku<?php if ($total_books > 0): ?> — halaman <?= $page ?> dari <?= $total_pages ?><?php endif; ?>
        </p>
    </div>

    <div class="content-layout">

        <!-- ── SIDEBAR FILTER ── -->
        <aside class="sidebar">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.9rem;">
                <span class="sidebar-title">
                    <i class="fas fa-filter"></i> Filter
                </span>
                <?php if ($search || $id_kategori || $min_harga || $max_harga): ?>
                    <a href="katalog.php" class="reset-link">Reset semua</a>
                <?php endif; ?>
            </div>

            <form action="katalog.php" method="GET" id="filter-form">
                <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <?php if ($sort !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>

                <!-- Genre -->
                <div style="margin-bottom:1.1rem;">
                    <p class="filter-section-label">Genre</p>
                    <div style="display:flex; flex-direction:column; max-height:180px; overflow-y:auto; padding-right:.2rem;">
                        <?php foreach ($categories as $cat): ?>
                            <label class="filter-radio">
                                <input type="radio" name="kategori" value="<?= $cat['id_kategori'] ?>"
                                       <?= $id_kategori === (int)$cat['id_kategori'] ? 'checked' : '' ?>
                                       onchange="this.form.submit()" />
                                <span><?= htmlspecialchars($cat['nama_kategori']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Rentang Harga -->
                <div style="margin-bottom:1rem;">
                    <p class="filter-section-label">Rentang Harga</p>
                    <?php
                    $price_ranges = [
                        ['label' => '&lt; Rp 75.000',           'min' => 0,      'max' => 75000],
                        ['label' => 'Rp 75.000 – Rp 100.000',  'min' => 75000,  'max' => 100000],
                        ['label' => 'Rp 100.000 – Rp 150.000', 'min' => 100000, 'max' => 150000],
                        ['label' => '&gt; Rp 150.000',          'min' => 150000, 'max' => 0],
                    ];
                    foreach ($price_ranges as $pr):
                        $checked = ($min_harga === $pr['min'] && $max_harga === $pr['max']);
                    ?>
                        <label class="filter-radio">
                            <input type="radio" name="price_range"
                                   data-min="<?= $pr['min'] ?>" data-max="<?= $pr['max'] ?>"
                                   <?= $checked ? 'checked' : '' ?>
                                   class="price-radio" />
                            <span><?= $pr['label'] ?></span>
                        </label>
                    <?php endforeach; ?>
                    <input type="hidden" name="min_harga" id="min_harga" value="<?= $min_harga ?>">
                    <input type="hidden" name="max_harga" id="max_harga" value="<?= $max_harga ?>">
                </div>

                <button type="submit" class="btn-filter">Terapkan Filter</button>
            </form>
        </aside>

        <!-- ── MAIN CONTENT ── -->
        <section style="flex:1; min-width:0;">

            <!-- Toolbar -->
            <div class="toolbar">
                <p class="sort-label">
                    <span style="font-weight:600; color:var(--gray-800);"><?= number_format($total_books) ?></span> buku ditemukan
                </p>
                <div style="display:flex; align-items:center; gap:.5rem;">
                    <label class="sort-label">Urutkan:</label>
                    <select class="sort-select" onchange="window.location=this.value">
                        <?php
                        $sort_opts = [
                            'terbaru'    => 'Terbaru',
                            'harga_asc'  => 'Harga: Rendah → Tinggi',
                            'harga_desc' => 'Harga: Tinggi → Rendah',
                            'rating'     => 'Rating Terbaik',
                            'az'         => 'A–Z',
                        ];
                        foreach ($sort_opts as $val => $label): ?>
                            <option value="<?= buildUrl(['sort' => $val, 'page' => 1]) ?>"
                                    <?= $sort === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Grid buku -->
            <?php if (empty($books)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open empty-icon"></i>
                    <p class="empty-title">Tidak ada buku yang ditemukan</p>
                    <p class="empty-sub">Coba ubah kata kunci atau filter pencarian</p>
                    <a href="katalog.php" class="btn-empty">Lihat Semua Buku</a>
                </div>
            <?php else: ?>
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
                <div class="books-grid">
                    <?php foreach ($books as $book):
                        $ci = $book['id_buku'] % count($cover_gradients);
                    ?>
                    <div class="book-card">
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
                            <a href="detail.php?id=<?= $book['id_buku'] ?>" class="book-title"
                               style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:.15rem;">
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

                            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:.2rem;">
                                <span class="book-price"><?= formatRupiah($book['harga']) ?></span>
                                <?php if ((int)$book['stok'] > 0): ?>
                                    <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)" title="Tambah ke keranjang">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn-cart" disabled title="Stok habis">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= buildUrl(['page' => $page - 1]) ?>" class="page-btn">
                                <i class="fas fa-chevron-left" style="font-size:.75rem;"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
                        if ($start > 1): ?>
                            <a href="<?= buildUrl(['page' => 1]) ?>" class="page-btn">1</a>
                            <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="<?= buildUrl(['page' => $i]) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
                            <a href="<?= buildUrl(['page' => $total_pages]) ?>" class="page-btn"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= buildUrl(['page' => $page + 1]) ?>" class="page-btn">
                                <i class="fas fa-chevron-right" style="font-size:.75rem;"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Toast -->
<div id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg">Buku ditambahkan ke keranjang!</span>
</div>

<style>
    @media (min-width: 600px) {
        #logo-text-desktop { display: inline !important; }
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

function tambahKeranjang(idBuku, btn) {
    <?php if (!$user_id): ?>
        window.location.href = '/auth/login.php'; return;
    <?php endif; ?>
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.75rem;"></i>';
    fetch('/api/keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty: 1 })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Buku ditambahkan ke keranjang!');
            btn.innerHTML = '<i class="fas fa-check" style="font-size:.75rem;"></i>';
            setTimeout(() => { btn.innerHTML = '<i class="fas fa-cart-plus"></i>'; btn.disabled = false; }, 2000);
        } else {
            showToast(d.message || 'Gagal menambahkan.', false);
            btn.innerHTML = '<i class="fas fa-cart-plus"></i>'; btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan.', false);
        btn.innerHTML = '<i class="fas fa-cart-plus"></i>'; btn.disabled = false;
    });
}

document.querySelectorAll('.price-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('min_harga').value = this.dataset.min;
        document.getElementById('max_harga').value = this.dataset.max;
    });
});
</script>
</body>
</html>