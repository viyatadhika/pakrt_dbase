<?php
ob_start();
session_start();
require 'config.php';

if (!isset($_SESSION['user'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Jakarta');

$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$jenis  = trim($_GET['jenis'] ?? '');
$search = trim($_GET['q']     ?? '');

$bulanI = [
    '',
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
];

// =====================================================================
// WHERE
// =====================================================================
$whereParts = [];
$params     = [];
$types      = '';

$jenisValid = ['pelayanan_umum', 'pelayanan_informasi', 'pelayanan_pengaduan'];
if ($jenis !== '' && in_array($jenis, $jenisValid)) {
    $whereParts[] = "jenis_layanan = ?";
    $params[]     = $jenis;
    $types       .= 's';
}

if ($search !== '') {
    $bulanMap = [
        'januari' => '01',
        'februari' => '02',
        'maret' => '03',
        'april' => '04',
        'mei' => '05',
        'juni' => '06',
        'juli' => '07',
        'agustus' => '08',
        'september' => '09',
        'oktober' => '10',
        'november' => '11',
        'desember' => '12',
        'jan' => '01',
        'feb' => '02',
        'mar' => '03',
        'apr' => '04',
        'jun' => '06',
        'jul' => '07',
        'agu' => '08',
        'sep' => '09',
        'okt' => '10',
        'nov' => '11',
        'des' => '12',
    ];
    $alt = strtolower($search);
    foreach ($bulanMap as $nama => $angka) {
        $alt = str_replace($nama, $angka, $alt);
    }
    $alt = preg_replace('/\s+/', ' ', trim($alt));

    $like    = "%$search%";
    $likeAlt = "%$alt%";

    $whereParts[] = "(
        nama         LIKE ? OR
        asal         LIKE ? OR
        email        LIKE ? OR
        no_hp        LIKE ? OR
        keperluan    LIKE ? OR
        DATE_FORMAT(created_at, '%d/%m/%Y') LIKE ? OR
        DATE_FORMAT(created_at, '%Y-%m-%d') LIKE ? OR
        DATE_FORMAT(created_at, '%d %m %Y') LIKE ? OR
        DATE_FORMAT(created_at, '%m/%Y')    LIKE ?
    )";
    $params  = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $likeAlt, $likeAlt]);
    $types  .= 'sssssssss';
}

$whereSQL = empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);

// Helper jalankan query aman
function runQ($conn, $sql, $types, $params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}

// =====================================================================
// COUNT aktif
// =====================================================================
$stmtCnt = runQ($conn, "SELECT COUNT(*) AS total FROM buku_tamu $whereSQL", $types, $params);
$total   = $stmtCnt ? (int)$stmtCnt->get_result()->fetch_assoc()['total'] : 0;
if ($stmtCnt) $stmtCnt->close();

// =====================================================================
// COUNT badges semua jenis (pakai search saja, tanpa filter jenis)
// =====================================================================
$searchWhere  = '';
$searchParams = [];
$searchTypes  = '';
if ($search !== '') {
    // ambil bagian search saja dari whereParts (index terakhir kalau ada jenis)
    $searchIdx = ($jenis !== '') ? 1 : 0;
    if (isset($whereParts[$searchIdx])) {
        $searchWhere  = 'WHERE ' . $whereParts[$searchIdx];
        $searchParams = array_slice($params, ($jenis !== '') ? 1 : 0);
        $searchTypes  = ($jenis !== '') ? substr($types, 1) : $types;
    }
}

$counts = ['semua' => 0, 'pelayanan_umum' => 0, 'pelayanan_informasi' => 0, 'pelayanan_pengaduan' => 0];
$sqlBadge = "SELECT jenis_layanan, COUNT(*) AS cnt FROM buku_tamu $searchWhere GROUP BY jenis_layanan";
$stmtBadge = runQ($conn, $sqlBadge, $searchTypes, $searchParams);
if ($stmtBadge) {
    $resBadge = $stmtBadge->get_result();
    while ($b = $resBadge->fetch_assoc()) {
        $counts[$b['jenis_layanan']] = (int)$b['cnt'];
        $counts['semua'] += (int)$b['cnt'];
    }
    $stmtBadge->close();
}

// =====================================================================
// DATA
// =====================================================================
$dataParams = array_merge($params, [$limit, $offset]);
$dataTypes  = $types . 'ii';
$stmtData   = runQ(
    $conn,
    "SELECT id, nama, email, asal, no_hp, jenis_layanan, keperluan, created_at
     FROM buku_tamu $whereSQL
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?",
    $dataTypes,
    $dataParams
);
$rawRows = $stmtData ? $stmtData->get_result()->fetch_all(MYSQLI_ASSOC) : [];
if ($stmtData) $stmtData->close();

// =====================================================================
// FORMAT
// =====================================================================
$formatted = [];
foreach ($rawRows as $r) {
    $t       = strtotime($r['created_at']);
    $bulanNm = $bulanI[(int)date('m', $t)];
    $formatted[] = [
        'id'           => (int)$r['id'],
        'nama'         => $r['nama'],
        'email'        => $r['email'],
        'asal'         => $r['asal'],
        'no_hp'        => $r['no_hp'],
        'jenis_layanan' => $r['jenis_layanan'],
        'keperluan'    => $r['keperluan'],
        'tanggal_key'  => date('Y-m-d', $t),
        'tanggal_label' => date('d', $t) . ' ' . $bulanNm . ' ' . date('Y', $t),
        'jam'          => date('H:i', $t),
        'waktu_label'  => date('d', $t) . ' ' . $bulanNm . ' ' . date('Y', $t) . ', ' . date('H:i', $t) . ' WIB',
    ];
}

ob_end_clean();
header('Content-Type: application/json');
echo json_encode([
    'rows'    => $formatted,
    'total'   => $total,
    'counts'  => $counts,
    'page'    => $page,
    'hasMore' => ($offset + $limit) < $total,
]);
