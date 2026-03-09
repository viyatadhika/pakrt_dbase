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

/* ===== USER ID ===== */
$stmtUser = $conn->prepare("SELECT id FROM users WHERE nip = ?");
$stmtUser->bind_param("s", $nip);
$stmtUser->execute();
$userRow = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$userId = (int)$userRow['id'];

/* ===== DATA ===== */
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
    WHERE lk.pelapor_user_id = ?
    ORDER BY lk.updated_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

/* ===== PISAHKAN STATUS ===== */
$laporanDilaporkan = [];
$laporanSelesai    = [];

while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'selesai') {
        $laporanSelesai[] = $row;
    } else {
        $laporanDilaporkan[] = $row;
    }
}

include 'header.php';
?>

<style>
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
        padding: 14px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
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
        padding: 6px 12px;
        border-radius: 999px;
    }

    .hidden {
        display: none;
    }
</style>

<!-- ===== HEADER ===== -->
<div class="seamless-header">
    <div class="flex items-center gap-3">
        <a href="lainnya.php" class="back-btn p-2 bg-white shadow-sm border border-sky-100 hover:bg-sky-50 transition">
            <i class="fa-solid fa-arrow-left text-sky-600 text-lg"></i>
        </a>
        <div>
            <h2 class="font-bold text-lg text-sky-600">Daftar Laporan Kerusakan</h2>
            <p class="text-xs md:text-sm text-gray-500">
                Rincian fasilitas rusak
            </p>
        </div>
    </div>
</div>

<div class="register-container checklist-page space-y-4 mt-6 mb-28">


    <div class="flex gap-2 bg-slate-100 p-1 rounded-full">

        <button id="tabDilaporkan" class="tab-btn active flex items-center justify-center gap-2">
            Dilaporkan
            <span class="text-[10px] bg-white text-sky-600 font-bold px-2 py-0.5 rounded-full">
                <?= count($laporanDilaporkan) ?>
            </span>
        </button>

        <button id="tabSelesai" class="tab-btn flex items-center justify-center gap-2">
            Selesai
            <span class="text-[10px] bg-white text-emerald-600 font-bold px-2 py-0.5 rounded-full">
                <?= count($laporanSelesai) ?>
            </span>
        </button>

    </div>


    <!-- ===== DILAPORKAN ===== -->
    <div id="contentDilaporkan" class="space-y-4">
        <?php if ($laporanDilaporkan): foreach ($laporanDilaporkan as $row): ?>
                <?php
                $lokasi = array_filter([
                    $row['tipe_lokasi'],
                    $row['nama_lokasi'],
                    $row['nama_lantai'],
                    $row['nama_ruangan'],
                    $row['nomor_kamar'] ? 'No. ' . $row['nomor_kamar'] : null
                ]);
                ?>
                <div class="card-report space-y-2">
                    <p class="text-sm font-semibold">
                        <?= htmlspecialchars($row['nama_kategori']) ?>
                        <span class="text-gray-400 mx-1">•</span>
                        <?= htmlspecialchars($row['nama_jenis']) ?>
                    </p>

                    <p class="text-xs text-gray-500">
                        <?= implode(' • ', $lokasi) ?>
                    </p>

                    <p class="text-[11px] text-gray-400">
                        📅 Dilaporkan: <?= date('d M Y H:i', strtotime($row['created_at'])) ?>
                    </p>

                    <div class="flex justify-between items-center pt-2">
                        <span class="badge badge-yellow">Dilaporkan</span>
                        <a href="laporan_kerusakan_detail.php?id=<?= $row['id'] ?>" class="btn-detail">
                            Detail
                        </a>
                    </div>
                </div>
            <?php endforeach;
        else: ?>
            <p class="text-center text-gray-400 mt-10">Tidak ada laporan</p>
        <?php endif; ?>
    </div>

    <!-- ===== SELESAI ===== -->
    <div id="contentSelesai" class="space-y-4 hidden">
        <?php if ($laporanSelesai): foreach ($laporanSelesai as $row): ?>
                <?php
                $lokasi = array_filter([
                    $row['tipe_lokasi'],
                    $row['nama_lokasi'],
                    $row['nama_lantai'],
                    $row['nama_ruangan'],
                    $row['nomor_kamar'] ? 'No. ' . $row['nomor_kamar'] : null
                ]);
                ?>
                <div class="card-report space-y-2">
                    <p class="text-sm font-semibold">
                        <?= htmlspecialchars($row['nama_kategori']) ?>
                        <span class="text-gray-400 mx-1">•</span>
                        <?= htmlspecialchars($row['nama_jenis']) ?>
                    </p>

                    <p class="text-xs text-gray-500">
                        <?= implode(' • ', $lokasi) ?>
                    </p>

                    <p class="text-[11px] text-gray-400">
                        ✅ Selesai: <?= date('d M Y H:i', strtotime($row['updated_at'])) ?>
                    </p>

                    <div class="flex justify-between items-center pt-2">
                        <span class="badge badge-green">Selesai</span>
                        <a href="laporan_kerusakan_detail.php?id=<?= $row['id'] ?>" class="btn-detail">
                            Detail
                        </a>
                    </div>
                </div>
            <?php endforeach;
        else: ?>
            <p class="text-center text-gray-400 mt-10">Belum ada laporan selesai</p>
        <?php endif; ?>
    </div>

</div>

<script>
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
</script>

<?php include 'footer.php'; ?>