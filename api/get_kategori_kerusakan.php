<?php
header('Content-Type: application/json');
require_once '../config.php';

$q = $conn->query("SELECT id, nama_kategori FROM master_kategori_kerusakan");
echo json_encode($q->fetch_all(MYSQLI_ASSOC));
