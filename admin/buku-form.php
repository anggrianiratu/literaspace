<?php
// admin/buku-form.php - Form Tambah/Edit Buku
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDB();
$admin_id = (int) $_SESSION['user_id'];

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

// Initialize variables
$edit_mode = false;
$book = null;
$errors = [];
$success_message = '';
$cover_image = 'default.jpg';

// Check if edit mode
$edit_id = (int) ($_GET['edit'] ?? 0);
if ($edit_id > 0) {
    $edit_mode = true;
    $stmt = $pdo->prepare("SELECT * FROM buku WHERE id_buku = ?");
    $stmt->execute([$edit_id]);
    $book = $stmt->fetch();
    
    if (!$book) {
        header('Location: buku.php');
        exit;
    }
}

// Get categories
$stmt = $pdo->query("SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_buku = $edit_mode ? $edit_id : null;
    $id_kategori = (int) ($_POST['id_kategori'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $penulis = trim($_POST['penulis'] ?? '');
    $penerbit = trim($_POST['penerbit'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $halaman = (int) ($_POST['halaman'] ?? 0);
    $harga = (int) ($_POST['harga'] ?? 0);
    $stok = (int) ($_POST['stok'] ?? 0);
    $sinopsis = trim($_POST['sinopsis'] ?? '');
    $cover_image = $edit_mode && $book ? $book['cover_image'] : 'default.jpg';

    // Handle file upload
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['cover']['tmp_name'];
            $file_name = $_FILES['cover']['name'];
            $file_size = $_FILES['cover']['size'];
            $file_type = mime_content_type($file_tmp);

            // Validate file
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Format file harus JPG, PNG, GIF, atau WebP";
            } elseif ($file_size > $max_size) {
                $errors[] = "Ukuran file maksimal 2MB";
            } else {
                // Generate unique filename
                $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_name = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $upload_dir = '../assets/covers/';
                $upload_path = $upload_dir . $unique_name;

                // Delete old cover if exists (and not default)
                if ($edit_mode && $book['cover_image'] && $book['cover_image'] !== 'default.jpg') {
                    $old_file = $upload_dir . $book['cover_image'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }

                // Upload file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $cover_image = $unique_name;
                } else {
                    $errors[] = "Gagal mengunggah file cover";
                }
            }
        } else {
            $errors[] = "Error mengupload file: " . $_FILES['cover']['error'];
        }
    }

    // Validation
    if (empty($judul)) {
        $errors[] = "Judul buku harus diisi";
    }
    if ($harga < 0) {
        $errors[] = "Harga tidak boleh negatif";
    }
    if ($stok < 0) {
        $errors[] = "Stok tidak boleh negatif";
    }
    if (!$edit_mode && empty($isbn)) {
        $errors[] = "ISBN harus diisi untuk buku baru";
    }

    // Check duplicate ISBN for new books or when ISBN changed
    if (!empty($isbn)) {
        $check_sql = "SELECT id_buku FROM buku WHERE isbn = ?";
        $params = [$isbn];
        if ($edit_mode) {
            $check_sql .= " AND id_buku != ?";
            $params[] = $edit_id;
        }
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = "ISBN sudah digunakan oleh buku lain";
        }
    }

    // If no errors, save to database
    if (empty($errors)) {
        try {
            if ($edit_mode) {
                // Update existing book
                $sql = "
                    UPDATE buku 
                    SET id_kategori = ?, judul = ?, penulis = ?, penerbit = ?, 
                        isbn = ?, halaman = ?, harga = ?, stok = ?, sinopsis = ?, cover_image = ?
                    WHERE id_buku = ?
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $id_kategori ?: null,
                    $judul,
                    $penulis,
                    $penerbit,
                    $isbn,
                    $halaman ?: null,
                    $harga,
                    $stok,
                    $sinopsis,
                    $cover_image,
                    $edit_id
                ]);
                $success_message = "Buku berhasil diperbarui!";
            } else {
                // Insert new book
                $sql = "
                    INSERT INTO buku (id_kategori, judul, penulis, penerbit, isbn, halaman, harga, stok, sinopsis, cover_image)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $id_kategori ?: null,
                    $judul,
                    $penulis,
                    $penerbit,
                    $isbn,
                    $halaman ?: null,
                    $harga,
                    $stok,
                    $sinopsis,
                    $cover_image
                ]);
                $success_message = "Buku berhasil ditambahkan!";
                
                // Reset form
                $id_kategori = 0;
                $judul = '';
                $penulis = '';
                $penerbit = '';
                $isbn = '';
                $halaman = 0;
                $harga = 0;
                $stok = 0;
                $sinopsis = '';
                $cover_image = 'default.jpg';
            }
        } catch (Exception $e) {
            $errors[] = "Kesalahan database: " . $e->getMessage();
        }
    }
} else if ($edit_mode && $book) {
    // Load existing data
    $id_kategori = $book['id_kategori'];
    $judul = $book['judul'];
    $penulis = $book['penulis'];
    $penerbit = $book['penerbit'];
    $isbn = $book['isbn'];
    $halaman = $book['halaman'];
    $harga = $book['harga'];
    $stok = $book['stok'];
    $sinopsis = $book['sinopsis'];
    $cover_image = $book['cover_image'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $edit_mode ? 'Edit' : 'Tambah' ?> Buku — Admin LiteraSpace</title>
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

        .alert ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .alert li {
            margin: .25rem 0;
        }

        /* ── CARD ── */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            max-width: 700px;
        }

        /* ── FORM ── */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: .95rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: .5rem;
        }

        .form-label .required {
            color: var(--error);
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: .85rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            font-family: inherit;
            font-size: .95rem;
            transition: border-color .2s;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--indigo-light);
            background: rgba(59, 46, 192, 0.02);
            box-shadow: 0 0 0 3px rgba(59, 46, 192, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* ── FORM FOOTER ── */
        .form-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                width: 260px;
            }

            .main-content {
                margin-left: 0;
            }

            .page-content {
                padding: 1rem;
            }

            .card {
                max-width: 100%;
            }

            .page-title {
                font-size: 1.5rem;
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
            <li><a href="#"><i class="fas fa-folder"></i> Kategori</a></li>
            <li><a href="#"><i class="fas fa-shopping-bag"></i> Pesanan</a></li>
            <li><a href="user.php"><i class="fas fa-user"></i> User</a></li>
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
                <div class="nav-title"><?= $edit_mode ? 'Edit Buku' : 'Tambah Buku Baru' ?></div>
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
                    <h1 class="page-title"><?= $edit_mode ? 'Edit Buku' : 'Tambah Buku Baru' ?></h1>
                    <p class="page-subtitle">Lengkapi informasi buku dengan detail yang akurat</p>
                </div>
                <a href="buku.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>

            <!-- ALERTS -->
            <?php if ($success_message) { ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php } ?>
            <?php if (!empty($errors)) { ?>
                <div class="alert alert-error">
                    <div style="display: flex; gap: .75rem;">
                        <i class="fas fa-exclamation-circle" style="flex-shrink: 0;"></i>
                        <ul style="margin: 0;">
                            <?php foreach ($errors as $error) { ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>

            <!-- FORM CARD -->
            <div class="card">
                <form method="post" enctype="multipart/form-data">
                    <!-- Judul & Kategori -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Judul Buku <span class="required">*</span></label>
                            <input type="text" name="judul" class="form-input" required value="<?= htmlspecialchars($judul ?? '') ?>" placeholder="Masukkan judul buku">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select name="id_kategori" class="form-select">
                                <option value="0">Pilih Kategori</option>
                                <?php foreach ($categories as $cat) { ?>
                                    <option value="<?= $cat['id_kategori'] ?>" <?= ($id_kategori ?? 0) === $cat['id_kategori'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nama_kategori']) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <!-- Penulis & Penerbit -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Penulis</label>
                            <input type="text" name="penulis" class="form-input" value="<?= htmlspecialchars($penulis ?? '') ?>" placeholder="Nama penulis">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Penerbit</label>
                            <input type="text" name="penerbit" class="form-input" value="<?= htmlspecialchars($penerbit ?? '') ?>" placeholder="Nama penerbit">
                        </div>
                    </div>

                    <!-- ISBN & Halaman -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">ISBN <?= !$edit_mode ? '<span class="required">*</span>' : '' ?></label>
                            <input type="text" name="isbn" class="form-input" <?= !$edit_mode ? 'required' : '' ?> value="<?= htmlspecialchars($isbn ?? '') ?>" placeholder="ISBN-10 atau ISBN-13">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Jumlah Halaman</label>
                            <input type="number" name="halaman" class="form-input" min="0" value="<?= $halaman ?? 0 ?>" placeholder="Contoh: 300">
                        </div>
                    </div>

                    <!-- Harga & Stok -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Harga <span class="required">*</span></label>
                            <input type="number" name="harga" class="form-input" required min="0" value="<?= $harga ?? 0 ?>" placeholder="Harga dalam Rupiah">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Stok <span class="required">*</span></label>
                            <input type="number" name="stok" class="form-input" required min="0" value="<?= $stok ?? 0 ?>" placeholder="Jumlah stok">
                        </div>
                    </div>

                    <!-- Sinopsis -->
                    <div class="form-group">
                        <label class="form-label">Sinopsis</label>
                        <textarea name="sinopsis" class="form-textarea" placeholder="Deskripsi singkat tentang buku..."><?= htmlspecialchars($sinopsis ?? '') ?></textarea>
                    </div>

                    <!-- Cover Buku -->
                    <div class="form-group">
                        <label class="form-label">Cover Buku</label>
                        <div style="display: grid; grid-template-columns: 1fr 200px; gap: 1.5rem; align-items: start;">
                            <div>
                                <input type="file" id="cover" name="cover" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp" style="padding: 0.5rem; cursor: pointer;">
                                <p style="font-size: 0.85rem; color: var(--gray-500); margin-top: 0.5rem;">
                                    <i class="fas fa-info-circle"></i> Format: JPG, PNG, GIF, WebP | Maksimal: 2MB
                                </p>
                            </div>
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 0.75rem;">
                                <div style="width: 160px; height: 240px; background: var(--gray-100); border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 2px dashed var(--gray-300); overflow: hidden;">
                                    <img id="coverPreview" src="<?= $cover_image && $cover_image !== 'default.jpg' ? '../assets/covers/' . htmlspecialchars($cover_image) : 'https://via.placeholder.com/160x240?text=No+Cover' ?>" alt="Cover Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <p style="font-size: 0.8rem; color: var(--gray-500); text-align: center;">Preview Cover</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Footer -->
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $edit_mode ? 'Perbarui' : 'Simpan' ?> Buku
                        </button>
                        <a href="buku.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Handle cover image preview
        const coverInput = document.getElementById('cover');
        const coverPreview = document.getElementById('coverPreview');

        if (coverInput) {
            coverInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        coverPreview.src = event.target.result;
                        coverPreview.style.objectFit = 'cover';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>
