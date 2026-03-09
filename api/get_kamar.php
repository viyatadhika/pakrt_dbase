<?php
header('Content-Type: application/json');
require_once '../config.php';

$lantai = (int)($_GET['lantai'] ?? 0);
if ($lantai <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, nomor_kamar
    FROM master_kamar
    WHERE lantai_id = ?
    ORDER BY nomor_kamar ASC
");
$stmt->bind_param("i", $lantai);
$stmt->execute();

$res = $stmt->get_result();
$data = [];
while ($r = $res->fetch_assoc()) {
    $data[] = $r;
}
echo json_encode($data);
