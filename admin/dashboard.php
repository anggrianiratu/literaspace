<?php
// admin/dashboard.php - Admin Dashboard Overview
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDB();
$admin_id = (int) $_SESSION['user_id'];

// Verify user is admin
$stmt = $pdo->prepare("SELECT role, nama_depan FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Get admin name and initial
$stmt = $pdo->prepare("SELECT nama_depan, nama_belakang FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin_data = $stmt->fetch();
$admin_name = $admin_data['nama_depan'] . ' ' . $admin_data['nama_belakang'];
$admin_initial = strtoupper(substr($admin_data['nama_depan'], 0, 1));

// 1. Total Revenue / Penjualan
$stmt = $pdo->query("SELECT SUM(total_harga) as total_revenue FROM pesanan WHERE status_pesanan = 'selesai'");
$revenue = $stmt->fetch()['total_revenue'] ?? 0;
$revenue = (int) $revenue;

// 2. Total Orders / Pesanan
$stmt = $pdo->query("SELECT COUNT(*) FROM pesanan");
$total_orders = (int) $stmt->fetchColumn();

// 3. Total Users
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_users = (int) $stmt->fetchColumn();

// 4. Total Books Stock
$stmt = $pdo->query("SELECT SUM(stok) as total_stock FROM buku");
$total_stock = (int) ($stmt->fetch()['total_stock'] ?? 0);

// 5. Pending Orders (diproses, dikemas, dikirim)
$stmt = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan IN ('diproses', 'dikemas', 'dikirim')");
$pending_orders = (int) $stmt->fetchColumn();

// 6. Low Stock Books (stok <= 5)
$stmt = $pdo->query("SELECT COUNT(*) FROM buku WHERE stok <= 5");
$low_stock_books = (int) $stmt->fetchColumn();

// 7. Top 5 Best Selling Books
$stmt = $pdo->query("
    SELECT b.id_buku, b.judul, b.penulis, b.harga, b.cover_image, 
           SUM(dp.qty) as total_terjual
    FROM buku b
    LEFT JOIN detail_pesanan dp ON b.id_buku = dp.id_buku
    LEFT JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
    WHERE p.status_pesanan = 'selesai' OR p.id_pesanan IS NULL
    GROUP BY b.id_buku
    ORDER BY total_terjual DESC
    LIMIT 5
");
$top_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Recent Orders (5 pesanan terbaru)
$stmt = $pdo->query("
    SELECT p.id_pesanan, p.tanggal_pesan, p.total_harga, p.status_pesanan,
           u.nama_depan, u.nama_belakang, u.email,
           COUNT(dp.id_detail) as jumlah_item
    FROM pesanan p
    JOIN users u ON p.id_user = u.id
    LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
    GROUP BY p.id_pesanan
    ORDER BY p.tanggal_pesan DESC
    LIMIT 8
");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Monthly Revenue (untuk 6 bulan terakhir)
$stmt = $pdo->query("
    SELECT DATE_FORMAT(tanggal_pesan, '%Y-%m') as bulan, SUM(total_harga) as revenue
    FROM pesanan
    WHERE status_pesanan = 'selesai' AND tanggal_pesan >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(tanggal_pesan, '%Y-%m')
    ORDER BY bulan ASC
");
$monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format functions
function formatRupiah($n) { 
    return 'Rp ' . number_format((int)$n, 0, ',', '.'); 
}

function formatTanggal($date) {
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    [$y, $m, $d] = explode('-', substr($date, 0, 10));
    return (int)$d . ' ' . $bulan[(int)$m] . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

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

        .nav-icon {
            color: var(--gray-500);
            font-size: 1.2rem;
            text-decoration: none;
            transition: color .2s;
            cursor: pointer;
        }

        .nav-icon:hover {
            color: var(--indigo-light);
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
            box-shadow: 0 2px 8px rgba(59, 46, 192, 0.2);
            transition: transform .2s;
        }

        .admin-avatar:hover {
            transform: scale(1.05);
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
            overflow: hidden;
        }

        .dropdown-wrap:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
        }

        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .dropdown-header-text {
            font-size: .85rem;
            color: var(--gray-500);
        }

        .dropdown-header-name {
            font-weight: 700;
            color: var(--gray-800);
            font-size: .95rem;
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem 1rem;
            font-size: .9rem;
            color: var(--gray-800);
            text-decoration: none;
            transition: background .15s, color .15s;
        }

        .dropdown-menu a:hover {
            background: var(--gray-50);
            color: var(--indigo-light);
        }

        .dropdown-menu a.logout {
            color: var(--error);
            border-top: 1px solid var(--gray-200);
        }

        .dropdown-menu a.logout:hover {
            background: rgba(255, 71, 87, 0.08);
        }

        /* ── PAGE CONTENT ── */
        .page-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: .25rem;
        }

        .page-subtitle {
            font-size: .95rem;
            color: var(--gray-500);
        }

        /* ── STATS GRID ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.75rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--indigo-light), var(--indigo-accent));
            opacity: 0;
            transition: opacity .3s;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: .9rem;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.revenue {
            background: linear-gradient(135deg, rgba(59, 46, 192, 0.1), rgba(124, 92, 250, 0.1));
            color: var(--indigo-light);
        }

        .stat-icon.orders {
            background: linear-gradient(135deg, rgba(46, 213, 115, 0.1), rgba(46, 213, 115, 0.15));
            color: var(--success);
        }

        .stat-icon.users {
            background: linear-gradient(135deg, rgba(9, 132, 227, 0.1), rgba(52, 152, 219, 0.1));
            color: var(--info);
        }

        .stat-icon.stock {
            background: linear-gradient(135deg, rgba(255, 165, 2, 0.1), rgba(255, 193, 7, 0.1));
            color: var(--warning);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--indigo-deep), var(--indigo-mid));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: .5rem;
        }

        .stat-footer {
            font-size: .85rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .stat-footer.success { color: var(--success); }
        .stat-footer.warning { color: var(--warning); }

        /* ── CONTENT GRID ── */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.75rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: box-shadow .3s;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .card-title i {
            color: var(--indigo-light);
            font-size: 1.25rem;
        }

        /* ── CHART ── */
        .chart-container {
            position: relative;
            height: 320px;
            margin-bottom: 1rem;
        }

        /* ── PROGRESS BARS ── */
        .progress-item {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            margin-bottom: 1.25rem;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .progress-label {
            font-size: .95rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .progress-value {
            font-size: .9rem;
            font-weight: 700;
            color: var(--indigo-light);
        }

        .progress-bar {
            height: 8px;
            background: var(--gray-100);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--indigo-light), var(--indigo-accent));
            border-radius: 4px;
            transition: width .6s cubic-bezier(0.4, 0, 0.2, 1);
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

        .order-id {
            font-weight: 700;
            color: var(--indigo-light);
        }

        .order-customer {
            font-size: .85rem;
            color: var(--gray-500);
            margin-top: .15rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .4rem .85rem;
            border-radius: 8px;
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-diproses { background: rgba(9, 132, 227, 0.1); color: var(--info); }
        .badge-dikemas { background: rgba(255, 165, 2, 0.1); color: var(--warning); }
        .badge-dikirim { background: rgba(255, 165, 2, 0.15); color: var(--warning); }
        .badge-selesai { background: rgba(46, 213, 115, 0.1); color: var(--success); }

        /* ── TOP BOOKS ── */
        .book-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
            padding: 1rem;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            border-radius: 12px;
            transition: all .3s;
            border: 1px solid var(--gray-200);
        }

        .book-item:hover {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-50) 100%);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(30,22,103,.08);
        }

        .book-thumb {
            width: 55px;
            height: 75px;
            background: linear-gradient(135deg, var(--indigo-light), var(--indigo-accent));
            border-radius: 8px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(59, 46, 192, 0.2);
        }

        .book-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .book-info {
            flex: 1;
            min-width: 0;
        }

        .book-title {
            font-weight: 700;
            color: var(--gray-800);
            font-size: .95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .book-author {
            font-size: .85rem;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: .35rem;
        }

        .book-sold {
            font-weight: 700;
            color: var(--success);
            font-size: .85rem;
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }

            .main-content {
                margin-left: 220px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                transition: left .3s;
                width: 260px;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .page-content {
                padding: 1rem;
            }

            .navbar {
                padding: .75rem 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .table {
                font-size: .8rem;
            }

            .table th, .table td {
                padding: .6rem;
            }
        }

        @media (max-width: 480px) {
            .navbar-content {
                gap: .5rem;
            }

            .nav-right {
                gap: 1rem;
            }

            .page-content {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .card {
                padding: 1rem;
            }

            .card-title {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="buku.php"><i class="fas fa-book"></i> Manajemen Buku</a></li>
            <li><a href="#"><i class="fas fa-folder"></i> Kategori</a></li>
            <li><a href="#"><i class="fas fa-shopping-bag"></i> Pesanan</a></li>
            <li><a href="#"><i class="fas fa-users"></i> User</a></li>
            <li><a href="#"><i class="fas fa-star"></i> Review</a></li>
            <li><a href="#"><i class="fas fa-chart-bar"></i> Laporan</a></li>
            <li><a href="#"><i class="fas fa-cog"></i> Pengaturan</a></li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- NAVBAR -->
        <nav class="navbar">
            <div class="navbar-content">
                <div class="nav-title">Dashboard</div>
                <div class="nav-right">
                    <a href="#" class="nav-icon" title="Notifications">
                        <i class="fas fa-bell"></i>
                    </a>
                    <div class="dropdown-wrap">
                        <div class="admin-avatar"><?= $admin_initial ?></div>
                        <div class="dropdown-menu">
                            <div class="dropdown-header">
                                <div class="dropdown-header-text">Logged in as</div>
                                <div class="dropdown-header-name"><?= htmlspecialchars($admin_name) ?></div>
                            </div>
                            <a href="../profile.php"><i class="fas fa-user"></i> Profil</a>
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
                <h1 class="page-title">Dashboard Admin</h1>
                <p class="page-subtitle">Selamat datang kembali, <?= htmlspecialchars($admin_data['nama_depan']) ?></p>
            </div>

            <!-- STATISTICS GRID -->
            <div class="stats-grid">
                <!-- Total Revenue -->
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Total Penjualan</span>
                        <div class="stat-icon revenue">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($revenue / 1000000, 1) ?>M</div>
                    <div class="stat-footer success">
                        <i class="fas fa-arrow-up"></i> <?= formatRupiah($revenue) ?>
                    </div>
                </div>

                <!-- Total Orders -->
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Total Pesanan</span>
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $total_orders ?></div>
                    <div class="stat-footer">
                        <i class="fas fa-info-circle"></i> <?= $pending_orders ?> pending
                    </div>
                </div>

                <!-- Total Users -->
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Total User</span>
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $total_users ?></div>
                    <div class="stat-footer">
                        <i class="fas fa-info-circle"></i> Pengguna aktif
                    </div>
                </div>

                <!-- Total Stock -->
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Total Stok</span>
                        <div class="stat-icon stock">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($total_stock, 0) ?></div>
                    <div class="stat-footer warning">
                        <i class="fas fa-exclamation-circle"></i> <?= $low_stock_books ?> stok rendah
                    </div>
                </div>
            </div>

            <!-- CONTENT GRID -->
            <div class="content-grid">
                <!-- REVENUE CHART -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-chart-line"></i> Penjualan 6 Bulan Terakhir
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- PENDING ORDERS SUMMARY -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-tasks"></i> Status Pesanan
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                        <?php
                        $statuses = [
                            'diproses' => 'Diproses',
                            'dikemas' => 'Dikemas',
                            'dikirim' => 'Dikirim',
                            'selesai' => 'Selesai'
                        ];
                        foreach ($statuses as $key => $label) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = ?");
                            $stmt->execute([$key]);
                            $count = (int)$stmt->fetchColumn();
                            $percentage = $total_orders > 0 ? ($count / $total_orders * 100) : 0;
                        ?>
                            <div class="progress-item">
                                <div class="progress-header">
                                    <span class="progress-label"><?= $label ?></span>
                                    <span class="progress-value"><?= $count ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- RECENT ORDERS & TOP BOOKS -->
            <div class="content-grid">
                <!-- RECENT ORDERS TABLE -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-list"></i> Pesanan Terbaru
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order) {
                                    $status_badges = [
                                        'diproses' => 'badge-diproses',
                                        'dikemas' => 'badge-dikemas',
                                        'dikirim' => 'badge-dikirim',
                                        'selesai' => 'badge-selesai'
                                    ];
                                    $badge_class = $status_badges[$order['status_pesanan']] ?? 'badge-diproses';
                                    $status_icons = [
                                        'diproses' => 'fas fa-hourglass-start',
                                        'dikemas' => 'fas fa-cube',
                                        'dikirim' => 'fas fa-truck',
                                        'selesai' => 'fas fa-check-circle'
                                    ];
                                    $icon = $status_icons[$order['status_pesanan']] ?? 'fas fa-info-circle';
                                ?>
                                    <tr>
                                        <td class="order-id">#<?= $order['id_pesanan'] ?></td>
                                        <td>
                                            <div><?= htmlspecialchars($order['nama_depan'] . ' ' . $order['nama_belakang']) ?></div>
                                            <div class="order-customer"><?= htmlspecialchars($order['email']) ?></div>
                                        </td>
                                        <td><?= formatRupiah($order['total_harga']) ?></td>
                                        <td>
                                            <span class="status-badge <?= $badge_class ?>">
                                                <i class="<?= $icon ?>"></i> <?= ucfirst($order['status_pesanan']) ?>
                                            </span>
                                        </td>
                                        <td><?= formatTanggal($order['tanggal_pesan']) ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TOP BOOKS -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-fire"></i> Buku Terlaris
                    </div>
                    <?php if (!empty($top_books)) {
                        foreach ($top_books as $idx => $book) {
                    ?>
                        <div class="book-item">
                            <div class="book-thumb">
                                <?php if ($book['cover_image'] && file_exists('../assets/covers/' . $book['cover_image'])) { ?>
                                    <img src="../assets/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['judul']) ?>" />
                                <?php } else { ?>
                                    <i class="fas fa-book"></i>
                                <?php } ?>
                            </div>
                            <div class="book-info">
                                <div class="book-title" title="<?= htmlspecialchars($book['judul']) ?>"><?= htmlspecialchars($book['judul']) ?></div>
                                <div class="book-author"><?= htmlspecialchars($book['penulis'] ?? 'Penulis Tidak Diketahui') ?></div>
                                <div class="book-sold">
                                    <i class="fas fa-check-circle"></i> <?= ($book['total_terjual'] ?? 0) ?> terjual
                                </div>
                            </div>
                        </div>
                    <?php }
                    } else {
                        echo '<div style="text-align: center; padding: 2rem; color: var(--gray-500);"><i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>Belum ada penjualan</div>';
                    } ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CHART.JS SCRIPT -->
    <script>
        // Prepare data for revenue chart
        const monthlyData = <?= json_encode($monthly_revenue) ?>;
        const labels = monthlyData.map(item => {
            const [year, month] = item.bulan.split('-');
            const monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return monthNames[parseInt(month)] + ' ' + year.slice(-2);
        });
        const revenues = monthlyData.map(item => parseInt(item.revenue) || 0);

        // Create chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (Rp)',
                    data: revenues,
                    borderColor: '#3b2ec0',
                    backgroundColor: 'rgba(59, 46, 192, 0.08)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3b2ec0',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp' + (value / 1000000).toFixed(1) + 'M';
                            }
                        },
                        grid: {
                            color: 'rgba(30, 22, 103, 0.05)',
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
