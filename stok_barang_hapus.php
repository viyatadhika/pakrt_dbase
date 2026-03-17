<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session habis']);
    exit;
}

// ✅ Proteksi server-side: hanya admin & gudang
$allowedRoles = ['admin', 'gudang'];
if (!in_array(strtolower($_SESSION['user']['role'] ?? ''), $allowedRoles)) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak diizinkan']);
    exit;
}

include 'config.php';

if (empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak ditemukan']);
    exit;
}

$id = (int)$_POST['id'];

$stmt = $conn->prepare("DELETE FROM master_barang WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success', 'message' => 'Barang berhasil dihapus']);
exit;
