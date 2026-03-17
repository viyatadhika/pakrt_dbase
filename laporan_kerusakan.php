<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Jakarta');

$user = $_SESSION['user'];
$nip  = $user['nip'] ?? '';

$stmtUser = $conn->prepare("SELECT id FROM users WHERE nip = ?");
$stmtUser->bind_param("s", $nip);
$stmtUser->execute();
$userRow = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();
$userId = (int)$userRow['id'];

$stmt = $conn->prepare("
    SELECT 
        lk.id, lk.status, lk.created_at, lk.updated_at,
        tl.nama AS tipe_lokasi, ml.nama_lokasi, ml2.nama_lantai,
        mr.nama_ruangan, mk.nomor_kamar,
        kk.nama_kategori, jk.nama_jenis
    FROM laporan_kerusakan lk
    LEFT JOIN master_tipe_lokasi tl ON lk.tipe_lokasi_id = tl.id
    LEFT JOIN master_lokasi ml ON lk.lokasi_id = ml.id
    LEFT JOIN master_lantai ml2 ON lk.lantai_id = ml2.id
    LEFT JOIN master_ruangan mr ON lk.ruangan_id = mr.id
    LEFT JOIN master_kamar mk ON lk.kamar_id = mk.id
    LEFT JOIN master_kategori_kerusakan kk ON lk.kategori_kerusakan_id = kk.id
    LEFT JOIN master_jenis_kerusakan jk ON lk.jenis_kerusakan_id = jk.id
    ORDER BY lk.updated_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$laporanDilaporkan = [];
$laporanSelesai    = [];
while ($row = $result->fetch_assoc()) {
    $row['status'] === 'selesai' ? $laporanSelesai[] = $row : $laporanDilaporkan[] = $row;
}

$title = "Daftar Laporan Kerusakan";
include 'header.php';
?>

<style>
    .sticky-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        background: #ffffff;
    }

    .tab-btn {
        flex: 1;
        padding: 10px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 999px;
        background: transparent;
        color: #475569;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all .2s;
    }

    .tab-btn.active {
        background: #0284c7;
        color: #fff;
    }

    .card-report {
        background: #fff;
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
        width: 100%;
    }

    .badge {
        font-size: 10px;
        padding: 4px 10px;
        border-radius: 999px;
        font-weight: 600;
    }

    .badge-yellow {
        background: #fff7ed;
        color: #c2410c;
    }

    .badge-green {
        background: #ecfdf5;
        color: #047857;
    }

    .btn-detail {
        font-size: 11px;
        font-weight: 600;
        background: #e0f2fe;
        color: #0369a1;
        padding: 6px 16px;
        border-radius: 999px;
    }
</style>

<!-- Header — sama dengan timetable & arsip surat -->
<header class="sticky-header px-5 py-4 relative">
    <div class="flex items-center gap-3 min-w-0">
        <a href="javascript:window.history.back()"
            class="w-10 h-10 shrink-0 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div class="min-w-0">
            <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Laporan Kerusakan</h1>
            <p class="text-[12px] text-gray-400 font-medium leading-tight">Rincian fasilitas rusak</p>
        </div>
    </div>
    <button onclick="openExportModal()"
        class="absolute top-5 right-4 w-11 h-11 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition text-lg">
        <i class="fa-solid fa-download text-lg"></i>
    </button>
</header>

<!-- Search + Tab (fixed di bawah header) -->
<div data-fixed-bar style="position:fixed; top:73px; left:0; right:0; z-index:48; background:#fff;">
    <div class="px-4 pt-3 pb-2">
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
            </div>
            <input type="text" id="mainSearch"
                placeholder="Cari nama, jenis atau tanggal kerusakan..."
                class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-sm focus:bg-white focus:border-sky-300 outline-none transition-all">
        </div>
    </div>
    <!-- Tab -->
    <div class="px-4 pb-3">
        <div class="flex gap-1 bg-slate-100 p-1 rounded-full w-full">
            <button id="tabDilaporkan" class="tab-btn active">
                Dilaporkan
                <span id="countDilaporkan" class="text-[10px] bg-white text-sky-600 font-bold px-2 py-0.5 rounded-full"><?= count($laporanDilaporkan) ?></span>
            </button>
            <button id="tabSelesai" class="tab-btn">
                Selesai
                <span id="countSelesai" class="text-[10px] bg-white text-emerald-600 font-bold px-2 py-0.5 rounded-full"><?= count($laporanSelesai) ?></span>
            </button>
        </div>
    </div>
</div>

<!-- Content -->
<div id="mainContent" class="px-4 mb-28 space-y-4" style="margin-top:73px;">
    <div id="listContainer">
        <p class="text-center text-gray-400 text-xs py-6">Memuat data...</p>
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

<?php include 'footer.php'; ?>

