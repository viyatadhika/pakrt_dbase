<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// ✅ Proteksi server-side: hanya admin & gudang
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$allowedRoles = ['admin', 'gudang'];
if (!in_array(strtolower($_SESSION['user']['role'] ?? ''), $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Tidak diizinkan']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user']['id'] ?? 0;

$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Payload tidak valid (JSON)']);
    exit;
}

$ref        = $data['ref_kode']    ?? '';
$tanggal    = $data['tanggal']     ?? date('Y-m-d');
$lokasi     = trim($data['lokasi']     ?? '');
$keterangan = trim($data['keterangan'] ?? '');
$items      = $data['items']       ?? [];

if (!$user_id || $ref === '' || empty($items)) {
    echo json_encode(['status' => 'error', 'message' => 'Data belum lengkap']);
    exit;
}

$conn->begin_transaction();

try {
    // insert header
    $stmt = $conn->prepare("
        INSERT INTO stok_opname (ref_kode, tanggal, lokasi, keterangan, user_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmt) throw new Exception($conn->error);

    $stmt->bind_param("ssssi", $ref, $tanggal, $lokasi, $keterangan, $user_id);
    $stmt->execute();

    $stok_opname_id = $conn->insert_id;

    // insert detail + update stok master
    foreach ($items as $i) {
        $kode        = $i['kode_barang'] ?? '';
        $nama        = $i['nama_barang'] ?? '';
        $stok_sistem = (int)($i['stok_sistem'] ?? 0);
        $stok_fisik  = (int)($i['stok_fisik']  ?? 0);
        $selisih     = (int)($i['selisih']      ?? ($stok_fisik - $stok_sistem));
        $satuan      = $i['satuan']  ?? '';
        $catatan     = $i['catatan'] ?? '';

        if ($kode === '') continue;

        // simpan detail
        $d = $conn->prepare("
            INSERT INTO stok_opname_detail
            (stok_opname_id, kode_barang, nama_barang, stok_sistem, stok_fisik, selisih, satuan, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$d) throw new Exception($conn->error);

        $d->bind_param("issiiiss", $stok_opname_id, $kode, $nama, $stok_sistem, $stok_fisik, $selisih, $satuan, $catatan);
        $d->execute();

        // update master_barang stok = stok_fisik (hasil opname)
        $u = $conn->prepare("UPDATE master_barang SET stok = ? WHERE kode_barang = ? LIMIT 1");
        if (!$u) throw new Exception($conn->error);
        $u->bind_param("is", $stok_fisik, $kode);
        $u->execute();
    }

    $conn->commit();
    echo json_encode(['status' => 'ok', 'id' => $stok_opname_id]);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
