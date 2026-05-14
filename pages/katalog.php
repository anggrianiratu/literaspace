<?php
// ========================================
// KATALOG.PHP - LITERASPACE
// Halaman Katalog Buku + Search + Filter
// Tema: Pastel Painterly (selaras index.php)
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

        $sw2 = $pdo->prepare("SELECT id_buku FROM wishlist WHERE id_user = ?");
        $sw2->execute([$user_id]);
        $wishlisted_ids = $sw2->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $wishlisted_ids = [];
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
    $wishlisted_ids = [];
}

function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

function starHtml($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= round($rating)
            ? '<i class="fas fa-star" style="color:#c4882a;font-size:.65rem;"></i>'
            : '<i class="far fa-star" style="color:#d4c2e0;font-size:.65rem;"></i>';
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

$wishlisted_json = json_encode(array_map('intval', $wishlisted_ids));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Katalog Buku — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --plum:       #4a2c5e;
            --plum-mid:   #6b3f82;
            --plum-light: #9b6bb5;
            --blush:      #e8c5d0;
            --cream:      #fdf8f3;
            --parchment:  #f5ede0;
            --ink:        #2a1a35;
            --muted:      #7a6585;
            --amber:      #c4882a;
            --white:      #ffffff;
            --error:      #c0403a;
            --success:    #2a8a5e;
            --radius-lg:  20px;
            --radius-sm:  10px;
            --shadow:     0 4px 24px rgba(74,44,94,.10);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ══ PAINTERLY PASTEL BACKGROUND ══ */
        body {
            font-family: 'Jost', sans-serif;
            color: var(--ink);
            background-color: #f2ebe8;
            position: relative;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none; opacity: .38;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='600' height='600'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.72' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='600' height='600' filter='url(%23n)' opacity='0.12'/%3E%3C/svg%3E");
            background-size: 300px 300px;
        }

        body::after {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 70% 45% at -5% 5%,  rgba(232,180,185,.55) 0%, transparent 65%),
                radial-gradient(ellipse 55% 40% at 105% 2%, rgba(200,180,220,.5)  0%, transparent 60%),
                radial-gradient(ellipse 60% 35% at 15% 45%, rgba(255,245,235,.7)  0%, transparent 65%),
                radial-gradient(ellipse 50% 30% at 85% 40%, rgba(185,210,225,.45) 0%, transparent 60%),
                radial-gradient(ellipse 65% 40% at 5%  95%, rgba(240,200,185,.5)  0%, transparent 65%),
                radial-gradient(ellipse 55% 38% at 95% 92%, rgba(220,175,185,.45) 0%, transparent 58%),
                radial-gradient(ellipse 80% 50% at 50% 55%, rgba(255,250,248,.6)  0%, transparent 70%),
                linear-gradient(170deg, #f0e6e1 0%, #ede4ec 30%, #e8ecf2 60%, #eee5e0 100%);
        }

        .navbar, main, footer, #toast {
            position: relative;
            z-index: 1;
        }

        /* ══ NAVBAR ══ */
        .navbar {
            position: sticky; top: 0; z-index: 50;
            background: rgba(253,248,243,.92);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 2px 20px rgba(74,44,94,.09), 0 1px 0 rgba(232,197,208,.4);
            border-bottom: 1px solid rgba(232,197,208,.3);
        }
        .navbar-inner {
            max-width: 1280px; margin: 0 auto; padding: 0 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
            height: 68px; gap: 1rem;
        }
        .logo-link { display: flex; align-items: center; gap: .7rem; text-decoration: none; flex-shrink: 0; }
        .logo-svg-nav { width: 38px; height: 38px; transition: transform .2s; }
        .logo-link:hover .logo-svg-nav { transform: scale(1.07); }
        .logo-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.25rem; font-weight: 600;
            color: var(--ink); letter-spacing: .04em;
        }
        .logo-name span { color: var(--plum-mid); font-style: italic; }

        .search-wrap { flex: 1; max-width: 400px; position: relative; }
        .search-input {
            width: 100%; padding: .6rem 2.4rem .6rem 1rem;
            background: var(--cream); border: 1.5px solid #6f5b7c;
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .9rem; color: var(--ink);
            outline: none; transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .search-input::placeholder { color: #7a6585; }
        .search-input:focus {
            border-color: var(--plum-light); background: var(--white);
            box-shadow: 0 0 0 3.5px rgba(107,63,130,.1);
        }
        .search-btn {
            position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color:  #5c466b;
            font-size: .85rem; transition: color .2s;
        }
        .search-btn:hover { color: var(--plum-mid); }

        .nav-icon {
            color: var(--muted); font-size: 1.15rem;
            text-decoration: none; position: relative; transition: color .2s;
        }
        .nav-icon:hover { color: var(--plum-mid); }
        .nav-badge {
            position: absolute; top: -7px; right: -7px;
            background: var(--error); color: var(--white);
            font-size: .62rem; font-weight: 700;
            width: 17px; height: 17px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-auth {
            padding: .42rem 1.1rem;
            background: var(--plum); color: var(--white);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .86rem; font-weight: 500;
            letter-spacing: .06em; cursor: pointer; text-decoration: none;
            transition: background .2s; white-space: nowrap;
        }
        .btn-auth:hover { background: var(--plum-mid); }

        .dropdown-wrap { position: relative; }
        .dropdown-menu {
            position: absolute; right: 0; top: calc(100% + 8px);
            width: 175px; background: var(--white); border-radius: var(--radius-sm);
            box-shadow: 0 8px 32px rgba(74,44,94,.18), 0 2px 8px rgba(74,44,94,.08);
            border: 1.5px solid rgba(232,197,208,.5);
            opacity: 0; visibility: hidden; transition: opacity .2s, visibility .2s; z-index: 100;
        }
        .dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a {
            display: block; padding: .62rem 1rem;
            font-size: .86rem; color: var(--ink);
            text-decoration: none; transition: background .15s, color .15s;
        }
        .dropdown-menu a:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-menu a:last-child  { border-radius: 0 0 8px 8px; color: var(--error); }
        .dropdown-menu a:hover { background: rgba(74,44,94,.05); color: var(--plum-mid); }
        .dropdown-menu a:last-child:hover { background: #fdf2f2; color: var(--error); }
        .dropdown-menu hr { border-color: rgba(232,197,208,.5); margin: .25rem 0; }

        /* ══ PAGE HEADER ══ */
        .page-header {
            background: var(--plum);
            position: relative; overflow: hidden;
            padding: 2.8rem 1.5rem 3rem;
        }
        .page-header::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 60% 80% at 80% 50%, rgba(107,63,130,.6) 0%, transparent 70%),
                radial-gradient(ellipse 40% 60% at 10% 90%, rgba(196,136,42,.15) 0%, transparent 60%);
            pointer-events: none;
        }
        .page-header-bg {
            position: absolute; inset: 0; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Ccircle cx='30' cy='50' r='18' fill='none' stroke='%23ffffff' stroke-opacity='0.04' stroke-width='1'/%3E%3Ccircle cx='150' cy='30' r='25' fill='none' stroke='%23ffffff' stroke-opacity='0.03' stroke-width='1'/%3E%3Ccircle cx='170' cy='150' r='35' fill='none' stroke='%23ffffff' stroke-opacity='0.03' stroke-width='1'/%3E%3Ccircle cx='40' cy='170' r='20' fill='none' stroke='%23ffffff' stroke-opacity='0.04' stroke-width='1'/%3E%3C/svg%3E");
            background-size: 200px 200px;
        }
        .page-header-inner {
            max-width: 1280px; margin: 0 auto;
            position: relative; z-index: 2;
        }
        .page-eyebrow {
            font-size: .7rem; font-weight: 500; letter-spacing: .22em;
            text-transform: uppercase; color: #9b6bb5;
            margin-bottom: .55rem; display: block;
        }
        .page-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(1.6rem, 3.5vw, 2.4rem);
            font-weight: 600; color: #2b2230; line-height: 1.15;
            margin-bottom: .4rem;
        }
        .page-title em { font-style: italic; color: #4a2c5e; }
        .page-subtitle {
            font-size: .88rem; color: #6f5b7c;;
        }

        /* ══ MAIN LAYOUT ══ */
        .page-inner { max-width: 1280px; margin: 0 auto; padding: 2rem 1.5rem 3rem; }
        .content-layout { display: flex; gap: 1.6rem; align-items: flex-start; }

        /* ══ SIDEBAR ══ */
        .sidebar {
            width: 232px; flex-shrink: 0;
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border: 1.5px solid #6f5b7c;
            border-radius: var(--radius-lg);
            padding: 1.3rem;
            position: sticky; top: 86px;
            align-self: flex-start;
            box-shadow: var(--shadow);
        }

        .sidebar-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem; font-weight: 600; color: var(--ink);
            display: flex; align-items: center; gap: .45rem; margin-bottom: 1rem;
        }
        .sidebar-title i { color: var(--plum-light); font-size: .9rem; }

        .reset-link {
            font-size: .74rem; color: var(--error); text-decoration: none;
            font-family: 'Jost', sans-serif; font-weight: 500;
        }
        .reset-link:hover { text-decoration: underline; }

        .filter-section-label {
            font-size: .68rem; font-weight: 600; color: var(--muted);
            letter-spacing: .18em; text-transform: uppercase; margin-bottom: .7rem;
        }

        .filter-radio {
            display: flex; align-items: center; gap: .55rem;
            cursor: pointer; padding: .3rem 0;
        }
        .filter-radio input[type="radio"] { accent-color: var(--plum-mid); }
        .filter-radio span {
            font-size: .84rem; color: var(--muted);
            transition: color .15s; font-family: 'Jost', sans-serif;
        }
        .filter-radio:hover span { color: var(--plum); }
        .filter-radio input:checked + span { color: var(--plum); font-weight: 500; }

        .filter-divider {
            border: none; height: 1px; margin: 1rem 0;
            background: linear-gradient(90deg, transparent, rgba(155,107,181,.2), transparent);
        }

        .btn-filter {
            width: 100%; padding: .68rem;
            background: var(--plum); color: var(--white);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .88rem; font-weight: 500;
            letter-spacing: .06em; cursor: pointer;
            transition: background .2s, transform .1s;
            margin-top: .5rem;
        }
        .btn-filter:hover  { background: var(--plum-mid); }
        .btn-filter:active { transform: scale(.98); }

        /* ══ TOOLBAR ══ */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.3rem; gap: 1rem; flex-wrap: wrap;
        }
        .toolbar-count {
            font-size: .88rem; color: var(--muted);
            font-family: 'Jost', sans-serif;
        }
        .toolbar-count strong { color: var(--ink); font-weight: 600; }

        .sort-label {
            font-size: .84rem; color: var(--muted);
            font-family: 'Jost', sans-serif;
        }
        .sort-select {
            font-size: .84rem;
            border: 1.5px solid #6f5b7c; border-radius: var(--radius-sm);
            padding: .45rem 2.2rem .45rem .8rem;
            outline: none; cursor: pointer;
            font-family: 'Jost', sans-serif; color: var(--ink);
            background: rgba(255,255,255,.85);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237a6585' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right .5rem center; background-size: 15px;
            transition: border-color .2s, box-shadow .2s;
        }
        .sort-select:focus {
            border-color: var(--plum-light);
            box-shadow: 0 0 0 3px rgba(107,63,130,.1);
        }

        /* ══ BOOK CARD ══ */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
            gap: 1rem;
        }

        .book-card {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-sm);
            border: 1.5px solid #7a6585;
            overflow: hidden;
            transition: transform .35s cubic-bezier(.34,1.56,.64,1), box-shadow .3s;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .book-card:hover {
            transform: translateY(-7px) scale(1.022);
            box-shadow: 0 20px 48px rgba(74,44,94,.16);
        }

        .cover-wrap { position: relative; width: 100%; overflow: hidden; }
        .cover-wrap img { transition: transform .5s ease; display: block; }
        .book-card:hover .cover-wrap img { transform: scale(1.05); }

        .cover-placeholder {
            width: 100%; aspect-ratio: 3/4;
            display: flex; align-items: center; justify-content: center;
        }
        .cover-placeholder svg { width: 32px; height: 32px; fill: rgba(255,255,255,.35); }

        .cat-badge {
            position: absolute; top: 6px; left: 6px;
            background: rgba(253,248,243,.92); color: var(--plum);
            font-size: .63rem; font-weight: 600; padding: .15rem .55rem;
            border-radius: 9999px; letter-spacing: .06em;
            font-family: 'Jost', sans-serif;
        }

        .sold-out-overlay {
            position: absolute; inset: 0;
            background: rgba(42,26,53,.55);
            display: flex; align-items: center; justify-content: center;
        }
        .sold-out-badge {
            background: var(--error); color: var(--white);
            font-size: .7rem; font-weight: 600; font-family: 'Jost', sans-serif;
            padding: .25rem .85rem; border-radius: 9999px;
            letter-spacing: .06em;
        }

        .book-info { 
            padding: .8rem .75rem; 
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .book-title-link {
            font-size: .84rem; font-weight: 500; color: var(--ink);
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; margin-bottom: .15rem;
            text-decoration: none; transition: color .2s;
            font-family: 'Jost', sans-serif; line-height: 1.4; min-height: 2.4rem;
        }
        .book-title-link:hover { color: var(--plum-mid); }

        .book-author {
            font-size: .73rem; color: var(--muted);
            margin-bottom: .5rem; font-family: 'Jost', sans-serif; min-height: 1rem;
        }

        .star-row { display: flex; align-items: center; gap: .3rem; margin-bottom: .55rem; }
        .star-count { font-size: .7rem; color: var(--muted); font-family: 'Jost', sans-serif; }

        .book-price {
            font-size: .9rem; font-weight: 700; color: var(--plum);
            font-family: 'Jost', sans-serif; white-space: nowrap;
        }

        .btn-cart {
            background: none; border: none; cursor: pointer;
            font-size: .95rem; color: var(--muted); padding: 0;
            transition: color .2s, transform .2s;
            display: flex; align-items: center; justify-content: center;
            width: 30px; height: 30px;
        }
        .btn-cart:hover { color: var(--plum-mid); transform: scale(1.2); }
        .btn-cart:disabled { color: rgba(212,194,224,.5); cursor: not-allowed; transform: none; }

        .btn-wish {
            background: none; border: none; cursor: pointer;
            font-size: .9rem; color: var(--muted); padding: 0;
            transition: color .2s, transform .2s;
            display: flex; align-items: center; justify-content: center;
            width: 28px; height: 28px;
        }
        .btn-wish:hover { color: var(--error); transform: scale(1.2); }
        .btn-wish.wishlisted { color: var(--error); }

        /* ══ EMPTY STATE ══ */
        .empty-state {
            background: rgba(255,255,255,.75);
            border-radius: var(--radius-lg);
            border: 1.5px solid rgba(232,197,208,.35);
            padding: 5rem 2rem; text-align: center;
        }
        .empty-state i {
            font-size: 2.5rem; color: #d4c2e0;
            display: block; margin-bottom: 1rem;
        }
        .empty-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.2rem; color: var(--muted); font-weight: 600;
        }
        .empty-sub {
            font-size: .85rem; color: var(--muted); margin-top: .35rem;
        }
        .btn-empty {
            display: inline-block; margin-top: 1.3rem;
            padding: .65rem 1.5rem;
            background: var(--plum); color: var(--white);
            border-radius: var(--radius-sm); text-decoration: none;
            font-size: .88rem; font-weight: 500; letter-spacing: .06em;
            font-family: 'Jost', sans-serif;
            transition: background .2s;
        }
        .btn-empty:hover { background: var(--plum-mid); }

        /* ══ PAGINATION ══ */
        .pagination {
            display: flex; gap: .35rem; justify-content: center;
            align-items: center; margin-top: 2.2rem; flex-wrap: wrap;
        }
        .page-btn {
            padding: .48rem .82rem;
            font-size: .84rem; font-family: 'Jost', sans-serif;
            border: 1.5px solid #6f5b7c; border-radius: var(--radius-sm);
            color: var(--ink); text-decoration: none;
            background: rgba(255,255,255,.8);
            transition: all .2s;
        }
        .page-btn:hover {
            background: rgba(74,44,94,.06);
            border-color: rgba(107,63,130,.3);
            color: var(--plum);
        }
        .page-btn.active {
            background: var(--plum); border-color: var(--plum); color: var(--white);
        }
        .page-btn.active:hover { background: var(--plum-mid); border-color: var(--plum-mid); }
        .page-ellipsis {
            padding: .48rem .35rem; color: var(--muted); font-size: .84rem;
        }

        /* ══ TOAST ══ */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999;
            padding: .7rem 1.1rem; border-radius: var(--radius-sm);
            color: var(--white); font-size: .87rem; font-family: 'Jost', sans-serif;
            display: flex; align-items: center; gap: .5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            transform: translateY(80px); opacity: 0;
            transition: all .3s; pointer-events: none;
        }

        /* ══ ERROR BANNER ══ */
        .error-banner {
            background: rgba(255,255,255,.75); border: 1.5px solid rgba(192,64,58,.2);
            color: var(--error); border-radius: var(--radius-sm);
            padding: .75rem 1rem; margin-bottom: 1.3rem;
            font-size: .88rem; border-left: 3px solid var(--error);
            font-family: 'Jost', sans-serif;
        }

        /* ══ ACTIVE FILTERS PILLS ══ */
        .active-filters {
            display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem;
        }
        .filter-pill {
            display: inline-flex; align-items: center; gap: .35rem;
            background: rgba(74,44,94,.08);
            border: 1px solid rgba(107,63,130,.2);
            border-radius: 9999px;
            padding: .2rem .65rem;
            font-size: .75rem; color: var(--plum);
            font-family: 'Jost', sans-serif; font-weight: 500;
        }
        .filter-pill a {
            color: var(--muted); text-decoration: none;
            font-size: .7rem; transition: color .15s;
        }
        .filter-pill a:hover { color: var(--error); }

        /* ══ RESPONSIVE ══ */
        @media (max-width: 760px) {
            .sidebar { width: 100%; position: static; }
            .content-layout { flex-direction: column; }
            .page-header { padding: 2rem 1.25rem 2.5rem; }
        }
        @media (max-width: 540px) {
            #logo-name-text { display: none; }
        }
    </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="/literaspace/index.php" class="logo-link">
            <svg class="logo-svg-nav" viewBox="0 0 90 90" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="10" y="20" width="18" height="52" rx="2" fill="rgba(74,44,94,0.15)" stroke="rgba(74,44,94,0.5)" stroke-width="1.2"/>
                <line x1="14" y1="28" x2="24" y2="28" stroke="rgba(74,44,94,0.3)" stroke-width=".9"/>
                <line x1="14" y1="34" x2="24" y2="34" stroke="rgba(74,44,94,0.2)" stroke-width=".9"/>
                <ellipse cx="19" cy="44" rx="4" ry="5" fill="none" stroke="rgba(74,44,94,0.3)" stroke-width=".9"/>
                <line x1="14" y1="56" x2="24" y2="56" stroke="rgba(74,44,94,0.2)" stroke-width=".9"/>
                <line x1="14" y1="62" x2="24" y2="62" stroke="rgba(74,44,94,0.15)" stroke-width=".9"/>
                <rect x="29" y="12" width="22" height="60" rx="2" fill="rgba(74,44,94,0.18)" stroke="rgba(74,44,94,0.55)" stroke-width="1.2"/>
                <ellipse cx="40" cy="28" rx="6" ry="7" fill="none" stroke="rgba(74,44,94,0.4)" stroke-width="1"/>
                <ellipse cx="40" cy="28" rx="3" ry="3.5" fill="rgba(74,44,94,0.15)"/>
                <path d="M34 42 Q40 38 46 42 Q40 46 34 42Z" fill="rgba(74,44,94,0.2)"/>
                <line x1="34" y1="52" x2="46" y2="52" stroke="rgba(74,44,94,0.2)" stroke-width=".9"/>
                <line x1="34" y1="58" x2="46" y2="58" stroke="rgba(74,44,94,0.15)" stroke-width=".9"/>
                <rect x="52" y="22" width="17" height="50" rx="2" fill="rgba(74,44,94,0.12)" stroke="rgba(74,44,94,0.4)" stroke-width="1.2"/>
                <ellipse cx="60" cy="35" rx="4" ry="4" fill="none" stroke="rgba(74,44,94,0.3)" stroke-width=".9"/>
                <path d="M10 52 Q19 48 29 50 Q40 52 51 50 Q60 48 69 52" fill="none" stroke="rgba(196,136,42,0.7)" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="40" cy="50" r="2.5" fill="none" stroke="rgba(196,136,42,0.8)" stroke-width="1.2"/>
                <line x1="73" y1="68" x2="73" y2="28" stroke="rgba(181,201,176,0.7)" stroke-width="1.2"/>
                <path d="M73 28 Q75 22 79 20 Q75 26 73 28Z" fill="rgba(232,197,208,0.8)"/>
                <path d="M73 28 Q69 22 65 22 Q70 26 73 28Z" fill="rgba(232,197,208,0.7)"/>
                <circle cx="73" cy="28" r="3" fill="rgba(232,150,170,0.9)"/>
            </svg>
            <span class="logo-name" id="logo-name-text">Litera <span>Space</span></span>
        </a>

        <div class="search-wrap">
            <form action="katalog.php" method="GET">
                <?php if ($id_kategori): ?>
                    <input type="hidden" name="kategori" value="<?= $id_kategori ?>">
                <?php endif; ?>
                <?php if ($sort !== 'terbaru'): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Cari judul, penulis, atau kategori..."
                       class="search-input" />
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
            <a href="/literaspace/pages/keranjang.php" class="nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="nav-badge"><?= min($cart_count, 99) ?></span>
                <?php endif; ?>
            </a>
            <a href="/literaspace/pages/wishlist.php" class="nav-icon">
                <i class="far fa-heart"></i>
                <?php if ($wishlist_count > 0): ?>
                    <span class="nav-badge"><?= min($wishlist_count, 99) ?></span>
                <?php endif; ?>
            </a>

            <?php if ($user_id): ?>
                <div class="dropdown-wrap">
                    <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                        <i class="fas fa-user-circle" style="font-size:1.45rem;"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="/literaspace/pages/profile.php">
                            <i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya
                        </a>
                        <a href="/literaspace/pages/pesanan.php">
                            <i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya
                        </a>
                        <hr />
                        <a href="/literaspace/auth/logout.php">
                            <i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/literaspace/auth/login.php" class="btn-auth">Masuk / Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- ══ PAGE HEADER ══ -->
