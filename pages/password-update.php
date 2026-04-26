<?php
// pages/password-update.php
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

requireLogin();

$user_id          = (int) $_SESSION['user_id'];
$pdo              = getDB();

$password_lama    = $_POST['password_lama']       ?? '';
$password_baru    = $_POST['password_baru']       ?? '';
$konfirmasi       = $_POST['konfirmasi_password'] ?? '';

// Validasi
if (empty($password_lama) || empty($password_baru) || empty($konfirmasi)) {
    header('Location: profile.php?error=Semua+field+password+wajib+diisi');
    exit;
}

if (strlen($password_baru) < 8) {
    header('Location: profile.php?error=Password+baru+minimal+8+karakter');
    exit;
}

if ($password_baru !== $konfirmasi) {
    header('Location: profile.php?error=Konfirmasi+password+tidak+cocok');
    exit;
}

// Ambil hash password saat ini
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !password_verify($password_lama, $user['password'])) {
    header('Location: profile.php?error=Password+lama+salah');
    exit;
}

// Simpan password baru
$hash = password_hash($password_baru, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?");
$stmt->execute([$hash, $user_id]);

header('Location: profile.php?pwupdated=1');
exit;