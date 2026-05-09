<?php
/**
 * File: api/finalize-order.php
 * Tujuan: Finalize order setelah pembayaran berhasil (callback dari Midtrans)
 * 
 * Midtrans akan mengirim POST request ke URL ini setelah customer menyelesaikan pembayaran
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

$response = ['success' => false, 'message' => 'Terjadi kesalahan'];

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['order_id']) || !isset($data['transaction_status'])) {
        throw new Exception('Data tidak lengkap');
    }
    
    $order_id = $data['order_id'];
    $transaction_status = $data['transaction_status'];
    
    $pdo = getDB();
    
    // Ambil data pesanan berdasarkan order_id
    $stmt = $pdo->prepare('SELECT * FROM pesanan WHERE order_id = ?');
    $stmt->execute([$order_id]);
    $pesanan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pesanan) {
        throw new Exception('Pesanan tidak ditemukan');
    }
    
    // Update status pembayaran
    if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
        $status_pembayaran = 'PAID';
        $pesan = 'Pembayaran berhasil!';
    } elseif ($transaction_status === 'pending') {
        $status_pembayaran = 'PENDING';
        $pesan = 'Menunggu pembayaran...';
    } elseif ($transaction_status === 'deny' || $transaction_status === 'cancel' || $transaction_status === 'expire') {
        $status_pembayaran = 'FAILED';
        $pesan = 'Pembayaran dibatalkan';
    } else {
        $status_pembayaran = 'UNKNOWN';
        $pesan = 'Status tidak dikenal';
    }
    
    $stmt = $pdo->prepare('UPDATE pesanan SET status_pembayaran = ? WHERE id_pesanan = ?');
    $stmt->execute([$status_pembayaran, $pesanan['id_pesanan']]);
    
    $response['success'] = true;
    $response['message'] = $pesan;
    $response['status_pembayaran'] = $status_pembayaran;
    
} catch (Throwable $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
