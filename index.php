<?php
// ========================================
// INDEX.PHP - LITERASPACE HOME PAGE
// Backend Logic & Database Queries
// ========================================

session_start();
require_once __DIR__ . '/config/db.php';

$pdo            = getDB();
$user_id        = $_SESSION['user_id'] ?? null;
$cart_count     = 0;
$wishlist_count = 0;
$categories     = [];
$popular_books  = [];
$fantasy_books  = [];
$error          = null;

try {
    $stmt_categories = $pdo->query("SELECT id_kategori, nama_kategori FROM kategori LIMIT 7");
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

    $stmt_popular = $pdo->query("SELECT id_buku, judul, penulis, harga, cover_image FROM buku ORDER BY id_buku DESC LIMIT 5");
    $popular_books = $stmt_popular->fetchAll(PDO::FETCH_ASSOC);

    $stmt_fantasy = $pdo->query("SELECT id_buku, judul, penulis, harga, cover_image FROM buku WHERE id_kategori = 2 OR judul LIKE '%fantasi%' OR judul LIKE '%fantasy%' LIMIT 4");
    $fantasy_books = $stmt_fantasy->fetchAll(PDO::FETCH_ASSOC);

    if ($user_id) {
        $sc = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?"); $sc->execute([$user_id]); $cart_count = (int)$sc->fetchColumn();
        $sw = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ?");  $sw->execute([$user_id]); $wishlist_count = (int)$sw->fetchColumn();
    }
} catch (PDOException $e) {
    $error = "Error loading data: " . $e->getMessage();
}

