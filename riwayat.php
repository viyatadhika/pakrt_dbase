<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
$activePage = basename($_SERVER['PHP_SELF']);
$title = "Riwayat Checklist";
include 'header.php';

// Ambil filter
$tgl_awal = $_GET['start'] ?? "";
$tgl_akhir = $_GET['end'] ?? "";
$petugas = $_GET['petugas'] ?? "";
$form_type = $_GET['form_type'] ?? "";

// Query dasar
$query = "SELECT * FROM checklist_forms WHERE 1";

// Filter
if ($tgl_awal && $tgl_akhir) {
    $query .= " AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'";
}
if ($form_type) {
    $query .= " AND form_type = '$form_type'";
}
if ($petugas) {
    $query .= " AND nama_petugas LIKE '%$petugas%'";
}

$query .= " ORDER BY tanggal DESC, id DESC";

$result = $conn->query($query);

// Ambil daftar Form Type (ganti area)
$qForm = $conn->query("SELECT DISTINCT form_type FROM checklist_forms ORDER BY form_type");
$listForm = $qForm ? $qForm->fetch_all(MYSQLI_ASSOC) : [];

// Ambil daftar petugas unik
$qPetugas = $conn->query("SELECT DISTINCT nama_petugas FROM checklist_forms ORDER BY nama_petugas");
$listPetugas = $qPetugas ? $qPetugas->fetch_all(MYSQLI_ASSOC) : [];
?>

<div class="page-container riwayat-page">

    <div class="page-header">
        <h1>Riwayat Checklist</h1>
        <p class="sub">Semua aktivitas checklist petugas</p>
    </div>

    <!-- Filter Box -->
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
                <?php foreach ($listForm as $f): ?>
                    <option value="<?= $f['form_type']; ?>" <?= $form_type == $f['form_type'] ? "selected" : ""; ?>>
                        <?= strtoupper($f['form_type']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Nama Petugas</label>
            <select name="petugas" class="input-modern">
                <option value="">Semua Petugas</option>
                <?php foreach ($listPetugas as $p): ?>
                    <option value="<?= $p['nama_petugas']; ?>" <?= $petugas == $p['nama_petugas'] ? "selected" : ""; ?>>
                        <?= $p['nama_petugas']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-filter">Terapkan Filter</button>
    </form>

    <div class="result-list">

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                // tanggal sudah disimpan di DB; tampilkan versi Indonesia
                $tanggal = date('d M Y', strtotime($row['tanggal']));

                // kita tidak menampilkan uraian checklist di halaman riwayat
                $checklist_preview = '';

                // lokasi: gunakan field yang ada pada checklist_forms
                $lokasiParts = [];
                if (!empty($row['area_kerja'])) $lokasiParts[] = $row['area_kerja'];
                if (!empty($row['area_gedung'])) $lokasiParts[] = $row['area_gedung'];
                if (!empty($row['lantai'])) $lokasiParts[] = $row['lantai'];
                if (!empty($row['rumah'])) $lokasiParts[] = $row['rumah'];
                if (!empty($row['pos_jaga'])) $lokasiParts[] = $row['pos_jaga'];
                $lokasi = implode(' • ', $lokasiParts);

                // cek apakah koridor
                $isKoridor = stripos($lokasi, 'koridor') !== false;
                ?>

                <div class="group bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 p-4">
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 flex items-center justify-center bg-sky-100 text-sky-600 rounded-xl">
                                <i class="fa-solid fa-building text-base"></i>
                            </div>

                            <div>
                                <?php
                                // Normalize and prettify form_type for display
                                $rawFormType = $row['form_type'] ?? '';
                                $key = is_string($rawFormType) ? strtolower(trim($rawFormType)) : '';

                                $prettyMap = [
                                    'piketob' => 'Piket OB',
                                    'piket_ob' => 'Piket OB',
                                    'piket ob' => 'Piket OB',
                                    'plotingjaga' => 'Ploting Jaga',
                                    'general_cleaning' => 'General Cleaning',
                                    'ptsp' => 'PTSP',
                                    // keep other known mappings here if needed
                                ];

                                if (isset($prettyMap[$key])) {
                                    $displayForm = $prettyMap[$key];
                                } else {
                                    // fallback: replace underscores with spaces and title-case
                                    $displayForm = ucwords(str_replace(['_', '-'], ' ', $rawFormType));
                                }
                                ?>

                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($displayForm); ?></p>

                                <p class="text-xs text-gray-500">
                                    <?= htmlspecialchars($lokasi ?: '-'); ?>
                                </p>
                                <?php
                                // show room/house number when available
                                $nomor = trim((string)($row['nomor_rumah'] ?? ''));
                                $formTypeKey = strtolower(trim($row['form_type'] ?? ''));
                                if ($nomor !== ''):
                                    if (!empty($row['area_gedung'])):
                                        // asrama case
                                ?>
                                        <p class="text-xs text-gray-500 mt-1">Kamar No: <?= htmlspecialchars($nomor) ?></p>
                                    <?php elseif (!empty($row['rumah'])): ?>
                                        <p class="text-xs text-gray-500 mt-1">No. Rumah: <?= htmlspecialchars($nomor) ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <span class="text-xs text-gray-400"><?= htmlspecialchars($tanggal); ?></span>
                    </div>

                    <!-- Uraian checklist tidak ditampilkan di halaman riwayat -->

                    <?php
                    $detailUrl = 'detail.php?' . http_build_query([
                        'id' => (int)$row['id']
                    ]);
                    ?>

                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border border-green-200 bg-green-50 text-green-700">
                            <i class="fa-solid fa-check mr-1 text-[10px]"></i> Selesai
                        </span>

                        <a href="<?= htmlspecialchars($detailUrl); ?>"
                            class="inline-flex items-center gap-2 text-xs font-medium text-sky-600 bg-sky-50 hover:bg-sky-100 px-3 py-1.5 rounded-full transition">
                            <i class="fa-solid fa-eye text-[11px]"></i> Lihat Detail
                        </a>
                    </div>


                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="text-center text-gray-500 mt-24 p-8 rounded-2xl border border-gray-100">
                <i class="fa-solid fa-calendar-check text-6xl text-sky-400 mb-4"></i>
                <p class="text-lg font-semibold text-gray-700 mb-1">Belum Ada Riwayat</p>
                <p class="text-sm">Belum ada kegiatan yang tercatat dalam bulan ini.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

<?php include 'nav_monitoring.php'; ?>
<?php include 'footer.php'; ?>