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
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    $p = explode('-', substr($d, 0, 10));
    if (count($p) !== 3) return $d;
    return $p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}

function fmtTime($t)
{
    return $t ? substr((string)$t, 0, 5) : '-';
}

function ensureUploadDir($dir)
{
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    return is_dir($dir);
}

function hitungDayKeMengikutiKegiatan($startDate, $endDate, $currentDate)
{
    $start = new DateTime($startDate);
    $end   = new DateTime($endDate);
    $curr  = new DateTime($currentDate);
    if ($curr < $start) return 0;
    if ($curr > $end) $curr = clone $end;
    return (int)$start->diff($curr)->days + 1;
}

function formatSesiLabel($startDate, $endDate, $selectedDate, $selectedDay)
{
    if ($startDate === $endDate) return fmtDate($selectedDate);
    return 'Day ' . $selectedDay . ' - ' . fmtDate($selectedDate);
}

/* ── 2. Validasi parameter ── */
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Jakarta');

$isAdmin = is_array($_SESSION['user'] ?? null)
    && isset($_SESSION['user']['role'])
    && strtolower((string)$_SESSION['user']['role']) === 'admin';

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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
              COALESCE(r.nama_ruang,'') AS ruang, COALESCE(r.lokasi,'') AS lokasi_ruang
       FROM booking_ruang_rapat b LEFT JOIN ruang_rapat r ON r.id = b.room_id
       WHERE b.id = ? LIMIT 1"
    : "SELECT b.id, b.pin, b.nama, b.peminjam, b.start_date, b.end_date,
              b.jam_start, b.jam_end, b.jenis_lokasi, b.lokasi_external,
              COALESCE(r.nama_ruang,'') AS ruang, COALESCE(r.lokasi,'') AS lokasi_ruang
       FROM booking_ruang_rapat b LEFT JOIN ruang_rapat r ON r.id = b.room_id
       WHERE b.id = ? AND b.pin = ? LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) die('Query booking gagal: ' . $conn->error);
if ($isAdmin) $stmt->bind_param('i', $bookingId);
else          $stmt->bind_param('is', $bookingId, $pin);
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

if ($isAdmin && empty($pin)) $pin = $booking['pin'];

$lokasi = $booking['jenis_lokasi'] === 'external'
    ? ($booking['lokasi_external'] ?: '-')
    : trim(($booking['ruang'] ?: '-') . ($booking['lokasi_ruang'] ? ' - ' . $booking['lokasi_ruang'] : ''));

/* ── 4. Tentukan sesi notulen aktif ── */
$today        = date('Y-m-d');
$selectedDate = $tanggal !== '' ? $tanggal : $today;
if ($selectedDate < $booking['start_date']) $selectedDate = $booking['start_date'];

$selectedDay  = hitungDayKeMengikutiKegiatan($booking['start_date'], $booking['end_date'], $selectedDate);
$isMultiDay   = $booking['start_date'] !== $booking['end_date'];
$sesiLabel    = formatSesiLabel($booking['start_date'], $booking['end_date'], $selectedDate, $selectedDay);
$canEditNotulen = $today >= $booking['start_date'];

/* ── 5. Siapkan daftar day kegiatan ── */
$days = [];
$startObj = new DateTime($booking['start_date']);
$endObj   = new DateTime($booking['end_date']);
$cursor   = clone $startObj;
$dayNo    = 1;
while ($cursor <= $endObj) {
    $d = $cursor->format('Y-m-d');
    $days[] = ['tanggal' => $d, 'label' => $isMultiDay ? ('Day ' . $dayNo) : fmtDate($d), 'fmt' => fmtDate($d)];
    $cursor->modify('+1 day');
    $dayNo++;
}

/* ── 6. AJAX / Inline actions (POST) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if (!$canEditNotulen) {
        echo json_encode(['status' => false, 'message' => 'Notulen baru bisa diisi mulai tanggal kegiatan']);
        exit;
    }

    if ($action === 'save_notulen') {
        $agenda       = trim($_POST['agenda'] ?? '');
        $pimpinan     = trim($_POST['pimpinan_rapat'] ?? '');
        $moderator    = trim($_POST['moderator'] ?? '');
        $notulis      = trim($_POST['notulis'] ?? '');
        $pesertaText  = trim($_POST['peserta_text'] ?? '');
        $pembahasan   = trim($_POST['pembahasan'] ?? '');
        $keputusan    = trim($_POST['keputusan'] ?? '');
        $tindakLanjut = trim($_POST['tindak_lanjut'] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO notulen_rapat
            (booking_id, tanggal_notulen, day_ke, agenda, pimpinan_rapat, moderator, notulis, peserta_text, pembahasan, keputusan, tindak_lanjut)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                day_ke=VALUES(day_ke), agenda=VALUES(agenda), pimpinan_rapat=VALUES(pimpinan_rapat),
                moderator=VALUES(moderator), notulis=VALUES(notulis), peserta_text=VALUES(peserta_text),
                pembahasan=VALUES(pembahasan), keputusan=VALUES(keputusan), tindak_lanjut=VALUES(tindak_lanjut)
        ");
        if (!$stmt) {
            echo json_encode(['status' => false, 'message' => 'Query simpan gagal: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param('isissssssss', $bookingId, $selectedDate, $selectedDay, $agenda, $pimpinan, $moderator, $notulis, $pesertaText, $pembahasan, $keputusan, $tindakLanjut);
        echo $stmt->execute()
            ? json_encode(['status' => true, 'message' => 'Notulen berhasil disimpan'])
            : json_encode(['status' => false, 'message' => 'Gagal menyimpan: ' . $stmt->error]);
        $stmt->close();
        exit;
    }

    if ($action === 'upload_dokumentasi') {
        if (!isset($_FILES['files'])) {
            echo json_encode(['status' => false, 'message' => 'Tidak ada file']);
            exit;
        }
        $uploadDirFs = __DIR__ . '/uploads/notulen/';
        $uploadDirDb = 'uploads/notulen/';
        if (!ensureUploadDir($uploadDirFs)) {
            echo json_encode(['status' => false, 'message' => 'Folder tidak tersedia']);
            exit;
        }
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $saved = 0;
        $names = $_FILES['files']['name'] ?? [];
        $tmps = $_FILES['files']['tmp_name'] ?? [];
        $errs = $_FILES['files']['error'] ?? [];
        for ($i = 0; $i < count($names); $i++) {
            if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) continue;
            $newName = 'notulen_' . $bookingId . '_' . $selectedDate . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destFs  = $uploadDirFs . $newName;
            $destDb = $uploadDirDb . $newName;
            if (move_uploaded_file($tmps[$i], $destFs)) {
                $s = $conn->prepare("INSERT INTO notulen_dokumentasi (booking_id, tanggal_notulen, day_ke, file_path) VALUES (?, ?, ?, ?)");
                if ($s) {
                    $s->bind_param('isis', $bookingId, $selectedDate, $selectedDay, $destDb);
                    $s->execute();
                    $s->close();
                    $saved++;
                }
            }
        }
        echo json_encode(['status' => $saved > 0, 'message' => $saved > 0 ? ($saved . ' foto berhasil diupload') : 'Tidak ada file yang berhasil diupload']);
        exit;
    }

    if ($action === 'delete_foto') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => false, 'message' => 'ID tidak valid']);
            exit;
        }
        $s = $conn->prepare("SELECT file_path FROM notulen_dokumentasi WHERE id=? AND booking_id=? AND tanggal_notulen=?");
        if (!$s) {
            echo json_encode(['status' => false, 'message' => 'Query gagal']);
            exit;
        }
        $s->bind_param('iis', $id, $bookingId, $selectedDate);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row) {
            echo json_encode(['status' => false, 'message' => 'Foto tidak ditemukan']);
            exit;
        }
        $fsPath = __DIR__ . '/' . $row['file_path'];
        if (is_file($fsPath)) @unlink($fsPath);
        $d = $conn->prepare("DELETE FROM notulen_dokumentasi WHERE id=? AND booking_id=? AND tanggal_notulen=?");
        $d->bind_param('iis', $id, $bookingId, $selectedDate);
        $d->execute();
        $ok = $d->affected_rows > 0;
        $d->close();
        echo json_encode(['status' => $ok, 'message' => $ok ? 'Foto berhasil dihapus' : 'Gagal menghapus foto']);
        exit;
    }

    echo json_encode(['status' => false, 'message' => 'Action tidak dikenal']);
    exit;
}

/* ── 7. JSON refresh ── */
if (isset($_GET['json']) && $_GET['json'] === '1') {
    $notulen = ['agenda' => '', 'pimpinan_rapat' => '', 'moderator' => '', 'notulis' => '', 'peserta_text' => '', 'pembahasan' => '', 'keputusan' => '', 'tindak_lanjut' => ''];
    $s = $conn->prepare("SELECT agenda, pimpinan_rapat, moderator, notulis, peserta_text, pembahasan, keputusan, tindak_lanjut FROM notulen_rapat WHERE booking_id=? AND tanggal_notulen=? LIMIT 1");
    if ($s) {
        $s->bind_param('is', $bookingId, $selectedDate);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        if ($r) $notulen = $r;
        $s->close();
    }
    $docs = [];
    $s = $conn->prepare("SELECT id, file_path, created_at FROM notulen_dokumentasi WHERE booking_id=? AND tanggal_notulen=? ORDER BY id DESC");
    if ($s) {
        $s->bind_param('is', $bookingId, $selectedDate);
        $s->execute();
        $rr = $s->get_result();
        while ($row = $rr->fetch_assoc()) $docs[] = $row;
        $s->close();
    }
    header('Content-Type: application/json');
    echo json_encode(['notulen' => $notulen, 'dokumentasi' => $docs, 'selected_date' => $selectedDate, 'selected_day' => $selectedDay, 'sesi_label' => $sesiLabel]);
    exit;
}

