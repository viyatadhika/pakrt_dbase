<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
date_default_timezone_set('Asia/Jakarta');
$todaySql = date('Y-m-d');
if (isset($conn) && $conn instanceof mysqli) {
    @$conn->query("SET time_zone = '+07:00'");
}
$activePage = basename($_SERVER['PHP_SELF']);

$title = "Beranda";
include 'header.php';

$namaLengkap = $_SESSION['user']['nama'] ?? '';
$namaDepan   = explode(' ', trim($namaLengkap))[0];
$fotoProfil  = $_SESSION['user']['foto_profil'] ?? null;

// Inisial nama
$parts   = explode(" ", trim($namaLengkap));
$initial = strtoupper(substr($parts[0], 0, 1));
$initial .= count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';

/* ===================== SUMMARY DASHBOARD ===================== */
$summaryResult = $conn->query("
    SELECT
        COUNT(*)                                                        AS total,
        COUNT(DISTINCT nama_petugas)                                    AS total_petugas,
        COUNT(DISTINCT form_type)                                       AS total_form,
        COUNT(DISTINCT CASE WHEN area_kerja <> '' THEN area_kerja END)  AS total_area
    FROM checklist_forms
");

if ($summaryResult) {
    $summary      = $summaryResult->fetch_assoc();
    $total        = $summary['total']         ?? 0;
    $totalPetugas = $summary['total_petugas'] ?? 0;
    $totalForm    = $summary['total_form']    ?? 0;
    $totalArea    = $summary['total_area']    ?? 0;
} else {
    $total = $totalPetugas = $totalForm = $totalArea = 0;
}

/* ===================== GRAFIK JENIS FORM ===================== */
$qGrafik = $conn->query("
    SELECT form_type, COUNT(*) AS total
    FROM checklist_forms
    GROUP BY form_type
    ORDER BY total DESC
");

$chartLabels = [];
$chartValues = [];

if ($qGrafik) {
    while ($row = $qGrafik->fetch_assoc()) {
        $chartLabels[] = $row['form_type'];
        $chartValues[] = $row['total'];
    }
}

/* ===================== GRAFIK AREA KERJA ===================== */
$qAreaChart = $conn->query("
    SELECT area_kerja, COUNT(*) AS total
    FROM checklist_forms
    WHERE area_kerja IS NOT NULL AND area_kerja <> ''
    GROUP BY area_kerja
    ORDER BY total DESC
");

$areaLabels = [];
$areaValues = [];

if ($qAreaChart) {
    while ($row = $qAreaChart->fetch_assoc()) {
        $areaLabels[] = $row['area_kerja'];
        $areaValues[] = $row['total'];
    }
}


/* ===================== RINGKASAN PRESENSI KONSERVATIF ===================== */
function dashboardTableExists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function dashboardColumnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}



function dashboardFetchPelatihanBerlangsung(mysqli $conn): array
{
    $candidates = [
        ['table' => 'timetable', 'name' => ['nama_kegiatan', 'nama', 'judul', 'agenda', 'kegiatan'], 'start' => ['start_date', 'tanggal_mulai', 'mulai', 'tanggal_awal', 'tgl_mulai', 'tanggal'], 'end' => ['end_date', 'tanggal_selesai', 'selesai', 'tanggal_akhir', 'tgl_selesai', 'tanggal']],
        ['table' => 'jadwal_kegiatan', 'name' => ['nama_kegiatan', 'nama', 'judul', 'agenda', 'kegiatan'], 'start' => ['start_date', 'tanggal_mulai', 'mulai', 'tanggal_awal', 'tgl_mulai', 'tanggal'], 'end' => ['end_date', 'tanggal_selesai', 'selesai', 'tanggal_akhir', 'tgl_selesai', 'tanggal']],
        ['table' => 'kegiatan', 'name' => ['nama_kegiatan', 'nama', 'judul', 'agenda', 'kegiatan'], 'start' => ['start_date', 'tanggal_mulai', 'mulai', 'tanggal_awal', 'tgl_mulai', 'tanggal'], 'end' => ['end_date', 'tanggal_selesai', 'selesai', 'tanggal_akhir', 'tgl_selesai', 'tanggal']],
        ['table' => 'agenda_kegiatan', 'name' => ['nama_kegiatan', 'nama', 'judul', 'agenda', 'kegiatan'], 'start' => ['start_date', 'tanggal_mulai', 'mulai', 'tanggal_awal', 'tgl_mulai', 'tanggal'], 'end' => ['end_date', 'tanggal_selesai', 'selesai', 'tanggal_akhir', 'tgl_selesai', 'tanggal']],
    ];

    foreach ($candidates as $cfg) {
        $table = $cfg['table'];
        if (!dashboardTableExists($conn, $table)) continue;

        $nameCol = '';
        foreach ($cfg['name'] as $col) {
            if (dashboardColumnExists($conn, $table, $col)) {
                $nameCol = $col;
                break;
            }
        }
        $startCol = '';
        foreach ($cfg['start'] as $col) {
            if (dashboardColumnExists($conn, $table, $col)) {
                $startCol = $col;
                break;
            }
        }
        $endCol = '';
        foreach ($cfg['end'] as $col) {
            if (dashboardColumnExists($conn, $table, $col)) {
                $endCol = $col;
                break;
            }
        }

        if ($nameCol === '' || $startCol === '') continue;
        if ($endCol === '') $endCol = $startCol;

        $safeTable = $conn->real_escape_string($table);
        $safeName = $conn->real_escape_string($nameCol);
        $safeStart = $conn->real_escape_string($startCol);
        $safeEnd = $conn->real_escape_string($endCol);

        $sql = "
            SELECT `{$safeName}` AS nama, `{$safeStart}` AS mulai, `{$safeEnd}` AS selesai
            FROM `{$safeTable}`
            WHERE DATE(`{$safeStart}`) <= CURDATE()
              AND DATE(`{$safeEnd}`) >= CURDATE()
            ORDER BY `{$safeStart}` ASC
            LIMIT 5
        ";
        $q = $conn->query($sql);
        if (!$q) continue;

        $rows = [];
        while ($row = $q->fetch_assoc()) {
            $nama = trim((string)($row['nama'] ?? ''));
            if ($nama !== '') $rows[] = $row;
        }
        if ($rows) return $rows;
    }

    return [];
}

function dashboardSafeCountQuery(mysqli $conn, string $sql): int
{
    $q = $conn->query($sql);
    if (!$q) return 0;
    $r = $q->fetch_assoc();
    return (int)($r['total'] ?? 0);
}

function dashboardCountRows(mysqli $conn, string $table, array $dateColumns = []): int
{
    if (!dashboardTableExists($conn, $table)) return 0;

    global $todaySql;
    $today = $conn->real_escape_string($todaySql ?: date('Y-m-d'));
    $safeTable = $conn->real_escape_string($table);

    foreach ($dateColumns as $col) {
        if (!dashboardColumnExists($conn, $table, $col)) continue;
        $safeCol = $conn->real_escape_string($col);
        return dashboardSafeCountQuery($conn, "SELECT COUNT(*) AS total FROM `{$safeTable}` WHERE DATE(`{$safeCol}`) = '{$today}'");
    }

    return 0;
}

function dashboardCountRowsFromTables(mysqli $conn, array $tables, array $dateColumns = []): int
{
    $total = 0;
    foreach ($tables as $table) {
        $total += dashboardCountRows($conn, $table, $dateColumns);
    }
    return $total;
}

function dashboardCountBetween(mysqli $conn, string $table, array $dateColumns = []): int
{
    if (!dashboardTableExists($conn, $table)) return 0;

    global $todaySql;
    $safeTable = $conn->real_escape_string($table);
    $safeToday = $conn->real_escape_string($todaySql ?: date('Y-m-d'));

    foreach ($dateColumns as $col) {
        if (!dashboardColumnExists($conn, $table, $col)) continue;
        $safeCol = $conn->real_escape_string($col);
        return dashboardSafeCountQuery($conn, "SELECT COUNT(*) AS total FROM `{$safeTable}` WHERE YEAR(`{$safeCol}`)=YEAR('{$safeToday}') AND MONTH(`{$safeCol}`)=MONTH('{$safeToday}')");
    }

    return 0;
}

function dashboardCountActiveAgenda(mysqli $conn, string $table, string $startCol, string $endCol): int
{
    if (!dashboardTableExists($conn, $table)) return 0;
    if (!dashboardColumnExists($conn, $table, $startCol)) return 0;
    if (!dashboardColumnExists($conn, $table, $endCol)) $endCol = $startCol;

    global $todaySql;
    $today = $conn->real_escape_string($todaySql ?: date('Y-m-d'));
    $safeTable = $conn->real_escape_string($table);
    $safeStart = $conn->real_escape_string($startCol);
    $safeEnd = $conn->real_escape_string($endCol);

    return dashboardSafeCountQuery($conn, "SELECT COUNT(*) AS total FROM `{$safeTable}` WHERE DATE(`{$safeStart}`) <= '{$today}' AND DATE(`{$safeEnd}`) >= '{$today}'");
}

function dashboardFirstAvailableCount(mysqli $conn, array $configs): int
{
    foreach ($configs as $cfg) {
        $type = $cfg['type'] ?? 'today';
        if ($type === 'active') {
            $count = dashboardCountActiveAgenda($conn, $cfg['table'], $cfg['start'], $cfg['end']);
        } else {
            $count = dashboardCountRows($conn, $cfg['table'], $cfg['date_columns'] ?? []);
        }

        if ($count > 0) return $count;

        // Jika tabelnya memang ada tetapi hari ini kosong, gunakan 0 sebagai jawaban final
        // agar tidak tertukar dengan tabel lain yang kebetulan punya nama mirip.
        if (isset($cfg['table']) && dashboardTableExists($conn, $cfg['table'])) return 0;
    }

    return 0;
}

$dashPresensi = [
    'hadir' => 0,
    'telat' => 0,
    'wfo' => 0,
    'wfa' => 0,
    'belum' => 0,
    'pulang_cepat' => 0,
];
$dashBelumPresensi = [];
$dashTerlambat = [];
$dashPresensiTerbaru = [];
$dashChart7Labels = [];
$dashChart7Values = [];

if (dashboardTableExists($conn, 'presensi_lokasi_petugas')) {
    $dashHasStatusPresensi = dashboardColumnExists($conn, 'presensi_lokasi_petugas', 'status_presensi');
    $dashHasMenitTelat = dashboardColumnExists($conn, 'presensi_lokasi_petugas', 'menit_telat');
    $dashHasMenitPulangCepat = dashboardColumnExists($conn, 'presensi_lokasi_petugas', 'menit_pulang_cepat');

    $dashTelatExprToday = "TIME(created_at) > '08:00:00'";
    if ($dashHasStatusPresensi && $dashHasMenitTelat) {
        $dashTelatExprToday = "(COALESCE(status_presensi,'') = 'Telat' OR COALESCE(menit_telat,0) > 0 OR TIME(created_at) > '08:00:00')";
    } elseif ($dashHasStatusPresensi) {
        $dashTelatExprToday = "(COALESCE(status_presensi,'') = 'Telat' OR TIME(created_at) > '08:00:00')";
    } elseif ($dashHasMenitTelat) {
        $dashTelatExprToday = "(COALESCE(menit_telat,0) > 0 OR TIME(created_at) > '08:00:00')";
    }

    $dashPulangCepatExprToday = "0";
    if ($dashHasStatusPresensi && $dashHasMenitPulangCepat) {
        $dashPulangCepatExprToday = "(COALESCE(status_presensi,'') = 'Pulang Cepat' OR COALESCE(menit_pulang_cepat,0) > 0)";
    } elseif ($dashHasStatusPresensi) {
        $dashPulangCepatExprToday = "COALESCE(status_presensi,'') = 'Pulang Cepat'";
    } elseif ($dashHasMenitPulangCepat) {
        $dashPulangCepatExprToday = "COALESCE(menit_pulang_cepat,0) > 0";
    }

    $qPresensiHariIni = $conn->query("
        SELECT
            COUNT(DISTINCT CASE WHEN LOWER(TRIM(jenis_presensi)) = 'masuk' THEN COALESCE(NULLIF(CAST(user_id AS CHAR), '0'), NULLIF(LOWER(TRIM(nama_petugas)), '')) END) AS hadir,
            COUNT(DISTINCT CASE WHEN LOWER(TRIM(jenis_presensi)) = 'masuk' AND {$dashTelatExprToday} THEN COALESCE(NULLIF(CAST(user_id AS CHAR), '0'), NULLIF(LOWER(TRIM(nama_petugas)), '')) END) AS telat,
            COUNT(DISTINCT CASE WHEN LOWER(TRIM(jenis_presensi)) = 'masuk' AND UPPER(TRIM(COALESCE(catatan,''))) = 'WFO' THEN COALESCE(NULLIF(CAST(user_id AS CHAR), '0'), NULLIF(LOWER(TRIM(nama_petugas)), '')) END) AS wfo,
            COUNT(DISTINCT CASE WHEN LOWER(TRIM(jenis_presensi)) = 'masuk' AND UPPER(TRIM(COALESCE(catatan,''))) = 'WFA' THEN COALESCE(NULLIF(CAST(user_id AS CHAR), '0'), NULLIF(LOWER(TRIM(nama_petugas)), '')) END) AS wfa,
            COUNT(DISTINCT CASE WHEN LOWER(TRIM(jenis_presensi)) = 'pulang' AND {$dashPulangCepatExprToday} THEN COALESCE(NULLIF(CAST(user_id AS CHAR), '0'), NULLIF(LOWER(TRIM(nama_petugas)), '')) END) AS pulang_cepat
        FROM presensi_lokasi_petugas
        WHERE DATE(created_at) = '{$todaySql}'
    ");
    if ($qPresensiHariIni) {
        $pr = $qPresensiHariIni->fetch_assoc();
        $dashPresensi['hadir'] = (int)($pr['hadir'] ?? 0);
        $dashPresensi['telat'] = (int)($pr['telat'] ?? 0);
        $dashPresensi['wfo'] = (int)($pr['wfo'] ?? 0);
        $dashPresensi['wfa'] = (int)($pr['wfa'] ?? 0);
        $dashPresensi['pulang_cepat'] = (int)($pr['pulang_cepat'] ?? 0);
    }

    if (dashboardTableExists($conn, 'users')) {
        $qTotalUser = $conn->query("SELECT COUNT(*) AS total FROM users");
        if ($qTotalUser) {
            $tu = $qTotalUser->fetch_assoc();
            $dashPresensi['belum'] = max(0, (int)($tu['total'] ?? 0) - $dashPresensi['hadir']);
        }

        $qBelum = $conn->query("\n            SELECT u.nama\n            FROM users u\n            LEFT JOIN presensi_lokasi_petugas p\n              ON p.user_id = u.id\n             AND LOWER(TRIM(p.jenis_presensi)) = 'masuk'\n             AND DATE(p.created_at) = '{$todaySql}'\n            WHERE p.id IS NULL\n            ORDER BY u.nama ASC\n            LIMIT 5\n        ");
        if ($qBelum) {
            while ($b = $qBelum->fetch_assoc()) $dashBelumPresensi[] = $b;
        }
    }

    $dashMenitTelatSelect = $dashHasMenitTelat ? 'menit_telat' : '0 AS menit_telat';
    $qTelat = $conn->query("
        SELECT nama_petugas, created_at, {$dashMenitTelatSelect}
        FROM presensi_lokasi_petugas
        WHERE LOWER(TRIM(jenis_presensi)) = 'masuk'
          AND DATE(created_at) = '{$todaySql}'
          AND {$dashTelatExprToday}
        ORDER BY created_at DESC
        LIMIT 5
    ");
    if ($qTelat) {
        while ($t = $qTelat->fetch_assoc()) $dashTerlambat[] = $t;
    }

    $qTerbaru = $conn->query("\n        SELECT nama_petugas, jenis_presensi, catatan, created_at\n        FROM presensi_lokasi_petugas\n        WHERE DATE(created_at) = '{$todaySql}'\n        ORDER BY created_at DESC, id DESC\n        LIMIT 5\n    ");
    if ($qTerbaru) {
        while ($p = $qTerbaru->fetch_assoc()) $dashPresensiTerbaru[] = $p;
    }

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $dashChart7Labels[] = date('d/m', strtotime($date));
        $dashChart7Values[$date] = 0;
    }
    $q7 = $conn->query("\n        SELECT DATE(created_at) AS tanggal, COUNT(DISTINCT user_id) AS total\n        FROM presensi_lokasi_petugas\n        WHERE LOWER(TRIM(jenis_presensi)) = 'masuk'\n          AND DATE(created_at) >= DATE_SUB('{$todaySql}', INTERVAL 6 DAY)\n        GROUP BY DATE(created_at)\n    ");
    if ($q7) {
        while ($r7 = $q7->fetch_assoc()) {
            $tgl7 = (string)$r7['tanggal'];
            if (isset($dashChart7Values[$tgl7])) $dashChart7Values[$tgl7] = (int)$r7['total'];
        }
    }
}
$dashChart7ValuesOut = array_values($dashChart7Values);

/* ===================== RINGKASAN OPERASIONAL KONSERVATIF ===================== */
$dashOpsDateColumns = ['created_at', 'tanggal', 'tanggal_input', 'tgl_input', 'tanggal_laporan', 'waktu_lapor', 'tgl_laporan', 'tanggal_surat', 'tgl_surat', 'tanggal_mulai', 'tgl_mulai', 'start_date', 'start_time', 'mulai'];

$dashOps = [
    // Sesuai struktur database warga_rt_bsdk:
    // checklist_forms memakai kolom tanggal, laporan_kerusakan memakai created_at,
    // barang_masuk/barang_keluar memakai tanggal, arsip_surat memakai tanggal_surat.
    'checklist_today' => dashboardCountRows($conn, 'checklist_forms', ['tanggal', 'created_at']),
    'checklist_month' => dashboardCountBetween($conn, 'checklist_forms', ['tanggal', 'created_at']),
    'kerusakan_today' => dashboardFirstAvailableCount($conn, [
        ['table' => 'laporan_kerusakan', 'date_columns' => ['created_at', 'tanggal_laporan', 'tanggal', 'waktu_lapor']],
        ['table' => 'kerusakan', 'date_columns' => $dashOpsDateColumns],
        ['table' => 'laporan_kerusakan_fasilitas', 'date_columns' => $dashOpsDateColumns],
    ]),
    'barang_masuk_today' => dashboardFirstAvailableCount($conn, [
        ['table' => 'barang_masuk', 'date_columns' => ['tanggal', 'created_at', 'tanggal_masuk']],
        ['table' => 'gudang_barang_masuk', 'date_columns' => $dashOpsDateColumns],
        ['table' => 'stok_masuk', 'date_columns' => $dashOpsDateColumns],
    ]),
    'barang_keluar_today' => dashboardFirstAvailableCount($conn, [
        ['table' => 'barang_keluar', 'date_columns' => ['tanggal', 'created_at', 'tanggal_keluar']],
        ['table' => 'gudang_barang_keluar', 'date_columns' => $dashOpsDateColumns],
        ['table' => 'stok_keluar', 'date_columns' => $dashOpsDateColumns],
    ]),
    'surat_today' => dashboardFirstAvailableCount($conn, [
        ['table' => 'arsip_surat', 'date_columns' => ['tanggal_surat', 'created_at', 'tanggal']],
        ['table' => 'surat_masuk', 'date_columns' => $dashOpsDateColumns],
        ['table' => 'surat_keluar', 'date_columns' => $dashOpsDateColumns],
        ['table' => 'surat', 'date_columns' => $dashOpsDateColumns],
    ]),
    'rapat_today' => dashboardFirstAvailableCount($conn, [
        ['table' => 'booking_ruang_rapat', 'date_columns' => ['start_date', 'tanggal', 'created_at']],
        ['table' => 'booking_rapat', 'date_columns' => ['start_date', 'tanggal', 'created_at']],
        ['table' => 'peminjaman_ruang_rapat', 'date_columns' => ['start_date', 'tanggal', 'created_at']],
        // fallback untuk database yang belum punya tabel booking rapat: agenda aktif hari ini
        ['type' => 'active', 'table' => 'agenda_kegiatan', 'start' => 'start_date', 'end' => 'end_date'],
    ]),
];
$dashOps['gudang_today'] = $dashOps['barang_masuk_today'] + $dashOps['barang_keluar_today'];
$dashOps['total_today'] = $dashOps['checklist_today'] + $dashOps['kerusakan_today'] + $dashOps['gudang_today'] + $dashOps['surat_today'] + $dashOps['rapat_today'];

$dashBriefParts = [];
$dashBriefParts[] = (int)$dashPresensi['hadir'] . ' pegawai sudah presensi masuk';
if ((int)$dashPresensi['belum'] > 0) $dashBriefParts[] = (int)$dashPresensi['belum'] . ' belum presensi';
if ((int)$dashPresensi['telat'] > 0) $dashBriefParts[] = (int)$dashPresensi['telat'] . ' terlambat';
if ((int)$dashOps['total_today'] > 0) $dashBriefParts[] = (int)$dashOps['total_today'] . ' aktivitas operasional tercatat';
$dashBriefText = implode(', ', $dashBriefParts) . '.';

/* ===================== EXECUTIVE DASHBOARD V2 ===================== */
$execTotalUsers = 0;
$execBelumPulang = [];
$execGpsTidakValid = 0;
$execMonth = [
    'hadir' => 0,
    'telat' => 0,
    'wfo' => 0,
    'wfa' => 0,
    'pulang_cepat' => 0,
    'hari_ini_pulang' => 0,
];
$execKpi = [
    'presensi' => 0,
    'wfo' => 0,
    'wfa' => 0,
    'telat' => 0,
];

if (dashboardTableExists($conn, 'users')) {
    $qExecUser = $conn->query("SELECT COUNT(*) AS total FROM users");
    if ($qExecUser) {
        $er = $qExecUser->fetch_assoc();
        $execTotalUsers = (int)($er['total'] ?? 0);
    }
}

if (dashboardTableExists($conn, 'presensi_lokasi_petugas')) {
    $hasStatusPresensi = dashboardColumnExists($conn, 'presensi_lokasi_petugas', 'status_presensi');
    $hasMenitTelat = dashboardColumnExists($conn, 'presensi_lokasi_petugas', 'menit_telat');
    $hasMenitPulangCepat = dashboardColumnExists($conn, 'presensi_lokasi_petugas', 'menit_pulang_cepat');
    $telatExpr = "TIME(created_at) > '08:00:00'";
    if ($hasStatusPresensi && $hasMenitTelat) {
        $telatExpr = "(status_presensi = 'Telat' OR menit_telat > 0 OR TIME(created_at) > '08:00:00')";
    } elseif ($hasStatusPresensi) {
        $telatExpr = "(status_presensi = 'Telat' OR TIME(created_at) > '08:00:00')";
    } elseif ($hasMenitTelat) {
        $telatExpr = "(menit_telat > 0 OR TIME(created_at) > '08:00:00')";
    }

    $pulangCepatExpr = "0";
    if ($hasStatusPresensi && $hasMenitPulangCepat) {
        $pulangCepatExpr = "(status_presensi = 'Pulang Cepat' OR menit_pulang_cepat > 0)";
    } elseif ($hasStatusPresensi) {
        $pulangCepatExpr = "status_presensi = 'Pulang Cepat'";
    } elseif ($hasMenitPulangCepat) {
        $pulangCepatExpr = "menit_pulang_cepat > 0";
    }

    $qExecToday = $conn->query("\n        SELECT\n            SUM(CASE WHEN LOWER(TRIM(jenis_presensi))='pulang' THEN 1 ELSE 0 END) AS hari_ini_pulang,\n            SUM(CASE WHEN lokasi_valid <> 1 THEN 1 ELSE 0 END) AS gps_tidak_valid\n        FROM presensi_lokasi_petugas\n        WHERE DATE(created_at)='{$todaySql}'\n    ");
    if ($qExecToday) {
        $et = $qExecToday->fetch_assoc();
        $execMonth['hari_ini_pulang'] = (int)($et['hari_ini_pulang'] ?? 0);
        $execGpsTidakValid = (int)($et['gps_tidak_valid'] ?? 0);
    }

    $qExecMonth = $conn->query("\n        SELECT\n            SUM(CASE WHEN LOWER(TRIM(jenis_presensi))='masuk' THEN 1 ELSE 0 END) AS hadir,\n            SUM(CASE WHEN LOWER(TRIM(jenis_presensi))='masuk' AND {$telatExpr} THEN 1 ELSE 0 END) AS telat,\n            SUM(CASE WHEN LOWER(TRIM(jenis_presensi))='masuk' AND UPPER(COALESCE(catatan,''))='WFO' THEN 1 ELSE 0 END) AS wfo,\n            SUM(CASE WHEN LOWER(TRIM(jenis_presensi))='masuk' AND UPPER(COALESCE(catatan,''))='WFA' THEN 1 ELSE 0 END) AS wfa,\n            SUM(CASE WHEN LOWER(TRIM(jenis_presensi))='pulang' AND {$pulangCepatExpr} THEN 1 ELSE 0 END) AS pulang_cepat\n        FROM presensi_lokasi_petugas\n        WHERE YEAR(created_at)=YEAR('{$todaySql}') AND MONTH(created_at)=MONTH('{$todaySql}')\n    ");
    if ($qExecMonth) {
        $em = $qExecMonth->fetch_assoc();
        foreach (['hadir', 'telat', 'wfo', 'wfa', 'pulang_cepat'] as $k) $execMonth[$k] = (int)($em[$k] ?? 0);
    }

    if (dashboardTableExists($conn, 'users')) {
        $qBelumPulang = $conn->query("\n            SELECT u.nama\n            FROM users u\n            JOIN presensi_lokasi_petugas pm\n              ON pm.user_id = u.id\n             AND LOWER(TRIM(pm.jenis_presensi)) = 'masuk'\n             AND DATE(pm.created_at) = '{$todaySql}'\n            LEFT JOIN presensi_lokasi_petugas pp\n              ON pp.user_id = u.id\n             AND LOWER(TRIM(pp.jenis_presensi)) = 'pulang'\n             AND DATE(pp.created_at) = '{$todaySql}'\n            WHERE pp.id IS NULL\n            GROUP BY u.id, u.nama\n            ORDER BY u.nama ASC\n            LIMIT 5\n        ");
        if ($qBelumPulang) {
            while ($bp = $qBelumPulang->fetch_assoc()) $execBelumPulang[] = $bp;
        }
    }
}

$execKpi['presensi'] = $execTotalUsers > 0 ? min(100, round(((int)$dashPresensi['hadir'] / $execTotalUsers) * 100)) : 0;
$execKpi['wfo'] = (int)$dashPresensi['hadir'] > 0 ? round(((int)$dashPresensi['wfo'] / max(1, (int)$dashPresensi['hadir'])) * 100) : 0;
$execKpi['wfa'] = (int)$dashPresensi['hadir'] > 0 ? round(((int)$dashPresensi['wfa'] / max(1, (int)$dashPresensi['hadir'])) * 100) : 0;
$execKpi['telat'] = (int)$dashPresensi['hadir'] > 0 ? round(((int)$dashPresensi['telat'] / max(1, (int)$dashPresensi['hadir'])) * 100) : 0;

$execPelatihanBerlangsung = dashboardFetchPelatihanBerlangsung($conn);
$execPelatihanNames = [];
foreach ($execPelatihanBerlangsung as $pl) {
    $nm = trim((string)($pl['nama'] ?? ''));
    if ($nm !== '') $execPelatihanNames[] = $nm;
}

$execAiSummary = 'Hingga pukul ' . date('H:i') . ' WIB, tercatat ' . (int)$dashPresensi['hadir'] . ' pegawai telah melakukan presensi masuk';
if ($execTotalUsers > 0) {
    $execAiSummary .= ' dari total ' . $execTotalUsers . ' pegawai';
}
$execAiSummary .= ', terdiri dari ' . (int)$dashPresensi['wfo'] . ' WFO dan ' . (int)$dashPresensi['wfa'] . ' WFA. ';
$execAiSummary .= 'Terdapat ' . (int)$dashPresensi['telat'] . ' pegawai terlambat dan ' . (int)$dashPresensi['belum'] . ' pegawai belum melakukan presensi. ';
$execAiSummary .= 'Operasional hari ini mencatat ' . (int)$dashOps['total_today'] . ' aktivitas utama.';
if ($execPelatihanNames) {
    $execAiSummary .= ' Saat ini terdapat ' . count($execPelatihanNames) . ' pelatihan atau kegiatan yang terdeteksi sedang berlangsung.';
} else {
    $execAiSummary .= ' Belum ada data pelatihan yang terdeteksi sedang berlangsung hari ini.';
}
if ($execGpsTidakValid > 0) {
    $execAiSummary .= ' Perhatian: terdapat ' . $execGpsTidakValid . ' presensi dengan GPS tidak valid yang perlu dicek.';
}


?>

<style>
    .bg-red {
        background: #ffe4e6;
        color: #dc2626;
    }

    .bg-teal {
        background: #ccfbf1;
        color: #0d9488;
    }

    .bg-yellow {
        background: #fef9c3;
        color: #ca8a04;
    }

    .bg-indigo {
        background: #e0e7ff;
        color: #4338ca;
    }

    /* ===== MODAL STATE ===== */
    body.modal-open {
        overflow: hidden;
    }

    body.modal-open nav,
    body.modal-open .bottom-nav,
    body.modal-open .navbar,
    body.modal-open #bottomNav,
    body.modal-open #navMonitoring {
        filter: blur(4px);
        pointer-events: none;
        transition: .3s ease;
    }

    body.modal-open .page-container,
    body.modal-open header {
        transition: .3s ease;
    }

    /* ===== FAB AI ===== */
    .fab-ai {
        position: fixed;
        right: 18px;
        bottom: calc(88px + env(safe-area-inset-bottom, 0px));
        z-index: 9998;
        width: 58px;
        height: 58px;
        border: none;
        border-radius: 999px;
        background: linear-gradient(135deg, #0ea5e9, #2563eb);
        color: #fff;
        box-shadow: 0 10px 30px rgba(37, 99, 235, .35);
        cursor: pointer;
        transition: .25s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .fab-ai:hover {
        transform: translateY(-2px) scale(1.03);
    }

    .fab-ai i {
        font-size: 22px;
        line-height: 1;
    }

    .fab-ai-label {
        position: absolute;
        opacity: 0;
        pointer-events: none;
        white-space: nowrap;
        font-size: 13px;
        font-weight: 700;
    }

    .fab-ai.show-label {
        width: auto;
        min-width: 58px;
        padding: 0 18px;
        gap: 10px;
        justify-content: center;
    }

    .fab-ai.show-label .fab-ai-label {
        position: static;
        opacity: 1;
        pointer-events: auto;
    }

    .ai-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .35);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 9998;
        opacity: 0;
        pointer-events: none;
        transition: .25s ease;
    }

    .ai-overlay.show {
        opacity: 1;
        pointer-events: auto;
    }

    .ai-sheet {
        position: fixed;
        left: 0;
        right: 0;
        bottom: -110%;
        z-index: 9999;
        background: #fff;
        border-top-left-radius: 24px;
        border-top-right-radius: 24px;
        box-shadow: 0 -10px 40px rgba(0, 0, 0, .18);
        padding: 10px 14px calc(14px + env(safe-area-inset-bottom, 0px));
        height: min(82vh, calc(var(--app-vh, 1vh) * 82));
        max-height: min(82vh, calc(var(--app-vh, 1vh) * 82));
        display: flex;
        flex-direction: column;
        transition: transform .3s ease, bottom .3s ease, height .2s ease;
        will-change: transform, bottom, height;
    }

    .ai-sheet.show {
        bottom: 0;
    }

    .ai-sheet.keyboard-open {
        height: min(96vh, calc(var(--app-vh, 1vh) * 96));
        max-height: min(96vh, calc(var(--app-vh, 1vh) * 96));
    }

    .ai-sheet-handle {
        width: 42px;
        height: 5px;
        background: #d1d5db;
        border-radius: 999px;
        margin: 4px auto 12px;
    }

    .ai-header {
        position: sticky;
        top: 0;
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        padding-bottom: 8px;
        background: #fff;
    }

    .ai-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .ai-icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        background: linear-gradient(135deg, #e0f2fe, #dbeafe);
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .ai-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: #111827;
    }

    .ai-header p {
        margin: 2px 0 0;
        font-size: 12px;
        color: #6b7280;
    }

    .ai-close {
        width: 38px;
        height: 38px;
        border: none;
        border-radius: 50%;
        background: #f3f4f6;
        color: #4b5563;
        cursor: pointer;
    }

    .ai-body {
        flex: 1;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        padding: 6px 2px 14px;
        min-height: 0;
        max-height: none;
        scroll-padding-bottom: 120px;
    }

    .ai-message {
        display: flex;
        margin-bottom: 10px;
    }

    .ai-message.ai-user {
        justify-content: flex-end;
    }

    .ai-message.ai-bot {
        justify-content: flex-start;
    }

    .ai-bubble {
        max-width: 85%;
        padding: 12px 14px;
        border-radius: 18px;
        font-size: 13px;
        line-height: 1.55;
        white-space: pre-wrap;
    }

    .ai-user .ai-bubble {
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        color: #fff;
        border-bottom-right-radius: 6px;
    }

    .ai-bot .ai-bubble {
        background: #f3f4f6;
        color: #111827;
        border-bottom-left-radius: 6px;
    }

    .ai-suggestions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
        padding-bottom: 6px;
    }

    .ai-chip {
        border: none;
        border-radius: 999px;
        padding: 10px 12px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .ai-input-wrap {
        position: sticky;
        bottom: 0;
        z-index: 4;
        display: flex;
        align-items: flex-end;
        gap: 10px;
        padding: 10px 0 calc(2px + env(safe-area-inset-bottom, 0px));
        border-top: 1px solid #e5e7eb;
        background: #fff;
    }

    .ai-input {
        flex: 1;
        min-height: 46px;
        border: 1px solid #d1d5db;
        border-radius: 14px;
        padding: 12px 14px;
        font-size: 13px;
        line-height: 1.4;
        outline: none;
        background: #fff;
    }

    .ai-input:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96, 165, 250, .15);
    }

    .ai-send {
        width: 46px;
        height: 46px;
        border: none;
        border-radius: 14px;
        background: linear-gradient(135deg, #0ea5e9, #2563eb);
        color: #fff;
        cursor: pointer;
        flex-shrink: 0;
    }

    .ai-loading {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #6b7280;
        font-size: 12px;
    }

    .ai-loading span {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #9ca3af;
        animation: blink 1.2s infinite ease-in-out;
    }

    .ai-loading span:nth-child(2) {
        animation-delay: .2s;
    }

    .ai-loading span:nth-child(3) {
        animation-delay: .4s;
    }

    @keyframes blink {

        0%,
        80%,
        100% {
            opacity: .3;
            transform: scale(.85);
        }

        40% {
            opacity: 1;
            transform: scale(1);
        }
    }

    @media (max-width: 640px) {
        .fab-ai {
            right: 14px;
            bottom: calc(78px + env(safe-area-inset-bottom, 0px));
            width: 54px;
            height: 54px;
            padding: 0;
            font-size: 13px;
        }

        .fab-ai i {
            font-size: 20px;
        }

        .fab-ai.show-label {
            width: auto;
            min-width: 54px;
            padding: 0 16px;
        }

        .fab-ai.show-label .fab-ai-label {
            font-size: 12px;
        }

        .ai-sheet {
            left: 0;
            right: 0;
            bottom: -110%;
            border-top-left-radius: 22px;
            border-top-right-radius: 22px;
            padding-top: 10px;
            padding-left: 12px;
            padding-right: 12px;
            padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
            height: min(88vh, calc(var(--app-vh, 1vh) * 88));
            max-height: min(88vh, calc(var(--app-vh, 1vh) * 88));
        }

        .ai-sheet.keyboard-open {
            height: min(100vh, calc(var(--app-vh, 1vh) * 100));
            max-height: min(100vh, calc(var(--app-vh, 1vh) * 100));
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .ai-header {
            margin-bottom: 10px;
        }

        .ai-header-left {
            gap: 10px;
            min-width: 0;
        }

        .ai-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .ai-header h3 {
            font-size: 15px;
            line-height: 1.2;
        }

        .ai-header p {
            font-size: 11px;
            line-height: 1.35;
        }

        .ai-close {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
        }

        .ai-body {
            padding: 4px 2px 12px;
            scroll-padding-bottom: 140px;
        }

        .ai-message {
            margin-bottom: 8px;
        }

        .ai-bubble {
            max-width: 92%;
            font-size: 12.5px;
            line-height: 1.5;
            padding: 11px 12px;
        }

        .ai-suggestions {
            gap: 7px;
            margin-top: 8px;
        }

        .ai-chip {
            font-size: 11px;
            padding: 9px 11px;
            border-radius: 999px;
        }

        .ai-input-wrap {
            gap: 8px;
            padding-top: 8px;
        }

        .ai-input {
            min-height: 44px;
            font-size: 16px;
            padding: 11px 13px;
            border-radius: 13px;
        }

        .ai-send {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            flex-shrink: 0;
        }
    }


    /* ===== PRESENSI DASHBOARD - konservatif, tidak mengubah layout lama ===== */
    .presensi-section {
        margin: 0 0 1rem;
    }

    .presensi-hero {
        border-radius: 22px;
        background: linear-gradient(135deg, #0284c7, #0ea5e9);
        color: #fff;
        padding: 1rem;
        box-shadow: 0 14px 30px rgba(2, 132, 199, .22);
        overflow: hidden;
        position: relative;
    }

    .presensi-hero::after {
        content: "";
        position: absolute;
        width: 120px;
        height: 120px;
        right: -42px;
        top: -48px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .14);
    }

    .presensi-hero-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        position: relative;
        z-index: 1;
    }

    .presensi-title {
        font-size: .95rem;
        font-weight: 800;
        margin: 0;
    }

    .presensi-sub {
        font-size: .72rem;
        opacity: .85;
        margin-top: .15rem;
    }

    .presensi-actions {
        display: flex;
        gap: .4rem;
        flex-shrink: 0;
    }

    .presensi-action-btn {
        height: 34px;
        width: 34px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .18);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        border: 1px solid rgba(255, 255, 255, .20);
    }

    .presensi-mini-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: .55rem;
        margin-top: .9rem;
        position: relative;
        z-index: 1;
    }

    .presensi-mini-card {
        background: rgba(255, 255, 255, .16);
        border: 1px solid rgba(255, 255, 255, .20);
        border-radius: 16px;
        padding: .65rem .5rem;
        min-height: 70px;
    }

    .presensi-mini-label {
        font-size: .62rem;
        font-weight: 700;
        opacity: .82;
        white-space: nowrap;
    }

    .presensi-mini-value {
        font-size: 1.35rem;
        font-weight: 900;
        line-height: 1;
        margin-top: .35rem;
    }

    .presensi-soft-grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: .75rem;
        margin-top: .75rem;
    }

    .presensi-soft-card {
        border-radius: 20px;
        background: #fff;
        border: 1px solid #e0f2fe;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        padding: .9rem;
        min-height: 116px;
    }

    .presensi-card-head {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        align-items: center;
        margin-bottom: .65rem;
    }

    .presensi-card-title {
        font-size: .82rem;
        font-weight: 800;
        color: #0f172a;
    }

    .presensi-card-link {
        font-size: .7rem;
        font-weight: 800;
        color: #0284c7;
        text-decoration: none;
        white-space: nowrap;
    }

    .presensi-list {
        display: grid;
        gap: .5rem;
    }

    .presensi-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .62rem .7rem;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .presensi-person {
        min-width: 0;
    }

    .presensi-person strong {
        display: block;
        font-size: .76rem;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .presensi-person span {
        display: block;
        font-size: .67rem;
        color: #64748b;
        margin-top: .08rem;
        font-weight: 600;
    }

    .presensi-pill {
        border-radius: 999px;
        padding: .35rem .55rem;
        font-size: .65rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .pill-blue {
        background: #e0f2fe;
        color: #0369a1;
    }

    .pill-green {
        background: #dcfce7;
        color: #166534;
    }

    .pill-red {
        background: #fee2e2;
        color: #991b1b;
    }

    .pill-amber {
        background: #fef3c7;
        color: #92400e;
    }

    .presensi-empty {
        padding: .8rem;
        border-radius: 14px;
        background: #f8fafc;
        color: #64748b;
        font-size: .75rem;
        font-weight: 700;
        text-align: center;
        border: 1px dashed #cbd5e1;
    }

    .presensi-chart-bars {
        display: flex;
        align-items: end;
        gap: .45rem;
        height: 115px;
        padding-top: .35rem;
    }

    .presensi-bar-wrap {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .35rem;
        min-width: 0;
    }

    .presensi-bar {
        width: 100%;
        max-width: 24px;
        border-radius: 999px 999px 6px 6px;
        background: linear-gradient(180deg, #38bdf8, #0284c7);
        min-height: 6px;
    }

    .presensi-bar-label {
        font-size: .6rem;
        color: #64748b;
        font-weight: 800;
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        .presensi-mini-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .presensi-soft-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .presensi-hero-top {
            align-items: flex-start;
        }

        .presensi-mini-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .presensi-mini-card {
            min-height: 64px;
        }

        .presensi-mini-value {
            font-size: 1.22rem;
        }
    }


    /* ===== FINAL POLISH DASHBOARD KONSERVATIF ===== */
    .final-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: 0 1rem;
        margin: 1rem 0 .65rem;
        color: #111827;
        font-size: .95rem;
        font-weight: 800;
    }

    .final-section-title small {
        color: #94a3b8;
        font-size: .68rem;
        font-weight: 700;
    }

    .ops-grid-final {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: .65rem;
        padding: 0 1rem;
        margin-bottom: 1rem;
    }

    .ops-card-final {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 18px;
        padding: .8rem .7rem;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .045);
        min-height: 88px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .ops-icon-final {
        width: 34px;
        height: 34px;
        border-radius: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: .5rem;
        font-size: .9rem;
    }

    .ops-label-final {
        color: #64748b;
        font-size: .65rem;
        font-weight: 800;
        line-height: 1.15;
    }

    .ops-value-final {
        color: #0f172a;
        font-size: 1.25rem;
        font-weight: 900;
        line-height: 1;
        margin-top: .35rem;
    }

    .dashboard-brief-final {
        margin: 0 1rem 1rem;
        border-radius: 18px;
        background: linear-gradient(135deg, #f8fafc, #ffffff);
        border: 1px solid #e2e8f0;
        padding: .85rem .9rem;
        display: flex;
        gap: .75rem;
        align-items: flex-start;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    }

    .dashboard-brief-icon-final {
        width: 38px;
        height: 38px;
        border-radius: 15px;
        background: #e0f2fe;
        color: #0284c7;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .dashboard-brief-final strong {
        display: block;
        font-size: .82rem;
        font-weight: 900;
        color: #0f172a;
        margin-bottom: .15rem;
    }

    .dashboard-brief-final span {
        display: block;
        font-size: .72rem;
        font-weight: 700;
        color: #64748b;
        line-height: 1.45;
    }

    .kinerja-grid .kinerja-card,
    .presensi-soft-card,
    .ops-card-final {
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .kinerja-grid .kinerja-card:hover,
    .presensi-soft-card:hover,
    .ops-card-final:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 28px rgba(15, 23, 42, .07);
    }

    @media (max-width: 900px) {
        .ops-grid-final {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 520px) {
        .ops-grid-final {
            grid-template-columns: repeat(2, 1fr);
            gap: .55rem;
        }

        .ops-card-final {
            min-height: 82px;
            border-radius: 16px;
        }

        .final-section-title {
            margin-top: .85rem;
        }

        .dashboard-brief-final {
            margin-left: 1rem;
            margin-right: 1rem;
        }
    }


    /* ===== EXECUTIVE DASHBOARD V2 - KONSERVATIF ===== */
    .exec-wrap {
        margin: 0 1rem 1rem;
    }

    .exec-hero {
        border-radius: 24px;
        background: linear-gradient(135deg, #0f172a 0%, #0369a1 48%, #0ea5e9 100%);
        color: #fff;
        padding: 1rem;
        box-shadow: 0 16px 36px rgba(2, 132, 199, .22);
        position: relative;
        overflow: hidden;
    }

    .exec-hero::after {
        content: "";
        position: absolute;
        width: 160px;
        height: 160px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .12);
        top: -70px;
        right: -55px;
    }

    .exec-top {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .8rem;
        margin-bottom: .9rem;
    }

    .exec-kicker {
        font-size: .65rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .08em;
        opacity: .78;
    }

    .exec-title {
        margin-top: .18rem;
        font-size: 1.1rem;
        font-weight: 900;
        line-height: 1.15;
    }

    .exec-date {
        margin-top: .2rem;
        font-size: .72rem;
        font-weight: 700;
        opacity: .82;
    }

    .exec-badge {
        position: relative;
        z-index: 1;
        border: 1px solid rgba(255, 255, 255, .25);
        background: rgba(255, 255, 255, .14);
        border-radius: 999px;
        padding: .55rem .75rem;
        font-size: .68rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .exec-grid {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: .55rem;
    }

    .exec-mini {
        min-height: 74px;
        border-radius: 18px;
        padding: .7rem .65rem;
        background: rgba(255, 255, 255, .14);
        border: 1px solid rgba(255, 255, 255, .18);
        backdrop-filter: blur(8px);
    }

    .exec-mini span {
        display: block;
        font-size: .62rem;
        font-weight: 800;
        opacity: .8;
        line-height: 1.15;
    }

    .exec-mini strong {
        display: block;
        margin-top: .38rem;
        font-size: 1.35rem;
        line-height: 1;
        font-weight: 900;
        letter-spacing: -.04em;
    }

    .exec-panel-grid {
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        gap: .75rem;
        margin-top: .75rem;
    }

    .exec-panel {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 22px;
        padding: .9rem;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
    }

    .exec-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .75rem;
    }

    .exec-panel-title {
        color: #0f172a;
        font-size: .86rem;
        font-weight: 900;
        line-height: 1.15;
    }

    .exec-panel-sub {
        color: #94a3b8;
        font-size: .66rem;
        font-weight: 700;
        margin-top: .12rem;
    }

    .exec-kpi-list {
        display: grid;
        gap: .58rem;
    }

    .exec-kpi-row {
        display: grid;
        grid-template-columns: 95px 1fr 44px;
        align-items: center;
        gap: .55rem;
        font-size: .72rem;
        font-weight: 800;
        color: #475569;
    }

    .exec-kpi-bar {
        height: 9px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .exec-kpi-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #38bdf8, #0284c7);
    }

    .exec-watch-list {
        display: grid;
        gap: .55rem;
    }

    .exec-watch-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .6rem;
        padding: .65rem .7rem;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .exec-watch-item strong {
        display: block;
        font-size: .75rem;
        color: #0f172a;
        line-height: 1.2;
    }

    .exec-watch-item span {
        display: block;
        margin-top: .1rem;
        font-size: .63rem;
        color: #64748b;
        font-weight: 700;
    }

    .exec-pill {
        border-radius: 999px;
        padding: .35rem .55rem;
        font-size: .62rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .exec-pill.red {
        background: #fee2e2;
        color: #991b1b;
    }

    .exec-pill.amber {
        background: #fef3c7;
        color: #92400e;
    }

    .exec-pill.blue {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .exec-ai-summary {
        margin-top: .75rem;
        border-radius: 20px;
        padding: .74rem .52rem .78rem;
        background: linear-gradient(135deg, #f8fafc, #eff6ff);
        border: 1px solid #dbeafe;
        display: flex;
        align-items: flex-start;
        gap: .5rem;
        width: 100%;
        box-sizing: border-box;
    }

    .exec-ai-summary>div:last-child {
        flex: 1;
        min-width: 0;
    }

    .exec-ai-icon {
        width: 36px;
        height: 36px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #dbeafe;
        color: #1d4ed8;
        flex-shrink: 0;
    }

    .exec-ai-summary strong {
        display: block;
        font-size: .82rem;
        font-weight: 900;
        color: #0f172a;
        margin-bottom: .12rem;
    }

    .exec-ai-meta {
        display: block;
        font-size: .62rem;
        font-weight: 800;
        color: #94a3b8;
        margin-bottom: .45rem;
        line-height: 1.35;
    }

    .exec-ai-copy {
        display: block;
        margin: 0;
        font-size: .74rem;
        font-weight: 700;
        color: #64748b;
        line-height: 1.65;
        text-align: justify;
        text-justify: inter-word;
        hyphens: auto;
    }

    .exec-ai-training {
        margin-top: .65rem;
        padding-top: .6rem;
        border-top: 1px dashed #cbd5e1;
    }

    .exec-ai-training-title {
        font-size: .68rem;
        font-weight: 900;
        color: #0f172a;
        margin-bottom: .35rem;
    }

    .exec-ai-training ul {
        margin: 0;
        padding-left: 1rem;
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        line-height: 1.6;
        text-align: justify;
        text-justify: inter-word;
    }

    @media (max-width: 900px) {
        .exec-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .exec-panel-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 520px) {
        .exec-wrap {
            margin-left: 1rem;
            margin-right: 1rem;
        }

        .exec-hero {
            border-radius: 22px;
            padding: .9rem;
        }

        .exec-top {
            display: block;
        }

        .exec-badge {
            display: inline-flex;
            margin-top: .65rem;
        }

        .exec-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: .5rem;
        }

        .exec-mini {
            min-height: 68px;
            border-radius: 16px;
        }

        .exec-mini strong {
            font-size: 1.2rem;
        }

        .exec-kpi-row {
            grid-template-columns: 80px 1fr 38px;
            gap: .45rem;
        }
    }


    .presensi-section-compact {
        margin-top: 14px;
    }

    .presensi-compact-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: 0 4px 12px;
        padding: 2px 2px 0;
    }

    @media (max-width: 640px) {
        .presensi-compact-head {
            align-items: flex-start;
        }
    }


    /* ===== FIX KHUSUS PRESENSI MOBILE/TABLET =====
       Desktop tetap mengikuti tampilan sebelumnya.
       Perbaikan ini hanya menyamakan lebar kiri-kanan panel presensi
       dengan section dashboard lain di mobile dan tablet. */
    .presensi-section-compact .presensi-action-btn {
        background: #eff6ff;
        color: #0284c7;
        border: 1px solid #dbeafe;
    }

    @media (max-width: 900px) {
        body[data-page="beranda"] .page-container>.presensi-section-compact {
            width: auto !important;
            max-width: none !important;
            margin-left: 1rem !important;
            margin-right: 1rem !important;
            margin-bottom: 1rem !important;
            box-sizing: border-box !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-compact-head {
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-soft-grid {
            width: 100% !important;
            max-width: 100% !important;
            grid-template-columns: 1fr !important;
            gap: .75rem !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            box-sizing: border-box !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-soft-card {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            border-radius: 20px;
        }
    }

    @media (max-width: 640px) {
        body[data-page="beranda"] .page-container>.presensi-section-compact {
            margin-left: 1rem !important;
            margin-right: 1rem !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-compact-head {
            align-items: flex-start !important;
            gap: .65rem !important;
            margin-bottom: .75rem !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-actions {
            gap: .35rem !important;
            flex-shrink: 0 !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-action-btn {
            width: 34px !important;
            height: 34px !important;
            border-radius: 12px !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-soft-card {
            padding: .85rem !important;
            min-height: auto !important;
            border-radius: 18px !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-list-item {
            padding: .62rem .65rem !important;
            border-radius: 14px !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-person strong {
            font-size: .75rem !important;
        }

        body[data-page="beranda"] .presensi-section-compact .presensi-person span,
        body[data-page="beranda"] .presensi-section-compact .presensi-pill {
            font-size: .63rem !important;
        }
    }


    /* ===== FIX LEBAR SEMUA INFORMASI BARU MOBILE/TABLET =====
       Desktop tidak diubah. Semua panel baru disamakan dengan lebar Menu Cepat
       yang memakai padding kiri-kanan 1rem pada page-container. */
    @media (max-width: 900px) {

        body[data-page="beranda"] .page-container>.exec-wrap,
        body[data-page="beranda"] .page-container>.presensi-section,
        body[data-page="beranda"] .page-container>.presensi-section-compact,
        body[data-page="beranda"] .page-container>.dashboard-brief-final {
            width: auto !important;
            max-width: none !important;
            margin-left: 1rem !important;
            margin-right: 1rem !important;
            box-sizing: border-box !important;
        }

        body[data-page="beranda"] .page-container>.ops-grid-final {
            width: auto !important;
            max-width: none !important;
            padding-left: 1rem !important;
            padding-right: 1rem !important;
            box-sizing: border-box !important;
        }

        body[data-page="beranda"] .page-container>.final-section-title {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            box-sizing: border-box !important;
        }

        body[data-page="beranda"] .exec-hero,
        body[data-page="beranda"] .exec-panel,
        body[data-page="beranda"] .presensi-hero,
        body[data-page="beranda"] .presensi-soft-card,
        body[data-page="beranda"] .dashboard-brief-final,
        body[data-page="beranda"] .ops-card-final {
            box-sizing: border-box !important;
            max-width: 100% !important;
        }

        body[data-page="beranda"] .exec-panel-grid,
        body[data-page="beranda"] .presensi-soft-grid {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
    }

    @media (max-width: 640px) {

        body[data-page="beranda"] .page-container>.exec-wrap,
        body[data-page="beranda"] .page-container>.presensi-section,
        body[data-page="beranda"] .page-container>.presensi-section-compact,
        body[data-page="beranda"] .page-container>.dashboard-brief-final {
            margin-left: 1rem !important;
            margin-right: 1rem !important;
        }

        body[data-page="beranda"] .page-container>.ops-grid-final,
        body[data-page="beranda"] .page-container>.final-section-title {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }

        body[data-page="beranda"] .exec-grid,
        body[data-page="beranda"] .presensi-mini-grid,
        body[data-page="beranda"] .ops-grid-final {
            gap: .55rem !important;
        }
    }



    /* ===== FINAL FIX MOBILE/TABLET FULL WIDTH =====
       Desktop tidak diubah. Pada mobile/tablet, section informasi baru
       mengikuti lebar container lama. Tidak pakai margin tambahan agar
       tidak dobel dengan padding .page-container dan px-4 Menu Cepat. */
    @media (max-width: 900px) {

        body[data-page="beranda"] .page-container>.exec-wrap,
        body[data-page="beranda"] .page-container>.presensi-section,
        body[data-page="beranda"] .page-container>.presensi-section-compact,
        body[data-page="beranda"] .page-container>.dashboard-brief-final {
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            box-sizing: border-box !important;
        }

        body[data-page="beranda"] .page-container>.ops-grid-final {
            width: 100% !important;
            max-width: 100% !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            box-sizing: border-box !important;
        }

        body[data-page="beranda"] .page-container>.final-section-title {
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            box-sizing: border-box !important;
        }

        body[data-page="beranda"] .exec-hero,
        body[data-page="beranda"] .exec-panel,
        body[data-page="beranda"] .dashboard-brief-final,
        body[data-page="beranda"] .presensi-soft-card,
        body[data-page="beranda"] .ops-card-final {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
    }

    @media (max-width: 640px) {

        body[data-page="beranda"] .page-container>.exec-wrap,
        body[data-page="beranda"] .page-container>.presensi-section,
        body[data-page="beranda"] .page-container>.presensi-section-compact,
        body[data-page="beranda"] .page-container>.dashboard-brief-final {
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        body[data-page="beranda"] .page-container>.ops-grid-final,
        body[data-page="beranda"] .page-container>.final-section-title {
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        body[data-page="beranda"] .exec-hero {
            border-radius: 22px !important;
        }
    }


    /* ===== FIX BADGE MONITORING MOBILE/TABLET ===== */
    .exec-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .42rem;
        padding: .52rem .78rem;
        min-height: 34px;
        border-radius: 999px;
        line-height: 1.15;
        white-space: nowrap;
        letter-spacing: 0;
    }

    .exec-badge i {
        flex-shrink: 0;
        font-size: .78rem;
        line-height: 1;
        margin-right: .02rem;
    }

    @media (max-width: 768px) {
        .exec-badge {
            align-self: flex-start;
            margin-top: .75rem;
            padding: .58rem .9rem;
            font-size: .72rem;
            gap: .48rem;
        }
    }

    @media (max-width: 420px) {
        .exec-badge {
            font-size: .7rem;
            padding: .56rem .82rem;
        }
    }

    @media (max-width: 768px) {
        .exec-ai-summary {
            gap: .46rem;
            padding: .68rem .48rem .72rem;
            border-radius: 18px;
        }

        .exec-ai-icon {
            width: 34px;
            height: 34px;
            border-radius: 13px;
            font-size: .86rem;
        }

        .exec-ai-summary strong {
            font-size: .8rem;
            margin-bottom: .08rem;
        }

        .exec-ai-copy {
            font-size: .74rem;
            line-height: 1.62;
            letter-spacing: -.005em;
        }

        .exec-ai-meta {
            font-size: .58rem;
            margin-bottom: .34rem;
        }
    }

    @media (max-width: 420px) {
        .exec-ai-summary {
            padding: .64rem .42rem .68rem;
            gap: .44rem;
        }

        .exec-ai-icon {
            width: 30px;
            height: 30px;
            border-radius: 11px;
        }

        .exec-ai-copy {
            font-size: .735rem;
            line-height: 1.58;
        }
    }



    /* FIX Ringkasan AI: isi teks dibuat melebar penuh sampai sisi kiri/kanan card */
    .exec-ai-summary {
        display: grid !important;
        grid-template-columns: 36px minmax(0, 1fr) !important;
        column-gap: .5rem !important;
        row-gap: .34rem !important;
        padding: .74rem .52rem .78rem !important;
        align-items: start !important;
    }

    .exec-ai-summary>div:last-child {
        display: contents !important;
    }

    .exec-ai-summary strong,
    .exec-ai-summary .exec-ai-meta {
        grid-column: 2 / 3 !important;
        min-width: 0 !important;
    }

    .exec-ai-summary .exec-ai-copy,
    .exec-ai-summary .exec-ai-training {
        grid-column: 1 / -1 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        box-sizing: border-box !important;
    }

    .exec-ai-summary .exec-ai-copy {
        text-align: justify !important;
        text-justify: inter-word !important;
        line-height: 1.62 !important;
    }

    .exec-ai-training ul {
        padding-left: .95rem !important;
    }

    @media (max-width: 768px) {
        .exec-ai-summary {
            grid-template-columns: 34px minmax(0, 1fr) !important;
            padding: .68rem .42rem .72rem !important;
            column-gap: .46rem !important;
            row-gap: .28rem !important;
        }

        .exec-ai-summary .exec-ai-copy {
            font-size: .735rem !important;
            line-height: 1.58 !important;
        }
    }

    @media (max-width: 420px) {
        .exec-ai-summary {
            grid-template-columns: 30px minmax(0, 1fr) !important;
            padding: .64rem .34rem .68rem !important;
            column-gap: .42rem !important;
        }
    }

    /* FINAL POLISH - Ringkasan AI compact, lebar teks maksimal, timestamp rapat */
    .exec-ai-summary {
        display: grid !important;
        grid-template-columns: 34px minmax(0, 1fr) !important;
        column-gap: 9px !important;
        row-gap: 6px !important;
        align-items: start !important;
        padding: 12px 8px 13px !important;
        width: 100% !important;
        box-sizing: border-box !important;
        border-radius: 20px !important;
    }

    .exec-ai-summary>div:last-child {
        display: contents !important;
    }

    .exec-ai-icon {
        width: 34px !important;
        height: 34px !important;
        border-radius: 13px !important;
        grid-column: 1 / 2 !important;
        grid-row: 1 / 3 !important;
        margin: 0 !important;
    }

    .exec-ai-summary strong {
        grid-column: 2 / 3 !important;
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1.12 !important;
        font-size: .86rem !important;
        min-width: 0 !important;
    }

    .exec-ai-summary .exec-ai-meta {
        grid-column: 2 / 3 !important;
        display: block !important;
        margin: 1px 0 0 !important;
        padding: 0 !important;
        line-height: 1.12 !important;
        font-size: .61rem !important;
        min-width: 0 !important;
    }

    .exec-ai-summary .exec-ai-copy,
    .exec-ai-summary .exec-ai-training {
        grid-column: 1 / -1 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        box-sizing: border-box !important;
    }

    .exec-ai-summary .exec-ai-copy {
        margin-top: 2px !important;
        font-size: .76rem !important;
        line-height: 1.58 !important;
        text-align: justify !important;
        text-justify: inter-word !important;
        hyphens: auto !important;
        color: #475569 !important;
    }

    .exec-ai-summary .exec-ai-training {
        margin-top: 7px !important;
        padding-top: 8px !important;
        border-top: 1px solid rgba(148, 163, 184, .18) !important;
    }

    .exec-ai-training ul {
        padding-left: 16px !important;
        margin: 5px 0 0 !important;
    }

    @media (max-width: 768px) {
        .exec-ai-summary {
            grid-template-columns: 32px minmax(0, 1fr) !important;
            column-gap: 8px !important;
            row-gap: 5px !important;
            padding: 11px 7px 12px !important;
        }

        .exec-ai-icon {
            width: 32px !important;
            height: 32px !important;
            border-radius: 12px !important;
        }

        .exec-ai-summary strong {
            font-size: .84rem !important;
            line-height: 1.1 !important;
        }

        .exec-ai-summary .exec-ai-meta {
            font-size: .58rem !important;
            line-height: 1.12 !important;
            margin-top: 1px !important;
        }

        .exec-ai-summary .exec-ai-copy {
            font-size: .745rem !important;
            line-height: 1.55 !important;
            margin-top: 1px !important;
        }
    }

    @media (max-width: 420px) {
        .exec-ai-summary {
            grid-template-columns: 30px minmax(0, 1fr) !important;
            column-gap: 7px !important;
            row-gap: 4px !important;
            padding: 10px 6px 11px !important;
            border-radius: 18px !important;
        }

        .exec-ai-icon {
            width: 30px !important;
            height: 30px !important;
            border-radius: 11px !important;
        }

        .exec-ai-summary .exec-ai-copy {
            font-size: .735rem !important;
            line-height: 1.53 !important;
        }
    }


    /* ===== FINAL PRODUKSI: RINGKASAN AI LEBIH LEBAR + NUMBERING PELATIHAN ===== */
    .exec-ai-summary {
        padding: 12px 10px 13px !important;
        display: grid !important;
        grid-template-columns: 34px minmax(0, 1fr) !important;
        column-gap: 9px !important;
        row-gap: 6px !important;
        align-items: start !important;
        box-sizing: border-box !important;
    }

    .exec-ai-summary>div:last-child {
        display: contents !important;
    }

    .exec-ai-icon {
        grid-column: 1 / 2 !important;
        grid-row: 1 / 3 !important;
        width: 34px !important;
        height: 34px !important;
        border-radius: 13px !important;
        margin: 0 !important;
    }

    .exec-ai-summary strong {
        grid-column: 2 / 3 !important;
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1.12 !important;
        font-size: .86rem !important;
        min-width: 0 !important;
    }

    .exec-ai-meta {
        grid-column: 2 / 3 !important;
        margin: 1px 0 0 !important;
        padding: 0 !important;
        line-height: 1.12 !important;
        font-size: .61rem !important;
        min-width: 0 !important;
    }

    .exec-ai-copy,
    .exec-ai-training {
        grid-column: 1 / -1 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        box-sizing: border-box !important;
    }

    .exec-ai-copy {
        margin-top: 2px !important;
        font-size: .76rem !important;
        line-height: 1.58 !important;
        color: #475569 !important;
        text-align: justify !important;
        text-justify: inter-word !important;
        hyphens: auto !important;
    }

    .exec-ai-training {
        margin-top: 8px !important;
        padding-top: 9px !important;
        border-top: 1px solid rgba(148, 163, 184, .22) !important;
    }

    .exec-ai-training-title {
        font-size: .69rem !important;
        font-weight: 900 !important;
        color: #0f172a !important;
        margin-bottom: 8px !important;
    }

    .exec-ai-training ul {
        display: none !important;
    }

    .exec-ai-training-list {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
        display: grid !important;
        gap: 8px !important;
        counter-reset: pelatihan-counter !important;
    }

    .exec-ai-training-list li {
        counter-increment: pelatihan-counter !important;
        display: grid !important;
        grid-template-columns: 28px minmax(0, 1fr) !important;
        gap: 8px !important;
        align-items: start !important;
        color: #475569 !important;
        font-size: .735rem !important;
        font-weight: 700 !important;
        line-height: 1.55 !important;
    }

    .exec-ai-training-list li::before {
        content: counter(pelatihan-counter) !important;
        width: 24px !important;
        height: 24px !important;
        border-radius: 999px !important;
        background: #dbeafe !important;
        color: #1d4ed8 !important;
        border: 1px solid #bfdbfe !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: .72rem !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        margin-top: 1px !important;
    }

    .exec-ai-training-list li.more::before {
        content: "+" !important;
        background: #f1f5f9 !important;
        color: #475569 !important;
        border-color: #e2e8f0 !important;
    }

    .exec-ai-training-list li span {
        min-width: 0 !important;
        text-align: left !important;
    }

    @media (max-width: 768px) {
        .exec-ai-summary {
            padding: 11px 8px 12px !important;
            grid-template-columns: 32px minmax(0, 1fr) !important;
            column-gap: 8px !important;
            row-gap: 5px !important;
        }

        .exec-ai-icon {
            width: 32px !important;
            height: 32px !important;
            border-radius: 12px !important;
        }

        .exec-ai-summary strong {
            font-size: .84rem !important;
            line-height: 1.1 !important;
        }

        .exec-ai-meta {
            font-size: .58rem !important;
            line-height: 1.12 !important;
        }

        .exec-ai-copy {
            font-size: .745rem !important;
            line-height: 1.55 !important;
        }

        .exec-ai-training-list {
            gap: 7px !important;
        }

        .exec-ai-training-list li {
            grid-template-columns: 27px minmax(0, 1fr) !important;
            gap: 7px !important;
            font-size: .72rem !important;
            line-height: 1.52 !important;
        }

        .exec-ai-training-list li::before {
            width: 23px !important;
            height: 23px !important;
        }
    }

    @media (max-width: 420px) {
        .exec-ai-summary {
            padding: 10px 5px 11px !important;
            grid-template-columns: 30px minmax(0, 1fr) !important;
            column-gap: 7px !important;
            row-gap: 4px !important;
        }

        .exec-ai-icon {
            width: 30px !important;
            height: 30px !important;
            border-radius: 11px !important;
        }

        .exec-ai-copy {
            font-size: .735rem !important;
            line-height: 1.53 !important;
        }

        .exec-ai-training-list li {
            grid-template-columns: 26px minmax(0, 1fr) !important;
            gap: 6px !important;
            font-size: .705rem !important;
        }

        .exec-ai-training-list li::before {
            width: 22px !important;
            height: 22px !important;
            font-size: .68rem !important;
        }
    }
</style>

<body data-page="beranda">
    <header>
        <div class="header-left">
            <div class="profile-avatar">
                <?php if ($fotoProfil && file_exists("uploads/$fotoProfil")): ?>
                    <img src="uploads/<?= $fotoProfil ?>" alt="Foto Profil">
                <?php else: ?>
                    <span class="avatar-text"><?= $initial ?></span>
                <?php endif; ?>
            </div>
            <div class="header-text">
                <h3>Halo, <?= htmlspecialchars($namaDepan); ?>👋</h3>
                <p>Semoga harimu menyenangkan</p>
            </div>
        </div>
        <div id="logoutLogo" class="header-right"><i class="fas fa-right-from-bracket"></i></div>
    </header>

    <div class="page-container">
        <div class="search-box mb-4">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span id="searchHint" class="search-hint">Cari laporan hari ini</span>
            <input type="text" id="searchQuery" class="search-input" autocomplete="off">
        </div>

        <div class="relative mb-4">
            <div id="carousel" class="flex gap-3 overflow-x-auto scrollbar-hide scroll-smooth snap-x">
                <div class="carousel-item flex-shrink-0 snap-center bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-4 rounded-2xl shadow flex items-center gap-3 w-full sm:w-80 h-24">
                    <img src="dokumen.png" class="w-12 h-12" alt="">
                    <div>
                        <h2 class="text-sm font-semibold text-white drop-shadow-sm">Cek Administrasi</h2>
                        <p class="text-xs opacity-80">Pantau laporan harian dan kegiatan terbaru</p>
                    </div>
                </div>

                <div class="carousel-item flex-shrink-0 snap-center bg-gradient-to-r from-green-400 to-emerald-600 text-white p-4 rounded-2xl shadow flex items-center gap-3 w-full sm:w-80 h-24">
                    <img src="cleaning.png" class="w-12 h-12" alt="">
                    <div>
                        <h2 class="text-sm font-semibold text-white drop-shadow-sm">Update Kebersihan</h2>
                        <p class="text-xs opacity-80">Laporan checklist kebersihan tersedia</p>
                    </div>
                </div>

                <div class="carousel-item flex-shrink-0 snap-center bg-gradient-to-r from-orange-400 to-red-500 text-white p-4 rounded-2xl shadow flex items-center gap-3 w-full sm:w-80 h-24">
                    <img src="kinerja.png" class="w-12 h-12" alt="">
                    <div>
                        <h2 class="text-sm font-semibold text-white drop-shadow-sm">Pemantauan Kinerja</h2>
                        <p class="text-xs opacity-80">Data progres pekerjaan tersedia</p>
                    </div>
                </div>
            </div>
            <div class="flex justify-center mt-2 gap-2">
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        </div>




        <section class="exec-wrap">
            <div class="exec-hero">
                <div class="exec-top">
                    <div>
                        <div class="exec-kicker">Executive Dashboard</div>
                        <div class="exec-title">Operasional Hari Ini</div>
                        <div class="exec-date"><?= date('l, d M Y') ?> • Update realtime dari data aplikasi</div>
                    </div>
                    <div class="exec-badge"><i class="fa-solid fa-signal"></i> Monitoring aktif</div>
                </div>
                <div class="exec-grid">
                    <div class="exec-mini"><span>Total Pegawai</span><strong><?= (int)$execTotalUsers ?></strong></div>
                    <div class="exec-mini"><span>Hadir</span><strong><?= (int)$dashPresensi['hadir'] ?></strong></div>
                    <div class="exec-mini"><span>Belum Hadir</span><strong><?= (int)$dashPresensi['belum'] ?></strong></div>
                    <div class="exec-mini"><span>Terlambat</span><strong><?= (int)$dashPresensi['telat'] ?></strong></div>
                    <div class="exec-mini"><span>WFO</span><strong><?= (int)$dashPresensi['wfo'] ?></strong></div>
                    <div class="exec-mini"><span>WFA</span><strong><?= (int)$dashPresensi['wfa'] ?></strong></div>
                </div>
            </div>

            <div class="exec-panel-grid">
                <div class="exec-panel">
                    <div class="exec-panel-head">
                        <div>
                            <div class="exec-panel-title">KPI Bulan Ini</div>
                            <div class="exec-panel-sub">Presensi, WFO/WFA, dan keterlambatan</div>
                        </div>
                        <a href="riwayat_absensi.php" class="exec-pill blue">Detail</a>
                    </div>
                    <div class="exec-kpi-list">
                        <div class="exec-kpi-row"><span>Presensi</span>
                            <div class="exec-kpi-bar">
                                <div class="exec-kpi-fill" style="width:<?= (int)$execKpi['presensi'] ?>%"></div>
                            </div><strong><?= (int)$execKpi['presensi'] ?>%</strong>
                        </div>
                        <div class="exec-kpi-row"><span>WFO</span>
                            <div class="exec-kpi-bar">
                                <div class="exec-kpi-fill" style="width:<?= (int)$execKpi['wfo'] ?>%"></div>
                            </div><strong><?= (int)$execKpi['wfo'] ?>%</strong>
                        </div>
                        <div class="exec-kpi-row"><span>WFA</span>
                            <div class="exec-kpi-bar">
                                <div class="exec-kpi-fill" style="width:<?= (int)$execKpi['wfa'] ?>%"></div>
                            </div><strong><?= (int)$execKpi['wfa'] ?>%</strong>
                        </div>
                        <div class="exec-kpi-row"><span>Telat</span>
                            <div class="exec-kpi-bar">
                                <div class="exec-kpi-fill" style="width:<?= (int)$execKpi['telat'] ?>%"></div>
                            </div><strong><?= (int)$execKpi['telat'] ?>%</strong>
                        </div>
                    </div>
                </div>

                <div class="exec-panel">
                    <div class="exec-panel-head">
                        <div>
                            <div class="exec-panel-title">Perlu Perhatian</div>
                            <div class="exec-panel-sub">Belum hadir, belum pulang, dan GPS</div>
                        </div>
                        <span class="exec-pill amber"><?= (int)$dashPresensi['belum'] + count($execBelumPulang) + (int)$execGpsTidakValid ?> item</span>
                    </div>
                    <div class="exec-watch-list">
                        <div class="exec-watch-item">
                            <div><strong>Belum Presensi</strong><span><?= (int)$dashPresensi['belum'] ?> pegawai belum masuk</span></div><span class="exec-pill amber">Belum</span>
                        </div>
                        <div class="exec-watch-item">
                            <div><strong>Belum Pulang</strong><span><?= count($execBelumPulang) ?> pegawai sudah masuk belum pulang</span></div><span class="exec-pill blue">Monitor</span>
                        </div>
                        <div class="exec-watch-item">
                            <div><strong>GPS Tidak Valid</strong><span><?= (int)$execGpsTidakValid ?> presensi perlu dicek</span></div><span class="exec-pill red">Cek</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="exec-ai-summary">
                <div class="exec-ai-icon"><i class="fa-solid fa-robot"></i></div>
                <div>
                    <strong>Ringkasan AI</strong>
                    <small class="exec-ai-meta">Diperbarui otomatis • <?= date('d M Y') ?> • <?= date('H:i') ?> WIB</small>
                    <p class="exec-ai-copy"><?= htmlspecialchars($execAiSummary, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($execPelatihanNames)): ?>
                        <div class="exec-ai-training">
                            <div class="exec-ai-training-title">Pelatihan/kegiatan yang sedang berlangsung</div>
                            <ol class="exec-ai-training-list">
                                <?php foreach (array_slice($execPelatihanNames, 0, 5) as $idxPelatihan => $namaPelatihan): ?>
                                    <li><span><?= htmlspecialchars($namaPelatihan, ENT_QUOTES, 'UTF-8') ?></span></li>
                                <?php endforeach; ?>
                                <?php if (count($execPelatihanNames) > 5): ?>
                                    <li class="more"><span>+<?= count($execPelatihanNames) - 5 ?> kegiatan lainnya</span></li>
                                <?php endif; ?>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="final-section-title">
            <span>Ringkasan Hari Ini</span>
            <small><?= date('d M Y') ?></small>
        </div>
        <div class="ops-grid-final">
            <div class="ops-card-final">
                <div class="ops-icon-final bg-blue"><i class="fa-solid fa-list-check"></i></div>
                <div class="ops-label-final">Checklist</div>
                <div class="ops-value-final"><?= (int)$dashOps['checklist_today'] ?></div>
            </div>
            <div class="ops-card-final">
                <div class="ops-icon-final bg-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="ops-label-final">Kerusakan</div>
                <div class="ops-value-final"><?= (int)$dashOps['kerusakan_today'] ?></div>
            </div>
            <div class="ops-card-final">
                <div class="ops-icon-final bg-teal"><i class="fa-solid fa-warehouse"></i></div>
                <div class="ops-label-final">Gudang</div>
                <div class="ops-value-final"><?= (int)$dashOps['gudang_today'] ?></div>
            </div>
            <div class="ops-card-final">
                <div class="ops-icon-final bg-yellow"><i class="fa-solid fa-envelope-open-text"></i></div>
                <div class="ops-label-final">Persuratan</div>
                <div class="ops-value-final"><?= (int)$dashOps['surat_today'] ?></div>
            </div>
            <div class="ops-card-final">
                <div class="ops-icon-final bg-indigo"><i class="fa-solid fa-people-roof"></i></div>
                <div class="ops-label-final">Rapat</div>
                <div class="ops-value-final"><?= (int)$dashOps['rapat_today'] ?></div>
            </div>
        </div>
        <!-- <?php
                $menuCepat = [
                    ["timetable.php", "fa-calendar-days", "Timetable", "sky", "", ""],
                    ["peminjaman_ruang_rapat.php", "fa-list-alt", "Ruang Rapat", "sky", "", ""],
                    ["javascript:void(0)", "fa-right-to-bracket", "Cekin", "purple", "openUploadCekin", ""],
                    ["javascript:void(0)", "fa-triangle-exclamation", "Kerusakan", "red", "openUploadLaporanKerusakan", ""],
                    ["javascript:void(0)", "fa-warehouse", "Gudang", "emerald", "openUploadGudang", ""],
                    ["arsip_surat.php", "fa-envelope-open-text", "Persuratan", "amber", "", ""],
                    ["kendaraan.php", "fa-car-side", "Kendaraan", "teal", "", ""],
                    ["daftar_tamu.php", "fa-book-open", "Buku Tamu", "orange", "", ""],
                    ["https://viyatadhika.github.io/noext/", "fa-phone-volume", "Nomor Ext", "indigo", "", "_blank"],
                ];
                ?>
        <h3 class="section-title">Menu Cepat</h3>
        <div class="grid grid-cols-4 gap-4 px-4 mb-4">
            <?php foreach ($menuCepat as $i => $m): ?>
                <a href="<?= $m[0] ?>"
                    <?= $m[4] ? 'id="' . $m[4] . '"' : '' ?>
                    <?= $m[5] ? 'target="' . $m[5] . '"' : '' ?>
                    class="group flex flex-col items-center text-gray-700 text-xs fade-up"
                    style="animation-delay:<?= 0.1 + $i * 0.05 ?>s">
                    <div class="w-14 h-14 flex items-center justify-center bg-<?= $m[3] ?>-50 rounded-2xl shadow-sm group-hover:scale-110 transition">
                        <i class="fa-solid <?= $m[1] ?> text-<?= $m[3] ?>-600 text-2xl"></i>
                    </div>
                    <span class="mt-2 font-medium text-center leading-tight w-full"><?= $m[2] ?></span>
                </a>
            <?php endforeach; ?>
        </div> -->

        <div id="latestActivity" class="space-y-3">
            <?php include 'api/get_latest_activity.php'; ?>
        </div>

        <section class="presensi-section presensi-section-compact">
            <div class="presensi-compact-head">
                <div>
                    <h3 class="presensi-title">Detail Monitoring Presensi</h3>
                    <div class="presensi-sub">Daftar pendukung dari ringkasan executive di atas</div>
                </div>
                <div class="presensi-actions">
                    <a class="presensi-action-btn" href="presensi_lokasi_petugas.php" title="Presensi"><i class="fa-solid fa-camera"></i></a>
                    <a class="presensi-action-btn" href="riwayat_absensi.php" title="Riwayat"><i class="fa-solid fa-clock-rotate-left"></i></a>
                    <a class="presensi-action-btn" href="jadwal_shift.php" title="Jadwal Shift"><i class="fa-solid fa-calendar-days"></i></a>
                </div>
            </div>

            <div class="presensi-soft-grid">
                <div class="presensi-soft-card">
                    <div class="presensi-card-head">
                        <div class="presensi-card-title">Presensi Terbaru</div>
                        <a href="riwayat_absensi.php" class="presensi-card-link">Lihat</a>
                    </div>
                    <div class="presensi-list">
                        <?php if (!$dashPresensiTerbaru): ?>
                            <div class="presensi-empty">Belum ada presensi hari ini.</div>
                            <?php else: foreach ($dashPresensiTerbaru as $p): ?>
                                <div class="presensi-list-item">
                                    <div class="presensi-person">
                                        <strong><?= htmlspecialchars($p['nama_petugas'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span><?= htmlspecialchars(($p['jenis_presensi'] ?? '-') . ' • ' . date('H:i', strtotime($p['created_at'] ?? 'now')) . ' WIB', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <span class="presensi-pill <?= (strtolower(trim((string)($p['jenis_presensi'] ?? ''))) === 'masuk') ? 'pill-green' : 'pill-blue' ?>"><?= htmlspecialchars($p['catatan'] ?: 'WFO', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>
                </div>

                <div class="presensi-soft-card">
                    <div class="presensi-card-head">
                        <div class="presensi-card-title">Tren 7 Hari</div>
                        <a href="riwayat_absensi.php" class="presensi-card-link">Detail</a>
                    </div>
                    <?php $maxBar = max(1, max($dashChart7ValuesOut ?: [0])); ?>
                    <div class="presensi-chart-bars">
                        <?php foreach ($dashChart7Labels as $idx => $lbl): ?>
                            <?php $val = (int)($dashChart7ValuesOut[$idx] ?? 0);
                            $h = max(6, (int)round(($val / $maxBar) * 92)); ?>
                            <div class="presensi-bar-wrap" title="<?= htmlspecialchars($lbl . ': ' . $val, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="presensi-bar" style="height:<?= $h ?>px"></div>
                                <div class="presensi-bar-label"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="presensi-soft-grid">
                <div class="presensi-soft-card">
                    <div class="presensi-card-head">
                        <div class="presensi-card-title">Belum Presensi</div>
                        <span class="presensi-card-link"><?= count($dashBelumPresensi) ?> tampil</span>
                    </div>
                    <div class="presensi-list">
                        <?php if (!$dashBelumPresensi): ?>
                            <div class="presensi-empty">Semua sudah presensi masuk.</div>
                            <?php else: foreach ($dashBelumPresensi as $b): ?>
                                <div class="presensi-list-item">
                                    <div class="presensi-person"><strong><?= htmlspecialchars($b['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong><span>Belum presensi masuk</span></div>
                                    <span class="presensi-pill pill-amber">Belum</span>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>
                </div>

                <div class="presensi-soft-card">
                    <div class="presensi-card-head">
                        <div class="presensi-card-title">Terlambat Hari Ini</div>
                        <span class="presensi-card-link"><?= count($dashTerlambat) ?> orang</span>
                    </div>
                    <div class="presensi-list">
                        <?php if (!$dashTerlambat): ?>
                            <div class="presensi-empty">Tidak ada data terlambat.</div>
                            <?php else: foreach ($dashTerlambat as $t): ?>
                                <?php
                                $jamTelat = strtotime($t['created_at'] ?? 'now');
                                $menitTelat = (int)($t['menit_telat'] ?? 0);
                                if ($menitTelat <= 0 && $jamTelat) {
                                    $standar = strtotime(date('Y-m-d', $jamTelat) . ' 08:00:00');
                                    $menitTelat = max(0, (int)floor(($jamTelat - $standar) / 60));
                                }
                                ?>
                                <div class="presensi-list-item">
                                    <div class="presensi-person"><strong><?= htmlspecialchars($t['nama_petugas'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong><span><?= date('H:i', $jamTelat ?: time()) ?> WIB</span></div>
                                    <span class="presensi-pill pill-red"><?= $menitTelat ?> mnt</span>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>
                </div>
            </div>
        </section>


        <!-- <h3 class="section-title">Kinerja Utama</h3>
        <div class="kinerja-grid">
            <div class="kinerja-card">
                <div class="badge bg-blue"><i class="fa-solid fa-calendar-check"></i></div>
                <p class="k-label">Total Checklist</p>
                <p class="k-value"><?= $total ?></p>
            </div>
            <div class="kinerja-card">
                <div class="badge bg-orange"><i class="fa-solid fa-user-group"></i></div>
                <p class="k-label">Total Petugas</p>
                <p class="k-value"><?= $totalPetugas ?></p>
            </div>
            <div class="kinerja-card">
                <div class="badge bg-green"><i class="fa-solid fa-list-check"></i></div>
                <p class="k-label">Jenis Form</p>
                <p class="k-value"><?= $totalForm ?></p>
            </div>
            <div class="kinerja-card">
                <div class="badge bg-purple"><i class="fa-solid fa-location-dot"></i></div>
                <p class="k-label">Area Kerja</p>
                <p class="k-value"><?= $totalArea ?></p>
            </div>
        </div> -->

        <div id="logoutModal">
            <div id="logoutBox" class="logout-card">
                <h2>Keluar dari Akun?</h2>
                <p>Anda akan keluar dari PAK RT Super App.</p>
                <div class="flex flex-col gap-2">
                    <button id="confirmLogout" class="btn-primary w-full">Keluar</button>
                    <button id="cancelLogout" class="btn-outline w-full">Batal</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'nav_monitoring.php'; ?>

    <div id="fadeBgLaporanKerusakan" class="fade-bg"></div>
    <div id="sheetLaporanKerusakan" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetLaporanKerusakan" class="absolute top-3 right-4 w-9 h-9 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="sheetLaporanKerusakanContent" class="p-5 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-extrabold text-sky-600">Laporan Kerusakan</h2>
                <p class="text-xs text-gray-500 mt-1">Laporkan fasilitas yang rusak</p>
            </div>
            <div class="space-y-3">
                <a href="laporan_kerusakan.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-red-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                        <i class="fa-solid fa-list-check text-red-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Daftar Laporan</p>
                        <p class="text-xs text-gray-500">Lihat laporan masuk</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="laporan_kerusakan_tambah.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-sky-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center">
                        <i class="fa-solid fa-plus text-sky-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Tambah Laporan</p>
                        <p class="text-xs text-gray-500">Laporkan kerusakan baru</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
            </div>
        </div>
    </div>

    <div id="fadeBgGudang" class="fade-bg"></div>
    <div id="sheetGudang" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetGudang" class="absolute top-3 right-4 w-9 h-9 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="sheetGudangContent" class="p-5 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-extrabold text-sky-600">Manajemen Gudang</h2>
                <p class="text-xs text-gray-500 mt-1">Manajemen stok &amp; laporan gudang</p>
            </div>
            <div class="space-y-3">
                <a href="stok_barang.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-emerald-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <i class="fa-solid fa-boxes-stacked text-emerald-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Stok Barang</p>
                        <p class="text-xs text-gray-500">Lihat &amp; kelola stok barang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="barang_masuk.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-sky-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center">
                        <i class="fa-solid fa-arrow-down-wide-short text-sky-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Barang Masuk</p>
                        <p class="text-xs text-gray-500">Input barang masuk gudang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="barang_keluar.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-rose-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-rose-100 flex items-center justify-center">
                        <i class="fa-solid fa-arrow-up-wide-short text-rose-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Barang Keluar</p>
                        <p class="text-xs text-gray-500">Input barang keluar gudang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="stok_opname.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-indigo-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <i class="fa-solid fa-clipboard-check text-indigo-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Stok Opname</p>
                        <p class="text-xs text-gray-500">Cek fisik stok barang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
                <a href="koreksi_stok.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-amber-50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                        <i class="fa-solid fa-file-lines text-amber-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Penyesuaian Stok</p>
                        <p class="text-xs text-gray-500">Catatan koreksi stok barang</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                </a>
            </div>
        </div>
    </div>

    <div id="fadeBgCekin" class="fade-bg"></div>
    <div id="sheetCekin" class="sheet">
        <div class="sheet-handle"></div>
        <button id="closeSheetCekin" class="absolute top-3 right-4 w-9 h-9 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="sheetCekinContent" class="p-5 pb-8 pt-4">
            <div class="text-center mb-5">
                <h2 class="text-lg font-extrabold text-sky-600">Cekin Peserta &amp; Pengajar</h2>
                <p class="text-xs text-gray-500 mt-1">Monitoring check-in peserta</p>
            </div>
            <div class="space-y-3">
                <a href="peserta_penginapan.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-emerald-50 transition-all">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-user-plus text-emerald-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800">Input Peserta &amp; Pengajar</p>
                        <p class="text-xs text-gray-500">Tambah data peserta / pengajar</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400 text-xs"></i>
                </a>
                <a href="cekin_cekout.php" class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:bg-sky-50 transition-all">
                    <div class="w-12 h-12 rounded-2xl bg-sky-100 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-right-to-bracket text-sky-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800">Cekin dan Cekout</p>
                        <p class="text-xs text-gray-500">Monitoring kehadiran peserta</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-400 text-xs"></i>
                </a>
            </div>
        </div>
    </div>

    <div id="popupNotif" class="fixed inset-0 hidden" style="z-index:99999; background:rgba(0,0,0,0.45); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);">
        <div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px;">
            <div class="w-full max-w-[480px] relative" style="border-radius:24px; padding-bottom:1rem; background:rgba(255,255,255,0.16); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); border:.5px solid rgba(255,255,255,0.30); box-shadow:0 20px 60px rgba(0,0,0,0.28);">
                <div style="display:flex; justify-content:center; padding:10px 0 4px;">
                    <div style="width:36px; height:4px; border-radius:2px; background:rgba(255,255,255,0.5);"></div>
                </div>
                <button id="closeNotif" style="position:absolute; top:10px; right:14px; width:30px; height:30px; border-radius:50%; background:rgba(255,255,255,0.25); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:white; font-size:13px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <p id="notifLabel" style="text-align:center; font-size:11px; color:rgba(255,255,255,0.78); margin:0 0 8px;"></p>
                <div style="overflow:hidden; padding:0 16px;">
                    <div id="notifTrack" style="display:flex; gap:12px; transition:transform .35s cubic-bezier(.4,0,.2,1); cursor:grab; user-select:none;"></div>
                </div>
                <div id="notifDots" style="display:flex; justify-content:center; gap:6px; padding-top:12px;"></div>
            </div>
        </div>
    </div>

    <button id="fabAiBtn" class="fab-ai" aria-label="Tanya Pak RT AI" title="Tanya Pak RT AI">
        <i class="fa-solid fa-robot"></i>
        <span class="fab-ai-label">Tanya Pak RT AI</span>
    </button>

    <div id="aiOverlay" class="ai-overlay"></div>

    <div id="aiSheet" class="ai-sheet">
        <div class="ai-sheet-handle"></div>
        <div class="ai-header">
            <div class="ai-header-left">
                <div class="ai-icon">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div>
                    <h3>Tanya Pak RT AI</h3>
                    <p>Siap bantu ringkasan &amp; rekap operasional</p>
                </div>
            </div>
            <button id="closeAiSheet" class="ai-close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="ai-body" id="aiBody">
            <div class="ai-message ai-bot">
                <div class="ai-bubble">Halo 👋 Saya bisa bantu:
                    • ringkasan kegiatan
                    • rekapitulasi laporan
                    • daftar kerusakan
                    • status gudang
                    • monitoring check-in</div>
            </div>

            <div class="ai-suggestions">
                <button class="ai-chip" data-prompt="Buatkan ringkasan kegiatan hari ini">Ringkasan hari ini</button>
                <button class="ai-chip" data-prompt="Tampilkan rekap kegiatan minggu ini">Rekap minggu ini</button>
                <button class="ai-chip" data-prompt="Tampilkan daftar kerusakan terbaru">Daftar kerusakan</button>
                <button class="ai-chip" data-prompt="Bagaimana status stok gudang saat ini">Status gudang</button>
                <button class="ai-chip" data-prompt="Siapa saja yang belum check-in hari ini">Check-in peserta</button>
            </div>
        </div>

        <div class="ai-input-wrap">
            <input type="text" id="aiQuestion" class="ai-input" placeholder="Tanya misalnya: ringkasan kegiatan hari ini" autocomplete="off">
            <button id="sendAiQuestion" class="ai-send">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script>
        (function() {
            const now = new Date();
            const y = now.getFullYear(),
                m = now.getMonth(),
                d = now.getDate();
            const items = [];

            const skpPeriodes = [{
                    label: 'Triwulan I',
                    periode: `1 Jan ${y} s/d 31 Mar ${y}`,
                    batas: `5 April ${y}`,
                    aktif: m === 2 && d >= 25
                },
                {
                    label: 'Triwulan II',
                    periode: `1 Apr ${y} s/d 30 Jun ${y}`,
                    batas: `5 Juli ${y}`,
                    aktif: m === 5 && d >= 24
                },
                {
                    label: 'Triwulan III',
                    periode: `1 Jul ${y} s/d 30 Sep ${y}`,
                    batas: `5 Oktober ${y}`,
                    aktif: m === 8 && d >= 24
                },
                {
                    label: 'Triwulan IV',
                    periode: `1 Okt ${y} s/d 31 Des ${y}`,
                    batas: `5 Januari ${y + 1}`,
                    aktif: m === 11 && d >= 25
                },
                {
                    label: 'Tahunan',
                    periode: `1 Jan ${y - 1} s/d 31 Des ${y - 1}`,
                    batas: `31 Januari ${y}`,
                    aktif: m === 0
                },
            ];

            skpPeriodes.forEach(p => {
                if (!p.aktif) return;
                items.push({
                    icon: 'fa-star',
                    title: 'Pengisian SKP!',
                    sub: 'Sasaran Kinerja Pegawai',
                    grad: 'linear-gradient(135deg,#10b981,#0d9488)',
                    accent: '#059669',
                    body: `
                <div style="background:#f0fdf4;border-radius:10px;padding:.6rem .8rem;margin-bottom:.75rem;border:.5px solid #bbf7d0;">
                    <p style="font-size:10px;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.04em;">Periode</p>
                    <p style="font-size:13px;font-weight:500;color:#065f46;margin:0 0 1px;">${p.label}</p>
                    <p style="font-size:11px;color:#374151;margin:0;">${p.periode}</p>
                </div>
                <div style="background:#fef2f2;border:.5px solid #fecaca;border-radius:10px;padding:.45rem;text-align:center;margin-bottom:.85rem;">
                    <p style="font-size:12px;font-weight:500;color:#dc2626;margin:0;">⏰ Batas: ${p.batas}</p>
                </div>`
                });
            });

            const lastDay = new Date(y, m + 1, 0).getDate();
            if (d > lastDay - 7) {
                items.push({
                    icon: 'fa-clipboard-check',
                    title: 'Tugas Mendesak!',
                    sub: 'Pengingat PKP Bulanan',
                    grad: 'linear-gradient(135deg,#6366f1,#4f46e5)',
                    accent: '#4f46e5',
                    body: `
                <div style="background:#eef2ff;border-radius:10px;padding:.6rem .8rem;margin-bottom:.75rem;border:.5px solid #c7d2fe;">
                    <p style="font-size:10px;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.04em;">Info</p>
                    <p style="font-size:13px;font-weight:500;color:#3730a3;margin:0;">PKP Bulan Ini</p>
                </div>
                <div style="background:#fef2f2;border:.5px solid #fecaca;border-radius:10px;padding:.45rem;text-align:center;margin-bottom:.85rem;">
                    <p style="font-size:12px;font-weight:500;color:#dc2626;margin:0;">⏰ Batas: Tanggal 4 setiap bulan</p>
                </div>`
                });
            }

            if (!items.length) return;
            const key = `notifShown_${y}-${m + 1}-${d}`;
            if (localStorage.getItem(key)) return;

            const modal = document.getElementById('popupNotif');
            const track = document.getElementById('notifTrack');
            const dotsEl = document.getElementById('notifDots');
            const label = document.getElementById('notifLabel');
            const total = items.length;

            items.forEach((n, i) => {
                const card = document.createElement('div');
                card.style.cssText = `min-width:calc(100% - 28px);flex-shrink:0;border-radius:18px;overflow:hidden;background:#ffffff;box-shadow:0 8px 32px rgba(0,0,0,0.18);`;
                card.innerHTML = `
            <div style="background:${n.grad}; padding:1.4rem 1.25rem .9rem; text-align:center;">
                <div style="width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;margin:0 auto .6rem;">
                    <i class="fa-solid ${n.icon} text-white" style="font-size:20px;"></i>
                </div>
                <p style="color:white;font-size:15px;font-weight:600;margin:0 0 2px;">${n.title}</p>
                <p style="color:rgba(255,255,255,.8);font-size:11px;margin:0;">${n.sub}</p>
            </div>
            <div style="padding:.9rem 1rem 1rem; background:#ffffff;">
                ${n.body}
                <button class="btn-tutup" style="width:100%;padding:.6rem;background:${n.accent};color:white;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .15s;">
                    Mengerti & Lanjutkan
                </button>
            </div>`;
                track.appendChild(card);

                const dot = document.createElement('div');
                dot.dataset.i = i;
                dot.style.cssText = `height:5px;border-radius:3px;transition:all .3s;cursor:pointer;background:${i === 0 ? n.accent : 'rgba(255,255,255,0.4)'};width:${i === 0 ? '20px' : '6px'};`;
                dotsEl.appendChild(dot);
            });

            const dots = [...dotsEl.children];
            let cur = 0;

            function goTo(i) {
                cur = Math.max(0, Math.min(i, total - 1));
                const cardW = track.children[0].offsetWidth + 12;
                track.style.transform = `translateX(-${cur * cardW}px)`;
                dots.forEach((dot, idx) => {
                    dot.style.width = idx === cur ? '20px' : '6px';
                    dot.style.background = idx === cur ? items[cur].accent : 'rgba(255,255,255,0.4)';
                });
                label.textContent = `${cur + 1} / ${total} pengingat`;
            }

            function buka() {
                document.body.classList.add('modal-open');
                modal.classList.remove('hidden');
                modal.style.opacity = '0';
                requestAnimationFrame(() => {
                    modal.style.transition = 'opacity .3s ease';
                    modal.style.opacity = '1';
                });
            }

            function tutup() {
                modal.style.transition = 'opacity .25s ease';
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.style.opacity = '';
                    document.body.classList.remove('modal-open');
                    localStorage.setItem(key, 'true');
                }, 250);
            }

            goTo(0);
            dots.forEach(dot => dot.addEventListener('click', () => goTo(+dot.dataset.i)));

            let sx = 0;
            track.addEventListener('touchstart', e => {
                sx = e.touches[0].clientX;
            }, {
                passive: true
            });
            track.addEventListener('touchend', e => {
                const dx = e.changedTouches[0].clientX - sx;
                if (dx < -30) goTo(cur + 1);
                if (dx > 30) goTo(cur - 1);
            });

            let mx = 0,
                drag = false;
            track.addEventListener('mousedown', e => {
                mx = e.clientX;
                drag = true;
                track.style.cursor = 'grabbing';
            });
            track.addEventListener('mouseup', e => {
                if (!drag) return;
                drag = false;
                track.style.cursor = 'grab';
                const dx = e.clientX - mx;
                if (dx < -30) goTo(cur + 1);
                if (dx > 30) goTo(cur - 1);
            });
            track.addEventListener('mouseleave', () => {
                drag = false;
                track.style.cursor = 'grab';
            });

            document.getElementById('closeNotif').addEventListener('click', tutup);
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-tutup')) tutup();
            });
            modal.addEventListener('click', function(e) {
                if (e.target === modal) tutup();
            });

            buka();
        })();
    </script>

    <script>
        (function() {
            const fabBtn = document.getElementById('fabAiBtn');
            const overlay = document.getElementById('aiOverlay');
            const sheet = document.getElementById('aiSheet');
            const closeBtn = document.getElementById('closeAiSheet');
            const aiBody = document.getElementById('aiBody');
            const aiInput = document.getElementById('aiQuestion');
            const sendBtn = document.getElementById('sendAiQuestion');
            const chips = document.querySelectorAll('.ai-chip');

            if (fabBtn) {
                fabBtn.classList.add('show-label');
                setTimeout(() => {
                    fabBtn.classList.remove('show-label');
                }, 2200);
            }

            function setAppVh() {
                const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                const vh = viewportHeight * 0.01;
                document.documentElement.style.setProperty('--app-vh', `${vh}px`);
            }

            function scrollInputIntoView() {
                setTimeout(() => {
                    aiInput.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                    aiBody.scrollTop = aiBody.scrollHeight;
                }, 180);
            }

            function handleKeyboardState() {
                const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                const screenHeight = window.innerHeight;
                const keyboardLikelyOpen = (screenHeight - viewportHeight) > 140;

                if (keyboardLikelyOpen) {
                    sheet.classList.add('keyboard-open');
                    fabBtn.style.opacity = '0';
                    fabBtn.style.pointerEvents = 'none';
                } else {
                    sheet.classList.remove('keyboard-open');
                    fabBtn.style.opacity = '1';
                    fabBtn.style.pointerEvents = 'auto';
                }
            }

            setAppVh();
            handleKeyboardState();
            window.addEventListener('resize', () => {
                setAppVh();
                handleKeyboardState();
            });
            window.addEventListener('orientationchange', () => {
                setAppVh();
                handleKeyboardState();
            });
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => {
                    setAppVh();
                    handleKeyboardState();
                });
            }
            aiInput.addEventListener('focus', () => {
                handleKeyboardState();
                scrollInputIntoView();
            });
            aiInput.addEventListener('blur', () => {
                setTimeout(handleKeyboardState, 100);
            });

            function openAiSheet() {
                overlay.classList.add('show');
                sheet.classList.add('show');
                document.body.classList.add('modal-open');
                setAppVh();
                handleKeyboardState();
                setTimeout(() => {
                    aiInput.focus();
                    scrollInputIntoView();
                }, 220);
            }

            function closeAiSheet() {
                overlay.classList.remove('show');
                sheet.classList.remove('show');
                sheet.classList.remove('keyboard-open');
                document.body.classList.remove('modal-open');
                fabBtn.style.opacity = '1';
                fabBtn.style.pointerEvents = 'auto';
                aiInput.blur();
            }

            function addMessage(text, role = 'bot', isHtml = false) {
                const wrapper = document.createElement('div');
                wrapper.className = `ai-message ai-${role}`;
                const bubble = document.createElement('div');
                bubble.className = 'ai-bubble';
                if (isHtml) {
                    bubble.innerHTML = text;
                } else {
                    bubble.textContent = text;
                }
                wrapper.appendChild(bubble);
                aiBody.appendChild(wrapper);
                aiBody.scrollTop = aiBody.scrollHeight;
                return wrapper;
            }

            function addLoading() {
                const wrapper = document.createElement('div');
                wrapper.className = 'ai-message ai-bot';
                wrapper.id = 'aiLoadingMessage';
                const bubble = document.createElement('div');
                bubble.className = 'ai-bubble';
                bubble.innerHTML = `
                    <div class="ai-loading">
                        <span></span><span></span><span></span>
                        <strong>Sedang menyiapkan jawaban...</strong>
                    </div>
                `;
                wrapper.appendChild(bubble);
                aiBody.appendChild(wrapper);
                aiBody.scrollTop = aiBody.scrollHeight;
            }

            function removeLoading() {
                const el = document.getElementById('aiLoadingMessage');
                if (el) el.remove();
            }

            async function askAi(question) {
                if (!question || !question.trim()) return;
                addMessage(question, 'user');
                aiInput.value = '';
                addLoading();

                try {
                    const response = await fetch('api/ai_assistant.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            question: question
                        })
                    });

                    const data = await response.json();
                    removeLoading();

                    if (!response.ok) {
                        addMessage(data.message || 'Terjadi kesalahan saat memproses pertanyaan.');
                        return;
                    }

                    addMessage(data.answer || 'Belum ada jawaban.');
                } catch (err) {
                    removeLoading();
                    addMessage('Maaf, AI sedang tidak bisa dihubungi. Silakan coba lagi.');
                }
            }

            fabBtn.addEventListener('click', openAiSheet);
            closeBtn.addEventListener('click', closeAiSheet);
            overlay.addEventListener('click', closeAiSheet);
            sendBtn.addEventListener('click', () => askAi(aiInput.value));
            aiInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    askAi(aiInput.value);
                }
            });
            chips.forEach(chip => {
                chip.addEventListener('click', function() {
                    const prompt = this.dataset.prompt || '';
                    askAi(prompt);
                });
            });
        })();
    </script>

    <?php include 'footer.php'; ?>