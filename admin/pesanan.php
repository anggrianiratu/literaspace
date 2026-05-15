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

// Get admin data
$stmt = $pdo->prepare("SELECT nama_depan, nama_belakang FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin_data = $stmt->fetch();
$admin_initial  = strtoupper(substr($admin_data['nama_depan'], 0, 1));
$admin_fullname = htmlspecialchars($admin_data['nama_depan'] . ' ' . $admin_data['nama_belakang']);

// Handle POST - Update Status Pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {

    $order_id   = isset($_POST['id_pesanan']) ? (int) $_POST['id_pesanan'] : 0;
    $new_status = $_POST['status'] ?? null;

    $allowed_status = ['dikemas', 'dikirim', 'selesai', 'dibatalkan'];

    // VALIDASI ID
    if ($order_id <= 0) {
        $error_message = "ID pesanan tidak valid.";
        return;
    }

    // VALIDASI STATUS
    if (!in_array($new_status, $allowed_status, true)) {
        $error_message = "Status tidak valid.";
        return;
    }

    $no_resi = trim($_POST['no_resi'] ?? '');

    try {

        if ($no_resi !== '' && $new_status === 'dikirim') {
            $stmt = $pdo->prepare("
                UPDATE pesanan 
                SET status_pesanan = ?, no_resi = ? 
                WHERE id_pesanan = ?
            ");
            $stmt->execute([$new_status, $no_resi, $order_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE pesanan 
                SET status_pesanan = ? 
                WHERE id_pesanan = ?
            ");
            $stmt->execute([$new_status, $order_id]);
        }

        // ✅ IMPORTANT: CEGAH RESUBMIT SAAT REFRESH
        header("Location: pesanan.php?success=1");
        exit;

    } catch (Exception $e) {
        $error_message = "Gagal memperbarui status pesanan: " . $e->getMessage();
    }
}

// Filters & Pagination
$page          = max(1, (int)($_GET['page'] ?? 1));
$search        = trim($_GET['search'] ?? '');

// ✅ FIX: filter_status hanya nilai yang ada di ENUM (hapus 'diproses')
$filter_status = in_array($_GET['status'] ?? '', ['dikemas', 'dikirim', 'selesai', 'dibatalkan'])
    ? $_GET['status'] : '';
$sort_by = in_array($_GET['sort'] ?? '', ['terbaru', 'terakhir', 'tertinggi', 'terendah'])
    ? $_GET['sort'] : 'terbaru';

$per_page = 10;
$offset   = ($page - 1) * $per_page;

$where  = [];
$params = [];

if ($search) {
    $where[]  = "(u.nama_depan LIKE ? OR u.nama_belakang LIKE ? OR u.email LIKE ? OR p.id_pesanan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_status) {
    $where[]  = "p.status_pesanan = ?";
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
$total_pages  = max(1, ceil($total_orders / $per_page));

$sql = "
    SELECT p.id_pesanan, p.tanggal_pesan, p.total_harga, p.status_pesanan,
           p.metode_pembayaran, p.no_resi, p.kurir,
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

// ✅ FIX: Stats hanya pakai status yang ADA di ENUM
// 'dikemas' = menunggu dikemas oleh admin
$stmt    = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'dikemas'");
$pending = (int)$stmt->fetchColumn();

// 'dikirim' = sedang dalam pengiriman
$stmt     = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'dikirim'");
$shipping = (int)$stmt->fetchColumn();

$stmt      = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'selesai'");
$completed = (int)$stmt->fetchColumn();

$stmt      = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'dibatalkan'");
$cancelled = (int)$stmt->fetchColumn();

$stmt          = $pdo->query("SELECT SUM(total_harga) FROM pesanan WHERE status_pesanan = 'selesai'");
$total_revenue = (int)($stmt->fetchColumn() ?? 0);

// Total semua pesanan
$stmt        = $pdo->query("SELECT COUNT(*) FROM pesanan");
$total_semua = (int)$stmt->fetchColumn();

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
    // ✅ FIX: hanya status yang ada di ENUM
    $colors = [
        'dikemas'    => ['bg' => 'rgba(9,132,227,.12)',  'color' => '#0984e3', 'label' => 'Dikemas',    'icon' => 'fa-box'],
        'dikirim'    => ['bg' => 'rgba(124,92,250,.12)', 'color' => '#7c5cfa', 'label' => 'Dikirim',    'icon' => 'fa-truck'],
        'selesai'    => ['bg' => 'rgba(46,213,115,.12)', 'color' => '#2ed573', 'label' => 'Selesai',    'icon' => 'fa-check-circle'],
        'dibatalkan' => ['bg' => 'rgba(255,71,87,.12)',  'color' => '#ff4757', 'label' => 'Dibatalkan', 'icon' => 'fa-times-circle'],
    ];
    $s = $colors[$status] ?? ['bg' => 'rgba(100,100,100,.1)', 'color' => '#666', 'label' => ucfirst($status), 'icon' => 'fa-circle'];
    return '<span style="background:' . $s['bg'] . '; color:' . $s['color'] . '; padding:0.35rem 0.75rem; border-radius:8px; font-size:0.82rem; font-weight:600; display:inline-flex; align-items:center; gap:.4rem;">
        <i class="fas ' . $s['icon'] . '" style="font-size:.7rem;"></i>' . $s['label'] . '</span>';
}

// ✅ FIX: Admin hanya bisa update jika pesanan belum selesai/dibatalkan
function canAdminUpdate($status) {
    return !in_array($status, ['dibatalkan', 'selesai']);
}

function canAdminCancel($status) {
    return !in_array($status, ['dibatalkan', 'selesai']);
}

// ✅ FIX: Opsi next status sesuai alur ENUM yang benar
function getNextStatusOptions($currentStatus) {
    $alur = [
        'dikemas' => ['dikirim' => 'Dikirim'],
        'dikirim' => ['selesai' => 'Selesai'],
    ];
    // Jika status tidak dikenal, tampilkan semua opsi
    return $alur[$currentStatus] ?? ['dikemas' => 'Dikemas', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manajemen Pesanan — LiteraSpace Admin</title>
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
            --gray-300:      #d5d5e8;
            --gray-500:      #6b6b8a;
            --gray-600:      #4a4a68;
            --gray-700:      #3a3a52;
            --gray-800:      #1a1a2e;
            --error:         #ff4757;
            --success:       #2ed573;
            --warning:       #ffa502;
            --info:          #0984e3;
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
            box-shadow: 0 2px 8px rgba(59,46,192,.2); transition: transform .2s;
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
        .dropdown-header { padding: 1rem; border-bottom: 1px solid var(--gray-200); background: var(--gray-50); }
        .dropdown-header-text { font-size: .8rem; color: var(--gray-500); }
        .dropdown-header-name { font-weight: 700; color: var(--gray-800); font-size: .95rem; }
        .dropdown-menu a { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; font-size: .9rem; color: var(--gray-800); text-decoration: none; transition: background .15s; }
        .dropdown-menu a:hover { background: var(--gray-50); color: var(--indigo-light); }
        .dropdown-menu a.logout { color: var(--error); border-top: 1px solid var(--gray-200); }
        .dropdown-menu a.logout:hover { background: rgba(255,71,87,.05); }

        /* ── PAGE ── */
        .page-content { flex: 1; padding: 2rem; overflow-y: auto; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 1.75rem; font-weight: 700; color: var(--gray-800); }
        .page-subtitle { font-size: .95rem; color: var(--gray-500); margin-top: .25rem; }

        /* ── STATS ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(5, 1fr);
            gap: 1.25rem; margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--white); border-radius: var(--radius); padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow); border: 1px solid var(--gray-200);
            display: flex; align-items: center; gap: 1rem;
            transition: transform .2s, box-shadow .2s; cursor: default;
            text-decoration: none;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .stat-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .stat-icon.blue    { background: rgba(9,132,227,.12);  color: var(--info); }
        .stat-icon.purple  { background: rgba(124,92,250,.12); color: var(--indigo-accent); }
        .stat-icon.success { background: rgba(46,213,115,.12); color: var(--success); }
        .stat-icon.error   { background: rgba(255,71,87,.12);  color: var(--error); }
        .stat-icon.gold    { background: rgba(255,165,2,.12);  color: var(--warning); }
        .stat-label { font-size: .75rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .3rem; }
        .stat-value { font-size: 1.9rem; font-weight: 700; color: var(--gray-800); line-height: 1; }
        .stat-value.small { font-size: 1.2rem; }

        /* ── BUTTONS ── */
        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .75rem 1.25rem; border: none; border-radius: 10px;
            font-size: .95rem; font-weight: 600; cursor: pointer;
            text-decoration: none; transition: all .2s; font-family: inherit;
        }
        .btn-primary { background: linear-gradient(135deg, var(--indigo-light), var(--indigo-accent)); color: var(--white); box-shadow: 0 4px 12px rgba(59,46,192,.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59,46,192,.4); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-800); border: 1px solid var(--gray-200); }
        .btn-secondary:hover { background: var(--gray-200); }
        .btn-danger { background: rgba(255,71,87,.1); color: var(--error); border: 1.5px solid rgba(255,71,87,.25); }
        .btn-danger:hover { background: var(--error); color: var(--white); }
        .btn-danger-solid { background: var(--error); color: var(--white); box-shadow: 0 4px 12px rgba(255,71,87,.3); }
        .btn-danger-solid:hover { filter: brightness(.9); transform: translateY(-1px); }
        .btn-sm { padding: .45rem .75rem; font-size: .82rem; border-radius: 8px; }

        /* ── FILTERS ── */
        .filters-section {
            background: var(--white); border-radius: var(--radius);
            padding: 1.5rem; margin-bottom: 1.5rem;
            box-shadow: var(--shadow); border: 1px solid var(--gray-200);
        }
        .filters-grid {
            display: grid; grid-template-columns: 1fr 1fr 1fr auto auto;
            gap: 1rem; align-items: flex-end;
        }
        .form-group { display: flex; flex-direction: column; gap: .5rem; }
        .form-label { font-size: .88rem; font-weight: 600; color: var(--gray-700); }
        .form-input, .form-select, .form-textarea {
            padding: .7rem .9rem; border: 1.5px solid var(--gray-200); border-radius: 8px;
            font-family: inherit; font-size: .92rem; transition: border-color .2s, box-shadow .2s;
            background: var(--white); color: var(--gray-800);
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none; border-color: var(--indigo-light);
            box-shadow: 0 0 0 3px rgba(59,46,192,.1);
        }
        .form-select {
            padding-right: 2.5rem; appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%233b2ec0' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right .9rem center; cursor: pointer;
        }
        .form-textarea { resize: vertical; min-height: 80px; }

        /* ── ALERTS ── */
        .alert {
            padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: .75rem;
            border-left: 4px solid; animation: fadeIn .3s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: none; } }
        .alert-success { background: rgba(46,213,115,.08); border-color: var(--success); color: #1a7a40; }
        .alert-error   { background: rgba(255,71,87,.08);  border-color: var(--error);   color: #b30019; }

        /* ── TABLE ── */
        .card {
            background: var(--white); border-radius: var(--radius);
            padding: 1.5rem; box-shadow: var(--shadow); border: 1px solid var(--gray-200);
        }
        .card-header-row {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.25rem; gap: 1rem; flex-wrap: wrap;
        }
        .card-title { font-size: 1rem; font-weight: 700; color: var(--gray-700); }
        .total-badge {
            font-size: .82rem; font-weight: 600; color: var(--gray-500);
            background: var(--gray-100); padding: .3rem .7rem; border-radius: 6px;
        }

        .table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--gray-200); }
        .table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .table th {
            background: var(--gray-50); padding: .9rem 1rem;
            text-align: left; font-weight: 700; color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200);
            text-transform: uppercase; font-size: .75rem; letter-spacing: .5px;
            white-space: nowrap;
        }
        .table td { padding: .9rem 1rem; border-bottom: 1px solid var(--gray-200); vertical-align: middle; }
        .table tbody tr { transition: background .15s; }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: rgba(59,46,192,.03); }
        .table tbody tr.row-dibatalkan { opacity: .55; }
        .table tbody tr.row-dibatalkan:hover { opacity: .7; }

        .action-group { display: flex; gap: .4rem; flex-wrap: wrap; }

        /* ── PAGINATION ── */
        .pagination {
            display: flex; align-items: center; justify-content: center;
            gap: .4rem; margin-top: 1.5rem;
        }
        .pagination a, .pagination span {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px; text-decoration: none;
            color: var(--gray-700); border: 1px solid var(--gray-200);
            font-size: .9rem; font-weight: 600; transition: all .2s;
        }
        .pagination a:hover { background: var(--indigo-light); color: var(--white); border-color: var(--indigo-light); }
        .pagination .pg-active { background: var(--indigo-light); color: var(--white); border-color: var(--indigo-light); }

        /* ── MODAL ── */
        .modal {
            display: none; position: fixed; inset: 0;
            background: rgba(10,8,40,.55); backdrop-filter: blur(4px);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: var(--white); border-radius: var(--radius);
            padding: 2rem; max-width: 520px; width: 90%;
            box-shadow: var(--shadow-lg); animation: modalIn .25s ease;
            max-height: 90vh; overflow-y: auto;
        }
        .modal-content.modal-danger { border-top: 4px solid var(--error); }
        .modal-content.modal-wide { max-width: 680px; }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(24px) scale(.97); }
            to   { opacity: 1; transform: none; }
        }
        .modal-header {
            font-size: 1.15rem; font-weight: 700; color: var(--gray-800);
            margin-bottom: 1.5rem; display: flex; align-items: center; gap: .5rem;
        }
        .modal-body { margin-bottom: 1.5rem; }
        .modal-footer { display: flex; gap: .75rem; justify-content: flex-end; flex-wrap: wrap; }

        /* Cancel warning box */
        .cancel-warning {
            background: rgba(255,71,87,.07); border: 1.5px solid rgba(255,71,87,.2);
            border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;
            font-size: .88rem; color: #b30019;
            display: flex; gap: .75rem; align-items: flex-start;
        }
        .cancel-warning i { margin-top: 2px; flex-shrink: 0; }

        /* Detail rows */
        .detail-section { margin-bottom: 1rem; }
        .detail-section-title {
            font-size: .78rem; font-weight: 700; color: var(--gray-500);
            text-transform: uppercase; letter-spacing: .5px;
            margin-bottom: .6rem; padding-bottom: .4rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .detail-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: .4rem 0; gap: 1rem;
        }
        .detail-label { font-size: .88rem; color: var(--gray-500); flex-shrink: 0; }
        .detail-value { font-size: .88rem; font-weight: 600; color: var(--gray-800); text-align: right; }
        .item-list {
            border: 1px solid var(--gray-200); border-radius: 8px;
            overflow: hidden; margin-top: .5rem;
        }
        .item-list-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: .65rem .85rem; border-bottom: 1px solid var(--gray-200);
            font-size: .88rem; gap: .5rem;
        }
        .item-list-row:last-child { border-bottom: none; }
        .item-list-row:nth-child(even) { background: var(--gray-50); }
        .item-judul { font-weight: 500; color: var(--gray-800); flex: 1; }
        .item-qty   { color: var(--gray-500); white-space: nowrap; font-size: .82rem; }
        .item-harga { font-weight: 700; color: var(--indigo-light); white-space: nowrap; }

        /* ── No resi field (muncul saat pilih dikirim) ── */
        #noResiGroup { display: none; }
        #noResiGroup.show { display: flex; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1280px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 1024px) { .filters-grid { grid-template-columns: 1fr 1fr; } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { left: -260px; }
            .main-content { margin-left: 0; }
            .filters-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
            <div class="brand-sub">Admin Panel</div>
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

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="navbar-content">
            <div class="nav-title">📦 Manajemen Pesanan</div>
            <div class="dropdown-wrap">
                <div class="admin-avatar"><?= htmlspecialchars($admin_initial) ?></div>
                <div class="dropdown-menu">
                    <div class="dropdown-header">
                        <div class="dropdown-header-text">Masuk sebagai</div>
                        <div class="dropdown-header-name"><?= $admin_fullname ?></div>
                    </div>
                    <a href="../pages/profile.php"><i class="fas fa-user fa-fw"></i> Profil</a>
                    <a href="../pages/password-update.php"><i class="fas fa-lock fa-fw"></i> Ubah Password</a>
                    <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="page-content">

        <div class="page-header">
            <h1 class="page-title">Manajemen Pesanan</h1>
            <p class="page-subtitle">Kelola dan pantau semua pesanan customer — Total <strong><?= $total_semua ?></strong> pesanan</p>
        </div>

        <!-- ALERTS -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle fa-lg"></i>
                <span><?= $success_message ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle fa-lg"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <!-- ✅ STATS - hanya status yang ada di ENUM -->
        <div class="stats-grid">
            <a href="pesanan.php?status=dikemas" class="stat-card" style="text-decoration:none;">
                <div class="stat-icon blue"><i class="fas fa-box-open"></i></div>
                <div>
                    <div class="stat-label">Dikemas</div>
                    <div class="stat-value"><?= $pending ?></div>
                </div>
            </a>
            <a href="pesanan.php?status=dikirim" class="stat-card" style="text-decoration:none;">
                <div class="stat-icon purple"><i class="fas fa-truck"></i></div>
                <div>
                    <div class="stat-label">Dikirim</div>
                    <div class="stat-value"><?= $shipping ?></div>
                </div>
            </a>
            <a href="pesanan.php?status=selesai" class="stat-card" style="text-decoration:none;">
                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-label">Selesai</div>
                    <div class="stat-value"><?= $completed ?></div>
                </div>
            </a>
            <a href="pesanan.php?status=dibatalkan" class="stat-card" style="text-decoration:none;">
                <div class="stat-icon error"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="stat-label">Dibatalkan</div>
                    <div class="stat-value"><?= $cancelled ?></div>
                </div>
            </a>
            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <div class="stat-label">Revenue</div>
                    <div class="stat-value small"><?= formatRupiah($total_revenue) ?></div>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filters-section">
            <form method="get" action="pesanan.php">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-search" style="margin-right:.35rem; opacity:.5;"></i>Cari Pesanan</label>
                        <input type="text" name="search" class="form-input"
                               placeholder="ID, Nama, atau Email..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-filter" style="margin-right:.35rem; opacity:.5;"></i>Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <!-- ✅ FIX: Hapus opsi 'diproses' yang tidak ada di ENUM -->
                            <option value="dikemas"    <?= $filter_status === 'dikemas'    ? 'selected' : '' ?>>Dikemas</option>
                            <option value="dikirim"    <?= $filter_status === 'dikirim'    ? 'selected' : '' ?>>Dikirim</option>
                            <option value="selesai"    <?= $filter_status === 'selesai'    ? 'selected' : '' ?>>Selesai</option>
                            <option value="dibatalkan" <?= $filter_status === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-sort" style="margin-right:.35rem; opacity:.5;"></i>Urutkan</label>
                        <select name="sort" class="form-select">
                            <option value="terbaru"   <?= $sort_by === 'terbaru'   ? 'selected' : '' ?>>Terbaru</option>
                            <option value="terakhir"  <?= $sort_by === 'terakhir'  ? 'selected' : '' ?>>Terlama</option>
                            <option value="tertinggi" <?= $sort_by === 'tertinggi' ? 'selected' : '' ?>>Harga Tertinggi</option>
                            <option value="terendah"  <?= $sort_by === 'terendah'  ? 'selected' : '' ?>>Harga Terendah</option>
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
            <div class="card-header-row">
                <div class="card-title">Daftar Pesanan</div>
                <span class="total-badge"><?= $total_orders ?> pesanan ditemukan</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:70px;">#ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th style="text-align:center;">Items</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr class="<?= $order['status_pesanan'] === 'dibatalkan' ? 'row-dibatalkan' : '' ?>">
                                <td>
                                    <span style="font-weight:700; color:var(--gray-500); font-size:.85rem;">
                                        #<?= htmlspecialchars($order['id_pesanan']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:600; color:var(--gray-800); font-size:.92rem;">
                                        <?= htmlspecialchars($order['nama_depan'] . ' ' . $order['nama_belakang']) ?>
                                    </div>
                                    <div style="font-size:.8rem; color:var(--gray-500); margin-top:.1rem;">
                                        <?= htmlspecialchars($order['email']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight:700; color:var(--indigo-light);">
                                        <?= formatRupiah($order['total_harga']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-weight:600; background:var(--gray-100); padding:.25rem .6rem; border-radius:6px; font-size:.85rem;">
                                        <?= $order['jumlah_item'] ?> item
                                    </span>
                                </td>
                                <td><?= getStatusBadge($order['status_pesanan']) ?></td>
                                <td style="font-size:.88rem; color:var(--gray-600); white-space:nowrap;">
                                    <?= formatTanggal($order['tanggal_pesan']) ?>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <!-- Detail selalu tampil -->
                                        <button class="btn btn-secondary btn-sm"
                                                onclick="openDetail(<?= $order['id_pesanan'] ?>)"
                                                title="Lihat detail pesanan">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>

                                        <?php if (canAdminUpdate($order['status_pesanan'])): ?>
                                            <button class="btn btn-primary btn-sm"
                                                    onclick="openUpdate(
                                                        <?= $order['id_pesanan'] ?>,
                                                        '<?= addslashes($order['status_pesanan']) ?>'
                                                    )"
                                                    title="Update status pesanan">
                                                <i class="fas fa-arrow-right"></i> Update
                                            </button>
                                        <?php endif; ?>

                                        <?php if (canAdminCancel($order['status_pesanan'])): ?>
                                            <button class="btn btn-danger btn-sm"
                                                    onclick="openCancel(
                                                        <?= $order['id_pesanan'] ?>,
                                                        '<?= addslashes($order['nama_depan'] . ' ' . $order['nama_belakang']) ?>',
                                                        '<?= addslashes($order['status_pesanan']) ?>'
                                                    )"
                                                    title="Batalkan pesanan">
                                                <i class="fas fa-ban"></i> Batal
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:3rem; color:var(--gray-500);">
                                <i class="fas fa-inbox" style="font-size:2.5rem; display:block; margin-bottom:.75rem; opacity:.4;"></i>
                                <span style="font-weight:600;">Tidak ada pesanan ditemukan</span>
                                <?php if ($search || $filter_status): ?>
                                    <br><a href="pesanan.php" style="font-size:.85rem; color:var(--indigo-accent); margin-top:.5rem; display:inline-block;">Reset filter</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= urlencode($sort_by) ?>" title="Pertama">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= urlencode($sort_by) ?>" title="Sebelumnya">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++) {
                        $cls = $i === $page ? 'pg-active' : '';
                        echo "<a href=\"?page=$i&search=" . urlencode($search) . "&status=" . urlencode($filter_status) . "&sort=" . urlencode($sort_by) . "\" class=\"$cls\">$i</a>";
                    }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= urlencode($sort_by) ?>" title="Berikutnya">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= urlencode($sort_by) ?>" title="Terakhir">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div><!-- /card -->

    </div><!-- /page-content -->
