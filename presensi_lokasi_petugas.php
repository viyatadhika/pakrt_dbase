<?php
session_start();
if (!isset($_SESSION['user'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Login dibutuhkan.');
    }
    header('Location: login.php');
    exit;
}

include 'config.php';

date_default_timezone_set('Asia/Jakarta');

/*
   PENTING:
   Proses POST harus berada SEBELUM include header.php.
   Kalau header.php dipanggil dulu, respon AJAX akan tercampur HTML + OK,
   sehingga JavaScript menganggap simpan presensi gagal.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'simpan_presensi') {
    header('Content-Type: text/plain; charset=utf-8');

    $user_id = (int)($_POST['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    $nama_petugas = trim((string)($_POST['nama_petugas'] ?? ($_SESSION['user']['nama'] ?? '')));
    $jenis_presensi = trim((string)($_POST['jenis_presensi'] ?? ''));
    $lokasi_presensi = trim((string)($_POST['lokasi_presensi'] ?? ''));
    $latitudeRaw = trim((string)($_POST['latitude'] ?? ''));
    $longitudeRaw = trim((string)($_POST['longitude'] ?? ''));
    $accuracyRaw = trim((string)($_POST['accuracy'] ?? ''));
    $distanceRaw = trim((string)($_POST['distance_meter'] ?? ''));
    $lokasi_valid = trim((string)($_POST['lokasi_valid'] ?? '0'));
    $mode_kerja = strtoupper(trim((string)($_POST['mode_kerja'] ?? 'WFO')));

    if (!in_array($mode_kerja, ['WFO', 'WFA'], true)) {
        http_response_code(400);
        exit('Mode kerja tidak valid.');
    }

    if ($user_id <= 0 || $nama_petugas === '' || $jenis_presensi === '' || $lokasi_presensi === '' || $latitudeRaw === '' || $longitudeRaw === '') {
        http_response_code(400);
        exit('Data presensi tidak lengkap. GPS wajib aktif.');
    }

    if ($mode_kerja === 'WFO' && $lokasi_valid !== '1') {
        http_response_code(400);
        exit('Presensi WFO hanya bisa dilakukan di dalam radius kantor. Pilih WFA jika sedang bekerja dari luar kantor.');
    }

    if (!is_numeric($latitudeRaw) || !is_numeric($longitudeRaw)) {
        http_response_code(400);
        exit('Koordinat GPS tidak valid.');
    }

    $latitude = (float)$latitudeRaw;
    $longitude = (float)$longitudeRaw;
    $accuracy = is_numeric($accuracyRaw) ? (float)$accuracyRaw : 0.0;
    $distance_meter = is_numeric($distanceRaw) ? (float)$distanceRaw : 0.0;
    $lokasiValidInt = $lokasi_valid === '1' ? 1 : 0;
    $catatan = $mode_kerja;

    if (!in_array($jenis_presensi, ['Masuk', 'Pulang'], true)) {
        http_response_code(400);
        exit('Jenis presensi tidak valid.');
    }

    $cek = $conn->prepare("
        SELECT id
        FROM presensi_lokasi_petugas
        WHERE user_id = ?
          AND jenis_presensi = ?
          AND DATE(created_at) = CURDATE()
        LIMIT 1
    ");

    if (!$cek) {
        http_response_code(500);
        exit('Query cek presensi gagal: ' . $conn->error);
    }

    $cek->bind_param('is', $user_id, $jenis_presensi);
    $cek->execute();
    $cekRes = $cek->get_result();
    $sudahPresensiJenisIni = $cekRes && $cekRes->num_rows > 0;
    $cek->close();

    if ($sudahPresensiJenisIni) {
        http_response_code(409);
        exit('Anda sudah presensi ' . $jenis_presensi . ' hari ini.');
    }

    if ($jenis_presensi === 'Pulang') {
        $cekMasuk = $conn->prepare("
            SELECT id, catatan
            FROM presensi_lokasi_petugas
            WHERE user_id = ?
              AND jenis_presensi = 'Masuk'
              AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");

        if (!$cekMasuk) {
            http_response_code(500);
            exit('Query cek presensi masuk gagal: ' . $conn->error);
        }

        $cekMasuk->bind_param('i', $user_id);
        $cekMasuk->execute();
        $cekMasukRes = $cekMasuk->get_result();
        $rowMasukHariIni = $cekMasukRes ? $cekMasukRes->fetch_assoc() : null;
        $sudahMasukHariIni = is_array($rowMasukHariIni);
        $cekMasuk->close();

        if (!$sudahMasukHariIni) {
            http_response_code(400);
            exit('Presensi Masuk harus dilakukan lebih dulu sebelum Pulang.');
        }

        $modeMasuk = strtoupper(trim((string)($rowMasukHariIni['catatan'] ?? '')));
        if (in_array($modeMasuk, ['WFO', 'WFA'], true)) {
            $mode_kerja = $modeMasuk;
            $catatan = $modeMasuk;
        }
    }

    $uploadDir = __DIR__ . '/uploads/presensi_lokasi/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            http_response_code(500);
            exit('Folder upload presensi gagal dibuat.');
        }
    }

    if (!is_writable($uploadDir)) {
        http_response_code(500);
        exit('Folder uploads/presensi_lokasi belum bisa ditulis.');
    }

    $fotoPath = null;

    if (!empty($_FILES['foto_presensi']['name'])) {
        $ext = strtolower(pathinfo($_FILES['foto_presensi']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            http_response_code(400);
            exit('Format foto tidak valid.');
        }

        $filename = 'absensi_asn_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.jpg';
        $target = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['foto_presensi']['tmp_name'], $target)) {
            http_response_code(500);
            exit('Gagal upload foto.');
        }

        $fotoPath = 'uploads/presensi_lokasi/' . $filename;
    }

    $stmt = $conn->prepare("\n        INSERT INTO presensi_lokasi_petugas\n        (user_id, nama_petugas, jenis_presensi, lokasi_presensi, latitude, longitude, accuracy, distance_meter, lokasi_valid, foto_presensi, catatan, created_at)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())\n    ");

    if (!$stmt) {
        http_response_code(500);
        exit('Query gagal: ' . $conn->error);
    }

    $stmt->bind_param(
        'isssddddiss',
        $user_id,
        $nama_petugas,
        $jenis_presensi,
        $lokasi_presensi,
        $latitude,
        $longitude,
        $accuracy,
        $distance_meter,
        $lokasiValidInt,
        $fotoPath,
        $catatan
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        exit('Gagal menyimpan presensi: ' . $stmt->error);
    }

    $stmt->close();
    echo 'OK';
    exit;
}

$title = 'Presensi';
include 'header.php';

$namaPetugas = $_SESSION['user']['nama'] ?? '';
$userId = $_SESSION['user']['id'] ?? '';

$lokasi = [
    'nama_lokasi' => 'Pusdiklat',
    'latitude' => '-6.673900',
    'longitude' => '106.895600',
    'radius_meter' => '150'
];

$q = $conn->query("SELECT * FROM pengaturan_lokasi_presensi WHERE aktif = 1 ORDER BY id DESC LIMIT 1");
if ($q && $q->num_rows > 0) {
    $lokasi = $q->fetch_assoc();
}

$statusHariIni = [
    'Masuk' => false,
    'Pulang' => false,
    'mode_masuk' => '',
];

if ((int)$userId > 0) {
    $stmtStatus = $conn->prepare("
        SELECT jenis_presensi, catatan
        FROM presensi_lokasi_petugas
        WHERE user_id = ?
          AND DATE(created_at) = CURDATE()
    ");

    if ($stmtStatus) {
        $uidStatus = (int)$userId;
        $stmtStatus->bind_param('i', $uidStatus);
        $stmtStatus->execute();
        $resStatus = $stmtStatus->get_result();
        while ($rowStatus = $resStatus->fetch_assoc()) {
            $jenisStatus = (string)($rowStatus['jenis_presensi'] ?? '');
            if ($jenisStatus === 'Masuk') {
                $statusHariIni['Masuk'] = true;
                $modeMasukStatus = strtoupper(trim((string)($rowStatus['catatan'] ?? '')));
                if (in_array($modeMasukStatus, ['WFO', 'WFA'], true)) {
                    $statusHariIni['mode_masuk'] = $modeMasukStatus;
                }
            }
            if ($jenisStatus === 'Pulang') {
                $statusHariIni['Pulang'] = true;
            }
        }
        $stmtStatus->close();
    }
}

$presensiLengkapHariIni = $statusHariIni['Masuk'] && $statusHariIni['Pulang'];
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f4f8fc;
        color: #0f172a;
    }

    .asn-page {
        min-height: 100vh;
        padding-bottom: 6rem;
        background: #f4f8fc;
    }

    .asn-header {
        position: sticky;
        top: 0;
        z-index: 100;
        width: 100%;
        padding: 0;
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 2px 10px rgba(15, 23, 42, .05);
    }

    .asn-header-card {
        width: 100%;
        min-height: 64px;
        background: #fff;
        border: 0;
        border-radius: 0;
        box-shadow: none;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 12px;
    }

    .asn-header h1 {
        margin: 0;
        color: #0284c7 !important;
        font-size: 17px !important;
        line-height: 1.12 !important;
        font-weight: 900 !important;
        letter-spacing: -.01em;
    }

    .asn-header p {
        margin-top: 3px;
        color: #94a3b8 !important;
        font-size: 11px !important;
        line-height: 1.15 !important;
        font-weight: 700 !important;
    }

    .icon-btn {
        width: 40px;
        height: 40px;
        border: 0;
        border-radius: 999px;
        background: #eff8ff;
        color: #0284c7;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 40px;
        transition: .15s ease;
    }

    .icon-btn:hover {
        background: #e0f2fe;
    }

    .wrap {
        max-width: 520px;
        margin: 0 auto;
        padding: 10px 14px 28px;
    }

    .profile-card {
        border-radius: 34px;
        background: linear-gradient(135deg, #0284c7, #0ea5e9);
        color: #fff;
        padding: 22px;
        box-shadow: 0 20px 38px rgba(2, 132, 199, .24);
        position: relative;
        overflow: hidden;
    }

    .profile-card::after {
        content: "";
        position: absolute;
        right: -50px;
        top: -50px;
        width: 150px;
        height: 150px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .13);
    }

    .avatar {
        width: 58px;
        height: 58px;
        border-radius: 22px;
        background: rgba(255, 255, 255, .2);
        border: 1px solid rgba(255, 255, 255, .35);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        margin-bottom: 14px;
    }

    .profile-name {
        font-size: 20px;
        font-weight: 900;
        line-height: 1.2;
    }

    .profile-sub {
        margin-top: 5px;
        font-size: 12px;
        font-weight: 700;
        opacity: .86;
    }

    .clock-box {
        margin-top: 16px;
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 12px;
    }

    .clock-time {
        font-size: 30px;
        line-height: 1;
        font-weight: 900;
        letter-spacing: -.04em;
    }

    .clock-date {
        font-size: 11px;
        font-weight: 800;
        opacity: .86;
    }

    .card {
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 28px;
        padding: 16px;
        margin-top: 14px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .05);
    }

    .location-status {
        display: flex;
        align-items: center;
        gap: 13px;
    }

    .status-icon {
        width: 50px;
        height: 50px;
        border-radius: 18px;
        background: #e0f2fe;
        color: #0284c7;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .status-valid .status-icon {
        background: #dcfce7;
        color: #16a34a;
    }

    .status-invalid .status-icon {
        background: #fee2e2;
        color: #ef4444;
    }

    .status-title {
        font-size: 14px;
        font-weight: 900;
    }

    .status-desc {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        margin-top: 4px;
    }

    .distance-pill {
        margin-top: 12px;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 11px 13px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        font-size: 11px;
        font-weight: 900;
        color: #64748b;
    }

    .distance-pill strong {
        color: #0f172a;
        text-align: right;
    }

    .mode-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .mode-btn {
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        background: #f8fafc;
        padding: 15px 10px;
        font-size: 13px;
        font-weight: 900;
        color: #334155;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .mode-btn.active {
        background: #e0f2fe;
        border-color: #38bdf8;
        color: #0369a1;
        box-shadow: 0 8px 18px rgba(14, 165, 233, .13);
    }

    .mode-btn:disabled,
    .mode-btn.disabled {
        opacity: .48;
        cursor: not-allowed;
        background: #e2e8f0;
        color: #94a3b8;
        box-shadow: none;
    }

    .workmode-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .workmode-btn {
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        background: #f8fafc;
        padding: 14px 12px;
        font-size: 13px;
        font-weight: 900;
        color: #334155;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
        cursor: pointer;
        text-align: left;
    }

    .workmode-btn strong {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }

    .workmode-btn span {
        font-size: 10px;
        font-weight: 800;
        color: #64748b;
        line-height: 1.25;
    }

    .workmode-btn.active {
        background: #e0f2fe;
        border-color: #38bdf8;
        color: #0369a1;
        box-shadow: 0 8px 18px rgba(14, 165, 233, .13);
    }

    .workmode-btn.active span {
        color: #0369a1;
    }

    .workmode-btn:disabled,
    .workmode-btn.disabled {
        opacity: .55;
        cursor: not-allowed;
        background: #e2e8f0;
        color: #94a3b8;
        box-shadow: none;
    }

    .mode-note {
        margin-top: 12px;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 10px 12px;
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        line-height: 1.35;
    }

    .today-status-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .today-status-item {
        border-radius: 20px;
        padding: 13px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .today-status-item.done {
        background: #dcfce7;
        border-color: #bbf7d0;
    }

    .today-status-label {
        font-size: 11px;
        font-weight: 900;
        color: #64748b;
    }

    .today-status-value {
        margin-top: 4px;
        font-size: 13px;
        font-weight: 900;
        color: #0f172a;
    }

    .today-status-item.done .today-status-value {
        color: #16a34a;
    }

    .selfie-btn {
        width: 100%;
        border: 0;
        border-radius: 30px;
        padding: 22px 16px;
        background: linear-gradient(135deg, #0ea5e9, #0284c7);
        color: #fff;
        box-shadow: 0 18px 34px rgba(2, 132, 199, .26);
        font-size: 15px;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }

    .selfie-btn:disabled {
        background: #cbd5e1;
        box-shadow: none;
    }

    .selfie-icon {
        width: 48px;
        height: 48px;
        border-radius: 18px;
        background: rgba(255, 255, 255, .2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .preview-card {
        display: none;
        margin-top: 14px;
    }

    .preview-card img {
        width: 100%;
        max-height: 360px;
        object-fit: cover;
        border-radius: 24px;
        border: 1px solid #dbeafe;
    }

    .success-box {
        display: none;
        text-align: center;
        padding: 22px;
    }

    .success-icon {
        width: 74px;
        height: 74px;
        border-radius: 28px;
        background: #dcfce7;
        color: #16a34a;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 34px;
        margin-bottom: 12px;
    }

    .toast {
        position: fixed;
        top: 92px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 999;
        background: #0f172a;
        color: #fff;
        padding: 12px 18px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 900;
        opacity: 0;
        pointer-events: none;
        transition: .25s;
    }

    .toast.show {
        opacity: 1;
    }

    .loading-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .55);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .loading-card {
        background: #fff;
        border-radius: 26px;
        padding: 22px;
        width: 260px;
        text-align: center;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .22);
    }

    .spinner {
        width: 42px;
        height: 42px;
        border-radius: 999px;
        border: 4px solid #e0f2fe;
        border-top-color: #0284c7;
        animation: spin .8s linear infinite;
        margin: 0 auto 12px;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .webcam-modal {
        position: fixed;
        inset: 0;
        z-index: 9998;
        background: rgba(15, 23, 42, .72);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
    }

    .webcam-card {
        width: 100%;
        max-width: 520px;
        background: #fff;
        border-radius: 28px;
        padding: 16px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .28);
    }

    .webcam-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .webcam-title {
        font-size: 15px;
        font-weight: 900;
        color: #0f172a;
    }

    .webcam-sub {
        margin-top: 3px;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
    }

    .webcam-close {
        width: 38px;
        height: 38px;
        border: 0;
        border-radius: 999px;
        background: #f1f5f9;
        color: #334155;
    }

    #webcamVideo {
        width: 100%;
        max-height: 420px;
        object-fit: cover;
        border-radius: 22px;
        background: #0f172a;
    }

    .webcam-actions {
        margin-top: 12px;
    }

    .webcam-capture {
        width: 100%;
        border: 0;
        border-radius: 20px;
        padding: 14px 16px;
        background: #0284c7;
        color: #fff;
        font-size: 13px;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    @media (max-width: 520px) {
        .asn-header {
            padding: 0;
        }

        .asn-header-card {
            width: 100%;
            border-radius: 0;
            padding: 12px 14px;
            min-height: 62px;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            flex-basis: 38px;
        }

        .asn-header h1 {
            font-size: 16px !important;
        }

        .asn-header p {
            font-size: 10.5px !important;
        }

        .wrap {
            padding-top: 8px;
        }
    }
</style>

<div class="asn-page">
    <header class="asn-header">
        <div class="asn-header-card">
            <button type="button" onclick="window.history.back()" class="icon-btn">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <div class="min-w-0">
                <h1 class="text-[17px] font-black text-sky-600 leading-tight">Presensi</h1>
                <p class="text-[11px] font-bold text-slate-400 leading-tight">Lokasi otomatis + selfie watermark</p>
            </div>
        </div>
    </header>

    <main class="wrap">
        <form id="absenForm" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="simpan_presensi">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId); ?>">
            <input type="hidden" name="nama_petugas" value="<?= htmlspecialchars($namaPetugas); ?>">
            <input type="hidden" name="lokasi_presensi" id="lokasi_presensi" value="<?= htmlspecialchars($lokasi['nama_lokasi']); ?>">
            <input type="hidden" name="jenis_presensi" id="jenis_presensi">
            <input type="hidden" name="mode_kerja" id="mode_kerja" value="WFO">
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
            <input type="hidden" name="accuracy" id="accuracy">
            <input type="hidden" name="distance_meter" id="distance_meter">
            <input type="hidden" name="lokasi_valid" id="lokasi_valid" value="0">
            <input type="file" name="foto_presensi" id="fotoInput" accept="image/*" capture="user" class="hidden" required>

            <section class="profile-card">
                <div class="avatar"><i class="fa-solid fa-user"></i></div>
                <div class="profile-name"><?= htmlspecialchars($namaPetugas ?: 'Petugas'); ?></div>
                <div class="profile-sub"><?= htmlspecialchars($lokasi['nama_lokasi']); ?></div>
                <div class="clock-box">
                    <div>
                        <div id="clockTime" class="clock-time">--:--</div>
                        <div id="clockDate" class="clock-date">Memuat tanggal...</div>
                    </div>
                    <div class="text-right text-[11px] font-black opacity-90">
                        Radius<br><?= (int)$lokasi['radius_meter']; ?> m
                    </div>
                </div>
            </section>

            <section class="card">
                <div id="statusBox" class="location-status">
                    <div class="status-icon"><i class="fa-solid fa-location-crosshairs"></i></div>
                    <div>
                        <div id="statusTitle" class="status-title">Mengecek lokasi...</div>
                        <div id="statusDesc" class="status-desc">Mohon izinkan akses lokasi pada browser.</div>
                    </div>
                </div>

                <div class="distance-pill">
                    <span>Jarak dari titik</span>
                    <strong id="distanceText">-</strong>
                </div>
            </section>

            <section class="card">
                <div class="workmode-grid">
                    <button type="button" id="btnWFO" class="workmode-btn active" data-mode="WFO" onclick="selectWorkMode(this)">
                        <strong><i class="fa-solid fa-building"></i> WFO</strong>
                        <span>Presensi di kantor, wajib dalam radius lokasi.</span>
                    </button>
                    <button type="button" id="btnWFA" class="workmode-btn" data-mode="WFA" onclick="selectWorkMode(this)">
                        <strong><i class="fa-solid fa-house-laptop"></i> WFA</strong>
                        <span>Bekerja dari luar kantor, GPS tetap aktif.</span>
                    </button>
                </div>
                <div class="mode-note" id="workModeNote">Mode aktif: WFO. Presensi harus berada di dalam radius kantor.</div>
            </section>

            <section class="card">
                <div class="today-status-grid">
                    <div id="statusMasukCard" class="today-status-item <?= $statusHariIni['Masuk'] ? 'done' : ''; ?>">
                        <div class="today-status-label">Presensi Masuk</div>
                        <div class="today-status-value"><?= $statusHariIni['Masuk'] ? 'Sudah presensi' : 'Belum presensi'; ?></div>
                    </div>
                    <div id="statusPulangCard" class="today-status-item <?= $statusHariIni['Pulang'] ? 'done' : ''; ?>">
                        <div class="today-status-label">Presensi Pulang</div>
                        <div class="today-status-value"><?= $statusHariIni['Pulang'] ? 'Sudah presensi' : 'Belum presensi'; ?></div>
                    </div>
                </div>
                <div class="distance-pill" style="margin-top:12px">
                    <span>Status hari ini</span>
                    <strong id="statusHariIniText"><?= $presensiLengkapHariIni ? 'Lengkap' : 'Belum lengkap'; ?></strong>
                </div>
            </section>

            <section class="card">
                <div class="mode-grid">
                    <button type="button" id="btnMasuk" class="mode-btn" data-jenis="Masuk" onclick="selectJenis(this)">
                        <i class="fa-solid fa-right-to-bracket"></i> Masuk
                    </button>
                    <button type="button" id="btnPulang" class="mode-btn" data-jenis="Pulang" onclick="selectJenis(this)">
                        <i class="fa-solid fa-right-from-bracket"></i> Pulang
                    </button>
                </div>
            </section>

            <section class="card">
                <button id="selfieBtn" type="button" class="selfie-btn" onclick="openCamera()" disabled>
                    <span class="selfie-icon"><i class="fa-solid fa-camera"></i></span>
                    <span>Ambil Selfie</span>
                </button>

                <div id="previewCard" class="preview-card">
                    <img id="previewImg" alt="Foto presensi">
                </div>
            </section>

            <section id="successBox" class="card success-box">
                <div class="success-icon"><i class="fa-solid fa-check"></i></div>
                <div class="text-[18px] font-black text-slate-900">Presensi Berhasil</div>
                <div id="successText" class="text-[12px] font-bold text-slate-400 mt-1">Data berhasil disimpan</div>
            </section>
        </form>
    </main>
</div>


<div id="webcamModal" class="webcam-modal">
    <div class="webcam-card">
        <div class="webcam-head">
            <div>
                <div class="webcam-title">Ambil Foto Webcam</div>
                <div class="webcam-sub">Pastikan wajah terlihat jelas</div>
            </div>
            <button type="button" onclick="closeWebcam()" class="webcam-close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <video id="webcamVideo" autoplay playsinline muted></video>

        <div class="webcam-actions">
            <button type="button" onclick="captureWebcam()" class="webcam-capture">
                <i class="fa-solid fa-camera"></i>
                Ambil Foto
            </button>
        </div>
    </div>
</div>


<div id="toast" class="toast">Pesan</div>

<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-card">
        <div class="spinner"></div>
        <div class="text-sm font-black text-slate-900">Menyimpan Presensi</div>
        <div class="text-[11px] font-bold text-slate-400 mt-1">Watermark dan upload foto...</div>
    </div>
</div>

<script>
    const namaPetugas = <?= json_encode($namaPetugas); ?>;
    const TARGET_LOCATION = {
        nama: <?= json_encode($lokasi['nama_lokasi']); ?>,
        lat: <?= json_encode((float)$lokasi['latitude']); ?>,
        lng: <?= json_encode((float)$lokasi['longitude']); ?>,
        radius: <?= json_encode((float)$lokasi['radius_meter']); ?>
    };

    const STATUS_HARI_INI = <?= json_encode($statusHariIni); ?>;

    let lokasiValid = false;
    let gpsReady = false;
    let selectedJenis = '';
    let selectedWorkMode = STATUS_HARI_INI.mode_masuk || 'WFO';
    let watermarkedFile = null;

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2600);
    }

    function updateClock() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('clockTime').textContent = `${hh}:${mm}`;

        document.getElementById('clockDate').textContent = new Intl.DateTimeFormat('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        }).format(now);
    }

    function setStatus(type, title, desc) {
        const box = document.getElementById('statusBox');
        box.classList.remove('status-valid', 'status-invalid', 'status-loading');
        if (type) box.classList.add(type);

        document.getElementById('statusTitle').textContent = title;
        document.getElementById('statusDesc').textContent = desc;
    }

    function haversineMeter(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const toRad = deg => deg * Math.PI / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);

        const a =
            Math.sin(dLat / 2) ** 2 +
            Math.cos(toRad(lat1)) *
            Math.cos(toRad(lat2)) *
            Math.sin(dLon / 2) ** 2;

        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function autoCheckLocation() {
        if (!navigator.geolocation) {
            setStatus('status-invalid', 'GPS tidak tersedia', 'Browser tidak mendukung lokasi.');
            return;
        }

        setStatus('status-loading', 'Mengecek lokasi...', 'Mohon izinkan akses lokasi pada browser.');

        navigator.geolocation.getCurrentPosition(
            pos => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const acc = pos.coords.accuracy || 0;
                const distance = haversineMeter(lat, lng, TARGET_LOCATION.lat, TARGET_LOCATION.lng);
                const valid = distance <= TARGET_LOCATION.radius;

                lokasiValid = valid;
                gpsReady = true;

                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                document.getElementById('accuracy').value = acc;
                document.getElementById('distance_meter').value = distance.toFixed(2);
                document.getElementById('lokasi_valid').value = valid ? '1' : '0';
                document.getElementById('lokasi_presensi').value = TARGET_LOCATION.nama;

                document.getElementById('distanceText').textContent = `${Math.round(distance)} meter`;

                setStatus(
                    valid ? 'status-valid' : 'status-invalid',
                    valid ? 'Dalam Area Presensi' : 'Di Luar Area Presensi',
                    valid ? `Lokasi valid, jarak ${Math.round(distance)} meter.` : `Jarak ${Math.round(distance)} meter melebihi radius.`
                );

                updateSelfieButton();
            },
            err => {
                lokasiValid = false;
                gpsReady = false;
                document.getElementById('lokasi_valid').value = '0';
                updateSelfieButton();

                let msg = 'Gagal mengambil lokasi';
                if (err.code === 1) msg = 'Izin lokasi ditolak';
                if (err.code === 2) msg = 'Lokasi tidak tersedia';
                if (err.code === 3) msg = 'GPS timeout';

                setStatus('status-invalid', 'Lokasi Gagal', msg);
                showToast(msg);
            }, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    }

    function sudahPresensi(jenis) {
        return !!STATUS_HARI_INI[jenis];
    }

    function selectWorkMode(btn) {
        const mode = btn.dataset.mode;
        if (STATUS_HARI_INI.Masuk) {
            showToast('Mode kerja mengikuti presensi Masuk hari ini');
            return;
        }

        selectedWorkMode = mode === 'WFA' ? 'WFA' : 'WFO';
        document.getElementById('mode_kerja').value = selectedWorkMode;
        document.querySelectorAll('.workmode-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const note = document.getElementById('workModeNote');
        if (note) {
            note.textContent = selectedWorkMode === 'WFA' ?
                'Mode aktif: WFA. GPS tetap wajib aktif, tetapi tidak wajib berada dalam radius kantor.' :
                'Mode aktif: WFO. Presensi harus berada di dalam radius kantor.';
        }
        updateSelfieButton();
    }

    function updateWorkModeUI() {
        const mode = STATUS_HARI_INI.mode_masuk || selectedWorkMode || 'WFO';
        selectedWorkMode = mode;
        const input = document.getElementById('mode_kerja');
        if (input) input.value = mode;

        document.querySelectorAll('.workmode-btn').forEach(btn => {
            const active = btn.dataset.mode === mode;
            btn.classList.toggle('active', active);
            btn.disabled = !!STATUS_HARI_INI.Masuk;
            btn.classList.toggle('disabled', !!STATUS_HARI_INI.Masuk);
        });

        const note = document.getElementById('workModeNote');
        if (note) {
            if (STATUS_HARI_INI.Masuk) {
                note.textContent = 'Mode hari ini sudah ditentukan saat presensi Masuk: ' + mode + '. Presensi Pulang akan mengikuti mode yang sama.';
            } else {
                note.textContent = mode === 'WFA' ?
                    'Mode aktif: WFA. GPS tetap wajib aktif, tetapi tidak wajib berada dalam radius kantor.' :
                    'Mode aktif: WFO. Presensi harus berada di dalam radius kantor.';
            }
        }
    }

    function updateTodayStatusUI() {
        updateWorkModeUI();
        const masukDone = sudahPresensi('Masuk');
        const pulangDone = sudahPresensi('Pulang');
        const btnMasuk = document.getElementById('btnMasuk');
        const btnPulang = document.getElementById('btnPulang');

        if (btnMasuk) {
            btnMasuk.disabled = masukDone;
            btnMasuk.classList.toggle('disabled', masukDone);
        }

        if (btnPulang) {
            btnPulang.disabled = !masukDone || pulangDone;
            btnPulang.classList.toggle('disabled', !masukDone || pulangDone);
        }

        const statusText = document.getElementById('statusHariIniText');
        if (statusText) {
            statusText.textContent = masukDone && pulangDone ? 'Lengkap' : (masukDone ? 'Menunggu pulang' : 'Belum masuk');
        }

        const masukCard = document.getElementById('statusMasukCard');
        if (masukCard) {
            masukCard.classList.toggle('done', masukDone);
            const val = masukCard.querySelector('.today-status-value');
            if (val) val.textContent = masukDone ? 'Sudah presensi' : 'Belum presensi';
        }

        const pulangCard = document.getElementById('statusPulangCard');
        if (pulangCard) {
            pulangCard.classList.toggle('done', pulangDone);
            const val = pulangCard.querySelector('.today-status-value');
            if (val) val.textContent = pulangDone ? 'Sudah presensi' : 'Belum presensi';
        }
    }

    function selectJenis(btn) {
        const jenis = btn.dataset.jenis;

        if (btn.disabled || btn.classList.contains('disabled')) {
            if (jenis === 'Masuk' && sudahPresensi('Masuk')) {
                showToast('Anda sudah presensi Masuk hari ini');
                return;
            }
            if (jenis === 'Pulang' && !sudahPresensi('Masuk')) {
                showToast('Presensi Masuk harus dilakukan dulu');
                return;
            }
            if (jenis === 'Pulang' && sudahPresensi('Pulang')) {
                showToast('Anda sudah presensi Pulang hari ini');
                return;
            }
            showToast('Presensi hari ini sudah lengkap');
            return;
        }

        document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        selectedJenis = jenis;
        document.getElementById('jenis_presensi').value = selectedJenis;

        updateSelfieButton();
    }

    function updateSelfieButton() {
        const sudahLengkap = sudahPresensi('Masuk') && sudahPresensi('Pulang');
        const jenisTerkunci = selectedJenis && sudahPresensi(selectedJenis);
        const belumMasukUntukPulang = selectedJenis === 'Pulang' && !sudahPresensi('Masuk');
        const lokasiOkUntukMode = selectedWorkMode === 'WFA' ? gpsReady : lokasiValid;
        document.getElementById('selfieBtn').disabled = !(lokasiOkUntukMode && selectedJenis) || sudahLengkap || jenisTerkunci || belumMasukUntukPulang;
    }

    let webcamStream = null;

    async function openCamera() {
        if (!gpsReady) {
            showToast('GPS belum siap. Mohon izinkan akses lokasi.');
            return;
        }

        if (selectedWorkMode === 'WFO' && !lokasiValid) {
            showToast('Mode WFO wajib berada di dalam radius kantor. Pilih WFA jika sedang bekerja dari luar kantor.');
            return;
        }

        if (!selectedJenis) {
            showToast('Pilih Masuk atau Pulang dulu');
            return;
        }

        if (sudahPresensi(selectedJenis)) {
            showToast('Anda sudah presensi ' + selectedJenis + ' hari ini');
            return;
        }

        if (selectedJenis === 'Pulang' && !sudahPresensi('Masuk')) {
            showToast('Presensi Masuk harus dilakukan dulu');
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showToast('Kamera langsung tidak didukung, membuka upload sebagai cadangan');
            document.getElementById('fotoInput').click();
            return;
        }

        try {
            webcamStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: {
                        ideal: 1280
                    },
                    height: {
                        ideal: 720
                    }
                },
                audio: false
            });

            const video = document.getElementById('webcamVideo');
            video.srcObject = webcamStream;

            document.getElementById('webcamModal').style.display = 'flex';
        } catch (err) {
            console.error(err);
            showToast('Kamera langsung gagal, membuka upload sebagai cadangan');
            document.getElementById('fotoInput').click();
        }
    }

    function closeWebcam() {
        const modal = document.getElementById('webcamModal');
        const video = document.getElementById('webcamVideo');

        if (webcamStream) {
            webcamStream.getTracks().forEach(track => track.stop());
            webcamStream = null;
        }

        if (video) {
            video.srcObject = null;
        }

        if (modal) {
            modal.style.display = 'none';
        }
    }

    async function captureWebcam() {
        const video = document.getElementById('webcamVideo');

        if (!video || !video.videoWidth || !video.videoHeight) {
            showToast('Kamera belum siap');
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', .9));
        const file = new File([blob], `webcam_${Date.now()}.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now()
        });

        closeWebcam();
        await processSelectedPhoto(file);
    }

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function timestampText() {
        const now = new Date();
        return `${pad(now.getDate())}-${pad(now.getMonth() + 1)}-${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())} WIB`;
    }

    function loadImage(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);

            img.onload = () => {
                URL.revokeObjectURL(url);
                resolve(img);
            };

            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Gagal membaca foto'));
            };

            img.src = url;
        });
    }

    async function createWatermarkedPhoto(file) {
        const img = await loadImage(file);

        const maxSize = 1600;
        let w = img.naturalWidth || img.width;
        let h = img.naturalHeight || img.height;

        if (w > h && w > maxSize) {
            h = Math.round(h * (maxSize / w));
            w = maxSize;
        } else if (h >= w && h > maxSize) {
            w = Math.round(w * (maxSize / h));
            h = maxSize;
        }

        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);

        const lines = [
            'Presensi',
            `Jenis: ${selectedJenis || '-'}`,
            `Mode: ${selectedWorkMode || 'WFO'}`,
            `Waktu: ${timestampText()}`,
            `Nama: ${namaPetugas || '-'}`,
            `Lokasi: ${TARGET_LOCATION.nama}`,
            `GPS: ${document.getElementById('latitude').value || '-'}, ${document.getElementById('longitude').value || '-'}`,
            `Jarak: ${document.getElementById('distance_meter').value || '-'} meter`
        ];

        const font = Math.max(22, Math.round(w * 0.025));
        const padBox = Math.max(18, Math.round(w * 0.018));
        const lineH = Math.round(font * 1.35);
        const boxH = padBox * 2 + lineH * lines.length;
        const y = h - boxH;

        ctx.fillStyle = 'rgba(15, 23, 42, .74)';
        ctx.fillRect(0, y, w, boxH);

        ctx.fillStyle = '#fff';
        ctx.textBaseline = 'top';
        ctx.font = `800 ${font}px Arial, sans-serif`;

        lines.forEach((line, i) => {
            ctx.fillText(line, padBox, y + padBox + i * lineH);
        });

        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', .88));
        return new File([blob], `absensi_asn_${Date.now()}.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now()
        });
    }

    function syncWatermarkedFile() {
        const input = document.getElementById('fotoInput');
        if (!watermarkedFile) return;

        const dt = new DataTransfer();
        dt.items.add(watermarkedFile);
        input.files = dt.files;
    }

    async function uploadPresensi() {
        const overlay = document.getElementById('loadingOverlay');
        overlay.style.display = 'flex';

        try {
            syncWatermarkedFile();

            const form = document.getElementById('absenForm');
            const formData = new FormData(form);

            // Jangan pakai form.action langsung, karena input name="action" bisa menimpa property form.action
            const submitUrl = form.getAttribute('action') || window.location.pathname;

            const res = await fetch(submitUrl, {
                method: 'POST',
                body: formData
            });

            const text = await res.text();

            if (!res.ok || text.trim() !== 'OK') {
                console.error(text);
                throw new Error(text || 'Gagal menyimpan presensi');
            }

            STATUS_HARI_INI[selectedJenis] = true;
            if (selectedJenis === 'Masuk') STATUS_HARI_INI.mode_masuk = selectedWorkMode;
            updateTodayStatusUI();

            document.getElementById('successBox').style.display = 'block';
            document.getElementById('successText').textContent = `${selectedJenis} berhasil disimpan pukul ${document.getElementById('clockTime').textContent} WIB`;
            showToast('Presensi berhasil');

            setTimeout(() => {
                window.location.reload();
            }, 2500);
        } catch (err) {
            console.error(err);
            showToast(err.message || 'Gagal menyimpan presensi');
        } finally {
            overlay.style.display = 'none';
        }
    }

    async function processSelectedPhoto(file) {
        if (!file) return;

        if (!gpsReady || !selectedJenis) {
            showToast('GPS atau jenis presensi belum valid');
            return;
        }

        if (selectedWorkMode === 'WFO' && !lokasiValid) {
            showToast('Mode WFO wajib berada di dalam radius kantor');
            return;
        }

        try {
            document.getElementById('loadingOverlay').style.display = 'flex';

            watermarkedFile = await createWatermarkedPhoto(file);
            syncWatermarkedFile();

            const url = URL.createObjectURL(watermarkedFile);
            document.getElementById('previewImg').src = url;
            document.getElementById('previewCard').style.display = 'block';

            await uploadPresensi();
        } catch (err) {
            console.error(err);
            showToast('Gagal memproses foto');
        } finally {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    }

    document.getElementById('fotoInput').addEventListener('change', async function() {
        const file = this.files && this.files[0];
        if (!file) return;

        await processSelectedPhoto(file);
        this.value = '';
    });

    updateClock();
    updateTodayStatusUI();
    setInterval(updateClock, 30000);
    window.addEventListener('load', autoCheckLocation);
    window.addEventListener('pagehide', closeWebcam);
</script>

<?php include 'footer.php'; ?>