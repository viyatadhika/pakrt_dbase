<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Jakarta');

$title = 'Riwayat Absensi';
include 'header.php';

function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function bulanIndo(int $m): string
{
    $b = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    return $b[$m] ?? '-';
}

function fmtTanggal(?string $d): string
{
    if (!$d) return '-';
    $ts = strtotime($d);
    if (!$ts) return (string)$d;
    return date('d', $ts) . ' ' . bulanIndo((int)date('n', $ts)) . ' ' . date('Y', $ts);
}

function fmtJam(?string $d): string
{
    if (!$d) return '-';
    $ts = strtotime($d);
    return $ts ? date('H:i', $ts) . ' WIB' : '-';
}

function initials(?string $name): string
{
    $name = trim((string)$name);
    if ($name === '') return 'U';
    $parts = preg_split('/\s+/', preg_replace('/[^a-zA-Z\s]/', '', $name));
    $out = '';
    foreach ((array)$parts as $p) {
        if ($p !== '') $out .= strtoupper(substr($p, 0, 1));
        if (strlen($out) >= 2) break;
    }
    return $out ?: 'U';
}


function fotoPresensiUrl(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') return '';

    $path = str_replace('\\', '/', $path);

    if (preg_match('~^https?://~i', $path) || strpos($path, '/') === 0) {
        return $path;
    }

    $path = ltrim($path, '/');
    $file = basename($path);

    /*
       riwayat_absensi.php berada di folder: pakrt_dbase
       foto berada di folder: wargart/uploads/presensi_lokasi
       Jadi URL relatif yang benar dari pakrt_dbase adalah:
       ../wargart/uploads/presensi_lokasi/nama_file.jpg
    */
    if (strpos($path, '../wargart/uploads/presensi_lokasi/') === 0) {
        return $path;
    }

    if (strpos($path, 'wargart/uploads/presensi_lokasi/') === 0) {
        return '../' . $path;
    }

    if (strpos($path, 'uploads/presensi_lokasi/') === 0) {
        return '../wargart/' . $path;
    }

    if (strpos($path, 'presensi_lokasi/') === 0) {
        return '../wargart/uploads/' . $path;
    }

    return '../wargart/uploads/presensi_lokasi/' . $file;
}

$user = $_SESSION['user'];
$userId = (int)($user['id'] ?? 0);
$role = strtolower((string)($user['role'] ?? ''));
$isAdmin = $role === 'admin' || $role === 'administrator';

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$filterUser = $isAdmin ? (int)($_GET['user_id'] ?? 0) : $userId;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($perPage, [5, 10, 20, 50], true)) $perPage = 10;

if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
if ($tahun < 2020 || $tahun > 2100) $tahun = (int)date('Y');

$start = sprintf('%04d-%02d-01 00:00:00', $tahun, $bulan);
$end = date('Y-m-t 23:59:59', strtotime($start));

$users = [];
if ($isAdmin) {
    $uq = $conn->query("SELECT id, nama FROM users ORDER BY nama ASC");
    if ($uq) {
        while ($u = $uq->fetch_assoc()) $users[] = $u;
    }
}

$where = "WHERE created_at BETWEEN ? AND ?";
$types = 'ss';
$params = [$start, $end];

if ($filterUser > 0) {
    $where .= " AND user_id = ?";
    $types .= 'i';
    $params[] = $filterUser;
} elseif (!$isAdmin) {
    $where .= " AND user_id = ?";
    $types .= 'i';
    $params[] = $userId;
}

$sql = "
    SELECT id, user_id, nama_petugas, jenis_presensi, lokasi_presensi,
           latitude, longitude, accuracy, distance_meter, lokasi_valid,
           foto_presensi, catatan, created_at
    FROM presensi_lokasi_petugas
    $where
    ORDER BY created_at DESC, id DESC
";
$stmt = $conn->prepare($sql);
if (!$stmt) die('Query gagal: ' . h($conn->error));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

