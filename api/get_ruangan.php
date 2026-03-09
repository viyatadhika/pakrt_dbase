<?php
// api/get_ruangan.php
header('Content-Type: application/json');
require_once '../config.php';

$lantaiId = isset($_GET['lantai']) ? (int)$_GET['lantai'] : 0;

if ($lantaiId <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        id,
        nama_ruangan
    FROM master_ruangan
    WHERE lantai_id = ?
    ORDER BY nama_ruangan ASC
");

$stmt->bind_param("i", $lantaiId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'           => (int)$row['id'],
        'nama_ruangan' => $row['nama_ruangan']
    ];
}

$stmt->close();

echo json_encode($data);