</div><!-- /main-content -->

<!-- ═══════════════════════════════════
     MODAL: UPDATE STATUS
═══════════════════════════════════ -->
<div class="modal" id="updateModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-arrow-right" style="color:var(--indigo-accent);"></i>
            Update Status Pesanan <span id="updateOrderLabel" style="color:var(--gray-500); font-weight:500; font-size:.95rem;"></span>
        </div>
        <form method="POST" action="pesanan.php" id="updateForm">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id_pesanan" id="updateOrderId">
            <div class="modal-body">   <!-- ← ini harus DITUTUP sebelum modal-footer -->
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Status saat ini</label>
                    <div id="currentStatusBadge"></div>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Ubah status ke <span style="color:var(--error);">*</span></label>
                    <input type="hidden" name="status" value="dikirim">
                    <div class="form-input" style="background: var(--gray-100); cursor: not-allowed;">
                        Dikirim
                    </div>
                </div>
            </div>  <!-- ← </div> untuk modal-body yang sebelumnya HILANG -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateModal')">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════
     MODAL: BATALKAN PESANAN
═══════════════════════════════════ -->
<div class="modal" id="cancelModal">
    <div class="modal-content modal-danger">
        <div class="modal-header">
            <i class="fas fa-ban" style="color:var(--error);"></i>
            Batalkan Pesanan
        </div>
        <div class="modal-body">
            <div class="cancel-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Tindakan ini tidak dapat dibatalkan.</strong><br>
                    Stok buku akan dikembalikan otomatis setelah pesanan dibatalkan.
                </div>
            </div>
            <p style="margin-bottom:1rem; color:var(--gray-700); font-size:.92rem; line-height:1.5;">
                Anda akan membatalkan pesanan
                <strong id="cancelOrderLabel"></strong>
                dengan status saat ini: <span id="cancelStatusBadge"></span>
            </p>
            <div class="form-group">
                <label class="form-label">
                    Alasan pembatalan
                    <span style="color:var(--gray-400); font-weight:400; font-size:.82rem;">(opsional)</span>
                </label>
                <textarea id="cancelAlasan" class="form-textarea"
                          placeholder="Contoh: Stok habis, pembayaran bermasalah, permintaan customer..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('cancelModal')">
                <i class="fas fa-arrow-left"></i> Kembali
            </button>
            <button type="button" class="btn btn-danger-solid" id="cancelConfirmBtn" onclick="submitCancel()">
                <i class="fas fa-ban"></i> Ya, Batalkan Pesanan
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════
     MODAL: DETAIL PESANAN
