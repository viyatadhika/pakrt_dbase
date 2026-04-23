<?php
require_once __DIR__ . '/config.php';

/* ── 1. Helper functions ── */
function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmtDate($d)
{
    if (!$d) return '-';
    $b = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    $p = explode('-', substr($d, 0, 10));
    return count($p) === 3 ? $p[2] . ' ' . $b[(int)$p[1]] . ' ' . $p[0] : $d;
}

function fmtTime($t)
{
    if (!$t) return '-';
    $t = (string)$t;
    if (strlen($t) >= 16 && strpos($t, ' ') !== false) return substr($t, 11, 5);
    return substr($t, 0, 5);
}

function hitungDayKeMengikutiKegiatan($startDate, $endDate, $currentDate)
{
    $start = new DateTime($startDate);
    $end   = new DateTime($endDate);
    $curr  = new DateTime($currentDate);

    if ($curr < $start) return 0;
    if ($curr > $end) $curr = clone $end;

    $diff = $start->diff($curr);
    return (int)$diff->days + 1;
}

function formatSesiLabel($startDate, $endDate, $selectedDate, $selectedDay)
{
    if ($startDate === $endDate) {
        return fmtDate($selectedDate);
    }
    return 'Day ' . $selectedDay . ' - ' . fmtDate($selectedDate);
}

/* ── 2. Ambil parameter ── */
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Jakarta');

$isAdmin = is_array($_SESSION['user'] ?? null)
    && isset($_SESSION['user']['role'])
    && strtolower((string)$_SESSION['user']['role']) === 'admin';

$bookingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pin       = trim((string)($_GET['pin'] ?? ''));
$tanggal   = trim((string)($_GET['tanggal'] ?? ''));

if ($bookingId <= 0 || (!$isAdmin && $pin === '')) {
    if (isset($_POST['action']) || isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Akses ditolak']);
        exit;
    }
    die('Akses ditolak. PIN wajib diisi.');
}

/* ── 3. Query booking ── */
$sql = $isAdmin
    ? "SELECT b.id, b.pin, b.nama, b.peminjam, b.start_date, b.end_date,
              b.jam_start, b.jam_end, b.jenis_lokasi, b.lokasi_external,
              COALESCE(r.nama_ruang,'') AS ruang,
              COALESCE(r.lokasi,'') AS lokasi_ruang
       FROM booking_ruang_rapat b
       LEFT JOIN ruang_rapat r ON r.id = b.room_id
       WHERE b.id = ? LIMIT 1"
    : "SELECT b.id, b.pin, b.nama, b.peminjam, b.start_date, b.end_date,
              b.jam_start, b.jam_end, b.jenis_lokasi, b.lokasi_external,
              COALESCE(r.nama_ruang,'') AS ruang,
              COALESCE(r.lokasi,'') AS lokasi_ruang
       FROM booking_ruang_rapat b
       LEFT JOIN ruang_rapat r ON r.id = b.room_id
       WHERE b.id = ? AND b.pin = ? LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) die('Query booking gagal: ' . $conn->error);

if ($isAdmin) {
    $stmt->bind_param('i', $bookingId);
} else {
    $stmt->bind_param('is', $bookingId, $pin);
}

$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    if (isset($_POST['action']) || isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Akses ditolak']);
        exit;
    }
    die('Akses ditolak. PIN salah atau booking tidak ditemukan.');
}

if ($isAdmin && empty($pin)) {
    $pin = $booking['pin'];
}

/* ── 4. Sesi aktif ── */
$today = date('Y-m-d');
$selectedDate = $tanggal !== '' ? $tanggal : $today;

if ($selectedDate < $booking['start_date']) {
    $selectedDate = $booking['start_date'];
}

$selectedDay = hitungDayKeMengikutiKegiatan(
    $booking['start_date'],
    $booking['end_date'],
    $selectedDate
);

$isMultiDay = $booking['start_date'] !== $booking['end_date'];
$sesiLabel = formatSesiLabel($booking['start_date'], $booking['end_date'], $selectedDate, $selectedDay);

$days = [];
$startObj = new DateTime($booking['start_date']);
$endObj   = new DateTime($booking['end_date']);
$cursor   = clone $startObj;
$dayNo    = 1;

while ($cursor <= $endObj) {
    $d = $cursor->format('Y-m-d');
    $days[] = [
        'tanggal' => $d,
        'label'   => $isMultiDay ? ('Day ' . $dayNo) : fmtDate($d),
    ];
    $cursor->modify('+1 day');
    $dayNo++;
}

