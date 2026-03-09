<?php
session_start();
require 'config.php';

/* ================= AUTH ================= */
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$canUpdate = ($user['role'] === 'petugas');

/* ================= VALIDASI ID ================= */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ID tidak valid");

/* ================= DATA LAPORAN ================= */
$stmt = $conn->prepare("
    SELECT 
        lk.*,
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
    WHERE lk.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$data) die("Data tidak ditemukan");

/* ================= FOTO ================= */
$fotos = [
    'kerusakan' => [],
    'perbaikan' => []
];

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

    if ($r['jenis'] === 'awal') {
        $fotos['kerusakan'][] = $r['foto_path'];
    }

    if ($r['jenis'] === 'selesai') {
        $fotos['perbaikan'][] = $r['foto_path'];
    }
}

$stmtFoto->close();



/* ======================================================
   LOG AKTIVITAS
====================================================== */
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

while ($row = $resLog->fetch_assoc()) {
    $logs[] = $row;
}
$stmtLog->close();

include 'header.php';
?>

<style>
    .card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 20px
    }

    .card-muted {
        font-size: 12px;
        color: #64748b
    }

    .photo-full {
        width: 100%;
        max-height: 320px;
        object-fit: cover;
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        margin-top: 12px;
        cursor: pointer
    }

    .status-chip {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 999px
    }

    .status-dilaporkan {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #fed7aa
    }

    .status-selesai {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0
    }

    .card-wrapper {
        position: relative;
        margin-top: 28px
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
        border-radius: 999px
    }

    .foto-container {
        border: 2px dashed #bae6fd;
        border-radius: 18px;
        padding: 16px;
        text-align: center
    }

    #photoModal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .85);
        justify-content: center;
        align-items: center;
        z-index: 9999
    }

    #photoModal img {
        max-width: 95%;
        max-height: 90%;
        border-radius: 16px
    }

    .close {
        position: absolute;
        top: 15px;
        right: 20px;
        color: #fff;
        font-size: 32px;
        cursor: pointer
    }
</style>