═══════════════════════════════════ -->
<div class="modal" id="detailModal">
    <div class="modal-content modal-wide">
        <div class="modal-header">
            <i class="fas fa-receipt" style="color:var(--indigo-accent);"></i>
            Detail Pesanan
        </div>
        <div class="modal-body" id="detailContent">
            <div style="text-align:center; padding:2rem; color:var(--gray-500);">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top:.75rem;">Memuat data...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('detailModal')">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>
    </div>
</div>

<script>
// ══════════════════════════
// Modal helpers
// ══════════════════════════
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});

// ══════════════════════════
// Update Status Modal
// ✅ FIX: next status sesuai alur ENUM (tidak ada 'diproses')
// ══════════════════════════
const STATUS_NEXT = {
    dikemas: [{ value: 'dikirim', label: 'Dikirim' }],
    dikirim: [{ value: 'selesai', label: 'Selesai' }],
};

const STATUS_BADGE = {
    dikemas:    { bg: 'rgba(9,132,227,.12)',  color: '#0984e3', label: 'Dikemas' },
    dikirim:    { bg: 'rgba(124,92,250,.12)', color: '#7c5cfa', label: 'Dikirim' },
    selesai:    { bg: 'rgba(46,213,115,.12)', color: '#2ed573', label: 'Selesai' },
    dibatalkan: { bg: 'rgba(255,71,87,.12)',  color: '#ff4757', label: 'Dibatalkan' },
};

