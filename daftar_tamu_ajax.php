<?php
ob_start();
session_start();
require 'config.php';

if (!isset($_SESSION['user'])) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Jakarta');

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;
$jenis  = trim((string)($_GET['jenis'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$period = trim((string)($_GET['period'] ?? 'all'));

$createdAtWibSql = "DATE_ADD(created_at, INTERVAL 7 HOUR)";
$bulanI = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function runQ($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if (!empty($params)) {
        $bind = [$types];
        foreach ($params as $key => $value) $bind[] = &$params[$key];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    return $stmt;
}

function buildWhere($jenis, $search, $period, $includeJenis, $createdAtWibSql)
{
    $where = [];
    $params = [];
    $types = '';
    $valid = ['pelayanan_umum', 'pelayanan_informasi', 'pelayanan_pengaduan'];
    if ($includeJenis && in_array($jenis, $valid, true)) {
        $where[] = 'jenis_layanan = ?';
        $params[] = $jenis;
        $types .= 's';
    }
    if ($period === 'today') {
        $where[] = "DATE($createdAtWibSql) = DATE(DATE_ADD(NOW(), INTERVAL 7 HOUR))";
    } elseif ($period === '7') {
        $where[] = "DATE($createdAtWibSql) >= DATE_SUB(DATE(DATE_ADD(NOW(), INTERVAL 7 HOUR)), INTERVAL 6 DAY)";
    } elseif ($period === '30') {
        $where[] = "DATE($createdAtWibSql) >= DATE_SUB(DATE(DATE_ADD(NOW(), INTERVAL 7 HOUR)), INTERVAL 29 DAY)";
    }
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = "(nama LIKE ? OR asal LIKE ? OR email LIKE ? OR no_hp LIKE ? OR keperluan LIKE ? OR DATE_FORMAT($createdAtWibSql, '%d/%m/%Y') LIKE ?)";
        for ($i = 0; $i < 6; $i++) $params[] = $like;
        $types .= 'ssssss';
    }
    return [empty($where) ? '' : 'WHERE ' . implode(' AND ', $where), $types, $params];
}

list($whereSQL, $types, $params) = buildWhere($jenis, $search, $period, true, $createdAtWibSql);
$stmtCnt = runQ($conn, "SELECT COUNT(*) AS total FROM buku_tamu $whereSQL", $types, $params);
$total = 0;
if ($stmtCnt) {
    $resCnt = $stmtCnt->get_result();
    $rowCnt = $resCnt->fetch_assoc();
    $total = (int)($rowCnt['total'] ?? 0);
    $stmtCnt->close();
}

list($badgeWhere, $badgeTypes, $badgeParams) = buildWhere($jenis, $search, $period, false, $createdAtWibSql);
$counts = ['semua' => 0, 'pelayanan_umum' => 0, 'pelayanan_informasi' => 0, 'pelayanan_pengaduan' => 0];
$stmtBadge = runQ($conn, "SELECT jenis_layanan, COUNT(*) AS cnt FROM buku_tamu $badgeWhere GROUP BY jenis_layanan", $badgeTypes, $badgeParams);
if ($stmtBadge) {
    $resBadge = $stmtBadge->get_result();
    while ($b = $resBadge->fetch_assoc()) {
        if (isset($counts[$b['jenis_layanan']])) {
            $counts[$b['jenis_layanan']] = (int)$b['cnt'];
            $counts['semua'] += (int)$b['cnt'];
        }
    }
    $stmtBadge->close();
}

$dataParams = array_merge($params, [$limit, $offset]);
$dataTypes = $types . 'ii';
$stmtData = runQ($conn, "SELECT id, nama, email, asal, no_hp, jenis_layanan, keperluan, $createdAtWibSql AS created_at_wib FROM buku_tamu $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?", $dataTypes, $dataParams);
$rawRows = [];
if ($stmtData) {
    $rawRows = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtData->close();
}

$rows = [];
foreach ($rawRows as $r) {
    $ts = strtotime($r['created_at_wib']);
    if (!$ts) continue;
    $bulan = $bulanI[(int)date('m', $ts)];
    $rows[] = [
        'id' => (int)$r['id'],
        'nama' => $r['nama'],
        'email' => $r['email'],
        'asal' => $r['asal'],
        'no_hp' => $r['no_hp'],
        'jenis_layanan' => $r['jenis_layanan'],
        'keperluan' => $r['keperluan'],
        'tanggal_key' => date('Y-m-d', $ts),
        'tanggal_label' => date('d', $ts) . ' ' . $bulan . ' ' . date('Y', $ts),
        'jam' => date('H:i', $ts),
        'waktu_label' => date('d', $ts) . ' ' . $bulan . ' ' . date('Y', $ts) . ', ' . date('H:i', $ts) . ' WIB'
    ];
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'rows' => $rows,
    'total' => $total,
    'counts' => $counts,
    'page' => $page,
    'hasMore' => ($offset + $limit) < $total
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
