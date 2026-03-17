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
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Payload tidak valid (JSON)']);
    exit;
}

$user_id = (int)($_SESSION['user']['id'] ?? 0);
$ref     = trim($data['ref_kode']   ?? '');
$tanggal = trim($data['tanggal']    ?? date('Y-m-d'));
$jenis   = trim($data['jenis']      ?? 'tambah');
$alasan  = trim($data['alasan']     ?? '');
$ket     = trim($data['keterangan'] ?? '-');
$items   = $data['items'] ?? [];

if (!$user_id || $ref === '' || $tanggal === '' || $alasan === '' || empty($items)) {
    echo json_encode(['status' => 'error', 'message' => 'Data belum lengkap']);
    exit;
}

if (!in_array($jenis, ['tambah', 'kurang'])) {
    echo json_encode(['status' => 'error', 'message' => 'Jenis koreksi tidak valid']);
    exit;
}

$conn->begin_transaction();

try {
    // insert header
    $stmt = $conn->prepare("
        INSERT INTO koreksi_stok
        (ref_kode, tanggal, jenis, alasan, keterangan, user_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) throw new Exception("Prepare header gagal: " . $conn->error);

    $stmt->bind_param("sssssi", $ref, $tanggal, $jenis, $alasan, $ket, $user_id);
    $stmt->execute();
    $koreksi_id = $conn->insert_id;

    $stokUpdates = [];

    foreach ($items as $it) {
        $kode = trim($it['kode_barang'] ?? '');
        $nama = trim($it['nama_barang'] ?? '');
        $qty  = (int)($it['qty']        ?? 0);
        $sat  = trim($it['satuan']       ?? '');

        if ($kode === '' || $nama === '' || $qty <= 0) {
            throw new Exception("Detail item tidak valid");
        }

        // ambil stok sekarang
        $q = $conn->prepare("SELECT id, stok, kode_barang, nama_barang FROM master_barang WHERE kode_barang = ? LIMIT 1");
        if (!$q) throw new Exception("Prepare cek stok gagal: " . $conn->error);
        $q->bind_param("s", $kode);
        $q->execute();
        $res = $q->get_result();
        if ($res->num_rows < 1) throw new Exception("Barang tidak ditemukan: $kode");
        $barang   = $res->fetch_assoc();
        $stokNow  = (int)$barang['stok'];

        if ($jenis === 'kurang' && $qty > $stokNow) {
            throw new Exception("Stok tidak cukup untuk $kode (stok $stokNow)");
        }

        // insert detail
        $d = $conn->prepare("
            INSERT INTO koreksi_stok_detail
            (koreksi_stok_id, kode_barang, nama_barang, qty, satuan, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        if (!$d) throw new Exception("Prepare detail gagal: " . $conn->error);
        $d->bind_param("issis", $koreksi_id, $kode, $nama, $qty, $sat);
        $d->execute();

        // update stok master
        if ($jenis === 'tambah') {
            $u = $conn->prepare("UPDATE master_barang SET stok = stok + ? WHERE id = ?");
        } else {
            $u = $conn->prepare("UPDATE master_barang SET stok = GREATEST(0, stok - ?) WHERE id = ?");
        }
        if (!$u) throw new Exception("Prepare update stok gagal: " . $conn->error);
        $u->bind_param("ii", $qty, $barang['id']);
        $u->execute();

        // ambil stok terbaru
        $qNew = $conn->prepare("SELECT stok FROM master_barang WHERE id = ? LIMIT 1");
        $qNew->bind_param("i", $barang['id']);
        $qNew->execute();
        $newRow   = $qNew->get_result()->fetch_assoc();
        $stokBaru = (int)($newRow['stok'] ?? 0);

        $stokUpdates[] = [
            'barang_id'   => (int)$barang['id'],
            'kode_barang' => $barang['kode_barang'],
            'nama_barang' => $barang['nama_barang'],
            'stok'        => $stokBaru,
            'jenis'       => $jenis,
            'qty'         => $qty
        ];
    }

    $conn->commit();

    echo json_encode(['status' => 'ok', 'id' => $koreksi_id, 'stok_updates' => $stokUpdates]);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
