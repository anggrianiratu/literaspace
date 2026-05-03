<?php
// admin/buku.php - Manajemen Buku
// Suppress error display to prevent exposing file paths
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

// Initialize messages
$success_message = null;
$error_message = null;

// Verify user is admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Get admin data
$stmt = $pdo->prepare("SELECT nama_depan FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin_data = $stmt->fetch();
$admin_initial = strtoupper(substr($admin_data['nama_depan'], 0, 1));

// Handle DELETE action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id_buku = (int) $_POST['id_buku'];
    try {
        $stmt = $pdo->prepare("DELETE FROM buku WHERE id_buku = ?");
        $stmt->execute([$id_buku]);
        $success_message = "Buku berhasil dihapus";
    } catch (Exception $e) {
        $error_message = "Gagal menghapus buku: " . $e->getMessage();
    }
}

// Get pagination and search parameters
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$kategori_filter = (int) ($_GET['kategori'] ?? 0);

// Validate sort and order BEFORE using in query
$valid_sorts = ['id_buku', 'judul', 'harga', 'stok', 'penulis'];
$sort = $_GET['sort'] ?? 'id_buku';
if (!in_array($sort, $valid_sorts)) $sort = 'id_buku';

$order = $_GET['order'] === 'asc' ? 'asc' : 'desc';

