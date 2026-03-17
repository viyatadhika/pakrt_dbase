<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$allowedRoles = ['admin', 'security'];
$role = strtolower($_SESSION['user']['role'] ?? '');
if (!in_array($role, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Tidak diizinkan']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
$aksi = trim($data['aksi'] ?? '');

$dicatatOleh = trim($_SESSION['user']['nama'] ?? ($_SESSION['user']['nama_lengkap'] ?? 'Petugas'));
$now         = date('Y-m-d H:i:s');

if ($aksi === 'keluar') {
    $plat       = strtoupper(trim($data['plat_nomor'] ?? ''));
    $pengemudi  = trim($data['pengemudi']              ?? '');
    $tujuan     = trim($data['tujuan']                 ?? '');
    $keterangan = trim($data['keterangan']             ?? '');

    if ($plat === '') {
        echo json_encode(['status' => 'error', 'message' => 'Plat nomor wajib diisi']);
        exit;
    }
    if ($pengemudi === '') {
        echo json_encode(['status' => 'error', 'message' => 'Nama pengemudi wajib diisi']);
        exit;
    }

    $cek = $conn->prepare("SELECT id FROM kendaraan_operasional_log WHERE plat_nomor = ? AND status = 'keluar' LIMIT 1");
    $cek->bind_param('s', $plat);
    $cek->execute();
    $sudahKeluar = $cek->get_result()->num_rows > 0;
    $cek->close();

    if ($sudahKeluar) {
        echo json_encode(['status' => 'error', 'message' => "Kendaraan $plat masih tercatat di luar. Catat kembali dulu."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO kendaraan_operasional_log (plat_nomor, pengemudi, tujuan, keterangan, waktu_keluar, status, dicatat_oleh) VALUES (?, ?, ?, ?, ?, 'keluar', ?)");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }

    $stmt->bind_param('ssssss', $plat, $pengemudi, $tujuan, $keterangan, $now, $dicatatOleh);
    $ok = $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();

    echo json_encode(
        $ok
            ? ['status' => 'ok', 'id' => $id, 'message' => "Kendaraan $plat berhasil dicatat KELUAR."]
            : ['status' => 'error', 'message' => 'Gagal menyimpan']
    );
    exit;
}

if ($aksi === 'kembali') {
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE kendaraan_operasional_log SET status = 'kembali', waktu_kembali = ? WHERE id = ? AND status = 'keluar'");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }

    $stmt->bind_param('si', $now, $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(
        $affected > 0
            ? ['status' => 'ok',    'message' => 'Kendaraan berhasil dicatat KEMBALI.']
            : ['status' => 'error', 'message' => 'Data tidak ditemukan atau sudah kembali.']
    );
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenali']);
