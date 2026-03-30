<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Cekin dan Cekout Peserta";
include 'header.php';
include 'config.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: #ffffff;
        color: #0f172a;
        overflow-x: hidden;
        -webkit-tap-highlight-color: transparent;
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

    .data-card {
        background: white;
        border-radius: 16px;
        padding: 18px;
        border: 1px solid #e0f2fe;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(2, 132, 199, 0.05);
    }

    .badge {
        font-size: 10px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
    }

    .badge-role-peserta {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #dcfce7;
        font-size: 9px;
    }

    .badge-role-pengajar {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #dbeafe;
        font-size: 9px;
    }

    .badge-role-panitia {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #ffedd5;
        font-size: 9px;
    }

    .badge-pending {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #ffedd5;
    }

    .badge-success {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #dcfce7;
    }

    .badge-out {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #f1f5f9;
    }

    .btn-action {
        padding: 8px 16px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        transition: all 0.2s;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-card {
        animation: fadeIn 0.4s ease-out forwards;
    }

    .dorm-pill {
        padding: 8px 16px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
        transition: all 0.2s;
        border: 1px solid #f1f5f9;
        background: #f8fafc;
        color: #64748b;
    }

    .dorm-pill.active {
        background: #0ea5e9;
        color: white;
        border-color: #0ea5e9;
        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.2);
    }

    .scroll-hide::-webkit-scrollbar {
        display: none;
    }
</style>

<!-- Header -->
<header class="sticky-header px-4 pt-4 pb-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <button onclick="window.history.back()"
                class="w-10 h-10 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </button>
            <div>
                <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight">Cekin &amp; Cekout</h1>
                <p class="text-[12px] text-gray-400 font-medium leading-tight">Monitoring Check-In/Out</p>
            </div>
        </div>
        <button onclick="openExportModal()"
            class="absolute top-5 right-4 w-11 h-11 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition">
            <i class="fa-solid fa-download text-lg"></i>
        </button>
    </div>
</header>

<!-- MODAL EXPORT -->
<div id="exportModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeExportModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-extrabold text-gray-800">Download Laporan</p>
                    <p class="text-[11px] text-gray-500">Pilih rentang tanggal</p>
                </div>
                <button onclick="closeExportModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600">
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

<!-- Main -->
<main class="header-offset px-5 py-6">

    <!-- Search -->
    <div class="mb-6">
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-sky-400 text-sm"></i>
            <input type="text" id="qSearch" onkeyup="filterData()"
                placeholder="Cari nama, kamar, atau pelatihan..."
                class="w-full pl-11 pr-4 py-3.5 bg-sky-50 border-none rounded-xl text-sm outline-none transition-all focus:bg-white focus:ring-1 focus:ring-sky-200">
        </div>
    </div>

    <!-- Filter Gedung -->
    <div class="mb-8">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 ml-1">Filter Gedung</p>
        <div class="flex gap-2 overflow-x-auto pb-2 scroll-hide" id="dormPills">
            <button onclick="setDormFilter('Semua')" class="dorm-pill active" id="pill-Semua">Semua</button>
            <button onclick="setDormFilter('Candra 1')" class="dorm-pill" id="pill-Candra1">Candra 1</button>
            <button onclick="setDormFilter('Candra 2')" class="dorm-pill" id="pill-Candra2">Candra 2</button>
            <button onclick="setDormFilter('Sari')" class="dorm-pill" id="pill-Sari">Sari</button>
            <button onclick="setDormFilter('Cakra 1')" class="dorm-pill" id="pill-Cakra1">Cakra 1</button>
            <button onclick="setDormFilter('Cakra 2')" class="dorm-pill" id="pill-Cakra2">Cakra 2</button>
            <button onclick="setDormFilter('Cakra 3')" class="dorm-pill" id="pill-Cakra3">Cakra 3</button>
            <button onclick="setDormFilter('Cakra 4')" class="dorm-pill" id="pill-Cakra4">Cakra 4</button>
            <button onclick="setDormFilter('Cakra 5')" class="dorm-pill" id="pill-Cakra5">Cakra 5</button>
        </div>
    </div>

    <!-- Count -->
    <div class="flex items-center justify-between mb-5 px-1">
        <h2 class="text-[12px] font-bold text-sky-600 uppercase tracking-tighter">Status Kehadiran</h2>
        <div class="h-px flex-grow mx-4 bg-sky-100"></div>
        <span id="dataCount" class="text-[11px] font-medium text-sky-500 whitespace-nowrap">0 Item</span>
    </div>

    <!-- Cards -->
    <div id="mainContainer" class="space-y-4 pb-32"></div>