<script>
    let currentPage = 1,
        currentTab = 'dilaporkan',
        currentSearch = '';
    let isLoading = false,
        hasMore = true,
        searchTimer = null;

    // Dynamic offset
    function updateOffset() {
        var header = document.querySelector('header.sticky-header');
        var bar = document.querySelector('[data-fixed-bar]');
        var main = document.getElementById('mainContent');
        if (header && bar && main) {
            main.style.marginTop = (header.offsetHeight + bar.offsetHeight + 4) + 'px';
        }
    }
    window.addEventListener('load', function() {
        setTimeout(updateOffset, 150);
    });
    window.addEventListener('resize', updateOffset);

    // Load Data
    async function loadData(reset = false) {
        if (isLoading || (!hasMore && !reset)) return;
        isLoading = true;
        if (reset) {
            currentPage = 1;
            hasMore = true;
            document.getElementById('listContainer').innerHTML = '<p class="text-center text-gray-400 text-xs py-6">Memuat data...</p>';
        }
        try {
            const params = new URLSearchParams({
                page: currentPage,
                status: currentTab,
                q: currentSearch
            });
            const res = await fetch('laporan_kerusakan_list_ajax.php?' + params, {
                cache: 'no-store'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (reset) document.getElementById('listContainer').innerHTML = '';
            renderRows(data.rows);
            hasMore = data.hasMore;
            currentPage = data.page + 1;
            updateBadge(data.total);
            const container = document.getElementById('listContainer');
            if (container.querySelectorAll('.laporan-item').length === 0 && !data.hasMore) {
                container.innerHTML = '<p class="text-center text-gray-400 text-sm py-10">Data tidak ditemukan</p>';
            }
        } catch (e) {
            console.error(e);
        } finally {
            isLoading = false;
            document.getElementById('loadMoreSpinner')?.remove();
        }
    }

    function renderRows(rows) {
        const container = document.getElementById('listContainer');
        document.getElementById('loadMoreSpinner')?.remove();
        rows.forEach(row => {
            const lokasi = row.lokasi.join(' • ');
            const icon = row.is_selesai ? '✅' : '⛔';
            const label = row.is_selesai ? 'Selesai' : 'Dilaporkan';
            const badgeClass = row.is_selesai ? 'badge badge-green' : 'badge badge-yellow';
            const card = document.createElement('div');
            card.className = 'card-report space-y-2 mb-4 laporan-item';
            card.dataset.status = row.status;
            card.innerHTML = `
                <p class="text-sm font-semibold">${escHtml(row.nama_kategori)} <span class="text-gray-400 mx-1">•</span> ${escHtml(row.nama_jenis)}</p>
                <p class="text-xs text-gray-500">${escHtml(lokasi)}</p>
                ${row.deskripsi ? `<p class="text-xs text-gray-600 line-clamp-2">${escHtml(row.deskripsi)}</p>` : ''}
                <p class="text-[11px] text-gray-400">${icon} ${label}: ${escHtml(row.tanggal)}</p>
                <div class="flex justify-between items-center pt-2">
                    <span class="${badgeClass}">${label}</span>
                    <a href="laporan_kerusakan_detail.php?id=${row.id}" class="btn-detail">Detail</a>
                </div>`;
            container.appendChild(card);
        });
        if (hasMore) {
            const spinner = document.createElement('div');
            spinner.id = 'loadMoreSpinner';
            spinner.className = 'text-center py-4 text-gray-400 text-xs';
            spinner.textContent = 'Memuat lebih banyak...';
            container.appendChild(spinner);
        }
    }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function updateBadge(total) {
        const el = currentTab === 'dilaporkan' ? 'countDilaporkan' : 'countSelesai';
        document.getElementById(el).textContent = total;
    }

    // Infinite scroll
    window.addEventListener('scroll', () => {
        if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 300) loadData();
    });

    // Tab
    document.getElementById('tabDilaporkan').onclick = () => {
        document.getElementById('tabDilaporkan').classList.add('active');
        document.getElementById('tabSelesai').classList.remove('active');
        currentTab = 'dilaporkan';
        loadData(true);
    };
    document.getElementById('tabSelesai').onclick = () => {
        document.getElementById('tabSelesai').classList.add('active');
        document.getElementById('tabDilaporkan').classList.remove('active');
        currentTab = 'selesai';
        loadData(true);
    };

    // Search
    document.getElementById('mainSearch').addEventListener('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentSearch = this.value.trim();
            loadData(true);
        }, 400);
    });

    // Export
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
        const from = document.getElementById('exportFrom').value,
            to = document.getElementById('exportTo').value;
        if (from && to && from > to) {
            alert("Tanggal 'Dari' tidak boleh lebih besar dari 'Sampai'");
            return;
        }
        const p = [];
        if (from) p.push('from=' + encodeURIComponent(from));
        if (to) p.push('to=' + encodeURIComponent(to));
        window.location.href = 'laporan_kerusakan_export.php' + (p.length ? '?' + p.join('&') : '');
    }

    // Stop saat navigasi
    window.addEventListener('beforeunload', () => {
        isLoading = true;
    });
    window.addEventListener('pagehide', () => {
        isLoading = true;
    });

    document.addEventListener('DOMContentLoaded', () => loadData(true));
</script>