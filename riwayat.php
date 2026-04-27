<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';

$activePage = basename($_SERVER['PHP_SELF']);

$title      = "Riwayat Checklist";
include 'header.php';

// ==================== AMBIL FILTER ====================
$tgl_awal   = $_GET['start']     ?? "";
$tgl_akhir  = $_GET['end']       ?? "";
$petugas    = $_GET['petugas']   ?? "";
$form_type  = $_GET['form_type'] ?? "";

$prettyMap = [
    'piketob'            => 'Piket OB',
    'piket_ob'           => 'Piket OB',
    'piket ob'           => 'Piket OB',
    'plotingjaga'        => 'Ploting Jaga',
    'general_cleaning'   => 'General Cleaning',
    'ptsp'               => 'PTSP',
];

// ==================== QUERY DASAR ====================
$query = "
    SELECT cf.*,
        GROUP_CONCAT(r.emoji ORDER BY r.created_at SEPARATOR '') AS emoji_list,
        COUNT(r.id) AS total_reactions
    FROM checklist_forms cf
    LEFT JOIN checklist_reactions r ON r.form_id = cf.id
    WHERE 1
";

// Filter tanggal
if ($tgl_awal && $tgl_akhir) {
    $query .= " AND cf.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'";
}

// Filter jenis form
if ($form_type) {
    $query .= " AND cf.form_type = '$form_type'";
}

// Filter petugas
if ($petugas) {
    $query .= " AND cf.nama_petugas LIKE '%$petugas%'";
}

$query .= " GROUP BY cf.id ORDER BY cf.tanggal DESC, cf.id DESC";
$result = $conn->query($query);

