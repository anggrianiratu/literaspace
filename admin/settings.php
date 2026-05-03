<?php
// admin/settings.php - Admin Settings Management
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

// Settings file path (using JSON for simplicity)
$settings_file = BASE_URL . 'config/settings.json';
$settings = [];

// Load settings if exists
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true) ?? [];
}

// Default settings
$default_settings = [
    'website_name' => 'LiteraSpace',
    'website_description' => 'Platform Jual Beli Buku Online',
    'phone' => '+62 123 456 7890',
    'email' => 'info@literaspace.com',
    'address' => 'Jakarta, Indonesia',
    'bank_name' => 'Bank BCA',
    'bank_account' => '1234567890',
    'bank_holder' => 'PT. Litera Space',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'smtp_email' => '',
    'smtp_password' => '',
];

// Merge with loaded settings
$settings = array_merge($default_settings, $settings);

// Handle POST - Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? 'general';

    if ($tab === 'general') {
        $settings['website_name'] = trim($_POST['website_name'] ?? '');
        $settings['website_description'] = trim($_POST['website_description'] ?? '');
        $settings['phone'] = trim($_POST['phone'] ?? '');
        $settings['email'] = trim($_POST['email'] ?? '');
        $settings['address'] = trim($_POST['address'] ?? '');

        if (!$settings['website_name']) {
            $error_message = "Nama website wajib diisi.";
        } else {
            if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                $success_message = "Pengaturan umum berhasil disimpan.";
            } else {
                $error_message = "Gagal menyimpan pengaturan. Periksa permission folder config.";
            }
        }
    }

    if ($tab === 'bank') {
        $settings['bank_name'] = trim($_POST['bank_name'] ?? '');
        $settings['bank_account'] = trim($_POST['bank_account'] ?? '');
        $settings['bank_holder'] = trim($_POST['bank_holder'] ?? '');

        if (!$settings['bank_name'] || !$settings['bank_account']) {
            $error_message = "Nama bank dan nomor rekening wajib diisi.";
        } else {
            if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                $success_message = "Pengaturan bank berhasil disimpan.";
            } else {
                $error_message = "Gagal menyimpan pengaturan.";
            }
        }
    }

    if ($tab === 'email') {
        $settings['smtp_host'] = trim($_POST['smtp_host'] ?? '');
        $settings['smtp_port'] = trim($_POST['smtp_port'] ?? '');
        $settings['smtp_email'] = trim($_POST['smtp_email'] ?? '');
        $settings['smtp_password'] = $_POST['smtp_password'] ?? '';

        if (!$settings['smtp_host'] || !$settings['smtp_port'] || !$settings['smtp_email']) {
            $error_message = "SMTP host, port, dan email wajib diisi.";
        } else {
            if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                $success_message = "Pengaturan email berhasil disimpan.";
            } else {
                $error_message = "Gagal menyimpan pengaturan.";
            }
        }
    }

    if ($tab === 'security') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'backup') {
            $success_message = "Fitur backup akan segera tersedia. Database bisa di-backup melalui phpMyAdmin.";
        }
    }
}

