<?php
session_start();
require_once __DIR__ . '/config/db.php';

$pdo            = getDB();
$user_id        = $_SESSION['user_id'] ?? null;
$cart_count     = 0;
$wishlist_count = 0;
$popular_books  = [];
$error          = null;
$wishlisted_ids = [];

try {
    $stmt_popular = $pdo->query("SELECT id_buku, judul, penulis, harga, cover_image FROM buku WHERE is_popular = 1 LIMIT 5");
    $popular_books = $stmt_popular->fetchAll(PDO::FETCH_ASSOC);

    $stmt_fantasy = $pdo->query("SELECT id_buku, judul, penulis, harga, cover_image FROM buku WHERE id_kategori = 3 LIMIT 6");
    $fantasy_books = $stmt_fantasy->fetchAll(PDO::FETCH_ASSOC);

    $stmt_drama = $pdo->query("SELECT id_buku, judul, penulis, harga, cover_image FROM buku WHERE id_kategori = 2 LIMIT 6");
    $drama_books = $stmt_drama->fetchAll(PDO::FETCH_ASSOC);

    $stmt_romance = $pdo->query("SELECT id_buku, judul, penulis, harga, cover_image FROM buku WHERE id_kategori = 1 LIMIT 6");
    $romance_books = $stmt_romance->fetchAll(PDO::FETCH_ASSOC);

    $stmt_historis = $pdo->query("SELECT id_buku, judul, penulis, harga, cover_image FROM buku WHERE id_kategori = 4 LIMIT 6");
    $historis_books = $stmt_historis->fetchAll(PDO::FETCH_ASSOC);

    if ($user_id) {
        $sc = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?");
        $sc->execute([$user_id]);
        $cart_count = (int)$sc->fetchColumn();

        $sw = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ?");
        $sw->execute([$user_id]);
        $wishlist_count = (int)$sw->fetchColumn();

        $sw2 = $pdo->prepare("SELECT id_buku FROM wishlist WHERE id_user = ?");
        $sw2->execute([$user_id]);
        $wishlisted_ids = $sw2->fetchAll(PDO::FETCH_COLUMN);
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
    <title>Litera Space — Toko Buku Online Terlengkap</title>
    <meta name="description" content="Jelajahi koleksi buku terlengkap di Litera Space." />
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
            --sage:       #b5c9b0;
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

        /* SVG noise grain overlay on whole page */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            opacity: .38;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='600' height='600'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.72' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='600' height='600' filter='url(%23n)' opacity='0.12'/%3E%3C/svg%3E");
            background-size: 300px 300px;
        }

        /* Painterly cloud blobs */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background:
                /* top-left warm blush */
                radial-gradient(ellipse 70% 45% at -5% 5%, rgba(232,180,185,.55) 0%, transparent 65%),
                /* top-right lilac */
                radial-gradient(ellipse 55% 40% at 105% 2%, rgba(200,180,220,.5) 0%, transparent 60%),
                /* center-left ivory */
                radial-gradient(ellipse 60% 35% at 15% 45%, rgba(255,245,235,.7) 0%, transparent 65%),
                /* center-right sky */
                radial-gradient(ellipse 50% 30% at 85% 40%, rgba(185,210,225,.45) 0%, transparent 60%),
                /* bottom-left peach */
                radial-gradient(ellipse 65% 40% at 5% 95%, rgba(240,200,185,.5) 0%, transparent 65%),
                /* bottom-right rose */
                radial-gradient(ellipse 55% 38% at 95% 92%, rgba(220,175,185,.45) 0%, transparent 58%),
                /* mid-center soft white */
                radial-gradient(ellipse 80% 50% at 50% 55%, rgba(255,250,248,.6) 0%, transparent 70%),
                /* base warm ivory */
                linear-gradient(170deg, #f0e6e1 0%, #ede4ec 30%, #e8ecf2 60%, #eee5e0 100%);
        }

        /* All content sits above bg */
        .navbar, section, footer, .ticker-wrap, #toast {
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
            background: none; border: none; cursor: pointer; color: #5c466b;
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

        /* ══ HERO ══ */
        .hero {
            background: var(--plum);
            overflow: hidden; position: relative;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 60% 80% at 80% 50%, rgba(107,63,130,.6) 0%, transparent 70%),
                radial-gradient(ellipse 40% 60% at 10% 90%, rgba(196,136,42,.15) 0%, transparent 60%);
            pointer-events: none;
        }
        .hero-bg-pattern {
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Ccircle cx='30' cy='50' r='18' fill='none' stroke='%23ffffff' stroke-opacity='0.04' stroke-width='1'/%3E%3Ccircle cx='150' cy='30' r='25' fill='none' stroke='%23ffffff' stroke-opacity='0.03' stroke-width='1'/%3E%3Ccircle cx='170' cy='150' r='35' fill='none' stroke='%23ffffff' stroke-opacity='0.03' stroke-width='1'/%3E%3Ccircle cx='40' cy='170' r='20' fill='none' stroke='%23ffffff' stroke-opacity='0.04' stroke-width='1'/%3E%3C/svg%3E");
            background-size: 200px 200px;
            pointer-events: none;
        }
        .hero-petal {
            position: absolute; z-index: 1; opacity: .12;
        }
        .hero-petal-1 { top: 12%; right: 8%; }
        .hero-petal-2 { bottom: 18%; left: 4%; }

        .hero-inner {
            max-width: 1280px; margin: 0 auto;
            padding: 5rem 1.5rem 5.5rem;
            position: relative; z-index: 2;
            display: grid; grid-template-columns: 1fr 1fr;
            align-items: center; gap: 3rem;
        }
        @media (max-width: 720px) {
            .hero-inner { grid-template-columns: 1fr; padding: 3.5rem 1.25rem 4rem; }
            .hero-visual { display: none; }
        }

        .hero-eyebrow {
            display: inline-block; font-size: .72rem; font-weight: 500;
            letter-spacing: .22em; text-transform: uppercase;
            color: rgba(232,197,208,.9); margin-bottom: .9rem;
        }
        .hero-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.2rem, 4vw, 3.4rem);
            font-weight: 600; line-height: 1.12;
            color: var(--white); margin-bottom: 1.1rem;
        }
        .hero-title em { font-style: italic; color: #d4b8ff; }
        .hero-body {
            font-size: .95rem; color: rgba(255,255,255,.7);
            line-height: 1.7; margin-bottom: 2.2rem;
        }
        .btn-primary {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .8rem 1.7rem;
            background: var(--amber); color: var(--ink);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .9rem; font-weight: 600;
            text-decoration: none; cursor: pointer; letter-spacing: .06em;
            transition: background .2s, transform .15s, box-shadow .2s;
        }
        .btn-primary:hover {
            background: #d4981a;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(196,136,42,.3);
        }
        .btn-primary:active { transform: scale(.98); }

        /* ══ ILLUSTRATED BOOK STACK (hero visual) ══ */
        .hero-visual {
            display: flex; align-items: center; justify-content: center;
        }

        .book-pile {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
            width: 260px;
        }

        /* Each book is a horizontal spine */
        .book-spine {
            width: 100%;
            height: 44px;
            border-radius: 4px 14px 14px 4px;
            position: relative;
            display: flex;
            align-items: center;
            padding-left: 16px;
            cursor: default;
            box-shadow:
                0 4px 12px rgba(0,0,0,.25),
                inset 0 1px 0 rgba(255,255,255,.18),
                inset 0 -1px 0 rgba(0,0,0,.12);
            transform-origin: center;
            transition: transform .25s cubic-bezier(.34,1.56,.64,1), box-shadow .25s;
            margin-bottom: 3px;
        }
        .book-spine:hover {
            transform: translateX(-8px) scale(1.03);
            box-shadow: 6px 8px 24px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.22);
            z-index: 10;
        }

        /* Spine left side darker strip */
        .book-spine::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0;
            width: 14px;
            background: rgba(0,0,0,.22);
            border-radius: 4px 0 0 4px;
        }

        /* Shine stripe on top */
        .book-spine::after {
            content: '';
            position: absolute; left: 14px; right: 0; top: 0;
            height: 40%;
            background: linear-gradient(180deg, rgba(255,255,255,.12) 0%, transparent 100%);
            border-radius: 0 14px 0 0;
            pointer-events: none;
        }

        .spine-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: .72rem; font-weight: 600;
            letter-spacing: .08em;
            white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis;
            position: relative; z-index: 1;
            text-shadow: 0 1px 3px rgba(0,0,0,.3);
            max-width: 180px;
        }

        /* Small bookmark ribbon */
        .spine-ribbon {
            position: absolute; right: 12px; top: -4px;
            width: 8px; height: 18px;
            border-radius: 0 0 4px 4px;
            opacity: .9;
        }

        /* Individual book colors */
        .bs-1  { background: linear-gradient(90deg, #5a9e72 0%, #74b88a 40%, #68ae7e 100%); }
        .bs-1 .spine-title { color: #fff; }
        .bs-1 .spine-ribbon { background: #c5e8cf; }

        .bs-2  { background: linear-gradient(90deg, #a04060 0%, #c4587a 40%, #b54e70 100%); }
        .bs-2 .spine-title { color: #ffe8ec; }
        .bs-2 .spine-ribbon { background: #f8c8d8; }

        .bs-3  { background: linear-gradient(90deg, #c8a82c 0%, #e4c445 40%, #d4b434 100%); }
        .bs-3 .spine-title { color: #3a2a00; }
        .bs-3 .spine-ribbon { background: #7a3a18; }

        .bs-4  { background: linear-gradient(90deg, #4a3880 0%, #6a5aac 40%, #5a4a9c 100%); }
        .bs-4 .spine-title { color: #e8e0ff; }
        .bs-4 .spine-ribbon { background: #c8b8ff; }

        .bs-5  { background: linear-gradient(90deg, #c84a30 0%, #e46048 40%, #d45038 100%); }
        .bs-5 .spine-title { color: #fff8f6; }
        .bs-5 .spine-ribbon { background: #ffccc0; }

        .bs-6  { background: linear-gradient(90deg, #2a608a 0%, #4484ae 40%, #3474a0 100%); }
        .bs-6 .spine-title { color: #e4f4ff; }
        .bs-6 .spine-ribbon { background: #a8d8f8; }

        .bs-7  {
            /* Polka-dot-ish: base white with speckled pseudo-decoration */
            background: linear-gradient(90deg, #e8e0d8 0%, #f4ece4 40%, #ece4dc 100%);
        }
        .bs-7 .spine-title { color: #3a2a1a; }
        .bs-7 .spine-ribbon { background: #d4a8c8; }
        /* Tiny dots pattern */
        .bs-7::after {
            background-image: radial-gradient(circle, rgba(160,120,100,.25) 1px, transparent 1px);
            background-size: 8px 8px;
            background-color: transparent;
            top: 0; height: 100%; border-radius: 0 14px 14px 0;
        }

        .bs-8  { background: linear-gradient(90deg, #3a2050 0%, #5a3878 40%, #4a2a68 100%); }
        .bs-8 .spine-title { color: #e8d8ff; }
        .bs-8 .spine-ribbon { background: #b890e8; }

        .bs-9  {
            background: linear-gradient(90deg, #b05020 0%, #d4763c 40%, #c06030 100%);
        }
        .bs-9 .spine-title { color: #fff0e4; }
        .bs-9 .spine-ribbon { background: #f8c88c; }

        .bs-10 {
            background: linear-gradient(90deg, #2a7a58 0%, #48986e 40%, #388a5e 100%);
        }
        .bs-10 .spine-title { color: #e0fff4; }
        .bs-10 .spine-ribbon { background: #90e8c8; }

        /* Stagger float animations */
        @keyframes spineFloat {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-6px); }
        }
        .book-spine:nth-child(odd)  { animation: spineFloat 3.2s ease-in-out infinite; }
        .book-spine:nth-child(even) { animation: spineFloat 2.8s ease-in-out infinite .7s; }
        .book-spine:nth-child(3n)   { animation: spineFloat 3.6s ease-in-out infinite 1.2s; }
        .book-spine:hover { animation: none; }

        /* Shadow under the whole stack */
        .book-pile-shadow {
            width: 200px;
            height: 14px;
            background: radial-gradient(ellipse 100% 100% at 50% 50%, rgba(0,0,0,.25) 0%, transparent 70%);
            margin-top: 4px;
            border-radius: 50%;
        }

        /* Ornament divider in hero */
        .hero-ornament {
            display: flex; align-items: center; gap: 10px;
            margin: 1.4rem 0;
            color: rgba(232,197,208,.4);
        }
        .hero-ornament::before, .hero-ornament::after {
            content: ''; flex: 0 0 40px; height: 1px;
            background: rgba(232,197,208,.3);
        }

        /* ══ TICKER ══ */
        .ticker-wrap {
            background: rgba(253,248,243,.85);
            backdrop-filter: blur(8px);
            border-top: 1px solid rgba(232,197,208,.6);
            border-bottom: 1px solid rgba(232,197,208,.6);
            overflow: hidden; padding: .56rem 0;
        }
        .ticker-track {
            display: flex; width: max-content;
            animation: ticker-scroll 36s linear infinite;
        }
        .ticker-track:hover { animation-play-state: paused; }
        @keyframes ticker-scroll {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }
        .ticker-item {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: 0 2rem;
            font-size: .72rem; font-weight: 500; letter-spacing: .14em;
            text-transform: uppercase; color: var(--muted); white-space: nowrap;
        }
        .ticker-dot {
            width: 4px; height: 4px;
            background: var(--plum-light); border-radius: 50%; flex-shrink: 0;
        }

        /* ══ SECTIONS ══ */
        .section { padding: 3.8rem 0; }
        .section-inner { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem; }

        .section-alt {
            background: rgba(232,197,208,.08);
            position: relative;
        }
        .section-alt::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(155,107,181,.2), transparent);
        }
        .section-alt::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(155,107,181,.12), transparent);
        }

        .section-head {
            display: flex; align-items: flex-end; justify-content: space-between;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .section-eyebrow {
            font-size: .66rem; font-weight: 500; letter-spacing: .22em;
            text-transform: uppercase; color: var(--plum-light); margin-bottom: 4px;
        }
        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.7rem; font-weight: 600; color: var(--ink);
            display: flex; align-items: center; gap: .5rem;
        }
        .section-subtitle { font-size: .83rem; color: var(--muted); margin-top: .2rem; }
        .see-all {
            font-size: .82rem; font-weight: 500; color: var(--plum-mid);
            text-decoration: none; display: flex; align-items: center; gap: .35rem;
            border-bottom: 1px solid rgba(107,63,130,.25);
            transition: border-color .2s; white-space: nowrap;
        }
        .see-all:hover { border-color: var(--plum-mid); }
        .see-all i { font-size: .7rem; transition: transform .2s; }
        .see-all:hover i { transform: translateX(3px); }

        /* ══ BOOK CARD ══ */
        .book-card {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-sm);
            border: 1.5px solid #7a6585;
            overflow: hidden;
            transition: transform .35s cubic-bezier(.34,1.56,.64,1), box-shadow .3s;
        }
        .book-card:hover {
            transform: translateY(-8px) scale(1.025);
            box-shadow: 0 20px 50px rgba(74,44,94,.16);
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
            position: absolute; top: 7px; left: 7px;
            background: rgba(253,248,243,.92); color: var(--plum);
            font-size: .65rem; font-weight: 600; padding: .15rem .55rem;
            border-radius: 9999px; letter-spacing: .06em;
        }
        .book-info { padding: .8rem .75rem; }
        .book-title-link {
            font-size: .84rem; font-weight: 500; color: var(--ink);
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; margin-bottom: .15rem;
            text-decoration: none; transition: color .2s;
        }
        .book-title-link:hover { color: var(--plum-mid); }
        .book-author { font-size: .73rem; color: var(--muted); margin-bottom: .55rem; }
        .book-price { font-size: .9rem; font-weight: 600; color: var(--plum); }
        .btn-cart {
            background: none; border: none; cursor: pointer;
            font-size: .95rem; color: var(--muted); padding: 0;
            transition: color .2s, transform .2s;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-cart:hover { color: var(--plum-mid); transform: scale(1.2); }
        .btn-wish {
            background: none; border: none; cursor: pointer;
            font-size: .95rem; color: var(--muted); padding: 0;
            transition: color .2s, transform .2s;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-wish:hover { color: var(--error); transform: scale(1.2); }
        .btn-wish.wishlisted { color: var(--error); }

        .books-grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; }
        .books-grid-4 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; }
        @media (max-width: 1024px) { .books-grid-5, .books-grid-4 { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 640px)  { .books-grid-5, .books-grid-4 { grid-template-columns: repeat(2, 1fr); } }

        .empty-state {
            background: rgba(255,255,255,.75); border-radius: var(--radius-sm);
            border: 1.5px solid rgba(232,197,208,.35);
            padding: 4rem 2rem; text-align: center;
        }
        .empty-state i { font-size: 2.2rem; color: #d4c2e0; display: block; margin-bottom: .8rem; }
        .empty-state p { font-size: .88rem; color: var(--muted); }

        /* ══ SECTION DIVIDER ══ */
        .section-divider {
            height: 1px; border: none;
            background: linear-gradient(90deg, transparent 0%, rgba(155,107,181,.18) 35%, rgba(155,107,181,.25) 50%, rgba(155,107,181,.18) 65%, transparent 100%);
        }

        /* ══ PROMO BANNER ══ */
        .promo-banner {
            background: var(--plum);
            border-radius: var(--radius-lg);
            padding: 2.8rem 2.8rem;
            display: grid; grid-template-columns: 1fr auto;
            gap: 2rem; align-items: center;
            position: relative; overflow: hidden;
            box-shadow: 0 12px 40px rgba(74,44,94,.25);
            border: 1px solid rgba(232,197,208,.15);
        }
        .promo-banner::before {
            content: ''; position: absolute; right: -80px; top: -80px;
            width: 280px; height: 280px; border-radius: 50%;
            background: rgba(255,255,255,.05); pointer-events: none;
        }
        .promo-banner::after {
            content: ''; position: absolute; right: 80px; bottom: -110px;
            width: 200px; height: 200px; border-radius: 50%;
            background: rgba(107,63,130,.3); pointer-events: none;
        }
        @media (max-width: 640px) { .promo-banner { grid-template-columns: 1fr; } .promo-icon { display: none !important; } }
        .promo-eyebrow {
            font-size: .7rem; font-weight: 500; letter-spacing: .2em;
            text-transform: uppercase; color: rgba(232,197,208,.9); margin-bottom: .5rem;
        }
        .promo-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.7rem; font-weight: 600;
            color: var(--white); margin-bottom: .6rem;
        }
        .promo-body { font-size: .87rem; color: rgba(255,255,255,.65); margin-bottom: 1.4rem; line-height: 1.65; }
        .promo-form { display: flex; gap: .6rem; flex-wrap: wrap; max-width: 480px; }
        .promo-input {
            flex: 1; min-width: 200px;
            padding: .68rem 1rem;
            background: rgba(255,255,255,.1); border: 1.5px solid rgba(232,197,208,.3);
            border-radius: var(--radius-sm); color: var(--white);
            font-family: 'Jost', sans-serif; font-size: .88rem;
            outline: none; transition: border-color .2s, background .2s;
        }
        .promo-input::placeholder { color: rgba(255,255,255,.4); }
        .promo-input:focus { border-color: rgba(232,197,208,.7); background: rgba(255,255,255,.15); }
        .btn-promo {
            padding: .68rem 1.4rem; background: var(--amber); color: var(--ink);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .88rem; font-weight: 600;
            cursor: pointer; white-space: nowrap; letter-spacing: .06em;
            transition: background .2s, transform .15s;
        }
        .btn-promo:hover { background: #d4981a; transform: translateY(-1px); }

        /* ══ FOOTER ══ */
        footer {
            background: var(--ink);
            color: rgba(255,255,255,.6);
            border-top: 1px solid rgba(232,197,208,.1);
        }
        .footer-inner {
            max-width: 1280px; margin: 0 auto;
            padding: 3.5rem 1.5rem 2rem;
            display: grid; grid-template-columns: 2fr 1fr 1fr 1.4fr; gap: 2.5rem;
        }
        @media (max-width: 900px) { .footer-inner { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 540px) { .footer-inner { grid-template-columns: 1fr; } }
        .footer-logo-svg { width: 36px; height: 36px; margin-bottom: .7rem; display: block; }
        .footer-brand {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem; font-weight: 600; color: var(--white);
            letter-spacing: .06em; margin-bottom: .6rem;
        }
        .footer-about { font-size: .82rem; line-height: 1.7; }
        .footer-copy { font-size: .74rem; color: rgba(255,255,255,.3); margin-top: .9rem; }
        .footer-heading {
            font-size: .72rem; font-weight: 600; letter-spacing: .18em;
            text-transform: uppercase; color: var(--white); margin-bottom: .9rem;
        }
        .footer-links { list-style: none; display: flex; flex-direction: column; gap: .5rem; }
        .footer-links a {
            font-size: .82rem; color: rgba(255,255,255,.5);
            text-decoration: none; transition: color .2s;
        }
        .footer-links a:hover { color: rgba(232,197,208,.9); }
        .social-row { display: flex; gap: .6rem; margin-bottom: 1.2rem; }
        .social-btn {
            width: 36px; height: 36px; background: rgba(255,255,255,.07);
            border-radius: 9px; display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,.55); text-decoration: none; font-size: .82rem;
            border: 1px solid rgba(255,255,255,.1);
            transition: background .2s, color .2s, border-color .2s;
        }
        .social-btn:hover { background: var(--plum-mid); color: var(--white); border-color: var(--plum-mid); }
        .contact-row {
            display: flex; align-items: flex-start; gap: .5rem;
            font-size: .82rem; margin-bottom: .5rem;
        }
        .contact-row i { color: var(--plum-light); margin-top: .1rem; font-size: .78rem; flex-shrink: 0; }
        .footer-bottom {
            max-width: 1280px; margin: 0 auto;
            padding: 1.2rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,.07);
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap;
        }
        .footer-bottom p { font-size: .78rem; }
        .pay-imgs { display: flex; gap: .6rem; align-items: center; }

        /* ══ TOAST ══ */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999;
            padding: .7rem 1.1rem; border-radius: var(--radius-sm);
            color: var(--white); font-size: .87rem;
            display: flex; align-items: center; gap: .5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            transform: translateY(80px); opacity: 0;
            transition: all .3s; pointer-events: none;
        }

        @media (max-width: 540px) { #logo-name-text { display: none; } }
    </style>
</head>
<body>

<?php $wishlisted_json = json_encode(array_map('intval', $wishlisted_ids)); ?>

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
                <path d="M37.5 50 Q34 46 36 44" fill="none" stroke="rgba(196,136,42,0.7)" stroke-width="1.2" stroke-linecap="round"/>
                <path d="M42.5 50 Q46 46 44 44" fill="none" stroke="rgba(196,136,42,0.7)" stroke-width="1.2" stroke-linecap="round"/>
                <line x1="73" y1="68" x2="73" y2="28" stroke="rgba(181,201,176,0.7)" stroke-width="1.2"/>
                <path d="M73 28 Q75 22 79 20 Q75 26 73 28Z" fill="rgba(232,197,208,0.8)"/>
                <path d="M73 28 Q69 22 65 22 Q70 26 73 28Z" fill="rgba(232,197,208,0.7)"/>
                <path d="M73 28 Q78 25 82 28 Q77 29 73 28Z" fill="rgba(232,197,208,0.75)"/>
                <path d="M73 28 Q68 25 65 28 Q70 29 73 28Z" fill="rgba(232,197,208,0.65)"/>
                <circle cx="73" cy="28" r="3" fill="rgba(232,150,170,0.9)"/>
            </svg>
            <span class="logo-name" id="logo-name-text">Litera <span>Space</span></span>
        </a>

        <div class="search-wrap">
            <form action="/literaspace/pages/katalog.php" method="GET">
                <input type="search" name="q" placeholder="Cari judul, penulis, atau kategori..." class="search-input" />
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
            <a href="/literaspace/pages/keranjang.php" class="nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?><span class="nav-badge"><?= min($cart_count, 99) ?></span><?php endif; ?>
            </a>
            <a href="/literaspace/pages/wishlist.php" class="nav-icon">
                <i class="far fa-heart"></i>
                <?php if ($wishlist_count > 0): ?><span class="nav-badge"><?= min($wishlist_count, 99) ?></span><?php endif; ?>
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
                <a href="/literaspace/auth/login.php" class="btn-auth">Masuk / Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- ══ HERO ══ -->
<section class="hero">
    <div class="hero-bg-pattern"></div>
    <canvas id="hero-particles" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;opacity:.35;"></canvas>

    <svg class="hero-petal hero-petal-1" width="32" height="42" viewBox="0 0 32 42">
        <ellipse cx="16" cy="21" rx="9" ry="20" fill="none" stroke="white" stroke-width="1.2" transform="rotate(-20,16,21)"/>
    </svg>
    <svg class="hero-petal hero-petal-2" width="24" height="32" viewBox="0 0 24 32">
        <ellipse cx="12" cy="16" rx="7" ry="15" fill="none" stroke="white" stroke-width="1" transform="rotate(15,12,16)"/>
    </svg>

    <div class="hero-inner">
        <div>
            <span class="hero-eyebrow">✦ Toko Buku Online #1 Indonesia</span>
            <h1 class="hero-title">
                Jelajahi Dunia<br>
                <em>Literatur</em> Tanpa Batas
            </h1>
            <div class="hero-ornament"><span style="font-size:.7rem;letter-spacing:.2em;color:rgba(232,197,208,.5);">✦ ✦ ✦</span></div>
            <p class="hero-body">Temukan ribuan judul dari penulis lokal dan mancanegara. Pengiriman cepat, harga terjangkau, dan kualitas terjamin untuk setiap pembaca.</p>
            <a href="/literaspace/pages/katalog.php" class="btn-primary">
                <i class="fas fa-book-open"></i> Lihat Katalog
            </a>
        </div>

        <!-- ══ ILLUSTRATED BOOK STACK ══ -->
        <div class="hero-visual">
  <svg viewBox="0 0 520 420" xmlns="http://www.w3.org/2000/svg" 
       style="width:100%; max-width:480px; display:block; filter: drop-shadow(0 20px 60px rgba(0,0,0,0.4));">
    <defs>
      <clipPath id="shapeClip">
        <path d="M60,10 Q520,10 520,10 L520,420 Q520,420 60,420 Q10,380 20,320 Q-10,270 20,210 Q-10,160 20,100 Q10,40 60,10Z"/>
      </clipPath>
    </defs>

    <!-- Dark bookshelf background -->
    <rect x="0" y="0" width="520" height="420" fill="#1e1028" clip-path="url(#shapeClip)"/>

    <!-- Shelf boards -->
    <rect x="20" y="170" width="500" height="14" rx="3" fill="#3d2a18"/>
    <rect x="20" y="180" width="500" height="4" fill="#2a1c10"/>
    <rect x="20" y="295" width="500" height="14" rx="3" fill="#3d2a18"/>
    <rect x="20" y="305" width="500" height="4" fill="#2a1c10"/>

    <!-- SHELF 1 BOOKS -->
    <rect x="35" y="80" width="28" height="90" rx="2" fill="#5a9e72"/>
    <rect x="35" y="80" width="5" height="90" rx="1" fill="#3d7a52"/>
    <text x="54" y="135" transform="rotate(-90,54,135)" font-family="Georgia,serif" font-size="8" fill="#e0f4e8" text-anchor="middle">Laskar Pelangi</text>

    <rect x="65" y="90" width="22" height="80" rx="2" fill="#c4587a"/>
    <rect x="65" y="90" width="4" height="80" rx="1" fill="#8a3050"/>
    <text x="77" y="133" transform="rotate(-90,77,133)" font-family="Georgia,serif" font-size="7.5" fill="#ffe8ec" text-anchor="middle">Perahu Kertas</text>

    <g transform="rotate(-3,103,123)">
      <rect x="90" y="75" width="26" height="95" rx="2" fill="#c8a82c"/>
      <rect x="90" y="75" width="5" height="95" rx="1" fill="#8a7010"/>
      <text x="104" y="127" transform="rotate(-90,104,127)" font-family="Georgia,serif" font-size="8" fill="#3a2a00" text-anchor="middle">Bumi Manusia</text>
    </g>

    <rect x="118" y="95" width="20" height="75" rx="2" fill="#4a3880"/>
    <rect x="118" y="95" width="4" height="75" rx="1" fill="#2d2050"/>
    <text x="129" y="135" transform="rotate(-90,129,135)" font-family="Georgia,serif" font-size="7" fill="#e8e0ff" text-anchor="middle">Dilan 1990</text>

    <rect x="140" y="82" width="25" height="88" rx="2" fill="#c84a30"/>
    <rect x="140" y="82" width="5" height="88" rx="1" fill="#8a2818"/>
    <text x="153" y="129" transform="rotate(-90,153,129)" font-family="Georgia,serif" font-size="7.5" fill="#fff8f6" text-anchor="middle">Negeri 5 Menara</text>

    <g transform="rotate(-4,205,156)">
      <rect x="170" y="145" width="70" height="22" rx="2" fill="#2a608a"/>
      <rect x="170" y="145" width="70" height="4" rx="1" fill="#1a3f60"/>
      <text x="205" y="160" font-family="Georgia,serif" font-size="7.5" fill="#e4f4ff" text-anchor="middle">Sang Pemimpi</text>
    </g>

    <g transform="rotate(5,256,126)">
      <rect x="245" y="82" width="22" height="88" rx="2" fill="#e8e0d8"/>
      <rect x="245" y="82" width="4" height="88" rx="1" fill="#c0b0a0"/>
      <text x="257" y="129" transform="rotate(-90,257,129)" font-family="Georgia,serif" font-size="7" fill="#3a2a1a" text-anchor="middle">Ayat-Ayat Cinta</text>
    </g>

    <rect x="270" y="88" width="24" height="82" rx="2" fill="#3a2050"/>
    <rect x="270" y="88" width="5" height="82" rx="1" fill="#221030"/>
    <text x="283" y="132" transform="rotate(-90,283,132)" font-family="Georgia,serif" font-size="7.5" fill="#e8d8ff" text-anchor="middle">Filosofi Kopi</text>

    <rect x="296" y="78" width="28" height="92" rx="2" fill="#b05020"/>
    <rect x="296" y="78" width="5" height="92" rx="1" fill="#703010"/>
    <text x="311" y="127" transform="rotate(-90,311,127)" font-family="Georgia,serif" font-size="7.5" fill="#fff0e4" text-anchor="middle">Supernova</text>

    <rect x="326" y="92" width="20" height="78" rx="2" fill="#2a7a58"/>
    <rect x="326" y="92" width="4" height="78" rx="1" fill="#164a34"/>
    <text x="337" y="134" transform="rotate(-90,337,134)" font-family="Georgia,serif" font-size="6.5" fill="#e0fff4" text-anchor="middle">Ronggeng D.P.</text>

    <rect x="348" y="85" width="25" height="85" rx="2" fill="#7b4f9e"/>
    <rect x="348" y="85" width="5" height="85" rx="1" fill="#4a2870"/>
    <text x="361" y="130" transform="rotate(-90,361,130)" font-family="Georgia,serif" font-size="7" fill="#f0e0ff" text-anchor="middle">Saman</text>

    <rect x="395" y="72" width="34" height="98" rx="2" fill="#1a5c8a"/>
    <rect x="395" y="72" width="6" height="98" rx="1" fill="#0e3858"/>
    <text x="413" y="124" transform="rotate(-90,413,124)" font-family="Georgia,serif" font-size="7.5" fill="#d4eeff" text-anchor="middle">Cantik Itu Luka</text>

    <rect x="431" y="90" width="22" height="80" rx="2" fill="#7a9e30"/>
    <rect x="431" y="90" width="4" height="80" rx="1" fill="#4a6818"/>
    <text x="443" y="133" transform="rotate(-90,443,133)" font-family="Georgia,serif" font-size="7" fill="#eeffd0" text-anchor="middle">Rindu</text>

    <rect x="455" y="82" width="26" height="88" rx="2" fill="#d45038"/>
    <rect x="455" y="82" width="5" height="88" rx="1" fill="#8a2818"/>
    <text x="469" y="129" transform="rotate(-90,469,129)" font-family="Georgia,serif" font-size="7.5" fill="#fff8f6" text-anchor="middle">Hujan</text>

    <!-- SHELF 2 BOOKS -->
    <rect x="35" y="200" width="30" height="95" rx="2" fill="#e8783c"/>
    <rect x="35" y="200" width="6" height="95" rx="1" fill="#9a4818"/>
    <text x="51" y="250" transform="rotate(-90,51,250)" font-family="Georgia,serif" font-size="7.5" fill="#fff4ee" text-anchor="middle">Tenggelamnya</text>

    <rect x="67" y="210" width="22" height="85" rx="2" fill="#5078b0"/>
    <rect x="67" y="210" width="4" height="85" rx="1" fill="#2a4880"/>
    <text x="79" y="255" transform="rotate(-90,79,255)" font-family="Georgia,serif" font-size="7" fill="#ddeeff" text-anchor="middle">Laut Bercerita</text>

    <rect x="91" y="195" width="26" height="100" rx="2" fill="#9e5a8a"/>
    <rect x="91" y="195" width="5" height="100" rx="1" fill="#6a3060"/>
    <text x="105" y="247" transform="rotate(-90,105,247)" font-family="Georgia,serif" font-size="7.5" fill="#ffe0f8" text-anchor="middle">Gadis Kretek</text>

    <rect x="119" y="270" width="65" height="22" rx="2" fill="#4a8040"/>
    <rect x="119" y="270" width="65" height="4" rx="1" fill="#2a5020"/>
    <text x="151" y="285" font-family="Georgia,serif" font-size="7.5" fill="#ddffd4" text-anchor="middle">Orang-Orang Biasa</text>

    <g transform="rotate(-6,202,250)">
      <rect x="190" y="205" width="24" height="90" rx="2" fill="#c49030"/>
      <rect x="190" y="205" width="5" height="90" rx="1" fill="#8a6010"/>
      <text x="203" y="252" transform="rotate(-90,203,252)" font-family="Georgia,serif" font-size="7.5" fill="#fff8d4" text-anchor="middle">Aroma Karsa</text>
    </g>

    <rect x="220" y="215" width="20" height="80" rx="2" fill="#e05878"/>
    <rect x="220" y="215" width="4" height="80" rx="1" fill="#903040"/>
    <text x="231" y="257" transform="rotate(-90,231,257)" font-family="Georgia,serif" font-size="7" fill="#ffecf0" text-anchor="middle">Danur</text>

    <rect x="242" y="200" width="28" height="95" rx="2" fill="#38608a"/>
    <rect x="242" y="200" width="6" height="95" rx="1" fill="#1c3858"/>
    <text x="257" y="250" transform="rotate(-90,257,250)" font-family="Georgia,serif" font-size="7.5" fill="#c8e8ff" text-anchor="middle">Matahari</text>

    <rect x="296" y="195" width="32" height="100" rx="2" fill="#508a3c"/>
    <rect x="296" y="195" width="6" height="100" rx="1" fill="#2c5820"/>
    <text x="313" y="248" transform="rotate(-90,313,248)" font-family="Georgia,serif" font-size="7.5" fill="#d8ffcc" text-anchor="middle">Bulan</text>

    <rect x="378" y="192" width="36" height="103" rx="2" fill="#3a4888"/>
    <rect x="378" y="192" width="7" height="103" rx="1" fill="#1e2858"/>
    <text x="397" y="246" transform="rotate(-90,397,246)" font-family="Georgia,serif" font-size="7.5" fill="#d8dcff" text-anchor="middle">Bintang</text>

    <rect x="416" y="208" width="22" height="87" rx="2" fill="#d46040"/>
    <rect x="416" y="208" width="4" height="87" rx="1" fill="#903828"/>
    <text x="428" y="253" transform="rotate(-90,428,253)" font-family="Georgia,serif" font-size="7" fill="#fff4f0" text-anchor="middle">Teluk Alaska</text>

    <rect x="440" y="200" width="26" height="95" rx="2" fill="#6a9060"/>
    <rect x="440" y="200" width="5" height="95" rx="1" fill="#3a6030"/>
    <text x="454" y="250" transform="rotate(-90,454,250)" font-family="Georgia,serif" font-size="7.5" fill="#e8ffe0" text-anchor="middle">Dear Nathan</text>

    <rect x="468" y="215" width="20" height="80" rx="2" fill="#804890"/>
    <rect x="468" y="215" width="4" height="80" rx="1" fill="#502860"/>
    <text x="479" y="257" transform="rotate(-90,479,257)" font-family="Georgia,serif" font-size="7" fill="#f0dcff" text-anchor="middle">Senja</text>

    <!-- Wavy left edge overlay -->
    <path d="M0,0 Q55,60 35,140 Q15,210 45,280 Q65,340 30,420 L0,420Z" fill="#1e1028"/>
  </svg>
</div>
    </div>
</section>

<!-- ══ TICKER ══ -->
<div class="ticker-wrap" aria-label="Promo berjalan">
    <div class="ticker-track">
        <?php
        $tickers = [
            'Buku Terlaris Bulan Ini',
            'Garansi Kepuasan 30 Hari',
            'Koleksi Baru Setiap Minggu',
            'Pilihan Buku untuk Semua Genre',
            'Pengiriman ke Seluruh Indonesia',
            'Buku Original Bergaransi',
        ];
        $all = array_merge($tickers, $tickers);
        foreach ($all as $item): ?>
            <span class="ticker-item">
                <span class="ticker-dot"></span>
                <?= htmlspecialchars($item) ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($error): ?>
<div style="max-width:1280px; margin:.8rem auto; padding:0 1.5rem;">
    <div style="background:#fdf2f2; border:1.5px solid rgba(192,64,58,.2); color:var(--error); border-radius:var(--radius-sm); padding:.75rem 1rem; font-size:.88rem; border-left:3px solid var(--error);">
        <i class="fas fa-exclamation-circle" style="margin-right:.4rem;"></i><?= htmlspecialchars($error) ?>
    </div>
</div>
<?php endif; ?>

<!-- ══ BUKU TERPOPULER ══ -->
<section class="section">
    <div class="section-inner">
        <div class="section-head">
            <div>
                <p class="section-eyebrow">Pilihan Pembaca</p>
                <h2 class="section-title">Buku Terpopuler</h2>
                <p class="section-subtitle">Paling banyak dicari dan disukai oleh pembaca kami</p>
            </div>
        </div>
        <?php if (!empty($popular_books)): ?>
            <?php $cover_gradients = [
                'linear-gradient(135deg,#4a2c5e,#9b6bb5)',
                'linear-gradient(135deg,#2d6a4f,#74b899)',
                'linear-gradient(135deg,#7a2040,#c44a6c)',
                'linear-gradient(135deg,#0f4c75,#3a8ab5)',
                'linear-gradient(135deg,#3d2b00,#8b6200)',
            ]; ?>
            <div class="books-grid-5">
                <?php foreach ($popular_books as $book):
                    $ci = $book['id_buku'] % count($cover_gradients);
                    $is_wishlisted = in_array((int)$book['id_buku'], array_map('intval', $wishlisted_ids));
                ?>
                <div class="book-card">
                    <div class="cover-wrap">
                        <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                            <img src="./assets/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['judul']) ?>" style="width:100%; aspect-ratio:3/4; object-fit:cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                            <div class="cover-placeholder" style="display:none; background:<?= $cover_gradients[$ci] ?>;"><svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg></div>
                        <?php else: ?>
                            <div class="cover-placeholder" style="background:<?= $cover_gradients[$ci] ?>;"><svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg></div>
                        <?php endif; ?>
                    </div>
                    <div class="book-info">
                        <a href="/literaspace/pages/detail.php?id=<?= $book['id_buku'] ?>" class="book-title-link"><?= htmlspecialchars(truncateText($book['judul'], 45)) ?></a>
                        <p class="book-author"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:.4rem;">
                            <span class="book-price"><?= formatRupiah($book['harga']) ?></span>
                            <div style="display:flex; gap:.6rem; align-items:center;">
                                <button class="btn-wish <?= $is_wishlisted ? 'wishlisted' : '' ?>" onclick="tambahWishlist(<?= $book['id_buku'] ?>, this)"><i class="<?= $is_wishlisted ? 'fas' : 'far' ?> fa-heart"></i></button>
                                <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)"><i class="fas fa-cart-plus"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-books"></i><p>Belum ada buku yang tersedia.</p></div>
        <?php endif; ?>
    </div>
</section>

<hr class="section-divider" />

<!-- ══ EPIK & FANTASI ══ -->
<section class="section section-alt">
    <div class="section-inner">
        <div class="section-head">
            <div>
                <p class="section-eyebrow">Genre Pilihan</p>
                <h2 class="section-title">Epik &amp; Fantasi</h2>
                <p class="section-subtitle">Masuki dunia penuh keajaiban dan petualangan tak terbatas</p>
            </div>
            <a href="/literaspace/pages/katalog.php?kategori=3" class="see-all">Lihat Katalog <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php if (!empty($fantasy_books)): ?>
            <?php $fantasy_gradients = [
                'linear-gradient(135deg,#1a1040,#4a2f9a)',
                'linear-gradient(135deg,#2d1b69,#7b5cf6)',
                'linear-gradient(135deg,#0f0c29,#302b63)',
                'linear-gradient(135deg,#3a0f4c,#7b2d8b)',
            ]; ?>
            <div class="books-grid-4">
                <?php foreach ($fantasy_books as $book):
                    $ci = $book['id_buku'] % count($fantasy_gradients);
                    $is_wishlisted = in_array((int)$book['id_buku'], array_map('intval', $wishlisted_ids));
                ?>
                <div class="book-card">
                    <div class="cover-wrap">
                        <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                            <img src="./assets/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['judul']) ?>" style="width:100%; aspect-ratio:3/4; object-fit:cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                            <div class="cover-placeholder" style="display:none; background:<?= $fantasy_gradients[$ci] ?>;"><svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg></div>
                        <?php else: ?>
                            <div class="cover-placeholder" style="background:<?= $fantasy_gradients[$ci] ?>;"><svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg></div>
                        <?php endif; ?>
                        <span class="cat-badge">Fantasi</span>
                    </div>
                    <div class="book-info">
                        <a href="/literaspace/pages/detail.php?id=<?= $book['id_buku'] ?>" class="book-title-link"><?= htmlspecialchars(truncateText($book['judul'], 45)) ?></a>
                        <p class="book-author"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:.4rem;">
                            <span class="book-price"><?= formatRupiah($book['harga']) ?></span>
                            <div style="display:flex; gap:.6rem; align-items:center;">
                                <button class="btn-wish <?= $is_wishlisted ? 'wishlisted' : '' ?>" onclick="tambahWishlist(<?= $book['id_buku'] ?>, this)"><i class="<?= $is_wishlisted ? 'fas' : 'far' ?> fa-heart"></i></button>
                                <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)"><i class="fas fa-cart-plus"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-hat-wizard"></i><p>Belum ada buku fantasi.</p></div>
        <?php endif; ?>
    </div>
</section>

<hr class="section-divider" />

<!-- ══ DRAMA ══ -->
<section class="section">
    <div class="section-inner">
        <div class="section-head">
            <div>
                <p class="section-eyebrow">Genre Pilihan</p>
                <h2 class="section-title">Drama</h2>
                <p class="section-subtitle">Kisah yang menyentuh perasaan dengan berbagai konflik kehidupan</p>
            </div>
            <a href="/literaspace/pages/katalog.php?kategori=2" class="see-all">Lihat Katalog <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php if (!empty($drama_books)): ?>
            <div class="books-grid-4">
                <?php foreach ($drama_books as $book):
                    $is_wishlisted = in_array((int)$book['id_buku'], array_map('intval', $wishlisted_ids));
                ?>
                <div class="book-card">
                    <div class="cover-wrap">
                        <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                            <img src="./assets/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['judul']) ?>" style="width:100%; aspect-ratio:3/4; object-fit:cover; display:block;">
                        <?php else: ?>
                            <div class="cover-placeholder" style="background:linear-gradient(135deg,#2d1b40,#7a4a6e);"><svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg></div>
                        <?php endif; ?>
                        <span class="cat-badge">Drama</span>
                    </div>
                    <div class="book-info">
                        <a href="/literaspace/pages/detail.php?id=<?= $book['id_buku'] ?>" class="book-title-link"><?= htmlspecialchars(truncateText($book['judul'], 45)) ?></a>
                        <p class="book-author"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:.4rem;">
                            <span class="book-price"><?= formatRupiah($book['harga']) ?></span>
                            <div style="display:flex; gap:.6rem; align-items:center;">
                                <button class="btn-wish <?= $is_wishlisted ? 'wishlisted' : '' ?>" onclick="tambahWishlist(<?= $book['id_buku'] ?>, this)"><i class="<?= $is_wishlisted ? 'fas' : 'far' ?> fa-heart"></i></button>
                                <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)"><i class="fas fa-cart-plus"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-masks-theater"></i><p>Belum ada buku drama.</p></div>
        <?php endif; ?>
    </div>
</section>

<hr class="section-divider" />

<!-- ══ ROMANCE ══ -->
<section class="section section-alt">
    <div class="section-inner">
        <div class="section-head">
            <div>
                <p class="section-eyebrow">Genre Pilihan</p>
                <h2 class="section-title">Romance</h2>
                <p class="section-subtitle">Kisah romantis yang membawa kehangatan dalam setiap momen</p>
            </div>
            <a href="/literaspace/pages/katalog.php?kategori=1" class="see-all">Lihat Katalog <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php if (!empty($romance_books)): ?>
            <div class="books-grid-4">
                <?php foreach ($romance_books as $book):
                    $is_wishlisted = in_array((int)$book['id_buku'], array_map('intval', $wishlisted_ids));
                ?>
                <div class="book-card">
                    <div class="cover-wrap">
                        <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                            <img src="./assets/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['judul']) ?>" style="width:100%; aspect-ratio:3/4; object-fit:cover; display:block;">
                        <?php else: ?>
                            <div class="cover-placeholder" style="background:linear-gradient(135deg,#7a2040,#c44a6c);"><svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg></div>
                        <?php endif; ?>
                        <span class="cat-badge">Romance</span>
                    </div>
                    <div class="book-info">
                        <a href="/literaspace/pages/detail.php?id=<?= $book['id_buku'] ?>" class="book-title-link"><?= htmlspecialchars(truncateText($book['judul'], 45)) ?></a>
                        <p class="book-author"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:.4rem;">
                            <span class="book-price"><?= formatRupiah($book['harga']) ?></span>
                            <div style="display:flex; gap:.6rem; align-items:center;">
                                <button class="btn-wish <?= $is_wishlisted ? 'wishlisted' : '' ?>" onclick="tambahWishlist(<?= $book['id_buku'] ?>, this)"><i class="<?= $is_wishlisted ? 'fas' : 'far' ?> fa-heart"></i></button>
                                <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)"><i class="fas fa-cart-plus"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-heart"></i><p>Belum ada buku romance.</p></div>
        <?php endif; ?>
    </div>
</section>

<hr class="section-divider" />

<!-- ══ HISTORIS ══ -->
<section class="section">
    <div class="section-inner">
        <div class="section-head">
            <div>
                <p class="section-eyebrow">Genre Pilihan</p>
                <h2 class="section-title">Historis</h2>
                <p class="section-subtitle">Jelajahi cerita berlatar sejarah masa lampau yang menginspirasi</p>
            </div>
            <a href="/literaspace/pages/katalog.php?kategori=4" class="see-all">Lihat Katalog <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php if (!empty($historis_books)): ?>
            <div class="books-grid-4">
                <?php foreach ($historis_books as $book):
                    $is_wishlisted = in_array((int)$book['id_buku'], array_map('intval', $wishlisted_ids));
                ?>
                <div class="book-card">
                    <div class="cover-wrap">
                        <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                            <img src="./assets/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['judul']) ?>" style="width:100%; aspect-ratio:3/4; object-fit:cover; display:block;">
                        <?php else: ?>
                            <div class="cover-placeholder" style="background:linear-gradient(135deg,#3d2b00,#8b6200);"><svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg></div>
                        <?php endif; ?>
                        <span class="cat-badge">Historis</span>
                    </div>
                    <div class="book-info">
                        <a href="/literaspace/pages/detail.php?id=<?= $book['id_buku'] ?>" class="book-title-link"><?= htmlspecialchars(truncateText($book['judul'], 45)) ?></a>
                        <p class="book-author"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:.4rem;">
                            <span class="book-price"><?= formatRupiah($book['harga']) ?></span>
                            <div style="display:flex; gap:.6rem; align-items:center;">
                                <button class="btn-wish <?= $is_wishlisted ? 'wishlisted' : '' ?>" onclick="tambahWishlist(<?= $book['id_buku'] ?>, this)"><i class="<?= $is_wishlisted ? 'fas' : 'far' ?> fa-heart"></i></button>
                                <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)"><i class="fas fa-cart-plus"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-landmark"></i><p>Belum ada buku historis.</p></div>
        <?php endif; ?>
    </div>
</section>

<!-- ══ PROMO BANNER ══ -->
<section class="section section-alt">
    <div class="section-inner">
        <div class="promo-banner">
            <div style="position:relative; z-index:1;">
                <p class="promo-eyebrow">✦ Penawaran Eksklusif</p>
                <h3 class="promo-title">Dapatkan Diskon Spesial!</h3>
                <p class="promo-body">Daftarkan email Anda dan dapatkan voucher diskon hingga 25%<br>untuk pembelian pertama.</p>
                <div class="promo-form">
                    <input type="email" placeholder="Masukkan email Anda..." class="promo-input" />
                    <button type="button" class="btn-promo">Berlangganan</button>
                </div>
            </div>
            <div class="promo-icon" style="position:relative; z-index:1; flex-shrink:0; display:flex; align-items:center; justify-content:center; width:120px;">
                <svg width="90" height="90" viewBox="0 0 90 90" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity:.25;">
                    <rect x="10" y="20" width="18" height="52" rx="2" fill="none" stroke="white" stroke-width="1.5"/>
                    <rect x="29" y="12" width="22" height="60" rx="2" fill="none" stroke="white" stroke-width="1.5"/>
                    <rect x="52" y="22" width="17" height="50" rx="2" fill="none" stroke="white" stroke-width="1.5"/>
                    <path d="M10 52 Q19 48 29 50 Q40 52 51 50 Q60 48 69 52" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="73" y1="68" x2="73" y2="28" stroke="white" stroke-width="1.5"/>
                    <circle cx="73" cy="28" r="3.5" fill="white"/>
                </svg>
            </div>
        </div>
    </div>
</section>

<!-- ══ FOOTER ══ -->
<footer>
    <div class="footer-inner">
        <div>
            <svg class="footer-logo-svg" viewBox="0 0 90 90" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="10" y="20" width="18" height="52" rx="2" fill="rgba(255,255,255,0.12)" stroke="rgba(255,255,255,0.4)" stroke-width="1"/>
                <rect x="29" y="12" width="22" height="60" rx="2" fill="rgba(255,255,255,0.15)" stroke="rgba(255,255,255,0.45)" stroke-width="1"/>
                <ellipse cx="40" cy="28" rx="6" ry="7" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width=".9"/>
                <rect x="52" y="22" width="17" height="50" rx="2" fill="rgba(255,255,255,0.1)" stroke="rgba(255,255,255,0.35)" stroke-width="1"/>
                <path d="M10 52 Q19 48 29 50 Q40 52 51 50 Q60 48 69 52" fill="none" stroke="rgba(196,136,42,0.8)" stroke-width="1.5" stroke-linecap="round"/>
                <line x1="73" y1="68" x2="73" y2="28" stroke="rgba(181,201,176,0.7)" stroke-width="1.2"/>
                <circle cx="73" cy="28" r="3" fill="rgba(232,197,208,0.9)"/>
            </svg>
            <p class="footer-brand">Litera Space</p>
            <p class="footer-about">Toko buku online terpercaya dengan koleksi lengkap dari berbagai genre dan penulis terbaik.</p>
            <p class="footer-copy">© 2026 Litera Space. Semua hak dilindungi.</p>
        </div>
        <div>
            <p class="footer-heading">Bantuan</p>
            <ul class="footer-links">
                <li><a href="#">FAQ</a></li>
                <li><a href="#">Lacak Pesanan</a></li>
                <li><a href="#">Kebijakan Pengembalian</a></li>
                <li><a href="#">Hubungi Kami</a></li>
            </ul>
        </div>
        <div>
            <p class="footer-heading">Informasi</p>
            <ul class="footer-links">
                <li><a href="#">Tentang Kami</a></li>
                <li><a href="#">Syarat &amp; Ketentuan</a></li>
                <li><a href="#">Privasi</a></li>
                <li><a href="#">Blog</a></li>
            </ul>
        </div>
        <div>
            <p class="footer-heading">Ikuti Kami</p>
            <div class="social-row">
                <a href="https://www.instagram.com/literaspace___" class="social-btn"><i class="fab fa-instagram"></i></a>
                <a href="https://www.tiktok.com/@literaspace___" class="social-btn"><i class="fab fa-tiktok"></i></a>
            </div>
            <div class="contact-row"><i class="fas fa-phone"></i><span>+62 812 3456 7890</span></div>
            <div class="contact-row"><i class="fas fa-envelope"></i><span>literaspace@gmail.com</span></div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>Dipercaya oleh lebih dari 50.000+ pembaca di seluruh Indonesia</p>
    </div>
</footer>

<div id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg">Berhasil!</span>
</div>

<script>
const wishlistedIds = new Set(<?= $wishlisted_json ?>);

// ── PARTIKEL HERO ──────────────────────────────────────────────────────────────
(function () {
    const canvas = document.getElementById('hero-particles');
    if (!canvas) return;
    const ctx  = canvas.getContext('2d');
    const hero = canvas.parentElement;
    let W, H;
    function resize() { W = canvas.width = hero.offsetWidth; H = canvas.height = hero.offsetHeight; }
    resize();
    window.addEventListener('resize', resize);
    const particles = Array.from({ length: 60 }, () => ({
        x: Math.random() * 1400, y: Math.random() * 600,
        r: Math.random() * 2 + .5,
        dx: (Math.random() - .5) * .4, dy: -(Math.random() * .5 + .15),
        o: Math.random() * .5 + .15,
    }));
    function draw() {
        ctx.clearRect(0, 0, W, H);
        particles.forEach(p => {
            ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(232,197,208,${p.o})`; ctx.fill();
            p.x += p.dx; p.y += p.dy;
            if (p.y < -6) p.y = H + 6;
            if (p.x < -6) p.x = W + 6;
            if (p.x > W+6) p.x = -6;
        });
        requestAnimationFrame(draw);
    }
    draw();
})();

// ── TOAST ──────────────────────────────────────────────────────────────────────
function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    t.style.background = ok ? '#2a8a5e' : '#c0403a';
    t.style.transform = 'translateY(0)'; t.style.opacity = '1';
    setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 2800);
}

// ── UPDATE BADGE ────────────────────────────────────────────────────────────────
function updateBadge(type, delta) {
    const selector = type === 'cart' ? 'a[href*="keranjang"] .nav-badge' : 'a[href*="wishlist"] .nav-badge';
    const linkSel  = type === 'cart' ? 'a[href*="keranjang"]'            : 'a[href*="wishlist"]';
    let badge = document.querySelector(selector);
    const link  = document.querySelector(linkSel);
    if (!badge) {
        badge = document.createElement('span'); badge.className = 'nav-badge'; badge.textContent = '0';
        link.appendChild(badge);
    }
    let count = parseInt(badge.textContent) || 0;
    count += delta;
    if (count <= 0) { badge.remove(); } else { badge.textContent = Math.min(count, 99); }
}

// ── KERANJANG ──────────────────────────────────────────────────────────────────
function tambahKeranjang(idBuku, btn) {
    <?php if (!$user_id): ?>
        window.location.href = '/literaspace/auth/login.php'; return;
    <?php endif; ?>
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('/literaspace/api/keranjang.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty: 1 })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Buku ditambahkan ke keranjang!');
            updateBadge('cart', 1);
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => { btn.innerHTML = '<i class="fas fa-cart-plus"></i>'; btn.disabled = false; }, 2000);
        } else {
            showToast(d.message || 'Gagal menambahkan.', false);
            btn.innerHTML = '<i class="fas fa-cart-plus"></i>'; btn.disabled = false;
        }
    })
    .catch(() => { showToast('Terjadi kesalahan.', false); btn.innerHTML = '<i class="fas fa-cart-plus"></i>'; btn.disabled = false; });
}

// ── WISHLIST ───────────────────────────────────────────────────────────────────
function tambahWishlist(idBuku, btn) {
    <?php if (!$user_id): ?>
        window.location.href = '/literaspace/auth/login.php'; return;
    <?php endif; ?>
    fetch('/literaspace/api/wishlist/add.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
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
</script>
</body>
</html>