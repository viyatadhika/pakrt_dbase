<?php
header('Content-Type: application/json');
require_once '../config.php';

$kategori = (int)($_GET['kategori'] ?? 0);

$q = $conn->prepare("
    SELECT id, nama_jenis
    FROM master_jenis_kerusakan
    WHERE kategori_id = ?
");
$q->bind_param("i", $kategori);
$q->execute();
echo json_encode($q->get_result()->fetch_all(MYSQLI_ASSOC));
