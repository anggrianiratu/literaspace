<?php
// ========================================
// WISHLIST.PHP - LITERASPACE
// Tema: Pastel Painterly (selaras katalog & keranjang)
// ========================================

session_start();
require_once __DIR__ . '/../config/db.php';

$pdo            = getDB();
$user_id        = $_SESSION['user_id'] ?? null;
$cart_count     = 0;
$wishlist_count = 0;
$wishlist_books = [];
$error          = null;

if (!$user_id) {
    header('Location: /literaspace/auth/login.php');
    exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'hapus_semua') {
            $pdo->prepare("DELETE FROM wishlist WHERE id_user = ?")->execute([$user_id]);
            header('Location: wishlist.php?msg=deleted'); exit;
        }
        if ($action === 'pindah_semua') {
            $stmt = $pdo->prepare("
                SELECT w.id_buku FROM wishlist w
                JOIN buku b ON w.id_buku = b.id_buku
                WHERE w.id_user = ? AND b.stok > 0
            ");
            $stmt->execute([$user_id]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id_buku) {
                $cek = $pdo->prepare("SELECT id_keranjang FROM keranjang WHERE id_user = ? AND id_buku = ?");
                $cek->execute([$user_id, $id_buku]);
                $existing = $cek->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $pdo->prepare("UPDATE keranjang SET qty = qty + 1 WHERE id_keranjang = ?")->execute([$existing['id_keranjang']]);
                } else {
                    $pdo->prepare("INSERT INTO keranjang (id_user, id_buku, qty) VALUES (?, ?, 1)")->execute([$user_id, $id_buku]);
                }
            }
            $pdo->prepare("DELETE FROM wishlist WHERE id_user = ?")->execute([$user_id]);
            header('Location: wishlist.php?msg=moved'); exit;
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

try {
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
            ? '<i class="fas fa-star" style="color:#c4882a;font-size:.65rem;"></i>'
            : '<i class="far fa-star" style="color:#d4c2e0;font-size:.65rem;"></i>';
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

        .navbar, main, .page-header, #toast {
            position: relative; z-index: 1;
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
            position: relative; overflow: hidden;
            padding: 2.8rem 1.5rem 3rem;
            background: transparent;
        }
        .page-header-inner {
            max-width: 1280px; margin: 0 auto;
            position: relative; z-index: 2;
            display: flex; align-items: flex-end; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
        }
        .page-eyebrow {
            font-size: .7rem; font-weight: 500; letter-spacing: .22em;
            text-transform: uppercase; color: #9b6bb5;
            margin-bottom: .55rem; display: block;
        }
        .page-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(1.6rem, 3.5vw, 2.4rem);
            font-weight: 600; color: var(--ink); line-height: 1.15;
            margin-bottom: .35rem;
        }
        .page-title em { font-style: italic; color: var(--plum); }
        .page-subtitle { font-size: .88rem; color: var(--muted); }

        /* ══ HEADER ACTION BUTTONS ══ */
        .header-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }

        .btn-move-all {
            padding: .52rem 1.1rem;
            background: var(--plum); color: var(--white);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .84rem; font-weight: 500;
            letter-spacing: .05em; cursor: pointer;
            display: flex; align-items: center; gap: .4rem;
            transition: background .2s, transform .1s;
        }
        .btn-move-all:hover  { background: var(--plum-mid); }
        .btn-move-all:active { transform: scale(.97); }

        .btn-delete-all {
            padding: .52rem 1.1rem;
            background: transparent; color: var(--error);
            border: 1.5px solid rgba(192,64,58,.4);
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .84rem; font-weight: 500;
            letter-spacing: .05em; cursor: pointer;
            display: flex; align-items: center; gap: .4rem;
            transition: background .2s, color .2s, border-color .2s, transform .1s;
        }
        .btn-delete-all:hover  { background: var(--error); color: var(--white); border-color: var(--error); }
        .btn-delete-all:active { transform: scale(.97); }

        /* ══ MAIN ══ */
        .page-inner { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem 3rem; }

        /* ══ FLASH MESSAGE ══ */
        .flash-msg {
            padding: .72rem 1rem; border-radius: var(--radius-sm);
            font-size: .86rem; margin-bottom: 1.4rem;
            display: flex; align-items: center; gap: .5rem;
            font-family: 'Jost', sans-serif;
        }
        .flash-success {
            background: rgba(42,138,94,.08);
            border: 1.5px solid rgba(42,138,94,.22);
            color: var(--success);
        }
        .flash-error {
            background: rgba(192,64,58,.07);
            border: 1.5px solid rgba(192,64,58,.2);
            color: var(--error);
        }

        /* ══ BOOK GRID ══ */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
            gap: 1rem;
        }

        /* ══ BOOK CARD ══ */
        .book-card {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-sm);
            border: 1.5px solid #7a6585;
            overflow: hidden;
            transition: transform .35s cubic-bezier(.34,1.56,.64,1), box-shadow .3s;
            display: flex; flex-direction: column; height: 100%;
            position: relative;
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
        .cover-placeholder svg { width: 30px; height: 30px; fill: rgba(255,255,255,.35); }

        /* Tombol hapus wishlist (pojok kanan atas) */
        .btn-remove-wish {
            position: absolute; top: 6px; right: 6px; z-index: 2;
            width: 28px; height: 28px;
            background: rgba(253,248,243,.9);
            backdrop-filter: blur(4px);
            border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--error);
            font-size: .75rem;
            box-shadow: 0 2px 8px rgba(74,44,94,.15);
            transition: background .2s, transform .2s, color .2s;
        }
        .btn-remove-wish:hover { background: var(--error); color: var(--white); transform: scale(1.12); }

        .cat-badge {
            position: absolute; top: 6px; left: 6px;
            background: rgba(253,248,243,.92); color: var(--plum);
            font-size: .63rem; font-weight: 600; padding: .15rem .55rem;
            border-radius: 9999px; letter-spacing: .06em;
            font-family: 'Jost', sans-serif;
        }

        .sold-out-overlay {
            position: absolute; inset: 0;
            background: rgba(42,26,53,.52);
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
            display: flex; flex-direction: column; flex: 1;
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

        .price-row {
            display: flex; align-items: center; justify-content: space-between;
            margin-top: auto;
        }

        .book-price {
            font-size: .9rem; font-weight: 700; color: var(--plum);
            font-family: 'Jost', sans-serif; white-space: nowrap;
        }

        /* ══ FIX: Tombol keranjang — tanpa kotak, hanya ikon ══ */
        .btn-cart {
            width: 30px; height: 30px; flex-shrink: 0;
            background: transparent; color: var(--plum);
            border: none; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1.05rem;
            transition: color .2s, transform .15s;
            padding: 0;
        }
        .btn-cart:hover  { color: var(--plum-mid); transform: scale(1.15); }
        .btn-cart:active { transform: scale(.95); }
        .btn-cart:disabled {
            color: rgba(122,101,133,.3);
            cursor: not-allowed;
            transform: none;
        }

        /* ══ EMPTY STATE ══ */
        .empty-state {
            background: rgba(255,255,255,.75);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-lg);
            border: 1.5px solid rgba(122,101,133,.3);
            padding: 5rem 2rem;
            text-align: center;
            display: flex; flex-direction: column; align-items: center;
        }
        .empty-state i {
            font-size: 2.8rem; color: rgba(155,107,181,.3);
            display: block; margin-bottom: 1.1rem;
        }
        .empty-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.3rem; color: var(--muted); font-weight: 600;
        }
        .empty-sub {
            font-size: .85rem; color: var(--muted); margin-top: .4rem;
            font-family: 'Jost', sans-serif;
        }

        /* ══ FIX: Tombol Jelajahi Katalog — ramping, ikon + teks sebaris ══ */
        .btn-empty {
            display: inline-flex; align-items: center; gap: .4rem;
            margin-top: 1.2rem; padding: .45rem .9rem;
            background: var(--plum); color: var(--white);
            border-radius: var(--radius-sm); text-decoration: none;
            font-size: .84rem; font-weight: 600; letter-spacing: .03em;
            font-family: 'Jost', sans-serif;
            transition: background .2s;
            width: max-content;
        }
        .btn-empty i { color: var(--white); font-size: .84rem; line-height: 1; position: relative; top: 1px; }
        .btn-empty:hover { background: var(--plum-mid); }

        /* ══ MODAL ══ */
        .modal-overlay {
            position: fixed; inset: 0; z-index: 200;
            display: flex; align-items: center; justify-content: center;
            background: rgba(42,26,53,.5);
            opacity: 0; visibility: hidden;
            transition: opacity .25s, visibility .25s;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-box {
            background: var(--cream); border-radius: var(--radius-lg);
            padding: 1.8rem 1.6rem; width: 100%; max-width: 360px;
            box-shadow: 0 20px 60px rgba(42,26,53,.25);
            border: 1.5px solid rgba(232,197,208,.5);
            transform: translateY(18px); transition: transform .28s;
        }
        .modal-overlay.active .modal-box { transform: translateY(0); }
        .modal-icon {
            width: 48px; height: 48px;
            background: rgba(192,64,58,.1); border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem;
        }
        .modal-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.15rem; font-weight: 600; color: var(--ink);
            margin-bottom: .4rem;
        }
        .modal-desc {
            font-size: .86rem; color: var(--muted);
            font-family: 'Jost', sans-serif;
            line-height: 1.55; margin-bottom: 1.4rem;
        }
        .modal-actions { display: flex; gap: .6rem; justify-content: flex-end; }
        .btn-modal-batal {
            padding: .45rem 1.1rem;
            background: rgba(122,101,133,.1); border: none;
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .84rem;
            font-weight: 500; color: var(--ink); cursor: pointer;
            transition: background .15s; white-space: nowrap;
        }
        .btn-modal-batal:hover { background: rgba(122,101,133,.18); }
        .btn-modal-hapus {
            padding: .45rem 1.1rem;
            background: var(--error); border: none;
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .84rem;
            font-weight: 500; color: var(--white); cursor: pointer;
            transition: background .15s; white-space: nowrap;
            display: inline-flex; align-items: center; gap: .35rem;
        }
        .btn-modal-hapus:hover { background: #a83530; }

        /* ══ TOAST ══ */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999;
            padding: .7rem 1.15rem; border-radius: var(--radius-sm);
            color: var(--white); font-size: .87rem;
            font-family: 'Jost', sans-serif;
            display: flex; align-items: center; gap: .5rem;
            box-shadow: 0 8px 24px rgba(42,26,53,.2);
            transform: translateY(80px); opacity: 0;
            transition: all .3s; pointer-events: none;
        }

        @media (max-width: 600px) {
            .books-grid { grid-template-columns: repeat(2, 1fr); }
            .header-actions { width: 100%; }
            .btn-move-all, .btn-delete-all { flex: 1; justify-content: center; }
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

        <div style="flex:1;"></div>

        <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
            <!-- Cart -->
            <a href="/literaspace/pages/keranjang.php" class="nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="nav-badge"><?= min($cart_count, 99) ?></span>
                <?php endif; ?>
            </a>

            <!-- Wishlist (aktif) -->
            <a href="/literaspace/pages/wishlist.php" class="nav-icon" style="color:var(--plum-mid);">
                <i class="fas fa-heart"></i>
                <?php if ($wishlist_count > 0): ?>
                    <span class="nav-badge"><?= min($wishlist_count, 99) ?></span>
                <?php endif; ?>
            </a>

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
        </div>
    </div>
</nav>

<!-- ══ PAGE HEADER ══ -->
<div class="page-header">
    <div class="page-header-inner">
        <div>
            <span class="page-eyebrow">✦ Koleksi Tersimpan</span>
            <h1 class="page-title">Wishlist <em>Saya</em></h1>
            <p class="page-subtitle" id="wishlist-subtitle">
                <?php if ($wishlist_count > 0): ?>
                    <?= $wishlist_count ?> buku tersimpan
                <?php else: ?>
                    Belum ada buku yang disimpan
                <?php endif; ?>
            </p>
        </div>

        <?php if ($wishlist_count > 0): ?>
        <div class="header-actions" id="header-actions">
            <form method="POST" style="display:contents;">
                <input type="hidden" name="action" value="pindah_semua">
                <button type="submit" class="btn-move-all">
                    <i class="fas fa-cart-plus" style="font-size:.8rem;"></i>
                    Pindah ke Keranjang
                </button>
            </form>
            <button type="button" class="btn-delete-all" onclick="openModalHapusSemua()">
                <i class="fas fa-trash-alt" style="font-size:.78rem;"></i>
                Hapus Semua
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ MAIN ══ -->
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

    <?php if (empty($wishlist_books)): ?>
        <!-- ── Empty state ── -->
        <div class="empty-state">
            <i class="far fa-heart"></i>
            <p class="empty-title">Wishlist kamu masih kosong</p>
            <p class="empty-sub">Simpan buku favorit kamu di sini</p>
            <a href="/literaspace/pages/katalog.php" class="btn-empty">
                Jelajahi Katalog
            </a>
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
        <div class="books-grid" id="books-grid">
            <?php foreach ($wishlist_books as $book):
                $ci = $book['id_buku'] % count($cover_gradients);
            ?>
            <div class="book-card" id="card-<?= $book['id_wishlist'] ?>">
                <div class="cover-wrap">
                    <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                        <img src="../assets/covers/<?= htmlspecialchars($book['cover_image']) ?>"
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
                    <a href="detail.php?id=<?= $book['id_buku'] ?>" class="book-title-link">
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

                    <div class="price-row">
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
    <?php endif; ?>

</main>

<!-- ══ MODAL KONFIRMASI HAPUS SEMUA ══ -->
<div class="modal-overlay" id="modal-hapus-semua">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-trash-alt" style="color:var(--error);font-size:1.1rem;"></i>
        </div>
        <p class="modal-title">Hapus semua wishlist?</p>
        <p class="modal-desc">Semua buku akan dihapus dari wishlist kamu. Tindakan ini tidak bisa dibatalkan.</p>
        <div class="modal-actions">
            <button class="btn-modal-batal" onclick="closeModal()">Batal</button>
            <form method="POST" style="display:contents;">
                <input type="hidden" name="action" value="hapus_semua">
                <button type="submit" class="btn-modal-hapus">
                    <i class="fas fa-trash-alt" style="margin-right:.35rem;font-size:.8rem;"></i>Ya, Hapus Semua
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ══ TOAST ══ -->
<div id="toast">
    <i class="fas fa-check-circle" id="toast-icon"></i>
    <span id="toast-msg">Berhasil!</span>
</div>

<script>
/* ── Toast ── */
function showToast(msg, ok = true) {
    const t    = document.getElementById('toast');
    const icon = document.getElementById('toast-icon');
    document.getElementById('toast-msg').textContent = msg;
    t.style.background = ok ? '#2a8a5e' : '#c0403a';
    icon.className = ok ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    t.style.transform = 'translateY(0)'; t.style.opacity = '1';
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 2800);
}

