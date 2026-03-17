<?php
// =====================================================================
// buku_tamu.php — Buku Tamu Digital (public, no login required)
// =====================================================================
session_start();
require 'config.php';
date_default_timezone_set('Asia/Jakarta');

$pesan     = '';
$pesanTipe = '';
$submitted = isset($_GET['sukses']) && $_GET['sukses'] === '1';

$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = htmlspecialchars(trim($_POST['nama']      ?? ''), ENT_QUOTES);
    $email     = filter_var(trim($_POST['email']   ?? ''), FILTER_SANITIZE_EMAIL);
    $asal      = htmlspecialchars(trim($_POST['asal']      ?? ''), ENT_QUOTES);
    $no_hp     = preg_replace('/[^0-9+\-\s()]/', '', trim($_POST['no_hp'] ?? ''));
    $jenis     = trim($_POST['jenis_layanan'] ?? '');
    $keperluan = htmlspecialchars(trim($_POST['keperluan'] ?? ''), ENT_QUOTES);

    $jenisValid = ['pelayanan_umum', 'pelayanan_informasi', 'pelayanan_pengaduan'];

    if (!$nama || !$email || !$asal || !$no_hp || !$jenis || !$keperluan) {
        $pesan = 'Semua field wajib diisi.';
        $pesanTipe = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan = 'Format email tidak valid.';
        $pesanTipe = 'error';
    } elseif (!in_array($jenis, $jenisValid)) {
        $pesan = 'Jenis layanan tidak valid.';
        $pesanTipe = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO buku_tamu (nama, email, asal, no_hp, jenis_layanan, keperluan, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssssss', $nama, $email, $asal, $no_hp, $jenis, $keperluan, $ipHash);
        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['tamu_nama'] = $nama;
            header('Location: buku_tamu.php?sukses=1');
            exit;
        } else {
            $pesan = 'Gagal menyimpan. Silakan coba lagi.';
            $pesanTipe = 'error';
        }
        $stmt->close();
    }
}