$totalMasuk = 0;
$totalPulang = 0;
$totalValid = 0;
foreach ($rows as $r) {
    if (strtolower((string)$r['jenis_presensi']) === 'masuk') $totalMasuk++;
    if (strtolower((string)$r['jenis_presensi']) === 'pulang') $totalPulang++;
    if ((int)$r['lokasi_valid'] === 1) $totalValid++;
}

$totalRows = count($rows);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$pageRows = array_slice($rows, $offset, $perPage);

$queryBase = [
    'bulan' => $bulan,
    'tahun' => $tahun,
    'per_page' => $perPage,
];
if ($isAdmin) $queryBase['user_id'] = $filterUser;

function pageUrl(array $base, int $page): string
{
    $base['page'] = $page;
    return '?' . http_build_query($base);
}

$showFrom = $totalRows > 0 ? $offset + 1 : 0;
$showTo = min($offset + $perPage, $totalRows);

$displayName = (string)($user['nama'] ?? 'Petugas');
if ($isAdmin && $filterUser > 0) {
    foreach ($users as $u) {
        if ((int)$u['id'] === $filterUser) {
            $displayName = (string)$u['nama'];
            break;
        }
    }
} elseif ($isAdmin && $filterUser === 0) {
    $displayName = 'Semua User';
}

