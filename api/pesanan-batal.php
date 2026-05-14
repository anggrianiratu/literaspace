<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

$response = ['success' => false, 'message' => 'Terjadi kesalahan'];

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesi habis, silakan login ulang']);
        exit;
    }

    $user_id    = (int) $_SESSION['user_id'];
    $input      = json_decode(file_get_contents('php://input'), true);
    $id_pesanan = (int) ($input['id_pesanan'] ?? 0);

    if (!$id_pesanan) throw new Exception('ID pesanan tidak valid');

    $pdo = getDB();

    // ✅ FIX: ganti 'diproses' → 'dikemas' sesuai ENUM di database
    // 'diproses' tidak ada di ENUM, jadi query lama selalu gagal
    $stmt = $pdo->prepare("
        SELECT id_pesanan FROM pesanan
        WHERE id_pesanan = ? AND id_user = ? AND status_pesanan = 'dikemas'
    ");
    $stmt->execute([$id_pesanan, $user_id]);
    if (!$stmt->fetch()) throw new Exception('Pesanan tidak ditemukan atau sudah tidak bisa dibatalkan');

    $pdo->beginTransaction();

    // Kembalikan stok buku
    $items = $pdo->prepare("SELECT id_buku, qty FROM detail_pesanan WHERE id_pesanan = ?");
    $items->execute([$id_pesanan]);
    $detail = $items->fetchAll(PDO::FETCH_ASSOC);

    $stokStmt = $pdo->prepare("UPDATE buku SET stok = stok + ? WHERE id_buku = ?");
    foreach ($detail as $item) {
        $stokStmt->execute([$item['qty'], $item['id_buku']]);
    }

    // Update status ke dibatalkan
    $update = $pdo->prepare("UPDATE pesanan SET status_pesanan = 'dibatalkan' WHERE id_pesanan = ?");
    $update->execute([$id_pesanan]);

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'Pesanan berhasil dibatalkan';

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = $e->getMessage();
    error_log('[pesanan-batal] ' . $e->getMessage());
}

echo json_encode($response);