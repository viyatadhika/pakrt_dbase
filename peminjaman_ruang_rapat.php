<?php
session_start();

$title = "Peminjaman Ruang Rapat";
include 'header.php';
include 'config.php';

$isLoggedIn = isset($_SESSION['user']);
$isAdmin = $isLoggedIn && strtolower($_SESSION['user']['role'] ?? '') === 'admin';

if (!defined('INSTANSI')) define('INSTANSI', 'Pusdiklat Mahkamah Agung RI');
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://192.168.200.49/wargart/');
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap');

    :root {
        --sky-blue: #0ea5e9;
        --sky-blue-dark: #0284c7;
        --sky-blue-light: #e0f2fe;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #fff;
        color: #1e293b;
    }

    html,
    body {
        overflow-x: hidden;
        height: auto !important;
        overflow-y: auto !important;
    }

    .sticky-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        background: #ffffff;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }

    .header-offset {
        padding-top: 73px;
    }

    @media (min-width: 768px) {
        .desktop-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 73px 2rem 2rem 2rem;
        }
    }

    .calendar-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 800;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        background-color: #f8fafc;
        color: #94a3b8;
    }

    .calendar-day:hover {
        background-color: #f1f5f9;
    }

    .cat-clash {
        background-color: #fee2e2 !important;
        color: #ef4444 !important;
        border: 2px dashed #f87171;
    }

    .is-today {
        box-shadow: 0 0 0 2px var(--sky-blue);
    }

    .card-selesai {
        opacity: 0.52;
        filter: grayscale(45%);
        transition: opacity 0.2s ease, filter 0.2s ease;
    }

    .card-selesai:hover {
        opacity: 0.75;
        filter: grayscale(20%);
    }

    .badge-selesai {
        background-color: #e2e8f0;
        color: #94a3b8;
        font-size: 8px;
        font-weight: 900;
        padding: 2px 7px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        line-height: 1.6;
    }

    .badge-soft {
        background: #f1f5f9;
        color: #64748b;
        font-size: 8px;
        font-weight: 900;
        padding: 2px 7px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .badge-clash {
        background: #fee2e2;
        color: #991b1b;
        font-size: 8px;
        font-weight: 900;
        padding: 2px 7px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .line-clamp-1 {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .form-readonly input,
    .form-readonly select,
    .form-readonly textarea {
        pointer-events: none;
        opacity: 0.65;
        background-color: #f8fafc !important;
    }

    .pin-code {
        font-size: 24px;
        font-weight: 900;
        letter-spacing: 8px;
        color: var(--sky-blue-dark);
        background: var(--sky-blue-light);
        border-radius: 16px;
        padding: 12px 18px;
        text-align: center;
    }

    .warning-box {
        display: none;
    }

    .warning-box.show {
        display: block;
    }

    .success-link-box {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 10px 12px;
    }

    .success-link-text {
        flex: 1;
        min-width: 0;
        font-size: 10px;
        font-weight: 700;
        color: #0369a1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .success-copy-btn {
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        background: #e0f2fe;
        color: #0369a1;
        border: none;
        cursor: pointer;
    }

    .qr-preview-wrap {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 14px;
        text-align: center;
    }

    .qr-preview-img {
        width: 180px;
        height: 180px;
        object-fit: contain;
        margin: 0 auto;
        background: white;
        border-radius: 14px;
        padding: 8px;
        border: 1px solid #e2e8f0;
    }

    ::-webkit-scrollbar {
        width: 4px;
    }

    ::-webkit-scrollbar-track {
        background: transparent;
    }

    ::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }
</style>

<header class="sticky-header px-5 pt-4 pb-0 relative border-b border-slate-100">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <button onclick="window.history.back()"
                class="w-10 h-10 shrink-0 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </button>
            <div class="min-w-0">
                <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Peminjaman Ruang Rapat</h1>
                <p class="text-[12px] text-gray-400 font-medium leading-tight"><?= INSTANSI ?></p>
            </div>
        </div>
        <button onclick="openExportModal()"
            class="absolute top-5 right-4 w-11 h-11 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition text-lg">
            <i class="fa-solid fa-download text-lg"></i>
        </button>
    </div>
</header>

<div class="desktop-grid header-offset">
    <aside class="space-y-6">
        <div class="bg-white border border-slate-100 p-6 rounded-[2rem] shadow-sm">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest" id="calendar-month">Bulan Ini</h3>
                <div class="flex space-x-1">
                    <button onclick="changeMonth(-1)" class="p-2 text-slate-400 hover:text-sky-500 transition-colors">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <button onclick="changeMonth(1)" class="p-2 text-slate-400 hover:text-sky-500 transition-colors">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-7 gap-1.5 text-[8px] font-black text-slate-300 uppercase text-center mb-2">
                <div>Min</div>
                <div>Sen</div>
                <div>Sel</div>
                <div>Rab</div>
                <div>Kam</div>
                <div>Jum</div>
                <div>Sab</div>
            </div>

            <div id="calendar-days" class="grid grid-cols-7 gap-1.5"></div>

            <div class="mt-8 pt-6 border-t border-slate-50 space-y-3">
                <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest mb-1">Lokasi Internal</p>
                <div id="room-legend" class="grid grid-cols-2 gap-2"></div>
            </div>
        </div>

        <div class="bg-white border border-slate-100 p-6 rounded-[2rem] shadow-sm">
            <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest mb-3">Filter Lokasi</p>
            <div id="room-filters" class="flex flex-wrap gap-2"></div>
        </div>
    </aside>

    <main class="px-6 md:px-0 mt-8 md:mt-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-5 w-1.5 bg-sky-500 rounded-full"></div>
                <h2 id="view-title" class="text-xs font-black text-slate-900 uppercase tracking-widest">Daftar Booking</h2>
            </div>
            <div class="flex items-center gap-2">
                <button id="btn-show-all" onclick="showAllBookings()"
                    class="hidden text-[10px] font-bold text-slate-500 hover:text-sky-600 px-3 py-1 rounded-full bg-slate-100 transition">
                    Lihat Semua
                </button>
                <span id="badge-count" class="text-[10px] font-bold text-sky-600 bg-sky-50 px-3 py-1 rounded-full">
                    0 Booking
                </span>
            </div>
        </div>

        <div id="list-items" class="space-y-6"></div>
    </main>
</div>

<button onclick="openBookingModal('create')"
    class="fixed bottom-8 right-8 w-11 h-11 bg-sky-600 text-white rounded-full shadow-lg shadow-sky-100 flex items-center justify-center z-[40] active:scale-90 transition-all">
    <i class="fa-solid fa-plus text-lg"></i>
</button>

<div id="bookingModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeBookingModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl max-h-[95vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p id="sheetTitle" class="text-sm font-extrabold text-gray-800">Booking Kegiatan</p>
                    <p class="text-[11px] text-gray-500"><?= INSTANSI ?></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="btnEditTrigger" onclick="enableEditMode()"
                        class="w-9 h-9 rounded-full bg-sky-50 text-sky-600 hidden flex items-center justify-center">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" onclick="closeBookingModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <form id="booking-form" onsubmit="handleSaveBooking(event)" class="space-y-3">
                <input type="hidden" id="booking-id">
                <input type="hidden" id="booking-pin">
                <input type="hidden" id="booking-room-id">

                <div>
                    <label class="text-xs font-bold text-gray-600">Jenis Lokasi</label>
                    <select id="f-jenis-lokasi" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                        <option value="internal">Dalam Kantor</option>
                        <option value="external">Luar Kantor / Hotel</option>
                    </select>
                </div>

                <div id="internal-room-wrap">
                    <label class="text-xs font-bold text-gray-600">Ruangan</label>
                    <select id="f-ruang" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                        <option value="">Memuat ruangan...</option>
                    </select>
                </div>

                <div id="external-location-wrap" class="hidden">
                    <label class="text-xs font-bold text-gray-600">Lokasi Luar Kantor / Hotel</label>
                    <input id="f-lokasi-external" type="text"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300"
                        placeholder="Contoh: Hotel Grand Mercure Jakarta">
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Info Lokasi</label>
                    <div id="room-info-box" class="mt-1 px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-[11px] text-slate-500">
                        Pilih jenis lokasi terlebih dahulu.
                    </div>
                </div>

                <div id="booking-warning" class="warning-box mt-1 rounded-2xl px-4 py-3 text-[11px] font-bold"></div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Nama Kegiatan / Rapat</label>
                    <input id="f-nama" type="text"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Nama Peminjam / Bidang</label>
                    <input id="f-peminjam" type="text"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Tanggal Mulai</label>
                        <input id="f-start" type="date"
                            class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Tanggal Selesai</label>
                        <input id="f-end" type="date"
                            class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Jam Mulai</label>
                        <input id="f-jam-start" type="time"
                            class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Jam Selesai</label>
                        <input id="f-jam-end" type="time"
                            class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Jumlah Peserta</label>
                        <input id="f-peserta" type="number" min="0"
                            class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">No. WhatsApp</label>
                        <input id="f-wa" type="text"
                            class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Keterangan</label>
                    <textarea id="f-ket" rows="3"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300 resize-none"></textarea>
                </div>

                <div id="detail-links" class="hidden space-y-2 pt-2">
                    <a id="btn-isi-absensi" href="#" target="_blank"
                        class="w-full block text-center py-3 rounded-2xl bg-green-50 text-green-700 font-extrabold text-sm">
                        <i class="fa-solid fa-signature mr-2"></i> Isi Absensi
                    </a>

                    <button type="button" onclick="requestOpenAbsensiMonitor()"
                        class="w-full py-3 rounded-2xl bg-emerald-50 text-emerald-700 font-extrabold text-sm">
                        <i class="fa-solid fa-clipboard-list mr-2"></i> Buka Absensi
                    </button>

                    <button type="button" onclick="requestOpenNotulen()"
                        class="w-full py-3 rounded-2xl bg-blue-50 text-blue-700 font-extrabold text-sm">
                        <i class="fa-solid fa-file-lines mr-2"></i> Buka Notulen
                    </button>
                </div>

                <button id="btnVerifyPin" type="button" onclick="openPinModal('show_manage')"
                    class="w-full py-3 rounded-2xl bg-slate-100 text-slate-600 font-extrabold text-sm hidden">
                    <i class="fa-solid fa-key mr-2"></i> Kelola Booking (butuh PIN)
                </button>

                <button id="btnSubmit" type="submit"
                    class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm hidden">
                    Simpan Booking
                </button>

                <button id="btnHapus" type="button" onclick="requestDelete()"
                    class="w-full py-3 rounded-2xl bg-red-50 text-red-600 font-extrabold text-sm hidden">
                    <i class="fa-solid fa-trash-can mr-2"></i> Hapus Booking
                </button>
            </form>
        </div>
    </div>
</div>

<div id="pinModal" class="fixed inset-0 bg-black/50 z-[1000] hidden">
    <div class="absolute inset-0" onclick="closePinModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-sm bg-white rounded-3xl p-5 shadow-xl">
            <div class="text-center">
                <div class="text-4xl mb-3">🔑</div>
                <p class="text-sm font-extrabold text-slate-800">Verifikasi PIN</p>
                <p class="text-[11px] text-slate-500 mt-1">Masukkan PIN 4 digit booking Anda</p>
            </div>

            <input id="pin-input" type="number" placeholder="1234"
                class="w-full mt-4 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 text-center text-xl font-black tracking-[0.4em] outline-none">

            <button onclick="verifyPinAction()"
                class="w-full mt-4 py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">
                <i class="fa-solid fa-check mr-2"></i> Verifikasi
            </button>

            <button onclick="closePinModal()"
                class="w-full mt-2 py-3 rounded-2xl bg-slate-100 text-slate-600 font-extrabold text-sm">
                Batal
            </button>
        </div>
    </div>
</div>

<div id="successModal" class="fixed inset-0 bg-black/50 z-[1000] hidden">
    <div class="absolute inset-0" onclick="closeSuccessModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl max-h-[95vh] overflow-y-auto">
            <div class="bg-green-50 rounded-2xl p-5 text-center">
                <div class="text-4xl mb-2">✅</div>
                <p class="text-[16px] font-extrabold text-green-700">Booking Berhasil!</p>
                <p class="text-[12px] text-green-600 mt-1">Link akses dan QR absensi sudah siap</p>
            </div>

            <div class="mt-5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">PIN Booking Anda</p>
                <div id="success-pin" class="pin-code">1234</div>
                <p class="text-[10px] text-slate-500 text-center mt-2 font-medium">
                    Simpan PIN ini untuk membuka monitoring, notulen, mengubah, atau menghapus booking
                </p>
            </div>

            <div class="mt-5 space-y-3">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link Halaman Booking</p>
                    <div class="success-link-box">
                        <span id="success-link-booking" class="success-link-text">-</span>
                        <button type="button" onclick="copyTextContent('success-link-booking')" class="success-copy-btn">Salin</button>
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link Isi Absensi</p>
                    <div class="success-link-box">
                        <span id="success-link-abs" class="success-link-text">-</span>
                        <button type="button" onclick="copyTextContent('success-link-abs')" class="success-copy-btn">Salin</button>
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link Monitoring Absensi</p>
                    <div class="success-link-box">
                        <span id="success-link-monitor" class="success-link-text">-</span>
                        <button type="button" onclick="copyTextContent('success-link-monitor')" class="success-copy-btn">Salin</button>
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link Notulen</p>
                    <div class="success-link-box">
                        <span id="success-link-not" class="success-link-text">-</span>
                        <button type="button" onclick="copyTextContent('success-link-not')" class="success-copy-btn">Salin</button>
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">QR Code Absensi</p>
                    <div class="qr-preview-wrap">
                        <img id="success-qr-abs" src="" alt="QR Absensi" class="qr-preview-img">
                        <p class="text-[10px] text-slate-500 mt-3 font-medium">Scan QR untuk membuka halaman isi absensi</p>
                    </div>
                </div>
            </div>

            <a id="success-wa-link" href="#" target="_blank"
                class="w-full mt-5 block text-center py-3 rounded-2xl bg-green-600 text-white font-extrabold text-sm">
                <i class="fa-brands fa-whatsapp mr-2"></i> Kirim ke WhatsApp Saya
            </a>

            <button onclick="closeSuccessModal()"
                class="w-full mt-2 py-3 rounded-2xl bg-slate-100 text-slate-600 font-extrabold text-sm">
                Tutup
            </button>
        </div>
    </div>
</div>

<div id="exportModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeExportModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-extrabold text-gray-800">Download Laporan</p>
                    <p class="text-[11px] text-gray-500">Pilih rentang tanggal booking</p>
                </div>
                <button onclick="closeExportModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="text-xs font-bold text-gray-600">Dari Tanggal</label>
                    <input type="date" id="exportFrom" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600">Sampai Tanggal</label>
                    <input type="date" id="exportTo" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                </div>

                <button onclick="downloadExport()" class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">
                    Download PDF
                </button>

                <p class="text-[10px] text-gray-400 text-center">Default otomatis 30 hari terakhir</p>
            </div>
        </div>
    </div>
</div>

<div id="toast"
    class="fixed top-24 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-6 py-3 rounded-full text-[10px] font-bold shadow-xl opacity-0 pointer-events-none transition-all duration-300 z-[200]">
    Aksi Berhasil!
</div>

<script>
    const API_URL = 'booking_rapat_api.php';
    const BASE_URL = <?= json_encode(BASE_URL) ?>;
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

    let bookingsData = [];
    let roomsData = [];
    let viewDate = new Date();
    let filterDate = null;
    let filterRoom = '';
    let currentBookingId = null;
    let verifiedPin = null;
    let pinAction = null;
    let currentModalMode = 'detail';
    let abortController = new AbortController();
    let checkBentrokTimer = null;

    const FALLBACK_COLORS = ['#3b82f6', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#14b8a6', '#f97316', '#6366f1'];

    function normalizeText(str) {
        return String(str ?? '').trim();
    }

    function slugify(text) {
        return normalizeText(text).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    }

    function esc(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str ?? ''));
        return div.innerHTML;
    }

    function getTodayStr() {
        return new Date().toISOString().split('T')[0];
    }

    function getField(id) {
        return document.getElementById(id);
    }

    function setVal(id, value) {
        const el = getField(id);
        if (el) el.value = value ?? '';
    }

    function getVal(id) {
        return getField(id)?.value ?? '';
    }

    function showToast(message) {
        const toast = getField('toast');
        toast.innerText = message;
        toast.style.opacity = '1';
        setTimeout(() => {
            toast.style.opacity = '0';
        }, 2800);
    }

    function copyTextContent(id) {
        const text = getField(id)?.innerText || '';
        navigator.clipboard?.writeText(text)
            .then(() => showToast('Link berhasil disalin'))
            .catch(() => showToast('Gagal menyalin link'));
    }

    function setFormReadOnly(state) {
        const form = getField('booking-form');
        if (!form) return;
        if (state) form.classList.add('form-readonly');
        else form.classList.remove('form-readonly');
    }

    function getRoomColor(index) {
        return FALLBACK_COLORS[index % FALLBACK_COLORS.length];
    }

    function formatDateID(dateStr) {
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }).format(new Date(dateStr));
    }

    function getRoomByName(name) {
        return roomsData.find(room => room.nama_ruang === name) || null;
    }

    function getRoomMetaByName(name) {
        const room = getRoomByName(name);
        if (room) return room;
        return {
            id: '',
            nama_ruang: name || '-',
            lokasi: '-',
            kapasitas: 0,
            fasilitas: '-',
            aktif: 1,
            color: '#94a3b8',
            short: name || '-'
        };
    }

    function showBookingWarning(type, html) {
        const box = getField('booking-warning');
        if (!box) return;

        box.className = 'warning-box show mt-1 rounded-2xl px-4 py-3 text-[11px] font-bold';

        if (type === 'danger') {
            box.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-200');
        } else if (type === 'success') {
            box.classList.add('bg-green-50', 'text-green-700', 'border', 'border-green-200');
        } else {
            box.classList.add('bg-slate-50', 'text-slate-600', 'border', 'border-slate-200');
        }

        box.innerHTML = html;
    }

    function clearBookingWarning() {
        const box = getField('booking-warning');
        if (!box) return;
        box.className = 'warning-box mt-1 rounded-2xl px-4 py-3 text-[11px] font-bold';
        box.innerHTML = '';
    }

    async function parseJsonSafe(response, label) {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (err) {
            console.error(`Response ${label} bukan JSON:`, text);
            showToast(`Response ${label} bukan JSON`);
            throw err;
        }
    }

    async function loadRooms() {
        try {
            const res = await fetch(API_URL + '?action=room_list');
            const raw = await parseJsonSafe(res, 'room_list');

            roomsData = (Array.isArray(raw) ? raw : []).map((room, index) => ({
                id: String(room.id),
                nama_ruang: normalizeText(room.nama_ruang),
                lokasi: normalizeText(room.lokasi),
                kapasitas: Number(room.kapasitas || 0),
                fasilitas: normalizeText(room.fasilitas),
                aktif: Number(room.aktif || 0),
                color: getRoomColor(index),
                short: normalizeText(room.nama_ruang),
                slug: slugify(room.nama_ruang)
            }));

            renderRoomLegend();
            renderRoomFilters();
            renderRoomOptions();
        } catch (error) {
            console.error(error);
            showToast('Gagal memuat data ruangan');
        }
    }

    function renderRoomLegend() {
        const container = getField('room-legend');
        if (!container) return;

        let html = roomsData.map(room => `
            <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100">
                <div class="w-2.5 h-2.5 rounded-full" style="background:${room.color};"></div>
                <span class="text-[9px] font-bold text-slate-600 line-clamp-1">${esc(room.short)}</span>
            </div>
        `).join('');

        html += `
            <div class="flex items-center gap-2 p-2 rounded-xl bg-red-50 border border-red-100 col-span-2">
                <div class="w-2.5 h-2.5 rounded-full bg-red-500"></div>
                <span class="text-[9px] font-black text-red-600">Jadwal Tumpang Tindih</span>
            </div>
            <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100 col-span-2">
                <div class="w-2.5 h-2.5 rounded-full bg-slate-300"></div>
                <span class="text-[9px] font-bold text-slate-400">Booking Sudah Selesai</span>
            </div>
        `;

        container.innerHTML = html;
    }

    function renderRoomFilters() {
        const container = getField('room-filters');
        if (!container) return;

        let html = `
            <button class="room-tab px-3 py-2 rounded-full text-[10px] font-bold bg-sky-600 text-white" data-room="">
                Semua Lokasi
            </button>
            <button class="room-tab px-3 py-2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500" data-room="__EXTERNAL__">
                Luar Kantor
            </button>
        `;

        html += roomsData.map(room => `
            <button class="room-tab px-3 py-2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500"
                data-room="${esc(room.nama_ruang)}">
                ${esc(room.short)}
            </button>
        `).join('');

        container.innerHTML = html;
    }

    function renderRoomOptions() {
        const select = getField('f-ruang');
        if (!select) return;

        if (!roomsData.length) {
            select.innerHTML = `<option value="">Tidak ada ruangan aktif</option>`;
            return;
        }

        select.innerHTML = roomsData.map(room => `
            <option value="${esc(room.nama_ruang)}">
                ${esc(room.nama_ruang)} — ${esc(room.lokasi)} (Kapasitas: ${room.kapasitas})
            </option>
        `).join('');

        syncSelectedRoomId();
        updateLokasiMode();
    }

    function syncSelectedRoomId() {
        const selectedName = getVal('f-ruang');
        const hiddenRoomId = getField('booking-room-id');
        if (!hiddenRoomId) return;

        const room = getRoomByName(selectedName);
        hiddenRoomId.value = room ? room.id : '';
    }

    function updateLokasiMode() {
        const jenis = getVal('f-jenis-lokasi');
        const wrapInternal = getField('internal-room-wrap');
        const wrapExternal = getField('external-location-wrap');
        const infoBox = getField('room-info-box');

        if (jenis === 'external') {
            wrapInternal.classList.add('hidden');
            wrapExternal.classList.remove('hidden');

            const lokasi = normalizeText(getVal('f-lokasi-external'));
            if (lokasi) {
                infoBox.innerHTML = `<div><span class="font-bold text-slate-700">Lokasi Luar Kantor:</span> ${esc(lokasi)}</div>`;
            } else {
                infoBox.innerHTML = 'Isi nama hotel / lokasi kegiatan luar kantor.';
            }
        } else {
            wrapInternal.classList.remove('hidden');
            wrapExternal.classList.add('hidden');
            syncSelectedRoomId();

            const selectedName = getVal('f-ruang');
            const room = getRoomByName(selectedName);

            if (!room) {
                infoBox.innerHTML = 'Pilih ruangan untuk melihat lokasi, kapasitas, dan fasilitas.';
                return;
            }

            infoBox.innerHTML = `
                <div class="space-y-1">
                    <div><span class="font-bold text-slate-700">Lokasi:</span> ${esc(room.lokasi || '-')}</div>
                    <div><span class="font-bold text-slate-700">Kapasitas:</span> ${room.kapasitas || 0} orang</div>
                    <div><span class="font-bold text-slate-700">Fasilitas:</span> ${esc(room.fasilitas || '-')}</div>
                </div>
            `;
        }
    }

    async function checkBentrokRealtime() {
        const id = getVal('booking-id');
        const jenisLokasi = getVal('f-jenis-lokasi');
        const roomId = getVal('booking-room-id');
        const lokasiExternal = normalizeText(getVal('f-lokasi-external'));
        const start = getVal('f-start');
        const end = getVal('f-end');
        const jamStart = getVal('f-jam-start');
        const jamEnd = getVal('f-jam-end');

        if (jenisLokasi === 'external') {
            clearBookingWarning();
            if (lokasiExternal) {
                showBookingWarning('success', 'Lokasi luar kantor tidak dicek bentrok ruangan.');
            }
            return;
        }

        if (!roomId || !start || !end || !jamStart || !jamEnd) {
            clearBookingWarning();
            return;
        }

        const fd = new FormData();
        fd.append('id', id);
        fd.append('jenis_lokasi', jenisLokasi);
        fd.append('room_id', roomId);
        fd.append('start', start);
        fd.append('end', end);
        fd.append('jam_start', jamStart);
        fd.append('jam_end', jamEnd);

        try {
            const res = await fetch(API_URL + '?action=booking_check', {
                method: 'POST',
                body: fd
            });

            const result = await parseJsonSafe(res, 'booking_check');

            if (result.error) {
                showBookingWarning('danger', esc(result.error));
                return;
            }

            if (result.bentrok) {
                const itemsHtml = (result.items || []).map(item =>
                    `<li>${esc(item.nama)} — ${esc(item.start_date)} s/d ${esc(item.end_date)} (${esc((item.jam_start || '').slice(0,5))} - ${esc((item.jam_end || '').slice(0,5))})</li>`
                ).join('');

                showBookingWarning(
                    'danger',
                    `Jadwal bentrok dengan booking lain.<ul class="list-disc ml-4 mt-2">${itemsHtml}</ul>`
                );
            } else {
                showBookingWarning('success', 'Jadwal tersedia.');
            }
        } catch (error) {
            console.error(error);
        }
    }

    function queueCheckBentrok() {
        clearTimeout(checkBentrokTimer);
        checkBentrokTimer = setTimeout(checkBentrokRealtime, 350);
    }

    async function loadBookings() {
        abortController.abort();
        abortController = new AbortController();

        try {
            const res = await fetch(API_URL + '?action=booking_list', {
                signal: abortController.signal
            });
            const raw = await parseJsonSafe(res, 'booking_list');

            bookingsData = Array.isArray(raw) ? raw.map(item => ({
                ...item,
                id: String(item.id),
                room_id: item.room_id ? String(item.room_id) : '',
                jenis_lokasi: normalizeText(item.jenis_lokasi || 'internal'),
                lokasi_external: normalizeText(item.lokasi_external || ''),
                lokasi_display: normalizeText(item.lokasi_display || item.ruang || item.lokasi_external || ''),
                nama: normalizeText(item.nama),
                peminjam: normalizeText(item.peminjam),
                ruang: normalizeText(item.ruang),
                ket: normalizeText(item.ket),
                start_date: item.start_date,
                end_date: item.end_date,
                jam_start: normalizeText(item.jam_start || '08:00'),
                jam_end: normalizeText(item.jam_end || '12:00'),
                peserta: Number(item.peserta || 0),
                wa: normalizeText(item.wa || ''),
                is_bentrok: !!item.is_bentrok
            })) : [];

            bookingsData = markBentrok(bookingsData);
            renderCalendar();
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
                showToast('Gagal memuat data booking');
            }
        }
    }

    function markBentrok(data) {
        return data.map((a, i) => ({
            ...a,
            is_bentrok: a.jenis_lokasi === 'internal' && data.some((b, j) =>
                i !== j &&
                a.jenis_lokasi === 'internal' &&
                b.jenis_lokasi === 'internal' &&
                a.ruang === b.ruang &&
                a.start_date <= b.end_date &&
                a.end_date >= b.start_date &&
                a.jam_start < b.jam_end &&
                a.jam_end > b.jam_start
            )
        }));
    }

    function getFilteredData(baseData = bookingsData) {
        let data = [...baseData];

        if (filterDate) {
            data = data.filter(item => filterDate >= item.start_date && filterDate <= item.end_date);
        } else {
            const y = viewDate.getFullYear();
            const m = viewDate.getMonth();

            data = data.filter(item => {
                const s = new Date(item.start_date);
                const e = new Date(item.end_date);
                return (
                    (s.getFullYear() === y && s.getMonth() === m) ||
                    (e.getFullYear() === y && e.getMonth() === m) ||
                    (s < new Date(y, m + 1, 0) && e > new Date(y, m, 1))
                );
            });
        }

        if (filterRoom) {
            if (filterRoom === '__EXTERNAL__') {
                data = data.filter(item => item.jenis_lokasi === 'external');
            } else {
                data = data.filter(item =>
                    item.jenis_lokasi === 'internal' &&
                    item.ruang === filterRoom
                );
            }
        }

        return data;
    }

    function renderCalendar() {
        const container = getField('calendar-days');
        const monthLabel = getField('calendar-month');
        container.innerHTML = '';

        const year = viewDate.getFullYear();
        const month = viewDate.getMonth();

        monthLabel.innerText = new Intl.DateTimeFormat('id-ID', {
            month: 'long',
            year: 'numeric'
        }).format(viewDate);

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const todayStr = getTodayStr();

        for (let i = 0; i < firstDay; i++) {
            container.innerHTML += `<div></div>`;
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const events = bookingsData.filter(item => dateStr >= item.start_date && dateStr <= item.end_date);
            const isToday = dateStr === todayStr;

            let stateClass = '';
            let inlineStyle = '';

            if (events.length === 1) {
                if (events[0].jenis_lokasi === 'internal') {
                    const room = getRoomMetaByName(events[0].ruang);
                    inlineStyle = `background:${room.color}22;color:${room.color};`;
                } else {
                    inlineStyle = `background:#e0f2fe;color:#0369a1;`;
                }
            } else if (events.length > 1) {
                const bentrokSameRoom = events.some((ev, idx) =>
                    ev.jenis_lokasi === 'internal' &&
                    events.some((other, j) =>
                        idx !== j &&
                        other.jenis_lokasi === 'internal' &&
                        ev.ruang === other.ruang &&
                        ev.start_date <= other.end_date &&
                        ev.end_date >= other.start_date &&
                        ev.jam_start < other.jam_end &&
                        ev.jam_end > other.jam_start
                    )
                );

                if (bentrokSameRoom) {
                    stateClass = 'cat-clash';
                } else {
                    inlineStyle = `background:#dbeafe;color:#1d4ed8;`;
                }
            }

            container.innerHTML += `
                <div onclick="filterByDate('${dateStr}')"
                    class="calendar-day ${stateClass} ${isToday ? 'is-today' : ''}"
                    style="${inlineStyle}">
                    ${day}
                </div>
            `;
        }

        getField('view-title').innerText = 'Daftar Booking';
        getField('btn-show-all').classList.add('hidden');
        renderList();
    }

    function groupBentrok(data) {
        const groups = [];
        const used = new Set();

        for (let i = 0; i < data.length; i++) {
            if (used.has(data[i].id)) continue;

            const group = [data[i]];
            used.add(data[i].id);

            for (let j = i + 1; j < data.length; j++) {
                if (used.has(data[j].id)) continue;

                const overlaps = group.some(g =>
                    g.jenis_lokasi === 'internal' &&
                    data[j].jenis_lokasi === 'internal' &&
                    g.ruang === data[j].ruang &&
                    data[j].start_date <= g.end_date &&
                    data[j].end_date >= g.start_date &&
                    data[j].jam_start < g.jam_end &&
                    data[j].jam_end > g.jam_start
                );

                if (overlaps) {
                    group.push(data[j]);
                    used.add(data[j].id);
                }
            }

            groups.push(group);
        }

        return groups;
    }

    function renderList(sourceData = null) {
        const listContainer = getField('list-items');
        const data = sourceData ? [...sourceData] : getFilteredData();

        getField('badge-count').innerText = data.length + ' Booking';

        if (!data.length) {
            listContainer.innerHTML = `
                <div class="text-center py-10 bg-white rounded-[2.5rem] border border-slate-50 text-slate-300 text-[10px] font-bold uppercase tracking-widest">
                    Tidak ada booking ditemukan
                </div>
            `;
            return;
        }

        const todayStr = getTodayStr();
        const sorted = [...data].sort((a, b) => {
            const compareDate = a.start_date.localeCompare(b.start_date);
            if (compareDate !== 0) return compareDate;
            return (a.jam_start || '').localeCompare(b.jam_start || '');
        });

        const groups = groupBentrok(sorted);

        listContainer.innerHTML = groups.map(group => {
            const isBentrok = group.length > 1;

            const cardsHtml = group.map(item => {
                const isSelesai = item.end_date < todayStr;
                const d = new Date(item.start_date);
                const day = String(d.getDate()).padStart(2, '0');
                const monthNames = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agt", "Sep", "Okt", "Nov", "Des"];
                const mon = monthNames[d.getMonth()];

                const room = item.jenis_lokasi === 'internal' ?
                    getRoomMetaByName(item.ruang) : {
                        color: '#0ea5e9',
                        short: 'Luar Kantor'
                    };

                const badgeSelesai = isSelesai ? `<span class="badge-selesai">Selesai</span>` : '';
                const badgeLokasi = `<span class="badge-soft">${esc(item.jenis_lokasi === 'internal' ? item.ruang : 'Luar Kantor')}</span>`;
                const badgeJam = `<span class="badge-soft">${esc((item.jam_start || '').slice(0,5))} - ${esc((item.jam_end || '').slice(0,5))}</span>`;
                const badgeBentrok = item.is_bentrok ? `<span class="badge-clash">Bentrok</span>` : '';

                return `
                    <div onclick="openBookingModal('detail', '${item.id}')"
                        class="bg-white border border-slate-50 p-5 rounded-[2.2rem] shadow-sm flex items-start space-x-4 cursor-pointer ${isSelesai ? 'card-selesai' : ''}">
                        <div class="w-14 h-14 rounded-[1.2rem] flex flex-col items-center justify-center text-white font-black flex-shrink-0"
                            style="background:${room.color};">
                            <span class="text-[12px] leading-none mb-0.5">${day}</span>
                            <span class="text-[8px] uppercase opacity-80">${mon}</span>
                        </div>

                        <div class="flex-1 overflow-hidden">
                            <div class="flex justify-between items-start mb-0.5 gap-2">
                                <h4 class="text-[13px] font-extrabold text-slate-800 leading-snug pr-2 line-clamp-2">
                                    ${esc(item.nama)}
                                </h4>
                                <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                    ${badgeSelesai}
                                    ${badgeLokasi}
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 mb-2.5">
                                <i class="fa-regular fa-calendar-check text-sky-500 text-[9px]"></i>
                                <span class="text-[10px] font-bold text-slate-400">
                                    ${formatDateID(item.start_date)} — ${formatDateID(item.end_date)}
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-1.5 mb-2.5">
                                ${badgeJam}
                                ${badgeBentrok}
                            </div>

                            <div class="grid grid-cols-2 gap-y-1.5 mt-1 border-t border-slate-50 pt-2.5">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-user text-slate-300 text-[10px] w-3"></i>
                                    <span class="text-[10px] font-bold text-slate-500 truncate">${esc(item.peminjam || '-')}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-users text-slate-300 text-[10px] w-3"></i>
                                    <span class="text-[10px] font-bold text-slate-500">${Number(item.peserta || 0)} Peserta</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-slate-300 text-[10px] w-3"></i>
                                    <span class="text-[10px] font-bold text-slate-500 truncate">${esc(item.lokasi_display || '-')}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fa-brands fa-whatsapp text-slate-300 text-[10px] w-3"></i>
                                    <span class="text-[10px] font-bold text-slate-500 truncate">${esc(item.wa || '-')}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="space-y-3">
                    ${isBentrok ? `
                        <div class="flex items-center gap-2 px-2">
                            <span class="flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                            <span class="text-[9px] font-black text-red-500 uppercase tracking-widest">
                                Jadwal Tumpang Tindih (${group.length} Booking)
                            </span>
                        </div>
                    ` : ''}
                    <div class="space-y-3 ${isBentrok ? 'p-3 bg-red-50/30 rounded-[2.5rem] border border-red-100/50' : ''}">
                        ${cardsHtml}
                    </div>
                </div>
            `;
        }).join('');
    }

    function changeMonth(direction) {
        viewDate.setMonth(viewDate.getMonth() + direction);
        renderCalendar();
    }

    function filterByDate(dateStr) {
        filterDate = dateStr;
        const events = getFilteredData(bookingsData).filter(item =>
            dateStr >= item.start_date && dateStr <= item.end_date
        );

        renderList(events);
        getField('view-title').innerText = dateStr.split('-').reverse().join(' / ');
        getField('badge-count').innerText = events.length + ' Booking';
        getField('btn-show-all').classList.remove('hidden');
    }

    function showAllBookings() {
        filterDate = null;
        getField('view-title').innerText = 'Daftar Booking';
        renderCalendar();
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.room-tab');
        if (!btn) return;

        document.querySelectorAll('.room-tab').forEach(tab => {
            tab.classList.remove('bg-sky-600', 'text-white');
            tab.classList.add('bg-slate-100', 'text-slate-500');
        });

        btn.classList.remove('bg-slate-100', 'text-slate-500');
        btn.classList.add('bg-sky-600', 'text-white');

        filterRoom = btn.dataset.room || '';

        if (filterDate) {
            const events = getFilteredData(bookingsData).filter(item =>
                filterDate >= item.start_date && filterDate <= item.end_date
            );
            renderList(events);
            getField('badge-count').innerText = events.length + ' Booking';
            getField('btn-show-all').classList.remove('hidden');
        } else {
            renderCalendar();
        }
    });

    function showModal(id) {
        getField(id).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function hideModal(id) {
        getField(id).classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function closeBookingModal() {
        hideModal('bookingModal');
        setFormReadOnly(false);
        verifiedPin = null;
        clearBookingWarning();

        const btnSubmit = getField('btnSubmit');
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerText = currentModalMode === 'edit' ? 'Simpan Perubahan' : 'Simpan Booking';
        }
    }

    function fillFormFromItem(item) {
        setVal('booking-id', item.id);
        setVal('booking-pin', '');
        setVal('f-jenis-lokasi', item.jenis_lokasi || 'internal');
        setVal('f-nama', item.nama);
        setVal('f-peminjam', item.peminjam);
        setVal('f-start', item.start_date);
        setVal('f-end', item.end_date);
        setVal('f-jam-start', (item.jam_start || '08:00').slice(0, 5));
        setVal('f-jam-end', (item.jam_end || '12:00').slice(0, 5));
        setVal('f-peserta', item.peserta ?? '');
        setVal('f-wa', item.wa || '');
        setVal('f-ket', item.ket || '');

        if (item.jenis_lokasi === 'external') {
            setVal('f-lokasi-external', item.lokasi_external || item.lokasi_display || '');
            setVal('f-ruang', '');
            setVal('booking-room-id', '');
        } else {
            setVal('f-lokasi-external', '');
            setVal('f-ruang', item.ruang || '');
            const room = getRoomByName(item.ruang);
            setVal('booking-room-id', room ? room.id : (item.room_id || ''));
        }

        updateLokasiMode();
    }

    function openBookingModal(mode, id = null) {
        currentModalMode = mode;
        currentBookingId = id ? String(id) : null;
        verifiedPin = null;
        clearBookingWarning();

        const form = getField('booking-form');
        form.reset();

        setVal('booking-id', '');
        setVal('booking-pin', '');
        setVal('booking-room-id', '');

        const today = getTodayStr();
        setVal('f-jenis-lokasi', 'internal');
        setVal('f-start', today);
        setVal('f-end', today);
        setVal('f-jam-start', '08:00');
        setVal('f-jam-end', '12:00');
        setVal('f-lokasi-external', '');

        renderRoomOptions();

        const btnEditTrigger = getField('btnEditTrigger');
        const btnVerifyPin = getField('btnVerifyPin');
        const btnSubmit = getField('btnSubmit');
        const btnHapus = getField('btnHapus');
        const detailLinks = getField('detail-links');

        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnVerifyPin) btnVerifyPin.classList.add('hidden');
        if (btnSubmit) btnSubmit.classList.add('hidden');
        if (btnHapus) btnHapus.classList.add('hidden');
        if (detailLinks) detailLinks.classList.add('hidden');

        if (mode === 'create') {
            getField('sheetTitle').innerText = 'Booking Kegiatan';
            setFormReadOnly(false);

            if (btnSubmit) {
                btnSubmit.classList.remove('hidden');
                btnSubmit.innerText = 'Simpan & Kirim Link';
            }

            updateLokasiMode();
            queueCheckBentrok();
            showModal('bookingModal');
            return;
        }

        const item = bookingsData.find(row => String(row.id) === String(id));
        if (!item) {
            showToast('Data booking tidak ditemukan');
            return;
        }

        fillFormFromItem(item);
        getField('btn-isi-absensi').href = BASE_URL + 'absensi_rapat.php?id=' + item.id;

        if (detailLinks) detailLinks.classList.remove('hidden');
        getField('sheetTitle').innerText = 'Detail Booking';
        setFormReadOnly(true);

        if (btnVerifyPin) btnVerifyPin.classList.remove('hidden');
        if (IS_ADMIN && btnEditTrigger) btnEditTrigger.classList.remove('hidden');

        showModal('bookingModal');
    }

    function enableEditMode() {
        const btnSubmit = getField('btnSubmit');
        const btnHapus = getField('btnHapus');
        const btnEditTrigger = getField('btnEditTrigger');
        const btnVerifyPin = getField('btnVerifyPin');

        currentModalMode = 'edit';
        getField('sheetTitle').innerText = 'Ubah Booking';
        setFormReadOnly(false);

        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnVerifyPin) btnVerifyPin.classList.add('hidden');

        if (btnSubmit) {
            btnSubmit.classList.remove('hidden');
            btnSubmit.disabled = false;
            btnSubmit.innerText = 'Simpan Perubahan';
            btnSubmit.type = 'submit';
        }

        if (btnHapus) btnHapus.classList.remove('hidden');

        queueCheckBentrok();
    }

    function openPinModal(action) {
        pinAction = action;
        setVal('pin-input', '');
        showModal('pinModal');
    }

    function requestOpenAbsensiMonitor() {
        openPinModal('open_absensi_monitor');
    }

    function requestOpenNotulen() {
        openPinModal('open_notulen');
    }

    function closePinModal() {
        hideModal('pinModal');
    }

    async function verifyPinAction() {
        const pin = normalizeText(getVal('pin-input'));
        const bookingId = getVal('booking-id') || currentBookingId;

        if (!bookingId) {
            showToast('Booking tidak ditemukan');
            return;
        }

        if (pin.length !== 4) {
            showToast('PIN harus 4 digit');
            return;
        }

        try {
            const fd = new FormData();
            fd.append('id', bookingId);
            fd.append('pin', pin);

            const res = await fetch(API_URL + '?action=booking_verify', {
                method: 'POST',
                body: fd
            });

            const data = await parseJsonSafe(res, 'booking_verify');

            if (!data.valid) {
                showToast('PIN salah');
                return;
            }

            verifiedPin = pin;
            setVal('booking-pin', pin);
            closePinModal();

            if (pinAction === 'show_manage') {
                showToast('PIN benar');
                enableEditMode();
            } else if (pinAction === 'delete') {
                doDeleteBooking();
            } else if (pinAction === 'open_absensi_monitor') {
                window.open(BASE_URL + 'absensi.php?id=' + bookingId + '&pin=' + encodeURIComponent(pin), '_blank');
            } else if (pinAction === 'open_notulen') {
                window.open(BASE_URL + 'notulen.php?id=' + bookingId + '&pin=' + encodeURIComponent(pin), '_blank');
            }
        } catch (error) {
            console.error(error);
            showToast('Gagal verifikasi PIN');
        }
    }

    async function handleSaveBooking(e) {
        e.preventDefault();

        const id = getVal('booking-id');
        const pinStored = getVal('booking-pin') || verifiedPin || '';

        const jenisLokasi = getVal('f-jenis-lokasi');
        const roomId = getVal('booking-room-id');
        const lokasiExternal = normalizeText(getVal('f-lokasi-external'));
        const ruang = getVal('f-ruang');

        const nama = normalizeText(getVal('f-nama'));
        const peminjam = normalizeText(getVal('f-peminjam'));
        const start = getVal('f-start');
        const end = getVal('f-end');
        const jamStart = getVal('f-jam-start');
        const jamEnd = getVal('f-jam-end');
        const peserta = getVal('f-peserta');
        const wa = normalizeText(getVal('f-wa'));
        const ket = normalizeText(getVal('f-ket'));

        if (!nama || !peminjam || !start || !end || !wa) {
            showToast('Lengkapi semua field wajib');
            return;
        }

        if (jenisLokasi === 'internal' && (!ruang || !roomId)) {
            showToast('Pilih ruangan internal');
            return;
        }

        if (jenisLokasi === 'external' && !lokasiExternal) {
            showToast('Isi lokasi luar kantor / hotel');
            return;
        }

        if (start > end) {
            showToast('Tanggal mulai tidak boleh lebih besar dari selesai');
            return;
        }

        if (jamStart && jamEnd && jamStart > jamEnd) {
            showToast('Jam mulai tidak boleh lebih besar dari jam selesai');
            return;
        }

        if (id && !pinStored) {
            showToast('PIN booking belum terverifikasi');
            return;
        }

        const fd = new FormData();
        fd.append('jenis_lokasi', jenisLokasi);
        fd.append('room_id', jenisLokasi === 'internal' ? roomId : '');
        fd.append('ruang', ruang);
        fd.append('lokasi_external', jenisLokasi === 'external' ? lokasiExternal : '');
        fd.append('nama', nama);
        fd.append('peminjam', peminjam);
        fd.append('start', start);
        fd.append('end', end);
        fd.append('jam_start', jamStart);
        fd.append('jam_end', jamEnd);
        fd.append('peserta', peserta || 0);
        fd.append('wa', wa);
        fd.append('ket', ket);

        let endpoint = API_URL + '?action=booking_create';

        const btnSubmit = getField('btnSubmit');
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerText = 'Menyimpan...';
        }

        if (id) {
            fd.append('id', id);
            fd.append('pin', pinStored);
            endpoint = API_URL + '?action=booking_update';
        }

        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                body: fd
            });

            const result = await parseJsonSafe(res, id ? 'booking_update' : 'booking_create');

            if (result.error) {
                showToast('Error: ' + result.error);

                if (result.bentrok && result.bentrok.length) {
                    const itemsHtml = result.bentrok.map(item =>
                        `<li>${esc(item.nama)} — ${esc(item.start_date)} s/d ${esc(item.end_date)} (${esc((item.jam_start || '').slice(0,5))} - ${esc((item.jam_end || '').slice(0,5))})</li>`
                    ).join('');

                    showBookingWarning(
                        'danger',
                        `Jadwal bentrok dengan booking lain.<ul class="list-disc ml-4 mt-2">${itemsHtml}</ul>`
                    );
                }
                return;
            }

            closeBookingModal();

            if (!id) {
                getField('success-pin').innerText = result.pin || '----';
                getField('success-link-booking').innerText = result.link_booking || '-';
                getField('success-link-abs').innerText = result.link_absensi || '-';
                getField('success-link-monitor').innerText = result.link_monitor || '-';
                getField('success-link-not').innerText = result.link_notulen || '-';

                const qrImg = getField('success-qr-abs');
                qrImg.src = result.qr_absensi_url || '';
                qrImg.style.display = result.qr_absensi_url ? 'block' : 'none';

                const waUrl = result.wa_url || '#';
                getField('success-wa-link').href = waUrl;

                showModal('successModal');
            } else {
                showToast('✓ Booking berhasil diubah');
            }

            await loadBookings();
        } catch (error) {
            console.error(error);
            showToast('Gagal menyimpan booking');
        } finally {
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerText = id ? 'Simpan Perubahan' : 'Simpan & Kirim Link';
            }
        }
    }

    function requestDelete() {
        openPinModal('delete');
    }

    async function doDeleteBooking() {
        if (!confirm('Hapus booking ini? Tindakan tidak dapat dibatalkan.')) return;

        const id = getVal('booking-id') || currentBookingId;
        const pin = getVal('booking-pin') || verifiedPin || '';

        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('pin', pin);

            const res = await fetch(API_URL + '?action=booking_delete', {
                method: 'POST',
                body: fd
            });

            const result = await parseJsonSafe(res, 'booking_delete');

            if (result.error) {
                showToast('Error: ' + result.error);
                return;
            }

            closeBookingModal();
            showToast('✓ Booking berhasil dihapus');
            await loadBookings();
        } catch (error) {
            console.error(error);
            showToast('Gagal menghapus booking');
        }
    }

    function closeSuccessModal() {
        hideModal('successModal');
    }

    function openExportModal() {
        showModal('exportModal');

        const today = new Date();
        const prior = new Date();
        prior.setDate(today.getDate() - 30);

        getField('exportTo').value = today.toISOString().split('T')[0];
        getField('exportFrom').value = prior.toISOString().split('T')[0];
    }

    function closeExportModal() {
        hideModal('exportModal');
    }

    function downloadExport() {
        const from = getVal('exportFrom');
        const to = getVal('exportTo');

        if (!from || !to) {
            showToast('Pilih rentang tanggal');
            return;
        }

        if (from > to) {
            showToast('Tanggal awal tidak boleh lebih besar dari akhir');
            return;
        }

        window.location.href = 'export_pdf.php?from=' + from + '&to=' + to;
        closeExportModal();
    }

    getField('f-jenis-lokasi').addEventListener('change', () => {
        updateLokasiMode();
        queueCheckBentrok();
    });
    getField('f-ruang').addEventListener('change', () => {
        syncSelectedRoomId();
        updateLokasiMode();
        queueCheckBentrok();
    });
    getField('f-lokasi-external').addEventListener('input', () => {
        updateLokasiMode();
        queueCheckBentrok();
    });
    getField('f-start').addEventListener('change', queueCheckBentrok);
    getField('f-end').addEventListener('change', queueCheckBentrok);
    getField('f-jam-start').addEventListener('change', queueCheckBentrok);
    getField('f-jam-end').addEventListener('change', queueCheckBentrok);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            Promise.all([loadRooms(), loadBookings()]);
        } else {
            abortController.abort();
        }
    });

    window.addEventListener('beforeunload', () => abortController.abort());
    window.addEventListener('pagehide', () => abortController.abort());

    window.onload = async function() {
        await loadRooms();
        await loadBookings();
    };
</script>