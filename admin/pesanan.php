<?php
// admin/pesanan.php - Manajemen Pesanan
error_reporting(E_ALL);
ini_set('display_errors', 0);

define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDB();
$admin_id = (int) $_SESSION['user_id'];

$success_message = null;
$error_message = null;

// Verify admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Get admin initial
$stmt = $pdo->prepare("SELECT nama_depan FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin_data = $stmt->fetch();
$admin_initial = strtoupper(substr($admin_data['nama_depan'], 0, 1));

// Handle POST - Update Status Pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $order_id = (int) $_POST['id_pesanan'];
    $new_status = in_array($_POST['status'] ?? '', ['diproses', 'dikemas', 'dikirim', 'selesai']) ? $_POST['status'] : 'diproses';
    $no_resi = trim($_POST['no_resi'] ?? '');

    try {
        if ($no_resi && $new_status === 'dikirim') {
            $stmt = $pdo->prepare("UPDATE pesanan SET status_pesanan = ?, no_resi = ? WHERE id_pesanan = ?");
            $stmt->execute([$new_status, $no_resi, $order_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE pesanan SET status_pesanan = ? WHERE id_pesanan = ?");
            $stmt->execute([$new_status, $order_id]);
        }
        $success_message = "Status pesanan berhasil diperbarui.";
    } catch (Exception $e) {
        $error_message = "Gagal memperbarui status pesanan.";
    }
}

// Filters & Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$filter_status = in_array($_GET['status'] ?? '', ['diproses', 'dikemas', 'dikirim', 'selesai']) ? $_GET['status'] : '';
$sort_by = in_array($_GET['sort'] ?? '', ['terbaru', 'terakhir', 'tertinggi', 'terendah']) ? $_GET['sort'] : 'terbaru';

$per_page = 10;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];

if ($search) {
    $where[] = "(u.nama_depan LIKE ? OR u.email LIKE ? OR p.id_pesanan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_status) {
    $where[] = "p.status_pesanan = ?";
    $params[] = $filter_status;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$order_by = 'p.tanggal_pesan DESC';
if ($sort_by === 'tertinggi') $order_by = 'p.total_harga DESC';
elseif ($sort_by === 'terendah') $order_by = 'p.total_harga ASC';
elseif ($sort_by === 'terakhir') $order_by = 'p.tanggal_pesan ASC';

$count_sql = "SELECT COUNT(*) FROM pesanan p JOIN users u ON p.id_user = u.id $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_orders = (int)$stmt->fetchColumn();
$total_pages = max(1, ceil($total_orders / $per_page));

$sql = "
    SELECT p.id_pesanan, p.tanggal_pesan, p.total_harga, p.status_pesanan, p.metode_pembayaran, p.no_resi,
           u.nama_depan, u.nama_belakang, u.email, u.telepon,
           COUNT(dp.id_detail) as jumlah_item
    FROM pesanan p
    JOIN users u ON p.id_user = u.id
    LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
    $where_clause
    GROUP BY p.id_pesanan
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmt = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'diproses'");
$pending = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan IN ('dikemas', 'dikirim')");
$shipping = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'selesai'");
$completed = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_harga) FROM pesanan WHERE status_pesanan = 'selesai'");
$total_revenue = (int)($stmt->fetchColumn() ?? 0);

// Helper functions
function formatRupiah($n) {
    return 'Rp ' . number_format((int)$n, 0, ',', '.');
}

function formatTanggal($date) {
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    [$y, $m, $d] = explode('-', substr($date, 0, 10));
    return (int)$d . ' ' . $bulan[(int)$m] . ' ' . $y;
}

function getStatusBadge($status) {
    $colors = [
        'diproses' => ['bg' => 'rgba(255,165,2,.1)', 'color' => '#ff8c00', 'label' => 'Diproses'],
        'dikemas' => ['bg' => 'rgba(9,132,227,.1)', 'color' => '#0984e3', 'label' => 'Dikemas'],
        'dikirim' => ['bg' => 'rgba(124,92,250,.1)', 'color' => '#7c5cfa', 'label' => 'Dikirim'],
        'selesai' => ['bg' => 'rgba(46,213,115,.1)', 'color' => '#2ed573', 'label' => 'Selesai'],
    ];
    $style = $colors[$status] ?? $colors['diproses'];
    return '<span style="background:' . $style['bg'] . '; color:' . $style['color'] . '; padding:0.4rem 0.75rem; border-radius:8px; font-size:0.85rem; font-weight:600;">' . $style['label'] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manajemen Pesanan — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
        :root {
            --indigo-deep:  #1e1667;
            --indigo-mid:   #2d2a8f;
            --indigo-light: #3b2ec0;
            --indigo-accent: #7c5cfa;
            --white:        #ffffff;
            --gray-50:      #f8f8fb;
            --gray-100:     #f0f0f7;
            --gray-200:     #e2e2ef;
            --gray-300:     #d5d5e8;
            --gray-500:     #6b6b8a;
            --gray-600:     #4a4a68;
            --gray-700:     #3a3a52;
            --gray-800:     #1a1a2e;
            --error:        #ff4757;
            --success:      #2ed573;
            --warning:      #ffa502;
            --info:         #0984e3;
            --radius:       16px;
            --shadow:       0 8px 32px rgba(30,22,103,.12);
            --shadow-lg:    0 16px 48px rgba(30,22,103,.16);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, #f5f2ff 100%);
            color: var(--gray-800);
            min-height: 100vh;
            display: flex;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed; left: 0; top: 0;
            width: 260px; height: 100vh;
            background: linear-gradient(180deg, var(--indigo-deep) 0%, var(--indigo-mid) 100%);
            box-shadow: 4px 0 24px rgba(30,22,103,.15);
            z-index: 40; overflow-y: auto; padding-top: 1.5rem;
        }

        .sidebar-brand { padding: 0 1.5rem; margin-bottom: 2rem; display: flex; align-items: center; gap: .75rem; }
        .brand-icon { width: 48px; height: 48px; background: rgba(255,255,255,.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--white); border: 2px solid rgba(255,255,255,.2); }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 1.1rem; font-weight: 700; color: var(--white); }
        .brand-sub  { font-size: .7rem; color: rgba(255,255,255,.7); text-transform: uppercase; letter-spacing: 1px; }

        .sidebar-menu { list-style: none; }
        .sidebar-menu li { padding: 0 1rem; margin-bottom: .5rem; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: .75rem;
            padding: .875rem 1rem; color: rgba(255,255,255,.8);
            text-decoration: none; border-radius: 12px;
            transition: all .3s ease; font-size: .95rem; font-weight: 500;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,.15); color: var(--white); }
        .sidebar-menu a.active { background: linear-gradient(90deg, var(--indigo-accent), var(--indigo-light)); box-shadow: 0 4px 12px rgba(124,92,250,.3); }
        .sidebar-menu i { width: 18px; text-align: center; }

        /* ── MAIN ── */
        .main-content { flex: 1; margin-left: 260px; display: flex; flex-direction: column; }

        /* ── NAVBAR ── */
        .navbar {
            position: sticky; top: 0; z-index: 30;
            background: var(--white);
            box-shadow: 0 4px 16px rgba(30,22,103,.08);
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 2rem;
        }

        .navbar-content { display: flex; align-items: center; justify-content: space-between; }
        .nav-title { font-size: 1.3rem; font-weight: 700; color: var(--gray-800); }

        .admin-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--indigo-light), var(--indigo-accent));
            display: flex; align-items: center; justify-content: center;
            color: var(--white); font-weight: 700; font-size: .95rem; cursor: pointer;
            box-shadow: 0 2px 8px rgba(59, 46, 192, 0.2); transition: transform .2s;
        }

        .admin-avatar:hover { transform: scale(1.05); }

        .dropdown-wrap { position: relative; }
        .dropdown-menu {
            position: absolute; right: 0; top: calc(100% + 8px);
            width: 220px; background: var(--white); border-radius: 12px;
            box-shadow: var(--shadow-lg); border: 1px solid var(--gray-200);
            opacity: 0; visibility: hidden; transition: opacity .2s, visibility .2s; z-index: 100; overflow: hidden;
        }

        .dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }

        .dropdown-header {
            padding: 1rem; border-bottom: 1px solid var(--gray-200); background: var(--gray-50);
        }

        .dropdown-header-text { font-size: .85rem; color: var(--gray-500); }
        .dropdown-header-name { font-weight: 700; color: var(--gray-800); font-size: .95rem; }

        .dropdown-menu a {
            display: flex; align-items: center; gap: .75rem;
            padding: .75rem 1rem; font-size: .9rem;
            color: var(--gray-800); text-decoration: none; transition: background .15s;
        }

        .dropdown-menu a:hover { background: var(--gray-50); color: var(--indigo-light); }
        .dropdown-menu a.logout { color: var(--error); border-top: 1px solid var(--gray-200); }

        /* ── PAGE ── */
        .page-content { flex: 1; padding: 2rem; overflow-y: auto; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 1.75rem; font-weight: 700; color: var(--gray-800); }
        .page-subtitle { font-size: .95rem; color: var(--gray-500); margin-top: .25rem; }

        /* ── STATS ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem; margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--white); border-radius: var(--radius); padding: 1.5rem;
            box-shadow: var(--shadow); border: 1px solid var(--gray-200);
            display: flex; align-items: center; gap: 1.25rem;
            transition: transform .2s, box-shadow .2s;
        }

        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }

        .stat-icon {
            width: 56px; height: 56px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }

        .stat-icon.warning { background: rgba(255,165,2,.12); color: var(--warning); }
        .stat-icon.info    { background: rgba(9,132,227,.12); color: var(--info); }
        .stat-icon.accent  { background: rgba(124,92,250,.12); color: var(--indigo-accent); }
        .stat-icon.success { background: rgba(46,213,115,.12); color: var(--success); }

        .stat-label { font-size: .78rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .3rem; }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--gray-800); line-height: 1; }
        .stat-sub   { font-size: .8rem; color: var(--gray-500); margin-top: .3rem; }

        /* ── BUTTONS ── */
        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .75rem 1.25rem; border: none; border-radius: 10px;
            font-size: .95rem; font-weight: 600; cursor: pointer;
            text-decoration: none; transition: all .2s; font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--indigo-light), var(--indigo-accent));
            color: var(--white); box-shadow: 0 4px 12px rgba(59,46,192,.3);
        }

        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59,46,192,.4); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-800); }
        .btn-secondary:hover { background: var(--gray-200); }
        .btn-sm { padding: .5rem .75rem; font-size: .85rem; }

        /* ── FILTERS ── */
        .filters-section {
            background: var(--white); border-radius: var(--radius); padding: 1.5rem;
            margin-bottom: 1.5rem; box-shadow: var(--shadow); border: 1px solid var(--gray-200);
        }

        .filters-grid {
            display: grid; grid-template-columns: 1fr 1fr 1fr auto auto;
            gap: 1rem; align-items: flex-end;
        }

        .form-group { display: flex; flex-direction: column; gap: .5rem; }
        .form-label { font-size: .9rem; font-weight: 600; color: var(--gray-700); }

        .form-input, .form-select {
            padding: .75rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: inherit;
            font-size: .95rem;
            transition: border-color .2s;
            background: var(--white);
        }

        .form-input:focus, .form-select:focus { outline: none; border-color: var(--indigo-light); }

        .form-select {
            padding-right: 2.5rem;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%233b2ec0' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .9rem center;
            cursor: pointer;
        }

        /* ── ALERTS ── */
        .alert {
            padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: .75rem; border-left: 4px solid;
        }

        .alert-success { background: rgba(46,213,115,.1); border-color: var(--success); color: #1a8a4a; }
        .alert-error   { background: rgba(255,71,87,.1);  border-color: var(--error);   color: var(--error); }

        /* ── TABLE ── */
        .card {
            background: var(--white); border-radius: var(--radius); padding: 1.5rem;
            box-shadow: var(--shadow); border: 1px solid var(--gray-200);
        }

        .table-wrap { overflow-x: auto; border-radius: 12px; }
        .table { width: 100%; border-collapse: collapse; font-size: .9rem; }

        .table th {
            background: linear-gradient(90deg, var(--gray-50), var(--gray-100));
            padding: 1rem; text-align: left; font-weight: 700; color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200); text-transform: uppercase;
            font-size: .78rem; letter-spacing: .5px;
        }

        .table td { padding: 1rem; border-bottom: 1px solid var(--gray-200); vertical-align: middle; }
        .table tbody tr { transition: background .2s; }
        .table tbody tr:hover { background: rgba(59,46,192,.04); }

        .action-group { display: flex; gap: .5rem; }

        /* ── PAGINATION ── */
        .pagination {
            display: flex; align-items: center; justify-content: center;
            gap: .5rem; margin-top: 2rem;
        }

        .pagination a, .pagination span {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px;
            text-decoration: none; color: var(--gray-800);
            border: 1px solid var(--gray-200); transition: all .2s;
        }

        .pagination a:hover { background: var(--indigo-light); color: var(--white); border-color: var(--indigo-light); }
        .pagination .active { background: var(--indigo-light); color: var(--white); border-color: var(--indigo-light); }

        /* ── MODAL ── */
        .modal {
            display: none; position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,.5); z-index: 1000;
            align-items: center; justify-content: center;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: var(--white); border-radius: var(--radius);
            padding: 2rem; max-width: 500px; width: 90%;
            box-shadow: var(--shadow-lg);
            animation: slideUp .25s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .modal-header { font-size: 1.2rem; font-weight: 700; color: var(--gray-800); margin-bottom: 1.5rem; }
        .modal-body { margin-bottom: 1.5rem; }
        .modal-footer { display: flex; gap: 1rem; justify-content: flex-end; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1024px) {
            .filters-grid { grid-template-columns: 1fr 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { left: -260px; }
            .main-content { margin-left: 0; }
            .filters-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .action-group { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-book"></i></div>
        <div>
            <div class="brand-name">LiteraSpace</div>
            <div class="brand-sub">Admin</div>
        </div>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="buku.php"><i class="fas fa-book"></i> Manajemen Buku</a></li>
        <li><a href="kategori.php"><i class="fas fa-folder"></i> Kategori</a></li>
        <li><a href="pesanan.php" class="active"><i class="fas fa-shopping-bag"></i> Pesanan</a></li>
        <li><a href="user.php"><i class="fas fa-user"></i> User</a></li>
        <li><a href="review.php"><i class="fas fa-star"></i> Review</a></li>
        <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
    </ul>
</aside>

<!-- MAIN -->
<div class="main-content">

    <nav class="navbar">
        <div class="navbar-content">
            <div class="nav-title">Manajemen Pesanan</div>
            <div class="dropdown-wrap">
                <div class="admin-avatar"><?= htmlspecialchars($admin_initial) ?></div>
                <div class="dropdown-menu">
                    <div class="dropdown-header">
                        <div class="dropdown-header-text">Masuk sebagai</div>
                        <div class="dropdown-header-name"><?= htmlspecialchars($admin_initial) ?></div>
                    </div>
                    <a href="../pages/profile.php"><i class="fas fa-user"></i> Profil</a>
                    <a href="../pages/password-update.php"><i class="fas fa-lock"></i> Ubah Password</a>
                    <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="page-content">

        <!-- HEADER -->
        <div class="page-header">
            <h1 class="page-title">Manajemen Pesanan</h1>
            <p class="page-subtitle">Kelola dan pantau semua pesanan customer</p>
        </div>

        <!-- ALERTS -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-label">Diproses</div>
                    <div class="stat-value"><?= $pending ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info"><i class="fas fa-box"></i></div>
                <div>
                    <div class="stat-label">Pengiriman</div>
                    <div class="stat-value"><?= $shipping ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon accent"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-label">Selesai</div>
                    <div class="stat-value"><?= $completed ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value" style="font-size:1.5rem;"><?= formatRupiah($total_revenue) ?></div>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filters-section">
            <form method="get" action="pesanan.php">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Cari Pesanan</label>
                        <input type="text" name="search" class="form-input"
                               placeholder="ID Pesanan, Nama, Email..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="diproses" <?= $filter_status === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                            <option value="dikemas" <?= $filter_status === 'dikemas' ? 'selected' : '' ?>>Dikemas</option>
                            <option value="dikirim" <?= $filter_status === 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                            <option value="selesai" <?= $filter_status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Urutkan</label>
                        <select name="sort" class="form-select">
                            <option value="terbaru" <?= $sort_by === 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
                            <option value="terakhir" <?= $sort_by === 'terakhir' ? 'selected' : '' ?>>Terakhir</option>
                            <option value="tertinggi" <?= $sort_by === 'tertinggi' ? 'selected' : '' ?>>Tertinggi</option>
                            <option value="terendah" <?= $sort_by === 'terendah' ? 'selected' : '' ?>>Terendah</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="pesanan.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- TABLE -->
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:80px;">#</th>
                            <th>Customer</th>
                            <th>Total Harga</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $i => $order): ?>
                            <tr>
                                <td style="color:var(--gray-500); font-size:.85rem; font-weight:600;">
                                    #<?= htmlspecialchars($order['id_pesanan']) ?>
                                </td>
                                <td>
                                    <div style="font-weight:600; color:var(--gray-800);">
                                        <?= htmlspecialchars($order['nama_depan'] . ' ' . $order['nama_belakang']) ?>
                                    </div>
                                    <div style="font-size:.85rem; color:var(--gray-500);">
                                        <?= htmlspecialchars($order['email']) ?>
                                    </div>
                                </td>
                                <td style="font-weight:700; color:var(--indigo-light);">
                                    <?= formatRupiah($order['total_harga']) ?>
                                </td>
                                <td style="text-align:center; font-weight:600;">
                                    <?= $order['jumlah_item'] ?> item
                                </td>
                                <td>
                                    <?= getStatusBadge($order['status_pesanan']) ?>
                                </td>
                                <td style="font-size:.9rem; color:var(--gray-600);">
                                    <?= formatTanggal($order['tanggal_pesan']) ?>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button class="btn btn-secondary btn-sm"
                                                onclick="openModal('detailModal'); loadOrderDetail(<?= $order['id_pesanan'] ?>)">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                        <button class="btn btn-secondary btn-sm"
                                                onclick="openModal('updateModal'); setOrderId(<?= $order['id_pesanan'] ?>, '<?= htmlspecialchars($order['status_pesanan']) ?>')">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:2.5rem; color:var(--gray-500);">
                                <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:1rem;"></i>
                                Tidak ada pesanan ditemukan
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= urlencode($sort_by) ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++) {
                        $cls = $i === $page ? 'active' : '';
                        echo "<a href=\"?page=$i&search=" . urlencode($search) . "&status=" . urlencode($filter_status) . "&sort=" . urlencode($sort_by) . "\" class=\"$cls\">$i</a>";
                    }
                    ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= urlencode($sort_by) ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- MODAL: UPDATE STATUS -->
<div class="modal" id="updateModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-edit" style="color:var(--indigo-accent); margin-right:.5rem;"></i>
            Update Status Pesanan
        </div>
        <form method="POST" action="pesanan.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id_pesanan" id="updateOrderId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Status <span style="color:var(--error);">*</span></label>
                    <select name="status" id="updateStatus" class="form-select" required>
                        <option value="diproses">Diproses</option>
                        <option value="dikemas">Dikemas</option>
                        <option value="dikirim">Dikirim</option>
                        <option value="selesai">Selesai</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor Resi (opsional)</label>
                    <input type="text" name="no_resi" id="updateResi" class="form-input"
                           placeholder="Masukkan nomor resi jika status dikirim">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: DETAIL PESANAN -->
<div class="modal" id="detailModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-info-circle" style="color:var(--indigo-accent); margin-right:.5rem;"></i>
            Detail Pesanan
        </div>
        <div class="modal-body" id="detailContent">
            <p style="text-align:center; color:var(--gray-500);"><i class="fas fa-spinner fa-spin"></i> Loading...</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('detailModal')">Tutup</button>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
    });

    function setOrderId(id, status) {
        document.getElementById('updateOrderId').value = id;
        document.getElementById('updateStatus').value = status;
    }

    function loadOrderDetail(orderId) {
        fetch(`pesanan-api.php?action=detail&id=${orderId}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('detailContent').innerHTML = `
                    <div style="gap:1rem; display:flex; flex-direction:column;">
                        <div><strong>ID Pesanan:</strong> #${data.id_pesanan}</div>
                        <div><strong>Customer:</strong> ${data.nama_depan} ${data.nama_belakang}</div>
                        <div><strong>Email:</strong> ${data.email}</div>
                        <div><strong>Total Harga:</strong> Rp ${data.total_harga.toLocaleString('id-ID')}</div>
                        <div><strong>Status:</strong> ${data.status_pesanan}</div>
                        <div><strong>Tanggal Pesan:</strong> ${data.tanggal_pesan}</div>
                        <hr style="border:none; border-top:1px solid var(--gray-200); margin:0.5rem 0;">
                        <strong style="font-size:0.95rem;">Item Pesanan:</strong>
                        <div style="border:1px solid var(--gray-200); border-radius:8px; padding:1rem; background:var(--gray-50);">
                            ${data.items.map(item => `
                                <div style="display:flex; justify-content:space-between; padding:0.5rem 0; border-bottom:1px solid var(--gray-200);">
                                    <span>${item.judul} x${item.qty}</span>
                                    <span style="font-weight:600;">Rp ${item.harga_saat_beli.toLocaleString('id-ID')}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            })
            .catch(e => {
                document.getElementById('detailContent').innerHTML = '<p style="color:var(--error);">Gagal memuat detail</p>';
            });
    }
</script>

</body>
</html>
