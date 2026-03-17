<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Manajemen Persuratan";
include 'header.php';
include 'config.php';

$canEdit = in_array(strtolower($_SESSION['user']['role'] ?? ''), ['admin', 'sekretariat']);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #fff;
        color: #1e293b;
    }

    /* Fix: pastikan fixed header tidak terblokir transform/overflow parent */
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

    .item-card {
        transition: all .2s ease;
    }

    .item-card:active {
        transform: scale(.97);
    }

    .badge-masuk {
        background: #f0f9ff;
        color: #0284c7;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 999px;
        text-transform: uppercase;
    }

    .badge-keluar {
        background: #ecfdf5;
        color: #059669;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 999px;
        text-transform: uppercase;
    }

    .modal-animate-up {
        animation: slideUp .35s cubic-bezier(.16, 1, .3, 1) forwards;
    }

    @keyframes slideUp {
        from {
            transform: translateY(16px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

<!-- ===== HEADER (sama persis dengan timetable) ===== -->
<header class="sticky-header px-5 py-4 relative">
    <div class="flex items-center gap-3 min-w-0">
        <button onclick="window.history.back()"
            class="w-10 h-10 shrink-0 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </button>
        <div class="min-w-0">
            <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Manajemen Persuratan</h1>
            <p class="text-[12px] text-gray-400 font-medium leading-tight">Database &amp; log surat resmi</p>
        </div>
    </div>
    <button onclick="openExportModal()"
        class="absolute top-5 right-4 w-11 h-11 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition text-lg">
        <i class="fa-solid fa-download text-lg"></i>
    </button>
</header>

<!-- ===== SEARCH BAR (di luar header, seperti halaman lain) ===== -->
<div class="px-4 pt-3 pb-2 bg-white" style="position:fixed; top:73px; left:0; right:0; z-index:48;">
    <div class="relative group">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
        </div>
        <input type="text" id="searchSurat"
            placeholder="Cari nomor / perihal / pengirim / tanggal..."
            class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-sm focus:bg-white focus:border-sky-300 outline-none transition-all">
    </div>
    <div class="mt-2 flex items-center justify-between">
        <span id="badgeCount" class="text-[10px] font-extrabold text-sky-700 bg-sky-50 px-3 py-1 rounded-full">0 Data</span>
        <span class="text-[10px] text-gray-400">Ketik untuk stop auto-refresh</span>
    </div>
</div>

<!-- ===== LIST ===== -->
<main class="px-4 py-6 mb-28" style="margin-top:169px;">
    <div id="listContainer">
        <div class="text-xs text-gray-400 py-8 text-center">Memuat data...</div>
    </div>
</main>

<!-- FAB -->
<?php if ($canEdit): ?>
    <button onclick="openTambah()"
        class="fixed bottom-8 right-8 w-11 h-11 bg-sky-600 text-white rounded-full shadow-lg shadow-sky-100 flex items-center justify-center z-[40] active:scale-90 transition-all">
        <i class="fa-solid fa-plus text-lg"></i>
    </button>
<?php endif; ?>

<!-- MODAL EXPORT -->
<div id="exportModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeExportModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl modal-animate-up">
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

<!-- MODAL PERSURATAN -->
<div id="suratModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeModal()"></div>
    <div class="fixed inset-0 flex items-end justify-center">
        <div class="w-full max-w-md bg-white rounded-t-3xl shadow-xl modal-animate-up max-h-[92vh] overflow-y-auto">
            <div class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p id="sheetTitle" class="text-sm font-extrabold text-gray-800">Detail Surat</p>
                        <p class="text-[11px] text-gray-500">Manajemen Persuratan</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($canEdit): ?>
                            <button type="button" id="btnEditTrigger" onclick="enableEdit()"
                                class="w-9 h-9 rounded-full bg-sky-50 text-sky-600 hidden">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                        <?php endif; ?>
                        <button type="button" onclick="closeModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
                <form id="formSurat" enctype="multipart/form-data" class="space-y-3">
                    <input type="hidden" id="edit-id">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Jenis Surat</label>
                        <select id="f-jenis" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                            <option value="masuk">Surat Masuk</option>
                            <option value="keluar">Surat Keluar</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Nomor Surat</label>
                        <input id="f-nomor" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Perihal</label>
                        <input id="f-perihal" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Pengirim / Tujuan</label>
                        <input id="f-pengirim" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Tanggal Surat</label>
                        <input id="f-tanggal" type="date" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Keterangan</label>
                        <textarea id="f-ket" rows="3" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200"></textarea>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Upload File (PDF/JPG/PNG/WebP)</label>
                        <input id="f-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                            class="w-full mt-1 px-4 py-3 rounded-2xl bg-white border border-gray-200">
                    </div>
                    <div id="filePreview" class="hidden rounded-2xl border border-gray-100 overflow-hidden bg-white"></div>
                    <?php if ($canEdit): ?>
                        <button id="btnSubmit" type="button" onclick="saveSurat()"
                            class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Simpan Surat</button>
                        <button id="btnHapus" type="button" onclick="deleteSurat()"
                            class="w-full py-3 rounded-2xl bg-red-50 text-red-600 font-extrabold text-sm hidden">
                            <i class="fa-solid fa-trash-can mr-2"></i> Hapus Surat
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ZOOM IMAGE -->
<div id="imgModal" class="fixed inset-0 bg-black/70 z-[9999] hidden">
    <div class="absolute inset-0" onclick="closeImgModal()"></div>
    <div class="relative w-full h-full flex items-center justify-center p-4">
        <button onclick="closeImgModal()"
            class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/90 text-gray-700 flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="imgModalSrc" src="" class="max-w-full max-h-full rounded-2xl shadow-lg" style="object-fit:contain; background:#fff;" />
    </div>
</div>

<div id="toast" class="fixed top-24 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-6 py-3 rounded-full text-[10px] font-bold shadow-xl opacity-0 pointer-events-none transition-all duration-300 z-[200]">OK</div>

<?php include 'footer.php'; ?>

<script>
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    let suratData = [],
        refreshTimer = null;

    const suratModal = document.getElementById('suratModal');
    const sheetTitle = document.getElementById('sheetTitle');
    const btnEditTrigger = CAN_EDIT ? document.getElementById('btnEditTrigger') : null;
    const btnSubmit = CAN_EDIT ? document.getElementById('btnSubmit') : null;
    const btnHapus = CAN_EDIT ? document.getElementById('btnHapus') : null;
    const filePreview = document.getElementById('filePreview');

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', 2500);
    }

    function openImgModal(src) {
        if (!src) return;
        document.getElementById('imgModalSrc').src = src;
        document.getElementById('imgModal').classList.remove('hidden');
    }

    function closeImgModal() {
        document.getElementById('imgModal').classList.add('hidden');
        document.getElementById('imgModalSrc').src = '';
    }

    function formatTanggalID(ymd) {
        if (!ymd) return '-';
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        }).format(new Date(ymd));
    }

    async function refreshList() {
        try {
            const res = await fetch('arsip_surat_api.php?action=list', {
                cache: 'no-store'
            });
            if (!res.ok) return;
            suratData = (await res.json()).data || [];
            renderList(suratData);
            applySearchFilter();
        } catch (e) {}
    }

    function renderList(data) {
        const box = document.getElementById('listContainer');
        if (!data || data.length === 0) {
            box.innerHTML = `<div class="text-center text-gray-500 mt-24"><i class="fa-solid fa-inbox text-6xl text-sky-400 mb-4"></i><p class="font-semibold">Belum ada arsip surat</p></div>`;
            document.getElementById('badgeCount').textContent = '0 Data';
            return;
        }
        const groups = {};
        data.forEach(r => {
            const k = r.tanggal_surat || '';
            if (!groups[k]) groups[k] = [];
            groups[k].push(r);
        });
        const sortedDates = Object.keys(groups).sort((a, b) => b.localeCompare(a));
        let html = '';
        sortedDates.forEach(tgl => {
            html += `<div class="groupTanggal mb-6"><div class="flex items-center justify-between mb-2"><p class="text-[11px] font-extrabold text-gray-500 uppercase tracking-widest">${formatTanggalID(tgl)}</p><span class="text-[10px] font-bold text-gray-400">${groups[tgl].length} surat</span></div><div class="space-y-3">`;
            groups[tgl].forEach(r => {
                const badge = r.jenis === 'keluar' ? 'badge-keluar' : 'badge-masuk';
                const jTxt = r.jenis === 'keluar' ? 'Surat Keluar' : 'Surat Masuk';
                const safe = s => (s ?? '').toString().replace(/"/g, '&quot;');
                html += `
                    <div class="item-card transaksi-item bg-white border border-gray-100 rounded-2xl shadow-sm p-4 cursor-pointer"
                        onclick="openDetail(${r.id})"
                        data-id="${r.id}" data-jenis="${safe(r.jenis)}" data-nomor="${safe(r.nomor_surat)}"
                        data-perihal="${safe(r.perihal)}" data-pengirim="${safe(r.pengirim)}"
                        data-tanggal="${safe(r.tanggal_surat)}" data-ket="${safe(r.keterangan)}" data-file="${safe(r.file_path)}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="${badge}">${jTxt}</span>
                                    <span class="text-[10px] text-gray-400 font-mono truncate max-w-[170px]">${r.nomor_surat||'-'}</span>
                                </div>
                                <p class="mt-1 text-sm font-extrabold text-gray-800 leading-snug truncate">${r.perihal||'-'}</p>
                                <p class="mt-1 text-[11px] text-gray-500 truncate">${r.pengirim||'-'}</p>
                            </div>
                            <span class="shrink-0 text-[10px] font-bold text-sky-700 bg-sky-50 px-3 py-1 rounded-full">Detail</span>
                        </div>
                        ${r.keterangan?`<div class="mt-3 text-[11px] text-gray-400 bg-gray-50 border border-gray-100 rounded-2xl px-3 py-2 line-clamp-2">${r.keterangan}</div>`:''}
                    </div>`;
            });
            html += `</div></div>`;
        });
        box.innerHTML = html;
        document.getElementById('badgeCount').textContent = `${data.length} Data`;
    }

    function applySearchFilter() {
        const kw = (document.getElementById('searchSurat').value || '').trim().toLowerCase();
        let n = 0;
        document.querySelectorAll('.groupTanggal').forEach(grp => {
            let ada = false;
            grp.querySelectorAll('.transaksi-item').forEach(item => {
                const ok = kw === '' || ['nomor', 'perihal', 'pengirim', 'tanggal'].some(k => (item.dataset[k] || '').toLowerCase().includes(kw)) ||
                    formatTanggalID(item.dataset.tanggal || '').toLowerCase().includes(kw);
                item.style.display = ok ? '' : 'none';
                if (ok) {
                    ada = true;
                    n++;
                }
            });
            grp.style.display = ada ? '' : 'none';
        });
        document.getElementById('badgeCount').textContent = `${n} Data`;
    }

    document.getElementById('searchSurat').addEventListener('input', function() {
        applySearchFilter();
        this.value.trim() ? stopAutoRefresh() : (startAutoRefresh(), refreshList());
    });

    function startAutoRefresh() {
        if (!refreshTimer) refreshTimer = setInterval(refreshList, 3000);
    }

    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    function toggleInputs(disabled) {
        ['f-jenis', 'f-nomor', 'f-perihal', 'f-pengirim', 'f-tanggal', 'f-ket', 'f-file'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.disabled = disabled;
            el.classList.toggle('opacity-70', disabled);
        });
    }

    function previewFile(fp) {
        if (!fp) {
            filePreview.classList.add('hidden');
            filePreview.innerHTML = '';
            return;
        }
        const ext = fp.split('.').pop().toLowerCase();
        const hdr = `<div class="flex items-center justify-between p-3 bg-gray-50 border-b border-gray-100"><div class="flex items-center gap-2"><div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center"><i class="fa-solid fa-file-lines text-sm"></i></div><div class="leading-tight"><p class="text-xs font-extrabold text-gray-800">Lampiran</p><p class="text-[10px] text-gray-400 font-mono truncate max-w-[180px]">${fp}</p></div></div><a href="${fp}" target="_blank" class="text-[11px] font-bold text-sky-600 bg-sky-50 px-3 py-1.5 rounded-full">Buka Full</a></div>`;
        let body = ext === 'pdf' ?
            `<iframe src="${fp}" class="w-full" style="height:55vh;min-height:340px;"></iframe>` : ['jpg', 'jpeg', 'png', 'webp'].includes(ext) ?
            `<img src="${fp}" onclick="openImgModal('${fp}')" class="w-full cursor-zoom-in" style="height:55vh;min-height:340px;object-fit:contain;background:#fff;"/><p class="text-[10px] text-gray-400 p-2 text-center">Tap gambar untuk zoom</p>` :
            `<div class="p-3 text-[11px] text-gray-500">File tidak bisa dipreview. Klik <b>Buka Full</b>.</div>`;
        filePreview.classList.remove('hidden');
        filePreview.innerHTML = hdr + body;
    }

    function openTambah() {
        if (!CAN_EDIT) return;
        sheetTitle.innerText = "Tambah Surat";
        document.getElementById('formSurat').reset();
        document.getElementById('edit-id').value = '';
        document.getElementById('f-tanggal').value = new Date().toISOString().split('T')[0];
        toggleInputs(false);
        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnHapus) btnHapus.classList.add('hidden');
        if (btnSubmit) {
            btnSubmit.classList.remove('hidden');
            btnSubmit.innerText = 'Simpan Surat';
        }
        previewFile('');
        suratModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function openDetail(id) {
        const el = document.querySelector(`.transaksi-item[data-id="${id}"]`);
        if (!el) return;
        sheetTitle.innerText = "Detail Surat";
        document.getElementById('edit-id').value = el.dataset.id;
        document.getElementById('f-jenis').value = el.dataset.jenis || 'masuk';
        document.getElementById('f-nomor').value = el.dataset.nomor || '';
        document.getElementById('f-perihal').value = el.dataset.perihal || '';
        document.getElementById('f-pengirim').value = el.dataset.pengirim || '';
        document.getElementById('f-tanggal').value = el.dataset.tanggal || '';
        document.getElementById('f-ket').value = el.dataset.ket || '';
        toggleInputs(true);
        if (CAN_EDIT && btnEditTrigger) btnEditTrigger.classList.remove('hidden');
        if (btnSubmit) btnSubmit.classList.add('hidden');
        if (btnHapus) btnHapus.classList.add('hidden');
        previewFile(el.dataset.file || '');
        suratModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function enableEdit() {
        if (!CAN_EDIT) return;
        sheetTitle.innerText = "Ubah Surat";
        toggleInputs(false);
        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnSubmit) {
            btnSubmit.classList.remove('hidden');
            btnSubmit.innerText = 'Simpan Perubahan';
        }
        if (btnHapus) btnHapus.classList.remove('hidden');
    }

    function closeModal() {
        suratModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    async function saveSurat() {
        if (!CAN_EDIT) return;
        const id = document.getElementById('edit-id').value;
        const fd = new FormData();
        fd.append('id', id);
        fd.append('jenis', document.getElementById('f-jenis').value);
        fd.append('nomor_surat', document.getElementById('f-nomor').value);
        fd.append('perihal', document.getElementById('f-perihal').value);
        fd.append('pengirim_tujuan', document.getElementById('f-pengirim').value);
        fd.append('tanggal_surat', document.getElementById('f-tanggal').value);
        fd.append('keterangan', document.getElementById('f-ket').value);
        const f = document.getElementById('f-file').files[0];
        if (f) fd.append('file', f);
        if (!fd.get('nomor_surat') || !fd.get('perihal') || !fd.get('tanggal_surat')) {
            alert('Nomor Surat, Perihal, dan Tanggal wajib diisi');
            return;
        }
        const res = await fetch(`arsip_surat_api.php?action=${id?'update':'create'}`, {
            method: 'POST',
            body: fd
        });
        const j = await res.json().catch(() => ({
            ok: false,
            message: 'Gagal'
        }));
        if (!j.ok) {
            alert(j.message || 'Gagal menyimpan');
            return;
        }
        closeModal();
        showToast('Tersimpan');
        await refreshList();
    }

    async function deleteSurat() {
        if (!CAN_EDIT) return;
        const id = document.getElementById('edit-id').value;
        if (!id || !confirm('Hapus surat ini?')) return;
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch('arsip_surat_api.php?action=delete', {
            method: 'POST',
            body: fd
        });
        const j = await res.json().catch(() => ({
            ok: false
        }));
        if (!j.ok) {
            alert(j.message || 'Gagal menghapus');
            return;
        }
        closeModal();
        showToast('Dihapus');
        await refreshList();
    }

    function openExportModal() {
        document.getElementById('exportModal').classList.remove('hidden');
        const today = new Date(),
            past = new Date();
        past.setDate(today.getDate() - 30);
        document.getElementById('exportTo').value = today.toISOString().split('T')[0];
        document.getElementById('exportFrom').value = past.toISOString().split('T')[0];
    }

    function closeExportModal() {
        document.getElementById('exportModal').classList.add('hidden');
    }

    function downloadExport() {
        const from = document.getElementById('exportFrom').value,
            to = document.getElementById('exportTo').value;
        if (from && to && from > to) {
            alert("Tanggal 'Dari' tidak boleh lebih besar dari 'Sampai'");
            return;
        }
        let url = "arsip_surat_export.php";
        const p = [];
        if (from) p.push("from=" + encodeURIComponent(from));
        if (to) p.push("to=" + encodeURIComponent(to));
        if (p.length) url += "?" + p.join("&");
        window.location.href = url;
        closeExportModal();
    }

    refreshList();
    startAutoRefresh();

    // Stop auto-refresh saat halaman ditinggalkan
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
            refreshList();
        }
    });
    window.addEventListener('beforeunload', stopAutoRefresh);
    window.addEventListener('pagehide', stopAutoRefresh);
</script>