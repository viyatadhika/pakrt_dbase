<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Timetable Kegiatan";
include 'header.php';
include 'config.php';

// ✅ Cek apakah user adalah admin
$isAdmin = strtolower($_SESSION['user']['role'] ?? '') === 'admin';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #fff;
        color: #1e293b;
    }

    .header-container {
        position: sticky;
        top: 0;
        z-index: 50;
        background-color: rgba(255, 255, 255, .9);
        backdrop-filter: blur(8px);
    }

    :root {
        --sky-blue: #0ea5e9;
        --sky-blue-dark: #0284c7;
        --sky-blue-light: #e0f2fe;
    }

    .text-sky {
        color: var(--sky-blue);
    }

    .bg-sky {
        background-color: var(--sky-blue);
    }

    .transition-soft {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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

    .modal-animate-up {
        animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes slideUp {
        from {
            transform: translateY(100%);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
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
        position: relative;
        background-color: #f8fafc;
        color: #94a3b8;
    }

    .calendar-day:hover {
        background-color: #f1f5f9;
    }

    .cat-menpim {
        background-color: #fef3c7;
        color: #92400e;
    }

    .cat-teknis {
        background-color: #dcfce7;
        color: #166534;
    }

    .cat-kerjasama {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .cat-pustrajak {
        background-color: #ffedd5;
        color: #9a3412;
    }

    .cat-clash {
        background-color: #fee2e2 !important;
        color: #ef4444 !important;
        border: 2px dashed #f87171;
    }

    .is-today {
        box-shadow: 0 0 0 2px var(--sky-blue);
    }

    /* ===== CARD SELESAI ===== */
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

    /* ======================== */

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

<!-- Header -->
<header class="sticky-header px-5 pt-4 pb-0 relative">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <button onclick="window.history.back()"
                class="w-10 h-10 shrink-0 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </button>
            <div class="min-w-0">
                <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Timetable Kegiatan</h1>
                <p class="text-[12px] text-gray-400 font-medium leading-tight">Lihat jadwal kegiatan terbaru</p>
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
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest" id="calendar-month">Februari 2026</h3>
                <div class="flex space-x-1">
                    <button onclick="changeMonth(-1)" class="p-2 text-slate-400 hover:text-sky transition-colors"><i class="fa-solid fa-chevron-left text-xs"></i></button>
                    <button onclick="changeMonth(1)" class="p-2 text-slate-400 hover:text-sky transition-colors"><i class="fa-solid fa-chevron-right text-xs"></i></button>
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
                <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest mb-1">Kategori Pelatihan</p>
                <div class="grid grid-cols-2 gap-2">
                    <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="w-2.5 h-2.5 rounded-full bg-yellow-400"></div>
                        <span class="text-[9px] font-bold text-slate-600">Menpim</span>
                    </div>
                    <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
                        <span class="text-[9px] font-bold text-slate-600">Teknis</span>
                    </div>
                    <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
                        <span class="text-[9px] font-bold text-slate-600">Kerjasama</span>
                    </div>
                    <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div>
                        <span class="text-[9px] font-bold text-slate-600">Pustrajak</span>
                    </div>
                    <div class="flex items-center gap-2 p-2 rounded-xl bg-red-50 border border-red-100 col-span-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-red-500"></div>
                        <span class="text-[9px] font-black text-red-600">Jadwal Tumpang Tindih</span>
                    </div>
                    <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100 col-span-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-slate-300"></div>
                        <span class="text-[9px] font-bold text-slate-400">Kegiatan Sudah Selesai</span>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="px-6 md:px-0 mt-8 md:mt-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-5 w-1.5 bg-sky-500 rounded-full"></div>
                <h2 id="view-title" class="text-xs font-black text-slate-900 uppercase tracking-widest">Daftar Agenda</h2>
            </div>
            <div class="flex items-center gap-2">
                <button id="btn-show-all" onclick="showAllAgenda()"
                    class="hidden text-[10px] font-bold text-slate-500 hover:text-sky px-3 py-1 rounded-full bg-slate-100 transition">
                    Lihat Semua
                </button>
                <span id="badge-count" class="text-[10px] font-bold text-sky bg-sky-50 px-3 py-1 rounded-full">
                    0 Agenda
                </span>
            </div>
        </div>
        <div id="list-items" class="space-y-6"></div>
    </main>
</div>

<!-- FAB Tambah — hanya tampil untuk admin -->
<?php if ($isAdmin): ?>
    <button onclick="openModalTambah()"
        class="fixed bottom-8 right-8 w-11 h-11 bg-sky-600 text-white rounded-full shadow-lg shadow-sky-100 flex items-center justify-center z-[40] active:scale-90 transition-all">
        <i class="fa-solid fa-plus text-lg"></i>
    </button>
<?php endif; ?>

<!-- MODAL TAMBAH/EDIT/DETAIL -->
<div id="stokModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p id="sheetTitle" class="text-sm font-extrabold text-gray-800">Tambah Jadwal</p>
                    <p class="text-[11px] text-gray-500">Pusdiklat Mahkamah Agung</p>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($isAdmin): ?>
                        <button type="button" id="btnEditTrigger" onclick="enableEditMode()"
                            class="w-9 h-9 rounded-full bg-sky-50 text-sky-600 hidden">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    <?php endif; ?>
                    <button type="button" onclick="closeModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <form id="agenda-form" onsubmit="handleSave(event)" class="space-y-3">
                <input type="hidden" id="edit-id">
                <div>
                    <label class="text-xs font-bold text-gray-600">Nama Pelatihan</label>
                    <input id="f-judul" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Mulai</label>
                        <input id="f-start" type="date" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Selesai</label>
                        <input id="f-end" type="date" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600">Kategori</label>
                    <select id="f-pny" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                        <option value="Menpim">Menpim</option>
                        <option value="Teknis">Teknis</option>
                        <option value="Kerjasama">Kerjasama</option>
                        <option value="Pustrajak">Pustrajak</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Peserta</label>
                        <input id="f-peserta" type="number" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Asrama</label>
                        <input id="f-asrama" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Kelas</label>
                        <input id="f-kelas" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Ruang Makan</label>
                        <input id="f-makan" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                    <button id="btnSubmit" type="submit" class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Simpan Jadwal</button>
                    <button id="btnHapus" type="button" onclick="handleDelete()" class="w-full py-3 rounded-2xl bg-red-50 text-red-600 font-extrabold text-sm hidden">
                        <i class="fa-solid fa-trash-can mr-2"></i> Hapus Jadwal
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

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
                <button onclick="downloadExport()" class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Download PDF</button>
                <p class="text-[10px] text-gray-400 text-center">Default otomatis 30 hari terakhir</p>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="fixed top-24 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-6 py-3 rounded-full text-[10px] font-bold shadow-xl opacity-0 pointer-events-none transition-all duration-300 z-[200]">Aksi Berhasil!</div>

<script>
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    let agendaData = [];

    async function loadAgenda() {
        const res = await fetch('get_timetable.php?action=read');
        const rawData = await res.json();
        agendaData = markBentrok(rawData);
        renderCalendar();
    }

    window.onload = loadAgenda;
    let viewDate = new Date();

    const stokModal = document.getElementById('stokModal');
    const sheetTitle = document.getElementById('sheetTitle');
    const btnSubmit = IS_ADMIN ? document.getElementById('btnSubmit') : null;
    const btnHapus = IS_ADMIN ? document.getElementById('btnHapus') : null;
    const btnEditTrigger = IS_ADMIN ? document.getElementById('btnEditTrigger') : null;

    function getTodayStr() {
        return new Date().toISOString().split('T')[0];
    }

    function formatDateID(dateStr) {
        const d = new Date(dateStr);
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }).format(d);
    }

    function renderCalendar() {
        const container = document.getElementById('calendar-days');
        const monthLabel = document.getElementById('calendar-month');
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

        for (let i = 0; i < firstDay; i++) container.innerHTML += `<div></div>`;

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const events = agendaData.filter(ev => dateStr >= ev.start && dateStr <= ev.end);
            const isToday = dateStr === todayStr;
            let stateClass = '';
            if (events.length === 1) stateClass = `cat-${events[0].pny.toLowerCase()}`;
            else if (events.length > 1) stateClass = 'cat-clash';
            container.innerHTML += `<div onclick="filterByDate('${dateStr}')" class="calendar-day ${stateClass} ${isToday ? 'is-today' : ''}">${d}</div>`;
        }

        const currentEvents = agendaData.filter(ev => {
            const start = new Date(ev.start);
            const end = new Date(ev.end);
            return (start.getMonth() === month || end.getMonth() === month) && start.getFullYear() === year;
        });

        const title = document.getElementById('view-title');
        if (title) title.innerText = "Daftar Agenda";
        const badge = document.getElementById('badge-count');
        if (badge) badge.innerText = `${countAgendaBulan(agendaData, viewDate)} Agenda Bulan Ini`;
        renderList(currentEvents);
        document.getElementById('btn-show-all').classList.add('hidden');
    }

    function markBentrok(data) {
        return data.map((a, i) => {
            const bentrok = data.some((b, j) => i !== j && a.start <= b.end && a.end >= b.start);
            return {
                ...a,
                isBentrok: bentrok
            };
        });
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
                const overlap = group.some(g => data[j].start <= g.end && data[j].end >= g.start);
                if (overlap) {
                    group.push(data[j]);
                    used.add(data[j].id);
                }
            }
            groups.push(group);
        }
        return groups;
    }

    function countAgendaBulan(data, viewDate) {
        const year = viewDate.getFullYear();
        const month = viewDate.getMonth();
        return data.filter(ev => {
            const start = new Date(ev.start);
            const end = new Date(ev.end);
            return (
                (start.getFullYear() === year && start.getMonth() === month) ||
                (end.getFullYear() === year && end.getMonth() === month) ||
                (start < new Date(year, month + 1, 0) && end > new Date(year, month, 1))
            );
        }).length;
    }

    function renderList(data) {
        const listContainer = document.getElementById('list-items');
        const badge = document.getElementById('badge-count');
        if (badge) badge.innerText = `${data.length} Agenda`;

        if (!data || data.length === 0) {
            listContainer.innerHTML = `<div class="text-center py-10 bg-white rounded-[2.5rem] border border-slate-50 text-slate-300 text-[10px] font-bold uppercase tracking-widest">Tidak ada agenda pada tanggal ini</div>`;
            return;
        }

        const todayStr = getTodayStr();
        const sorted = [...data].sort((a, b) => a.start.localeCompare(b.start));
        const groups = groupBentrok(sorted);

        const colorMap = {
            'Menpim': 'bg-yellow-400',
            'Teknis': 'bg-green-500',
            'Kerjasama': 'bg-blue-500',
            'Pustrajak': 'bg-orange-500'
        };
        const monthNames = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agt", "Sep", "Okt", "Nov", "Des"];

        listContainer.innerHTML = groups.map(group => {
            const isBentrok = group.length > 1;
            return `
                <div class="space-y-3">
                    ${isBentrok ? `<div class="flex items-center gap-2 px-2"><span class="flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span><span class="text-[9px] font-black text-red-500 uppercase tracking-widest">Jadwal Tumpang Tindih (${group.length} Agenda)</span></div>` : ''}
                    <div class="space-y-3 ${isBentrok ? 'p-3 bg-red-50/30 rounded-[2.5rem] border border-red-100/50' : ''}">
                        ${group.map(item => {
                            const isSelesai  = item.end < todayStr;
                            const d          = new Date(item.start);
                            const day        = String(d.getDate()).padStart(2, '0');
                            const mon        = monthNames[d.getMonth()];
                            const dotColor   = isSelesai ? 'bg-slate-300' : (colorMap[item.pny] || 'bg-slate-200');
                            const badgeSelesai = isSelesai
                                ? `<span class="badge-selesai">Selesai</span>`
                                : '';
                            const badgeKategori = `<span class="text-[9px] font-black uppercase px-2 py-1 rounded-lg ${colorMap[item.pny]} bg-opacity-10 flex-shrink-0" style="color: ${getHexColor(item.pny)}">${item.pny}</span>`;

                            return `
                                <div onclick="openModalDetail(${item.id})"
                                     class="bg-white border border-slate-50 p-5 rounded-[2.2rem] shadow-sm flex items-start space-x-4 cursor-pointer ${isSelesai ? 'card-selesai' : ''}">
                                    <div class="w-14 h-14 ${dotColor} rounded-[1.2rem] flex flex-col items-center justify-center text-white font-black flex-shrink-0">
                                        <span class="text-[12px] leading-none mb-0.5">${day}</span>
                                        <span class="text-[8px] uppercase opacity-80">${mon}</span>
                                    </div>
                                    <div class="flex-1 overflow-hidden">
                                        <div class="flex justify-between items-start mb-0.5">
                                            <h4 class="text-[13px] font-extrabold text-slate-800 leading-snug pr-2 truncate">${item.judul}</h4>
                                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                ${badgeSelesai}
                                                ${badgeKategori}
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5 mb-2.5">
                                            <i class="fa-regular fa-calendar-check text-sky text-[9px]"></i>
                                            <span class="text-[10px] font-bold text-slate-400">${formatDateID(item.start)} — ${formatDateID(item.end)}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-y-1.5 mt-1 border-t border-slate-50 pt-2.5">
                                            <div class="flex items-center gap-2"><i class="fa-solid fa-users text-slate-300 text-[10px] w-3"></i><span class="text-[10px] font-bold text-slate-500">${item.peserta} Peserta</span></div>
                                            <div class="flex items-center gap-2"><i class="fa-solid fa-building text-slate-300 text-[10px] w-3"></i><span class="text-[10px] font-bold text-slate-500">${item.asrama}</span></div>
                                            <div class="flex items-center gap-2"><i class="fa-solid fa-door-open text-slate-300 text-[10px] w-3"></i><span class="text-[10px] font-bold text-slate-500">${item.kelas}</span></div>
                                            <div class="flex items-center gap-2"><i class="fa-solid fa-utensils text-slate-300 text-[10px] w-3"></i><span class="text-[10px] font-bold text-slate-500 truncate pr-1">${item.makan}</span></div>
                                        </div>
                                    </div>
                                </div>`;
                        }).join('')}
                    </div>
                </div>`;
        }).join('');
    }

    function getHexColor(pny) {
        const map = {
            'Menpim': '#92400e',
            'Teknis': '#166534',
            'Kerjasama': '#1e40af',
            'Pustrajak': '#9a3412'
        };
        return map[pny] || '#64748b';
    }

    function openModalTambah() {
        if (!IS_ADMIN) return;
        sheetTitle.innerText = "Tambah Jadwal";
        resetForm();
        toggleInputs(false);
        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnHapus) btnHapus.classList.add('hidden');
        if (btnSubmit) {
            btnSubmit.classList.remove('hidden');
            btnSubmit.innerText = "Simpan Jadwal";
        }
        stokModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function openModalDetail(id) {
        const item = agendaData.find(a => String(a.id) === String(id));
        if (!item) return;
        sheetTitle.innerText = "Detail Jadwal";
        document.getElementById('edit-id').value = item.id;
        document.getElementById('f-judul').value = item.judul;
        document.getElementById('f-start').value = item.start;
        document.getElementById('f-end').value = item.end;
        document.getElementById('f-asrama').value = item.asrama;
        document.getElementById('f-pny').value = item.pny;
        document.getElementById('f-peserta').value = item.peserta || '';
        document.getElementById('f-kelas').value = item.kelas || '';
        document.getElementById('f-makan').value = item.makan || '';
        toggleInputs(true);
        if (IS_ADMIN && btnEditTrigger) btnEditTrigger.classList.remove('hidden');
        if (btnHapus) btnHapus.classList.add('hidden');
        if (btnSubmit) btnSubmit.classList.add('hidden');
        stokModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function enableEditMode() {
        if (!IS_ADMIN) return;
        sheetTitle.innerText = "Ubah Jadwal";
        toggleInputs(false);
        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnHapus) btnHapus.classList.remove('hidden');
        if (btnSubmit) {
            btnSubmit.classList.remove('hidden');
            btnSubmit.innerText = "Simpan Perubahan";
        }
    }

    function closeModal() {
        stokModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function toggleInputs(disabled) {
        ['f-judul', 'f-start', 'f-end', 'f-asrama', 'f-pny', 'f-peserta', 'f-kelas', 'f-makan'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.disabled = disabled;
            disabled ? el.classList.add('opacity-70') : el.classList.remove('opacity-70');
        });
    }

    function resetForm() {
        document.getElementById('agenda-form').reset();
        document.getElementById('edit-id').value = '';
        const today = getTodayStr();
        document.getElementById('f-start').value = today;
        document.getElementById('f-end').value = today;
    }

    async function handleSave(e) {
        e.preventDefault();
        if (!IS_ADMIN) return;
        const id = document.getElementById('edit-id').value;
        const formData = new FormData();
        formData.append('judul', document.getElementById('f-judul').value);
        formData.append('start', document.getElementById('f-start').value);
        formData.append('end', document.getElementById('f-end').value);
        formData.append('pny', document.getElementById('f-pny').value);
        formData.append('asrama', document.getElementById('f-asrama').value);
        formData.append('peserta', document.getElementById('f-peserta').value);
        formData.append('kelas', document.getElementById('f-kelas').value);
        formData.append('makan', document.getElementById('f-makan').value);
        let url = 'get_timetable.php?action=create';
        if (id) {
            formData.append('id', id);
            url = 'get_timetable.php?action=update';
        }
        await fetch(url, {
            method: 'POST',
            body: formData
        });
        closeModal();
        showToast('Data tersimpan');
        loadAgenda();
    }

    async function handleDelete() {
        if (!IS_ADMIN) return;
        if (!confirm('Hapus jadwal ini?')) return;
        const id = document.getElementById('edit-id').value;
        const fd = new FormData();
        fd.append('id', id);
        await fetch('get_timetable.php?action=delete', {
            method: 'POST',
            body: fd
        });
        closeModal();
        showToast('Jadwal dihapus');
        loadAgenda();
    }

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.innerText = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', 3000);
    }

    function changeMonth(dir) {
        viewDate.setMonth(viewDate.getMonth() + dir);
        renderCalendar();
    }

    function filterByDate(dateStr) {
        const events = agendaData.filter(ev => dateStr >= ev.start && dateStr <= ev.end);
        renderList(events);
        const title = document.getElementById('view-title');
        if (title) title.innerText = dateStr.split('-').reverse().join(' / ');
        const badge = document.getElementById('badge-count');
        if (badge) badge.innerText = `${events.length} Agenda`;
        const btn = document.getElementById('btn-show-all');
        if (btn) btn.classList.remove('hidden');
    }

    function showAllAgenda() {
        document.getElementById('view-title').innerText = "Daftar Agenda";
        renderCalendar();
    }

    function openExportModal() {
        const modal = document.getElementById('exportModal');
        modal.classList.remove('hidden');
        const today = new Date();
        const prior = new Date();
        prior.setDate(today.getDate() - 30);
        document.getElementById('exportTo').value = today.toISOString().split('T')[0];
        document.getElementById('exportFrom').value = prior.toISOString().split('T')[0];
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
        window.location.href = `timetable_export.php?from=${from}&to=${to}`;
        closeExportModal();
    }

    // ✅ Batalkan semua request saat halaman ditinggalkan
    let abortController = new AbortController();

    async function loadAgendaSafe() {
        abortController.abort();
        abortController = new AbortController();
        try {
            const res = await fetch('get_timetable.php?action=read', {
                signal: abortController.signal
            });
            const rawData = await res.json();
            agendaData = markBentrok(rawData);
            renderCalendar();
        } catch (e) {
            if (e.name !== 'AbortError') console.error(e);
        }
    }

    window.loadAgenda = loadAgendaSafe;

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) abortController.abort();
        else loadAgendaSafe();
    });

    window.addEventListener('beforeunload', () => abortController.abort());
    window.addEventListener('pagehide', () => abortController.abort());
</script>