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

function dashboardFetchAgendaBulanIni(mysqli $conn): array
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
            WHERE DATE(`{$safeStart}`) <= LAST_DAY(CURDATE())
              AND DATE(`{$safeEnd}`) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            ORDER BY
                CASE
                    WHEN CURDATE() BETWEEN DATE(`{$safeStart}`) AND DATE(`{$safeEnd}`) THEN 0
                    WHEN DATE(`{$safeStart}`) > CURDATE() THEN 1
                    ELSE 2
                END,
                `{$safeStart}` ASC
            LIMIT 8
        ";
        $q = $conn->query($sql);
        if (!$q) continue;

        $rows = [];
        while ($row = $q->fetch_assoc()) {
            $nama = trim((string)($row['nama'] ?? ''));
            if ($nama !== '') $rows[] = $row;
        }
        return $rows;
    }

    return [];
}


function dashboardNormalizeAgendaCategory(string $value): string
{
    $v = strtolower(trim($value));
    if ($v === '') return 'Lainnya';

    if (strpos($v, 'menpim') !== false || strpos($v, 'manajemen') !== false || strpos($v, 'pimpinan') !== false) {
        return 'Menpim';
    }
    if (strpos($v, 'teknis') !== false || strpos($v, 'hakim') !== false || strpos($v, 'yudisial') !== false) {
        return 'Teknis';
    }
    if (strpos($v, 'pustrajak') !== false || strpos($v, 'strategi') !== false || strpos($v, 'kebijakan') !== false) {
        return 'Pustrajak';
    }
    if (strpos($v, 'kerja sama') !== false || strpos($v, 'kerjasama') !== false || strpos($v, 'kolaborasi') !== false) {
        return 'Kerjasama';
    }

    return 'Lainnya';
}

function dashboardFetchKomposisiAgendaBulanIni(mysqli $conn, array $agendaRows = []): array
{
    $candidates = [
        ['table' => 'timetable', 'name' => ['nama_kegiatan', 'nama', 'judul', 'agenda', 'kegiatan'], 'category' => ['kategori', 'kategori_kegiatan', 'jenis_kegiatan', 'bidang', 'unit', 'tipe_kegiatan'], 'start' => ['start_date', 'tanggal_mulai', 'mulai', 'tanggal_awal', 'tgl_mulai', 'tanggal'], 'end' => ['end_date', 'tanggal_selesai', 'selesai', 'tanggal_akhir', 'tgl_selesai', 'tanggal']],
        ['table' => 'jadwal_kegiatan', 'name' => ['nama_kegiatan', 'nama', 'judul', 'agenda', 'kegiatan'], 'category' => ['kategori', 'kategori_kegiatan', 'jenis_kegiatan', 'bidang', 'unit', 'tipe_kegiatan'], 'start' => ['start_date', 'tanggal_mulai', 'mulai', 'tanggal_awal', 'tgl_mulai', 'tanggal'], 'end' => ['end_date', 'tanggal_selesai', 'selesai', 'tanggal_akhir', 'tgl_selesai', 'tanggal']],
        ['table' => 'kegiatan', 'name' => ['nama_kegiatan', 'nama', 'judul', 'agenda', 'kegiatan'], 'category' => ['kategori', 'kategori_kegiatan', 'jenis_kegiatan', 'bidang', 'unit', 'tipe_kegiatan'], 'start' => ['start_date', 'tanggal_mulai', 'mulai', 'tanggal_awal', 'tgl_mulai', 'tanggal'], 'end' => ['end_date', 'tanggal_selesai', 'selesai', 'tanggal_akhir', 'tgl_selesai', 'tanggal']],
        ['table' => 'agenda_kegiatan', 'name' => ['nama_kegiatan', 'nama', 'judul', 'agenda', 'kegiatan'], 'category' => ['kategori', 'kategori_kegiatan', 'jenis_kegiatan', 'bidang', 'unit', 'tipe_kegiatan'], 'start' => ['start_date', 'tanggal_mulai', 'mulai', 'tanggal_awal', 'tgl_mulai', 'tanggal'], 'end' => ['end_date', 'tanggal_selesai', 'selesai', 'tanggal_akhir', 'tgl_selesai', 'tanggal']],
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
        $categoryCol = '';
        foreach ($cfg['category'] as $col) {
            if (dashboardColumnExists($conn, $table, $col)) {
                $categoryCol = $col;
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
        $categorySelect = $categoryCol !== ''
            ? "`" . $conn->real_escape_string($categoryCol) . "` AS kategori_asli"
            : "'' AS kategori_asli";

        $q = $conn->query("\n            SELECT\n                `{$safeName}` AS nama,\n                {$categorySelect},\n                `{$safeStart}` AS mulai,\n                `{$safeEnd}` AS selesai\n            FROM `{$safeTable}`\n            WHERE DATE(`{$safeStart}`) <= LAST_DAY(CURDATE())\n              AND DATE(`{$safeEnd}`) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')\n            ORDER BY `{$safeStart}` ASC, `{$safeName}` ASC\n        ");

        if (!$q) continue;

        $groups = [];
        while ($row = $q->fetch_assoc()) {
            $nama = trim((string)($row['nama'] ?? ''));
            if ($nama === '') continue;

            $sourceCategory = trim((string)($row['kategori_asli'] ?? ''));
            $kategori = dashboardNormalizeAgendaCategory($sourceCategory !== '' ? $sourceCategory : $nama);
            if (!isset($groups[$kategori])) {
                $groups[$kategori] = ['kategori' => $kategori, 'total' => 0, 'items' => []];
            }
            $groups[$kategori]['total']++;
            $groups[$kategori]['items'][] = [
                'nama' => $nama,
                'mulai' => (string)($row['mulai'] ?? ''),
                'selesai' => (string)($row['selesai'] ?? $row['mulai'] ?? ''),
            ];
        }

        if ($groups) {
            $order = ['Menpim', 'Teknis', 'Pustrajak', 'Kerjasama', 'Lainnya'];
            $rows = [];
            foreach ($order as $key) {
                if (isset($groups[$key])) $rows[] = $groups[$key];
            }
            return $rows;
        }
    }

    // Fallback dari data agenda yang sudah diambil sebelumnya.
    $groups = [];
    foreach ($agendaRows as $agenda) {
        $nama = trim((string)($agenda['nama'] ?? ''));
        if ($nama === '') continue;
        $kategori = dashboardNormalizeAgendaCategory($nama);
        if (!isset($groups[$kategori])) {
            $groups[$kategori] = ['kategori' => $kategori, 'total' => 0, 'items' => []];
        }
        $groups[$kategori]['total']++;
        $groups[$kategori]['items'][] = [
            'nama' => $nama,
            'mulai' => (string)($agenda['mulai'] ?? ''),
            'selesai' => (string)($agenda['selesai'] ?? $agenda['mulai'] ?? ''),
        ];
    }

    $order = ['Menpim', 'Teknis', 'Pustrajak', 'Kerjasama', 'Lainnya'];
    $out = [];
    foreach ($order as $key) {
        if (isset($groups[$key])) $out[] = $groups[$key];
    }
    return $out;
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

$dashAgendaBulanIni = dashboardFetchAgendaBulanIni($conn);
$dashKomposisiAgenda = dashboardFetchKomposisiAgendaBulanIni($conn, $dashAgendaBulanIni);
$dashTotalAgendaBulanIni = array_sum(array_map(static function ($row) {
    return (int)($row['total'] ?? 0);
}, $dashKomposisiAgenda));

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

/* ===================== MONITORING KERUSAKAN & KETERSEDIAAN FASILITAS ===================== */
function dashboardFirstExistingColumn(mysqli $conn, string $table, array $columns): string
{
    foreach ($columns as $col) {
        if (dashboardColumnExists($conn, $table, $col)) return $col;
    }
    return '';
}

function dashboardFetchKerusakanGedung(mysqli $conn): array
{
    if (!dashboardTableExists($conn, 'laporan_kerusakan')) return [];

    $statusAktif = "('dilaporkan','diverifikasi','diterima_teknisi')";
    $statusProses = "('dalam_perbaikan','menunggu_part')";

    if (dashboardTableExists($conn, 'master_lokasi') && dashboardColumnExists($conn, 'master_lokasi', 'nama_lokasi')) {
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(ml.nama_lokasi), ''), 'Lokasi belum diisi') AS gedung,
                COUNT(lk.id) AS total,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(lk.status,''))) IN {$statusAktif} THEN 1 ELSE 0 END) AS aktif,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(lk.status,''))) IN {$statusProses} THEN 1 ELSE 0 END) AS proses,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(lk.status,''))) = 'selesai' THEN 1 ELSE 0 END) AS selesai,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(lk.status,''))) <> 'selesai' THEN 1 ELSE 0 END) AS belum
            FROM laporan_kerusakan lk
            LEFT JOIN master_lokasi ml ON lk.lokasi_id = ml.id
            GROUP BY COALESCE(NULLIF(TRIM(ml.nama_lokasi), ''), 'Lokasi belum diisi')
            ORDER BY aktif DESC, proses DESC, total DESC, gedung ASC
        ";

        $q = $conn->query($sql);
        if ($q) {
            $rows = [];
            while ($r = $q->fetch_assoc()) {
                $total = (int)($r['total'] ?? 0);
                $selesai = (int)($r['selesai'] ?? 0);
                $rows[] = [
                    'gedung' => (string)($r['gedung'] ?? '-'),
                    'total' => $total,
                    'aktif' => (int)($r['aktif'] ?? 0),
                    'proses' => (int)($r['proses'] ?? 0),
                    'selesai' => $selesai,
                    'belum' => (int)($r['belum'] ?? max(0, $total - $selesai)),
                    'persen_selesai' => $total > 0 ? (int)round(($selesai / $total) * 100) : 0,
                ];
            }
            return $rows;
        }
    }

    if (dashboardTableExists($conn, 'lokasi') && dashboardColumnExists($conn, 'lokasi', 'nama_lokasi')) {
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(l.nama_lokasi), ''), 'Lokasi belum diisi') AS gedung,
                COUNT(lk.id) AS total,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(lk.status,''))) IN {$statusAktif} THEN 1 ELSE 0 END) AS aktif,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(lk.status,''))) IN {$statusProses} THEN 1 ELSE 0 END) AS proses,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(lk.status,''))) = 'selesai' THEN 1 ELSE 0 END) AS selesai,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(lk.status,''))) <> 'selesai' THEN 1 ELSE 0 END) AS belum
            FROM laporan_kerusakan lk
            LEFT JOIN lokasi l ON lk.lokasi_id = l.id
            GROUP BY COALESCE(NULLIF(TRIM(l.nama_lokasi), ''), 'Lokasi belum diisi')
            ORDER BY aktif DESC, proses DESC, total DESC, gedung ASC
        ";

        $q = $conn->query($sql);
        if ($q) {
            $rows = [];
            while ($r = $q->fetch_assoc()) {
                $total = (int)($r['total'] ?? 0);
                $selesai = (int)($r['selesai'] ?? 0);
                $rows[] = [
                    'gedung' => (string)($r['gedung'] ?? '-'),
                    'total' => $total,
                    'aktif' => (int)($r['aktif'] ?? 0),
                    'proses' => (int)($r['proses'] ?? 0),
                    'selesai' => $selesai,
                    'belum' => (int)($r['belum'] ?? max(0, $total - $selesai)),
                    'persen_selesai' => $total > 0 ? (int)round(($selesai / $total) * 100) : 0,
                ];
            }
            return $rows;
        }
    }

    return [];
}