<div class="page-header">
    <div class="page-header-bg"></div>
    <div class="page-header-inner">
        <span class="page-eyebrow">✦ Koleksi Lengkap</span>
        <h1 class="page-title">
            <?php if ($search): ?>
                Hasil untuk "<em><?= htmlspecialchars($search) ?></em>"
            <?php elseif ($id_kategori): ?>
                <?php
                $cat_name = '';
                foreach ($categories as $c) {
                    if ((int)$c['id_kategori'] === $id_kategori) { $cat_name = $c['nama_kategori']; break; }
                }
                ?>
                Kategori: <em><?= htmlspecialchars($cat_name) ?></em>
            <?php else: ?>
                Katalog <em>Buku</em>
            <?php endif; ?>
        </h1>
        <p class="page-subtitle">
            Menampilkan <strong style="color: #4a2c5e;"><?= number_format($total_books) ?></strong> buku
            <?php if ($total_books > 0 && $total_pages > 1): ?>
                — halaman <?= $page ?> dari <?= $total_pages ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- ══ MAIN ══ -->
<main class="page-inner">

    <?php if ($error): ?>
        <div class="error-banner">
            <i class="fas fa-exclamation-circle" style="margin-right:.4rem;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Active filter pills -->
    <?php if ($search || $id_kategori || $min_harga || $max_harga): ?>
        <div class="active-filters">
            <?php if ($search): ?>
                <span class="filter-pill">
                    <i class="fas fa-search" style="font-size:.65rem;opacity:.6;"></i>
                    <?= htmlspecialchars($search) ?>
                    <a href="<?= buildUrl(['q' => '', 'page' => 1]) ?>">✕</a>
                </span>
            <?php endif; ?>
            <?php if ($id_kategori && $cat_name ?? false): ?>
                <span class="filter-pill">
                    <i class="fas fa-tag" style="font-size:.65rem;opacity:.6;"></i>
                    <?= htmlspecialchars($cat_name) ?>
                    <a href="<?= buildUrl(['kategori' => '', 'page' => 1]) ?>">✕</a>
                </span>
            <?php elseif ($id_kategori): ?>
                <span class="filter-pill">
                    <i class="fas fa-tag" style="font-size:.65rem;opacity:.6;"></i>
                    Kategori #<?= $id_kategori ?>
                    <a href="<?= buildUrl(['kategori' => '', 'page' => 1]) ?>">✕</a>
                </span>
            <?php endif; ?>
            <?php if ($min_harga || $max_harga): ?>
                <span class="filter-pill">
                    <i class="fas fa-wallet" style="font-size:.65rem;opacity:.6;"></i>
                    <?= $min_harga ? 'Rp '.number_format($min_harga,0,',','.') : '0' ?> –
                    <?= $max_harga ? 'Rp '.number_format($max_harga,0,',','.') : '∞' ?>
                    <a href="<?= buildUrl(['min_harga' => '', 'max_harga' => '', 'page' => 1]) ?>">✕</a>
                </span>
            <?php endif; ?>
            <a href="katalog.php" class="reset-link" style="display:inline-flex;align-items:center;gap:.3rem;font-size:.75rem;">
                <i class="fas fa-times" style="font-size:.65rem;"></i> Reset semua
            </a>
        </div>
    <?php endif; ?>

    <div class="content-layout">

        <!-- ══ SIDEBAR ══ -->
        <aside class="sidebar">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
                <span class="sidebar-title">
                    <i class="fas fa-sliders-h"></i>
                    Filter
                </span>
            </div>

            <form action="katalog.php" method="GET" id="filter-form">
                <?php if ($search): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>
                <?php if ($sort !== 'terbaru'): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <?php endif; ?>

                <!-- Genre -->
                <div style="margin-bottom:1.1rem;">
                    <p class="filter-section-label">Genre</p>
                    <div style="display:flex; flex-direction:column; max-height:190px; overflow-y:auto; padding-right:.2rem;">
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

                <hr class="filter-divider" />

                <!-- Harga -->
                <div style="margin-bottom:1rem;">
                    <p class="filter-section-label">Rentang Harga</p>
                    <?php
                    $price_ranges = [
                        ['label' => '< Rp 75.000',           'min' => 0,      'max' => 75000],
                        ['label' => 'Rp 75.000 – 100.000',   'min' => 75000,  'max' => 100000],
                        ['label' => 'Rp 100.000 – 150.000',  'min' => 100000, 'max' => 150000],
                        ['label' => '> Rp 150.000',           'min' => 150000, 'max' => 0],
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

                <button type="submit" class="btn-filter">
                    <i class="fas fa-check" style="margin-right:.4rem;font-size:.8rem;"></i>Terapkan Filter
                </button>
            </form>
        </aside>

        <!-- ══ BOOK SECTION ══ -->
        <section style="flex:1; min-width:0;">

            <!-- Toolbar -->
            <div class="toolbar">
                <p class="toolbar-count">
                    <strong><?= number_format($total_books) ?></strong> buku ditemukan
                </p>
                <div style="display:flex; align-items:center; gap:.55rem;">
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

            <?php if (empty($books)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <p class="empty-title">Tidak ada buku ditemukan</p>
                    <p class="empty-sub">Coba ubah kata kunci atau filter pencarian</p>
                    <a href="katalog.php" class="btn-empty">Lihat Semua Buku</a>
                </div>
            <?php else: ?>
                <?php
                $cover_gradients = [
                    'linear-gradient(135deg,#4a2c5e,#9b6bb5)',
                    'linear-gradient(135deg,#2d6a4f,#74b899)',
                    'linear-gradient(135deg,#7a2040,#c44a6c)',
                    'linear-gradient(135deg,#0f4c75,#3a8ab5)',
                    'linear-gradient(135deg,#3d2b00,#8b6200)',
                    'linear-gradient(135deg,#1a1040,#4a2f9a)',
                    'linear-gradient(135deg,#2d1b69,#7b5cf6)',
                    'linear-gradient(135deg,#3a0f4c,#7b2d8b)',
                ];
                ?>
                <div class="books-grid">
                    <?php foreach ($books as $book):
                        $ci = $book['id_buku'] % count($cover_gradients);
                        $is_wishlisted = in_array((int)$book['id_buku'], array_map('intval', $wishlisted_ids));
                    ?>
                    <div class="book-card">
                        <div class="cover-wrap">
                            <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                                <img src="../assets/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                                     alt="<?= htmlspecialchars($book['judul']) ?>"
                                     style="width:100%; aspect-ratio:3/4; object-fit:cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                                <div class="cover-placeholder" style="display:none; background:<?= $cover_gradients[$ci] ?>;">
                                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg>
                                </div>
                            <?php else: ?>
                                <div class="cover-placeholder" style="background:<?= $cover_gradients[$ci] ?>;">
                                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg>
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
                            <a href="detail.php?id=<?= $book['id_buku'] ?>" class="book-title-link">
                                <?= htmlspecialchars($book['judul']) ?>
                            </a>
                            <p class="book-author"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>

                            <div class="star-row">
                                <?= starHtml($book['avg_rating']) ?>
                                <span class="star-count">
                                    <?= $book['avg_rating'] > 0 ? $book['avg_rating'] : '—' ?>
                                    <?php if ($book['review_count'] > 0): ?>
                                        · <?= number_format($book['review_count']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:auto;">
                                <span class="book-price"><?= formatRupiah($book['harga']) ?></span>
                                <div style="display:flex; align-items:center; gap:.2rem;">
                                    
                                    <?php if ((int)$book['stok'] > 0): ?>
                                        <button class="btn-cart"
                                                onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)"
                                                title="Tambah ke keranjang">
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
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ══ PAGINATION ══ -->
                <?php if ($total_pages > 1): ?>
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= buildUrl(['page' => $page - 1]) ?>" class="page-btn">
                                <i class="fas fa-chevron-left" style="font-size:.72rem;"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
                        if ($start > 1): ?>
                            <a href="<?= buildUrl(['page' => 1]) ?>" class="page-btn">1</a>
                            <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="<?= buildUrl(['page' => $i]) ?>"
                               class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span class="page-ellipsis">…</span>
                            <?php endif; ?>
                            <a href="<?= buildUrl(['page' => $total_pages]) ?>" class="page-btn"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= buildUrl(['page' => $page + 1]) ?>" class="page-btn">
                                <i class="fas fa-chevron-right" style="font-size:.72rem;"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

        </section>
    </div>
</main>

<!-- ══ TOAST ══ -->
<div id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg">Berhasil!</span>
</div>

<script>
const wishlistedIds = new Set(<?= $wishlisted_json ?>);

function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    t.style.background = ok ? '#2a8a5e' : '#c0403a';
    t.style.transform = 'translateY(0)'; t.style.opacity = '1';
    setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 2800);
}

