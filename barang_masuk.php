<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Barang Masuk";
include 'header.php';
include 'config.php';

// ✅ Hanya admin & gudang yang bisa tambah
$canEdit = in_array(strtolower($_SESSION['user']['role'] ?? ''), ['admin', 'gudang']);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #fff;
        color: #1e293b;
    }

    .sticky-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        background: #ffffff;
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
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        text-transform: uppercase;
    }
</style>

<!-- Header -->
<header class="sticky-header px-5 py-4 relative">
    <div class="flex items-center gap-3 min-w-0">
        <button onclick="window.history.back()"
            class="w-10 h-10 shrink-0 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </button>
        <div class="min-w-0">
            <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Barang Masuk</h1>
            <p class="text-[12px] text-gray-400 font-medium leading-tight">Data 30 hari terakhir (auto update)</p>
        </div>
    </div>
    <button onclick="openExportModal()"
        class="absolute top-5 right-4 w-11 h-11 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition text-lg">
        <i class="fa-solid fa-download text-lg"></i>
    </button>
</header>

<!-- Search (fixed di bawah header) -->
<div data-fixed-bar style="position:fixed; top:73px; left:0; right:0; z-index:48; background:#fff;">
    <div class="px-4 pt-3 pb-3">
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
            </div>
            <input type="text" id="searchTransaksi"
                placeholder="Cari ref / supplier / no sj / barang / kategori..."
                class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-sm focus:bg-white focus:border-sky-300 outline-none transition-all">
        </div>
    </div>
</div>

<!-- LIST CONTAINER -->
<main id="mainContent" class="px-4 mb-28" style="margin-top:73px;">
    <div id="listContainer" class="py-4">
        <div class="text-xs text-gray-400 py-8 text-center">Memuat data...</div>
    </div>
</main>

<!-- FAB Tambah -->
<?php if ($canEdit): ?>
    <a href="barang_masuk_tambah.php"
        class="fixed bottom-8 right-8 w-11 h-11 bg-sky-600 text-white rounded-full shadow-lg shadow-sky-100 flex items-center justify-center z-[40] active:scale-90 transition-all">
        <i class="fa-solid fa-plus text-lg"></i>
    </a>
<?php endif; ?>

<!-- Overlay -->
<div id="sheetOverlay" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 hidden" onclick="closeSheet()"></div>

<!-- Bottom Sheet -->
<div id="sheetDetail" class="fixed bottom-0 left-0 right-0 bg-white rounded-t-[28px] z-[60] translate-y-full transition-transform duration-300">
    <div class="flex justify-center py-3">
        <div class="w-12 h-1.5 bg-gray-300 rounded-full"></div>
    </div>
    <div class="px-6 pb-4 border-b border-gray-100">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-extrabold text-gray-800 text-sm">Detail Barang Masuk</h3>
                <p id="sheetRef" class="text-[11px] text-sky-600 font-bold">-</p>
            </div>
            <button onclick="closeSheet()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-500">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
    </div>
    <div id="sheetContent" class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto"></div>
</div>