function dashboardFetchKerusakanSummary(mysqli $conn): array
{
    $out = ['total' => 0, 'belum' => 0, 'selesai' => 0];
    if (!dashboardTableExists($conn, 'laporan_kerusakan')) return $out;

    $q = $conn->query("\n        SELECT\n            COUNT(id) AS total,\n            SUM(CASE WHEN LOWER(TRIM(COALESCE(status,''))) = 'selesai' THEN 1 ELSE 0 END) AS selesai,\n            SUM(CASE WHEN LOWER(TRIM(COALESCE(status,''))) <> 'selesai' THEN 1 ELSE 0 END) AS belum\n        FROM laporan_kerusakan\n    ");
    if ($q) {
        $r = $q->fetch_assoc();
        foreach ($out as $k => $v) $out[$k] = (int)($r[$k] ?? 0);
    }
    return $out;
}

function dashboardCountDistinctRoom(mysqli $conn, string $table, string $gedungCol, string $kamarCol, string $where = ''): int
{
    if (!dashboardTableExists($conn, $table)) return 0;
    if (!dashboardColumnExists($conn, $table, $gedungCol) || !dashboardColumnExists($conn, $table, $kamarCol)) return 0;

    $safeTable = $conn->real_escape_string($table);
    $safeGedung = $conn->real_escape_string($gedungCol);
    $safeKamar = $conn->real_escape_string($kamarCol);
    $sqlWhere = $where !== '' ? "WHERE {$where}" : '';

    return dashboardSafeCountQuery($conn, "
        SELECT COUNT(DISTINCT CONCAT(COALESCE(`{$safeGedung}`,''),'|',COALESCE(`{$safeKamar}`,''))) AS total
        FROM `{$safeTable}`
        {$sqlWhere}
    ");
}

function dashboardCountTotalAsramaRooms(mysqli $conn): int
{
    $masterCandidates = [
        ['table' => 'kamar_asrama', 'gedung' => 'gedung', 'kamar' => 'kamar'],
        ['table' => 'asrama_kamar', 'gedung' => 'gedung', 'kamar' => 'kamar'],
        ['table' => 'kamar', 'gedung' => 'gedung', 'kamar' => 'nomor_kamar'],
        ['table' => 'kamar', 'gedung' => 'gedung', 'kamar' => 'kamar'],
    ];

    foreach ($masterCandidates as $cfg) {
        $count = dashboardCountDistinctRoom($conn, $cfg['table'], $cfg['gedung'], $cfg['kamar']);
        if ($count > 0) return $count;
    }

    // Fallback: jika belum ada master kamar, total kamar diambil dari kamar yang pernah tercatat di peserta_penginapan.
    foreach (['peserta_penginapan', 'peserta_inap', 'penginapan_peserta'] as $table) {
        if (!dashboardTableExists($conn, $table)) continue;
        $gedungCol = dashboardFirstExistingColumn($conn, $table, ['gedung', 'nama_gedung', 'asrama']);
        $kamarCol = dashboardFirstExistingColumn($conn, $table, ['kamar', 'nomor_kamar', 'no_kamar']);
        if ($gedungCol !== '' && $kamarCol !== '') {
            $count = dashboardCountDistinctRoom($conn, $table, $gedungCol, $kamarCol, "TRIM(COALESCE(`{$conn->real_escape_string($kamarCol)}`,'')) <> ''");
            if ($count > 0) return $count;
        }
    }

    return 0;
}

function dashboardCountUsedAsramaRooms(mysqli $conn): int
{
    foreach (['peserta_penginapan', 'peserta_inap', 'penginapan_peserta'] as $table) {
        if (!dashboardTableExists($conn, $table)) continue;

        $gedungCol = dashboardFirstExistingColumn($conn, $table, ['gedung', 'nama_gedung', 'asrama']);
        $kamarCol = dashboardFirstExistingColumn($conn, $table, ['kamar', 'nomor_kamar', 'no_kamar']);
        if ($gedungCol === '' || $kamarCol === '') continue;

        $statusCol = dashboardFirstExistingColumn($conn, $table, ['status_inap', 'status', 'status_peserta']);
        $checkinCol = dashboardFirstExistingColumn($conn, $table, ['checkin_date', 'tanggal_checkin', 'tanggal_masuk', 'tgl_masuk']);
        $checkoutCol = dashboardFirstExistingColumn($conn, $table, ['checkout_date', 'tanggal_checkout', 'tanggal_keluar', 'tgl_keluar']);

        $safeKamar = $conn->real_escape_string($kamarCol);
        $whereParts = ["TRIM(COALESCE(`{$safeKamar}`,'')) <> ''"];

        if ($statusCol !== '') {
            $safeStatus = $conn->real_escape_string($statusCol);
            $whereParts[] = "LOWER(TRIM(COALESCE(`{$safeStatus}`,''))) IN ('check-in','checkin','inap','belum check-in','belum checkin')";
        } elseif ($checkinCol !== '' && $checkoutCol !== '') {
            $safeIn = $conn->real_escape_string($checkinCol);
            $safeOut = $conn->real_escape_string($checkoutCol);
            $whereParts[] = "DATE(`{$safeIn}`) <= CURDATE() AND DATE(`{$safeOut}`) >= CURDATE()";
        }

        return dashboardCountDistinctRoom($conn, $table, $gedungCol, $kamarCol, implode(' AND ', $whereParts));
    }

    return 0;
}

function dashboardCountTotalClasses(mysqli $conn): int
{
    $candidates = [
        ['table' => 'ruang_kelas', 'col' => 'nama_kelas'],
        ['table' => 'ruang_kelas', 'col' => 'nama_ruang'],
        ['table' => 'kelas', 'col' => 'nama_kelas'],
        ['table' => 'kelas', 'col' => 'nama'],
    ];

    foreach ($candidates as $cfg) {
        if (!dashboardTableExists($conn, $cfg['table']) || !dashboardColumnExists($conn, $cfg['table'], $cfg['col'])) continue;
        $safeTable = $conn->real_escape_string($cfg['table']);
        $safeCol = $conn->real_escape_string($cfg['col']);
        $count = dashboardSafeCountQuery($conn, "SELECT COUNT(DISTINCT `{$safeCol}`) AS total FROM `{$safeTable}` WHERE TRIM(COALESCE(`{$safeCol}`,'')) <> ''");
        if ($count > 0) return $count;
    }

    // Fallback dari data agenda/timetable yang pernah memakai kelas.
    foreach (['timetable', 'agenda_kegiatan', 'jadwal_kegiatan', 'kegiatan'] as $table) {
        if (!dashboardTableExists($conn, $table)) continue;
        $classCol = dashboardFirstExistingColumn($conn, $table, ['kelas', 'ruang_kelas', 'nama_kelas', 'ruang']);
        if ($classCol !== '') {
            $safeTable = $conn->real_escape_string($table);
            $safeCol = $conn->real_escape_string($classCol);
            $count = dashboardSafeCountQuery($conn, "SELECT COUNT(DISTINCT `{$safeCol}`) AS total FROM `{$safeTable}` WHERE TRIM(COALESCE(`{$safeCol}`,'')) <> ''");
            if ($count > 0) return $count;
        }
    }

    return 0;
}

function dashboardCountUsedClasses(mysqli $conn): int
{
    foreach (['timetable', 'agenda_kegiatan', 'jadwal_kegiatan', 'kegiatan'] as $table) {
        if (!dashboardTableExists($conn, $table)) continue;

        $classCol = dashboardFirstExistingColumn($conn, $table, ['kelas', 'ruang_kelas', 'nama_kelas', 'ruang']);
        if ($classCol === '') continue;

        $startCol = dashboardFirstExistingColumn($conn, $table, ['start_date', 'tanggal_mulai', 'mulai', 'tgl_mulai', 'tanggal']);
        $endCol = dashboardFirstExistingColumn($conn, $table, ['end_date', 'tanggal_selesai', 'selesai', 'tgl_selesai', 'tanggal']);

        $safeTable = $conn->real_escape_string($table);
        $safeClass = $conn->real_escape_string($classCol);

        $where = "WHERE TRIM(COALESCE(`{$safeClass}`,'')) <> ''";
        if ($startCol !== '') {
            $safeStart = $conn->real_escape_string($startCol);
            $safeEnd = $endCol !== '' ? $conn->real_escape_string($endCol) : $safeStart;
            $where .= " AND DATE(`{$safeStart}`) <= CURDATE() AND DATE(`{$safeEnd}`) >= CURDATE()";
        }

        return dashboardSafeCountQuery($conn, "
            SELECT COUNT(DISTINCT `{$safeClass}`) AS total
            FROM `{$safeTable}`
            {$where}
        ");
    }

    return 0;
}

$dashKerusakanGedung = dashboardFetchKerusakanGedung($conn);
$dashKerusakanSummary = dashboardFetchKerusakanSummary($conn);

$dashTotalKamarAsrama = dashboardCountTotalAsramaRooms($conn);
$dashKamarTerpakai = dashboardCountUsedAsramaRooms($conn);
$dashKamarKosong = max(0, $dashTotalKamarAsrama - $dashKamarTerpakai);

$dashTotalKelas = dashboardCountTotalClasses($conn);
$dashKelasTerpakai = dashboardCountUsedClasses($conn);
$dashKelasKosong = max(0, $dashTotalKelas - $dashKelasTerpakai);



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

    /* ===== TREN 7 HARI + AGENDA BULANAN DUA KOLOM ===== */
    .trend-agenda-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(320px, .9fr);
        gap: .75rem;
        margin-top: .75rem;
        align-items: stretch;
    }

    .trend-agenda-grid>.trend-full-card,
    .trend-agenda-grid>.presensi-soft-card {
        width: 100%;
        min-width: 0;
        height: 100%;
        box-sizing: border-box;
    }

    .trend-agenda-grid .agenda-card-body {
        max-height: 245px;
        overflow-y: auto;
        padding-right: 2px;
    }

    .trend-full-card {
        margin-top: 0;
        width: 100%;
        background: #fff;
        border: 1px solid #e0f2fe;
        border-radius: 22px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
        padding: .95rem;
        box-sizing: border-box;
    }

    .trend-line-wrap {
        width: 100%;
        margin-top: .35rem;
        overflow: hidden;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    .trend-line-svg {
        display: block;
        width: 100%;
        height: 230px;
        overflow: visible;
    }



    .trend-modern-chart-wrap {
        position: relative;
        width: 100%;
        height: 255px;
        margin-top: .45rem;
        padding: .65rem .35rem .15rem;
        border-radius: 18px;
        background:
            radial-gradient(circle at 88% 6%, rgba(99, 102, 241, .10), transparent 32%),
            linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #eef2ff;
        overflow: hidden;
    }

    .trend-modern-chart-wrap canvas {
        width: 100% !important;
        height: 100% !important;
    }

    .trend-grid-line {
        stroke: #e2e8f0;
        stroke-width: 1;
        stroke-dasharray: 4 5;
    }

    .trend-axis-label {
        fill: #94a3b8;
        font-size: 11px;
        font-weight: 700;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .trend-date-label {
        fill: #64748b;
        font-size: 11px;
        font-weight: 800;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .trend-area {
        fill: url(#trendAreaGradient);
    }

    .trend-line-path {
        fill: none;
        stroke: #4f46e5;
        stroke-width: 4;
        stroke-linecap: round;
        stroke-linejoin: round;
        filter: drop-shadow(0 5px 7px rgba(79, 70, 229, .18));
    }

    .trend-point-halo {
        fill: rgba(79, 70, 229, .12);
    }

    .trend-point {
        fill: #fff;
        stroke: #4f46e5;
        stroke-width: 3;
    }

    .trend-point-value {
        fill: #1e293b;
        font-size: 12px;
        font-weight: 900;
        text-anchor: middle;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .trend-summary-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        flex-wrap: wrap;
        margin-top: .25rem;
        padding-top: .7rem;
        border-top: 1px solid #eef2f7;
        color: #64748b;
        font-size: .68rem;
        font-weight: 800;
    }

    .trend-summary-row strong {
        color: #0f172a;
        font-weight: 900;
    }

    .agenda-card-body {
        display: grid;
        gap: .55rem;
    }

    .agenda-item {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) auto;
        gap: .7rem;
        align-items: center;
        padding: .68rem .72rem;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .agenda-date-box {
        width: 42px;
        height: 42px;
        border-radius: 13px;
        background: #eef2ff;
        color: #4338ca;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .agenda-date-box strong {
        font-size: .9rem;
        font-weight: 900;
    }

    .agenda-date-box span {
        margin-top: 3px;
        font-size: .55rem;
        font-weight: 900;
        text-transform: uppercase;
    }

    .agenda-info {
        min-width: 0;
    }

    .agenda-info strong {
        display: block;
        font-size: .75rem;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .agenda-info span {
        display: block;
        margin-top: .18rem;
        font-size: .64rem;
        color: #64748b;
        font-weight: 700;
    }

    .agenda-status {
        border-radius: 999px;
        padding: .32rem .48rem;
        font-size: .59rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .agenda-status.ongoing {
        background: #dcfce7;
        color: #166534;
    }

    .agenda-status.upcoming {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .agenda-status.done {
        background: #f1f5f9;
        color: #475569;
    }

    .agenda-card-footer {
        margin: .75rem -.9rem -.9rem;
        border-top: 1px solid #e2e8f0;
        padding: .72rem .9rem;
        text-align: center;
    }

    .agenda-card-footer a {
        color: #2563eb;
        font-size: .72rem;
        font-weight: 900;
        text-decoration: none;
    }

    @media (max-width: 900px) {
        .trend-agenda-grid {
            grid-template-columns: 1fr;
        }

        .trend-agenda-grid .agenda-card-body {
            max-height: none;
        }
    }

    @media (max-width: 520px) {
        .trend-full-card {
            border-radius: 18px;
            padding: .85rem;
        }

        .trend-line-svg {
            height: 205px;
        }

        .trend-modern-chart-wrap {
            height: 220px;
            padding: .5rem .15rem .1rem;
            border-radius: 15px;
        }

        .trend-axis-label,
        .trend-date-label {
            font-size: 10px;
        }

        .trend-point-value {
            font-size: 11px;
        }

        .agenda-item {
            grid-template-columns: 38px minmax(0, 1fr);
        }

        .agenda-date-box {
            width: 38px;
            height: 38px;
        }

        .agenda-status {
            grid-column: 2;
            justify-self: start;
        }
    }


    /* ===== DONUT KOMPOSISI AGENDA BULAN INI ===== */
    .agenda-donut-card {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .agenda-donut-content {
        flex: 1 1 auto;
        min-height: 0;
        display: grid;
        grid-template-columns: minmax(170px, .95fr) minmax(155px, 1.05fr);
        gap: .8rem;
        align-items: center;
    }

    .agenda-donut-chart-wrap {
        position: relative;
        width: 100%;
        height: 245px;
        min-height: 210px;
    }

    .agenda-donut-chart-wrap canvas {
        width: 100% !important;
        height: 100% !important;
    }

    .agenda-donut-center {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        text-align: center;
    }

    .agenda-donut-center strong {
        color: #0f172a;
        font-size: 1.55rem;
        line-height: 1;
        font-weight: 900;
        letter-spacing: -.05em;
    }

    .agenda-donut-center span {
        margin-top: .28rem;
        color: #64748b;
        font-size: .62rem;
        line-height: 1;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .agenda-donut-legend {
        display: grid;
        gap: .48rem;
        align-content: center;
        min-width: 0;
        max-height: 245px;
        overflow-y: auto;
        padding-right: 3px;
    }

    .agenda-donut-legend-item {
        display: grid;
        grid-template-columns: 10px minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        padding: .55rem .6rem;
        border-radius: 13px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .agenda-donut-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        background: #4f46e5;
    }

    .agenda-donut-label {
        min-width: 0;
        color: #475569;
        font-size: .67rem;
        line-height: 1.2;
        font-weight: 800;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .agenda-donut-category {
        min-width: 0;
    }

    .agenda-donut-legend-item {
        width: 100%;
        cursor: pointer;
        text-align: left;
        font-family: inherit;
        transition: border-color .18s ease, background .18s ease, transform .18s ease;
    }

    .agenda-donut-legend-item:hover,
    .agenda-donut-legend-item.is-open {
        border-color: #bfdbfe;
        background: #eff6ff;
        transform: translateY(-1px);
    }

    .agenda-donut-chevron {
        color: #94a3b8;
        font-size: .62rem;
        transition: transform .18s ease;
    }

    .agenda-donut-legend-item.is-open .agenda-donut-chevron {
        transform: rotate(180deg);
        color: #2563eb;
    }

    .agenda-donut-count {
        color: #0f172a;
        font-size: .72rem;
        font-weight: 900;
    }

    .agenda-donut-detail {
        display: none;
        margin-top: .42rem;
        padding: .55rem;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #dbeafe;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .8), 0 8px 18px rgba(15, 23, 42, .05);
        max-height: 185px;
        overflow-y: auto;
    }

    .agenda-donut-detail.is-open {
        display: grid;
        gap: .5rem;
        animation: agendaDetailIn .18s ease-out;
    }

    @keyframes agendaDetailIn {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .agenda-donut-training {
        position: relative;
        padding: .62rem .65rem .62rem .72rem;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(15, 23, 42, .035);
    }

    .agenda-donut-training::before {
        content: "";
        position: absolute;
        left: 0;
        top: .55rem;
        bottom: .55rem;
        width: 3px;
        border-radius: 999px;
        background: #cbd5e1;
    }

    .agenda-donut-training:last-child {
        margin-bottom: 0;
    }

    .agenda-donut-training-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .55rem;
    }

    .agenda-donut-training strong {
        display: block;
        min-width: 0;
        color: #0f172a;
        font-size: .65rem;
        line-height: 1.42;
        font-weight: 850;
    }

    .agenda-donut-training-date {
        display: flex;
        align-items: center;
        gap: .32rem;
        margin-top: .3rem;
        color: #64748b;
        font-size: .59rem;
        line-height: 1.3;
        font-weight: 700;
    }

    .agenda-donut-status {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: .26rem;
        padding: .28rem .46rem;
        border-radius: 999px;
        font-size: .56rem;
        line-height: 1;
        font-weight: 900;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .agenda-donut-status::before {
        content: "";
        width: 5px;
        height: 5px;
        border-radius: 999px;
        background: currentColor;
    }

    .agenda-donut-status.done {
        color: #475569;
        background: #f1f5f9;
        border-color: #e2e8f0;
    }

    .agenda-donut-status.ongoing {
        color: #15803d;
        background: #dcfce7;
        border-color: #bbf7d0;
    }

    .agenda-donut-status.upcoming {
        color: #1d4ed8;
        background: #dbeafe;
        border-color: #bfdbfe;
    }

    .agenda-donut-empty {
        flex: 1 1 auto;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 1100px) and (min-width: 901px) {
        .agenda-donut-content {
            grid-template-columns: 1fr;
            gap: .4rem;
        }

        .agenda-donut-chart-wrap {
            height: 195px;
            min-height: 180px;
        }

        .agenda-donut-legend {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            max-height: 105px;
        }
    }

    @media (max-width: 520px) {
        .agenda-donut-content {
            grid-template-columns: 1fr;
        }

        .agenda-donut-chart-wrap {
            height: 220px;
        }

        .agenda-donut-legend {
            max-height: none;
        }
    }

    /* ===== MONITORING KERUSAKAN PIMPINAN - SIMPLE & RINGKAS ===== */
    .facility-monitor-grid {
        display: grid;
        /* Disamakan persis dengan .exec-panel-grid di atas */
        grid-template-columns: 1.1fr .9fr;
        gap: .75rem;
        margin: 0 1rem 1rem;
        width: auto;
        max-width: none;
        box-sizing: border-box;
        align-items: stretch;
    }

    .facility-card {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 22px;
        padding: .9rem;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
        height: 100%;
    }

    .facility-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: .75rem;
        margin-bottom: .75rem;
    }

    .facility-title {
        font-size: .86rem;
        font-weight: 900;
        color: #0f172a;
        line-height: 1.15;
    }

    .facility-sub {
        margin-top: .15rem;
        font-size: .66rem;
        color: #94a3b8;
        font-weight: 700;
    }

    .facility-link {
        border-radius: 999px;
        padding: .38rem .6rem;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: .65rem;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
    }

    .damage-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .5rem;
        margin-bottom: .75rem;
    }

    .damage-summary-box {
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: .65rem .55rem;
        min-height: 70px;
    }

    .damage-summary-label {
        font-size: .6rem;
        font-weight: 900;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .04em;
        line-height: 1.15;
    }

    .damage-summary-value {
        margin-top: .34rem;
        font-size: 1.22rem;
        font-weight: 900;
        color: #0f172a;
        line-height: 1;
        letter-spacing: -.04em;
    }

    .damage-summary-box.active .damage-summary-value {
        color: #b91c1c;
    }

    .damage-summary-box.process .damage-summary-value {
        color: #c2410c;
    }

    .damage-summary-box.done .damage-summary-value {
        color: #15803d;
    }

    .damage-list {
        display: grid;
        gap: .58rem;
        max-height: 620px;
        overflow-y: auto;
        padding-right: 2px;
    }

    .damage-list::-webkit-scrollbar {
        width: 6px;
    }

    .damage-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .damage-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .damage-row-simple {
        padding: .68rem .72rem;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .damage-row-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .7rem;
        margin-bottom: .5rem;
    }

    .damage-name {
        font-size: .78rem;
        font-weight: 900;
        color: #0f172a;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .damage-total {
        font-size: .68rem;
        font-weight: 900;
        color: #475569;
        white-space: nowrap;
    }

    .damage-progress {
        height: 9px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .damage-progress-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #38bdf8, #0284c7);
    }

    .damage-row-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .55rem;
        margin-top: .48rem;
        font-size: .64rem;
        font-weight: 800;
        color: #64748b;
    }

    .damage-status-inline {
        display: inline-flex;
        gap: .45rem;
        flex-wrap: wrap;
    }

    .damage-status-inline span b {
        color: #0f172a;
    }

    .availability-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .6rem;
    }

    .availability-box {
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: .75rem;
        min-width: 0;
        box-sizing: border-box;
    }

    .availability-icon {
        width: 36px;
        height: 36px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: .55rem;
        background: #e0f2fe;
        color: #0284c7;
    }

    .availability-label {
        font-size: .66rem;
        font-weight: 900;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .availability-value {
        margin-top: .25rem;
        font-size: 1.45rem;
        font-weight: 900;
        color: #0f172a;
        line-height: 1;
        letter-spacing: -.04em;
    }

    .availability-note {
        margin-top: .35rem;
        font-size: .65rem;
        font-weight: 700;
        color: #64748b;
        line-height: 1.35;
    }

    @media (max-width: 900px) {
        .facility-monitor-grid {
            grid-template-columns: 1fr;
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            box-sizing: border-box !important;
        }
    }

    @media (max-width: 640px) {
        .damage-summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 520px) {
        .facility-card {
            border-radius: 20px;
            padding: .85rem;
        }

        .availability-grid {
            grid-template-columns: 1fr;
        }
    }


    /* ===== FINAL ALIGN CARD DASHBOARD =====
       Kerusakan Gedung dan Ketersediaan dibuat sejajar dengan KPI Bulan Ini / Perlu Perhatian. */
    body[data-page="beranda"] .facility-monitor-grid {
        display: grid !important;
        grid-template-columns: 1.1fr .9fr !important;
        gap: .75rem !important;
        margin: 0 1rem 1rem !important;
        width: auto !important;
        max-width: none !important;
        box-sizing: border-box !important;
        align-items: stretch !important;
    }

    body[data-page="beranda"] .facility-card {
        width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        height: 100% !important;
    }

    body[data-page="beranda"] .damage-summary-grid,
    body[data-page="beranda"] .availability-grid {
        width: 100% !important;
        box-sizing: border-box !important;
    }

    body[data-page="beranda"] .damage-list {
        max-height: 430px !important;
        overflow-y: auto !important;
        padding-right: 2px !important;
    }

    @media (max-width: 900px) {
        body[data-page="beranda"] .facility-monitor-grid {
            grid-template-columns: 1fr !important;
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
    }

    @media (max-width: 520px) {

        body[data-page="beranda"] .damage-summary-grid,
        body[data-page="beranda"] .availability-grid {
            grid-template-columns: 1fr !important;
        }

        body[data-page="beranda"] .damage-list {
            max-height: 520px !important;
        }
    }


    /* ===== FINAL COMPACT FACILITY CARDS =====
       Tinggi Kerusakan Gedung dipadatkan dan disamakan dengan card Ketersediaan. */
    body[data-page="beranda"] .facility-monitor-grid {
        align-items: start !important;
    }

    body[data-page="beranda"] .facility-monitor-grid>.facility-card {
        height: 320px !important;
        min-height: 320px !important;
        max-height: 320px !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }

    body[data-page="beranda"] .facility-head {
        flex: 0 0 auto !important;
        margin-bottom: .58rem !important;
    }

    body[data-page="beranda"] .damage-summary-grid {
        flex: 0 0 auto !important;
        margin-bottom: .55rem !important;
        gap: .42rem !important;
    }

    body[data-page="beranda"] .damage-summary-box {
        min-height: 58px !important;
        padding: .52rem .5rem !important;
        border-radius: 14px !important;
    }

    body[data-page="beranda"] .damage-summary-value {
        margin-top: .24rem !important;
        font-size: 1.08rem !important;
    }

    body[data-page="beranda"] .damage-list {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
        padding-right: 4px !important;
        gap: .42rem !important;
    }

    body[data-page="beranda"] .damage-row-simple {
        padding: .52rem .58rem !important;
        border-radius: 13px !important;
    }

    body[data-page="beranda"] .damage-row-top {
        margin-bottom: .34rem !important;
    }

    body[data-page="beranda"] .damage-progress {
        height: 7px !important;
    }

    body[data-page="beranda"] .damage-row-bottom {
        margin-top: .34rem !important;
        font-size: .61rem !important;
    }

    body[data-page="beranda"] .availability-grid {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        align-content: stretch !important;
    }

    body[data-page="beranda"] .availability-box {
        height: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
    }

    @media (max-width: 900px) {
        body[data-page="beranda"] .facility-monitor-grid>.facility-card {
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
        }

        body[data-page="beranda"] .damage-list {
            max-height: 300px !important;
        }

        body[data-page="beranda"] .availability-box {
            min-height: 130px !important;
        }
    }

    /* ===== DETAIL MONITORING PRESENSI: 2 KOLOM SEJAJAR ===== */
    .monitoring-presensi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        align-items: stretch;
    }

    .monitoring-presensi-grid .presensi-soft-card {
        height: 100%;
        min-width: 0;
    }

    @media (max-width: 768px) {
        .monitoring-presensi-grid {
            grid-template-columns: 1fr !important;
        }
    }


    /* ===== FINAL: TINGGI CARD DASHBOARD DINAIKKAN SEDIKIT =====
       Menjaga card dua kolom tetap sejajar dan memastikan isi tidak terpotong. */
    @media (min-width: 901px) {
        body[data-page="beranda"] .trend-agenda-grid {
            align-items: stretch !important;
        }

        body[data-page="beranda"] .trend-agenda-grid>.trend-full-card,
        body[data-page="beranda"] .trend-agenda-grid>.presensi-soft-card {
            height: 390px !important;
            min-height: 390px !important;
            max-height: 390px !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }

        body[data-page="beranda"] .trend-modern-chart-wrap {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            height: auto !important;
        }

        body[data-page="beranda"] .trend-summary-row {
            flex: 0 0 auto !important;
        }

        body[data-page="beranda"] .trend-agenda-grid .agenda-card-body {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow-y: auto !important;
            overscroll-behavior: contain !important;
            padding-right: 5px !important;
        }

        body[data-page="beranda"] .trend-agenda-grid .agenda-card-footer {
            flex: 0 0 auto !important;
            margin-top: .7rem !important;
        }

        body[data-page="beranda"] .facility-monitor-grid {
            align-items: stretch !important;
        }

        body[data-page="beranda"] .facility-monitor-grid>.facility-card {
            height: 370px !important;
            min-height: 370px !important;
            max-height: 370px !important;
        }

        body[data-page="beranda"] .availability-grid {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            align-items: stretch !important;
        }

        body[data-page="beranda"] .availability-box {
            min-height: 0 !important;
            height: 100% !important;
        }
    }

    @media (max-width: 900px) {

        body[data-page="beranda"] .trend-agenda-grid>.trend-full-card,
        body[data-page="beranda"] .trend-agenda-grid>.presensi-soft-card,
        body[data-page="beranda"] .facility-monitor-grid>.facility-card {
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow: visible !important;
        }

        body[data-page="beranda"] .trend-agenda-grid .agenda-card-body {
            max-height: 330px !important;
            overflow-y: auto !important;
        }
    }


    /* ===== FINAL: KATEGORI DONUT MODERN, SIMPLE, DAN RAPI ===== */
    .agenda-donut-legend {
        gap: .42rem !important;
        padding: 2px 5px 2px 1px !important;
        align-content: start !important;
    }

    .agenda-donut-category {
        position: relative;
        min-width: 0;
    }

    .agenda-donut-legend-item {
        display: grid !important;
        grid-template-columns: 10px minmax(0, 1fr) auto 22px !important;
        gap: .55rem !important;
        align-items: center !important;
        width: 100% !important;
        min-height: 46px !important;
        padding: .62rem .68rem !important;
        border: 1px solid #e5eaf1 !important;
        border-radius: 14px !important;
        background: #ffffff !important;
        color: #0f172a !important;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .035) !important;
        transform: none !important;
        outline: none !important;
        overflow: hidden !important;
    }

    .agenda-donut-legend-item:hover {
        border-color: #cbd5e1 !important;
        background: #f8fafc !important;
        box-shadow: 0 5px 14px rgba(15, 23, 42, .055) !important;
    }

    .agenda-donut-legend-item:focus-visible {
        border-color: #93c5fd !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .12) !important;
    }

    .agenda-donut-legend-item.is-open {
        border-color: #bfdbfe !important;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%) !important;
        box-shadow: 0 7px 18px rgba(37, 99, 235, .08) !important;
    }

    .agenda-donut-dot {
        width: 9px !important;
        height: 9px !important;
        box-shadow: 0 0 0 4px rgba(148, 163, 184, .10) !important;
    }

    .agenda-donut-label {
        color: #1e293b !important;
        font-size: .69rem !important;
        font-weight: 850 !important;
        letter-spacing: -.01em !important;
    }

    .agenda-donut-count {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 26px !important;
        height: 24px !important;
        padding: 0 .45rem !important;
        border-radius: 999px !important;
        background: #f1f5f9 !important;
        color: #334155 !important;
        font-size: .66rem !important;
        font-weight: 900 !important;
        line-height: 1 !important;
    }

    .agenda-donut-legend-item.is-open .agenda-donut-count {
        background: #dbeafe !important;
        color: #1d4ed8 !important;
    }

    .agenda-donut-chevron {
        width: 22px !important;
        height: 22px !important;
        border-radius: 999px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        justify-self: end !important;
        color: #94a3b8 !important;
        background: #f8fafc !important;
        font-size: .55rem !important;
        transition: transform .2s ease, color .2s ease, background .2s ease !important;
    }

    .agenda-donut-legend-item.is-open .agenda-donut-chevron {
        color: #2563eb !important;
        background: #eaf2ff !important;
        transform: rotate(180deg) !important;
    }

    .agenda-donut-detail {
        margin: .4rem 0 .08rem !important;
        padding: .45rem !important;
        border: 1px solid #e7edf5 !important;
        border-radius: 14px !important;
        background: #f8fafc !important;
        box-shadow: none !important;
        max-height: 205px !important;
    }

    .agenda-donut-detail.is-open {
        gap: .42rem !important;
    }

    .agenda-donut-training {
        padding: .58rem .62rem !important;
        border: 1px solid #e8edf3 !important;
        border-radius: 12px !important;
        background: #ffffff !important;
        box-shadow: none !important;
        transition: border-color .18s ease, box-shadow .18s ease !important;
    }

    .agenda-donut-training:hover {
        border-color: #cbd5e1 !important;
        box-shadow: 0 4px 12px rgba(15, 23, 42, .04) !important;
    }

    .agenda-donut-training::before {
        display: none !important;
    }

    .agenda-donut-training-top {
        align-items: center !important;
        gap: .45rem !important;
    }

    .agenda-donut-training strong {
        font-size: .64rem !important;
        line-height: 1.4 !important;
        color: #172033 !important;
    }

    .agenda-donut-training-date {
        margin-top: .28rem !important;
        font-size: .58rem !important;
        color: #64748b !important;
    }

    .agenda-donut-status {
        padding: .27rem .43rem !important;
        font-size: .53rem !important;
    }

    @media (max-width: 520px) {
        .agenda-donut-legend-item {
            grid-template-columns: 9px minmax(0, 1fr) auto 21px !important;
            min-height: 44px !important;
            padding: .58rem .62rem !important;
            border-radius: 13px !important;
        }

        .agenda-donut-label {
            font-size: .67rem !important;
        }

        .agenda-donut-count {
            min-width: 24px !important;
            height: 22px !important;
            font-size: .63rem !important;
        }
    }


    /* ===== AKTIVITAS TERBARU: BENAR-BENAR FULL WIDTH SEPERTI HEADER ===== */
    body[data-page="beranda"] .activity-latest-shell {
        position: relative;
        left: 50%;
        width: 100vw;
        max-width: none !important;
        margin-left: -50vw !important;
        margin-right: 0 !important;
        margin-top: 0 !important;
        margin-bottom: 1rem !important;
        padding: 1.2rem clamp(1rem, 1.6vw, 2rem) 1.45rem;
        isolation: isolate;
        overflow: hidden;
        border: 0;
        border-radius: 0 0 34px 34px;
        background:
            radial-gradient(circle at 88% 12%, rgba(255, 255, 255, .18) 0 78px, transparent 79px),
            radial-gradient(circle at 7% 112%, rgba(255, 255, 255, .11) 0 105px, transparent 106px),
            linear-gradient(135deg, #0b55d5 0%, #1486ef 52%, #35bdf6 100%);
        box-shadow: 0 18px 38px rgba(3, 105, 161, .22);
        box-sizing: border-box;
    }

    body[data-page="beranda"] .activity-latest-shell::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: -2;
        pointer-events: none;
        background:
            linear-gradient(115deg, transparent 0 54%, rgba(255, 255, 255, .07) 54.5% 56%, transparent 56.5%),
            linear-gradient(180deg, rgba(255, 255, 255, .08), transparent 52%);
    }

    body[data-page="beranda"] .activity-latest-shell::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 34px;
        z-index: -1;
        pointer-events: none;
        background: rgba(255, 255, 255, .08);
        clip-path: ellipse(64% 54% at 50% 100%);
    }

    body[data-page="beranda"] .activity-latest-inner {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: none;
        margin: 0;
        min-width: 0;
        box-sizing: border-box;
    }

    body[data-page="beranda"] .activity-latest-glow {
        position: absolute;
        z-index: -1;
        border-radius: 999px;
        pointer-events: none;
        filter: blur(1px);
    }

    body[data-page="beranda"] .activity-latest-glow-one {
        width: 230px;
        height: 230px;
        top: -128px;
        right: -72px;
        background: rgba(255, 255, 255, .10);
        border: 1px solid rgba(255, 255, 255, .14);
    }

    body[data-page="beranda"] .activity-latest-glow-two {
        width: 175px;
        height: 175px;
        left: -92px;
        bottom: -105px;
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .10);
    }

    body[data-page="beranda"] .activity-latest-shell .latest-section,
    body[data-page="beranda"] .activity-latest-shell .latest-section-modern {
        position: relative;
        z-index: 2;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header,
    body[data-page="beranda"] .activity-latest-shell .latest-header-modern {
        margin-bottom: .95rem !important;
        align-items: center !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header h3,
    body[data-page="beranda"] .activity-latest-shell .latest-header-copy h3 {
        color: #fff !important;
        letter-spacing: -.015em;
        text-shadow: 0 1px 2px rgba(15, 23, 42, .12);
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header-copy span,
    body[data-page="beranda"] .activity-latest-shell .latest-header p {
        color: rgba(255, 255, 255, .80) !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header-icon {
        color: #fff !important;
        background: rgba(255, 255, 255, .16) !important;
        border-color: rgba(255, 255, 255, .24) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .18);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }

    /* Tombol Lihat Semua dibuat lebih rapi, tegas, dan modern */
    body[data-page="beranda"] .activity-latest-shell .latest-header>a,
    body[data-page="beranda"] .activity-latest-shell .latest-see-all {
        min-height: 36px !important;
        padding: .58rem .88rem !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: .46rem !important;
        border-radius: 999px !important;
        color: #075fbd !important;
        background: rgba(255, 255, 255, .96) !important;
        border: 1px solid rgba(255, 255, 255, .82) !important;
        box-shadow: 0 9px 22px rgba(3, 82, 155, .20) !important;
        font-size: .68rem !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        letter-spacing: .01em !important;
        text-decoration: none !important;
        white-space: nowrap !important;
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header>a::after,
    body[data-page="beranda"] .activity-latest-shell .latest-see-all::after {
        content: "\f061";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        font-size: .65rem;
        line-height: 1;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header>a:hover,
    body[data-page="beranda"] .activity-latest-shell .latest-see-all:hover {
        color: #064f9c !important;
        background: #fff !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 13px 28px rgba(3, 82, 155, .26) !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-scroll,
    body[data-page="beranda"] .activity-latest-shell .latest-grid-modern {
        gap: .78rem !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-card,
    body[data-page="beranda"] .activity-latest-shell .latest-card-modern {
        background: rgba(255, 255, 255, .97) !important;
        border-color: rgba(255, 255, 255, .86) !important;
        box-shadow: 0 12px 26px rgba(2, 73, 149, .16) !important;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    body[data-page="beranda"] .activity-latest-shell .latest-card:hover,
    body[data-page="beranda"] .activity-latest-shell .latest-card-modern:hover {
        border-color: #fff !important;
        box-shadow: 0 17px 34px rgba(2, 73, 149, .22) !important;
        transform: translateY(-2px);
    }

    body[data-page="beranda"] .activity-latest-shell .empty-state,
    body[data-page="beranda"] .activity-latest-shell .latest-empty-modern {
        background: rgba(255, 255, 255, .94) !important;
        border-color: rgba(255, 255, 255, .76) !important;
    }

    @media (max-width: 900px) {
        body[data-page="beranda"] .activity-latest-shell {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
            border-radius: 0 0 28px 28px;
        }
    }

    @media (max-width: 520px) {
        body[data-page="beranda"] .activity-latest-shell {
            padding: 1rem 1rem 1.25rem !important;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 14px 28px rgba(3, 105, 161, .20);
        }

        body[data-page="beranda"] .activity-latest-shell .latest-header>a,
        body[data-page="beranda"] .activity-latest-shell .latest-see-all {
            min-height: 34px !important;
            padding: .52rem .72rem !important;
            font-size: .64rem !important;
        }

        body[data-page="beranda"] .activity-latest-glow-one {
            width: 170px;
            height: 170px;
            top: -90px;
            right: -55px;
        }
    }


    /* ===== FINAL: AKTIVITAS TERBARU TANPA BACKGROUND WARNA ===== */
    body[data-page="beranda"] .activity-latest-shell {
        position: relative !important;
        left: auto !important;
        right: auto !important;
        width: auto !important;
        max-width: none !important;
        margin: 0 1rem 1rem !important;
        padding: 0 !important;
        overflow: visible !important;
        isolation: auto !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        box-sizing: border-box !important;
    }

    body[data-page="beranda"] .activity-latest-shell::before,
    body[data-page="beranda"] .activity-latest-shell::after,
    body[data-page="beranda"] .activity-latest-glow {
        display: none !important;
        content: none !important;
    }

    body[data-page="beranda"] .activity-latest-inner {
        position: relative !important;
        z-index: auto !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        box-sizing: border-box !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-section,
    body[data-page="beranda"] .activity-latest-shell .latest-section-modern {
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header,
    body[data-page="beranda"] .activity-latest-shell .latest-header-modern {
        margin-bottom: .72rem !important;
        padding: 0 !important;
        align-items: center !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header h3,
    body[data-page="beranda"] .activity-latest-shell .latest-header-copy h3 {
        color: #0f172a !important;
        text-shadow: none !important;
        letter-spacing: -.012em !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header-copy span,
    body[data-page="beranda"] .activity-latest-shell .latest-header p {
        color: #94a3b8 !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header-icon {
        color: #0284c7 !important;
        background: #e0f2fe !important;
        border-color: #bae6fd !important;
        box-shadow: none !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header>a,
    body[data-page="beranda"] .activity-latest-shell .latest-see-all {
        min-height: 34px !important;
        padding: .5rem .78rem !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: .42rem !important;
        border-radius: 999px !important;
        color: #0369a1 !important;
        background: #f0f9ff !important;
        border: 1px solid #bae6fd !important;
        box-shadow: none !important;
        font-size: .66rem !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        text-decoration: none !important;
        white-space: nowrap !important;
        transition: transform .18s ease, background .18s ease, border-color .18s ease !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-header>a:hover,
    body[data-page="beranda"] .activity-latest-shell .latest-see-all:hover {
        color: #075985 !important;
        background: #e0f2fe !important;
        border-color: #7dd3fc !important;
        transform: translateY(-1px) !important;
        box-shadow: none !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-scroll,
    body[data-page="beranda"] .activity-latest-shell .latest-grid-modern {
        width: 100% !important;
        gap: .75rem !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-card,
    body[data-page="beranda"] .activity-latest-shell .latest-card-modern {
        background: #fff !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .05) !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
    }

    body[data-page="beranda"] .activity-latest-shell .latest-card:hover,
    body[data-page="beranda"] .activity-latest-shell .latest-card-modern:hover {
        border-color: #bae6fd !important;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .075) !important;
        transform: translateY(-2px) !important;
    }

    body[data-page="beranda"] .activity-latest-shell .empty-state,
    body[data-page="beranda"] .activity-latest-shell .latest-empty-modern {
        background: #fff !important;
        border-color: #cbd5e1 !important;
    }

    @media (max-width: 900px) {
        body[data-page="beranda"] .activity-latest-shell {
            width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-bottom: 1rem !important;
        }
    }

    @media (max-width: 520px) {
        body[data-page="beranda"] .activity-latest-shell {
            padding: 0 !important;
            border-radius: 0 !important;
        }

        body[data-page="beranda"] .activity-latest-shell .latest-header,
        body[data-page="beranda"] .activity-latest-shell .latest-header-modern {
            align-items: center !important;
            gap: .65rem !important;
        }

        body[data-page="beranda"] .activity-latest-shell .latest-header>a,
        body[data-page="beranda"] .activity-latest-shell .latest-see-all {
            min-height: 32px !important;
            padding: .46rem .65rem !important;
            font-size: .62rem !important;
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

            <div class="trend-agenda-grid">
                <div class="trend-full-card">
                    <div class="presensi-card-head">
                        <div>
                            <div class="presensi-card-title">Tren Kehadiran 7 Hari</div>
                            <div class="presensi-sub">Jumlah pegawai presensi masuk setiap hari</div>
                        </div>
                        <a href="riwayat_absensi.php" class="presensi-card-link">Detail</a>
                    </div>
                    <?php
                    $trendValues = array_map('intval', $dashChart7ValuesOut ?: array_fill(0, 7, 0));
                    $trendLabels = $dashChart7Labels ?: [];
                    $trendAverage = count($trendValues) ? round(array_sum($trendValues) / count($trendValues), 1) : 0;
                    $trendLast = $trendValues ? (int)end($trendValues) : 0;
                    $trendPrevious = count($trendValues) > 1 ? (int)$trendValues[count($trendValues) - 2] : 0;
                    $trendChange = $trendLast - $trendPrevious;
                    ?>
                    <div class="trend-modern-chart-wrap">
                        <canvas id="trendKehadiranChart" aria-label="Grafik tren kehadiran tujuh hari terakhir" role="img"></canvas>
                    </div>
                    <div class="trend-summary-row">
                        <span>Rata-rata <strong><?= number_format($trendAverage, 1, ',', '.') ?> pegawai/hari</strong></span>
                        <span>Hari terakhir <strong><?= $trendLast ?> pegawai</strong></span>
                        <span>Perubahan <strong><?= $trendChange > 0 ? '+' : '' ?><?= $trendChange ?></strong> dari hari sebelumnya</span>
                    </div>
                </div>
                <div class="presensi-soft-card agenda-donut-card">
                    <div class="presensi-card-head">
                        <div>
                            <div class="presensi-card-title">Komposisi Agenda Bulan Ini</div>
                            <div class="presensi-sub"><?= date('F Y') ?> berdasarkan kategori kegiatan</div>
                        </div>
                        <span class="presensi-card-link"><?= (int)$dashTotalAgendaBulanIni ?> agenda</span>
                    </div>

                    <?php if (empty($dashKomposisiAgenda)): ?>
                        <div class="presensi-empty agenda-donut-empty">Belum ada data agenda yang dapat dikelompokkan pada bulan ini.</div>
                    <?php else: ?>
                        <div class="agenda-donut-content">
                            <div class="agenda-donut-chart-wrap">
                                <canvas id="agendaKomposisiChart" aria-label="Grafik komposisi agenda bulan ini" role="img"></canvas>
                                <div class="agenda-donut-center" aria-hidden="true">
                                    <strong><?= (int)$dashTotalAgendaBulanIni ?></strong>
                                    <span>Agenda</span>
                                </div>
                            </div>
                            <div class="agenda-donut-legend" id="agendaDonutLegend">
                                <?php foreach ($dashKomposisiAgenda as $idxKategori => $kategoriAgenda): ?>
                                    <?php
                                    $kategoriKey = 'agenda-category-' . (int)$idxKategori;
                                    $agendaItems = is_array($kategoriAgenda['items'] ?? null) ? $kategoriAgenda['items'] : [];
                                    ?>
                                    <div class="agenda-donut-category" data-category-index="<?= (int)$idxKategori ?>">
                                        <button type="button" class="agenda-donut-legend-item" data-target="<?= $kategoriKey ?>" aria-expanded="false">
                                            <span class="agenda-donut-dot" data-color-index="<?= (int)$idxKategori ?>"></span>
                                            <span class="agenda-donut-label"><?= htmlspecialchars($kategoriAgenda['kategori'] ?? 'Lainnya', ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="agenda-donut-count"><?= (int)($kategoriAgenda['total'] ?? 0) ?></span>
                                            <i class="fa-solid fa-chevron-down agenda-donut-chevron" aria-hidden="true"></i>
                                        </button>
                                        <div class="agenda-donut-detail" id="<?= $kategoriKey ?>">
                                            <?php if (!$agendaItems): ?>
                                                <div class="presensi-empty">Belum ada rincian pelatihan.</div>
                                                <?php else: foreach ($agendaItems as $agendaItem): ?>
                                                    <?php
                                                    $mulaiTs = strtotime((string)($agendaItem['mulai'] ?? ''));
                                                    $selesaiTs = strtotime((string)($agendaItem['selesai'] ?? $agendaItem['mulai'] ?? ''));
                                                    $rentangAgenda = $mulaiTs ? date('d M Y', $mulaiTs) : '-';
                                                    if ($selesaiTs && $mulaiTs && date('Y-m-d', $selesaiTs) !== date('Y-m-d', $mulaiTs)) {
                                                        $rentangAgenda .= ' - ' . date('d M Y', $selesaiTs);
                                                    }
                                                    ?>
                                                    <?php
                                                    $mulaiDate = $mulaiTs ? date('Y-m-d', $mulaiTs) : $todaySql;
                                                    $selesaiDate = $selesaiTs ? date('Y-m-d', $selesaiTs) : $mulaiDate;
                                                    if ($todaySql >= $mulaiDate && $todaySql <= $selesaiDate) {
                                                        $statusAgendaClass = 'ongoing';
                                                        $statusAgendaLabel = 'Berlangsung';
                                                    } elseif ($todaySql < $mulaiDate) {
                                                        $statusAgendaClass = 'upcoming';
                                                        $statusAgendaLabel = 'Akan datang';
                                                    } else {
                                                        $statusAgendaClass = 'done';
                                                        $statusAgendaLabel = 'Selesai';
                                                    }
                                                    ?>
                                                    <div class="agenda-donut-training">
                                                        <div class="agenda-donut-training-top">
                                                            <strong><?= htmlspecialchars($agendaItem['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                                                            <span class="agenda-donut-status <?= $statusAgendaClass ?>"><?= $statusAgendaLabel ?></span>
                                                        </div>
                                                        <span class="agenda-donut-training-date"><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($rentangAgenda, ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                            <?php endforeach;
                                            endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="agenda-card-footer">
                        <a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> Lihat Agenda</a>
                    </div>
                </div>
            </div>

            <!-- <div class="exec-panel-grid">
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
            </div> -->


        </section>

        <!-- <div class="final-section-title">
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
        </div> -->

        <div class="facility-monitor-grid">
            <div class="facility-card">
                <div class="facility-head">
                    <div>
                        <div class="facility-title">Kerusakan Gedung</div>
                        <div class="facility-sub">Total, belum diperbaiki, dan selesai</div>
                    </div>
                    <a href="laporan_kerusakan.php" class="facility-link">Detail</a>
                </div>

                <div class="damage-summary-grid">
                    <div class="damage-summary-box">
                        <div class="damage-summary-label">Total</div>
                        <div class="damage-summary-value"><?= (int)$dashKerusakanSummary['total'] ?></div>
                    </div>
                    <div class="damage-summary-box active">
                        <div class="damage-summary-label">Belum Diperbaiki</div>
                        <div class="damage-summary-value"><?= (int)$dashKerusakanSummary['belum'] ?></div>
                    </div>
                    <div class="damage-summary-box done">
                        <div class="damage-summary-label">Selesai</div>
                        <div class="damage-summary-value"><?= (int)$dashKerusakanSummary['selesai'] ?></div>
                    </div>
                </div>

                <div class="damage-list">
                    <?php if (empty($dashKerusakanGedung)): ?>
                        <div class="presensi-empty">Belum ada data kerusakan per gedung.</div>
                        <?php else: foreach ($dashKerusakanGedung as $kg): ?>
                            <div class="damage-row-simple">
                                <div class="damage-row-top">
                                    <div class="damage-name"><?= htmlspecialchars($kg['gedung'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="damage-total"><?= (int)$kg['total'] ?> laporan</div>
                                </div>
                                <div class="damage-progress" title="<?= (int)$kg['persen_selesai'] ?>% selesai">
                                    <div class="damage-progress-fill" style="width:<?= max(0, min(100, (int)$kg['persen_selesai'])) ?>%"></div>
                                </div>
                                <div class="damage-row-bottom">
                                    <span><?= (int)$kg['persen_selesai'] ?>% selesai</span>
                                    <span class="damage-status-inline">
                                        <span>Belum <b><?= (int)$kg['belum'] ?></b></span>
                                        <span>Selesai <b><?= (int)$kg['selesai'] ?></b></span>
                                    </span>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>

            <div class="facility-card">
                <div class="facility-head">
                    <div>
                        <div class="facility-title">Ketersediaan Asrama &amp; Kelas</div>
                        <div class="facility-sub">Estimasi hari ini dari data penginapan dan timetable</div>
                    </div>
                    <a href="timetable.php" class="facility-link">Lihat</a>
                </div>
                <div class="availability-grid">
                    <div class="availability-box">
                        <div class="availability-icon"><i class="fa-solid fa-bed"></i></div>
                        <div class="availability-label">Kamar kosong</div>
                        <div class="availability-value"><?= (int)$dashKamarKosong ?></div>
                        <div class="availability-note"><?= (int)$dashKamarTerpakai ?> terpakai dari <?= (int)$dashTotalKamarAsrama ?> kamar</div>
                    </div>
                    <div class="availability-box">
                        <div class="availability-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                        <div class="availability-label">Kelas kosong</div>
                        <div class="availability-value"><?= (int)$dashKelasKosong ?></div>
                        <div class="availability-note"><?= (int)$dashKelasTerpakai ?> terpakai dari <?= (int)$dashTotalKelas ?> kelas</div>
                    </div>
                </div>
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

            <div class="presensi-soft-grid monitoring-presensi-grid">
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
                                    <div class="presensi-person">
                                        <strong><?= htmlspecialchars($t['nama_petugas'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span><?= date('H:i', $jamTelat ?: time()) ?> WIB</span>
                                    </div>
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

        <!-- Ringkasan AI dipindahkan ke paling bawah halaman dashboard -->
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


    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (async function() {
            const canvas = document.getElementById('trendKehadiranChart');
            if (!canvas || typeof Chart === 'undefined') return;

            /*
             * Canvas tidak mengikuti font CSS secara otomatis.
             * Tunggu seluruh webfont selesai dimuat, lalu ambil font yang benar-benar
             * dipakai halaman agar tulisan grafik sama persis dengan tema aplikasi.
             */
            if (document.fonts && document.fonts.ready) {
                try {
                    await document.fonts.ready;
                } catch (e) {}
            }

            const bodyStyle = window.getComputedStyle(document.body);
            const appFontFamily = bodyStyle.fontFamily || 'Arial, sans-serif';

            Chart.defaults.font.family = appFontFamily;
            Chart.defaults.font.size = 12;
            Chart.defaults.font.weight = '600';
            Chart.defaults.color = '#64748b';

            const labels = <?= json_encode(array_values($dashChart7Labels), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const values = <?= json_encode(array_values($dashChart7ValuesOut), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 250);
            gradient.addColorStop(0, 'rgba(79, 70, 229, .30)');
            gradient.addColorStop(.55, 'rgba(99, 102, 241, .10)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

            const chartFont = function(size, weight) {
                return {
                    family: appFontFamily,
                    size: size,
                    weight: weight,
                    lineHeight: 1.25
                };
            };

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Kehadiran',
                        data: values,
                        borderColor: '#4f46e5',
                        backgroundColor: gradient,
                        fill: true,
                        borderWidth: 3,
                        tension: .42,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBorderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#4f46e5',
                        pointHoverBackgroundColor: '#4f46e5',
                        pointHoverBorderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    animation: {
                        duration: 700,
                        easing: 'easeOutQuart'
                    },
                    layout: {
                        padding: {
                            top: 14,
                            right: 10,
                            bottom: 0,
                            left: 2
                        }
                    },
                    font: chartFont(12, '600'),
                    plugins: {
                        legend: {
                            display: false,
                            labels: {
                                font: chartFont(12, '700')
                            }
                        },
                        tooltip: {
                            displayColors: false,
                            backgroundColor: '#0f172a',
                            titleColor: '#ffffff',
                            bodyColor: '#e2e8f0',
                            padding: 11,
                            cornerRadius: 10,
                            titleFont: chartFont(12, '800'),
                            bodyFont: chartFont(11, '600'),
                            footerFont: chartFont(10, '600'),
                            callbacks: {
                                title: function(items) {
                                    return items.length ? 'Tanggal ' + items[0].label : '';
                                },
                                label: function(item) {
                                    return item.parsed.y + ' pegawai hadir';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            border: {
                                display: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: chartFont(10, '700'),
                                maxRotation: 0,
                                autoSkip: false
                            },
                            title: {
                                font: chartFont(11, '700')
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grace: '15%',
                            border: {
                                display: false
                            },
                            grid: {
                                color: 'rgba(148,163,184,.18)',
                                drawTicks: false
                            },
                            ticks: {
                                precision: 0,
                                color: '#94a3b8',
                                padding: 8,
                                font: chartFont(10, '700')
                            },
                            title: {
                                font: chartFont(11, '700')
                            }
                        }
                    }
                }
            });
        })();
    </script>


    <script>
        (async function() {
            const canvas = document.getElementById('agendaKomposisiChart');
            if (!canvas || typeof Chart === 'undefined') return;

            if (document.fonts && document.fonts.ready) {
                try {
                    await document.fonts.ready;
                } catch (e) {}
            }

            const bodyStyle = window.getComputedStyle(document.body);
            const appFontFamily = bodyStyle.fontFamily || 'Arial, sans-serif';
            const labels = <?= json_encode(array_values(array_map(static function ($row) {
                                return (string)($row['kategori'] ?? 'Lainnya');
                            }, $dashKomposisiAgenda)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const values = <?= json_encode(array_values(array_map(static function ($row) {
                                return (int)($row['total'] ?? 0);
                            }, $dashKomposisiAgenda)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const categoryColors = {
                'Menpim': '#facc15',
                'Teknis': '#22c55e',
                'Pustrajak': '#f97316',
                'Kerjasama': '#3b82f6',
                'Lainnya': '#94a3b8'
            };
            const palette = labels.map(function(label) {
                return categoryColors[label] || categoryColors.Lainnya;
            });

            document.querySelectorAll('.agenda-donut-dot[data-color-index]').forEach(function(dot) {
                const index = parseInt(dot.getAttribute('data-color-index') || '0', 10);
                dot.style.backgroundColor = palette[index] || categoryColors.Lainnya;
            });

            function toggleAgendaCategory(index) {
                const category = document.querySelector('.agenda-donut-category[data-category-index="' + index + '"]');
                if (!category) return;
                const button = category.querySelector('.agenda-donut-legend-item');
                const detail = category.querySelector('.agenda-donut-detail');
                if (!button || !detail) return;

                const willOpen = !detail.classList.contains('is-open');
                document.querySelectorAll('.agenda-donut-detail.is-open').forEach(function(el) {
                    el.classList.remove('is-open');
                });
                document.querySelectorAll('.agenda-donut-legend-item.is-open').forEach(function(el) {
                    el.classList.remove('is-open');
                    el.setAttribute('aria-expanded', 'false');
                });

                if (willOpen) {
                    detail.classList.add('is-open');
                    button.classList.add('is-open');
                    button.setAttribute('aria-expanded', 'true');
                    category.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                }
            }

            document.querySelectorAll('.agenda-donut-legend-item[data-target]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const category = button.closest('.agenda-donut-category');
                    if (!category) return;
                    toggleAgendaCategory(parseInt(category.getAttribute('data-category-index') || '0', 10));
                });
            });

            const agendaChart = new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: palette.slice(0, Math.max(1, labels.length)),
                        borderColor: '#ffffff',
                        borderWidth: 4,
                        hoverBorderWidth: 4,
                        hoverOffset: 7,
                        spacing: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: function(event, elements) {
                        if (elements && elements.length) {
                            toggleAgendaCategory(elements[0].index);
                        }
                    },
                    cutout: '68%',
                    animation: {
                        duration: 750,
                        easing: 'easeOutQuart'
                    },
                    layout: {
                        padding: 8
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#ffffff',
                            bodyColor: '#e2e8f0',
                            padding: 11,
                            cornerRadius: 10,
                            titleFont: {
                                family: appFontFamily,
                                size: 12,
                                weight: '800'
                            },
                            bodyFont: {
                                family: appFontFamily,
                                size: 11,
                                weight: '600'
                            },
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce(function(sum, value) {
                                        return sum + Number(value || 0);
                                    }, 0);
                                    const value = Number(context.raw || 0);
                                    const percent = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return ' ' + context.label + ': ' + value + ' agenda (' + percent + '%)';
                                }
                            }
                        }
                    }
                }
            });
        })();
    </script>
    <?php include 'footer.php'; ?>