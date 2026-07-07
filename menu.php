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
$namaDepan = explode(' ', trim($namaLengkap))[0] ?: 'Pengguna';
?>

<style>
    /* ===================== MENU - LAYOUT SAMA SEPERTI RIWAYAT/PROFIL ===================== */
    html,
    body,
    body[data-page="menu"] {
        background: #fff !important;
        min-height: 100vh;
    }

    body[data-page="menu"] main,
    body[data-page="menu"] .menu-page-header,
    body[data-page="menu"] .menu-main {
        background: #fff !important;
    }

    /* Header dibuat sama posisi dan tema seperti halaman Riwayat */
    .menu-page-header {
        padding: 1.5rem 1.5rem .75rem !important;
        text-align: left !important;
        background: #fff !important;
        box-sizing: border-box !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
    }

    .menu-page-title {
        margin: 0 !important;
        color: #0369a1 !important;
        font-size: 1.25rem !important;
        line-height: 1.2 !important;
        font-weight: 800 !important;
    }

    .menu-page-subtitle {
        margin: .25rem 0 0 !important;
        color: #6b7280 !important;
        font-size: .875rem !important;
        line-height: 1.45 !important;
        font-weight: 500 !important;
    }

    /* Konten mengikuti halaman Riwayat: mulai dari kiri, tidak masuk ke tengah */
    .menu-main {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: .35rem 1.5rem 6.5rem !important;
        min-height: calc(100vh - 86px) !important;
        box-sizing: border-box !important;
        background: #fff !important;
    }

    .menu-group {
        width: 100% !important;
        max-width: none !important;
        margin: 0 0 1.5rem !important;
    }

    .menu-group-head {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: .75rem !important;
        margin-bottom: .65rem !important;
    }

    .menu-group-title {
        color: #94a3b8 !important;
        font-size: .72rem !important;
        font-weight: 900 !important;
        text-transform: uppercase !important;
        letter-spacing: .12em !important;
    }

    .menu-group-count {
        display: none !important;
    }

    /* List menu full lebar area konten, 1 kolom ke bawah */
    .menu-list {
        width: 100% !important;
        max-width: none !important;
        display: flex !important;
        flex-direction: column !important;
        grid-template-columns: none !important;
        gap: .65rem !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .menu-item {
        width: 100% !important;
        max-width: none !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: .8rem !important;
        min-height: 72px !important;
        padding: .9rem 1rem !important;
        background: #fff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 14px !important;
        box-shadow: none !important;
        text-decoration: none !important;
        color: inherit !important;
        box-sizing: border-box !important;
        text-align: left !important;
        transition: background .16s ease, border-color .16s ease !important;
    }

    .menu-item:hover {
        background: #ecfeff !important;
        border-color: #bae6fd !important;
        transform: none !important;
        box-shadow: none !important;
    }

    .menu-item:active {
        transform: scale(.99) !important;
    }

    .menu-icon {
        width: 42px !important;
        height: 42px !important;
        border-radius: 14px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex: 0 0 42px !important;
        font-size: 1rem !important;
    }

    .menu-text {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        text-align: left !important;
    }

    .menu-name {
        color: #334155 !important;
        font-size: .92rem !important;
        font-weight: 800 !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        text-align: left !important;
    }

    .menu-desc {
        margin-top: .22rem !important;
        color: #64748b !important;
        font-size: .78rem !important;
        font-weight: 500 !important;
        line-height: 1.35 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        text-align: left !important;
    }

    .menu-chevron {
        margin-left: auto !important;
        color: #cbd5e1 !important;
        font-size: .78rem !important;
        flex: 0 0 auto !important;
    }

    .soft-sky {
        background: #e0f2fe !important;
        color: #0284c7 !important;
    }

    .soft-purple {
        background: #f3e8ff !important;
        color: #9333ea !important;
    }

    .soft-red {
        background: #fee2e2 !important;
        color: #dc2626 !important;
    }

    .soft-emerald {
        background: #dcfce7 !important;
        color: #16a34a !important;
    }

    .soft-amber {
        background: #fef3c7 !important;
        color: #d97706 !important;
    }

    .soft-teal {
        background: #ccfbf1 !important;
        color: #0d9488 !important;
    }

    .soft-orange {
        background: #ffedd5 !important;
        color: #ea580c !important;
    }

    .soft-blue {
        background: #dbeafe !important;
        color: #2563eb !important;
    }

    .soft-lime {
        background: #ecfccb !important;
        color: #65a30d !important;
    }

    .soft-rose {
        background: #ffe4e6 !important;
        color: #e11d48 !important;
    }

    .soft-indigo {
        background: #e0e7ff !important;
        color: #4f46e5 !important;
    }

    .soft-slate {
        background: #f1f5f9 !important;
        color: #475569 !important;
    }

    @media (min-width: 1024px) {
        .menu-page-header {
            padding-left: 1.5rem !important;
            padding-right: 1.5rem !important;
        }

        .menu-main {
            padding-left: 1.5rem !important;
            padding-right: 1.5rem !important;
        }
    }

    @media (max-width: 520px) {
        .menu-page-header {
            padding: 1.5rem 1.5rem .65rem !important;
        }

        .menu-main {
            padding: .35rem 1.5rem 6.25rem !important;
        }

        .menu-group {
            margin-bottom: 1.5rem !important;
        }

        .menu-list {
            gap: .5rem !important;
        }

        .menu-item {
            min-height: 68px !important;
            border-radius: 14px !important;
            padding: .75rem .9rem !important;
            gap: .75rem !important;
            box-shadow: none !important;
        }

        .menu-icon {
            width: 38px !important;
            height: 38px !important;
            flex-basis: 38px !important;
            border-radius: 12px !important;
            font-size: .95rem !important;
        }

        .menu-name {
            font-size: .875rem !important;
        }

        .menu-desc {
            font-size: .72rem !important;
        }
    }

    /* ===================== BOTTOM SHEET MODAL FINAL FIX ===================== */
    .fade-bg {
        position: fixed !important;
        inset: 0 !important;
        background: rgba(15, 23, 42, .45) !important;
        backdrop-filter: blur(4px) !important;
        -webkit-backdrop-filter: blur(4px) !important;
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        transition: opacity .22s ease, visibility .22s ease !important;
        z-index: 9980 !important;
    }

    .fade-bg.show {
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
    }

    .sheet {
        position: fixed !important;
        left: 50% !important;
        bottom: 0 !important;
        width: min(100%, 520px) !important;
        max-height: 88vh !important;
        overflow-y: auto !important;
        background: #fff !important;
        border-radius: 26px 26px 0 0 !important;
        box-shadow: 0 -18px 55px rgba(15, 23, 42, .20) !important;
        transform: translate(-50%, 110%) !important;
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        transition: transform .26s ease, opacity .22s ease, visibility .22s ease !important;
        z-index: 9990 !important;
        box-sizing: border-box !important;
        padding-top: 10px !important;
    }

    .sheet.show {
        transform: translate(-50%, 0) !important;
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
    }

    .sheet-handle {
        width: 44px !important;
        height: 5px !important;
        border-radius: 999px !important;
        background: #d1d5db !important;
        margin: 0 auto 10px !important;
    }

    body.sheet-open {
        overflow: hidden !important;
        touch-action: none !important;
    }

    @media (min-width: 768px) {
        .sheet {
            bottom: 22px !important;
            border-radius: 26px !important;
            max-height: 82vh !important;
        }

        .sheet.show {
            transform: translate(-50%, 0) !important;
        }
    }

    @media (max-width: 520px) {
        .sheet {
            width: 100% !important;
            max-height: 90vh !important;
            border-radius: 24px 24px 0 0 !important;
        }
    }
</style>

<body data-page="menu">

    <!-- ===================== HEADER SECTION ===================== -->
    <div class="menu-page-header">
        <h2 class="menu-page-title">Menu Lainnya</h2>
        <p class="menu-page-subtitle">Seluruh fitur PAK RT Super App</p>
    </div>

    <main class="menu-main">
        <!-- ===== OPERASIONAL ===== -->
        <section class="menu-group">
            <div class="menu-group-head">
                <div class="menu-group-title">Operasional</div>
                <div class="menu-group-count">8 menu</div>
            </div>
            <div class="menu-list">
                <a href="timetable.php" class="menu-item" data-menu="timetable jadwal kegiatan operasional">
                    <div class="menu-icon soft-sky"><i class="fa-solid fa-calendar-days"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Timetable Kegiatan</div>
                        <div class="menu-desc">Lihat jadwal kegiatan terbaru</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <!-- <a href="peserta_penginapan.php" class="menu-item" data-menu="peserta pengajar penginapan cekin">
                    <div class="menu-icon soft-emerald"><i class="fa-solid fa-users"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Data Peserta</div>
                        <div class="menu-desc">Input peserta dan pengajar</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a> -->

                <a id="openUploadCekin" href="javascript:void(0)" class="menu-item" data-menu="cekin checkout checkin peserta pengajar">
                    <div class="menu-icon soft-purple"><i class="fa-solid fa-right-to-bracket"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Cekin Peserta &amp; Pengajar</div>
                        <div class="menu-desc">Monitoring check-in peserta</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a href="peminjaman_ruang_rapat.php" class="menu-item" data-menu="ruang rapat peminjaman booking">
                    <div class="menu-icon soft-sky"><i class="fa-solid fa-list-alt"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Peminjaman Ruang Rapat</div>
                        <div class="menu-desc">Lihat jadwal peminjaman ruang rapat</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a href="kendaraan.php" class="menu-item" data-menu="kendaraan mobil operasional">
                    <div class="menu-icon soft-teal"><i class="fa-solid fa-car-side"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Manajemen Kendaraan</div>
                        <div class="menu-desc">Database dan log kendaraan</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a href="daftar_tamu.php" class="menu-item" data-menu="buku tamu pengunjung">
                    <div class="menu-icon soft-orange"><i class="fa-solid fa-book-open"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Buku Tamu &amp; Pengunjung</div>
                        <div class="menu-desc">Database dan log tamu</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a href="presensi_lokasi_petugas.php" class="menu-item" data-menu="presensi lokasi absensi petugas kamera">
                    <div class="menu-icon soft-blue"><i class="fa-solid fa-camera"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Presensi Lokasi</div>
                        <div class="menu-desc">Presensi petugas berbasis lokasi</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a href="admin_log.php?tab=tracking" class="menu-item" data-menu="live tracking lokasi gps monitoring">
                    <div class="menu-icon soft-rose"><i class="fa-solid fa-location-crosshairs"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Live Tracking</div>
                        <div class="menu-desc">Pantau lokasi terakhir pengguna</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>
            </div>
        </section>

        <!-- ===== ADMINISTRASI ===== -->
        <section class="menu-group">
            <div class="menu-group-head">
                <div class="menu-group-title">Administrasi</div>
                <div class="menu-group-count">5 menu</div>
            </div>
            <div class="menu-list">
                <a href="arsip_surat.php" class="menu-item" data-menu="persuratan arsip surat dokumen">
                    <div class="menu-icon soft-amber"><i class="fa-solid fa-envelope-open-text"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Manajemen Persuratan</div>
                        <div class="menu-desc">Database dan log surat resmi</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a id="openUploadLaporanKerusakan" href="javascript:void(0)" class="menu-item" data-menu="kerusakan laporan fasilitas rusak">
                    <div class="menu-icon soft-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Laporan Kerusakan</div>
                        <div class="menu-desc">Laporkan fasilitas yang rusak</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a id="openUploadGudang" href="javascript:void(0)" class="menu-item" data-menu="gudang stok barang masuk keluar opname">
                    <div class="menu-icon soft-emerald"><i class="fa-solid fa-warehouse"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Manajemen Gudang</div>
                        <div class="menu-desc">Manajemen stok dan laporan gudang</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a href="riwayat.php" class="menu-item" data-menu="riwayat laporan aktivitas">
                    <div class="menu-icon soft-slate"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Riwayat</div>
                        <div class="menu-desc">Riwayat aktivitas dan laporan</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a href="statistik.php" class="menu-item" data-menu="statistik grafik laporan chart">
                    <div class="menu-icon soft-indigo"><i class="fa-solid fa-chart-column"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Statistik</div>
                        <div class="menu-desc">Grafik dan ringkasan data</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>
            </div>
        </section>

        <!-- ===== SISTEM & LAYANAN ===== -->
        <section class="menu-group">
            <div class="menu-group-head">
                <div class="menu-group-title">Sistem &amp; Layanan</div>
                <div class="menu-group-count">5 menu</div>
            </div>
            <div class="menu-list">
                <a href="https://sikep.mahkamahagung.go.id/site/login" target="_blank" class="menu-item" data-menu="sikep sistem kepegawaian">
                    <div class="menu-icon soft-emerald"><i class="fa-solid fa-id-badge"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">SIKEP</div>
                        <div class="menu-desc">Akses Sistem Kepegawaian</div>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square menu-chevron"></i>
                </a>

                <a href="https://asndigital.bkn.go.id/" target="_blank" class="menu-item" data-menu="e-kinerja kinerja bkn asn digital">
                    <div class="menu-icon soft-orange"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">E-Kinerja</div>
                        <div class="menu-desc">Input dan monitoring kinerja</div>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square menu-chevron"></i>
                </a>

                <a id="openUploadPresensi" href="javascript:void(0)" class="menu-item" data-menu="upload presensi bukti kehadiran bulanan">
                    <div class="menu-icon soft-lime"><i class="fa-solid fa-calendar-check"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Upload Presensi</div>
                        <div class="menu-desc">Upload bukti kehadiran bulanan</div>
                    </div>
                    <i class="fa-solid fa-chevron-right menu-chevron"></i>
                </a>

                <a href="https://forms.gle/zBkC5TxvVezbRN9N9" target="_blank" class="menu-item" data-menu="upload pkp dokumen bulanan">
                    <div class="menu-icon soft-rose"><i class="fa-solid fa-file-arrow-up"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Upload PKP</div>
                        <div class="menu-desc">Upload dokumen PKP bulanan</div>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square menu-chevron"></i>
                </a>

                <a href="https://viyatadhika.github.io/noext/" target="_blank" class="menu-item" data-menu="nomor ext telepon ekstensi kantor">
                    <div class="menu-icon soft-blue"><i class="fa-solid fa-phone-volume"></i></div>
                    <div class="menu-text">
                        <div class="menu-name">Nomor Ext Telepon</div>
                        <div class="menu-desc">Daftar nomor penting kantor</div>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square menu-chevron"></i>
                </a>
            </div>
        </section>
    </main>

    <?php include 'nav_monitoring.php'; ?>

    <!-- BG untuk Sheet Upload Presensi -->
    <div id="fadeBgPresensi" class="fade-bg"></div>

    <!-- SHEET UPLOAD PRESENSI -->
    <div id="sheetPresensi" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetPresensi" class="absolute top-3 right-4 text-gray-400 hover:text-gray-600 text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="sheetPresensiContent" class="p-4 pb-8 pt-2">
            <div class="text-center mb-5">
                <h2 class="text-lg font-bold text-sky-600">Upload Presensi</h2>
                <p class="text-xs text-gray-500 mt-1">Upload bukti kehadiran bulanan</p>
            </div>

            <div class="space-y-3">
                <a href="https://docs.google.com/forms/d/e/1FAIpQLSdJ0cE3fE7Snb6a4dzfQrM1VL9x-YmEppNxD7qQAsh32tC92A/viewform" target="_blank"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-sky-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center">
                        <i class="fa-solid fa-building text-sky-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">PPPK Sekretariat</p>
                        <p class="text-xs text-gray-500">Presensi pegawai sekretariat</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>

                <a href="https://docs.google.com/forms/d/e/1FAIpQLSc1mv-ViBDEHsfTUVhphXyRscmifBZXwX5znuH8Ui4zX-KOmQ/viewform" target="_blank"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-emerald-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <i class="fa-solid fa-broom text-emerald-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Cleaning Service</p>
                        <p class="text-xs text-gray-500">Presensi cleaning service</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>

                <a href="https://docs.google.com/forms/d/e/1FAIpQLSeuwvIKORTut8kHq5cyjYUSb_VX8WN-uMJkn096R91uHbAxqw/viewform" target="_blank"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-indigo-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <i class="fa-solid fa-shield-halved text-indigo-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Security</p>
                        <p class="text-xs text-gray-500">Presensi security</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- BG untuk Sheet Laporan Kerusakan -->
    <div id="fadeBgLaporanKerusakan" class="fade-bg"></div>

    <!-- SHEET LAPORAN KERUSAKAN -->
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

    <!-- BG untuk Sheet Gudang -->
    <div id="fadeBgGudang" class="fade-bg"></div>

    <!-- SHEET GUDANG -->
    <div id="sheetGudang" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetGudang" class="absolute top-3 right-4 text-gray-400 hover:text-gray-600 text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="sheetGudangContent" class="p-4 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-bold text-sky-600">Manajemen Gudang</h2>
                <p class="text-xs text-gray-500 mt-1">Manajemen stok &amp; laporan gudang</p>
            </div>

            <div class="space-y-3">
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

    <!-- BG untuk Sheet Cekin -->
    <div id="fadeBgCekin" class="fade-bg"></div>

    <!-- SHEET CEKIN -->
    <div id="sheetCekin" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetCekin"
            class="absolute top-3 right-4 w-9 h-9 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="sheetCekinContent" class="p-5 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-extrabold text-sky-600">Cekin Peserta &amp; Pengajar</h2>
                <p class="text-xs text-gray-500 mt-1">Monitoring check-in peserta</p>
            </div>

            <div class="space-y-3">
                <a href="peserta_penginapan.php"
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

                <a href="cekin_cekout.php"
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:bg-sky-50 transition-all active:scale-[0.98]">
                    <div class="w-12 h-12 rounded-2xl bg-sky-100 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-right-to-bracket text-sky-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800 truncate">Cekin dan Cekout</p>
                        <p class="text-xs text-gray-500 truncate">Monitoring kehadiran peserta</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400 text-xs"></i>
                </a>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function byId(id) {
                return document.getElementById(id);
            }

            function openSheet(sheetId, bgId) {
                const sheet = byId(sheetId);
                const bg = byId(bgId);
                if (!sheet || !bg) return;
                bg.classList.add('show');
                sheet.classList.add('show');
                document.body.classList.add('sheet-open');
            }

            function closeSheet(sheetId, bgId) {
                const sheet = byId(sheetId);
                const bg = byId(bgId);
                if (sheet) sheet.classList.remove('show');
                if (bg) bg.classList.remove('show');
                document.body.classList.remove('sheet-open');
            }

            function bindSheet(openId, closeId, sheetId, bgId) {
                const opener = byId(openId);
                const closer = byId(closeId);
                const bg = byId(bgId);

                if (opener) {
                    opener.addEventListener('click', function(e) {
                        e.preventDefault();
                        openSheet(sheetId, bgId);
                    });
                }

                if (closer) {
                    closer.addEventListener('click', function(e) {
                        e.preventDefault();
                        closeSheet(sheetId, bgId);
                    });
                }

                if (bg) {
                    bg.addEventListener('click', function() {
                        closeSheet(sheetId, bgId);
                    });
                }
            }

            bindSheet('openUploadPresensi', 'closeSheetPresensi', 'sheetPresensi', 'fadeBgPresensi');
            bindSheet('openUploadLaporanKerusakan', 'closeSheetLaporanKerusakan', 'sheetLaporanKerusakan', 'fadeBgLaporanKerusakan');
            bindSheet('openUploadGudang', 'closeSheetGudang', 'sheetGudang', 'fadeBgGudang');
            bindSheet('openUploadCekin', 'closeSheetCekin', 'sheetCekin', 'fadeBgCekin');

            document.addEventListener('keydown', function(e) {
                if (e.key !== 'Escape') return;
                closeSheet('sheetPresensi', 'fadeBgPresensi');
                closeSheet('sheetLaporanKerusakan', 'fadeBgLaporanKerusakan');
                closeSheet('sheetGudang', 'fadeBgGudang');
                closeSheet('sheetCekin', 'fadeBgCekin');
            });
        });
    </script>

    <?php include 'footer.php'; ?>