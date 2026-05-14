<?php
// ✅ BENAR: pakai config/session.php yang sudah ada, jangan set cookie_path manual
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Terjadi kesalahan'];

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesi habis, silakan login ulang']);
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $id_pesanan = (int) ($input['id_pesanan'] ?? 0);

    if (!$id_pesanan) {
        throw new Exception('ID pesanan tidak valid');
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT status_pesanan 
        FROM pesanan 
        WHERE id_pesanan = ? AND id_user = ?
        LIMIT 1
    ");
    $stmt->execute([$id_pesanan, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan');
    }

    if ($order['status_pesanan'] === 'selesai') {
        throw new Exception('Pesanan sudah selesai');
    }

    if ($order['status_pesanan'] !== 'dikirim') {
        throw new Exception('Pesanan belum dikirim');
    }

    $update = $pdo->prepare("
        UPDATE pesanan 
        SET status_pesanan = 'selesai'
        WHERE id_pesanan = ? 
        AND id_user = ?
        AND status_pesanan = 'dikirim'
    ");
    $update->execute([$id_pesanan, $user_id]);

    if ($update->rowCount() === 0) {
        throw new Exception('Gagal update status (data berubah)');
    }

    $response['success'] = true;
    $response['message'] = 'Pesanan berhasil dikonfirmasi';

} catch (Throwable $e) {
    $response['message'] = $e->getMessage();
    error_log('[pesanan-konfirmasi] ' . $e->getMessage());
}

echo json_encode($response);