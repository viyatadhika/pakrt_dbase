<?php
include 'config.php';
header('Content-Type: application/json');

$kategoriId = $_GET['kategori_id'] ?? '';

if (!$kategoriId) {
    echo json_encode(['success' => false, 'message' => 'Kategori kosong']);
    exit;
}

/* ambil prefix kategori */
$stmt = $conn->prepare("
    SELECT kode_prefix
    FROM master_kategori_barang
    WHERE id = ?
");
$stmt->bind_param("i", $kategoriId);
$stmt->execute();
$res = $stmt->get_result();
$kat = $res->fetch_assoc();

if (!$kat) {
    echo json_encode(['success' => false, 'message' => 'Kategori tidak ditemukan']);
    exit;
}

$prefix = $kat['kode_prefix'];

/* ambil kode terakhir */
$stmt2 = $conn->prepare("
    SELECT MAX(kode_barang) AS max_kode
    FROM master_barang
    WHERE kode_barang LIKE CONCAT(?, '-%')
");
$stmt2->bind_param("s", $prefix);
$stmt2->execute();
$res2 = $stmt2->get_result();
$row = $res2->fetch_assoc();

$lastNumber = 0;
if (!empty($row['max_kode'])) {
    // ambil angka setelah PREFIX-
    $lastNumber = (int) substr($row['max_kode'], strlen($prefix) + 1);
}

/* 🔥 4 DIGIT */
$newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
$newKode = $prefix . '-' . $newNumber;

echo json_encode([
    'success' => true,
    'kode' => $newKode
]);
