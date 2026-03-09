<?php
session_start();
error_reporting(E_ALL);

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';

$title = "Detail Riwayat";
include 'header.php';

if (!isset($_GET['id'])) {
    echo "<div class='p-6 text-center text-gray-500'>ID checklist tidak ditemukan.</div>";
    include 'footer.php';
    exit;
}

$id = (int) $_GET['id'];

function tanggalIndo($tgl)
{
    if (!$tgl) return '-';
    $t = strtotime($tgl);
    $bulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    return date('j', $t) . ' ' . $bulan[(int)date('m', $t) - 1] . ' ' . date('Y', $t);
}

$stmt = $conn->prepare("SELECT * FROM checklist_forms WHERE id=?");
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    echo "<div class='p-6 text-center text-gray-500'>Data tidak ditemukan.</div>";
    include 'footer.php';
    exit;
}

$items = [];
$stmtItems = $conn->prepare("SELECT area,item FROM checklist_items WHERE form_id=? ORDER BY id ASC");
$stmtItems->bind_param("i", $id);
$stmtItems->execute();
$resItems = $stmtItems->get_result();
while ($r = $resItems->fetch_assoc()) $items[$r['area']][] = $r['item'];
$stmtItems->close();

$photos = [];
$stmtPhotos = $conn->prepare("SELECT id,jenis,foto_path FROM checklist_fotos WHERE form_id=?");
$stmtPhotos->bind_param("i", $id);
$stmtPhotos->execute();
$resPhotos = $stmtPhotos->get_result();
while ($r = $resPhotos->fetch_assoc()) $photos[$r['jenis']][] = $r;
$stmtPhotos->close();

function photo_to_web_src($raw)
{
    if (!$raw) return '';
    $filename = basename($raw);
    $host = $_SERVER['HTTP_HOST'];
    if (strpos($host, "localhost") !== false) return "http://localhost/wargart_html/uploads/" . $filename;
    return "http://$host/wargart/uploads/" . $filename;
}

$formTypeLower = strtolower(trim($data['form_type']));
?>

<style>
    .detail-header-bar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: #fff;
        padding: 14px 20px 12px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .detail-back-btn {
        width: 40px;
        height: 40px;
        border-radius: 14px;
        background: #fff;
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

    .detail-content-wrapper {
        padding: 18px 20px 100px;
    }

    .detail-card {
        background: #fff;
        padding: 18px;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    .detail-card p {
        font-size: 14px;
        color: #374151;
        margin-bottom: 6px;
    }

    .detail-card strong {
        color: #0c4a6e;
    }

    .checklist-items {
        font-size: 13px;
        line-height: 1.35;
    }

    .checklist-items .area-title {
        font-size: 13px;
        font-weight: 600;
        margin-top: 6px;
    }

    .checklist-items li {
        font-size: 14px;
        margin-left: 14px;
        margin-bottom: 2px;
    }

    .photo-full {
        width: 100%;
        border-radius: 14px;
        margin-top: 8px;
        border: 1px solid #e5e7eb;
        cursor: pointer;
        transition: .2s ease-out;
        display: block;
    }

    .photo-full:hover {
        transform: scale(1.02);
    }

    /* ✅ FIX: hapus ... dan tambah property yang hilang */
    #photoModal {
        display: flex;
        position: fixed;
        inset: 0;
        visibility: hidden;
        pointer-events: none;
        opacity: 0;
        transition: opacity .2s;
        background: rgba(0, 0, 0, .85);
        backdrop-filter: blur(3px);
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    #photoModal.active {
        visibility: visible;
        pointer-events: all;
        opacity: 1;
    }

    #photoModal img {
        max-width: 92%;
        max-height: 88%;
        border-radius: 16px;
    }

    #photoModal .close {
        position: absolute;
        top: 20px;
        right: 24px;
        color: #fff;
        font-size: 34px;
        font-weight: bold;
        cursor: pointer;
        z-index: 10000;
    }

    .photo-reactions {
        display: flex;
        gap: 6px;
        margin-top: 6px;
        flex-wrap: wrap;
    }

    .reaction-badge {
        background: #f1f5f9;
        border-radius: 12px;
        padding: 2px 8px;
        font-size: 13px;
        cursor: pointer;
        user-select: none;
        border: 1px solid #e2e8f0;
    }

    .reaction-picker {
        display: flex;
        gap: 10px;
        font-size: 22px;
        margin-top: 8px;
        cursor: pointer;
        user-select: none;
        position: relative;
        z-index: 2;
    }

    .reaction-picker span {
        transition: transform .15s;
        display: inline-block;
    }

    .reaction-picker span:hover {
        transform: scale(1.3);
    }

    .reaction-picker span.active {
        background: #e0f2fe;
        border-radius: 8px;
        padding: 2px 4px;
    }

    .react-users {
        margin-top: 6px;
        background: #f8fafc;
        border-radius: 10px;
        padding: 8px 10px;
        font-size: 13px;
        border: 1px solid #e5e7eb;
    }

    .react-users div {
        padding: 3px 0;
        border-bottom: 1px dashed #e5e7eb;
    }

    .react-users div:last-child {
        border-bottom: 0;
    }

    .hidden {
        display: none;
    }
