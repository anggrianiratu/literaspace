<?php
/**
 * File: api/finalize-order.php
 * Tujuan: Simpan pesanan ke DB setelah pembayaran berhasil (dipanggil dari frontend onSuccess)
 *
 * Dipanggil oleh JavaScript saat Midtrans Snap callback onSuccess / onPending,
 * bukan oleh Midtrans server (webhook).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

$response = ['success' => false, 'message' => 'Terjadi kesalahan'];

try {
    // -----------------------------------------------------------------------
    // AUTH CHECK
    // -----------------------------------------------------------------------
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesi habis, silakan login ulang']);
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];

    // -----------------------------------------------------------------------
    // PARSE INPUT
    // Frontend mengirim: { midtrans_result, order_data, selected_items }
    // -----------------------------------------------------------------------
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Data tidak valid');
    }

    $midtrans_result = $input['midtrans_result'] ?? [];   // hasil callback Snap
    $order_data      = $input['order_data']      ?? [];   // data dari checkout.php
    $selected_items  = $input['selected_items']  ?? [];   // [{id_buku, qty}]

    $order_id           = $midtrans_result['order_id']           ?? null;
    $transaction_status = $midtrans_result['transaction_status'] ?? null;

    if (!$order_id || !$transaction_status) {
        throw new Exception('Data Midtrans tidak lengkap');
    }

    if (empty($order_data['alamat']) || empty($order_data['kurir'])) {
        throw new Exception('Data pesanan tidak lengkap');
    }

    if (empty($selected_items)) {
        throw new Exception('Tidak ada item pesanan');
    }

    // -----------------------------------------------------------------------
    // TENTUKAN STATUS PEMBAYARAN
    // -----------------------------------------------------------------------
    if (in_array($transaction_status, ['settlement', 'capture'])) {
        $status_pembayaran = 'PAID';
    } elseif ($transaction_status === 'pending') {
        $status_pembayaran = 'PENDING';
    } elseif (in_array($transaction_status, ['deny', 'cancel', 'expire'])) {
        $status_pembayaran = 'FAILED';
    } else {
        $status_pembayaran = 'UNKNOWN';
    }

    $pdo = getDB();

    // -----------------------------------------------------------------------
    // CEK DUPLIKAT: jika order_id sudah ada, jangan insert ulang
    // -----------------------------------------------------------------------
    $stmtCek = $pdo->prepare('SELECT id_pesanan FROM pesanan WHERE order_id = ?');
    $stmtCek->execute([$order_id]);

    if ($stmtCek->fetch()) {
        // Sudah ada — kembalikan sukses saja agar frontend tidak error
        $response['success'] = true;
        $response['message'] = 'Pesanan sudah tercatat sebelumnya';
        echo json_encode($response);
        exit;
    }

    // -----------------------------------------------------------------------
    // AMBIL DATA BUKU (validasi stok + harga saat beli)
    // -----------------------------------------------------------------------
    $selected_ids = array_map(fn($i) => (int) $i['id_buku'], $selected_items);
    $selected_qty = [];
    foreach ($selected_items as $i) {
        $selected_qty[(int) $i['id_buku']] = (int) $i['qty'];
    }

    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $stmtBuku = $pdo->prepare("SELECT id_buku, judul, harga, stok FROM buku WHERE id_buku IN ($placeholders)");
    $stmtBuku->execute($selected_ids);
    $bukuList = $stmtBuku->fetchAll(PDO::FETCH_ASSOC);

    if (count($bukuList) !== count($selected_ids)) {
        throw new Exception('Salah satu buku tidak ditemukan');
    }

    foreach ($bukuList as $buku) {
        $qty = $selected_qty[$buku['id_buku']];
        if ($buku['stok'] < $qty) {
            throw new Exception("Stok buku '{$buku['judul']}' tidak mencukupi");
        }
    }

    // -----------------------------------------------------------------------
    // TRANSAKSI DB: INSERT pesanan + detail + kurangi stok + hapus keranjang
    // -----------------------------------------------------------------------
    $pdo->beginTransaction();

    // 1. Insert ke tabel pesanan
    $stmtPesanan = $pdo->prepare("
        INSERT INTO pesanan
            (order_id, id_user, total_harga, alamat_pengiriman, kurir,
             metode_pembayaran, status_pembayaran, status_pesanan)
        VALUES (?, ?, ?, ?, ?, 'midtrans', ?, 'dikemas')
    ");
    $stmtPesanan->execute([
        $order_id,
        $user_id,
        (int) $order_data['total_harga'],
        $order_data['alamat'],
        $order_data['kurir'],
        $status_pembayaran,
    ]);

    $id_pesanan = (int) $pdo->lastInsertId();

    // 2. Insert detail pesanan + kurangi stok
    $stmtDetail = $pdo->prepare("
        INSERT INTO detail_pesanan (id_pesanan, id_buku, qty, harga_saat_beli)
        VALUES (?, ?, ?, ?)
    ");
    $stmtStok = $pdo->prepare("
        UPDATE buku SET stok = stok - ? WHERE id_buku = ?
    ");

    foreach ($bukuList as $buku) {
        $qty = $selected_qty[$buku['id_buku']];

        $stmtDetail->execute([
            $id_pesanan,
            (int) $buku['id_buku'],
            $qty,
            (int) $buku['harga'],
        ]);

        $stmtStok->execute([$qty, (int) $buku['id_buku']]);
    }

    // 3. Hapus item dari keranjang (hanya item yang dicheckout)
    $stmtHapusKeranjang = $pdo->prepare("
        DELETE FROM keranjang
        WHERE id_user = ? AND id_buku IN ($placeholders)
    ");
    $stmtHapusKeranjang->execute(array_merge([$user_id], $selected_ids));

    $pdo->commit();

    // -----------------------------------------------------------------------
    // RESPONSE SUKSES
    // -----------------------------------------------------------------------
    $response['success']    = true;
    $response['message']    = 'Pesanan berhasil disimpan!';
    $response['id_pesanan'] = $id_pesanan;
    $response['order_id']   = $order_id;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('[finalize-order] ' . $e->getMessage());
}

echo json_encode($response);