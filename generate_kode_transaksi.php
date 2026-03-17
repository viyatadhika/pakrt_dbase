<?php
include 'config.php';

$tanggal = date('Y-m-d');
$prefix = 'IN-' . date('Ymd') . '-';

// ambil transaksi terakhir di tanggal yg sama
$q = $conn->prepare("
    SELECT ref_kode 
    FROM barang_masuk 
    WHERE tanggal = ? 
    ORDER BY id DESC 
    LIMIT 1
");
$q->bind_param('s', $tanggal);
$q->execute();
$r = $q->get_result();

$urutan = 1;
if ($row = $r->fetch_assoc()) {
    $last = (int) substr($row['ref_kode'], -4);
    $urutan = $last + 1;
}

$ref = $prefix . str_pad($urutan, 4, '0', STR_PAD_LEFT);

echo json_encode([
    'status' => 'ok',
    'ref' => $ref
]);