// Get some stats for dashboard
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM buku");
$total_books = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM pesanan");
$total_orders = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM kategori");
$total_categories = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pengaturan Admin — LiteraSpace</title>
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
            gap: 1.25rem; margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white); border-radius: var(--radius); padding: 1.5rem;
            box-shadow: var(--shadow); border: 1px solid var(--gray-200);
            display: flex; flex-direction: column; gap: .75rem;
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
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--gray-800); }

        /* ── TABS ── */
        .tabs-wrapper {
            background: var(--white); border-radius: var(--radius);
            box-shadow: var(--shadow); border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .tabs-header {
            display: flex;
            border-bottom: 2px solid var(--gray-200);
            background: var(--gray-50);
        }

        .tab-button {
            flex: 1; padding: 1rem 1.5rem;
            background: none; border: none;
            color: var(--gray-600); font-size: .95rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }

        .tab-button:hover { color: var(--indigo-light); }
        .tab-button.active {
            color: var(--indigo-light);
            border-bottom-color: var(--indigo-light);
            background: var(--white);
        }

        .tabs-content { padding: 2rem; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* ── FORMS ── */
        .form-group { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.5rem; }
        .form-label { font-size: .9rem; font-weight: 600; color: var(--gray-700); }

        .form-input, .form-textarea {
            padding: .75rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: inherit;
            font-size: .95rem;
            transition: border-color .2s;
            background: var(--white);
        }

        .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--indigo-light); }

        .form-textarea { resize: vertical; min-height: 100px; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }

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
        .btn-danger { background: rgba(255,71,87,.1); color: var(--error); }
        .btn-danger:hover { background: rgba(255,71,87,.2); }
        .btn-sm { padding: .5rem .75rem; font-size: .85rem; }

        /* ── ALERTS ── */
        .alert {
            padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: .75rem; border-left: 4px solid;
        }

        .alert-success { background: rgba(46,213,115,.1); border-color: var(--success); color: #1a8a4a; }
        .alert-error   { background: rgba(255,71,87,.1);  border-color: var(--error);   color: var(--error); }

        /* ── SETTING CARD ── */
        .setting-card {
            background: var(--white); border-radius: var(--radius); padding: 1.5rem;
            margin-bottom: 1.5rem; border: 1px solid var(--gray-200);
        }

        .setting-card-title { font-size: 1.1rem; font-weight: 700; color: var(--gray-800); margin-bottom: 1rem; }
        .setting-card-desc { font-size: .85rem; color: var(--gray-500); margin-bottom: 1.5rem; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { left: -260px; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .tabs-header { flex-wrap: wrap; }
            .tab-button { padding: .75rem 1rem; font-size: .85rem; }
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
        <li><a href="#"><i class="fas fa-shopping-bag"></i> Pesanan</a></li>
        <li><a href="user.php"><i class="fas fa-user"></i> User</a></li>
        <li><a href="#"><i class="fas fa-star"></i> Review</a></li>
        <li><a href="#"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Pengaturan</a></li>
    </ul>
</aside>

<!-- MAIN -->
<div class="main-content">

    <nav class="navbar">
        <div class="navbar-content">
            <div class="nav-title">Pengaturan Admin</div>
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
            <h1 class="page-title">Pengaturan Sistem</h1>
            <p class="page-subtitle">Kelola konfigurasi website dan fitur administrator LiteraSpace</p>
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
                <div class="stat-icon indigo"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-label">Total User</div>
                    <div class="stat-value"><?= $total_users ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-book"></i></div>
                <div>
                    <div class="stat-label">Total Buku</div>
                    <div class="stat-value"><?= $total_books ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fas fa-shopping-bag"></i></div>
                <div>
                    <div class="stat-label">Total Pesanan</div>
                    <div class="stat-value"><?= $total_orders ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info"><i class="fas fa-folder"></i></div>
                <div>
                    <div class="stat-label">Kategori</div>
                    <div class="stat-value"><?= $total_categories ?></div>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="tabs-wrapper">
            <div class="tabs-header">
                <button class="tab-button active" onclick="switchTab(event, 'general')">
                    <i class="fas fa-sliders-h"></i> Umum
                </button>
                <button class="tab-button" onclick="switchTab(event, 'bank')">
                    <i class="fas fa-building"></i> Bank
                </button>
                <button class="tab-button" onclick="switchTab(event, 'email')">
                    <i class="fas fa-envelope"></i> Email
                </button>
                <button class="tab-button" onclick="switchTab(event, 'security')">
                    <i class="fas fa-shield-alt"></i> Keamanan
                </button>
            </div>

            <div class="tabs-content">

                <!-- TAB: GENERAL -->
                <div class="tab-pane active" id="general">
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="tab" value="general">

                        <div class="setting-card">
                            <div class="setting-card-title">Informasi Website</div>
                            <div class="setting-card-desc">Ubah nama dan deskripsi website Anda</div>

                            <div class="form-group">
                                <label class="form-label">Nama Website <span style="color:var(--error);">*</span></label>
                                <input type="text" name="website_name" class="form-input"
                                       value="<?= htmlspecialchars($settings['website_name'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Deskripsi Website</label>
                                <textarea name="website_description" class="form-textarea"
                                          placeholder="Deskripsi singkat tentang website Anda"><?= htmlspecialchars($settings['website_description'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="setting-card">
                            <div class="setting-card-title">Kontak Perusahaan</div>
                            <div class="setting-card-desc">Informasi kontak yang ditampilkan kepada customer</div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nomor Telepon</label>
                                    <input type="text" name="phone" class="form-input"
                                           placeholder="+62 123 456 7890"
                                           value="<?= htmlspecialchars($settings['phone'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Email Perusahaan</label>
                                    <input type="email" name="email" class="form-input"
                                           placeholder="info@literaspace.com"
                                           value="<?= htmlspecialchars($settings['email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Alamat</label>
                                <textarea name="address" class="form-textarea"
                                          placeholder="Alamat lengkap perusahaan"><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div style="display:flex; gap:1rem; justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- TAB: BANK -->
                <div class="tab-pane" id="bank">
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="tab" value="bank">

                        <div class="setting-card">
                            <div class="setting-card-title">Rekening Bank</div>
                            <div class="setting-card-desc">Informasi rekening untuk transfer pembayaran customer</div>

                            <div class="form-group">
                                <label class="form-label">Nama Bank <span style="color:var(--error);">*</span></label>
                                <input type="text" name="bank_name" class="form-input"
                                       placeholder="cth: BCA, Mandiri, BRI..."
                                       value="<?= htmlspecialchars($settings['bank_name'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Nomor Rekening <span style="color:var(--error);">*</span></label>
                                <input type="text" name="bank_account" class="form-input"
                                       placeholder="cth: 1234567890"
                                       value="<?= htmlspecialchars($settings['bank_account'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Atas Nama Rekening</label>
                                <input type="text" name="bank_holder" class="form-input"
                                       placeholder="Nama pemilik rekening"
                                       value="<?= htmlspecialchars($settings['bank_holder'] ?? '') ?>">
                            </div>
                        </div>

                        <div style="display:flex; gap:1rem; justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- TAB: EMAIL -->
                <div class="tab-pane" id="email">
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="tab" value="email">

                        <div class="setting-card">
                            <div class="setting-card-title">Pengaturan Email SMTP</div>
                            <div class="setting-card-desc">Konfigurasi SMTP untuk mengirim email notifikasi ke customer</div>

                            <div class="form-group">
                                <label class="form-label">SMTP Host <span style="color:var(--error);">*</span></label>
                                <input type="text" name="smtp_host" class="form-input"
                                       placeholder="cth: smtp.gmail.com"
                                       value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">SMTP Port <span style="color:var(--error);">*</span></label>
                                    <input type="text" name="smtp_port" class="form-input"
                                           placeholder="cth: 587"
                                           value="<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Email SMTP <span style="color:var(--error);">*</span></label>
                                    <input type="email" name="smtp_email" class="form-input"
                                           placeholder="your-email@gmail.com"
                                           value="<?= htmlspecialchars($settings['smtp_email'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">SMTP Password</label>
                                <input type="password" name="smtp_password" class="form-input"
                                       placeholder="Password atau App Password">
                                <small style="color:var(--gray-500); margin-top:.5rem; display:block;">
                                    ⓘ Untuk Gmail, gunakan App Password (bukan password akun biasa). Lihat: https://myaccount.google.com/apppasswords
                                </small>
                            </div>
                        </div>

                        <div style="display:flex; gap:1rem; justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- TAB: SECURITY -->
                <div class="tab-pane" id="security">
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="tab" value="security">
                        <input type="hidden" name="action" value="">

                        <div class="setting-card">
                            <div class="setting-card-title">Backup Database</div>
                            <div class="setting-card-desc">Download backup database website Anda untuk keamanan data</div>

                            <p style="color:var(--gray-600); margin-bottom:1rem; line-height:1.6;">
                                Backup database bisa dilakukan melalui <strong>phpMyAdmin</strong>. 
                                Akses phpMyAdmin di server Anda dan export database <code style="background:var(--gray-100); padding:0.25rem 0.5rem; border-radius:4px;">literaspace</code>.
                            </p>

                            <button type="button" class="btn btn-secondary" onclick="alert('Fitur backup otomatis akan dikembangkan. Saat ini gunakan phpMyAdmin untuk backup database.')">
                                <i class="fas fa-download"></i> Info Backup
                            </button>
                        </div>

                        <div class="setting-card">
                            <div class="setting-card-title">Keamanan Admin</div>
                            <div class="setting-card-desc">Rekomendasi keamanan untuk akun administrator</div>

                            <ul style="list-style:none; padding:0; gap:1rem; display:flex; flex-direction:column;">
                                <li style="display:flex; gap:1rem; align-items:flex-start;">
                                    <i class="fas fa-check-circle" style="color:var(--success); flex-shrink:0; margin-top:0.25rem;"></i>
                                    <span>
                                        <strong>Gunakan Password Kuat</strong><br>
                                        <small style="color:var(--gray-500);">Kombinasi huruf besar, kecil, angka, dan simbol (minimal 12 karakter)</small>
                                    </span>
                                </li>
                                <li style="display:flex; gap:1rem; align-items:flex-start;">
                                    <i class="fas fa-check-circle" style="color:var(--success); flex-shrink:0; margin-top:0.25rem;"></i>
                                    <span>
                                        <strong>Ubah Password Secara Berkala</strong><br>
                                        <small style="color:var(--gray-500);">Rekomendasikan mengubah password setiap 3 bulan</small>
                                    </span>
                                </li>
                                <li style="display:flex; gap:1rem; align-items:flex-start;">
                                    <i class="fas fa-check-circle" style="color:var(--success); flex-shrink:0; margin-top:0.25rem;"></i>
                                    <span>
                                        <strong>Jaga Kerahasiaan Akun</strong><br>
                                        <small style="color:var(--gray-500);">Jangan bagikan akun dengan orang lain</small>
                                    </span>
                                </li>
                                <li style="display:flex; gap:1rem; align-items:flex-start;">
                                    <i class="fas fa-check-circle" style="color:var(--success); flex-shrink:0; margin-top:0.25rem;"></i>
                                    <span>
                                        <strong>Selalu Logout</strong><br>
                                        <small style="color:var(--gray-500);">Logout setelah selesai bekerja, terutama di perangkat publik</small>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div class="setting-card" style="background:rgba(255, 71, 87, 0.05); border-color:var(--error);">
                            <div class="setting-card-title" style="color:var(--error);">Ubah Password Admin</div>
                            <div class="setting-card-desc">Perbarui password akun administrator Anda</div>

                            <a href="../pages/password-update.php" class="btn btn-primary">
                                <i class="fas fa-lock"></i> Ubah Password Sekarang
                            </a>
                        </div>
                    </form>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
    function switchTab(e, tabName) {
        e.preventDefault();

        // Hide all panes
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

        // Show selected pane
        document.getElementById(tabName).classList.add('active');

        // Update buttons
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        e.target.closest('.tab-button').classList.add('active');
    }
</script>

</body>
</html>