$pdfUrl = 'absensi_laporan_bulanan_pdf.php?bulan=' . urlencode((string)$bulan) . '&tahun=' . urlencode((string)$tahun);
if ($isAdmin && $filterUser > 0) $pdfUrl .= '&user_id=' . urlencode((string)$filterUser);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #eef7fd;
        color: #0f172a;
    }

    .asn-page {
        min-height: 100vh;
        padding-bottom: 6rem;
        background:
            radial-gradient(circle at top right, rgba(14, 165, 233, .16), transparent 34%),
            linear-gradient(180deg, #eef7fd 0%, #f8fafc 100%);
    }

    .asn-header {
        position: sticky;
        top: 0;
        left: 0;
        right: 0;
        z-index: 100;
        width: 100%;
        padding: 0;
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 2px 10px rgba(15, 23, 42, .045);
        backdrop-filter: none;
    }

    .asn-header-card {
        width: 100%;
        max-width: 1120px;
        min-height: 66px;
        margin: 0 auto;
        background: #fff;
        border: 0;
        border-radius: 0;
        box-shadow: none;
        padding: 0 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0
    }

    .icon-btn {
        width: 42px;
        height: 42px;
        border: 0;
        border-radius: 999px;
        background: #eff8ff;
        color: #0284c7;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        flex-shrink: 0;
    }

    .icon-btn:hover {
        background: #e0f2fe;
        color: #0369a1
    }

    .header-title {
        font-size: 17px;
        font-weight: 900;
        color: #0284c7;
        line-height: 1.15;
        margin: 0
    }

    .header-sub {
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        line-height: 1.2;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-shrink: 0;
    }

    .wrap {
        max-width: 1120px;
        margin: 0 auto;
        padding: 12px 14px 28px
    }

    .profile-card {
        border-radius: 34px;
        background: linear-gradient(135deg, #0284c7, #0ea5e9);
        color: #fff;
        padding: 22px;
        box-shadow: 0 20px 38px rgba(2, 132, 199, .24);
        position: relative;
        overflow: hidden;
    }

    .profile-card::after {
        content: "";
        position: absolute;
        right: -50px;
        top: -50px;
        width: 150px;
        height: 150px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .13);
    }

    .profile-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        position: relative;
        z-index: 1
    }

    .avatar {
        width: 58px;
        height: 58px;
        border-radius: 22px;
        background: rgba(255, 255, 255, .2);
        border: 1px solid rgba(255, 255, 255, .35);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        font-weight: 900;
        margin-bottom: 14px;
    }

    .profile-name {
        font-size: 20px;
        font-weight: 900;
        line-height: 1.2
    }

    .profile-sub {
        margin-top: 5px;
        font-size: 12px;
        font-weight: 700;
        opacity: .86
    }

    .profile-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
        position: relative;
        z-index: 1
    }

    .btn {
        border: 0;
        border-radius: 20px;
        padding: 12px 14px;
        background: #0284c7;
        color: #fff;
        box-shadow: 0 12px 24px rgba(2, 132, 199, .18);
        font-size: 12px;
        font-weight: 900;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
    }

    .btn.light {
        background: rgba(255, 255, 255, .18);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .28);
        box-shadow: none
    }

    .btn.white {
        background: #fff;
        color: #0284c7;
        box-shadow: 0 12px 24px rgba(15, 23, 42, .08)
    }

    .card {
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 28px;
        padding: 16px;
        margin-top: 14px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .05);
    }

    .filter-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1.2fr .8fr auto;
        gap: 10px;
        align-items: end
    }

    .fg label {
        display: block;
        font-size: 11px;
        font-weight: 900;
        color: #64748b;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: .06em
    }

    .fg select,
    .fg input {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        background: #f8fafc;
        padding: 12px 13px;
        font-size: 13px;
        font-weight: 900;
        color: #0f172a;
        outline: none;
    }

    .fg select:focus,
    .fg input:focus {
        border-color: #38bdf8;
        box-shadow: 0 0 0 4px rgba(14, 165, 233, .12);
        background: #fff
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px
    }

    .stat-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        padding: 14px
    }

    .stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 15px;
        background: #e0f2fe;
        color: #0284c7;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px
    }

    .stat-label {
        font-size: 10px;
        font-weight: 900;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .06em
    }

    .stat-val {
        font-size: 24px;
        font-weight: 900;
        color: #0f172a;
        margin-top: 3px;
        letter-spacing: -.04em
    }

    .table-card {
        padding: 0;
        overflow: hidden
    }

    .table-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 16px;
        border-bottom: 1px solid #dbeafe
    }

    .table-title {
        font-size: 15px;
        font-weight: 900;
        color: #0f172a
    }

    .table-sub {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        margin-top: 2px
    }

    .table-wrap {
        overflow-x: auto
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px
    }

    th {
        background: #f8fafc;
        color: #64748b;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .06em;
        text-align: left;
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap
    }

    td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle
    }

    tr:last-child td {
        border-bottom: none
    }

    .name-cell {
        font-weight: 900;
        color: #0f172a
    }

    .muted {
        color: #64748b;
        font-weight: 700;
        font-size: 12px
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap
    }

    .masuk {
        background: #dcfce7;
        color: #166534
    }

    .pulang {
        background: #dbeafe;
        color: #1d4ed8
    }

    .valid {
        background: #dcfce7;
        color: #166534
    }

    .invalid {
        background: #fee2e2;
        color: #991b1b
    }

    .photo {
        width: 58px;
        height: 44px;
        object-fit: cover;
        border-radius: 14px;
        border: 1px solid #dbeafe;
        background: #f8fafc
    }

    .photo-link {
        display: inline-flex
    }

    .empty {
        text-align: center;
        padding: 48px 14px;
        color: #64748b;
        font-size: 13px;
        font-weight: 800
    }

    .empty i {
        font-size: 28px;
        color: #cbd5e1;
        display: block;
        margin-bottom: 8px
    }

    .mobile-list {
        display: none;
        margin-top: 14px
    }

    .m-card {
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 28px;
        padding: 14px;
        margin-bottom: 10px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .05)
    }

    .m-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start
    }

    .m-name {
        font-weight: 900;
        font-size: 14px
    }

    .m-meta {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        margin-top: 5px
    }

    .m-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-top: 12px
    }

    .m-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 10px
    }

    .m-lbl {
        font-size: 10px;
        font-weight: 900;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .04em
    }

    .m-val {
        font-size: 12px;
        font-weight: 900;
        margin-top: 3px;
        color: #0f172a
    }

    .m-photo {
        margin-top: 12px
    }

    .m-photo img {
        width: 100%;
        max-height: 220px;
        object-fit: cover;
        border-radius: 22px;
        border: 1px solid #dbeafe
    }


    .photo-btn {
        border: 0;
        padding: 0;
        background: transparent;
        cursor: pointer;
        border-radius: 14px;
        display: inline-flex;
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .photo-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 18px rgba(15, 23, 42, .10);
    }

    .photo-error::after {
        content: 'Foto tidak ditemukan';
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 120px;
        height: 44px;
        border-radius: 14px;
        background: #fee2e2;
        color: #991b1b;
        font-size: 10px;
        font-weight: 900;
    }

    .photo-error img {
        display: none;
    }

    .pagination-card {
        margin-top: 14px;
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 24px;
        padding: 12px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .page-info {
        font-size: 12px;
        font-weight: 800;
        color: #64748b;
    }

    .page-info strong {
        color: #0f172a;
    }

    .page-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .page-btn,
    .page-num {
        min-width: 38px;
        height: 38px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #0f172a;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 12px;
        font-weight: 900;
    }

    .page-num.active {
        background: #0284c7;
        color: #fff;
        border-color: #0284c7;
    }

    .page-btn.disabled {
        opacity: .35;
        pointer-events: none;
    }

    .m-card {
        position: relative;
    }

    .m-card::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 28px;
        pointer-events: none;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .7);
    }

    .m-head {
        min-width: 0;
    }

    .m-head>div:first-child {
        min-width: 0;
    }

    .m-name,
    .m-meta,
    .m-val {
        overflow-wrap: anywhere;
    }

    .m-link-btn {
        border: 0;
        background: transparent;
        color: #0284c7;
        padding: 0;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
    }

    .m-photo {
        width: 100%;
        border: 0;
        padding: 0;
        background: transparent;
        display: block;
        cursor: pointer;
        text-align: left;
    }

    .m-photo img {
        display: block;
    }

    .foto-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15, 23, 42, .74);
        backdrop-filter: blur(8px);
    }

    .foto-modal.show {
        display: flex;
    }

    .foto-modal-card {
        width: min(760px, 100%);
        max-height: 90vh;
        background: #fff;
        border-radius: 28px;
        padding: 14px;
        position: relative;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .35);
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .foto-modal-card img {
        width: 100%;
        max-height: 74vh;
        object-fit: contain;
        border-radius: 22px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .foto-close {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 42px;
        height: 42px;
        border: 0;
        border-radius: 999px;
        background: rgba(15, 23, 42, .82);
        color: #fff;
        cursor: pointer;
    }

    .foto-open {
        width: 100%;
        border-radius: 18px;
        padding: 12px;
        background: #eff8ff;
        color: #0284c7;
        text-decoration: none;
        text-align: center;
        font-size: 12px;
        font-weight: 900;
    }

    @media(max-width:880px) {
        .filter-grid {
            grid-template-columns: 1fr 1fr
        }

        .filter-action {
            grid-column: 1/-1
        }

        .filter-action .btn {
            width: 100%
        }

        .stat-grid {
            grid-template-columns: 1fr 1fr
        }

        .profile-row {
            display: block
        }

        .profile-actions {
            margin-top: 16px;
            justify-content: flex-start
        }

        .profile-actions .btn {
            flex: 1
        }

        .table-card {
            display: none
        }

        .mobile-list {
            display: block
        }

        .asn-header-card {
            align-items: flex-start
        }

        .header-actions {
            display: flex;
            gap: 6px
        }

        .header-title {
            font-size: 16px
        }

        .wrap {
            padding-left: 14px;
            padding-right: 14px
        }
    }

    @media(max-width:520px) {
        .filter-grid {
            grid-template-columns: 1fr
        }

        .asn-header {
            padding: 0;
            background: #fff;
        }

        .asn-header-card {
            min-height: 64px;
            border-radius: 0;
            padding: 0 14px;
            gap: 8px
        }

        .header-actions .icon-btn {
            width: 38px;
            height: 38px
        }

        .profile-card {
            border-radius: 28px;
            padding: 18px
        }

        .card {
            border-radius: 24px;
            padding: 14px;
            margin-top: 12px
        }

        .m-card {
            border-radius: 24px;
            padding: 13px;
            margin-bottom: 10px
        }

        .m-head {
            align-items: center
        }

        .m-grid {
            gap: 7px;
            margin-top: 10px
        }

        .m-box {
            border-radius: 16px;
            padding: 9px
        }

        .m-photo img {
            max-height: 190px;
            border-radius: 18px
        }

        .pagination-card {
            display: block;
            border-radius: 22px
        }

        .page-info {
            text-align: center;
            margin-bottom: 10px
        }

        .page-actions {
            justify-content: center
        }

        .stat-grid {
            grid-template-columns: 1fr 1fr
        }

        .profile-actions {
            display: grid;
            grid-template-columns: 1fr
        }

        .profile-actions .btn {
            width: 100%
        }

        .header-sub {
            max-width: 210px
        }

        .m-grid {
            grid-template-columns: 1fr 1fr
        }
    }


    /* =========================================================
       FINAL HEADER FIX - RIWAYAT ABSENSI
       Header putih full, konten kiri, tombol aksi kanan.
       Tidak mengubah query, tabel, foto, filter, pagination.
    ========================================================= */
    html,
    body {
        background: #eef7fd !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        overflow-x: hidden !important;
    }

    .asn-page {
        padding-top: 0 !important;
        background:
            radial-gradient(circle at top right, rgba(14, 165, 233, .14), transparent 34%),
            linear-gradient(180deg, #eef7fd 0%, #f8fafc 100%) !important;
    }

    .asn-header {
        position: sticky !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 1000 !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        background: #ffffff !important;
        border-bottom: 1px solid #e5e7eb !important;
        box-shadow: 0 2px 10px rgba(15, 23, 42, .045) !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
    }

    .asn-header-card {
        width: 100% !important;
        max-width: 1120px !important;
        min-height: 66px !important;
        margin: 0 auto !important;
        padding: 0 18px !important;
        background: #ffffff !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        box-sizing: border-box !important;
    }

    .header-left {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 12px !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
    }

    .header-left .min-w-0 {
        min-width: 0 !important;
        text-align: left !important;
    }

    .header-title {
        margin: 0 !important;
        color: #0284c7 !important;
        font-size: 17px !important;
        line-height: 1.12 !important;
        font-weight: 900 !important;
        letter-spacing: -.01em !important;
        text-align: left !important;
        white-space: nowrap !important;
    }

    .header-sub {
        display: block !important;
        margin-top: 3px !important;
        color: #94a3b8 !important;
        font-size: 11px !important;
        line-height: 1.15 !important;
        font-weight: 700 !important;
        text-align: left !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 100% !important;
    }

    .icon-btn {
        width: 42px !important;
        height: 42px !important;
        min-width: 42px !important;
        flex: 0 0 42px !important;
        border: 0 !important;
        border-radius: 999px !important;
        background: #eff8ff !important;
        color: #0284c7 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-decoration: none !important;
        box-shadow: none !important;
        line-height: 1 !important;
    }

    .icon-btn:hover {
        background: #e0f2fe !important;
        color: #0369a1 !important;
    }

    .icon-btn i {
        line-height: 1 !important;
        margin: 0 !important;
    }

    .header-actions {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: 8px !important;
        flex: 0 0 auto !important;
        min-width: max-content !important;
    }

    .wrap {
        padding-top: 14px !important;
    }

    @media(max-width:880px) {
        .asn-header-card {
            align-items: center !important;
            min-height: 66px !important;
            padding: 0 16px !important;
        }

        .header-actions {
            gap: 6px !important;
        }

        .header-title {
            font-size: 16px !important;
        }
    }

    @media(max-width:520px) {
        .asn-header {
            min-height: 64px !important;
        }

        .asn-header-card {
            min-height: 64px !important;
            padding: 0 14px !important;
            gap: 8px !important;
        }

        .icon-btn,
        .header-actions .icon-btn {
            width: 38px !important;
            height: 38px !important;
            min-width: 38px !important;
            flex-basis: 38px !important;
        }

        .header-left {
            gap: 10px !important;
        }

        .header-title {
            font-size: 16px !important;
        }

        .header-sub {
            max-width: 165px !important;
            font-size: 10.5px !important;
        }

        .header-actions {
            gap: 5px !important;
        }

        .wrap {
            padding-top: 12px !important;
        }
    }

    @media(max-width:380px) {
        .asn-header-card {
            padding: 0 12px !important;
        }

        .header-left {
            gap: 8px !important;
        }

        .header-sub {
            max-width: 140px !important;
        }

        .header-actions {
            gap: 4px !important;
        }
    }
</style>

<div class="asn-page">
    <header class="asn-header">
        <div class="asn-header-card">
            <div class="header-left">
                <button type="button" onclick="window.history.back()" class="icon-btn" title="Kembali">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div class="min-w-0">
                    <h1 class="header-title">Riwayat Absensi</h1>
                    <div class="header-sub">Laporan bulanan + download PDF</div>
                </div>
            </div>
            <div class="header-actions">
                <a class="icon-btn" href="absensi.php" title="Presensi"><i class="fa-solid fa-camera"></i></a>
                <a class="icon-btn" href="<?= h($pdfUrl) ?>" target="_blank" title="Download PDF"><i class="fa-solid fa-file-pdf"></i></a>
            </div>
        </div>
    </header>

    <main class="wrap">
        <section class="profile-card">
            <div class="profile-row">
                <div>
                    <div class="avatar"><?= h(initials($displayName)) ?></div>
                    <div class="profile-name"><?= h($displayName) ?></div>
                    <div class="profile-sub">Riwayat <?= h(bulanIndo($bulan)) ?> <?= h((string)$tahun) ?> • <?= $totalRows ?> data absensi</div>
                </div>
                <div class="profile-actions">
                    <a class="btn light" href="absensi.php"><i class="fa-solid fa-camera"></i> Presensi</a>
                    <a class="btn white" href="<?= h($pdfUrl) ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
                </div>
            </div>
        </section>

        <form method="GET" class="card filter-grid">
            <div class="fg">
                <label>Bulan</label>
                <select name="bulan">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i === $bulan ? 'selected' : '' ?>><?= h(bulanIndo($i)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="fg">
                <label>Tahun</label>
                <input type="number" name="tahun" value="<?= h((string)$tahun) ?>" min="2020" max="2100">
            </div>
            <?php if ($isAdmin): ?>
                <div class="fg">
                    <label>User</label>
                    <select name="user_id">
                        <option value="0">Semua User</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $filterUser ? 'selected' : '' ?>><?= h($u['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="fg">
                <label>Tampil</label>
                <select name="per_page">
                    <?php foreach ([5, 10, 20, 50] as $pp): ?>
                        <option value="<?= $pp ?>" <?= $pp === $perPage ? 'selected' : '' ?>><?= $pp ?> data</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg filter-action">
                <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Tampilkan</button>
            </div>
        </form>

        <section class="card">
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
                    <div class="stat-label">Total Data</div>
                    <div class="stat-val"><?= $totalRows ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-right-to-bracket"></i></div>
                    <div class="stat-label">Masuk</div>
                    <div class="stat-val"><?= $totalMasuk ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
                    <div class="stat-label">Pulang</div>
                    <div class="stat-val"><?= $totalPulang ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-location-dot"></i></div>
                    <div class="stat-label">Lokasi Valid</div>
                    <div class="stat-val"><?= $totalValid ?></div>
                </div>
            </div>
        </section>

        <section class="card table-card">
            <div class="table-head">
                <div>
                    <div class="table-title">Detail Riwayat Absensi</div>
                    <div class="table-sub"><?= h(bulanIndo($bulan)) ?> <?= h((string)$tahun) ?></div>
                </div>
                <span class="badge valid"><i class="fa-solid fa-calendar-check"></i><?= $totalRows ?> data</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <?php if ($isAdmin && $filterUser === 0): ?><th>Nama</th><?php endif; ?>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Jenis</th>
                            <th>Lokasi</th>
                            <th>Jarak</th>
                            <th>Status</th>
                            <th>Foto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$pageRows): ?>
                            <tr>
                                <td colspan="<?= ($isAdmin && $filterUser === 0) ? 9 : 8 ?>">
                                    <div class="empty"><i class="fa-solid fa-inbox"></i>Belum ada data absensi pada bulan ini.</div>
                                </td>
                            </tr>
                            <?php else: foreach ($pageRows as $i => $r): ?>
                                <?php
                                $jenisDb = trim((string)($r['jenis_presensi'] ?? ''));
                                $jenisText = $jenisDb !== '' ? $jenisDb : '-';
                                $lokasiValidRaw = $r['lokasi_valid'] ?? '';
                                $validText = ((string)$lokasiValidRaw === '1' || (int)$lokasiValidRaw === 1) ? 'Valid' : 'Tidak valid';
                                $fotoUrl = fotoPresensiUrl($r['foto_presensi'] ?? '');
                                ?>
                                <tr>
                                    <td class="muted"><?= $offset + $i + 1 ?></td>
                                    <?php if ($isAdmin && $filterUser === 0): ?><td class="name-cell"><?= h($r['nama_petugas']) ?></td><?php endif; ?>
                                    <td class="name-cell"><?= h(fmtTanggal($r['created_at'])) ?></td>
                                    <td class="muted"><?= h(fmtJam($r['created_at'])) ?></td>
                                    <td><strong style="display:inline-block;color:#0f172a;background:#f8fafc;border:1px solid #cbd5e1;padding:6px 10px;border-radius:10px;"><?= h($jenisText) ?></strong></td>
                                    <td class="muted"><?= h($r['lokasi_presensi']) ?></td>
                                    <td class="muted"><?= h((string)round((float)$r['distance_meter'])) ?> m</td>
                                    <td><strong style="display:inline-block;color:#0f172a;background:#f8fafc;border:1px solid #cbd5e1;padding:6px 10px;border-radius:10px;"><?= h($validText) ?></strong></td>
                                    <td><?php if ($fotoUrl !== ''): ?><button type="button" class="photo-btn" onclick="previewFoto('<?= h($fotoUrl) ?>')"><img class="photo" src="<?= h($fotoUrl) ?>" alt="Foto" data-original-foto="<?= h($fotoUrl) ?>" onerror="fotoFallback(this)"></button><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($totalRows > 0): ?>
            <nav class="pagination-card" aria-label="Navigasi halaman">
                <div class="page-info">
                    Menampilkan <strong><?= $showFrom ?></strong>-<strong><?= $showTo ?></strong> dari <strong><?= $totalRows ?></strong> data
                </div>
                <div class="page-actions">
                    <a class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : h(pageUrl($queryBase, $page - 1)) ?>">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($pg = $startPage; $pg <= $endPage; $pg++):
                    ?>
                        <a class="page-num <?= $pg === $page ? 'active' : '' ?>" href="<?= h(pageUrl($queryBase, $pg)) ?>"><?= $pg ?></a>
                    <?php endfor; ?>
                    <a class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : h(pageUrl($queryBase, $page + 1)) ?>">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </nav>
        <?php endif; ?>

        <div class="mobile-list">
            <?php if (!$pageRows): ?>
                <div class="card empty"><i class="fa-solid fa-inbox"></i>Belum ada data absensi pada bulan ini.</div>
                <?php else: foreach ($pageRows as $r): ?>
                    <?php
                    $jenisDb = trim((string)($r['jenis_presensi'] ?? ''));
                    $jenisText = $jenisDb !== '' ? $jenisDb : '-';
                    $lokasiValidRaw = $r['lokasi_valid'] ?? '';
                    $validText = ((string)$lokasiValidRaw === '1' || (int)$lokasiValidRaw === 1) ? 'Valid' : 'Tidak valid';
                    $fotoUrl = fotoPresensiUrl($r['foto_presensi'] ?? '');
                    ?>
                    <div class="m-card">
                        <div class="m-head">
                            <div>
                                <div class="m-name"><?= h($r['nama_petugas']) ?></div>
                                <div class="m-meta"><?= h(fmtTanggal($r['created_at'])) ?> • <?= h(fmtJam($r['created_at'])) ?></div>
                            </div>
                            <strong style="display:inline-block;color:#0f172a;background:#f8fafc;border:1px solid #cbd5e1;padding:6px 10px;border-radius:10px;"><?= h($jenisText) ?></strong>
                        </div>
                        <div class="m-grid">
                            <div class="m-box">
                                <div class="m-lbl">Lokasi</div>
                                <div class="m-val"><?= h($r['lokasi_presensi']) ?></div>
                            </div>
                            <div class="m-box">
                                <div class="m-lbl">Jarak</div>
                                <div class="m-val"><?= h((string)round((float)$r['distance_meter'])) ?> m</div>
                            </div>
                            <div class="m-box">
                                <div class="m-lbl">Status</div>
                                <div class="m-val"><strong style="display:inline-block;color:#0f172a;background:#f8fafc;border:1px solid #cbd5e1;padding:6px 10px;border-radius:10px;"><?= h($validText) ?></strong></div>
                            </div>
                            <div class="m-box">
                                <div class="m-lbl">Foto</div>
                                <div class="m-val"><?php if ($fotoUrl !== ''): ?><button type="button" class="m-link-btn" onclick="previewFoto('<?= h($fotoUrl) ?>')">Lihat Foto</button><?php else: ?>-<?php endif; ?></div>
                            </div>
                        </div>
                        <?php if ($fotoUrl !== ''): ?>
                            <button type="button" class="m-photo" onclick="previewFoto('<?= h($fotoUrl) ?>')"><img src="<?= h($fotoUrl) ?>" alt="Foto Presensi" data-original-foto="<?= h($fotoUrl) ?>" onerror="fotoFallback(this)"></button>
                        <?php endif; ?>
                    </div>
            <?php endforeach;
            endif; ?>
        </div>
    </main>
</div>

<div id="fotoModal" class="foto-modal" onclick="closeFotoPreview()">
    <div class="foto-modal-card" onclick="event.stopPropagation()">
        <button type="button" class="foto-close" onclick="closeFotoPreview()"><i class="fa-solid fa-xmark"></i></button>
        <img id="fotoPreviewImg" src="" alt="Preview Foto">
        <a id="fotoOpenLink" href="#" target="_blank" class="foto-open"><i class="fa-solid fa-up-right-from-square"></i> Buka foto asli</a>
    </div>
</div>

<script>
    function resolveFotoSrc(src) {
        if (!src) return '';
        src = String(src).trim().replace(/\\/g, '/').replace(/^\/+/, '');
        if (/^(https?:)?\/\//i.test(src) || src.charAt(0) === '/') return src;
        if (src.indexOf('../wargart/uploads/presensi_lokasi/') === 0) return src;
        if (src.indexOf('wargart/uploads/presensi_lokasi/') === 0) return '../' + src;
        if (src.indexOf('uploads/presensi_lokasi/') === 0) return '../wargart/' + src;
        if (src.indexOf('presensi_lokasi/') === 0) return '../wargart/uploads/' + src;
        return '../wargart/uploads/presensi_lokasi/' + src.split('/').pop();
    }

    function fotoFallback(img) {
        if (!img) return;
        var original = img.getAttribute('data-original-foto') || img.getAttribute('src') || '';
        original = String(original).replace(/\\/g, '/');
        var file = original.split('/').pop();
        var tries = [
            '../wargart/uploads/presensi_lokasi/' + file,
            '/wargart/uploads/presensi_lokasi/' + file,
            'wargart/uploads/presensi_lokasi/' + file,
            'uploads/presensi_lokasi/' + file
        ];
        var idx = parseInt(img.getAttribute('data-foto-try') || '0', 10);
        if (idx < tries.length) {
            img.setAttribute('data-foto-try', String(idx + 1));
            img.src = tries[idx];
            return;
        }
        var btn = img.closest('button');
        if (btn) btn.classList.add('photo-error');
    }

    function previewFoto(src) {
        src = resolveFotoSrc(src);
        if (!src) return;
        const modal = document.getElementById('fotoModal');
        const img = document.getElementById('fotoPreviewImg');
        const link = document.getElementById('fotoOpenLink');
        img.src = src;
        link.href = src;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeFotoPreview() {
        const modal = document.getElementById('fotoModal');
        const img = document.getElementById('fotoPreviewImg');
        modal.classList.remove('show');
        img.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeFotoPreview();
    });
</script>

<?php include 'footer.php'; ?>