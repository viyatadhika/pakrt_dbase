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

// ==================== QUERY DASAR ====================
$query = "SELECT * FROM checklist_forms WHERE 1";

// Filter tanggal
if ($tgl_awal && $tgl_akhir) {
    $query .= " AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'";
}

// Filter jenis form
if ($form_type) {
    $query .= " AND form_type = '$form_type'";
}

// Filter petugas
if ($petugas) {
    $query .= " AND nama_petugas LIKE '%$petugas%'";
}

$query .= " ORDER BY tanggal DESC, id DESC";
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
            <?php foreach ($listForm as $f): ?>
                <option value="<?= $f['form_type']; ?>"
                    <?= $form_type == $f['form_type'] ? "selected" : ""; ?>>
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
                <option value="<?= $p['nama_petugas']; ?>"
                    <?= $petugas == $p['nama_petugas'] ? "selected" : ""; ?>>
                    <?= $p['nama_petugas']; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn-primary mt-4">Terapkan Filter</button>
</form>

<?php
// Cek apakah filter sudah digunakan
$filterUsed = ($tgl_awal || $tgl_akhir || $petugas || $form_type);
?>

<!-- ==================== RESULT LIST ==================== -->
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
                $key = strtolower(trim($rawFormType));
                $prettyMap = [
                    'piketob' => 'Piket OB',
                    'piket_ob' => 'Piket OB',
                    'piket ob' => 'Piket OB',
                    'plotingjaga' => 'Ploting Jaga',
                    'general_cleaning' => 'General Cleaning',
                    'ptsp' => 'PTSP',
                ];
                $displayForm = $prettyMap[$key] ?? ucwords(str_replace(['_', '-'], ' ', $rawFormType));

                $detailUrl = 'detail.php?' . http_build_query(['id' => (int)$row['id']]);
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

                        <!-- STATUS SELESAI -->
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border border-green-200 bg-green-50 text-green-700 flex items-center">
                            <i class="fa-solid fa-check mr-1 text-[10px]"></i>
                            Selesai
                        </span>

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