/* ── 8. Data awal ── */
$notulen = ['agenda' => '', 'pimpinan_rapat' => '', 'moderator' => '', 'notulis' => '', 'peserta_text' => '', 'pembahasan' => '', 'keputusan' => '', 'tindak_lanjut' => ''];
$stmt = $conn->prepare("SELECT agenda, pimpinan_rapat, moderator, notulis, peserta_text, pembahasan, keputusan, tindak_lanjut FROM notulen_rapat WHERE booking_id=? AND tanggal_notulen=? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('is', $bookingId, $selectedDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $notulen = $row;
    $stmt->close();
}
$dokumentasi = [];
$stmt = $conn->prepare("SELECT id, file_path, created_at FROM notulen_dokumentasi WHERE booking_id=? AND tanggal_notulen=? ORDER BY id DESC");
if ($stmt) {
    $stmt->bind_param('is', $bookingId, $selectedDate);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $dokumentasi[] = $row;
    $stmt->close();
}

$title = "Notulen Rapat - " . h($booking['nama']);
include 'header.php';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

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
        --amber: #854F0B;
        --amber-lt: #FAEEDA;
        --ink: #0f172a;
        --muted: #64748b;
        --border: rgba(0, 0, 0, .1);
        --bg: #f1f5f9;
        --white: #fff;
        --radius: 12px;
        --radius-lg: 16px;
        --stt-active: #E53E3E;
        --stt-pulse: #FC8181;
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

    /* ── HEADER ── */
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
        padding: 10px 16px
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
        font-size: 14px
    }

    .icon-btn:hover {
        background: var(--blue-lt)
    }

    .day-tabs {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        padding: 0 14px 10px;
        scrollbar-width: none
    }

    .day-tabs::-webkit-scrollbar {
        display: none
    }

    .day-tab {
        text-decoration: none;
        padding: 6px 12px;
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

    .hdr-offset-single {
        padding-top: 58px
    }

    .hdr-offset-multi {
        padding-top: 96px
    }

    /* ── CONTENT ── */

    /* ── CONTENT ── */
    .page-body {
        padding: 22px 0 90px
    }

    .blocked-box {
        margin: 0 14px 10px;
        background: #FFF7ED;
        border: 1px solid #FED7AA;
        color: #B45309;
        border-radius: 14px;
        padding: 11px 14px;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.6
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
        padding: 10px 14px;
        border-bottom: .5px solid var(--border)
    }

    .section-icon {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 13px
    }

    .ic-blue {
        background: var(--blue-lt);
        color: var(--blue)
    }

    .ic-green {
        background: var(--green-lt);
        color: var(--green)
    }

    .ic-amber {
        background: var(--amber-lt);
        color: var(--amber)
    }

    .ic-red {
        background: var(--red-lt);
        color: var(--red)
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
        padding: 12px 14px
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
        margin-bottom: 9px
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

    /* ── STT PANEL ── */
    .stt-panel {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 14px;
        padding: 16px;
        margin-bottom: 12px;
        position: relative;
        overflow: hidden
    }

    .stt-panel::before {
        content: '';
        position: absolute;
        top: -40px;
        right: -40px;
        width: 120px;
        height: 120px;
        background: radial-gradient(circle, rgba(59, 130, 246, .15), transparent 70%);
        pointer-events: none
    }

    .stt-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px
    }

    .stt-title {
        font-size: 13px;
        font-weight: 700;
        color: #e2e8f0
    }

    .stt-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 999px;
        background: rgba(59, 130, 246, .2);
        color: #93c5fd;
        border: 1px solid rgba(59, 130, 246, .3)
    }

    .stt-mic-zone {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 12px
    }

    .mic-btn {
        position: relative;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
        transition: transform .15s;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
        box-shadow: 0 4px 15px rgba(37, 99, 235, .4)
    }

    .mic-btn:hover {
        transform: scale(1.05)
    }

    .mic-btn.recording {
        background: linear-gradient(135deg, var(--stt-active), #c53030);
        box-shadow: 0 4px 15px rgba(229, 62, 62, .5), 0 0 0 0 rgba(229, 62, 62, .4);
        animation: micPulse 1.5s ease-in-out infinite
    }

    .mic-btn:disabled {
        opacity: .4;
        cursor: not-allowed;
        transform: none
    }

    @keyframes micPulse {
        0% {
            box-shadow: 0 4px 15px rgba(229, 62, 62, .5), 0 0 0 0 rgba(229, 62, 62, .4)
        }

        70% {
            box-shadow: 0 4px 15px rgba(229, 62, 62, .5), 0 0 0 16px rgba(229, 62, 62, 0)
        }

        100% {
            box-shadow: 0 4px 15px rgba(229, 62, 62, .5), 0 0 0 0 rgba(229, 62, 62, 0)
        }
    }

    .stt-status-zone {
        flex: 1;
        min-width: 0
    }

    .stt-status-text {
        font-size: 13px;
        font-weight: 600;
        color: #94a3b8;
        margin-bottom: 4px
    }

    .stt-status-text.active {
        color: #60a5fa
    }

    .stt-status-text.error {
        color: #f87171
    }

    .stt-interim {
        font-size: 12px;
        color: #475569;
        font-style: italic;
        min-height: 18px;
        transition: color .2s
    }

    .stt-interim.live {
        color: #93c5fd
    }

    .stt-waveform {
        display: flex;
        align-items: center;
        gap: 3px;
        height: 28px;
        margin-bottom: 4px
    }

    .stt-bar {
        width: 3px;
        border-radius: 3px;
        background: #334155;
        transition: height .1s ease
    }

    .stt-bar.active {
        background: linear-gradient(to top, #2563eb, #60a5fa)
    }

    .stt-target-row {
        display: flex;
        gap: 6px;
        flex-wrap: wrap
    }

    .stt-target-btn {
        padding: 5px 10px;
        border-radius: 8px;
        border: .5px solid rgba(255, 255, 255, .1);
        background: rgba(255, 255, 255, .05);
        color: #94a3b8;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all .15s
    }

    .stt-target-btn:hover {
        background: rgba(59, 130, 246, .2);
        color: #93c5fd;
        border-color: rgba(59, 130, 246, .4)
    }

    .stt-target-btn.selected {
        background: rgba(59, 130, 246, .25);
        color: #60a5fa;
        border-color: rgba(59, 130, 246, .5)
    }

    .stt-target-label {
        font-size: 10px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: 5px
    }

    .stt-lang-row {
        display: flex;
        gap: 6px;
        align-items: center;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, .06)
    }

    .stt-lang-label {
        font-size: 11px;
        color: #475569;
        flex-shrink: 0
    }

    .stt-lang-select {
        background: rgba(255, 255, 255, .06);
        border: .5px solid rgba(255, 255, 255, .1);
        color: #94a3b8;
        border-radius: 8px;
        padding: 5px 8px;
        font-size: 11px;
        cursor: pointer;
        outline: none;
        font-family: inherit
    }

    .stt-lang-select option {
        background: #1e293b
    }

    .stt-mode-row {
        display: flex;
        gap: 6px;
        align-items: center;
        margin-top: 8px
    }

    .stt-mode-label {
        font-size: 11px;
        color: #475569;
        flex-shrink: 0
    }

    .stt-mode-btn {
        padding: 4px 10px;
        border-radius: 7px;
        border: .5px solid rgba(255, 255, 255, .1);
        background: rgba(255, 255, 255, .05);
        color: #475569;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all .15s
    }

    .stt-mode-btn.on {
        background: rgba(59, 130, 246, .25);
        color: #60a5fa;
        border-color: rgba(59, 130, 246, .5)
    }

    .stt-transcript-preview {
        margin-top: 10px;
        padding: 8px 10px;
        background: rgba(0, 0, 0, .3);
        border-radius: 10px;
        font-size: 12px;
        color: #64748b;
        min-height: 38px;
        border: .5px solid rgba(255, 255, 255, .06);
        line-height: 1.6;
        max-height: 80px;
        overflow-y: auto
    }

    .stt-transcript-preview span {
        color: #93c5fd
    }

    .stt-action-row {
        display: flex;
        gap: 6px;
        margin-top: 8px
    }

    .stt-small-btn {
        padding: 5px 10px;
        border-radius: 8px;
        border: .5px solid rgba(255, 255, 255, .1);
        background: rgba(255, 255, 255, .06);
        color: #94a3b8;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all .15s
    }

    .stt-small-btn:hover {
        background: rgba(255, 255, 255, .12);
        color: #e2e8f0
    }

    .stt-small-btn.danger:hover {
        background: rgba(239, 68, 68, .2);
        color: #f87171;
        border-color: rgba(239, 68, 68, .3)
    }

    .stt-insert-btn {
        padding: 5px 12px;
        border-radius: 8px;
        border: none;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px
    }

    .stt-insert-btn:disabled {
        opacity: .4;
        cursor: not-allowed
    }

    /* ── FORM ── */
    .f-group {
        margin-bottom: 11px
    }

    .f-group:last-child {
        margin-bottom: 0
    }

    .f-lbl {
        font-size: 11px;
        font-weight: 700;
        color: var(--muted);
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: .04em
    }

    .f-wrap {
        position: relative
    }

    .f-ico {
        position: absolute;
        left: 11px;
        top: 13px;
        font-size: 11px;
        color: var(--muted)
    }

    .f-input,
    .f-textarea {
        width: 100%;
        padding: 11px 12px 11px 36px;
        background: var(--bg);
        border: .5px solid var(--border);
        border-radius: var(--radius);
        font-size: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--ink);
        outline: none;
        transition: border-color .15s, box-shadow .15s, background .15s
    }

    .f-input:focus,
    .f-textarea:focus {
        border-color: var(--blue-md);
        background: var(--white);
        box-shadow: 0 0 0 3px rgba(55, 138, 221, .1)
    }

    .f-input.stt-target-active,
    .f-textarea.stt-target-active {
        border-color: #60a5fa;
        background: #f0f7ff;
        box-shadow: 0 0 0 3px rgba(96, 165, 250, .15)
    }

    .f-textarea {
        min-height: 110px;
        resize: vertical;
        line-height: 1.65
    }

    .field-mic-btn {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: var(--muted);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        transition: all .15s;
        margin-left: auto
    }

    .field-mic-btn:hover {
        background: var(--blue-lt);
        color: var(--blue)
    }

    .field-mic-btn.active {
        background: var(--red-lt);
        color: var(--red);
        animation: fieldMicPulse 1.2s ease-in-out infinite
    }

    @keyframes fieldMicPulse {

        0%,
        100% {
            transform: scale(1)
        }

        50% {
            transform: scale(1.2)
        }
    }

    /* ── UPLOAD ── */
    .upload-box {
        border: 1px dashed var(--blue-bd);
        background: var(--blue-lt);
        border-radius: 14px;
        padding: 14px;
        text-align: center
    }

    .upload-box input {
        display: none
    }

    .upload-label {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 10px 15px;
        border-radius: 10px;
        background: var(--white);
        border: .5px solid var(--blue-bd);
        font-size: 13px;
        font-weight: 700;
        color: var(--blue);
        cursor: pointer
    }

    .upload-note {
        font-size: 12px;
        color: var(--muted);
        margin-top: 8px;
        line-height: 1.5
    }

    /* ── GALLERY ── */
    .gallery {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px
    }

    @media(min-width:768px) {
        .gallery {
            grid-template-columns: repeat(4, 1fr)
        }
    }

    .gallery-item {
        background: var(--white);
        border: .5px solid var(--border);
        border-radius: 14px;
        overflow: hidden
    }

    .gallery-thumb {
        width: 100%;
        height: 130px;
        background: var(--bg);
        display: block;
        object-fit: cover;
        cursor: pointer
    }

    .gallery-meta {
        padding: 8px 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px
    }

    .gallery-cap {
        font-size: 12px;
        font-weight: 700;
        color: var(--muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .btn-del-img {
        width: 28px;
        height: 28px;
        border: none;
        border-radius: 7px;
        background: var(--red-lt);
        color: var(--red);
        cursor: pointer;
        flex-shrink: 0;
        font-size: 12px
    }

    .btn-del-img:hover {
        background: var(--red-bd)
    }

    /* ── ACTION CARD ── */
    .action-card {
        margin: 0 14px 90px;
        background: var(--white);
        border: .5px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 14px
    }

    .btn-submit {
        width: 100%;
        padding: 13px;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--blue), #0891b2);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px
    }

    .btn-submit:hover {
        opacity: .95
    }

    .btn-submit:disabled {
        opacity: .55;
        cursor: not-allowed
    }

    .privacy {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 11px;
        color: var(--muted);
        margin-top: 9px
    }

    .autosave {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 11px;
        color: var(--muted);
        margin-top: 7px
    }

    /* ── TOAST ── */
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
        padding: 26px 0;
        color: var(--muted);
        font-size: 13px
    }

    .empty-st i {
        font-size: 26px;
        opacity: .18;
        display: block;
        margin-bottom: 8px
    }

    /* ── MODAL ── */
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
        max-width: 640px;
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
        overflow: auto;
        flex: 1
    }

    .modal-img {
        width: 100%;
        max-height: 72vh;
        object-fit: contain;
        border-radius: 12px;
        background: var(--bg)
    }

    /* ── STT NOT SUPPORTED ── */
    .stt-unsupported {
        background: #FFF7ED;
        border: 1px solid #FED7AA;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 12px;
        color: #B45309;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px
    }

    @keyframes spin {
        to {
            transform: rotate(360deg)
        }
    }

    .spin {
        animation: spin .6s linear infinite
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

        .hdr-offset-single,
        .hdr-offset-multi {
            padding-top: 0 !important
        }

        .section-card,
        .action-card {
            border: 1px solid #ddd;
            box-shadow: none
        }

        .f-input,
        .f-textarea {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important
        }

        .stt-panel {
            display: none !important
        }
    }