$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(judul LIKE ? OR penulis LIKE ? OR isbn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($kategori_filter > 0) {
    $where[] = "id_kategori = ?";
    $params[] = $kategori_filter;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total books, categories, and books data with error handling
$total_books = 0;
$total_pages = 1;
$books = [];
$categories = [];

try {
    // Get total books
    $count_sql = "SELECT COUNT(*) FROM buku $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_books = (int) $stmt->fetchColumn();
    $total_pages = ceil($total_books / $per_page);

    // Get books
    $sql = "
        SELECT b.*, k.nama_kategori 
        FROM buku b
        LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
        $where_clause
        ORDER BY b.$sort $order
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for filter
    $stmt = $pdo->query("SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Terjadi kesalahan saat mengambil data: " . htmlspecialchars($e->getMessage());
}

// Format functions
function formatRupiah($n) {
    return 'Rp ' . number_format((int)$n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manajemen Buku — Admin LiteraSpace</title>
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
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: linear-gradient(180deg, var(--indigo-deep) 0%, var(--indigo-mid) 100%);
            box-shadow: 4px 0 24px rgba(30,22,103,.15);
            z-index: 40;
            overflow-y: auto;
            padding-top: 1.5rem;
        }

        .sidebar-brand {
            padding: 0 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--white);
        }

        .brand-sub {
            font-size: .7rem;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin: 0;
            padding: 0 1rem;
            margin-bottom: .5rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .875rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all .3s ease;
            font-size: .95rem;
            font-weight: 500;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--white);
        }

        .sidebar-menu a.active {
            background: linear-gradient(90deg, var(--indigo-accent), var(--indigo-light));
            box-shadow: 0 4px 12px rgba(124, 92, 250, 0.3);
        }

        .sidebar-menu i {
            width: 18px;
            text-align: center;
            font-size: 1rem;
        }

        /* ── MAIN CONTENT ── */
        .main-content {
            flex: 1;
            margin-left: 260px;
            display: flex;
            flex-direction: column;
        }

        /* ── NAVBAR ── */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 30;
            background: var(--white);
            box-shadow: 0 4px 16px rgba(30,22,103,.08);
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 2rem;
        }

        .navbar-content {
            max-width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .nav-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-800);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--indigo-light), var(--indigo-accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 700;
            font-size: .95rem;
            cursor: pointer;
        }

        .dropdown-wrap {
            position: relative;
        }

        .dropdown-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            width: 220px;
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
            opacity: 0;
            visibility: hidden;
            transition: opacity .2s, visibility .2s;
            z-index: 100;
        }

        .dropdown-wrap:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem 1rem;
            font-size: .9rem;
            color: var(--gray-800);
            text-decoration: none;
            transition: background .15s;
        }

        .dropdown-menu a:hover {
            background: var(--gray-50);
            color: var(--indigo-light);
        }

        .dropdown-menu a.logout {
            color: var(--error);
            border-top: 1px solid var(--gray-200);
        }

        /* ── PAGE CONTENT ── */
        .page-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-800);
        }

        .page-subtitle {
            font-size: .95rem;
            color: var(--gray-500);
            margin-top: .25rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .75rem 1.25rem;
            border: none;
            border-radius: 10px;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--indigo-light), var(--indigo-accent));
            color: var(--white);
            box-shadow: 0 4px 12px rgba(59, 46, 192, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 46, 192, 0.4);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-danger {
            background: rgba(255, 71, 87, 0.1);
            color: var(--error);
        }

        .btn-danger:hover {
            background: rgba(255, 71, 87, 0.2);
        }

        .btn-sm {
            padding: .5rem .75rem;
            font-size: .85rem;
        }

        /* ── FILTERS ── */
        .filters-section {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto auto;
            gap: 1rem;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .form-label {
            font-size: .9rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-input,
        .form-select {
            padding: .75rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: inherit;
            font-size: .95rem;
            transition: border-color .2s;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--indigo-light);
            background: rgba(59, 46, 192, 0.02);
        }

        /* ── ALERTS ── */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(46, 213, 115, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(255, 71, 87, 0.1);
            border-color: var(--error);
            color: var(--error);
        }

        /* ── CARD ── */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        /* ── TABLE ── */
        .table-wrap {
            overflow-x: auto;
            border-radius: 12px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: .9rem;
        }

        .table th {
            background: linear-gradient(90deg, var(--gray-50), var(--gray-100));
            padding: 1rem;
            text-align: left;
            font-weight: 700;
            color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200);
            text-transform: uppercase;
            font-size: .8rem;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .table tbody tr {
            transition: background .2s;
        }

        .table tbody tr:hover {
            background: rgba(59, 46, 192, 0.04);
        }

        .table td:first-child {
            text-align: center;
            width: 80px;
        }

        .table img {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform .2s;
        }

        .table tbody tr:hover img {
            transform: scale(1.05);
        }

        .book-title {
            font-weight: 700;
            color: var(--indigo-light);
        }

        .book-author {
            font-size: .85rem;
            color: var(--gray-500);
            margin-top: .25rem;
        }

        .stock-badge {
            display: inline-block;
            padding: .4rem .85rem;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 600;
        }

        .stock-available {
            background: rgba(46, 213, 115, 0.1);
            color: var(--success);
        }

        .stock-warning {
            background: rgba(255, 165, 2, 0.1);
            color: var(--warning);
        }

        .stock-danger {
            background: rgba(255, 71, 87, 0.1);
            color: var(--error);
        }

        .action-group {
            display: flex;
            gap: .5rem;
        }

        /* ── PAGINATION ── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--gray-800);
            border: 1px solid var(--gray-200);
            transition: all .2s;
        }

        .pagination a:hover {
            background: var(--indigo-light);
            color: var(--white);
            border-color: var(--indigo-light);
        }

        .pagination .active {
            background: var(--indigo-light);
            color: var(--white);
            border-color: var(--indigo-light);
        }

        /* ── MODAL ── */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 1200px) {
            .filters-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                width: 260px;
            }

            .main-content {
                margin-left: 0;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .page-content {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .table {
                font-size: .8rem;
            }

            .table th, .table td {
                padding: .6rem;
            }

            .action-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="brand-text">
                <div class="brand-name">LiteraSpace</div>
                <div class="brand-sub">Admin</div>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="buku.php" class="active"><i class="fas fa-book"></i> Manajemen Buku</a></li>
            <li><a href="kategori.php"><i class="fas fa-folder"></i> Kategori</a></li>
            <li><a href="pesanan.php"><i class="fas fa-shopping-bag"></i> Pesanan</a></li>
            <li><a href="user.php"><i class="fas fa-user"></i> User</a></li>
            <li><a href="review.php"><i class="fas fa-star"></i> Review</a></li>
            <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- NAVBAR -->
        <nav class="navbar">
            <div class="navbar-content">
                <div class="nav-title">Manajemen Buku</div>
                <div class="nav-right">
                    <div class="dropdown-wrap">
                        <div class="admin-avatar"><?= $admin_initial ?></div>
                        <div class="dropdown-menu">
                            <a href="../pages/profile.php"><i class="fas fa-user"></i> Profil</a>
                            <a href="../pages/password-update.php"><i class="fas fa-lock"></i> Ubah Password</a>
                            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- PAGE CONTENT -->
        <div class="page-content">
            <!-- PAGE HEADER -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Manajemen Buku</h1>
                    <p class="page-subtitle">Total: <?= $total_books ?> buku</p>
                </div>
                <a href="buku-form.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Buku
                </a>
            </div>

            <!-- ALERTS -->
            <?php if (isset($success_message)) { ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php } ?>
            <?php if (isset($error_message)) { ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php } ?>

            <!-- FILTERS -->
            <div class="filters-section">
                <form method="get" action="buku.php">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label class="form-label">Cari Buku</label>
                            <input type="text" name="search" class="form-input" placeholder="Judul, penulis, ISBN..." value="<?= htmlspecialchars($search) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" class="form-select">
                                <option value="0">Semua Kategori</option>
                                <?php foreach ($categories as $cat) { ?>
                                    <option value="<?= $cat['id_kategori'] ?>" <?= $kategori_filter === $cat['id_kategori'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nama_kategori']) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Urutkan</label>
                            <select name="sort" class="form-select">
                                <option value="id_buku" <?= $sort === 'id_buku' ? 'selected' : '' ?>>Terbaru</option>
                                <option value="judul" <?= $sort === 'judul' ? 'selected' : '' ?>>Judul</option>
                                <option value="harga" <?= $sort === 'harga' ? 'selected' : '' ?>>Harga</option>
                                <option value="stok" <?= $sort === 'stok' ? 'selected' : '' ?>>Stok</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Cari
                        </button>

                        <a href="buku.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- BOOKS TABLE -->
            <div class="card">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>ID</th>
                                <th>Judul & Penulis</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($books)) {
                                foreach ($books as $book) {
                                    $stock_class = $book['stok'] > 10 ? 'stock-available' : ($book['stok'] > 5 ? 'stock-warning' : 'stock-danger');
                                    $stock_label = $book['stok'] > 10 ? 'Tersedia' : ($book['stok'] > 0 ? 'Terbatas' : 'Habis');
                                    $cover_url = $book['cover_image'] && $book['cover_image'] !== 'default.jpg' ? '../assets/covers/' . htmlspecialchars($book['cover_image']) : 'https://via.placeholder.com/60x90?text=No+Cover';
                            ?>
                                <tr>
                                    <td style="text-align: center;">
                                        <img src="<?= $cover_url ?>" alt="Cover" style="width: 50px; height: 75px; object-fit: cover; border-radius: 6px;">
                                    </td>
                                    <td style="font-weight: 700; color: var(--indigo-light);">#<?= $book['id_buku'] ?></td>
                                    <td>
                                        <div class="book-title"><?= htmlspecialchars($book['judul']) ?></div>
                                        <div class="book-author"><?= htmlspecialchars($book['penulis'] ?? 'Penulis Tidak Diketahui') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($book['nama_kategori'] ?? 'Tanpa Kategori') ?></td>
                                    <td style="font-weight: 600; color: var(--indigo-light);"><?= number_format($book['harga'], 0) ?></td>
                                    <td>
                                        <span class="stock-badge <?= $stock_class ?>">
                                            <?= $book['stok'] ?> <?= $stock_label ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <a href="buku-form.php?edit=<?= $book['id_buku'] ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button class="btn btn-danger btn-sm" onclick="openDeleteModal(<?= $book['id_buku'] ?>, '<?= htmlspecialchars($book['judul']) ?>')">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php }
                            } else {
                                echo '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--gray-500);"><i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 1rem;"></i>Tidak ada buku ditemukan</td></tr>';
                            } ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ($total_pages > 1) { ?>
                    <div class="pagination">
                        <?php if ($page > 1) { ?>
                            <a href="?page=1&search=<?= urlencode($search) ?>&kategori=<?= $kategori_filter ?>&sort=<?= $sort ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php } ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        for ($i = $start; $i <= $end; $i++) {
                            $active_class = $i === $page ? 'active' : '';
                            echo "<a href=\"?page=$i&search=" . urlencode($search) . "&kategori=$kategori_filter&sort=$sort\" class=\"$active_class\">$i</a>";
                        }
                        ?>

                        <?php if ($page < $total_pages) { ?>
                            <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&kategori=<?= $kategori_filter ?>&sort=<?= $sort ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- DELETE MODAL -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle" style="color: var(--error);"></i>
                Hapus Buku
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus buku "<strong id="deleteBookTitle"></strong>"?</p>
                <p style="font-size: .9rem; color: var(--gray-500); margin-top: 1rem;">Aksi ini tidak dapat dibatalkan.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                <button class="btn btn-danger" onclick="confirmDelete()">Hapus</button>
            </div>
        </div>
    </div>

    <script>
        let deleteBookId = null;

        function openDeleteModal(id, title) {
            deleteBookId = id;
            document.getElementById('deleteBookTitle').textContent = title;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            deleteBookId = null;
        }

        function confirmDelete() {
            if (deleteBookId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id_buku" value="' + deleteBookId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
    </script>
</body>
</html>
