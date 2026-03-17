<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Jakarta');

$user        = $_SESSION['user'];
$role        = strtolower($user['role'] ?? '');
$dicatatOleh = trim($user['nama'] ?? ($user['nama_lengkap'] ?? 'Petugas'));
$canEdit     = in_array($role, ['admin', 'security']);
$pesan       = '';
$pesanTipe   = '';

// ── POST: Catat Masuk Tamu ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'masuk') {
    if (!$canEdit) {
        $pesan = 'Anda tidak memiliki akses.';
        $pesanTipe = 'error';
    } else {
        $plat     = strtoupper(trim($_POST['plat_nomor']   ?? ''));
        $instansi = trim($_POST['instansi_tamu'] ?? '');
        $tujuan   = trim($_POST['tujuan']        ?? '');
        if ($plat === '') {
            $pesan = 'Plat nomor wajib diisi.';
            $pesanTipe = 'error';
        } else {
            $cek = $conn->prepare("SELECT id FROM kendaraan_log WHERE plat_nomor = ? AND status = 'masuk' LIMIT 1");
            $cek->bind_param('s', $plat);
            $cek->execute();
            $sudahMasuk = $cek->get_result()->num_rows > 0;
            $cek->close();
            if ($sudahMasuk) {
                $pesan = "Kendaraan $plat masih tercatat di dalam.";
                $pesanTipe = 'error';
            } else {
                $now  = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("INSERT INTO kendaraan_log (plat_nomor, instansi_tamu, tujuan, waktu_masuk, status, dicatat_oleh) VALUES (?, ?, ?, ?, 'masuk', ?)");
                $stmt->bind_param('sssss', $plat, $instansi, $tujuan, $now, $dicatatOleh);
                $ok = $stmt->execute();
                $pesan = $ok ? "✅ Kendaraan $plat berhasil dicatat MASUK." : 'Gagal menyimpan.';
                $pesanTipe = $ok ? 'sukses' : 'error';
                $stmt->close();
            }
        }
    }
}

// ── POST: Catat Keluar Tamu ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'keluar') {
    if (!$canEdit) {
        $pesan = 'Anda tidak memiliki akses.';
        $pesanTipe = 'error';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $now  = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE kendaraan_log SET status = 'keluar', waktu_keluar = ? WHERE id = ? AND status = 'masuk'");
            $stmt->bind_param('si', $now, $id);
            $stmt->execute();
            $pesan = $stmt->affected_rows > 0 ? '✅ Kendaraan berhasil dicatat KELUAR.' : 'Data tidak ditemukan.';
            $pesanTipe = $stmt->affected_rows > 0 ? 'sukses' : 'error';
            $stmt->close();
        }
    }
}

