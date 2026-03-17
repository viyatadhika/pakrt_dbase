<?php
session_start();

// ✅ Proteksi server-side: hanya admin & gudang
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$allowedRoles = ['admin', 'gudang'];
if (!in_array(strtolower($_SESSION['user']['role'] ?? ''), $allowedRoles)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Tidak diizinkan']);
    exit;
}

include 'config.php';

$raw     = file_get_contents("php://input");
$payload = json_decode($raw, true);

if (!$payload) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Payload tidak valid']);
    exit;
}

$ref_kode   = trim($payload['ref_kode']   ?? '');
$tanggal    = trim($payload['tanggal']    ?? '');
$penerima   = trim($payload['penerima']   ?? '');
$keterangan = trim($payload['keterangan'] ?? '-');
$items      = $payload['items'] ?? [];

$user_id = $_SESSION['user']['id'] ?? 1;

if ($ref_kode === '' || $tanggal === '' || $penerima === '' || !is_array($items) || count($items) < 1) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Data transaksi tidak lengkap']);
    exit;
}

$conn->begin_transaction();

try {
    // insert header
    $stmt = $conn->prepare("
        INSERT INTO barang_keluar (ref_kode, tanggal, penerima, keterangan, user_id, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) throw new Exception($conn->error);

    $stmt->bind_param("ssssi", $ref_kode, $tanggal, $penerima, $keterangan, $user_id);
    $stmt->execute();
    $barang_keluar_id = $conn->insert_id;

    // insert detail + update stok
    $stmtDetail = $conn->prepare("
        INSERT INTO barang_keluar_detail (barang_keluar_id, kode_barang, nama_barang, qty, satuan)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmtDetail) throw new Exception($conn->error);

    $stmtUpdate = $conn->prepare("
        UPDATE master_barang
        SET stok = GREATEST(0, stok - ?)
        WHERE kode_barang = ?
    ");
    if (!$stmtUpdate) throw new Exception($conn->error);

    foreach ($items as $it) {
        $kode_barang = trim($it['kode_barang'] ?? '');
        $nama_barang = trim($it['nama_barang'] ?? '');
        $qty         = (int)($it['qty']        ?? 0);
        $satuan      = trim($it['satuan']       ?? '');

        if ($kode_barang === '' || $qty <= 0) continue;

        $stmtDetail->bind_param("issis", $barang_keluar_id, $kode_barang, $nama_barang, $qty, $satuan);
        $stmtDetail->execute();

        $stmtUpdate->bind_param("is", $qty, $kode_barang);
        $stmtUpdate->execute();
    }

    $conn->commit();

    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'message' => 'Berhasil disimpan', 'id' => $barang_keluar_id]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