function makeBadgeHtml(status) {
    const s = STATUS_BADGE[status] || { bg: '#eee', color: '#666', label: status };
    return `<span style="background:${s.bg}; color:${s.color}; padding:.3rem .75rem; border-radius:8px; font-size:.85rem; font-weight:600;">${s.label}</span>`;
}

function openUpdate(id, currentStatus) {
    document.getElementById('updateOrderId').value = id;
    document.getElementById('updateOrderLabel').textContent = '#' + id;
    document.getElementById('currentStatusBadge').innerHTML = makeBadgeHtml(currentStatus);

    // Trigger no-resi toggle untuk nilai awal
    openModal('updateModal');
}

// ══════════════════════════
// Cancel Modal
// ══════════════════════════
let cancelTargetId = null;

function openCancel(id, namaCustomer, currentStatus) {
    cancelTargetId = id;
    document.getElementById('cancelOrderLabel').textContent = `#${id} — ${namaCustomer}`;
    document.getElementById('cancelStatusBadge').innerHTML  = makeBadgeHtml(currentStatus);
    document.getElementById('cancelAlasan').value = '';
    openModal('cancelModal');
}

function submitCancel() {
    if (!cancelTargetId) return;
    const btn    = document.getElementById('cancelConfirmBtn');
    const alasan = document.getElementById('cancelAlasan').value.trim();

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

    fetch('pesanan-batal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_pesanan: cancelTargetId, alasan })
    })
    .then(r => r.json())
    .then(data => {
        closeModal('cancelModal');
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 1600);
    })
    .catch(() => {
        closeModal('cancelModal');
        showToast('Terjadi kesalahan jaringan. Coba lagi.', 'error');
    })
    .finally(() => {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-ban"></i> Ya, Batalkan Pesanan';
    });
}