function updateBadge(type, delta) {
    const selector = type === 'cart' ? 'a[href*="keranjang"] .nav-badge' : 'a[href*="wishlist"] .nav-badge';
    const linkSel  = type === 'cart' ? 'a[href*="keranjang"]'            : 'a[href*="wishlist"]';
    let badge = document.querySelector(selector);
    const link = document.querySelector(linkSel);
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'nav-badge'; badge.textContent = '0';
        link.appendChild(badge);
    }
    let count = parseInt(badge.textContent) || 0;
    count += delta;
    if (count <= 0) { badge.remove(); } else { badge.textContent = Math.min(count, 99); }
}

function tambahKeranjang(idBuku, btn) {
    <?php if (!$user_id): ?>
        window.location.href = '/literaspace/auth/login.php'; return;
    <?php endif; ?>
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.8rem;"></i>';
    fetch('/literaspace/api/keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty: 1 })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Buku ditambahkan ke keranjang!');
            updateBadge('cart', 1);
            btn.innerHTML = '<i class="fas fa-check" style="font-size:.8rem;color:#2a8a5e;"></i>';
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

function tambahWishlist(idBuku, btn) {
    <?php if (!$user_id): ?>
        window.location.href = '/literaspace/auth/login.php'; return;
    <?php endif; ?>
    fetch('/literaspace/api/wishlist/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const icon = btn.querySelector('i');
            if (d.action === 'removed') {
                wishlistedIds.delete(idBuku); icon.className = 'far fa-heart';
                btn.classList.remove('wishlisted'); syncWishlistButtons(idBuku, false);
                updateBadge('wish', -1); showToast('Dihapus dari wishlist.');
            } else {
                wishlistedIds.add(idBuku); icon.className = 'fas fa-heart';
                btn.classList.add('wishlisted'); syncWishlistButtons(idBuku, true);
                updateBadge('wish', 1); showToast('Disimpan ke wishlist!');
            }
        } else { showToast(d.message || 'Gagal.', false); }
    })
    .catch(() => showToast('Terjadi kesalahan.', false));
}

function syncWishlistButtons(idBuku, aktif) {
    document.querySelectorAll(`button[onclick*="tambahWishlist(${idBuku},"]`).forEach(b => {
        const ic = b.querySelector('i');
        if (aktif) { ic.className = 'fas fa-heart'; b.classList.add('wishlisted'); }
        else       { ic.className = 'far fa-heart'; b.classList.remove('wishlisted'); }
    });
}

document.querySelectorAll('.price-radio').forEach(radio => {
    radio.addEventListener('change', function () {
        document.getElementById('min_harga').value = this.dataset.min;
        document.getElementById('max_harga').value = this.dataset.max;
    });
});
</script>
</body>
</html>