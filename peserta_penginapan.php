<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Input Peserta & Pengajar";
include 'header.php';
include 'config.php';

$db = $conn ?? $koneksi ?? null;
if (!($db instanceof mysqli)) die('Koneksi database tidak ditemukan.');
$db->set_charset('utf8mb4');

/* FIX AKSES:
   sebelumnya hanya role === 'admin'
   sekarang lebih fleksibel supaya edit/tambah manual tidak macet
*/
$role = strtolower(trim($_SESSION['user']['role'] ?? ''));
$isAdmin = in_array($role, ['admin', 'administrator', 'superadmin', 'petugas', 'operator'], true);

$agendaList = [];
$q = $db->query("SELECT id,judul,start_date,end_date FROM agenda_kegiatan ORDER BY start_date DESC,id DESC");
if ($q) while ($r = $q->fetch_assoc()) $agendaList[] = $r;

$agendaMapSafe = array_map(function ($a) {
    return ['id' => (string)$a['id'], 'judul' => $a['judul']];
}, $agendaList);
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    *,
    *::before,
    *::after {
        box-sizing: border-box;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f1f5f9;
        color: #0f172a;
        -webkit-tap-highlight-color: transparent;
        overflow-x: hidden;
    }

    html,
    body {
        overflow-x: hidden;
        height: auto !important;
        overflow-y: auto !important;
    }

    .sticky-header {
        position: fixed;
        left: 0;
        right: 0;
        z-index: 999;
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        padding-bottom: 2px;
    }

    .header-offset {
        padding-top: 145px;
    }

    .badge {
        display: inline-flex !important;
        align-items: center !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        line-height: 1 !important;
        padding: 3px 8px !important;
        border-radius: 999px !important;
        white-space: nowrap !important;
        position: static !important;
        float: none !important;
    }

    .badge-sky {
        background: #f0f9ff !important;
        color: #0369a1 !important;
        border: 1px solid #bae6fd !important;
    }

    .badge-indigo {
        background: #eef2ff !important;
        color: #4338ca !important;
        border: 1px solid #c7d2fe !important;
    }

    .badge-amber {
        background: #fffbeb !important;
        color: #b45309 !important;
        border: 1px solid #fde68a !important;
    }

    .badge-rose {
        background: #fff1f2 !important;
        color: #be123c !important;
        border: 1px solid #fecdd3 !important;
    }

    .badge-emerald {
        background: #ecfdf5 !important;
        color: #047857 !important;
        border: 1px solid #a7f3d0 !important;
    }

    .badge-gray {
        background: #f8fafc !important;
        color: #475569 !important;
        border: 1px solid #cbd5e1 !important;
    }

    .tbl-wrap {
        overflow-x: auto;
    }

    .tbl-wrap::-webkit-scrollbar {
        height: 4px;
    }

    .tbl-wrap::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 99px;
    }

    table.dt {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        table-layout: auto;
    }

    table.dt th {
        background: #f8fafc;
        padding: 9px 10px;
        text-align: left;
        font-weight: 700;
        color: #64748b;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }

    table.dt td {
        padding: 9px 10px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    table.dt tr:hover td {
        background: #fafafa;
    }

    table.dt tr.sel td {
        background: #eff6ff;
    }

    table.dt td.tc {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 180px;
    }

    .mf {
        width: 100%;
        margin-top: 3px;
        padding: 10px 14px;
        border-radius: 14px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        outline: none;
        transition: border-color .15s, background .15s;
        -webkit-appearance: none;
        appearance: none;
        font-size: 13px;
        font-family: inherit;
    }

    .mf:focus {
        border-color: #7dd3fc;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, .1);
        background: #fff;
    }

    .mf:disabled {
        opacity: .5;
        background: #f1f5f9;
    }

    .ms::-webkit-scrollbar {
        width: 4px;
    }

    .ms::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 99px;
    }

    .pi {
        width: 100%;
        padding: 6px 9px;
        border-radius: 9px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        outline: none;
        font-size: 12px;
        font-family: inherit;
    }

    .pi:focus {
        border-color: #7dd3fc;
        background: #fff;
    }

    .pi-miss {
        border-color: #fca5a5 !important;
        background: #fff1f2 !important;
    }

    .ps::-webkit-scrollbar {
        width: 4px;
        height: 4px;
    }

    .ps::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 99px;
    }

    .warn-box {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 14px;
        padding: 14px;
    }

    @media(min-width:480px) {
        .pg2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .pg3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
        }
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(6px)
        }

        to {
            opacity: 1;
            transform: translateY(0)
        }
    }

    .au {
        animation: fadeUp .25s ease-out forwards;
    }
</style>

<header class="sticky-header px-5 py-4">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <button onclick="window.history.back()"
                class="w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <div class="min-w-0">
                <h1 class="text-lg font-extrabold text-sky-600 leading-tight truncate">Input Peserta &amp; Pengajar </h1>
                <p class="text-xs text-slate-400 font-medium mt-0.5">Tambah data peserta/pengajar</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <input type="file" id="excelInput" accept=".xlsx,.xls" class="hidden" onchange="handleExcel(event)">
            <button id="btnDeleteSelected" onclick="deleteSelected()" title="Hapus terpilih"
                class="hidden w-10 h-10 flex items-center justify-center text-rose-500 hover:bg-rose-50 rounded-full transition">
                <i class="fa-solid fa-trash-can"></i>
            </button>
            <button onclick="document.getElementById('excelInput').click()" title="Import Excel"
                class="w-10 h-10 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition">
                <i class="fa-solid fa-file-import"></i>
            </button>
        </div>
    </div>
</header>

