<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session habis']);
    exit;
}

// ✅ Proteksi server-side: hanya admin & gudang
$allowedRoles = ['admin', 'gudang'];
if (!in_array(strtolower($_SESSION['user']['role'] ?? ''), $allowedRoles)) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak diizinkan']);
    exit;
}

include 'config.php';

$id          = (int)($_POST['id']          ?? 0);
$nama        = trim($_POST['nama_barang']  ?? '');
$kategori_id = (int)($_POST['kategori_id'] ?? 0);
$satuan_id   = (int)($_POST['satuan_id']   ?? 0);

if ($id <= 0 || $nama === '' || $kategori_id <= 0 || $satuan_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE master_barang SET nama_barang = ?, kategori_id = ?, satuan_id = ? WHERE id = ? LIMIT 1");
    if (!$stmt) throw new Exception($conn->error);

    $stmt->bind_param("siii", $nama, $kategori_id, $satuan_id, $id);
    $stmt->execute();

    $q = $conn->prepare("
        SELECT b.id, b.kode_barang, b.nama_barang, b.stok, b.satuan_id, b.kategori_id,
               COALESCE(k.icon,'fa-box') AS icon, COALESCE(k.color,'sky') AS color
        FROM master_barang b
        LEFT JOIN master_kategori_barang k ON k.id = b.kategori_id
        WHERE b.id = ? LIMIT 1
    ");
    if (!$q) throw new Exception($conn->error);

    $q->bind_param("i", $id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();

    echo json_encode(['status' => 'success', 'data' => $row]);
    exit;
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
