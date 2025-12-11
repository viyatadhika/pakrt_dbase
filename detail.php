<?php
session_start();

// Pastikan user login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';

$title = "Detail Riwayat";
include 'header.php';

// Validasi ID
if (!isset($_GET['id'])) {
    echo "<div class='p-6 text-center text-gray-500'>ID checklist tidak ditemukan.</div>";
    include 'footer.php';
    exit;
}

$id = (int) $_GET['id'];

/* =========================
   Fungsi Tanggal Indonesia
========================= */
function tanggalIndo_detail($tgl)
{
    if (!$tgl) return '-';
    $t = strtotime($tgl);
    $bulan = [
        "Januari",
        "Februari",
        "Maret",
        "April",
        "Mei",
        "Juni",
        "Juli",
        "Agustus",
        "September",
        "Oktober",
        "November",
        "Desember"
    ];
    return date('j', $t) . ' ' . $bulan[(int)date('m', $t) - 1] . ' ' . date('Y', $t);
}

/* =========================
   Ambil Data Detail
========================= */
$stmt = $conn->prepare("SELECT * FROM checklist_forms WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    echo "<div class='p-6 text-center text-gray-500'>Data tidak ditemukan.</div>";
    include 'footer.php';
    exit;
}

/* =========================
   Ambil Checklist Items
========================= */
$items = [];
$stmtItems = $conn->prepare("SELECT area, item FROM checklist_items WHERE form_id = ? ORDER BY id ASC");
$stmtItems->bind_param("i", $id);
$stmtItems->execute();
$resItems = $stmtItems->get_result();

while ($r = $resItems->fetch_assoc()) {
    $items[$r['area']][] = $r['item'];
}
$stmtItems->close();

/* =========================
   Ambil Foto
========================= */
$photos = [];
$stmtPhotos = $conn->prepare("SELECT jenis, foto_path FROM checklist_fotos WHERE form_id = ?");
$stmtPhotos->bind_param("i", $id);
$stmtPhotos->execute();
$resPhotos = $stmtPhotos->get_result();

while ($r = $resPhotos->fetch_assoc()) {
    $photos[$r['jenis']][] = $r['foto_path'];
}
$stmtPhotos->close();

/* =========================
   Convert Path Foto
========================= */
function photo_to_web_src($raw)
{
    if (!$raw) return '';
    $filename = basename($raw);
    $host = $_SERVER['HTTP_HOST'];

    // Localhost (XAMPP)
    if (strpos($host, "localhost") !== false) {
        return "http://localhost/wargart_html/uploads/" . $filename;
    }

    // Server LAN
    return "http://{$host}/wargart/uploads/" . $filename;
}

$formTypeLower = strtolower(trim($data['form_type']));
?>

<style>
    /* ===============================
   HEADER DETAIL — PREMIUM
================================*/
    .detail-header-bar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: #ffffff;

        padding: 14px 20px 12px;
        display: flex;
        align-items: center;
        gap: 15px;
        /* 
        border-bottom: 1px solid #eef2f7;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05); */
    }

    .detail-back-btn {
        width: 40px;
        height: 40px;
        border-radius: 14px;

        background: #ffffff;
        border: 1px solid #e2e8f0;

        display: flex;
        align-items: center;
        justify-content: center;

        color: #0369a1;
        font-size: 17px;
        transition: .18s ease-in-out;
    }

    .detail-back-btn:hover {
        background: #e0f2fe;
    }

    .detail-title {
        font-size: 20px;
        font-weight: 700;
        color: #0369a1;
        margin: 0;
    }

    /* ===============================
   WRAPPER KONTEN
================================*/
    .detail-content-wrapper {
        padding: 18px 20px 100px;
    }

    /* Card utama */
    .detail-card {
        background: #ffffff;
        padding: 18px;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    /* ===============================
   TEXT SPACING
================================*/
    .detail-card p {
        font-size: 14px;
        color: #374151;
        margin-bottom: 6px;
    }

    .detail-card strong {
        color: #0c4a6e;
    }

    /* ===============================
   CHECKLIST SECTION
================================*/
    .checklist-section {
        margin-top: 16px;
        padding: 12px;
        background: #f9fafb;
        border-radius: 14px;
    }

    .checklist-section h4 {
        font-size: 15px;
        font-weight: 600;
        color: #0284c7;
        margin-bottom: 6px;
    }

    .checklist-section ul {
        margin-top: 4px;
        padding-left: 20px;
    }

    .checklist-section ul li {
        color: #374151;
        font-size: 13px;
        margin-bottom: 4px;
    }

    /* Ukuran font checklist lebih kecil */
    .checklist-items {
        font-size: 13px;
        /* default */
        line-height: 1.35;
    }

    .checklist-items .area-title {
        font-size: 13px;
        font-weight: 600;
        margin-top: 6px;
    }

    .checklist-items li {
        font-size: 14px;
        /* ukuran item lebih kecil */
        margin-left: 14px;
        margin-bottom: 2px;
    }


    /* ===============================
   FOTO SECTION
================================*/
    .photo-title {
        margin-top: 18px;
        font-size: 15px;
        font-weight: 600;
        color: #334155;
    }

    .photo-full {
        width: 100%;
        border-radius: 14px;
        margin-top: 8px;
        border: 1px solid #e5e7eb;
        cursor: pointer;
        transition: .2s ease-out;
    }

    .photo-full:hover {
        transform: scale(1.02);
    }

    #photoModal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(3px);
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    #photoModal img {
        max-width: 92%;
        max-height: 88%;
        border-radius: 16px;
    }

    /* Tombol X selalu di atas */
    #photoModal .close {
        position: absolute;
        top: 20px;
        right: 24px;
        color: #ffffff;
        font-size: 34px;
        font-weight: bold;
        cursor: pointer;
        z-index: 10000;
        /* <<< fix terpenting */
    }
