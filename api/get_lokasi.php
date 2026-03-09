<?php
header('Content-Type: application/json');
require_once '../config.php';

$tipe = isset($_GET['tipe']) ? (int)$_GET['tipe'] : 0;
if ($tipe <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
  SELECT id, nama_lokasi
  FROM master_lokasi
  WHERE tipe_lokasi_id = ?
  ORDER BY nama_lokasi ASC
");

$stmt->bind_param("i", $tipe);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($r = $res->fetch_assoc()) {
    $data[] = [
        'id' => (int)$r['id'],
        'nama_lokasi' => $r['nama_lokasi']
    ];
}

echo json_encode($data);
