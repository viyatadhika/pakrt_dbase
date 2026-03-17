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

/* ================= VALIDASI ================= */
if (empty($_POST['nama_barang']) || empty($_POST['kategori_id']) || empty($_POST['satuan_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

/* ================= AMBIL DATA ================= */
$nama_barang = trim($_POST['nama_barang']);
$kategori_id = (int)$_POST['kategori_id'];
$satuan_id   = (int)$_POST['satuan_id'];
$stok        = isset($_POST['stok']) ? (int)$_POST['stok'] : 0;

/* ================= GENERATE KODE ================= */
$stmtKode = $conn->prepare("SELECT kode_prefix FROM master_kategori_barang WHERE id = ? AND status = 'aktif' LIMIT 1");
$stmtKode->bind_param("i", $kategori_id);
$stmtKode->execute();
$kat = $stmtKode->get_result()->fetch_assoc();
$stmtKode->close();

if (!$kat) {
    echo json_encode(['status' => 'error', 'message' => 'Kategori tidak valid']);
    exit;
}

$prefix   = $kat['kode_prefix'];
$stmtLast = $conn->prepare("SELECT kode_barang FROM master_barang WHERE kategori_id = ? ORDER BY id DESC LIMIT 1");
$stmtLast->bind_param("i", $kategori_id);
$stmtLast->execute();
$last = $stmtLast->get_result()->fetch_assoc();
$stmtLast->close();

$nextNumber = 1;
if ($last) {
    $num = (int)substr($last['kode_barang'], strlen($prefix) + 1);
    $nextNumber = $num + 1;
}
$kode_barang = sprintf('%s-%04d', $prefix, $nextNumber);

/* ================= SIMPAN ================= */
$stmt = $conn->prepare("INSERT INTO master_barang (kode_barang, nama_barang, kategori_id, satuan_id, stok) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}

$stmt->bind_param("ssiii", $kode_barang, $nama_barang, $kategori_id, $satuan_id, $stok);
$stmt->execute();
$insert_id = $stmt->insert_id;
$stmt->close();

$q = $conn->prepare("SELECT b.id, b.kode_barang, b.nama_barang, b.kategori_id, k.nama_kategori, k.icon, k.color, b.satuan_id, b.stok FROM master_barang b JOIN master_kategori_barang k ON b.kategori_id = k.id WHERE b.id = ? LIMIT 1");
$q->bind_param("i", $insert_id);
$q->execute();
$data = $q->get_result()->fetch_assoc();
$q->close();

echo json_encode(['status' => 'success', 'data' => $data]);
exit;