<!-- MODAL ZOOM IMAGE -->
<div id="imgModal" class="fixed inset-0 bg-black/70 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeImgModal()"></div>
    <div class="relative w-full h-full flex items-center justify-center p-4">
        <button onclick="closeImgModal()"
            class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/90 text-gray-700 flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="imgModalSrc" src="" class="max-w-full max-h-full rounded-2xl shadow-lg" style="object-fit:contain; background:#fff;" />
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
                    <input type="date" id="exportFrom" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600">Sampai Tanggal</label>
                    <input type="date" id="exportTo" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>
                <button onclick="downloadExport()" class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Download PDF</button>
                <p class="text-[10px] text-gray-400 text-center">Default otomatis 30 hari terakhir</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Dynamic offset
    window.addEventListener('load', function() {
        setTimeout(function() {
            var bar = document.querySelector('[data-fixed-bar]');
            var main = document.getElementById('mainContent');
            if (bar && main) main.style.marginTop = (73 + bar.offsetHeight + 4) + 'px';
        }, 150);
    });

    function closeSheet() {
        document.getElementById('sheetOverlay').classList.add('hidden');
        document.getElementById('sheetDetail').classList.add('translate-y-full');
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

    function openExportModal() {
        document.getElementById('exportModal').classList.remove('hidden');
        const today = new Date(),
            past = new Date();
        past.setDate(today.getDate() - 30);
        document.getElementById('exportTo').value = today.toISOString().slice(0, 10);
        document.getElementById('exportFrom').value = past.toISOString().slice(0, 10);
    }

    function closeExportModal() {
        document.getElementById('exportModal').classList.add('hidden');
    }

    function downloadExport() {
        const from = document.getElementById('exportFrom').value;
        const to = document.getElementById('exportTo').value;
        if (from && to && from > to) {
            alert("Tanggal 'Dari' tidak boleh lebih besar dari 'Sampai'");
            return;
        }
        let url = "barang_masuk_export.php";
        const p = [];
        if (from) p.push("from=" + encodeURIComponent(from));
        if (to) p.push("to=" + encodeURIComponent(to));
        if (p.length) url += "?" + p.join("&");
        window.location.href = url;
    }

    function openSheetLocal(el) {
        document.getElementById('sheetOverlay').classList.remove('hidden');
        document.getElementById('sheetDetail').classList.remove('translate-y-full');

        const ref = el.dataset.ref || '-';
        const tanggal = el.dataset.tanggal || '-';
        const jam = el.dataset.jam || '-';
        const supplier = el.dataset.supplier || '-';
        const noSj = el.dataset.no_sj || '-';
        const fileSj = el.dataset.file_sj || '';

        let items = [];
        try {
            items = JSON.parse(el.dataset.items || "[]");
        } catch (e) {
            items = [];
        }

        document.getElementById('sheetRef').innerText = ref;

        const colorMap = {
            sky: {
                bg: "bg-sky-100",
                text: "text-sky-600"
            },
            purple: {
                bg: "bg-purple-100",
                text: "text-purple-600"
            },
            amber: {
                bg: "bg-amber-100",
                text: "text-amber-600"
            },
            teal: {
                bg: "bg-teal-100",
                text: "text-teal-600"
            },
            emerald: {
                bg: "bg-emerald-100",
                text: "text-emerald-600"
            },
        };

        let previewHtml = '';
        if (!fileSj || fileSj.trim() === '') {
            previewHtml = `<div class="mt-3 text-[11px] text-gray-400 text-center py-3 bg-gray-50 border border-gray-100 rounded-2xl">Surat jalan belum diupload</div>`;
        } else {
            const ext = fileSj.split('.').pop().toLowerCase();
            const hdr = `<div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center"><i class="fa-solid fa-file-lines text-sm"></i></div>
                    <div class="leading-tight">
                        <p class="text-xs font-extrabold text-gray-800">Surat Jalan</p>
                        <p class="text-[10px] text-gray-400 font-mono truncate max-w-[180px]">${fileSj}</p>
                    </div>
                </div>
                <a href="${fileSj}" target="_blank" class="text-[11px] font-bold text-sky-600 bg-sky-50 px-3 py-1.5 rounded-full">Buka Full</a>
            </div>`;
            if (ext === 'pdf') {
                previewHtml = `<div class="mt-3">${hdr}<div class="rounded-2xl border border-gray-100 overflow-hidden bg-white shadow-sm"><iframe src="${fileSj}" class="w-full" style="height:55vh; min-height:340px;"></iframe></div></div>`;
            } else if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                previewHtml = `<div class="mt-3">${hdr}<div class="rounded-2xl border border-gray-100 overflow-hidden bg-white shadow-sm"><img src="${fileSj}" onclick="openImgModal('${fileSj}')" class="w-full cursor-zoom-in" style="height:55vh; min-height:340px; object-fit:contain; background:#fff;" /></div><p class="text-[10px] text-gray-400 mt-2 text-center">Tap gambar untuk zoom</p></div>`;
            } else {
                previewHtml = `<div class="mt-3">${hdr}<div class="text-[11px] text-gray-500 bg-gray-50 border border-gray-100 rounded-2xl p-3">File tidak bisa dipreview. Klik <b>Buka Full</b>.</div></div>`;
            }
        }

        let html = `
            <div class="text-xs text-gray-500 space-y-1">
                <div class="flex justify-between"><span>${tanggal} • ${jam}</span><span>${items.length} item</span></div>
                <div class="flex justify-between"><span class="text-gray-400">Supplier</span><span class="font-semibold text-gray-700">${supplier}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">No SJ</span><span class="font-mono text-[10px] text-gray-600">${noSj}</span></div>
            </div>
            ${previewHtml}
            <div class="mt-4 space-y-2">`;

        if (!items.length) {
            html += `<div class="text-xs text-gray-400 py-6 text-center">Tidak ada detail barang</div>`;
        } else {
            items.forEach(i => {
                const icon = i.kategori_icon || "fa-box";
                const kategori = i.nama_kategori || "Tanpa Kategori";
                const clr = colorMap[i.kategori_color] || {
                    bg: "bg-gray-100",
                    text: "text-gray-600"
                };
                html += `
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 ${clr.bg} ${clr.text}">
                                <i class="fa-solid ${icon} text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">${i.nama_barang ?? '-'}</p>
                                <div class="flex items-center gap-2">
                                    <p class="text-[10px] text-gray-400 font-mono truncate">${i.kode_barang ?? ''}</p>
                                    <span class="text-[10px] font-semibold ${clr.text}">• ${kategori}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right ml-3 shrink-0">
                            <p class="text-sm font-bold text-gray-800">+ ${i.qty ?? 0}</p>
                            <p class="text-[10px] text-gray-400">${i.satuan ?? ''}</p>
                        </div>
                    </div>`;
            });
        }

        html += `</div>`;
        document.getElementById('sheetContent').innerHTML = html;
    }

    // Search
    function applySearchFilter() {
        const input = document.getElementById('searchTransaksi');
        if (!input) return;
        const keyword = input.value.toLowerCase().trim();

        document.querySelectorAll('.groupTanggal').forEach(group => {
            let ada = false;
            group.querySelectorAll('.transaksi-item').forEach(item => {
                const ref = (item.dataset.ref || '').toLowerCase();
                const tanggal = (item.dataset.tanggal || '').toLowerCase();
                const jam = (item.dataset.jam || '').toLowerCase();
                const supplier = (item.dataset.supplier || '').toLowerCase();
                const noSj = (item.dataset.no_sj || '').toLowerCase();
                const ringkasan = (item.querySelector('.ringkasan')?.innerText || '').toLowerCase();
                const kategoriText = (item.querySelector('.chipsKategori')?.innerText || '').toLowerCase() +
                    ' ' + (item.querySelector('.kategoriSearchText')?.innerText || '').toLowerCase();
                let detailText = '';
                try {
                    const items = JSON.parse(item.dataset.items || '[]');
                    detailText = items.map(i => [i.nama_barang ?? '', i.kode_barang ?? '', i.nama_kategori ?? ''].join(' ')).join(' ').toLowerCase();
                } catch (e) {}

                const cocok = keyword === '' || ref.includes(keyword) || ringkasan.includes(keyword) ||
                    kategoriText.includes(keyword) || detailText.includes(keyword) ||
                    supplier.includes(keyword) || noSj.includes(keyword) ||
                    tanggal.includes(keyword) || jam.includes(keyword);

                item.style.display = cocok ? '' : 'none';
                if (cocok) ada = true;
            });
            group.style.display = ada ? '' : 'none';
        });
    }

    // Auto refresh
    async function refreshList() {
        try {
            const res = await fetch('barang_masuk_list_ajax.php', {
                cache: 'no-store'
            });
            if (!res.ok) return;
            document.getElementById('listContainer').innerHTML = await res.text();
            applySearchFilter();
        } catch (e) {}
    }

    let refreshTimer = null;

    function startAutoRefresh() {
        if (!refreshTimer) refreshTimer = setInterval(refreshList, 3000);
    }

    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    document.getElementById('searchTransaksi').addEventListener('input', function() {
        applySearchFilter();
        this.value.trim() ? stopAutoRefresh() : (startAutoRefresh(), refreshList());
    });

    // Stop saat navigasi
    window.addEventListener('beforeunload', stopAutoRefresh);
    window.addEventListener('pagehide', stopAutoRefresh);
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopAutoRefresh();
        else {
            startAutoRefresh();
            refreshList();
        }
    });

    refreshList();
    startAutoRefresh();
</script>

<?php include 'footer.php'; ?>