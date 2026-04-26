<?php
// pages/profile-update.php
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$user_id       = (int) $_SESSION['user_id'];
$pdo           = getDB();

$nama_depan    = trim($_POST['nama_depan']    ?? '');
$nama_belakang = trim($_POST['nama_belakang'] ?? '');
$email         = trim($_POST['email']         ?? '');
$telepon       = trim($_POST['telepon']       ?? '');
$alamat        = trim($_POST['alamat']        ?? '');

// Validasi wajib
if (empty($nama_depan) || empty($nama_belakang) || empty($email)) {
    header('Location: profile.php?error=Data+tidak+lengkap');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: profile.php?error=Format+email+tidak+valid');
    exit;
}

// Cek email duplikat (selain milik sendiri)
$cek = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$cek->execute([$email, $user_id]);
if ($cek->fetch()) {
    header('Location: profile.php?error=Email+sudah+digunakan+akun+lain');
    exit;
}

// Update ke database
$stmt = $pdo->prepare("UPDATE users SET nama_depan=?, nama_belakang=?, email=?, telepon=?, alamat=?, updated_at=NOW() WHERE id=?");
$stmt->execute([$nama_depan, $nama_belakang, $email, $telepon, $alamat, $user_id]);

// Update session nama
$_SESSION['user_nama'] = $nama_depan . ' ' . $nama_belakang;

header('Location: profile.php?updated=1');
exit;