<?php
// admin/laporan.php - Laporan Penjualan & Statistik
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

// Get period from request
$period = in_array($_GET['period'] ?? '', ['7hari', '30hari', '90hari', 'semua']) ? $_GET['period'] : '30hari';

switch ($period) {
    case '7hari':
        $days = 7;
        break;
    case '30hari':
        $days = 30;
        break;
    case '90hari':
        $days = 90;
        break;
    default:
        $days = 365 * 10;
}

// Stats calculations
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_harga) as total_revenue,
        AVG(total_harga) as avg_order,
        COUNT(CASE WHEN status_pesanan = 'selesai' THEN 1 END) as completed_orders
    FROM pesanan 
    WHERE tanggal_pesan >= DATE_SUB(NOW(), INTERVAL ? DAY)
");
$stmt->execute([$days]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_customers = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM buku");
$total_books = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM kategori");
$total_categories = (int)$stmt->fetchColumn();

// Monthly revenue data for chart
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(tanggal_pesan, '%b %Y') as bulan, SUM(total_harga) as revenue
    FROM pesanan
    WHERE status_pesanan = 'selesai' AND tanggal_pesan >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY DATE_FORMAT(tanggal_pesan, '%Y-%m')
    ORDER BY DATE_FORMAT(tanggal_pesan, '%Y-%m') ASC
");
$stmt->execute([$days]);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 books
$stmt = $pdo->prepare("
    SELECT b.id_buku, b.judul, b.harga, COALESCE(SUM(dp.qty), 0) as total_terjual, COALESCE(SUM(dp.qty * dp.harga_saat_beli), 0) as total_revenue
    FROM buku b
    LEFT JOIN detail_pesanan dp ON b.id_buku = dp.id_buku
    LEFT JOIN pesanan p ON dp.id_pesanan = p.id_pesanan AND p.status_pesanan = 'selesai'
    GROUP BY b.id_buku, b.judul, b.harga
    ORDER BY total_terjual DESC
    LIMIT 5
");
$stmt->execute();
$top_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sales status breakdown
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(status_pesanan, 'Unknown') as status_pesanan,
        COUNT(*) as total,
        COALESCE(SUM(total_harga), 0) as revenue
    FROM pesanan
    GROUP BY status_pesanan
");
$stmt->execute();
$status_breakdown = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status_breakdown[$row['status_pesanan']] = $row;
}

// Helper functions
function formatRupiah($n) {
    return 'Rp ' . number_format((int)$n, 0, ',', '.');
}

function formatTanggal($date) {
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    [$y, $m, $d] = explode('-', substr($date, 0, 10));
    return (int)$d . ' ' . $bulan[(int)$m] . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Laporan Penjualan — LiteraSpace</title>
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
        .page-header { margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
        .page-title { font-size: 1.75rem; font-weight: 700; color: var(--gray-800); }
        .page-subtitle { font-size: .95rem; color: var(--gray-500); margin-top: .25rem; }

        /* ── PERIOD SELECTOR ── */
        .period-selector { display: flex; gap: 0.5rem; }
        .period-btn {
            padding: 0.6rem 1.2rem; border: 2px solid var(--gray-200);
            border-radius: 8px; background: var(--white);
            color: var(--gray-600); font-size: 0.9rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
            text-decoration: none;
        }
        .period-btn:hover, .period-btn.active {
            border-color: var(--indigo-light); color: var(--indigo-light);
            background: rgba(59, 46, 192, 0.05);
        }

        /* ── STATS ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem; margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white); border-radius: var(--radius); padding: 1.5rem;
            box-shadow: var(--shadow); border: 1px solid var(--gray-200);
            display: flex; flex-direction: column; gap: 0.75rem;
            transition: transform .2s, box-shadow .2s;
        }

        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }

        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }

        .stat-icon.indigo  { background: rgba(59,46,192,.12); color: var(--indigo-light); }
        .stat-icon.success { background: rgba(46,213,115,.12); color: var(--success); }
        .stat-icon.warning { background: rgba(255,165,2,.12); color: var(--warning); }
        .stat-icon.info    { background: rgba(9,132,227,.12); color: var(--info); }

        .stat-label { font-size: .78rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .5px; }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--gray-800); }
        .stat-sub   { font-size: .85rem; color: var(--gray-500); }

        /* ── CARDS ── */
        .card {
            background: var(--white); border-radius: var(--radius); padding: 1.5rem;
            box-shadow: var(--shadow); border: 1px solid var(--gray-200); margin-bottom: 1.5rem;
        }

        .card-title { font-size: 1.1rem; font-weight: 700; color: var(--gray-800); margin-bottom: 1.5rem; }

        /* ── CHARTS ── */
        .chart-container { position: relative; height: 300px; margin-bottom: 1rem; }

        /* ── TABLES ── */
        .table-wrap { overflow-x: auto; }
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

        .badge {
            display: inline-block; padding: 0.4rem 0.75rem; border-radius: 6px;
            font-size: 0.8rem; font-weight: 600;
        }

        .badge-success { background: rgba(46,213,115,.1); color: var(--success); }
        .badge-warning { background: rgba(255,165,2,.1); color: var(--warning); }

        /* ── GRID ── */
        .cards-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .cards-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar { left: -260px; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; }
            .period-selector { flex-wrap: wrap; }
            .page-content { padding: 1rem; }
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
        <li><a href="pesanan.php"><i class="fas fa-shopping-bag"></i> Pesanan</a></li>
        <li><a href="user.php"><i class="fas fa-user"></i> User</a></li>
        <li><a href="review.php"><i class="fas fa-star"></i> Review</a></li>
        <li><a href="laporan.php" class="active"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
    </ul>
</aside>

<!-- MAIN -->
<div class="main-content">

    <nav class="navbar">
        <div class="navbar-content">
            <div class="nav-title">Laporan Penjualan</div>
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
            <div>
                <h1 class="page-title">Laporan Penjualan</h1>
                <p class="page-subtitle">Analisis performa penjualan dan statistik bisnis</p>
            </div>
            <div class="period-selector">
                <a href="?period=7hari" class="period-btn <?= $period === '7hari' ? 'active' : '' ?>">7 Hari</a>
                <a href="?period=30hari" class="period-btn <?= $period === '30hari' ? 'active' : '' ?>">30 Hari</a>
                <a href="?period=90hari" class="period-btn <?= $period === '90hari' ? 'active' : '' ?>">90 Hari</a>
                <a href="?period=semua" class="period-btn <?= $period === 'semua' ? 'active' : '' ?>">Semua</a>
            </div>
        </div>

        <!-- MAIN STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <div class="stat-icon indigo"><i class="fas fa-shopping-bag"></i></div>
                    <div style="flex:1;">
                        <div class="stat-label">Total Pesanan</div>
                        <div class="stat-value"><?= $stats['total_orders'] ?? 0 ?></div>
                        <div class="stat-sub"><?= $stats['completed_orders'] ?? 0 ?> selesai</div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <div class="stat-icon success"><i class="fas fa-money-bill-wave"></i></div>
                    <div style="flex:1;">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value" style="font-size:1.3rem;"><?= formatRupiah($stats['total_revenue'] ?? 0) ?></div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <div class="stat-icon warning"><i class="fas fa-chart-line"></i></div>
                    <div style="flex:1;">
                        <div class="stat-label">Rata-rata Pesanan</div>
                        <div class="stat-value" style="font-size:1.3rem;"><?= formatRupiah($stats['avg_order'] ?? 0) ?></div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <div class="stat-icon info"><i class="fas fa-database"></i></div>
                    <div style="flex:1;">
                        <div class="stat-label">Total Produk</div>
                        <div class="stat-value"><?= $total_books ?></div>
                        <div class="stat-sub"><?= $total_categories ?> kategori</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="cards-grid">

            <!-- REVENUE CHART -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-chart-line"></i> Revenue Bulanan
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- STATUS BREAKDOWN -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-pie-chart"></i> Status Pesanan
                </div>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

        </div>

        <!-- TOP PRODUCTS -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-crown"></i> Top 5 Produk Terlaris
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th style="width:100px;">Terjual</th>
                            <th style="width:150px;">Revenue</th>
                            <th style="width:150px;">Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($top_books)): ?>
                        <?php foreach ($top_books as $book): ?>
                            <tr>
                                <td style="font-weight:600;"><?= htmlspecialchars($book['judul']) ?></td>
                                <td>
                                    <span class="badge badge-success"><?= $book['total_terjual'] ?? 0 ?> unit</span>
                                </td>
                                <td><?= formatRupiah($book['total_revenue'] ?? 0) ?></td>
                                <td><?= formatRupiah($book['harga'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:var(--gray-500);">Tidak ada data</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    // Revenue Chart
    const monthlyData = <?= json_encode($monthly_data) ?>;
    const monthLabels = monthlyData.map(d => d.bulan);
    const monthRevenue = monthlyData.map(d => parseInt(d.revenue));

    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Revenue',
                data: monthRevenue,
                borderColor: '#3b2ec0',
                backgroundColor: 'rgba(59, 46, 192, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#3b2ec0',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'Rp' + (v/1000000).toFixed(0) + 'M' }
                }
            }
        }
    });

    // Status Chart
    const statusBreakdown = <?= json_encode($status_breakdown) ?>;
    const statusLabels = Object.keys(statusBreakdown).map(s => ({
        'diproses': 'Diproses',
        'dikemas': 'Dikemas',
        'dikirim': 'Dikirim',
        'selesai': 'Selesai'
    }[s]));
    const statusCounts = Object.values(statusBreakdown).map(d => d.total);
    const statusColors = ['#ff8c00', '#0984e3', '#7c5cfa', '#2ed573'];

    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: statusColors,
                borderColor: '#ffffff',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 15 } }
            }
        }
    });
</script>

</body>
</html>
