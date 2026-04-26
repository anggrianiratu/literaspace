<?php
require_once '../config/session.php';

// pastikan session benar-benar aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// hapus semua session
$_SESSION = [];
session_unset();
session_destroy();

// hapus cookie session
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// hapus remember me
setcookie('remember_token', '', time() - 3600, '/');

// redirect ke login
header('Location: /literaspace/auth/login.php');
exit;