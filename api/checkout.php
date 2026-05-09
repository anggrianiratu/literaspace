<?php
// api/checkout.php - Handle order checkout & Midtrans Snap
header('Content-Type: application/json');
// Tampilkan error saat development agar mudah di-debug
error_reporting(E_ALL);
ini_set('display_errors', 1); 

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/http-client.php';

$response = ['success' => false, 'message' => 'Terjadi kesalahan'];

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu']);
        exit;
    }

    $pdo = getDB();
    $user_id = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $alamat  = trim($data['alamat'] ?? '');
        $telepon = trim($data['telepon'] ?? '');
        $kurir   = trim($data['kurir'] ?? '');
        $metode_pembayaran = 'midtrans'; 

        if (empty($alamat) || empty($kurir)) {
            echo json_encode(['success' => false, 'message' => 'Data pengiriman tidak lengkap']);
            exit;
        }

        $stmtUser = $pdo->prepare('SELECT nama_depan, nama_belakang, email FROM users WHERE id = ?');
        $stmtUser->execute([$user_id]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $stmtCart = $pdo->prepare('
            SELECT k.id_buku, k.qty, b.harga, b.stok, b.judul 
            FROM keranjang k 
            JOIN buku b ON k.id_buku = b.id_buku 
            WHERE k.id_user = ?
        ');
        $stmtCart->execute([$user_id]);
        $cartItems = $stmtCart->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cartItems)) {
            echo json_encode(['success' => false, 'message' => 'Keranjang Anda kosong']);
            exit;
        }

        $ongkir = ($kurir === 'express') ? 25000 : 15000;
        $total_harga_buku = 0;
        $detail_items = [];

        foreach ($cartItems as $item) {
            if ($item['stok'] < $item['qty']) {
                echo json_encode(['success' => false, 'message' => "Stok buku '{$item['judul']}' tidak mencukupi."]);
                exit;
            }
            $total_harga_buku += ($item['harga'] * $item['qty']);
            $detail_items[] = $item;
        }

        $total_keseluruhan = $total_harga_buku + $ongkir;

        // ===================================================================
        // PENTING: JANGAN SAVE PESANAN KE DATABASE DI SINI!
        // Hanya validate & prepare data untuk Midtrans
        // Pesanan akan disave SETELAH pembayaran berhasil via finalize-order.php
        // ===================================================================

        // Prepare order data untuk dikirim ke frontend (nanti dikirim kembali saat onSuccess)
        $temp_order_data = [
            'alamat' => $alamat,
            'kurir' => $kurir,
            'total_harga' => $total_keseluruhan
        ];

        // Load environment variables dari .env file
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    putenv(trim($key) . '=' . trim($value));
                }
            }
        }

        // Server Key Midtrans dari environment variable
        $serverKey = getenv('MIDTRANS_SERVER_KEY');

        $item_details_midtrans = [];
        foreach ($detail_items as $detail) {
            $item_details_midtrans[] = [
                'id'       => (string)$detail['id_buku'],
                'price'    => (int)$detail['harga'],
                'quantity' => (int)$detail['qty'],
                'name'     => substr($detail['judul'], 0, 50)
            ];
        }
        $item_details_midtrans[] = [
            'id'       => 'ONGKIR',
            'price'    => (int)$ongkir,
            'quantity' => 1,
            'name'     => 'Ongkos Kirim (' . strtoupper($kurir) . ')'
        ];

        $transaction_data = [
            'transaction_details' => [
                'order_id'     => 'LS-' . $user_id . '-' . time(),  // Gunakan user_id, bukan id_pesanan
                'gross_amount' => (int)$total_keseluruhan
            ],
            'customer_details' => [
                'first_name' => $user['nama_depan'],
                'last_name'  => $user['nama_belakang'],
                'email'      => $user['email'],
                'phone'      => $telepon
            ],
            'item_details' => $item_details_midtrans
        ];

        // Request ke Midtrans API (gunakan cURL atau Streams)
        $auth_header = 'Basic ' . base64_encode($serverKey . ':');
        
        error_log("=== MIDTRANS REQUEST DEBUG ===");
        error_log("URL: https://app.sandbox.midtrans.com/snap/v1/transactions");
        error_log("Auth Header: " . substr($auth_header, 0, 40) . "...");
        error_log("Request Data: " . json_encode($transaction_data));
        
        $result = HttpClient::post(
            'https://app.sandbox.midtrans.com/snap/v1/transactions',
            $transaction_data,
            [
                'Authorization' => $auth_header,
                'Accept' => 'application/json'
            ]
        );
        
        $midtrans_response = $result['body'];
        $http_code = $result['http_code'];
        
        // Debug: Log response untuk troubleshooting
        error_log("Midtrans HTTP Code: $http_code");
        error_log("Midtrans Response: " . substr($midtrans_response, 0, 1000));
        
        // Cek HTTP status code
        if ($http_code !== 201) {
            throw new Exception("Midtrans API gagal (HTTP $http_code). Cek Server Key Anda di dashboard Midtrans.");
        }
        
        $midtrans_result = json_decode($midtrans_response, true);
        
        // Validasi response dari Midtrans
        if (!$midtrans_result) {
            throw new Exception('Response dari Midtrans tidak valid. Hubungi support.');
        }
        
        // Cek apakah Midtrans menolak Server Key kita atau ada error
        if (isset($midtrans_result['error_messages'])) {
            throw new Exception('Midtrans Error: ' . implode(", ", $midtrans_result['error_messages']));
        }
        
        // Cek apakah ada token dalam response
        if (!isset($midtrans_result['token'])) {
            throw new Exception('Midtrans tidak mengirimkan token. Response: ' . json_encode($midtrans_result));
        }

        $response['success']    = true;
        $response['message']    = 'Siap untuk pembayaran!';
        $response['snap_token'] = $midtrans_result['token'] ?? null;
        $response['order_data'] = $temp_order_data;  // Kirim order data ke frontend
    }
} catch (Throwable $e) {
    // Balikkan pesan error asli ke layar agar kita tau penyebabnya
    $response['message'] = 'Sistem Error: ' . $e->getMessage();
}

echo json_encode($response);