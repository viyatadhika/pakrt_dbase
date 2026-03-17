<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Daftar Tamu";
include 'header.php';
include 'config.php';

date_default_timezone_set('Asia/Jakarta');

$counts = ['semua' => 0, 'pelayanan_umum' => 0, 'pelayanan_informasi' => 0, 'pelayanan_pengaduan' => 0];
$res = $conn->query("SELECT jenis_layanan, COUNT(*) AS cnt FROM buku_tamu GROUP BY jenis_layanan");
while ($r = $res->fetch_assoc()) {
    $counts[$r['jenis_layanan']] = (int)$r['cnt'];
    $counts['semua'] += (int)$r['cnt'];
}
?>

<style>
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

    .jenis-tab.active {
        background: #0284c7;
        color: #fff;
    }

    .spinner {
        display: none;
    }

    .spinner.show {
        display: flex;
    }
</style>

<!-- Header -->
<header class="sticky-header px-5 py-4 relative">
    <div class="flex items-center gap-3 min-w-0">
        <a href="javascript:window.history.back()"
            class="w-10 h-10 shrink-0 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div class="min-w-0">
            <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Daftar Tamu</h1>
            <p class="text-[12px] text-gray-400 font-medium leading-tight">Riwayat kunjungan tamu</p>
        </div>
    </div>
    <button onclick="openDownloadModal()"
        class="absolute top-5 right-4 w-11 h-11 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition text-lg">
        <i class="fa-solid fa-download text-lg"></i>
    </button>
</header>

<!-- Search & Filter (di luar header) -->
<div data-fixed-bar style="position:fixed; top:73px; left:0; right:0; z-index:48; background:#fff;">
    <div class="px-4 pt-3 pb-2">
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
            </div>
            <input type="text" id="mainSearch"
                placeholder="Cari nama, instansi, email..."
                class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-sm focus:bg-white focus:border-sky-300 outline-none transition-all">
        </div>
    </div>
    <!-- Filter Tabs -->
    <div class="flex gap-2 px-4 pb-3 overflow-x-auto scrollbar-hide" id="tabContainer">
        <button type="button" class="jenis-tab active shrink-0 px-5 py-2 rounded-2xl text-xs font-semibold border border-transparent transition flex items-center gap-1.5"
            data-jenis="" onclick="switchTab('', this)">
            Semua
            <span id="badge-semua" class="bg-white/30 text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"><?= $counts['semua'] ?></span>
        </button>
        <button type="button" class="jenis-tab bg-gray-50 text-gray-600 shrink-0 px-5 py-2 rounded-2xl text-xs font-semibold border border-transparent transition flex items-center gap-1.5"
            data-jenis="pelayanan_umum" onclick="switchTab('pelayanan_umum', this)">
            Pelayanan Umum
            <span id="badge-pelayanan_umum" class="bg-sky-100 text-sky-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"><?= $counts['pelayanan_umum'] ?></span>
        </button>
        <button type="button" class="jenis-tab bg-gray-50 text-gray-600 shrink-0 px-5 py-2 rounded-2xl text-xs font-semibold border border-transparent transition flex items-center gap-1.5"
            data-jenis="pelayanan_informasi" onclick="switchTab('pelayanan_informasi', this)">
            Pelayanan Informasi
            <span id="badge-pelayanan_informasi" class="bg-amber-100 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"><?= $counts['pelayanan_informasi'] ?></span>
        </button>
        <button type="button" class="jenis-tab bg-gray-50 text-gray-600 shrink-0 px-5 py-2 rounded-2xl text-xs font-semibold border border-transparent transition flex items-center gap-1.5"
            data-jenis="pelayanan_pengaduan" onclick="switchTab('pelayanan_pengaduan', this)">
            Pelayanan Pengaduan
            <span id="badge-pelayanan_pengaduan" class="bg-red-100 text-red-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"><?= $counts['pelayanan_pengaduan'] ?></span>
        </button>
    </div>
</div>

