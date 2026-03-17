<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require 'config.php';
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

$status = $_GET['status'] ?? 'keluar';
$q      = trim($_GET['q']      ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

if (!in_array($status, ['keluar', 'kembali'])) $status = 'keluar';

$where  = "WHERE status = ?";
$params = [$status];
$types  = 's';

if ($q !== '') {
    $like    = '%' . $q . '%';
    $where  .= " AND (plat_nomor LIKE ? OR pengemudi LIKE ? OR tujuan LIKE ? OR keterangan LIKE ?)";
    $params  = array_merge($params, [$like, $like, $like, $like]);
    $types  .= 'ssss';
}

if ($status === 'kembali') {
    $where .= " AND waktu_keluar >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$cntStmt = $conn->prepare("SELECT COUNT(*) FROM kendaraan_operasional_log $where");
$cntStmt->bind_param($types, ...$params);
$cntStmt->execute();
$cntStmt->bind_result($total);
$cntStmt->fetch();
$cntStmt->close();

$sql       = "SELECT * FROM kendaraan_operasional_log $where ORDER BY waktu_keluar DESC LIMIT ? OFFSET ?";
$allParams = array_merge($params, [$limit, $offset]);
$allTypes  = $types . 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$res  = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $tKeluar = strtotime($row['waktu_keluar']);
    $tanggal = date('d M Y H:i', $tKeluar);

    if ($row['status'] === 'kembali' && $row['waktu_kembali']) {
        $detik          = strtotime($row['waktu_kembali']) - $tKeluar;
        $jam_d          = floor($detik / 3600);
        $mnt_d          = floor(($detik % 3600) / 60);
        $durasiDisplay  = $jam_d > 0 ? "{$jam_d}j {$mnt_d}m" : "{$mnt_d} menit";
        $kembaliDisplay = date('d M Y H:i', strtotime($row['waktu_kembali']));
    } else {
        $detik          = time() - $tKeluar;
        $jam_d          = floor($detik / 3600);
        $mnt_d          = floor(($detik % 3600) / 60);
        $durasiDisplay  = $jam_d > 0 ? "{$jam_d}j {$mnt_d}m" : "{$mnt_d} menit";
        $kembaliDisplay = '';
    }

    $rows[] = [
        'id'              => (int)$row['id'],
        'plat_nomor'      => $row['plat_nomor'],
        'pengemudi'       => $row['pengemudi'],
        'tujuan'          => $row['tujuan'],
        'keterangan'      => $row['keterangan'] ?? '',
        'tanggal_display' => $tanggal,
        'kembali_display' => $kembaliDisplay,
        'durasi'          => $durasiDisplay,
        'status'          => $row['status'],
        'dicatat_oleh'    => $row['dicatat_oleh'],
    ];
}
$stmt->close();

// Count status lawan untuk update badge tab sebaliknya
$lawanStatus = $status === 'keluar' ? 'kembali' : 'keluar';
$cntLawan = $conn->query("SELECT COUNT(*) FROM kendaraan_operasional_log WHERE status='$lawanStatus'")->fetch_row()[0];

// Untuk riwayat kembali batasi 30 hari juga
if ($lawanStatus === 'kembali') {
    $cntLawan = $conn->query("SELECT COUNT(*) FROM kendaraan_operasional_log WHERE status='kembali' AND waktu_keluar >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_row()[0];
}

echo json_encode([
    'rows'       => $rows,
    'page'       => $page,
    'hasMore'    => ($offset + count($rows)) < $total,
    'total'      => (int)$total,
    'totalLawan' => (int)$cntLawan,
]);
