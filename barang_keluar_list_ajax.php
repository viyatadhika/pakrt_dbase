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
    if ($tgl === $today)     return ['Hari Ini, ' . date('d M'), 'orange'];
    if ($tgl === $yesterday) return ['Kemarin, ' . date('d M', strtotime($tgl)), 'gray'];
    return [date('d M Y', strtotime($tgl)), 'gray'];
}

function twColorMap($key)
{
    $map = [
        'sky'     => ['bg' => 'bg-sky-50',     'text' => 'text-sky-600',     'ring' => 'ring-sky-100'],
        'purple'  => ['bg' => 'bg-purple-50',  'text' => 'text-purple-600',  'ring' => 'ring-purple-100'],
        'amber'   => ['bg' => 'bg-amber-50',   'text' => 'text-amber-600',   'ring' => 'ring-amber-100'],
        'teal'    => ['bg' => 'bg-teal-50',    'text' => 'text-teal-600',    'ring' => 'ring-teal-100'],
        'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'ring' => 'ring-emerald-100'],
        'gray'    => ['bg' => 'bg-gray-50',    'text' => 'text-gray-600',    'ring' => 'ring-gray-200'],
    ];
    return $map[$key] ?? $map['gray'];
}

/* HEADER 30 hari */
$data = [];

$sql = "
    SELECT
        bk.id,
        bk.ref_kode,
        bk.tanggal,
        bk.created_at,
        bk.penerima,
        bk.keterangan,
        COUNT(d.id)  AS total_item,
        SUM(d.qty)   AS total_qty
    FROM barang_keluar bk
    JOIN barang_keluar_detail d ON bk.id = d.barang_keluar_id
    WHERE bk.tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY bk.id
    ORDER BY bk.tanggal DESC, bk.created_at DESC
";

$q = $conn->query($sql);
if (!$q) {
    http_response_code(500);
    exit("Query header error: " . $conn->error);
}

while ($row = $q->fetch_assoc()) {
    $tgl = $row['tanggal'];
    $data[$tgl][] = $row;
}

/* DETAIL 30 hari */
$detailMap = [];
$qDetail = $conn->query("
    SELECT
        d.barang_keluar_id,
        d.nama_barang,
        d.kode_barang,
        d.qty,
        d.satuan,
        k.nama_kategori,
        k.icon  AS kategori_icon,
        k.color AS kategori_color
    FROM barang_keluar_detail d
    JOIN barang_keluar bk ON bk.id = d.barang_keluar_id
    LEFT JOIN master_barang b ON b.kode_barang = d.kode_barang
    LEFT JOIN master_kategori_barang k ON k.id = b.kategori_id
    WHERE bk.tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY d.id ASC
");

if (!$qDetail) {
    http_response_code(500);
    exit("Query detail error: " . $conn->error);
}

while ($d = $qDetail->fetch_assoc()) {
    $detailMap[$d['barang_keluar_id']][] = $d;
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
                    $items = $detailMap[$r['id']] ?? [];

                    $katUnik = [];
                    foreach ($items as $it) {
                        $nama = $it['nama_kategori'] ?? 'Tanpa Kategori';
                        $clr  = $it['kategori_color'] ?? 'gray';
                        $ico  = $it['kategori_icon']  ?? 'fa-box';
                        $key  = $nama . '|' . $clr . '|' . $ico;
                        $katUnik[$key] = ['nama' => $nama, 'color' => $clr, 'icon' => $ico];
                    }
                    $katUnik = array_values($katUnik);

                    $katNames = [];
                    foreach ($katUnik as $ku) $katNames[] = $ku['nama'];
                    $katSearchText = implode(', ', $katNames);

                    $penerima = $r['penerima']   ?? '-';
                    $ket      = $r['keterangan'] ?? '-';
                    ?>

                    <div class="item-card bg-white rounded-3xl p-4 flex justify-between items-center border border-gray-100 shadow-sm cursor-pointer transaksi-item"
                        onclick="openSheetLocal(this)"
                        data-ref="<?= htmlspecialchars($r['ref_kode']) ?>"
                        data-tanggal="<?= htmlspecialchars(date('d M Y', strtotime($r['tanggal']))) ?>"
                        data-jam="<?= htmlspecialchars(date('H:i:s', strtotime($r['created_at']))) ?>"
                        data-penerima="<?= htmlspecialchars($penerima) ?>"
                        data-keterangan="<?= htmlspecialchars($ket) ?>"
                        data-items='<?= htmlspecialchars(json_encode($items), ENT_QUOTES, "UTF-8") ?>'>

                        <div class="flex gap-4 items-center min-w-0">
                            <div class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-arrow-up-from-bracket text-xl"></i>
                            </div>

                            <div class="min-w-0 w-full">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-[10px] font-bold text-orange-600 uppercase refkode">
                                        <?= htmlspecialchars($r['ref_kode']) ?>
                                    </span>
                                    <span class="badge-keluar">Keluar</span>
                                </div>

                                <h3 class="font-bold text-gray-800 text-sm truncate ringkasan">
                                    <?= (int)$r['total_item'] ?> item barang
                                </h3>

                                <p class="text-[11px] text-gray-500 mt-1 truncate">
                                    <span class="font-semibold text-gray-700"><?= htmlspecialchars($penerima) ?></span>
                                </p>

                                <p class="text-xs text-gray-500 mt-1">
                                    Total qty:
                                    <span class="font-bold text-red-600">- <?= (int)$r['total_qty'] ?></span>
                                    <span class="mx-1 text-gray-300">•</span>
                                    <span class="text-[10px]"><?= date('H:i:s', strtotime($r['created_at'])) ?></span>
                                </p>

                                <div class="mt-2 flex flex-wrap gap-1.5 chipsKategori">
                                    <?php
                                    $maxChip  = 3;
                                    $shown    = 0;
                                    $totalKat = count($katUnik);
                                    foreach ($katUnik as $ku) {
                                        if ($shown >= $maxChip) break;
                                        $m = twColorMap($ku['color']);
                                        $shown++;
                                    ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-bold <?= $m['bg'] ?> <?= $m['text'] ?> ring-1 <?= $m['ring'] ?>">
                                            <i class="fa-solid <?= htmlspecialchars($ku['icon']) ?> text-[10px]"></i>
                                            <?= htmlspecialchars($ku['nama']) ?>
                                        </span>
                                    <?php } ?>

                                    <?php if ($totalKat > $maxChip): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold bg-gray-50 text-gray-600 ring-1 ring-gray-200">
                                            +<?= (int)($totalKat - $maxChip) ?>
                                        </span>
                                    <?php endif; ?>

                                    <span class="hidden kategoriSearchText"><?= htmlspecialchars($katSearchText) ?></span>
                                </div>
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