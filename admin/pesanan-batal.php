<?php
// admin/pesanan-batal.php — Batalkan pesanan dari sisi admin
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

$response = ['success' => false, 'message' => 'Terjadi kesalahan'];

try {
    // Pastikan yang akses adalah admin yang sudah login
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesi habis, silakan login ulang']);
        exit;
    }

    $pdo = getDB();
    $admin_id = (int) $_SESSION['user_id'];

    // Verifikasi role admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    // Ambil input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $id_pesanan = (int) ($input['id_pesanan'] ?? 0);
    $alasan = trim($input['alasan'] ?? '');

    if (!$id_pesanan) throw new Exception('ID pesanan tidak valid');

    // Cek pesanan ada dan statusnya bisa dibatalkan
    // Admin bisa batalkan semua status KECUALI yang sudah dibatalkan atau selesai
    $stmt = $pdo->prepare("
        SELECT p.id_pesanan, p.status_pesanan, u.nama_depan, u.nama_belakang
        FROM pesanan p
        JOIN users u ON p.id_user = u.id
        WHERE p.id_pesanan = ?
    ");
    $stmt->execute([$id_pesanan]);
    $pesanan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pesanan) throw new Exception('Pesanan tidak ditemukan');

    if ($pesanan['status_pesanan'] === 'dibatalkan') {
        throw new Exception('Pesanan ini sudah dibatalkan sebelumnya');
    }

    if ($pesanan['status_pesanan'] === 'selesai') {
        throw new Exception('Pesanan yang sudah selesai tidak dapat dibatalkan');
    }

    $pdo->beginTransaction();

    // Kembalikan stok buku untuk setiap item di pesanan ini
    $items = $pdo->prepare("SELECT id_buku, qty FROM detail_pesanan WHERE id_pesanan = ?");
    $items->execute([$id_pesanan]);
    $detail = $items->fetchAll(PDO::FETCH_ASSOC);

    $stokStmt = $pdo->prepare("UPDATE buku SET stok = stok + ? WHERE id_buku = ?");
    foreach ($detail as $item) {
        $stokStmt->execute([$item['qty'], $item['id_buku']]);
    }

    // Update status pesanan ke dibatalkan
    // Simpan juga alasan pembatalan jika kolom tersedia (opsional)
    $update = $pdo->prepare("UPDATE pesanan SET status_pesanan = 'dibatalkan' WHERE id_pesanan = ?");
    $update->execute([$id_pesanan]);

    $pdo->commit();

    $nama_customer = $pesanan['nama_depan'] . ' ' . $pesanan['nama_belakang'];
    $response['success'] = true;
    $response['message'] = "Pesanan #$id_pesanan atas nama $nama_customer berhasil dibatalkan";

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = $e->getMessage();
    error_log('[admin/pesanan-batal] ' . $e->getMessage());
}

echo json_encode($response);