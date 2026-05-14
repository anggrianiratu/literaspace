<?php
// pages/profile.php
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$user_id = (int) $_SESSION['user_id'];
$pdo     = getDB();

$stmt = $pdo->prepare("SELECT id, nama_depan, nama_belakang, email, telepon, alamat, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

$q = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE id_user = ?");
$q->execute([$user_id]); $total_pesanan = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE id_user = ? AND status_pesanan = 'selesai'");
$q->execute([$user_id]); $pesanan_selesai = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ?");
$q->execute([$user_id]); $total_wishlist = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?");
$q->execute([$user_id]); $total_keranjang = (int)$q->fetchColumn();

function formatTgl($d) {
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    [$y, $m, $rest] = explode('-', $d);
    return (int)substr($rest, 0, 2) . ' ' . $bulan[(int)$m] . ' ' . $y;
}

$nama_lengkap = htmlspecialchars(trim($user['nama_depan'] . ' ' . $user['nama_belakang']));
$inisial      = strtoupper(substr($user['nama_depan'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Profil Saya — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
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

        .navbar, main, #toast {
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

        /* ══ LAYOUT ══ */
        .page-inner {
            max-width: 1100px; margin: 0 auto;
            padding: 2rem 1.5rem 3rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 1.5rem;
            align-items: flex-start;
        }
        @media (max-width: 820px) { .profile-grid { grid-template-columns: 1fr; } }

        /* ══ CARD ══ */
        .card {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-lg);
            border: 1.5px solid rgba(122,101,133,.25);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* ══ SIDEBAR ══ */
        .avatar-wrap {
            display: flex; flex-direction: column; align-items: center;
            padding: 2rem 1.5rem 1.4rem;
            border-bottom: 1.5px solid rgba(232,197,208,.4);
            background: linear-gradient(160deg, rgba(232,197,208,.18) 0%, transparent 60%);
        }
        .avatar-ring {
            width: 88px; height: 88px; border-radius: 50%;
            background: linear-gradient(135deg, var(--plum), var(--plum-light));
            display: flex; align-items: center; justify-content: center;
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem; font-weight: 600; color: var(--white);
            box-shadow: 0 6px 24px rgba(74,44,94,.25);
        }
        .avatar-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem; font-weight: 600; color: var(--ink);
            margin-top: .85rem; text-align: center;
        }
        .avatar-email {
            font-size: .76rem; color: var(--muted);
            margin-top: .2rem; text-align: center;
        }
        .avatar-since {
            font-size: .7rem; color: var(--muted);
            margin-top: .55rem;
            background: rgba(122,101,133,.1);
            padding: .2rem .65rem; border-radius: 9999px;
            font-family: 'Jost', sans-serif;
        }

        .side-menu { padding: .6rem; }
        .side-menu a {
            display: flex; align-items: center; gap: .7rem;
            padding: .62rem .8rem; border-radius: var(--radius-sm);
            font-size: .86rem; font-weight: 500; color: var(--ink);
            text-decoration: none; transition: background .15s, color .15s;
            font-family: 'Jost', sans-serif;
        }
        .side-menu a i { width: 16px; text-align: center; color: var(--muted); font-size: .88rem; }
        .side-menu a:hover { background: rgba(74,44,94,.06); color: var(--plum-mid); }
        .side-menu a:hover i { color: var(--plum-mid); }
        .side-menu a.active {
            background: rgba(74,44,94,.10); color: var(--plum); font-weight: 600;
        }
        .side-menu a.active i { color: var(--plum); }
        .side-menu a.danger { color: var(--error); }
        .side-menu a.danger i { color: var(--error); }
        .side-menu a.danger:hover { background: rgba(192,64,58,.07); }
        .side-menu hr { border: none; border-top: 1.5px solid rgba(232,197,208,.4); margin: .4rem 0; }

        /* ══ STATS ══ */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 1rem; margin-bottom: 1.5rem;
        }
        @media (max-width: 600px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

        .stat-card {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(6px);
            border: 1.5px solid rgba(122,101,133,.2);
            border-radius: var(--radius-sm);
            padding: 1rem 1.1rem; text-align: center;
            box-shadow: var(--shadow);
        }
        .stat-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem; font-weight: 600;
            color: var(--plum); line-height: 1;
        }
        .stat-label { font-size: .74rem; color: var(--muted); margin-top: .3rem; font-family: 'Jost', sans-serif; }
        .stat-card.success .stat-num { color: var(--success); }
        .stat-card.amber   .stat-num { color: var(--amber); }

        /* ══ SECTION TITLE ══ */
        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem; font-weight: 600; color: var(--ink);
            padding: 1.1rem 1.4rem;
            border-bottom: 1.5px solid rgba(232,197,208,.4);
            display: flex; align-items: center; justify-content: space-between;
        }

        .btn-edit-header {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: .8rem; font-weight: 500; color: var(--plum-mid);
            background: rgba(74,44,94,.08); border: none;
            border-radius: var(--radius-sm); padding: .32rem .8rem;
            cursor: pointer; font-family: 'Jost', sans-serif;
            transition: background .15s;
        }
        .btn-edit-header:hover { background: rgba(74,44,94,.15); }

        /* ══ INFO GRID ══ */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; }
        @media (max-width: 560px) { .info-grid { grid-template-columns: 1fr; } }

        .info-row {
            padding: .9rem 1.4rem;
            border-bottom: 1.5px solid rgba(232,197,208,.3);
            display: flex; flex-direction: column; gap: .22rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row.full { grid-column: 1 / -1; }

        .info-label {
            font-size: .7rem; font-weight: 600; color: var(--muted);
            text-transform: uppercase; letter-spacing: .06em;
            font-family: 'Jost', sans-serif;
        }
        .info-value {
            font-size: .9rem; font-weight: 500; color: var(--ink);
            font-family: 'Jost', sans-serif;
        }
        .info-value.empty { color: var(--muted); font-style: italic; font-weight: 400; }

        /* ══ FORM ══ */
        .form-wrap { padding: 1.4rem; display: none; }
        .form-wrap.show { display: block; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 560px) { .form-grid { grid-template-columns: 1fr; } }

        .form-group { display: flex; flex-direction: column; gap: .38rem; }
        .form-group.full { grid-column: 1 / -1; }

        .form-label {
            font-size: .74rem; font-weight: 600; color: var(--muted);
            font-family: 'Jost', sans-serif; letter-spacing: .04em;
        }
        .form-input {
            padding: .58rem .85rem;
            border: 1.5px solid rgba(122,101,133,.3);
            border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .88rem; color: var(--ink);
            background: rgba(255,255,255,.8);
            transition: border-color .2s, box-shadow .2s; outline: none; width: 100%;
        }
        .form-input:focus {
            border-color: var(--plum-mid);
            box-shadow: 0 0 0 3px rgba(107,63,130,.1);
            background: var(--white);
        }

        .form-actions {
            display: flex; gap: .65rem; margin-top: 1.2rem;
            justify-content: flex-end;
        }
        .btn-save {
            padding: .52rem 1.3rem;
            background: var(--plum); color: var(--white);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .86rem; font-weight: 600;
            cursor: pointer; transition: background .2s;
        }
        .btn-save:hover { background: var(--plum-mid); }
        .btn-cancel-form {
            padding: .52rem 1.1rem;
            background: rgba(122,101,133,.1); color: var(--ink);
            border: none; border-radius: var(--radius-sm);
            font-family: 'Jost', sans-serif; font-size: .86rem; font-weight: 500;
            cursor: pointer; transition: background .15s;
        }
        .btn-cancel-form:hover { background: rgba(122,101,133,.18); }

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
            <a href="/literaspace/pages/keranjang.php" class="nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($total_keranjang > 0): ?>
                    <span class="nav-badge"><?= min($total_keranjang, 99) ?></span>
                <?php endif; ?>
            </a>

            <a href="/literaspace/pages/wishlist.php" class="nav-icon">
                <i class="far fa-heart"></i>
                <?php if ($total_wishlist > 0): ?>
                    <span class="nav-badge"><?= min($total_wishlist, 99) ?></span>
                <?php endif; ?>
            </a>

            <div class="dropdown-wrap">
                <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                    <i class="fas fa-user-circle" style="font-size:1.45rem; color:var(--plum-mid);"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="/literaspace/pages/profile.php" style="color:var(--plum); font-weight:600;">
                        <i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya
                    </a>
                    <a href="/literaspace/pages/pesanan.php">
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

<!-- ══ MAIN ══ -->
<main class="page-inner">

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-num"><?= $total_pesanan ?></div>
            <div class="stat-label">Total Pesanan</div>
        </div>
        <div class="stat-card success">
            <div class="stat-num"><?= $pesanan_selesai ?></div>
            <div class="stat-label">Pesanan Selesai</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-num"><?= $total_wishlist ?></div>
            <div class="stat-label">Wishlist</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= $total_keranjang ?></div>
            <div class="stat-label">Di Keranjang</div>
        </div>
    </div>

    <div class="profile-grid">

        <!-- ── Sidebar ── -->
        <div>
            <div class="card">
                <div class="avatar-wrap">
                    <div class="avatar-ring"><?= $inisial ?></div>
                    <p class="avatar-name"><?= $nama_lengkap ?></p>
                    <p class="avatar-email"><?= htmlspecialchars($user['email']) ?></p>
                    <span class="avatar-since">Bergabung <?= formatTgl(substr($user['created_at'], 0, 10)) ?></span>
                </div>
                <nav class="side-menu">
                    <a href="profile.php" class="active">
                        <i class="fas fa-user"></i> Profil Saya
                    </a>
                    <a href="pesanan.php">
                        <i class="fas fa-box"></i> Pesanan Saya
                    </a>
                    <a href="wishlist.php">
                        <i class="far fa-heart"></i> Wishlist
                    </a>
                    <hr/>
                    <a href="../auth/logout.php" class="danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
        </div>

        <!-- ── Konten Kanan ── -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">

            <!-- Informasi Pribadi -->
            <div class="card">
                <div class="section-title">
                    <span>Informasi Pribadi</span>
                    <button class="btn-edit-header" id="btn-toggle-edit" onclick="toggleEdit()">
                        <i class="fas fa-pen"></i> Edit
                    </button>
                </div>

                <!-- View Mode -->
                <div id="view-mode">
                    <div class="info-grid">
                        <div class="info-row">
                            <span class="info-label">Nama Depan</span>
                            <span class="info-value"><?= htmlspecialchars($user['nama_depan']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Nama Belakang</span>
                            <span class="info-value"><?= htmlspecialchars($user['nama_belakang']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">No. Telepon</span>
                            <span class="info-value <?= empty($user['telepon']) ? 'empty' : '' ?>">
                                <?= !empty($user['telepon']) ? htmlspecialchars($user['telepon']) : 'Belum diisi' ?>
                            </span>
                        </div>
                        <div class="info-row full">
                            <span class="info-label">Alamat</span>
                            <span class="info-value <?= empty($user['alamat']) ? 'empty' : '' ?>">
                                <?= !empty($user['alamat']) ? htmlspecialchars($user['alamat']) : 'Belum diisi' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Edit Mode -->
                <div class="form-wrap" id="edit-mode">
                    <form method="POST" action="profile-update.php">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Nama Depan</label>
                                <input class="form-input" type="text" name="nama_depan" value="<?= htmlspecialchars($user['nama_depan']) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nama Belakang</label>
                                <input class="form-input" type="text" name="nama_belakang" value="<?= htmlspecialchars($user['nama_belakang']) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">No. Telepon</label>
                                <input class="form-input" type="text" name="telepon" value="<?= htmlspecialchars($user['telepon'] ?? '') ?>" placeholder="08xxxxxxxxxx"/>
                            </div>
                            <div class="form-group full">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-input" name="alamat" rows="2" placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-cancel-form" onclick="toggleEdit()">Batal</button>
                            <button type="submit" class="btn-save">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Keamanan Akun -->
            <div class="card">
                <div class="section-title">
                    <span>Keamanan Akun</span>
                    <button class="btn-edit-header" onclick="togglePassword()">
                        <i class="fas fa-lock"></i> Ubah Password
                    </button>
                </div>

                <!-- Password placeholder -->
                <div id="password-placeholder" style="padding:1.1rem 1.4rem;">
                    <p style="font-size:.84rem; color:var(--muted); font-family:'Jost',sans-serif;">
                        <i class="fas fa-shield-alt" style="margin-right:.4rem; color:var(--success);"></i>
                        Password kamu aman. Klik <strong style="color:var(--ink);">Ubah Password</strong> untuk memperbaruinya.
                    </p>
                </div>

                <!-- Password form -->
                <div class="form-wrap" id="password-mode">
                    <form method="POST" action="password-update.php">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label class="form-label">Password Lama</label>
                                <input class="form-input" type="password" name="password_lama" placeholder="Masukkan password lama" required/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password Baru</label>
                                <input class="form-input" type="password" name="password_baru" placeholder="Min. 8 karakter" minlength="8" required/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Konfirmasi Password</label>
                                <input class="form-input" type="password" name="konfirmasi_password" placeholder="Ulangi password baru" required/>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-cancel-form" onclick="togglePassword()">Batal</button>
                            <button type="submit" class="btn-save">Simpan Password</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</main>

<!-- ══ TOAST ══ -->
<div id="toast">
    <i class="fas fa-check-circle" id="toast-icon"></i>
    <span id="toast-msg">Berhasil!</span>
</div>

<script>
function toggleEdit() {
    const form = document.getElementById('edit-mode');
    const view = document.getElementById('view-mode');
    const btn  = document.getElementById('btn-toggle-edit');
    if (form.classList.contains('show')) {
        form.classList.remove('show');
        view.style.display = '';
        btn.innerHTML = '<i class="fas fa-pen"></i> Edit';
    } else {
        form.classList.add('show');
        view.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-times"></i> Batal';
    }
}

function togglePassword() {
    const form        = document.getElementById('password-mode');
    const placeholder = document.getElementById('password-placeholder');
    if (form.classList.contains('show')) {
        form.classList.remove('show');
        placeholder.style.display = 'block';
    } else {
        form.classList.add('show');
        placeholder.style.display = 'none';
    }
}

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

<?php if (isset($_GET['updated'])): ?>showToast('Profil berhasil diperbarui!');<?php endif; ?>
<?php if (isset($_GET['pwupdated'])): ?>showToast('Password berhasil diperbarui!');<?php endif; ?>
<?php if (isset($_GET['error'])): ?>showToast('<?= addslashes(htmlspecialchars($_GET['error'])) ?>', false);<?php endif; ?>
</script>
</body>
</html>