$jenisLabel = [
    'pelayanan_umum'      => 'Pelayanan Umum',
    'pelayanan_informasi' => 'Pelayanan Informasi',
    'pelayanan_pengaduan' => 'Pelayanan Pengaduan',
];
$jenisIcon = [
    'pelayanan_umum'      => 'fa-users',
    'pelayanan_informasi' => 'fa-circle-info',
    'pelayanan_pengaduan' => 'fa-bullhorn',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Buku Tamu — PTSP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --blue: #1d4ed8;
            --blue-md: #2563eb;
            --blue-lt: #eff6ff;
            --blue-dk: #1e3a8a;
            --teal: #0891b2;
            --ink: #0f172a;
            --ink-2: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --bg: #f1f5f9;
            --white: #ffffff;
            --red: #ef4444;
            --green: #10b981;
            --radius: 18px;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ===================== STATUS BAR AREA ===================== */
        .status-bar {
            background: var(--blue-dk);
            height: env(safe-area-inset-top, 0px);
        }

        /* ===================== TOP NAV ===================== */
        .top-nav {
            background: var(--blue-dk);
            padding: 14px 20px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-logo-icon {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, .15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .2);
        }

        .nav-logo-text {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -.01em;
        }

        .nav-logo-sub {
            font-size: 10px;
            color: rgba(255, 255, 255, .55);
            font-weight: 400;
        }

        .nav-time {
            font-size: 11px;
            color: rgba(255, 255, 255, .7);
            font-weight: 500;
            text-align: right;
        }

        /* ===================== HERO BANNER ===================== */
        .hero {
            background: linear-gradient(160deg, var(--blue-dk) 0%, var(--blue-md) 55%, var(--teal) 100%);
            padding: 28px 20px 80px;
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 48px;
            background: var(--bg);
            border-radius: 32px 32px 0 0;
        }

        /* decorative circles */
        .hero-deco {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, .1);
        }

        .hero-deco-1 {
            width: 220px;
            height: 220px;
            top: -80px;
            right: -60px;
        }

        .hero-deco-2 {
            width: 140px;
            height: 140px;
            top: 20px;
            right: 40px;
            background: rgba(255, 255, 255, .04);
        }

        .hero-deco-3 {
            width: 80px;
            height: 80px;
            bottom: 40px;
            left: 20px;
        }

        .hero-inner {
            position: relative;
            z-index: 1;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .2);
            backdrop-filter: blur(8px);
            color: rgba(255, 255, 255, .9);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            padding: 5px 12px 5px 8px;
            border-radius: 999px;
            margin-bottom: 14px;
        }

        .hero-chip-dot {
            width: 6px;
            height: 6px;
            background: #4ade80;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .5;
                transform: scale(.8);
            }
        }

        .hero-title {
            font-size: 26px;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
            letter-spacing: -.02em;
            margin-bottom: 8px;
        }

        .hero-title span {
            background: linear-gradient(90deg, #93c5fd, #67e8f9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            font-size: 12px;
            color: rgba(255, 255, 255, .65);
            line-height: 1.65;
            font-weight: 400;
            max-width: 320px;
        }

        /* ===================== FORM SECTIONS ===================== */
        .form-wrap {
            padding: 0 20px 32px;
        }

        .section-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 14px;
            box-shadow: 0 1px 8px rgba(15, 23, 42, .06);
            opacity: 0;
            transform: translateY(16px);
            animation: cardIn .4s ease forwards;
        }

        .section-card:nth-child(1) {
            animation-delay: .05s;
        }

        .section-card:nth-child(2) {
            animation-delay: .12s;
        }

        .section-card:nth-child(3) {
            animation-delay: .19s;
        }

        .section-card:nth-child(4) {
            animation-delay: .26s;
        }

        @keyframes cardIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        .section-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .icon-blue {
            background: var(--blue-lt);
            color: var(--blue-md);
        }

        .icon-teal {
            background: #ecfeff;
            color: var(--teal);
        }

        .icon-amber {
            background: #fffbeb;
            color: #d97706;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
        }

        .section-sub {
            font-size: 10px;
            color: var(--muted);
            margin-top: 1px;
        }

        /* ===================== FIELDS ===================== */
        .field {
            margin-bottom: 14px;
        }

        .field:last-child {
            margin-bottom: 0;
        }

        .field-label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 7px;
            letter-spacing: .02em;
        }

        .field-label .req {
            color: var(--red);
            font-size: 13px;
            line-height: 1;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: var(--muted);
            pointer-events: none;
            transition: color .2s;
        }

        .input-wrap:focus-within .input-icon {
            color: var(--blue-md);
        }

        .field-input,
        .field-textarea {
            width: 100%;
            padding: 13px 14px 13px 40px;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            font-size: 13px;
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--ink);
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
            appearance: none;
            -webkit-appearance: none;
        }

        .field-input::placeholder,
        .field-textarea::placeholder {
            color: #94a3b8;
        }

        .field-input:focus,
        .field-textarea:focus {
            border-color: var(--blue-md);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
            background: var(--white);
        }

        .field-input.valid {
            border-color: var(--green);
        }

        .field-input.invalid {
            border-color: var(--red);
        }

        .field-textarea {
            padding-left: 40px;
            resize: none;
            min-height: 96px;
            line-height: 1.65;
        }

        .field-hint {
            font-size: 10px;
            color: var(--muted);
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
        }

        /* ===================== LAYANAN PILLS ===================== */
        .layanan-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .layanan-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            cursor: pointer;
            transition: all .2s;
            background: var(--bg);
            position: relative;
            overflow: hidden;
        }

        .layanan-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(37, 99, 235, .06), rgba(8, 145, 178, .04));
            opacity: 0;
            transition: opacity .2s;
        }

        .layanan-item:has(input:checked) {
            border-color: var(--blue-md);
            background: var(--white);
            box-shadow: 0 2px 12px rgba(37, 99, 235, .12);
        }

        .layanan-item:has(input:checked)::before {
            opacity: 1;
        }

        .layanan-item input {
            display: none;
        }

        .layanan-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s;
            background: var(--white);
        }

        .layanan-item:has(input:checked) .layanan-radio {
            border-color: var(--blue-md);
            background: var(--blue-md);
        }

        .layanan-item:has(input:checked) .layanan-radio::after {
            content: '';
            width: 7px;
            height: 7px;
            background: #fff;
            border-radius: 50%;
        }

        .layanan-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--blue-lt);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--blue-md);
            flex-shrink: 0;
            transition: all .2s;
        }

        .layanan-item:has(input:checked) .layanan-icon-wrap {
            background: var(--blue-md);
            color: #fff;
        }

        .layanan-text {
            flex: 1;
        }

        .layanan-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 2px;
        }

        .layanan-desc {
            font-size: 10px;
            color: var(--muted);
            line-height: 1.5;
        }

        .layanan-check {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--blue-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
            opacity: 0;
            transform: scale(0);
            transition: all .2s;
            flex-shrink: 0;
        }

        .layanan-item:has(input:checked) .layanan-check {
            opacity: 1;
            transform: scale(1);
        }

        /* ===================== ALERT ===================== */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 16px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i {
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ===================== SUBMIT BUTTON ===================== */
        .btn-section {
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 14px;
            box-shadow: 0 1px 8px rgba(15, 23, 42, .06);
            animation: cardIn .4s .3s ease forwards;
            opacity: 0;
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--blue-md), var(--teal));
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            cursor: pointer;
            transition: opacity .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 20px rgba(37, 99, 235, .35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: -.01em;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, .12), transparent);
        }

        .btn-submit:hover {
            box-shadow: 0 6px 24px rgba(37, 99, 235, .45);
            opacity: .95;
        }

        .btn-submit:active {
            transform: scale(.98);
        }

        .btn-submit:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .privacy-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 10px;
            color: var(--muted);
            margin-top: 12px;
        }

        @keyframes popIn {
            from {
                transform: scale(0) rotate(-20deg);
                opacity: 0;
            }

            to {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }
    </style>
</head>

<body>

    <!-- TOP NAV -->
    <div class="top-nav">
        <div class="nav-logo">
            <div class="nav-logo-icon">
                <img src="assets/MA_Corpu.png" alt="Logo" style="width:24px; height:24px; object-fit:contain;">
            </div>
            <div>
                <div class="nav-logo-text">PTSP Badan Strajak Diklat Kumdil</div>
                <div class="nav-logo-sub">Pelayanan Terpadu Satu Pintu</div>
            </div>
        </div>
        <div class="nav-time" id="navTime">
            <div id="clockTime"><?= date('H:i') ?></div>
            <div><?= date('d M Y') ?></div>
        </div>
    </div>

    <?php if ($submitted): ?>

        <!-- ===================== SUCCESS ===================== -->
        <div style="min-height: calc(100vh - 64px); display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 40px 24px; text-align:center;">

            <div style="
        width: 96px; height: 96px;
        background: linear-gradient(135deg, var(--blue-md), var(--teal));
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 38px; color: #fff;
        margin-bottom: 28px;
        box-shadow: 0 8px 32px rgba(37,99,235,.35);
        animation: popIn .5s cubic-bezier(.34,1.56,.64,1) both;
    ">
                <i class="fa-solid fa-check"></i>
            </div>

            <h1 style="
        font-size: 26px; font-weight: 800;
        color: var(--ink); letter-spacing:-.02em;
        margin-bottom: 12px;
        animation: cardIn .4s .15s ease both;
    ">Kunjungan Tercatat!</h1>

            <p style="
        font-size: 14px; color: var(--muted);
        line-height: 1.7; max-width: 280px;
        animation: cardIn .4s .25s ease both;
    ">Terima kasih, <strong style="color:var(--ink);"><?= htmlspecialchars(explode(' ', $_SESSION['tamu_nama'] ?? 'Tamu')[0]) ?></strong>. Kunjungan Anda telah berhasil dicatat dalam sistem kami.</p>

            <div style="
        margin-top: 32px;
        background: var(--blue-lt);
        border-radius: 16px;
        padding: 16px 24px;
        display: inline-flex; align-items: center; gap: 10px;
        animation: cardIn .4s .35s ease both;
    ">
                <i class="fa-solid fa-clock" style="color:var(--blue-md); font-size:16px;"></i>
                <span style="font-size:13px; font-weight:600; color:var(--blue-dk);"><?= date('d M Y, H:i') ?> WIB</span>
            </div>

        </div>

    <?php else: ?>

        <!-- ===================== HERO ===================== -->
        <div class="hero">
            <div class="hero-deco hero-deco-1"></div>
            <div class="hero-deco hero-deco-2"></div>
            <div class="hero-deco hero-deco-3"></div>
            <div class="hero-inner">
                <div class="hero-chip">
                    <div class="hero-chip-dot"></div>
                    Layanan Aktif
                </div>
                <h1 class="hero-title">Buku Tamu<br><span>Digital</span></h1>
                <p class="hero-sub">Isi formulir di bawah untuk mencatat kunjungan Anda ke layanan kami.</p>
            </div>
        </div>

        <!-- FORM -->
        <div class="form-wrap">

            <?php if ($pesan): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= $pesan ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="formTamu" novalidate>

                <!-- IDENTITAS -->
                <div class="section-card">
                    <div class="section-head">
                        <div class="section-icon icon-blue"><i class="fa-solid fa-id-card"></i></div>
                        <div>
                            <div class="section-title">Data Identitas</div>
                            <div class="section-sub">Informasi dasar pengunjung</div>
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label"><i class="fa-solid fa-user" style="font-size:10px;"></i> Nama Lengkap <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="input-icon fa-solid fa-user"></i>
                            <input type="text" name="nama" class="field-input" id="inputNama"
                                placeholder="Masukkan nama lengkap"
                                value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                                maxlength="100" required autocomplete="name">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label"><i class="fa-solid fa-envelope" style="font-size:10px;"></i> Alamat Email <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="input-icon fa-solid fa-envelope"></i>
                            <input type="email" name="email" class="field-input" id="inputEmail"
                                placeholder="contoh@email.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                maxlength="150" required autocomplete="email">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label"><i class="fa-solid fa-building" style="font-size:10px;"></i> Asal / Instansi / Unit Kerja <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="input-icon fa-solid fa-building"></i>
                            <input type="text" name="asal" class="field-input" id="inputAsal"
                                placeholder="Contoh: Dinas Pendidikan / PT. XYZ"
                                value="<?= htmlspecialchars($_POST['asal'] ?? '') ?>"
                                maxlength="150" required>
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label"><i class="fa-solid fa-phone" style="font-size:10px;"></i> Nomor Handphone <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="input-icon fa-solid fa-phone"></i>
                            <input type="tel" name="no_hp" class="field-input" id="inputHp"
                                placeholder="Contoh: 08123456789"
                                value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>"
                                maxlength="20" required autocomplete="tel">
                        </div>
                    </div>
                </div>

                <!-- JENIS LAYANAN -->
                <div class="section-card">
                    <div class="section-head">
                        <div class="section-icon icon-teal"><i class="fa-solid fa-list-check"></i></div>
                        <div>
                            <div class="section-title">Jenis Layanan</div>
                            <div class="section-sub">Pilih layanan yang Anda butuhkan</div>
                        </div>
                    </div>
                    <div class="layanan-grid">
                        <label class="layanan-item">
                            <input type="radio" name="jenis_layanan" value="pelayanan_umum"
                                <?= (($_POST['jenis_layanan'] ?? '') === 'pelayanan_umum') ? 'checked' : '' ?>>
                            <div class="layanan-radio"></div>
                            <div class="layanan-icon-wrap"><i class="fa-solid fa-users"></i></div>
                            <div class="layanan-text">
                                <div class="layanan-name">Pelayanan Umum</div>
                                <div class="layanan-desc">Administrasi & keperluan umum lainnya</div>
                            </div>
                            <div class="layanan-check"><i class="fa-solid fa-check"></i></div>
                        </label>

                        <label class="layanan-item">
                            <input type="radio" name="jenis_layanan" value="pelayanan_informasi"
                                <?= (($_POST['jenis_layanan'] ?? '') === 'pelayanan_informasi') ? 'checked' : '' ?>>
                            <div class="layanan-radio"></div>
                            <div class="layanan-icon-wrap"><i class="fa-solid fa-circle-info"></i></div>
                            <div class="layanan-text">
                                <div class="layanan-name">Pelayanan Informasi</div>
                                <div class="layanan-desc">Permintaan data, informasi & dokumen resmi</div>
                            </div>
                            <div class="layanan-check"><i class="fa-solid fa-check"></i></div>
                        </label>

                        <label class="layanan-item">
                            <input type="radio" name="jenis_layanan" value="pelayanan_pengaduan"
                                <?= (($_POST['jenis_layanan'] ?? '') === 'pelayanan_pengaduan') ? 'checked' : '' ?>>
                            <div class="layanan-radio"></div>
                            <div class="layanan-icon-wrap"><i class="fa-solid fa-bullhorn"></i></div>
                            <div class="layanan-text">
                                <div class="layanan-name">Pelayanan Pengaduan</div>
                                <div class="layanan-desc">Laporan, keluhan & saran perbaikan</div>
                            </div>
                            <div class="layanan-check"><i class="fa-solid fa-check"></i></div>
                        </label>
                    </div>
                </div>

                <!-- KEPERLUAN -->
                <div class="section-card">
                    <div class="section-head">
                        <div class="section-icon icon-amber"><i class="fa-solid fa-file-lines"></i></div>
                        <div>
                            <div class="section-title">Tujuan & Keperluan</div>
                            <div class="section-sub">Jelaskan maksud kunjungan Anda</div>
                        </div>
                    </div>
                    <div class="field">
                        <div class="input-wrap">
                            <i class="input-icon fa-solid fa-pen-to-square" style="top:18px;transform:none;"></i>
                            <textarea name="keperluan" class="field-textarea" id="keperluanInput"
                                placeholder="Contoh: Mengambil dokumen perizinan, rapat koordinasi, konsultasi..."
                                required><?= htmlspecialchars($_POST['keperluan'] ?? '') ?></textarea>
                        </div>
                        <div class="field-hint">
                            <span>Deskripsikan tujuan kunjungan secara singkat</span>
                        </div>
                    </div>
                </div>

                <!-- SUBMIT -->
                <div class="btn-section">
                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <i class="fa-solid fa-paper-plane"></i>
                        Kirim & Catat Kunjungan
                    </button>
                    <div class="privacy-note">
                        <i class="fa-solid fa-shield-halved" style="color:var(--green);"></i>
                        Data Anda aman &amp; hanya digunakan untuk pencatatan kunjungan
                    </div>
                </div>

            </form>

        </div>

    <?php endif; ?>

    <script>
        // Clock
        function updateClock() {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            const el = document.getElementById('clockTime');
            if (el) el.textContent = hh + ':' + mm;
        }
        setInterval(updateClock, 10000);

        // Client-side validation + disable double submit
        const form = document.getElementById('formTamu');
        if (form) {
            form.addEventListener('submit', function(e) {
                const nama = (document.getElementById('inputNama')?.value || '').trim();
                const email = (document.getElementById('inputEmail')?.value || '').trim();
                const asal = (document.getElementById('inputAsal')?.value || '').trim();
                const hp = (document.getElementById('inputHp')?.value || '').trim();
                const jenis = document.querySelector('[name=jenis_layanan]:checked');
                const kep = (document.getElementById('keperluanInput')?.value || '').trim();

                if (!nama || !email || !asal || !hp || !jenis || !kep) {
                    e.preventDefault();
                    alert('Semua field wajib diisi termasuk jenis layanan.');
                    return;
                }
                const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRe.test(email)) {
                    e.preventDefault();
                    alert('Format email tidak valid.');
                    return;
                }
                const btn = document.getElementById('btnSubmit');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';
                }
            });
        }
    </script>
</body>

</html>