<?php
// admin/user.php - Manajemen User
error_reporting(E_ALL);
ini_set('display_errors', 0);

define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo      = getDB();
$admin_id = (int) $_SESSION['user_id'];

$success_message = null;
$error_message   = null;

// Verify admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user_auth = $stmt->fetch();

if (!$user_auth || $user_auth['role'] !== 'admin') {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Admin initial
$stmt = $pdo->prepare("SELECT nama_depan FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin_data    = $stmt->fetch();
$admin_initial = strtoupper(substr($admin_data['nama_depan'], 0, 1));

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // DELETE
    if ($action === 'delete') {
        $target_id = (int) $_POST['user_id'];
        if ($target_id === $admin_id) {
            $error_message = "Tidak dapat menghapus akun Anda sendiri.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
                $stmt->execute([$target_id, $admin_id]);
                $success_message = "User berhasil dihapus.";
            } catch (Exception $e) {
                $error_message = "Gagal menghapus user.";
            }
        }
    }

    // ADD USER
    if ($action === 'add_user') {
        $nama_depan    = trim($_POST['nama_depan'] ?? '');
        $nama_belakang = trim($_POST['nama_belakang'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $password      = $_POST['password'] ?? '';
        $role          = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';

        if (!$nama_depan || !$email || !$password) {
            $error_message = "Nama depan, email, dan password wajib diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid.";
        } elseif (strlen($password) < 8) {
            $error_message = "Password minimal 8 karakter.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error_message = "Email sudah digunakan.";
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt   = $pdo->prepare("
                        INSERT INTO users (nama_depan, nama_belakang, email, password, role, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$nama_depan, $nama_belakang, $email, $hashed, $role]);
                    $success_message = "User baru berhasil ditambahkan.";
                }
            } catch (Exception $e) {
                $error_message = "Gagal menambah user.";
            }
        }
    }

    // EDIT USER
    if ($action === 'edit_user') {
        $target_id     = (int) $_POST['user_id'];
        $nama_depan    = trim($_POST['nama_depan'] ?? '');
        $nama_belakang = trim($_POST['nama_belakang'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $role          = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
        $new_password  = $_POST['password'] ?? '';

        if ($target_id === $admin_id && $role !== 'admin') {
            $error_message = "Tidak dapat mengubah role akun Anda sendiri.";
        } elseif (!$nama_depan || !$email) {
            $error_message = "Nama depan dan email wajib diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid.";
        } else {
            try {
                // Cek email duplikat (kecuali user itu sendiri)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $target_id]);
                if ($stmt->fetch()) {
                    $error_message = "Email sudah digunakan.";
                } else {
                    if ($new_password && strlen($new_password) >= 8) {
                        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE users SET nama_depan=?, nama_belakang=?, email=?, role=?, password=? WHERE id=?");
                        $stmt->execute([$nama_depan, $nama_belakang, $email, $role, $hashed, $target_id]);
                    } elseif ($new_password && strlen($new_password) < 8) {
                        $error_message = "Password baru minimal 8 karakter.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET nama_depan=?, nama_belakang=?, email=?, role=? WHERE id=?");
                        $stmt->execute([$nama_depan, $nama_belakang, $email, $role, $target_id]);
                    }
                    if (!$error_message) $success_message = "Data user berhasil diperbarui.";
                }
            } catch (Exception $e) {
                $error_message = "Gagal memperbarui user.";
            }
        }
    }
}

// Pagination & filters
$page        = max(1, (int) ($_GET['page'] ?? 1));
$search      = trim($_GET['search'] ?? '');
$filter_role = in_array($_GET['role'] ?? '', ['admin', 'user']) ? $_GET['role'] : '';

// Sort: tiap kolom punya arah tetap (tidak bisa dibalik user)
// id=1,2,3... | nama=A-Z | email=A-Z | bergabung=paling dulu duluan
$valid_sort_options = [
    'id'         => ['label' => 'ID',        'order' => 'asc'],
    'nama_depan' => ['label' => 'Nama',      'order' => 'asc'],
    'email'      => ['label' => 'Email',     'order' => 'asc'],
    'created_at' => ['label' => 'Bergabung', 'order' => 'asc'],
];

$sort = $_GET['sort'] ?? 'id';
if (!array_key_exists($sort, $valid_sort_options)) {
    $sort = 'id';
}
$order = $valid_sort_options[$sort]['order']; // arah selalu tetap

$per_page = 10;
$offset   = ($page - 1) * $per_page;

$where  = ["id != $admin_id"]; // tampilkan semua kecuali admin yg login — ATAU tampilkan semua
$where  = []; // tampilkan semua user termasuk admin lain
$params = [];

if ($search) {
    $where[]  = "(nama_depan LIKE ? OR nama_belakang LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_role) {
    $where[]  = "role = ?";
    $params[] = $filter_role;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_users  = 0;
$total_pages  = 1;
$users        = [];
$total_count  = 0;
$total_admin  = 0;
$total_member = 0;

try {
    $stmt        = $pdo->query("SELECT COUNT(*) FROM users");
    $total_count = (int) $stmt->fetchColumn();

    $stmt        = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $total_admin = (int) $stmt->fetchColumn();

    $stmt         = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $total_member = (int) $stmt->fetchColumn();

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where_clause");
    $count_stmt->execute($params);
    $total_users = (int) $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_users / $per_page));

    $sql = "
        SELECT u.id, u.nama_depan, u.nama_belakang, u.email, u.role, u.created_at,
               COUNT(p.id_pesanan) AS total_pesanan
        FROM users u
        LEFT JOIN pesanan p ON u.id = p.id_user
        $where_clause
        GROUP BY u.id
        ORDER BY u.$sort $order
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Terjadi kesalahan saat mengambil data.";
}

