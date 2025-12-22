<?php
require __DIR__ . "/../config.php";


/* ===============================
   HELPER: PRETTY FORM NAME
================================ */
function prettyForm($raw)
{
    $map = [
        'piketob'          => 'Piket OB',
        'piket_ob'         => 'Piket OB',
        'piket ob'         => 'Piket OB',
        'plotingjaga'      => 'Ploting Jaga',
        'general_cleaning' => 'General Cleaning',
        'ptsp'             => 'PTSP',
    ];

    $key = strtolower(trim($raw));
    return $map[$key] ?? ucwords(str_replace(['_', '-'], ' ', $raw));
}

/* ===============================
   QUERY
================================ */
$sql = "
    SELECT *
    FROM checklist_forms
    ORDER BY tanggal DESC, id DESC
    LIMIT 3
";

$result = $conn->query($sql);

/* ===============================
   OUTPUT
================================ */
if ($result && $result->num_rows > 0): ?>

    <!-- ================= AKTIVITAS TERBARU ================= -->
    <section class="latest-section">

        <div class="latest-header">
            <h3>Aktivitas Terbaru</h3>
            <a href="riwayat.php">Lihat Semua</a>
        </div>

        <div class="latest-scroll">

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php
                /* ===============================
       DATA PREP
    =============================== */

                // Tanggal
                $tanggal = date('d M Y', strtotime($row['tanggal']));
                $detailUrl = 'detail.php?id=' . (int)$row['id'];

                // Lokasi
                $lokasiParts = [];
                foreach (['area_kerja', 'area_gedung', 'lantai', 'rumah', 'pos_jaga'] as $k) {
                    if (!empty($row[$k])) $lokasiParts[] = $row[$k];
                }
                $lokasi = $lokasiParts ? implode(' • ', $lokasiParts) : '-';

                // Jenis Form (badge)
                $displayForm = prettyForm($row['form_type']);
                ?>

                <a href="<?= htmlspecialchars($detailUrl); ?>" class="latest-card">

                    <!-- BADGE (Jenis Form) -->
                    <span class="latest-badge">
                        <?= htmlspecialchars($displayForm); ?>
                    </span>

                    <!-- TANGGAL (judul utama) -->
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