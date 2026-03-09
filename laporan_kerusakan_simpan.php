<?php
session_start();
require 'config.php';

/* ================= AUTH ================= */
if (!isset($_SESSION['user'])) {
    die("Akses ditolak");
}

$user = $_SESSION['user'];

/* ================= VALIDASI INPUT ================= */
$tipeLokasiId = (int)($_POST['tipe_lokasi_id'] ?? 0);
$lokasiId     = (int)($_POST['lokasi_id'] ?? 0);
$lantaiId     = (int)($_POST['lantai_id'] ?? 0);
$ruanganId    = (int)($_POST['ruangan_id'] ?? 0);
$kamarId      = (int)($_POST['kamar_id'] ?? 0);
$kategoriId   = (int)($_POST['kategori_kerusakan_id'] ?? 0);
$jenisId      = (int)($_POST['jenis_kerusakan_id'] ?? 0);
$deskripsi    = trim($_POST['deskripsi'] ?? '');

if (
    $tipeLokasiId <= 0 ||
    $lokasiId <= 0 ||
    $kategoriId <= 0 ||
    $jenisId <= 0 ||
    $deskripsi === ''
) {
    die("Data tidak lengkap");
}

/* ================= USER ID (AMAN TANPA get_result) ================= */
$stmtUser = $conn->prepare("SELECT id FROM users WHERE nip = ?");
$stmtUser->bind_param("s", $user['nip']);
$stmtUser->execute();
$stmtUser->bind_result($userId);
$stmtUser->fetch();
$stmtUser->close();

if (!$userId) {
    die("User tidak ditemukan");
}

/* ================= NORMALISASI NULL ================= */
$lantaiBind  = $lantaiId > 0 ? $lantaiId : null;
$ruanganBind = $ruanganId > 0 ? $ruanganId : null;
$kamarBind   = $kamarId > 0 ? $kamarId : null;

/* ================= CEK DUPLIKAT ================= */
$stmtCek = $conn->prepare("
    SELECT id FROM laporan_kerusakan
    WHERE
        tipe_lokasi_id = ?
        AND lokasi_id = ?
        AND IFNULL(lantai_id,0) = IFNULL(?,0)
        AND IFNULL(ruangan_id,0) = IFNULL(?,0)
        AND IFNULL(kamar_id,0) = IFNULL(?,0)
        AND jenis_kerusakan_id = ?
        AND status = 'dilaporkan'
    LIMIT 1
");
$stmtCek->bind_param(
    "iiiiii",
    $tipeLokasiId,
    $lokasiId,
    $lantaiBind,
    $ruanganBind,
    $kamarBind,
    $jenisId
);
$stmtCek->execute();
$stmtCek->bind_result($duplikatId);
$stmtCek->fetch();
$stmtCek->close();

if ($duplikatId) {
    header("Location: laporan_kerusakan_tambah.php?duplikat=1&id=$duplikatId");
    exit;
}

/* ================= SIMPAN LAPORAN ================= */
$status = 'dilaporkan';

$stmt = $conn->prepare("
    INSERT INTO laporan_kerusakan (
        pelapor_user_id,
        pelapor_nip,
        pelapor_nama,
        deskripsi,
        status,
        tipe_lokasi_id,
        lokasi_id,
        lantai_id,
        ruangan_id,
        kamar_id,
        jenis_kerusakan_id,
        kategori_kerusakan_id,
        created_at,
        updated_at
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW()
    )
");

if (!$stmt) {
    die("SQL ERROR: " . $conn->error);
}

$stmt->bind_param(
    "issssiiiiiii",
    $userId,
    $user['nip'],
    $user['nama'],
    $deskripsi,
    $status,
    $tipeLokasiId,
    $lokasiId,
    $lantaiBind,
    $ruanganBind,
    $kamarBind,
    $jenisId,
    $kategoriId
);

$stmt->execute();
$laporanId = $stmt->insert_id;
$stmt->close();

if ($laporanId <= 0) {
    die("Gagal menyimpan laporan");
}

/* ================= LOG AKTIVITAS ================= */
$stmtLog = $conn->prepare("
    INSERT INTO laporan_kerusakan_log
    (laporan_id, aksi, keterangan, actor_user_id, actor_nama, created_at)
    VALUES (?, 'Laporan_kerusakan', 'Laporan kerusakan dibuat', ?, ?, NOW())
");
$stmtLog->bind_param("iis", $laporanId, $userId, $user['nama']);
$stmtLog->execute();
$stmtLog->close();

/* ================= FOTO KERUSAKAN ================= */
$uploadDir = __DIR__ . "/uploads/laporan_kerusakan/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

if (!empty($_FILES['foto_kerusakan']['tmp_name'][0])) {
    foreach ($_FILES['foto_kerusakan']['tmp_name'] as $i => $tmp) {
        if (!is_uploaded_file($tmp)) continue;

        $file = "lk_{$laporanId}_" . time() . "_{$i}.jpg";
        move_uploaded_file($tmp, $uploadDir . $file);

        $path = "uploads/laporan_kerusakan/" . $file;

        $stmtF = $conn->prepare("
            INSERT INTO laporan_kerusakan_fotos
            (laporan_id, jenis, foto_path, uploaded_by_user_id, uploaded_at)
            VALUES (?, 'awal', ?, ?, NOW())
        ");
        $stmtF->bind_param("isi", $laporanId, $path, $userId);
        $stmtF->execute();
        $stmtF->close();
    }
}

header("Location: laporan_sukses.php?id=$laporanId");
exit;
