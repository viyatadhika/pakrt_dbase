<?php
session_start();

$title = "Peminjaman Ruang Rapat";
include 'header.php';
include 'config.php';

$isLoggedIn = isset($_SESSION['user']);
$isAdmin = $isLoggedIn && strtolower($_SESSION['user']['role'] ?? '') === 'admin';

if (!defined('INSTANSI')) define('INSTANSI', 'Pusdiklat Mahkamah Agung RI');
if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://app.bsdk.mahkamahagung.go.id/wargart/');
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
        text-decoration: none;
        display: block;
    }

    .success-link-text:hover {
        text-decoration: underline;
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
        transition: background 0.2s, color 0.2s;
        flex-shrink: 0;
    }

    .success-copy-btn.copied {
        background: #dcfce7;
        color: #16a34a;
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

    .admin-pin-box {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 18px;
        padding: 14px 16px;
    }

    .admin-pin-title {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #b45309;
        margin-bottom: 6px;
    }

    .admin-pin-value {
        font-size: 22px;
        font-weight: 900;
        letter-spacing: 0.35em;
        color: #92400e;
    }

    .admin-pin-help {
        font-size: 10px;
        color: #b45309;
        margin-top: 8px;
        font-weight: 600;
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
                    <button onclick="changeMonth(-1)" class="p-2 text-slate-400 hover:text-sky-500 transition-colors"><i class="fa-solid fa-chevron-left text-xs"></i></button>
                    <button onclick="changeMonth(1)" class="p-2 text-slate-400 hover:text-sky-500 transition-colors"><i class="fa-solid fa-chevron-right text-xs"></i></button>
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
                <span id="badge-count" class="text-[10px] font-bold text-sky-600 bg-sky-50 px-3 py-1 rounded-full">0 Booking</span>
            </div>
        </div>
        <div id="list-items" class="space-y-6"></div>
    </main>
</div>

<button onclick="openBookingModal('create')"
    class="fixed bottom-8 right-8 w-11 h-11 bg-sky-600 text-white rounded-full shadow-lg shadow-sky-100 flex items-center justify-center z-[40] active:scale-90 transition-all">
    <i class="fa-solid fa-plus text-lg"></i>
</button>

<!-- Modal Booking -->
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
                    <button type="button" onclick="closeBookingModal()"
                        class="w-9 h-9 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center">
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

                <div id="admin-pin-box" class="admin-pin-box hidden">
                    <p class="admin-pin-title">PIN Booking</p>
                    <div id="admin-pin-text" class="admin-pin-value">----</div>
                    <p class="admin-pin-help">PIN ini bisa diberikan ke user jika lupa.</p>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Nama Kegiatan / Rapat</label>
                    <input id="f-nama" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600">Nama Peminjam / Bidang</label>
                    <input id="f-peminjam" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Tanggal Mulai</label>
                        <input id="f-start" type="date" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Tanggal Selesai</label>
                        <input id="f-end" type="date" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Jam Mulai</label>
                        <input id="f-jam-start" type="time" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Jam Selesai</label>
                        <input id="f-jam-end" type="time" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Jumlah Peserta</label>
                        <input id="f-peserta" type="number" min="0" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">No. WhatsApp</label>
                        <input id="f-wa" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600">Keterangan</label>
                    <textarea id="f-ket" rows="3" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300 resize-none"></textarea>
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
                    <button type="button" id="btnKirimWA" onclick="kirimUlangWA()"
                        class="w-full py-3 rounded-2xl bg-green-600 text-white font-extrabold text-sm hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                            style="width:16px;height:16px;display:inline;vertical-align:-2px;margin-right:6px">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                        </svg>
                        Kirim Ulang ke WhatsApp
                    </button>
                </div>

                <button id="btnVerifyPin" type="button" onclick="openPinModal('show_manage')"
                    class="w-full py-3 rounded-2xl bg-slate-100 text-slate-600 font-extrabold text-sm hidden">
                    <i class="fa-solid fa-key mr-2"></i> Kelola Booking
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

<!-- Modal PIN -->
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
            <button onclick="verifyPinAction()" class="w-full mt-4 py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">
                <i class="fa-solid fa-check mr-2"></i> Verifikasi
            </button>
            <button onclick="closePinModal()" class="w-full mt-2 py-3 rounded-2xl bg-slate-100 text-slate-600 font-extrabold text-sm">
                Batal
            </button>
        </div>
    </div>
</div>

<!-- Modal Success -->
<div id="successModal" class="fixed inset-0 bg-black/50 z-[1000] hidden">
    <div class="absolute inset-0" onclick="closeSuccessModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl max-h-[95vh] overflow-y-auto">
            <div class="bg-green-50 rounded-2xl p-5 text-center">
                <div class="text-4xl mb-2" id="success-banner-emoji">✅</div>
                <p class="text-[16px] font-extrabold text-green-700" id="success-banner-title">Booking Berhasil!</p>
                <p class="text-[12px] text-green-600 mt-1" id="success-banner-sub">Link akses dan QR absensi sudah siap</p>
            </div>
            <div class="mt-5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">PIN Booking</p>
                <div id="success-pin" class="pin-code">1234</div>
                <p class="text-[10px] text-slate-500 text-center mt-2 font-medium">
                    Simpan PIN ini untuk membuka monitoring, notulen, mengubah, atau menghapus booking
                </p>
            </div>
            <div class="mt-5 space-y-3">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link Halaman Booking</p>
                    <div class="success-link-box">
                        <a id="success-link-booking" href="#" target="_blank" class="success-link-text">-</a>
                        <button type="button" onclick="copyLink('success-link-booking', this)" class="success-copy-btn">Salin</button>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link Isi Absensi</p>
                    <div class="success-link-box">
                        <a id="success-link-abs" href="#" target="_blank" class="success-link-text">-</a>
                        <button type="button" onclick="copyLink('success-link-abs', this)" class="success-copy-btn">Salin</button>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link Monitoring Absensi</p>
                    <div class="success-link-box">
                        <a id="success-link-monitor" href="#" target="_blank" class="success-link-text">-</a>
                        <button type="button" onclick="copyLink('success-link-monitor', this)" class="success-copy-btn">Salin</button>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Link Notulen</p>
                    <div class="success-link-box">
                        <a id="success-link-not" href="#" target="_blank" class="success-link-text">-</a>
                        <button type="button" onclick="copyLink('success-link-not', this)" class="success-copy-btn">Salin</button>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">QR Code Absensi</p>
                    <div class="qr-preview-wrap">
                        <a id="success-qr-link" href="#" target="_blank">
                            <img id="success-qr-abs" src="" alt="QR Absensi" class="qr-preview-img">
                        </a>
                        <p class="text-[10px] text-slate-500 mt-3 font-medium">Scan QR / klik gambar untuk membuka halaman isi absensi</p>
                    </div>
                </div>
            </div>
            <a id="success-wa-link" href="#" target="_blank"
                class="w-full mt-5 flex items-center justify-center gap-2 py-3 rounded-2xl bg-green-600 text-white font-extrabold text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;flex-shrink:0">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                </svg>
                <span id="success-wa-label">Kirim ke WhatsApp Saya</span>
            </a>
            <button onclick="closeSuccessModal()" class="w-full mt-2 py-3 rounded-2xl bg-slate-100 text-slate-600 font-extrabold text-sm">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- Modal Export -->
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

    /* ── utils ── */
    const normalizeText = str => String(str ?? '').trim();
    const slugify = t => normalizeText(t).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str ?? ''));
        return d.innerHTML;
    }

    const getTodayStr = () => new Date().toISOString().split('T')[0];
    const getField = id => document.getElementById(id);
    const setVal = (id, v) => {
        const el = getField(id);
        if (el) el.value = v ?? '';
    };
    const getVal = id => getField(id)?.value ?? '';

    function showToast(msg) {
        const t = getField('toast');
        t.innerText = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', 2800);
    }

    /* ── Format created_at → "25 Apr 2026, 09:30" ── */
    function formatCreatedAt(str) {
        if (!str) return '';
        const d = new Date(str.replace(' ', 'T'));
        if (isNaN(d)) return '';
        const tgl = d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
        const jam = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        return tgl + ', ' + jam;
    }

    /* ── copyLink — HTTP-safe fallback ── */
    function copyLink(id, btn) {
        const el = getField(id);
        const text = el?.href && el.href !== '#' ? el.href : (el?.innerText || '');
        if (!text || text === '#') {
            showToast('Link belum tersedia');
            return;
        }

        const doFallback = () => {
            try {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                markCopied(btn);
            } catch (e) {
                showToast('Gagal menyalin — pilih teks lalu Ctrl+C');
            }
        };

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => markCopied(btn)).catch(doFallback);
        } else {
            doFallback();
        }
    }

    function markCopied(btn) {
        if (!btn) return;
        btn.textContent = '✓ Disalin';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.textContent = 'Salin';
            btn.classList.remove('copied');
        }, 2000);
    }

    function fillSuccessModal({
        pin,
        linkBooking,
        linkAbs,
        linkMonitor,
        linkNotulen,
        qrUrl,
        waUrl,
        isResend = false
    }) {
        getField('success-pin').innerText = pin;
        const setLink = (id, url) => {
            const el = getField(id);
            if (!el) return;
            el.href = url || '#';
            el.innerText = url || '-';
        };
        setLink('success-link-booking', linkBooking);
        setLink('success-link-abs', linkAbs);
        setLink('success-link-monitor', linkMonitor);
        setLink('success-link-not', linkNotulen);
        const qrImg = getField('success-qr-abs');
        const qrLink = getField('success-qr-link');
        qrImg.src = qrUrl;
        qrImg.style.display = 'block';
        if (qrLink) qrLink.href = linkAbs || '#';
        getField('success-wa-link').href = waUrl || '#';
        getField('success-banner-emoji').textContent = isResend ? '📲' : '✅';
        getField('success-banner-title').textContent = isResend ? 'Kirim Ulang Info Booking' : 'Booking Berhasil!';
        getField('success-banner-sub').textContent = isResend ? 'Tap tombol WhatsApp untuk kirim ulang ke peminjam' : 'Link akses dan QR absensi sudah siap';
        getField('success-wa-label').textContent = isResend ? 'Kirim Ulang ke WhatsApp Peminjam' : 'Kirim ke WhatsApp Saya';
    }

    function buildQrUrl(targetUrl, size = '300x300') {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' + encodeURIComponent(size) + '&data=' + encodeURIComponent(targetUrl);
    }

    function buildWaMessage(nama, peminjam, lokasiDisplay, startDate, endDate, jamStart, jamEnd, pin, linkAbs, linkMonitor, linkNotulen, qrUrl) {
        const tgl = startDate === endDate ? startDate : startDate + ' s.d. ' + endDate;
        const jam = jamStart.slice(0, 5) + ' - ' + jamEnd.slice(0, 5) + ' WIB';
        const sep = '-'.repeat(30);
        return (
            '[ INFORMASI BOOKING RUANG RAPAT ]\n' +
            'Pusdiklat Mahkamah Agung RI\n' + sep + '\n' +
            'Kegiatan : ' + nama + '\n' +
            'Peminjam : ' + peminjam + '\n' +
            'Lokasi   : ' + lokasiDisplay + '\n' +
            'Tanggal  : ' + tgl + '\n' +
            'Waktu    : ' + jam + '\n' + sep + '\n' +
            'PIN Booking : ' + pin + '\n' +
            '_(Gunakan PIN untuk akses monitoring,\nnotulen, edit & hapus booking)_\n' + sep + '\n' +
            '[ LINK AKSES ]\n' +
            '>> Isi Absensi\n' + linkAbs + '\n\n' +
            '>> Monitoring Absensi\n' + linkMonitor + '\n\n' +
            '>> Notulen\n' + linkNotulen + '\n\n' +
            '>> QR Code Absensi\n' + qrUrl + '\n' + sep + '\n' +
            '_Terima kasih. Hubungi admin jika ada pertanyaan._'
        );
    }

    const getRoomColor = i => FALLBACK_COLORS[i % FALLBACK_COLORS.length];
    const getRoomByName = name => roomsData.find(r => r.nama_ruang === name) || null;
    const getRoomMetaByName = name => getRoomByName(name) || {
        id: '',
        nama_ruang: name || '-',
        lokasi: '-',
        kapasitas: 0,
        fasilitas: '-',
        aktif: 1,
        color: '#94a3b8',
        short: name || '-'
    };

    function formatDateID(dateStr) {
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }).format(new Date(dateStr));
    }

    function showBookingWarning(type, html) {
        const box = getField('booking-warning');
        if (!box) return;
        box.className = 'warning-box show mt-1 rounded-2xl px-4 py-3 text-[11px] font-bold';
        if (type === 'danger') box.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-200');
        else if (type === 'success') box.classList.add('bg-green-50', 'text-green-700', 'border', 'border-green-200');
        else box.classList.add('bg-slate-50', 'text-slate-600', 'border', 'border-slate-200');
        box.innerHTML = html;
    }

    function clearBookingWarning() {
        const box = getField('booking-warning');
        if (!box) return;
        box.className = 'warning-box mt-1 rounded-2xl px-4 py-3 text-[11px] font-bold';
        box.innerHTML = '';
    }

    function showAdminPin(pinValue = '') {
        const box = getField('admin-pin-box'),
            text = getField('admin-pin-text');
        if (!box || !text) return;
        if (IS_ADMIN && pinValue) {
            text.innerText = pinValue;
            box.classList.remove('hidden');
        } else {
            text.innerText = '----';
            box.classList.add('hidden');
        }
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

    /* ── Rooms ── */
    async function loadRooms() {
        try {
            const res = await fetch(API_URL + '?action=room_list');
            const raw = await parseJsonSafe(res, 'room_list');
            roomsData = (Array.isArray(raw) ? raw : []).map((room, i) => ({
                id: String(room.id),
                nama_ruang: normalizeText(room.nama_ruang),
                lokasi: normalizeText(room.lokasi),
                kapasitas: Number(room.kapasitas || 0),
                fasilitas: normalizeText(room.fasilitas),
                aktif: Number(room.aktif || 0),
                color: getRoomColor(i),
                short: normalizeText(room.nama_ruang),
                slug: slugify(room.nama_ruang)
            }));
            renderRoomLegend();
            renderRoomFilters();
            renderRoomOptions();
        } catch (e) {
            console.error(e);
            showToast('Gagal memuat data ruangan');
        }
    }

    function renderRoomLegend() {
        const c = getField('room-legend');
        if (!c) return;
        let html = roomsData.map(r => `
            <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100">
                <div class="w-2.5 h-2.5 rounded-full" style="background:${r.color};"></div>
                <span class="text-[9px] font-bold text-slate-600 line-clamp-1">${esc(r.short)}</span>
            </div>`).join('');
        html += `
            <div class="flex items-center gap-2 p-2 rounded-xl bg-red-50 border border-red-100 col-span-2">
                <div class="w-2.5 h-2.5 rounded-full bg-red-500"></div>
                <span class="text-[9px] font-black text-red-600">Jadwal Tumpang Tindih</span>
            </div>
            <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100 col-span-2">
                <div class="w-2.5 h-2.5 rounded-full bg-slate-300"></div>
                <span class="text-[9px] font-bold text-slate-400">Booking Sudah Selesai</span>
            </div>`;
        c.innerHTML = html;
    }

    function renderRoomFilters() {
        const c = getField('room-filters');
        if (!c) return;
        let html = `
            <button class="room-tab px-3 py-2 rounded-full text-[10px] font-bold bg-sky-600 text-white" data-room="">Semua Lokasi</button>
            <button class="room-tab px-3 py-2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500" data-room="__EXTERNAL__">Luar Kantor</button>`;
        html += roomsData.map(r => `
            <button class="room-tab px-3 py-2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500" data-room="${esc(r.nama_ruang)}">${esc(r.short)}</button>`).join('');
        c.innerHTML = html;
    }

    function renderRoomOptions() {
        const s = getField('f-ruang');
        if (!s) return;
        if (!roomsData.length) {
            s.innerHTML = `<option value="">Tidak ada ruangan aktif</option>`;
            return;
        }
        s.innerHTML = roomsData.map(r =>
            `<option value="${esc(r.nama_ruang)}">${esc(r.nama_ruang)} — ${esc(r.lokasi)} (Kapasitas: ${r.kapasitas})</option>`
        ).join('');
        syncSelectedRoomId();
        updateLokasiMode();
    }

    function syncSelectedRoomId() {
        const room = getRoomByName(getVal('f-ruang'));
        const el = getField('booking-room-id');
        if (el) el.value = room ? room.id : '';
    }

    function updateLokasiMode() {
        const jenis = getVal('f-jenis-lokasi');
        const wI = getField('internal-room-wrap'),
            wE = getField('external-location-wrap'),
            ib = getField('room-info-box');
        if (jenis === 'external') {
            wI.classList.add('hidden');
            wE.classList.remove('hidden');
            const lok = normalizeText(getVal('f-lokasi-external'));
            ib.innerHTML = lok ? `<div><span class="font-bold text-slate-700">Lokasi Luar Kantor:</span> ${esc(lok)}</div>` : 'Isi nama hotel / lokasi kegiatan luar kantor.';
        } else {
            wI.classList.remove('hidden');
            wE.classList.add('hidden');
            syncSelectedRoomId();
            const room = getRoomByName(getVal('f-ruang'));
            if (!room) {
                ib.innerHTML = 'Pilih ruangan untuk melihat lokasi, kapasitas, dan fasilitas.';
                return;
            }
            ib.innerHTML = `<div class="space-y-1">
                <div><span class="font-bold text-slate-700">Lokasi:</span> ${esc(room.lokasi||'-')}</div>
                <div><span class="font-bold text-slate-700">Kapasitas:</span> ${room.kapasitas||0} orang</div>
                <div><span class="font-bold text-slate-700">Fasilitas:</span> ${esc(room.fasilitas||'-')}</div></div>`;
        }
    }

    /* ── Bentrok ── */
    async function checkBentrokRealtime() {
        const id = getVal('booking-id'),
            jenis = getVal('f-jenis-lokasi');
        const roomId = getVal('booking-room-id'),
            lokasiExt = normalizeText(getVal('f-lokasi-external'));
        const start = getVal('f-start'),
            end = getVal('f-end');
        const jamStart = getVal('f-jam-start'),
            jamEnd = getVal('f-jam-end');
        if (jenis === 'external') {
            clearBookingWarning();
            if (lokasiExt) showBookingWarning('success', 'Lokasi luar kantor tidak dicek bentrok ruangan.');
            return;
        }
        if (!roomId || !start || !end || !jamStart || !jamEnd) {
            clearBookingWarning();
            return;
        }
        const fd = new FormData();
        fd.append('id', id);
        fd.append('jenis_lokasi', jenis);
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
                const ih = (result.items || []).map(it => `<li>${esc(it.nama)} — ${esc(it.start_date)} s/d ${esc(it.end_date)} (${esc((it.jam_start||'').slice(0,5))} - ${esc((it.jam_end||'').slice(0,5))})</li>`).join('');
                showBookingWarning('danger', `Jadwal bentrok dengan booking lain.<ul class="list-disc ml-4 mt-2">${ih}</ul>`);
            } else {
                showBookingWarning('success', 'Jadwal tersedia.');
            }
        } catch (e) {
            console.error(e);
        }
    }

    function queueCheckBentrok() {
        clearTimeout(checkBentrokTimer);
        checkBentrokTimer = setTimeout(checkBentrokRealtime, 350);
    }

    /* ── Bookings ── */
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
                lokasi_ruang: normalizeText(item.lokasi_ruang || ''),
                ket: normalizeText(item.ket),
                pin: normalizeText(item.pin || ''),
                start_date: item.start_date,
                end_date: item.end_date,
                jam_start: normalizeText(item.jam_start || '08:00'),
                jam_end: normalizeText(item.jam_end || '12:00'),
                peserta: Number(item.peserta || 0),
                wa: normalizeText(item.wa || ''),
                created_at: normalizeText(item.created_at || ''), // ← tambahan
                is_bentrok: !!item.is_bentrok
            })) : [];
            bookingsData = markBentrok(bookingsData);
            renderCalendar();
        } catch (e) {
            if (e.name !== 'AbortError') {
                console.error(e);
                showToast('Gagal memuat data booking');
            }
        }
    }

    function markBentrok(data) {
        return data.map((a, i) => ({
            ...a,
            is_bentrok: a.jenis_lokasi === 'internal' && data.some((b, j) =>
                i !== j && b.jenis_lokasi === 'internal' && a.ruang === b.ruang &&
                a.start_date <= b.end_date && a.end_date >= b.start_date &&
                a.jam_start < b.jam_end && a.jam_end > b.jam_start
            )
        }));
    }

    function getFilteredData(baseData = bookingsData) {
        let data = [...baseData];
        if (filterDate) {
            data = data.filter(it => filterDate >= it.start_date && filterDate <= it.end_date);
        } else {
            const y = viewDate.getFullYear(),
                m = viewDate.getMonth();
            data = data.filter(it => {
                const s = new Date(it.start_date),
                    e = new Date(it.end_date);
                return (s.getFullYear() === y && s.getMonth() === m) ||
                    (e.getFullYear() === y && e.getMonth() === m) ||
                    (s < new Date(y, m + 1, 0) && e > new Date(y, m, 1));
            });
        }
        if (filterRoom) {
            if (filterRoom === '__EXTERNAL__') data = data.filter(it => it.jenis_lokasi === 'external');
            else data = data.filter(it => it.jenis_lokasi === 'internal' && it.ruang === filterRoom);
        }
        return data;
    }

    /* ── Calendar ── */
    function renderCalendar() {
        const container = getField('calendar-days'),
            monthLabel = getField('calendar-month');
        container.innerHTML = '';
        const y = viewDate.getFullYear(),
            m = viewDate.getMonth();
        monthLabel.innerText = new Intl.DateTimeFormat('id-ID', {
            month: 'long',
            year: 'numeric'
        }).format(viewDate);
        const firstDay = new Date(y, m, 1).getDay(),
            daysInMonth = new Date(y, m + 1, 0).getDate(),
            todayStr = getTodayStr();
        for (let i = 0; i < firstDay; i++) container.innerHTML += `<div></div>`;
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${y}-${String(m+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const events = bookingsData.filter(it => dateStr >= it.start_date && dateStr <= it.end_date);
            const isToday = dateStr === todayStr;
            let sc = '',
                is = '';
            if (events.length === 1) {
                if (events[0].jenis_lokasi === 'internal') {
                    const r = getRoomMetaByName(events[0].ruang);
                    is = `background:${r.color}22;color:${r.color};`;
                } else is = `background:#e0f2fe;color:#0369a1;`;
            } else if (events.length > 1) {
                const clash = events.some((ev, idx) => ev.jenis_lokasi === 'internal' && events.some((ot, j) =>
                    idx !== j && ot.jenis_lokasi === 'internal' && ev.ruang === ot.ruang &&
                    ev.start_date <= ot.end_date && ev.end_date >= ot.start_date &&
                    ev.jam_start < ot.jam_end && ev.jam_end > ot.jam_start
                ));
                if (clash) sc = 'cat-clash';
                else is = `background:#dbeafe;color:#1d4ed8;`;
            }
            container.innerHTML += `<div onclick="filterByDate('${dateStr}')" class="calendar-day ${sc} ${isToday?'is-today':''}" style="${is}">${day}</div>`;
        }
        getField('view-title').innerText = 'Daftar Booking';
        getField('btn-show-all').classList.add('hidden');
        renderList();
    }

    function groupBentrok(data) {
        const groups = [],
            used = new Set();
        for (let i = 0; i < data.length; i++) {
            if (used.has(data[i].id)) continue;
            const group = [data[i]];
            used.add(data[i].id);
            for (let j = i + 1; j < data.length; j++) {
                if (used.has(data[j].id)) continue;
                const ov = group.some(g =>
                    g.jenis_lokasi === 'internal' && data[j].jenis_lokasi === 'internal' && g.ruang === data[j].ruang &&
                    data[j].start_date <= g.end_date && data[j].end_date >= g.start_date &&
                    data[j].jam_start < g.jam_end && data[j].jam_end > g.jam_start
                );
                if (ov) {
                    group.push(data[j]);
                    used.add(data[j].id);
                }
            }
            groups.push(group);
        }
        return groups;
    }

    function renderList(sourceData = null) {
        const lc = getField('list-items');
        const data = sourceData ? [...sourceData] : getFilteredData();
        getField('badge-count').innerText = data.length + ' Booking';
        if (!data.length) {
            lc.innerHTML = `<div class="text-center py-10 bg-white rounded-[2.5rem] border border-slate-50 text-slate-300 text-[10px] font-bold uppercase tracking-widest">Tidak ada booking ditemukan</div>`;
            return;
        }

        const todayStr = getTodayStr();

        /* ── Sorting:
           1. start_date  — tanggal kegiatan
           2. jam_start   — jam kegiatan
           3. created_at  — siapa booking lebih dulu
           4. id          — fallback
        ── */
        const sorted = [...data].sort((a, b) => {
            const byDate = a.start_date.localeCompare(b.start_date);
            if (byDate !== 0) return byDate;
            const byJam = (a.jam_start || '').localeCompare(b.jam_start || '');
            if (byJam !== 0) return byJam;
            const byCreated = (a.created_at || '').localeCompare(b.created_at || '');
            if (byCreated !== 0) return byCreated;
            return Number(a.id) - Number(b.id);
        });

        const groups = groupBentrok(sorted);

        lc.innerHTML = groups.map(group => {
            const isBentrok = group.length > 1;
            const cardsHtml = group.map(item => {
                const isSelesai = item.end_date < todayStr;
                const d = new Date(item.start_date);
                const day = String(d.getDate()).padStart(2, '0');
                const mon = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agt", "Sep", "Okt", "Nov", "Des"][d.getMonth()];
                const room = item.jenis_lokasi === 'internal' ? getRoomMetaByName(item.ruang) : {
                    color: '#0ea5e9',
                    short: 'Luar Kantor'
                };

                /* Label waktu booking dibuat */
                const createdLabel = item.created_at ? formatCreatedAt(item.created_at) : '';

                return `
                    <div onclick="openBookingModal('detail','${item.id}')"
                        class="bg-white border border-slate-50 p-5 rounded-[2.2rem] shadow-sm flex items-start space-x-4 cursor-pointer ${isSelesai?'card-selesai':''}">
                        <div class="w-14 h-14 rounded-[1.2rem] flex flex-col items-center justify-center text-white font-black flex-shrink-0" style="background:${room.color};">
                            <span class="text-[12px] leading-none mb-0.5">${day}</span>
                            <span class="text-[8px] uppercase opacity-80">${mon}</span>
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <div class="flex justify-between items-start mb-0.5 gap-2">
                                <h4 class="text-[13px] font-extrabold text-slate-800 leading-snug pr-2 line-clamp-2">${esc(item.nama)}</h4>
                                <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                    ${isSelesai?'<span class="badge-selesai">Selesai</span>':''}
                                    <span class="badge-soft">${esc(item.jenis_lokasi==='internal'?item.ruang:'Luar Kantor')}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 mb-2.5">
                                <i class="fa-regular fa-calendar-check text-sky-500 text-[9px]"></i>
                                <span class="text-[10px] font-bold text-slate-400">${formatDateID(item.start_date)} — ${formatDateID(item.end_date)}</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mb-2.5">
                                <span class="badge-soft">${esc((item.jam_start||'').slice(0,5))} - ${esc((item.jam_end||'').slice(0,5))}</span>
                                ${item.is_bentrok?'<span class="badge-clash">Bentrok</span>':''}
                            </div>
                            <div class="grid grid-cols-2 gap-y-1.5 mt-1 border-t border-slate-50 pt-2.5">
                                <div class="flex items-center gap-2"><i class="fa-solid fa-user text-slate-300 text-[10px] w-3"></i><span class="text-[10px] font-bold text-slate-500 truncate">${esc(item.peminjam||'-')}</span></div>
                                <div class="flex items-center gap-2"><i class="fa-solid fa-users text-slate-300 text-[10px] w-3"></i><span class="text-[10px] font-bold text-slate-500">${Number(item.peserta||0)} Peserta</span></div>
                                <div class="flex items-center gap-2"><i class="fa-solid fa-location-dot text-slate-300 text-[10px] w-3"></i><span class="text-[10px] font-bold text-slate-500 truncate">${esc(item.lokasi_display||'-')}</span></div>
                                <div class="flex items-center gap-2"><i class="fa-brands fa-whatsapp text-slate-300 text-[10px] w-3"></i><span class="text-[10px] font-bold text-slate-500 truncate">${esc(item.wa||'-')}</span></div>
                                ${createdLabel ? `
                                <div class="flex items-center gap-2 col-span-2 pt-1.5 mt-0.5 border-t border-slate-50">
                                    <i class="fa-regular fa-clock text-slate-300 text-[10px] w-3"></i>
                                    <span class="text-[10px] font-semibold text-slate-400 italic">Dibooking ${esc(createdLabel)}</span>
                                </div>` : ''}
                            </div>
                        </div>
                    </div>`;
            }).join('');

            return `
                <div class="space-y-3">
                    ${isBentrok?`<div class="flex items-center gap-2 px-2"><span class="flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span><span class="text-[9px] font-black text-red-500 uppercase tracking-widest">Jadwal Tumpang Tindih (${group.length} Booking)</span></div>`:''}
                    <div class="space-y-3 ${isBentrok?'p-3 bg-red-50/30 rounded-[2.5rem] border border-red-100/50':''}">${cardsHtml}</div>
                </div>`;
        }).join('');
    }

    function changeMonth(d) {
        viewDate.setMonth(viewDate.getMonth() + d);
        renderCalendar();
    }

    function filterByDate(dateStr) {
        filterDate = dateStr;
        const events = getFilteredData(bookingsData).filter(it => dateStr >= it.start_date && dateStr <= it.end_date);
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
        document.querySelectorAll('.room-tab').forEach(t => {
            t.classList.remove('bg-sky-600', 'text-white');
            t.classList.add('bg-slate-100', 'text-slate-500');
        });
        btn.classList.remove('bg-slate-100', 'text-slate-500');
        btn.classList.add('bg-sky-600', 'text-white');
        filterRoom = btn.dataset.room || '';
        if (filterDate) {
            const events = getFilteredData(bookingsData).filter(it => filterDate >= it.start_date && filterDate <= it.end_date);
            renderList(events);
            getField('badge-count').innerText = events.length + ' Booking';
            getField('btn-show-all').classList.remove('hidden');
        } else {
            renderCalendar();
        }
    });

    /* ── Modal helpers ── */
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
        showAdminPin('');
        const b = getField('btnSubmit');
        if (b) {
            b.disabled = false;
            b.innerText = currentModalMode === 'edit' ? 'Simpan Perubahan' : 'Simpan Booking';
        }
    }

    function setFormReadOnly(state) {
        const form = getField('booking-form');
        if (!form) return;
        if (state) form.classList.add('form-readonly');
        else form.classList.remove('form-readonly');
    }

    function fillFormFromItem(item) {
        setVal('booking-id', item.id);
        setVal('booking-pin', IS_ADMIN ? (item.pin || '') : '');
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
        showAdminPin('');
        getField('booking-form').reset();
        ['booking-id', 'booking-pin', 'booking-room-id'].forEach(k => setVal(k, ''));
        const today = getTodayStr();
        setVal('f-jenis-lokasi', 'internal');
        setVal('f-start', today);
        setVal('f-end', today);
        setVal('f-jam-start', '08:00');
        setVal('f-jam-end', '12:00');
        setVal('f-lokasi-external', '');
        renderRoomOptions();
        ['btnEditTrigger', 'btnVerifyPin', 'btnSubmit', 'btnHapus'].forEach(b => getField(b)?.classList.add('hidden'));
        getField('detail-links')?.classList.add('hidden');
        getField('btnKirimWA')?.classList.add('hidden');

        if (mode === 'create') {
            getField('sheetTitle').innerText = 'Booking Kegiatan';
            setFormReadOnly(false);
            const b = getField('btnSubmit');
            if (b) {
                b.classList.remove('hidden');
                b.innerText = 'Simpan & Kirim Link';
            }
            updateLokasiMode();
            queueCheckBentrok();
            showModal('bookingModal');
            return;
        }

        const item = bookingsData.find(r => String(r.id) === String(id));
        if (!item) {
            showToast('Data booking tidak ditemukan');
            return;
        }
        fillFormFromItem(item);
        getField('btn-isi-absensi').href = BASE_URL + 'absensi_rapat.php?id=' + item.id;
        showAdminPin(item.pin || '');
        getField('detail-links')?.classList.remove('hidden');
        getField('sheetTitle').innerText = 'Detail Booking';
        setFormReadOnly(true);
        if (IS_ADMIN && item.wa) getField('btnKirimWA')?.classList.remove('hidden');
        if (IS_ADMIN) getField('btnEditTrigger')?.classList.remove('hidden');
        else getField('btnVerifyPin')?.classList.remove('hidden');
        showModal('bookingModal');
    }

    function enableEditMode() {
        currentModalMode = 'edit';
        getField('sheetTitle').innerText = 'Ubah Booking';
        setFormReadOnly(false);
        ['btnEditTrigger', 'btnVerifyPin', 'btnKirimWA'].forEach(id => getField(id)?.classList.add('hidden'));
        const b = getField('btnSubmit');
        if (b) {
            b.classList.remove('hidden');
            b.disabled = false;
            b.innerText = 'Simpan Perubahan';
            b.type = 'submit';
        }
        getField('btnHapus')?.classList.remove('hidden');
        queueCheckBentrok();
    }

    function openPinModal(action) {
        pinAction = action;
        setVal('pin-input', '');
        showModal('pinModal');
    }

    function closePinModal() {
        hideModal('pinModal');
    }

    function requestOpenAbsensiMonitor() {
        const id = getVal('booking-id') || currentBookingId;
        if (IS_ADMIN) {
            window.open(BASE_URL + 'absensi.php?id=' + id, '_blank');
            return;
        }
        openPinModal('open_absensi_monitor');
    }

    function requestOpenNotulen() {
        const id = getVal('booking-id') || currentBookingId;
        if (IS_ADMIN) {
            window.open(BASE_URL + 'notulen.php?id=' + id, '_blank');
            return;
        }
        openPinModal('open_notulen');
    }

    async function verifyPinAction() {
        const pin = normalizeText(getVal('pin-input')),
            id = getVal('booking-id') || currentBookingId;
        if (!id) {
            showToast('Booking tidak ditemukan');
            return;
        }
        if (pin.length !== 4) {
            showToast('PIN harus 4 digit');
            return;
        }
        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('pin', pin);
            const res = await fetch(API_URL + '?action=booking_verify', {
                method: 'POST',
                body: fd
            });
            const d = await parseJsonSafe(res, 'booking_verify');
            if (!d.valid) {
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
                window.open(BASE_URL + 'absensi.php?id=' + id + '&pin=' + encodeURIComponent(pin), '_blank');
            } else if (pinAction === 'open_notulen') {
                window.open(BASE_URL + 'notulen.php?id=' + id + '&pin=' + encodeURIComponent(pin), '_blank');
            }
        } catch (e) {
            console.error(e);
            showToast('Gagal verifikasi PIN');
        }
    }

    async function handleSaveBooking(e) {
        e.preventDefault();
        const id = getVal('booking-id'),
            isEditing = !!id,
            pinStored = getVal('booking-pin') || verifiedPin || '';
        const jenis = getVal('f-jenis-lokasi'),
            roomId = getVal('booking-room-id'),
            lokasiExt = normalizeText(getVal('f-lokasi-external'));
        const ruang = getVal('f-ruang'),
            nama = normalizeText(getVal('f-nama')),
            peminjam = normalizeText(getVal('f-peminjam'));
        const start = getVal('f-start'),
            end = getVal('f-end'),
            jamStart = getVal('f-jam-start'),
            jamEnd = getVal('f-jam-end');
        const peserta = getVal('f-peserta'),
            wa = normalizeText(getVal('f-wa')),
            ket = normalizeText(getVal('f-ket'));

        if (!nama || !peminjam || !start || !end || !wa) {
            showToast('Lengkapi semua field wajib');
            return;
        }
        if (jenis === 'internal' && (!ruang || !roomId)) {
            showToast('Pilih ruangan internal');
            return;
        }
        if (jenis === 'external' && !lokasiExt) {
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
        if (isEditing && !IS_ADMIN && !pinStored) {
            showToast('Klik tombol "Kelola Booking" lalu masukkan PIN dulu');
            return;
        }

        const fd = new FormData();
        fd.append('jenis_lokasi', jenis);
        fd.append('room_id', jenis === 'internal' ? roomId : '');
        fd.append('ruang', ruang);
        fd.append('lokasi_external', jenis === 'external' ? lokasiExt : '');
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
        const btnS = getField('btnSubmit');
        if (btnS) {
            btnS.disabled = true;
            btnS.innerText = 'Menyimpan...';
        }

        if (isEditing) {
            fd.append('id', id);
            if (!IS_ADMIN) fd.append('pin', pinStored);
            endpoint = API_URL + '?action=booking_update';
        }

        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                body: fd
            });
            const result = await parseJsonSafe(res, isEditing ? 'booking_update' : 'booking_create');
            if (result.error) {
                showToast(result.error);
                if (result.bentrok?.length) {
                    const ih = result.bentrok.map(it => `<li>${esc(it.nama)} — ${esc(it.start_date)} s/d ${esc(it.end_date)} (${esc((it.jam_start||'').slice(0,5))} - ${esc((it.jam_end||'').slice(0,5))})</li>`).join('');
                    showBookingWarning('danger', `Jadwal bentrok dengan booking lain.<ul class="list-disc ml-4 mt-2">${ih}</ul>`);
                }
                return;
            }
            closeBookingModal();
            if (!isEditing) {
                const linkAbs = result.link_absensi || '';
                fillSuccessModal({
                    pin: result.pin || '----',
                    linkBooking: result.link_booking || '-',
                    linkAbs,
                    linkMonitor: result.link_monitor || '-',
                    linkNotulen: result.link_notulen || '-',
                    qrUrl: result.qr_absensi_url || buildQrUrl(linkAbs),
                    waUrl: result.wa_url || '#',
                    isResend: false
                });
                showModal('successModal');
            } else {
                showToast('✓ Booking berhasil diubah');
            }
            await loadBookings();
        } catch (e) {
            console.error(e);
            showToast('Gagal menyimpan booking');
        } finally {
            if (btnS) {
                btnS.disabled = false;
                btnS.innerText = isEditing ? 'Simpan Perubahan' : 'Simpan & Kirim Link';
            }
        }
    }

    function requestDelete() {
        if (IS_ADMIN) {
            doDeleteBooking();
            return;
        }
        openPinModal('delete');
    }

    async function doDeleteBooking() {
        if (!confirm('Hapus booking ini? Tindakan tidak dapat dibatalkan.')) return;
        const id = getVal('booking-id') || currentBookingId,
            pin = getVal('booking-pin') || verifiedPin || '';
        if (!IS_ADMIN && !pin) {
            showToast('Masukkan PIN dulu');
            return;
        }
        try {
            const fd = new FormData();
            fd.append('id', id);
            if (!IS_ADMIN) fd.append('pin', pin);
            const res = await fetch(API_URL + '?action=booking_delete', {
                method: 'POST',
                body: fd
            });
            const result = await parseJsonSafe(res, 'booking_delete');
            if (result.error) {
                showToast(result.error);
                return;
            }
            closeBookingModal();
            showToast('✓ Booking berhasil dihapus');
            await loadBookings();
        } catch (e) {
            console.error(e);
            showToast('Gagal menghapus booking');
        }
    }

    /* ── Kirim ulang WA ── */
    function kirimUlangWA() {
        const id = getVal('booking-id') || currentBookingId;
        const item = bookingsData.find(r => String(r.id) === String(id));
        if (!item || !item.wa) {
            showToast('Nomor WhatsApp tidak tersedia');
            return;
        }
        const pin = item.pin || '----';
        const linkBooking = BASE_URL + 'peminjaman_ruang_rapat.php';
        const linkAbs = BASE_URL + 'absensi_rapat.php?id=' + item.id;
        const linkMonitor = BASE_URL + 'absensi.php?id=' + item.id + '&pin=' + encodeURIComponent(pin);
        const linkNotulen = BASE_URL + 'notulen.php?id=' + item.id + '&pin=' + encodeURIComponent(pin);
        const qrUrl = buildQrUrl(linkAbs, '300x300');
        const pesan = buildWaMessage(item.nama, item.peminjam, item.lokasi_display || '-', item.start_date, item.end_date, item.jam_start || '', item.jam_end || '', pin, linkAbs, linkMonitor, linkNotulen, qrUrl);
        let nomor = item.wa.replace(/\D/g, '');
        if (nomor.startsWith('0')) nomor = '62' + nomor.slice(1);
        else if (!nomor.startsWith('62')) nomor = '62' + nomor;
        const waUrl = 'https://wa.me/' + nomor + '?text=' + encodeURIComponent(pesan);
        closeBookingModal();
        fillSuccessModal({
            pin,
            linkBooking,
            linkAbs,
            linkMonitor,
            linkNotulen,
            qrUrl,
            waUrl,
            isResend: true
        });
        showModal('successModal');
    }

    /* ── Success modal ── */
    function closeSuccessModal() {
        hideModal('successModal');
        getField('success-banner-emoji').textContent = '✅';
        getField('success-banner-title').textContent = 'Booking Berhasil!';
        getField('success-banner-sub').textContent = 'Link akses dan QR absensi sudah siap';
        getField('success-wa-label').textContent = 'Kirim ke WhatsApp Saya';
    }

    /* ── Export ── */
    function openExportModal() {
        showModal('exportModal');
        const t = new Date(),
            p = new Date();
        p.setDate(t.getDate() - 30);
        getField('exportTo').value = t.toISOString().split('T')[0];
        getField('exportFrom').value = p.toISOString().split('T')[0];
    }

    function closeExportModal() {
        hideModal('exportModal');
    }

    function downloadExport() {
        const from = getVal('exportFrom'),
            to = getVal('exportTo');
        if (!from || !to) {
            showToast('Pilih rentang tanggal');
            return;
        }
        if (from > to) {
            showToast('Tanggal awal tidak boleh lebih besar dari akhir');
            return;
        }
        window.location.href = 'peminjaman_ruang_rapat_export.php?from=' + from + '&to=' + to;
        closeExportModal();
    }

    /* ── Event listeners ── */
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
    ['f-start', 'f-end', 'f-jam-start', 'f-jam-end'].forEach(id => getField(id).addEventListener('change', queueCheckBentrok));

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) Promise.all([loadRooms(), loadBookings()]);
        else abortController.abort();
    });
    window.addEventListener('beforeunload', () => abortController.abort());
    window.addEventListener('pagehide', () => abortController.abort());

    window.onload = async function() {
        await loadRooms();
        await loadBookings();
    };
</script>