// ── Badge counts ──────────────────────────────────────────────
$cntTamuMasuk  = (int)$conn->query("SELECT COUNT(*) FROM kendaraan_log WHERE status='masuk'")->fetch_row()[0];
$cntTamuKeluar = (int)$conn->query("SELECT COUNT(*) FROM kendaraan_log WHERE status='keluar' AND DATE(waktu_masuk)>=CURDATE()-INTERVAL 30 DAY")->fetch_row()[0];
$cntOpsDiLuar  = (int)$conn->query("SELECT COUNT(*) FROM kendaraan_operasional_log WHERE status='keluar'")->fetch_row()[0];

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

    .page-container {
        width: 100%;
        padding: 0 16px;
    }

    @media(min-width:768px) {
        .page-container {
            padding: 0 32px;
        }
    }

    .main-tab-btn {
        flex: 1;
        padding: 10px 8px;
        font-size: 12px;
        font-weight: 700;
        border-radius: 999px;
        background: transparent;
        color: #64748b;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        transition: all .2s;
    }

    .main-tab-btn.active-sky {
        background: #0284c7;
        color: #fff;
    }

    .main-tab-btn.active-amber {
        background: #d97706;
        color: #fff;
    }

    .sub-tab-wrap {
        display: flex;
        gap: 8px;
        padding: 0 16px 10px;
    }

    .sub-tab-wrap.hidden {
        display: none !important;
    }

    .sub-tab-btn {
        flex: 1;
        padding: 7px 10px;
        font-size: 11px;
        font-weight: 700;
        border-radius: 999px;
        border: 1.5px solid transparent;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        transition: all .2s;
    }

    .sub-tab-btn.sub-sky-active {
        background: #e0f2fe;
        color: #0284c7;
        border-color: #7dd3fc;
    }

    .sub-tab-btn.sub-sky-inactive {
        background: #f8fafc;
        color: #94a3b8;
        border-color: #e2e8f0;
    }

    .sub-tab-btn.sub-amber-active {
        background: #fef3c7;
        color: #d97706;
        border-color: #fcd34d;
    }

    .sub-tab-btn.sub-amber-inactive {
        background: #f8fafc;
        color: #94a3b8;
        border-color: #e2e8f0;
    }

    .card-kend {
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

    .badge-amber {
        background: #fffbeb;
        color: #b45309;
    }

    .btn-keluar-card {
        font-size: 11px;
        font-weight: 600;
        background: #fee2e2;
        color: #dc2626;
        padding: 6px 14px;
        border-radius: 999px;
        border: none;
        cursor: pointer;
    }

    .btn-kembali-card {
        font-size: 11px;
        font-weight: 600;
        background: #d1fae5;
        color: #065f46;
        padding: 6px 14px;
        border-radius: 999px;
        border: none;
        cursor: pointer;
    }

    .field-wrap {
        margin-bottom: 14px;
    }

    .field-label {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 5px;
        display: block;
    }

    .field-input {
        width: 100%;
        padding: 12px 14px;
        border: 1.5px solid #e2e8f0;
        border-radius: 14px;
        font-size: 13px;
        background: #f8fafc;
        outline: none;
        box-sizing: border-box;
        transition: border-color .2s;
    }

    .field-input:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, .12);
        background: #fff;
    }

    .field-input.plat {
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 2px;
        font-size: 15px;
    }

    textarea.field-input {
        resize: vertical;
        min-height: 60px;
    }

    .btn-submit {
        width: 100%;
        padding: 14px;
        border-radius: 16px;
        background: #0284c7;
        color: #fff;
        font-weight: 800;
        font-size: 14px;
        border: none;
        cursor: pointer;
    }

    .btn-submit-amber {
        background: #d97706;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 14px;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .alert-sukses {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .5);
        z-index: 999;
        display: none;
        align-items: flex-end;
        justify-content: center;
        padding: 16px;
    }

    .modal-overlay.show {
        display: flex;
    }

    .modal-box {
        background: #fff;
        border-radius: 28px;
        padding: 24px 20px 28px;
        width: 100%;
        max-width: 420px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .2);
        animation: slideUp .25s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(60px);
            opacity: 0
        }

        to {
            transform: translateY(0);
            opacity: 1
        }
    }

    .modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 18px;
    }

    .modal-title {
        font-size: 15px;
        font-weight: 800;
        color: #1e293b;
    }

    .modal-sub {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }

    .modal-close {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #f1f5f9;
        border: none;
        cursor: pointer;
        color: #64748b;
        font-size: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .cnt-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        padding: 1px 7px;
        border-radius: 999px;
    }
</style>

<!-- Header — sama dengan timetable & arsip surat -->
<header class="sticky-header px-5 py-4 relative">
    <div class="flex items-center gap-3 min-w-0">
        <button onclick="window.history.back()"
            class="w-10 h-10 shrink-0 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </button>
        <div class="min-w-0">
            <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Pencatatan Kendaraan</h1>
            <p class="text-[12px] text-gray-400 font-medium leading-tight">Tamu &amp; Operasional</p>
        </div>
    </div>
    <button onclick="openDownloadModal()"
        class="absolute top-5 right-4 w-11 h-11 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition text-lg">
        <i class="fa-solid fa-download text-lg"></i>
    </button>
</header>

