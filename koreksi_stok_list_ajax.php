<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit("Unauthorized");
}

include 'config.php';

function labelTanggal($tgl)
{
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if ($tgl === $today)     return ['Hari Ini, ' . date('d M'), 'sky'];
    if ($tgl === $yesterday) return ['Kemarin, ' . date('d M', strtotime($tgl)), 'gray'];
    return [date('d M Y', strtotime($tgl)), 'gray'];
}

$data = [];

$sql = "
    SELECT
        ks.id,
        ks.ref_kode,
        ks.tanggal,
        ks.created_at,
        ks.jenis,
        ks.alasan,
        ks.keterangan,
        COUNT(d.id)  AS total_item,
        SUM(d.qty)   AS total_qty
    FROM koreksi_stok ks
    JOIN koreksi_stok_detail d ON ks.id = d.koreksi_stok_id
    WHERE ks.tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY ks.id
    ORDER BY ks.tanggal DESC, ks.created_at DESC
";

$q = $conn->query($sql);
if (!$q) {
    http_response_code(500);
    exit("Query header error: " . $conn->error);
}

while ($row = $q->fetch_assoc()) {
    $data[$row['tanggal']][] = $row;
}

$detailMap = [];
$qDetail = $conn->query("
    SELECT
        d.koreksi_stok_id,
        d.nama_barang,
        d.kode_barang,
        d.qty,
        d.satuan,
        COALESCE(k.nama_kategori,'Tanpa Kategori') AS nama_kategori
    FROM koreksi_stok_detail d
    JOIN koreksi_stok ks ON ks.id = d.koreksi_stok_id
    LEFT JOIN master_barang b
        ON b.kode_barang COLLATE utf8mb4_general_ci = d.kode_barang COLLATE utf8mb4_general_ci
    LEFT JOIN master_kategori_barang k ON k.id = b.kategori_id
    WHERE ks.tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY d.id ASC
");

if (!$qDetail) {
    http_response_code(500);
    exit("Query detail error: " . $conn->error);
}

while ($d = $qDetail->fetch_assoc()) {
    $detailMap[$d['koreksi_stok_id']][] = $d;
}
?>

<?php if (empty($data)): ?>
    <div class="text-xs text-gray-400 py-10 text-center">Belum ada data 30 hari terakhir</div>
<?php else: ?>

    <?php foreach ($data as $tanggal => $rows):
        [$label, $color] = labelTanggal($tanggal);
    ?>
        <div class="mb-8 groupTanggal">
            <div class="flex items-center gap-3 mb-4">
                <div class="h-5 w-1.5 bg-<?= $color ?>-500 rounded-full"></div>
                <h2 class="font-bold text-<?= $color ?>-800 text-sm"><?= $label ?></h2>
            </div>

            <div class="space-y-3">
                <?php foreach ($rows as $r): ?>
                    <?php
                    $items  = $detailMap[$r['id']] ?? [];
                    $jenis  = $r['jenis']      ?? 'tambah';
                    $alasan = $r['alasan']     ?? '-';
                    $ket    = $r['keterangan'] ?? '-';
                    ?>

                    <div class="item-card bg-white rounded-3xl p-4 flex justify-between items-center border border-gray-100 shadow-sm cursor-pointer transaksi-item"
                        onclick="openSheetLocal(this)"
                        data-ref="<?= htmlspecialchars($r['ref_kode']) ?>"
                        data-tanggal="<?= htmlspecialchars(date('d M Y', strtotime($r['tanggal']))) ?>"
                        data-jam="<?= htmlspecialchars(date('H:i:s', strtotime($r['created_at']))) ?>"
                        data-jenis="<?= htmlspecialchars($jenis) ?>"
                        data-alasan="<?= htmlspecialchars($alasan) ?>"
                        data-keterangan="<?= htmlspecialchars($ket) ?>"
                        data-items='<?= htmlspecialchars(json_encode($items), ENT_QUOTES, "UTF-8") ?>'>

                        <div class="flex gap-4 items-center min-w-0">
                            <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-rotate text-xl"></i>
                            </div>

                            <div class="min-w-0 w-full">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-[10px] font-bold text-sky-600 uppercase refkode">
                                        <?= htmlspecialchars($r['ref_kode']) ?>
                                    </span>
                                    <span class="badge-koreksi">Koreksi</span>
                                </div>

                                <h3 class="font-bold text-gray-800 text-sm truncate ringkasan">
                                    <?= (int)$r['total_item'] ?> item barang
                                </h3>

                                <p class="text-[11px] text-gray-500 mt-1 truncate">
                                    <span class="font-semibold text-gray-700"><?= htmlspecialchars($alasan) ?></span>
                                </p>

                                <p class="text-xs text-gray-500 mt-1">
                                    Total qty:
                                    <span class="font-bold <?= $jenis === 'tambah' ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $jenis === 'tambah' ? '+ ' : '- ' ?><?= (int)$r['total_qty'] ?>
                                    </span>
                                    <span class="mx-1 text-gray-300">•</span>
                                    <span class="text-[10px]"><?= date('H:i:s', strtotime($r['created_at'])) ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="w-10 h-10 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    <?php endforeach ?>

<?php endif; ?>