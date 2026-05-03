<?php
// admin/pesanan-api.php - API untuk Pesanan
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();
$admin_id = (int) $_SESSION['user_id'];

// Verify admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'detail') {
    $order_id = (int) $_GET['id'];

    $stmt = $pdo->prepare("
        SELECT p.id_pesanan, p.total_harga, p.status_pesanan, p.tanggal_pesan,
               u.nama_depan, u.nama_belakang, u.email
        FROM pesanan p
        JOIN users u ON p.id_user = u.id
        WHERE p.id_pesanan = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT dp.qty, dp.harga_saat_beli, b.judul
        FROM detail_pesanan dp
        JOIN buku b ON dp.id_buku = b.id_buku
        WHERE dp.id_pesanan = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'id_pesanan' => $order['id_pesanan'],
        'nama_depan' => $order['nama_depan'],
        'nama_belakang' => $order['nama_belakang'],
        'email' => $order['email'],
        'total_harga' => (int) $order['total_harga'],
        'status_pesanan' => $order['status_pesanan'],
        'tanggal_pesan' => $order['tanggal_pesan'],
        'items' => array_map(function($item) {
            return [
                'judul' => $item['judul'],
                'qty' => (int) $item['qty'],
                'harga_saat_beli' => (int) $item['harga_saat_beli']
            ];
        }, $items)
    ]);
}
?>
