<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true);
$action = $body['action'] ?? '';

try {
    $pdo = getDB();

    if ($action === 'hapus_item') {
        $id_buku = isset($body['id_buku']) ? (int)$body['id_buku'] : 0;

        if ($id_buku <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID buku tidak valid.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_user = ? AND id_buku = ?");
        $stmt->execute([$user_id, $id_buku]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Item berhasil dihapus.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan di keranjang.']);
        }

    } elseif ($action === 'hapus_semua') {
        $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_user = ?");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => 'Semua item berhasil dihapus.']);

    } else {
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}