function getInitials($nama_depan, $nama_belakang = '') {
    $i = strtoupper(substr($nama_depan, 0, 1));
    if ($nama_belakang) $i .= strtoupper(substr($nama_belakang, 0, 1));
    return $i;
}

$avatar_colors = ['#4f46e5','#10b981','#ef4444','#f59e0b','#8b5cf6','#06b6d4','#ec4899','#14b8a6'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manajemen User — Admin LiteraSpace</title>
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
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, #f5f2ff 100%);
            color: var(--gray-800);
            min-height: 100vh;
            display: flex;
        }

        /* SIDEBAR */
        .sidebar { position: fixed; left: 0; top: 0; width: 260px; height: 100vh; background: linear-gradient(180deg, var(--indigo-deep) 0%, var(--indigo-mid) 100%); box-shadow: 4px 0 24px rgba(30,22,103,.15); z-index: 40; overflow-y: auto; padding-top: 1.5rem; }
        .sidebar-brand { padding: 0 1.5rem; margin-bottom: 2rem; display: flex; align-items: center; gap: .75rem; }
        .brand-icon { width: 48px; height: 48px; background: rgba(255,255,255,.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--white); border: 2px solid rgba(255,255,255,.2); }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 1.1rem; font-weight: 700; color: var(--white); }
        .brand-sub  { font-size: .7rem; color: rgba(255,255,255,.7); text-transform: uppercase; letter-spacing: 1px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { padding: 0 1rem; margin-bottom: .5rem; }
        .sidebar-menu a { display: flex; align-items: center; gap: .75rem; padding: .875rem 1rem; color: rgba(255,255,255,.8); text-decoration: none; border-radius: 12px; transition: all .3s; font-size: .95rem; font-weight: 500; }
        .sidebar-menu a:hover { background: rgba(255,255,255,.12); color: var(--white); }
        .sidebar-menu a.active { background: linear-gradient(90deg, var(--indigo-accent), var(--indigo-light)); color: var(--white); box-shadow: 0 4px 12px rgba(124,92,250,.3); }
        .sidebar-menu i { width: 18px; text-align: center; }

        /* MAIN */
        .main-content { flex: 1; margin-left: 260px; display: flex; flex-direction: column; }

        /* NAVBAR */
        .navbar { position: sticky; top: 0; z-index: 30; background: var(--white); box-shadow: 0 4px 16px rgba(30,22,103,.08); border-bottom: 1px solid var(--gray-200); padding: 1rem 2rem; }
        .navbar-content { display: flex; align-items: center; justify-content: space-between; }
        .nav-title { font-size: 1.3rem; font-weight: 700; color: var(--gray-800); }
        .admin-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--indigo-light), var(--indigo-accent)); display: flex; align-items: center; justify-content: center; color: var(--white); font-weight: 700; font-size: .95rem; cursor: pointer; }
        .dropdown-wrap { position: relative; }
        .dropdown-menu { position: absolute; right: 0; top: calc(100% + 8px); width: 220px; background: var(--white); border-radius: 12px; box-shadow: var(--shadow-lg); border: 1px solid var(--gray-200); opacity: 0; visibility: hidden; transition: opacity .2s, visibility .2s; z-index: 100; }
        .dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; font-size: .9rem; color: var(--gray-800); text-decoration: none; transition: background .15s; }
        .dropdown-menu a:hover { background: var(--gray-50); color: var(--indigo-light); }
        .dropdown-menu a.logout { color: var(--error); border-top: 1px solid var(--gray-200); }

        /* PAGE */
        .page-content { flex: 1; padding: 2rem; overflow-y: auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; }
        .page-title { font-size: 1.75rem; font-weight: 700; }
        .page-subtitle { font-size: .95rem; color: var(--gray-500); margin-top: .25rem; }

        /* BUTTONS */
        .btn { display: inline-flex; align-items: center; gap: .5rem; padding: .75rem 1.25rem; border: none; border-radius: 10px; font-size: .95rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .2s; font-family: inherit; }
        .btn-primary { background: linear-gradient(135deg, var(--indigo-light), var(--indigo-accent)); color: var(--white); box-shadow: 0 4px 12px rgba(59,46,192,.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59,46,192,.4); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-800); }
        .btn-secondary:hover { background: var(--gray-200); }
        .btn-warning { background: rgba(245,158,11,.12); color: #b45309; }
        .btn-warning:hover { background: rgba(245,158,11,.22); }
        .btn-danger { background: rgba(255,71,87,.1); color: var(--error); }
        .btn-danger:hover { background: rgba(255,71,87,.2); }
        .btn-sm { padding: .45rem .75rem; font-size: .82rem; border-radius: 8px; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--white); border-radius: var(--radius); padding: 1.25rem 1.5rem; border: 1px solid var(--gray-200); box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; }
        .stat-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .stat-label { font-size: .8rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; letter-spacing: .5px; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--gray-800); line-height: 1; margin: .2rem 0; }
        .stat-sub { font-size: .8rem; color: var(--gray-500); }

        /* FILTERS */
        .filters-section { background: var(--white); border-radius: var(--radius); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); border: 1px solid var(--gray-200); }
        .filters-grid { display: grid; grid-template-columns: 1fr 180px 180px auto auto; gap: 1rem; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: .4rem; }
        .form-label { font-size: .85rem; font-weight: 600; color: var(--gray-700); }
        .form-input, .form-select { padding: .7rem .9rem; border: 1.5px solid var(--gray-200); border-radius: 8px; font-family: inherit; font-size: .9rem; transition: border-color .2s; background: var(--white); width: 100%; }
        .form-input:focus, .form-select:focus { outline: none; border-color: var(--indigo-light); }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b6b8a' d='M6 8L0 0h12z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right .9rem center; padding-right: 2.2rem; cursor: pointer; }

        /* ALERT */
        .alert { padding: .875rem 1rem; border-radius: 10px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: .75rem; border-left: 4px solid; }
        .alert-success { background: rgba(46,213,115,.1); border-color: var(--success); color: #1a8a4a; }
        .alert-error   { background: rgba(255,71,87,.1);  border-color: var(--error);   color: var(--error); }

        /* CARD / TABLE */
        .card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--gray-200); overflow: hidden; }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .table th { background: linear-gradient(90deg, var(--gray-50), var(--gray-100)); padding: .875rem 1rem; text-align: left; font-weight: 700; color: var(--gray-600); border-bottom: 2px solid var(--gray-200); text-transform: uppercase; font-size: .78rem; letter-spacing: .5px; white-space: nowrap; }
        .table th a { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: .3rem; }
        .table th a:hover { color: var(--indigo-light); }
        .table td { padding: .875rem 1rem; border-bottom: 1px solid var(--gray-200); vertical-align: middle; }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: rgba(59,46,192,.03); }

        .user-cell { display: flex; align-items: center; gap: .75rem; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .85rem; font-weight: 700; color: white; flex-shrink: 0; }
        .user-name { font-weight: 600; font-size: .9rem; }
        .user-id   { font-size: .78rem; color: var(--gray-500); margin-top: 1px; }

        /* ROLE BADGE */
        .badge { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .65rem; border-radius: 99px; font-size: .75rem; font-weight: 700; letter-spacing: .3px; }
        .badge-admin  { background: rgba(59,46,192,.12); color: var(--indigo-light); }
        .badge-user   { background: rgba(16,185,129,.12); color: #0d7a55; }

        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--gray-500); }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: .4; }

        /* PAGINATION */
        .pagination-wrap { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-top: 1px solid var(--gray-200); background: var(--gray-50); }
        .pagination-info { font-size: .85rem; color: var(--gray-500); }
        .pagination { display: flex; align-items: center; gap: .35rem; }
        .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; text-decoration: none; font-size: .85rem; font-weight: 500; color: var(--gray-800); border: 1px solid var(--gray-200); transition: all .2s; }
        .pagination a:hover { background: var(--indigo-light); color: var(--white); border-color: var(--indigo-light); }
        .pagination .active { background: var(--indigo-light); color: var(--white); border-color: var(--indigo-light); }
        .pagination .dots { border: none; cursor: default; }

        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-box { background: var(--white); border-radius: var(--radius); padding: 2rem; width: 90%; max-width: 480px; box-shadow: var(--shadow-lg); animation: slideUp .25s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-title { font-size: 1.15rem; font-weight: 700; color: var(--gray-800); margin-bottom: 1.25rem; display: flex; align-items: center; gap: .6rem; }
        .modal-body { margin-bottom: 1.5rem; }
        .modal-body p { color: var(--gray-600); line-height: 1.6; }
        .modal-footer { display: flex; gap: .75rem; justify-content: flex-end; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-row-full { margin-bottom: 1rem; }
        .modal-box .form-input,
        .modal-box .form-select { width: 100%; }
        .pass-note { font-size: .78rem; color: var(--gray-500); margin-top: .3rem; }

        @media (max-width: 900px) {
            .filters-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { left: -260px; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .filters-grid { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .form-row { grid-template-columns: 1fr; }
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
        <li><a href="user.php" class="active"><i class="fas fa-users"></i> User</a></li>
        <li><a href="review.php"><i class="fas fa-star"></i> Review</a></li>
        <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        <li><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
    </ul>
</aside>

<!-- MAIN -->
<div class="main-content">

    <nav class="navbar">
        <div class="navbar-content">
            <div class="nav-title">Manajemen User</div>
            <div class="dropdown-wrap">
                <div class="admin-avatar"><?= $admin_initial ?></div>
                <div class="dropdown-menu">
                    <a href="../pages/profile.php"><i class="fas fa-user"></i> Profil</a>
                    <a href="../pages/password-update.php"><i class="fas fa-lock"></i> Ubah Password</a>
                    <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="page-content">

        <div class="page-header">
            <div>
                <h1 class="page-title">Manajemen User</h1>
                <p class="page-subtitle">Kelola semua pengguna terdaftar di platform LiteraSpace</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('addModal')">
                <i class="fas fa-user-plus"></i> Tambah User
            </button>
        </div>

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

        <!-- STATS: Total semua, Admin, User biasa -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eef2ff; color:#4f46e5;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="stat-label">Total Semua</div>
                    <div class="stat-value"><?= number_format($total_count) ?></div>
                    <div class="stat-sub">Admin + User</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(124,92,250,.12); color:#7c5cfa;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <div class="stat-label">Admin</div>
                    <div class="stat-value"><?= number_format($total_admin) ?></div>
                    <div class="stat-sub">Pengelola sistem</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,.12); color:#0d7a55;">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="stat-label">User</div>
                    <div class="stat-value"><?= number_format($total_member) ?></div>
                    <div class="stat-sub">Pengguna terdaftar</div>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filters-section">
            <form method="get" action="user.php">
                <div class="filters-grid">
                    <!-- Search -->
                    <div class="form-group">
                        <label class="form-label">Cari User</label>
                        <input type="text" name="search" class="form-input"
                               placeholder="Nama atau email..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <!-- Filter Role -->
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="">Semua Role</option>
                            <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="user"  <?= $filter_role === 'user'  ? 'selected' : '' ?>>User</option>
                        </select>
                    </div>

                    <!-- Sort -->
                    <div class="form-group">
                        <label class="form-label">Urutkan</label>
                        <select name="sort" class="form-select">
                            <?php foreach ($valid_sort_options as $val => $opt): ?>
                                <option value="<?= $val ?>" <?= $sort === $val ? 'selected' : '' ?>>
                                    <?= $opt['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="align-self:flex-end;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="user.php" class="btn btn-secondary" style="align-self:flex-end;">
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
                        <?php
                        // Helper: tampilkan ikon sort di header kolom yang aktif
                        function sortIcon($col, $sort, $order) {
                            if ($sort !== $col) return '<i class="fas fa-sort" style="opacity:.25;font-size:.7rem;margin-left:.3rem;"></i>';
                            $icon = $order === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
                            return '<i class="fas ' . $icon . '" style="font-size:.7rem;margin-left:.3rem;color:var(--indigo-accent);"></i>';
                        }
                        ?>
                        <tr>
                            <th>ID <?= sortIcon('id', $sort, $order) ?></th>
                            <th>Nama User <?= sortIcon('nama_depan', $sort, $order) ?></th>
                            <th>Email <?= sortIcon('email', $sort, $order) ?></th>
                            <th>Role <?= sortIcon('role', $sort, $order) ?></th>
                            <th style="text-align:center;">Pesanan</th>
                            <th>Bergabung <?= sortIcon('created_at', $sort, $order) ?></th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u):
                            $fullname = trim($u['nama_depan'] . ' ' . $u['nama_belakang']);
                            $initials = getInitials($u['nama_depan'], $u['nama_belakang']);
                            $color    = $avatar_colors[$u['id'] % count($avatar_colors)];
                            $joined   = $u['created_at'] ? date('d M Y', strtotime($u['created_at'])) : '—';
                            $is_self  = (int)$u['id'] === $admin_id;
                        ?>
                        <tr>
                            <td style="font-weight:700; color:var(--indigo-light);">#<?= $u['id'] ?></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar" style="background:<?= $color ?>;">
                                        <?= htmlspecialchars($initials) ?>
                                    </div>
                                    <div>
                                        <div class="user-name">
                                            <?= htmlspecialchars($fullname) ?>
                                            <?php if ($is_self): ?>
                                                <span style="font-size:.72rem;color:var(--indigo-accent);font-weight:600;"> (Anda)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-id">#USR-<?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--gray-600);"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge badge-admin"><i class="fas fa-shield-alt" style="font-size:.65rem;"></i> Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-user"><i class="fas fa-user" style="font-size:.65rem;"></i> User</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center; font-weight:600; color:var(--indigo-light);">
                                <?= $u['total_pesanan'] > 0
                                    ? $u['total_pesanan']
                                    : '<span style="color:var(--gray-500);font-weight:400;">—</span>' ?>
                            </td>
                            <td style="color:var(--gray-500); font-size:.85rem;"><?= $joined ?></td>
                            <td>
                                <div style="display:flex; gap:.4rem;">
                                    <!-- Tombol Edit -->
                                    <button class="btn btn-warning btn-sm"
                                        onclick="openEditModal(
                                            <?= $u['id'] ?>,
                                            '<?= htmlspecialchars(addslashes($u['nama_depan'])) ?>',
                                            '<?= htmlspecialchars(addslashes($u['nama_belakang'])) ?>',
                                            '<?= htmlspecialchars(addslashes($u['email'])) ?>',
                                            '<?= $u['role'] ?>'
                                        )"
                                        title="Edit User">
                                        <i class="fas fa-pencil-alt"></i> Edit
                                    </button>
                                    <!-- Tombol Hapus (nonaktif untuk akun sendiri) -->
                                    <?php if (!$is_self): ?>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="openDeleteModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($fullname)) ?>')"
                                        title="Hapus User">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm" disabled
                                        style="opacity:.35; cursor:not-allowed; background:var(--gray-100);"
                                        title="Tidak bisa hapus akun sendiri">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-users-slash"></i>
                                    <?= $search || $filter_role
                                        ? 'Tidak ada user yang sesuai filter'
                                        : 'Belum ada user terdaftar' ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div class="pagination-wrap">
                <div class="pagination-info">
                    <?php if ($total_users > 0): ?>
                        Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_users) ?>
                        dari <?= number_format($total_users) ?> akun
                    <?php else: ?>
                        Tidak ada data
                    <?php endif; ?>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php $base = "?search=" . urlencode($search) . "&sort=" . urlencode($sort) . "&role=" . urlencode($filter_role); ?>
                    <?php if ($page > 1): ?>
                        <a href="<?= $base ?>&page=1" title="Pertama"><i class="fas fa-angle-double-left" style="font-size:.7rem;"></i></a>
                        <a href="<?= $base ?>&page=<?= $page - 1 ?>"><i class="fas fa-angle-left" style="font-size:.7rem;"></i></a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($total_pages, $page + 2);
                    if ($start > 1) echo '<span class="dots">…</span>';
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="<?= $base ?>&page=<?= $i ?>"
                           class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor;
                    if ($end < $total_pages) echo '<span class="dots">…</span>';
                    if ($page < $total_pages): ?>
                        <a href="<?= $base ?>&page=<?= $page + 1 ?>"><i class="fas fa-angle-right" style="font-size:.7rem;"></i></a>
                        <a href="<?= $base ?>&page=<?= $total_pages ?>" title="Terakhir"><i class="fas fa-angle-double-right" style="font-size:.7rem;"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /page-content -->
</div><!-- /main-content -->

<!-- ===================== MODAL: ADD USER ===================== -->
<div class="modal" id="addModal">
    <div class="modal-box">
        <div class="modal-title">
            <i class="fas fa-user-plus" style="color:var(--indigo-accent);"></i>
            Tambah User Baru
        </div>
        <form method="post">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Depan <span style="color:var(--error);">*</span></label>
                        <input type="text" name="nama_depan" class="form-input" placeholder="Nama depan" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Belakang</label>
                        <input type="text" name="nama_belakang" class="form-input" placeholder="Nama belakang">
                    </div>
                </div>
                <div class="form-row-full form-group">
                    <label class="form-label">Email <span style="color:var(--error);">*</span></label>
                    <input type="email" name="email" class="form-input" placeholder="email@contoh.com" required>
                </div>
                <div class="form-row-full form-group">
                    <label class="form-label">Password <span style="color:var(--error);">*</span></label>
                    <input type="password" name="password" class="form-input" placeholder="Minimal 8 karakter" required minlength="8">
                </div>
                <div class="form-row-full form-group">
                    <label class="form-label">Role <span style="color:var(--error);">*</span></label>
                    <select name="role" class="form-select">
                        <option value="user" selected>User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== MODAL: EDIT USER ===================== -->
<div class="modal" id="editModal">
    <div class="modal-box">
        <div class="modal-title">
            <i class="fas fa-user-edit" style="color:#f59e0b;"></i>
            Edit User
        </div>
        <form method="post">
            <input type="hidden" name="action"  value="edit_user">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Depan <span style="color:var(--error);">*</span></label>
                        <input type="text" name="nama_depan" id="editNamaDepan" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Belakang</label>
                        <input type="text" name="nama_belakang" id="editNamaBelakang" class="form-input">
                    </div>
                </div>
                <div class="form-row-full form-group">
                    <label class="form-label">Email <span style="color:var(--error);">*</span></label>
                    <input type="email" name="email" id="editEmail" class="form-input" required>
                </div>
                <div class="form-row-full form-group">
                    <label class="form-label">Role <span style="color:var(--error);">*</span></label>
                    <select name="role" id="editRole" class="form-select">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-row-full form-group">
                    <label class="form-label">Password Baru <span style="color:var(--gray-500); font-weight:400;">(opsional)</span></label>
                    <input type="password" name="password" class="form-input" placeholder="Kosongkan jika tidak ingin mengubah" minlength="8">
                    <span class="pass-note"><i class="fas fa-info-circle"></i> Isi hanya jika ingin mengganti password. Minimal 8 karakter.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Perbarui</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== MODAL: DELETE ===================== -->
<div class="modal" id="deleteModal">
    <div class="modal-box">
        <div class="modal-title">
            <i class="fas fa-exclamation-triangle" style="color:var(--error);"></i>
            Hapus User
        </div>
        <div class="modal-body">
            <p>Apakah Anda yakin ingin menghapus user <strong id="deleteUserName"></strong>?</p>
            <p style="font-size:.88rem; color:var(--gray-500); margin-top:.75rem;">
                Aksi ini tidak dapat dibatalkan dan akan menghapus semua data terkait user tersebut.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Batal</button>
            <form method="post" style="display:inline;">
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Ya, Hapus
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id)  { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    // Tutup modal kalau klik backdrop
    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); });
    });

    // Buka modal edit & isi data
    function openEditModal(id, namaDepan, namaBelakang, email, role) {
        document.getElementById('editUserId').value       = id;
        document.getElementById('editNamaDepan').value    = namaDepan;
        document.getElementById('editNamaBelakang').value = namaBelakang;
        document.getElementById('editEmail').value        = email;
        document.getElementById('editRole').value         = role;
        openModal('editModal');
    }

    // Buka modal hapus
    function openDeleteModal(id, name) {
        document.getElementById('deleteUserId').value      = id;
        document.getElementById('deleteUserName').textContent = name;
        openModal('deleteModal');
    }

    // Auto-fade alert setelah 4 detik
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => {
            el.style.transition = 'opacity .5s';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 500);
        });
    }, 4000);
</script>
</body>
</html>