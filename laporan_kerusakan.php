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
        tl.nama AS tipe_lokasi,
        ml.nama_lokasi,
        ml2.nama_lantai,
        mr.nama_ruangan,
        mk.nomor_kamar,
        kk.nama_kategori,
        jk.nama_jenis
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
$laporanSelesai = [];

while ($row = $result->fetch_assoc()) {
    $row['status'] === 'selesai'
        ? $laporanSelesai[] = $row
        : $laporanDilaporkan[] = $row;
}

include 'header.php';


function indoMonthShort($m)
{
    $map = [1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'mei', 6 => 'jun', 7 => 'jul', 8 => 'agu', 9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'des'];
    return $map[(int)$m] ?? '';
}
function indoMonthLong($m)
{
    $map = [1 => 'januari', 2 => 'februari', 3 => 'maret', 4 => 'april', 5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'agustus', 9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'desember'];
    return $map[(int)$m] ?? '';
}


?>

<style>
    /* === FULL WIDTH DESKTOP === */
    .page-container {
        width: 100%;
        padding: 0 16px;
    }

    @media (min-width: 768px) {
        .page-container {
            padding: 0 32px;
        }
    }

    @media (min-width: 1280px) {
        .page-container {
            padding: 0 20px;
        }
    }

    .header-container {
        position: sticky;
        top: 0;
        z-index: 50;
        background: #fff
    }

    .tab-btn {
        flex: 1;
        padding: 10px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        border: none;
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

    .hidden {
        display: none;
    }
</style>

<!-- Header -->
<div class="header-container">
    <header class="px-4 py-4 flex items-center justify-between bg-white">
        <div class="flex items-center gap-4">
            <a href="javascript:window.history.back()" class="w-10 h-10 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-lg font-extrabold text-sky-600 leading-tight">Daftar Laporan Kerusakan</h1>
                <p class="text-[11px] text-gray-500 font-medium">Rincian fasilitas rusak</p>
            </div>
        </div>

        <!-- DOWNLOAD -->
        <button onclick="openExportModal()" class="w-10 h-10 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition">
            <i class="fa-solid fa-download text-lg"></i>
        </button>

    </header>

    <!-- Search -->
    <div class="px-4 pt-2 pb-2 bg-white">
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
            </div>
            <input type="text" id="mainSearch" placeholder="Cari nama, jenis atau tanggal kerusakan..."
                class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-sm focus:bg-white focus:border-sky-300 focus:ring-4 focus:ring-sky-50 outline-none transition-all"
                onkeyup="cariKerusakan(this.value)">
        </div>
    </div>

    <!-- TAB TENGAH -->
    <div class="mt-3 px-4">
        <div class="flex justify-center">
            <div class="flex gap-2 bg-slate-100 p-1 rounded-full w-full max-w-md">
                <button id="tabDilaporkan" class="tab-btn active flex items-center justify-center gap-2 flex-1">
                    Dilaporkan
                    <span id="countDilaporkan" class="text-[10px] bg-white text-sky-600 font-bold px-2 py-0.5 rounded-full">
                        <?= count($laporanDilaporkan) ?>
                    </span>


                </button>

                <button id="tabSelesai" class="tab-btn flex items-center justify-center gap-2 flex-1">
                    Selesai
                    <span id="countSelesai" class="text-[10px] bg-white text-emerald-600 font-bold px-2 py-0.5 rounded-full">
                        <?= count($laporanSelesai) ?>
                    </span>


                </button>
            </div>
        </div>
    </div>

</div>

<div class="page-container mt-3 mb-28 space-y-6">
    <div id="listContainer">
        <div class="text-xs text-gray-400 py-8 text-center">Memuat data...</div>
    </div>
</div>


<!-- MODAL EXPORT LAPORAN -->
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
                    <input type="date" id="exportFrom"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Sampai Tanggal</label>
                    <input type="date" id="exportTo"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>

                <button onclick="downloadExport()"
                    class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">
                    Download PDF
                </button>

                <p class="text-[10px] text-gray-400 text-center">
                    Default otomatis 30 hari terakhir
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    // ================================================================
    // STATE
    // ================================================================
    let currentPage = 1;
    let currentTab = 'dilaporkan';
    let currentSearch = '';
    let isLoading = false;
    let hasMore = true;
    let searchTimer = null;

    // ================================================================
    // LOAD DATA
    // ================================================================
    async function loadData(reset = false) {
        if (isLoading) return;
        if (!hasMore && !reset) return;

        isLoading = true;

        if (reset) {
            currentPage = 1;
            hasMore = true;
            document.getElementById('listContainer').innerHTML =
                '<p class="text-center text-gray-400 text-xs py-6">Memuat data...</p>';
        }

        try {
            const params = new URLSearchParams({
                page: currentPage,
                status: currentTab,
                q: currentSearch,
            });

            const res = await fetch('laporan_kerusakan_list_ajax.php?' + params, {
                cache: 'no-store'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();

            if (reset) {
                document.getElementById('listContainer').innerHTML = '';
            }

            renderRows(data.rows, reset);

            hasMore = data.hasMore;
            currentPage = data.page + 1;

            // Update badge
            updateBadge(data.total);

            // Empty state
            const container = document.getElementById('listContainer');
            if (container.querySelectorAll('.laporan-item').length === 0 && !data.hasMore) {
                container.innerHTML =
                    '<p class="text-center text-gray-400 text-sm py-10">Data tidak ditemukan</p>';
            }

        } catch (e) {
            console.error('loadData error:', e);
        } finally {
            isLoading = false;
            document.getElementById('loadMoreSpinner')?.remove();
        }
    }

    // ================================================================
    // RENDER KARTU
    // ================================================================
    function renderRows(rows, reset) {
        const container = document.getElementById('listContainer');

        // Hapus spinner lama
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
                <p class="text-sm font-semibold">
                    ${escHtml(row.nama_kategori)}
                    <span class="text-gray-400 mx-1">•</span>
                    ${escHtml(row.nama_jenis)}
                </p>
                <p class="text-xs text-gray-500">${escHtml(lokasi)}</p>
                ${row.deskripsi ? `<p class="text-xs text-gray-600 line-clamp-2">${escHtml(row.deskripsi)}</p>` : ''}
                <p class="text-[11px] text-gray-400">${icon} ${label}: ${escHtml(row.tanggal)}</p>
                <div class="flex justify-between items-center pt-2">
                    <span class="${badgeClass}">${label}</span>
                    <a href="laporan_kerusakan_detail.php?id=${row.id}" class="btn-detail">Detail</a>
                </div>
            `;
            container.appendChild(card);
        });

        // Spinner "load more" jika masih ada data
        if (hasMore) {
            const spinner = document.createElement('div');
            spinner.id = 'loadMoreSpinner';
            spinner.className = 'text-center py-4 text-gray-400 text-xs';
            spinner.textContent = 'Memuat lebih banyak...';
            container.appendChild(spinner);
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================
    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function updateBadge(total) {
        if (currentTab === 'dilaporkan') {
            document.getElementById('countDilaporkan').textContent = total;
        } else {
            document.getElementById('countSelesai').textContent = total;
        }
    }

    // ================================================================
    // INFINITE SCROLL
    // ================================================================
    window.addEventListener('scroll', () => {
        const scrolled = window.innerHeight + window.scrollY;
        const threshold = document.body.offsetHeight - 300;
        if (scrolled >= threshold) loadData();
    });

    // ================================================================
    // TAB
    // ================================================================
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

    // ================================================================
    // SEARCH — debounce 400ms
    // ================================================================
    document.getElementById('mainSearch').addEventListener('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentSearch = this.value.trim();
            loadData(true);
        }, 400);
    });

    // ================================================================
    // EXPORT MODAL
    // ================================================================
    function openExportModal() {
        const modal = document.getElementById('exportModal');
        modal.classList.remove('hidden');
        const today = new Date();
        const past = new Date();
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
        let url = 'laporan_kerusakan_export.php';
        const p = [];
        if (from) p.push('from=' + encodeURIComponent(from));
        if (to) p.push('to=' + encodeURIComponent(to));
        if (p.length) url += '?' + p.join('&');
        window.location.href = url;
    }

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', () => loadData(true));
</script>

<!-- <script>
    const tabDilaporkan = document.getElementById("tabDilaporkan");
    const tabSelesai = document.getElementById("tabSelesai");
    const contentDilaporkan = document.getElementById("contentDilaporkan");
    const contentSelesai = document.getElementById("contentSelesai");

    tabDilaporkan.onclick = () => {
        tabDilaporkan.classList.add("active");
        tabSelesai.classList.remove("active");
        contentDilaporkan.classList.remove("hidden");
        contentSelesai.classList.add("hidden");
    };

    tabSelesai.onclick = () => {
        tabSelesai.classList.add("active");
        tabDilaporkan.classList.remove("active");
        contentSelesai.classList.remove("hidden");
        contentDilaporkan.classList.add("hidden");
    };

    function openExportModal() {
        const modal = document.getElementById('exportModal');
        modal.classList.remove('hidden');

        // default: 30 hari terakhir
        const today = new Date();
        const past = new Date();
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

        let url = "laporan_kerusakan_export.php";
        const params = [];

        if (from) params.push("from=" + encodeURIComponent(from));
        if (to) params.push("to=" + encodeURIComponent(to));

        if (params.length) url += "?" + params.join("&");

        window.location.href = url;
    }

    function updateTabCounts() {
        const countDil = document.getElementById('countDilaporkan');
        const countSel = document.getElementById('countSelesai');

        let dil = 0,
            sel = 0;

        document.querySelectorAll('.laporan-item').forEach(item => {
            // item dianggap "tampil" kalau display bukan none
            if (item.style.display === 'none') return;

            const st = item.dataset.status;
            if (st === 'selesai') sel++;
            else dil++;
        });

        if (countDil) countDil.textContent = dil;
        if (countSel) countSel.textContent = sel;
    }

    let refreshTimer = null;

    function startAutoRefresh() {
        if (refreshTimer) return;
        refreshTimer = setInterval(refreshList, 3000);
    }

    function stopAutoRefresh() {
        if (!refreshTimer) return;
        clearInterval(refreshTimer);
        refreshTimer = null;
    }

    async function refreshList() {
        try {
            const res = await fetch('laporan_kerusakan_list_ajax.php', {
                cache: 'no-store'
            });
            if (!res.ok) return;

            const html = await res.text();
            document.getElementById('listContainer').innerHTML = html;

            // setelah reload list, apply search lagi biar konsisten
            const q = document.getElementById('mainSearch')?.value || '';
            cariKerusakan(q);

            // kalau tab "Selesai" sedang aktif, tetap tampilkan
            const tabDil = document.getElementById("tabDilaporkan");
            const tabSel = document.getElementById("tabSelesai");
            const contentDil = document.getElementById("contentDilaporkan");
            const contentSel = document.getElementById("contentSelesai");

            if (tabSel && tabSel.classList.contains('active')) {
                contentSel?.classList.remove("hidden");
                contentDil?.classList.add("hidden");
            } else {
                contentDil?.classList.remove("hidden");
                contentSel?.classList.add("hidden");
            }
        } catch (e) {}
    }

    // Search kamu (tetap pakai yang sekarang)
    function normalizeText(str) {
        return (str || '').toLowerCase().replace(/[\s\/\-:]/g, '');
    }

    function cariKerusakan(val) {
        const keyword = (val || '').toLowerCase().trim();
        const keywordNormalized = normalizeText(keyword);

        const items = document.querySelectorAll('.laporan-item');
        const badgeDil = document.getElementById('countDilaporkan');
        const badgeSel = document.getElementById('countSelesai');
        const emptyState = document.getElementById('emptyState');

        let countDil = 0,
            countSel = 0,
            totalMatch = 0;

        items.forEach(item => {
            const text = item.dataset.search || '';
            const textNormalized = normalizeText(text);
            const status = item.dataset.status || '';

            const cocok = keyword === '' || text.includes(keyword) || textNormalized.includes(keywordNormalized);
            item.style.display = cocok ? '' : 'none';

            if (cocok) {
                totalMatch++;
                if (status === 'selesai') countSel++;
                else countDil++;
            }
        });

        if (badgeDil) badgeDil.textContent = countDil;
        if (badgeSel) badgeSel.textContent = countSel;

        if (emptyState) {
            emptyState.classList.toggle('hidden', totalMatch > 0);
            emptyState.classList.toggle('flex', totalMatch === 0);
        }
    }

    // STOP refresh saat mengetik
    document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById('mainSearch');
        if (input) {
            input.addEventListener('input', function() {
                cariKerusakan(this.value);
                if (this.value.trim() !== '') stopAutoRefresh();
                else {
                    startAutoRefresh();
                    refreshList();
                }
            });
        }

        refreshList();
        startAutoRefresh();
    });
</script> -->

<?php include 'footer.php'; ?>