/* ── Modal ── */
function openModalHapusSemua() {
    document.getElementById('modal-hapus-semua').classList.add('active');
}
function closeModal() {
    document.getElementById('modal-hapus-semua').classList.remove('active');
}
document.getElementById('modal-hapus-semua').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

/* ── Hapus dari wishlist (AJAX) ── */
function hapusDariWishlist(idWishlist, idBuku, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.7rem;"></i>';

    fetch('/literaspace/api/wishlist.php', {
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

/* ── Update subtitle & badge ── */
function updateWishlistCount(n) {
    const subtitle = document.getElementById('wishlist-subtitle');
    if (subtitle) subtitle.textContent = n > 0 ? n + ' buku tersimpan' : 'Belum ada buku yang disimpan';

    const badge = document.querySelector('a[href*="wishlist"] .nav-badge');
    if (badge) {
        if (n > 0) badge.textContent = Math.min(n, 99);
        else badge.remove();
    }

    if (n === 0) {
        const actions = document.getElementById('header-actions');
        if (actions) actions.remove();
    }
}

/* ── Tampilkan empty state ── */
function showEmptyState() {
    const grid = document.getElementById('books-grid');
    if (grid) {
        grid.outerHTML = `
            <div class="empty-state">
                <i class="far fa-heart"></i>
                <p class="empty-title">Wishlist kamu masih kosong</p>
                <p class="empty-sub">Simpan buku favorit kamu di sini</p>
                <a href="/literaspace/pages/katalog.php" class="btn-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    Jelajahi Katalog
                </a>
            </div>
        `;
    }
}

/* ── Tambah ke keranjang ── */
function tambahKeranjang(idBuku, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.75rem;"></i>';

    fetch('/literaspace/api/keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty: 1 })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Buku ditambahkan ke keranjang!');
            btn.innerHTML = '<i class="fas fa-check" style="font-size:.75rem;"></i>';
            btn.style.color = 'var(--success)';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-cart-plus"></i>';
                btn.style.color = '';
                btn.disabled = false;
            }, 2000);
        } else {
            showToast(d.message || 'Gagal menambahkan.', false);
            btn.innerHTML = '<i class="fas fa-cart-plus"></i>';
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan.', false);
        btn.innerHTML = '<i class="fas fa-cart-plus"></i>';
        btn.disabled = false;
    });
}

/* ── Auto-dismiss flash message ── */
const flash = document.querySelector('.flash-msg');
if (flash) setTimeout(() => {
    flash.style.transition = 'opacity .4s';
    flash.style.opacity = '0';
    setTimeout(() => flash.remove(), 400);
}, 3500);
</script>
</body>
</html>