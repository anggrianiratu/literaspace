<?php
// admin/kategori.php - Manajemen Kategori
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

// Handle ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $nama = trim($_POST['nama_kategori'] ?? '');
    if ($nama === '') {
        $error_message = "Nama kategori tidak boleh kosong.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kategori WHERE nama_kategori = ?");
            $stmt->execute([$nama]);
            if ((int)$stmt->fetchColumn() > 0) {
                $error_message = "Kategori \"$nama\" sudah ada.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
                $stmt->execute([$nama]);
                $success_message = "Kategori berhasil ditambahkan.";
            }
        } catch (Exception $e) {
            $error_message = "Gagal menambahkan: " . $e->getMessage();
        }
    }
}

// Handle EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id   = (int) $_POST['id_kategori'];
    $nama = trim($_POST['nama_kategori'] ?? '');
    if ($nama === '') {
        $error_message = "Nama kategori tidak boleh kosong.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori = ? WHERE id_kategori = ?");
            $stmt->execute([$nama, $id]);
            $success_message = "Kategori berhasil diperbarui.";
        } catch (Exception $e) {
            $error_message = "Gagal memperbarui: " . $e->getMessage();
        }
    }
}

// Handle DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) $_POST['id_kategori'];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM buku WHERE id_kategori = ?");
        $stmt->execute([$id]);
        $buku_count = (int)$stmt->fetchColumn();

        if ($buku_count > 0) {
            $error_message = "Kategori tidak bisa dihapus, masih memiliki $buku_count buku.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM kategori WHERE id_kategori = ?");
            $stmt->execute([$id]);
            $success_message = "Kategori berhasil dihapus.";
        }
    } catch (Exception $e) {
        $error_message = "Gagal menghapus: " . $e->getMessage();
    }
}

// Filters & Pagination
$search   = trim($_GET['search'] ?? '');
$sort_raw = $_GET['sort'] ?? 'nama';
$sort     = $sort_raw === 'buku' ? 'jumlah_buku' : 'k.nama_kategori';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

$where  = [];
$params = [];

if ($search) {
    $where[]  = "k.nama_kategori LIKE ?";
    $params[] = "%$search%";
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_kategori   = 0;
$total_buku_semua = 0;
$total_pages      = 1;
$categories       = [];
$top_kategori     = null;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM kategori");
    $total_kategori = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM buku");
    $total_buku_semua = (int)$stmt->fetchColumn();

    $count_sql = "SELECT COUNT(*) FROM kategori k $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_filtered = (int)$stmt->fetchColumn();
    $total_pages = max(1, ceil($total_filtered / $per_page));

    $sql = "
        SELECT k.id_kategori, k.nama_kategori, COUNT(b.id_buku) AS jumlah_buku
        FROM kategori k
        LEFT JOIN buku b ON b.id_kategori = k.id_kategori
        $where_clause
        GROUP BY k.id_kategori, k.nama_kategori
        ORDER BY $sort ASC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT k.nama_kategori, COUNT(b.id_buku) AS jml
        FROM kategori k
        LEFT JOIN buku b ON b.id_kategori = k.id_kategori
        GROUP BY k.id_kategori
        ORDER BY jml DESC LIMIT 1
    ");
    $top_kategori = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Terjadi kesalahan: " . htmlspecialchars($e->getMessage());
}