/* ── 5. POST actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'add_manual') {
        $nama = trim($_POST['nama_peserta'] ?? '');
        $unit = trim($_POST['unit_jabatan'] ?? '');
        $ins  = trim($_POST['instansi'] ?? '');

        if ($nama === '') {
            echo json_encode(['status' => false, 'message' => 'Nama wajib diisi']);
            exit;
        }

        $s = $conn->prepare("
            INSERT INTO absensi_rapat
            (booking_id, tanggal_hadir, day_ke, nama_peserta, unit_jabatan, instansi, waktu_hadir)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$s) {
            echo json_encode(['status' => false, 'message' => 'Query gagal: ' . $conn->error]);
            exit;
        }

        $s->bind_param('isisss', $bookingId, $selectedDate, $selectedDay, $nama, $unit, $ins);
        echo $s->execute()
            ? json_encode(['status' => true, 'id' => $conn->insert_id])
            : json_encode(['status' => false, 'message' => $s->error]);
        $s->close();
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => false, 'message' => 'ID tidak valid']);
            exit;
        }

        $s = $conn->prepare("DELETE FROM absensi_rapat WHERE id = ? AND booking_id = ?");
        if (!$s) {
            echo json_encode(['status' => false, 'message' => 'Query gagal: ' . $conn->error]);
            exit;
        }

        $s->bind_param('ii', $id, $bookingId);
        $s->execute();
        echo $s->affected_rows > 0
            ? json_encode(['status' => true])
            : json_encode(['status' => false, 'message' => 'Data tidak ditemukan']);
        $s->close();
        exit;
    }

    if ($action === 'bulk_delete') {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        $ids = array_values(array_filter(array_map('intval', (array)$ids)));

        if (!$ids) {
            echo json_encode(['status' => false, 'message' => 'Tidak ada ID']);
            exit;
        }

        $ph     = implode(',', array_fill(0, count($ids), '?'));
        $types  = str_repeat('i', count($ids)) . 'i';
        $params = array_merge($ids, [$bookingId]);

        $s = $conn->prepare("DELETE FROM absensi_rapat WHERE id IN ($ph) AND booking_id = ?");
        if (!$s) {
            echo json_encode(['status' => false, 'message' => 'Query gagal: ' . $conn->error]);
            exit;
        }

        $s->bind_param($types, ...$params);
        $s->execute();
        echo json_encode(['status' => true, 'deleted' => $s->affected_rows]);
        $s->close();
        exit;
    }

    echo json_encode(['status' => false, 'message' => 'Action tidak dikenal']);
    exit;
}

/* ── 6. Refresh JSON ── */
if (isset($_GET['json']) && $_GET['json'] === '1') {
    $s = $conn->prepare("
        SELECT *
        FROM absensi_rapat
        WHERE booking_id = ? AND tanggal_hadir = ?
        ORDER BY id ASC
    ");
    $s->bind_param('is', $bookingId, $selectedDate);
    $s->execute();
    $fresh = [];
    $rr = $s->get_result();
    while ($row = $rr->fetch_assoc()) $fresh[] = $row;
    $s->close();

    header('Content-Type: application/json');
    echo json_encode([
        'rows' => $fresh,
        'selected_date' => $selectedDate,
        'selected_day' => $selectedDay,
        'sesi_label' => $sesiLabel
    ]);
    exit;
}

/* ── 7. Data awal ── */
$lokasi = $booking['jenis_lokasi'] === 'external'
    ? ($booking['lokasi_external'] ?: '-')
    : trim(($booking['ruang'] ?: '-') . (!empty($booking['lokasi_ruang']) ? ' - ' . $booking['lokasi_ruang'] : ''));

$stmt = $conn->prepare("
    SELECT *
    FROM absensi_rapat
    WHERE booking_id = ? AND tanggal_hadir = ?
    ORDER BY id ASC
");
$stmt->bind_param('is', $bookingId, $selectedDate);
$stmt->execute();
$res  = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) $rows[] = $row;
$stmt->close();

$title = 'Monitoring Absensi - ' . h($booking['nama']);
include 'header.php';

$exportUrl = 'rapat_absensi_export.php?id=' . (int)$bookingId
    . '&pin=' . urlencode($pin)
    . '&tanggal=' . urlencode($selectedDate);
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap');

    *,
    *::before,
    *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0
    }

    :root {
        --blue: #185FA5;
        --blue-lt: #E6F1FB;
        --blue-md: #378ADD;
        --blue-bd: #B5D4F4;
        --green: #3B6D11;
        --green-lt: #EAF3DE;
        --green-bd: #C0DD97;
        --red: #A32D2D;
        --red-lt: #FCEBEB;
        --red-bd: #F7C1C1;
        --ink: #0f172a;
        --muted: #64748b;
        --border: rgba(0, 0, 0, .1);
        --bg: #f1f5f9;
        --white: #fff;
        --radius: 12px;
        --radius-lg: 16px;
    }

    html,
    body {
        overflow-x: hidden;
        height: auto
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: var(--bg);
        color: var(--ink);
        -webkit-font-smoothing: antialiased
    }

    .sticky-hdr {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 200;
        background: var(--white);
        border-bottom: .5px solid var(--border);
        box-shadow: 0 2px 10px rgba(0, 0, 0, .06)
    }

    .hdr-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 11px 14px 9px
    }

    .hdr-titles {
        flex: 1;
        min-width: 0
    }

    .hdr-titles h1 {
        font-size: 15px;
        font-weight: 700;
        color: var(--blue);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .hdr-titles p {
        font-size: 12px;
        color: var(--muted);
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .hdr-actions {
        display: flex;
        gap: 5px;
        flex-shrink: 0
    }

    .icon-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: .5px solid var(--border);
        background: transparent;
        color: var(--blue);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none
    }

    .icon-btn:hover {
        background: var(--blue-lt)
    }

    .bulk-bar {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 7px 14px;
        background: var(--blue-lt);
        border-bottom: .5px solid var(--blue-bd);
        font-size: 13px;
        font-weight: 700;
        color: var(--blue)
    }

    .bulk-bar.show {
        display: flex
    }

    .btn-bulk-del,
    .btn-bulk-cancel {
        padding: 6px 11px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer
    }

    .btn-bulk-del {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: var(--red-lt);
        border: .5px solid var(--red-bd);
        color: var(--red)
    }

    .btn-bulk-cancel {
        background: transparent;
        border: .5px solid var(--blue-bd);
        color: var(--blue)
    }

    .hdr-offset {
        padding-top: 128px
    }

    .day-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 4px 14px 10px;
        scrollbar-width: none
    }

    .day-tabs::-webkit-scrollbar {
        display: none
    }

    .day-tab {
        text-decoration: none;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        border: 1px solid rgba(0, 0, 0, .1);
        background: #fff;
        color: var(--muted)
    }

    .day-tab.active {
        background: var(--blue-lt);
        color: var(--blue);
        border-color: var(--blue-bd)
    }

    .section-card {
        margin: 0 14px 10px;
        background: var(--white);
        border: .5px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden
    }

    .section-head {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 13px 14px;
        border-bottom: .5px solid var(--border)
    }

    .section-icon {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        background: var(--green-lt);
        color: var(--green);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 13px
    }

    .section-title {
        font-size: 14px;
        font-weight: 700
    }

    .section-sub {
        font-size: 11px;
        color: var(--muted);
        margin-top: 1px
    }

    .section-body {
        padding: 14px
    }

    .grid-2 {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px
    }

    @media(min-width:768px) {
        .grid-2 {
            grid-template-columns: 1fr 1fr
        }
    }

    .info-row {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        margin-bottom: 10px
    }

    .info-row:last-child {
        margin-bottom: 0
    }

    .info-ico {
        width: 26px;
        height: 26px;
        border-radius: 6px;
        background: var(--blue-lt);
        color: var(--blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        flex-shrink: 0
    }

    .info-lbl {
        font-size: 10px;
        font-weight: 800;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .06em
    }

    .info-val {
        font-size: 13px;
        font-weight: 700;
        margin-top: 1px
    }

    .toolbar {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 0 14px;
        margin-bottom: 7px
    }

    .search-wrap {
        flex: 1;
        position: relative
    }

    .search-wrap input {
        width: 100%;
        padding: 9px 12px 9px 34px;
        background: var(--white);
        border: .5px solid var(--border);
        border-radius: var(--radius);
        font-size: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--ink);
        outline: none
    }

    .search-wrap input:focus {
        border-color: var(--blue-md);
        box-shadow: 0 0 0 3px rgba(55, 138, 221, .1)
    }

    .search-icon {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 11px;
        color: var(--muted);
        pointer-events: none
    }

    .count-lbl {
        font-size: 12px;
        font-weight: 600;
        color: var(--blue);
        padding: 0 14px;
        margin-bottom: 6px
    }

    .tbl-card {
        margin: 0 14px 10px;
        background: var(--white);
        border: .5px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden
    }

    .tbl-wrap {
        overflow-x: auto
    }

    table.dt {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        table-layout: auto
    }

    table.dt th {
        background: var(--bg);
        padding: 10px 11px;
        text-align: left;
        font-weight: 700;
        font-size: 12px;
        color: var(--muted);
        border-bottom: .5px solid var(--border);
        white-space: nowrap
    }

    table.dt td {
        padding: 11px 11px;
        border-bottom: .5px solid var(--border);
        vertical-align: middle
    }

    table.dt tr:last-child td {
        border-bottom: none
    }

    table.dt tr:hover td {
        background: #fafbfc
    }

    .cb-col {
        width: 36px;
        text-align: center
    }

    input[type=checkbox] {
        width: 15px;
        height: 15px;
        cursor: pointer;
        accent-color: var(--blue)
    }

    .avatar {
        width: 30px;
        height: 30px;
        border-radius: 7px;
        background: var(--blue-lt);
        border: .5px solid var(--blue-bd);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 700;
        color: var(--blue);
        flex-shrink: 0
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap
    }

    .badge-green {
        background: var(--green-lt);
        color: var(--green);
        border: .5px solid var(--green-bd)
    }

    .badge-blue {
        background: var(--blue-lt);
        color: var(--blue);
        border: .5px solid var(--blue-bd)
    }

    .ttd-box,
    .m-ttd {
        background: var(--bg);
        border: .5px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden
    }

    .ttd-box {
        width: 54px;
        height: 34px;
        border-radius: 6px;
        cursor: pointer
    }

    .ttd-box img,
    .m-ttd img {
        width: 100%;
        height: 100%;
        object-fit: contain
    }

    .ttd-box.signed,
    .m-ttd.signed {
        background: var(--green-lt);
        border-color: var(--green-bd)
    }

    .ttd-none {
        opacity: .3
    }

    .act-btn {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        border: none;
        background: transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 13px;
        color: var(--muted)
    }

    .act-btn:hover {
        background: var(--bg)
    }

    .act-btn.del:hover {
        background: var(--red-lt);
        color: var(--red)
    }

    .mobile-list {
        padding: 0 14px 100px
    }

    .m-card {
        background: var(--white);
        border: .5px solid var(--border);
        border-radius: var(--radius-lg);
        margin-bottom: 7px;
        overflow: hidden
    }

    .m-card.selected {
        border-color: var(--blue-bd);
        background: #f0f7ff
    }

    .m-card-top {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 12px 13px 9px
    }

    .m-num {
        width: 34px;
        height: 34px;
        border-radius: 7px;
        background: var(--blue-lt);
        border: .5px solid var(--blue-bd);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex-shrink: 0
    }

    .m-num span:first-child {
        font-size: 8px;
        font-weight: 700;
        color: var(--blue-md);
        text-transform: uppercase
    }

    .m-num span:last-child {
        font-size: 14px;
        font-weight: 800;
        color: var(--blue);
        line-height: 1
    }

    .m-name {
        font-size: 14px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .m-unit {
        font-size: 11px;
        color: var(--muted);
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .m-card-bot {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 9px 13px 12px;
        border-top: .5px solid var(--border);
        cursor: pointer
    }

    .m-ttd {
        width: 56px;
        height: 36px;
        border-radius: 7px;
        flex-shrink: 0
    }

    .m-meta {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 3px
    }

    .m-meta i {
        font-size: 10px;
        opacity: .6;
        width: 10px
    }

    .fab {
        position: fixed;
        bottom: 18px;
        right: 16px;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: var(--blue);
        color: #fff;
        font-size: 20px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 100;
        box-shadow: 0 4px 16px rgba(24, 95, 165, .35)
    }

    .modal-ov {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding: 10px;
        z-index: 300
    }

    .modal-ov.hidden {
        display: none
    }

    .modal-box {
        background: var(--white);
        border-radius: 18px 18px 14px 14px;
        width: 100%;
        max-width: 480px;
        border: .5px solid var(--border);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 92vh
    }

    .modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 13px 16px;
        border-bottom: .5px solid var(--border);
        flex-shrink: 0
    }

    .modal-head h2 {
        font-size: 15px;
        font-weight: 700
    }

    .modal-head p {
        font-size: 11px;
        color: var(--muted);
        margin-top: 1px
    }

    .modal-body {
        padding: 14px 16px;
        overflow-y: auto;
        flex: 1
    }

    .modal-foot {
        padding: 10px 16px;
        border-top: .5px solid var(--border);
        display: flex;
        gap: 7px;
        flex-shrink: 0
    }

    .f-group {
        margin-bottom: 10px
    }

    .f-group:last-child {
        margin-bottom: 0
    }

    .f-lbl {
        font-size: 11px;
        font-weight: 700;
        color: var(--muted);
        margin-bottom: 4px;
        display: block
    }

    .f-input {
        width: 100%;
        padding: 10px 12px;
        background: var(--bg);
        border: .5px solid var(--border);
        border-radius: var(--radius);
        font-size: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--ink);
        outline: none
    }

    .f-input:focus {
        border-color: var(--blue-md);
        background: var(--white);
        box-shadow: 0 0 0 3px rgba(55, 138, 221, .1)
    }

    .detail-sec {
        background: var(--bg);
        border-radius: var(--radius);
        padding: 12px;
        margin-bottom: 10px
    }

    .dt-lbl {
        font-size: 10px;
        font-weight: 800;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: 2px
    }

    .dt-val {
        font-size: 14px;
        font-weight: 700;
        color: var(--ink)
    }

    .btn-primary,
    .btn-danger,
    .btn-cancel {
        padding: 10px;
        border-radius: var(--radius);
        font-size: 13px;
        font-weight: 700;
        cursor: pointer
    }

    .btn-primary {
        flex: 1;
        background: var(--blue-lt);
        border: .5px solid var(--blue-bd);
        color: var(--blue)
    }

    .btn-danger {
        flex: 1;
        background: var(--red-lt);
        border: .5px solid var(--red-bd);
        color: var(--red)
    }

    .btn-cancel {
        padding: 10px 14px;
        background: var(--bg);
        border: .5px solid var(--border);
        color: var(--muted)
    }

    .ttd-lg {
        max-width: 100%;
        max-height: 150px;
        object-fit: contain;
        background: var(--white);
        border-radius: var(--radius);
        border: .5px solid var(--border);
        padding: 6px
    }

    .toast {
        position: fixed;
        bottom: 26px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--ink);
        color: #fff;
        padding: 8px 18px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        opacity: 0;
        transition: opacity .2s;
        pointer-events: none;
        z-index: 999;
        white-space: nowrap
    }

    .toast.show {
        opacity: 1
    }

    .empty-st {
        text-align: center;
        padding: 48px 0;
        color: var(--muted);
        font-size: 14px
    }

    .empty-st i {
        font-size: 28px;
        opacity: .2;
        display: block;
        margin-bottom: 8px
    }

    @keyframes spin {
        to {
            transform: rotate(360deg)
        }
    }

    .spin {
        animation: spin .6s linear infinite
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(5px)
        }

        to {
            opacity: 1;
            transform: translateY(0)
        }
    }

    .au {
        animation: fadeUp .2s ease-out forwards
    }

    @media(min-width:768px) {
        .mobile-list {
            display: none
        }

        .tbl-card {
            display: block
        }

        .hdr-offset {
            padding-top: 128px
        }
    }

    @media(max-width:767px) {
        .tbl-card {
            display: none
        }

        .mobile-list {
            display: block
        }

        .hdr-offset {
            padding-top: 128px
        }
    }

    @media print {
        .no-print {
            display: none !important
        }

        body {
            background: #fff !important
        }

        .sticky-hdr {
            position: static !important;
            box-shadow: none !important
        }

        .hdr-offset {
            padding-top: 0 !important
        }
    }
