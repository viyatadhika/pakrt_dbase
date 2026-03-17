<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

$page   = max(1, (int)($_GET['page']   ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? 'dilaporkan';
$search = trim($_GET['q'] ?? '');

// ---- Bangun WHERE ----
$whereParts = [];
$params     = [];
$types      = '';

// filter tab
if ($status === 'selesai') {
    $whereParts[] = "lk.status = 'selesai'";
} else {
    $whereParts[] = "lk.status != 'selesai'";
}

// filter search (server-side)
if ($search !== '') {
    $whereParts[] = "(
        kk.nama_kategori  LIKE ? OR
        jk.nama_jenis     LIKE ? OR
        ml.nama_lokasi    LIKE ? OR
        ml2.nama_lantai   LIKE ? OR
        mr.nama_ruangan   LIKE ? OR
        mk.nomor_kamar    LIKE ? OR
        tl.nama           LIKE ? OR
        lk.deskripsi      LIKE ?
    )";
    $like    = "%$search%";
    $params  = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like]);
    $types  .= 'ssssssss';
}

$whereSQL = 'WHERE ' . implode(' AND ', $whereParts);

$joins = "
    LEFT JOIN master_tipe_lokasi tl  ON lk.tipe_lokasi_id         = tl.id
    LEFT JOIN master_lokasi ml       ON lk.lokasi_id               = ml.id
    LEFT JOIN master_lantai ml2      ON lk.lantai_id               = ml2.id
    LEFT JOIN master_ruangan mr      ON lk.ruangan_id              = mr.id
    LEFT JOIN master_kamar mk        ON lk.kamar_id                = mk.id
    LEFT JOIN master_kategori_kerusakan kk ON lk.kategori_kerusakan_id = kk.id
    LEFT JOIN master_jenis_kerusakan jk    ON lk.jenis_kerusakan_id    = jk.id
";

// ---- Hitung total ----
$sqlCount  = "SELECT COUNT(*) AS total FROM laporan_kerusakan lk $joins $whereSQL";
$stmtCount = $conn->prepare($sqlCount);
if (!empty($params)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$total = (int)$stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

// ---- Ambil data ----
$sqlData = "
    SELECT
        lk.id, lk.status, lk.created_at, lk.updated_at, lk.deskripsi,
        tl.nama          AS tipe_lokasi,
        ml.nama_lokasi,
        ml2.nama_lantai,
        mr.nama_ruangan,
        mk.nomor_kamar,
        kk.nama_kategori,
        jk.nama_jenis
    FROM laporan_kerusakan lk
    $joins
    $whereSQL
    ORDER BY lk.updated_at DESC
    LIMIT ? OFFSET ?
";

$dataParams = array_merge($params, [$limit, $offset]);
$dataTypes  = $types . 'ii';

$stmtData = $conn->prepare($sqlData);
$stmtData->bind_param($dataTypes, ...$dataParams);
$stmtData->execute();
$rows = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtData->close();

// ---- Format data untuk JSON ----
$formatted = [];
foreach ($rows as $row) {
    $lokasi = array_values(array_filter([
        $row['tipe_lokasi']  ?? '',
        $row['nama_lokasi']  ?? '',
        $row['nama_lantai']  ?? '',
        $row['nama_ruangan'] ?? '',
        !empty($row['nomor_kamar']) ? 'No. ' . $row['nomor_kamar'] : null,
    ]));

    $isSelesai  = ($row['status'] === 'selesai');
    $tanggalVal = $isSelesai ? $row['updated_at'] : $row['created_at'];

    // Format tanggal pakai timezone Jakarta
    $dt = new DateTime($tanggalVal, new DateTimeZone('Asia/Jakarta'));

    $formatted[] = [
        'id'           => (int)$row['id'],
        'status'       => $row['status'],
        'nama_kategori' => $row['nama_kategori'] ?? '-',
        'nama_jenis'   => $row['nama_jenis']    ?? '-',
        'deskripsi'    => $row['deskripsi']     ?? '',
        'lokasi'       => $lokasi,
        'tanggal'      => $dt->format('d M Y H:i'),
        'is_selesai'   => $isSelesai,
    ];
}

echo json_encode([
    'rows'    => $formatted,
    'total'   => $total,
    'page'    => $page,
    'hasMore' => ($page * $limit) < $total,
]);