</style>

<!-- DETAIL HEADER FINAL -->
<div class="detail-header-bar">
    <a href="riwayat.php" class="detail-back-btn">
        <i class="fa-solid fa-arrow-left"></i>
    </a>

    <h2 class="detail-title">Detail Riwayat</h2>
</div>


<!-- CONTENT -->
<div class="detail-content-wrapper">
    <div class="detail-card">

        <p><strong>Tanggal:</strong> <?= tanggalIndo_detail($data['tanggal']) ?></p>
        <p><strong>Nama Petugas:</strong> <?= htmlspecialchars($data['nama_petugas']) ?></p>
        <p><strong>NIP:</strong> <?= htmlspecialchars($data['nip_user']) ?></p>

        <?php
        $fields = [
            "Area Kerja" => "area_kerja",
            "Gedung" => "area_gedung",
            "Lantai" => "lantai",
            "Ruangan" => "ruangan",
            "Rumah" => "rumah",
            "Nomor" => "nomor_rumah",
            "Pos Jaga" => "pos_jaga"
        ];

        foreach ($fields as $label => $field):
            if (!empty($data[$field])):
        ?>
                <p><strong><?= $label ?>:</strong> <?= htmlspecialchars($data[$field]) ?></p>
        <?php endif;
        endforeach; ?>

        <?php if (!empty($data['catatan_kerusakan'])): ?>
            <p><strong>Catatan Khusus:</strong><br>
                <?= nl2br(htmlspecialchars($data['catatan_kerusakan'])) ?>
            </p>
        <?php endif; ?>

        <hr class="my-3">

        <!-- Checklist -->
        <?php if ($formTypeLower !== 'plotingjaga'): ?>
            <p><strong>Checklist:</strong></p>
            <div class="p-2 bg-gray-50 rounded checklist-items">
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $area => $list): ?>
                        <p class="area-title"><?= htmlspecialchars($area) ?></p>
                        <ul class="list-disc ml-5 text-gray-800">
                            <?php foreach ($list as $it): ?>
                                <li><?= htmlspecialchars($it) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 italic">Tidak ada data checklist.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
        // MAPPING LABEL YANG RAPI
        $labelMap = [
            "foto_pekerjaan_sesi1"   => "Foto Pekerjaan Sesi 1",
            "foto_kerusakan_sesi1"   => "Foto Kerusakan Sesi 1",
            "foto_pelayanan_sesi1"   => "Foto Pelayanan Sesi 1",

            "foto_pekerjaan_sesi2"   => "Foto Pekerjaan Sesi 2",
            "foto_kerusakan_sesi2"   => "Foto Kerusakan Sesi 2",
            "foto_pelayanan_sesi2"   => "Foto Pelayanan Sesi 2",

            "foto_apelpagi"          => "Foto Apel Pagi",
            "foto_apelmalam"         => "Foto Apel Malam",

            "foto_ploting"           => "Foto Ploting Jaga",
            "foto_pekerjaan"         => "Foto Pekerjaan",
        ];
        ?>

        <?php if (!empty($photos)): ?>
            <h3 class="mt-4 font-semibold">Foto Dokumentasi</h3>

            <?php foreach ($photos as $jenis => $arr): ?>

                <?php
                // CARI LABEL YANG RAPI
                $label = $labelMap[$jenis] ?? ucwords(str_replace('_', ' ', $jenis));
                ?>

                <p class="font-medium mt-3"><?= htmlspecialchars($label) ?></p>

                <?php foreach ($arr as $file):
                    $src = photo_to_web_src($file);
                    if (!$src) continue;
                ?>
                    <img src="<?= $src ?>" class="photo-full" onclick="openPhotoModal('<?= $src ?>')">
                <?php endforeach; ?>

            <?php endforeach; ?>
        <?php endif; ?>


    </div>
</div>

<div id="photoModal">
    <span class="close">&times;</span>
    <img id="modalImage" src="">
</div>



<script>
    function openPhotoModal(src) {
        document.getElementById("modalImage").src = src;
        document.getElementById("photoModal").style.display = "flex";
    }

    function closePhotoModal() {
        document.getElementById("photoModal").style.display = "none";
    }

    // Tutup saat klik tombol X
    document.querySelector("#photoModal .close").addEventListener("click", function(e) {
        e.stopPropagation(); // cegah klik merembet ke modal
        closePhotoModal();
    });

    // Tutup saat klik area luar foto
    document.getElementById("photoModal").addEventListener("click", function(e) {
        if (e.target.id === "photoModal") {
            closePhotoModal();
        }
    });
</script>

<?php include 'footer.php'; ?>