<?php
// api/keranjang.php — Handler untuk operasi keranjang (ADD/UPDATE/DELETE)

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
        // Tambah atau update item ke keranjang
        $id_buku = (int)($data['id_buku'] ?? 0);
        $qty = (int)($data['qty'] ?? 1);

        if ($id_buku <= 0 || $qty <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Data tidak valid.'
            ]);
            exit;
        }

        // Cek stok buku
        $stmt_buku = $pdo->prepare("SELECT stok FROM buku WHERE id_buku = ?");
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

        if ($buku['stok'] < 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Stok buku tidak tersedia.'
            ]);
            exit;
        }

        // Cek apakah item sudah ada di keranjang
        $stmt_cek = $pdo->prepare("SELECT id_keranjang, qty FROM keranjang WHERE id_user = ? AND id_buku = ?");
        $stmt_cek->execute([$user_id, $id_buku]);
        $existing = $stmt_cek->fetch();

        if ($existing) {
            // Update quantity
            $new_qty = $existing['qty'] + $qty;
            
            if ($new_qty > $buku['stok']) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Jumlah melebihi stok yang tersedia.'
                ]);
                exit;
            }

            $stmt_update = $pdo->prepare("UPDATE keranjang SET qty = ? WHERE id_keranjang = ?");
            $stmt_update->execute([$new_qty, $existing['id_keranjang']]);
        } else {
            // Insert item baru
            $stmt_insert = $pdo->prepare("INSERT INTO keranjang (id_user, id_buku, qty) VALUES (?, ?, ?)");
            $stmt_insert->execute([$user_id, $id_buku, $qty]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Buku berhasil ditambahkan ke keranjang.'
        ]);

    } elseif ($method === 'PUT') {
        // Update quantity item di keranjang
        $id_keranjang = (int)($data['id_keranjang'] ?? 0);
        $qty = (int)($data['qty'] ?? 1);

        if ($id_keranjang <= 0 || $qty <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Data tidak valid.'
            ]);
            exit;
        }

        $stmt_update = $pdo->prepare("UPDATE keranjang SET qty = ? WHERE id_keranjang = ? AND id_user = ?");
        $stmt_update->execute([$qty, $id_keranjang, $user_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Keranjang berhasil diperbarui.'
        ]);

    } elseif ($method === 'DELETE') {
        // Hapus item dari keranjang
        $id_keranjang = (int)($data['id_keranjang'] ?? 0);

        if ($id_keranjang <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Data tidak valid.'
            ]);
            exit;
        }

        $stmt_delete = $pdo->prepare("DELETE FROM keranjang WHERE id_keranjang = ? AND id_user = ?");
        $stmt_delete->execute([$id_keranjang, $user_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Item berhasil dihapus dari keranjang.'
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
    error_log("Database error in keranjang API: " . $e->getMessage());
}
?>
