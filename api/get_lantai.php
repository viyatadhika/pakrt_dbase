<?php
// api/get_lantai.php
header('Content-Type: application/json');
require_once '../config.php';

$gedungId = isset($_GET['gedung']) ? (int)$_GET['gedung'] : 0;

if ($gedungId <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        id,
        nama_lantai,
        IFNULL(is_virtual, 0) AS is_virtual
    FROM master_lantai
    WHERE lokasi_id = ?
    ORDER BY is_virtual ASC, id ASC
");

$stmt->bind_param("i", $gedungId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'          => (int)$row['id'],
        'nama_lantai' => $row['nama_lantai'],
        'is_virtual'  => (int)$row['is_virtual']
    ];
}

$stmt->close();

echo json_encode($data);
