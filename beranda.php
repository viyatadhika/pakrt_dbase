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
$namaDepan = explode(' ', trim($namaLengkap))[0];

// $namaLengkap = $_SESSION['user']['nama'] ?? 'User';
$fotoProfil  = $_SESSION['user']['foto_profil'] ?? null; // pastikan field ini ada di DB

// ambil inisial
$parts = explode(" ", trim($namaLengkap));
$initial = strtoupper(substr($parts[0], 0, 1));
if (count($parts) > 1) {
    $initial .= strtoupper(substr(end($parts), 0, 1));
}

/* ===================== SUMMARY DASHBOARD ===================== */

$total        = $conn->query("SELECT COUNT(*) AS jml FROM checklist_forms")->fetch_assoc()['jml'];
$totalPetugas = $conn->query("SELECT COUNT(DISTINCT nama_petugas) AS jml FROM checklist_forms")->fetch_assoc()['jml'];
$totalForm    = $conn->query("SELECT COUNT(DISTINCT form_type) AS jml FROM checklist_forms")->fetch_assoc()['jml'];
$totalArea    = $conn->query("SELECT COUNT(DISTINCT area_kerja) AS jml FROM checklist_forms WHERE area_kerja IS NOT NULL")->fetch_assoc()['jml'];

/* ===================== GRAFIK JENIS FORM ===================== */

$qGrafik = $conn->query("
    SELECT form_type, COUNT(*) AS total
    FROM checklist_forms
    GROUP BY form_type
    ORDER BY total DESC
");

$chartLabels = [];
$chartValues = [];

while ($row = $qGrafik->fetch_assoc()) {
    $chartLabels[] = $row['form_type'];
    $chartValues[] = $row['total'];
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

while ($row = $qAreaChart->fetch_assoc()) {
    $areaLabels[] = $row['area_kerja'];
    $areaValues[] = $row['total'];
}
?>

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
        <div class="search-box mb-3" id="openSearch">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Cari laporan, petugas, area..." readonly>
        </div>


        <!-- BANNER / CAROUSEL -->
        <div id="carousel" class="flex overflow-x-auto scrollbar-hide gap-3 rounded-2xl scroll-smooth mt-2">
            <!-- SLIDE 1 -->
            <div class="carousel-item flex-shrink-0 snap-center bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-4 rounded-2xl shadow flex items-center gap-3 w-[90%] sm:w-80 h-24 fade-up" style="animation-delay:0.2s">
                <img src="dokumen.png" alt="Dokumen" class="w-12 h-12">
                <div>
                    <h2 class="text-sm font-semibold">Cek Administrasi</h2>
                    <p class="text-xs opacity-80">Pantau laporan harian dan kegiatan terbaru</p>
                </div>
            </div>

            <!-- SLIDE 2 -->
            <div class="carousel-item flex-shrink-0 snap-center bg-gradient-to-r from-green-400 to-emerald-600 text-white p-4 rounded-2xl shadow flex items-center gap-3 w-[90%] sm:w-80 h-24 fade-up" style="animation-delay:0.3s">
                <img src="cleaning.png" alt="Cleaning" class="w-12 h-12">
                <div>
                    <h2 class="text-sm font-semibold">Update Kebersihan</h2>
                    <p class="text-xs opacity-80">Laporan checklist kebersihan tersedia</p>
                </div>
            </div>

            <!-- SLIDE 3 -->
            <div class="carousel-item flex-shrink-0 snap-center bg-gradient-to-r from-orange-400 to-red-500 text-white p-4 rounded-2xl shadow flex items-center gap-3 w-[90%] sm:w-80 h-24 fade-up" style="animation-delay:0.4s">
                <img src="kinerja.png" alt="Kinerja" class="w-12 h-12">
                <div>
                    <h2 class="text-sm font-semibold">Pemantauan Kinerja</h2>
                    <p class="text-xs opacity-80">Data progres pekerjaan tersedia</p>
                </div>
            </div>
        </div>

        <div class="flex justify-center mt-2 gap-2">
            <span class="dot active" id="dot0"></span>
            <span class="dot" id="dot1"></span>
            <span class="dot" id="dot2"></span>
        </div>

        <!-- QUICK MENU -->
        <h3 class="section-title">Menu Cepat</h3>

        <div class="quick-menu clean-menu">

            <!-- Timetable Kegiatan -->
            <a href="https://docs.google.com/spreadsheets/d/1UpJrbk6gDnNie_jXzb7xfgpVGhc68MelDuFlmYU4boQ/edit"
                target="_blank"
                class="super-menu clean-item">
                <div class="icon-box bg-blue">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <span>Timetable Kegiatan</span>
            </a>

            <!-- Cekin Peserta -->
            <a href="https://docs.google.com/spreadsheets/d/1BAQtY1h_msfZtQc4sw0UC15DmZK0AihKC-M10F3Tz-I/edit?usp=sharing"
                target="_blank"
                class="super-menu clean-item">
                <div class="icon-box bg-purple">
                    <i class="fa-solid fa-bed"></i>
                </div>
                <span>Cekin Asrama</span>
            </a>

            <!-- Laporan Kerusakan -->
            <a href="https://docs.google.com/spreadsheets/d/1H3wMRJaw5R241OE0cgzMWumyR2oeGvVf0HzHBsu8Omg/edit"
                target="_blank"
                class="super-menu clean-item">
                <div class="icon-box bg-orange">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <span>Laporan Kerusakan</span>
            </a>

            <!-- Lainnya -->
            <a href="lainnya.php" class="super-menu clean-item">
                <div class="icon-box bg-green">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <span>Menu Lainnya</span>
            </a>

        </div>



        <!-- AKTIVITAS TERBARU -->
        <h3 class="section-title">Aktivitas Terbaru</h3>
        <div id="latestActivity" class="space-y-3"></div>

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
                    <!-- <button id="cancelLogout" class="btn-outline w-full py-2.5 rounded-xl">Batal</button> -->
                </div>
            </div>
        </div>
    </div>


    <?php include 'nav_monitoring.php'; ?>
    <?php include 'footer.php'; ?>