</style>

<header class="sticky-hdr no-print">
    <div class="hdr-top">
        <div class="hdr-titles">
            <h1>Notulen Rapat</h1>
            <p><?= h($booking['nama']) ?></p>
        </div>
        <div class="hdr-actions">
            <button type="button" class="icon-btn"
                onclick="window.location.href='notulen_export.php?id=<?= $bookingId ?>&pin=<?= urlencode($pin) ?>&tanggal=<?= urlencode($selectedDate) ?>'"
                title="Export PDF"><i class="fa-solid fa-download"></i></button>
            <button type="button" class="icon-btn" onclick="refreshData()" title="Refresh">
                <i class="fa-solid fa-rotate-right" id="refreshIcon"></i>
            </button>
        </div>
    </div>
    <?php if (count($days) > 1): ?>
        <div class="day-tabs">
            <?php foreach ($days as $d): ?>
                <a href="notulen.php?id=<?= (int)$bookingId ?>&pin=<?= urlencode($pin) ?>&tanggal=<?= urlencode($d['tanggal']) ?>"
                    class="day-tab <?= $selectedDate === $d['tanggal'] ? 'active' : '' ?>"><?= h($d['label']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</header>

<main class="<?= count($days) > 1 ? 'hdr-offset-multi' : 'hdr-offset-single' ?>">
    <div class="page-body">

        <?php if (!$canEditNotulen): ?>
            <div class="blocked-box">
                Notulen baru bisa diisi mulai tanggal kegiatan, yaitu <strong><?= h(fmtDate($booking['start_date'])) ?></strong>.
            </div>
        <?php endif; ?>

        <!-- Informasi Rapat -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon ic-green"><i class="fa-solid fa-calendar-check"></i></div>
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
                                <div class="info-val"><?= h($booking['peminjam']) ?></div>
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
                                <div class="info-val"><?= h(fmtDate($booking['start_date'])) ?><?php if ($booking['start_date'] !== $booking['end_date']): ?> — <?= h(fmtDate($booking['end_date'])) ?><?php endif; ?></div>
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
                                <div class="info-lbl">Sesi Notulen</div>
                                <div class="info-val"><?= h($sesiLabel) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ SPEECH-TO-TEXT PANEL ═══ -->
        <?php if ($canEditNotulen): ?>
            <div class="section-card no-print">
                <div class="section-head">
                    <div class="section-icon ic-red"><i class="fa-solid fa-microphone"></i></div>
                    <div>
                        <div class="section-title">Speech-to-Text Otomatis</div>
                        <div class="section-sub">Rekam suara pembicara, teks otomatis masuk ke field notulen</div>
                    </div>
                </div>
                <div class="section-body">
                    <div id="sttUnsupported" class="stt-unsupported" style="display:none">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Browser Anda tidak mendukung Speech Recognition. Gunakan Chrome/Edge versi terbaru.
                    </div>

                    <div id="sttPanel" class="stt-panel">
                        <!-- Header row -->
                        <div class="stt-header">
                            <div class="stt-title"><i class="fa-solid fa-waveform" style="margin-right:6px;color:#60a5fa"></i>Voice Recorder</div>
                            <div class="stt-badge" id="sttBadge">Siap</div>
                        </div>

                        <!-- Mic + waveform -->
                        <div class="stt-mic-zone">
                            <button type="button" class="mic-btn" id="mainMicBtn" onclick="toggleMainStt()">
                                <i class="fa-solid fa-microphone" id="mainMicIcon"></i>
                            </button>
                            <div class="stt-status-zone">
                                <div class="stt-waveform" id="sttWaveform">
                                    <?php for ($i = 0; $i < 18; $i++): ?><div class="stt-bar" style="height:4px"></div><?php endfor; ?>
                                </div>
                                <div class="stt-status-text" id="sttStatusText">Tekan mikrofon untuk mulai merekam</div>
                                <div class="stt-interim" id="sttInterim">—</div>
                            </div>
                        </div>

                        <!-- Target field selector -->
                        <div style="margin-bottom:8px">
                            <div class="stt-target-label">Tujuan input suara</div>
                            <div class="stt-target-row" id="sttTargetRow">
                                <button type="button" class="stt-target-btn selected" data-target="pembahasan">Pembahasan</button>
                                <button type="button" class="stt-target-btn" data-target="peserta_text">Peserta</button>
                                <button type="button" class="stt-target-btn" data-target="keputusan">Keputusan</button>
                                <button type="button" class="stt-target-btn" data-target="tindak_lanjut">Tindak Lanjut</button>
                                <button type="button" class="stt-target-btn" data-target="agenda">Agenda</button>
                                <button type="button" class="stt-target-btn" data-target="pimpinan_rapat">Pimpinan</button>
                                <button type="button" class="stt-target-btn" data-target="moderator">Moderator</button>
                                <button type="button" class="stt-target-btn" data-target="notulis">Notulis</button>
                            </div>
                        </div>

                        <!-- Language + Mode -->
                        <div class="stt-lang-row">
                            <span class="stt-lang-label"><i class="fa-solid fa-globe" style="margin-right:4px"></i>Bahasa</span>
                            <select class="stt-lang-select" id="sttLang" onchange="updateSttLang()">
                                <option value="id-ID" selected>Bahasa Indonesia</option>
                                <option value="en-US">English (US)</option>
                                <option value="en-GB">English (UK)</option>
                                <option value="ar-SA">العربية</option>
                            </select>
                            <span class="stt-lang-label" style="margin-left:10px"><i class="fa-solid fa-repeat" style="margin-right:4px"></i>Mode</span>
                            <button type="button" class="stt-mode-btn on" id="sttContinuousBtn" onclick="toggleContinuous()">Kontinyu</button>
                        </div>

                        <!-- Live transcript preview -->
                        <div class="stt-transcript-preview" id="sttTranscriptPreview">
                            <span style="color:#334155;font-style:italic">Transkrip sesi akan muncul di sini...</span>
                        </div>

                        <!-- Actions -->
                        <div class="stt-action-row">
                            <button type="button" class="stt-small-btn" onclick="insertToField()">
                                <i class="fa-solid fa-arrow-right-to-bracket"></i> Sisipkan ke Field
                            </button>
                            <button type="button" class="stt-small-btn" onclick="clearTranscript()">
                                <i class="fa-solid fa-eraser"></i> Bersihkan
                            </button>
                            <button type="button" class="stt-small-btn danger" onclick="clearFieldContent()">
                                <i class="fa-solid fa-trash"></i> Kosongkan Field
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Identitas Notulen -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon ic-blue"><i class="fa-solid fa-pen"></i></div>
                <div>
                    <div class="section-title">Identitas Notulen</div>
                    <div class="section-sub">Agenda dan petugas rapat untuk sesi ini</div>
                </div>
            </div>
            <div class="section-body">
                <div class="f-group">
                    <label class="f-lbl">
                        Agenda
                        <?php if ($canEditNotulen): ?><button type="button" class="field-mic-btn" title="Rekam ke Agenda" onclick="quickRecord('agenda')"><i class="fa-solid fa-microphone"></i></button><?php endif; ?>
                    </label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-bullseye"></i>
                        <input id="agenda" type="text" class="f-input" value="<?= h($notulen['agenda']) ?>" <?= !$canEditNotulen ? 'disabled' : '' ?>>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="f-group">
                        <label class="f-lbl">
                            Pimpinan Rapat
                            <?php if ($canEditNotulen): ?><button type="button" class="field-mic-btn" title="Rekam ke Pimpinan" onclick="quickRecord('pimpinan_rapat')"><i class="fa-solid fa-microphone"></i></button><?php endif; ?>
                        </label>
                        <div class="f-wrap">
                            <i class="f-ico fa-solid fa-user-tie"></i>
                            <input id="pimpinan_rapat" type="text" class="f-input" value="<?= h($notulen['pimpinan_rapat']) ?>" <?= !$canEditNotulen ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <div class="f-group">
                        <label class="f-lbl">
                            Moderator
                            <?php if ($canEditNotulen): ?><button type="button" class="field-mic-btn" title="Rekam ke Moderator" onclick="quickRecord('moderator')"><i class="fa-solid fa-microphone"></i></button><?php endif; ?>
                        </label>
                        <div class="f-wrap">
                            <i class="f-ico fa-solid fa-microphone"></i>
                            <input id="moderator" type="text" class="f-input" value="<?= h($notulen['moderator']) ?>" <?= !$canEditNotulen ? 'disabled' : '' ?>>
                        </div>
                    </div>
                </div>
                <div class="f-group">
                    <label class="f-lbl">
                        Notulis
                        <?php if ($canEditNotulen): ?><button type="button" class="field-mic-btn" title="Rekam ke Notulis" onclick="quickRecord('notulis')"><i class="fa-solid fa-microphone"></i></button><?php endif; ?>
                    </label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-pencil"></i>
                        <input id="notulis" type="text" class="f-input" value="<?= h($notulen['notulis']) ?>" <?= !$canEditNotulen ? 'disabled' : '' ?>>
                    </div>
                </div>
            </div>
        </div>

        <!-- Isi Notulen -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon ic-amber"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="section-title">Isi Notulen</div>
                    <div class="section-sub">Peserta, pembahasan, keputusan, dan tindak lanjut</div>
                </div>
            </div>
            <div class="section-body">
                <?php
                $fields = [
                    ['id' => 'peserta_text', 'lbl' => 'Peserta', 'ico' => 'fa-users'],
                    ['id' => 'pembahasan', 'lbl' => 'Pembahasan', 'ico' => 'fa-comments'],
                    ['id' => 'keputusan', 'lbl' => 'Keputusan', 'ico' => 'fa-circle-check'],
                    ['id' => 'tindak_lanjut', 'lbl' => 'Tindak Lanjut', 'ico' => 'fa-list-check'],
                ];
                foreach ($fields as $f): ?>
                    <div class="f-group">
                        <label class="f-lbl">
                            <?= h($f['lbl']) ?>
                            <?php if ($canEditNotulen): ?><button type="button" class="field-mic-btn" title="Rekam ke <?= h($f['lbl']) ?>" onclick="quickRecord('<?= h($f['id']) ?>')"><i class="fa-solid fa-microphone"></i></button><?php endif; ?>
                        </label>
                        <div class="f-wrap">
                            <i class="f-ico fa-solid <?= h($f['ico']) ?>"></i>
                            <textarea id="<?= h($f['id']) ?>" class="f-textarea" <?= !$canEditNotulen ? 'disabled' : '' ?>><?= h($notulen[$f['id']]) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Dokumentasi -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon ic-green"><i class="fa-solid fa-camera"></i></div>
                <div>
                    <div class="section-title">Dokumentasi Rapat</div>
                    <div class="section-sub">Upload foto dokumentasi untuk <?= h($sesiLabel) ?></div>
                </div>
            </div>
            <div class="section-body">
                <div class="upload-box no-print">
                    <label class="upload-label" for="fileUpload">
                        <i class="fa-solid fa-upload"></i> Pilih Foto Dokumentasi
                    </label>
                    <input id="fileUpload" type="file" multiple accept=".jpg,.jpeg,.png,.webp"
                        onchange="uploadFiles(this.files)" <?= !$canEditNotulen ? 'disabled' : '' ?>>
                    <div class="upload-note">Format: JPG, JPEG, PNG, WEBP. Bisa upload lebih dari satu foto.</div>
                </div>
                <div id="galleryWrap" style="margin-top:12px">
                    <?php if (empty($dokumentasi)): ?>
                        <div class="empty-st" id="galleryEmpty"><i class="fa-solid fa-images"></i>Belum ada foto dokumentasi untuk sesi ini</div>
                    <?php else: ?>
                        <div class="gallery" id="galleryGrid">
                            <?php foreach ($dokumentasi as $img): ?>
                                <div class="gallery-item" data-id="<?= (int)$img['id'] ?>">
                                    <img src="<?= h($img['file_path']) ?>" class="gallery-thumb" alt="Dokumentasi" onclick="openImage('<?= h($img['file_path']) ?>')">
                                    <div class="gallery-meta">
                                        <div class="gallery-cap">Foto #<?= (int)$img['id'] ?></div>
                                        <button type="button" class="btn-del-img no-print" onclick="deleteFoto(<?= (int)$img['id'] ?>)" <?= !$canEditNotulen ? 'disabled' : '' ?>>
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="empty-st" id="galleryEmpty" style="display:none"><i class="fa-solid fa-images"></i>Belum ada foto dokumentasi untuk sesi ini</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Action Card -->
        <div class="action-card no-print">
            <button type="button" class="btn-submit" onclick="saveNotulen(true)" id="btnSave" <?= !$canEditNotulen ? 'disabled' : '' ?>>
                <i class="fa-solid fa-floppy-disk"></i> Simpan Notulen
            </button>
            <div class="autosave" id="autosaveStatus">
                <i class="fa-solid fa-cloud-arrow-up" style="color:var(--blue)"></i>
                <?= $canEditNotulen ? 'Siap disimpan' : 'Menunggu hari kegiatan' ?>
            </div>
            <div class="privacy">
                <i class="fa-solid fa-shield-halved" style="color:var(--green)"></i>
                Notulen disimpan per sesi dan dapat diperbarui kembali
            </div>
        </div>

    </div>
</main>

<!-- Image Modal -->
<div id="imgModal" class="modal-ov hidden">
    <div style="position:absolute;inset:0" onclick="closeImage()"></div>
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h2>Preview Dokumentasi</h2>
                <p>Pusdiklat Mahkamah Agung RI</p>
            </div>
            <button type="button" class="icon-btn" onclick="closeImage()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="display:flex;align-items:center;justify-content:center">
            <img id="imgPreview" src="" class="modal-img" alt="Dokumentasi">
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
    const BOOKING_ID = <?= $bookingId ?>;
    const PIN = <?= json_encode($pin) ?>;
    const SELECTED_DATE = <?= json_encode($selectedDate) ?>;
    const SELECTED_DAY = <?= (int)$selectedDay ?>;
    const CAN_EDIT = <?= $canEditNotulen ? 'true' : 'false' ?>;
    const SESI_LABEL = <?= json_encode($sesiLabel) ?>;
    const SELF_URL = location.pathname + '?id=' + BOOKING_ID + '&pin=' + encodeURIComponent(PIN) + '&tanggal=' + encodeURIComponent(SELECTED_DATE);

    let docsData = <?= json_encode(array_values($dokumentasi), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let autosaveTimer = null;
    let autosaveBusy = false;
    let lastSavedPayload = '';

    /* ═══════════════════════════════════════
       SPEECH-TO-TEXT ENGINE
    ═══════════════════════════════════════ */
    let recognition = null;
    let sttActive = false;
    let sttContinuous = true;
    let sttTarget = 'pembahasan';
    let sttLang = 'id-ID';
    let sessionTranscript = '';
    let interimText = '';
    let waveInterval = null;
    let quickRecordTarget = null;
    let quickRecordTimer = null;

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    function initSTT() {
        if (!SpeechRecognition) {
            document.getElementById('sttUnsupported').style.display = 'flex';
            document.getElementById('sttPanel').style.display = 'none';
            return;
        }
        setupRecognition();
    }

    function setupRecognition() {
        if (recognition) {
            try {
                recognition.abort();
            } catch (e) {}
        }
        recognition = new SpeechRecognition();
        recognition.lang = sttLang;
        recognition.continuous = sttContinuous;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;

        recognition.onstart = () => {
            sttActive = true;
            setBadge('Merekam...', '#ef4444');
            setStatusText('Sedang merekam — bicara sekarang', true, false);
            document.getElementById('mainMicBtn').classList.add('recording');
            document.getElementById('mainMicIcon').className = 'fa-solid fa-stop';
            startWaveform();
            updateFieldHighlight();
        };

        recognition.onresult = (e) => {
            let interim = '';
            let final = '';
            for (let i = e.resultIndex; i < e.results.length; i++) {
                const t = e.results[i][0].transcript;
                if (e.results[i].isFinal) final += t + ' ';
                else interim += t;
            }
            interimText = interim;
            document.getElementById('sttInterim').textContent = interim || '—';
            document.getElementById('sttInterim').className = 'stt-interim' + (interim ? ' live' : '');

            if (final.trim()) {
                sessionTranscript += final;
                appendToField(final.trim());
                updateTranscriptPreview();
            }
        };

        recognition.onerror = (e) => {
            const errMap = {
                'not-allowed': 'Izin mikrofon ditolak. Aktifkan di pengaturan browser.',
                'no-speech': 'Tidak terdeteksi suara. Coba lagi.',
                'audio-capture': 'Tidak ada mikrofon yang terdeteksi.',
                'network': 'Gagal terhubung ke server speech. Cek koneksi internet.',
                'aborted': null,
            };
            const msg = errMap[e.error];
            if (msg) setStatusText(msg, false, true);
            if (e.error !== 'no-speech') stopStt();
        };

        recognition.onend = () => {
            if (sttActive && sttContinuous) {
                try {
                    recognition.start();
                    return;
                } catch (ex) {}
            }
            stopSttUI();
        };
    }

    function toggleMainStt() {
        if (!recognition) return;
        if (sttActive) stopStt();
        else startStt();
    }

    function startStt() {
        if (!recognition) return;
        setupRecognition();
        try {
            recognition.start();
        } catch (e) {
            setStatusText('Gagal memulai: ' + e.message, false, true);
        }
    }

    function stopStt() {
        sttActive = false;
        try {
            recognition.stop();
        } catch (e) {}
        stopSttUI();
    }

    function stopSttUI() {
        sttActive = false;
        setBadge('Siap', '#60a5fa');
        setStatusText('Rekaman selesai', false, false);
        document.getElementById('mainMicBtn').classList.remove('recording');
        document.getElementById('mainMicIcon').className = 'fa-solid fa-microphone';
        document.getElementById('sttInterim').textContent = '—';
        document.getElementById('sttInterim').className = 'stt-interim';
        stopWaveform();
        clearFieldHighlight();
    }

    function setBadge(text, color) {
        const b = document.getElementById('sttBadge');
        b.textContent = text;
        b.style.color = color;
    }

    function setStatusText(text, active, error) {
        const el = document.getElementById('sttStatusText');
        el.textContent = text;
        el.className = 'stt-status-text' + (active ? ' active' : '') + (error ? ' error' : '');
    }

    function startWaveform() {
        const bars = document.querySelectorAll('.stt-bar');
        waveInterval = setInterval(() => {
            bars.forEach(bar => {
                const h = sttActive ? (4 + Math.random() * 22) : 4;
                bar.style.height = h + 'px';
                bar.className = 'stt-bar' + (sttActive ? ' active' : '');
            });
        }, 100);
    }

    function stopWaveform() {
        clearInterval(waveInterval);
        document.querySelectorAll('.stt-bar').forEach(b => {
            b.style.height = '4px';
            b.className = 'stt-bar';
        });
    }

    function updateTranscriptPreview() {
        const el = document.getElementById('sttTranscriptPreview');
        const t = sessionTranscript.trim();
        el.innerHTML = t ? '<span>' + escHtml(t) + '</span>' : '<span style="color:#334155;font-style:italic">Transkrip sesi akan muncul di sini...</span>';
        el.scrollTop = el.scrollHeight;
    }

    function appendToField(text) {
        const target = quickRecordTarget || sttTarget;
        const el = document.getElementById(target);
        if (!el) return;
        const cur = el.value;
        el.value = cur ? cur + '\n' + text : text;
        el.dispatchEvent(new Event('input'));
        el.scrollTop = el.scrollHeight;
    }

    function insertToField() {
        if (!sessionTranscript.trim()) {
            showToast('Belum ada transkrip untuk disisipkan');
            return;
        }
        const target = quickRecordTarget || sttTarget;
        const el = document.getElementById(target);
        if (!el) return;
        const cur = el.value;
        el.value = cur ? cur + '\n' + sessionTranscript.trim() : sessionTranscript.trim();
        el.dispatchEvent(new Event('input'));
        showToast('✓ Transkrip disisipkan ke field ' + getFieldLabel(target));
    }

    function clearTranscript() {
        sessionTranscript = '';
        interimText = '';
        updateTranscriptPreview();
        document.getElementById('sttInterim').textContent = '—';
    }

    function clearFieldContent() {
        const target = quickRecordTarget || sttTarget;
        const el = document.getElementById(target);
        if (!el) return;
        if (!confirm('Kosongkan isi field "' + getFieldLabel(target) + '"?')) return;
        el.value = '';
        el.dispatchEvent(new Event('input'));
        showToast('Field dikosongkan');
    }

    function updateFieldHighlight() {
        document.querySelectorAll('.f-input,.f-textarea').forEach(el => el.classList.remove('stt-target-active'));
        const target = quickRecordTarget || sttTarget;
        const el = document.getElementById(target);
        if (el && sttActive) el.classList.add('stt-target-active');
    }

    function clearFieldHighlight() {
        document.querySelectorAll('.f-input,.f-textarea').forEach(el => el.classList.remove('stt-target-active'));
    }

    function getFieldLabel(id) {
        const map = {
            pembahasan: 'Pembahasan',
            peserta_text: 'Peserta',
            keputusan: 'Keputusan',
            tindak_lanjut: 'Tindak Lanjut',
            agenda: 'Agenda',
            pimpinan_rapat: 'Pimpinan',
            moderator: 'Moderator',
            notulis: 'Notulis'
        };
        return map[id] || id;
    }

    function updateSttLang() {
        sttLang = document.getElementById('sttLang').value;
        if (sttActive) {
            stopStt();
            setTimeout(startStt, 300);
        }
    }

    function toggleContinuous() {
        sttContinuous = !sttContinuous;
        const btn = document.getElementById('sttContinuousBtn');
        btn.textContent = sttContinuous ? 'Kontinyu' : 'Sekali';
        btn.className = 'stt-mode-btn' + (sttContinuous ? ' on' : '');
        if (sttActive) {
            stopStt();
            setTimeout(startStt, 300);
        }
    }

    /* Quick record via field mic button */
    function quickRecord(fieldId) {
        if (!SpeechRecognition) {
            showToast('Browser tidak mendukung Speech Recognition');
            return;
        }
        const btn = document.querySelector(`[onclick="quickRecord('${fieldId}')"]`);

        if (sttActive && quickRecordTarget === fieldId) {
            stopStt();
            quickRecordTarget = null;
            if (btn) btn.classList.remove('active');
            return;
        }

        if (sttActive) stopStt();
        quickRecordTarget = fieldId;

        // select target in main panel
        document.querySelectorAll('.stt-target-btn').forEach(b => {
            b.classList.toggle('selected', b.dataset.target === fieldId);
        });
        sttTarget = fieldId;

        document.querySelectorAll('.field-mic-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        clearTranscript();
        setupRecognition();

        const origOnend = recognition.onend;
        recognition.onend = function() {
            origOnend && origOnend.call(recognition);
            if (btn) btn.classList.remove('active');
            quickRecordTarget = null;
        };

        try {
            recognition.start();
            showToast('Merekam ke field "' + getFieldLabel(fieldId) + '"');
        } catch (e) {
            showToast('Gagal memulai: ' + e.message);
            if (btn) btn.classList.remove('active');
            quickRecordTarget = null;
        }
    }

    /* Target selector in STT panel */
    document.querySelectorAll('.stt-target-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.stt-target-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            sttTarget = this.dataset.target;
            updateFieldHighlight();
        });
    });

    /* ═══════════════════════════════════════
       CORE NOTULEN FUNCTIONS
    ═══════════════════════════════════════ */
    const $id = id => document.getElementById(id);
    const escHtml = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const esc = escHtml;

    function showToast(msg, dur = 2500) {
        const t = $id('toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), dur);
    }

    function setAutosaveStatus(text, color = 'var(--muted)', icon = 'fa-cloud-arrow-up') {
        const el = $id('autosaveStatus');
        if (!el) return;
        el.innerHTML = `<i class="fa-solid ${icon}" style="color:${color}"></i> ${text}`;
    }

    function getNotulenPayload() {
        return {
            agenda: $id('agenda').value.trim(),
            pimpinan_rapat: $id('pimpinan_rapat').value.trim(),
            moderator: $id('moderator').value.trim(),
            notulis: $id('notulis').value.trim(),
            peserta_text: $id('peserta_text').value.trim(),
            pembahasan: $id('pembahasan').value.trim(),
            keputusan: $id('keputusan').value.trim(),
            tindak_lanjut: $id('tindak_lanjut').value.trim()
        };
    }

    function renderGallery() {
        const wrap = $id('galleryWrap');
        const empty = $id('galleryEmpty');
        let grid = $id('galleryGrid');
        if (!docsData.length) {
            if (grid) grid.remove();
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';
        if (!grid) {
            grid = document.createElement('div');
            grid.id = 'galleryGrid';
            grid.className = 'gallery';
            wrap.prepend(grid);
        }
        grid.innerHTML = docsData.map(img => `
        <div class="gallery-item" data-id="${img.id}">
            <img src="${esc(img.file_path)}" class="gallery-thumb" alt="Dokumentasi" onclick="openImage('${esc(img.file_path)}')">
            <div class="gallery-meta">
                <div class="gallery-cap">Foto #${img.id}</div>
                <button type="button" class="btn-del-img no-print" onclick="deleteFoto(${img.id})" ${!CAN_EDIT?'disabled':''}>
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
    }

    async function saveNotulen(showManualToast = false) {
        if (!CAN_EDIT) {
            showToast('Notulen baru bisa diisi mulai tanggal kegiatan');
            return;
        }
        if (autosaveBusy) return;
        const payload = getNotulenPayload();
        const payloadString = JSON.stringify(payload);
        autosaveBusy = true;
        setAutosaveStatus('Menyimpan...', 'var(--blue)', 'fa-rotate-right');
        const fd = new FormData();
        fd.append('action', 'save_notulen');
        Object.entries(payload).forEach(([k, v]) => fd.append(k, v));
        try {
            const res = await fetch(SELF_URL, {
                method: 'POST',
                body: fd
            });
            const j = await res.json();
            if (j.status) {
                lastSavedPayload = payloadString;
                setAutosaveStatus('Tersimpan otomatis', 'var(--green)', 'fa-check-circle');
                if (showManualToast) showToast('✓ Notulen berhasil disimpan');
            } else {
                setAutosaveStatus('Gagal auto-save', 'var(--red)', 'fa-triangle-exclamation');
                showToast(j.message || 'Gagal menyimpan');
            }
        } catch (e) {
            setAutosaveStatus('Gagal auto-save', 'var(--red)', 'fa-triangle-exclamation');
            showToast('Error: ' + e.message);
        } finally {
            autosaveBusy = false;
        }
    }

    function queueAutosave() {
        if (!CAN_EDIT) return;
        clearTimeout(autosaveTimer);
        setAutosaveStatus('Perubahan terdeteksi...', 'var(--amber)', 'fa-pen');
        autosaveTimer = setTimeout(() => {
            const s = JSON.stringify(getNotulenPayload());
            if (s !== lastSavedPayload) saveNotulen(false);
            else setAutosaveStatus('Tidak ada perubahan', 'var(--muted)', 'fa-check');
        }, 1800);
    }

    async function uploadFiles(files) {
        if (!CAN_EDIT) {
            showToast('Upload foto baru bisa dilakukan mulai tanggal kegiatan');
            return;
        }
        if (!files || !files.length) return;
        const fd = new FormData();
        fd.append('action', 'upload_dokumentasi');
        [...files].forEach(f => fd.append('files[]', f));
        try {
            const res = await fetch(SELF_URL, {
                method: 'POST',
                body: fd
            });
            const j = await res.json();
            if (j.status) {
                showToast('✓ ' + (j.message || 'Upload berhasil'));
                await refreshData(false);
                $id('fileUpload').value = '';
            } else showToast(j.message || 'Upload gagal');
        } catch (e) {
            showToast('Error: ' + e.message);
        }
    }

    async function deleteFoto(id) {
        if (!CAN_EDIT) {
            showToast('Hapus foto baru bisa dilakukan mulai tanggal kegiatan');
            return;
        }
        if (!confirm('Hapus foto dokumentasi ini?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_foto');
        fd.append('id', id);
        try {
            const res = await fetch(SELF_URL, {
                method: 'POST',
                body: fd
            });
            const j = await res.json();
            if (j.status) {
                docsData = docsData.filter(x => String(x.id) !== String(id));
                renderGallery();
                showToast('✓ Foto berhasil dihapus');
            } else showToast(j.message || 'Gagal menghapus foto');
        } catch (e) {
            showToast('Error: ' + e.message);
        }
    }

    function openImage(src) {
        $id('imgPreview').src = src;
        $id('imgModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeImage() {
        $id('imgModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    async function refreshData(showMsg = true) {
        const icon = $id('refreshIcon');
        icon.classList.add('spin');
        try {
            const res = await fetch(SELF_URL + '&json=1');
            const j = await res.json();
            if (j.notulen) {
                ['agenda', 'pimpinan_rapat', 'moderator', 'notulis', 'peserta_text', 'pembahasan', 'keputusan', 'tindak_lanjut'].forEach(k => {
                    $id(k).value = j.notulen[k] || '';
                });
            }
            docsData = j.dokumentasi || [];
            renderGallery();
            lastSavedPayload = JSON.stringify(getNotulenPayload());
            setAutosaveStatus(CAN_EDIT ? 'Semua perubahan tersimpan' : 'Menunggu hari kegiatan', CAN_EDIT ? 'var(--green)' : 'var(--muted)', CAN_EDIT ? 'fa-check-circle' : 'fa-clock');
            if (showMsg) showToast('✓ Data diperbarui');
        } catch (e) {
            showToast('Gagal refresh data');
        } finally {
            setTimeout(() => icon.classList.remove('spin'), 700);
        }
    }

    ['agenda', 'pimpinan_rapat', 'moderator', 'notulis', 'peserta_text', 'pembahasan', 'keputusan', 'tindak_lanjut'].forEach(id => {
        const el = $id(id);
        if (el) {
            el.addEventListener('input', queueAutosave);
            el.addEventListener('change', queueAutosave);
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && CAN_EDIT) {
            const c = JSON.stringify(getNotulenPayload());
            if (c !== lastSavedPayload) saveNotulen(false);
        }
    });
    window.addEventListener('beforeunload', () => {
        if (!CAN_EDIT) return;
        const c = JSON.stringify(getNotulenPayload());
        if (c !== lastSavedPayload) saveNotulen(false);
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeImage();
    });

    /* Init */
    renderGallery();
    lastSavedPayload = JSON.stringify(getNotulenPayload());
    setAutosaveStatus(CAN_EDIT ? 'Semua perubahan tersimpan' : 'Menunggu hari kegiatan', CAN_EDIT ? 'var(--green)' : 'var(--muted)', CAN_EDIT ? 'fa-check-circle' : 'fa-clock');
    if (CAN_EDIT) initSTT();
</script>