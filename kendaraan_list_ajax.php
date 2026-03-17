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

// =====================================================================
// HELPERS
// =====================================================================
$bulanI = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function fmtDatetime($dt)
{
    global $bulanI;
    if (!$dt || strpos($dt, '0000') === 0) return '-';
    $t = strtotime($dt);
    if (!$t) return '-';
    return date('d', $t) . ' ' . $bulanI[(int)date('m', $t)] . ' ' . date('Y H:i', $t);
}

function calcDurasi($masuk, $keluar)
{
    if (empty($masuk) || empty($keluar)) return '-';
    if (strpos($keluar, '0000') === 0 || strpos($masuk, '0000') === 0) return '-';
    $tsMasuk  = strtotime($masuk);
    $tsKeluar = strtotime($keluar);
    if (!$tsMasuk || !$tsKeluar || $tsMasuk <= 0 || $tsKeluar <= 0) return '-';
    $sel = $tsKeluar - $tsMasuk;
    if ($sel <= 0) return '-';
    $detik = $sel % 60;
    $menit = floor($sel / 60) % 60;
    $jam   = floor($sel / 3600) % 24;
    $hari  = floor($sel / 86400);
    if ($hari  > 0) return "{$hari}h {$jam}j {$menit}m";
    if ($jam   > 0) return "{$jam}j {$menit}m";
    if ($menit > 0) return "{$menit}m {$detik}d";
    return "{$detik} detik";
}

function runQuery($conn, $sql, $types, $params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

// =====================================================================
// ✅ Kirim status canEdit ke response agar JS bisa pakai
// =====================================================================
$role    = strtolower($_SESSION['user']['role'] ?? '');
$canEdit = in_array($role, ['admin', 'security']);

// =====================================================================
// INPUT
// =====================================================================
$page   = max(1, (int)($_GET['page']   ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$status = ($_GET['status'] ?? 'masuk') === 'keluar' ? 'keluar' : 'masuk';
$search = trim($_GET['q'] ?? '');

// =====================================================================
// BANGUN KONDISI SEARCH
// =====================================================================
$searchCondition = '';
$searchParams    = [];
$searchTypes     = '';

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
    $searchNorm = strtolower($search);
    $alt = $searchNorm;
    foreach ($bulanMap as $nama => $angka) {
        $alt = str_replace($nama, $angka, $alt);
    }
    $alt = preg_replace('/\s+/', ' ', trim($alt));

    $like    = "%$search%";
    $likeAlt = "%$alt%";

    $searchCondition = "(
        kl.plat_nomor    LIKE ? OR
        kl.instansi_tamu LIKE ? OR
        kl.tujuan        LIKE ? OR
        kl.dicatat_oleh  LIKE ? OR
        DATE_FORMAT(kl.waktu_masuk,  '%d/%m/%Y') LIKE ? OR
        DATE_FORMAT(kl.waktu_masuk,  '%Y-%m-%d') LIKE ? OR
        DATE_FORMAT(kl.waktu_masuk,  '%d %m %Y') LIKE ? OR
        DATE_FORMAT(kl.waktu_masuk,  '%m/%Y')    LIKE ? OR
        DATE_FORMAT(kl.waktu_keluar, '%d/%m/%Y') LIKE ? OR
        DATE_FORMAT(kl.waktu_keluar, '%Y-%m-%d') LIKE ? OR
        DATE_FORMAT(kl.waktu_keluar, '%d %m %Y') LIKE ? OR
        DATE_FORMAT(kl.waktu_keluar, '%m/%Y')    LIKE ?
    )";
    $searchParams = [$like, $like, $like, $like, $like, $like, $likeAlt, $likeAlt, $like, $like, $likeAlt, $likeAlt];
    $searchTypes  = 'ssssssssssss';
}

// =====================================================================
// WHERE
// =====================================================================
if ($status === 'masuk') {
    $statusCond  = "kl.status = 'masuk'";
    $statusLawan = "kl.status = 'keluar' AND DATE(kl.waktu_masuk) >= CURDATE() - INTERVAL 30 DAY";
} else {
    $statusCond  = "kl.status = 'keluar' AND DATE(kl.waktu_masuk) >= CURDATE() - INTERVAL 30 DAY";
    $statusLawan = "kl.status = 'masuk'";
}

$whereAktif = "WHERE $statusCond"  . ($searchCondition ? " AND $searchCondition" : '');
$whereLawan = "WHERE $statusLawan" . ($searchCondition ? " AND $searchCondition" : '');

// =====================================================================
// COUNT
// =====================================================================
$stmtCnt = runQuery($conn, "SELECT COUNT(*) AS total FROM kendaraan_log kl $whereAktif", $searchTypes, $searchParams);
$total = $stmtCnt ? (int)$stmtCnt->get_result()->fetch_assoc()['total'] : 0;
if ($stmtCnt) $stmtCnt->close();

$stmtLawan = runQuery($conn, "SELECT COUNT(*) AS total FROM kendaraan_log kl $whereLawan", $searchTypes, $searchParams);
$totalLawan = $stmtLawan ? (int)$stmtLawan->get_result()->fetch_assoc()['total'] : 0;
if ($stmtLawan) $stmtLawan->close();

// =====================================================================
// DATA
// =====================================================================
$orderBy  = $status === 'masuk' ? 'kl.waktu_masuk DESC' : 'kl.waktu_keluar DESC, kl.waktu_masuk DESC';
$sqlData  = "SELECT kl.* FROM kendaraan_log kl $whereAktif ORDER BY $orderBy LIMIT ? OFFSET ?";
$stmtData = runQuery($conn, $sqlData, $searchTypes . 'ii', array_merge($searchParams, [$limit, $offset]));
$rows     = $stmtData ? $stmtData->get_result()->fetch_all(MYSQLI_ASSOC) : [];
if ($stmtData) $stmtData->close();

// =====================================================================
// FORMAT ROWS
// =====================================================================
$formatted = [];
foreach ($rows as $r) {
    $isMasuk = ($r['status'] === 'masuk');
    $parts   = array_values(array_filter([
        $r['instansi_tamu'] ?: null,
        $r['tujuan']        ?: null,
    ]));
    $formatted[] = [
        'id'                    => (int)$r['id'],
        'status'                => $r['status'],
        'plat_nomor'            => $r['plat_nomor']    ?? '',
        'instansi_tamu'         => $r['instansi_tamu'] ?? '',
        'tujuan'                => $r['tujuan']        ?? '',
        'dicatat_oleh'          => $r['dicatat_oleh']  ?? '',
        'lokasi_parts'          => $parts,
        'tanggal_display'       => fmtDatetime($isMasuk ? $r['waktu_masuk'] : $r['waktu_keluar']),
        'tanggal_masuk_display' => fmtDatetime($r['waktu_masuk']),
        'durasi'                => calcDurasi($r['waktu_masuk'], $r['waktu_keluar']),
        'is_masuk'              => $isMasuk,
    ];
}

// =====================================================================
// OUTPUT
// =====================================================================
ob_end_clean();
header('Content-Type: application/json');
echo json_encode([
    'rows'       => $formatted,
    'total'      => $total,
    'totalLawan' => $totalLawan,
    'page'       => $page,
    'hasMore'    => ($offset + $limit) < $total,
    'canEdit'    => $canEdit, // ✅ dikirim ke frontend
]);
