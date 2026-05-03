<?php
// api/wishlist.php — Handler untuk operasi wishlist (ADD/DELETE)

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

if (!$user_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu.'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = getDB();

    if ($method === 'POST') {
        // Tambah item ke wishlist
        $id_buku = (int)($data['id_buku'] ?? 0);

        if ($id_buku <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Data tidak valid.'
            ]);
            exit;
        }

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
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Buku sudah ada di wishlist.',
                'action' => 'exists'
            ]);
            exit;
        }

        // Insert item baru ke wishlist
        $stmt_insert = $pdo->prepare("INSERT INTO wishlist (id_user, id_buku) VALUES (?, ?)");
        $stmt_insert->execute([$user_id, $id_buku]);

        echo json_encode([
            'success' => true,
            'message' => 'Buku berhasil ditambahkan ke wishlist.',
            'action' => 'added'
        ]);

    } elseif ($method === 'DELETE') {
        // Hapus item dari wishlist
        $id_buku = (int)($data['id_buku'] ?? 0);

        if ($id_buku <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Data tidak valid.'
            ]);
            exit;
        }

        $stmt_delete = $pdo->prepare("DELETE FROM wishlist WHERE id_user = ? AND id_buku = ?");
        $stmt_delete->execute([$user_id, $id_buku]);

        if ($stmt_delete->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Item tidak ditemukan di wishlist.'
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Buku berhasil dihapus dari wishlist.',
            'action' => 'removed'
        ]);

    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method tidak didukung.'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server.'
    ]);
    error_log("Database error in wishlist API: " . $e->getMessage());
}
?>
