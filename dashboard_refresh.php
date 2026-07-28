<?php
session_start();

ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function dashboardJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user'])) {
    dashboardJson([
        'success' => false,
        'message' => 'Sesi login berakhir.'
    ], 401);
}

require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Jakarta');

if (isset($conn) && $conn instanceof mysqli) {
    @$conn->query("SET time_zone = '+07:00'");
}

$today = date('Y-m-d');
$totalUsers = 0;
$presensi = [
    'hadir' => 0,
    'belum' => 0,
    'telat' => 0,
    'wfo' => 0,
    'wfa' => 0,
    'pulang_cepat' => 0
];

function dashboardApiTableExists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function dashboardApiColumnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

if (dashboardApiTableExists($conn, 'users')) {
    $qUsers = $conn->query("SELECT COUNT(*) AS total FROM users");
    if ($qUsers) {
        $totalUsers = (int)($qUsers->fetch_assoc()['total'] ?? 0);
    }
}

if (dashboardApiTableExists($conn, 'presensi_lokasi_petugas')) {
    $hasStatus = dashboardApiColumnExists($conn, 'presensi_lokasi_petugas', 'status_presensi');
    $hasMenitTelat = dashboardApiColumnExists($conn, 'presensi_lokasi_petugas', 'menit_telat');
    $hasMenitPulangCepat = dashboardApiColumnExists($conn, 'presensi_lokasi_petugas', 'menit_pulang_cepat');

    $telatExpr = "TIME(created_at) > '08:00:00'";
    if ($hasStatus && $hasMenitTelat) {
        $telatExpr = "(COALESCE(status_presensi,'')='Telat' OR COALESCE(menit_telat,0)>0 OR TIME(created_at)>'08:00:00')";
    } elseif ($hasStatus) {
        $telatExpr = "(COALESCE(status_presensi,'')='Telat' OR TIME(created_at)>'08:00:00')";
    } elseif ($hasMenitTelat) {
        $telatExpr = "(COALESCE(menit_telat,0)>0 OR TIME(created_at)>'08:00:00')";
    }

    $pulangCepatExpr = '0';
    if ($hasStatus && $hasMenitPulangCepat) {
        $pulangCepatExpr = "(COALESCE(status_presensi,'')='Pulang Cepat' OR COALESCE(menit_pulang_cepat,0)>0)";
    } elseif ($hasStatus) {
        $pulangCepatExpr = "COALESCE(status_presensi,'')='Pulang Cepat'";
    } elseif ($hasMenitPulangCepat) {
        $pulangCepatExpr = "COALESCE(menit_pulang_cepat,0)>0";
    }

    $safeToday = $conn->real_escape_string($today);
    $sql = "
        SELECT
            COUNT(DISTINCT CASE
                WHEN LOWER(TRIM(jenis_presensi))='masuk'
                THEN COALESCE(NULLIF(CAST(user_id AS CHAR),'0'), NULLIF(LOWER(TRIM(nama_petugas)),''))
            END) AS hadir,
            COUNT(DISTINCT CASE
                WHEN LOWER(TRIM(jenis_presensi))='masuk' AND {$telatExpr}
                THEN COALESCE(NULLIF(CAST(user_id AS CHAR),'0'), NULLIF(LOWER(TRIM(nama_petugas)),''))
            END) AS telat,
            COUNT(DISTINCT CASE
                WHEN LOWER(TRIM(jenis_presensi))='masuk' AND UPPER(TRIM(COALESCE(catatan,'')))='WFO'
                THEN COALESCE(NULLIF(CAST(user_id AS CHAR),'0'), NULLIF(LOWER(TRIM(nama_petugas)),''))
            END) AS wfo,
            COUNT(DISTINCT CASE
                WHEN LOWER(TRIM(jenis_presensi))='masuk' AND UPPER(TRIM(COALESCE(catatan,'')))='WFA'
                THEN COALESCE(NULLIF(CAST(user_id AS CHAR),'0'), NULLIF(LOWER(TRIM(nama_petugas)),''))
            END) AS wfa,
            COUNT(DISTINCT CASE
                WHEN LOWER(TRIM(jenis_presensi))='pulang' AND {$pulangCepatExpr}
                THEN COALESCE(NULLIF(CAST(user_id AS CHAR),'0'), NULLIF(LOWER(TRIM(nama_petugas)),''))
            END) AS pulang_cepat
        FROM presensi_lokasi_petugas
        WHERE DATE(created_at)='{$safeToday}'
    ";

    $qToday = $conn->query($sql);
    if ($qToday) {
        $row = $qToday->fetch_assoc();
        foreach (['hadir', 'telat', 'wfo', 'wfa', 'pulang_cepat'] as $key) {
            $presensi[$key] = (int)($row[$key] ?? 0);
        }
    }
}

$presensi['belum'] = max(0, $totalUsers - $presensi['hadir']);

$trendLabels = [];
$trendMap = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $trendLabels[] = date('d/m', strtotime($date));
    $trendMap[$date] = 0;
}

if (dashboardApiTableExists($conn, 'presensi_lokasi_petugas')) {
    $safeToday = $conn->real_escape_string($today);
    $qTrend = $conn->query("
        SELECT DATE(created_at) AS tanggal, COUNT(DISTINCT user_id) AS total
        FROM presensi_lokasi_petugas
        WHERE LOWER(TRIM(jenis_presensi))='masuk'
          AND DATE(created_at) >= DATE_SUB('{$safeToday}', INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
    ");

    if ($qTrend) {
        while ($row = $qTrend->fetch_assoc()) {
            $date = (string)($row['tanggal'] ?? '');
            if (array_key_exists($date, $trendMap)) {
                $trendMap[$date] = (int)($row['total'] ?? 0);
            }
        }
    }
}

dashboardJson([
    'success' => true,
    'updated_at' => date('H:i:s'),
    'total_users' => $totalUsers,
    'presensi' => $presensi,
    'trend_7_hari' => [
        'labels' => $trendLabels,
        'values' => array_values($trendMap)
    ]
]);