function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
function truncateText($text, $limit = 50) { return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '…' : $text; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LiteraSpace — Toko Buku Online Terlengkap</title>
    <meta name="description" content="Jelajahi koleksi buku terlengkap di LiteraSpace. Dari fiksi, non-fiksi, hingga buku anak-anak." />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
        /* ── Design tokens (identik dengan katalog.php) ── */
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

        /* ── Navbar (identik dengan katalog.php) ── */
        .navbar {
            position: sticky; top: 0; z-index: 50;
            background: var(--white);
            box-shadow: 0 4px 24px rgba(30,22,103,.13), 0 1px 0 rgba(30,22,103,.06);
            border-bottom: none;
        }

        .logo-icon {
            width: 40px; height: 40px;
            background: var(--indigo-deep);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s, transform .2s; flex-shrink: 0;
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
            background: var(--gray-50); border: 1.5px solid var(--gray-200); border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .9rem; color: var(--gray-800);
            outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .search-input::placeholder { color: var(--gray-500); }
        .search-input:focus { border-color: var(--indigo-light); box-shadow: 0 0 0 3px rgba(59,46,192,.10); background: var(--white); }
        .search-btn {
            position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--gray-500); font-size: .85rem; transition: color .2s;
        }
        .search-btn:hover { color: var(--indigo-light); }

        .nav-icon { color: var(--gray-500); font-size: 1.15rem; text-decoration: none; position: relative; transition: color .2s; }
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
        .dropdown-menu a { display: block; padding: .62rem 1rem; font-size: .86rem; color: var(--gray-800); text-decoration: none; transition: background .15s, color .15s; }
        .dropdown-menu a:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-menu a:last-child  { border-radius: 0 0 8px 8px; color: var(--error); }
        .dropdown-menu a:hover       { background: rgba(30,22,103,.05); color: var(--indigo-light); }
        .dropdown-menu a:last-child:hover { background: #fdecea; color: var(--error); }
        .dropdown-menu hr { border-color: var(--gray-200); margin: .25rem 0; }

        /* ── Category pill bar ── */
        .cat-bar {
            border-top: 1.5px solid var(--gray-100);
            background: var(--white);
        }
        .cat-bar-inner {
            max-width: 1280px; margin: 0 auto;
            padding: 0 1.5rem;
            display: flex; gap: .5rem; overflow-x: auto;
            scrollbar-width: none; -ms-overflow-style: none;
        }
        .cat-bar-inner::-webkit-scrollbar { display: none; }
        .cat-pill {
            display: inline-flex; align-items: center;
            padding: .45rem 1rem; white-space: nowrap;
            font-size: .82rem; font-weight: 500;
            color: var(--gray-500);
            border: 1.5px solid transparent; border-radius: 9999px;
            text-decoration: none;
            transition: color .2s, border-color .2s, background .2s;
            flex-shrink: 0; margin: .55rem 0;
        }
        .cat-pill:hover { color: var(--indigo-deep); border-color: var(--indigo-light); background: rgba(59,46,192,.06); }
        .cat-pill.all { color: var(--indigo-light); border-color: var(--indigo-light); }
        .cat-pill.all:hover { background: rgba(59,46,192,.08); }

        /* ── Hero ── */
        .hero {
            background: var(--indigo-deep);
            overflow: hidden; position: relative;
            margin-top: 1.5rem;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 60% 80% at 80% 50%, rgba(59,46,192,.55) 0%, transparent 70%),
                radial-gradient(ellipse 40% 60% at 10% 90%, rgba(212,146,10,.18) 0%, transparent 60%);
            pointer-events: none;
        }
        .hero-inner {
            max-width: 1280px; margin: 0 auto;
            padding: 4.5rem 1.5rem 5rem;
            position: relative; z-index: 1;
            display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 3rem;
        }
        @media (max-width: 720px) { .hero-inner { grid-template-columns: 1fr; padding: 3rem 1.25rem 3.5rem; } .hero-visual { display: none; } }

        .hero-eyebrow {
            display: inline-block;
            font-size: .75rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase;
            color: var(--amber); margin-bottom: .9rem;
        }
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1.15; color: var(--white);
            margin-bottom: 1.1rem;
        }
        .hero-title em { font-style: italic; color: #c9b8ff; }
        .hero-body { font-size: 1rem; color: rgba(255,255,255,.72); line-height: 1.65; margin-bottom: 2rem; }

        .hero-actions { display: flex; gap: .75rem; flex-wrap: wrap; }
        .btn-primary {
            padding: .75rem 1.5rem;
            background: var(--amber); color: var(--gray-800);
            border: none; border-radius: 9px;
            font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 700;
            text-decoration: none; cursor: pointer;
            transition: background .2s, transform .1s; display: inline-flex; align-items: center; gap: .4rem;
        }
        .btn-primary:hover { background: #e8a412; }
        .btn-primary:active { transform: scale(.98); }

        .btn-ghost {
            padding: .75rem 1.5rem;
            background: rgba(255,255,255,.1); color: var(--white);
            border: 1.5px solid rgba(255,255,255,.3); border-radius: 9px;
            font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 600;
            text-decoration: none; cursor: pointer;
            transition: background .2s, border-color .2s; display: inline-flex; align-items: center; gap: .4rem;
        }
        .btn-ghost:hover { background: rgba(255,255,255,.18); border-color: rgba(255,255,255,.55); }

        /* Hero visual (floating book stack) */
        .hero-visual {
            display: flex; align-items: center; justify-content: center;
        }
        .book-stack { position: relative; width: 200px; height: 260px; }
        .book-stack .b {
            position: absolute; width: 140px; height: 190px;
            border-radius: 6px 12px 12px 6px;
            box-shadow: 0 12px 40px rgba(0,0,0,.35);
        }
        .b1 { background: linear-gradient(145deg,#4a2f9a,#1e1667); top: 30px; left: 0; transform: rotate(-8deg); }
        .b2 { background: linear-gradient(145deg,#b5451b,#e76f51); top: 10px; left: 40px; transform: rotate(2deg); }
        .b3 { background: linear-gradient(145deg,#0f4c75,#1b6ca8); top: 50px; left: 70px; transform: rotate(10deg); }
        .b-spine {
            position: absolute; left: 0; top: 0; width: 14px; height: 100%;
            background: rgba(0,0,0,.25); border-radius: 6px 0 0 6px;
        }

        /* ── Section commons ── */
        .section { padding: 3.5rem 0; }
        .section-inner { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem; }
        .section-alt { background: linear-gradient(160deg, rgba(59,46,192,.04) 0%, rgba(30,22,103,.07) 100%); }

        .section-head {
            display: flex; align-items: flex-end; justify-content: space-between;
            margin-bottom: 1.8rem; gap: 1rem; flex-wrap: wrap;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.55rem; color: var(--gray-800);
            display: flex; align-items: center; gap: .5rem;
        }
        .section-title .icon { font-size: 1.1rem; }
        .section-subtitle { font-size: .84rem; color: var(--gray-500); margin-top: .2rem; }

        .see-all {
            font-size: .84rem; font-weight: 600; color: var(--indigo-light);
            text-decoration: none; display: flex; align-items: center; gap: .3rem;
            white-space: nowrap; transition: color .2s;
        }
        .see-all:hover { color: var(--indigo-deep); }
        .see-all i { font-size: .72rem; transition: transform .2s; }
        .see-all:hover i { transform: translateX(3px); }

        /* ── Book card (sesuai katalog.php) ── */
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

        .cat-badge {
            position: absolute; top: 6px; left: 6px;
            background: rgba(255,255,255,.92); color: var(--indigo-deep);
            font-size: .68rem; font-weight: 700;
            padding: .15rem .5rem; border-radius: 9999px; letter-spacing: .03em;
        }

        .book-info { padding: .75rem; }
        .book-title-link {
            font-size: .84rem; font-weight: 600; color: var(--gray-800);
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            margin-bottom: .15rem; text-decoration: none; transition: color .2s;
        }
        .book-title-link:hover { color: var(--indigo-light); }
        .book-author { font-size: .74rem; color: var(--gray-500); margin-bottom: .5rem; }
        .book-price  { font-size: .9rem; font-weight: 700; color: var(--indigo-deep); }

        .btn-cart {
            width: 32px; height: 32px;
            background: var(--indigo-deep); color: var(--white);
            border: none; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: .75rem; transition: background .2s;
        }
        .btn-cart:hover { background: var(--indigo-light); }

        .btn-wish {
            background: none; border: none; cursor: pointer;
            font-size: .9rem; color: var(--gray-500); padding: 0; transition: color .2s;
        }
        .btn-wish:hover { color: var(--error); }

        /* Grids */
        .books-grid-5 {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
        }
        .books-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        @media (max-width: 1024px) {
            .books-grid-5 { grid-template-columns: repeat(3, 1fr); }
            .books-grid-4 { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 640px) {
            .books-grid-5, .books-grid-4 { grid-template-columns: repeat(2, 1fr); }
        }

        /* Empty state */
        .empty-state {
            background: var(--white); border-radius: var(--radius);
            border: 1.5px solid var(--gray-200);
            padding: 4rem 2rem; text-align: center;
        }
        .empty-state i { font-size: 2.5rem; color: var(--gray-200); display: block; margin-bottom: .8rem; }
        .empty-state p { font-size: .88rem; color: var(--gray-500); }

        /* ── Promo Banner ── */
        .promo-banner {
            background: var(--indigo-deep);
            border-radius: var(--radius);
            padding: 2.8rem 2.5rem;
            display: grid; grid-template-columns: 1fr auto;
            gap: 2rem; align-items: center;
            position: relative; overflow: hidden;
        }
        .promo-banner::before {
            content: '';
            position: absolute; right: -80px; top: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255,255,255,.04);
            pointer-events: none;
        }
        .promo-banner::after {
            content: '';
            position: absolute; right: 60px; bottom: -100px;
            width: 200px; height: 200px; border-radius: 50%;
            background: rgba(59,46,192,.2);
            pointer-events: none;
        }
        @media (max-width: 640px) { .promo-banner { grid-template-columns: 1fr; } .promo-icon { display: none !important; } }

        .promo-eyebrow {
            font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
            color: var(--amber); margin-bottom: .5rem;
        }
        .promo-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem; color: var(--white); margin-bottom: .6rem;
        }
        .promo-body { font-size: .88rem; color: rgba(255,255,255,.68); margin-bottom: 1.4rem; line-height: 1.6; }

        .promo-form { display: flex; gap: .6rem; flex-wrap: wrap; max-width: 520px; }
        .promo-input {
            flex: 1; min-width: 200px;
            padding: .65rem 1rem;
            background: rgba(255,255,255,.1);
            border: 1.5px solid rgba(255,255,255,.25);
            border-radius: 8px; color: var(--white);
            font-family: 'DM Sans', sans-serif; font-size: .88rem;
            outline: none; transition: border-color .2s, background .2s;
        }
        .promo-input::placeholder { color: rgba(255,255,255,.45); }
        .promo-input:focus { border-color: rgba(255,255,255,.6); background: rgba(255,255,255,.15); }

        .btn-promo {
            padding: .65rem 1.4rem;
            background: var(--amber); color: var(--gray-800);
            border: none; border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .88rem; font-weight: 700;
            cursor: pointer; white-space: nowrap;
            transition: background .2s;
        }
        .btn-promo:hover { background: #e8a412; }

        /* ── Footer ── */
        footer {
            background: var(--gray-800);
            color: rgba(255,255,255,.65);
            margin-top: 0;
        }
        .footer-inner {
            max-width: 1280px; margin: 0 auto;
            padding: 3.5rem 1.5rem 2rem;
            display: grid; grid-template-columns: 2fr 1fr 1fr 1.4fr;
            gap: 2.5rem;
        }
        @media (max-width: 900px) { .footer-inner { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 540px) { .footer-inner { grid-template-columns: 1fr; } }

        .footer-logo-icon {
            width: 36px; height: 36px;
            background: var(--indigo-light); border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: .7rem;
        }
        .footer-logo-icon svg { width: 18px; height: 18px; fill: var(--white); }
        .footer-brand { font-family: 'Playfair Display', serif; font-size: 1rem; color: var(--white); margin-bottom: .6rem; }
        .footer-about { font-size: .82rem; line-height: 1.65; }
        .footer-copy { font-size: .74rem; color: rgba(255,255,255,.35); margin-top: .9rem; }

        .footer-heading { font-size: .82rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--white); margin-bottom: .9rem; }
        .footer-links { list-style: none; display: flex; flex-direction: column; gap: .5rem; }
        .footer-links a { font-size: .82rem; color: rgba(255,255,255,.55); text-decoration: none; transition: color .2s; }
        .footer-links a:hover { color: var(--white); }

        .social-row { display: flex; gap: .6rem; margin-bottom: 1.2rem; }
        .social-btn {
            width: 36px; height: 36px;
            background: rgba(255,255,255,.08); border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,.65); text-decoration: none; font-size: .82rem;
            transition: background .2s, color .2s;
        }
        .social-btn:hover { background: var(--indigo-light); color: var(--white); }

        .contact-row { display: flex; align-items: flex-start; gap: .5rem; font-size: .82rem; margin-bottom: .4rem; }
        .contact-row i { color: var(--indigo-light); margin-top: .1rem; font-size: .78rem; flex-shrink: 0; }

        .footer-bottom {
            max-width: 1280px; margin: 0 auto;
            padding: 1.2rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,.08);
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap;
        }
        .footer-bottom p { font-size: .78rem; }
        .pay-imgs { display: flex; gap: .6rem; align-items: center; }
        .pay-imgs img { height: 22px; opacity: .5; transition: opacity .2s; }
        .pay-imgs img:hover { opacity: .85; }

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

<!-- ════════════════════════ NAVBAR ════════════════════════ -->
<nav class="navbar">
    <div style="max-width:1280px; margin:0 auto; padding:0 1.5rem;">
        <div style="display:flex; align-items:center; justify-content:space-between; height:68px; gap:1rem;">

            <!-- Logo -->
            <a href="/index.php" style="display:flex; align-items:center; gap:.6rem; text-decoration:none; flex-shrink:0;">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                </div>
                <span class="logo-text" id="logo-text-desk" style="display:none;">LiteraSpace</span>
            </a>

            <!-- Search -->
            <div class="search-wrap">
                <form action="/search" method="GET">
                    <input type="search" name="q" placeholder="Cari judul, penulis, atau kategori..." class="search-input" />
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <!-- Right icons -->
            <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
                <a href="/literaspace/pages/keranjang.php" class="nav-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cart_count > 0): ?><span class="nav-badge"><?= min($cart_count, 99) ?></span><?php endif; ?>
                </a>
                <a href="/literaspace/pages/wishlist.php" class="nav-icon" style="color:var(--gray-500);"
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
                            <a href="/profile"><i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya</a>
                            <a href="/pesanan"><i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya</a>
                            <hr />
                            <a href="/literaspace/auth/logout.php"><i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/literaspace/auth/login.php" class="btn-auth">Masuk / Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category pill bar -->
    <?php if (!empty($categories)): ?>
    <div class="cat-bar">
        <div class="cat-bar-inner">
            <?php foreach ($categories as $cat): ?>
                <a href="/literaspace/pages/kategori.php?id=<?= urlencode($cat['id_kategori']) ?>" class="cat-pill">
                    <?= htmlspecialchars($cat['nama_kategori']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</nav>

<?php if ($error): ?>
<div style="max-width:1280px; margin:.8rem auto; padding:0 1.5rem;">
    <div style="background:#fdecea; border:1.5px solid #f5c6c6; color:var(--error); border-radius:8px; padding:.75rem 1rem; font-size:.88rem;">
        <i class="fas fa-exclamation-circle" style="margin-right:.4rem;"></i><?= htmlspecialchars($error) ?>
    </div>
</div>
<?php endif; ?>

<!-- ════════════════════════ HERO ════════════════════════ -->
<section class="hero">
    <div class="hero-inner">
        <div>
            <span class="hero-eyebrow">✦ Toko Buku Online #1 Indonesia</span>
            <h1 class="hero-title">
                Jelajahi Dunia <em>Literatur</em> Tanpa Batas
            </h1>
            <p class="hero-body">
                Temukan ribuan judul dari penulis lokal dan mancanegara. Pengiriman cepat, harga terjangkau, dan kualitas terjamin untuk setiap pembaca.
            </p>
            <div class="hero-actions">
                <a href="/literaspace/pages/kategori.php" class="btn-primary">
                    <i class="fas fa-book-open"></i> Lihat Katalog
                </a>
            </div>
        </div>

        <!-- Floating book stack illustration -->
        <div class="hero-visual">
            <div class="book-stack">
                <div class="b b1"><div class="b-spine"></div></div>
                <div class="b b2"><div class="b-spine"></div></div>
                <div class="b b3"><div class="b-spine"></div></div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════ BUKU TERPOPULER ════════════════════════ -->
<section class="section">
    <div class="section-inner">
        <div class="section-head">
            <div>
                <h2 class="section-title">
                    <i class="fas fa-fire icon" style="color:#d4920a;"></i> Buku Terpopuler
                </h2>
                <p class="section-subtitle">Pilihan terbaik dan paling dicari oleh pembaca kami</p>
            </div>
            <a href="/kategori" class="see-all">Lihat Semua <i class="fas fa-arrow-right"></i></a>
        </div>

        <?php if (!empty($popular_books)): ?>
            <?php
            $cover_gradients = [
                'linear-gradient(135deg,#1e1667,#3b2ec0)',
                'linear-gradient(135deg,#0f4c75,#1b6ca8)',
                'linear-gradient(135deg,#2d6a4f,#52b788)',
                'linear-gradient(135deg,#7b2d8b,#c77dff)',
                'linear-gradient(135deg,#b5451b,#e76f51)',
            ];
            ?>
            <div class="books-grid-5">
                <?php foreach ($popular_books as $book):
                    $ci = $book['id_buku'] % count($cover_gradients);
                ?>
                    <div class="book-card">
                        <div class="cover-wrap">
                            <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . "/assets/covers/{$book['cover_image']}")): ?>
                                <img src="/assets/covers/<?= htmlspecialchars($book['cover_image']) ?>"
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
                        </div>

                        <div class="book-info">
                            <a href="/pages/detail.php?id=<?= $book['id_buku'] ?>" class="book-title-link">
                                <?= htmlspecialchars(truncateText($book['judul'], 45)) ?>
                            </a>
                            <p class="book-author"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:.4rem;">
                                <span class="book-price"><?= formatRupiah($book['harga']) ?></span>
                                <div style="display:flex; gap:.4rem;">
                                    <button class="btn-wish" onclick="tambahWishlist(<?= $book['id_buku'] ?>)" title="Simpan ke wishlist">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)" title="Tambah ke keranjang">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Belum ada buku yang tersedia.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ════════════════════════ EPIK & FANTASI ════════════════════════ -->
<section class="section section-alt">
    <div class="section-inner">
        <div class="section-head">
            <div>
                <h2 class="section-title">
                    <i class="fas fa-wand-magic-sparkles icon" style="color:var(--indigo-light);"></i> Epik &amp; Fantasi
                </h2>
                <p class="section-subtitle">Petualangan menakjubkan menanti Anda di setiap halaman</p>
            </div>
            <a href="/kategori/2" class="see-all">Lihat Kategori <i class="fas fa-arrow-right"></i></a>
        </div>

        <?php if (!empty($fantasy_books)): ?>
            <?php
            $fantasy_gradients = [
                'linear-gradient(135deg,#1a1040,#4a2f9a)',
                'linear-gradient(135deg,#7b2d8b,#c77dff)',
                'linear-gradient(135deg,#2d1b69,#8b5cf6)',
                'linear-gradient(135deg,#0f0c29,#302b63)',
            ];
            ?>
            <div class="books-grid-4">
                <?php foreach ($fantasy_books as $book):
                    $ci = $book['id_buku'] % count($fantasy_gradients);
                ?>
                    <div class="book-card">
                        <div class="cover-wrap">
                            <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . "/assets/covers/{$book['cover_image']}")): ?>
                                <img src="/assets/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                                     alt="<?= htmlspecialchars($book['judul']) ?>"
                                     style="width:100%; aspect-ratio:3/4; object-fit:cover; display:block;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                                <div class="cover-placeholder" style="display:none; background:<?= $fantasy_gradients[$ci] ?>;">
                                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                                </div>
                            <?php else: ?>
                                <div class="cover-placeholder" style="background:<?= $fantasy_gradients[$ci] ?>;">
                                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                                </div>
                            <?php endif; ?>
                            <span class="cat-badge">Fantasi</span>
                        </div>

                        <div class="book-info">
                            <a href="/pages/detail.php?id=<?= $book['id_buku'] ?>" class="book-title-link">
                                <?= htmlspecialchars(truncateText($book['judul'], 45)) ?>
                            </a>
                            <p class="book-author"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:.4rem;">
                                <span class="book-price" style="color:var(--indigo-light);"><?= formatRupiah($book['harga']) ?></span>
                                <div style="display:flex; gap:.4rem;">
                                    <button class="btn-wish" onclick="tambahWishlist(<?= $book['id_buku'] ?>)" title="Simpan ke wishlist">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)" title="Tambah ke keranjang">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-wand-magic-sparkles"></i>
                <p>Belum ada buku fantasi yang tersedia.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ════════════════════════ PROMO BANNER ════════════════════════ -->
<section class="section">
    <div class="section-inner">
        <div class="promo-banner">
            <div style="position:relative; z-index:1;">
                <p class="promo-eyebrow">✦ Penawaran Eksklusif</p>
                <h3 class="promo-title">Dapatkan Diskon Spesial!</h3>
                <p class="promo-body">
                    Daftarkan email Anda dan dapatkan voucher diskon hingga 25%<br>untuk pembelian pertama.
                </p>
                <div class="promo-form">
                    <input type="email" placeholder="Masukkan email Anda..." class="promo-input" />
                    <button type="button" class="btn-promo">Berlangganan</button>
                </div>
            </div>
            <div class="promo-icon" style="position:relative; z-index:1; flex-shrink:0; display:flex; align-items:center; justify-content:center; width:120px;">
                <i class="fas fa-gift" style="font-size:5rem; color:rgba(255,255,255,.15);"></i>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════ FOOTER ════════════════════════ -->
<footer>
    <div class="footer-inner">
        <!-- Brand -->
        <div>
            <div class="footer-logo-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
            </div>
            <p class="footer-brand">LiteraSpace</p>
            <p class="footer-about">Toko buku online terpercaya dengan koleksi lengkap dari berbagai genre dan penulis. Kami berkomitmen memberikan pengalaman belanja terbaik.</p>
            <p class="footer-copy">© 2026 LiteraSpace. Semua hak dilindungi.</p>
        </div>

        <!-- Bantuan -->
        <div>
            <p class="footer-heading">Bantuan</p>
            <ul class="footer-links">
                <li><a href="/faq">FAQ</a></li>
                <li><a href="/lacak-pesanan">Lacak Pesanan</a></li>
                <li><a href="/return-policy">Kebijakan Pengembalian</a></li>
                <li><a href="/hubungi-kami">Hubungi Kami</a></li>
            </ul>
        </div>

        <!-- Informasi -->
        <div>
            <p class="footer-heading">Informasi</p>
            <ul class="footer-links">
                <li><a href="/tentang">Tentang Kami</a></li>
                <li><a href="/syarat-ketentuan">Syarat &amp; Ketentuan</a></li>
                <li><a href="/privacy-policy">Privasi</a></li>
                <li><a href="/blog">Blog</a></li>
            </ul>
        </div>

        <!-- Sosial & Kontak -->
        <div>
            <p class="footer-heading">Ikuti Kami</p>
            <div class="social-row">
                <a href="#" class="social-btn"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-btn"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-btn"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-btn"><i class="fab fa-youtube"></i></a>
            </div>
            <div class="contact-row"><i class="fas fa-phone"></i><span>+62 812 3456 7890</span></div>
            <div class="contact-row"><i class="fas fa-envelope"></i><span>support@literaspace.com</span></div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>Dipercaya oleh lebih dari 50.000+ pembaca di seluruh Indonesia</p>
        <div class="pay-imgs">
            <img src="https://via.placeholder.com/50x28?text=VISA"  alt="Visa">
            <img src="https://via.placeholder.com/50x28?text=MC"    alt="Mastercard">
            <img src="https://via.placeholder.com/50x28?text=BCA"   alt="BCA">
            <img src="https://via.placeholder.com/50x28?text=GOPAY" alt="GoPay">
        </div>
    </div>
</footer>

<!-- Toast -->
<div id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg">Buku ditambahkan ke keranjang!</span>
</div>

<style>
    @media (min-width: 600px) { #logo-text-desk { display: inline !important; } }
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
        window.location.href = '/literaspace/auth/login.php'; return;
    <?php endif; ?>
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('/api/keranjang/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty: 1 })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Buku ditambahkan ke keranjang!');
            btn.innerHTML = '<i class="fas fa-check"></i>';
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

function tambahWishlist(idBuku) {
    <?php if (!$user_id): ?>
        window.location.href = '/literaspace/auth/login.php'; return;
    <?php endif; ?>
    fetch('/api/wishlist/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.success ? 'Disimpan ke wishlist!' : (d.message || 'Gagal.'), d.success);
    })
    .catch(() => showToast('Terjadi kesalahan.', false));
}
</script>
</body>
</html>