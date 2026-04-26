<?php
// config/session.php — Konfigurasi & helper session

// Pengaturan keamanan session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// ini_set('session.cookie_secure', 1); // Aktifkan jika pakai HTTPS

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------
// Helper: cek apakah user sudah login
// -------------------------------------------------------
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

// -------------------------------------------------------
// Helper: wajib login — redirect ke login jika belum
// -------------------------------------------------------
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

// -------------------------------------------------------
// Helper: redirect jika sudah login (untuk halaman auth)
// -------------------------------------------------------
function redirectIfLoggedIn(): void {
    if (isLoggedIn()) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }
}

// -------------------------------------------------------
// Helper: set flash message (tampil sekali)
// -------------------------------------------------------
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// -------------------------------------------------------
// Helper: ambil & hapus flash message
// -------------------------------------------------------
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}