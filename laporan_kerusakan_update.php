<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user'])) {
    die("Akses ditolak");
}

$user = $_SESSION['user'];
$userId   = (int)$user['id'];
$userNama = $user['nama'];

$laporanId = (int)($_POST['laporan_id'] ?? 0);
$catatan   = trim($_POST['catatan'] ?? '');

if (
    $laporanId <= 0 ||
    $catatan === '' ||
    empty($_FILES['foto_perbaikan']['tmp_name'][0])
) {
    die("Data tidak valid");
}

$conn->begin_transaction();

try {

    /* ================= UPDATE LAPORAN ================= */
    $stmt = $conn->prepare("
        UPDATE laporan_kerusakan
        SET status = 'selesai',
            teknisi_user_id = ?,
            teknisi_nama = ?,
            catatan_teknisi = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("issi", $userId, $userNama, $catatan, $laporanId);
    $stmt->execute();

    /* ================= LOG ================= */
    $stmtLog = $conn->prepare("
        INSERT INTO laporan_kerusakan_log
        (laporan_id, aksi, keterangan, actor_user_id, actor_nama, created_at)
        VALUES (?, 'Laporan_perbaikan', 'Kerusakan telah diperbaiki', ?, ?, NOW())
    ");
    $stmtLog->bind_param("iis", $laporanId, $userId, $userNama);
    $stmtLog->execute();

    /* ================= FOTO PERBAIKAN ================= */
    $uploadDir = __DIR__ . "/uploads/laporan_kerusakan/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    foreach ($_FILES['foto_perbaikan']['tmp_name'] as $i => $tmp) {

        if (!is_uploaded_file($tmp)) continue;

        $fileName = "lk_{$laporanId}_" . time() . "_{$i}.jpg";
        $fullPath = $uploadDir . $fileName;
        $dbPath   = "uploads/laporan_kerusakan/" . $fileName;

        move_uploaded_file($tmp, $fullPath);

        $stmtFoto = $conn->prepare("
    INSERT INTO laporan_kerusakan_fotos
    (laporan_id, jenis, foto_path, uploaded_by_user_id, uploaded_at)
    VALUES (?, 'selesai', ?, ?, NOW())
");

        $stmtFoto->bind_param(
            "isi",
            $laporanId,
            $dbPath,
            $userId
        );

        $stmtFoto->execute();
    }

    $conn->commit();

    header("Location: laporan_kerusakan_detail.php?id=$laporanId");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die("Gagal menyimpan perbaikan");
}
