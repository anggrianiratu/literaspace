<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$user_id    = $_SESSION['user_id'] ?? null;
$cart_count = 0;
$cart_items = [];
$wishlist_count = 0;

function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

try {
    $pdo = getDB();

    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT
                k.id_keranjang,
                k.qty,
                b.id_buku,
                b.judul,
                b.penulis,
                b.harga,
                b.cover_image,
                b.stok,
                kat.nama_kategori
            FROM keranjang k
            JOIN buku b ON k.id_buku = b.id_buku
            LEFT JOIN kategori kat ON b.id_kategori = kat.id_kategori
            WHERE k.id_user = ?
            ORDER BY k.id_keranjang DESC
        ");
        $stmt->execute([$user_id]);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cart_count = count($cart_items);

        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ?");
        $stmt2->execute([$user_id]);
        $wishlist_count = (int)$stmt2->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Error loading cart: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Keranjang Belanja — LiteraSpace</title>
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
        .page-header-bg { display: none; }
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
            font-weight: 600; color: var(--ink); line-height: 1.15;
            margin-bottom: .4rem;
        }
        .page-title em { font-style: italic; color: var(--plum); }
        .page-subtitle {
            font-size: .88rem; color: var(--muted);
        }

        /* ══ MAIN ══ */
        .page-inner { max-width: 1280px; margin: 0 auto; padding: 2rem 1.5rem 3rem; }

        /* ══ CART LAYOUT ══ */
        .cart-layout { display: grid; grid-template-columns: 1fr 300px; gap: 1.6rem; align-items: flex-start; }
        @media (max-width: 860px) { .cart-layout { grid-template-columns: 1fr; } }

        /* ══ CART PANEL ══ */
        .cart-panel {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border: 1.5px solid #7a6585;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .select-all-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding: .9rem 1.3rem;
            border-bottom: 1px solid rgba(122,101,133,.18);
            background: rgba(245,237,224,.5);
        }
        .select-all-label {
            display: flex; align-items: center; gap: .55rem;
            font-size: .88rem; font-weight: 500; color: var(--ink); cursor: pointer;
            font-family: 'Jost', sans-serif;
        }
        .select-all-label input[type="checkbox"] { accent-color: var(--plum-mid); width: 15px; height: 15px; }

        .btn-hapus-semua {
            background: none; border: none; font-size: .82rem; color: var(--error);
            cursor: pointer; font-family: 'Jost', sans-serif; font-weight: 500;
            display: flex; align-items: center; gap: .3rem;
            padding: .3rem .6rem; border-radius: var(--radius-sm);
            transition: background .15s;
        }
        .btn-hapus-semua:hover { background: rgba(192,64,58,.08); }

        /* ══ CART ITEM ══ */
        .cart-item {
            display: flex; align-items: center; gap: 1rem;
            padding: 1rem 1.3rem;
            border-bottom: 1px solid rgba(122,101,133,.12);
            transition: background .18s;
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-item:hover { background: rgba(74,44,94,.025); }

        .cart-item-check input[type="checkbox"] {
            accent-color: var(--plum-mid); width: 15px; height: 15px;
            cursor: pointer; display: block; flex-shrink: 0;
        }

        .cart-item-cover {
            width: 66px; height: 88px; flex-shrink: 0;
            border-radius: 8px; overflow: hidden;
            border: 1.5px solid rgba(122,101,133,.25);
            display: flex; align-items: center; justify-content: center;
        }
        .cart-item-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .cart-item-cover svg { width: 22px; height: 22px; fill: rgba(255,255,255,.38); }

        .cart-item-info { flex: 1; min-width: 0; }
        .cart-item-title {
            font-size: .9rem; font-weight: 500; color: var(--ink);
            display: block; white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis; text-decoration: none;
            transition: color .18s; margin-bottom: .18rem;
            font-family: 'Jost', sans-serif;
        }
        .cart-item-title:hover { color: var(--plum-mid); }
        .cart-item-author {
            font-size: .78rem; color: var(--muted);
            margin-bottom: .35rem; font-family: 'Jost', sans-serif;
        }
        .cart-item-cat {
            font-size: .68rem; font-weight: 600;
            background: rgba(74,44,94,.09); color: var(--plum);
            padding: .12rem .5rem; border-radius: 9999px;
            display: inline-block; font-family: 'Jost', sans-serif;
            letter-spacing: .04em;
        }
        .stok-warning {
            font-size: .72rem; color: var(--amber);
            margin-top: .28rem; display: flex; align-items: center; gap: .25rem;
            font-family: 'Jost', sans-serif;
        }

        .cart-item-price {
            font-size: .92rem; font-weight: 600; color: var(--plum);
            white-space: nowrap; font-family: 'Jost', sans-serif;
        }

        /* ══ QTY CONTROL ══ */
        .qty-wrap {
            display: flex; align-items: center; gap: .25rem;
            background: rgba(245,237,224,.7); border-radius: 8px;
            padding: .18rem .28rem;
            border: 1px solid rgba(122,101,133,.2);
        }
        .qty-btn {
            width: 27px; height: 27px; border: none;
            background: var(--white); border-radius: 6px;
            cursor: pointer; font-size: .85rem; font-weight: 700;
            color: var(--ink); transition: background .15s, color .15s;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 1px 3px rgba(74,44,94,.1);
        }
        .qty-btn:hover { background: var(--plum); color: var(--white); }
        .qty-val {
            font-size: .88rem; font-weight: 600;
            min-width: 22px; text-align: center; color: var(--ink);
            font-family: 'Jost', sans-serif;
        }

        .btn-delete {
            width: 30px; height: 30px; background: none; border: none;
            cursor: pointer; color: var(--muted); font-size: .82rem;
            display: flex; align-items: center; justify-content: center;
            transition: color .2s, background .15s; padding: 0;
            border-radius: 6px;
        }
        .btn-delete:hover { color: var(--error); background: rgba(192,64,58,.08); }

        /* ══ SUMMARY PANEL ══ */
        .summary-panel {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border: 1.5px solid #7a6585;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            position: sticky; top: 86px;
        }
        .summary-heading {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.15rem; font-weight: 600; color: var(--ink);
            margin-bottom: 1.1rem; padding-bottom: .75rem;
            border-bottom: 1px solid rgba(122,101,133,.15);
            display: flex; align-items: center; gap: .45rem;
        }
        .summary-heading i { color: var(--plum-light); font-size: .95rem; }

        .summary-row {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: .65rem; font-size: .86rem;
        }
        .summary-row .label { color: var(--muted); font-family: 'Jost', sans-serif; }
        .summary-row .value { font-weight: 500; color: var(--ink); font-family: 'Jost', sans-serif; }

        .summary-divider {
            border: none;
            height: 1px; margin: .9rem 0;
            background: linear-gradient(90deg, transparent, rgba(155,107,181,.25), transparent);
        }
        .summary-total {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.25rem;
        }
        .summary-total .label {
            font-size: .92rem; font-weight: 600; color: var(--ink);
            font-family: 'Jost', sans-serif;
        }
        .summary-total .value {
            font-size: 1.08rem; font-weight: 700; color: var(--plum);
            font-family: 'Jost', sans-serif;
        }

        .btn-checkout {
            width: 100%; padding: .82rem;
            background: var(--plum); color: var(--white);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .92rem;
            font-weight: 500; letter-spacing: .06em; cursor: pointer;
            transition: background .2s, transform .1s;
        }
        .btn-checkout:hover  { background: var(--plum-mid); }
        .btn-checkout:active { transform: scale(.98); }
        .btn-checkout:disabled {
            background: rgba(122,101,133,.2); color: var(--muted); cursor: not-allowed;
        }
        .btn-checkout:disabled:hover { background: rgba(122,101,133,.2); }

        /* ══ EMPTY STATE ══ */
        .empty-state {
            background: rgba(255,255,255,.75);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-lg);
            border: 1.5px solid rgba(122,101,133,.3);
            padding: 5rem 2rem; text-align: center;
        }
        .empty-state i {
            font-size: 3rem; color: rgba(155,107,181,.3);
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
        .btn-empty {
            display: inline-flex; align-items: center; gap: .45rem;
            margin-top: 1.2rem; padding: .45rem .9rem;
            background: var(--plum); color: var(--white);
            border-radius: var(--radius-sm); text-decoration: none;
            font-size: .84rem; font-weight: 600; letter-spacing: .03em;
            font-family: 'Jost', sans-serif;
            transition: background .2s;
        }
        .btn-empty:hover { background: var(--plum-mid); }

        /* ══ MODAL ══ */
        .modal-overlay {
            position: fixed; inset: 0; z-index: 200;
            display: flex; align-items: center; justify-content: center;
            background: rgba(42,26,53,.5);
            opacity: 0; visibility: hidden;
            transition: opacity .25s, visibility .25s;
        }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal-box {
            background: var(--cream); border-radius: var(--radius-lg);
            padding: 1.8rem 1.6rem; width: 100%; max-width: 360px;
            box-shadow: 0 20px 60px rgba(42,26,53,.25);
            border: 1.5px solid rgba(232,197,208,.5);
            transform: translateY(18px); transition: transform .28s;
        }
        .modal-overlay.show .modal-box { transform: translateY(0); }
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
        .modal-actions { display: flex; gap: .75rem; }
        .btn-modal-batal {
            flex: 1; padding: .72rem;
            background: rgba(122,101,133,.1); border: none;
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .9rem;
            font-weight: 500; color: var(--ink); cursor: pointer;
            transition: background .15s;
        }
        .btn-modal-batal:hover { background: rgba(122,101,133,.18); }
        .btn-modal-hapus {
            flex: 1; padding: .72rem;
            background: var(--error); border: none;
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .9rem;
            font-weight: 500; color: var(--white); cursor: pointer;
            transition: background .15s;
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

        @media (max-width: 540px) {
            #logo-name-text { display: none; }
            .cart-item { gap: .7rem; padding: .85rem 1rem; }
            .cart-item-cover { width: 56px; height: 75px; }
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
            <!-- Cart (aktif) -->
            <a href="/literaspace/pages/keranjang.php" class="nav-icon" style="color:var(--plum-mid);">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="nav-badge"><?= min($cart_count, 99) ?></span>
                <?php endif; ?>
            </a>

            <!-- Wishlist -->
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
        <span class="page-eyebrow">✦ Daftar Belanja</span>
        <h1 class="page-title">Keranjang <em>Belanja</em></h1>
        <p class="page-subtitle" id="cart-count-label">
            <?= count($cart_items) ?> item di keranjang
        </p>
    </div>
</div>

<!-- ══ MAIN ══ -->
<main class="page-inner">

    <?php if (empty($cart_items)): ?>
        <div class="empty-state">
            <i class="fas fa-shopping-basket"></i>
            <p class="empty-title">Keranjangmu masih kosong</p>
            <p class="empty-sub">Yuk, temukan buku favorit dan mulai belanja!</p>
            <a href="/literaspace/pages/katalog.php" class="btn-empty">
                <i class></i> Jelajahi Katalog
            </a>
        </div>

    <?php else: ?>

        <div class="cart-layout">

            <!-- ══ CART ITEMS ══ -->
            <div>
                <div class="cart-panel" id="cart-panel">

                    <div class="select-all-bar">
                        <label class="select-all-label">
                            <input type="checkbox" id="check-all" />
                            Pilih Semua
                        </label>
                        <button class="btn-hapus-semua" id="btn-hapus-semua">
                            <i class="fas fa-trash-alt" style="font-size:.78rem;"></i> Hapus
                        </button>
                    </div>

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
                    foreach ($cart_items as $item):
                        $ci = $item['id_buku'] % count($cover_gradients);
                    ?>
                    <div class="cart-item" data-id="<?= $item['id_buku'] ?>" data-harga="<?= $item['harga'] ?>">
                        <div class="cart-item-check">
                            <input type="checkbox" class="item-check" />
                        </div>

                        <div class="cart-item-cover" style="background:<?= $cover_gradients[$ci] ?>;">
                            <?php if (!empty($item['cover_image']) && $item['cover_image'] !== 'default.jpg'): ?>
                                <img src="../assets/covers/<?= htmlspecialchars($item['cover_image']) ?>"
                                     alt="<?= htmlspecialchars($item['judul']) ?>"
                                     onerror="this.style.display='none'" />
                            <?php else: ?>
                                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                            <?php endif; ?>
                        </div>

                        <div class="cart-item-info">
                            <a href="detail.php?id=<?= $item['id_buku'] ?>" class="cart-item-title">
                                <?= htmlspecialchars($item['judul']) ?>
                            </a>
                            <p class="cart-item-author"><?= htmlspecialchars($item['penulis'] ?? '—') ?></p>
                            <span class="cart-item-cat"><?= htmlspecialchars($item['nama_kategori'] ?? 'Umum') ?></span>
                            <?php if ($item['stok'] <= 5 && $item['stok'] > 0): ?>
                                <p class="stok-warning">
                                    <i class="fas fa-exclamation-triangle" style="font-size:.68rem;"></i>
                                    Sisa <?= $item['stok'] ?> stok
                                </p>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.6rem;flex-shrink:0;">
                            <span class="cart-item-price"><?= formatRupiah($item['harga']) ?></span>
                            <div style="display:flex;align-items:center;gap:.45rem;">
                                <div class="qty-wrap">
                                    <button class="qty-btn btn-minus" data-stok="<?= $item['stok'] ?>">−</button>
                                    <span class="qty-val"><?= $item['qty'] ?></span>
                                    <button class="qty-btn btn-plus" data-stok="<?= $item['stok'] ?>">+</button>
                                </div>
                                <button class="btn-delete" data-id="<?= $item['id_buku'] ?>" title="Hapus item">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <!-- ══ SUMMARY PANEL ══ -->
            <aside>
                <div class="summary-panel">
                    <p class="summary-heading">
                        <i class="fas fa-receipt"></i>
                        Ringkasan Belanja
                    </p>

                    <div class="summary-row">
                        <span class="label">Item dipilih</span>
                        <span class="value" id="summary-item-count">0 item</span>
                    </div>
                    <div class="summary-row">
                        <span class="label">Total buku</span>
                        <span class="value" id="summary-qty-total">0 buku</span>
                    </div>

                    <hr class="summary-divider" />

                    <div class="summary-total">
                        <span class="label">Subtotal</span>
                        <span class="value" id="summary-subtotal">Rp 0</span>
                    </div>

                    <button class="btn-checkout" id="btn-checkout" disabled>
                        <i class style="font-size:.78rem;margin-right:.4rem;"></i>
                        Checkout
                    </button>
                </div>
            </aside>

        </div>

    <?php endif; ?>
</main>

<!-- ══ MODAL KONFIRMASI ══ -->
<div class="modal-overlay" id="hapus-modal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-trash-alt" style="color:var(--error);font-size:1.1rem;"></i>
        </div>
        <p class="modal-title" id="modal-title"></p>
        <p class="modal-desc"  id="modal-desc"></p>
        <div class="modal-actions">
            <button class="btn-modal-batal" id="modal-batal">Batal</button>
            <button class="btn-modal-hapus" id="modal-hapus">Hapus</button>
        </div>
    </div>
</div>

<!-- ══ TOAST ══ -->
<div id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg"></span>
</div>

<script>
(function () {
    const API_URL = '/literaspace/pages/hapus_keranjang.php';

    const checkAll      = document.getElementById('check-all');
    const btnHapusSemua = document.getElementById('btn-hapus-semua');
    const modal         = document.getElementById('hapus-modal');
    const modalTitle    = document.getElementById('modal-title');
    const modalDesc     = document.getElementById('modal-desc');
    const modalBatal    = document.getElementById('modal-batal');
    const modalHapus    = document.getElementById('modal-hapus');
    const toast         = document.getElementById('toast');
    const toastMsg      = document.getElementById('toast-msg');

    let pendingAction = null;

    // ── Toast ──────────────────────────────────────────────────────
    function showToast(msg, ok = true) {
        toastMsg.textContent   = msg;
        toast.style.background = ok ? '#2a8a5e' : '#c0403a';
        toast.style.transform  = 'translateY(0)';
        toast.style.opacity    = '1';
        clearTimeout(toast._t);
        toast._t = setTimeout(() => {
            toast.style.transform = 'translateY(80px)';
            toast.style.opacity   = '0';
        }, 2800);
    }

    // ── Modal ──────────────────────────────────────────────────────
    function openModal(title, desc, action) {
        modalTitle.textContent = title;
        modalDesc.textContent  = desc;
        pendingAction          = action;
        modal.classList.add('show');
    }
    function closeModal() {
        modal.classList.remove('show');
        pendingAction = null;
    }

    // ── Animasi hilang baris ───────────────────────────────────────
    function animateRemove(row, cb) {
        row.style.transition = 'opacity .28s, max-height .28s, padding .28s, margin .28s';
        row.style.overflow   = 'hidden';
        row.style.maxHeight  = row.offsetHeight + 'px';
        requestAnimationFrame(() => {
            row.style.opacity   = '0';
            row.style.maxHeight = '0';
            row.style.padding   = '0';
            row.style.margin    = '0';
        });
        setTimeout(() => { row.remove(); if (cb) cb(); }, 300);
    }

    // ── Update label subtitle header ───────────────────────────────
    function updateCartLabel() {
        const remaining = document.querySelectorAll('.cart-item').length;
        const label     = document.getElementById('cart-count-label');
        if (label) label.textContent = remaining + ' item di keranjang';
    }

    // ── API ────────────────────────────────────────────────────────
    async function apiHapus(payload) {
        const res = await fetch(API_URL, {
            method:      'POST',
            headers:     { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body:        JSON.stringify(payload)
        });
        return res.json();
    }

    // ── Hitung ringkasan ───────────────────────────────────────────
    function updateSummary() {
        const checks  = Array.from(document.querySelectorAll('.item-check'));
        const total   = checks.length;
        const checked = checks.filter(c => c.checked);

        if (checkAll) {
            checkAll.indeterminate = false;
            checkAll.checked       = false;
            if (total > 0 && checked.length === total) {
                checkAll.checked = true;
            } else if (checked.length > 0) {
                checkAll.indeterminate = true;
            }
        }

        let qtyTotal = 0, subtotal = 0;
        checked.forEach(c => {
            const row   = c.closest('.cart-item');
            const qty   = parseInt(row.querySelector('.qty-val').textContent, 10);
            const harga = parseInt(row.dataset.harga, 10);
            qtyTotal  += qty;
            subtotal  += qty * harga;
        });

        document.getElementById('summary-item-count').textContent = checked.length + ' item';
        document.getElementById('summary-qty-total').textContent  = qtyTotal + ' buku';
        document.getElementById('summary-subtotal').textContent   = 'Rp ' + subtotal.toLocaleString('id-ID');
        document.getElementById('btn-checkout').disabled          = checked.length === 0;
    }

    // ── Checkbox "Semua" ───────────────────────────────────────────
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.item-check').forEach(c => c.checked = this.checked);
            updateSummary();
        });
    }

    // ── Event delegation ───────────────────────────────────────────
    document.addEventListener('click', function (e) {

        // Tombol hapus per-item
        const delBtn = e.target.closest('.btn-delete');
        if (delBtn) {
            const row    = delBtn.closest('.cart-item');
            const idBuku = parseInt(delBtn.dataset.id, 10);
            openModal(
                'Hapus Produk',
                'Produk yang dihapus akan hilang dari keranjang. Lanjutkan?',
                async function () {
                    try {
                        const result = await apiHapus({ action: 'hapus_item', id_buku: idBuku });
                        if (result.success) {
                            animateRemove(row, () => { updateSummary(); updateCartLabel(); });
                            showToast('Item berhasil dihapus dari keranjang.');
                        } else {
                            showToast(result.message || 'Gagal menghapus item.', false);
                        }
                    } catch { showToast('Terjadi kesalahan, coba lagi.', false); }
                }
            );
            return;
        }

        // Checkbox per-item
        if (e.target.classList.contains('item-check')) {
            updateSummary(); return;
        }

        // Minus qty
        if (e.target.closest('.btn-minus')) {
            const btn   = e.target.closest('.btn-minus');
            const valEl = btn.closest('.qty-wrap').querySelector('.qty-val');
            let qty     = parseInt(valEl.textContent, 10) - 1;
            if (qty < 1) qty = 1;
            valEl.textContent = qty;
            updateSummary(); return;
        }

        // Plus qty
        if (e.target.closest('.btn-plus')) {
            const btn   = e.target.closest('.btn-plus');
            const valEl = btn.closest('.qty-wrap').querySelector('.qty-val');
            const stok  = parseInt(btn.dataset.stok, 10) || 99;
            let qty     = parseInt(valEl.textContent, 10) + 1;
            if (qty > stok) { showToast('Stok tidak mencukupi!', false); return; }
            valEl.textContent = qty;
            updateSummary(); return;
        }
    });

    // ── Hapus Semua ────────────────────────────────────────────────
    if (btnHapusSemua) {
        btnHapusSemua.addEventListener('click', function () {
            const rows = document.querySelectorAll('.cart-item');
            if (!rows.length) return;
            openModal(
                'Hapus Semua Produk',
                'Semua produk di keranjang akan dihapus. Lanjutkan?',
                async function () {
                    try {
                        const result = await apiHapus({ action: 'hapus_semua' });
                        if (result.success) {
                            rows.forEach(r => animateRemove(r, null));
                            setTimeout(() => { updateSummary(); updateCartLabel(); }, 320);
                            showToast('Semua item berhasil dihapus dari keranjang.');
                        } else {
                            showToast(result.message || 'Gagal menghapus semua item.', false);
                        }
                    } catch { showToast('Terjadi kesalahan, coba lagi.', false); }
                }
            );
        });
    }

    // ── Modal actions ──────────────────────────────────────────────
    if (modalBatal) modalBatal.addEventListener('click', closeModal);
    if (modalHapus) modalHapus.addEventListener('click', function () {
        const action = pendingAction;
        closeModal();
        if (action) action();
    });
    if (modal) modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    // ── Checkout ───────────────────────────────────────────────────
    // FIX: kirim item yang dipilih via URL parameter ke pages/checkout.php
    const btnCheckout = document.getElementById('btn-checkout');
    if (btnCheckout) {
        btnCheckout.addEventListener('click', function () {
            const checks = Array.from(document.querySelectorAll('.item-check:checked'));
            if (checks.length === 0) { showToast('Pilih minimal 1 item', false); return; }
            const items = checks.map(c => {
                const row    = c.closest('.cart-item');
                const qty    = parseInt(row.querySelector('.qty-val').textContent, 10);
                const idBuku = parseInt(row.dataset.id, 10);
                return { id_buku: idBuku, qty: qty };
            });
            // Simpan ke sessionStorage (untuk dipakai submitCheckout di checkout.php)
            sessionStorage.setItem('checkout_items', JSON.stringify(items));
            // Kirim juga via URL agar pages/checkout.php (PHP) bisa filter item yang benar
            const encoded = encodeURIComponent(JSON.stringify(items));
            window.location.href = '/literaspace/pages/checkout.php?items=' + encoded;
        });
    }

    updateSummary();
})();
</script>
</body>
</html>