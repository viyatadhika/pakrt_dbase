<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user      = $_SESSION['user'];
$canUpdate = in_array($user['role'], ['petugas', 'teknisi', 'admin']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ID tidak valid");

$stmt = $conn->prepare("
    SELECT 
        lk.*,
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
    WHERE lk.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$data) die("Data tidak ditemukan");

/* ================= FOTO ================= */
// Path wargart untuk display di browser
define('WARGART_URL', '../wargart/');

$fotos = ['kerusakan' => [], 'perbaikan' => []];

$stmtFoto = $conn->prepare("
    SELECT jenis, foto_path
    FROM laporan_kerusakan_fotos
    WHERE laporan_id = ?
    ORDER BY uploaded_at ASC
");
$stmtFoto->bind_param("i", $id);
$stmtFoto->execute();
$resFoto = $stmtFoto->get_result();

while ($r = $resFoto->fetch_assoc()) {
    // Konversi path agar bisa diakses dari folder Pak RT
    $path = WARGART_URL . $r['foto_path'];
    if ($r['jenis'] === 'awal')    $fotos['kerusakan'][] = $path;
    if ($r['jenis'] === 'selesai') $fotos['perbaikan'][] = $path;
}
$stmtFoto->close();

/* ================= LOG ================= */
$logs = [];
$stmtLog = $conn->prepare("
    SELECT aksi, keterangan, actor_nama, created_at
    FROM laporan_kerusakan_log
    WHERE laporan_id = ?
    ORDER BY created_at ASC
");
$stmtLog->bind_param("i", $id);
$stmtLog->execute();
$resLog = $stmtLog->get_result();
while ($row = $resLog->fetch_assoc()) $logs[] = $row;
$stmtLog->close();

$title = "Detail Kerusakan";
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

    .card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 20px;
    }

    .photo-full {
        width: 100%;
        max-height: 320px;
        object-fit: cover;
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        margin-top: 12px;
        cursor: pointer;
    }

    .status-chip {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 999px;
    }

    .status-dilaporkan {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #fed7aa;
    }

    .status-selesai {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }

    .card-wrapper {
        position: relative;
        margin-top: 28px;
    }

    .card-badge {
        position: absolute;
        top: -12px;
        left: 16px;
        background: #fff;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 600;
        color: #0284c7;
        border: 1px solid #bae6fd;
        border-radius: 999px;
    }

    .foto-container {
        border: 2px dashed #bae6fd;
        border-radius: 18px;
        padding: 16px;
        text-align: center;
    }

    #photoModal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .85);
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    #photoModal img {
        max-width: 95%;
        max-height: 90%;
        border-radius: 16px;
    }

    .close {
        position: absolute;
        top: 15px;
        right: 20px;
        color: #fff;
        font-size: 32px;
        cursor: pointer;
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
            <h1 class="text-[17px] font-extrabold text-sky-600 leading-tight truncate">Detail Kerusakan</h1>
            <p class="text-[12px] text-gray-400 font-medium leading-tight">Rincian laporan fasilitas</p>
        </div>
    </div>
    <!-- Status chip di kanan -->
    <div class="absolute top-1/2 -translate-y-1/2 right-4">
        <?php if ($data['status'] === 'selesai'): ?>
            <span class="status-chip status-selesai"><i class="fa-solid fa-check-circle"></i> Selesai</span>
        <?php else: ?>
            <span class="status-chip status-dilaporkan"><i class="fa-solid fa-clock"></i> Dilaporkan</span>
        <?php endif; ?>
    </div>
</header>

<!-- Content -->
<div class="px-4 mb-28" style="margin-top:89px;">

    <!-- LOG AKTIVITAS -->
    <div class="bg-white border rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-400 font-semibold mb-3">Riwayat Aktivitas</p>
        <?php if ($logs): ?>
            <ol class="relative border-l border-sky-200 ml-3 space-y-6">
                <?php foreach ($logs as $log): ?>
                    <li class="ml-4">
                        <div class="flex items-center gap-3">
                            <span class="w-3 h-3 bg-sky-500 rounded-full -ml-[22px]"></span>
                            <p class="text-sm font-semibold text-gray-700">
                                <?= htmlspecialchars(str_replace('_', ' ', $log['aksi'])) ?>
                            </p>
                        </div>
                        <?php if (!empty($log['keterangan'])): ?>
                            <p class="text-sm text-gray-600 mt-1 ml-1"><?= htmlspecialchars($log['keterangan']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-1 ml-1">
                            <?= htmlspecialchars($log['actor_nama']) ?> •
                            <?= date('d M Y H:i', strtotime($log['created_at'])) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="text-xs text-gray-400 italic">Belum ada aktivitas</p>
        <?php endif; ?>
    </div>

    <!-- LAPORAN KERUSAKAN -->
    <div class="card-wrapper">
        <span class="card-badge">Laporan Kerusakan</span>
        <div class="card space-y-3">
            <p class="font-semibold">
                <?= htmlspecialchars($data['nama_kategori']) ?> • <?= htmlspecialchars($data['nama_jenis']) ?>
            </p>
            <p class="text-sm text-gray-600">
                <?= htmlspecialchars($data['tipe_lokasi']) ?> • <?= htmlspecialchars($data['nama_lokasi']) ?>
                <?= $data['nama_lantai']  ? ' • ' . htmlspecialchars($data['nama_lantai'])  : '' ?>
                <?= $data['nama_ruangan'] ? ' • ' . htmlspecialchars($data['nama_ruangan']) : '' ?>
                <?= $data['nomor_kamar']  ? ' • No. ' . htmlspecialchars($data['nomor_kamar']) : '' ?>
            </p>

            <?php if ($fotos['kerusakan']): ?>
                <p class="text-xs text-gray-400 font-semibold mb-1">Foto Kerusakan</p>
                <?php foreach ($fotos['kerusakan'] as $foto): ?>
                    <img src="<?= htmlspecialchars($foto) ?>" class="photo-full js-photo" alt="Foto Kerusakan">
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="bg-slate-50 rounded-xl p-4 text-sm">
                <p class="text-xs text-gray-400 font-semibold mb-2">Deskripsi Kerusakan</p>
                <?= nl2br(htmlspecialchars($data['deskripsi'])) ?>
            </div>
        </div>
    </div>

    <!-- FORM PERBAIKAN (hanya jika belum selesai & bisa update) -->
    <?php if ($canUpdate && $data['status'] === 'dilaporkan'): ?>
        <div class="card-wrapper">
            <span class="card-badge">Laporan Perbaikan</span>
            <form id="formPerbaikan" action="laporan_kerusakan_update.php" method="POST" enctype="multipart/form-data" class="card space-y-5">
                <input type="hidden" name="laporan_id" value="<?= $data['id'] ?>">
                <p class="text-xs text-gray-400 font-semibold mb-1">Upload Foto Perbaikan</p>
                <label for="foto_perbaikan" id="container-foto_perbaikan"
                    class="foto-container cursor-pointer block">
                    <i class="fa-solid fa-upload text-sky-500 mb-1 text-xl"></i>
                    <span class="block text-sm font-medium text-sky-700 mt-1">Foto Perbaikan</span>
                    <input type="file" name="foto_perbaikan[]" id="foto_perbaikan"
                        accept="image/*" class="hidden" multiple>
                    <div id="preview-foto_perbaikan" class="flex flex-wrap gap-2 mt-3 justify-center"></div>
                </label>
                <div>
                    <p class="text-xs text-gray-400 font-semibold mb-2">Deskripsi Perbaikan</p>
                    <textarea name="catatan" rows="4"
                        class="w-full rounded-xl border border-sky-200 p-3 bg-white/50 focus:ring-2 focus:ring-sky-300"
                        placeholder="Jelaskan perbaikan..." required></textarea>
                </div>
                <button class="btn-primary w-full">Simpan Perbaikan</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- HASIL PERBAIKAN (jika sudah selesai) -->
    <?php if ($data['status'] === 'selesai'): ?>
        <div class="card-wrapper">
            <span class="card-badge">Laporan Perbaikan</span>
            <div class="card space-y-4">
                <?php if ($fotos['perbaikan']): ?>
                    <p class="text-xs text-gray-400 font-semibold mb-1">Foto Perbaikan</p>
                    <?php foreach ($fotos['perbaikan'] as $foto): ?>
                        <img src="<?= htmlspecialchars($foto) ?>" class="photo-full js-photo" alt="Foto Perbaikan">
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="bg-emerald-50 rounded-xl p-4 text-sm">
                    <p class="text-xs text-gray-400 font-semibold mb-2">Deskripsi Perbaikan</p>
                    <?= nl2br(htmlspecialchars($data['catatan_teknisi'])) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Modal Zoom Foto -->
<div id="photoModal">
    <span class="close" id="photoModalClose">&times;</span>
    <img id="photoModalImg" alt="Preview">
</div>

<?php include 'footer.php'; ?>

<script>
    // Zoom foto
    document.addEventListener('click', e => {
        const img = e.target.closest('.js-photo');
        if (img) {
            document.getElementById('photoModalImg').src = img.src;
            document.getElementById('photoModal').style.display = 'flex';
        }
        if (e.target.id === 'photoModal' || e.target.id === 'photoModalClose') {
            document.getElementById('photoModal').style.display = 'none';
            document.getElementById('photoModalImg').src = '';
        }
    });

    // Validasi form perbaikan
    const form = document.getElementById('formPerbaikan');
    if (form) {
        form.addEventListener('submit', function(e) {
            const inputFoto = document.getElementById('foto_perbaikan');
            if (!inputFoto || inputFoto.files.length === 0) {
                e.preventDefault();
                setTimeout(() => alert('Harap upload Foto Perbaikan sebelum menyimpan.'), 50);
            }
        });
    }
</script>