</main>

<!-- Toast -->
<div id="toast"
    class="fixed bottom-10 left-1/2 -translate-x-1/2 bg-sky-900 text-white px-5 py-2.5 rounded-full text-[11px] font-bold opacity-0 transition-all z-[200] pointer-events-none shadow-lg text-center min-w-[200px]">
</div>

<script>
    const apiUrl = 'peserta_penginapan_api.php';
    let guestData = [];
    let currentDormFilter = 'Semua';

    /* ── Helpers ── */
    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', 2500);
    }

    function normalizeStatus(statusInap) {
        if (statusInap === 'Check-in') return 'IN';
        if (statusInap === 'Check-out') return 'OUT';
        return 'PENDING';
    }

    function roleClass(peran) {
        if (peran === 'Pengajar') return 'badge-role-pengajar';
        if (peran === 'Panitia') return 'badge-role-panitia';
        return 'badge-role-peserta';
    }

    function safeText(value, fallback = '-') {
        if (value === null || value === undefined || value === '') return fallback;
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatTime(timeValue) {
        if (!timeValue) return '-';
        return String(timeValue).slice(0, 5);
    }

    function mapApiData(rows) {
        return (rows || []).map(row => {
            const status = normalizeStatus(row.status_inap);
            return {
                id: Number(row.id),
                nama: row.nama || '',
                kamar: row.kamar || '',
                gedung: row.gedung || '',
                lantai: row.lantai || '',
                status,
                timeIn: row.checkin_time ? formatTime(row.checkin_time) : '-',
                timeOut: row.checkout_time ? formatTime(row.checkout_time) : '-',
                pelatihan: row.judul || 'Tanpa Kegiatan',
                peran: row.peran || 'Peserta',
                raw: row
            };
        });
    }

    /* ── Load Data ── */
    async function loadData() {
        try {
            const res = await fetch(apiUrl + '?action=list');
            const json = await res.json();
            if (!json.status) {
                showToast(json.message || 'Gagal memuat data');
                return;
            }
            guestData = mapApiData(json.data || []);
            filterData();
        } catch (err) {
            console.error(err);
            showToast('Terjadi kesalahan saat memuat data');
        }
    }

    /* ── Render ── */
    function render(data) {
        const container = document.getElementById('mainContainer');
        const safeData = data || [];
        document.getElementById('dataCount').textContent = `${safeData.length} Orang`;

        if (safeData.length === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20 text-center animate-card">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4 border border-dashed border-slate-200">
                        <i class="fa-solid fa-folder-open text-slate-300 text-2xl"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 mb-1">Data Tidak Ditemukan</h3>
                    <p class="text-[11px] text-slate-400 mb-6">Tidak ada data yang sesuai kriteria.</p>
                    <button onclick="resetFilters()"
                        class="text-xs font-bold text-sky-600 bg-sky-50 px-6 py-3 rounded-xl border border-sky-100 active:scale-95 transition-all">
                        Tampilkan Semua Data
                    </button>
                </div>`;
            return;
        }

        container.innerHTML = safeData.map((d, index) => {
            const statusBadge =
                d.status === 'PENDING' ?
                `<span class="badge badge-pending">Belum Hadir</span>` :
                d.status === 'IN' ?
                `<span class="badge badge-success text-[9px]">Check-In @ ${safeText(d.timeIn)}</span>` :
                `<span class="badge badge-out">Check-Out @ ${safeText(d.timeOut)}</span>`;

            const actionBtn =
                d.status === 'PENDING' ?
                `<button onclick="handleStatus(${d.id}, 'IN'); event.stopPropagation();"
                        class="btn-action bg-sky-600 text-white active:scale-95 shadow-md shadow-sky-100">Check-In</button>` :
                d.status === 'IN' ?
                `<button onclick="handleStatus(${d.id}, 'OUT'); event.stopPropagation();"
                        class="btn-action border border-rose-100 text-rose-500 bg-rose-50 active:scale-95">Check-Out</button>` :
                `<span class="text-[10px] font-bold text-slate-300 px-4 italic">Selesai</span>`;

            return `
                <div class="data-card animate-card" style="animation-delay:${index * 0.03}s">
                    <div class="flex justify-between items-center mb-4">
                        ${statusBadge}
                        <div class="flex items-center gap-2">${actionBtn}</div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 bg-sky-50 rounded-xl flex flex-col items-center justify-center shrink-0 border border-sky-100 mt-1 shadow-sm">
                            <span class="text-[8px] font-bold text-sky-400 uppercase leading-none mb-0.5">ROOM</span>
                            <span class="text-sm font-black text-sky-800">${safeText(d.kamar)}</span>
                        </div>
                        <div class="min-w-0 flex-grow">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-extrabold text-sky-900 truncate leading-tight">${safeText(d.nama)}</h3>
                                <span class="badge ${roleClass(d.peran)} shrink-0">${safeText(String(d.peran).toUpperCase())}</span>
                            </div>
                            <p class="text-[10px] text-sky-500 font-bold mb-2 line-clamp-1">
                                <i class="fa-solid fa-graduation-cap mr-1"></i>${safeText(d.pelatihan)}
                            </p>
                            <div class="flex items-center gap-2">
                                <div class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1.5">
                                    <i class="fa-solid fa-building text-sky-600 text-[9px]"></i>
                                    <span class="text-[11px] font-extrabold text-slate-700">${safeText(d.gedung)}</span>
                                </div>
                                <div class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1.5">
                                    <i class="fa-solid fa-stairs text-sky-600 text-[9px]"></i>
                                    <span class="text-[11px] font-extrabold text-slate-700">Lt. ${safeText(d.lantai)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
        }).join('');
    }

    /* ── Handle Status ── */
    async function handleStatus(id, type) {
        const item = guestData.find(g => g.id === id);
        if (!item) {
            showToast('Data tidak ditemukan');
            return;
        }

        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        const hh = String(now.getHours()).padStart(2, '0');
        const ii = String(now.getMinutes()).padStart(2, '0');
        const ss = String(now.getSeconds()).padStart(2, '0');

        const today = `${yyyy}-${mm}-${dd}`;
        const nowTime = `${hh}:${ii}:${ss}`;
        const statusInap = type === 'IN' ? 'Check-in' : 'Check-out';

        const fd = new FormData();
        fd.append('id', item.raw.id || item.id);
        fd.append('agenda_id', item.raw.agenda_id || '');
        fd.append('nama', item.raw.nama || '');
        fd.append('instansi', item.raw.instansi || '');
        fd.append('nip', item.raw.nip || '');
        fd.append('no_hp', item.raw.no_hp || '');
        fd.append('peran', item.raw.peran || 'Peserta');
        fd.append('jenis_kelamin', item.raw.jenis_kelamin || '');
        fd.append('gedung', item.raw.gedung || '');
        fd.append('lantai', item.raw.lantai || '');
        fd.append('kamar', item.raw.kamar || '');
        fd.append('bed', item.raw.bed || '');
        fd.append('status_inap', statusInap);
        fd.append('kondisi', item.raw.kondisi || '');
        fd.append('catatan', item.raw.catatan || '');

        if (type === 'IN') {
            fd.append('checkin_date', today);
            fd.append('checkin_time', nowTime);
            fd.append('checkout_date', item.raw.checkout_date || '');
            fd.append('checkout_time', item.raw.checkout_time || '');
        } else {
            fd.append('checkin_date', item.raw.checkin_date || today);
            fd.append('checkin_time', item.raw.checkin_time || '');
            fd.append('checkout_date', today);
            fd.append('checkout_time', nowTime);
        }

        try {
            const res = await fetch(apiUrl + '?action=save', {
                method: 'POST',
                body: fd
            });
            const json = await res.json();
            if (!json.status) {
                showToast(json.message || 'Gagal mengubah status');
                return;
            }
            showToast(`${item.nama} ${type === 'IN' ? 'Check-In' : 'Check-Out'}`);
            await loadData();
        } catch (err) {
            console.error(err);
            showToast('Terjadi kesalahan saat menyimpan status');
        }
    }

    /* ── Filter ── */
    function setDormFilter(dorm) {
        currentDormFilter = dorm;
        document.querySelectorAll('.dorm-pill').forEach(p => p.classList.remove('active'));
        const safeId = dorm.replace(/\s/g, '');
        const target = document.getElementById(`pill-${safeId}`);
        if (target) target.classList.add('active');
        filterData();
    }

    function resetFilters() {
        document.getElementById('qSearch').value = '';
        setDormFilter('Semua');
    }

    function filterData() {
        const q = document.getElementById('qSearch').value.toLowerCase().trim();
        const filtered = guestData.filter(d => {
            const matchQuery =
                String(d.nama).toLowerCase().includes(q) ||
                String(d.kamar).toLowerCase().includes(q) ||
                String(d.pelatihan).toLowerCase().includes(q) ||
                String(d.peran).toLowerCase().includes(q);
            const matchDorm = currentDormFilter === 'Semua' || d.gedung === currentDormFilter;
            return matchQuery && matchDorm;
        });
        render(filtered);
    }

    /* ── Export Modal ── */
    function openExportModal() {
        const modal = document.getElementById('exportModal');
        const today = new Date();
        const prior = new Date();
        prior.setDate(today.getDate() - 30);
        document.getElementById('exportTo').value = today.toISOString().split('T')[0];
        document.getElementById('exportFrom').value = prior.toISOString().split('T')[0];
        modal.classList.remove('hidden');
    }

    function closeExportModal() {
        document.getElementById('exportModal').classList.add('hidden');
    }

    function downloadExport() {
        const from = document.getElementById('exportFrom').value;
        const to = document.getElementById('exportTo').value;
        if (!from || !to) {
            alert('Silakan pilih rentang tanggal');
            return;
        }
        if (from > to) {
            alert('Tanggal awal tidak boleh lebih besar dari tanggal akhir');
            return;
        }
        window.location.href = `peserta_penginapan_export.php?from=${from}&to=${to}`;
        closeExportModal();
    }

    /* ── Abort controller (konsisten dengan timetable) ── */
    let abortController = new AbortController();

    async function loadDataSafe() {
        abortController.abort();
        abortController = new AbortController();
        try {
            const res = await fetch(apiUrl + '?action=list', {
                signal: abortController.signal
            });
            const json = await res.json();
            if (!json.status) {
                showToast(json.message || 'Gagal memuat data');
                return;
            }
            guestData = mapApiData(json.data || []);
            filterData();
        } catch (e) {
            if (e.name !== 'AbortError') console.error(e);
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) abortController.abort();
        else loadDataSafe();
    });

    window.addEventListener('beforeunload', () => abortController.abort());
    window.addEventListener('pagehide', () => abortController.abort());

    window.onload = loadDataSafe;
</script>