<!-- Search + Tabs (fixed di bawah header) -->
<div data-fixed-bar style="position:fixed; top:73px; left:0; right:0; z-index:48; background:#fff;">
    <div class="px-4 pt-3 pb-2">
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
            </div>
            <input type="text" id="mainSearch" placeholder="Cari semua kendaraan..."
                class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-sm focus:bg-white focus:border-sky-300 outline-none transition-all">
        </div>
    </div>
    <!-- 2 Main Tab -->
    <div class="px-4 pb-2">
        <div class="flex gap-1 bg-slate-100 p-1 rounded-full w-full">
            <button id="mainTabTamu" class="main-tab-btn active-sky" onclick="switchMainTab('tamu')">
                <i class="fa-solid fa-car text-[11px]"></i> Tamu / Pengunjung
            </button>
            <button id="mainTabOps" class="main-tab-btn" onclick="switchMainTab('ops')">
                <i class="fa-solid fa-truck-fast text-[11px]"></i> Operasional
                <span id="cntOpsMain" class="cnt-badge bg-amber-100 text-amber-700"></span>
            </button>
        </div>
    </div>
    <!-- Sub-tab Tamu -->
    <div id="subTabTamu" class="sub-tab-wrap">
        <button id="stTamuMasuk" class="sub-tab-btn sub-sky-active" onclick="switchSubTab('tamu','masuk')">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Di Dalam
            <span id="cntTamuMasuk" class="cnt-badge bg-sky-100 text-sky-700"></span>
        </button>
        <button id="stTamuKeluar" class="sub-tab-btn sub-sky-inactive" onclick="switchSubTab('tamu','keluar')">
            <i class="fa-solid fa-clock-rotate-left"></i> Riwayat Keluar
            <span id="cntTamuKeluar" class="cnt-badge bg-slate-100 text-slate-500"></span>
        </button>
    </div>
    <!-- Sub-tab Operasional -->
    <div id="subTabOps" class="sub-tab-wrap hidden">
        <button id="stOpsDiLuar" class="sub-tab-btn sub-amber-active" onclick="switchSubTab('ops','keluar')">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
            <span id="cntOpsDiLuar" class="cnt-badge bg-amber-100 text-amber-700"></span>
        </button>
        <button id="stOpsKembali" class="sub-tab-btn sub-amber-inactive" onclick="switchSubTab('ops','kembali')">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Sudah Kembali
            <span id="cntOpsKembali" class="cnt-badge bg-slate-100 text-slate-500"></span>
        </button>
    </div>
</div>

<script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            var h1 = document.querySelector('header.sticky-header')?.offsetHeight || 73;
            var h2 = document.querySelector('[data-fixed-bar]')?.offsetHeight || 150;
            var total = h1 + h2 + 12;

            var alert = document.getElementById('alertBox');
            var list = document.getElementById('listContainer');
            if (alert) alert.style.marginTop = total + 'px';
            if (list) list.style.marginTop = total + 'px';

            // Update juga saat tab switch
            ['switchMainTab', 'switchSubTab'].forEach(fn => {
                var orig = window[fn];
                if (orig) window[fn] = function() {
                    orig.apply(this, arguments);
                    setTimeout(function() {
                        var h2new = document.querySelector('[data-fixed-bar]')?.offsetHeight || 150;
                        var t = h1 + h2new + 12;
                        if (list) list.style.marginTop = t + 'px';
                    }, 50);
                };
            });
        }, 150);
    });
</script>

<!-- ===== ALERT ===== -->
<?php if ($pesan): ?>
    <div class="page-container mt-2">
        <div class="alert alert-<?= $pesanTipe ?>"><?= htmlspecialchars($pesan) ?></div>
    </div>
<?php endif; ?>

<!-- ===== LIST ===== -->
<div class="page-container mt-3 mb-28">
    <div id="listContainer">
        <p class="text-center text-gray-400 text-xs py-8">Memuat data...</p>
    </div>
</div>

<!-- FAB -->
<?php if ($canEdit): ?>
    <button id="fabTamu" onclick="openFormModal()" title="Catat Kendaraan Tamu Masuk"
        class="fixed bottom-8 right-8 w-11 h-11 bg-sky-600 text-white rounded-full shadow-lg flex items-center justify-center z-[40] active:scale-90 transition-all">
        <i class="fa-solid fa-plus text-lg"></i>
    </button>
    <button id="fabOps" onclick="openFormOpsModal()" title="Catat Kendaraan Operasional Keluar"
        class="fixed bottom-8 right-8 w-11 h-11 bg-amber-500 text-white rounded-full shadow-lg flex items-center justify-center z-[40] active:scale-90 transition-all hidden">
        <i class="fa-solid fa-plus text-lg"></i>
    </button>
<?php endif; ?>

