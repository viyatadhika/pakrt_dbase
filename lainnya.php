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
<!-- HEADER -->
<div class="p-6 text-left">
    <h2 class="text-xl font-bold text-sky-700">Menu Lainnya</h2>
    <p class="text-sm text-gray-500 mt-1">Akses cepat sistem & layanan</p>
</div>

<main class="lainnya-content">

    <!-- AKSES CEPAT -->
    <section class="mb-8">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Menu Cepat</h2>

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

        </div>
    </section>

    <!-- SISTEM & LAYANAN -->
    <section class="mb-4">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Sistem &amp; Layanan</h2>

        <div class="space-y-3">

            <!-- RIWAYAT -->
            <a
                href="riwayat.php"
                class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl 
           shadow-sm hover:shadow-md hover:bg-sky-50 transition-all">

                <div class="w-10 h-10 rounded-xl bg-sky-100 flex items-center justify-center">
                    <i class="fa-solid fa-clock-rotate-left text-sky-600 text-lg"></i>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-800">Riwayat</p>
                    <p class="text-xs text-gray-500">Lihat aktivitas checklist</p>
                </div>
            </a>

            <!-- STATISTIK -->
            <a
                href="statistik.php"
                class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-2xl 
           shadow-sm hover:shadow-md hover:bg-purple-50 transition-all">

                <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
                    <i class="fa-solid fa-chart-pie text-purple-600 text-lg"></i>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-800">Statistik</p>
                    <p class="text-xs text-gray-500">Visualisasi data checklist</p>
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
</main>


<?php include 'nav_monitoring.php'; ?>
<?php include 'footer.php'; ?>