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

        // Cek apakah buku ini sudah di wishlist
        $swb = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ? AND id_buku = ?");
        $swb->execute([$user_id, $id_buku]);
        $in_wishlist = (int)$swb->fetchColumn() > 0;
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

function starHtml($rating, $size = '0.8rem') {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $color = $i <= round($rating) ? '#f59e0b' : '#d1d5db';
        $html .= "<i class=\"" . ($i <= round($rating) ? 'fas' : 'far') . " fa-star\" style=\"color:{$color};font-size:{$size};\"></i>";
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet" />
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
            --gray-400:     #9090b0;
            --gray-500:     #6b6b8a;
            --gray-800:     #1a1a2e;
            --error:        #e03c3c;
            --success:      #1db87d;
            --amber:        #f59e0b;
            --radius:       14px;
            --shadow-sm:    0 2px 8px rgba(30,22,103,.08);
            --shadow:       0 4px 24px rgba(30,22,103,.10);
            --shadow-lg:    0 12px 40px rgba(30,22,103,.15);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            min-height: 100vh;
        }

        /* ── NAVBAR ── */
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
        .logo-link { display:flex; align-items:center; gap:.6rem; text-decoration:none; flex-shrink:0; }
        .logo-icon {
            width: 40px; height: 40px;
            background: var(--indigo-deep);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s, transform .2s;
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
        .dropdown-menu a:hover { background: rgba(30,22,103,.05); }

        /* ── PAGE ── */
        .page-inner {
            max-width: 1100px; margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        .breadcrumb {
            display: flex; gap: .5rem; margin-bottom: 2rem;
            font-size: .85rem; color: var(--gray-500);
            flex-wrap: wrap;
        }
        .breadcrumb a { color: var(--indigo-light); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* ── DETAIL CARD ── */
        .detail-card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .detail-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 0;
        }

        @media (max-width: 768px) {
            .detail-layout { grid-template-columns: 1fr; }
        }

        /* Cover column */
        .cover-column {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            background: var(--gray-50);
            border-right: 1px solid var(--gray-200);
        }

        @media (max-width: 768px) {
            .cover-column { border-right: none; border-bottom: 1px solid var(--gray-200); }
        }

        .cover-img {
            width: 100%; max-width: 220px;
            aspect-ratio: 3/4;
            background: linear-gradient(135deg,#1e1667,#3b2ec0);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 16px 48px rgba(30,22,103,.22), 0 4px 12px rgba(30,22,103,.12);
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .cover-img img { width: 100%; height: 100%; object-fit: cover; }
        .cover-img svg { width: 60px; height: 60px; fill: rgba(255,255,255,.3); }

        /* Qty & action buttons */
        .qty-row {
            display: flex; align-items: center; gap: .6rem;
            width: 100%; max-width: 220px;
        }
        .qty-btn {
            width: 36px; height: 36px;
            border: 1.5px solid var(--gray-200);
            background: var(--white);
            border-radius: 8px;
            font-size: 1.1rem; font-weight: 700;
            color: var(--indigo-deep);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: border-color .2s, background .2s;
            flex-shrink: 0;
        }
        .qty-btn:hover { border-color: var(--indigo-light); background: var(--gray-50); }
        .qty-input {
            flex: 1; height: 36px;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            text-align: center;
            font-family: 'DM Sans', sans-serif;
            font-size: .95rem; font-weight: 600;
            color: var(--gray-800);
            outline: none;
        }
        .qty-input:focus { border-color: var(--indigo-light); }

        .action-row {
            display: flex; gap: .6rem;
            width: 100%; max-width: 220px;
        }

        .btn-cart {
            flex: 1;
            padding: .7rem .5rem;
            background: var(--indigo-deep);
            color: var(--white);
            border: none; border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: .85rem; font-weight: 700;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            transition: background .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(30,22,103,.25);
        }
        .btn-cart:hover { background: var(--indigo-light); box-shadow: 0 6px 20px rgba(59,46,192,.35); }
        .btn-cart:active { transform: scale(.97); }
        .btn-cart:disabled { background: var(--gray-400); box-shadow: none; cursor: not-allowed; }

        .btn-buynow {
            width: 100%; max-width: 220px;
            padding: .7rem .5rem;
            background: transparent;
            color: var(--indigo-deep);
            border: 2px solid var(--indigo-deep);
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: .85rem; font-weight: 700;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            transition: background .2s, color .2s;
        }
        .btn-buynow:hover { background: var(--indigo-deep); color: var(--white); }

        .btn-wishlist {
            width: 42px; height: 42px; flex-shrink: 0;
            background: var(--white);
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: border-color .2s, background .2s, color .2s;
            color: var(--gray-400);
            font-size: 1rem;
        }
        .btn-wishlist:hover { color: var(--error); background: rgba(224,60,60,.06); }
        .btn-wishlist.active { color: var(--error); }

        /* Info column */
        .info-column {
            padding: 2rem 2.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            padding: .3rem .8rem;
            background: rgba(59,46,192,.1);
            color: var(--indigo-light);
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .03em;
            text-transform: uppercase;
            align-self: flex-start;
        }

        .book-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.9rem;
            color: var(--gray-800);
            line-height: 1.25;
        }

        .book-author {
            font-size: .95rem;
            color: var(--gray-500);
        }
        .book-author strong {
            color: var(--gray-800);
        }

        .rating-row {
            display: flex; align-items: center; gap: .6rem;
        }
        .rating-num {
            font-size: 1rem; font-weight: 700; color: var(--amber);
        }
        .rating-stars-row { display: flex; gap: .15rem; align-items: center; }
        .rating-reviews {
            font-size: .85rem; color: var(--gray-400);
        }

        .divider { height: 1px; background: var(--gray-100); }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: .75rem 1.5rem;
        }
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: .15rem;
        }
        .meta-label {
            font-size: .75rem;
            font-weight: 600;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .meta-value {
            font-size: .92rem;
            color: var(--gray-800);
            font-weight: 500;
        }

        .deskripsi-text {
            font-size: .9rem;
            color: var(--gray-500);
            line-height: 1.75;
            text-align: justify;
        }

        .price-row {
            display: flex; align-items: center; gap: 1rem;
            flex-wrap: wrap;
        }
        .price-main {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--indigo-deep);
            letter-spacing: -.02em;
        }
        .stok-badge {
            display: inline-flex; align-items: center; gap: .35rem;
            padding: .35rem .8rem;
            border-radius: 20px;
            font-size: .8rem; font-weight: 600;
        }
        .stok-badge.tersedia { background: rgba(29,184,125,.12); color: #1db87d; }
        .stok-badge.terbatas { background: rgba(212,146,10,.12); color: #d4920a; }
        .stok-badge.habis    { background: rgba(224,60,60,.12);  color: #e03c3c; }

        /* Desktop action strip at bottom of info */
        .desktop-actions {
            display: flex; gap: .75rem; align-items: center;
            flex-wrap: wrap;
            margin-top: .5rem;
        }
        .desktop-actions .btn-cart   { flex: none; padding: .75rem 1.6rem; font-size: .9rem; }
        .desktop-actions .btn-buynow { flex: none; padding: .75rem 1.6rem; font-size: .9rem; }
        .desktop-actions .btn-wishlist { width: 48px; height: 48px; font-size: 1.1rem; }

        /* ── SYNOPSIS ── */
        .section-card {
            background: var(--white);
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            color: var(--gray-800);
            margin-bottom: 1.25rem;
            padding-bottom: .75rem;
            border-bottom: 2px solid var(--gray-100);
        }
        .synopsis-text {
            color: var(--gray-500);
            line-height: 1.8;
            font-size: .93rem;
        }

        /* ── REVIEW FORM ── */
        .review-form-box {
            background: var(--gray-50);
            border: 1.5px solid var(--gray-200);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .review-form-title {
            font-weight: 700;
            font-size: .95rem;
            color: var(--gray-800);
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .5rem;
        }
        .star-selector {
            display: flex; gap: .5rem;
            margin-bottom: .4rem;
        }
        .star-selector i {
            font-size: 1.6rem;
            cursor: pointer;
            transition: color .15s, transform .1s;
        }
        .star-selector i:hover { transform: scale(1.2); }
        .star-label {
            font-size: .82rem; color: var(--gray-400); margin-bottom: 1.25rem;
        }
        .review-textarea {
            width: 100%;
            padding: .9rem 1rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            color: var(--gray-800);
            resize: vertical;
            outline: none;
            min-height: 110px;
            transition: border-color .2s, box-shadow .2s;
            background: var(--white);
        }
        .review-textarea:focus {
            border-color: var(--indigo-light);
            box-shadow: 0 0 0 3px rgba(59,46,192,.08);
        }
        .char-count {
            font-size: .78rem; color: var(--gray-400);
            text-align: right; margin-top: .3rem; margin-bottom: 1.25rem;
        }
        .review-form-actions { display: flex; gap: .75rem; }
        .btn-submit-review {
            flex: 1;
            padding: .7rem 1rem;
            background: var(--indigo-deep); color: var(--white);
            border: none; border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem; font-weight: 700;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            transition: background .2s;
        }
        .btn-submit-review:hover { background: var(--indigo-light); }
        .btn-submit-review:disabled { background: var(--gray-400); cursor: not-allowed; }
        .btn-reset-review {
            padding: .7rem 1.25rem;
            background: var(--white); color: var(--gray-500);
            border: 1.5px solid var(--gray-200); border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem; font-weight: 600;
            cursor: pointer;
            transition: border-color .2s;
        }
        .btn-reset-review:hover { border-color: var(--gray-400); }

        .existing-review-note {
            display: flex; align-items: center; gap: .5rem;
            margin-top: 1rem;
            padding: .75rem 1rem;
            background: rgba(59,46,192,.06);
            border-left: 3px solid var(--indigo-light);
            border-radius: 0 8px 8px 0;
            font-size: .85rem; color: var(--gray-500);
        }

        /* ── REVIEW LIST ── */
        .reviews-list { display: flex; flex-direction: column; gap: 0; }

        .review-item {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--gray-100);
            animation: fadeIn .3s ease;
        }
        .review-item:first-child { padding-top: 0; }
        .review-item:last-child { border-bottom: none; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        .review-top {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 1rem; margin-bottom: .75rem;
        }
        .reviewer-avatar {
            width: 40px; height: 40px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--indigo-deep), var(--indigo-light));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; font-weight: 700; color: var(--white);
        }
        .reviewer-info { flex: 1; }
        .reviewer-name {
            font-weight: 700; font-size: .92rem; color: var(--gray-800);
        }
        .reviewer-meta {
            display: flex; align-items: center; gap: .5rem;
            margin-top: .2rem;
        }
        .reviewer-stars { display: flex; gap: .1rem; }
        .reviewer-date {
            font-size: .78rem; color: var(--gray-400);
        }
        .review-body {
            font-size: .9rem; color: var(--gray-500);
            line-height: 1.65;
            padding-left: 52px;
        }

        .no-reviews {
            text-align: center; padding: 3rem 2rem;
            color: var(--gray-400);
        }
        .no-reviews i { font-size: 2.5rem; margin-bottom: .75rem; display: block; }

        /* ── LOGIN PROMPT ── */
        .login-prompt {
            padding: 2rem; background: var(--gray-50); border-radius: 14px;
            text-align: center; border: 1.5px dashed var(--gray-200);
            margin-bottom: 2rem;
        }
        .login-prompt i { font-size: 2rem; color: var(--gray-200); margin-bottom: .75rem; display: block; }
        .login-prompt p { color: var(--gray-500); margin-bottom: 1.25rem; font-size: .9rem; }
        .btn-login-link {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .65rem 1.4rem;
            background: var(--indigo-deep); color: var(--white);
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif; font-size: .88rem; font-weight: 700;
            text-decoration: none; transition: background .2s;
        }
        .btn-login-link:hover { background: var(--indigo-light); }

        /* ── TOAST ── */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
            padding: .75rem 1.25rem;
            border-radius: 12px;
            color: var(--white); font-size: .88rem; font-weight: 500;
            display: flex; align-items: center; gap: .5rem;
            box-shadow: 0 8px 30px rgba(0,0,0,.2);
            transform: translateY(90px); opacity: 0;
            transition: transform .35s cubic-bezier(.34,1.56,.64,1), opacity .35s;
            pointer-events: none; max-width: 320px;
        }
        #toast.show { transform: translateY(0); opacity: 1; }

        @media (min-width: 600px) {
            .logo-text { display: inline !important; }
        }
        @media (max-width: 599px) {
            .logo-text { display: none; }
            .info-column { padding: 1.5rem; }
            .cover-column { padding: 1.5rem; }
            .book-title { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="../index.php" class="logo-link">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
            </div>
            <span class="logo-text">LiteraSpace</span>
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

<!-- ── MAIN ── -->
<div class="page-inner">
    <?php if (!$book): ?>
        <div style="text-align:center; padding:5rem 2rem;">
            <i class="fas fa-exclamation-circle" style="font-size:3rem; color:var(--gray-200); margin-bottom:1rem; display:block;"></i>
            <p style="color:var(--gray-500); margin-bottom:1.5rem;">Buku tidak ditemukan</p>
            <a href="kategori.php" style="display:inline-flex; align-items:center; gap:.4rem; padding:.65rem 1.4rem; background:var(--indigo-deep); color:var(--white); border-radius:10px; font-weight:700; text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Kembali ke Katalog
            </a>
        </div>
    <?php else: ?>

        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../index.php">Beranda</a>
            <span>/</span>
            <a href="katalog.php">Katalog</a>
            <span>/</span>
            <span><?= htmlspecialchars($book['judul']) ?></span>
        </div>

        <!-- ── DETAIL CARD ── -->
        <div class="detail-card">
            <div class="detail-layout">

                <!-- Cover Column -->
                <div class="cover-column">
                    <div class="cover-img">
                        <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                            <img src="../assets/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                                 alt="<?= htmlspecialchars($book['judul']) ?>" />
                        <?php else: ?>
                            <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                        <?php endif; ?>
                    </div>

                    <!-- Qty -->
                    <?php if ($book['stok'] > 0): ?>
                    <div class="qty-row">
                        <button class="qty-btn" onclick="changeQty(-1)">−</button>
                        <input class="qty-input" type="number" id="qtyInput" value="1" min="1" max="<?= $book['stok'] ?>" readonly />
                        <button class="qty-btn" onclick="changeQty(1)">+</button>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="action-row">
                        <button class="btn-cart" onclick="tambahKeranjang(<?= $book['id_buku'] ?>)"
                                <?= ($book['stok'] <= 0) ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart"></i>
                            <span>Keranjang</span>
                        </button>
                        <button class="btn-wishlist <?= ($in_wishlist ?? false) ? 'active' : '' ?>" id="wishlistBtn" onclick="tambahWishlist(<?= $book['id_buku'] ?>)" title="Simpan ke Wishlist">
                            <i class="<?= ($in_wishlist ?? false) ? 'fas' : 'far' ?> fa-heart"></i>
                        </button>
                    </div>

                    <!-- Beli Sekarang -->
                    <?php if ($book['stok'] > 0): ?>
                    <button class="btn-buynow" onclick="beliSekarang(<?= $book['id_buku'] ?>)">
                        Beli Sekarang
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Info Column -->
                <div class="info-column">
                    <?php if (!empty($book['nama_kategori'])): ?>
                    <div class="category-badge">
                        <i class="fas fa-tag" style="font-size:.7rem; margin-right:.3rem;"></i>
                        <?= htmlspecialchars($book['nama_kategori']) ?>
                    </div>
                    <?php endif; ?>

                    <h1 class="book-title"><?= htmlspecialchars($book['judul']) ?></h1>

                    <div class="book-author">
                        oleh <strong><?= htmlspecialchars($book['penulis'] ?? '—') ?></strong>
                    </div>

                    <!-- Rating -->
                    <div class="rating-row">
                        <span class="rating-num"><?= $avg_rating ?: '—' ?></span>
                        <div class="rating-stars-row"><?= starHtml($avg_rating, '0.9rem') ?></div>
                        <span class="rating-reviews"><?= $review_count ?> ulasan</span>
                    </div>

                    <div class="divider"></div>

                    <!-- Meta Info -->
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
                    <p class="deskripsi-text">
                        <?= nl2br(htmlspecialchars($book['sinopsis'])) ?>
                    </p>
                    <?php endif; ?>

                    <div class="divider"></div>

                    <!-- Price & Stock -->
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

        <!-- ── ULASAN ── -->
        <div class="section-card">
            <div class="section-title">Ulasan Pembaca</div>

            <!-- Review Form -->
            <?php if ($user_id): ?>
            <?php if ($user_review): ?>
                <!-- Already reviewed: show a simple note -->
                <div style="display:flex; align-items:center; gap:.75rem; padding:1rem 1.25rem; background:rgba(59,46,192,.05); border:1.5px solid var(--gray-200); border-radius:12px; margin-bottom:2rem;">
                    <i class="fas fa-check-circle" style="color:var(--success); font-size:1.2rem; flex-shrink:0;"></i>
                    <div>
                        <div style="font-weight:700; font-size:.9rem; color:var(--gray-800);">Anda sudah memberikan ulasan</div>
                        <div style="display:flex; align-items:center; gap:.4rem; margin-top:.25rem;">
                            <?= starHtml($user_review['rating'], '0.82rem') ?>
                            <span style="font-size:.82rem; color:var(--gray-500);"><?= $user_review['rating'] ?>/5</span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
            <div class="review-form-box">
                <div class="review-form-title">
                    <i class="fas fa-pencil-alt" style="color:var(--indigo-light); margin-right:.4rem;"></i>
                    Tulis Ulasan
                </div>
                <form id="reviewForm" onsubmit="submitReview(event, <?= $id_buku ?>)">
                    <label class="form-label">Rating</label>
                    <div class="star-selector" id="starSelector">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="far fa-star" data-value="<?= $i ?>"
                               style="color:#d1d5db;"
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
                    <p>Belum ada ulasan untuk buku ini.<br><span style="font-size:.85rem;">Jadilah yang pertama memberikan ulasan!</span></p>
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
                                    <div class="reviewer-stars"><?= starHtml($rv['rating'], '0.8rem') ?></div>
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
    const t = document.getElementById('toast');
    const ic = document.getElementById('toast-icon');
    document.getElementById('toast-msg').textContent = msg;
    t.style.background = ok ? '#1db87d' : '#e03c3c';
    ic.className = ok ? 'fas fa-check-circle' : 'fas fa-times-circle';
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 3000);
}

// ✅ Update badge navbar secara realtime tanpa reload
function updateBadge(type, delta) {
    const selector = type === 'cart' ? 'a[href*="keranjang"] .nav-badge' : 'a[href*="wishlist"] .nav-badge';
    const linkSel  = type === 'cart' ? 'a[href*="keranjang"]'            : 'a[href*="wishlist"]';

    let badge  = document.querySelector(selector);
    const link = document.querySelector(linkSel);

    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'nav-badge';
        badge.textContent = '0';
        link.appendChild(badge);
    }

    let count = parseInt(badge.textContent) || 0;
    count += delta;

    if (count <= 0) {
        badge.remove();
    } else {
        badge.textContent = Math.min(count, 99);
    }
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
        if (i < val) { s.className = 'fas fa-star'; s.style.color = '#f59e0b'; }
        else          { s.className = 'far fa-star'; s.style.color = '#d1d5db'; }
    });
}

