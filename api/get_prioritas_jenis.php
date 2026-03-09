<?php
// api/get_prioritas_jenis.php
header('Content-Type: application/json');
require_once '../config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'prioritas' => null
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT prioritas_default
    FROM master_jenis_kerusakan
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    echo json_encode([
        'success' => false,
        'prioritas' => null
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'prioritas' => $res['prioritas_default']
]);
