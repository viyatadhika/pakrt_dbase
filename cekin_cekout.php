<?php
session_start();
require_once 'config.php';
$db = $conn ?? $koneksi ?? null;
if (!($db instanceof mysqli)) die('Koneksi database tidak ditemukan.');
$db->set_charset('utf8mb4');

$title = "Cekin & Cekout";
include 'header.php';
include 'config.php';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f1f5f9;
        color: #0f172a;
        overflow-x: hidden;
        -webkit-tap-highlight-color: transparent;
    }

    .header-container {
        position: sticky;
        top: 0;
        z-index: 50;
        background: rgba(255, 255, 255, .97) !important;
        backdrop-filter: blur(10px);
        box-shadow: 0 1px 12px rgba(0, 0, 0, .06);
    }

    .pill {
        padding: 6px 14px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        transition: all .18s;
        cursor: pointer;
    }

    .pill.active {
        background: #0ea5e9;
        color: #fff;
        border-color: #0ea5e9;
        box-shadow: 0 2px 8px rgba(14, 165, 233, .3);
    }

    .scroll-x::-webkit-scrollbar {
        display: none;
    }

    .kegiatan-wrap {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e0f2fe;
        box-shadow: 0 2px 8px rgba(2, 132, 199, .07);
        overflow: hidden;
        transition: box-shadow .2s;
    }

    .kegiatan-wrap:hover {
        box-shadow: 0 4px 18px rgba(2, 132, 199, .12);
    }

    .kegiatan-header {
        padding: 16px;
        cursor: pointer;
        transition: background .15s;
        user-select: none;
    }

    .kegiatan-header:hover {
        background: #f8fbff;
    }

    .stat-box {
        flex: 1;
        border-radius: 12px;
        padding: 8px 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
    }

    .stat-box .stat-num {
        font-size: 20px;
        font-weight: 900;
        line-height: 1;
    }

    .stat-box .stat-lbl {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .stat-pending {
        background: #fff1f2;
    }

    .stat-pending .stat-num {
        color: #be123c;
    }

    .stat-pending .stat-lbl {
        color: #fb7185;
    }

    .stat-in {
        background: #ecfdf5;
    }

    .stat-in .stat-num {
        color: #047857;
    }

    .stat-in .stat-lbl {
        color: #34d399;
    }

    .stat-out {
        background: #f8fafc;
    }

    .stat-out .stat-num {
        color: #475569;
    }

    .stat-out .stat-lbl {
        color: #94a3b8;
    }

    .prog-track {
        height: 5px;
        border-radius: 99px;
        background: #f1f5f9;
        overflow: hidden;
        margin-top: 10px;
    }

    .prog-bar {
        height: 100%;
        border-radius: 99px;
        background: linear-gradient(90deg, #0ea5e9, #06b6d4);
        transition: width .5s ease;
    }

    .prog-bar-out {
        height: 100%;
        border-radius: 99px;
        background: linear-gradient(90deg, #94a3b8, #64748b);
        transition: width .5s ease;
    }

    .kegiatan-body {
        border-top: 1px solid #f0f9ff;
    }

    .peserta-card {
        background: #f8fbff;
        border-radius: 12px;
        padding: 11px 13px;
        border: 1px solid #e0f2fe;
        transition: background .15s;
    }

    .peserta-card:hover {
        background: #f0f9ff;
    }

    .search-card {
        background: #fff;
        border-radius: 14px;
        padding: 12px 14px;
        border: 1px solid #e0f2fe;
        box-shadow: 0 1px 4px rgba(2, 132, 199, .06);
    }

    /* Empty state */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 56px 24px;
        text-align: center;
    }

    .empty-state-icon {
        width: 76px;
        height: 76px;
        background: #f0f9ff;
        border: 1.5px solid #bae6fd;
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 18px;
        box-shadow: 0 4px 16px rgba(14, 165, 233, .1);
    }

    .empty-state-icon i {
        font-size: 30px;
        color: #38bdf8;
    }

    .empty-state-title {
        font-size: 14px;
        font-weight: 800;
        color: #0c4a6e;
        margin: 0 0 8px;
    }

    .empty-state-desc {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 500;
        margin: 0;
        line-height: 1.7;
    }

    /* Fallback banner */
    .fallback-banner {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 16px;
        padding: 14px 16px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 16px;
    }

    .fallback-banner i {
        color: #f97316;
        font-size: 14px;
        margin-top: 1px;
        flex-shrink: 0;
    }

    .fallback-banner-title {
        font-size: 12px;
        font-weight: 800;
        color: #c2410c;
        margin: 0 0 2px;
    }

    .fallback-banner-desc {
        font-size: 11px;
        color: #9a3412;
        font-weight: 500;
        margin: 0;
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .au {
        animation: fadeUp .22s ease-out forwards;
    }


    /* Modal konfirmasi checkout */
    .checkout-modal-panel {
        width: 100%;
        max-width: 420px;
        background: #fff;
        border-radius: 24px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
        overflow: hidden;
        animation: checkoutModalIn .22s ease-out;
    }

    @keyframes checkoutModalIn {
        from {
            opacity: 0;
            transform: translateY(14px) scale(.98);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .checkout-info-row {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px 12px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .checkout-info-icon {
        width: 36px;
        height: 36px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
</style>

<div class="header-container border-b border-gray-100">
    <header class="px-5 py-4 flex items-center justify-between" style="background:#fff!important;background-image:none!important">
        <div class="flex items-center gap-3 min-w-0">
            <button onclick="window.history.back()"
                class="w-10 h-10 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition shrink-0">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <div class="min-w-0">
                <h1 class="text-lg font-extrabold text-sky-600 leading-tight">Cekin &amp; Cekout</h1>
                <p class="text-xs text-gray-400 font-medium mt-0.5">Monitoring kehadiran peserta</p>
            </div>
        </div>
        <button onclick="openExportModal()"
            class="w-10 h-10 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition shrink-0" title="Download PDF">
            <i class="fa-solid fa-download"></i>
        </button>
    </header>
</div>

<main class="px-4 py-5">

    <div class="relative mb-3">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-sky-400 text-xs pointer-events-none"></i>
        <input type="text" id="qSearch" oninput="handleSearchInput()"
            placeholder="Cari nama, kamar, instansi, kegiatan..."
            class="w-full pl-10 pr-10 py-3 bg-white border border-gray-200 rounded-2xl text-sm outline-none shadow-sm focus:ring-2 focus:ring-sky-100 focus:border-sky-300 transition">
        <button id="btnClearSearch" onclick="clearSearch()"
            class="hidden absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-slate-400 hover:text-slate-600 transition">
            <i class="fa-solid fa-xmark text-xs"></i>
        </button>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-2 scroll-x mb-5">
        <button onclick="setGedungFilter('Semua')" class="pill active" id="pill-Semua">Semua</button>
        <button onclick="setGedungFilter('Candra 1')" class="pill" id="pill-Candra1">Candra 1</button>
        <button onclick="setGedungFilter('Candra 2')" class="pill" id="pill-Candra2">Candra 2</button>
        <button onclick="setGedungFilter('Sari')" class="pill" id="pill-Sari">Sari</button>
        <button onclick="setGedungFilter('Cakra 1')" class="pill" id="pill-Cakra1">Cakra 1</button>
        <button onclick="setGedungFilter('Cakra 2')" class="pill" id="pill-Cakra2">Cakra 2</button>
        <button onclick="setGedungFilter('Cakra 3')" class="pill" id="pill-Cakra3">Cakra 3</button>
        <button onclick="setGedungFilter('Cakra 4')" class="pill" id="pill-Cakra4">Cakra 4</button>
        <button onclick="setGedungFilter('Cakra 5')" class="pill" id="pill-Cakra5">Cakra 5</button>
    </div>

    <div id="mainContainer" class="space-y-4 pb-24">
        <div class="text-center py-16 text-slate-300 text-sm">Memuat data...</div>
    </div>
</main>

<!-- Modal Export -->
<div id="exportModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeExportModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-extrabold text-gray-800">Download Laporan</p>
                    <p class="text-[11px] text-gray-400">Pilih rentang tanggal</p>
                </div>
                <button onclick="closeExportModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-bold text-gray-600">Dari Tanggal</label>
                    <input type="date" id="exportFrom" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 text-sm outline-none">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600">Sampai Tanggal</label>
                    <input type="date" id="exportTo" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 text-sm outline-none">
                </div>
                <button onclick="doExport()" class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Download PDF</button>
            </div>
        </div>
    </div>
</div>


<!-- Modal Konfirmasi Check-Out -->
<div id="checkoutConfirmModal" class="fixed inset-0 z-[1000] hidden">
    <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-sm" onclick="closeCheckoutConfirm()"></div>
    <div class="relative w-full h-full flex items-end sm:items-center justify-center p-4">
        <div class="checkout-modal-panel">
            <div class="px-5 pt-5 pb-4 border-b border-slate-100">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-door-open"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-base font-extrabold text-slate-800 leading-tight">Konfirmasi Check-Out</p>
                            <p class="text-[11px] text-slate-400 font-medium mt-1">Periksa kembali data peserta</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeCheckoutConfirm()" class="w-9 h-9 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="p-5 space-y-3">
                <div class="checkout-info-row">
                    <div class="checkout-info-icon bg-sky-50 text-sky-600"><i class="fa-solid fa-user"></i></div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wide font-extrabold text-slate-400">Peserta</p>
                        <p id="checkoutConfirmName" class="text-sm font-extrabold text-slate-800 truncate">-</p>
                    </div>
                </div>

                <div class="checkout-info-row">
                    <div class="checkout-info-icon bg-indigo-50 text-indigo-600"><i class="fa-solid fa-bed"></i></div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wide font-extrabold text-slate-400">Lokasi Kamar</p>
                        <p id="checkoutConfirmRoom" class="text-sm font-bold text-slate-700 truncate">-</p>
                    </div>
                </div>

                <div class="checkout-info-row">
                    <div class="checkout-info-icon bg-emerald-50 text-emerald-600"><i class="fa-solid fa-arrow-right-to-bracket"></i></div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wide font-extrabold text-slate-400">Waktu Check-In</p>
                        <p id="checkoutConfirmCheckin" class="text-sm font-bold text-slate-700">-</p>
                    </div>
                </div>

                <div class="rounded-2xl bg-rose-50 border border-rose-100 p-3.5 flex items-start gap-3">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500 mt-0.5"></i>
                    <p class="text-[11px] leading-relaxed text-rose-700 font-semibold">Pastikan peserta benar-benar telah meninggalkan kamar. Setelah dikonfirmasi, tanggal dan jam Check-Out akan langsung tersimpan.</p>
                </div>
            </div>

            <div class="px-5 pb-5 grid grid-cols-2 gap-3">
                <button type="button" onclick="closeCheckoutConfirm()" class="py-3 rounded-2xl bg-slate-100 text-slate-600 text-sm font-extrabold">Batal</button>
                <button type="button" id="checkoutConfirmButton" onclick="executeConfirmedCheckout()" class="py-3 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-extrabold shadow-lg shadow-rose-200">
                    <i class="fa-solid fa-door-open mr-1.5"></i>Ya, Check-Out
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-8 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-5 py-2.5 rounded-full text-[11px] font-bold opacity-0 transition-all z-[300] pointer-events-none shadow-lg whitespace-nowrap"></div>

<script>
    const apiUrl = 'peserta_penginapan_api.php';
    let rawData = [];
    let isFallback = false;
    let gedungFilter = 'Semua';
    let openAgendaIds = new Set();
    let remoteSearchResults = [];
    let searchTimer = null;
    let searchRequestSeq = 0;
    let searchLoading = false;
    let pendingCheckout = null;

    const $ = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    /* ── TOAST ─────────────────────────────────────────────────── */
    function showToast(msg, dur = 2500) {
        const t = $('toast');
        t.textContent = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', dur);
    }

    /* ── HELPERS ────────────────────────────────────────────────── */
    const normSt = s => s === 'Check-in' ? 'IN' : s === 'Check-out' ? 'OUT' : 'PENDING';

    function getCurrentTimeString() {
        const d = new Date();
        return String(d.getHours()).padStart(2, '0') + ':' +
            String(d.getMinutes()).padStart(2, '0') + ':' +
            String(d.getSeconds()).padStart(2, '0');
    }

    function getCurrentDateString() {
        const d = new Date();
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    /* ── LOAD DATA ──────────────────────────────────────────────── */
    async function loadData() {
        try {
            const r = await fetch(apiUrl + '?action=list_cekin&before_days=2&after_days=2');
            const j = await r.json();
            if (!j.status) {
                showToast(j.message || 'Gagal');
                return;
            }

            if (j.data && Array.isArray(j.data.agendas)) {
                rawData = j.data.agendas;
                isFallback = j.data.is_fallback || false;
            } else {
                rawData = j.data || [];
                isFallback = false;
            }
            filterAndRender();
        } catch (e) {
            console.error(e);
            showToast('Kesalahan koneksi');
        }
    }

    /* ── FILTER GEDUNG ──────────────────────────────────────────── */
    function setGedungFilter(g) {
        gedungFilter = g;
        document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
        const t = $('pill-' + g.replace(/\s/g, ''));
        if (t) t.classList.add('active');
        filterAndRender();
    }

    function clearSearch() {
        $('qSearch').value = '';
        $('btnClearSearch').classList.add('hidden');
        remoteSearchResults = [];
        searchLoading = false;
        searchRequestSeq++;
        if (searchTimer) clearTimeout(searchTimer);
        filterAndRender();
    }

    function handleSearchInput() {
        const q = $('qSearch').value.trim();
        $('btnClearSearch').classList.toggle('hidden', !q);

        if (searchTimer) clearTimeout(searchTimer);

        if (!q) {
            remoteSearchResults = [];
            searchLoading = false;
            searchRequestSeq++;
            filterAndRender();
            return;
        }

        searchLoading = true;
        filterAndRender();
        searchTimer = setTimeout(() => searchAllPeserta(q), 350);
    }

    async function searchAllPeserta(q) {
        const requestId = ++searchRequestSeq;
        try {
            const r = await fetch(apiUrl + '?action=search_peserta&q=' + encodeURIComponent(q) + '&limit=200');
            const j = await r.json();
            if (requestId !== searchRequestSeq) return;

            if (!j.status) {
                remoteSearchResults = [];
                showToast(j.message || 'Pencarian gagal');
            } else {
                remoteSearchResults = (Array.isArray(j.data) ? j.data : []).map(p => ({
                    ...p,
                    _judul: p.judul || 'Tanpa Kegiatan',
                    _agendaId: p.agenda_id || 0,
                    _startDate: p.start_date || '',
                    _endDate: p.end_date || '',
                    _canOperate: Boolean(p.can_operate)
                }));
            }
        } catch (e) {
            if (requestId !== searchRequestSeq) return;
            console.error(e);
            remoteSearchResults = [];
            showToast('Kesalahan koneksi pencarian');
        } finally {
            if (requestId === searchRequestSeq) {
                searchLoading = false;
                filterAndRender();
            }
        }
    }

    /* ── CAPTURE OPEN ACCORDIONS ────────────────────────────────── */
    function captureOpenAgendasFromDom() {
        const opened = new Set();
        document.querySelectorAll('.kegiatan-wrap').forEach(wrap => {
            const id = String(wrap.dataset.agendaId || '');
            const body = wrap.querySelector('.kegiatan-body');
            if (!id || !body) return;
            if (body.style.display !== 'none' && body.style.display !== '') opened.add(id);
        });
        openAgendaIds = opened;
    }

    function restoreOpenAgendas() {
        document.querySelectorAll('.kegiatan-wrap').forEach(wrap => {
            const id = String(wrap.dataset.agendaId || '');
            if (!id || !openAgendaIds.has(id)) return;
            const body = wrap.querySelector('.kegiatan-body');
            const icon = wrap.querySelector('.toggle-icon');
            const hintIcon = wrap.querySelector('.toggle-hint-icon');
            if (body) body.style.display = 'block';
            if (icon) icon.style.transform = 'rotate(180deg)';
            if (hintIcon) hintIcon.style.transform = 'rotate(180deg)';
        });
    }

    /* ── BADGE & BUTTON BUILDERS ────────────────────────────────── */
    function formatTanggalIndo(value) {
        if (!value || value === '0000-00-00') return 'Belum tercatat';
        const parts = String(value).slice(0, 10).split('-');
        if (parts.length !== 3) return String(value);
        const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const y = Number(parts[0]);
        const m = Number(parts[1]);
        const d = Number(parts[2]);
        if (!y || !m || !d || !bulan[m - 1]) return String(value);
        return String(d).padStart(2, '0') + ' ' + bulan[m - 1] + ' ' + y;
    }

    function formatJam(value) {
        if (!value || value === '00:00:00') return 'Belum tercatat';
        return String(value).slice(0, 5) + ' WIB';
    }

    function waktuInapHtml(p) {
        const ciAda = !!(p.checkin_date || p.checkin_time);
        const coAda = !!(p.checkout_date || p.checkout_time);

        const ciTanggal = ciAda ? esc(formatTanggalIndo(p.checkin_date)) : 'Belum check-in';
        const ciJam = ciAda ? esc(formatJam(p.checkin_time)) : '—';
        const coTanggal = coAda ? esc(formatTanggalIndo(p.checkout_date)) : 'Belum check-out';
        const coJam = coAda ? esc(formatJam(p.checkout_time)) : '—';

        return `
        <div style="margin-top:11px;padding-top:10px;border-top:1px solid #eaf2f8">
          <div style="display:grid;grid-template-columns:minmax(0,1fr) 20px minmax(0,1fr);align-items:center;gap:5px;"><!-- Check-in -->
            <div style="display:flex;align-items:center;gap:8px;min-width:0">
              <div style="width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
                          background:${ciAda ? '#dcfce7' : '#f1f5f9'};color:${ciAda ? '#16a34a' : '#94a3b8'}">
                <i class="fa-solid fa-arrow-right-to-bracket" style="font-size:11px"></i>
              </div>
              <div style="min-width:0">
                <div style="font-size:8px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8;margin-bottom:2px">Check-In</div>
                <div style="font-size:10px;font-weight:800;color:${ciAda ? '#0f172a' : '#94a3b8'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${ciTanggal}</div>
                <div style="font-size:9px;font-weight:600;color:${ciAda ? '#16a34a' : '#cbd5e1'};margin-top:1px">${ciJam}</div>
              </div>
            </div>

            <div style="height:1px;background:linear-gradient(90deg,#dbeafe,#bae6fd,#dbeafe);position:relative">
              <span style="position:absolute;left:50%;top:50%;width:5px;height:5px;border-radius:50%;background:#7dd3fc;transform:translate(-50%,-50%)"></span>
            </div>

            <div style="display:flex;align-items:center;gap:8px;min-width:0;justify-content:flex-end">
              <div style="min-width:0;text-align:right">
                <div style="font-size:8px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8;margin-bottom:2px">Check-Out</div>
                <div style="font-size:10px;font-weight:800;color:${coAda ? '#0f172a' : '#94a3b8'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${coTanggal}</div>
                <div style="font-size:9px;font-weight:600;color:${coAda ? '#64748b' : '#cbd5e1'};margin-top:1px">${coJam}</div>
              </div>
              <div style="width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
                          background:${coAda ? '#e2e8f0' : '#f1f5f9'};color:${coAda ? '#475569' : '#94a3b8'}">
                <i class="fa-solid fa-arrow-right-from-bracket" style="font-size:11px"></i>
              </div>
            </div>
          </div>
        </div>`;
    }

    function badgeSt(p, agendaId) {
        const st = normSt(p.status_inap);
        const time = st === 'IN' ?
            (p.checkin_time ? ' @ ' + String(p.checkin_time).slice(0, 5) : '') :
            st === 'OUT' ?
            (p.checkout_time ? ' @ ' + String(p.checkout_time).slice(0, 5) : '') :
            '';

        const cfg = {
            PENDING: {
                bg: '#fff7ed',
                color: '#c2410c',
                border: '#fed7aa',
                icon: 'fa-regular fa-clock',
                label: 'Belum CI'
            },
            IN: {
                bg: '#ecfdf5',
                color: '#047857',
                border: '#a7f3d0',
                icon: 'fa-solid fa-check',
                label: 'Check-In'
            },
            OUT: {
                bg: '#f8fafc',
                color: '#475569',
                border: '#cbd5e1',
                icon: 'fa-solid fa-door-open',
                label: 'Check-Out'
            }
        } [st];

        if (st === 'OUT' || p._canOperate === false) {
            return `<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;
                padding:3px 9px;border-radius:999px;white-space:nowrap;line-height:1.4;
                background:${cfg.bg};color:${cfg.color};border:1px solid ${cfg.border}">
                <i class="${cfg.icon}" style="font-size:8px"></i>${cfg.label}
            </span>`;
        }

        return `<button onclick="${st === 'PENDING' ? `doStatus(${p.id},${agendaId},'IN')` : `confirmCheckout(${p.id},${agendaId})`};event.stopPropagation();"
            style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;
            padding:3px 9px;border-radius:999px;white-space:nowrap;line-height:1.4;
            background:${cfg.bg};color:${cfg.color};border:1px solid ${cfg.border};cursor:pointer">
            <i class="${cfg.icon}" style="font-size:8px"></i>${cfg.label}
        </button>`;
    }

    function badgeRole(peran) {
        const cfg = {
            Pengajar: {
                bg: '#eff6ff',
                color: '#2563eb',
                border: '#bfdbfe'
            },
            Panitia: {
                bg: '#fffbeb',
                color: '#b45309',
                border: '#fde68a'
            }
        } [peran] || {
            bg: '#f0fdf4',
            color: '#16a34a',
            border: '#bbf7d0'
        };

        return `<span style="display:inline-flex;align-items:center;font-size:9px;font-weight:700;
            padding:2px 7px;border-radius:999px;white-space:nowrap;flex-shrink:0;
            background:${cfg.bg};color:${cfg.color};border:1px solid ${cfg.border}">
            ${esc((peran || 'PESERTA').toUpperCase())}
        </span>`;
    }

    function btnAction(p, agendaId) {
        const st = normSt(p.status_inap);
        if (p._canOperate === false) {
            return `<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;color:#64748b;font-weight:700;
                padding:4px 9px;border-radius:999px;background:#f1f5f9;border:1px solid #e2e8f0">
                <i class="fa-solid fa-clock-rotate-left" style="font-size:8px"></i>Riwayat
            </span>`;
        }
        if (st === 'PENDING') {
            return `<button onclick="doStatus(${p.id},${agendaId},'IN');event.stopPropagation();"
                style="padding:6px 14px;border-radius:10px;font-size:11px;font-weight:800;
                background:linear-gradient(135deg,#0ea5e9,#06b6d4);color:#fff;
                box-shadow:0 2px 8px rgba(14,165,233,.35);border:none;cursor:pointer;white-space:nowrap">Check-In</button>`;
        }
        if (st === 'IN') {
            return `<button onclick="confirmCheckout(${p.id},${agendaId});event.stopPropagation();"
                style="padding:6px 14px;border-radius:10px;font-size:11px;font-weight:800;
                background:#fff1f2;color:#be123c;border:1px solid #fecdd3;cursor:pointer;white-space:nowrap">Check-Out</button>`;
        }
        return `<span style="font-size:10px;color:#cbd5e1;font-weight:700">Selesai</span>`;
    }

    /* ── CARD HTML ──────────────────────────────────────────────── */
    function pesertaCardHtml(p, agendaId, delay = 0) {
        return `
<div class="peserta-card au" style="animation-delay:${delay}s">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
    ${badgeSt(p, agendaId)}
    ${btnAction(p, agendaId)}
  </div>
  <div style="display:flex;align-items:center;gap:12px">
    <div style="width:42px;height:42px;flex-shrink:0;background:#f0f9ff;border:1px solid #bae6fd;
                border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center">
      <span style="font-size:7px;font-weight:800;color:#7dd3fc;text-transform:uppercase;line-height:1">Kamar</span>
      <span style="font-size:13px;font-weight:900;color:#0369a1;line-height:1;margin-top:2px">${esc(p.kamar || '−')}</span>
    </div>
    <div style="min-width:0;flex:1">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">
        <p style="font-size:13px;font-weight:700;color:#0c4a6e;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(p.nama || '−')}</p>
        ${badgeRole(p.peran)}
      </div>
      <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap">
        <span style="font-size:10px;color:#64748b;font-weight:600">${esc(p.gedung || '−')}</span>
        <span style="font-size:10px;color:#cbd5e1">·</span>
        <span style="font-size:10px;color:#94a3b8">Lt.${esc(p.lantai || '−')}</span>
        ${p.instansi ? `<span style="font-size:10px;color:#cbd5e1">·</span>
          <span style="font-size:10px;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:110px">${esc(p.instansi)}</span>` : ''}
      </div>
    </div>
  </div>
  ${waktuInapHtml(p)}
</div>`;
    }

    function searchCardHtml(p, i) {
        return `
<div class="search-card au" style="animation-delay:${i * .02}s">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
    ${badgeSt(p, p._agendaId)}
    ${btnAction(p, p._agendaId)}
  </div>
  <div style="display:flex;align-items:center;gap:12px">
    <div style="width:42px;height:42px;flex-shrink:0;background:#f0f9ff;border:1px solid #bae6fd;
                border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center">
      <span style="font-size:7px;font-weight:800;color:#7dd3fc;text-transform:uppercase;line-height:1">Kamar</span>
      <span style="font-size:13px;font-weight:900;color:#0369a1;line-height:1;margin-top:2px">${esc(p.kamar || '−')}</span>
    </div>
    <div style="min-width:0;flex:1">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">
        <p style="font-size:13px;font-weight:700;color:#0c4a6e;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(p.nama || '−')}</p>
        ${badgeRole(p.peran)}
      </div>
      <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap">
        <span style="font-size:10px;color:#64748b;font-weight:600">${esc(p.gedung || '−')}</span>
        <span style="font-size:10px;color:#cbd5e1">·</span>
        <span style="font-size:10px;color:#94a3b8">Lt.${esc(p.lantai || '−')}</span>
        ${p.instansi ? `<span style="font-size:10px;color:#cbd5e1">·</span>
          <span style="font-size:10px;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:110px">${esc(p.instansi)}</span>` : ''}
      </div>
      <div style="margin-top:4px;font-size:10px;color:#38bdf8;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
        <i class="fa-solid fa-calendar-days" style="margin-right:3px;opacity:.6"></i>${esc(p._judul || '')}
      </div>
      <div style="margin-top:3px;font-size:9px;color:#94a3b8;font-weight:600">
        ${esc(p._startDate || '−')} s/d ${esc(p._endDate || '−')}
      </div>
    </div>
  </div>
  ${waktuInapHtml(p)}
</div>`;
    }

    /* ── RENDER AGENDA LIST ─────────────────────────────────────── */
    function renderAgendaList() {
        return rawData.map((ag, agIdx) => {
            const allPeserta = (ag.peserta || []).filter(p => gedungFilter === 'Semua' || p.gedung === gedungFilter);
            const belum = allPeserta.filter(p => normSt(p.status_inap) === 'PENDING').length;
            const hadir = allPeserta.filter(p => normSt(p.status_inap) === 'IN').length;
            const co = allPeserta.filter(p => normSt(p.status_inap) === 'OUT').length;
            const total = allPeserta.length;

            const pct = total ? Math.round((hadir / total) * 100) : 0;
            const pctCheckout = total ? Math.round((co / total) * 100) : 0;
            const pctColor = pct >= 80 ? '#047857' : pct >= 50 ? '#0ea5e9' : '#be123c';
            const pctCheckoutColor = pctCheckout >= 80 ? '#475569' : pctCheckout >= 50 ? '#64748b' : '#94a3b8';

            return `
<div class="kegiatan-wrap au" data-agenda-id="${ag.agenda_id}" style="animation-delay:${agIdx * .05}s">
  <div class="kegiatan-header" onclick="toggleKegiatan(this)">
    <div class="flex items-start justify-between gap-3 mb-3">
      <div class="min-w-0 flex-1">
        <p class="font-extrabold text-sky-800 text-sm leading-tight">${esc(ag.judul || 'Tanpa Kegiatan')}</p>
        <p class="text-slate-400 mt-0.5 font-medium" style="font-size:10px">
          <i class="fa-regular fa-calendar" style="margin-right:3px"></i>${esc(ag.start_date || '')} s/d ${esc(ag.end_date || '')}
          <span style="margin:0 4px;color:#e2e8f0">·</span>${total} peserta
        </p>
      </div>
      <i class="fa-solid fa-chevron-down toggle-icon" style="color:#bae6fd;font-size:11px;margin-top:3px;flex-shrink:0;transition:transform .3s"></i>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:8px">
      <div class="stat-box stat-pending"><span class="stat-num">${belum}</span><span class="stat-lbl">Belum CI</span></div>
      <div class="stat-box stat-in"><span class="stat-num">${hadir}</span><span class="stat-lbl">Hadir</span></div>
      <div class="stat-box stat-out"><span class="stat-num">${co}</span><span class="stat-lbl">Check-out</span></div>
    </div>

    <div class="prog-track">
      <div class="prog-bar" style="width:${pct}%"></div>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px">
      <span style="font-size:10px;color:#94a3b8;font-weight:600">Kehadiran</span>
      <span style="font-size:10px;font-weight:900;color:${pctColor}">${pct}%</span>
    </div>

    <div class="prog-track" style="margin-top:8px">
      <div class="prog-bar-out" style="width:${pctCheckout}%"></div>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px">
      <span style="font-size:10px;color:#94a3b8;font-weight:600">Check-out</span>
      <span style="font-size:10px;font-weight:900;color:${pctCheckoutColor}">${pctCheckout}%</span>
    </div>

    <div style="display:flex;align-items:center;justify-content:center;gap:6px;
                margin-top:12px;padding-top:10px;border-top:1px dashed #bae6fd">
      <i class="fa-solid fa-list" style="color:#7dd3fc;font-size:10px"></i>
      <span style="font-size:11px;font-weight:700;color:#7dd3fc">Ketuk untuk lihat daftar peserta</span>
      <i class="fa-solid fa-chevron-down toggle-hint-icon" style="color:#7dd3fc;font-size:9px;transition:transform .3s"></i>
    </div>
  </div>

  <div class="kegiatan-body" style="display:none">
    <div class="px-4 py-3">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em">Daftar Peserta</span>
        <span style="font-size:11px;color:#94a3b8;font-weight:600">${allPeserta.length} orang</span>
      </div>
      <div class="space-y-2">
        ${allPeserta.map((p, i) => pesertaCardHtml(p, ag.agenda_id, i * .02)).join('')
          || '<p style="text-align:center;padding:24px 0;font-size:12px;color:#cbd5e1">Tidak ada peserta</p>'}
      </div>
    </div>
  </div>
</div>`;
        }).join('');
    }

    /* ── MAIN RENDER ────────────────────────────────────────────── */
    function filterAndRender() {
        const q = $('qSearch').value.trim();
        const ql = q.toLowerCase();

        $('btnClearSearch').classList.toggle('hidden', !q);

        // ── MODE SEARCH SELURUH RIWAYAT ──────────────────────────
        if (q) {
            if (searchLoading) {
                $('mainContainer').innerHTML = `
<div class="text-center py-16">
  <i class="fa-solid fa-spinner fa-spin" style="font-size:26px;color:#38bdf8;display:block;margin-bottom:12px"></i>
  <p class="text-slate-400 text-sm font-semibold">Mencari seluruh riwayat peserta...</p>
</div>`;
                return;
            }

            const results = remoteSearchResults.filter(p =>
                gedungFilter === 'Semua' || p.gedung === gedungFilter
            );

            if (!results.length) {
                $('mainContainer').innerHTML = `
<div class="text-center py-16">
  <i class="fa-solid fa-magnifying-glass" style="font-size:28px;color:#e2e8f0;display:block;margin-bottom:10px"></i>
  <p class="text-slate-300 text-sm font-semibold">Tidak ada hasil untuk "<span class="text-slate-400">${esc(q)}</span>"</p>
  <p style="font-size:10px;color:#cbd5e1;margin-top:6px">Pencarian mencakup kegiatan aktif dan kegiatan yang sudah selesai.</p>
</div>`;
                return;
            }

            $('mainContainer').innerHTML =
                `<div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;padding:0 4px;flex-wrap:wrap">
                    <i class="fa-solid fa-clock-rotate-left" style="color:#38bdf8;font-size:11px"></i>
                    <span style="font-size:12px;font-weight:700;color:#0ea5e9">${results.length} hasil ditemukan</span>
                    <span style="font-size:12px;color:#94a3b8">dari seluruh riwayat untuk "<b>${esc(q)}</b>"</span>
                </div>
                <div class="space-y-2">${results.map(searchCardHtml).join('')}</div>`;
            return;
        }

        // ── TIDAK ADA DATA SAMA SEKALI ───────────────────────────
        if (!rawData.length) {
            $('mainContainer').innerHTML = `
<div class="empty-state">
  <div class="empty-state-icon">
    <i class="fa-regular fa-calendar-xmark"></i>
  </div>
  <p class="empty-state-title">Tidak Ada Kegiatan dalam Rentang H-2 sampai H+2</p>
  <p class="empty-state-desc">
    Saat ini tidak ada kegiatan aktif.<br>
    Kegiatan akan muncul otomatis mulai H-2 sampai H+2.
  </p>
</div>`;
            return;
        }

        // ── FALLBACK: tidak ada kegiatan aktif ───────────────────
        if (isFallback) {
            $('mainContainer').innerHTML = `
<div class="empty-state">
  <div class="empty-state-icon">
    <i class="fa-regular fa-calendar-xmark"></i>
  </div>
  <p class="empty-state-title">Tidak Ada Kegiatan dalam Rentang H-2 sampai H+2</p>
  <p class="empty-state-desc">
    Saat ini tidak ada kegiatan aktif.<br>
    Kegiatan akan muncul otomatis mulai H-2 sampai H+2.
  </p>
</div>`;
            return;
        }

        // ── MODE NORMAL: kegiatan aktif berjalan ─────────────────
        $('mainContainer').innerHTML = `<div class="space-y-4">${renderAgendaList()}</div>`;
        restoreOpenAgendas();
    }

    /* ── TOGGLE ACCORDION ───────────────────────────────────────── */
    function toggleKegiatan(headerEl) {
        const wrap = headerEl.closest('.kegiatan-wrap');
        if (!wrap) return;

        const agendaId = String(wrap.dataset.agendaId || '');
        const body = wrap.querySelector('.kegiatan-body');
        const icon = headerEl.querySelector('.toggle-icon');
        const hintIcon = headerEl.querySelector('.toggle-hint-icon');
        if (!body) return;

        const isOpen = body.style.display !== 'none' && body.style.display !== '';

        body.style.display = isOpen ? 'none' : 'block';
        if (agendaId) isOpen ? openAgendaIds.delete(agendaId) : openAgendaIds.add(agendaId);
        if (icon) icon.style.transform = isOpen ? '' : 'rotate(180deg)';
        if (hintIcon) hintIcon.style.transform = isOpen ? '' : 'rotate(180deg)';
    }


    function findPesertaById(pesertaId) {
        for (const ag of rawData) {
            const p = (ag.peserta || []).find(x => Number(x.id) === Number(pesertaId));
            if (p) return p;
        }
        return remoteSearchResults.find(x => Number(x.id) === Number(pesertaId)) || null;
    }

    function confirmCheckout(pesertaId, agendaId) {
        const peserta = findPesertaById(pesertaId);
        if (!peserta) {
            showToast('Data peserta tidak ditemukan');
            return;
        }

        pendingCheckout = {
            pesertaId,
            agendaId
        };
        $('checkoutConfirmName').textContent = peserta.nama || '-';
        $('checkoutConfirmRoom').textContent = [peserta.gedung, peserta.kamar ? 'Kamar ' + peserta.kamar : ''].filter(Boolean).join(' • ') || '-';

        const tgl = peserta.checkin_date ? formatTanggalIndo(peserta.checkin_date) : 'Belum tercatat';
        const jam = peserta.checkin_time ? formatJam(peserta.checkin_time) : '';
        $('checkoutConfirmCheckin').textContent = jam ? tgl + ' • ' + jam : tgl;

        $('checkoutConfirmModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeCheckoutConfirm() {
        $('checkoutConfirmModal').classList.add('hidden');
        document.body.style.overflow = '';
        pendingCheckout = null;
    }

    async function executeConfirmedCheckout() {
        if (!pendingCheckout) return;
        const data = pendingCheckout;
        const btn = $('checkoutConfirmButton');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>Menyimpan...';
        closeCheckoutConfirm();
        try {
            await doStatus(data.pesertaId, data.agendaId, 'OUT');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-door-open mr-1.5"></i>Ya, Check-Out';
        }
    }

    /* ── DO STATUS (CHECK-IN / CHECK-OUT) ───────────────────────── */
    async function doStatus(pesertaId, agendaId, type) {
        captureOpenAgendasFromDom();

        let found = null,
            foundAgenda = null;
        for (const ag of rawData) {
            const p = (ag.peserta || []).find(x => Number(x.id) === Number(pesertaId));
            if (p) {
                found = p;
                foundAgenda = ag;
                break;
            }
        }

        if (!found) {
            found = remoteSearchResults.find(x => Number(x.id) === Number(pesertaId)) || null;
        }

        if (!found) {
            showToast('Data tidak ditemukan');
            return;
        }

        const now = getCurrentTimeString();
        const today = getCurrentDateString();
        const fd = new FormData();

        ['id', 'agenda_id', 'nama', 'instansi', 'nip', 'no_hp', 'peran', 'jenis_kelamin',
            'gedung', 'lantai', 'kamar', 'bed', 'checkin_date', 'checkin_time',
            'checkout_date', 'checkout_time', 'kondisi', 'catatan'
        ]
        .forEach(k => fd.append(k, found[k] || ''));

        fd.set('status_inap', type === 'IN' ? 'Check-in' : 'Check-out');
        if (type === 'IN') {
            fd.set('checkin_time', now);
            fd.set('checkin_date', today);
        } else {
            fd.set('checkout_time', now);
            fd.set('checkout_date', today);
        }
        fd.append('force_kamar', '1');

        try {
            const r = await fetch(apiUrl + '?action=save', {
                method: 'POST',
                body: fd
            });
            const j = await r.json();
            if (!j.status) {
                showToast(j.message || 'Gagal');
                return;
            }

            if (type === 'IN') {
                found.status_inap = 'Check-in';
                found.checkin_time = now;
                found.checkin_date = today;
            } else {
                found.status_inap = 'Check-out';
                found.checkout_time = now;
                found.checkout_date = today;
            }

            if (foundAgenda) {
                const ps = foundAgenda.peserta || [];
                foundAgenda.belum = ps.filter(p => normSt(p.status_inap) === 'PENDING').length;
                foundAgenda.hadir = ps.filter(p => normSt(p.status_inap) === 'IN').length;
                foundAgenda.checkout = ps.filter(p => normSt(p.status_inap) === 'OUT').length;
                foundAgenda.total = ps.length;
            }

            filterAndRender();
            requestAnimationFrame(() => restoreOpenAgendas());
            showToast(`${found.nama} ${type === 'IN' ? 'Check-In' : 'Check-Out'} @ ${now.slice(0,5)}`);

        } catch (e) {
            console.error(e);
            showToast('Kesalahan menyimpan');
        }
    }

    /* ── EXPORT MODAL ───────────────────────────────────────────── */
    function openExportModal() {
        const today = new Date();
        const prior = new Date();
        prior.setDate(today.getDate() - 30);
        $('exportTo').value = today.toISOString().split('T')[0];
        $('exportFrom').value = prior.toISOString().split('T')[0];
        $('exportModal').classList.remove('hidden');
    }

    function closeExportModal() {
        $('exportModal').classList.add('hidden');
    }

    function doExport() {
        const from = $('exportFrom').value;
        const to = $('exportTo').value;
        if (!from || !to) {
            showToast('Pilih rentang tanggal');
            return;
        }
        if (from > to) {
            showToast('Tanggal awal tidak boleh lebih besar');
            return;
        }
        window.location.href = 'peserta_penginapan_export.php?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
        closeExportModal();
    }

    window.onload = loadData;
</script>