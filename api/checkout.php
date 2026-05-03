<?php
// api/checkout.php - Handle order checkout
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

$response = ['success' => false, 'message' => 'Terjadi kesalahan'];

try {
    // Check if user logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        $response['message'] = 'Anda harus login terlebih dahulu';
        echo json_encode($response);
        exit;
    }

    $pdo = getDB();
    $user_id = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        // Parse input
        $items = $data['items'] ?? [];  // Array of {id_buku, qty}
        $alamat = trim($data['alamat'] ?? '');
        $kurir = trim($data['kurir'] ?? '');
        $metode_pembayaran = trim($data['metode_pembayaran'] ?? '');

        // Validation
        if (empty($items) || !is_array($items)) {
            $response['message'] = 'Pilih minimal 1 item';
            echo json_encode($response);
            exit;
        }

        if (empty($alamat)) {
            $response['message'] = 'Alamat pengiriman tidak boleh kosong';
            echo json_encode($response);
            exit;
        }

        if (strlen($alamat) < 10) {
            $response['message'] = 'Alamat terlalu pendek (minimal 10 karakter)';
            echo json_encode($response);
            exit;
        }

        if (empty($kurir)) {
            $response['message'] = 'Pilih kurir pengiriman';
            echo json_encode($response);
            exit;
        }

        if (empty($metode_pembayaran)) {
            $response['message'] = 'Pilih metode pembayaran';
            echo json_encode($response);
            exit;
        }

        // Validate kurir & metode
        $valid_kurir = ['jne', 'tiki', 'pos'];
        $valid_metode = ['transfer', 'ewallet', 'cod'];

        if (!in_array(strtolower($kurir), $valid_kurir)) {
            $response['message'] = 'Kurir tidak valid';
            echo json_encode($response);
            exit;
        }

        if (!in_array(strtolower($metode_pembayaran), $valid_metode)) {
            $response['message'] = 'Metode pembayaran tidak valid';
            echo json_encode($response);
            exit;
        }

        // Start transaction
        $pdo->beginTransaction();

        try {
            $total_harga = 0;
            $detail_items = [];

            // Validate and collect items
            foreach ($items as $item) {
                $id_buku = (int)($item['id_buku'] ?? 0);
                $qty = (int)($item['qty'] ?? 0);

                if ($id_buku <= 0 || $qty <= 0) {
                    throw new Exception('Item tidak valid');
                }

                // Get book info
                $stmt = $pdo->prepare('SELECT harga, stok FROM buku WHERE id_buku = ?');
                $stmt->execute([$id_buku]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$book) {
                    throw new Exception("Buku ID $id_buku tidak ditemukan");
                }

                if ($book['stok'] < $qty) {
                    throw new Exception("Stok buku tidak mencukupi (sisa {$book['stok']})");
                }

                $harga_satuan = $book['harga'];
                $total_harga += $harga_satuan * $qty;

                $detail_items[] = [
                    'id_buku' => $id_buku,
                    'qty' => $qty,
                    'harga_satuan' => $harga_satuan
                ];
            }

            // Create pesanan
            $stmt = $pdo->prepare('
                INSERT INTO pesanan (id_user, total_harga, alamat_pengiriman, kurir, metode_pembayaran, status_pesanan)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $user_id,
                $total_harga,
                $alamat,
                $kurir,
                $metode_pembayaran,
                'diproses'
            ]);

            $id_pesanan = $pdo->lastInsertId();

            // Create detail pesanan & update stok
            $stmt_detail = $pdo->prepare('
                INSERT INTO detail_pesanan (id_pesanan, id_buku, qty, harga_saat_beli)
                VALUES (?, ?, ?, ?)
            ');

            $stmt_update_stok = $pdo->prepare('
                UPDATE buku SET stok = stok - ? WHERE id_buku = ?
            ');

            foreach ($detail_items as $detail) {
                $stmt_detail->execute([
                    $id_pesanan,
                    $detail['id_buku'],
                    $detail['qty'],
                    $detail['harga_satuan']
                ]);

                $stmt_update_stok->execute([
                    $detail['qty'],
                    $detail['id_buku']
                ]);
            }

            // Delete items from keranjang
            $stmt_delete_cart = $pdo->prepare('
                DELETE FROM keranjang 
                WHERE id_user = ? AND id_buku IN (' . implode(',', array_map(fn($i) => '?', $detail_items)) . ')
            ');
            
            $delete_params = [$user_id];
            foreach ($detail_items as $item) {
                $delete_params[] = $item['id_buku'];
            }
            $stmt_delete_cart->execute($delete_params);

            $pdo->commit();

            $response['success'] = true;
            $response['message'] = 'Pesanan berhasil dibuat!';
            $response['id_pesanan'] = $id_pesanan;
            $response['total_harga'] = $total_harga;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } else {
        http_response_code(405);
        $response['message'] = 'Method tidak diizinkan';
    }

} catch (Exception $e) {
    error_log('Checkout error: ' . $e->getMessage());
    $response['message'] = $e->getMessage() ?: 'Terjadi kesalahan saat membuat pesanan';
}

echo json_encode($response);
