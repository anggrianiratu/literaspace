<?php
// ========================================
// PESANAN.PHP - LITERASPACE
// Halaman Pesanan Saya
// ========================================

session_start();
require_once __DIR__ . '/../config/db.php';

$user_id        = $_SESSION['user_id'] ?? null;
$cart_count     = 0;
$wishlist_count = 0;
$active_tab     = $_GET['status'] ?? 'semua';
$orders         = [];
$filtered       = [];
$error          = null;

try {
    $pdo = getDB();

    if ($user_id) {
        $sc = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?");
        $sc->execute([$user_id]); $cart_count = (int)$sc->fetchColumn();
        $sw = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ?");
        $sw->execute([$user_id]); $wishlist_count = (int)$sw->fetchColumn();

        // ✅ FIX: valid_tabs sesuai ENUM di database (tidak ada 'diproses')
        $valid_tabs = ['semua', 'dikemas', 'dikirim', 'selesai', 'dibatalkan'];
        if (!in_array($active_tab, $valid_tabs)) $active_tab = 'semua';

        if ($active_tab === 'semua') {
            $where_status = '';
            $params = [$user_id];
        } else {
            $where_status = 'AND p.status_pesanan = ?';
            $params = [$user_id, $active_tab];
        }

        $stmt = $pdo->prepare("
            SELECT p.id_pesanan,
                   p.order_id,
                   p.tanggal_pesan      AS tanggal,
                   p.status_pesanan     AS status,
                   p.status_pembayaran,
                   p.kurir              AS metode_kirim,
                   p.metode_pembayaran  AS metode_bayar,
                   p.alamat_pengiriman  AS alamat,
                   p.total_harga        AS total,
                   p.no_resi
            FROM pesanan p
            WHERE p.id_user = ? $where_status
            ORDER BY p.tanggal_pesan DESC
        ");
        $stmt->execute($params);
        $orders_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders_raw as $order) {
            $stmt_items = $pdo->prepare("
                SELECT b.judul,
                       b.cover_image      AS cover,
                       dp.qty,
                       dp.harga_saat_beli AS harga
                FROM detail_pesanan dp
                JOIN buku b ON dp.id_buku = b.id_buku
                WHERE dp.id_pesanan = ?
            ");
            $stmt_items->execute([$order['id_pesanan']]);
            $order['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            $orders[] = $order;
        }

        $filtered = $orders;
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

function formatRupiah($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function formatTanggal($ts) {
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $d = date('j', strtotime($ts));
    $m = (int)date('n', strtotime($ts));
    $y = date('Y', strtotime($ts));
    return "$d {$bulan[$m]} $y";
}

// ✅ FIX: Hapus 'diproses' — tidak ada di ENUM database
$status_config = [
    'dikemas'    => ['label' => 'Dikemas',    'color' => '#6b3f82', 'bg' => '#f3ecf8', 'icon' => 'fa-box'],
    'dikirim'    => ['label' => 'Dikirim',    'color' => '#2a5e8a', 'bg' => '#e4eef8', 'icon' => 'fa-truck'],
    'selesai'    => ['label' => 'Selesai',    'color' => '#2a8a5e', 'bg' => '#e3f5ed', 'icon' => 'fa-check-circle'],
    'dibatalkan' => ['label' => 'Dibatalkan', 'color' => '#c0403a', 'bg' => '#fdf0ef', 'icon' => 'fa-times-circle'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pesanan Saya — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --plum:       #4a2c5e;
            --plum-mid:   #6b3f82;
            --plum-light: #9b6bb5;
            --ink:        #2a1a35;
            --muted:      #7a6585;
            --white:      #ffffff;
            --error:      #c0403a;
            --success:    #2a8a5e;
            --radius-lg:  20px;
            --radius-sm:  10px;
            --shadow:     0 4px 24px rgba(74,44,94,.10);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

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

        .navbar, main, .modal-overlay, #toast { position: relative; z-index: 1; }

        /* ── Navbar ── */
        .navbar {
            position: sticky; top: 0; z-index: 50;
            background: rgba(253,248,243,.92);
            backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
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
        .logo-name { font-family: 'Cormorant Garamond', serif; font-size: 1.25rem; font-weight: 600; color: var(--ink); letter-spacing: .04em; }
        .logo-name span { color: var(--plum-mid); font-style: italic; }

        .nav-icon { color: var(--muted); font-size: 1.15rem; text-decoration: none; position: relative; transition: color .2s; }
        .nav-icon:hover { color: var(--plum-mid); }
        .nav-badge {
            position: absolute; top: -7px; right: -7px;
            background: var(--error); color: var(--white);
            font-size: .62rem; font-weight: 700; width: 17px; height: 17px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
        }

        .dropdown-wrap { position: relative; }
        .dropdown-menu {
            position: absolute; right: 0; top: calc(100% + 8px); width: 175px;
            background: var(--white); border-radius: var(--radius-sm);
            box-shadow: 0 8px 32px rgba(74,44,94,.18), 0 2px 8px rgba(74,44,94,.08);
            border: 1.5px solid rgba(232,197,208,.5);
            opacity: 0; visibility: hidden; transition: opacity .2s, visibility .2s; z-index: 100;
        }
        .dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: block; padding: .62rem 1rem; font-size: .86rem; color: var(--ink); text-decoration: none; transition: background .15s, color .15s; }
        .dropdown-menu a:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-menu a:last-child  { border-radius: 0 0 8px 8px; color: var(--error); }
        .dropdown-menu a:hover { background: rgba(74,44,94,.05); color: var(--plum-mid); }
        .dropdown-menu a:last-child:hover { background: #fdf2f2; color: var(--error); }
        .dropdown-menu hr { border-color: rgba(232,197,208,.5); margin: .25rem 0; }

        /* ── Page layout ── */
        .page-inner { max-width: 860px; margin: 0 auto; padding: 2rem 1.5rem 3rem; }
        .page-heading { margin-bottom: 1.6rem; }
        .page-heading h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.9rem; font-weight: 600; color: var(--ink); letter-spacing: .01em; }
        .page-heading p  { font-size: .82rem; color: var(--muted); margin-top: .25rem; }

        /* ── Tabs ── */
        .tabs-wrap {
            background: rgba(255,255,255,.82); backdrop-filter: blur(6px);
            border: 1.5px solid rgba(122,101,133,.22); border-radius: var(--radius-sm);
            padding: .3rem .4rem; display: flex; gap: .15rem;
            margin-bottom: 1.4rem; box-shadow: var(--shadow); overflow-x: auto;
        }
        .tab-btn {
            padding: .48rem 1.1rem; border: none; border-radius: 8px; background: none;
            cursor: pointer; font-family: 'Jost', sans-serif; font-size: .84rem; font-weight: 500;
            color: var(--muted); transition: all .2s; white-space: nowrap;
            text-decoration: none; display: inline-block;
        }
        .tab-btn:hover { color: var(--plum-mid); background: rgba(74,44,94,.06); }
        .tab-btn.active { background: var(--plum); color: var(--white); font-weight: 600; }
        .tab-btn.active-cancel { background: var(--error); color: var(--white); font-weight: 600; }

        /* ── Order Card ── */
        .order-card {
            background: rgba(255,255,255,.82); backdrop-filter: blur(6px);
            border-radius: var(--radius-lg); border: 1.5px solid rgba(122,101,133,.22);
            box-shadow: var(--shadow); overflow: hidden; margin-bottom: 1.1rem;
            transition: box-shadow .25s, transform .25s; animation: slideUp .38s ease both;
        }
        .order-card:hover { box-shadow: 0 12px 36px rgba(74,44,94,.16); transform: translateY(-2px); }
        .order-card.cancelled { border-color: rgba(192,64,58,.25); opacity: .88; }
        .order-card.cancelled:hover { border-color: rgba(192,64,58,.45); }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .order-card:nth-child(1) { animation-delay: .04s; }
        .order-card:nth-child(2) { animation-delay: .11s; }
        .order-card:nth-child(3) { animation-delay: .18s; }
        .order-card:nth-child(4) { animation-delay: .25s; }

        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .95rem 1.4rem; border-bottom: 1.5px solid rgba(232,197,208,.35);
            background: linear-gradient(160deg, rgba(232,197,208,.15) 0%, transparent 60%);
            gap: 1rem; flex-wrap: wrap;
        }
        .order-card.cancelled .card-header { background: linear-gradient(160deg, rgba(192,64,58,.06) 0%, transparent 60%); }

        .order-meta { display: flex; gap: 1.8rem; align-items: center; flex-wrap: wrap; }
        .meta-item  { display: flex; flex-direction: column; gap: .15rem; }
        .meta-label { font-size: .68rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
        .meta-value { font-family: 'Cormorant Garamond', serif; font-size: 1rem; font-weight: 600; color: var(--ink); }

        .status-badge {
            display: inline-flex; align-items: center; gap: .38rem;
            padding: .3rem .85rem; border-radius: 9999px;
            font-size: .76rem; font-weight: 600; letter-spacing: .03em;
        }

        .card-items { padding: .8rem 1.4rem; }
        .item-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: .7rem 0; border-bottom: 1px solid rgba(232,197,208,.3); gap: 1rem;
        }
        .item-row:last-child { border-bottom: none; }

        .item-cover { width: 42px; height: 56px; flex-shrink: 0; border-radius: 5px; object-fit: cover; border: 1.5px solid rgba(122,101,133,.18); }
        .item-cover-placeholder { width: 42px; height: 56px; flex-shrink: 0; border-radius: 5px; display: flex; align-items: center; justify-content: center; }
        .item-cover-placeholder svg { width: 14px; height: 14px; fill: rgba(255,255,255,.55); }
        .order-card.cancelled .item-cover,
        .order-card.cancelled .item-cover-placeholder { filter: grayscale(30%); }

        .item-info { flex: 1; min-width: 0; }
        .item-title { font-size: .88rem; font-weight: 500; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .item-qty   { font-size: .75rem; color: var(--muted); margin-top: .12rem; }
        .item-price { font-family: 'Cormorant Garamond', serif; font-size: 1rem; font-weight: 600; color: var(--plum); white-space: nowrap; }
        .order-card.cancelled .item-price { color: var(--muted); }

        .card-footer {
            padding: .95rem 1.4rem; border-top: 1.5px solid rgba(232,197,208,.35);
            background: linear-gradient(180deg, transparent 0%, rgba(232,197,208,.07) 100%);
        }
        .order-card.cancelled .card-footer { background: linear-gradient(180deg, transparent 0%, rgba(192,64,58,.04) 100%); }

        .shipping-info { display: grid; grid-template-columns: 1fr 1fr; gap: .2rem .5rem; margin-bottom: .9rem; }
        .ship-label { font-size: .68rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; padding: .28rem 0; display: flex; align-items: center; }
        .ship-value { font-size: .82rem; color: var(--ink); padding: .28rem 0; text-align: right; }

        .total-row {
            display: flex; align-items: center; justify-content: space-between;
            padding-top: .8rem; border-top: 1.5px solid rgba(232,197,208,.4); margin-top: .4rem;
        }
        .total-label { font-size: .88rem; font-weight: 600; color: var(--muted); }
        .total-value { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 600; color: var(--plum); }
        .order-card.cancelled .total-value { color: var(--muted); text-decoration: line-through; }

        .cancelled-notice {
            display: flex; align-items: center; gap: .55rem;
            background: rgba(192,64,58,.07); border: 1px solid rgba(192,64,58,.18);
            border-radius: 8px; padding: .55rem .85rem; margin-bottom: .85rem;
            font-size: .8rem; color: var(--error);
        }

        /* ── Buttons ── */
        .card-actions { display: flex; gap: .55rem; justify-content: flex-end; margin-top: .85rem; flex-wrap: wrap; }
        .btn-action {
            padding: .42rem 1.1rem; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .82rem; font-weight: 600;
            cursor: pointer; transition: all .2s; border: 1.5px solid transparent;
            text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
            background: none;
        }
        .btn-primary { background: var(--plum); color: var(--white); border-color: var(--plum); }
        .btn-primary:hover { background: var(--plum-mid); border-color: var(--plum-mid); }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .btn-outline { background: rgba(74,44,94,.08); color: var(--plum); border-color: rgba(122,101,133,.3); }
        .btn-outline:hover { background: rgba(74,44,94,.14); }
        .btn-danger { color: var(--error); border-color: rgba(192,64,58,.3); }
        .btn-danger:hover { background: rgba(192,64,58,.07); }
        .btn-danger:disabled { opacity: .6; cursor: not-allowed; }

        /* ── Empty ── */
        .empty-state {
            background: rgba(255,255,255,.82); backdrop-filter: blur(6px);
            border: 1.5px solid rgba(122,101,133,.22); border-radius: var(--radius-lg);
            padding: 5rem 2rem; text-align: center; box-shadow: var(--shadow);
        }
        .empty-icon  { font-size: 3rem; color: rgba(122,101,133,.3); margin-bottom: 1rem; display: block; }
        .empty-title { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 600; color: var(--muted); }
        .empty-sub   { font-size: .82rem; color: var(--muted); margin-top: .3rem; }
        .btn-empty {
            display: inline-block; margin-top: 1.3rem; padding: .55rem 1.5rem;
            background: var(--plum); color: var(--white); border-radius: var(--radius-sm);
            text-decoration: none; font-size: .86rem; font-weight: 600; transition: background .2s;
        }
        .btn-empty:hover { background: var(--plum-mid); }

        .error-banner {
            background: rgba(192,64,58,.08); border: 1.5px solid rgba(192,64,58,.25);
            color: var(--error); border-radius: var(--radius-sm);
            padding: .75rem 1rem; margin-bottom: 1.2rem; font-size: .86rem;
        }

        .grad-0 { background: linear-gradient(135deg,#4a2c5e,#9b6bb5); }
        .grad-1 { background: linear-gradient(135deg,#1e3a5f,#4a7fb5); }
        .grad-2 { background: linear-gradient(135deg,#2d5a3d,#68a87e); }
        .grad-3 { background: linear-gradient(135deg,#5a2d3a,#b57a8a); }
        .grad-4 { background: linear-gradient(135deg,#5a3a1e,#b58a4a); }
        .grad-5 { background: linear-gradient(135deg,#2d2a5e,#7a77b5); }

        /* ══════════════════════════════
           MODAL
        ══════════════════════════════ */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 200;
            background: rgba(42,26,53,.52); backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            align-items: center; justify-content: center; padding: 1.5rem;
        }
        .modal-overlay.open { display: flex; }

        .modal-box {
            background: var(--white); border-radius: var(--radius-lg);
            padding: 2rem; max-width: 400px; width: 100%;
            box-shadow: 0 32px 72px rgba(42,26,53,.28);
            animation: modalPop .28s cubic-bezier(.34,1.56,.64,1) both;
        }
        @keyframes modalPop {
            from { opacity: 0; transform: scale(.92) translateY(12px); }
            to   { opacity: 1; transform: none; }
        }
        .modal-box.modal-danger { border-top: 3px solid var(--error); }

        .modal-icon-wrap {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; margin-bottom: 1rem;
        }
        .modal-icon-wrap.success { background: rgba(42,138,94,.1); color: var(--success); }
        .modal-icon-wrap.danger  { background: rgba(192,64,58,.1);  color: var(--error); }

        .modal-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.2rem; font-weight: 600; color: var(--ink); margin-bottom: .4rem;
        }
        .modal-desc { font-size: .85rem; color: var(--muted); line-height: 1.6; margin-bottom: 1rem; }

        .modal-order-chip {
            display: flex; align-items: center; gap: .5rem;
            background: rgba(74,44,94,.05); border: 1px solid rgba(122,101,133,.18);
            border-radius: 8px; padding: .55rem .85rem; margin-bottom: 1.4rem;
            font-size: .85rem; font-weight: 600; color: var(--ink);
            font-family: 'Cormorant Garamond', serif;
        }
        .modal-order-chip i { color: var(--plum-mid); opacity: .7; }

        .modal-footer { display: flex; gap: .5rem; justify-content: flex-end; flex-wrap: wrap; }

        /* ── Toast ── */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999;
            padding: .7rem 1.15rem; border-radius: var(--radius-sm);
            color: var(--white); font-size: .87rem;
            display: flex; align-items: center; gap: .5rem;
            box-shadow: 0 8px 24px rgba(42,26,53,.2);
            transform: translateY(80px); opacity: 0;
            transition: all .35s cubic-bezier(.34,1.26,.64,1); pointer-events: none;
        }

        @media (max-width: 600px) {
            .shipping-info { grid-template-columns: 1fr; }
            .ship-value    { text-align: left; }
            .order-meta    { gap: .9rem; }
            #logo-name-text { display: none; }
        }
    </style>
</head>
<body>

<!-- ════════════ NAVBAR ════════════ -->
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
            <div class="dropdown-wrap">
                <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                    <i class="fas fa-user-circle" style="font-size:1.45rem; color:var(--plum-mid);"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="/literaspace/pages/profile.php">
                        <i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya
                    </a>
                    <a href="/literaspace/pages/pesanan.php" style="color:var(--plum); font-weight:600;">
                        <i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya
                    </a>
                    <hr/>
                    <a href="/literaspace/auth/logout.php">
                        <i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ════════════ MAIN ════════════ -->
<main class="page-inner">

    <?php if ($error): ?>
        <div class="error-banner">
            <i class="fas fa-exclamation-circle" style="margin-right:.4rem;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="page-heading">
        <h1>Pesanan Saya</h1>
        <p>Lihat dan kelola semua pesanan kamu</p>
    </div>

    <!-- Tabs -->
    <div class="tabs-wrap">
        <?php
        $tab_labels = [
            'semua'      => 'Semua',
            'dikemas'    => 'Dikemas',
            'dikirim'    => 'Dikirim',
            'selesai'    => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
        ];
        foreach ($tab_labels as $key => $label):
            $is_active   = $active_tab === $key;
            $extra_class = $is_active ? ($key === 'dibatalkan' ? 'active-cancel' : 'active') : '';
        ?>
            <a href="pesanan.php?status=<?= $key ?>" class="tab-btn <?= $extra_class ?>">
                <?php if ($key === 'dibatalkan'): ?>
                    <i class="fas fa-times-circle" style="margin-right:.3rem; font-size:.75rem;"></i>
                <?php endif; ?>
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Daftar Pesanan -->
    <?php if (empty($filtered)): ?>
        <div class="empty-state">
            <?php if ($active_tab === 'dibatalkan'): ?>
                <i class="fas fa-times-circle empty-icon" style="color:rgba(192,64,58,.25);"></i>
                <p class="empty-title">Tidak ada pesanan dibatalkan</p>
                <p class="empty-sub">Syukurlah, semua pesananmu berjalan lancar!</p>
            <?php else: ?>
                <i class="fas fa-box-open empty-icon"></i>
                <p class="empty-title">Belum ada pesanan di sini</p>
                <p class="empty-sub">Yuk mulai belanja buku favoritmu!</p>
                <a href="/literaspace/pages/kategori.php" class="btn-empty">Jelajahi Katalog</a>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <?php foreach ($filtered as $i => $order):
            $sc = $status_config[$order['status']] ?? [
                'label' => ucfirst($order['status']),
                'color' => '#7a6585',
                'bg'    => 'rgba(122,101,133,.1)',
                'icon'  => 'fa-circle'
            ];
            $is_cancelled  = $order['status'] === 'dibatalkan';

            // ✅ Ambil judul pertama untuk label modal — escape aman untuk atribut JS
            $judul_pertama = !empty($order['items']) ? $order['items'][0]['judul'] : '';
            $judul_js      = addslashes(htmlspecialchars($judul_pertama, ENT_QUOTES));
            $suffix_js     = count($order['items']) > 1 ? ', dll.' : '';
        ?>
        <div class="order-card <?= $is_cancelled ? 'cancelled' : '' ?>">

            <!-- Header -->
            <div class="card-header">
                <div class="order-meta">
                    <div class="meta-item">
                        <span class="meta-label">No. Pesanan</span>
                        <span class="meta-value">#<?= htmlspecialchars($order['id_pesanan']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Tanggal</span>
                        <span class="meta-value"><?= formatTanggal($order['tanggal']) ?></span>
                    </div>
                    <?php if (!empty($order['no_resi'])): ?>
                    <div class="meta-item">
                        <span class="meta-label">No. Resi</span>
                        <span class="meta-value"><?= htmlspecialchars($order['no_resi']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="status-badge" style="color:<?= $sc['color'] ?>; background:<?= $sc['bg'] ?>;">
                    <i class="fas <?= $sc['icon'] ?>" style="font-size:.7rem;"></i>
                    <?= $sc['label'] ?>
                </span>
            </div>

            <!-- Items -->
            <div class="card-items">
                <?php
                $cover_gradients = ['grad-0','grad-1','grad-2','grad-3','grad-4','grad-5'];
                foreach ($order['items'] as $j => $item):
                    $grad = $cover_gradients[($i * 3 + $j) % count($cover_gradients)];
                ?>
                <div class="item-row">
                    <?php if (!empty($item['cover'])): ?>
                        <img src="/assets/images/covers/<?= htmlspecialchars($item['cover']) ?>"
                             class="item-cover" alt="<?= htmlspecialchars($item['judul']) ?>"
                             onerror="this.style.display='none';" />
                    <?php else: ?>
                        <div class="item-cover-placeholder <?= $grad ?>">
                            <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg>
                        </div>
                    <?php endif; ?>
                    <div class="item-info">
                        <p class="item-title"><?= htmlspecialchars($item['judul']) ?></p>
                        <p class="item-qty"><?= $item['qty'] ?> × <?= formatRupiah($item['harga']) ?></p>
                    </div>
                    <p class="item-price"><?= formatRupiah($item['qty'] * $item['harga']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer -->
            <div class="card-footer">

                <?php if ($is_cancelled): ?>
                <div class="cancelled-notice">
                    <i class="fas fa-info-circle"></i>
                    Pesanan ini telah dibatalkan dan tidak akan diproses lebih lanjut.
                </div>
                <?php endif; ?>

                <div class="shipping-info">
                    <span class="ship-label">Metode Pengiriman</span>
                    <span class="ship-value"><?= htmlspecialchars($order['metode_kirim']) ?></span>
                    <span class="ship-label">Metode Pembayaran</span>
                    <span class="ship-value"><?= htmlspecialchars($order['metode_bayar']) ?></span>
                    <span class="ship-label">Alamat Pengiriman</span>
                    <span class="ship-value"><?= htmlspecialchars($order['alamat']) ?></span>
                </div>

                <div class="total-row">
                    <span class="total-label">Total Pembayaran</span>
                    <span class="total-value"><?= formatRupiah($order['total']) ?></span>
                </div>

                <!-- ✅ FIX: Tombol aksi lengkap per status -->
                <div class="card-actions">
                    <?php if ($order['status'] === 'dikemas'): ?>
                        <!-- ✅ Batalkan hanya saat dikemas (belum dikirim) -->
                        <button class="btn-action btn-danger"
                                onclick="openBatalModal(<?= (int)$order['id_pesanan'] ?>, '<?= $judul_js ?>', '<?= $suffix_js ?>')">
                            <i class="fas fa-times" style="font-size:.75rem;"></i>
                            Batalkan Pesanan
                        </button>

                    <?php elseif ($order['status'] === 'dikirim'): ?>
                        <!-- Konfirmasi terima saat status dikirim -->
                        <button class="btn-action btn-primary"
                                onclick="openKonfirmasiModal(<?= (int)$order['id_pesanan'] ?>, '<?= $judul_js ?>', '<?= $suffix_js ?>')">
                            <i class="fas fa-check" style="font-size:.75rem;"></i>
                            Konfirmasi Terima
                        </button>

                    <?php elseif ($order['status'] === 'selesai'): ?>
                        <a href="/literaspace/pages/katalog.php" class="btn-action btn-outline">
                            <i class="fas fa-shopping-bag" style="font-size:.75rem;"></i>
                            Beli Lagi
                        </a>

                    <?php elseif ($order['status'] === 'dibatalkan'): ?>
                        <a href="/literaspace/pages/katalog.php" class="btn-action btn-outline">
                            <i class="fas fa-redo" style="font-size:.75rem;"></i>
                            Belanja Lagi
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<!-- ════════════════════════════════
     MODAL: KONFIRMASI TERIMA
════════════════════════════════ -->
<div class="modal-overlay" id="konfirmasiModal">
    <div class="modal-box">
        <div class="modal-icon-wrap success">
            <i class="fas fa-box-open"></i>
        </div>
        <h3 class="modal-title">Sudah Terima Pesanan?</h3>
        <p class="modal-desc">
            Pastikan semua item dalam kondisi baik sebelum mengkonfirmasi. Tindakan ini tidak dapat dibatalkan.
        </p>
        <div class="modal-order-chip" id="konfirmasi-chip">
            <i class="fas fa-box fa-fw"></i>
            <span id="konfirmasi-label">—</span>
        </div>
        <div class="modal-footer">
            <button class="btn-action btn-outline" onclick="closeModal('konfirmasiModal')">
                Belum, Kembali
            </button>
            <button class="btn-action btn-primary" id="konfirmasi-btn" onclick="submitKonfirmasi()">
                <i class="fas fa-check" style="font-size:.75rem;"></i>
                Ya, Sudah Diterima
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════
     MODAL: BATALKAN PESANAN (✅ BARU)
════════════════════════════════ -->
<div class="modal-overlay" id="batalModal">
    <div class="modal-box modal-danger">
        <div class="modal-icon-wrap danger">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title">Batalkan Pesanan?</h3>
        <p class="modal-desc">
            Pesanan yang dibatalkan tidak bisa dikembalikan ke status semula.
            Pembatalan hanya bisa dilakukan saat pesanan masih berstatus <strong style="color:var(--ink);">Dikemas</strong>.
        </p>
        <div class="modal-order-chip" id="batal-chip">
            <i class="fas fa-box fa-fw"></i>
            <span id="batal-label">—</span>
        </div>
        <div class="modal-footer">
            <button class="btn-action btn-outline" onclick="closeModal('batalModal')">
                Tidak, Kembali
            </button>
            <button class="btn-action btn-danger" id="batal-btn"
                    style="background:rgba(192,64,58,.08);"
                    onclick="submitBatal()">
                <i class="fas fa-times" style="font-size:.75rem;"></i>
                Ya, Batalkan
            </button>
        </div>
    </div>
</div>

<!-- ════════════ TOAST ════════════ -->
<div id="toast">
    <i class="fas fa-check-circle" id="toast-icon"></i>
    <span id="toast-msg">Berhasil!</span>
</div>

<script>
// ── State ──
let activeOrderId = null;

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Tutup saat klik backdrop
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// Tutup dengan Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
});

// ── Toast ──
function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    document.getElementById('toast-icon').className  = ok ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    t.style.background = ok ? '#2a8a5e' : '#c0403a';
    t.style.transform  = 'translateY(0)';
    t.style.opacity    = '1';
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 3000);
}

// ── Modal Konfirmasi Terima ──
function openKonfirmasiModal(id, judul, suffix) {
    activeOrderId = id;
    document.getElementById('konfirmasi-label').textContent = 'Pesanan #' + id + (judul ? ' — ' + judul + suffix : '');
    openModal('konfirmasiModal');
}

function submitKonfirmasi() {
    if (!activeOrderId) return;
    const btn = document.getElementById('konfirmasi-btn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.75rem;"></i> Memproses...';

    fetch('/literaspace/api/pesanan-konfirmasi.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id_pesanan: activeOrderId })
    })
    .then(r => r.json())
    .then(data => {
        closeModal('konfirmasiModal');
        if (data.success) {
            showToast('Pesanan berhasil dikonfirmasi! 🎉');
            setTimeout(() => location.reload(), 1600);
        } else {
            showToast(data.message || 'Gagal mengkonfirmasi pesanan.', false);
        }
    })
    .catch(() => { closeModal('konfirmasiModal'); showToast('Terjadi kesalahan jaringan.', false); })
    .finally(() => {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-check" style="font-size:.75rem;"></i> Ya, Sudah Diterima';
    });
}

// ── Modal Batalkan (✅ BARU) ──
function openBatalModal(id, judul, suffix) {
    activeOrderId = id;
    document.getElementById('batal-label').textContent = 'Pesanan #' + id + (judul ? ' — ' + judul + suffix : '');
    openModal('batalModal');
}

function submitBatal() {
    if (!activeOrderId) return;
    const btn = document.getElementById('batal-btn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.75rem;"></i> Memproses...';

    fetch('/literaspace/api/pesanan-batal.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id_pesanan: activeOrderId })
    })
    .then(r => r.json())
    .then(data => {
        closeModal('batalModal');
        if (data.success) {
            showToast('Pesanan berhasil dibatalkan.');
            setTimeout(() => location.reload(), 1600);
        } else {
            showToast(data.message || 'Gagal membatalkan pesanan.', false);
        }
    })
    .catch(() => { closeModal('batalModal'); showToast('Terjadi kesalahan jaringan.', false); })
    .finally(() => {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-times" style="font-size:.75rem;"></i> Ya, Batalkan';
    });
}

<?php if (isset($_GET['success'])): ?>
showToast('<?= addslashes(htmlspecialchars($_GET['success'])) ?>');
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
showToast('<?= addslashes(htmlspecialchars($_GET['error'])) ?>', false);
<?php endif; ?>

 </script>
</body>
</html>