</style>

<div class="detail-header-bar">
    <a href="javascript:history.back()" class="detail-back-btn"><i class="fa-solid fa-arrow-left"></i></a>
    <h2 class="detail-title">Detail Riwayat</h2>
</div>

<div class="detail-content-wrapper">
    <div class="detail-card">
        <p><strong>Tanggal:</strong> <?= tanggalIndo($data['tanggal']) ?></p>
        <p><strong>Nama Petugas:</strong> <?= htmlspecialchars($data['nama_petugas']) ?></p>
        <p><strong>NIP:</strong> <?= htmlspecialchars($data['nip_user']) ?></p>

        <?php
        $fields = [
            "Area Kerja" => "area_kerja",
            "Gedung"     => "area_gedung",
            "Lantai"     => "lantai",
            "Ruangan"    => "ruangan",
            "Rumah"      => "rumah",
            "Nomor"      => "nomor_rumah",
            "Pos Jaga"   => "pos_jaga"
        ];
        foreach ($fields as $label => $field) {
            if (!empty($data[$field])) echo "<p><strong>$label:</strong> " . htmlspecialchars($data[$field]) . "</p>";
        }
        if ($formTypeLower === 'plotingjaga' && !empty($data['pergeseran'])) {
            echo "<p><strong>Pergeseran Plotingan:</strong> " . htmlspecialchars($data['pergeseran']) . "</p>";
        }
        if (!empty($data['catatan_kerusakan'])) {
            echo "<p><strong>Catatan Khusus:</strong><br>" . nl2br(htmlspecialchars($data['catatan_kerusakan'])) . "</p>";
        }
        ?>

        <hr class="my-3">

        <?php if ($formTypeLower !== 'plotingjaga'): ?>
            <p><strong>Checklist:</strong></p>
            <div class="p-2 bg-gray-50 rounded checklist-items">
                <?php if (!empty($items)):
                    foreach ($items as $area => $list):
                        echo "<p class='area-title'>" . htmlspecialchars($area) . "</p>";
                        echo "<ul class='list-disc ml-5 text-gray-800'>";
                        foreach ($list as $it) echo "<li>" . htmlspecialchars($it) . "</li>";
                        echo "</ul>";
                    endforeach;
                else:
                    echo "<p class='text-gray-500 italic'>Tidak ada data checklist.</p>";
                endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($photos)): ?>
            <h3 class="mt-4 font-semibold">Foto Dokumentasi</h3>

            <?php foreach ($photos as $jenis => $arr):
                echo "<p class='font-medium mt-3'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $jenis))) . "</p>";

                foreach ($arr as $foto):
                    $fotoId = (int)$foto['id'];
                    $src    = photo_to_web_src($foto['foto_path']);
                    if (!$src) continue;

                    $reactUsers = [];
                    $stmtR = $conn->prepare("
                        SELECT r.nip_user, r.emoji, u.nama
                        FROM checklist_reactions r
                        JOIN users u ON u.nip = r.nip_user
                        WHERE r.foto_id = ?
                    ");
                    $stmtR->bind_param("i", $fotoId);
                    $stmtR->execute();
                    $resR = $stmtR->get_result();
                    while ($r = $resR->fetch_assoc()) $reactUsers[$r['nip_user']] = $r;
                    $stmtR->close();

                    $reactSummary = [];
                    foreach ($reactUsers as $u) {
                        $reactSummary[$u['emoji']] = ($reactSummary[$u['emoji']] ?? 0) + 1;
                    }

                    $myNip   = $_SESSION['user']['nip'];
                    $myEmoji = $reactUsers[$myNip]['emoji'] ?? '';
            ?>
                    <div class="photo-box" data-foto-id="<?= $fotoId ?>">

                        <!-- ✅ FIX: hapus onclick dari img, pakai event listener -->
                        <img src="<?= htmlspecialchars($src) ?>" class="photo-full"
                            data-src="<?= htmlspecialchars($src) ?>">

                        <div class="reaction-picker" data-foto-id="<?= $fotoId ?>">
                            <?php foreach (['👍', '❤️', '😂', '😮', '😢', '🙏'] as $e): ?>
                                <span
                                    data-emoji="<?= $e ?>"
                                    class="<?= $myEmoji === $e ? 'active' : '' ?>">
                                    <?= $e ?>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <div class="photo-reactions" id="reactions-<?= $fotoId ?>">
                            <?php foreach ($reactSummary as $emoji => $total): ?>
                                <span class="reaction-badge"
                                    onclick="toggleReactUsers(<?= $fotoId ?>)">
                                    <?= htmlspecialchars($emoji) ?> <?= $total ?>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <div class="react-users hidden" id="users-<?= $fotoId ?>">
                            <?php if (!empty($reactUsers)): ?>
                                <?php foreach ($reactUsers as $u): ?>
                                    <div>
                                        <?= htmlspecialchars($u['emoji']) ?>
                                        <?= htmlspecialchars($u['nama']) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-gray-500">Belum ada reaction</div>
                            <?php endif; ?>
                        </div>

                    </div>
            <?php endforeach;
            endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="photoModal">
    <span class="close">&times;</span>
    <img id="modalImage" src="">
</div>

<script>
    const FORM_ID = <?= (int)$id ?>;

    // ✅ FIX: klik foto pakai event listener, bukan onclick di HTML
    document.addEventListener('click', function(e) {
        const img = e.target.closest('img.photo-full');
        if (img) {
            openPhotoModal(img.getAttribute('data-src'));
            return;
        }
    });

    function openPhotoModal(src) {
        document.getElementById("modalImage").src = src;
        document.getElementById("photoModal").classList.add("active");
    }

    function closePhotoModal() {
        document.getElementById("photoModal").classList.remove("active");
    }

    document.querySelector("#photoModal .close").addEventListener("click", function(e) {
        e.stopPropagation();
        closePhotoModal();
    });

    document.getElementById("photoModal").addEventListener("click", function(e) {
        if (e.target.id === "photoModal") closePhotoModal();
    });

    // Klik emoji → kirim ke react_photo.php
    document.addEventListener('click', function(e) {
        const emojiEl = e.target.closest('span[data-emoji]');
        if (!emojiEl) return;

        const picker = emojiEl.closest('.reaction-picker[data-foto-id]');
        if (!picker) return;

        const fotoId = picker.getAttribute('data-foto-id');
        const emoji = emojiEl.getAttribute('data-emoji');
        if (!fotoId || !emoji) return;

        fetch('react_photo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    form_id: FORM_ID,
                    foto_id: fotoId,
                    emoji: emoji
                })
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('Bukan JSON:', text);
                    throw err;
                }
            })
            .then(data => {
                if (data.error) {
                    console.warn('React error:', data.error);
                    return;
                }

                const thisPicker = document.querySelector(`.reaction-picker[data-foto-id="${fotoId}"]`);
                if (thisPicker) {
                    thisPicker.querySelectorAll('span[data-emoji]').forEach(s => {
                        s.classList.toggle('active', s.getAttribute('data-emoji') === emoji && !!data.summary?.[emoji]);
                    });
                }

                if (data.summary) renderSummary(fotoId, data.summary);
                if (data.users) renderUsers(fotoId, data.users);
            });
    });

    function toggleReactUsers(fotoId) {
        document.getElementById('users-' + fotoId)?.classList.toggle('hidden');
    }

    function renderSummary(fotoId, summary) {
        const box = document.getElementById('reactions-' + fotoId);
        if (!box) return;
        box.innerHTML = '';
        Object.entries(summary).forEach(([emoji, total]) => {
            box.innerHTML += `<span class="reaction-badge" onclick="toggleReactUsers(${fotoId})">${emoji} ${total}</span>`;
        });
    }

    function renderUsers(fotoId, users) {
        const box = document.getElementById('users-' + fotoId);
        if (!box) return;
        box.innerHTML = '';
        if (!users.length) {
            box.innerHTML = '<div class="text-gray-500">Belum ada reaction</div>';
            return;
        }
        users.forEach(u => {
            box.innerHTML += `<div>${u.emoji} <strong>${u.nama}</strong></div>`;
        });
    }
</script>

<?php include 'footer.php'; ?>