<!-- ===== MODAL TAMU MASUK ===== -->
<?php if ($canEdit): ?>
    <div id="modalForm" class="modal-overlay" onclick="if(event.target===this) closeFormModal()">
        <div class="modal-box">
            <div class="modal-head">
                <div>
                    <div class="modal-title"><i class="fa-solid fa-car" style="color:#0284c7;margin-right:6px;"></i>Catat Kendaraan Masuk</div>
                    <div class="modal-sub">Kendaraan tamu yang baru masuk area</div>
                </div>
                <button class="modal-close" onclick="closeFormModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi" value="masuk">
                <div class="field-wrap">
                    <label class="field-label">PLAT NOMOR <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="plat_nomor" id="inputPlat" class="field-input plat"
                        placeholder="Contoh: B 1234 ABC" required maxlength="20"
                        oninput="this.value=this.value.toUpperCase()">
                </div>
                <div class="field-wrap">
                    <label class="field-label">INSTANSI / NAMA TAMU</label>
                    <input type="text" name="instansi_tamu" class="field-input"
                        placeholder="Contoh: PT. Maju Jaya / Budi Santoso" maxlength="100">
                </div>
                <div class="field-wrap">
                    <label class="field-label">TUJUAN / KEPERLUAN</label>
                    <input type="text" name="tujuan" class="field-input"
                        placeholder="Contoh: Pengiriman barang, Rapat..." maxlength="255">
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>&nbsp; Catat Masuk
                </button>
            </form>
        </div>
    </div>

    <!-- ===== MODAL OPERASIONAL KELUAR ===== -->
    <div id="modalFormOps" class="modal-overlay" onclick="if(event.target===this) closeFormOpsModal()">
        <div class="modal-box">
            <div class="modal-head">
                <div>
                    <div class="modal-title"><i class="fa-solid fa-truck-fast" style="color:#d97706;margin-right:6px;"></i>Catat Kendaraan Keluar</div>
                    <div class="modal-sub">Kendaraan operasional / dinas keluar area</div>
                </div>
                <button class="modal-close" onclick="closeFormOpsModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="opsAlert" class="alert hidden mb-3"></div>
            <div class="field-wrap">
                <label class="field-label">PLAT NOMOR <span style="color:#dc2626;">*</span></label>
                <input type="text" id="opsPlat" class="field-input plat"
                    placeholder="Contoh: B 9876 ZZZ" maxlength="20"
                    oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="field-wrap">
                <label class="field-label">NAMA PENGEMUDI <span style="color:#dc2626;">*</span></label>
                <input type="text" id="opsPengemudi" class="field-input" placeholder="Nama pengemudi" maxlength="100">
            </div>
            <div class="field-wrap">
                <label class="field-label">TUJUAN PERJALANAN</label>
                <input type="text" id="opsTujuan" class="field-input" placeholder="Contoh: Kantor Pusat..." maxlength="255">
            </div>
            <button type="button" onclick="submitOpsKeluar()" class="btn-submit btn-submit-amber">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>&nbsp; Catat Keluar
            </button>
        </div>
    </div>
<?php endif; ?>

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
                <button onclick="closeDownloadModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600"><i class="fa-solid fa-xmark"></i></button>
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

