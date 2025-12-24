<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit;
}

$foto_id = (int)($_POST['foto_id'] ?? 0);
$emoji   = $_POST['emoji'] ?? '';

$nip  = $_SESSION['user']['nip'];
$nama = $_SESSION['user']['nama'];

if (!$foto_id || !$emoji) {
    http_response_code(400);
    exit;
}

// ambil reactions lama
$stmt = $conn->prepare("SELECT reactions FROM checklist_fotos WHERE id=?");
$stmt->bind_param("i", $foto_id);
$stmt->execute();
$stmt->bind_result($json);
$stmt->fetch();
$stmt->close();

$data = [];
if ($json) {
    $data = json_decode($json, true);
    if (!is_array($data)) $data = [];
}

// set / update reaction user (1 user = 1)
$data[$nip] = [
    "emoji" => $emoji,
    "nama"  => $nama
];

// simpan ulang
$newJson = json_encode($data, JSON_UNESCAPED_UNICODE);

$stmt = $conn->prepare("UPDATE checklist_fotos SET reactions=? WHERE id=?");
$stmt->bind_param("si", $newJson, $foto_id);
$stmt->execute();
$stmt->close();

// hitung ulang untuk response
$summary = [];
foreach ($data as $u) {
    $summary[$u['emoji']] = ($summary[$u['emoji']] ?? 0) + 1;
}

echo json_encode([
    "summary" => $summary,
    "users"   => $data
]);
