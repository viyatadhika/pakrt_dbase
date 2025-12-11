<?php
session_start();

// Pastikan user sudah login
if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Detail Riwayat | WARGA RT Super App";
include 'header.php';
include 'config.php';

// Pastikan ID dikirim
if (!isset($_GET['id'])) {
    echo "<div class='p-6 text-center text-gray-500'>ID checklist tidak ditemukan.</div>";
    include 'footer_checklist.php';
    exit;
}

$id   = (int) $_GET['id'];
$user = $_SESSION['user'];
$nip  = $user['nip'] ?? '';

if (!$nip) {
    echo "<div class='p-6 text-center text-gray-500'>NIP tidak ditemukan di sesi. Silakan login ulang.</div>";
    include 'footer_checklist.php';
    exit;
}

/* ===================== TANGGAL INDONESIA ===================== */
function tanggalIndo_detail($tgl)
{
    if (!$tgl) return '-';
    $t = strtotime($tgl);
    if (!$t) return htmlspecialchars($tgl);
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('j', $t) . ' ' . $bulan[(int)date('m', $t) - 1] . ' ' . date('Y', $t);
}

/* ===================== AMBIL DATA FORM ===================== */
$stmt = $conn->prepare("SELECT * FROM checklist_forms WHERE id = ? AND nip_user = ?");
$stmt->bind_param("is", $id, $nip);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
$formTypeLower = strtolower(trim($data['form_type'] ?? ''));

if (!$data) {
    echo "<div class='p-6 text-center text-gray-500'>Data tidak ditemukan atau bukan milik Anda.</div>";
    include 'footer_checklist.php';
    exit;
}

/* ===================== CHECKLIST ITEMS ===================== */
$items = [];
$stmtItems = $conn->prepare("SELECT area, item FROM checklist_items WHERE form_id = ? ORDER BY id ASC");
$stmtItems->bind_param('i', $id);
$stmtItems->execute();
$res = $stmtItems->get_result();
while ($r = $res->fetch_assoc()) {
    $items[$r['area']][] = $r['item'];
}
$stmtItems->close();

/* ===================== FOTO ===================== */
$photos = [];
$stmtPhotos = $conn->prepare("SELECT jenis, foto_path FROM checklist_fotos WHERE form_id = ?");
$stmtPhotos->bind_param('i', $id);
$stmtPhotos->execute();
$resP = $stmtPhotos->get_result();
while ($r = $resP->fetch_assoc()) {
    $photos[$r['jenis']][] = $r['foto_path'];
}
$stmtPhotos->close();

function photo_to_web_src($raw)
{
    if (!$raw) return "";
    $raw = trim($raw);

    // Jika sudah berupa URL penuh
    if (preg_match('#^https?://#i', $raw)) {
        return $raw;
    }

    // Ambil nama file saja
    $filename = basename($raw);

    // Deteksi apakah localhost atau LAN
    $host = $_SERVER['HTTP_HOST'];

    // Lokasi folder uploads aplikasi Warga RT
    $wargaFolder = "wargart/uploads/";

    // Jika dijalankan di localhost / XAMPP
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        return "http://localhost/wargart_html/uploads/" . $filename;
    }

    // Jika server LAN
    return "http://{$host}/wargart/uploads/" . $filename;
}
?>


<style>
    .photo-wrapper {
        margin-top: 12px;
    }

    .photo-full {
        width: 100%;
        max-height: 320px;
        object-fit: cover;
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        cursor: pointer;
        transition: 0.2s;
    }

    .photo-full:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    }

    /* MODAL ZOOM */
    #photoModal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(3px);
        justify-content: center;
        align-items: center;
    }

    #photoModal img {
        max-width: 95%;
        max-height: 90%;
        border-radius: 16px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    }

    .close {
        position: absolute;
        top: 15px;
        right: 20px;
        color: #ffffff;
        font-size: 32px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.2s;
    }
</style>

<!-- Modern Sticky Header -->
<div id="stickyHeader" class="seamless-header">
    <div class="flex items-center gap-1 mb-2">
        <!-- Back Button -->
        <a href="riwayat.php" class="back-btn p-2 bg-white shadow-sm border border-sky-100 hover:bg-sky-50 transition">
            <i class="fa-solid fa-arrow-left text-sky-600 text-lg"></i>
        </a>

        <!-- Title & Subtitle -->
        <div class="flex-1">
            <h2 class="title checklist-page font-bold text-xl md:text-2xl text-sky-600 leading-tight">
                Detail Riwayat
            </h2>
        </div>
    </div>
</div>