function resetStars() { updateStarDisplay(selectedRating); }

function updateStarDisplay(rating) {
    const stars = document.querySelectorAll('#starSelector i');
    stars.forEach((s, i) => {
        if (i < rating) { s.className = 'fas fa-star'; s.style.color = '#f59e0b'; }
        else             { s.className = 'far fa-star'; s.style.color = '#d1d5db'; }
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
    const rating = parseInt(document.getElementById('ratingValue').value);
    const komentar = document.getElementById('komentar').value.trim();
    if (rating === 0) { showToast('Pilih rating terlebih dahulu', false); return; }
    if (!komentar)    { showToast('Komentar tidak boleh kosong', false); return; }
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
        else { showToast(d.message || 'Gagal mengirim ulasan', false); btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Ulasan'; }
    })
    .catch(() => { showToast('Terjadi kesalahan', false); btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Ulasan'; });
}

// ── KERANJANG ──
function tambahKeranjang(idBuku) {
    <?php if (!$user_id): ?>
    window.location.href = '../auth/login.php'; return;
    <?php endif; ?>
    const qty = parseInt(document.getElementById('qtyInput')?.value || 1);
    const btn = document.querySelector('.btn-cart');
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
            updateBadge('cart', qty); // ✅ naikkan badge sejumlah qty yang ditambahkan
            btn.innerHTML = '<i class="fas fa-check"></i> Ditambahkan';
            setTimeout(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Keranjang'; }, 2000);
        } else {
            showToast(d.message || 'Gagal', false);
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Keranjang';
        }
    })
    .catch(() => { showToast('Terjadi kesalahan', false); btn.disabled = false; btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Keranjang'; });
}

// ── BELI SEKARANG ──
function beliSekarang(idBuku) {
    <?php if (!$user_id): ?>
    window.location.href = '../auth/login.php'; return;
    <?php endif; ?>
    const qty = parseInt(document.getElementById('qtyInput')?.value || 1);
    fetch('/literaspace/api/keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) window.location.href = 'checkout.php';
        else showToast(d.message || 'Gagal', false);
    })
    .catch(() => showToast('Terjadi kesalahan', false));
}

// ── WISHLIST ──
function tambahWishlist(idBuku) {
    <?php if (!$user_id): ?>
    window.location.href = '../auth/login.php'; return;
    <?php endif; ?>
    const btn = document.getElementById('wishlistBtn');
    btn.disabled = true;
    fetch('/literaspace/api/wishlist/add.php', // ✅ samakan endpoint dengan index.php
        {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku })
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        if (d.success) {
            if (d.action === 'removed') {
                // ✅ toggle OFF
                btn.classList.remove('active');
                btn.innerHTML = '<i class="far fa-heart"></i>';
                updateBadge('wish', -1);
                showToast('Dihapus dari wishlist');
            } else {
                // ✅ toggle ON
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