// ══════════════════════════
// Detail Modal
// ══════════════════════════
function openDetail(orderId) {
    document.getElementById('detailContent').innerHTML = `
        <div style="text-align:center; padding:2rem; color:var(--gray-500);">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top:.75rem;">Memuat data pesanan...</p>
        </div>`;
    openModal('detailModal');

    fetch(`pesanan-api.php?action=detail&id=${orderId}`)
        .then(r => r.json())
        .then(data => {
            if (!data || data.error) {
                document.getElementById('detailContent').innerHTML =
                    `<p style="color:red;">${data?.error || 'Gagal memuat detail'}</p>`;
                return;
            }

            const fmt = n => 'Rp ' + Number(n).toLocaleString('id-ID');

            const itemsHtml = data.items.map(item => `
                <div class="item-list-row">
                    <span class="item-judul">${item.judul}</span>
                    <span class="item-qty">${item.qty}x</span>
                    <span class="item-harga">${fmt(item.harga_saat_beli)}</span>
                </div>
            `).join('');

            // Alamat: tampilkan jika ada
            const alamatHtml = data.alamat_pengiriman
                ? `<div class="detail-row">
                       <span class="detail-label">Alamat</span>
                       <span class="detail-value" style="max-width:280px; text-align:right; line-height:1.4;">
                           ${data.alamat_pengiriman}
                       </span>
                   </div>`
                : '';

            document.getElementById('detailContent').innerHTML = `
                <!-- Info Customer -->
                <div class="detail-section">
                    <div class="detail-section-title">
                        <i class="fas fa-user" style="margin-right:.4rem;"></i>Info Customer
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Nama</span>
                        <span class="detail-value">${data.nama_depan} ${data.nama_belakang}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value">${data.email}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Telepon</span>
                        <span class="detail-value">${data.telepon || '-'}</span>
                    </div>
                </div>

                <!-- Info Pesanan -->
                <div class="detail-section">
                    <div class="detail-section-title">
                        <i class="fas fa-receipt" style="margin-right:.4rem;"></i>Info Pesanan
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">ID Pesanan</span>
                        <span class="detail-value" style="color:var(--gray-500);">#${data.id_pesanan}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">${makeBadgeHtml(data.status_pesanan)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Tanggal</span>
                        <span class="detail-value">${new Date(data.tanggal_pesan).toLocaleDateString('id-ID', {day:'numeric', month:'long', year:'numeric'})}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total</span>
                        <span class="detail-value" style="color:var(--indigo-light); font-size:1rem;">${fmt(data.total_harga)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Metode Bayar</span>
                        <span class="detail-value">${data.metode_pembayaran || '-'}</span>
                    </div>
                </div>

                <!-- Info Pengiriman -->
                <div class="detail-section">
                    <div class="detail-section-title">
                        <i class="fas fa-truck" style="margin-right:.4rem;"></i>Info Pengiriman
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Kurir</span>
                        <span class="detail-value">${data.kurir || '-'}</span>
                    </div>
                    ${alamatHtml}
                </div>

                <!-- Item Pesanan -->
                <div class="detail-section">
                    <div class="detail-section-title">
                        <i class="fas fa-book" style="margin-right:.4rem;"></i>Item Pesanan
                    </div>
                    <div class="item-list">${itemsHtml}</div>
                </div>
            `;
        })
        .catch(err => {
            console.error(err);
            document.getElementById('detailContent').innerHTML =
                `<p style="color:red; padding:1rem;">Gagal mengambil data. Coba lagi.</p>`;
        });
}

// ════════════════════════
// Toast Notification
// ══════════════════════════
function showToast(message, type = 'success') {
    const existing = document.getElementById('ls-toast');
    if (existing) existing.remove();

    const color = type === 'success' ? 'var(--success)' : 'var(--error)';
    const icon  = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

    const toast = document.createElement('div');
    toast.id = 'ls-toast';
    toast.style.cssText = `
        position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
        background:var(--white); border-left:4px solid ${color};
        border-radius:12px; padding:.9rem 1.4rem;
        box-shadow:0 8px 32px rgba(0,0,0,.18);
        display:flex; align-items:center; gap:.75rem;
        font-weight:600; color:var(--gray-800); font-size:.92rem;
        animation:slideUp .25s ease; max-width:380px;
        font-family:'DM Sans',sans-serif;
    `;
    toast.innerHTML = `<i class="fas ${icon}" style="color:${color}; font-size:1.1rem;"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, 4000);
}

// Auto-hide alert setelah 5 detik
document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => { a.style.opacity = '0'; a.style.transition = 'opacity .4s'; setTimeout(() => a.remove(), 400); }, 5000);
});

</script>

</body>
</html>