</style>

<header class="sticky-hdr no-print">
    <div class="hdr-top">
        <div class="hdr-titles">
            <h1>Monitoring Absensi</h1>
            <p><?= h($booking['nama']) ?></p>
        </div>
        <div class="hdr-actions">
            <a href="<?= h($exportUrl) ?>" class="icon-btn" title="Download PDF">
                <i class="fa-solid fa-download"></i>
            </a>
            <button type="button" class="icon-btn" onclick="refreshData()" title="Refresh">
                <i class="fa-solid fa-rotate-right" id="refreshIcon"></i>
            </button>
        </div>
    </div>
    <div class="bulk-bar" id="bulkBar">
        <span id="bulkCount">0 dipilih</span>
        <div style="display:flex;gap:6px">
            <button type="button" class="btn-bulk-cancel" onclick="clearSelection()">Batal</button>
            <button type="button" class="btn-bulk-del" onclick="bulkDelete()">
                <i class="fa-solid fa-trash-can"></i> Hapus Dipilih
            </button>
        </div>
    </div>
</header>

<main class="hdr-offset">

    <?php if (count($days) > 1): ?>
        <div class="day-tabs no-print">
            <?php foreach ($days as $d): ?>
                <a href="absensi.php?id=<?= (int)$bookingId ?>&pin=<?= urlencode($pin) ?>&tanggal=<?= urlencode($d['tanggal']) ?>"
                    class="day-tab <?= $selectedDate === $d['tanggal'] ? 'active' : '' ?>">
                    <?= h($d['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <div>
                <div class="section-title">Informasi Rapat</div>
                <div class="section-sub">Detail kegiatan yang sedang didokumentasikan</div>
            </div>
        </div>
        <div class="section-body">
            <div class="grid-2">
                <div>
                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-file-lines"></i></div>
                        <div>
                            <div class="info-lbl">Nama Kegiatan</div>
                            <div class="info-val"><?= h($booking['nama']) ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-user"></i></div>
                        <div>
                            <div class="info-lbl">Peminjam / Bidang</div>
                            <div class="info-val"><?= h($booking['peminjam'] ?? '-') ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-location-dot"></i></div>
                        <div>
                            <div class="info-lbl">Lokasi</div>
                            <div class="info-val"><?= h($lokasi) ?></div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-calendar"></i></div>
                        <div>
                            <div class="info-lbl">Tanggal Kegiatan</div>
                            <div class="info-val">
                                <?= h(fmtDate($booking['start_date'])) ?>
                                <?php if ($booking['start_date'] !== $booking['end_date']): ?>
                                    — <?= h(fmtDate($booking['end_date'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-clock"></i></div>
                        <div>
                            <div class="info-lbl">Waktu</div>
                            <div class="info-val"><?= h(fmtTime($booking['jam_start'])) ?> – <?= h(fmtTime($booking['jam_end'])) ?> WIB</div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-calendar-day"></i></div>
                        <div>
                            <div class="info-lbl">Sesi Monitoring</div>
                            <div class="info-val"><?= h($sesiLabel) ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-users"></i></div>
                        <div>
                            <div class="info-lbl">Total Hadir</div>
                            <div class="info-val"><span id="infoTotalHadir"><?= count($rows) ?></span> peserta</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toolbar no-print">
        <div class="search-wrap">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" id="qSearch" oninput="filterData()" placeholder="Cari nama, instansi, unit/jabatan...">
        </div>
    </div>

    <div class="count-lbl no-print">
        <span id="dataCount"><?= count($rows) ?> peserta hadir • <?= h($sesiLabel) ?></span>
    </div>

    <div class="tbl-card">
        <div class="tbl-wrap">
            <table class="dt">
                <thead>
                    <tr>
                        <th class="cb-col no-print"><input type="checkbox" id="cbAll" onchange="toggleAll(this)"></th>
                        <th style="width:32px">No</th>
                        <th style="min-width:120px"><?= $isMultiDay ? 'Day' : 'Tanggal' ?></th>
                        <th style="min-width:160px">Nama Peserta</th>
                        <th style="min-width:130px">Unit / Jabatan</th>
                        <th style="min-width:130px">Instansi</th>
                        <th style="width:110px">Waktu Hadir</th>
                        <th style="width:70px">TTD</th>
                        <th style="width:60px" class="no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-st"><i class="fa-solid fa-inbox"></i>Belum ada data absensi untuk sesi ini</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $row): ?>
                            <?php $rowLabel = $isMultiDay ? ('Day ' . (int)($row['day_ke'] ?? $selectedDay)) : fmtDate($row['tanggal_hadir'] ?? $selectedDate); ?>
                            <tr data-id="<?= $row['id'] ?>">
                                <td class="cb-col no-print"><input type="checkbox" class="row-cb" value="<?= $row['id'] ?>" onchange="onCheckChange()"></td>
                                <td style="text-align:center;font-size:12px;color:var(--muted)"><?= $i + 1 ?></td>
                                <td><span class="badge badge-blue"><?= h($rowLabel) ?></span></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <div class="avatar"><?= mb_strtoupper(mb_substr(preg_replace('/[^a-zA-Z\s]/', '', $row['nama_peserta']), 0, 2)) ?></div>
                                        <span style="font-weight:700;font-size:14px"><?= h($row['nama_peserta']) ?></span>
                                    </div>
                                </td>
                                <td style="font-size:13px;color:var(--muted)"><?= h($row['unit_jabatan'] ?: '-') ?></td>
                                <td style="font-size:13px;color:var(--muted)"><?= h($row['instansi'] ?: '-') ?></td>
                                <td>
                                    <span class="badge badge-green">
                                        <i class="fa-solid fa-clock" style="font-size:9px;margin-right:4px;opacity:.8"></i>
                                        <?= fmtTime($row['waktu_hadir'] ?? '') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($row['tanda_tangan'])): ?>
                                        <div class="ttd-box signed" onclick="openDetail(<?= $row['id'] ?>)" title="Klik untuk perbesar">
                                            <img src="<?= h($row['tanda_tangan']) ?>" alt="TTD">
                                        </div>
                                    <?php else: ?>
                                        <div class="ttd-box ttd-none"><i class="fa-solid fa-signature" style="font-size:14px;color:var(--muted)"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print">
                                    <button type="button" class="act-btn" onclick="openDetail(<?= $row['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                                    <button type="button" class="act-btn del" onclick="confirmDelete(<?= $row['id'] ?>)"><i class="fa-solid fa-trash-can"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mobile-list" id="mobileCards">
        <?php if (!$rows): ?>
            <div class="empty-st"><i class="fa-solid fa-inbox"></i>Belum ada data absensi untuk sesi ini</div>
        <?php else: ?>
            <?php foreach ($rows as $i => $row): ?>
                <?php $rowLabel = $isMultiDay ? ('Day ' . (int)($row['day_ke'] ?? $selectedDay)) : fmtDate($row['tanggal_hadir'] ?? $selectedDate); ?>
                <div class="m-card au" style="animation-delay:<?= $i * 0.03 ?>s" data-id="<?= $row['id'] ?>">
                    <div class="m-card-top">
                        <input type="checkbox" class="row-cb-m no-print" value="<?= $row['id'] ?>" style="display:none;margin-right:2px;flex-shrink:0" onchange="onCheckChange()">
                        <div class="m-num"><span>No</span><span><?= $i + 1 ?></span></div>
                        <div style="flex:1;min-width:0">
                            <div class="m-name"><?= h($row['nama_peserta']) ?></div>
                            <div class="m-unit"><?= h($row['unit_jabatan'] ?: '-') ?></div>
                        </div>
                        <span class="badge badge-blue" style="flex-shrink:0"><?= h($rowLabel) ?></span>
                    </div>
                    <div class="m-card-bot" onclick="openDetail(<?= $row['id'] ?>)">
                        <div class="m-ttd <?= !empty($row['tanda_tangan']) ? 'signed' : '' ?>">
                            <?php if (!empty($row['tanda_tangan'])): ?>
                                <img src="<?= h($row['tanda_tangan']) ?>" alt="TTD">
                            <?php else: ?>
                                <i class="fa-solid fa-signature" style="font-size:16px;color:var(--muted);opacity:.3"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div class="m-meta"><i class="fa-solid fa-building"></i><span><?= h($row['instansi'] ?: '-') ?></span></div>
                            <div class="m-meta"><i class="fa-solid fa-clock"></i><span>Hadir: <?= fmtTime($row['waktu_hadir'] ?? '') ?></span></div>
                        </div>
                        <button type="button" class="act-btn del no-print" onclick="event.stopPropagation();confirmDelete(<?= $row['id'] ?>)">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<button type="button" class="fab no-print" onclick="openAdd()"><i class="fa-solid fa-plus"></i></button>

<div id="detailModal" class="modal-ov hidden">
    <div style="position:absolute;inset:0" onclick="closeDetail()"></div>
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h2>Detail Absensi</h2>
                <p>Pusdiklat Mahkamah Agung RI</p>
            </div>
            <button type="button" class="icon-btn" onclick="closeDetail()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<div id="addModal" class="modal-ov hidden">
    <div style="position:absolute;inset:0" onclick="closeAdd()"></div>
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h2>Tambah Absensi Manual</h2>
                <p><?= h($sesiLabel) ?></p>
            </div>
            <button type="button" class="icon-btn" onclick="closeAdd()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="f-group">
                <label class="f-lbl">Nama Peserta <span style="color:var(--red)">*</span></label>
                <input id="aNama" type="text" class="f-input" placeholder="Wajib diisi">
            </div>
            <div class="f-group">
                <label class="f-lbl">Unit / Jabatan</label>
                <input id="aUnit" type="text" class="f-input" placeholder="Contoh: Kabag Kepegawaian">
            </div>
            <div class="f-group">
                <label class="f-lbl">Instansi</label>
                <input id="aIns" type="text" class="f-input" placeholder="Contoh: Pusdiklat MA RI">
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-cancel" onclick="closeAdd()">Batal</button>
            <button type="button" class="btn-primary" onclick="handleAdd()">
                <i class="fa-solid fa-plus"></i> Simpan
            </button>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
    const BOOKING_ID = <?= $bookingId ?>;
    const PIN = <?= json_encode($pin) ?>;
    const SELECTED_DATE = <?= json_encode($selectedDate) ?>;
    const SELECTED_DAY = <?= (int)$selectedDay ?>;
    const IS_MULTI_DAY = <?= $isMultiDay ? 'true' : 'false' ?>;
    const SESI_LABEL = <?= json_encode($sesiLabel) ?>;
    const SELF_URL = location.pathname + '?id=' + BOOKING_ID + '&pin=' + encodeURIComponent(PIN) + '&tanggal=' + encodeURIComponent(SELECTED_DATE);

    let allRows = <?= json_encode(array_values($rows), JSON_UNESCAPED_UNICODE) ?>;
    let selectedIds = new Set();

    const $id = id => document.getElementById(id);

    function esc(v) {
        return String(v ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initials(n) {
        return (n || '')
            .replace(/[^a-zA-Z\s]/g, '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(w => w[0].toUpperCase())
            .join('');
    }

    function fmtT(s) {
        if (!s) return '-';
        const str = String(s);
        return str.length >= 16 ? str.substring(11, 16) : str.substring(0, 5);
    }

    function rowSesiLabel(r) {
        return IS_MULTI_DAY ?
            'Day ' + parseInt(r.day_ke || SELECTED_DAY) :
            new Date((r.tanggal_hadir || SELECTED_DATE) + 'T00:00:00').toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
    }

    function showToast(msg, dur = 2500) {
        const t = $id('toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), dur);
    }

    function updateStats(data) {
        const infoTotal = $id('infoTotalHadir');
        if (infoTotal) infoTotal.textContent = data.length;
        $id('dataCount').textContent = data.length + ' peserta hadir • ' + SESI_LABEL;
    }

    function filterData() {
        const q = $id('qSearch').value.toLowerCase().trim();
        const d = q ?
            allRows.filter(r => [r.nama_peserta, r.unit_jabatan, r.instansi].some(v => String(v || '').toLowerCase().includes(q))) :
            allRows;
        updateStats(d);
        renderTable(d);
        renderCards(d);
    }

    function renderTable(data) {
        if (!data.length) {
            $id('tableBody').innerHTML = `<tr><td colspan="9"><div class="empty-st"><i class="fa-solid fa-inbox"></i>Data tidak ditemukan</div></td></tr>`;
            return;
        }
        $id('tableBody').innerHTML = data.map((r, i) => `
            <tr data-id="${r.id}">
                <td class="cb-col no-print"><input type="checkbox" class="row-cb" value="${r.id}" ${selectedIds.has(r.id) ? 'checked' : ''} onchange="onCheckChange()"></td>
                <td style="text-align:center;font-size:12px;color:var(--muted)">${i + 1}</td>
                <td><span class="badge badge-blue">${esc(rowSesiLabel(r))}</span></td>
                <td><div style="display:flex;align-items:center;gap:8px">
                    <div class="avatar">${esc(initials(r.nama_peserta))}</div>
                    <span style="font-weight:700;font-size:14px">${esc(r.nama_peserta)}</span>
                </div></td>
                <td style="font-size:13px;color:var(--muted)">${esc(r.unit_jabatan || '-')}</td>
                <td style="font-size:13px;color:var(--muted)">${esc(r.instansi || '-')}</td>
                <td><span class="badge badge-green"><i class="fa-solid fa-clock" style="font-size:9px;margin-right:4px;opacity:.8"></i>${esc(fmtT(r.waktu_hadir))}</span></td>
                <td>${r.tanda_tangan
                    ? `<div class="ttd-box signed" onclick="openDetail(${r.id})"><img src="${esc(r.tanda_tangan)}" alt="TTD"></div>`
                    : `<div class="ttd-box ttd-none"><i class="fa-solid fa-signature" style="font-size:14px;color:var(--muted)"></i></div>`
                }</td>
                <td class="no-print">
                    <button type="button" class="act-btn" onclick="openDetail(${r.id})"><i class="fa-solid fa-eye"></i></button>
                    <button type="button" class="act-btn del" onclick="confirmDelete(${r.id})"><i class="fa-solid fa-trash-can"></i></button>
                </td>
            </tr>
        `).join('');
    }

    function renderCards(data) {
        const wrap = $id('mobileCards');
        if (!data.length) {
            wrap.innerHTML = `<div class="empty-st"><i class="fa-solid fa-inbox"></i>Data tidak ditemukan</div>`;
            return;
        }
        const inSelect = selectedIds.size > 0;
        wrap.innerHTML = data.map((r, i) => `
            <div class="m-card au ${selectedIds.has(r.id) ? 'selected' : ''}" style="animation-delay:${i * 0.03}s" data-id="${r.id}">
                <div class="m-card-top">
                    <input type="checkbox" class="row-cb-m no-print" value="${r.id}" ${selectedIds.has(r.id) ? 'checked' : ''}
                        style="display:${inSelect ? 'inline-block' : 'none'};margin-right:2px;flex-shrink:0" onchange="onCheckChange()">
                    <div class="m-num"><span>No</span><span>${i + 1}</span></div>
                    <div style="flex:1;min-width:0">
                        <div class="m-name">${esc(r.nama_peserta)}</div>
                        <div class="m-unit">${esc(r.unit_jabatan || '-')}</div>
                    </div>
                    <span class="badge badge-blue" style="flex-shrink:0">${esc(rowSesiLabel(r))}</span>
                </div>
                <div class="m-card-bot" onclick="openDetail(${r.id})">
                    <div class="m-ttd ${r.tanda_tangan ? 'signed' : ''}">
                        ${r.tanda_tangan ? `<img src="${esc(r.tanda_tangan)}" alt="TTD">` : `<i class="fa-solid fa-signature" style="font-size:16px;color:var(--muted);opacity:.3"></i>`}
                    </div>
                    <div style="flex:1;min-width:0">
                        <div class="m-meta"><i class="fa-solid fa-building"></i><span>${esc(r.instansi || '-')}</span></div>
                        <div class="m-meta"><i class="fa-solid fa-clock"></i><span>Hadir: ${esc(fmtT(r.waktu_hadir))}</span></div>
                    </div>
                    <button type="button" class="act-btn del no-print" onclick="event.stopPropagation();confirmDelete(${r.id})">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>
        `).join('');

        wrap.querySelectorAll('.m-card').forEach(card => {
            let lt;
            card.addEventListener('touchstart', () => {
                lt = setTimeout(() => toggleSelect(parseInt(card.dataset.id)), 600);
            }, {
                passive: true
            });
            card.addEventListener('touchend', () => clearTimeout(lt), {
                passive: true
            });
            card.addEventListener('touchmove', () => clearTimeout(lt), {
                passive: true
            });
        });
    }

    function onCheckChange() {
        selectedIds = new Set();
        document.querySelectorAll('.row-cb,.row-cb-m').forEach(cb => {
            if (cb.checked) selectedIds.add(parseInt(cb.value));
        });
        updateBulkBar();
    }

    function toggleAll(cbAll) {
        document.querySelectorAll('.row-cb').forEach(cb => cb.checked = cbAll.checked);
        if (cbAll.checked) allRows.forEach(r => selectedIds.add(r.id));
        else selectedIds.clear();
        updateBulkBar();
        filterData();
    }

    function toggleSelect(id) {
        if (selectedIds.has(id)) selectedIds.delete(id);
        else selectedIds.add(id);
        updateBulkBar();
        filterData();
    }

    function clearSelection() {
        selectedIds.clear();
        document.querySelectorAll('.row-cb,.row-cb-m').forEach(cb => cb.checked = false);
        const ca = $id('cbAll');
        if (ca) ca.checked = false;
        updateBulkBar();
        filterData();
    }

    function updateBulkBar() {
        const bar = $id('bulkBar');
        if (selectedIds.size > 0) {
            bar.classList.add('show');
            $id('bulkCount').textContent = selectedIds.size + ' dipilih';
        } else {
            bar.classList.remove('show');
        }
    }

    function openDetail(id) {
        const r = allRows.find(x => String(x.id) === String(id));
        if (!r) return;
        $id('modalBody').innerHTML = `
            <div class="detail-sec">
                <div style="margin-bottom:10px">
                    <div class="dt-lbl">Nama Peserta</div>
                    <div class="dt-val">${esc(r.nama_peserta)}</div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                    <div><div class="dt-lbl">Unit / Jabatan</div><div class="dt-val" style="font-size:13px">${esc(r.unit_jabatan || '-')}</div></div>
                    <div><div class="dt-lbl">Instansi</div><div class="dt-val" style="font-size:13px">${esc(r.instansi || '-')}</div></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <div class="dt-lbl">Tanggal Sesi</div>
                        <div class="dt-val" style="font-size:13px">${esc(r.tanggal_hadir || SELECTED_DATE)}</div>
                    </div>
                    <div>
                        <div class="dt-lbl">${IS_MULTI_DAY ? 'Day' : 'Tanggal'}</div>
                        <div class="dt-val" style="font-size:13px">${esc(rowSesiLabel(r))}</div>
                    </div>
                </div>
                <div style="margin-top:10px">
                    <div class="dt-lbl">Waktu Hadir</div>
                    <span class="badge badge-green" style="margin-top:3px">
                        <i class="fa-solid fa-clock" style="font-size:9px;margin-right:4px;opacity:.8"></i>
                        ${esc(r.waktu_hadir || '-')}
                    </span>
                </div>
            </div>
            ${r.tanda_tangan ? `
                <div style="margin-bottom:10px">
                    <div class="dt-lbl" style="margin-bottom:6px">Tanda Tangan</div>
                    <div style="background:var(--bg);border-radius:var(--radius);padding:10px;border:.5px solid var(--border);display:flex;justify-content:center">
                        <img src="${esc(r.tanda_tangan)}" class="ttd-lg" alt="TTD">
                    </div>
                </div>` : ''}
            <div style="display:flex;gap:7px">
                <button type="button" class="btn-danger" onclick="confirmDelete(${r.id});closeDetail()">
                    <i class="fa-solid fa-trash-can"></i> Hapus
                </button>
                <button type="button" class="btn-cancel" style="flex:1" onclick="closeDetail()">Tutup</button>
            </div>`;
        $id('detailModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeDetail() {
        $id('detailModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function openAdd() {
        $id('aNama').value = '';
        $id('aUnit').value = '';
        $id('aIns').value = '';
        $id('addModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => $id('aNama').focus(), 150);
    }

    function closeAdd() {
        $id('addModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    async function handleAdd() {
        const nama = $id('aNama').value.trim();
        if (!nama) {
            showToast('Nama wajib diisi');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'add_manual');
        fd.append('nama_peserta', nama);
        fd.append('unit_jabatan', $id('aUnit').value.trim());
        fd.append('instansi', $id('aIns').value.trim());

        try {
            const res = await fetch(SELF_URL, {
                method: 'POST',
                body: fd
            });
            const j = await res.json();
            if (j.status) {
                showToast('✓ Absensi ditambahkan ke sesi ini');
                closeAdd();
                await refreshData();
            } else {
                showToast(j.message || 'Gagal menyimpan');
            }
        } catch (e) {
            showToast('Error: ' + e.message);
        }
    }

    async function confirmDelete(id) {
        if (!confirm('Hapus data absensi ini?')) return;

        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);

        try {
            const res = await fetch(SELF_URL, {
                method: 'POST',
                body: fd
            });
            const j = await res.json();
            if (j.status) {
                allRows = allRows.filter(r => String(r.id) !== String(id));
                filterData();
                showToast('✓ Data dihapus');
            } else {
                showToast(j.message || 'Gagal menghapus');
            }
        } catch (e) {
            showToast('Error: ' + e.message);
        }
    }

    async function bulkDelete() {
        if (!selectedIds.size) return;
        if (!confirm('Hapus ' + selectedIds.size + ' data yang dipilih?')) return;

        const fd = new FormData();
        fd.append('action', 'bulk_delete');
        fd.append('ids', JSON.stringify([...selectedIds]));

        try {
            const res = await fetch(SELF_URL, {
                method: 'POST',
                body: fd
            });
            const j = await res.json();
            if (j.status) {
                allRows = allRows.filter(r => !selectedIds.has(r.id));
                selectedIds.clear();
                updateBulkBar();
                filterData();
                showToast('✓ ' + j.deleted + ' data dihapus');
            } else {
                showToast(j.message || 'Gagal menghapus');
            }
        } catch (e) {
            showToast('Error: ' + e.message);
        }
    }

    async function refreshData() {
        const icon = $id('refreshIcon');
        icon.classList.add('spin');
        try {
            const res = await fetch(SELF_URL + '&json=1');
            if (res.ok) {
                const j = await res.json().catch(() => null);
                if (j && j.rows) {
                    allRows = j.rows;
                    filterData();
                    showToast('✓ Data diperbarui');
                } else {
                    location.reload();
                }
            }
        } catch {
            location.reload();
        } finally {
            setTimeout(() => icon.classList.remove('spin'), 700);
        }
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !$id('addModal').classList.contains('hidden')) handleAdd();
        if (e.key === 'Escape') {
            closeDetail();
            closeAdd();
        }
    });

    renderTable(allRows);
    renderCards(allRows);
    updateStats(allRows);
</script>