<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Input Peserta & Pengajar";
include 'header.php';
include 'config.php';

if (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} elseif (isset($koneksi) && $koneksi instanceof mysqli) {
    $db = $koneksi;
} else {
    die('Koneksi database tidak ditemukan. Pastikan config.php menyediakan $conn atau $koneksi.');
}

$db->set_charset('utf8mb4');

$isAdmin = strtolower(isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '') === 'admin';

$agendaList = [];
$qAgenda = $db->query("
    SELECT id, judul, start_date, end_date, kategori
    FROM agenda_kegiatan
    ORDER BY start_date DESC, id DESC
");
if ($qAgenda) {
    while ($row = $qAgenda->fetch_assoc()) {
        $agendaList[] = $row;
    }
}

$agendaMapJs = array_map(function ($a) {
    return array(
        'id'    => (string)$a['id'],
        'judul' => $a['judul']
    );
}, $agendaList);
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

    .data-card:active {
        transform: scale(0.98);
        background-color: #f0f9ff;
    }

    .badge {
        font-size: 10px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 999px;
        display: inline-block;
        white-space: nowrap;
    }

    .badge-sky {
        background: #f0f9ff;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }

    .badge-indigo {
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #c7d2fe;
    }

    .badge-amber {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #fdba74;
    }

    .badge-rose {
        background: #fff1f2;
        color: #be123c;
        border: 1px solid #fecdd3;
    }

    .badge-emerald {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }

    .badge-gray {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    .modal-field {
        width: 100%;
        margin-top: 4px;
        padding: 12px 16px;
        border-radius: 16px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        outline: none;
        transition: .2s ease;
    }

    .modal-field:focus,
    .preview-input:focus,
    .preview-table-input:focus {
        border-color: #7dd3fc;
        box-shadow: 0 0 0 2px rgba(14, 165, 233, .08);
        background: #fff;
    }

    .modal-field:disabled,
    textarea:disabled,
    select:disabled {
        opacity: .75;
        background: #f8fafc;
    }

    .modal-scroll::-webkit-scrollbar,
    .preview-scroll::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .modal-scroll::-webkit-scrollbar-thumb,
    .preview-scroll::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 999px;
    }

    .preview-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        padding: 14px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .preview-input {
        width: 100%;
        margin-top: 4px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        outline: none;
        font-size: 13px;
    }

    .preview-check {
        width: 18px;
        height: 18px;
    }

    .preview-table-input {
        width: 100%;
        min-width: 110px;
        padding: 8px 10px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        outline: none;
        font-size: 12px;
    }

    @media (min-width: 768px) {
        .preview-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .preview-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }
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

    .preview-missing {
        border-color: #fecaca !important;
        background: #fff1f2 !important;
    }
</style>

<!-- Header -->
<header class="sticky-header px-4 pt-4 pb-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4 min-w-0">
            <button onclick="window.history.back()"
                class="w-10 h-10 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition shrink-0">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </button>
            <div class="min-w-0">
                <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Input Peserta &amp; Pengajar</h1>
                <p class="text-[12px] text-gray-400 font-medium leading-tight">Tambah data peserta / pengajar</p>
            </div>
        </div>

        <input type="file" id="excelInput" accept=".xlsx,.xls" class="hidden" onchange="handleExcel(event)">

        <button onclick="document.getElementById('excelInput').click()"
            class="absolute top-5 right-4 w-11 h-11 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition"
            title="Import Excel">
            <i class="fa-solid fa-file-import text-lg"></i>
        </button>
    </div>
</header>

<!-- MODAL TAMBAH / EDIT / DETAIL -->
<div id="stokModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-3 sm:p-4">
        <div class="w-full max-w-md bg-white rounded-3xl shadow-xl max-h-[92vh] overflow-hidden flex flex-col">

            <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0 bg-white">
                <div>
                    <p id="sheetTitle" class="text-sm font-extrabold text-gray-800">Tambah Peserta Penginapan</p>
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

            <div class="overflow-y-auto px-5 py-4 flex-1 modal-scroll">
                <form id="penginapan-form" onsubmit="handleSave(event)" class="space-y-3">
                    <input type="hidden" id="edit-id">

                    <div>
                        <label class="text-xs font-bold text-gray-600">Kegiatan</label>
                        <select id="f-agenda" class="modal-field">
                            <option value="">-- Pilih kegiatan --</option>
                            <?php foreach ($agendaList as $agenda): ?>
                                <option value="<?= (int)$agenda['id'] ?>">
                                    <?= htmlspecialchars($agenda['judul']) ?> | <?= htmlspecialchars($agenda['start_date']) ?> s/d <?= htmlspecialchars($agenda['end_date']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-gray-600">Nama Lengkap</label>
                        <input id="f-nama" type="text" class="modal-field">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-gray-600">Gedung</label>
                            <select id="f-gedung" class="modal-field">
                                <option value="">-- Pilih gedung --</option>
                                <option value="Candra 1">Candra 1</option>
                                <option value="Candra 2">Candra 2</option>
                                <option value="Cakra 1">Cakra 1</option>
                                <option value="Cakra 2">Cakra 2</option>
                                <option value="Cakra 3">Cakra 3</option>
                                <option value="Cakra 4">Cakra 4</option>
                                <option value="Cakra 5">Cakra 5</option>
                                <option value="Sari">Sari</option>
                                <option value="Tirta">Tirta</option>
                                <option value="Kartika">Kartika</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Kamar</label>
                            <input id="f-kamar" type="text" class="modal-field">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-gray-600">Lantai</label>
                            <input id="f-lantai" type="text" class="modal-field">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Bed</label>
                            <input id="f-bed" type="text" class="modal-field">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-gray-600">Peran</label>
                            <select id="f-peran" class="modal-field">
                                <option value="Peserta">Peserta</option>
                                <option value="Pengajar">Pengajar</option>
                                <option value="Panitia">Panitia</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Status Inap</label>
                            <select id="f-status" class="modal-field">
                                <option value="Belum Check-in">Belum Check-in</option>
                                <option value="Check-in">Check-in</option>
                                <option value="Check-out">Check-out</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-gray-600">Jenis Kelamin</label>
                            <select id="f-jk" class="modal-field">
                                <option value="">-- Pilih --</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Instansi</label>
                            <input id="f-instansi" type="text" class="modal-field">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-gray-600">NIP</label>
                            <input id="f-nip" type="text" class="modal-field">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">No HP</label>
                            <input id="f-nohp" type="text" class="modal-field">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-gray-600">Check-in</label>
                            <input id="f-checkin" type="date" class="modal-field">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Check-out</label>
                            <input id="f-checkout" type="date" class="modal-field">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-gray-600">Jam Check-in</label>
                            <input id="f-checkin-time" type="time" step="1" class="modal-field">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Jam Check-out</label>
                            <input id="f-checkout-time" type="time" step="1" class="modal-field">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-gray-600">Kondisi</label>
                        <input id="f-kondisi" type="text" class="modal-field">
                    </div>

                    <div>
                        <label class="text-xs font-bold text-gray-600">Catatan</label>
                        <textarea id="f-catatan" class="modal-field min-h-[90px]"></textarea>
                    </div>
                </form>
            </div>

            <?php if ($isAdmin): ?>
                <div class="shrink-0 border-t border-gray-100 bg-white p-4 space-y-3">
                    <button id="btnSubmit" type="submit" form="penginapan-form"
                        class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">
                        Simpan Data
                    </button>
                    <button id="btnHapus" type="button" onclick="handleDelete()"
                        class="w-full py-3 rounded-2xl bg-red-50 text-red-600 font-extrabold text-sm hidden">
                        <i class="fa-solid fa-trash-can mr-2"></i> Hapus Data
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL PREVIEW IMPORT -->
<div id="previewModal" class="fixed inset-0 bg-black/50 z-[1000] hidden">
    <div class="absolute inset-0" onclick="closePreviewModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-3 sm:p-4">
        <div class="w-full max-w-6xl bg-white rounded-3xl shadow-xl max-h-[92vh] overflow-hidden flex flex-col">

            <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0 bg-white">
                <div>
                    <p class="text-sm font-extrabold text-gray-800">Preview Import Excel</p>
                    <p class="text-[11px] text-gray-500">Cek, edit, lalu simpan ke database</p>
                </div>
                <button type="button" onclick="closePreviewModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-4 border-b border-gray-100 flex flex-wrap gap-2 shrink-0 bg-white">
                <button onclick="toggleSelectAllPreview(true)" class="px-4 py-2 rounded-xl bg-sky-50 text-sky-700 text-sm font-bold">Pilih Semua</button>
                <button onclick="toggleSelectAllPreview(false)" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-bold">Batal Pilih</button>
                <button onclick="deleteCheckedPreviewRows()" class="px-4 py-2 rounded-xl bg-rose-50 text-rose-700 text-sm font-bold">Hapus Yang Dicentang</button>
                <div class="ml-auto text-sm font-bold text-slate-500">
                    <span id="previewCount">0</span> data
                </div>
            </div>

            <!-- Desktop table -->
            <div class="hidden md:block flex-1 overflow-auto preview-scroll bg-white">
                <table class="w-full text-sm min-w-[1100px]">
                    <thead class="sticky top-0 bg-white border-b border-gray-100 z-10">
                        <tr>
                            <th class="p-3 text-left w-10">
                                <input type="checkbox" id="checkAllPreview" onchange="handleMasterPreviewCheck(this)">
                            </th>
                            <th class="p-3 text-left">Nama Kegiatan</th>
                            <th class="p-3 text-left">Nama</th>
                            <th class="p-3 text-left">JK</th>
                            <th class="p-3 text-left">NIP</th>
                            <th class="p-3 text-left">Instansi</th>
                            <th class="p-3 text-left">Gedung</th>
                            <th class="p-3 text-left">Lantai</th>
                            <th class="p-3 text-left">Kamar</th>
                            <th class="p-3 text-left">Peran</th>
                            <th class="p-3 text-left">Agenda ID</th>
                        </tr>
                    </thead>
                    <tbody id="previewTableBody"></tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="block md:hidden flex-1 overflow-y-auto preview-scroll bg-slate-50 p-4">
                <div id="previewCardContainer" class="space-y-3"></div>
            </div>

            <div class="p-4 border-t border-gray-100 bg-white shrink-0 flex gap-3">
                <button onclick="closePreviewModal()" class="flex-1 py-3 rounded-2xl bg-slate-100 text-slate-700 font-extrabold text-sm">Batal</button>
                <button onclick="savePreviewToDatabase()" class="flex-1 py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Simpan ke Database</button>
            </div>
        </div>
    </div>
</div>

<!-- Main -->
<main class="header-offset px-5 py-6">
    <div class="mb-8">
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-sky-400 text-sm"></i>
            <input type="text" id="qSearch" onkeyup="filterData()"
                placeholder="Cari nama, kamar, NIP, instansi..."
                class="w-full pl-11 pr-4 py-3.5 bg-sky-50 border-none rounded-xl text-sm outline-none transition-all focus:bg-white focus:ring-1 focus:ring-sky-200">
        </div>
    </div>

    <div class="flex items-center justify-between mb-5 px-1">
        <h2 class="text-[12px] font-bold text-sky-600">Daftar Data Terbaru</h2>
        <div class="h-px flex-grow mx-4 bg-sky-100"></div>
        <span id="dataCount" class="text-[11px] font-medium text-sky-500 whitespace-nowrap">0 Item</span>
    </div>

    <div id="mainContainer" class="space-y-4 pb-32"></div>
</main>

<!-- FAB Tambah -->
<?php if ($isAdmin): ?>
    <button onclick="openModalTambah()"
        class="fixed bottom-8 right-8 w-11 h-11 bg-sky-600 text-white rounded-full shadow-lg shadow-sky-100 flex items-center justify-center z-[40] active:scale-90 transition-all">
        <i class="fa-solid fa-plus text-lg"></i>
    </button>
<?php endif; ?>

<!-- Toast -->
<div id="toast"
    class="fixed bottom-10 left-1/2 -translate-x-1/2 bg-sky-900 text-white px-5 py-2.5 rounded-full text-[11px] font-bold opacity-0 transition-all z-[200] pointer-events-none shadow-lg">
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    const apiUrl = 'peserta_penginapan_api.php';
    const agendaMap = <?= json_encode($agendaMapJs, JSON_UNESCAPED_UNICODE) ?>;

    let localData = [];
    let importedPreviewData = [];

    const stokModal = document.getElementById('stokModal');
    const btnEditTrigger = document.getElementById('btnEditTrigger');
    const btnHapus = document.getElementById('btnHapus');
    const btnSubmit = document.getElementById('btnSubmit');
    const sheetTitle = document.getElementById('sheetTitle');

    /* ── Helpers ── */
    function safeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', 2200);
    }

    function normalizeText(str) {
        return String(str || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function findAgendaIdByName(namaKegiatan) {
        const q = normalizeText(namaKegiatan);
        if (!q) return '';
        const exact = agendaMap.find(a => normalizeText(a.judul) === q);
        if (exact) return exact.id;
        const partial = agendaMap.find(a => normalizeText(a.judul).includes(q) || q.includes(normalizeText(a.judul)));
        if (partial) return partial.id;
        return '';
    }

    function badgeClass(peran) {
        if (peran === 'Pengajar') return 'badge-indigo';
        if (peran === 'Panitia') return 'badge-amber';
        return 'badge-sky';
    }

    function statusBadge(status) {
        if (status === 'Check-in') return 'badge-emerald';
        if (status === 'Check-out') return 'badge-gray';
        return 'badge-rose';
    }

    /* ── Render ── */
    function render(data) {
        const rows = data || [];
        document.getElementById('dataCount').textContent = `${rows.length} Item`;
        const container = document.getElementById('mainContainer');

        if (!rows.length) {
            container.innerHTML = `<div class="py-20 text-center text-sky-300 text-xs font-medium">Data tidak ditemukan</div>`;
            return;
        }

        container.innerHTML = rows.map((d, index) => `
            <div class="data-card animate-card" style="animation-delay:${index * 0.05}s" onclick="openModalDetail(${Number(d.id)})">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex gap-1.5 flex-wrap">
                        <span class="badge ${badgeClass(d.peran)}">${safeHtml(d.peran || 'Peserta')}</span>
                        <span class="badge ${statusBadge(d.status_inap || 'Belum Check-in')}">${safeHtml(d.status_inap || 'Belum Check-in')}</span>
                        ${d.kondisi ? `<span class="badge bg-rose-50 text-rose-600 border border-rose-100">${safeHtml(d.kondisi)}</span>` : ''}
                    </div>
                    ${IS_ADMIN ? `
                    <div class="flex gap-3" onclick="event.stopPropagation()">
                        <button onclick="openModalDetail(${Number(d.id)}, true)" class="text-sky-400 hover:text-sky-900 transition-colors p-1">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </button>
                        <button onclick="deleteDirect(${Number(d.id)})" class="text-sky-200 hover:text-rose-500 transition-colors p-1">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>` : ''}
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 bg-sky-50 rounded-lg flex flex-col items-center justify-center shrink-0 border border-sky-100">
                        <span class="text-[7px] font-bold text-sky-400 uppercase leading-none mb-0.5">Room</span>
                        <span class="text-xs font-black text-sky-700">${safeHtml(d.kamar || '-')}</span>
                    </div>
                    <div class="min-w-0 flex-grow">
                        <h3 class="font-bold text-sky-900 truncate leading-tight">${safeHtml(d.nama || '-')}</h3>
                        <p class="text-[10px] text-sky-500 font-medium mt-0.5">${safeHtml(d.gedung || '-')} • Lt. ${safeHtml(d.lantai || '-')}</p>
                        <p class="text-[10px] font-bold text-sky-400 mt-1">${safeHtml(d.judul || 'Tanpa Kegiatan')}</p>
                        <p class="text-[10px] text-slate-400 mt-1">${safeHtml(d.instansi || '-')}</p>
                    </div>
                </div>
            </div>`).join('');
    }

    /* ── Load Data ── */
    let abortController = new AbortController();

    async function loadData() {
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
            localData = json.data || [];
            render(localData);
        } catch (e) {
            if (e.name !== 'AbortError') {
                console.error(e);
                showToast('Terjadi kesalahan saat memuat data');
            }
        }
    }

    function filterData() {
        const q = document.getElementById('qSearch').value.toLowerCase().trim();
        const filtered = localData.filter(d =>
            (d.nama || '').toLowerCase().includes(q) ||
            (d.kamar || '').toLowerCase().includes(q) ||
            (d.nip || '').toLowerCase().includes(q) ||
            (d.instansi || '').toLowerCase().includes(q) ||
            (d.judul || '').toLowerCase().includes(q) ||
            (d.gedung || '').toLowerCase().includes(q)
        );
        render(filtered);
    }

    /* ── Modal helpers ── */
    function toggleInputs(disabled) {
        ['f-agenda', 'f-nama', 'f-gedung', 'f-kamar', 'f-lantai', 'f-bed',
            'f-peran', 'f-status', 'f-jk', 'f-instansi', 'f-nip', 'f-nohp',
            'f-checkin', 'f-checkout', 'f-checkin-time', 'f-checkout-time',
            'f-kondisi', 'f-catatan'
        ].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.disabled = disabled;
            disabled ? el.classList.add('opacity-70') : el.classList.remove('opacity-70');
        });
    }

    function resetForm() {
        document.getElementById('penginapan-form').reset();
        document.getElementById('edit-id').value = '';
        document.getElementById('f-status').value = 'Belum Check-in';
    }

    function closeModal() {
        stokModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openModalTambah() {
        if (!IS_ADMIN) return;
        sheetTitle.innerText = 'Tambah Peserta Penginapan';
        resetForm();
        toggleInputs(false);
        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnHapus) btnHapus.classList.add('hidden');
        if (btnSubmit) {
            btnSubmit.classList.remove('hidden');
            btnSubmit.innerText = 'Simpan Data';
        }
        stokModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function openModalDetail(id, directEdit = false) {
        const item = localData.find(a => String(a.id) === String(id));
        if (!item) return;

        document.getElementById('edit-id').value = item.id || '';
        document.getElementById('f-agenda').value = item.agenda_id || '';
        document.getElementById('f-nama').value = item.nama || '';
        document.getElementById('f-gedung').value = item.gedung || '';
        document.getElementById('f-kamar').value = item.kamar || '';
        document.getElementById('f-lantai').value = item.lantai || '';
        document.getElementById('f-bed').value = item.bed || '';
        document.getElementById('f-peran').value = item.peran || 'Peserta';
        document.getElementById('f-status').value = item.status_inap || 'Belum Check-in';
        document.getElementById('f-jk').value = item.jenis_kelamin || '';
        document.getElementById('f-instansi').value = item.instansi || '';
        document.getElementById('f-nip').value = item.nip || '';
        document.getElementById('f-nohp').value = item.no_hp || '';
        document.getElementById('f-checkin').value = item.checkin_date || '';
        document.getElementById('f-checkout').value = item.checkout_date || '';
        document.getElementById('f-checkin-time').value = item.checkin_time || '';
        document.getElementById('f-checkout-time').value = item.checkout_time || '';
        document.getElementById('f-kondisi').value = item.kondisi || '';
        document.getElementById('f-catatan').value = item.catatan || '';

        if (directEdit && IS_ADMIN) {
            sheetTitle.innerText = 'Ubah Peserta Penginapan';
            toggleInputs(false);
            if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
            if (btnHapus) btnHapus.classList.remove('hidden');
            if (btnSubmit) {
                btnSubmit.classList.remove('hidden');
                btnSubmit.innerText = 'Simpan Perubahan';
            }
        } else {
            sheetTitle.innerText = 'Detail Peserta Penginapan';
            toggleInputs(true);
            if (IS_ADMIN && btnEditTrigger) btnEditTrigger.classList.remove('hidden');
            if (btnHapus) btnHapus.classList.add('hidden');
            if (btnSubmit) btnSubmit.classList.add('hidden');
        }

        stokModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function enableEditMode() {
        if (!IS_ADMIN) return;
        sheetTitle.innerText = 'Ubah Peserta Penginapan';
        toggleInputs(false);
        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnHapus) btnHapus.classList.remove('hidden');
        if (btnSubmit) {
            btnSubmit.classList.remove('hidden');
            btnSubmit.innerText = 'Simpan Perubahan';
        }
    }

    async function handleSave(e) {
        e.preventDefault();
        if (!IS_ADMIN) return;

        const id = document.getElementById('edit-id').value;
        const fd = new FormData();

        fd.append('agenda_id', document.getElementById('f-agenda').value);
        fd.append('nama', document.getElementById('f-nama').value.trim());
        fd.append('gedung', document.getElementById('f-gedung').value);
        fd.append('kamar', document.getElementById('f-kamar').value.trim().toUpperCase());
        fd.append('lantai', document.getElementById('f-lantai').value.trim());
        fd.append('bed', document.getElementById('f-bed').value.trim().toUpperCase());
        fd.append('peran', document.getElementById('f-peran').value);
        fd.append('status_inap', document.getElementById('f-status').value);
        fd.append('jenis_kelamin', document.getElementById('f-jk').value);
        fd.append('instansi', document.getElementById('f-instansi').value.trim());
        fd.append('nip', document.getElementById('f-nip').value.trim());
        fd.append('no_hp', document.getElementById('f-nohp').value.trim());
        fd.append('checkin_date', document.getElementById('f-checkin').value);
        fd.append('checkout_date', document.getElementById('f-checkout').value);
        fd.append('checkin_time', document.getElementById('f-checkin-time').value || '');
        fd.append('checkout_time', document.getElementById('f-checkout-time').value || '');
        fd.append('kondisi', document.getElementById('f-kondisi').value.trim());
        fd.append('catatan', document.getElementById('f-catatan').value.trim());

        if (!fd.get('nama') || !fd.get('gedung') || !fd.get('kamar')) {
            showToast('Nama, gedung, dan kamar wajib diisi');
            return;
        }
        if (id) fd.append('id', id);

        try {
            const res = await fetch(apiUrl + '?action=save', {
                method: 'POST',
                body: fd
            });
            const json = await res.json();
            showToast(json.message || 'Proses selesai');
            if (json.status) {
                closeModal();
                loadData();
            }
        } catch (err) {
            console.error(err);
            showToast('Gagal menyimpan data');
        }
    }

    async function handleDelete() {
        if (!IS_ADMIN || !confirm('Hapus data ini?')) return;
        const id = document.getElementById('edit-id').value;
        if (!id) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
            const res = await fetch(apiUrl + '?action=delete', {
                method: 'POST',
                body: fd
            });
            const json = await res.json();
            showToast(json.message || 'Data dihapus');
            if (json.status) {
                closeModal();
                loadData();
            }
        } catch (err) {
            console.error(err);
            showToast('Gagal menghapus data');
        }
    }

    async function deleteDirect(id) {
        if (!confirm('Hapus data ini?')) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
            const res = await fetch(apiUrl + '?action=delete', {
                method: 'POST',
                body: fd
            });
            const json = await res.json();
            showToast(json.message || 'Data dihapus');
            if (json.status) loadData();
        } catch (err) {
            console.error(err);
            showToast('Gagal menghapus data');
        }
    }

    /* ── Preview Import ── */
    function openPreviewModal() {
        document.getElementById('previewModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closePreviewModal() {
        document.getElementById('previewModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function updatePreviewField(index, field, value) {
        if (!importedPreviewData[index]) return;
        importedPreviewData[index][field] = value;
    }

    function updatePreviewAgenda(index, namaKegiatan) {
        if (!importedPreviewData[index]) return;
        importedPreviewData[index].nama_kegiatan = namaKegiatan;
        importedPreviewData[index].agenda_id = findAgendaIdByName(namaKegiatan) || '';
    }

    function hasMissingAgenda(row) {
        return !!(row.nama_kegiatan && !findAgendaIdByName(row.nama_kegiatan) && !row.agenda_id);
    }

    function renderPreviewTable() {
        const tableBody = document.getElementById('previewTableBody');
        const cardContainer = document.getElementById('previewCardContainer');
        document.getElementById('previewCount').textContent = importedPreviewData.length;

        if (!importedPreviewData.length) {
            tableBody.innerHTML = `<tr><td colspan="11" class="p-6 text-center text-slate-400">Tidak ada data preview</td></tr>`;
            cardContainer.innerHTML = `<div class="bg-white rounded-2xl border border-slate-200 p-6 text-center text-slate-400">Tidak ada data preview</div>`;
            return;
        }

        tableBody.innerHTML = importedPreviewData.map((row, index) => {
            const miss = hasMissingAgenda(row);
            return `
            <tr class="border-b border-slate-100 align-top ${miss ? 'bg-rose-50/40' : ''}">
                <td class="p-3"><input type="checkbox" class="preview-row-check preview-check" data-index="${index}"></td>
                <td class="p-3"><input type="text" class="preview-table-input ${miss ? 'preview-missing' : ''}" value="${safeHtml(row.nama_kegiatan||'')}" oninput="updatePreviewAgenda(${index}, this.value); renderPreviewTable()"></td>
                <td class="p-3"><input type="text" class="preview-table-input" value="${safeHtml(row.nama||'')}" oninput="updatePreviewField(${index}, 'nama', this.value)"></td>
                <td class="p-3">
                    <select class="preview-table-input" onchange="updatePreviewField(${index}, 'jenis_kelamin', this.value)">
                        <option value="" ${row.jenis_kelamin===''?'selected':''}>-</option>
                        <option value="L" ${row.jenis_kelamin==='L'?'selected':''}>L</option>
                        <option value="P" ${row.jenis_kelamin==='P'?'selected':''}>P</option>
                    </select>
                </td>
                <td class="p-3"><input type="text" class="preview-table-input" value="${safeHtml(row.nip||'')}" oninput="updatePreviewField(${index}, 'nip', this.value)"></td>
                <td class="p-3"><input type="text" class="preview-table-input" value="${safeHtml(row.instansi||'')}" oninput="updatePreviewField(${index}, 'instansi', this.value)"></td>
                <td class="p-3"><input type="text" class="preview-table-input" value="${safeHtml(row.gedung||'')}" oninput="updatePreviewField(${index}, 'gedung', this.value)"></td>
                <td class="p-3"><input type="text" class="preview-table-input" value="${safeHtml(row.lantai||'')}" oninput="updatePreviewField(${index}, 'lantai', this.value)"></td>
                <td class="p-3"><input type="text" class="preview-table-input" value="${safeHtml(row.kamar||'')}" oninput="updatePreviewField(${index}, 'kamar', this.value.toUpperCase())"></td>
                <td class="p-3">
                    <select class="preview-table-input" onchange="updatePreviewField(${index}, 'peran', this.value)">
                        <option value="Peserta"  ${row.peran==='Peserta'?'selected':''}>Peserta</option>
                        <option value="Pengajar" ${row.peran==='Pengajar'?'selected':''}>Pengajar</option>
                        <option value="Panitia"  ${row.peran==='Panitia'?'selected':''}>Panitia</option>
                    </select>
                </td>
                <td class="p-3"><input type="text" class="preview-table-input ${miss ? 'preview-missing' : ''}" value="${safeHtml(row.agenda_id||'')}" oninput="updatePreviewField(${index}, 'agenda_id', this.value)"></td>
            </tr>`;
        }).join('');

        cardContainer.innerHTML = importedPreviewData.map((row, index) => {
            const miss = hasMissingAgenda(row);
            return `
            <div class="preview-card ${miss ? 'border-rose-300 bg-rose-50/40' : ''}">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" class="preview-row-check preview-check" data-index="${index}">
                        <div>
                            <div class="text-sm font-extrabold text-slate-800">Data #${index + 1}</div>
                            <div class="text-[11px] text-slate-400">${safeHtml(row.peran || 'Peserta')}</div>
                        </div>
                    </div>
                    <span class="badge ${row.peran==='Pengajar'?'badge-indigo':row.peran==='Panitia'?'badge-amber':'badge-sky'}">${safeHtml(row.peran||'Peserta')}</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="text-[11px] font-bold text-slate-600">Nama Kegiatan</label>
                        <input type="text" class="preview-input ${miss ? 'preview-missing' : ''}" value="${safeHtml(row.nama_kegiatan||'')}" oninput="updatePreviewAgenda(${index}, this.value); renderPreviewTable()">
                        ${miss ? `<p class="text-[11px] text-rose-600 mt-1 font-semibold">Agenda belum cocok. Periksa nama kegiatan atau isi Agenda ID.</p>` : ''}
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-600">Nama</label>
                        <input type="text" class="preview-input" value="${safeHtml(row.nama||'')}" oninput="updatePreviewField(${index}, 'nama', this.value)">
                    </div>
                    <div class="preview-grid-2">
                        <div>
                            <label class="text-[11px] font-bold text-slate-600">Jenis Kelamin</label>
                            <select class="preview-input" onchange="updatePreviewField(${index}, 'jenis_kelamin', this.value)">
                                <option value="" ${row.jenis_kelamin===''?'selected':''}>-- Pilih --</option>
                                <option value="L" ${row.jenis_kelamin==='L'?'selected':''}>L</option>
                                <option value="P" ${row.jenis_kelamin==='P'?'selected':''}>P</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-slate-600">NIP</label>
                            <input type="text" class="preview-input" value="${safeHtml(row.nip||'')}" oninput="updatePreviewField(${index}, 'nip', this.value)">
                        </div>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-600">Instansi</label>
                        <input type="text" class="preview-input" value="${safeHtml(row.instansi||'')}" oninput="updatePreviewField(${index}, 'instansi', this.value)">
                    </div>
                    <div class="preview-grid-3">
                        <div>
                            <label class="text-[11px] font-bold text-slate-600">Gedung</label>
                            <input type="text" class="preview-input" value="${safeHtml(row.gedung||'')}" oninput="updatePreviewField(${index}, 'gedung', this.value)">
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-slate-600">Lantai</label>
                            <input type="text" class="preview-input" value="${safeHtml(row.lantai||'')}" oninput="updatePreviewField(${index}, 'lantai', this.value)">
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-slate-600">Kamar</label>
                            <input type="text" class="preview-input" value="${safeHtml(row.kamar||'')}" oninput="updatePreviewField(${index}, 'kamar', this.value.toUpperCase())">
                        </div>
                    </div>
                    <div class="preview-grid-2">
                        <div>
                            <label class="text-[11px] font-bold text-slate-600">Peran</label>
                            <select class="preview-input" onchange="updatePreviewField(${index}, 'peran', this.value)">
                                <option value="Peserta"  ${row.peran==='Peserta'?'selected':''}>Peserta</option>
                                <option value="Pengajar" ${row.peran==='Pengajar'?'selected':''}>Pengajar</option>
                                <option value="Panitia"  ${row.peran==='Panitia'?'selected':''}>Panitia</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-slate-600">Agenda ID</label>
                            <input type="text" class="preview-input ${miss ? 'preview-missing' : ''}" value="${safeHtml(row.agenda_id||'')}" oninput="updatePreviewField(${index}, 'agenda_id', this.value)">
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function handleMasterPreviewCheck(el) {
        document.querySelectorAll('.preview-row-check').forEach(cb => cb.checked = el.checked);
    }

    function toggleSelectAllPreview(state) {
        document.querySelectorAll('.preview-row-check').forEach(cb => cb.checked = state);
        const master = document.getElementById('checkAllPreview');
        if (master) master.checked = state;
    }

    function deleteCheckedPreviewRows() {
        const checked = Array.from(document.querySelectorAll('.preview-row-check:checked')).map(cb => Number(cb.dataset.index));
        if (!checked.length) {
            showToast('Pilih data yang ingin dihapus');
            return;
        }
        importedPreviewData = importedPreviewData.filter((_, i) => !checked.includes(i));
        renderPreviewTable();
        showToast('Data terpilih berhasil dihapus');
    }

    async function savePreviewToDatabase() {
        if (!importedPreviewData.length) {
            showToast('Tidak ada data untuk disimpan');
            return;
        }

        let successCount = 0,
            failedCount = 0;
        const errors = [];

        for (const row of importedPreviewData) {
            const finalAgendaId = row.agenda_id || findAgendaIdByName(row.nama_kegiatan || '');
            if (!row.nama || !row.gedung || !row.kamar) {
                failedCount++;
                errors.push((row.nama || 'Tanpa nama') + ': nama/gedung/kamar wajib diisi');
                continue;
            }
            const fd = new FormData();
            fd.append('agenda_id', finalAgendaId || '');
            fd.append('nama', row.nama || '');
            fd.append('jenis_kelamin', row.jenis_kelamin || '');
            fd.append('nip', row.nip || '');
            fd.append('instansi', row.instansi || '');
            fd.append('gedung', row.gedung || '');
            fd.append('lantai', row.lantai || '');
            fd.append('kamar', String(row.kamar || '').toUpperCase());
            fd.append('bed', row.bed || '');
            fd.append('peran', row.peran || 'Peserta');
            fd.append('status_inap', row.status_inap || 'Belum Check-in');
            fd.append('no_hp', row.no_hp || '');
            fd.append('checkin_date', row.checkin_date || '');
            fd.append('checkin_time', row.checkin_time || '');
            fd.append('checkout_date', row.checkout_date || '');
            fd.append('checkout_time', row.checkout_time || '');
            fd.append('kondisi', row.kondisi || '');
            fd.append('catatan', row.catatan || '');
            try {
                const res = await fetch(apiUrl + '?action=save', {
                    method: 'POST',
                    body: fd
                });
                const result = await res.json();
                if (result.status) {
                    successCount++;
                } else {
                    failedCount++;
                    errors.push((row.nama || 'Tanpa nama') + ': ' + (result.message || 'Gagal simpan'));
                }
            } catch (err) {
                failedCount++;
                errors.push((row.nama || 'Tanpa nama') + ': error jaringan');
            }
        }

        showToast(`${successCount} data disimpan${failedCount ? ', gagal ' + failedCount : ''}`);
        if (errors.length) console.log('Import errors:', errors);
        if (successCount > 0) {
            importedPreviewData = [];
            renderPreviewTable();
            closePreviewModal();
            await loadData();
        }
    }

    /* ── Import Excel ── */
    async function handleExcel(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = async () => {
            try {
                const workbook = XLSX.read(new Uint8Array(reader.result), {
                    type: 'array'
                });
                const validSheetPatterns = [/check\s*in/i, /peserta/i, /pengajar/i, /panitia/i];
                const targetSheets = workbook.SheetNames.filter(name => validSheetPatterns.some(rx => rx.test(String(name || '').toLowerCase())));
                const sheetsToProcess = targetSheets.length ? targetSheets : [workbook.SheetNames[0]];

                let skippedCount = 0;
                importedPreviewData = [];

                function cleanText(v) {
                    return String(v == null ? '' : v).trim();
                }

                function normalizeJK(jk) {
                    const val = cleanText(jk).toUpperCase();
                    if (val === 'L' || val === 'LAKI-LAKI') return 'L';
                    if (val === 'P' || val === 'PEREMPUAN') return 'P';
                    return '';
                }

                function normalizePeranFromSheet(name) {
                    const n = cleanText(name).toLowerCase();
                    if (n.indexOf('pengajar') !== -1) return 'Pengajar';
                    if (n.indexOf('panitia') !== -1) return 'Panitia';
                    return 'Peserta';
                }

                function normalizeGedung(g) {
                    const val = cleanText(g).replace(/\s+/g, ' ').toUpperCase();
                    const map = {
                        'CANDRA 1': 'Candra 1',
                        'CANDRA 2': 'Candra 2',
                        'CAKRA 1': 'Cakra 1',
                        'CAKRA 2': 'Cakra 2',
                        'CAKRA 3': 'Cakra 3',
                        'CAKRA 4': 'Cakra 4',
                        'CAKRA 5': 'Cakra 5',
                        'SARI': 'Sari',
                        'TIRTA': 'Tirta',
                        'KARTIKA': 'Kartika'
                    };
                    return map[val] || g;
                }

                function getCell(row, keys) {
                    for (const key of keys) {
                        if (Object.prototype.hasOwnProperty.call(row, key)) return row[key];
                    }
                    return '';
                }

                function parseRoom(roomText) {
                    const raw = cleanText(roomText);
                    if (!raw) return {
                        gedung: '',
                        lantai: '',
                        kamar: ''
                    };
                    const m1 = raw.match(/^(.+?)\s+LT\s+([A-Z0-9\-\/]+)\s+NO\.?\s*(.+)$/i);
                    if (m1) return {
                        gedung: normalizeGedung(cleanText(m1[1])),
                        lantai: cleanText(m1[2]),
                        kamar: cleanText(m1[3]).toUpperCase()
                    };
                    const m2 = raw.match(/^(.+?)\s+LT\s+([A-Z0-9\-\/]+)$/i);
                    if (m2) return {
                        gedung: normalizeGedung(cleanText(m2[1])),
                        lantai: cleanText(m2[2]),
                        kamar: ''
                    };
                    return {
                        gedung: '',
                        lantai: '',
                        kamar: raw.toUpperCase()
                    };
                }

                const selectedAgendaId = (document.getElementById('f-agenda') && document.getElementById('f-agenda').value) ? document.getElementById('f-agenda').value : '';

                for (const sheetName of sheetsToProcess) {
                    const rows = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {
                        defval: ''
                    });
                    let lastRoomText = '';
                    const defaultPeran = normalizePeranFromSheet(sheetName);

                    for (const row of rows) {
                        const nama = cleanText(getCell(row, ['NAMA', 'Nama', 'nama']));
                        const jk = normalizeJK(getCell(row, ['JK', 'Jk', 'jk']));
                        const nip = cleanText(getCell(row, ['NIP', 'Nip', 'nip']));
                        const instansi = cleanText(getCell(row, ['UNIT KERJA', 'Unit Kerja', 'unit kerja', 'UNIT_KERJA', 'Instansi', 'instansi']));
                        const namaKegiatan = cleanText(getCell(row, ['NAMA_KEGIATAN', 'Nama Kegiatan', 'KEGIATAN', 'Kegiatan', 'nama_kegiatan', 'kegiatan']));
                        let roomText = cleanText(getCell(row, ['KAMAR', 'Kamar', 'kamar']));

                        if (!nama) {
                            skippedCount++;
                            continue;
                        }
                        const upperNama = nama.toUpperCase();
                        if (upperNama === 'NAMA' || upperNama === 'NO' || upperNama === 'TTD') {
                            skippedCount++;
                            continue;
                        }
                        if (!roomText && lastRoomText) roomText = lastRoomText;
                        if (roomText) lastRoomText = roomText;

                        const {
                            gedung,
                            lantai,
                            kamar
                        } = parseRoom(roomText);
                        if (!nama || !gedung || !kamar) continue;

                        importedPreviewData.push({
                            agenda_id: findAgendaIdByName(namaKegiatan) || selectedAgendaId,
                            nama_kegiatan: namaKegiatan,
                            nama,
                            jenis_kelamin: jk,
                            nip,
                            instansi,
                            gedung,
                            lantai,
                            kamar,
                            bed: '',
                            peran: defaultPeran,
                            status_inap: 'Belum Check-in',
                            no_hp: '',
                            checkin_date: '',
                            checkin_time: '',
                            checkout_date: '',
                            checkout_time: '',
                            kondisi: '',
                            catatan: 'Import dari Excel: ' + sheetName
                        });
                    }
                }

                renderPreviewTable();
                openPreviewModal();
                showToast(`${importedPreviewData.length} data siap dicek` + (skippedCount ? `, dilewati ${skippedCount}` : ''));
                event.target.value = '';
            } catch (err) {
                console.error(err);
                showToast('File Excel gagal diproses');
                event.target.value = '';
            }
        };
        reader.readAsArrayBuffer(file);
    }

    /* ── Abort on navigation ── */
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) abortController.abort();
        else loadData();
    });
    window.addEventListener('beforeunload', () => abortController.abort());
    window.addEventListener('pagehide', () => abortController.abort());

    window.onload = loadData;
</script>