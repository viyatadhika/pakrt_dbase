<?php
require "../config.php";

header("Content-Type: application/json");

$q = isset($_GET['q']) ? trim($_GET['q']) : "";

// Ambil 10 aktivitas terbaru
$sql = "
    SELECT *
    FROM checklist_forms
    WHERE 1
";

if ($q !== "") {
    $safe = $conn->real_escape_string($q);
    $sql .= " AND (
        form_type LIKE '%$safe%' OR
        area_kerja LIKE '%$safe%' OR
        area_gedung LIKE '%$safe%' OR
        lantai LIKE '%$safe%' OR
        rumah LIKE '%$safe%' OR
        pos_jaga LIKE '%$safe%'
    )";
}

$sql .= " ORDER BY tanggal DESC LIMIT 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0):
    while ($row = $result->fetch_assoc()):

        // Format tanggal
        $tanggal = date('d M Y', strtotime($row['tanggal']));

        // Lokasi
        $lokasiParts = [];
        if (!empty($row['area_kerja'])) $lokasiParts[] = $row['area_kerja'];
        if (!empty($row['area_gedung'])) $lokasiParts[] = $row['area_gedung'];
        if (!empty($row['lantai'])) $lokasiParts[] = $row['lantai'];
        if (!empty($row['rumah'])) $lokasiParts[] = $row['rumah'];
        if (!empty($row['pos_jaga'])) $lokasiParts[] = $row['pos_jaga'];

        $lokasi = implode(' • ', $lokasiParts);

        // Form type prettier
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


    <?php
    endwhile;
else:
    ?>
    <div class="text-center text-gray-400 text-sm py-4">Belum ada aktivitas.</div>
<?php
endif;
?>