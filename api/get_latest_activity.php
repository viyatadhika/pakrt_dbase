<?php
require __DIR__ . "/../config.php";

/* ===============================
   HELPER: PRETTY FORM NAME
================================ */
function prettyForm($raw)
{
    $map = [
        'piketob'            => 'Piket OB',
        'piket_ob'           => 'Piket OB',
        'piket ob'           => 'Piket OB',
        'plotingjaga'        => 'Ploting Jaga',
        'general_cleaning'   => 'General Cleaning',
        'ptsp'               => 'PTSP',
        'petugas_gedung'     => 'Petugas Gedung',
        'petugas_gudang'     => 'Petugas Gudang',
        'bmn'                => 'BMN',
        'poliklinik'         => 'Poliklinik',
        'driver'             => 'Driver',
        'teknisi'            => 'Teknisi',
        'admin_sekretariat'  => 'Admin Sekretariat',
    ];

    $key = strtolower(trim($raw));
    return $map[$key] ?? ucwords(str_replace(['_', '-'], ' ', $raw));
}

/* ===============================
   QUERY (FINAL – FIX ID → NAMA)
================================ */
$sql = "
SELECT
    cf.*,

    /* Nama Area Kerja (Petugas Gedung pakai master) */
    COALESCE(mtl.nama, cf.area_kerja) AS area_kerja_nama,

    /* Nama Gedung (Petugas Gedung pakai master) */
    COALESCE(ml.nama_lokasi, cf.area_gedung) AS area_gedung_nama

FROM checklist_forms cf

LEFT JOIN master_tipe_lokasi mtl
       ON cf.form_type = 'petugas_gedung'
      AND cf.area_kerja = mtl.id

LEFT JOIN master_lokasi ml
       ON cf.form_type = 'petugas_gedung'
      AND cf.area_gedung = ml.id

ORDER BY cf.tanggal DESC, cf.id DESC
LIMIT 3
";

$result = $conn->query($sql);
?>

<?php if ($result && $result->num_rows > 0): ?>

    <!-- ================= AKTIVITAS TERBARU ================= -->
    <section class="latest-section">

        <div class="latest-header">
            <h3>Aktivitas Terbaru</h3>
            <a href="riwayat.php">Lihat Semua</a>
        </div>

        <div class="latest-scroll">

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php
                /* ================= DATA PREP ================= */

                // Tanggal
                $tanggal   = date('d M Y', strtotime($row['tanggal']));
                $detailUrl = 'detail.php?id=' . (int)$row['id'];

                // Lokasi (SUDAH FIX ID → NAMA)
                $lokasiParts = [];

                if (!empty($row['area_kerja_nama'])) {
                    $lokasiParts[] = $row['area_kerja_nama'];
                }

                if (!empty($row['area_gedung_nama'])) {
                    $lokasiParts[] = $row['area_gedung_nama'];
                }

                if (!empty($row['lantai'])) {
                    $lokasiParts[] = $row['lantai'];
                }

                if (!empty($row['rumah'])) {
                    $lokasiParts[] = $row['rumah'];
                }

                if (!empty($row['pos_jaga'])) {
                    $lokasiParts[] = $row['pos_jaga'];
                }

                $lokasi = $lokasiParts ? implode(' • ', $lokasiParts) : '-';

                // Jenis Form (Badge)
                $displayForm = prettyForm($row['form_type']);
                ?>

                <a href="<?= htmlspecialchars($detailUrl); ?>" class="latest-card">

                    <!-- BADGE -->
                    <span class="latest-badge">
                        <?= htmlspecialchars($displayForm); ?>
                    </span>

                    <!-- TANGGAL -->
                    <p class="latest-title">
                        <?= htmlspecialchars($tanggal); ?>
                    </p>

                    <div class="latest-icon bg-blue">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <!-- LOKASI -->
                    <p class="latest-sub">
                        <?= htmlspecialchars($lokasi); ?>
                    </p>

                    <!-- FOOTER -->
                    <div class="latest-bottom">
                        <span><?= htmlspecialchars($row['nama_petugas']); ?></span>
                    </div>

                </a>

            <?php endwhile; ?>

        </div>
    </section>

<?php else: ?>

    <div class="empty-state">
        <div class="icon-wrap">
            <i class="fa-solid fa-clock"></i>
        </div>
        <h3 class="empty-title">Belum Ada Aktivitas</h3>
        <p class="empty-sub">Aktivitas terbaru akan muncul di sini.</p>
    </div>

<?php endif; ?>