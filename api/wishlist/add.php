<?php
// api/wishlist/add.php — Menambahkan item ke wishlist

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu.'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_buku = (int)($data['id_buku'] ?? 0);

if ($id_buku <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Data tidak valid.'
    ]);
    exit;
}

try {
    $pdo = getDB();

    // Cek apakah buku ada
    $stmt_buku = $pdo->prepare("SELECT id_buku FROM buku WHERE id_buku = ?");
    $stmt_buku->execute([$id_buku]);
    $buku = $stmt_buku->fetch();

    if (!$buku) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Buku tidak ditemukan.'
        ]);
        exit;
    }

    // Cek apakah item sudah ada di wishlist
    $stmt_cek = $pdo->prepare("SELECT id_wishlist FROM wishlist WHERE id_user = ? AND id_buku = ?");
    $stmt_cek->execute([$user_id, $id_buku]);
    $existing = $stmt_cek->fetch();

    if ($existing) {
        // Hapus dari wishlist jika sudah ada (toggle)
        $stmt_delete = $pdo->prepare("DELETE FROM wishlist WHERE id_wishlist = ?");
        $stmt_delete->execute([$existing['id_wishlist']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Buku dihapus dari wishlist.',
            'action' => 'removed'
        ]);
    } else {
        // Insert item baru ke wishlist
        $stmt_insert = $pdo->prepare("INSERT INTO wishlist (id_user, id_buku) VALUES (?, ?)");
        $stmt_insert->execute([$user_id, $id_buku]);

        echo json_encode([
            'success' => true,
            'message' => 'Buku berhasil ditambahkan ke wishlist.',
            'action' => 'added'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server.'
    ]);
    error_log("Database error in add to wishlist: " . $e->getMessage());
}
?>
