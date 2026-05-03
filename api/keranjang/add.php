<?php
// api/keranjang/add.php — Menambahkan item ke keranjang

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
$qty = (int)($data['qty'] ?? 1);

if ($id_buku <= 0 || $qty <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Data tidak valid.'
    ]);
    exit;
}

try {
    $pdo = getDB();

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
        // Update quantity jika sudah ada
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
        // Insert item baru ke keranjang
        $stmt_insert = $pdo->prepare("INSERT INTO keranjang (id_user, id_buku, qty) VALUES (?, ?, ?)");
        $stmt_insert->execute([$user_id, $id_buku, $qty]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Buku berhasil ditambahkan ke keranjang.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server.'
    ]);
    error_log("Database error in add to cart: " . $e->getMessage());
}
?>