// Warna dot statis per kategori (karena kolom warna tidak ada di DB)
$dot_colors = ['#7c5cfa','#2ed573','#ff4757','#ffa502','#0984e3','#fd79a8','#00b894','#e17055','#6c5ce7','#fdcb6e'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manajemen Kategori — Admin LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --indigo-deep:   #1e1667;
            --indigo-mid:    #2d2a8f;
            --indigo-light:  #3b2ec0;
            --indigo-accent: #7c5cfa;
            --white:         #ffffff;
            --gray-50:       #f8f8fb;
            --gray-100:      #f0f0f7;
            --gray-200:      #e2e2ef;
            --gray-500:      #6b6b8a;
            --gray-600:      #4a4a68;
            --gray-700:      #3a3a52;
            --gray-800:      #1a1a2e;
            --error:         #ff4757;
            --success:       #2ed573;
            --radius:        16px;
            --shadow:        0 8px 32px rgba(30,22,103,.12);
            --shadow-lg:     0 16px 48px rgba(30,22,103,.16);
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

        .brand-icon {
            width: 48px; height: 48px; background: rgba(255,255,255,.15);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: var(--white); border: 2px solid rgba(255,255,255,.2);
        }

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

        .sidebar-menu a:hover,
        .sidebar-menu a.active { background: rgba(255,255,255,.15); color: var(--white); }

        .sidebar-menu a.active {
            background: linear-gradient(90deg, var(--indigo-accent), var(--indigo-light));
            box-shadow: 0 4px 12px rgba(124,92,250,.3);
        }

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
        }

        .dropdown-wrap { position: relative; }

        .dropdown-menu {
            position: absolute; right: 0; top: calc(100% + 8px);
            width: 220px; background: var(--white); border-radius: 12px;
            box-shadow: var(--shadow-lg); border: 1px solid var(--gray-200);
            opacity: 0; visibility: hidden; transition: opacity .2s, visibility .2s; z-index: 100;
        }

        .dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }

        .dropdown-menu a {
            display: flex; align-items: center; gap: .75rem;
            padding: .75rem 1rem; font-size: .9rem;
            color: var(--gray-800); text-decoration: none; transition: background .15s;
        }

        .dropdown-menu a:hover  { background: var(--gray-50); color: var(--indigo-light); }
        .dropdown-menu a.logout { color: var(--error); border-top: 1px solid var(--gray-200); }

        /* ── PAGE ── */
        .page-content { flex: 1; padding: 2rem; overflow-y: auto; }

        .page-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 2rem; gap: 1rem;
        }

        .page-title   { font-size: 1.75rem; font-weight: 700; color: var(--gray-800); }
        .page-subtitle { font-size: .95rem; color: var(--gray-500); margin-top: .25rem; }

        /* ── STATS ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
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

        .stat-icon.indigo  { background: rgba(59,46,192,.12); color: var(--indigo-light); }
        .stat-icon.success { background: rgba(46,213,115,.12); color: var(--success); }
        .stat-icon.accent  { background: rgba(124,92,250,.12); color: var(--indigo-accent); }

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
        .btn-danger  { background: rgba(255,71,87,.1); color: var(--error); }
        .btn-danger:hover { background: rgba(255,71,87,.2); }
        .btn-sm { padding: .5rem .75rem; font-size: .85rem; }

        /* ── FILTERS ── */
        .filters-section {
            background: var(--white); border-radius: var(--radius); padding: 1.5rem;
            margin-bottom: 1.5rem; box-shadow: var(--shadow); border: 1px solid var(--gray-200);
        }

        .filters-grid {
            display: grid; grid-template-columns: 1fr 1fr auto auto;
            gap: 1rem; align-items: flex-end;
        }

        .form-group { display: flex; flex-direction: column; gap: .5rem; }
        .form-label { font-size: .9rem; font-weight: 600; color: var(--gray-700); }

        .form-input, .form-select {
            padding: .75rem; border: 1.5px solid var(--gray-200);
            border-radius: 8px; font-family: inherit; font-size: .95rem;
            transition: border-color .2s; background: var(--white);
        }

        .form-input:focus, .form-select:focus { outline: none; border-color: var(--indigo-light); }

        /* ── ALERTS ── */
        .alert {
            padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: .75rem; border-left: 4px solid;
        }

        .alert-success { background: rgba(46,213,115,.1); border-color: var(--success); color: #1a8a4a; }
        .alert-error   { background: rgba(255,71,87,.1);  border-color: var(--error);   color: var(--error); }

        /* ── CARD / TABLE ── */
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

        .color-dot-wrap { display: flex; align-items: center; gap: .75rem; }

        .color-dot {
            width: 13px; height: 13px; border-radius: 50%;
            flex-shrink: 0; border: 2px solid rgba(0,0,0,.08);
        }

        .cat-name { font-weight: 700; color: var(--gray-800); }

        .no-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 60px; height: 28px; padding: 0 .75rem;
            border-radius: 8px; font-weight: 700; font-size: .85rem;
        }

        .buku-ada    { background: rgba(59,46,192,.1); color: var(--indigo-light); }
        .buku-kosong { background: var(--gray-100); color: var(--gray-500); }

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
            padding: 2rem; max-width: 420px; width: 90%;
            box-shadow: var(--shadow-lg);
            animation: slideUp .25s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            font-size: 1.2rem; font-weight: 700; color: var(--gray-800);
            margin-bottom: 1.5rem; display: flex; align-items: center; gap: .75rem;
        }

        .modal-body   { margin-bottom: 1.5rem; }
        .modal-footer { display: flex; gap: 1rem; justify-content: flex-end; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1024px) {
            .filters-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar { left: -260px; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .filters-grid { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .page-header  { flex-direction: column; align-items: flex-start; }
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
        <li><a href="kategori.php" class="active"><i class="fas fa-folder"></i> Kategori</a></li>
        <li><a href="#"><i class="fas fa-shopping-bag"></i> Pesanan</a></li>
        <li><a href="user.php"><i class="fas fa-user"></i> User</a></li>
        <li><a href="#"><i class="fas fa-star"></i> Review</a></li>
        <li><a href="#"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        <li><a href="#"><i class="fas fa-cog"></i> Pengaturan</a></li>
    </ul>
</aside>

<!-- MAIN -->
<div class="main-content">

    <nav class="navbar">
        <div class="navbar-content">
            <div class="nav-title">Manajemen Kategori</div>
            <div class="dropdown-wrap">
                <div class="admin-avatar"><?= htmlspecialchars($admin_initial) ?></div>
                <div class="dropdown-menu">
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
            <div>
                <h1 class="page-title">Manajemen Kategori</h1>
                <p class="page-subtitle">Kelola kategori buku di platform LiteraSpace</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('addModal')">
                <i class="fas fa-plus"></i> Tambah Kategori
            </button>
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
                <div class="stat-icon indigo"><i class="fas fa-folder"></i></div>
                <div>
                    <div class="stat-label">Total Kategori</div>
                    <div class="stat-value"><?= $total_kategori ?></div>
                    <div class="stat-sub">Semua kategori</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-book-open"></i></div>
                <div>
                    <div class="stat-label">Total Buku</div>
                    <div class="stat-value"><?= $total_buku_semua ?></div>
                    <div class="stat-sub">Di semua kategori</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon accent"><i class="fas fa-crown"></i></div>
                <div>
                    <div class="stat-label">Kategori Terbanyak</div>
                    <div class="stat-value" style="font-size:1.15rem; line-height:1.3;">
                        <?= $top_kategori ? htmlspecialchars($top_kategori['nama_kategori']) : '-' ?>
                    </div>
                    <div class="stat-sub">
                        <?= $top_kategori ? $top_kategori['jml'] . ' buku' : 'Belum ada data' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filters-section">
            <form method="get" action="kategori.php">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Cari Kategori</label>
                        <input type="text" name="search" class="form-input"
                               placeholder="Nama kategori..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Urutkan</label>
                        <select name="sort" class="form-select">
                            <option value="nama" <?= $sort_raw !== 'buku' ? 'selected' : '' ?>>Nama A–Z</option>
                            <option value="buku" <?= $sort_raw === 'buku' ? 'selected' : '' ?>>Jumlah Buku</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="kategori.php" class="btn btn-secondary">
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
                            <th style="width:50px; text-align:center;">#</th>
                            <th>Kategori</th>
                            <th>Jumlah Buku</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $i => $cat): ?>
                            <?php $dot = $dot_colors[($cat['id_kategori'] - 1) % count($dot_colors)]; ?>
                            <tr>
                                <td style="color:var(--gray-500); font-size:.85rem; text-align:center;">
                                    <?= $offset + $i + 1 ?>
                                </td>
                                <td>
                                    <div class="color-dot-wrap">
                                        <span class="color-dot" style="background:<?= $dot ?>"></span>
                                        <span class="cat-name"><?= htmlspecialchars($cat['nama_kategori']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php $jml = (int)$cat['jumlah_buku']; ?>
                                    <span class="no-badge <?= $jml > 0 ? 'buku-ada' : 'buku-kosong' ?>">
                                        <?= $jml ?> buku
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button class="btn btn-secondary btn-sm"
                                                onclick="openEditModal(<?= $cat['id_kategori'] ?>, '<?= htmlspecialchars(addslashes($cat['nama_kategori'])) ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm"
                                                onclick="openDeleteModal(<?= $cat['id_kategori'] ?>, '<?= htmlspecialchars(addslashes($cat['nama_kategori'])) ?>')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding:2.5rem; color:var(--gray-500);">
                                <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:1rem;"></i>
                                Tidak ada kategori ditemukan
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
                        <a href="?page=1&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort_raw) ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++) {
                        $cls = $i === $page ? 'active' : '';
                        echo "<a href=\"?page=$i&search=" . urlencode($search) . "&sort=" . urlencode($sort_raw) . "\" class=\"$cls\">$i</a>";
                    }
                    ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort_raw) ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- MODAL: TAMBAH -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-plus-circle" style="color:var(--indigo-accent);"></i>
            Tambah Kategori
        </div>
        <form method="POST" action="kategori.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Kategori <span style="color:var(--error)">*</span></label>
                    <input type="text" name="nama_kategori" class="form-input"
                           placeholder="cth: Fiksi, Sains, Biografi..." required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-edit" style="color:var(--indigo-accent);"></i>
            Edit Kategori
        </div>
        <form method="POST" action="kategori.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_kategori" id="editId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Kategori <span style="color:var(--error)">*</span></label>
                    <input type="text" name="nama_kategori" id="editNama" class="form-input" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: HAPUS -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle" style="color:var(--error);"></i>
            Hapus Kategori
        </div>
        <div class="modal-body">
            <p>Apakah Anda yakin ingin menghapus kategori "<strong id="deleteKatName"></strong>"?</p>
            <p style="font-size:.88rem; color:var(--gray-500); margin-top:.75rem;">
                Kategori yang masih memiliki buku tidak dapat dihapus. Aksi ini tidak dapat dibatalkan.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Batal</button>
            <button class="btn btn-danger" onclick="confirmDelete()">
                <i class="fas fa-trash"></i> Hapus
            </button>
        </div>
    </div>
</div>

<script>
    function openModal(id)  { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
    });

    function openEditModal(id, nama) {
        document.getElementById('editId').value   = id;
        document.getElementById('editNama').value = nama;
        openModal('editModal');
    }

    let deleteId = null;

    function openDeleteModal(id, nama) {
        deleteId = id;
        document.getElementById('deleteKatName').textContent = nama;
        openModal('deleteModal');
    }

    function confirmDelete() {
        if (!deleteId) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML =
            '<input type="hidden" name="action" value="delete">' +
            '<input type="hidden" name="id_kategori" value="' + deleteId + '">';
        document.body.appendChild(form);
        form.submit();
    }
</script>
</body>
</html>