<!-- Container Form -->
<div class="register-container checklist-page">
    <div class="bg-white p-4 rounded-xl border border-sky-100 shadow-sm">
        <p><strong>Tanggal:</strong>
            <?= htmlspecialchars(tanggalIndo_detail($data['tanggal'])) ?>
        </p>
        <p><strong>Nama Petugas:</strong>
            <?= htmlspecialchars($data['nama_petugas'] ?? ($user['nama'] ?? '-')) ?>
        </p>
        <p><strong>NIP:</strong>
            <?= htmlspecialchars($data['nip_user'] ?? $nip) ?>
        </p>

        <?php
        // Tampilkan Area Kerja
        $skipLocation = false;
        $areaKerjaRaw = $data['area_kerja'] ?? '';
        if (!empty($areaKerjaRaw)): ?>
            <p><strong>Area Kerja:</strong>
                <?= htmlspecialchars($areaKerjaRaw) ?>
            </p>
        <?php
            // jika area kerja termasuk salah satu dari:
            // supervisor, taman, gondola, general cleaning, piket ob, koordinator, ptsp, regu
            $akLc = strtolower(trim($areaKerjaRaw));
            $specialAreas = ['supervisor', 'taman', 'gondola', 'general cleaning', 'piket ob', 'koordinator', 'ptsp'];

            foreach ($specialAreas as $kw) {
                if (strpos($akLc, $kw) !== false) {
                    $skipLocation = true;
                    break;
                }
            }
        endif;
        ?>

        <?php if (!$skipLocation): ?>

            <?php
            // tampilkan lokasi secara terpisah untuk kejelasan
            $shownSpecific = false;
            ?>

            <?php if (!empty($data['area_gedung'])): $shownSpecific = true; ?>
                <p><strong>Gedung:</strong> <?= htmlspecialchars($data['area_gedung']) ?></p>
            <?php endif; ?>

            <?php if (!empty($data['lantai'])): $shownSpecific = true; ?>
                <p><strong>Lantai:</strong> <?= htmlspecialchars($data['lantai']) ?></p>
            <?php endif; ?>

            <?php if (!empty($data['ruangan'])): $shownSpecific = true; ?>
                <p><strong>Ruangan:</strong> <?= htmlspecialchars($data['ruangan']) ?></p>
            <?php endif; ?>

            <?php if (!empty($data['rumah'])): $shownSpecific = true; ?>
                <p><strong>Rumah Pimpinan:</strong> <?= htmlspecialchars($data['rumah']) ?></p>
            <?php endif; ?>

            <?php if (!empty($data['nomor_rumah'])): $shownSpecific = true; ?>
                <?php if (!empty($data['area_gedung'])): ?>
                    <p><strong>Nomor Kamar:</strong> <?= htmlspecialchars($data['nomor_rumah']) ?></p>
                <?php elseif (!empty($data['rumah'])): ?>
                    <p><strong>Nomor Rumah:</strong> <?= htmlspecialchars($data['nomor_rumah']) ?></p>
                <?php else: ?>
                    <p><strong>Nomor:</strong> <?= htmlspecialchars($data['nomor_rumah']) ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($data['pos_jaga'])): $shownSpecific = true; ?>
                <p><strong>Pos Jaga:</strong> <?= htmlspecialchars($data['pos_jaga']) ?></p>
            <?php endif; ?>

            <?php if (!$shownSpecific):
                // fallback: komposit lokasi TANPA area_kerja
                $lokasiParts = [];
                if (!empty($data['area_gedung'])) $lokasiParts[] = $data['area_gedung'];
                if (!empty($data['lantai']))      $lokasiParts[] = $data['lantai'];
                if (!empty($data['rumah']))       $lokasiParts[] = $data['rumah'];
                if (!empty($data['ruangan']))     $lokasiParts[] = $data['ruangan'];
                if (!empty($data['nomor_rumah'])) $lokasiParts[] = $data['nomor_rumah'];
                if (!empty($data['pos_jaga']))    $lokasiParts[] = $data['pos_jaga'];

                if (!empty($lokasiParts)):
                    $lokasiDisplay = implode(' - ', $lokasiParts);
            ?>
                    <p><strong>Lokasi:</strong> <?= htmlspecialchars($lokasiDisplay) ?></p>
            <?php
                endif;
            endif; ?>


        <?php endif; // end if !$skipLocation 
        ?>

        <?php if ($formTypeLower === 'plotingjaga' && !empty($data['pergeseran'])): ?>
            <p><strong>Pergeseran Plotingan:</strong>
                <?= htmlspecialchars($data['pergeseran']) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($data['catatan_kerusakan'])): ?>
            <p><strong>Catatan Khusus:</strong>
                <?= nl2br(htmlspecialchars($data['catatan_kerusakan'])) ?>
            </p>
        <?php endif; ?>

        <hr class="my-3">

        <?php if ($formTypeLower !== 'plotingjaga'): ?>
            <!-- FORM BIASA: tampilkan checklist -->
            <p><strong>Checklist:</strong></p>
            <div class="p-2 bg-gray-50 rounded text-sm text-gray-700 whitespace-pre-line">
                <?php
                if (!empty($items)) {
                    foreach ($items as $area => $list) {
                        echo "<div class='mb-2'>";
                        echo "<p class='font-semibold text-sky-700'>" . htmlspecialchars($area) . "</p>";
                        echo "<ul class='list-disc ml-5 text-gray-800'>";
                        foreach ($list as $it) {
                            echo "<li>" . htmlspecialchars($it) . "</li>";
                        }
                        echo "</ul>";
                        echo "</div>";
                    }
                } else {
                    // fallback to legacy checklist column
                    $checklistRaw  = $data['checklist'] ?? '';
                    $checklistData = json_decode($checklistRaw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($checklistData) && !empty($checklistData)) {
                        foreach ($checklistData as $area => $itemsJson) {
                            echo "<div class='mb-2'>";
                            echo "<p class='font-semibold text-sky-700'>" . htmlspecialchars($area) . "</p>";
                            echo "<ul class='list-disc ml-5 text-gray-800'>";
                            foreach ($itemsJson as $it) {
                                echo "<li>" . htmlspecialchars($it) . "</li>";
                            }
                            echo "</ul>";
                            echo "</div>";
                        }
                    } elseif (!empty($checklistRaw)) {
                        echo nl2br(htmlspecialchars($checklistRaw));
                    } else {
                        echo "<p class='text-gray-500 italic'>Tidak ada data checklist.</p>";
                    }
                }
                ?>
            </div>

        <?php endif; ?>

        <?php
        // MAPPING LABEL YANG RAPI
        $labelMap = [
            "foto_pekerjaan_sesi1"   => "Foto Pekerjaan Sesi 1",
            "foto_kerusakan_sesi1"   => "Foto Kerusakan Sesi 1",
            "foto_pelayanan_sesi1"   => "Foto Pelayanan Sesi 1",
            "foto_pekerjaan_sesi2"  => "Foto Pekerjaan Sesi 2",
            "foto_kerusakan_sesi2"  => "Foto Kerusakan Sesi 2",
            "foto_pelayanan_sesi2"  => "Foto Pelayanan Sesi 2",
            "foto_apelpagi"         => "Foto Apel Pagi",
            "foto_apelmalam"        => "Foto Apel Malam",
            "foto_ploting"          => "Foto Ploting Jaga",
            "foto_pekerjaan"        => "Foto Pekerjaan",
        ];

        // tampilkan foto dari tabel checklist_fotos bila ada
        if (!empty($photos)) {
            foreach ($photos as $jenis => $arr) {

                // Ambil label yang rapi
                $label = $labelMap[$jenis] ?? ucfirst(str_replace(['_', '-'], ' ', $jenis));
        ?>
                <div class="mt-4">
                    <p><strong><?= htmlspecialchars($label) ?>:</strong></p>

                    <?php foreach ($arr as $p):
                        $src = photo_to_web_src($p);
                        if (!$src) continue;
                    ?>
                        <div class="photo-wrapper">
                            <img src="<?= htmlspecialchars($src, ENT_QUOTES) ?>"
                                class="photo-full"
                                onclick="openPhotoModal('<?= htmlspecialchars($src, ENT_QUOTES) ?>')">
                        </div>
                    <?php endforeach; ?>
                </div>
        <?php
            }
        } else {
            // fallback legacy system…
        }
        ?>


    </div>
</div>

<!-- MODAL ZOOM -->
<div id="photoModal" onclick="closePhotoModal()">
    <span class="close">&times;</span>
    <img id="modalImage" src="" alt="">
</div>

<script>
    function openPhotoModal(src) {
        const modal = document.getElementById("photoModal");
        const img = document.getElementById("modalImage");

        img.src = src;
        modal.style.display = "flex";
    }

    function closePhotoModal() {
        const modal = document.getElementById("photoModal");
        modal.style.display = "none";
    }

    // Cegah error kalau elemen tidak ada
    const closeFullscreenBtn = document.getElementById('closeFullscreen');
    if (closeFullscreenBtn) {
        closeFullscreenBtn.addEventListener('click', () => {
            const fullscreenModal = document.getElementById('fullscreenModal');
            if (fullscreenModal) {
                fullscreenModal.style.display = "none";
            }
        });
    }
</script>

<?php include 'footer.php'; ?>