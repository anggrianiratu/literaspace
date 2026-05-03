<?php
// api/review.php - Handle review submission
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

    // Handle POST - Submit review
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $id_buku = (int)($data['id_buku'] ?? 0);
        $rating = (int)($data['rating'] ?? 0);
        $komentar = trim($data['komentar'] ?? '');

        // Validation
        if ($id_buku <= 0) {
            $response['message'] = 'ID buku tidak valid';
            echo json_encode($response);
            exit;
        }

        if ($rating < 1 || $rating > 5) {
            $response['message'] = 'Rating harus antara 1-5';
            echo json_encode($response);
            exit;
        }

        if (empty($komentar)) {
            $response['message'] = 'Komentar tidak boleh kosong';
            echo json_encode($response);
            exit;
        }

        if (strlen($komentar) > 1000) {
            $response['message'] = 'Komentar terlalu panjang (maksimal 1000 karakter)';
            echo json_encode($response);
            exit;
        }

        // Check if book exists
        $stmt = $pdo->prepare("SELECT id_buku FROM buku WHERE id_buku = ?");
        $stmt->execute([$id_buku]);
        if (!$stmt->fetch()) {
            $response['message'] = 'Buku tidak ditemukan';
            echo json_encode($response);
            exit;
        }

        // Check if user already reviewed this book
        $stmt = $pdo->prepare("SELECT id_review FROM review WHERE id_user = ? AND id_buku = ?");
        $stmt->execute([$user_id, $id_buku]);
        $existing_review = $stmt->fetch();

        if ($existing_review) {
            // Update existing review
            $stmt = $pdo->prepare("
                UPDATE review 
                SET rating = ?, komentar = ?, created_at = CURRENT_TIMESTAMP
                WHERE id_user = ? AND id_buku = ?
            ");
            $stmt->execute([$rating, $komentar, $user_id, $id_buku]);
            $response = [
                'success' => true,
                'message' => 'Review berhasil diperbarui!',
                'action' => 'update'
            ];
        } else {
            // Insert new review
            $stmt = $pdo->prepare("
                INSERT INTO review (id_user, id_buku, rating, komentar)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $id_buku, $rating, $komentar]);
            $response = [
                'success' => true,
                'message' => 'Review berhasil ditambahkan!',
                'action' => 'create'
            ];
        }
    } else {
        http_response_code(405);
        $response['message'] = 'Method tidak diizinkan';
    }
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Terjadi kesalahan server: ' . $e->getMessage();
}

echo json_encode($response);
?>
