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
// Digabung menjadi 1 query untuk efisiensi
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

            <span id="searchHint" class="search-hint">
                Cari laporan hari ini
            </span>

            <input
                type="text"
                id="searchQuery"
                class="search-input"
                autocomplete="off">
        </div>


        <!-- ===================== CAROUSEL ===================== -->
        <div class="relative mb-4">
            <div id="carousel"
                class="flex gap-3 overflow-x-auto scrollbar-hide scroll-smooth snap-x">

                <!-- SLIDE 1 -->
                <div class="carousel-item flex-shrink-0 snap-center
                    bg-gradient-to-r from-blue-500 to-indigo-600
                    text-white p-4 rounded-2xl shadow
                    flex items-center gap-3
                    w-full sm:w-80 h-24">
                    <img src="dokumen.png" class="w-12 h-12" alt="">
                    <div>
                        <h2 class="text-sm font-semibold text-white drop-shadow-sm">Cek Administrasi</h2>
                        <p class="text-xs opacity-80">Pantau laporan harian dan kegiatan terbaru</p>
                    </div>
                </div>

                <!-- SLIDE 2 -->
                <div class="carousel-item flex-shrink-0 snap-center
                    bg-gradient-to-r from-green-400 to-emerald-600
                    text-white p-4 rounded-2xl shadow
                    flex items-center gap-3
                    w-full sm:w-80 h-24">
                    <img src="cleaning.png" class="w-12 h-12" alt="">
                    <div>
                        <h2 class="text-sm font-semibold text-white drop-shadow-sm">Update Kebersihan</h2>
                        <p class="text-xs opacity-80">Laporan checklist kebersihan tersedia</p>
                    </div>
                </div>

                <!-- SLIDE 3 -->
                <div class="carousel-item flex-shrink-0 snap-center
                    bg-gradient-to-r from-orange-400 to-red-500
                    text-white p-4 rounded-2xl shadow
                    flex items-center gap-3
                    w-full sm:w-80 h-24">
                    <img src="kinerja.png" class="w-12 h-12" alt="">
                    <div>
                        <h2 class="text-sm font-semibold text-white drop-shadow-sm">Pemantauan Kinerja</h2>
                        <p class="text-xs opacity-80">Data progres pekerjaan tersedia</p>
                    </div>
                </div>
            </div>

            <!-- DOT -->
            <div class="flex justify-center mt-2 gap-2">
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        </div>

        <!-- MENU CEPAT -->
        <h3 class="section-title">Menu Cepat</h3>

        <div class="quick-menu clean-menu">

            <!-- Timetable -->
            <a href="timetable.php" class="super-menu clean-item">
                <div class="icon-box bg-blue">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <span>Timetable</span>
            </a>

            <!-- Cekin -->
            <a id="openUploadCekin" href="javascript:void(0)" class="super-menu clean-item">
                <div class="icon-box bg-purple">
                    <i class="fa-solid fa-right-to-bracket"></i>
                </div>
                <span>Cekin</span>
            </a>

            <!-- Laporan Kerusakan -->
            <a id="openUploadLaporanKerusakan" href="javascript:void(0)" class="super-menu clean-item">
                <div class="icon-box bg-red">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <span>Kerusakan</span>
            </a>

            <!-- Gudang -->
            <a id="openUploadGudang" href="javascript:void(0)" class="super-menu clean-item">
                <div class="icon-box bg-green">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <span>Gudang</span>
            </a>

            <!-- Persuratan -->
            <a href="arsip_surat.php" class="super-menu clean-item">
                <div class="icon-box bg-yellow">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>
                <span>Persuratan</span>
            </a>

            <!-- Kendaraan -->
            <a href="kendaraan.php" class="super-menu clean-item">
                <div class="icon-box bg-teal">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <span>Kendaraan</span>
            </a>

            <!-- Buku Tamu -->
            <a href="daftar_tamu.php" class="super-menu clean-item">
                <div class="icon-box bg-orange">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <span>Buku Tamu</span>
            </a>

            <!-- Nomor Ext -->
            <a href="https://viyatadhika.github.io/noext/" target="_blank" class="super-menu clean-item">
                <div class="icon-box bg-indigo">
                    <i class="fa-solid fa-phone-volume"></i>
                </div>
                <span>Nomor Ext</span>
            </a>

        </div>

        <!-- AKTIVITAS TERBARU -->
        <div id="latestActivity" class="space-y-3">
            <?php include 'api/get_latest_activity.php'; ?>
        </div>


        <!-- KINERJA UTAMA -->
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


        <!-- MODAL LOGOUT -->
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

    <!-- BG untuk Sheet Laporan Kerusakan -->
    <div id="fadeBgLaporanKerusakan" class="fade-bg"></div>

    <!-- SHEET UPLOAD PRESENSI PPPK -->
    <div id="sheetLaporanKerusakan" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetLaporanKerusakan" class="absolute top-3 right-4 text-gray-400 hover:text-gray-600 text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="sheetLaporanKerusakanContent" class="p-4 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-bold text-sky-600">Laporan Kerusakan</h2>
                <p class="text-xs text-gray-500 mt-1">Laporkan fasilitas yang rusak</p>
            </div>

            <div class="space-y-3">

                <a href="laporan_kerusakan.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-red-50 transition-all">

                    <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                        <i class="fa-solid fa-list-check text-red-600 text-xl"></i>
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-semibold">Daftar Laporan</p>
                        <p class="text-xs text-gray-500">Lihat laporan masuk</p>
                    </div>

                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>

                <a href="laporan_kerusakan_tambah.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-sky-50 transition-all">

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
        <button id="closeSheetGudang" class="absolute top-3 right-4 text-gray-400 hover:text-gray-600 text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="sheetGudangContent" class="p-4 pb-8 pt-4">
            <!-- HEADER -->
            <div class="text-center mb-5">
                <h2 class="text-lg font-bold text-sky-600">Manajemen Gudang</h2>
                <p class="text-xs text-gray-500 mt-1">Manajemen stok &amp; laporan gudang</p>
            </div>

            <!-- MENU LIST -->
            <div class="space-y-3">

                <!-- Stok Barang -->
                <a href="stok_barang.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-emerald-50 transition-all">

                    <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <i class="fa-solid fa-boxes-stacked text-emerald-600 text-xl"></i>
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">Stok Barang</p>
                        <p class="text-xs text-gray-500">Lihat &amp; kelola stok barang</p>
                    </div>

                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>

                <!-- Barang Masuk -->
                <a href="barang_masuk.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-sky-50 transition-all">

                    <div class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center">
                        <i class="fa-solid fa-arrow-down-wide-short text-sky-600 text-xl"></i>
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">Barang Masuk</p>
                        <p class="text-xs text-gray-500">Input barang masuk gudang</p>
                    </div>

                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>

                <!-- Barang Keluar -->
                <a href="barang_keluar.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-rose-50 transition-all">

                    <div class="w-12 h-12 rounded-xl bg-rose-100 flex items-center justify-center">
                        <i class="fa-solid fa-arrow-up-wide-short text-rose-600 text-xl"></i>
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">Barang Keluar</p>
                        <p class="text-xs text-gray-500">Input barang keluar gudang</p>
                    </div>

                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>

                <!-- Stok Opname -->
                <a href="stok_opname.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-indigo-50 transition-all">

                    <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <i class="fa-solid fa-clipboard-check text-indigo-600 text-xl"></i>
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">Stok Opname</p>
                        <p class="text-xs text-gray-500">Cek fisik stok barang</p>
                    </div>

                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>

                <a href="koreksi_stok.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-amber-50 transition-all">

                    <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                        <i class="fa-solid fa-file-lines text-amber-600 text-xl"></i>
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">Penyesuaian Stok Barang</p>
                        <p class="text-xs text-gray-500">Catatan koreksi stok barang</p>
                    </div>

                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>

            </div>

        </div>
    </div>

    <!-- BG untuk Sheet Laporan Kerusakan -->
    <div id="fadeBgLaporanKerusakan" class="fade-bg"></div>

    <!-- SHEET UPLOAD PRESENSI PPPK -->
    <div id="sheetLaporanKerusakan" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetLaporanKerusakan" class="absolute top-3 right-4 text-gray-400 hover:text-gray-600 text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="sheetLaporanKerusakanContent" class="p-4 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-bold text-sky-600">Laporan Kerusakan</h2>
                <p class="text-xs text-gray-500 mt-1">Laporkan fasilitas yang rusak</p>
            </div>

            <div class="space-y-3">

                <a href="laporan_kerusakan.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-red-50 transition-all">

                    <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                        <i class="fa-solid fa-list-check text-red-600 text-xl"></i>
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-semibold">Daftar Laporan</p>
                        <p class="text-xs text-gray-500">Lihat laporan masuk</p>
                    </div>

                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>

                <a href="laporan_kerusakan_tambah.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-sky-50 transition-all">

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

    <!-- FADE BG -->
    <div id="fadeBgCekin" class="fade-bg"></div>

    <!-- SHEET -->
    <div id="sheetCekin" class="sheet">
        <div class="sheet-handle"></div>

        <!-- Close Button -->
        <button id="closeSheetCekin"
            class="absolute top-3 right-4 w-9 h-9 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="sheetCekinContent" class="p-5 pb-8 pt-4">

            <!-- HEADER -->
            <div class="text-center mb-5">
                <h2 class="text-lg font-extrabold text-sky-600">Cekin Peserta &amp; Pengajar</h2>
                <p class="text-xs text-gray-500 mt-1">Monitoring check-in peserta</p>
            </div>

            <!-- MENU LIST -->
            <div class="space-y-3">

                <!-- Input Peserta/Pengajar -->
                <a href="input_data_peserta_pengajar.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-emerald-50 transition-all active:scale-[0.98]">

                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-user-plus text-emerald-600 text-xl"></i>
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800 truncate">Input Peserta &amp; Pengajar</p>
                        <p class="text-xs text-gray-500 truncate">Tambah data peserta / pengajar</p>
                    </div>

                    <i class="fa-solid fa-chevron-right text-gray-400 text-xs"></i>
                </a>

                <!-- Cekin Peserta -->
                <a href="cekin_cekout.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-sky-50 transition-all active:scale-[0.98]">

                    <div class="w-12 h-12 rounded-2xl bg-sky-100 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-right-to-bracket text-sky-600 text-xl"></i>
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800 truncate">Cekin dan Cekout</p>
                        <p class="text-xs text-gray-500 truncate">Peserta dan Pengajar</p>
                    </div>

                    <i class="fa-solid fa-chevron-right text-gray-400 text-xs"></i>
                </a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>