<!-- ================= HEADER ================= -->
<div class="seamless-header">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <a href="laporan_kerusakan.php" class="back-btn p-2 bg-white border rounded-xl">
                <i class="fa-solid fa-arrow-left text-sky-600"></i>
            </a>
            <h2 class="font-bold text-lg text-sky-600">Detail Kerusakan</h2>
        </div>

        <?php if ($data['status'] === 'selesai'): ?>
            <span class="status-chip status-selesai">
                <i class="fa-solid fa-check-circle"></i> Selesai
            </span>
        <?php else: ?>
            <span class="status-chip status-dilaporkan">
                <i class="fa-solid fa-clock"></i> Dilaporkan
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="register-container checklist-page mb-28">
    <!-- ===== LOG ===== -->
    <div class="bg-white border rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-400 font-semibold mb-3">Riwayat Aktivitas</p>

        <?php if ($logs): ?>
            <ol class="relative border-l border-sky-200 ml-3 space-y-6">
                <?php foreach ($logs as $log): ?>
                    <li class="ml-4">

                        <!-- DOT + TITLE (SEJAJAR) -->
                        <div class="flex items-center gap-3">
                            <span class="w-3 h-3 bg-sky-500 rounded-full -ml-[22px]"></span>

                            <p class="text-sm font-semibold text-gray-700">
                                <?= htmlspecialchars(str_replace('_', ' ', $log['aksi'])) ?>
                            </p>
                        </div>

                        <!-- KETERANGAN -->
                        <?php if (!empty($log['keterangan'])): ?>
                            <p class="text-sm text-gray-600 mt-1 ml-1">
                                <?= htmlspecialchars($log['keterangan']) ?>
                            </p>
                        <?php endif; ?>

                        <!-- META -->
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





    <!-- ================= LAPORAN KERUSAKAN ================= -->
    <div class="card-wrapper">
        <span class="card-badge">Laporan Kerusakan</span>
        <div class="card space-y-3">

            <p class="font-semibold"><?= htmlspecialchars($data['nama_kategori']) ?> • <?= htmlspecialchars($data['nama_jenis']) ?></p>
            <!-- <p class="text-sm text-gray-600"><?= htmlspecialchars($data['pelapor_nama']) ?> • <?= date('d M Y H:i', strtotime($data['created_at'])) ?></p> -->
            <p class="text-sm text-gray-600">
                <?= htmlspecialchars($data['tipe_lokasi']) ?> • <?= htmlspecialchars($data['nama_lokasi']) ?>
                <?= $data['nama_lantai'] ? ' • ' . $data['nama_lantai'] : '' ?>
                <?= $data['nama_ruangan'] ? ' • ' . $data['nama_ruangan'] : '' ?>
                <?= $data['nomor_kamar'] ? ' • No. ' . $data['nomor_kamar'] : '' ?>
            </p>



            <?php if ($fotos['kerusakan']): ?>
                <p class="text-xs text-gray-400 font-semibold mb-3">Foto Kerusakan</p>
                <?php foreach ($fotos['kerusakan'] as $foto): ?>
                    <img src="<?= htmlspecialchars($foto) ?>" class="photo-full js-photo">
            <?php endforeach;
            endif; ?>

            <div class="bg-slate-50 rounded-xl p-4 text-sm">
                <p class="text-xs text-gray-400 font-semibold mb-3">Deskripsi Kerusakan</p>
                <?= nl2br(htmlspecialchars($data['deskripsi'])) ?>
            </div>

        </div>
    </div>



    <!-- ================= FORM PERBAIKAN ================= -->
    <?php if ($canUpdate && $data['status'] === 'dilaporkan'): ?>
        <div class="card-wrapper">
            <span class="card-badge">Laporan Perbaikan</span>
            <form id="formPerbaikan" action="laporan_kerusakan_update.php" method="POST" enctype="multipart/form-data" class="card space-y-5">
                <input type="hidden" name="laporan_id" value="<?= $data['id'] ?>">
                <p class="text-xs text-gray-400 font-semibold mb-3">Upload Foto Perbaikan</p>
                <label for="foto_perbaikan" id="container-foto_perbaikan"
                    class="foto-container border-2 border-dashed border-sky-300/50 rounded-2xl p-3 text-center cursor-pointer">

                    <i data-lucide="upload" class="w-6 h-6 text-sky-500 mb-1"></i>
                    <span class="text-sm font-medium text-sky-700">Foto Perbaikan</span>

                    <input type="file"
                        name="foto_perbaikan[]"
                        id="foto_perbaikan"
                        accept="image/*"
                        class="hidden"
                        multiple>

                    <div id="preview-foto_perbaikan"
                        class="flex flex-wrap gap-2 mt-3 justify-center"></div>
                </label>


                <section>
                    <p class="text-xs text-gray-400 font-semibold mb-3">Deskripsi Perbaikan</p>
                    <textarea name="catatan" rows="4" class="w-full rounded-xl border border-sky-200 p-3 bg-white/50 focus:ring-2 focus:ring-sky-300"
                        placeholder="Jelaskan perbaikan..."
                        required></textarea>
                </section>

                <button class="btn-primary w-full">Simpan Perbaikan</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ================= HASIL PERBAIKAN ================= -->
    <?php if ($data['status'] === 'selesai'): ?>
        <div class="card-wrapper">
            <span class="card-badge">Laporan Perbaikan</span>
            <div class="card space-y-4">
                <?php if ($fotos['perbaikan']): ?>
                    <p class="text-xs text-gray-400 font-semibold mb-3">Foto Perbaikan</p>
                    <?php foreach ($fotos['perbaikan'] as $foto): ?>
                        <img src="<?= htmlspecialchars($foto) ?>" class="photo-full js-photo">
                <?php endforeach;
                endif; ?>
                <div class="bg-emerald-50 rounded-xl p-4 text-sm">
                    <p class="text-xs text-gray-400 font-semibold mb-3">Deskripsi Perbaikan</p>
                    <?= nl2br(htmlspecialchars($data['catatan_teknisi'])) ?>
                </div>

            </div>
        </div>
    <?php endif; ?>



</div>

<div id="photoModal">
    <span class="close" id="photoModalClose">&times;</span>
    <img id="photoModalImg">
</div>

<script>
    document.addEventListener("click", e => {
        const img = e.target.closest(".js-photo");
        if (img) {
            photoModalImg.src = img.src;
            photoModal.style.display = "flex";
        }
        if (e.target.id === "photoModal" || e.target.id === "photoModalClose") {
            photoModal.style.display = "none";
            photoModalImg.src = "";
        }
    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function() {

        const form = document.getElementById("formPerbaikan");
        if (!form) return;

        const inputFoto = document.getElementById("foto_perbaikan");
        const container = document.getElementById("container-foto_perbaikan");


        /* =============================
           VALIDASI SAAT SUBMIT
        ============================= */
        form.addEventListener("submit", function(e) {

            let gagal = false;
            let pesan = [];

            if (!inputFoto || inputFoto.files.length === 0) {
                gagal = true;
                pesan.push("Foto Perbaikan");
            }

            if (gagal) {
                e.preventDefault();

                const alertText =
                    "Harap lengkapi data berikut sebelum menyimpan:\n\n- " +
                    pesan.join("\n- ");

                // FIX iOS
                setTimeout(() => {
                    alert(alertText);
                }, 50);

                return; // ✅ BOLEH → DI DALAM FUNCTION
            }

        });

    });
</script>




<?php include 'footer.php'; ?>