// ==================== DATA DROPDOWN ====================
$listForm = $conn->query("
    SELECT DISTINCT form_type 
    FROM checklist_forms 
    ORDER BY form_type
")->fetch_all(MYSQLI_ASSOC);

$listPetugas = $conn->query("
    SELECT DISTINCT nama_petugas 
    FROM checklist_forms 
    ORDER BY nama_petugas
")->fetch_all(MYSQLI_ASSOC);
?>

<!-- ==================== HEADER SECTION ==================== -->
<div class="p-6 text-left">
    <h2 class="text-xl font-bold text-sky-700">Riwayat Checklist</h2>
    <p class="text-sm text-gray-500 mt-1">Semua aktivitas checklist petugas</p>
</div>

<!-- ==================== FILTER BOX ==================== -->
<form method="GET" class="filter-box">

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label>Dari</label>
            <input type="date" name="start" class="input-modern"
                value="<?= htmlspecialchars($tgl_awal); ?>">
        </div>

        <div>
            <label>Sampai</label>
            <input type="date" name="end" class="input-modern"
                value="<?= htmlspecialchars($tgl_akhir); ?>">
        </div>
    </div>

    <div>
        <label>Jenis Form</label>
        <select name="form_type" class="input-modern">
            <option value="">Semua Form</option>

            <?php
            $usedLabels = [];

            foreach ($listForm as $f):
                $raw = $f['form_type'];
                $key = strtolower(trim($raw));

                $label = $prettyMap[$key]
                    ?? ucwords(str_replace(['_', '-'], ' ', $raw));

                // Hindari duplikat label (Piket OB muncul sekali)
                if (in_array($label, $usedLabels)) continue;
                $usedLabels[] = $label;
            ?>
                <option value="<?= htmlspecialchars($raw); ?>"
                    <?= $form_type === $raw ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="relative">
        <label>Nama Petugas</label>

        <input
            type="text"
            id="petugasInput"
            name="petugas"
            class="input-modern"
            placeholder="Ketik nama petugas…"
            value="<?= htmlspecialchars($petugas ?? ''); ?>"
            autocomplete="off">

        <div id="petugasDropdown"
            class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-20 max-h-40 overflow-auto">

            <?php foreach ($listPetugas as $p): ?>
                <div
                    class="px-3 py-2 text-sm hover:bg-sky-50 cursor-pointer petugas-item"
                    data-nama="<?= htmlspecialchars(strtolower($p['nama_petugas'])); ?>">
                    <?= htmlspecialchars($p['nama_petugas']); ?>
                </div>
            <?php endforeach; ?>

        </div>
    </div>

    <button type="submit" class="btn-primary mt-4">Terapkan Filter</button>
</form>

<?php
// Cek apakah filter sudah digunakan
$filterUsed = ($tgl_awal || $tgl_akhir || $petugas || $form_type);
?>

<!-- ==================== RESULT SECTION ==================== -->

<?php if ($filterUsed): ?>

    <!-- Result muncul dengan animasi -->
    <div class="result-list fade-in">

        <?php if ($result && $result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php
                $tanggal = date('d M Y', strtotime($row['tanggal']));

                // Lokasi
                $lokasiParts = [];
                foreach (['area_kerja', 'area_gedung', 'lantai', 'rumah', 'pos_jaga'] as $key) {
                    if (!empty($row[$key])) $lokasiParts[] = $row[$key];
                }
                $lokasi = implode(' • ', $lokasiParts) ?: '-';

                // Nama form
                $rawFormType = $row['form_type'] ?? '';
                $key         = strtolower(trim($rawFormType));
                $displayForm = $prettyMap[$key] ?? ucwords(str_replace(['_', '-'], ' ', $rawFormType));

                $detailUrl = 'detail.php?' . http_build_query(['id' => (int)$row['id']]);

                // Reaksi emoji
                $emojiList      = $row['emoji_list'] ?? '';
                $totalReactions = (int)($row['total_reactions'] ?? 0);
                ?>

                <!-- ==================== CARD ITEM ==================== -->
                <div class="group bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 p-4">

                    <div class="flex justify-between items-start mb-2">

                        <!-- ================== KIRI: ICON + INFO ============== -->
                        <div class="flex items-start gap-2">

                            <div class="w-9 h-9 flex items-center justify-center bg-sky-100 text-sky-600 rounded-xl mt-0.5">
                                <i class="fa-solid fa-building text-base"></i>
                            </div>

                            <div>
                                <!-- JENIS FORM -->
                                <p class="font-semibold text-gray-800">
                                    <?= htmlspecialchars($displayForm); ?>
                                </p>

                                <!-- LOKASI -->
                                <p class="text-xs text-gray-500">
                                    <?= htmlspecialchars($lokasi); ?>
                                </p>

                                <!-- NOMOR RUMAH/KAMAR -->
                                <?php if (!empty($row['nomor_rumah'])): ?>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?= !empty($row['area_gedung']) ? "Kamar No: " : "No. Rumah: "; ?>
                                        <?= htmlspecialchars($row['nomor_rumah']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ================== KANAN: TANGGAL + NAMA PETUGAS ============== -->
                        <div class="flex flex-col items-end min-w-[90px] text-right">
                            <!-- TANGGAL -->
                            <span class="text-xs text-gray-400">
                                <?= htmlspecialchars($tanggal); ?>
                            </span>

                            <!-- NAMA PETUGAS -->
                            <span class="text-[11px] text-gray-600 flex items-center gap-1 mt-1">
                                <i class="fa-solid fa-user text-[10px]"></i>
                                <?= htmlspecialchars($row['nama_petugas']); ?>
                            </span>
                        </div>

                    </div>

                    <!-- ================== BAWAH: STATUS + DETAIL BUTTON ============== -->
                    <div class="flex justify-between items-center mt-2">

                        <!-- KIRI: STATUS + BADGE EMOJI -->
                        <div class="flex items-center gap-2 flex-wrap">

                            <!-- STATUS SELESAI -->
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border border-green-200 bg-green-50 text-green-700 flex items-center">
                                <i class="fa-solid fa-check mr-1 text-[10px]"></i>
                                Selesai
                            </span>

                            <!-- BADGE REAKSI EMOJI (hanya tampil jika ada reaksi) -->
                            <?php if (!empty($emojiList)): ?>
                                <span class="inline-flex items-center gap-1 text-[11px] bg-yellow-50 border border-yellow-200 text-yellow-800 px-2 py-0.5 rounded-full font-medium">
                                    <?= htmlspecialchars($emojiList); ?>
                                    <?php if ($totalReactions > 1): ?>
                                        <span class="text-[10px] text-yellow-700 font-semibold">
                                            <?= $totalReactions; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>

                        </div>

                        <!-- TOMBOL LIHAT DETAIL -->
                        <a href="<?= htmlspecialchars($detailUrl); ?>"
                            class="inline-flex items-center gap-2 text-xs font-medium text-sky-600 bg-sky-50 hover:bg-sky-100 px-3 py-1.5 rounded-full transition">
                            <i class="fa-solid fa-eye text-[11px]"></i>
                            Lihat Detail
                        </a>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>
            <div class="empty-state fade-in">
                <div class="icon-wrap">
                    <i class="fa-solid fa-filter"></i>
                </div>
                <h3 class="empty-title">Belum Ada Data</h3>
                <p class="empty-sub">
                    Tidak ada data sesuai filter.
                </p>
            </div>
        <?php endif; ?>

    </div>

<?php else: ?>
    <!-- ==================== UI KETIKA FILTER BELUM DIGUNAKAN ==================== -->
    <div class="empty-state fade-in">
        <div class="icon-wrap">
            <i class="fa-solid fa-filter"></i>
        </div>
        <h3 class="empty-title">Belum Ada Data</h3>
        <p class="empty-sub">
            Silakan gunakan filter untuk menampilkan riwayat checklist.
        </p>
    </div>
<?php endif; ?>
</div>

<?php include 'nav_monitoring.php'; ?>
<?php include 'footer.php'; ?>