<div id="formModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-3">
        <div class="w-full max-w-md bg-white rounded-3xl shadow-xl max-h-[93vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
                <div>
                    <p id="modalTitle" class="text-sm font-extrabold text-gray-800">Tambah Peserta</p>
                    <p class="text-[11px] text-gray-400">Pusdiklat Mahkamah Agung</p>
                </div>
                <div class="flex gap-2">
                    <?php if ($isAdmin): ?>
                        <button id="btnEditTrigger" onclick="enableEditMode()"
                            class="hidden w-9 h-9 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center">
                            <i class="fa-solid fa-pen-to-square text-sm"></i>
                        </button>
                    <?php endif; ?>
                    <button onclick="closeModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="overflow-y-auto px-5 py-4 flex-1 ms">
                <form id="fForm" onsubmit="handleSave(event)" class="space-y-3">
                    <input type="hidden" id="fId">
                    <input type="hidden" id="fForceKamar" value="0">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Kegiatan</label>
                        <select id="fAgenda" class="mf">
                            <option value="">-- Pilih kegiatan --</option>
                            <?php foreach ($agendaList as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['judul']) ?> | <?= $a['start_date'] ?> s/d <?= $a['end_date'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Nama <span class="text-rose-500">*</span></label>
                        <input id="fNama" type="text" placeholder="Wajib diisi" class="mf">
                    </div>
                    <div class="pg2">
                        <div>
                            <label class="text-xs font-bold text-gray-600">Gedung</label>
                            <select id="fGedung" class="mf" onchange="resetForceKamar()">
                                <option value="">-- Pilih --</option>
                                <?php foreach (['Candra 1', 'Candra 2', 'Cakra 1', 'Cakra 2', 'Cakra 3', 'Cakra 4', 'Cakra 5', 'Sari', 'Tirta', 'Kartika'] as $g): ?>
                                    <option><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Kamar</label>
                            <input id="fKamar" type="text" placeholder="Cth: 201" class="mf" onchange="resetForceKamar()">
                        </div>
                    </div>
                    <div class="pg2">
                        <div><label class="text-xs font-bold text-gray-600">Lantai</label><input id="fLantai" type="text" class="mf"></div>
                        <div><label class="text-xs font-bold text-gray-600">Bed</label><input id="fBed" type="text" class="mf"></div>
                    </div>
                    <div class="pg2">
                        <div>
                            <label class="text-xs font-bold text-gray-600">Peran</label>
                            <select id="fPeran" class="mf">
                                <option>Peserta</option>
                                <option>Pengajar</option>
                                <option>Panitia</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Status Inap</label>
                            <select id="fStatus" class="mf">
                                <option value="Belum Check-in">Belum Check-in</option>
                                <option value="Check-in">Check-in</option>
                                <option value="Check-out">Check-out</option>
                            </select>
                        </div>
                    </div>
                    <div class="pg2">
                        <div>
                            <label class="text-xs font-bold text-gray-600">JK</label>
                            <select id="fJK" class="mf">
                                <option value="">--</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div><label class="text-xs font-bold text-gray-600">NIP</label><input id="fNip" type="text" class="mf"></div>
                    </div>
                    <div class="pg2">
                        <div><label class="text-xs font-bold text-gray-600">Instansi</label><input id="fInstansi" type="text" class="mf"></div>
                        <div><label class="text-xs font-bold text-gray-600">No HP</label><input id="fNohp" type="text" class="mf"></div>
                    </div>
                    <div class="pg2">
                        <div><label class="text-xs font-bold text-gray-600">Tgl Check-in</label><input id="fCI" type="date" class="mf" onchange="resetForceKamar()"></div>
                        <div><label class="text-xs font-bold text-gray-600">Jam Check-in</label><input id="fCITime" type="time" step="1" class="mf"></div>
                    </div>
                    <div class="pg2">
                        <div><label class="text-xs font-bold text-gray-600">Tgl Check-out</label><input id="fCO" type="date" class="mf" onchange="resetForceKamar()"></div>
                        <div><label class="text-xs font-bold text-gray-600">Jam Check-out</label><input id="fCOTime" type="time" step="1" class="mf"></div>
                    </div>
                    <div><label class="text-xs font-bold text-gray-600">Kondisi</label><input id="fKondisi" type="text" placeholder="Hamil, Diet khusus..." class="mf"></div>
                    <div><label class="text-xs font-bold text-gray-600">Catatan</label><textarea id="fCatatan" class="mf min-h-[70px]"></textarea></div>
                    <div id="kamarWarning" class="hidden warn-box">
                        <p class="text-xs font-bold text-amber-800 mb-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Kamar sudah digunakan oleh:</p>
                        <div id="kamarPenghuni" class="text-xs text-amber-700 space-y-1 mb-3"></div>
                        <label class="flex items-center gap-2 text-xs font-bold text-amber-800 cursor-pointer">
                            <input type="checkbox" id="cbForceKamar" onchange="document.getElementById('fForceKamar').value=this.checked?1:0">
                            Saya mengerti, tetap simpan ke kamar ini
                        </label>
                    </div>
                </form>
            </div>
            <?php if ($isAdmin): ?>
                <div class="flex-shrink-0 border-t border-gray-100 px-5 py-3 space-y-2">
                    <button id="btnSubmit" type="submit" form="fForm"
                        class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Simpan Data</button>
                    <button id="btnHapus" onclick="handleDelete()" type="button"
                        class="hidden w-full py-3 rounded-2xl bg-red-50 text-red-500 font-extrabold text-sm">
                        <i class="fa-solid fa-trash-can mr-1"></i>Hapus Data
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="previewModal" class="fixed inset-0 bg-black/50 z-[1000] hidden">
    <div class="absolute inset-0" onclick="closePreview()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-3">
        <div class="w-full max-w-6xl bg-white rounded-3xl shadow-xl max-h-[93vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
                <div>
                    <p class="text-sm font-extrabold text-gray-800">Preview Import Excel</p>
                    <p class="text-[11px] text-gray-400">Cek lalu simpan ke database</p>
                </div>
                <button onclick="closePreview()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap gap-2 flex-shrink-0">
                <button onclick="selAllPrev(true)" class="px-3 py-1.5 rounded-xl bg-sky-50 text-sky-700 text-xs font-bold">Pilih Semua</button>
                <button onclick="selAllPrev(false)" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 text-xs font-bold">Batal</button>
                <button onclick="delCheckedPrev()" class="px-3 py-1.5 rounded-xl bg-rose-50 text-rose-600 text-xs font-bold">Hapus Dipilih</button>
                <span class="ml-auto text-xs font-bold text-slate-500 self-center"><span id="prevCount">0</span> data</span>
            </div>
            <div class="hidden md:block flex-1 overflow-auto ps">
                <table class="w-full text-xs min-w-[1000px]">
                    <thead class="sticky top-0 bg-white border-b border-gray-100 z-10">
                        <tr class="text-slate-500 font-bold">
                            <th class="p-2 w-8"><input type="checkbox" id="prevCheckAll" onchange="prevMasterCheck(this)"></th>
                            <th class="p-2 text-left">Kegiatan</th>
                            <th class="p-2 text-left">Nama</th>
                            <th class="p-2 text-left">JK</th>
                            <th class="p-2 text-left">NIP</th>
                            <th class="p-2 text-left">Instansi</th>
                            <th class="p-2 text-left">Gedung</th>
                            <th class="p-2 text-left">Lt</th>
                            <th class="p-2 text-left">Kamar</th>
                            <th class="p-2 text-left">Peran</th>
                            <th class="p-2 text-left">Agenda ID</th>
                        </tr>
                    </thead>
                    <tbody id="prevTableBody"></tbody>
                </table>
            </div>
            <div class="block md:hidden flex-1 overflow-y-auto ps bg-slate-50 p-3">
                <div id="prevCards" class="space-y-2"></div>
            </div>
            <div class="px-4 py-3 border-t border-gray-100 flex gap-3 flex-shrink-0">
                <button onclick="closePreview()" class="flex-1 py-3 rounded-2xl bg-slate-100 text-slate-600 font-extrabold text-sm">Batal</button>
                <button onclick="savePrevToDB()" id="btnBatch" class="flex-1 py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Simpan ke Database</button>
            </div>
        </div>
    </div>
</div>

<main class="header-offset px-4 py-5">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-md px-4 py-4 mb-4">
        <div class="relative mb-2">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-sky-400 text-xs pointer-events-none"></i>
            <input type="text" id="qSearch" onkeyup="filterData()"
                placeholder="Cari nama, kamar, NIP, instansi, kegiatan..."
                class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:bg-white focus:ring-2 focus:ring-sky-100 focus:border-sky-300 transition">
        </div>
        <div class="flex gap-2">
            <select id="filterStatus" onchange="filterData()"
                class="flex-1 min-w-0 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-gray-600 outline-none focus:ring-2 focus:ring-sky-100">
                <option value="">Semua Status</option>
                <option value="Belum Check-in">Belum CI</option>
                <option value="Check-in">Check-in</option>
                <option value="Check-out">Check-out</option>
            </select>
        </div>
    </div>

    <div class="flex items-center justify-between mb-2 px-1">
        <span id="dataCount" class="text-[11px] font-semibold text-sky-500">0 data</span>
        <div id="selectionInfo" class="hidden text-[11px] font-bold text-sky-700">
            <span id="selCount">0</span> terpilih
        </div>
    </div>

    <div class="hidden md:block bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-4">
        <div class="tbl-wrap">
            <table class="dt">
                <thead>
                    <tr>
                        <th style="width:32px"><input type="checkbox" id="checkAll" onchange="handleCheckAll(this)"></th>
                        <th style="min-width:140px">Nama</th>
                        <th style="width:90px">Peran</th>
                        <th style="min-width:110px">Instansi</th>
                        <th style="width:80px">Gedung</th>
                        <th style="width:60px">Kamar</th>
                        <th style="width:120px">Status</th>
                        <th style="width:85px">CI</th>
                        <th style="width:85px">CO</th>
                        <th style="min-width:130px">Kegiatan</th>
                        <?php if ($isAdmin): ?><th style="width:64px">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td colspan="11" class="text-center py-12 text-slate-300 text-sm">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="block md:hidden space-y-2 pb-28" id="mobileCards">
        <div class="py-16 text-center text-slate-300 text-sm">Memuat data...</div>
    </div>
</main>

<?php if ($isAdmin): ?>
    <button onclick="openModalTambah()"
        class="fixed bottom-6 right-6 w-12 h-12 bg-sky-600 text-white rounded-full shadow-xl shadow-sky-200 flex items-center justify-center z-40 active:scale-90 hover:bg-sky-700 transition-all">
        <i class="fa-solid fa-plus text-xl"></i>
    </button>
<?php endif; ?>

<div id="toast" class="fixed bottom-8 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-5 py-2.5 rounded-full text-[11px] font-bold opacity-0 transition-all z-[300] pointer-events-none shadow-lg whitespace-nowrap"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    const apiUrl = 'peserta_penginapan_api.php';
    const agendaMap = <?= json_encode($agendaMapSafe, JSON_UNESCAPED_UNICODE) ?>;

    let localData = [],
        previewData = [],
        selectedIds = new Set();
    let abortCtrl = new AbortController();

    const $ = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const norm = s => String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();
    const dt = v => v ? v.split('-').reverse().join('/') : '−';

    function showToast(msg, dur = 2500) {
        const t = $('toast');
        t.textContent = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', dur);
    }

    function findAgendaId(name) {
        if (!name) return '';
        const q = norm(name);
        return (agendaMap.find(a => norm(a.judul) === q) ?? agendaMap.find(a => norm(a.judul).includes(q) || q.includes(norm(a.judul))))?.id ?? '';
    }

    const bCls = p => p === 'Pengajar' ? 'badge-indigo' : p === 'Panitia' ? 'badge-amber' : 'badge-sky';
    const sCls = s => s === 'Check-in' ? 'badge-emerald' : s === 'Check-out' ? 'badge-gray' : 'badge-rose';

    async function loadData() {
        abortCtrl.abort();
        abortCtrl = new AbortController();
        try {
            const r = await fetch(apiUrl + '?action=list', {
                signal: abortCtrl.signal
            });
            const j = await r.json();
            if (!j.status) {
                showToast(j.message || 'Gagal');
                return;
            }
            localData = (j.data || []).sort((a, b) => (+b.id) - (+a.id));
            renderAll(localData);
        } catch (e) {
            if (e.name !== 'AbortError') showToast('Kesalahan koneksi');
        }
    }

    function filterData() {
        const q = $('qSearch').value.toLowerCase().trim();
        const st = $('filterStatus').value;
        renderAll(localData.filter(d => {
            const mq = !q || [d.nama, d.kamar, d.nip, d.instansi, d.judul, d.gedung].some(v => String(v || '').toLowerCase().includes(q));
            const ms = !st || ((d.status_inap || '') === st);
            return mq && ms;
        }));
    }

    function renderAll(data) {
        $('dataCount').textContent = `${data.length} data`;
        renderTable(data);
        renderCards(data);
        updateSelectionBar();
    }

    function renderTable(data) {
        const colspan = IS_ADMIN ? 11 : 10;
        if (!data.length) {
            $('tableBody').innerHTML = `<tr><td colspan="${colspan}" class="text-center py-12 text-slate-300 text-sm">Data tidak ditemukan</td></tr>`;
            return;
        }
        $('tableBody').innerHTML = data.map(d => {
            const sel = selectedIds.has(String(d.id));
            return `<tr class="${sel?'sel':''} cursor-pointer" onclick="${IS_ADMIN?`openModalDetail(${+d.id})`:'void(0)'}">
  <td onclick="event.stopPropagation()">
    <input type="checkbox" class="row-check" data-id="${d.id}" ${sel?'checked':''} onchange="toggleSelect('${d.id}',this.checked)">
  </td>
  <td class="tc" style="max-width:160px" title="${esc(d.nama||'')}">${esc(d.nama||'−')}</td>
  <td><span class="badge ${bCls(d.peran)}">${esc(d.peran||'Peserta')}</span></td>
  <td class="tc text-slate-500" style="max-width:130px" title="${esc(d.instansi||'')}">${esc(d.instansi||'−')}</td>
  <td class="text-slate-600">${esc(d.gedung||'−')}</td>
  <td class="font-bold text-sky-700">${esc(d.kamar||'−')}</td>
  <td><span class="badge ${sCls(d.status_inap)}">${esc(d.status_inap||'Belum Check-in')}</span></td>
  <td class="text-slate-400 text-[11px]">${dt(d.checkin_date)}</td>
  <td class="text-slate-400 text-[11px]">${dt(d.checkout_date)}</td>
  <td class="tc text-slate-400 text-[11px]" style="max-width:150px" title="${esc(d.judul||'')}">${esc(d.judul||'−')}</td>
  ${IS_ADMIN?`<td onclick="event.stopPropagation()" class="whitespace-nowrap">
    <button onclick="openModalDetail(${+d.id},true)" class="text-sky-400 hover:text-sky-700 p-1"><i class="fa-solid fa-pen-to-square text-xs"></i></button>
    <button onclick="deleteDirect(${+d.id})" class="text-rose-300 hover:text-rose-500 p-1"><i class="fa-solid fa-trash-can text-xs"></i></button>
  </td>`:''}
</tr>`;
        }).join('');
    }

    function bStyle(p) {
        if (p === 'Pengajar') return 'background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe';
        if (p === 'Panitia') return 'background:#fffbeb;color:#b45309;border:1px solid #fde68a';
        return 'background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd';
    }

    function sStyle(s) {
        if (s === 'Check-in') return 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0';
        if (s === 'Check-out') return 'background:#f8fafc;color:#475569;border:1px solid #cbd5e1';
        return 'background:#fff1f2;color:#be123c;border:1px solid #fecdd3';
    }
    const badgeStyle = 'display:inline-flex;align-items:center;font-size:10px;font-weight:700;line-height:1;padding:3px 8px;border-radius:999px;white-space:nowrap;font-family:inherit';

    function renderCards(data) {
        const wrap = $('mobileCards');
        if (!data.length) {
            wrap.innerHTML = `<div style="padding:64px 0;text-align:center;color:#cbd5e1;font-size:14px">Data tidak ditemukan</div>`;
            return;
        }
        wrap.innerHTML = data.map((d, i) => {
            const sel = selectedIds.has(String(d.id));
            const cardBg = sel ? '#f0f9ff' : '#ffffff';
            const cardBorder = sel ? '#93c5fd' : '#e0f2fe';
            return `
<div id="mc-${d.id}"
  style="background:${cardBg};border-radius:16px;border:1px solid ${cardBorder};
         box-shadow:0 1px 4px rgba(2,132,199,.06);
         margin-bottom:8px;overflow:hidden;
         animation:fadeUp .25s ease-out ${i*.02}s both;
         transition:border-color .15s,background .15s;
         font-family:'Plus Jakarta Sans',sans-serif">

  <div style="display:flex;align-items:center;gap:8px;padding:10px 12px 6px">
    <input type="checkbox" class="card-check" data-id="${d.id}" ${sel?'checked':''}
      onclick="event.stopPropagation()"
      onchange="toggleSelect('${d.id}',this.checked);toggleCardStyle('${d.id}',this.checked)"
      style="width:16px;height:16px;flex-shrink:0;accent-color:#0ea5e9;cursor:pointer;margin:0">
    <span style="flex:1;min-width:0;font-size:13px;font-weight:800;color:#0c4a6e;
                 white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(d.nama||'−')}</span>
    ${IS_ADMIN?`<div style="display:flex;gap:2px;flex-shrink:0">
      <button onclick="event.stopPropagation();openModalDetail(${+d.id},true)"
        style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;
               justify-content:center;background:transparent;border:none;cursor:pointer;color:#38bdf8">
        <i class="fa-solid fa-pen-to-square" style="font-size:11px"></i></button>
      <button onclick="event.stopPropagation();deleteDirect(${+d.id})"
        style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;
               justify-content:center;background:transparent;border:none;cursor:pointer;color:#fca5a5">
        <i class="fa-solid fa-trash-can" style="font-size:11px"></i></button>
    </div>`:''}
  </div>

  <div style="display:flex;flex-wrap:wrap;gap:4px;padding:0 12px 8px 36px">
    <span style="${badgeStyle};${bStyle(d.peran)}">${esc(d.peran||'Peserta')}</span>
    <span style="${badgeStyle};${sStyle(d.status_inap)}">${esc(d.status_inap||'Belum Check-in')}</span>
    ${d.kondisi?`<span style="${badgeStyle};background:#fff1f2;color:#be123c;border:1px solid #fecdd3">${esc(d.kondisi)}</span>`:''}
  </div>

  <div onclick="${IS_ADMIN?`openModalDetail(${+d.id})`:'void(0)'}"
    style="display:flex;align-items:center;gap:10px;padding:8px 12px 12px;
           border-top:1px solid #f0f9ff;cursor:${IS_ADMIN?'pointer':'default'}">
    <div style="width:44px;height:44px;flex-shrink:0;background:#f0f9ff;border:1px solid #bae6fd;
                border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center">
      <span style="font-size:7px;font-weight:800;color:#7dd3fc;text-transform:uppercase;line-height:1">Room</span>
      <span style="font-size:14px;font-weight:900;color:#0369a1;line-height:1;margin-top:2px">${esc(d.kamar||'−')}</span>
    </div>
    <div style="min-width:0;flex:1">
      <div style="font-size:11px;font-weight:700;color:#38bdf8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
        ${esc(d.gedung||'−')} · Lt.${esc(d.lantai||'−')}</div>
      <div style="font-size:10px;color:#94a3b8;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
        ${esc(d.judul||'Tanpa Kegiatan')}</div>
      <div style="font-size:10px;color:#cbd5e1;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
        ${esc(d.instansi||'−')}</div>
    </div>
  </div>
</div>`;
        }).join('');
    }

    function toggleSelect(id, checked) {
        if (checked) selectedIds.add(String(id));
        else selectedIds.delete(String(id));
        updateSelectionBar();
    }

    function toggleCardStyle(id, checked) {
        const c = document.getElementById(`mc-${id}`);
        if (!c) return;
        c.style.background = checked ? '#f0f9ff' : '#ffffff';
        c.style.borderColor = checked ? '#93c5fd' : '#e0f2fe';
    }

    function handleCheckAll(el) {
        document.querySelectorAll('.row-check').forEach(cb => {
            cb.checked = el.checked;
            toggleSelect(cb.dataset.id, el.checked);
        });
        updateSelectionBar();
    }

    function updateSelectionBar() {
        const n = selectedIds.size;
        $('selectionInfo').classList.toggle('hidden', n === 0);
        $('selCount').textContent = n;
        $('btnDeleteSelected').classList.toggle('hidden', n === 0);
    }

    async function deleteSelected() {
        if (!selectedIds.size) return;
        if (!confirm(`Hapus ${selectedIds.size} data terpilih?`)) return;
        try {
            const r = await fetch(apiUrl + '?action=delete_batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ids: [...selectedIds]
                })
            });
            const j = await r.json();
            showToast(j.message || 'OK');
            if (j.status) {
                selectedIds.clear();
                loadData();
            }
        } catch {
            showToast('Gagal menghapus');
        }
    }

    const FMAP = {
        fAgenda: 'agenda_id',
        fNama: 'nama',
        fGedung: 'gedung',
        fKamar: 'kamar',
        fLantai: 'lantai',
        fBed: 'bed',
        fPeran: 'peran',
        fStatus: 'status_inap',
        fJK: 'jenis_kelamin',
        fNip: 'nip',
        fInstansi: 'instansi',
        fNohp: 'no_hp',
        fCI: 'checkin_date',
        fCITime: 'checkin_time',
        fCO: 'checkout_date',
        fCOTime: 'checkout_time',
        fKondisi: 'kondisi',
        fCatatan: 'catatan'
    };

    function toggleInputs(dis) {
        Object.keys(FMAP).forEach(id => {
            const el = $(id);
            if (!el) return;
            el.disabled = dis;
            el.classList.toggle('opacity-50', dis);
        });
    }

    function resetForm() {
        $('fForm').reset();
        $('fId').value = '';
        $('fForceKamar').value = '0';
        $('fStatus').value = 'Belum Check-in';
        $('fPeran').value = 'Peserta';
        $('kamarWarning').classList.add('hidden');
        $('cbForceKamar').checked = false;
    }

    function resetForceKamar() {
        $('fForceKamar').value = '0';
        $('cbForceKamar').checked = false;
        $('kamarWarning').classList.add('hidden');
    }

    function closeModal() {
        $('formModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openModalTambah() {
        if (!IS_ADMIN) return;
        $('modalTitle').innerText = 'Tambah Peserta';
        resetForm();
        toggleInputs(false);
        $('btnEditTrigger')?.classList.add('hidden');
        $('btnHapus')?.classList.add('hidden');
        const bs = $('btnSubmit');
        if (bs) {
            bs.classList.remove('hidden');
            bs.innerText = 'Simpan Data';
        }
        $('formModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function openModalDetail(id, directEdit = false) {
        const d = localData.find(a => String(a.id) === String(id));
        if (!d) return;
        resetForm();
        Object.entries(FMAP).forEach(([fid, key]) => {
            const el = $(fid);
            if (el) el.value = d[key] || '';
        });
        $('fId').value = d.id;

        if (directEdit && IS_ADMIN) {
            $('modalTitle').innerText = 'Ubah Peserta';
            toggleInputs(false);
            $('btnEditTrigger')?.classList.add('hidden');
            $('btnHapus')?.classList.remove('hidden');
            const bs = $('btnSubmit');
            if (bs) {
                bs.classList.remove('hidden');
                bs.innerText = 'Simpan Perubahan';
            }
        } else {
            $('modalTitle').innerText = 'Detail Peserta';
            toggleInputs(true);
            if (IS_ADMIN) $('btnEditTrigger')?.classList.remove('hidden');
            $('btnHapus')?.classList.add('hidden');
            if (IS_ADMIN) $('btnSubmit')?.classList.add('hidden');
        }

        $('formModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function enableEditMode() {
        if (!IS_ADMIN) return;
        $('modalTitle').innerText = 'Ubah Peserta';
        toggleInputs(false);
        $('btnEditTrigger')?.classList.add('hidden');
        $('btnHapus')?.classList.remove('hidden');
        const bs = $('btnSubmit');
        if (bs) {
            bs.classList.remove('hidden');
            bs.innerText = 'Simpan Perubahan';
        }
    }

    async function handleSave(e) {
        e.preventDefault();
        if (!IS_ADMIN) {
            showToast('Akun Anda tidak memiliki akses simpan');
            return;
        }

        const id = $('fId').value;
        const nama = $('fNama').value.trim();
        if (!nama) {
            showToast('Nama wajib diisi');
            $('fNama').focus();
            return;
        }

        const force = $('fForceKamar').value;
        if (force === '0') {
            const kamar = $('fKamar').value.trim(),
                gedung = $('fGedung').value;
            if (kamar && gedung) {
                try {
                    const p = new URLSearchParams({
                        action: 'check',
                        kamar,
                        gedung,
                        checkin_date: $('fCI').value,
                        checkout_date: $('fCO').value,
                        exclude_id: id || 0
                    });
                    const r = await fetch(apiUrl + '?' + p);
                    const j = await r.json();
                    if (j.status && j.data.kamar) {
                        const pen = j.data.kamar_penghuni || [];
                        $('kamarPenghuni').innerHTML = pen.map(p =>
                            `<div>• ${esc(p.nama)} (${p.checkin_date||'?'} – ${p.checkout_date||'?'}) — ${esc(p.status_inap)}</div>`
                        ).join('');
                        $('kamarWarning').classList.remove('hidden');
                        showToast('Kamar sudah digunakan, centang untuk tetap simpan', 3500);
                        return;
                    }
                } catch {}
            }
        }

        const fd = new FormData();
        Object.entries(FMAP).forEach(([fid, key]) => fd.append(key, $(fid)?.value || ''));
        fd.append('force_kamar', force);
        if (id) fd.append('id', id);

        const bs = $('btnSubmit');
        bs.disabled = true;
        bs.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin mr-1"></i>Menyimpan...';

        try {
            const r = await fetch(apiUrl + '?action=save', {
                method: 'POST',
                body: fd
            });

            const text = await r.text();
            let j = null;
            try {
                j = JSON.parse(text);
            } catch (parseErr) {
                console.error('RAW SAVE RESPONSE:', text);
                showToast('Response simpan tidak valid');
                return;
            }

            if (j.status) {
                showToast(j.message || 'Berhasil disimpan');
                closeModal();
                loadData();
            } else if (j.message === 'KAMAR_BENTROK') {
                const pen = (j.data?.penghuni) || [];
                $('kamarPenghuni').innerHTML = pen.map(p =>
                    `<div>• ${esc(p.nama)} (${p.checkin_date||'?'} – ${p.checkout_date||'?'})</div>`
                ).join('');
                $('kamarWarning').classList.remove('hidden');
                showToast('Kamar sudah digunakan, centang untuk tetap simpan', 3500);
            } else {
                showToast(j.message || 'Gagal menyimpan');
            }
        } catch (err) {
            console.error(err);
            showToast('Gagal menyimpan');
        } finally {
            bs.disabled = false;
            bs.innerText = id ? 'Simpan Perubahan' : 'Simpan Data';
        }
    }

    async function handleDelete() {
        if (!IS_ADMIN || !confirm('Hapus data ini?')) return;
        const id = $('fId').value;
        if (!id) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
            const r = await fetch(apiUrl + '?action=delete', {
                method: 'POST',
                body: fd
            });
            const j = await r.json();
            showToast(j.message || 'OK');
            if (j.status) {
                closeModal();
                loadData();
            }
        } catch {
            showToast('Gagal menghapus');
        }
    }

    async function deleteDirect(id) {
        if (!confirm('Hapus data ini?')) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
            const r = await fetch(apiUrl + '?action=delete', {
                method: 'POST',
                body: fd
            });
            const j = await r.json();
            showToast(j.message || 'OK');
            if (j.status) loadData();
        } catch {
            showToast('Gagal menghapus');
        }
    }

    const openPreview = () => {
        $('previewModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    };
    const closePreview = () => {
        $('previewModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    };

    const updPrev = (i, k, v) => {
        if (previewData[i]) previewData[i][k] = v;
    };
    const updAgenda = (i, name) => {
        if (!previewData[i]) return;
        previewData[i].nama_kegiatan = name;
        previewData[i].agenda_id = findAgendaId(name) || '';
    };
    const hasMiss = r => !!(r.nama_kegiatan && !findAgendaId(r.nama_kegiatan) && !r.agenda_id);
    const selOpts = (v, opts) => opts.map(([ov, ol]) => `<option value="${ov}"${v===ov?' selected':''}>${ol}</option>`).join('');
    const jkOpts = [
        ['', '−'],
        ['L', 'L'],
        ['P', 'P']
    ];
    const peranOpts = [
        ['Peserta', 'Peserta'],
        ['Pengajar', 'Pengajar'],
        ['Panitia', 'Panitia']
    ];

    function renderPreview() {
        $('prevCount').textContent = previewData.length;
        if (!previewData.length) {
            $('prevTableBody').innerHTML = `<tr><td colspan="11" class="p-6 text-center text-slate-400">Tidak ada data</td></tr>`;
            $('prevCards').innerHTML = `<div class="bg-white rounded-2xl border p-6 text-center text-slate-400">Tidak ada data</div>`;
            return;
        }
        $('prevTableBody').innerHTML = previewData.map((r, i) => {
            const m = hasMiss(r);
            return `<tr class="border-b border-slate-50 align-top${m?' bg-rose-50/40':''}">
  <td class="p-2"><input type="checkbox" class="prev-row-check" data-index="${i}"></td>
  <td class="p-2"><input type="text" class="pi${m?' pi-miss':''}" value="${esc(r.nama_kegiatan||'')}" oninput="updAgenda(${i},this.value);renderPreview()"></td>
  <td class="p-2"><input type="text" class="pi" value="${esc(r.nama||'')}" oninput="updPrev(${i},'nama',this.value)"></td>
  <td class="p-2"><select class="pi" onchange="updPrev(${i},'jenis_kelamin',this.value)">${selOpts(r.jenis_kelamin,jkOpts)}</select></td>
  <td class="p-2"><input type="text" class="pi" value="${esc(r.nip||'')}" oninput="updPrev(${i},'nip',this.value)"></td>
  <td class="p-2"><input type="text" class="pi" value="${esc(r.instansi||'')}" oninput="updPrev(${i},'instansi',this.value)"></td>
  <td class="p-2"><input type="text" class="pi" value="${esc(r.gedung||'')}" oninput="updPrev(${i},'gedung',this.value)"></td>
  <td class="p-2"><input type="text" class="pi" value="${esc(r.lantai||'')}" oninput="updPrev(${i},'lantai',this.value)"></td>
  <td class="p-2"><input type="text" class="pi" value="${esc(r.kamar||'')}" oninput="updPrev(${i},'kamar',this.value.toUpperCase())"></td>
  <td class="p-2"><select class="pi" onchange="updPrev(${i},'peran',this.value)">${selOpts(r.peran,peranOpts)}</select></td>
  <td class="p-2"><input type="text" class="pi${m?' pi-miss':''}" value="${esc(r.agenda_id||'')}" oninput="updPrev(${i},'agenda_id',this.value)"></td>
</tr>`;
        }).join('');

        $('prevCards').innerHTML = previewData.map((r, i) => {
            const m = hasMiss(r);
            return `<div class="bg-white rounded-2xl border p-3 ${m?'border-rose-300 bg-rose-50/40':'border-slate-200'}">
  <div class="flex items-center gap-2 mb-2">
    <input type="checkbox" class="prev-row-check" data-index="${i}">
    <span class="text-xs font-bold text-slate-700">Data #${i+1}</span>
    <span class="badge ${bCls(r.peran||'Peserta')}">${esc(r.peran||'Peserta')}</span>
  </div>
  <div class="space-y-1.5">
    <div>
      <label class="text-[10px] font-bold text-slate-500">Nama Kegiatan</label>
      <input type="text" class="pi${m?' pi-miss':''}" value="${esc(r.nama_kegiatan||'')}" oninput="updAgenda(${i},this.value);renderPreview()">
      ${m?`<p class="text-[10px] text-rose-500 mt-0.5 font-semibold">Agenda belum cocok</p>`:''}
    </div>
    <div>
      <label class="text-[10px] font-bold text-slate-500">Nama</label>
      <input type="text" class="pi" value="${esc(r.nama||'')}" oninput="updPrev(${i},'nama',this.value)">
    </div>
    <div class="grid grid-cols-3 gap-1.5">
      <div><label class="text-[10px] font-bold text-slate-500">Gedung</label><input type="text" class="pi" value="${esc(r.gedung||'')}" oninput="updPrev(${i},'gedung',this.value)"></div>
      <div><label class="text-[10px] font-bold text-slate-500">Lt</label><input type="text" class="pi" value="${esc(r.lantai||'')}" oninput="updPrev(${i},'lantai',this.value)"></div>
      <div><label class="text-[10px] font-bold text-slate-500">Kamar</label><input type="text" class="pi" value="${esc(r.kamar||'')}" oninput="updPrev(${i},'kamar',this.value.toUpperCase())"></div>
    </div>
  </div>
</div>`;
        }).join('');
    }

    const prevMasterCheck = el => document.querySelectorAll('.prev-row-check').forEach(c => c.checked = el.checked);

    function selAllPrev(s) {
        document.querySelectorAll('.prev-row-check').forEach(c => c.checked = s);
        const m = $('prevCheckAll');
        if (m) m.checked = s;
    }

    function delCheckedPrev() {
        const idx = new Set([...document.querySelectorAll('.prev-row-check:checked')].map(c => +c.dataset.index));
        if (!idx.size) {
            showToast('Pilih data terlebih dahulu');
            return;
        }
        previewData = previewData.filter((_, i) => !idx.has(i));
        renderPreview();
        showToast('Dihapus dari preview');
    }

    async function savePrevToDB() {
        if (!previewData.length) {
            showToast('Tidak ada data');
            return;
        }
        const btn = $('btnBatch');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin mr-1"></i>Menyimpan...';
        const valid = previewData.filter(r => r.nama).map(r => ({
            agenda_id: r.agenda_id || findAgendaId(r.nama_kegiatan || ''),
            nama: r.nama,
            jenis_kelamin: r.jenis_kelamin || '',
            nip: r.nip || '',
            instansi: r.instansi || '',
            gedung: r.gedung || '',
            lantai: r.lantai || '',
            kamar: String(r.kamar || '').toUpperCase(),
            bed: r.bed || '',
            peran: r.peran || 'Peserta',
            status_inap: r.status_inap || 'Belum Check-in',
            no_hp: r.no_hp || '',
            checkin_date: r.checkin_date || '',
            checkin_time: r.checkin_time || '',
            checkout_date: r.checkout_date || '',
            checkout_time: r.checkout_time || '',
            kondisi: r.kondisi || '',
            catatan: r.catatan || ''
        }));
        const skip = previewData.length - valid.length;
        try {
            const res = await fetch(apiUrl + '?action=batch_save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    rows: valid
                })
            });
            const j = await res.json();
            showToast(j.message + (skip ? `, dilewati ${skip}` : ''), 3000);
            if (j.status) {
                previewData = [];
                renderPreview();
                closePreview();
                loadData();
            }
        } catch {
            showToast('Gagal: koneksi bermasalah');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Simpan ke Database';
        }
    }

    function handleExcel(event) {
        const file = event.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
            try {
                const wb = XLSX.read(new Uint8Array(reader.result), {
                    type: 'array'
                });
                const pats = [/check\s*in/i, /peserta/i, /pengajar/i, /panitia/i];
                const sheets = wb.SheetNames.filter(n => pats.some(rx => rx.test(n)));
                const targets = sheets.length ? sheets : [wb.SheetNames[0]];

                const c = v => String(v ?? '').trim();
                const normJK = jk => {
                    const v = c(jk).toUpperCase();
                    return v === 'L' || v === 'LAKI-LAKI' ? 'L' : v === 'P' || v === 'PEREMPUAN' ? 'P' : '';
                };
                const normPeran = sn => {
                    const n = c(sn).toLowerCase();
                    return n.includes('pengajar') ? 'Pengajar' : n.includes('panitia') ? 'Panitia' : 'Peserta';
                };
                const normGedung = g => {
                    const m = {
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
                    return m[c(g).replace(/\s+/g, ' ').toUpperCase()] || g;
                };
                const getCell = (row, keys) => {
                    for (const k of keys)
                        if (Object.prototype.hasOwnProperty.call(row, k)) return row[k];
                    return '';
                };
                const parseRoom = raw => {
                    raw = c(raw);
                    if (!raw) return {
                        gedung: '',
                        lantai: '',
                        kamar: ''
                    };
                    let m = raw.match(/^(.+?)\s+LT\s+([A-Z0-9\-\/]+)\s+NO\.?\s*(.+)$/i);
                    if (m) return {
                        gedung: normGedung(m[1]),
                        lantai: c(m[2]),
                        kamar: c(m[3]).toUpperCase()
                    };
                    m = raw.match(/^(.+?)\s+LT\s+([A-Z0-9\-\/]+)$/i);
                    if (m) return {
                        gedung: normGedung(m[1]),
                        lantai: c(m[2]),
                        kamar: ''
                    };
                    return {
                        gedung: '',
                        lantai: '',
                        kamar: raw.toUpperCase()
                    };
                };

                previewData = [];
                let skipped = 0;
                const selAgenda = $('fAgenda')?.value || '';

                for (const sn of targets) {
                    const rows = XLSX.utils.sheet_to_json(wb.Sheets[sn], {
                        defval: ''
                    });
                    let lastRoom = '';
                    const defPeran = normPeran(sn);
                    for (const row of rows) {
                        const nama = c(getCell(row, ['NAMA', 'Nama', 'nama']));
                        if (!nama || ['NAMA', 'NO', 'TTD'].includes(nama.toUpperCase())) {
                            skipped++;
                            continue;
                        }
                        let roomText = c(getCell(row, ['KAMAR', 'Kamar', 'kamar']));
                        if (!roomText && lastRoom) roomText = lastRoom;
                        if (roomText) lastRoom = roomText;
                        const {
                            gedung,
                            lantai,
                            kamar
                        } = parseRoom(roomText);
                        const namaKegiatan = c(getCell(row, ['NAMA_KEGIATAN', 'Nama Kegiatan', 'KEGIATAN', 'Kegiatan', 'nama_kegiatan', 'kegiatan']));
                        previewData.push({
                            agenda_id: findAgendaId(namaKegiatan) || selAgenda,
                            nama_kegiatan: namaKegiatan,
                            nama,
                            jenis_kelamin: normJK(getCell(row, ['JK', 'Jk', 'jk'])),
                            nip: c(getCell(row, ['NIP', 'Nip', 'nip'])),
                            instansi: c(getCell(row, ['UNIT KERJA', 'Unit Kerja', 'Instansi', 'instansi'])),
                            gedung,
                            lantai,
                            kamar,
                            bed: '',
                            peran: defPeran,
                            status_inap: 'Belum Check-in',
                            no_hp: '',
                            checkin_date: '',
                            checkin_time: '',
                            checkout_date: '',
                            checkout_time: '',
                            kondisi: '',
                            catatan: 'Import: ' + sn
                        });
                    }
                }
                renderPreview();
                openPreview();
                showToast(`${previewData.length} data siap` + (skipped ? `, dilewati ${skipped}` : ''));
                event.target.value = '';
            } catch (err) {
                console.error(err);
                showToast('File gagal diproses');
                event.target.value = '';
            }
        };
        reader.readAsArrayBuffer(file);
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) abortCtrl.abort();
        else loadData();
    });
    window.addEventListener('beforeunload', () => abortCtrl.abort());
    window.addEventListener('pagehide', () => abortCtrl.abort());

    function fixHeaderOffset() {
        const stickyHeader = document.querySelector('.sticky-header');
        if (!stickyHeader) return;

        let topOffset = 0;
        document.querySelectorAll('*').forEach(el => {
            if (el === stickyHeader || stickyHeader.contains(el)) return;
            const st = window.getComputedStyle(el);
            if ((st.position === 'fixed' || st.position === 'sticky') && st.display !== 'none') {
                const rect = el.getBoundingClientRect();
                if (rect.top >= 0 && rect.top < 200 && rect.height > 0) {
                    const bottom = rect.top + rect.height;
                    if (bottom > topOffset) topOffset = bottom;
                }
            }
        });

        stickyHeader.style.top = topOffset + 'px';
        const headerHeight = topOffset + stickyHeader.offsetHeight;

        document.querySelectorAll('.header-offset').forEach(el => {
            el.style.paddingTop = (headerHeight + 20) + 'px';
        });
    }

    window.onload = () => {
        fixHeaderOffset();
        loadData();
    };
    window.addEventListener('resize', fixHeaderOffset);
    setTimeout(fixHeaderOffset, 300);
    setTimeout(fixHeaderOffset, 800);
</script>