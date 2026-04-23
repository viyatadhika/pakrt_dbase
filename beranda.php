<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
$activePage = basename($_SERVER['PHP_SELF']);

$title = "Beranda";
include 'header.php';

$namaLengkap = $_SESSION['user']['nama'] ?? '';
$namaDepan   = explode(' ', trim($namaLengkap))[0];
$fotoProfil  = $_SESSION['user']['foto_profil'] ?? null;

// Inisial nama
$parts   = explode(" ", trim($namaLengkap));
$initial = strtoupper(substr($parts[0], 0, 1));
$initial .= count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';

/* ===================== SUMMARY DASHBOARD ===================== */
$summaryResult = $conn->query("
    SELECT
        COUNT(*)                                                        AS total,
        COUNT(DISTINCT nama_petugas)                                    AS total_petugas,
        COUNT(DISTINCT form_type)                                       AS total_form,
        COUNT(DISTINCT CASE WHEN area_kerja <> '' THEN area_kerja END)  AS total_area
    FROM checklist_forms
");

if ($summaryResult) {
    $summary      = $summaryResult->fetch_assoc();
    $total        = $summary['total']         ?? 0;
    $totalPetugas = $summary['total_petugas'] ?? 0;
    $totalForm    = $summary['total_form']    ?? 0;
    $totalArea    = $summary['total_area']    ?? 0;
} else {
    $total = $totalPetugas = $totalForm = $totalArea = 0;
}

/* ===================== GRAFIK JENIS FORM ===================== */
$qGrafik = $conn->query("
    SELECT form_type, COUNT(*) AS total
    FROM checklist_forms
    GROUP BY form_type
    ORDER BY total DESC
");

$chartLabels = [];
$chartValues = [];

if ($qGrafik) {
    while ($row = $qGrafik->fetch_assoc()) {
        $chartLabels[] = $row['form_type'];
        $chartValues[] = $row['total'];
    }
}

/* ===================== GRAFIK AREA KERJA ===================== */
$qAreaChart = $conn->query("
    SELECT area_kerja, COUNT(*) AS total
    FROM checklist_forms
    WHERE area_kerja IS NOT NULL AND area_kerja <> ''
    GROUP BY area_kerja
    ORDER BY total DESC
");

$areaLabels = [];
$areaValues = [];

if ($qAreaChart) {
    while ($row = $qAreaChart->fetch_assoc()) {
        $areaLabels[] = $row['area_kerja'];
        $areaValues[] = $row['total'];
    }
}
?>

<style>
    .bg-red {
        background: #ffe4e6;
        color: #dc2626;
    }

    .bg-teal {
        background: #ccfbf1;
        color: #0d9488;
    }

    .bg-yellow {
        background: #fef9c3;
        color: #ca8a04;
    }

    .bg-indigo {
        background: #e0e7ff;
        color: #4338ca;
    }

    /* ===== MODAL STATE ===== */
    body.modal-open {
        overflow: hidden;
    }

    body.modal-open nav,
    body.modal-open .bottom-nav,
    body.modal-open .navbar,
    body.modal-open #bottomNav,
    body.modal-open #navMonitoring {
        filter: blur(4px);
        pointer-events: none;
        transition: .3s ease;
    }

    body.modal-open .page-container,
    body.modal-open header {
        transition: .3s ease;
    }

    /* ===== FAB AI ===== */
    .fab-ai {
        position: fixed;
        right: 18px;
        bottom: calc(88px + env(safe-area-inset-bottom, 0px));
        z-index: 9998;
        width: 58px;
        height: 58px;
        border: none;
        border-radius: 999px;
        background: linear-gradient(135deg, #0ea5e9, #2563eb);
        color: #fff;
        box-shadow: 0 10px 30px rgba(37, 99, 235, .35);
        cursor: pointer;
        transition: .25s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .fab-ai:hover {
        transform: translateY(-2px) scale(1.03);
    }

    .fab-ai i {
        font-size: 22px;
        line-height: 1;
    }

    .fab-ai-label {
        position: absolute;
        opacity: 0;
        pointer-events: none;
        white-space: nowrap;
        font-size: 13px;
        font-weight: 700;
    }

    .fab-ai.show-label {
        width: auto;
        min-width: 58px;
        padding: 0 18px;
        gap: 10px;
        justify-content: center;
    }

    .fab-ai.show-label .fab-ai-label {
        position: static;
        opacity: 1;
        pointer-events: auto;
    }

    .ai-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .35);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 9998;
        opacity: 0;
        pointer-events: none;
        transition: .25s ease;
    }

    .ai-overlay.show {
        opacity: 1;
        pointer-events: auto;
    }

    .ai-sheet {
        position: fixed;
        left: 0;
        right: 0;
        bottom: -110%;
        z-index: 9999;
        background: #fff;
        border-top-left-radius: 24px;
        border-top-right-radius: 24px;
        box-shadow: 0 -10px 40px rgba(0, 0, 0, .18);
        padding: 10px 14px calc(14px + env(safe-area-inset-bottom, 0px));
        height: min(82vh, calc(var(--app-vh, 1vh) * 82));
        max-height: min(82vh, calc(var(--app-vh, 1vh) * 82));
        display: flex;
        flex-direction: column;
        transition: transform .3s ease, bottom .3s ease, height .2s ease;
        will-change: transform, bottom, height;
    }

    .ai-sheet.show {
        bottom: 0;
    }

    .ai-sheet.keyboard-open {
        height: min(96vh, calc(var(--app-vh, 1vh) * 96));
        max-height: min(96vh, calc(var(--app-vh, 1vh) * 96));
    }

    .ai-sheet-handle {
        width: 42px;
        height: 5px;
        background: #d1d5db;
        border-radius: 999px;
        margin: 4px auto 12px;
    }

    .ai-header {
        position: sticky;
        top: 0;
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        padding-bottom: 8px;
        background: #fff;
    }

    .ai-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .ai-icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        background: linear-gradient(135deg, #e0f2fe, #dbeafe);
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .ai-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: #111827;
    }

    .ai-header p {
        margin: 2px 0 0;
        font-size: 12px;
        color: #6b7280;
    }

    .ai-close {
        width: 38px;
        height: 38px;
        border: none;
        border-radius: 50%;
        background: #f3f4f6;
        color: #4b5563;
        cursor: pointer;
    }

    .ai-body {
        flex: 1;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        padding: 6px 2px 14px;
        min-height: 0;
        max-height: none;
        scroll-padding-bottom: 120px;
    }

    .ai-message {
        display: flex;
        margin-bottom: 10px;
    }

    .ai-message.ai-user {
        justify-content: flex-end;
    }

    .ai-message.ai-bot {
        justify-content: flex-start;
    }

    .ai-bubble {
        max-width: 85%;
        padding: 12px 14px;
        border-radius: 18px;
        font-size: 13px;
        line-height: 1.55;
        white-space: pre-wrap;
    }

    .ai-user .ai-bubble {
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        color: #fff;
        border-bottom-right-radius: 6px;
    }

    .ai-bot .ai-bubble {
        background: #f3f4f6;
        color: #111827;
        border-bottom-left-radius: 6px;
    }

    .ai-suggestions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
        padding-bottom: 6px;
    }

    .ai-chip {
        border: none;
        border-radius: 999px;
        padding: 10px 12px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .ai-input-wrap {
        position: sticky;
        bottom: 0;
        z-index: 4;
        display: flex;
        align-items: flex-end;
        gap: 10px;
        padding: 10px 0 calc(2px + env(safe-area-inset-bottom, 0px));
        border-top: 1px solid #e5e7eb;
        background: #fff;
    }

    .ai-input {
        flex: 1;
        min-height: 46px;
        border: 1px solid #d1d5db;
        border-radius: 14px;
        padding: 12px 14px;
        font-size: 13px;
        line-height: 1.4;
        outline: none;
        background: #fff;
    }

    .ai-input:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96, 165, 250, .15);
    }

    .ai-send {
        width: 46px;
        height: 46px;
        border: none;
        border-radius: 14px;
        background: linear-gradient(135deg, #0ea5e9, #2563eb);
        color: #fff;
        cursor: pointer;
        flex-shrink: 0;
    }

    .ai-loading {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #6b7280;
        font-size: 12px;
    }

    .ai-loading span {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #9ca3af;
        animation: blink 1.2s infinite ease-in-out;
    }

    .ai-loading span:nth-child(2) {
        animation-delay: .2s;
    }

    .ai-loading span:nth-child(3) {
        animation-delay: .4s;
    }

    @keyframes blink {

        0%,
        80%,
        100% {
            opacity: .3;
            transform: scale(.85);
        }

        40% {
            opacity: 1;
            transform: scale(1);
        }
    }

    @media (max-width: 640px) {
        .fab-ai {
            right: 14px;
            bottom: calc(78px + env(safe-area-inset-bottom, 0px));
            width: 54px;
            height: 54px;
            padding: 0;
            font-size: 13px;
        }

        .fab-ai i {
            font-size: 20px;
        }

        .fab-ai.show-label {
            width: auto;
            min-width: 54px;
            padding: 0 16px;
        }

        .fab-ai.show-label .fab-ai-label {
            font-size: 12px;
        }

        .ai-sheet {
            left: 0;
            right: 0;
            bottom: -110%;
            border-top-left-radius: 22px;
            border-top-right-radius: 22px;
            padding-top: 10px;
            padding-left: 12px;
            padding-right: 12px;
            padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
            height: min(88vh, calc(var(--app-vh, 1vh) * 88));
            max-height: min(88vh, calc(var(--app-vh, 1vh) * 88));
        }

        .ai-sheet.keyboard-open {
            height: min(100vh, calc(var(--app-vh, 1vh) * 100));
            max-height: min(100vh, calc(var(--app-vh, 1vh) * 100));
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .ai-header {
            margin-bottom: 10px;
        }

        .ai-header-left {
            gap: 10px;
            min-width: 0;
        }

        .ai-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .ai-header h3 {
            font-size: 15px;
            line-height: 1.2;
        }

        .ai-header p {
            font-size: 11px;
            line-height: 1.35;
        }

        .ai-close {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
        }

        .ai-body {
            padding: 4px 2px 12px;
            scroll-padding-bottom: 140px;
        }

        .ai-message {
            margin-bottom: 8px;
        }

        .ai-bubble {
            max-width: 92%;
            font-size: 12.5px;
            line-height: 1.5;
            padding: 11px 12px;
        }

        .ai-suggestions {
            gap: 7px;
            margin-top: 8px;
        }

        .ai-chip {
            font-size: 11px;
            padding: 9px 11px;
            border-radius: 999px;
        }

        .ai-input-wrap {
            gap: 8px;
            padding-top: 8px;
        }

        .ai-input {
            min-height: 44px;
            font-size: 16px;
            padding: 11px 13px;
            border-radius: 13px;
        }

        .ai-send {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            flex-shrink: 0;
        }
    }
</style>

<body data-page="beranda">
    <header>
        <div class="header-left">
            <div class="profile-avatar">
                <?php if ($fotoProfil && file_exists("uploads/$fotoProfil")): ?>
                    <img src="uploads/<?= $fotoProfil ?>" alt="Foto Profil">
                <?php else: ?>
                    <span class="avatar-text"><?= $initial ?></span>
                <?php endif; ?>
            </div>
            <div class="header-text">
                <h3>Halo, <?= htmlspecialchars($namaDepan); ?>👋</h3>
                <p>Semoga harimu menyenangkan</p>
            </div>
        </div>
        <div id="logoutLogo" class="header-right"><i class="fas fa-right-from-bracket"></i></div>
    </header>

    <div class="page-container">
        <div class="search-box mb-4">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span id="searchHint" class="search-hint">Cari laporan hari ini</span>
            <input type="text" id="searchQuery" class="search-input" autocomplete="off">
        </div>

        <div class="relative mb-4">
            <div id="carousel" class="flex gap-3 overflow-x-auto scrollbar-hide scroll-smooth snap-x">
                <div class="carousel-item flex-shrink-0 snap-center bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-4 rounded-2xl shadow flex items-center gap-3 w-full sm:w-80 h-24">
                    <img src="dokumen.png" class="w-12 h-12" alt="">
                    <div>
                        <h2 class="text-sm font-semibold text-white drop-shadow-sm">Cek Administrasi</h2>
                        <p class="text-xs opacity-80">Pantau laporan harian dan kegiatan terbaru</p>
                    </div>
                </div>

                <div class="carousel-item flex-shrink-0 snap-center bg-gradient-to-r from-green-400 to-emerald-600 text-white p-4 rounded-2xl shadow flex items-center gap-3 w-full sm:w-80 h-24">
                    <img src="cleaning.png" class="w-12 h-12" alt="">
                    <div>
                        <h2 class="text-sm font-semibold text-white drop-shadow-sm">Update Kebersihan</h2>
                        <p class="text-xs opacity-80">Laporan checklist kebersihan tersedia</p>
                    </div>
                </div>

                <div class="carousel-item flex-shrink-0 snap-center bg-gradient-to-r from-orange-400 to-red-500 text-white p-4 rounded-2xl shadow flex items-center gap-3 w-full sm:w-80 h-24">
                    <img src="kinerja.png" class="w-12 h-12" alt="">
                    <div>
                        <h2 class="text-sm font-semibold text-white drop-shadow-sm">Pemantauan Kinerja</h2>
                        <p class="text-xs opacity-80">Data progres pekerjaan tersedia</p>
                    </div>
                </div>
            </div>
            <div class="flex justify-center mt-2 gap-2">
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        </div>

        <?php
        $menuCepat = [
            ["timetable.php", "fa-calendar-days", "Timetable", "sky", "", ""],
            ["peminjaman_ruang_rapat.php", "fa-list-alt", "Ruang Rapat", "sky", "", ""],
            ["javascript:void(0)", "fa-right-to-bracket", "Cekin", "purple", "openUploadCekin", ""],
            ["javascript:void(0)", "fa-triangle-exclamation", "Kerusakan", "red", "openUploadLaporanKerusakan", ""],
            ["javascript:void(0)", "fa-warehouse", "Gudang", "emerald", "openUploadGudang", ""],
            ["arsip_surat.php", "fa-envelope-open-text", "Persuratan", "amber", "", ""],
            ["kendaraan.php", "fa-car-side", "Kendaraan", "teal", "", ""],
            ["daftar_tamu.php", "fa-book-open", "Buku Tamu", "orange", "", ""],
            ["https://viyatadhika.github.io/noext/", "fa-phone-volume", "Nomor Ext", "indigo", "", "_blank"],
        ];
        ?>
        <h3 class="section-title">Menu Cepat</h3>
        <div class="grid grid-cols-4 gap-4 px-4 mb-4">
            <?php foreach ($menuCepat as $i => $m): ?>
                <a href="<?= $m[0] ?>"
                    <?= $m[4] ? 'id="' . $m[4] . '"' : '' ?>
                    <?= $m[5] ? 'target="' . $m[5] . '"' : '' ?>
                    class="group flex flex-col items-center text-gray-700 text-xs fade-up"
                    style="animation-delay:<?= 0.1 + $i * 0.05 ?>s">
                    <div class="w-14 h-14 flex items-center justify-center bg-<?= $m[3] ?>-50 rounded-2xl shadow-sm group-hover:scale-110 transition">
                        <i class="fa-solid <?= $m[1] ?> text-<?= $m[3] ?>-600 text-2xl"></i>
                    </div>
                    <span class="mt-2 font-medium text-center leading-tight w-full"><?= $m[2] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div id="latestActivity" class="space-y-3">
            <?php include 'api/get_latest_activity.php'; ?>
        </div>

        <h3 class="section-title">Kinerja Utama</h3>
        <div class="kinerja-grid">
            <div class="kinerja-card">
                <div class="badge bg-blue"><i class="fa-solid fa-calendar-check"></i></div>
                <p class="k-label">Total Checklist</p>
                <p class="k-value"><?= $total ?></p>
            </div>
            <div class="kinerja-card">
                <div class="badge bg-orange"><i class="fa-solid fa-user-group"></i></div>
                <p class="k-label">Total Petugas</p>
                <p class="k-value"><?= $totalPetugas ?></p>
            </div>
            <div class="kinerja-card">
                <div class="badge bg-green"><i class="fa-solid fa-list-check"></i></div>
                <p class="k-label">Jenis Form</p>
                <p class="k-value"><?= $totalForm ?></p>
            </div>
            <div class="kinerja-card">
                <div class="badge bg-purple"><i class="fa-solid fa-location-dot"></i></div>
                <p class="k-label">Area Kerja</p>
                <p class="k-value"><?= $totalArea ?></p>
            </div>
        </div>

        <div id="logoutModal">
            <div id="logoutBox" class="logout-card">
                <h2>Keluar dari Akun?</h2>
                <p>Anda akan keluar dari PAK RT Super App.</p>
                <div class="flex flex-col gap-2">
                    <button id="confirmLogout" class="btn-primary w-full">Keluar</button>
                    <button id="cancelLogout" class="btn-outline w-full">Batal</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'nav_monitoring.php'; ?>

    <div id="fadeBgLaporanKerusakan" class="fade-bg"></div>
    <div id="sheetLaporanKerusakan" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetLaporanKerusakan" class="absolute top-3 right-4 w-9 h-9 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="sheetLaporanKerusakanContent" class="p-5 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-extrabold text-sky-600">Laporan Kerusakan</h2>
                <p class="text-xs text-gray-500 mt-1">Laporkan fasilitas yang rusak</p>
            </div>
            <div class="space-y-3">
                <a href="laporan_kerusakan.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-red-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                        <i class="fa-solid fa-list-check text-red-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Daftar Laporan</p>
                        <p class="text-xs text-gray-500">Lihat laporan masuk</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="laporan_kerusakan_tambah.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-sky-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center">
                        <i class="fa-solid fa-plus text-sky-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Tambah Laporan</p>
                        <p class="text-xs text-gray-500">Laporkan kerusakan baru</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
            </div>
        </div>
    </div>

    <div id="fadeBgGudang" class="fade-bg"></div>
    <div id="sheetGudang" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetGudang" class="absolute top-3 right-4 w-9 h-9 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="sheetGudangContent" class="p-5 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-extrabold text-sky-600">Manajemen Gudang</h2>
                <p class="text-xs text-gray-500 mt-1">Manajemen stok &amp; laporan gudang</p>
            </div>
            <div class="space-y-3">
                <a href="stok_barang.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-emerald-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <i class="fa-solid fa-boxes-stacked text-emerald-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Stok Barang</p>
                        <p class="text-xs text-gray-500">Lihat &amp; kelola stok barang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="barang_masuk.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-sky-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center">
                        <i class="fa-solid fa-arrow-down-wide-short text-sky-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Barang Masuk</p>
                        <p class="text-xs text-gray-500">Input barang masuk gudang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="barang_keluar.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-rose-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-rose-100 flex items-center justify-center">
                        <i class="fa-solid fa-arrow-up-wide-short text-rose-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Barang Keluar</p>
                        <p class="text-xs text-gray-500">Input barang keluar gudang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="stok_opname.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-indigo-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <i class="fa-solid fa-clipboard-check text-indigo-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Stok Opname</p>
                        <p class="text-xs text-gray-500">Cek fisik stok barang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="koreksi_stok.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-amber-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                        <i class="fa-solid fa-file-lines text-amber-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Penyesuaian Stok</p>
                        <p class="text-xs text-gray-500">Catatan koreksi stok barang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
            </div>
        </div>
    </div>

    <div id="fadeBgCekin" class="fade-bg"></div>
    <div id="sheetCekin" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetCekin" class="absolute top-3 right-4 w-9 h-9 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="sheetCekinContent" class="p-5 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-extrabold text-sky-600">Cekin Peserta &amp; Pengajar</h2>
                <p class="text-xs text-gray-500 mt-1">Monitoring check-in peserta</p>
            </div>
            <div class="space-y-3">
                <a href="peserta_penginapan.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-emerald-50 transition-all">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-user-plus text-emerald-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800">Input Peserta &amp; Pengajar</p>
                        <p class="text-xs text-gray-500">Tambah data peserta / pengajar</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400 text-xs"></i>
                </a>
                <a href="cekin_cekout.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-sky-50 transition-all">
                    <div class="w-12 h-12 rounded-2xl bg-sky-100 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-right-to-bracket text-sky-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800">Cekin dan Cekout</p>
                        <p class="text-xs text-gray-500">Monitoring kehadiran peserta</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400 text-xs"></i>
                </a>
            </div>
        </div>
    </div>

    <div id="popupNotif" class="fixed inset-0 hidden" style="z-index:99999; background:rgba(0,0,0,0.45); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);">
        <div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px;">
            <div class="w-full max-w-[480px] relative" style="border-radius:24px; padding-bottom:1rem; background:rgba(255,255,255,0.16); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); border:.5px solid rgba(255,255,255,0.30); box-shadow:0 20px 60px rgba(0,0,0,0.28);">
                <div style="display:flex; justify-content:center; padding:10px 0 4px;">
                    <div style="width:36px; height:4px; border-radius:2px; background:rgba(255,255,255,0.5);"></div>
                </div>
                <button id="closeNotif" style="position:absolute; top:10px; right:14px; width:30px; height:30px; border-radius:50%; background:rgba(255,255,255,0.25); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:white; font-size:13px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <p id="notifLabel" style="text-align:center; font-size:11px; color:rgba(255,255,255,0.78); margin:0 0 8px;"></p>
                <div style="overflow:hidden; padding:0 16px;">
                    <div id="notifTrack" style="display:flex; gap:12px; transition:transform .35s cubic-bezier(.4,0,.2,1); cursor:grab; user-select:none;"></div>
                </div>
                <div id="notifDots" style="display:flex; justify-content:center; gap:6px; padding-top:12px;"></div>
            </div>
        </div>
    </div>

    <button id="fabAiBtn" class="fab-ai" aria-label="Tanya Pak RT AI" title="Tanya Pak RT AI">
        <i class="fa-solid fa-robot"></i>
        <span class="fab-ai-label">Tanya Pak RT AI</span>
    </button>

    <div id="aiOverlay" class="ai-overlay"></div>

    <div id="aiSheet" class="ai-sheet">
        <div class="ai-sheet-handle"></div>
        <div class="ai-header">
            <div class="ai-header-left">
                <div class="ai-icon">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div>
                    <h3>Tanya Pak RT AI</h3>
                    <p>Siap bantu ringkasan &amp; rekap operasional</p>
                </div>
            </div>
            <button id="closeAiSheet" class="ai-close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="ai-body" id="aiBody">
            <div class="ai-message ai-bot">
                <div class="ai-bubble">Halo 👋 Saya bisa bantu:
                    • ringkasan kegiatan
                    • rekapitulasi laporan
                    • daftar kerusakan
                    • status gudang
                    • monitoring check-in</div>
            </div>

            <div class="ai-suggestions">
                <button class="ai-chip" data-prompt="Buatkan ringkasan kegiatan hari ini">Ringkasan hari ini</button>
                <button class="ai-chip" data-prompt="Tampilkan rekap kegiatan minggu ini">Rekap minggu ini</button>
                <button class="ai-chip" data-prompt="Tampilkan daftar kerusakan terbaru">Daftar kerusakan</button>
                <button class="ai-chip" data-prompt="Bagaimana status stok gudang saat ini">Status gudang</button>
                <button class="ai-chip" data-prompt="Siapa saja yang belum check-in hari ini">Check-in peserta</button>
            </div>
        </div>

        <div class="ai-input-wrap">
            <input type="text" id="aiQuestion" class="ai-input" placeholder="Tanya misalnya: ringkasan kegiatan hari ini" autocomplete="off">
            <button id="sendAiQuestion" class="ai-send">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script>
        (function() {
            const now = new Date();
            const y = now.getFullYear(),
                m = now.getMonth(),
                d = now.getDate();
            const items = [];

            const skpPeriodes = [{
                    label: 'Triwulan I',
                    periode: `1 Jan ${y} s/d 31 Mar ${y}`,
                    batas: `5 April ${y}`,
                    aktif: m === 2 && d >= 25
                },
                {
                    label: 'Triwulan II',
                    periode: `1 Apr ${y} s/d 30 Jun ${y}`,
                    batas: `5 Juli ${y}`,
                    aktif: m === 5 && d >= 24
                },
                {
                    label: 'Triwulan III',
                    periode: `1 Jul ${y} s/d 30 Sep ${y}`,
                    batas: `5 Oktober ${y}`,
                    aktif: m === 8 && d >= 24
                },
                {
                    label: 'Triwulan IV',
                    periode: `1 Okt ${y} s/d 31 Des ${y}`,
                    batas: `5 Januari ${y + 1}`,
                    aktif: m === 11 && d >= 25
                },
                {
                    label: 'Tahunan',
                    periode: `1 Jan ${y - 1} s/d 31 Des ${y - 1}`,
                    batas: `31 Januari ${y}`,
                    aktif: m === 0
                },
            ];

            skpPeriodes.forEach(p => {
                if (!p.aktif) return;
                items.push({
                    icon: 'fa-star',
                    title: 'Pengisian SKP!',
                    sub: 'Sasaran Kinerja Pegawai',
                    grad: 'linear-gradient(135deg,#10b981,#0d9488)',
                    accent: '#059669',
                    body: `
                <div style="background:#f0fdf4;border-radius:10px;padding:.6rem .8rem;margin-bottom:.75rem;border:.5px solid #bbf7d0;">
                    <p style="font-size:10px;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.04em;">Periode</p>
                    <p style="font-size:13px;font-weight:500;color:#065f46;margin:0 0 1px;">${p.label}</p>
                    <p style="font-size:11px;color:#374151;margin:0;">${p.periode}</p>
                </div>
                <div style="background:#fef2f2;border:.5px solid #fecaca;border-radius:10px;padding:.45rem;text-align:center;margin-bottom:.85rem;">
                    <p style="font-size:12px;font-weight:500;color:#dc2626;margin:0;">⏰ Batas: ${p.batas}</p>
                </div>`
                });
            });

            const lastDay = new Date(y, m + 1, 0).getDate();
            if (d > lastDay - 7) {
                items.push({
                    icon: 'fa-clipboard-check',
                    title: 'Tugas Mendesak!',
                    sub: 'Pengingat PKP Bulanan',
                    grad: 'linear-gradient(135deg,#6366f1,#4f46e5)',
                    accent: '#4f46e5',
                    body: `
                <div style="background:#eef2ff;border-radius:10px;padding:.6rem .8rem;margin-bottom:.75rem;border:.5px solid #c7d2fe;">
                    <p style="font-size:10px;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.04em;">Info</p>
                    <p style="font-size:13px;font-weight:500;color:#3730a3;margin:0;">PKP Bulan Ini</p>
                </div>
                <div style="background:#fef2f2;border:.5px solid #fecaca;border-radius:10px;padding:.45rem;text-align:center;margin-bottom:.85rem;">
                    <p style="font-size:12px;font-weight:500;color:#dc2626;margin:0;">⏰ Batas: Tanggal 4 setiap bulan</p>
                </div>`
                });
            }

            if (!items.length) return;
            const key = `notifShown_${y}-${m + 1}-${d}`;
            if (localStorage.getItem(key)) return;

            const modal = document.getElementById('popupNotif');
            const track = document.getElementById('notifTrack');
            const dotsEl = document.getElementById('notifDots');
            const label = document.getElementById('notifLabel');
            const total = items.length;

            items.forEach((n, i) => {
                const card = document.createElement('div');
                card.style.cssText = `min-width:calc(100% - 28px);flex-shrink:0;border-radius:18px;overflow:hidden;background:#ffffff;box-shadow:0 8px 32px rgba(0,0,0,0.18);`;
                card.innerHTML = `
            <div style="background:${n.grad}; padding:1.4rem 1.25rem .9rem; text-align:center;">
                <div style="width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;margin:0 auto .6rem;">
                    <i class="fa-solid ${n.icon} text-white" style="font-size:20px;"></i>
                </div>
                <p style="color:white;font-size:15px;font-weight:600;margin:0 0 2px;">${n.title}</p>
                <p style="color:rgba(255,255,255,.8);font-size:11px;margin:0;">${n.sub}</p>
            </div>
            <div style="padding:.9rem 1rem 1rem; background:#ffffff;">
                ${n.body}
                <button class="btn-tutup" style="width:100%;padding:.6rem;background:${n.accent};color:white;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .15s;">
                    Mengerti & Lanjutkan
                </button>
            </div>`;
                track.appendChild(card);

                const dot = document.createElement('div');
                dot.dataset.i = i;
                dot.style.cssText = `height:5px;border-radius:3px;transition:all .3s;cursor:pointer;background:${i === 0 ? n.accent : 'rgba(255,255,255,0.4)'};width:${i === 0 ? '20px' : '6px'};`;
                dotsEl.appendChild(dot);
            });

            const dots = [...dotsEl.children];
            let cur = 0;

            function goTo(i) {
                cur = Math.max(0, Math.min(i, total - 1));
                const cardW = track.children[0].offsetWidth + 12;
                track.style.transform = `translateX(-${cur * cardW}px)`;
                dots.forEach((dot, idx) => {
                    dot.style.width = idx === cur ? '20px' : '6px';
                    dot.style.background = idx === cur ? items[cur].accent : 'rgba(255,255,255,0.4)';
                });
                label.textContent = `${cur + 1} / ${total} pengingat`;
            }

            function buka() {
                document.body.classList.add('modal-open');
                modal.classList.remove('hidden');
                modal.style.opacity = '0';
                requestAnimationFrame(() => {
                    modal.style.transition = 'opacity .3s ease';
                    modal.style.opacity = '1';
                });
            }

            function tutup() {
                modal.style.transition = 'opacity .25s ease';
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.style.opacity = '';
                    document.body.classList.remove('modal-open');
                    localStorage.setItem(key, 'true');
                }, 250);
            }

            goTo(0);
            dots.forEach(dot => dot.addEventListener('click', () => goTo(+dot.dataset.i)));

            let sx = 0;
            track.addEventListener('touchstart', e => {
                sx = e.touches[0].clientX;
            }, {
                passive: true
            });
            track.addEventListener('touchend', e => {
                const dx = e.changedTouches[0].clientX - sx;
                if (dx < -30) goTo(cur + 1);
                if (dx > 30) goTo(cur - 1);
            });

            let mx = 0,
                drag = false;
            track.addEventListener('mousedown', e => {
                mx = e.clientX;
                drag = true;
                track.style.cursor = 'grabbing';
            });
            track.addEventListener('mouseup', e => {
                if (!drag) return;
                drag = false;
                track.style.cursor = 'grab';
                const dx = e.clientX - mx;
                if (dx < -30) goTo(cur + 1);
                if (dx > 30) goTo(cur - 1);
            });
            track.addEventListener('mouseleave', () => {
                drag = false;
                track.style.cursor = 'grab';
            });

            document.getElementById('closeNotif').addEventListener('click', tutup);
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-tutup')) tutup();
            });
            modal.addEventListener('click', function(e) {
                if (e.target === modal) tutup();
            });

            buka();
        })();
    </script>

    <script>
        (function() {
            const fabBtn = document.getElementById('fabAiBtn');
            const overlay = document.getElementById('aiOverlay');
            const sheet = document.getElementById('aiSheet');
            const closeBtn = document.getElementById('closeAiSheet');
            const aiBody = document.getElementById('aiBody');
            const aiInput = document.getElementById('aiQuestion');
            const sendBtn = document.getElementById('sendAiQuestion');
            const chips = document.querySelectorAll('.ai-chip');

            if (fabBtn) {
                fabBtn.classList.add('show-label');
                setTimeout(() => {
                    fabBtn.classList.remove('show-label');
                }, 2200);
            }

            function setAppVh() {
                const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                const vh = viewportHeight * 0.01;
                document.documentElement.style.setProperty('--app-vh', `${vh}px`);
            }

            function scrollInputIntoView() {
                setTimeout(() => {
                    aiInput.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                    aiBody.scrollTop = aiBody.scrollHeight;
                }, 180);
            }

            function handleKeyboardState() {
                const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                const screenHeight = window.innerHeight;
                const keyboardLikelyOpen = (screenHeight - viewportHeight) > 140;

                if (keyboardLikelyOpen) {
                    sheet.classList.add('keyboard-open');
                    fabBtn.style.opacity = '0';
                    fabBtn.style.pointerEvents = 'none';
                } else {
                    sheet.classList.remove('keyboard-open');
                    fabBtn.style.opacity = '1';
                    fabBtn.style.pointerEvents = 'auto';
                }
            }

            setAppVh();
            handleKeyboardState();
            window.addEventListener('resize', () => {
                setAppVh();
                handleKeyboardState();
            });
            window.addEventListener('orientationchange', () => {
                setAppVh();
                handleKeyboardState();
            });
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => {
                    setAppVh();
                    handleKeyboardState();
                });
            }
            aiInput.addEventListener('focus', () => {
                handleKeyboardState();
                scrollInputIntoView();
            });
            aiInput.addEventListener('blur', () => {
                setTimeout(handleKeyboardState, 100);
            });

            function openAiSheet() {
                overlay.classList.add('show');
                sheet.classList.add('show');
                document.body.classList.add('modal-open');
                setAppVh();
                handleKeyboardState();
                setTimeout(() => {
                    aiInput.focus();
                    scrollInputIntoView();
                }, 220);
            }

            function closeAiSheet() {
                overlay.classList.remove('show');
                sheet.classList.remove('show');
                sheet.classList.remove('keyboard-open');
                document.body.classList.remove('modal-open');
                fabBtn.style.opacity = '1';
                fabBtn.style.pointerEvents = 'auto';
                aiInput.blur();
            }

            function addMessage(text, role = 'bot', isHtml = false) {
                const wrapper = document.createElement('div');
                wrapper.className = `ai-message ai-${role}`;
                const bubble = document.createElement('div');
                bubble.className = 'ai-bubble';
                if (isHtml) {
                    bubble.innerHTML = text;
                } else {
                    bubble.textContent = text;
                }
                wrapper.appendChild(bubble);
                aiBody.appendChild(wrapper);
                aiBody.scrollTop = aiBody.scrollHeight;
                return wrapper;
            }

            function addLoading() {
                const wrapper = document.createElement('div');
                wrapper.className = 'ai-message ai-bot';
                wrapper.id = 'aiLoadingMessage';
                const bubble = document.createElement('div');
                bubble.className = 'ai-bubble';
                bubble.innerHTML = `
                    <div class="ai-loading">
                        <span></span><span></span><span></span>
                        <strong>Sedang menyiapkan jawaban...</strong>
                    </div>
                `;
                wrapper.appendChild(bubble);
                aiBody.appendChild(wrapper);
                aiBody.scrollTop = aiBody.scrollHeight;
            }

            function removeLoading() {
                const el = document.getElementById('aiLoadingMessage');
                if (el) el.remove();
            }

            async function askAi(question) {
                if (!question || !question.trim()) return;
                addMessage(question, 'user');
                aiInput.value = '';
                addLoading();

                try {
                    const response = await fetch('api/ai_assistant.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            question: question
                        })
                    });

                    const data = await response.json();
                    removeLoading();

                    if (!response.ok) {
                        addMessage(data.message || 'Terjadi kesalahan saat memproses pertanyaan.');
                        return;
                    }

                    addMessage(data.answer || 'Belum ada jawaban.');
                } catch (err) {
                    removeLoading();
                    addMessage('Maaf, AI sedang tidak bisa dihubungi. Silakan coba lagi.');
                }
            }

            fabBtn.addEventListener('click', openAiSheet);
            closeBtn.addEventListener('click', closeAiSheet);
            overlay.addEventListener('click', closeAiSheet);
            sendBtn.addEventListener('click', () => askAi(aiInput.value));
            aiInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    askAi(aiInput.value);
                }
            });
            chips.forEach(chip => {
                chip.addEventListener('click', function() {
                    const prompt = this.dataset.prompt || '';
                    askAi(prompt);
                });
            });
        })();
    </script>

    <?php include 'footer.php'; ?>