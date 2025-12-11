<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Menu Lainnya";
include 'header.php';

$activePage = basename($_SERVER['PHP_SELF']);
$namaLengkap = $_SESSION['user']['nama'] ?? '';
$namaDepan = explode(' ', trim($namaLengkap))[0];
?>
<div class="page-container lainnya-page">

    <div class="page-header">
        <h1>Menu Lainnya</h1>
        <p class="sub">Akses cepat sistem & layanan</p>
    </div>

    <main class="lainnya-content">

        <!-- AKSES CEPAT -->
        <section class="mb-8">
            <h2 class="text-sm font-semibold text-slate-700 mb-3">Akses Cepat</h2>

            <div class="space-y-3">

                <!-- Timetable Kegiatan -->
                <a
                    href="https://docs.google.com/spreadsheets/d/1UpJrbk6gDnNie_jXzb7xfgpVGhc68MelDuFlmYU4boQ/edit"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-sky-50 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-sky-100 flex items-center justify-center">
                        <i class="fa-solid fa-calendar-days text-sky-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Timetable Kegiatan</p>
                        <p class="text-xs text-gray-500">Lihat jadwal kegiatan terbaru</p>
                    </div>
                </a>

                <!-- Cekin Peserta dan Pengajar -->
                <a
                    href="https://docs.google.com/spreadsheets/d/1BAQtY1h_msfZtQc4sw0UC15DmZK0AihKC-M10F3Tz-I/edit?usp=sharing"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-purple-50 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
                        <i class="fa-solid fa-bed text-purple-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Cekin Peserta dan Pengajar</p>
                        <p class="text-xs text-gray-500">Monitoring check-in peserta</p>
                    </div>
                </a>

                <!-- Laporan Kerusakan -->
                <a
                    href="https://docs.google.com/spreadsheets/d/1H3wMRJaw5R241OE0cgzMWumyR2oeGvVf0HzHBsu8Omg/edit"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-red-50 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                        <i class="fa-solid fa-triangle-exclamation text-red-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Laporan Kerusakan</p>
                        <p class="text-xs text-gray-500">Laporkan fasilitas yang rusak</p>
                    </div>
                </a>

                <!-- Nomor Ext Telepon -->
                <a
                    href="https://viyatadhika.github.io/noext/"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-blue-50 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                        <i class="fa-solid fa-phone-volume text-blue-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Nomor Ext Telepon</p>
                        <p class="text-xs text-gray-500">Daftar nomor penting kantor</p>
                    </div>
                </a>
            </div>
        </section>

        <!-- SISTEM & LAYANAN -->
        <section class="mb-4">
            <h2 class="text-sm font-semibold text-slate-700 mb-3">Sistem &amp; Layanan</h2>

            <div class="space-y-3">

                <!-- SIKEP -->
                <a
                    href="https://sikep.mahkamahagung.go.id/site/login"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-emerald-50 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <i class="fa-solid fa-id-badge text-emerald-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">SIKEP</p>
                        <p class="text-xs text-gray-500">Akses Sistem Kepegawaian</p>
                    </div>
                </a>

                <!-- E-Kinerja -->
                <a
                    href="https://asndigital.bkn.go.id/"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-orange-50 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center">
                        <i class="fa-solid fa-chart-line text-orange-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">E-Kinerja</p>
                        <p class="text-xs text-gray-500">Input &amp; monitoring kinerja</p>
                    </div>
                </a>

                <!-- Upload Presensi -->
                <a
                    id="openUploadPresensi"
                    href="javascript:void(0)"
                    class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-lime-50 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-lime-100 flex items-center justify-center">
                        <i class="fa-solid fa-calendar-check text-lime-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Upload Presensi</p>
                        <p class="text-xs text-gray-500">Unggah bukti kehadiran</p>
                    </div>
                </a>

                <!-- Upload PKP -->
                <a
                    href="https://docs.google.com/forms/d/e/1FAIpQLSeKc9n0Hb9CoZPFcy7u88GpeQ4vgVNAKjtD8FQiD49Vp6K4Bw/viewform"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-rose-50 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center">
                        <i class="fa-solid fa-file-arrow-up text-rose-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Upload PKP</p>
                        <p class="text-xs text-gray-500">Unggah dokumen PKP</p>
                    </div>
                </a>
            </div>
        </section>

    </main>

    <?php include 'nav_monitoring.php'; ?>
    <?php include 'footer.php'; ?>

    <!-- BG untuk Sheet Upload Presensi -->
    <div id="fadeBgPresensi" class="fade-bg"></div>

    <!-- SHEET UPLOAD PRESENSI PPPK -->
    <div id="sheetPresensi" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetPresensi" class="absolute top-3 right-4 text-gray-400 hover:text-gray-600 text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="sheetPresensiContent" class="p-4 pb-8 pt-4">
            <h2 class="text-base font-semibold mb-4 text-center">Upload Presensi PPPK</h2>
            <div class="space-y-3">
                <a href="https://docs.google.com/forms/d/e/1FAIpQLSdJ0cE3fE7Snb6a4dzfQrM1VL9x-YmEppNxD7qQAsh32tC92A/viewform"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-sky-50 transition">
                    <i class="fa-solid fa-building text-sky-600 text-xl"></i>
                    <span class="text-sm font-medium">PPPK Sekretariat</span>
                </a>

                <a href="https://docs.google.com/forms/d/e/1FAIpQLSc1mv-ViBDEHsfTUVhphXyRscmifBZXwX5znuH8Ui4zX-KOmQ/viewform"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-emerald-50 transition">
                    <i class="fa-solid fa-broom text-emerald-600 text-xl"></i>
                    <span class="text-sm font-medium">PPPK Cleaning Service</span>
                </a>

                <a href="https://docs.google.com/forms/d/e/1FAIpQLSeuwvIKORTut8kHq5cyjYUSb_VX8WN-uMJkn096R91uHbAxqw/viewform"
                    target="_blank"
                    class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-indigo-50 transition">
                    <i class="fa-solid fa-shield-halved text-indigo-600 text-xl"></i>
                    <span class="text-sm font-medium">PPPK Security</span>
                </a>
            </div>
        </div>
    </div>