<!-- Main List — offset: 73px header + ~110px searchbar+tabs -->
<main id="listTamu" class="px-4 py-4 mb-28 bg-white" id="mainContent" style="margin-top:183px;">

    <div id="emptyState" class="hidden flex-col items-center justify-center py-20 px-6 text-center">
        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-6">
            <i class="fa-solid fa-users text-3xl text-gray-200"></i>
        </div>
        <h3 class="text-gray-800 font-bold text-sm">Tidak ada data ditemukan</h3>
        <p class="text-gray-400 text-xs mt-2 leading-relaxed">Coba kata kunci atau filter lain.</p>
        <button type="button" onclick="resetTampilan()" class="mt-8 text-sky-600 font-bold text-sm hover:underline">Tampilkan Semua</button>
    </div>

    <div id="sectionsWrapper" class="space-y-8"></div>

    <div id="spinner" class="spinner items-center justify-center py-8">
        <i class="fa-solid fa-circle-notch animate-spin text-sky-400 text-2xl"></i>
    </div>

    <div id="endMsg" class="hidden text-center py-6 text-[11px] text-gray-400 font-medium">
        Semua data sudah ditampilkan
    </div>
</main>

<!-- ===== MODAL DETAIL ===== -->
<div id="detailModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeDetail()"></div>
    <div class="fixed inset-0 flex items-end justify-center">
        <div class="w-full max-w-md bg-white rounded-t-3xl shadow-xl max-h-[92vh] overflow-y-auto">
            <div class="p-5">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <p class="text-sm font-extrabold text-gray-800">Detail Kunjungan</p>
                        <p class="text-[11px] text-gray-500" id="detailWaktu">-</p>
                    </div>
                    <button onclick="closeDetail()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div id="detailBadge" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold mb-5"></div>
                <div class="space-y-3">
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-2xl">
                        <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center border border-gray-100 shrink-0">
                            <i class="fa-solid fa-user text-sky-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide">Nama Lengkap</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="detailNama">-</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-2xl">
                        <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center border border-gray-100 shrink-0">
                            <i class="fa-solid fa-building text-sky-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide">Instansi / Unit Kerja</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="detailAsal">-</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-2xl">
                        <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center border border-gray-100 shrink-0">
                            <i class="fa-solid fa-envelope text-sky-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide">Email</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="detailEmail">-</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-2xl">
                        <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center border border-gray-100 shrink-0">
                            <i class="fa-solid fa-phone text-sky-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide">No. Handphone</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="detailHp">-</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-2xl">
                        <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center border border-gray-100 shrink-0">
                            <i class="fa-solid fa-file-lines text-sky-500 text-xs"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide">Tujuan / Keperluan</p>
                            <p class="text-sm text-gray-700 mt-0.5 leading-relaxed" id="detailKeperluan">-</p>
                        </div>
                    </div>
                </div>
                <button id="btnHapusTamu" type="button" onclick="hapusTamu()"
                    class="w-full mt-5 py-3 rounded-2xl bg-red-50 text-red-600 font-extrabold text-sm">
                    <i class="fa-solid fa-trash-can mr-2"></i> Hapus Data Tamu
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL DOWNLOAD ===== -->
<div id="modalDownload" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeDownloadModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-extrabold text-gray-800">Download Laporan</p>
                    <p class="text-[11px] text-gray-500">Pilih rentang tanggal</p>
                </div>
                <button onclick="closeDownloadModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-bold text-gray-600">Dari Tanggal</label>
                    <input type="date" id="dlFrom" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600">Sampai Tanggal</label>
                    <input type="date" id="dlTo" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>
                <button onclick="doDownload()" class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Download PDF</button>
                <p class="text-[10px] text-gray-400 text-center">Default otomatis 30 hari terakhir</p>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    let currentPage = 1,
        currentJenis = '',
        currentSearch = '';
    let isLoading = false,
        hasMore = true,
        searchDebounce = null,
        currentDetailId = null;

    const sectionsWrapper = document.getElementById('sectionsWrapper');
    const spinner = document.getElementById('spinner');
    const emptyState = document.getElementById('emptyState');
    const endMsg = document.getElementById('endMsg');

    const jenisColorMap = {
        pelayanan_umum: {
            icon: 'bg-sky-50 text-sky-600',
            badge: 'bg-sky-50 text-sky-600',
            kode: 'text-sky-500'
        },
        pelayanan_informasi: {
            icon: 'bg-amber-50 text-amber-600',
            badge: 'bg-amber-50 text-amber-600',
            kode: 'text-amber-500'
        },
        pelayanan_pengaduan: {
            icon: 'bg-red-50 text-red-600',
            badge: 'bg-red-50 text-red-600',
            kode: 'text-red-500'
        },
    };
    const jenisIconMap = {
        pelayanan_umum: 'fa-users',
        pelayanan_informasi: 'fa-circle-info',
        pelayanan_pengaduan: 'fa-bullhorn'
    };
    const jenisLabelMap = {
        pelayanan_umum: 'Pelayanan Umum',
        pelayanan_informasi: 'Pelayanan Informasi',
        pelayanan_pengaduan: 'Pelayanan Pengaduan'
    };

    async function loadData(reset = false) {
        if (isLoading || (!hasMore && !reset)) return;
        if (reset) {
            currentPage = 1;
            hasMore = true;
            sectionsWrapper.innerHTML = '';
            endMsg.classList.add('hidden');
            emptyState.classList.add('hidden');
            emptyState.classList.remove('flex');
        }
        isLoading = true;
        spinner.classList.add('show');
        try {
            const url = `daftar_tamu_ajax.php?page=${currentPage}&jenis=${encodeURIComponent(currentJenis)}&q=${encodeURIComponent(currentSearch)}`;
            const res = await fetch(url);
            const data = await res.json();
            renderRows(data.rows);
            hasMore = data.hasMore;
            currentPage++;
            if (data.counts) Object.keys(data.counts).forEach(k => {
                const el = document.getElementById('badge-' + k);
                if (el) el.textContent = data.counts[k];
            });
            if (data.total === 0) {
                emptyState.classList.remove('hidden');
                emptyState.classList.add('flex');
            }
            if (!hasMore && data.total > 0) endMsg.classList.remove('hidden');
        } catch (e) {
            console.error(e);
        } finally {
            isLoading = false;
            spinner.classList.remove('show');
        }
    }

    function renderRows(rows) {
        rows.forEach(r => {
            const tgl = r.tanggal_key;
            const warna = jenisColorMap[r.jenis_layanan] || jenisColorMap['pelayanan_umum'];
            const icon = jenisIconMap[r.jenis_layanan] || 'fa-user';
            const label = jenisLabelMap[r.jenis_layanan] || '-';
            let sec = sectionsWrapper.querySelector(`.tgl-section[data-tgl="${tgl}"]`);
            if (!sec) {
                sec = document.createElement('section');
                sec.className = 'tgl-section';
                sec.dataset.tgl = tgl;
                sec.innerHTML = `<div class="flex items-center gap-2 mb-4"><div class="h-5 w-1.5 bg-sky-500 rounded-full"></div><h2 class="font-bold text-gray-800 text-sm">${r.tanggal_label}</h2></div><div class="space-y-3 tgl-list"></div>`;
                sectionsWrapper.appendChild(sec);
            }
            const list = sec.querySelector('.tgl-list');
            const detail = JSON.stringify({
                id: r.id,
                nama: r.nama,
                email: r.email,
                asal: r.asal,
                no_hp: r.no_hp,
                jenis: r.jenis_layanan,
                label,
                keperluan: r.keperluan,
                waktu: r.waktu_label
            });
            const card = document.createElement('div');
            card.className = 'item-card bg-white rounded-3xl p-4 flex justify-between items-center border shadow-sm cursor-pointer';
            card.dataset.id = r.id;
            card.dataset.detail = detail;
            card.onclick = function() {
                openDetail(JSON.parse(this.dataset.detail));
            };
            card.innerHTML = `
                <div class="flex gap-4 items-center min-w-0">
                    <div class="w-12 h-12 rounded-2xl ${warna.icon} flex items-center justify-center shrink-0">
                        <i class="fa-solid ${icon} text-xl"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold ${warna.kode}">${label}</span>
                        <h3 class="font-bold text-gray-800 text-sm truncate">${escHtml(r.nama)}</h3>
                        <p class="text-xs text-gray-500 truncate">${escHtml(r.asal)}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-[10px] text-gray-400">${r.jam}</span>
                    <button type="button" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                </div>`;
            card.querySelector('button').onclick = e => {
                e.stopPropagation();
                openDetail(JSON.parse(card.dataset.detail));
            };
            list.appendChild(card);
        });
    }

    function escHtml(s) {
        return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function resetTampilan() {
        currentSearch = '';
        document.getElementById('mainSearch').value = '';
        const allTab = document.querySelectorAll('.jenis-tab')[0];
        if (allTab) switchTab('', allTab);
    }

    function switchTab(jenis, btn) {
        currentJenis = jenis;
        currentSearch = '';
        document.getElementById('mainSearch').value = '';
        document.querySelectorAll('.jenis-tab').forEach(t => {
            t.classList.remove('active');
            t.classList.add('bg-gray-50', 'text-gray-600');
        });
        btn.classList.add('active');
        btn.classList.remove('bg-gray-50', 'text-gray-600');
        loadData(true);
    }

    document.getElementById('mainSearch').addEventListener('input', function() {
        clearTimeout(searchDebounce);
        const val = this.value.trim();
        searchDebounce = setTimeout(() => {
            currentSearch = val;
            loadData(true);
        }, 400);
    });

    window.addEventListener('scroll', () => {
        if (!isLoading && hasMore && window.scrollY + window.innerHeight >= document.body.offsetHeight - 300) loadData();
    });

    function openDetail(data) {
        currentDetailId = data.id;
        document.getElementById('detailNama').textContent = data.nama || '-';
        document.getElementById('detailAsal').textContent = data.asal || '-';
        document.getElementById('detailEmail').textContent = data.email || '-';
        document.getElementById('detailHp').textContent = data.no_hp || '-';
        document.getElementById('detailKeperluan').textContent = data.keperluan || '-';
        document.getElementById('detailWaktu').textContent = data.waktu || '-';
        const warna = jenisColorMap[data.jenis] || jenisColorMap['pelayanan_umum'];
        const icon = jenisIconMap[data.jenis] || 'fa-user';
        const badge = document.getElementById('detailBadge');
        badge.className = `inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold mb-5 ${warna.badge}`;
        badge.innerHTML = `<i class="fa-solid ${icon}"></i> ${data.label}`;
        document.getElementById('detailModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeDetail() {
        document.getElementById('detailModal').classList.add('hidden');
        document.body.style.overflow = '';
        currentDetailId = null;
    }

    function hapusTamu() {
        if (!currentDetailId || !confirm('Hapus data tamu ini?')) return;
        const btn = document.getElementById('btnHapusTamu');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin mr-2"></i> Menghapus...';
        const fd = new FormData();
        fd.append('id', currentDetailId);
        fetch('buku_tamu_hapus.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash-can mr-2"></i> Hapus Data Tamu';
                if (res.status !== 'success') {
                    alert('Gagal: ' + (res.message || 'Error'));
                    return;
                }
                const card = sectionsWrapper.querySelector(`.item-card[data-id="${currentDetailId}"]`);
                if (card) {
                    const sec = card.closest('.tgl-section');
                    card.remove();
                    if (sec && sec.querySelector('.tgl-list').children.length === 0) sec.remove();
                }
                closeDetail();
                loadData(true);
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash-can mr-2"></i> Hapus Data Tamu';
                alert('Network error');
            });
    }

    function openDownloadModal() {
        const today = new Date(),
            past = new Date();
        past.setDate(today.getDate() - 30);
        document.getElementById('dlTo').value = today.toISOString().slice(0, 10);
        document.getElementById('dlFrom').value = past.toISOString().slice(0, 10);
        document.getElementById('modalDownload').classList.remove('hidden');
    }

    function closeDownloadModal() {
        document.getElementById('modalDownload').classList.add('hidden');
    }

    function doDownload() {
        const from = document.getElementById('dlFrom').value,
            to = document.getElementById('dlTo').value;
        if (from && to && from > to) {
            alert("Tanggal 'Dari' tidak boleh lebih besar dari 'Sampai'");
            return;
        }
        const p = [];
        if (from) p.push('from=' + encodeURIComponent(from));
        if (to) p.push('to=' + encodeURIComponent(to));
        window.location.href = 'buku_tamu_export.php' + (p.length ? '?' + p.join('&') : '');
        closeDownloadModal();
    }

    loadData(true);

    // Dynamic offset agar konten tidak tertutup fixed bar
    function updateOffset() {
        var bar = document.querySelector('[data-fixed-bar]');
        var main = document.getElementById('mainContent');
        if (bar && main) main.style.marginTop = (bar.offsetHeight + 73) + 'px';
    }
    window.addEventListener('load', updateOffset);
    window.addEventListener('resize', updateOffset);
</script>