<script>
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    let currentMain = 'tamu'; // 'tamu' | 'ops'
    let currentSub = 'masuk'; // tamu: 'masuk'|'keluar'  ops: 'keluar'|'kembali'
    let currentPage = 1;
    let currentSearch = '';
    let isLoading = false;
    let hasMore = true;
    let searchTimer = null;

    function escHtml(str) {
        return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Load Data ──────────────────────────────────────────────
    async function loadData(reset = false) {
        if (currentSearch !== '') {
            // Mode search: fetch semua tab sekaligus
            await loadSearchAll(reset);
            return;
        }

        if (isLoading) return;
        if (!hasMore && !reset) return;
        isLoading = true;

        var container = document.getElementById('listContainer');
        if (reset) {
            currentPage = 1;
            hasMore = true;
            container.innerHTML = '<p class="text-center text-gray-400 text-xs py-6">Memuat data...</p>';
        }

        try {
            var params = new URLSearchParams({
                page: currentPage,
                q: '',
                status: currentSub
            });
            var url = currentMain === 'ops' ? 'kendaraan_operasional_list_ajax.php' : 'kendaraan_list_ajax.php';
            var res = await fetch(url + '?' + params, {
                cache: 'no-store',
                credentials: 'same-origin'
            });
            var data = await res.json();

            if (reset) container.innerHTML = '';

            if (currentMain === 'ops') {
                renderOpsRows(data.rows);
                if (currentSub === 'keluar') {
                    document.getElementById('cntOpsDiLuar').textContent = data.total;
                    var mainBadge = document.getElementById('cntOpsMain');
                    if (mainBadge) mainBadge.textContent = data.total > 0 ? data.total : '';
                    // Update badge Sudah Kembali sekaligus
                    var elLawan = document.getElementById('cntOpsKembali');
                    if (elLawan) elLawan.textContent = data.totalLawan ?? '';
                } else {
                    document.getElementById('cntOpsKembali').textContent = data.total;
                    // Update badge Keluar sekaligus
                    document.getElementById('cntOpsDiLuar').textContent = data.totalLawan ?? '';
                    var mainBadge = document.getElementById('cntOpsMain');
                    if (mainBadge) mainBadge.textContent = data.totalLawan > 0 ? data.totalLawan : '';
                }
            } else {
                renderTamuRows(data.rows);
                if (currentSub === 'masuk') {
                    document.getElementById('cntTamuMasuk').textContent = data.total;
                    document.getElementById('cntTamuKeluar').textContent = data.totalLawan ?? '?';
                } else {
                    document.getElementById('cntTamuKeluar').textContent = data.total;
                    document.getElementById('cntTamuMasuk').textContent = data.totalLawan ?? '?';
                }
            }

            hasMore = data.hasMore;
            currentPage = data.page + 1;

            if (!container.querySelector('.kend-item')) {
                container.innerHTML = '<p class="text-center text-gray-400 text-sm py-10">Tidak ada data</p>';
            } else if (hasMore) {
                appendSpinner();
            }

        } catch (e) {
            console.error('loadData error:', e);
            container.innerHTML = '<p class="text-center text-red-400 text-xs py-8">Gagal memuat data</p>';
        } finally {
            isLoading = false;
        }
    }

    // ── Search Semua Tab ───────────────────────────────────────
    async function loadSearchAll(reset = false) {
        if (isLoading) return;
        isLoading = true;

        var container = document.getElementById('listContainer');
        if (reset) {
            container.innerHTML = '<p class="text-center text-gray-400 text-xs py-6">Mencari...</p>';
        }

        try {
            var q = currentSearch;
            var paramTamuMasuk = new URLSearchParams({
                page: 1,
                q: q,
                status: 'masuk'
            });
            var paramTamuKeluar = new URLSearchParams({
                page: 1,
                q: q,
                status: 'keluar'
            });
            var paramOpsKeluar = new URLSearchParams({
                page: 1,
                q: q,
                status: 'keluar'
            });
            var paramOpsKembali = new URLSearchParams({
                page: 1,
                q: q,
                status: 'kembali'
            });

            var [resTamuMasuk, resTamuKeluar, resOpsKeluar, resOpsKembali] = await Promise.all([
                fetch('kendaraan_list_ajax.php?' + paramTamuMasuk, {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }),
                fetch('kendaraan_list_ajax.php?' + paramTamuKeluar, {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }),
                fetch('kendaraan_operasional_list_ajax.php?' + paramOpsKeluar, {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }),
                fetch('kendaraan_operasional_list_ajax.php?' + paramOpsKembali, {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }),
            ]);

            var [dTamuMasuk, dTamuKeluar, dOpsKeluar, dOpsKembali] = await Promise.all([
                resTamuMasuk.json(), resTamuKeluar.json(),
                resOpsKeluar.json(), resOpsKembali.json(),
            ]);

            container.innerHTML = '';
            hasMore = false; // search tidak pakai pagination

            var total = dTamuMasuk.rows.length + dTamuKeluar.rows.length + dOpsKeluar.rows.length + dOpsKembali.rows.length;

            if (total === 0) {
                container.innerHTML = '<p class="text-center text-gray-400 text-sm py-10">Tidak ada hasil untuk <strong>' + escHtml(q) + '</strong></p>';
                return;
            }

            // ── Tamu Di Dalam ──
            if (dTamuMasuk.rows.length > 0) {
                container.appendChild(makeSectionHeader('Tamu — Di Dalam', dTamuMasuk.rows.length, 'sky'));
                renderTamuRows(dTamuMasuk.rows);
            }
            // ── Tamu Riwayat Keluar ──
            if (dTamuKeluar.rows.length > 0) {
                container.appendChild(makeSectionHeader('Tamu — Riwayat Keluar', dTamuKeluar.rows.length, 'emerald'));
                renderTamuRows(dTamuKeluar.rows);
            }
            // ── Ops Di Luar ──
            if (dOpsKeluar.rows.length > 0) {
                container.appendChild(makeSectionHeader('Operasional — Keluar', dOpsKeluar.rows.length, 'amber'));
                renderOpsRows(dOpsKeluar.rows);
            }
            // ── Ops Sudah Kembali ──
            if (dOpsKembali.rows.length > 0) {
                container.appendChild(makeSectionHeader('Operasional — Sudah Kembali', dOpsKembali.rows.length, 'green'));
                renderOpsRows(dOpsKembali.rows);
            }

        } catch (e) {
            console.error('searchAll error:', e);
            container.innerHTML = '<p class="text-center text-red-400 text-xs py-8">Gagal mencari data</p>';
        } finally {
            isLoading = false;
        }
    }

    function makeSectionHeader(label, count, color) {
        var colors = {
            sky: 'text-sky-600 bg-sky-50 border-sky-200',
            emerald: 'text-emerald-600 bg-emerald-50 border-emerald-200',
            amber: 'text-amber-600 bg-amber-50 border-amber-200',
            green: 'text-emerald-700 bg-emerald-50 border-emerald-200',
        };
        var cls = colors[color] || colors.sky;
        var el = document.createElement('div');
        el.className = 'flex items-center gap-2 mb-3 mt-1';
        el.innerHTML = '<span class="text-[11px] font-bold px-3 py-1 rounded-full border ' + cls + '">' +
            escHtml(label) + ' <span class="opacity-70">(' + count + ')</span></span>' +
            '<div class="flex-1 h-px bg-gray-100"></div>';
        return el;
    }

    // ── Render Kartu Tamu ──────────────────────────────────────
    function renderTamuRows(rows) {
        var container = document.getElementById('listContainer');
        rows.forEach(function(row) {
            var isMasuk = row.status === 'masuk';
            var actionHtml = '';
            if (isMasuk && CAN_EDIT) {
                actionHtml = '<form method="POST" onsubmit="return confirm(&apos;Catat kendaraan ini keluar sekarang?&apos;)">' +
                    '<input type="hidden" name="aksi" value="keluar">' +
                    '<input type="hidden" name="id" value="' + row.id + '">' +
                    '<button type="submit" class="btn-keluar-card"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar</button>' +
                    '</form>';
            } else if (!isMasuk) {
                actionHtml = '<span class="text-[10px] text-gray-400 font-semibold"><i class="fa-solid fa-stopwatch"></i> ' + escHtml(row.durasi) + '</span>';
            }

            var html = '<div class="flex items-start justify-between gap-2">' +
                '<p class="text-base font-extrabold text-sky-700 tracking-widest">' + escHtml(row.plat_nomor) + '</p>' +
                '<span class="badge ' + (isMasuk ? 'badge-yellow' : 'badge-green') + '">' + (isMasuk ? 'Di Dalam' : 'Sudah Keluar') + '</span>' +
                '</div>';
            if (row.instansi_tamu) html += '<p class="text-xs text-gray-600"><i class="fa-solid fa-building w-3 mr-1"></i>' + escHtml(row.instansi_tamu) + '</p>';
            if (row.tujuan) html += '<p class="text-xs text-gray-500"><i class="fa-solid fa-location-dot w-3 mr-1"></i>' + escHtml(row.tujuan) + '</p>';
            html += '<p class="text-[11px] text-gray-400">' + (isMasuk ? '🚗 Masuk' : '✅ Keluar') + ': ' + escHtml(row.tanggal_display) + '</p>';
            if (!isMasuk && row.tanggal_masuk_display) {
                html += '<p class="text-[11px] text-gray-400"><i class="fa-solid fa-arrow-right-to-bracket" style="color:#0284c7;"></i> Masuk: ' + escHtml(row.tanggal_masuk_display) + '</p>';
            }
            html += '<div class="flex justify-between items-center pt-1">' +
                '<p class="text-[10px] text-gray-400"><i class="fa-solid fa-user w-3"></i> ' + escHtml(row.dicatat_oleh) + '</p>' +
                actionHtml + '</div>';

            var card = document.createElement('div');
            card.className = 'card-kend space-y-2 mb-4 kend-item';
            card.innerHTML = html;
            document.getElementById('listContainer').appendChild(card);
        });
    }

    // ── Render Kartu Operasional ───────────────────────────────
    function renderOpsRows(rows) {
        var container = document.getElementById('listContainer');
        rows.forEach(function(row) {
            var isKeluar = row.status === 'keluar';
            var actionHtml = '';
            if (isKeluar && CAN_EDIT) {
                actionHtml = '<button class="btn-kembali-card" data-id="' + row.id + '" data-plat="' + escHtml(row.plat_nomor) + '" onclick="konfirmasiKembali(this)">' +
                    '<i class="fa-solid fa-arrow-right-to-bracket"></i> Kembali</button>';
            } else if (!isKeluar) {
                actionHtml = '<span class="text-[10px] text-gray-400 font-semibold"><i class="fa-solid fa-stopwatch"></i> ' + escHtml(row.durasi) + '</span>';
            }

            var html = '<div class="flex items-start justify-between gap-2 flex-wrap">' +
                '<p class="text-base font-extrabold text-amber-700 tracking-widest">' + escHtml(row.plat_nomor) + '</p>' +
                '<span class="badge ' + (isKeluar ? 'badge-amber' : 'badge-green') + '">' + (isKeluar ? 'Di Luar' : 'Sudah Kembali') + '</span>' +
                '</div>';
            if (row.pengemudi) html += '<p class="text-xs text-gray-600"><i class="fa-solid fa-user w-3 mr-1"></i>' + escHtml(row.pengemudi) + '</p>';
            if (row.tujuan) html += '<p class="text-xs text-gray-500"><i class="fa-solid fa-location-dot w-3 mr-1"></i>' + escHtml(row.tujuan) + '</p>';

            html += '<p class="text-[11px] text-gray-400"><i class="fa-solid fa-arrow-right-from-bracket" style="color:#d97706;"></i> Keluar: ' + escHtml(row.tanggal_display) + '</p>';
            if (!isKeluar && row.kembali_display) {
                html += '<p class="text-[11px] text-emerald-600 font-semibold"><i class="fa-solid fa-arrow-right-to-bracket"></i> Kembali: ' + escHtml(row.kembali_display) + '</p>';
            }
            html += '<div class="flex justify-between items-center pt-1">' +
                '<p class="text-[10px] text-gray-400"><i class="fa-solid fa-user w-3"></i> ' + escHtml(row.dicatat_oleh) + '</p>' +
                actionHtml + '</div>';

            var card = document.createElement('div');
            card.className = 'card-kend space-y-2 mb-4 kend-item';
            card.innerHTML = html;
            container.appendChild(card);
        });
    }

    function appendSpinner() {
        var s = document.createElement('div');
        s.id = 'loadMoreSpinner';
        s.className = 'text-center py-4 text-gray-400 text-xs';
        s.textContent = 'Memuat lebih banyak...';
        document.getElementById('listContainer').appendChild(s);
    }

    // ── Switch Main Tab ────────────────────────────────────────
    function switchMainTab(main) {
        currentMain = main;
        currentSearch = '';
        var searchEl = document.getElementById('mainSearch');
        searchEl.value = '';

        document.getElementById('mainTabTamu').className = 'main-tab-btn' + (main === 'tamu' ? ' active-sky' : '');
        document.getElementById('mainTabOps').className = 'main-tab-btn' + (main === 'ops' ? ' active-amber' : '');

        document.getElementById('subTabTamu').classList.toggle('hidden', main !== 'tamu');
        document.getElementById('subTabOps').classList.toggle('hidden', main !== 'ops');

        var fabTamu = document.getElementById('fabTamu');
        var fabOps = document.getElementById('fabOps');
        if (fabTamu && fabOps) {
            fabTamu.classList.toggle('hidden', main === 'ops');
            fabOps.classList.toggle('hidden', main !== 'ops');
        }

        // Set default sub
        if (main === 'tamu') {
            currentSub = 'masuk';
            setSubStyle('tamu', 'masuk');
        } else {
            currentSub = 'keluar';
            setSubStyle('ops', 'keluar');
        }
        loadData(true);
    }

    // ── Switch Sub Tab ─────────────────────────────────────────
    function switchSubTab(main, sub) {
        currentSub = sub;
        currentSearch = '';
        document.getElementById('mainSearch').value = '';
        setSubStyle(main, sub);
        loadData(true);
    }

    function setSubStyle(main, sub) {
        if (main === 'tamu') {
            document.getElementById('stTamuMasuk').className = 'sub-tab-btn ' + (sub === 'masuk' ? 'sub-sky-active' : 'sub-sky-inactive');
            document.getElementById('stTamuKeluar').className = 'sub-tab-btn ' + (sub === 'keluar' ? 'sub-sky-active' : 'sub-sky-inactive');
        } else {
            document.getElementById('stOpsDiLuar').className = 'sub-tab-btn ' + (sub === 'keluar' ? 'sub-amber-active' : 'sub-amber-inactive');
            document.getElementById('stOpsKembali').className = 'sub-tab-btn ' + (sub === 'kembali' ? 'sub-amber-active' : 'sub-amber-inactive');
        }
    }

    // ── Konfirmasi Kembali ─────────────────────────────────────
    async function konfirmasiKembali(btn) {
        var id = btn.dataset.id,
            plat = btn.dataset.plat;
        if (!confirm('Catat kendaraan ' + plat + ' sudah KEMBALI sekarang?')) return;
        try {
            var res = await fetch('kendaraan_operasional_simpan.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    aksi: 'kembali',
                    id: parseInt(id)
                })
            });
            var json = await res.json();
            json.status === 'ok' ? loadData(true) : alert(json.message || 'Gagal');
        } catch (e) {
            alert('Koneksi gagal');
        }
    }

    // ── Submit Operasional Keluar ──────────────────────────────
    async function submitOpsKeluar() {
        var plat = document.getElementById('opsPlat').value.trim().toUpperCase();
        var drv = document.getElementById('opsPengemudi').value.trim();
        var dst = document.getElementById('opsTujuan').value.trim();
        document.getElementById('opsAlert').classList.add('hidden');
        if (!plat) {
            showOpsAlert('Plat nomor wajib diisi', 'error');
            return;
        }
        if (!drv) {
            showOpsAlert('Nama pengemudi wajib diisi', 'error');
            return;
        }
        try {
            var res = await fetch('kendaraan_operasional_simpan.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    aksi: 'keluar',
                    plat_nomor: plat,
                    pengemudi: drv,
                    tujuan: dst
                })
            });
            var json = await res.json();
            if (json.status === 'ok') {
                closeFormOpsModal();
                loadData(true);
            } else showOpsAlert(json.message || 'Gagal menyimpan', 'error');
        } catch (e) {
            showOpsAlert('Koneksi gagal', 'error');
        }
    }

    function showOpsAlert(msg, tipe) {
        var el = document.getElementById('opsAlert');
        el.className = 'alert alert-' + tipe + ' mb-3';
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    // ── Infinite Scroll ────────────────────────────────────────
    window.addEventListener('scroll', function() {
        if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 300) loadData();
    });

    // ── Search ─────────────────────────────────────────────────
    document.getElementById('mainSearch').addEventListener('input', function() {
        clearTimeout(searchTimer);
        var q = this.value.trim();
        searchTimer = setTimeout(function() {
            currentSearch = q;
            loadData(true);
        }, 400);
    });
    document.getElementById('mainSearch').addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            currentSearch = '';
            loadData(true);
        }
    });

    // ── Modals ─────────────────────────────────────────────────
    function openFormModal() {
        if (!CAN_EDIT) return;
        document.getElementById('modalForm').classList.add('show');
        setTimeout(function() {
            document.getElementById('inputPlat').focus();
        }, 200);
    }

    function closeFormModal() {
        document.getElementById('modalForm').classList.remove('show');
    }

    function openFormOpsModal() {
        if (!CAN_EDIT) return;
        ['opsPlat', 'opsPengemudi', 'opsTujuan'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('opsAlert').classList.add('hidden');
        document.getElementById('modalFormOps').classList.add('show');
        setTimeout(function() {
            document.getElementById('opsPlat').focus();
        }, 200);
    }

    function closeFormOpsModal() {
        document.getElementById('modalFormOps').classList.remove('show');
    }

    function openDownloadModal() {
        var today = new Date(),
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
        var from = document.getElementById('dlFrom').value,
            to = document.getElementById('dlTo').value;
        if (from && to && from > to) {
            alert("Tanggal 'Dari' tidak boleh lebih besar dari 'Sampai'");
            return;
        }
        var p = [];
        if (from) p.push('from=' + encodeURIComponent(from));
        if (to) p.push('to=' + encodeURIComponent(to));
        window.location.href = 'kendaraan_export.php' + (p.length ? '?' + p.join('&') : '');
        closeDownloadModal();
    }

    // ── Init ───────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($pesanTipe === 'error' && $canEdit): ?>openFormModal();
    <?php endif; ?>
    loadData(true);
    });
</script>

<?php include 'footer.php'; ?>