<?php
/* ============================================================
   admin_log.php  —  Halaman Log Aktivitas (khusus admin)
   Versi revisi — clean white theme, fixed layout & pagination
   ============================================================ */
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$userRole = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';

if ($userRole !== 'admin') {
    http_response_code(403);
    echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:"Plus Jakarta Sans",sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh}
        .box{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:36px 28px;max-width:340px;width:90%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.06)}
        .icon{font-size:2.8rem;margin-bottom:14px}
        h2{font-size:1rem;font-weight:700;color:#dc2626;margin-bottom:8px}
        p{font-size:.84rem;color:#64748b;margin-bottom:22px;line-height:1.6}
        a{display:inline-block;padding:11px 24px;background:#0ea5e9;color:#fff;text-decoration:none;border-radius:12px;font-size:.84rem;font-weight:700}


        /* ===== LEAFLET LIVE MAP REAL ===== */
        .tracking-map-canvas {
            position: relative;
            min-height: 560px;
            height: 62vh;
            border-radius: 0 0 16px 16px;
            overflow: hidden;
            background: #e0f2fe;
        }

        .tracking-map-canvas .leaflet-container,
        #trackingMap {
            width: 100%;
            height: 100%;
        }

        .tracking-map-canvas .leaflet-control-container {
            font-family: var(--font);
        }

        .track-leaflet-marker {
            width: auto !important;
            height: auto !important;
        }

        .track-marker-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            padding: 6px 10px 6px 6px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .18);
            white-space: nowrap;
            transform: translate(-10px, -10px);
        }

        .track-marker-dot {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 11px;
            font-weight: 900;
            flex-shrink: 0;
        }

        .track-marker-wrap.online .track-marker-dot { background: #16a34a; }
        .track-marker-wrap.idle .track-marker-dot { background: #d97706; }
        .track-marker-wrap.offline .track-marker-dot { background: #dc2626; }

        .track-marker-text {
            display: grid;
            gap: 1px;
            min-width: 0;
        }

        .track-marker-name {
            color: #0f172a;
            font-size: 12px;
            font-weight: 900;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .track-marker-meta {
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 16px;
            font-family: var(--font);
        }

        .leaflet-popup-content {
            margin: 12px 14px;
            min-width: 210px;
            font-size: 12px;
        }

        .track-popup-title {
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .track-popup-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            font-weight: 700;
        }

        .track-popup-line span:first-child { color: #64748b; }
        .track-popup-line span:last-child { color: #0f172a; text-align: right; }

        .track-popup-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 10px;
            padding: 9px 12px;
            border-radius: 10px;
            background: #0ea5e9;
            color: #fff !important;
            text-decoration: none;
            font-size: 12px;
            font-weight: 900;
        }

        .map-floating-badge {
            position: absolute;
            z-index: 500;
            top: 14px;
            left: 14px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .95);
            color: #0369a1;
            border: 1px solid #dbeafe;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .10);
            font-size: 11px;
            font-weight: 900;
            pointer-events: none;
        }

        .map-load-error {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            color: #64748b;
            font-weight: 800;
        }

        @media(max-width:768px) {
            .tracking-map-canvas {
                min-height: 430px;
                height: 55vh;
            }
            .track-marker-name { max-width: 110px; }
        }


        /* ===== LIVE TRACKING CLEAN FIX ===== */
        .tracking-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(340px, .75fr);
            gap: 14px;
            align-items: stretch;
        }

        .tracking-map-panel,
        .tracking-list-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .tracking-panel-head {
            min-height: 62px;
            background: #fff;
        }

        .tracking-map-canvas {
            position: relative;
            height: 620px;
            min-height: 620px;
            background: #e0f2fe;
            overflow: hidden;
        }

        #trackingMap,
        .tracking-map-canvas .leaflet-container {
            width: 100%;
            height: 100%;
            min-height: 620px;
        }

        .tracking-map-canvas:before {
            display: none !important;
        }

        .tracking-list-body {
            height: 620px;
            max-height: 620px;
            overflow-y: auto;
            padding: 12px;
            background: #f8fafc;
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .track-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 13px;
            box-shadow: var(--shadow-sm);
            transition: .12s ease;
        }

        .track-card:hover {
            border-color: #bae6fd;
            box-shadow: var(--shadow);
        }

        .track-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 10px;
        }

        .track-name {
            font-size: .88rem;
            font-weight: 900;
            color: var(--ink);
            line-height: 1.25;
        }

        .track-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 5px 8px;
            font-size: .65rem;
            font-weight: 900;
            white-space: nowrap;
        }

        .track-status.online { background: #dcfce7; color: #166534; }
        .track-status.idle { background: #fef3c7; color: #92400e; }
        .track-status.offline { background: #fee2e2; color: #991b1b; }

        .track-info {
            display: grid;
            gap: 7px;
            margin-bottom: 10px;
        }

        .track-row {
            display: grid;
            grid-template-columns: 86px 1fr;
            gap: 8px;
            font-size: .72rem;
        }

        .track-key {
            color: var(--muted);
            font-weight: 800;
        }

        .track-val {
            color: var(--ink-2);
            font-weight: 700;
            word-break: break-word;
        }

        .track-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .track-actions a {
            flex: 1;
            justify-content: center;
        }

        .track-leaflet-marker {
            width: auto !important;
            height: auto !important;
        }

        .track-marker-wrap {
            display: flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,.97);
            border: 1px solid #dbeafe;
            border-radius: 999px;
            padding: 5px 9px 5px 5px;
            box-shadow: 0 10px 24px rgba(15,23,42,.18);
            white-space: nowrap;
        }

        .track-marker-dot {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            flex-shrink: 0;
        }

        .track-marker-wrap.online .track-marker-dot { background: #16a34a; }
        .track-marker-wrap.idle .track-marker-dot { background: #d97706; }
        .track-marker-wrap.offline .track-marker-dot { background: #dc2626; }

        .track-marker-text {
            display: grid;
            gap: 1px;
            min-width: 0;
        }

        .track-marker-name {
            color: #0f172a;
            font-size: 11px;
            font-weight: 900;
            max-width: 130px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .track-marker-meta {
            color: #64748b;
            font-size: 9px;
            font-weight: 800;
        }

        .map-floating-badge {
            position: absolute;
            z-index: 500;
            top: 14px;
            left: 14px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 11px;
            border-radius: 999px;
            background: rgba(255,255,255,.94);
            color: #0369a1;
            border: 1px solid #dbeafe;
            box-shadow: 0 8px 22px rgba(15,23,42,.10);
            font-size: .68rem;
            font-weight: 900;
            pointer-events: none;
        }

        .map-load-error {
            height: 100%;
            min-height: 420px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            color: #64748b;
            font-weight: 800;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 14px;
            font-family: var(--font);
            box-shadow: 0 14px 38px rgba(15,23,42,.20);
        }

        .leaflet-popup-content {
            margin: 12px 14px;
            min-width: 210px;
            font-size: 12px;
        }

        .track-popup-title {
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .track-popup-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            font-weight: 700;
        }

        .track-popup-line span:first-child { color: #64748b; }
        .track-popup-line span:last-child { color: #0f172a; text-align: right; }

        .track-popup-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 10px;
            padding: 9px 12px;
            border-radius: 10px;
            background: #0ea5e9;
            color: #fff !important;
            text-decoration: none;
            font-size: 12px;
            font-weight: 900;
        }

        @media(max-width:1100px) {
            .tracking-layout {
                grid-template-columns: 1fr;
            }

            .tracking-map-canvas,
            #trackingMap,
            .tracking-map-canvas .leaflet-container,
            .tracking-list-body {
                height: auto;
                min-height: 430px;
                max-height: none;
            }

            .tracking-list-body {
                min-height: auto;
            }
        }

        @media(max-width:520px) {
            .tracking-map-canvas,
            #trackingMap,
            .tracking-map-canvas .leaflet-container {
                min-height: 380px;
            }

            .track-row {
                grid-template-columns: 78px 1fr;
            }
        }

    </style>
</head>
<body>
    <div class="box">
        <div class="icon">🔒</div>
        <h2>Akses Ditolak</h2>
        <p>Halaman ini hanya dapat diakses oleh administrator sistem.</p>
        <a href="beranda.php">Kembali ke Beranda</a>
    </div>
</body>
</html>';
    exit;
}

require_once 'config.php';
$title = "Log Aktivitas";


/* ============================================================
   LIVE TRACKING PETUGAS - realtime + history ringan
   Data realtime dibaca dari user_lokasi_live (1 baris per user)
   Riwayat disimpan di user_lokasi_history oleh api/simpan_lokasi_tracking.php
   ============================================================ */
date_default_timezone_set('Asia/Jakarta');

$conn->query("CREATE TABLE IF NOT EXISTS user_lokasi_live (
    user_id INT NOT NULL PRIMARY KEY,
    nama_user VARCHAR(150) NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy FLOAT NULL,
    halaman VARCHAR(190) NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS user_lokasi_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nama_user VARCHAR(150) NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy FLOAT NULL,
    halaman VARCHAR(190) NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function al_trackingRows(mysqli $conn): array
{
    $sql = "
        SELECT
            l.user_id AS id,
            l.user_id,
            COALESCE(NULLIF(u.nama, ''), NULLIF(l.nama_user, ''), CONCAT('User #', l.user_id)) AS nama,
            COALESCE(u.role, '') AS role_user,
            l.latitude,
            l.longitude,
            l.accuracy,
            l.halaman,
            l.ip_address,
            l.first_seen,
            l.last_seen AS created_at,
            TIMESTAMPDIFF(SECOND, l.last_seen, NOW()) AS age_second
        FROM user_lokasi_live l
        LEFT JOIN users u ON u.id = l.user_id
        ORDER BY l.last_seen DESC
        LIMIT 200
    ";
    $q = $conn->query($sql);
    if (!$q) return [];
    return $q->fetch_all(MYSQLI_ASSOC);
}

function al_trackingStatus(int $ageSecond): array
{
    if ($ageSecond <= 30) return ['online', 'Online', '🟢'];
    if ($ageSecond <= 120) return ['idle', 'Idle', '🟡'];
    return ['offline', 'Offline', '🔴'];
}

function al_trackingAgeText(int $ageSecond): string
{
    if ($ageSecond < 60) return $ageSecond . ' detik lalu';
    if ($ageSecond < 3600) return floor($ageSecond / 60) . ' menit lalu';
    if ($ageSecond < 86400) return floor($ageSecond / 3600) . ' jam lalu';
    return floor($ageSecond / 86400) . ' hari lalu';
}

function al_trackingSummary(array $rows): array
{
    $online = $idle = $offline = 0;
    foreach ($rows as $r) {
        $age = max(0, (int)($r['age_second'] ?? 999999));
        if ($age <= 30) $online++;
        elseif ($age <= 120) $idle++;
        else $offline++;
    }
    return [
        'total' => count($rows),
        'online' => $online,
        'idle' => $idle,
        'offline' => $offline,
    ];
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'tracking') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $rows = al_trackingRows($conn);
    $summary = al_trackingSummary($rows);
    $out = [];
    foreach ($rows as $r) {
        $age = max(0, (int)($r['age_second'] ?? 999999));
        [$cls, $label, $emoji] = al_trackingStatus($age);
        $out[] = [
            'id' => (int)$r['id'],
            'user_id' => (int)$r['user_id'],
            'nama' => (string)$r['nama'],
            'role' => (string)$r['role_user'],
            'latitude' => (float)$r['latitude'],
            'longitude' => (float)$r['longitude'],
            'accuracy' => $r['accuracy'] !== null ? round((float)$r['accuracy']) : null,
            'halaman' => (string)($r['halaman'] ?? ''),
            'ip_address' => (string)($r['ip_address'] ?? ''),
            'created_at' => (string)$r['created_at'],
            'age_text' => al_trackingAgeText($age),
            'status_class' => $cls,
            'status_label' => $label,
            'status_emoji' => $emoji,
            'maps_url' => 'https://www.google.com/maps?q=' . $r['latitude'] . ',' . $r['longitude'],
        ];
    }
    echo json_encode(['success' => true, 'summary' => $summary, 'rows' => $out, 'server_time' => date('H:i:s')]);
    exit;
}

$activeTab = $_GET['tab'] ?? 'aktivitas';
if (!in_array($activeTab, ['aktivitas', 'tracking'], true)) $activeTab = 'aktivitas';

/* ============================================================
   EXPORT HANDLER
   ============================================================ */
$doExport = $_GET['export'] ?? '';

function buildQuery(mysqli $conn, array $f): array
{
    $where = [];
    $params = [];
    $types = '';
    if (!empty($f['aksi'])) {
        $where[] = 'aksi = ?';
        $params[] = $f['aksi'];
        $types .= 's';
    }
    if (!empty($f['form_type'])) {
        $where[] = 'form_type = ?';
        $params[] = $f['form_type'];
        $types .= 's';
    }
    if (!empty($f['nama'])) {
        $where[] = 'nama_petugas LIKE ?';
        $params[] = '%' . $f['nama'] . '%';
        $types .= 's';
    }
    if (!empty($f['tgl_dari'])) {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $f['tgl_dari'];
        $types .= 's';
    }
    if (!empty($f['tgl_sampai'])) {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $f['tgl_sampai'];
        $types .= 's';
    }
    $sql = "SELECT * FROM activity_log" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY created_at DESC";
    if (!$params) return ['rows' => $conn->query($sql)->fetch_all(MYSQLI_ASSOC)];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return ['rows' => $rows];
}

$filter = [
    'aksi'       => trim($_GET['aksi']       ?? ''),
    'form_type'  => trim($_GET['form_type']  ?? ''),
    'nama'       => trim($_GET['nama']       ?? ''),
    'tgl_dari'   => trim($_GET['tgl_dari']   ?? ''),
    'tgl_sampai' => trim($_GET['tgl_sampai'] ?? ''),
];

if ($doExport === 'excel') {
    $data = buildQuery($conn, $filter)['rows'];
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="log_aktivitas_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    echo implode("\t", ['No', 'Waktu', 'Nama Petugas', 'Role', 'Aksi', 'Form/Modul', 'ID Checklist', 'IP Address', 'Detail']) . "\n";
    foreach ($data as $i => $r) {
        $d = $r['detail'] ? json_decode($r['detail'], true) : [];
        $parts = [];
        foreach (array_diff_key($d, ['_ua' => 1, '_role' => 1]) as $k => $v) {
            if (is_array($v) || is_object($v)) $v = json_encode($v);
            $parts[] = "$k: $v";
        }
        echo implode("\t", [$i + 1, $r['created_at'], $r['nama_petugas'], $d['_role'] ?? '-', strtoupper($r['aksi']), $r['form_type'], $r['checklist_id'] ?: '-', $r['ip_address'], implode('; ', $parts)]) . "\n";
    }
    exit;
}

if ($doExport === 'pdf') {
    $data = buildQuery($conn, $filter)['rows'];
    $bc = ['submit' => '#16a34a', 'edit' => '#d97706', 'delete' => '#dc2626', 'login' => '#2563eb', 'logout' => '#7c3aed', 'view' => '#475569'];
    $rows_html = '';
    foreach ($data as $i => $r) {
        $color = $bc[$r['aksi']] ?? '#475569';
        $d = $r['detail'] ? json_decode($r['detail'], true) : [];
        $parts = [];
        foreach (array_diff_key($d, ['_ua' => 1, '_role' => 1]) as $k => $v) {
            if (is_array($v) || is_object($v)) $v = json_encode($v);
            $parts[] = '<b>' . htmlspecialchars($k) . ':</b> ' . htmlspecialchars((string)$v);
        }
        $rows_html .= "<tr>
            <td>" . ($i + 1) . "</td>
            <td>" . htmlspecialchars($r['created_at']) . "</td>
            <td><b>" . htmlspecialchars($r['nama_petugas']) . "</b><br><small>" . htmlspecialchars($d['_role'] ?? '-') . "</small></td>
            <td><span style='background:$color;color:#fff;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700'>" . strtoupper(htmlspecialchars($r['aksi'])) . "</span></td>
            <td>" . htmlspecialchars(str_replace('_', ' ', $r['form_type'])) . "</td>
            <td>" . ($r['checklist_id'] ?: '—') . "</td>
            <td style='font-family:monospace;font-size:10px'>" . htmlspecialchars($r['ip_address']) . "</td>
            <td style='font-size:10px'>" . implode('<br>', $parts) . "</td>
        </tr>";
    }
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'>
    <title>Log Aktivitas — " . date('d M Y') . "</title>
    <style>body{font-family:'Segoe UI',Arial,sans-serif;font-size:11px;color:#1e293b;margin:0;padding:16px}.hdr{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:12px;border-bottom:2px solid #0ea5e9;margin-bottom:16px}.hdr h1{font-size:15px;font-weight:700;color:#0284c7;margin:0 0 4px}.hdr p,.hdr small{margin:0;font-size:10px;color:#64748b}table{width:100%;border-collapse:collapse}th{background:#0f172a;color:#fff;padding:7px 8px;text-align:left;font-size:10px;font-weight:600;letter-spacing:.04em;text-transform:uppercase}td{padding:6px 8px;border-bottom:1px solid #f1f5f9;vertical-align:top}tr:nth-child(even) td{background:#f8fafc}@media print{@page{margin:.8cm;size:A4 landscape}body{padding:0}}</style>
    </head><body>
    <div class='hdr'><div><h1>📋 Log Aktivitas Pengguna</h1><p>Sistem Checklist — Audit Trail</p></div>
    <div style='text-align:right'><small>Diekspor: " . date('d M Y, H:i:s') . "</small><br><small>Total: <b>" . count($data) . "</b> baris</small></div></div>
    <table><tr><th>No</th><th>Waktu</th><th>Nama Petugas</th><th>Aksi</th><th>Form/Modul</th><th>ID</th><th>IP</th><th>Detail</th></tr>
    $rows_html</table>
    <script>window.onload=()=>window.print();</script></body></html>";
    exit;
}

/* ============================================================
   DATA HALAMAN
   ============================================================ */
$perPage    = 25;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

$whereArr = [];
$pArr = [];
$pTypes = '';
if ($filter['aksi']) {
    $whereArr[] = 'aksi=?';
    $pArr[] = $filter['aksi'];
    $pTypes .= 's';
}
if ($filter['form_type']) {
    $whereArr[] = 'form_type=?';
    $pArr[] = $filter['form_type'];
    $pTypes .= 's';
}
if ($filter['nama']) {
    $whereArr[] = 'nama_petugas LIKE ?';
    $pArr[] = '%' . $filter['nama'] . '%';
    $pTypes .= 's';
}
if ($filter['tgl_dari']) {
    $whereArr[] = 'DATE(created_at)>=?';
    $pArr[] = $filter['tgl_dari'];
    $pTypes .= 's';
}
if ($filter['tgl_sampai']) {
    $whereArr[] = 'DATE(created_at)<=?';
    $pArr[] = $filter['tgl_sampai'];
    $pTypes .= 's';
}

$whereSQL = $whereArr ? 'WHERE ' . implode(' AND ', $whereArr) : '';

$countSQL = "SELECT COUNT(*) AS jml FROM activity_log $whereSQL";
if ($pArr) {
    $sc = $conn->prepare($countSQL);
    $sc->bind_param($pTypes, ...$pArr);
    $sc->execute();
    $totalRows = (int)$sc->get_result()->fetch_assoc()['jml'];
    $sc->close();
} else {
    $totalRows = (int)$conn->query($countSQL)->fetch_assoc()['jml'];
}
$totalPages = (int)ceil($totalRows / $perPage);
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

$dataSQL = "SELECT * FROM activity_log $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
if ($pArr) {
    $sd = $conn->prepare($dataSQL);
    $sd->bind_param($pTypes, ...$pArr);
    $sd->execute();
    $logs = $sd->get_result()->fetch_all(MYSQLI_ASSOC);
    $sd->close();
} else {
    $logs = $conn->query($dataSQL)->fetch_all(MYSQLI_ASSOC);
}

$aksiList     = $conn->query("SELECT DISTINCT aksi FROM activity_log ORDER BY aksi")->fetch_all(MYSQLI_ASSOC);
$formTypeList = $conn->query("SELECT DISTINCT form_type FROM activity_log WHERE form_type != '' ORDER BY form_type")->fetch_all(MYSQLI_ASSOC);

$totalAll    = (int)$conn->query("SELECT COUNT(*) AS c FROM activity_log")->fetch_assoc()['c'];
$totalSubmit = (int)$conn->query("SELECT COUNT(*) AS c FROM activity_log WHERE aksi='submit'")->fetch_assoc()['c'];
$totalEdit   = (int)$conn->query("SELECT COUNT(*) AS c FROM activity_log WHERE aksi='edit'")->fetch_assoc()['c'];
$totalDelete = (int)$conn->query("SELECT COUNT(*) AS c FROM activity_log WHERE aksi='delete'")->fetch_assoc()['c'];
$todayCount  = (int)$conn->query("SELECT COUNT(*) AS c FROM activity_log WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];

$qBase = http_build_query(array_filter($filter));
$qBase = $qBase ? '&' . $qBase : '';

include 'header.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/leaflet/leaflet.css">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-d: #0284c7;
            --primary-xl: #e0f2fe;
            --ink: #0f172a;
            --ink-2: #1e293b;
            --muted: #64748b;
            --muted-l: #94a3b8;
            --bg: #f8fafc;
            --white: #ffffff;
            --border: #e2e8f0;
            --border-l: #f1f5f9;
            --font: 'Plus Jakarta Sans', sans-serif;
            --radius: 12px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .06), 0 1px 2px rgba(0, 0, 0, .04);
            --shadow: 0 4px 16px rgba(0, 0, 0, .07);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 14px;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ══════════════════════════════════════════
           TOPBAR
        ══════════════════════════════════════════ */
        .al-topbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            height: 56px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px;
            box-shadow: var(--shadow-sm);
        }

        .al-back {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid var(--border);
            color: var(--muted);
            text-decoration: none;
            font-size: 12px;
            transition: all .15s;
            flex-shrink: 0;
        }

        .al-back:hover {
            background: var(--primary-xl);
            border-color: var(--primary);
            color: var(--primary-d);
        }

        .al-title {
            font-size: .9rem;
            font-weight: 800;
            color: var(--ink);
        }

        .al-subtitle {
            font-size: .7rem;
            color: var(--muted);
            margin-top: 1px;
        }

        .al-topbar-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .live-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 99px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            font-size: .68rem;
            font-weight: 700;
            color: #15803d;
        }

        .live-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22c55e;
            animation: blink 1.6s ease-in-out infinite;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .3
            }
        }

        .al-date {
            font-size: .72rem;
            color: var(--muted);
            white-space: nowrap;
        }

        /* ══════════════════════════════════════════
           PAGE LAYOUT
        ══════════════════════════════════════════ */
        .al-page {
            padding: 20px 24px 60px;
        }

        @media(max-width:768px) {
            .al-page {
                padding: 14px 14px 60px;
            }
        }

        /* ══════════════════════════════════════════
           STAT CARDS
        ══════════════════════════════════════════ */
        .al-stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        @media(max-width:900px) {
            .al-stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media(max-width:520px) {
            .al-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .stat-num {
            font-size: 1.35rem;
            font-weight: 900;
            line-height: 1;
        }

        .stat-lbl {
            font-size: .65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            margin-top: 3px;
        }

        .s-blue .stat-icon {
            background: #eff6ff;
            color: #2563eb;
        }

        .s-blue .stat-num {
            color: #1d4ed8;
        }

        .s-green .stat-icon {
            background: #f0fdf4;
            color: #16a34a;
        }

        .s-green .stat-num {
            color: #15803d;
        }

        .s-amber .stat-icon {
            background: #fffbeb;
            color: #d97706;
        }

        .s-amber .stat-num {
            color: #b45309;
        }

        .s-red .stat-icon {
            background: #fef2f2;
            color: #dc2626;
        }

        .s-red .stat-num {
            color: #b91c1c;
        }

        .s-purple .stat-icon {
            background: #f5f3ff;
            color: #7c3aed;
        }

        .s-purple .stat-num {
            color: #6d28d9;
        }

        /* ══════════════════════════════════════════
           FILTER CARD
        ══════════════════════════════════════════ */
        .filter-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 14px;
            overflow: hidden;
        }

        .filter-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            cursor: pointer;
            user-select: none;
            font-size: .82rem;
            font-weight: 700;
            color: var(--ink-2);
        }

        .filter-header:hover {
            background: var(--bg);
        }

        .filter-header i.fa-sliders {
            color: var(--primary);
            font-size: 12px;
        }

        .filter-badge {
            background: var(--primary);
            color: #fff;
            font-size: .6rem;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: 99px;
        }

        .filter-chevron {
            margin-left: auto;
            color: var(--muted-l);
            font-size: 10px;
            transition: transform .2s;
        }

        .filter-chevron.open {
            transform: rotate(180deg);
        }

        .filter-body {
            padding: 0 16px 14px;
            border-top: 1px solid var(--border-l);
        }

        .filter-body.collapsed {
            display: none;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .field-lbl {
            display: block;
            font-size: .63rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .f-inp,
        .f-sel {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: .8rem;
            font-family: var(--font);
            color: var(--ink);
            background: var(--bg);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            -webkit-appearance: none;
            appearance: none;
        }

        .f-inp:focus,
        .f-sel:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, .1);
        }

        .f-sel {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 9px center;
            background-size: 15px;
            padding-right: 28px;
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        /* ══════════════════════════════════════════
           BUTTONS
        ══════════════════════════════════════════ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: .78rem;
            font-weight: 700;
            font-family: var(--font);
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all .15s;
            white-space: nowrap;
            line-height: 1;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-d);
        }

        .btn-ghost {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            background: var(--bg);
            color: var(--ink);
        }

        .btn-excel {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .btn-excel:hover {
            background: #dcfce7;
        }

        .btn-pdf {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .btn-pdf:hover {
            background: #fee2e2;
        }

        .btn-sm {
            padding: 7px 13px;
            font-size: .75rem;
        }

        /* ══════════════════════════════════════════
           TOOLBAR
        ══════════════════════════════════════════ */
        .al-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .result-badge {
            margin-left: auto;
            background: var(--primary-xl);
            color: var(--primary-d);
            border: 1px solid #bae6fd;
            border-radius: 99px;
            padding: 5px 14px;
            font-size: .73rem;
            font-weight: 600;
            white-space: nowrap;
        }

        @media(max-width:480px) {
            .result-badge {
                margin-left: 0;
            }
        }

        /* ══════════════════════════════════════════
           TABLE CARD
        ══════════════════════════════════════════ */
        .table-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Desktop table shown ≥900px */
        .al-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .78rem;
            min-width: 860px;
        }

        .al-table thead tr {
            background: #f8fafc;
            border-bottom: 2px solid var(--border);
        }

        .al-table thead th {
            padding: 11px 14px;
            text-align: left;
            font-size: .63rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
            white-space: nowrap;
        }

        .al-table thead th:first-child {
            padding-left: 18px;
        }

        .al-table tbody tr {
            border-bottom: 1px solid var(--border-l);
            transition: background .1s;
        }

        .al-table tbody tr:last-child {
            border-bottom: none;
        }

        .al-table tbody tr:hover {
            background: #f0f9ff;
        }

        .al-table td {
            padding: 10px 14px;
            vertical-align: middle;
        }

        .al-table td:first-child {
            padding-left: 18px;
        }

        .td-no {
            color: var(--muted-l);
            font-size: .71rem;
            font-variant-numeric: tabular-nums;
            width: 44px;
        }

        .td-date {
            font-size: .76rem;
            font-weight: 600;
            color: var(--ink-2);
            white-space: nowrap;
        }

        .td-time {
            font-size: .67rem;
            color: var(--muted);
            margin-top: 2px;
            font-feature-settings: "tnum";
        }

        .td-name {
            font-weight: 700;
            font-size: .8rem;
            color: var(--ink);
        }

        .role-tag {
            display: inline-block;
            font-size: .62rem;
            font-weight: 600;
            background: var(--border-l);
            color: var(--muted);
            padding: 1px 6px;
            border-radius: 99px;
            margin-top: 3px;
        }

        .td-ip {
            font-size: .7rem;
            color: var(--muted-l);
            font-feature-settings: "tnum";
            white-space: nowrap;
        }

        .modul-tag {
            display: inline-block;
            font-size: .7rem;
            font-weight: 700;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 3px 9px;
            border-radius: 6px;
            border: 1px solid #bfdbfe;
            white-space: nowrap;
        }

        .td-id {
            font-size: .71rem;
            color: var(--muted);
            font-feature-settings: "tnum";
        }

        .td-dash {
            color: #d1d5db;
        }

        .excerpt {
            font-size: .69rem;
            color: var(--muted);
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── Aksi Badges ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 99px;
            font-size: .63rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .badge i {
            font-size: 8px;
        }

        .b-submit {
            background: #dcfce7;
            color: #14532d;
            border-color: #a7f3d0;
        }

        .b-edit {
            background: #fef3c7;
            color: #78350f;
            border-color: #fde68a;
        }

        .b-delete {
            background: #fee2e2;
            color: #7f1d1d;
            border-color: #fca5a5;
        }

        .b-login {
            background: #dbeafe;
            color: #1e3a8a;
            border-color: #93c5fd;
        }

        .b-logout {
            background: #ede9fe;
            color: #3b0764;
            border-color: #c4b5fd;
        }

        .b-view {
            background: #f1f5f9;
            color: #1e293b;
            border-color: #cbd5e1;
        }

        .b-def {
            background: #f1f5f9;
            color: #1e293b;
            border-color: #cbd5e1;
        }

        /* detail btn */
        .detail-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 7px;
            border: 1px solid #bae6fd;
            background: transparent;
            color: var(--primary-d);
            font-size: .71rem;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font);
            transition: all .15s;
        }

        .detail-btn:hover {
            background: var(--primary-xl);
        }

        /* ── Mobile Cards (<900px) ── */
        .card-list {
            display: none;
        }

        @media(max-width:899px) {
            .al-table {
                display: none;
            }

            .card-list {
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding: 12px;
            }
        }

        @media(min-width:560px) and (max-width:899px) {
            .card-list {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
        }

        .log-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .card-accent-bar {
            height: 3px;
        }

        .acc-submit {
            background: #16a34a;
        }

        .acc-edit {
            background: #d97706;
        }

        .acc-delete {
            background: #dc2626;
        }

        .acc-login {
            background: #2563eb;
        }

        .acc-logout {
            background: #7c3aed;
        }

        .acc-view {
            background: #475569;
        }

        .acc-def {
            background: #cbd5e1;
        }

        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px 8px;
            border-bottom: 1px solid var(--border-l);
        }

        .card-no {
            font-size: .66rem;
            font-weight: 700;
            color: var(--muted-l);
        }

        .card-time-d {
            font-size: .72rem;
            font-weight: 700;
            color: var(--ink-2);
        }

        .card-time-t {
            font-size: .65rem;
            color: var(--muted);
        }

        .card-body {
            padding: 10px 14px;
        }

        .card-row {
            display: flex;
            gap: 8px;
            margin-bottom: 7px;
            font-size: .76rem;
        }

        .card-row:last-child {
            margin-bottom: 0;
        }

        .card-key {
            width: 68px;
            flex-shrink: 0;
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
            padding-top: 2px;
        }

        .card-val {
            color: var(--ink-2);
            font-weight: 500;
            flex: 1;
        }

        .card-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px 11px;
            gap: 8px;
        }

        .card-ip {
            font-size: .66rem;
            color: var(--muted-l);
        }

        /* ══════════════════════════════════════════
           EMPTY STATE
        ══════════════════════════════════════════ */
        .empty-state {
            text-align: center;
            padding: 52px 20px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 2rem;
            color: #cbd5e1;
            display: block;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: .84rem;
            font-weight: 500;
        }

        /* ══════════════════════════════════════════
           PAGINATION
        ══════════════════════════════════════════ */
        .pag-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .pag-info {
            font-size: .76rem;
            color: var(--muted);
        }

        .pag-info strong {
            color: var(--ink-2);
            font-weight: 700;
        }

        .pag-nav {
            display: flex;
            align-items: center;
            gap: 3px;
            flex-wrap: wrap;
        }

        .pg {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 6px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            font-size: .78rem;
            font-weight: 600;
            color: var(--ink-2);
            text-decoration: none;
            transition: all .12s;
            cursor: pointer;
            font-family: var(--font);
            line-height: 1;
        }

        .pg:hover:not(.pg-on):not(.pg-dots) {
            background: var(--primary-xl);
            border-color: #7dd3fc;
            color: var(--primary-d);
        }

        .pg.pg-on {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            pointer-events: none;
            font-weight: 800;
        }

        .pg.pg-dots {
            border: none;
            background: transparent;
            color: var(--muted-l);
            cursor: default;
            min-width: 20px;
        }

        .pg.pg-off {
            opacity: .35;
            pointer-events: none;
        }

        .pg-arrow {
            font-size: 11px;
        }

        /* ══════════════════════════════════════════
           MODAL
        ══════════════════════════════════════════ */
        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .45);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 900;
            align-items: flex-end;
            justify-content: center;
        }

        @media(min-width:600px) {
            .overlay {
                align-items: center;
                padding: 20px;
            }
        }

        .overlay.open {
            display: flex;
        }

        .modal {
            background: #fff;
            width: 100%;
            max-width: 500px;
            border-radius: 20px 20px 0 0;
            max-height: 88vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 -8px 40px rgba(0, 0, 0, .16);
            animation: slideUp .22s ease;
        }

        @media(min-width:600px) {
            .modal {
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(16px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-drag {
            width: 36px;
            height: 4px;
            background: var(--border);
            border-radius: 99px;
            margin: 10px auto 0;
        }

        @media(min-width:600px) {
            .modal-drag {
                display: none;
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px 12px;
            border-bottom: 1px solid var(--border-l);
            flex-shrink: 0;
        }

        .modal-ico {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--primary-xl);
            color: var(--primary-d);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .modal-title {
            font-size: .9rem;
            font-weight: 800;
            color: var(--ink);
        }

        .modal-sub {
            font-size: .7rem;
            color: var(--muted);
            margin-top: 1px;
        }

        .modal-close {
            margin-left: auto;
            width: 28px;
            height: 28px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: transparent;
            cursor: pointer;
            color: var(--muted);
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .15s;
        }

        .modal-close:hover {
            background: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }

        .modal-body {
            overflow-y: auto;
            padding: 12px 16px 20px;
            flex: 1;
        }

        .drow {
            display: flex;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-l);
            font-size: .79rem;
        }

        .drow:last-child {
            border-bottom: none;
        }

        .dkey {
            min-width: 100px;
            flex-shrink: 0;
            font-weight: 700;
            color: var(--primary-d);
        }

        .dval {
            color: var(--ink);
            word-break: break-word;
            flex: 1;
            line-height: 1.5;
        }

        .dval pre {
            margin: 4px 0 0;
            font-size: .71rem;
            background: var(--bg);
            padding: 8px 10px;
            border-radius: 7px;
            border: 1px solid var(--border);
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            color: var(--ink-2);
        }


        /* ══════════════════════════════════════════
           TABS + LIVE TRACKING
        ══════════════════════════════════════════ */
        .al-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 6px;
            box-shadow: var(--shadow-sm);
            width: fit-content;
            max-width: 100%
        }

        .al-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 14px;
            border-radius: 10px;
            color: var(--muted);
            text-decoration: none;
            font-size: .78rem;
            font-weight: 800;
            white-space: nowrap;
            transition: .15s ease
        }

        .al-tab:hover {
            background: var(--bg);
            color: var(--ink)
        }

        .al-tab.active {
            background: var(--primary);
            color: #fff
        }

        @media(max-width:520px) {
            .al-tabs {
                width: 100%
            }

            .al-tab {
                flex: 1;
                justify-content: center;
                padding: 10px 8px
            }
        }

        .tracking-hero {
            background: linear-gradient(135deg, #0f172a, #0284c7 55%, #0ea5e9);
            color: #fff;
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 14px;
            box-shadow: 0 14px 34px rgba(2, 132, 199, .20);
            position: relative;
            overflow: hidden
        }

        .tracking-hero:after {
            content: "";
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .12);
            right: -50px;
            top: -60px
        }

        .tracking-hero-inner {
            position: relative;
            z-index: 1
        }

        .tracking-hero h2 {
            font-size: 1.05rem;
            font-weight: 900;
            margin-bottom: 4px
        }

        .tracking-hero p {
            font-size: .75rem;
            opacity: .86;
            line-height: 1.55;
            max-width: 760px
        }

        .tracking-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 16px
        }

        .tracking-stat {
            border: 1px solid rgba(255, 255, 255, .20);
            background: rgba(255, 255, 255, .14);
            border-radius: 14px;
            padding: 12px;
            backdrop-filter: blur(8px)
        }

        .tracking-stat span {
            display: block;
            font-size: .66rem;
            font-weight: 800;
            opacity: .82;
            text-transform: uppercase;
            letter-spacing: .04em
        }

        .tracking-stat strong {
            display: block;
            margin-top: 6px;
            font-size: 1.45rem;
            font-weight: 900;
            line-height: 1
        }

        @media(max-width:700px) {
            .tracking-stat-grid {
                grid-template-columns: repeat(2, 1fr)
            }
        }

        .tracking-toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm)
        }

        .tracking-search {
            min-width: 240px;
            flex: 1;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            font-family: var(--font);
            font-size: .82rem;
            outline: none;
            background: var(--bg)
        }

        .tracking-search:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, .10)
        }

        .tracking-last {
            font-size: .74rem;
            color: var(--muted);
            font-weight: 700
        }

        .tracking-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(360px, .85fr);
            gap: 14px;
            align-items: start;
        }

        .tracking-map-panel,
        .tracking-list-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .tracking-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 13px 14px;
            border-bottom: 1px solid var(--border-l);
            background: #fff;
        }

        .tracking-panel-title {
            font-size: .86rem;
            font-weight: 900;
            color: var(--ink);
        }

        .tracking-panel-sub {
            font-size: .68rem;
            color: var(--muted);
            font-weight: 700;
            margin-top: 2px;
        }

        .tracking-map-canvas {
            position: relative;
            min-height: 560px;
            height: 62vh;
            background: #e0f2fe;
            overflow: hidden;
        }

        #trackingMap,
        .tracking-map-canvas .leaflet-container {
            width: 100%;
            height: 100%;
            min-height: 560px;
        }

        .tracking-map-canvas:before {
            display: none !important;
        }

        .tracking-map-canvas .leaflet-control-container {
            font-family: var(--font);
        }

        .map-floating-badge {
            position: absolute;
            z-index: 500;
            top: 14px;
            left: 14px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 11px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .94);
            color: #0369a1;
            border: 1px solid #dbeafe;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .10);
            font-size: .68rem;
            font-weight: 900;
            pointer-events: none;
        }

        .track-leaflet-marker {
            width: auto !important;
            height: auto !important;
        }

        .track-marker-wrap {
            display: flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, .96);
            border: 1px solid #dbeafe;
            border-radius: 999px;
            padding: 5px 9px 5px 5px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .18);
            white-space: nowrap;
        }

        .track-marker-dot {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            flex-shrink: 0;
        }

        .track-marker-wrap.online .track-marker-dot {
            background: #16a34a;
        }

        .track-marker-wrap.idle .track-marker-dot {
            background: #d97706;
        }

        .track-marker-wrap.offline .track-marker-dot {
            background: #dc2626;
        }

        .track-marker-text {
            display: grid;
            gap: 1px;
            min-width: 0;
        }

        .track-marker-name {
            color: #0f172a;
            font-size: 11px;
            font-weight: 900;
            max-width: 135px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .track-marker-meta {
            color: #64748b;
            font-size: 9px;
            font-weight: 800;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 14px;
            font-family: var(--font);
            box-shadow: 0 14px 38px rgba(15, 23, 42, .20);
        }

        .leaflet-popup-content {
            margin: 12px 14px;
            min-width: 210px;
            font-size: 12px;
        }

        .track-popup-title {
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .track-popup-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            font-weight: 700;
        }

        .track-popup-line span:first-child {
            color: #64748b;
        }

        .track-popup-line span:last-child {
            color: #0f172a;
            text-align: right;
        }

        .track-popup-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 10px;
            padding: 9px 12px;
            border-radius: 10px;
            background: #0ea5e9;
            color: #fff !important;
            text-decoration: none;
            font-size: 12px;
            font-weight: 900;
        }

        .map-load-error {
            height: 100%;
            min-height: 420px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            color: #64748b;
            font-weight: 800;
        }

        .map-center-badge {
            position: absolute;
            left: 18px;
            top: 18px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 11px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .90);
            border: 1px solid #dbeafe;
            color: #0369a1;
            font-size: .7rem;
            font-weight: 900;
            box-shadow: var(--shadow-sm);
            z-index: 2;
        }

        .map-marker {
            position: absolute;
            min-width: 148px;
            max-width: 210px;
            transform: translate(-50%, -50%);
            z-index: 3;
        }

        .map-marker-pin {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, .94);
            border: 1px solid #dbeafe;
            border-radius: 16px;
            padding: 8px 10px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .14);
            backdrop-filter: blur(10px);
        }

        .map-dot {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .82rem;
            font-weight: 900;
            flex-shrink: 0;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .18);
        }

        .map-marker.online .map-dot {
            background: #16a34a;
        }

        .map-marker.idle .map-dot {
            background: #d97706;
        }

        .map-marker.offline .map-dot {
            background: #dc2626;
        }

        .map-marker-name {
            display: block;
            font-size: .72rem;
            font-weight: 900;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .map-marker-meta {
            display: block;
            font-size: .62rem;
            font-weight: 800;
            color: var(--muted);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tracking-list-body {
            max-height: 640px;
            overflow-y: auto;
            padding: 12px;
            display: grid;
            gap: 10px;
        }

        .tracking-map-empty {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--muted);
            font-size: .86rem;
            font-weight: 800;
            padding: 20px;
        }

        .tracking-map-empty i {
            display: block;
            font-size: 2rem;
            color: #cbd5e1;
            margin-bottom: 10px;
        }

        @media(max-width:1100px) {
            .tracking-layout {
                grid-template-columns: 1fr;
            }

            .tracking-map-canvas,
            #trackingMap {
                min-height: 430px;
                height: 55vh;
            }

            .tracking-list-body {
                max-height: none;
            }
        }

        @media(max-width:520px) {

            .tracking-map-canvas,
            #trackingMap {
                min-height: 380px;
                height: 52vh;
            }

            .map-marker {
                min-width: 128px;
                max-width: 160px;
            }

            .map-marker-pin {
                padding: 7px 8px;
            }

            .map-dot {
                width: 30px;
                height: 30px;
                font-size: .72rem;
            }
        }

        .track-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 14px;
            box-shadow: var(--shadow-sm);
            transition: .15s ease
        }

        .track-card:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow)
        }

        .track-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px
        }

        .track-name {
            font-size: .92rem;
            font-weight: 900;
            color: var(--ink);
            line-height: 1.25
        }

        .track-role {
            display: inline-flex;
            margin-top: 5px;
            font-size: .62rem;
            font-weight: 800;
            color: var(--muted);
            background: var(--bg);
            border: 1px solid var(--border-l);
            padding: 2px 7px;
            border-radius: 99px
        }

        .track-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 6px 9px;
            font-size: .68rem;
            font-weight: 900;
            white-space: nowrap
        }

        .track-status.online {
            background: #dcfce7;
            color: #166534
        }

        .track-status.idle {
            background: #fef3c7;
            color: #92400e
        }

        .track-status.offline {
            background: #fee2e2;
            color: #991b1b
        }

        .track-info {
            display: grid;
            gap: 8px;
            margin-bottom: 12px
        }

        .track-row {
            display: grid;
            grid-template-columns: 96px 1fr;
            gap: 8px;
            font-size: .74rem
        }

        .track-key {
            color: var(--muted);
            font-weight: 800
        }

        .track-val {
            color: var(--ink-2);
            font-weight: 700;
            word-break: break-word
        }

        .track-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap
        }

        .track-actions a {
            flex: 1;
            justify-content: center
        }

        .track-empty {
            background: #fff;
            border: 1px dashed var(--border);
            border-radius: 16px;
            padding: 44px 16px;
            text-align: center;
            color: var(--muted);
            font-size: .86rem;
            font-weight: 700
        }

        .track-empty i {
            display: block;
            font-size: 2rem;
            color: #cbd5e1;
            margin-bottom: 10px
        }

        /* ===== FINAL SUPER APP MAP ONLY OVERRIDE ===== */
        .tracking-toolbar {
            display: none !important;
        }

        .tracking-layout {
            display: block !important;
        }

        .tracking-list-panel {
            display: none !important;
        }

        .tracking-map-panel {
            width: 100% !important;
            border-radius: 22px !important;
            overflow: hidden !important;
            border: 1px solid var(--border) !important;
            box-shadow: 0 10px 34px rgba(15, 23, 42, .10) !important;
            background: #fff !important;
        }

        .tracking-panel-head {
            min-height: 68px !important;
            padding: 14px 16px !important;
            background: linear-gradient(180deg, #ffffff, #f8fafc) !important;
            border-bottom: 1px solid var(--border-l) !important;
        }

        .tracking-panel-title {
            font-size: .95rem !important;
            font-weight: 900 !important;
            color: var(--ink) !important;
        }

        .tracking-panel-sub {
            font-size: .70rem !important;
            color: var(--muted) !important;
            font-weight: 700 !important;
            margin-top: 3px !important;
        }

        .tracking-map-canvas {
            position: relative !important;
            width: 100% !important;
            height: calc(100vh - 270px) !important;
            min-height: 640px !important;
            background: #e0f2fe !important;
            overflow: hidden !important;
            border-radius: 0 0 22px 22px !important;
        }

        #trackingMap,
        .tracking-map-canvas .leaflet-container {
            width: 100% !important;
            height: 100% !important;
            min-height: 640px !important;
        }

        .tracking-map-canvas:before,
        .tracking-map-canvas:after {
            display: none !important;
        }

        .track-leaflet-marker {
            width: auto !important;
            height: auto !important;
        }

        .track-marker-wrap {
            display: flex !important;
            align-items: center !important;
            gap: 7px !important;
            background: rgba(255, 255, 255, .96) !important;
            border: 1px solid #dbeafe !important;
            border-radius: 999px !important;
            padding: 5px 10px 5px 5px !important;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .22) !important;
            white-space: nowrap !important;
            transform: none !important;
        }

        .track-marker-dot {
            width: 34px !important;
            height: 34px !important;
            border-radius: 999px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #fff !important;
            font-size: 11px !important;
            font-weight: 900 !important;
            flex-shrink: 0 !important;
        }

        .track-marker-wrap.online .track-marker-dot {
            background: #16a34a !important;
        }

        .track-marker-wrap.idle .track-marker-dot {
            background: #d97706 !important;
        }

        .track-marker-wrap.offline .track-marker-dot {
            background: #dc2626 !important;
        }

        .track-marker-name {
            display: block !important;
            color: #0f172a !important;
            font-size: 11px !important;
            font-weight: 900 !important;
            max-width: 145px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        .track-marker-meta {
            display: block !important;
            color: #64748b !important;
            font-size: 9px !important;
            font-weight: 800 !important;
        }

        .map-floating-badge {
            position: absolute !important;
            z-index: 500 !important;
            top: 14px !important;
            left: 14px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 7px !important;
            padding: 9px 12px !important;
            border-radius: 999px !important;
            background: rgba(255, 255, 255, .95) !important;
            color: #0369a1 !important;
            border: 1px solid #dbeafe !important;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .10) !important;
            font-size: 11px !important;
            font-weight: 900 !important;
            pointer-events: none !important;
        }

        .map-floating-actions-final {
            position: absolute;
            z-index: 501;
            right: 14px;
            bottom: 14px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .map-final-btn {
            border: 1px solid #dbeafe;
            background: rgba(255, 255, 255, .96);
            color: #0369a1;
            border-radius: 999px;
            padding: 9px 12px;
            font-family: var(--font);
            font-size: .70rem;
            font-weight: 900;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .12);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .map-final-btn:hover {
            background: #eff6ff;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 18px !important;
            font-family: var(--font) !important;
            box-shadow: 0 18px 48px rgba(15, 23, 42, .24) !important;
        }

        .leaflet-popup-content {
            margin: 14px 16px !important;
            min-width: 230px !important;
            font-size: 12px !important;
        }

        .track-popup-title {
            font-size: 14px !important;
            font-weight: 900 !important;
            color: #0f172a !important;
            margin-bottom: 9px !important;
        }

        .track-popup-line {
            display: flex !important;
            justify-content: space-between !important;
            gap: 12px !important;
            padding: 5px 0 !important;
            border-bottom: 1px solid #f1f5f9 !important;
            color: #475569 !important;
            font-weight: 700 !important;
        }

        .track-popup-line span:first-child {
            color: #64748b !important;
        }

        .track-popup-line span:last-child {
            color: #0f172a !important;
            text-align: right !important;
        }

        .track-popup-link {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            margin-top: 11px !important;
            padding: 10px 12px !important;
            border-radius: 12px !important;
            background: #0ea5e9 !important;
            color: #fff !important;
            text-decoration: none !important;
            font-size: 12px !important;
            font-weight: 900 !important;
        }

        .map-load-error {
            height: 100% !important;
            min-height: 420px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 24px !important;
            text-align: center !important;
            color: #64748b !important;
            font-weight: 800 !important;
        }

        @media(max-width:900px) {

            .tracking-map-canvas,
            #trackingMap,
            .tracking-map-canvas .leaflet-container {
                height: calc(100vh - 250px) !important;
                min-height: 520px !important;
            }
        }

        @media(max-width:520px) {

            .tracking-map-canvas,
            #trackingMap,
            .tracking-map-canvas .leaflet-container {
                height: calc(100vh - 230px) !important;
                min-height: 440px !important;
            }

            .map-floating-actions-final {
                left: 12px;
                right: 12px;
                bottom: 12px;
                justify-content: center;
            }

            .map-final-btn {
                flex: 1;
                justify-content: center;
                padding: 9px 8px;
                font-size: .66rem;
            }

            .track-marker-name {
                max-width: 105px !important;
            }
        }


        /* ===== MAP CLUSTER + STATUS FILTER FINAL ===== */
        .tracking-map-filterbar {
            display: flex;
            gap: 7px;
            flex-wrap: nowrap;
            overflow-x: auto;
            margin: -4px 0 12px;
            padding: 4px;
            width: fit-content;
            max-width: 100%;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 999px;
            box-shadow: var(--shadow-sm);
        }

        .tm-filter {
            border: 0;
            background: transparent;
            color: var(--muted);
            border-radius: 999px;
            padding: 8px 13px;
            font-family: var(--font);
            font-size: .72rem;
            font-weight: 900;
            cursor: pointer;
            white-space: nowrap;
        }

        .tm-filter:hover {
            background: var(--bg);
            color: var(--ink);
        }

        .tm-filter.active {
            background: var(--ink);
            color: #fff;
        }

        .track-cluster-marker {
            width: auto !important;
            height: auto !important;
        }

        .track-cluster-wrap {
            width: 48px;
            height: 48px;
            border-radius: 999px;
            background: #0ea5e9;
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 14px;
            font-weight: 900;
            border: 4px solid rgba(255, 255, 255, .94);
            box-shadow: 0 16px 34px rgba(15, 23, 42, .25);
        }

        .track-cluster-wrap.online {
            background: #16a34a;
        }

        .track-cluster-wrap.idle {
            background: #d97706;
        }

        .track-cluster-wrap.offline {
            background: #dc2626;
        }

        .track-cluster-wrap.mixed {
            background: linear-gradient(135deg, #16a34a 0 33%, #d97706 33% 66%, #dc2626 66% 100%);
        }

        .map-cluster-note {
            position: absolute;
            z-index: 501;
            left: 14px;
            bottom: 14px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .95);
            color: #0f172a;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .10);
            font-size: 11px;
            font-weight: 900;
            pointer-events: none;
        }

        @media(max-width:520px) {
            .tracking-map-filterbar {
                width: 100%;
            }

            .tm-filter {
                flex: 1;
                padding-left: 8px;
                padding-right: 8px;
            }

            .map-cluster-note {
                display: none;
            }
        }


        /* =========================================================
           FINAL OVERRIDE - HEADER ADMIN LOG PUTIH FULL + KIRI
           Hanya mengubah header dan jarak halaman.
           Logika, query, tab, tracking, export, dan modal tidak diubah.
        ========================================================= */
        :root {
            --al-hdr-h: 64px;
            --al-bg-soft: #f4f8fc;
            --al-blue: #0284c7;
            --al-blue-soft: #eff8ff;
            --al-header-line: #e5e7eb;
        }

        html,
        body {
            background: var(--al-bg-soft) !important;
        }

        .al-topbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1000 !important;
            width: 100% !important;
            height: var(--al-hdr-h) !important;
            padding: 0 16px !important;
            background: #fff !important;
            border: 0 !important;
            border-bottom: 1px solid var(--al-header-line) !important;
            border-radius: 0 !important;
            box-shadow: 0 2px 10px rgba(15, 23, 42, .045) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 12px !important;
        }

        .al-topbar::before {
            display: none !important;
            content: none !important;
        }

        .al-back {
            width: 40px !important;
            height: 40px !important;
            border-radius: 999px !important;
            background: var(--al-blue-soft) !important;
            border: 0 !important;
            color: var(--al-blue) !important;
            box-shadow: none !important;
            flex: 0 0 40px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .al-back:hover {
            background: #e0f2fe !important;
            color: #0369a1 !important;
            border: 0 !important;
        }

        .al-topbar>div:not(.al-topbar-right) {
            min-width: 0 !important;
            text-align: left !important;
        }

        .al-title {
            margin: 0 !important;
            font-size: 17px !important;
            font-weight: 900 !important;
            color: var(--al-blue) !important;
            line-height: 1.12 !important;
            letter-spacing: -.01em !important;
            text-align: left !important;
        }

        .al-subtitle {
            margin-top: 3px !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            color: #94a3b8 !important;
            line-height: 1.15 !important;
            text-align: left !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            max-width: 100% !important;
        }

        .al-topbar-right {
            margin-left: auto !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            padding-right: 0 !important;
            position: relative !important;
            z-index: 1 !important;
            flex-shrink: 0 !important;
        }

        .live-badge {
            height: 32px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 0 12px !important;
            border-radius: 999px !important;
            background: #f0fdf4 !important;
            border: 1px solid #bbf7d0 !important;
            color: #15803d !important;
            font-size: 11px !important;
            font-weight: 900 !important;
            white-space: nowrap !important;
        }

        .al-date {
            height: 32px !important;
            display: inline-flex !important;
            align-items: center !important;
            padding: 0 12px !important;
            border-radius: 999px !important;
            background: #fff !important;
            border: 1px solid #e2e8f0 !important;
            color: #64748b !important;
            font-size: 11px !important;
            font-weight: 800 !important;
            white-space: nowrap !important;
        }

        .al-page {
            padding: calc(var(--al-hdr-h) + 20px) 20px 80px !important;
            background: var(--al-bg-soft) !important;
            min-height: 100vh !important;
        }

        .al-tabs,
        .filter-card,
        .table-card,
        .tracking-toolbar,
        .tracking-map-panel,
        .tracking-list-panel {
            border-color: #e0f2fe !important;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .035) !important;
        }

        @media (max-width: 900px) {
            :root {
                --al-hdr-h: 64px;
            }

            html,
            body {
                background: #fff !important;
            }

            .al-topbar {
                height: var(--al-hdr-h) !important;
                padding: 0 14px !important;
                background: #fff !important;
                border-bottom: 1px solid #f1f5f9 !important;
                box-shadow: 0 2px 8px rgba(15, 23, 42, .04) !important;
            }

            .al-back {
                width: 38px !important;
                height: 38px !important;
                flex-basis: 38px !important;
                background: #f0f9ff !important;
            }

            .al-title {
                font-size: 16px !important;
                line-height: 1.15 !important;
            }

            .al-subtitle {
                margin-top: 2px !important;
                font-size: 10.5px !important;
                font-weight: 700 !important;
                color: #94a3b8 !important;
            }

            .al-topbar-right {
                padding-right: 0 !important;
            }

            .al-date {
                display: none !important;
            }

            .live-badge {
                height: 30px !important;
                padding: 0 10px !important;
                font-size: 10px !important;
            }

            .al-page {
                padding: calc(var(--al-hdr-h) + 16px) 14px 92px !important;
                background: #fff !important;
            }
        }

        @media (max-width: 420px) {
            .al-topbar {
                padding-left: 12px !important;
                padding-right: 12px !important;
                gap: 10px !important;
            }

            .al-page {
                padding-left: 12px !important;
                padding-right: 12px !important;
            }

            .live-badge {
                display: none !important;
            }
        }
    </style>

    <!-- TOPBAR -->
    <div class="al-topbar">
        <a href="javascript:history.back()" class="al-back"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <div class="al-title">Log Aktivitas</div>
            <div class="al-subtitle">Audit trail dan live tracking pengguna</div>
        </div>
        <div class="al-topbar-right">
            <div class="live-badge"><span class="live-dot"></span> Live</div>
            <span class="al-date"><?= date('d M Y') ?></span>
        </div>
    </div>

    <div class="al-page">

        <!-- TAB NAVIGATION -->
        <div class="al-tabs">
            <a href="admin_log.php" class="al-tab <?= $activeTab === 'aktivitas' ? 'active' : '' ?>">
                <i class="fa-solid fa-list-check"></i> Aktivitas
            </a>
            <a href="admin_log.php?tab=tracking" class="al-tab <?= $activeTab === 'tracking' ? 'active' : '' ?>">
                <i class="fa-solid fa-location-dot"></i> Live Tracking
            </a>
        </div>

        <?php if ($activeTab === 'tracking'):
            $trackingRows = al_trackingRows($conn);
            $trackingSummary = al_trackingSummary($trackingRows);
        ?>
            <section class="tracking-hero">
                <div class="tracking-hero-inner">
                    <h2>Live Tracking Petugas</h2>
                    <p>Monitoring posisi terakhir pengguna yang sedang membuka aplikasi. Data otomatis diperbarui tanpa reload halaman setiap 5 detik.</p>
                    <div class="tracking-stat-grid">
                        <div class="tracking-stat"><span>Total Terpantau</span><strong id="sumTotal"><?= (int)$trackingSummary['total'] ?></strong></div>
                        <div class="tracking-stat"><span>Online</span><strong id="sumOnline"><?= (int)$trackingSummary['online'] ?></strong></div>
                        <div class="tracking-stat"><span>Idle</span><strong id="sumIdle"><?= (int)$trackingSummary['idle'] ?></strong></div>
                        <div class="tracking-stat"><span>Offline</span><strong id="sumOffline"><?= (int)$trackingSummary['offline'] ?></strong></div>
                    </div>
                </div>
            </section>
            <div class="tracking-map-filterbar">
                <button type="button" class="tm-filter active" data-filter="all">Semua</button>
                <button type="button" class="tm-filter" data-filter="online">Online</button>
                <button type="button" class="tm-filter" data-filter="idle">Idle</button>
                <button type="button" class="tm-filter" data-filter="offline">Offline</button>
            </div>
            <div class="tracking-toolbar" style="display:none">
                <input type="text" id="trackingSearch" class="tracking-search" placeholder="Cari nama petugas...">
                <div class="tracking-last">Auto update 5 detik • Update server: <strong id="trackingServerTime"><?= date('H:i:s') ?></strong></div>
            </div>
            <div class="tracking-layout">
                <section class="tracking-map-panel">
                    <div class="tracking-panel-head">
                        <div>
                            <div class="tracking-panel-title">Live Map</div>
                            <div class="tracking-panel-sub">Klik marker pada peta untuk melihat detail petugas</div>
                        </div>
                        <a id="trackingOpenAll" class="btn btn-ghost btn-sm" href="#" target="_blank"><i class="fa-solid fa-map"></i> Google Maps</a>
                    </div>
                    <div id="trackingMap" class="tracking-map-canvas">
                        <div class="map-floating-actions-final">
                            <button type="button" class="map-final-btn" id="btnFitAllMap"><i class="fa-solid fa-crosshairs"></i> Fokus Semua</button>
                            <button type="button" class="map-final-btn" id="btnRefreshMap"><i class="fa-solid fa-rotate"></i> Refresh</button>
                        </div>
                        <div class="map-cluster-note"><i class="fa-solid fa-layer-group"></i> Marker otomatis digabung saat lokasi berdekatan</div>
                    </div>
                </section>
            </div>
            <div id="trackingGrid" style="display:none"></div>
            <script src="assets/leaflet/leaflet.js"></script>
            <script>
                const initialTrackingRows = <?= json_encode(array_map(function ($r) {
                                                $age = max(0, (int)($r['age_second'] ?? 999999));
                                                [$cls, $label, $emoji] = al_trackingStatus($age);
                                                return ['id' => (int)$r['id'], 'user_id' => (int)$r['user_id'], 'nama' => (string)$r['nama'], 'role' => (string)$r['role_user'], 'latitude' => (float)$r['latitude'], 'longitude' => (float)$r['longitude'], 'accuracy' => $r['accuracy'] !== null ? round((float)$r['accuracy']) : null, 'halaman' => (string)($r['halaman'] ?? ''), 'created_at' => (string)$r['created_at'], 'age_text' => al_trackingAgeText($age), 'status_class' => $cls, 'status_label' => $label, 'status_emoji' => $emoji, 'maps_url' => 'https://www.google.com/maps?q=' . $r['latitude'] . ',' . $r['longitude']];
                                            }, $trackingRows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                let trackingRows = initialTrackingRows;
                let activeMapFilter = 'all';
                const trackingGrid = document.getElementById('trackingGrid');
                const trackingSearch = document.getElementById('trackingSearch');

                function htmlEsc(v) {
                    return String(v ?? '').replace(/[&<>'"]/g, function(c) {
                        return {
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            "'": '&#039;',
                            '"': '&quot;'
                        } [c];
                    });
                }

                function initials(name) {
                    const parts = String(name || 'U').trim().split(/\s+/).filter(Boolean);
                    if (!parts.length) return 'U';
                    const first = parts[0].charAt(0);
                    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
                    return (first + last).toUpperCase();
                }

                let liveMap = null;
                let liveMarkers = {};
                let liveLayer = null;
                let lastFitKey = '';

                if (typeof L !== 'undefined') {
                    L.Icon.Default.mergeOptions({
                        iconRetinaUrl: 'assets/leaflet/images/marker-icon-2x.png',
                        iconUrl: 'assets/leaflet/images/marker-icon.png',
                        shadowUrl: 'assets/leaflet/images/marker-shadow.png'
                    });
                }

                function buildMarkerIcon(row) {
                    const statusClass = htmlEsc(row.status_class || 'offline');
                    const label = htmlEsc(row.status_label || '-');
                    const age = htmlEsc(row.age_text || '-');
                    return L.divIcon({
                        className: 'track-leaflet-marker',
                        html: `<div class="track-marker-wrap ${statusClass}">
                            <span class="track-marker-dot">${htmlEsc(initials(row.nama))}</span>
                            <span class="track-marker-text">
                                <span class="track-marker-name">${htmlEsc(row.nama)}</span>
                                <span class="track-marker-meta">${label} • ${age}</span>
                            </span>
                        </div>`,
                        iconSize: [1, 1],
                        iconAnchor: [18, 38],
                        popupAnchor: [0, -34]
                    });
                }

                function buildPopup(row) {
                    const acc = row.accuracy !== null ? `${htmlEsc(row.accuracy)} meter` : '-';
                    return `<div>
                        <div class="track-popup-title">${htmlEsc(row.nama)}</div>
                        <div class="track-popup-line"><span>Status</span><span>${htmlEsc(row.status_label)}</span></div>
                        <div class="track-popup-line"><span>Update</span><span>${htmlEsc(row.age_text)}</span></div>
                        <div class="track-popup-line"><span>Akurasi</span><span>${acc}</span></div>
                        <div class="track-popup-line"><span>Halaman</span><span>${htmlEsc(row.halaman || '-')}</span></div>
                        <a class="track-popup-link" href="${htmlEsc(row.maps_url)}" target="_blank"><i class="fa-solid fa-map-location-dot"></i> Buka Google Maps</a>
                        <a class="track-popup-link" style="background:#0f172a" href="admin_log.php?nama=${encodeURIComponent(row.nama)}"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Log</a>
                    </div>`;
                }

                function initLiveMap(rows) {
                    const mapEl = document.getElementById('trackingMap');
                    if (!mapEl) return false;
                    if (typeof L === 'undefined') {
                        mapEl.innerHTML = '<div class="map-load-error"><div><i class="fa-solid fa-triangle-exclamation"></i><br>Leaflet belum termuat. Pastikan assets/leaflet/leaflet.css, assets/leaflet/leaflet.js, dan assets/leaflet/images/ sudah ada.</div></div>';
                        return false;
                    }
                    if (liveMap) return true;

                    const first = rows.find(r => Number(r.latitude) && Number(r.longitude));
                    const center = first ? [Number(first.latitude), Number(first.longitude)] : [-6.6801333, 106.8920487];
                    liveMap = L.map('trackingMap', {
                        zoomControl: true,
                        attributionControl: true
                    }).setView(center, 16);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(liveMap);

                    liveLayer = L.layerGroup().addTo(liveMap);

                    const badge = document.createElement('div');
                    badge.className = 'map-floating-badge';
                    badge.innerHTML = '<i class="fa-solid fa-satellite-dish"></i> Auto update realtime';
                    mapEl.appendChild(badge);

                    liveMap.on('zoomend', function() {
                        renderTracking();
                    });

                    setTimeout(() => liveMap.invalidateSize(), 250);
                    return true;
                }

                function getFilteredTrackingRows() {
                    return trackingRows.filter(r => activeMapFilter === 'all' || String(r.status_class || '') === activeMapFilter);
                }

                function clusterRows(rows) {
                    if (!liveMap) return rows.map(r => ({
                        type: 'single',
                        rows: [r]
                    }));

                    const zoom = liveMap.getZoom();
                    const radius = zoom >= 18 ? 18 : zoom >= 16 ? 30 : zoom >= 14 ? 46 : 64;
                    const buckets = {};

                    rows.forEach(r => {
                        if (!Number(r.latitude) || !Number(r.longitude)) return;
                        const p = liveMap.latLngToLayerPoint([Number(r.latitude), Number(r.longitude)]);
                        const key = Math.round(p.x / radius) + ':' + Math.round(p.y / radius);
                        if (!buckets[key]) buckets[key] = [];
                        buckets[key].push(r);
                    });

                    return Object.values(buckets).map(group => ({
                        type: group.length > 1 ? 'cluster' : 'single',
                        rows: group
                    }));
                }

                function clusterStatus(rows) {
                    const s = new Set(rows.map(r => String(r.status_class || 'offline')));
                    if (s.size === 1) return [...s][0];
                    return 'mixed';
                }

                function buildClusterIcon(rows) {
                    const cls = clusterStatus(rows);
                    return L.divIcon({
                        className: 'track-cluster-marker',
                        html: `<div class="track-cluster-wrap ${htmlEsc(cls)}">${rows.length}</div>`,
                        iconSize: [48, 48],
                        iconAnchor: [24, 24]
                    });
                }

                function buildClusterPopup(rows) {
                    const list = rows.slice(0, 12).map(r => `
                        <div class="track-popup-line">
                            <span>${htmlEsc(r.status_emoji || '')} ${htmlEsc(r.nama)}</span>
                            <span>${htmlEsc(r.age_text || '-')}</span>
                        </div>`).join('');
                    const more = rows.length > 12 ? `<div style="margin-top:8px;color:#64748b;font-weight:800">+ ${rows.length - 12} petugas lainnya</div>` : '';
                    return `<div>
                        <div class="track-popup-title">${rows.length} Petugas di Area Ini</div>
                        ${list}
                        ${more}
                    </div>`;
                }

                function focusAllTrackingMarkers() {
                    if (!liveMap) return;
                    const validRows = getFilteredTrackingRows().filter(r => Number(r.latitude) && Number(r.longitude));
                    if (!validRows.length) return;
                    const bounds = L.latLngBounds(validRows.map(r => [Number(r.latitude), Number(r.longitude)]));
                    if (validRows.length === 1) {
                        liveMap.setView(bounds.getCenter(), 17);
                    } else {
                        liveMap.fitBounds(bounds, {
                            padding: [70, 70],
                            maxZoom: 17
                        });
                    }
                }

                function renderTrackingMap(rows) {
                    const openAll = document.getElementById('trackingOpenAll');
                    const validRows = rows.filter(r => Number(r.latitude) && Number(r.longitude));
                    if (openAll) openAll.href = validRows.length ? (validRows[0].maps_url || '#') : '#';
                    if (!initLiveMap(validRows)) return;

                    if (!liveLayer) liveLayer = L.layerGroup().addTo(liveMap);
                    liveLayer.clearLayers();
                    liveMarkers = {};

                    const clusters = clusterRows(validRows);

                    clusters.forEach(item => {
                        if (item.type === 'cluster') {
                            const avgLat = item.rows.reduce((s, r) => s + Number(r.latitude), 0) / item.rows.length;
                            const avgLng = item.rows.reduce((s, r) => s + Number(r.longitude), 0) / item.rows.length;
                            L.marker([avgLat, avgLng], {
                                icon: buildClusterIcon(item.rows)
                            }).addTo(liveLayer).bindPopup(buildClusterPopup(item.rows));
                        } else {
                            const r = item.rows[0];
                            const id = String(r.user_id || r.id);
                            const latLng = [Number(r.latitude), Number(r.longitude)];
                            const marker = L.marker(latLng, {
                                icon: buildMarkerIcon(r)
                            }).addTo(liveLayer).bindPopup(buildPopup(r));
                            liveMarkers[id] = marker;
                        }
                    });

                    if (validRows.length) {
                        const fitKey = validRows.map(r => `${r.user_id}:${r.latitude},${r.longitude}`).join('|') + ':' + activeMapFilter;
                        const bounds = L.latLngBounds(validRows.map(r => [Number(r.latitude), Number(r.longitude)]));
                        if (!lastFitKey) {
                            if (validRows.length === 1) {
                                liveMap.setView(bounds.getCenter(), Math.max(liveMap.getZoom(), 16));
                            } else {
                                liveMap.fitBounds(bounds, {
                                    padding: [70, 70],
                                    maxZoom: 17
                                });
                            }
                        }
                        lastFitKey = fitKey;
                    }
                }

                function renderTracking() {
                    renderTrackingMap(getFilteredTrackingRows());
                }
                async function refreshTracking() {
                    try {
                        const res = await fetch('admin_log.php?tab=tracking&ajax=tracking', {
                            cache: 'no-store'
                        });
                        const data = await res.json();
                        if (!data.success) return;
                        trackingRows = data.rows || [];
                        document.getElementById('sumTotal').textContent = data.summary.total || 0;
                        document.getElementById('sumOnline').textContent = data.summary.online || 0;
                        document.getElementById('sumIdle').textContent = data.summary.idle || 0;
                        document.getElementById('sumOffline').textContent = data.summary.offline || 0;
                        document.getElementById('trackingServerTime').textContent = data.server_time || '-';
                        renderTracking();
                    } catch (e) {}
                }
                if (trackingSearch) trackingSearch.addEventListener('input', renderTracking);

                const btnFitAllMap = document.getElementById('btnFitAllMap');
                const btnRefreshMap = document.getElementById('btnRefreshMap');
                if (btnFitAllMap) btnFitAllMap.addEventListener('click', focusAllTrackingMarkers);
                if (btnRefreshMap) btnRefreshMap.addEventListener('click', refreshTracking);

                document.querySelectorAll('.tm-filter').forEach(btn => {
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.tm-filter').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        activeMapFilter = this.getAttribute('data-filter') || 'all';
                        lastFitKey = '';
                        renderTracking();
                    });
                });

                renderTracking();
                refreshTracking();
                setInterval(refreshTracking, 5000);
            </script>
    </div><!-- /.al-page -->
    <?php include 'footer.php';
            exit; ?>
<?php endif; ?>


<!-- STAT CARDS -->
<div class="al-stats">
    <div class="stat-card s-blue">
        <div class="stat-icon"><i class="fa-solid fa-database"></i></div>
        <div>
            <div class="stat-num"><?= number_format($totalAll) ?></div>
            <div class="stat-lbl">Total Log</div>
        </div>
    </div>
    <div class="stat-card s-green">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <div class="stat-num"><?= number_format($totalSubmit) ?></div>
            <div class="stat-lbl">Submit</div>
        </div>
    </div>
    <div class="stat-card s-amber">
        <div class="stat-icon"><i class="fa-solid fa-pen-to-square"></i></div>
        <div>
            <div class="stat-num"><?= number_format($totalEdit) ?></div>
            <div class="stat-lbl">Edit</div>
        </div>
    </div>
    <div class="stat-card s-red">
        <div class="stat-icon"><i class="fa-solid fa-trash-can"></i></div>
        <div>
            <div class="stat-num"><?= number_format($totalDelete) ?></div>
            <div class="stat-lbl">Delete</div>
        </div>
    </div>
    <div class="stat-card s-purple">
        <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
        <div>
            <div class="stat-num"><?= number_format($todayCount) ?></div>
            <div class="stat-lbl">Hari Ini</div>
        </div>
    </div>
</div>

<!-- FILTER CARD -->
<div class="filter-card">
    <div class="filter-header" onclick="toggleFilter()">
        <i class="fa-solid fa-sliders"></i>
        Filter &amp; Pencarian
        <?php if (array_filter($filter)): ?>
            <span class="filter-badge"><?= count(array_filter($filter)) ?> aktif</span>
        <?php endif; ?>
        <i class="fa-solid fa-chevron-down filter-chevron <?= array_filter($filter) ? 'open' : '' ?>" id="fChevron"></i>
    </div>
    <div class="filter-body <?= array_filter($filter) ? '' : 'collapsed' ?>" id="fBody">
        <form method="GET" action="">
            <div class="filter-grid">
                <div>
                    <label class="field-lbl">Nama Petugas</label>
                    <input class="f-inp" type="text" name="nama" placeholder="Cari nama..." value="<?= htmlspecialchars($filter['nama']) ?>">
                </div>
                <div>
                    <label class="field-lbl">Aksi</label>
                    <select class="f-sel" name="aksi">
                        <option value="">Semua Aksi</option>
                        <?php foreach ($aksiList as $a): ?>
                            <option value="<?= htmlspecialchars($a['aksi']) ?>" <?= $filter['aksi'] === $a['aksi'] ? 'selected' : '' ?>>
                                <?= strtoupper(htmlspecialchars($a['aksi'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="field-lbl">Modul / Form</label>
                    <select class="f-sel" name="form_type">
                        <option value="">Semua Modul</option>
                        <?php foreach ($formTypeList as $ft): ?>
                            <option value="<?= htmlspecialchars($ft['form_type']) ?>" <?= $filter['form_type'] === $ft['form_type'] ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', htmlspecialchars($ft['form_type']))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="field-lbl">Dari Tanggal</label>
                    <input class="f-inp" type="date" name="tgl_dari" value="<?= htmlspecialchars($filter['tgl_dari']) ?>">
                </div>
                <div>
                    <label class="field-lbl">Sampai Tanggal</label>
                    <input class="f-inp" type="date" name="tgl_sampai" value="<?= htmlspecialchars($filter['tgl_sampai']) ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Cari</button>
                <a href="admin_log.php" class="btn btn-ghost"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- TOOLBAR -->
<div class="al-toolbar">
    <a href="?export=excel<?= $qBase ?>" class="btn btn-sm btn-excel">
        <i class="fa-solid fa-file-excel"></i> Excel
    </a>
    <a href="?export=pdf<?= $qBase ?>" target="_blank" class="btn btn-sm btn-pdf">
        <i class="fa-solid fa-file-pdf"></i> PDF
    </a>
    <div class="result-badge">
        <i class="fa-solid fa-list-ul" style="font-size:.6rem;margin-right:3px"></i>
        <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $totalRows)) ?>
        dari <strong><?= number_format($totalRows) ?></strong> log
    </div>
</div>

<?php
$badgeMap  = [
    'submit' => ['b-submit', 'fa-circle-check'],
    'edit'   => ['b-edit',  'fa-pen-to-square'],
    'delete' => ['b-delete', 'fa-trash-can'],
    'login'  => ['b-login', 'fa-right-to-bracket'],
    'logout' => ['b-logout', 'fa-right-from-bracket'],
    'view'   => ['b-view',  'fa-eye'],
];
$accentMap = ['submit' => 'acc-submit', 'edit' => 'acc-edit', 'delete' => 'acc-delete', 'login' => 'acc-login', 'logout' => 'acc-logout', 'view' => 'acc-view'];
?>

<!-- TABLE CARD (desktop ≥900px) -->
<div class="table-card">
    <div class="table-wrap">
        <table class="al-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th style="width:100px">Waktu</th>
                    <th style="min-width:150px">Nama Petugas</th>
                    <th style="width:85px">Aksi</th>
                    <th style="min-width:120px">Modul / Form</th>
                    <th style="width:60px">ID</th>
                    <th style="width:118px">IP Address</th>
                    <th style="min-width:170px">Detail</th>
                    <th style="width:68px">Info</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): ?>
                    <?php foreach ($logs as $i => $row):
                        [$bClass, $bIcon] = $badgeMap[$row['aksi']] ?? ['b-def', 'fa-circle'];
                        $detail    = $row['detail'] ? json_decode($row['detail'], true) : [];
                        $clean     = array_diff_key($detail, ['_ua' => 1, '_role' => 1]);
                        $roleLabel = htmlspecialchars($detail['_role'] ?? '');
                        $ts        = strtotime($row['created_at']);
                        $modul     = $row['form_type'] ? ucfirst(str_replace('_', ' ', $row['form_type'])) : '';
                        $excerptParts = [];
                        foreach ($clean as $k => $v) {
                            if (is_array($v)) continue;
                            $excerptParts[] = htmlspecialchars($k) . ': ' . htmlspecialchars((string)$v);
                        }
                        $excerpt = implode(' · ', array_slice($excerptParts, 0, 2));
                    ?>
                        <tr>
                            <td class="td-no"><?= $offset + $i + 1 ?></td>
                            <td>
                                <div class="td-date"><?= date('d M Y', $ts) ?></div>
                                <div class="td-time"><?= date('H:i:s', $ts) ?></div>
                            </td>
                            <td>
                                <div class="td-name"><?= htmlspecialchars($row['nama_petugas']) ?></div>
                                <?php if ($roleLabel): ?><span class="role-tag"><?= $roleLabel ?></span><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $bClass ?>">
                                    <i class="fa-solid <?= $bIcon ?>"></i><?= htmlspecialchars($row['aksi']) ?>
                                </span>
                            </td>
                            <td>
                                <?= $modul ? '<span class="modul-tag">' . htmlspecialchars($modul) . '</span>' : '<span class="td-dash">—</span>' ?>
                            </td>
                            <td class="td-id">
                                <?= $row['checklist_id'] ? htmlspecialchars($row['checklist_id']) : '<span class="td-dash">—</span>' ?>
                            </td>
                            <td class="td-ip"><?= htmlspecialchars($row['ip_address']) ?></td>
                            <td>
                                <?= $excerpt ? '<span class="excerpt" title="' . htmlspecialchars(implode(' · ', $excerptParts)) . '">' . $excerpt . '</span>' : '<span class="td-dash">—</span>' ?>
                            </td>
                            <td>
                                <?php if ($clean): ?>
                                    <button class="detail-btn" onclick='showDetail(<?= htmlspecialchars(json_encode($clean), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($row['nama_petugas']), ENT_QUOTES) ?>)'>
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </button>
                                <?php else: ?>
                                    <span class="td-dash">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fa-solid fa-inbox"></i>
                                <p>Tidak ada log<?= array_filter($filter) ? ' untuk filter ini' : '' ?>.</p>
                                <?php if (array_filter($filter)): ?>
                                    <a href="admin_log.php" class="btn btn-ghost btn-sm" style="margin-top:12px">
                                        <i class="fa-solid fa-rotate-left"></i> Hapus Filter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MOBILE CARDS (<900px) -->
    <div class="card-list">
        <?php if ($logs): ?>
            <?php foreach ($logs as $i => $row):
                [$bClass, $bIcon] = $badgeMap[$row['aksi']] ?? ['b-def', 'fa-circle'];
                $accClass  = $accentMap[$row['aksi']] ?? 'acc-def';
                $detail    = $row['detail'] ? json_decode($row['detail'], true) : [];
                $clean     = array_diff_key($detail, ['_ua' => 1, '_role' => 1]);
                $roleLabel = htmlspecialchars($detail['_role'] ?? '');
                $ts        = strtotime($row['created_at']);
                $modul     = $row['form_type'] ? ucfirst(str_replace('_', ' ', $row['form_type'])) : '';
            ?>
                <div class="log-card">
                    <div class="card-accent-bar <?= $accClass ?>"></div>
                    <div class="card-head">
                        <span class="card-no">#<?= $offset + $i + 1 ?></span>
                        <div style="text-align:right">
                            <div class="card-time-d"><?= date('d M Y', $ts) ?></div>
                            <div class="card-time-t"><?= date('H:i:s', $ts) ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-row">
                            <div class="card-key">Aksi</div>
                            <div class="card-val">
                                <span class="badge <?= $bClass ?>">
                                    <i class="fa-solid <?= $bIcon ?>"></i><?= htmlspecialchars($row['aksi']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-row">
                            <div class="card-key">Petugas</div>
                            <div class="card-val">
                                <strong><?= htmlspecialchars($row['nama_petugas']) ?></strong>
                                <?php if ($roleLabel): ?><span class="role-tag" style="margin-left:4px"><?= $roleLabel ?></span><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($modul): ?>
                            <div class="card-row">
                                <div class="card-key">Modul</div>
                                <div class="card-val"><span class="modul-tag"><?= htmlspecialchars($modul) ?></span></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($row['checklist_id']): ?>
                            <div class="card-row">
                                <div class="card-key">ID</div>
                                <div class="card-val td-id"><?= htmlspecialchars($row['checklist_id']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-foot">
                        <span class="card-ip"><i class="fa-solid fa-network-wired" style="font-size:.6rem;margin-right:3px"></i><?= htmlspecialchars($row['ip_address']) ?></span>
                        <?php if ($clean): ?>
                            <button class="detail-btn" onclick='showDetail(<?= htmlspecialchars(json_encode($clean), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($row['nama_petugas']), ENT_QUOTES) ?>)'>
                                <i class="fa-solid fa-eye"></i> Detail
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="padding:40px 20px">
                <i class="fa-solid fa-inbox"></i>
                <p>Tidak ada log<?= array_filter($filter) ? ' untuk filter ini' : '' ?>.</p>
            </div>
        <?php endif; ?>
    </div>
</div><!-- /.table-card -->

<!-- PAGINATION -->
<?php if ($totalPages > 1):
    $qF = array_filter($filter);
    $rS = max(1, $page - 2);
    $rE = min($totalPages, $page + 2);
    $startRow = ($page - 1) * $perPage + 1;
    $endRow   = min($page * $perPage, $totalRows);
?>
    <div class="pag-wrap">
        <div class="pag-info">
            Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong>
            &nbsp;&middot;&nbsp;
            Baris <strong><?= number_format($startRow) ?>–<?= number_format($endRow) ?></strong>
        </div>
        <nav class="pag-nav" aria-label="Paginasi">
            <!-- Prev -->
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($qF, ['page' => $page - 1])) ?>" class="pg pg-arrow" title="Halaman sebelumnya">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="pg pg-off pg-arrow"><i class="fa-solid fa-chevron-left"></i></span>
            <?php endif; ?>

            <!-- First page + dots -->
            <?php if ($rS > 1): ?>
                <a href="?<?= http_build_query(array_merge($qF, ['page' => 1])) ?>" class="pg">1</a>
                <?php if ($rS > 2): ?><span class="pg pg-dots">…</span><?php endif; ?>
            <?php endif; ?>

            <!-- Page range -->
            <?php for ($p = $rS; $p <= $rE; $p++): ?>
                <a href="?<?= http_build_query(array_merge($qF, ['page' => $p])) ?>"
                    class="pg <?= $p === $page ? 'pg-on' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>

            <!-- Last page + dots -->
            <?php if ($rE < $totalPages): ?>
                <?php if ($rE < $totalPages - 1): ?><span class="pg pg-dots">…</span><?php endif; ?>
                <a href="?<?= http_build_query(array_merge($qF, ['page' => $totalPages])) ?>" class="pg"><?= $totalPages ?></a>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($qF, ['page' => $page + 1])) ?>" class="pg pg-arrow" title="Halaman berikutnya">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="pg pg-off pg-arrow"><i class="fa-solid fa-chevron-right"></i></span>
            <?php endif; ?>
        </nav>
    </div>
<?php endif; ?>

</div><!-- /.al-page -->

<!-- MODAL DETAIL -->
<div id="overlay" class="overlay" role="dialog" aria-modal="true">
    <div class="modal">
        <span class="modal-drag"></span>
        <div class="modal-header">
            <div class="modal-ico"><i class="fa-solid fa-circle-info"></i></div>
            <div>
                <div class="modal-title">Detail Aktivitas</div>
                <div class="modal-sub" id="modalSub"></div>
            </div>
            <button class="modal-close" onclick="closeDetail()" aria-label="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>
    /* filter toggle */
    const fBody = document.getElementById('fBody');
    const fChevron = document.getElementById('fChevron');
    let fOpen = !fBody.classList.contains('collapsed');

    function toggleFilter() {
        fOpen = !fOpen;
        fBody.classList.toggle('collapsed', !fOpen);
        fChevron.classList.toggle('open', fOpen);
    }

    /* modal */
    function showDetail(detail, name) {
        document.getElementById('modalSub').textContent = name || '';
        const body = document.getElementById('modalBody');
        let html = '';
        for (const [k, v] of Object.entries(detail)) {
            const isObj = typeof v === 'object' && v !== null;
            html += `<div class="drow">
                    <div class="dkey">${esc(k)}</div>
                    <div class="dval">${isObj ? `<pre>${esc(JSON.stringify(v,null,2))}</pre>` : esc(String(v))}</div>
                </div>`;
        }
        body.innerHTML = html || '<p style="color:#94a3b8;text-align:center;padding:24px">Tidak ada detail.</p>';
        document.getElementById('overlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDetail() {
        document.getElementById('overlay').classList.remove('open');
        document.body.style.overflow = '';
    }
    document.getElementById('overlay').addEventListener('click', e => {
        if (e.target === document.getElementById('overlay')) closeDetail();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeDetail();
    });

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>

<?php include 'footer.php'; ?>