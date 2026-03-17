<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Koreksi Stok";
include 'header.php';
include 'config.php';

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

    .badge-koreksi {
        background: #eff6ff;
        color: #0284c7;
        font-size: 10px;
        font-weight: 800;
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
            <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Koreksi Stok</h1>
            <p class="text-[12px] text-gray-400 font-medium leading-tight">Data 30 hari terakhir (auto update)</p>
        </div>
    </div>
    <button onclick="openExportModal()"
        class="absolute top-5 right-4 w-11 h-11 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition text-lg">
        <i class="fa-solid fa-download text-lg"></i>
    </button>
</header>

<!-- Search -->
<div data-fixed-bar style="position:fixed; top:73px; left:0; right:0; z-index:48; background:#fff;">
    <div class="px-4 pt-3 pb-3">
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
            </div>
            <input type="text" id="searchTransaksi"
                placeholder="Cari ref / alasan / barang / kategori..."
                class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-sm focus:bg-white focus:border-sky-300 outline-none transition-all">
        </div>
    </div>
</div>

<!-- LIST -->
<main id="mainContent" class="px-4 mb-28" style="margin-top:73px;">
    <div id="listContainer" class="py-4">
        <div class="text-xs text-gray-400 py-8 text-center">Memuat data...</div>
    </div>
</main>

<!-- FAB -->
<?php if ($canEdit): ?>
    <a href="koreksi_stok_tambah.php"
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
                <h3 class="font-extrabold text-gray-800 text-sm">Detail Koreksi Stok</h3>
                <p id="sheetRef" class="text-[11px] text-sky-600 font-bold">-</p>
            </div>
            <button onclick="closeSheet()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-500">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
    </div>
    <div id="sheetContent" class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto"></div>
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
        const p = [];
        if (from) p.push("from=" + encodeURIComponent(from));
        if (to) p.push("to=" + encodeURIComponent(to));
        window.location.href = "koreksi_stok_export.php" + (p.length ? "?" + p.join("&") : "");
    }

    function openSheetLocal(el) {
        document.getElementById('sheetOverlay').classList.remove('hidden');
        document.getElementById('sheetDetail').classList.remove('translate-y-full');

        const ref = el.dataset.ref || '-';
        const tanggal = el.dataset.tanggal || '-';
        const jam = el.dataset.jam || '-';
        const jenis = el.dataset.jenis || '-';
        const alasan = el.dataset.alasan || '-';
        const ket = el.dataset.keterangan || '-';

        let items = [];
        try {
            items = JSON.parse(el.dataset.items || "[]");
        } catch (e) {}

        document.getElementById('sheetRef').innerText = ref;

        let html = `
            <div class="text-xs text-gray-500 space-y-1">
                <div class="flex justify-between"><span>${tanggal} • ${jam}</span><span>${items.length} item</span></div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Jenis</span>
                    <span class="font-bold ${jenis === 'tambah' ? 'text-green-600' : 'text-red-600'}">${jenis.toUpperCase()}</span>
                </div>
                <div class="text-[11px] text-gray-500 bg-gray-50 border border-gray-100 rounded-2xl p-3 mt-2">
                    <span class="font-bold text-gray-700">Alasan:</span><br>${alasan}
                </div>
                <div class="text-[11px] text-gray-500 bg-gray-50 border border-gray-100 rounded-2xl p-3 mt-2">
                    <span class="font-bold text-gray-700">Keterangan:</span><br>${ket}
                </div>
            </div>
            <div class="mt-4 space-y-2">`;

        if (!items.length) {
            html += `<div class="text-xs text-gray-400 py-6 text-center">Tidak ada detail koreksi</div>`;
        } else {
            items.forEach(i => {
                html += `
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">${i.nama_barang ?? '-'}</p>
                            <p class="text-[10px] text-gray-400 font-mono truncate">${i.kode_barang ?? ''}</p>
                            <p class="text-[10px] text-sky-600 font-bold">${i.nama_kategori || 'Tanpa Kategori'}</p>
                        </div>
                        <div class="text-right ml-3 shrink-0">
                            <p class="text-sm font-bold ${jenis === 'tambah' ? 'text-green-600' : 'text-red-600'}">
                                ${jenis === 'tambah' ? '+ ' : '- '}${i.qty ?? 0}
                            </p>
                            <p class="text-[10px] text-gray-400">${i.satuan ?? ''}</p>
                        </div>
                    </div>`;
            });
        }

        html += `</div>`;
        document.getElementById('sheetContent').innerHTML = html;
    }

    function applySearchFilter() {
        const keyword = (document.getElementById('searchTransaksi').value || '').toLowerCase().trim();
        document.querySelectorAll('.groupTanggal').forEach(group => {
            let ada = false;
            group.querySelectorAll('.transaksi-item').forEach(item => {
                const ref = (item.dataset.ref || '').toLowerCase();
                const alasan = (item.dataset.alasan || '').toLowerCase();
                const ket = (item.dataset.keterangan || '').toLowerCase();
                const tanggal = (item.dataset.tanggal || '').toLowerCase();
                const jam = (item.dataset.jam || '').toLowerCase();
                let detailText = '';
                try {
                    const items = JSON.parse(item.dataset.items || '[]');
                    detailText = items.map(i => [i.nama_barang ?? '', i.kode_barang ?? '', i.nama_kategori ?? ''].join(' ')).join(' ').toLowerCase();
                } catch (e) {}

                const cocok = keyword === '' || ref.includes(keyword) || alasan.includes(keyword) ||
                    ket.includes(keyword) || detailText.includes(keyword) ||
                    tanggal.includes(keyword) || jam.includes(keyword);

                item.style.display = cocok ? '' : 'none';
                if (cocok) ada = true;
            });
            group.style.display = ada ? '' : 'none';
        });
    }

    async function refreshList() {
        try {
            const res = await fetch('koreksi_stok_list_ajax.php', {
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