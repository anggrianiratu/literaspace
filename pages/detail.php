<?php
// ========================================
// DETAIL.PHP - LITERASPACE
// Halaman Detail Buku
// Tema: Pastel Painterly (selaras katalog.php)
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
$in_wishlist = false;

$id_buku = (int)($_GET['id'] ?? 0);

if ($id_buku <= 0) {
    header('Location: kategori.php');
    exit;
}

try {
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

    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT id_review, rating, komentar
            FROM review
            WHERE id_user = ? AND id_buku = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $id_buku]);
        $user_review = $stmt->fetch(PDO::FETCH_ASSOC);

        $sc = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?");
        $sc->execute([$user_id]);
        $cart_count = (int)$sc->fetchColumn();

        $sw = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ?");
        $sw->execute([$user_id]);
        $wishlist_count = (int)$sw->fetchColumn();

        $swb = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ? AND id_buku = ?");
        $swb->execute([$user_id, $id_buku]);
        $in_wishlist = (int)$swb->fetchColumn() > 0;
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

function starHtml($rating, $size = '0.78rem') {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= round($rating)) {
            $html .= "<i class=\"fas fa-star\" style=\"color:#c4882a;font-size:{$size};\"></i>";
        } else {
            $html .= "<i class=\"far fa-star\" style=\"color:#d4c2e0;font-size:{$size};\"></i>";
        }
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
            --shadow-lg:  0 12px 40px rgba(74,44,94,.15);
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

        .navbar, .page-header, .page-inner, #toast {
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

        /* ══ PAGE HEADER ══ */
        .page-header {
            background: var(--plum);
            position: relative; overflow: hidden;
            padding: 2.2rem 1.5rem 2.6rem;
        }
        .page-header::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 60% 80% at 80% 50%, rgba(107,63,130,.6) 0%, transparent 70%),
                radial-gradient(ellipse 40% 60% at 10% 90%, rgba(196,136,42,.15) 0%, transparent 60%);
            pointer-events: none;
        }
        .page-header-inner {
            max-width: 1100px; margin: 0 auto;
            position: relative; z-index: 2;
        }
        .page-eyebrow {
            font-size: .7rem; font-weight: 500; letter-spacing: .22em;
            text-transform: uppercase; color: #9b6bb5;
            margin-bottom: .45rem; display: block;
        }

        /* ══ BREADCRUMB ══ */
        .breadcrumb {
            display: flex; gap: .5rem; align-items: center;
            font-size: .82rem; color: rgba(155,107,181,.8);
            flex-wrap: wrap;
        }
        .breadcrumb a {
            color: rgba(232,197,208,.85); text-decoration: none;
            transition: color .2s; font-family: 'Jost', sans-serif;
        }
        .breadcrumb a:hover { color: var(--white); }
        .breadcrumb span.sep { color: rgba(155,107,181,.5); }
        .breadcrumb span.current { color: rgba(253,248,243,.7); font-weight: 500; }

        /* ══ PAGE INNER ══ */
        .page-inner {
            max-width: 1100px; margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        /* ══ DETAIL CARD ══ */
        .detail-card {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-lg);
            border: 1.5px solid #7a6585;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .detail-layout {
            display: grid;
            grid-template-columns: 270px 1fr;
        }

        @media (max-width: 768px) {
            .detail-layout { grid-template-columns: 1fr; }
        }

        /* ── Cover Column ── */
        .cover-column {
            padding: 2rem 1.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.1rem;
            background: rgba(245,237,224,.45);
            border-right: 1.5px solid rgba(122,101,133,.2);
        }

        @media (max-width: 768px) {
            .cover-column { border-right: none; border-bottom: 1.5px solid rgba(122,101,133,.2); }
        }

        .cover-img {
            width: 100%; max-width: 200px;
            aspect-ratio: 3/4;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 18px 48px rgba(74,44,94,.22), 0 4px 12px rgba(74,44,94,.12);
            display: flex; align-items: center; justify-content: center;
            position: relative;
            background: linear-gradient(135deg, #4a2c5e, #9b6bb5);
        }
        .cover-img img { width: 100%; height: 100%; object-fit: cover; }
        .cover-img svg { width: 52px; height: 52px; fill: rgba(255,255,255,.3); }

        /* Qty */
        .qty-row {
            display: flex; align-items: center; gap: .55rem;
            width: 100%; max-width: 200px;
        }
        .qty-btn {
            width: 34px; height: 34px;
            border: 1.5px solid #7a6585;
            background: var(--white);
            border-radius: var(--radius-sm);
            font-size: 1rem; font-weight: 700;
            color: var(--plum);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: border-color .2s, background .2s;
            flex-shrink: 0;
        }
        .qty-btn:hover { border-color: var(--plum-mid); background: var(--parchment); }
        .qty-input {
            flex: 1; height: 34px;
            border: 1.5px solid #7a6585;
            border-radius: var(--radius-sm);
            text-align: center;
            font-family: 'Jost', sans-serif;
            font-size: .95rem; font-weight: 600;
            color: var(--ink);
            outline: none; background: var(--white);
            padding: 0; line-height: 34px; appearance: textfield; -moz-appearance: textfield; transform: translateY(-1px);
        }
        .qty-input::-webkit-outer-spin-button,
        .qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .qty-input:focus { border-color: var(--plum-light); }

        .action-row {
            display: flex; gap: .55rem;
            width: 100%; max-width: 200px;
        }

        .btn-cart-main {
            flex: 1;
            padding: .65rem .5rem;
            background: var(--plum);
            color: var(--white);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif;
            font-size: .84rem; font-weight: 500;
            letter-spacing: .04em;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            transition: background .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(74,44,94,.25);
        }
        .btn-cart-main:hover { background: var(--plum-mid); box-shadow: 0 6px 20px rgba(107,63,130,.35); }
        .btn-cart-main:active { transform: scale(.97); }
        .btn-cart-main:disabled { background: #b0a0bc; box-shadow: none; cursor: not-allowed; }

        .btn-buynow {
            width: 100%; max-width: 200px;
            padding: .65rem .5rem;
            background: transparent;
            color: var(--plum);
            border: 1.5px solid #7a6585;
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif;
            font-size: .84rem; font-weight: 500;
            letter-spacing: .04em;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            transition: background .2s, color .2s, border-color .2s;
        }
        .btn-buynow:hover { background: var(--plum); color: var(--white); border-color: var(--plum); }

        .btn-wishlist {
            width: 40px; height: 40px; flex-shrink: 0;
            background: var(--white);
            border: 1.5px solid #7a6585;
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: border-color .2s, background .2s, color .2s;
            color: var(--muted);
            font-size: .95rem;
        }
        .btn-wishlist:hover { color: var(--error); background: rgba(192,64,58,.06); border-color: rgba(192,64,58,.3); }
        .btn-wishlist.active { color: var(--error); border-color: rgba(192,64,58,.3); }

        /* ── Info Column ── */
        .info-column {
            padding: 2rem 2.25rem;
            display: flex;
            flex-direction: column;
            gap: 1.1rem;
        }

        .category-badge {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .25rem .75rem;
            background: rgba(74,44,94,.08);
            color: var(--plum);
            border: 1px solid rgba(107,63,130,.2);
            border-radius: 9999px;
            font-family: 'Jost', sans-serif;
            font-size: .7rem; font-weight: 600;
            letter-spacing: .12em; text-transform: uppercase;
            align-self: flex-start;
        }

        .book-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(1.5rem, 3vw, 2.1rem);
            font-weight: 600;
            color: var(--ink);
            line-height: 1.2;
        }

        .book-author {
            font-size: .92rem;
            color: var(--muted);
            font-family: 'Jost', sans-serif;
        }
        .book-author strong { color: var(--ink); font-weight: 500; }

        .rating-row {
            display: flex; align-items: center; gap: .55rem;
        }
        .rating-num {
            font-size: .95rem; font-weight: 600; color: var(--amber);
            font-family: 'Jost', sans-serif;
        }
        .rating-stars-row { display: flex; gap: .12rem; align-items: center; }
        .rating-reviews {
            font-size: .8rem; color: var(--muted); font-family: 'Jost', sans-serif;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(155,107,181,.2), transparent);
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: .65rem 1.25rem;
        }
        .meta-item { display: flex; flex-direction: column; gap: .1rem; }
        .meta-label {
            font-size: .68rem; font-weight: 600; color: var(--muted);
            text-transform: uppercase; letter-spacing: .16em;
            font-family: 'Jost', sans-serif;
        }
        .meta-value {
            font-size: .88rem; color: var(--ink); font-weight: 500;
            font-family: 'Jost', sans-serif;
        }

        .deskripsi-text {
            font-size: .88rem; color: var(--muted);
            line-height: 1.8; text-align: justify;
            font-family: 'Jost', sans-serif; max-width: 95%;
        }

        .price-row {
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        }
        .price-main {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem; font-weight: 600;
            color: var(--plum); letter-spacing: -.01em;
        }
        .stok-badge {
            display: inline-flex; align-items: center; gap: .35rem;
            padding: .3rem .75rem;
            border-radius: 9999px;
            font-size: .75rem; font-weight: 600; font-family: 'Jost', sans-serif;
        }
        .stok-badge.tersedia { background: rgba(42,138,94,.1); color: #2a8a5e; }
        .stok-badge.terbatas { background: rgba(196,136,42,.12); color: #c4882a; }
        .stok-badge.habis    { background: rgba(192,64,58,.1);  color: var(--error); }

        /* ══ SECTION CARD ══ */
        .section-card {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-lg);
            border: 1.5px solid #7a6585;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.35rem; font-weight: 600;
            color: var(--ink);
            margin-bottom: 1.25rem;
            padding-bottom: .75rem;
            border-bottom: 1px solid rgba(155,107,181,.2);
            display: flex; align-items: center; gap: .5rem;
        }
        .section-title i { color: var(--plum-light); font-size: 1rem; }

        /* ══ REVIEW FORM ══ */
        .review-form-box {
            background: rgba(245,237,224,.4);
            border: 1.5px solid rgba(122,101,133,.25);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .review-form-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem; font-weight: 600; color: var(--ink);
            margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: .45rem;
        }
        .review-form-title i { color: var(--plum-light); }

        .form-label {
            display: block;
            font-size: .68rem; font-weight: 600; color: var(--muted);
            text-transform: uppercase; letter-spacing: .16em;
            margin-bottom: .5rem; font-family: 'Jost', sans-serif;
        }
        .star-selector {
            display: flex; gap: .45rem; margin-bottom: .35rem;
        }
        .star-selector i {
            font-size: 1.55rem; cursor: pointer;
            transition: color .15s, transform .1s;
        }
        .star-selector i:hover { transform: scale(1.2); }
        .star-label {
            font-size: .78rem; color: var(--muted); margin-bottom: 1.25rem;
            font-family: 'Jost', sans-serif;
        }
        .review-textarea {
            width: 100%; padding: .85rem 1rem;
            border: 1.5px solid #7a6585;
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .88rem; color: var(--ink);
            resize: vertical; outline: none; min-height: 110px;
            transition: border-color .2s, box-shadow .2s;
            background: rgba(255,255,255,.9);
        }
        .review-textarea:focus {
            border-color: var(--plum-light);
            box-shadow: 0 0 0 3px rgba(107,63,130,.08);
        }
        .char-count {
            font-size: .74rem; color: var(--muted); font-family: 'Jost', sans-serif;
            text-align: right; margin-top: .3rem; margin-bottom: 1.25rem;
        }
        .review-form-actions { display: flex; gap: .75rem; }
        .btn-submit-review {
            flex: 1;
            padding: .68rem 1rem;
            background: var(--plum); color: var(--white);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif;
            font-size: .88rem; font-weight: 500; letter-spacing: .05em;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            transition: background .2s;
        }
        .btn-submit-review:hover { background: var(--plum-mid); }
        .btn-submit-review:disabled { background: #b0a0bc; cursor: not-allowed; }
        .btn-reset-review {
            padding: .68rem 1.25rem;
            background: rgba(255,255,255,.8); color: var(--muted);
            border: 1.5px solid #7a6585; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .88rem; font-weight: 500;
            cursor: pointer; transition: border-color .2s, background .2s;
        }
        .btn-reset-review:hover { border-color: var(--plum-light); background: var(--white); }

        /* Already reviewed note */
        .reviewed-note {
            display: flex; align-items: center; gap: .75rem;
            padding: .9rem 1.1rem;
            background: rgba(42,138,94,.06);
            border: 1.5px solid rgba(42,138,94,.15);
            border-radius: 12px; margin-bottom: 2rem;
        }
        .reviewed-note i { color: var(--success); font-size: 1.1rem; flex-shrink: 0; }
        .reviewed-note-label { font-weight: 600; font-size: .88rem; color: var(--ink); font-family: 'Jost', sans-serif; }
        .reviewed-note-sub { font-size: .8rem; color: var(--muted); margin-top: .2rem; display: flex; align-items: center; gap: .35rem; font-family: 'Jost', sans-serif; }

        /* ══ REVIEW LIST ══ */
        .reviews-list { display: flex; flex-direction: column; }

        .review-item {
            padding: 1.4rem 0;
            border-bottom: 1px solid rgba(155,107,181,.12);
            animation: fadeIn .3s ease;
        }
        .review-item:first-child { padding-top: 0; }
        .review-item:last-child  { border-bottom: none; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        .review-top {
            display: flex; align-items: flex-start;
            gap: .9rem; margin-bottom: .65rem;
        }
        .reviewer-avatar {
            width: 38px; height: 38px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--plum), var(--plum-light));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem; font-weight: 700; color: var(--white);
            font-family: 'Jost', sans-serif;
        }
        .reviewer-info { flex: 1; }
        .reviewer-name { font-weight: 600; font-size: .9rem; color: var(--ink); font-family: 'Jost', sans-serif; }
        .reviewer-meta {
            display: flex; align-items: center; gap: .5rem; margin-top: .2rem;
        }
        .reviewer-stars { display: flex; gap: .1rem; }
        .reviewer-date { font-size: .74rem; color: var(--muted); font-family: 'Jost', sans-serif; }
        .review-body {
            font-size: .88rem; color: var(--muted);
            line-height: 1.7; padding-left: 50px;
            font-family: 'Jost', sans-serif;
        }

        .no-reviews {
            text-align: center; padding: 3rem 2rem; color: var(--muted);
            font-family: 'Jost', sans-serif;
        }
        .no-reviews i { font-size: 2.2rem; color: #d4c2e0; margin-bottom: .75rem; display: block; }
        .no-reviews p { font-size: .9rem; }
        .no-reviews small { font-size: .82rem; color: #b0a0bc; }

        /* ══ LOGIN PROMPT ══ */
        .login-prompt {
            padding: 2rem; border-radius: 14px;
            text-align: center;
            border: 1.5px dashed rgba(122,101,133,.3);
            background: rgba(245,237,224,.3);
            margin-bottom: 2rem;
        }
        .login-prompt i { font-size: 1.8rem; color: #d4c2e0; margin-bottom: .65rem; display: block; }
        .login-prompt p { color: var(--muted); margin-bottom: 1.1rem; font-size: .88rem; font-family: 'Jost', sans-serif; }
        .btn-login-link {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .62rem 1.4rem;
            background: var(--plum); color: var(--white);
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .86rem; font-weight: 500;
            letter-spacing: .05em; text-decoration: none; transition: background .2s;
        }
        .btn-login-link:hover { background: var(--plum-mid); }

        /* ══ TOAST ══ */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
            padding: .7rem 1.1rem; border-radius: var(--radius-sm);
            color: var(--white); font-size: .87rem;
            font-family: 'Jost', sans-serif;
            display: flex; align-items: center; gap: .5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            transform: translateY(80px); opacity: 0;
            transition: transform .35s cubic-bezier(.34,1.56,.64,1), opacity .35s;
            pointer-events: none; max-width: 320px;
        }
        #toast.show { transform: translateY(0); opacity: 1; }

        /* ══ RESPONSIVE ══ */
        @media (max-width: 599px) {
            #logo-name-text { display: none; }
            .info-column { padding: 1.5rem; }
            .cover-column { padding: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="../index.php" class="logo-link">
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
            <form action="../pages/katalog.php" method="GET">
                <input type="search" name="q" placeholder="Cari judul, penulis, atau kategori..." class="search-input" />
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
            <a href="keranjang.php" class="nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?><span class="nav-badge"><?= min($cart_count, 99) ?></span><?php endif; ?>
            </a>
            <a href="wishlist.php" class="nav-icon">
                <i class="far fa-heart"></i>
                <?php if ($wishlist_count > 0): ?><span class="nav-badge"><?= min($wishlist_count, 99) ?></span><?php endif; ?>
            </a>
            <?php if ($user_id): ?>
                <div class="dropdown-wrap">
                    <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                        <i class="fas fa-user-circle" style="font-size:1.45rem;"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="profile.php">
                            <i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya
                        </a>
                        <a href="pesanan.php">
                            <i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya
                        </a>
                        <hr />
                        <a href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="../auth/login.php" class="btn-auth">Masuk / Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if ($book): ?>
<!-- ══ PAGE HEADER ══ -->
<div class="page-header">
    <div class="page-header-inner">
        <span class="page-eyebrow">✦ Detail Buku</span>
        <nav class="breadcrumb">
            <a href="../index.php">Beranda</a>
            <span class="sep">/</span>
            <a href="katalog.php">Katalog</a>
            <span class="sep">/</span>
            <?php if (!empty($book['nama_kategori'])): ?>
                <a href="katalog.php?kategori=<?= $book['id_kategori'] ?>"><?= htmlspecialchars($book['nama_kategori']) ?></a>
                <span class="sep">/</span>
            <?php endif; ?>
            <span class="current"><?= htmlspecialchars(mb_strimwidth($book['judul'], 0, 45, '…')) ?></span>
        </nav>
    </div>
</div>
<?php endif; ?>

<!-- ══ MAIN ══ -->
<div class="page-inner">
    <?php if (!$book): ?>
        <div style="text-align:center; padding:5rem 2rem;">
            <i class="fas fa-exclamation-circle" style="font-size:3rem; color:#d4c2e0; margin-bottom:1rem; display:block;"></i>
            <p style="color:var(--muted); margin-bottom:1.5rem; font-family:'Jost',sans-serif;">Buku tidak ditemukan</p>
            <a href="katalog.php" style="display:inline-flex; align-items:center; gap:.4rem; padding:.65rem 1.4rem; background:var(--plum); color:var(--white); border-radius:var(--radius-sm); font-weight:500; font-family:'Jost',sans-serif; text-decoration:none; letter-spacing:.05em;">
                <i class="fas fa-arrow-left"></i> Kembali ke Katalog
            </a>
        </div>
    <?php else: ?>

        <!-- ══ DETAIL CARD ══ -->
        <div class="detail-card">
            <div class="detail-layout">

                <!-- Cover Column -->
                <div class="cover-column">
                    <div class="cover-img">
                        <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                            <img src="../assets/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                                 alt="<?= htmlspecialchars($book['judul']) ?>"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                            <div style="display:none; width:100%; height:100%; align-items:center; justify-content:center;">
                                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg>
                            </div>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg>
                        <?php endif; ?>
                    </div>

                    <?php if ($book['stok'] > 0): ?>
                    <div class="qty-row">
                        <button class="qty-btn" onclick="changeQty(-1)">−</button>
                        <input class="qty-input" type="number" id="qtyInput" value="1" min="1" max="<?= $book['stok'] ?>" readonly />
                        <button class="qty-btn" onclick="changeQty(1)">+</button>
                    </div>
                    <?php endif; ?>

                    <div class="action-row">
                        <button class="btn-cart-main" onclick="tambahKeranjang(<?= $book['id_buku'] ?>)"
                                <?= ($book['stok'] <= 0) ? 'disabled' : '' ?>>
                            <i class="fas fa-cart-plus"></i>
                            <span>Keranjang</span>
                        </button>
                        <button class="btn-wishlist <?= $in_wishlist ? 'active' : '' ?>"
                                id="wishlistBtn"
                                onclick="tambahWishlist(<?= $book['id_buku'] ?>)"
                                title="Simpan ke Wishlist">
                            <i class="<?= $in_wishlist ? 'fas' : 'far' ?> fa-heart"></i>
                        </button>
                    </div>

                    <?php if ($book['stok'] > 0): ?>
                    <button class="btn-buynow" onclick="beliSekarang(<?= $book['id_buku'] ?>)">
                        <i class></i> Beli Sekarang
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Info Column -->
                <div class="info-column">
                    <?php if (!empty($book['nama_kategori'])): ?>
                    <div class="category-badge">
                        <i class="fas fa-tag" style="font-size:.62rem;"></i>
                        <?= htmlspecialchars($book['nama_kategori']) ?>
                    </div>
                    <?php endif; ?>

                    <h1 class="book-title"><?= htmlspecialchars($book['judul']) ?></h1>

                    <div class="book-author">
                        oleh <strong><?= htmlspecialchars($book['penulis'] ?? '—') ?></strong>
                    </div>

                    <div class="rating-row">
                        <span class="rating-num"><?= $avg_rating ?: '—' ?></span>
                        <div class="rating-stars-row"><?= starHtml($avg_rating, '0.82rem') ?></div>
                        <span class="rating-reviews"><?= $review_count ?> ulasan</span>
                    </div>

                    <div class="divider"></div>

                    <div class="meta-grid">
                        <?php if (!empty($book['penerbit'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Penerbit</span>
                            <span class="meta-value"><?= htmlspecialchars($book['penerbit']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($book['isbn'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">ISBN</span>
                            <span class="meta-value"><?= htmlspecialchars($book['isbn']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($book['halaman'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Halaman</span>
                            <span class="meta-value"><?= htmlspecialchars($book['halaman']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($book['sinopsis'])): ?>
                    <div class="divider"></div>
                    <p class="deskripsi-text"><?= nl2br(htmlspecialchars($book['sinopsis'])) ?></p>
                    <?php endif; ?>

                    <div class="divider"></div>

                    <div class="price-row">
                        <span class="price-main"><?= formatRupiah($book['harga']) ?></span>
                        <?php
                        $sc = ($book['stok'] > 10) ? 'tersedia' : (($book['stok'] > 0) ? 'terbatas' : 'habis');
                        $sl = ($book['stok'] > 10) ? 'Tersedia' : (($book['stok'] > 0) ? 'Terbatas' : 'Stok Habis');
                        $si = ($book['stok'] > 0) ? 'check-circle' : 'exclamation-circle';
                        ?>
                        <div class="stok-badge <?= $sc ?>">
                            <i class="fas fa-<?= $si ?>"></i>
                            <?= $sl ?>
                            <?php if ($book['stok'] > 0): ?>(<?= $book['stok'] ?> stok)<?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ══ ULASAN ══ -->
        <div class="section-card">
            <div class="section-title">
                <i class="fas fa-comment-dots"></i>
                Ulasan Pembaca
            </div>

            <?php if ($user_id): ?>
                <?php if ($user_review): ?>
                    <div class="reviewed-note">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <div class="reviewed-note-label">Anda sudah memberikan ulasan</div>
                            <div class="reviewed-note-sub">
                                <?= starHtml($user_review['rating'], '0.75rem') ?>
                                <span><?= $user_review['rating'] ?>/5</span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="review-form-box">
                        <div class="review-form-title">
                            <i class="fas fa-pencil-alt"></i> Tulis Ulasan
                        </div>
                        <form id="reviewForm" onsubmit="submitReview(event, <?= $id_buku ?>)">
                            <label class="form-label">Rating</label>
                            <div class="star-selector" id="starSelector">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="far fa-star" data-value="<?= $i ?>"
                                       style="color:#d4c2e0;"
                                       onmouseover="hoverStars(<?= $i ?>)"
                                       onmouseout="resetStars();"
                                       onclick="selectStar(<?= $i ?>)"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" id="ratingValue" name="rating" value="0" />
                            <p id="ratingLabel" class="star-label">Pilih rating Anda</p>

                            <label class="form-label" for="komentar">Komentar</label>
                            <textarea id="komentar" name="komentar"
                                      class="review-textarea"
                                      placeholder="Bagikan pengalaman Anda membaca buku ini..."
                                      rows="4" maxlength="1000" required
                                      oninput="updateCharCount()"></textarea>
                            <div class="char-count"><span id="charCount">0</span>/1000</div>

                            <div class="review-form-actions">
                                <button type="submit" class="btn-submit-review">
                                    <i class="fas fa-paper-plane"></i> Kirim Ulasan
                                </button>
                                <button type="reset" class="btn-reset-review" onclick="resetReviewForm()">
                                    <i class="fas fa-times"></i> Batal
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="login-prompt">
                    <i class="fas fa-sign-in-alt"></i>
                    <p>Silakan masuk untuk memberikan ulasan</p>
                    <a href="../auth/login.php" class="btn-login-link">
                        <i class="fas fa-sign-in-alt"></i> Masuk / Daftar
                    </a>
                </div>
            <?php endif; ?>

            <!-- Review List -->
            <?php if (empty($reviews)): ?>
                <div class="no-reviews">
                    <i class="fas fa-comment-dots"></i>
                    <p>Belum ada ulasan untuk buku ini.</p>
                    <small>Jadilah yang pertama memberikan ulasan!</small>
                </div>
            <?php else: ?>
                <div class="reviews-list">
                    <?php foreach ($reviews as $rv): ?>
                    <div class="review-item">
                        <div class="review-top">
                            <div class="reviewer-avatar">
                                <?= strtoupper(mb_substr($rv['nama_depan'], 0, 1)) ?>
                            </div>
                            <div class="reviewer-info">
                                <div class="reviewer-name"><?= htmlspecialchars($rv['nama_depan'] . ' ' . $rv['nama_belakang']) ?></div>
                                <div class="reviewer-meta">
                                    <div class="reviewer-stars"><?= starHtml($rv['rating'], '0.75rem') ?></div>
                                    <span class="reviewer-date"><?= date('d M Y', strtotime($rv['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="review-body"><?= nl2br(htmlspecialchars($rv['komentar'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<!-- Toast -->
<div id="toast">
    <i class="fas fa-check-circle" id="toast-icon"></i>
    <span id="toast-msg">Berhasil!</span>
</div>

<script>
// ── TOAST ──
function showToast(msg, ok = true) {
    const t  = document.getElementById('toast');
    const ic = document.getElementById('toast-icon');
    document.getElementById('toast-msg').textContent = msg;
    t.style.background = ok ? '#2a8a5e' : '#c0403a';
    ic.className = ok ? 'fas fa-check-circle' : 'fas fa-times-circle';
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 3000);
}

function updateBadge(type, delta) {
    const selector = type === 'cart' ? 'a[href*="keranjang"] .nav-badge' : 'a[href*="wishlist"] .nav-badge';
    const linkSel  = type === 'cart' ? 'a[href*="keranjang"]'            : 'a[href*="wishlist"]';
    let badge  = document.querySelector(selector);
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

// ── QTY ──
function changeQty(delta) {
    const inp = document.getElementById('qtyInput');
    if (!inp) return;
    const max = parseInt(inp.max) || 99;
    let v = parseInt(inp.value) + delta;
    if (v < 1) v = 1;
    if (v > max) v = max;
    inp.value = v;
}

// ── STARS ──
let selectedRating = 0;

function selectStar(val) {
    selectedRating = val;
    document.getElementById('ratingValue').value = val;
    updateStarDisplay(val);
    const labels = ['', 'Sangat Buruk', 'Buruk', 'Cukup', 'Bagus', 'Sangat Bagus'];
    document.getElementById('ratingLabel').textContent = labels[val] + ' (' + val + '/5)';
}

function hoverStars(val) {
    const stars = document.querySelectorAll('#starSelector i');
    stars.forEach((s, i) => {
        if (i < val) { s.className = 'fas fa-star'; s.style.color = '#c4882a'; }
        else         { s.className = 'far fa-star';  s.style.color = '#d4c2e0'; }
    });
}

function resetStars() { updateStarDisplay(selectedRating); }

function updateStarDisplay(rating) {
    const stars = document.querySelectorAll('#starSelector i');
    stars.forEach((s, i) => {
        if (i < rating) { s.className = 'fas fa-star'; s.style.color = '#c4882a'; }
        else            { s.className = 'far fa-star';  s.style.color = '#d4c2e0'; }
    });
}

function updateCharCount() {
    const ta = document.getElementById('komentar');
    if (ta) document.getElementById('charCount').textContent = ta.value.length;
}

function resetReviewForm() {
    selectedRating = 0;
    updateStarDisplay(0);
    document.getElementById('ratingValue').value = 0;
    document.getElementById('ratingLabel').textContent = 'Pilih rating Anda';
    document.getElementById('charCount').textContent = '0';
}

// ── SUBMIT REVIEW ──
function submitReview(event, idBuku) {
    event.preventDefault();
    const rating   = parseInt(document.getElementById('ratingValue').value);
    const komentar = document.getElementById('komentar').value.trim();
    if (rating === 0)  { showToast('Pilih rating terlebih dahulu', false); return; }
    if (!komentar)     { showToast('Komentar tidak boleh kosong', false); return; }
    const btn = event.target.querySelector('.btn-submit-review');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
    fetch('/literaspace/api/review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, rating, komentar })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { showToast(d.message); setTimeout(() => location.reload(), 1200); }
        else {
            showToast(d.message || 'Gagal mengirim ulasan', false);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Ulasan';
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan', false);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Ulasan';
    });
}

// ── KERANJANG ──
function tambahKeranjang(idBuku) {
    <?php if (!$user_id): ?>
    window.location.href = '../auth/login.php'; return;
    <?php endif; ?>
    const qty = parseInt(document.getElementById('qtyInput')?.value || 1);
    const btn = document.querySelector('.btn-cart-main');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('/literaspace/api/keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Ditambahkan ke keranjang!');
            updateBadge('cart', qty);
            btn.innerHTML = '<i class="fas fa-check"></i> Ditambahkan';
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cart-plus"></i> Keranjang';
            }, 2000);
        } else {
            showToast(d.message || 'Gagal', false);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-cart-plus"></i> Keranjang';
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan', false);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cart-plus"></i> Keranjang';
    });
}

// ── BELI SEKARANG — langsung ke checkout, tidak lewat keranjang ──
function beliSekarang(idBuku) {
    <?php if (!$user_id): ?>
    window.location.href = '../auth/login.php'; return;
    <?php endif; ?>
    const qty = parseInt(document.getElementById('qtyInput')?.value) || 1;
    window.location.href = '/literaspace/pages/checkout.php?id_buku=' + idBuku + '&qty=' + qty;
}

// ── WISHLIST ──
function tambahWishlist(idBuku) {
    <?php if (!$user_id): ?>
    window.location.href = '../auth/login.php'; return;
    <?php endif; ?>
    const btn = document.getElementById('wishlistBtn');
    btn.disabled = true;
    fetch('/literaspace/api/wishlist/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku })
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        if (d.success) {
            if (d.action === 'removed') {
                btn.classList.remove('active');
                btn.innerHTML = '<i class="far fa-heart"></i>';
                updateBadge('wish', -1);
                showToast('Dihapus dari wishlist');
            } else {
                btn.classList.add('active');
                btn.innerHTML = '<i class="fas fa-heart"></i>';
                updateBadge('wish', 1);
                showToast('Disimpan ke wishlist!');
            }
        } else {
            showToast(d.message || 'Gagal', false);
        }
    })
    .catch(() => { btn.disabled = false; showToast('Terjadi kesalahan', false); });
}
</script>
</body>
</html>