<?php
header('Content-Type: application/json');
require_once '../config.php';

$res = $conn->query("
  SELECT id, nama 
  FROM master_tipe_lokasi
  ORDER BY id ASC
");

$data = [];
while ($r = $res->fetch_assoc()) {
  $data[] = [
    'id'   => (int)$r['id'],
    'nama' => $r['nama']
  ];
}

echo json_encode($data);
