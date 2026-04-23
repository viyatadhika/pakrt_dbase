<?php
require_once __DIR__ . '/config.php';
session_start();
date_default_timezone_set('Asia/Jakarta');

/* ── Helper ── */
function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function formatTanggalID($d)
{
    if (!$d) return '-';
    $b = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    $p = explode('-', substr($d, 0, 10));
    return count($p) === 3 ? $p[2] . ' ' . $b[(int)$p[1]] . ' ' . $p[0] : $d;
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
        return formatTanggalID($selectedDate);
    }
    return 'Day ' . $selectedDay . ' - ' . formatTanggalID($selectedDate);
}

/* ── Validasi parameter ── */
$bookingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bookingId <= 0) die('ID booking tidak valid');

/* ── Query booking ── */
$stmt = $conn->prepare("
    SELECT b.id, b.nama, b.start_date, b.end_date,
           b.jam_start, b.jam_end, b.jenis_lokasi, b.lokasi_external,
           COALESCE(r.nama_ruang,'') AS ruang,
           COALESCE(r.lokasi,'') AS lokasi_ruang
    FROM booking_ruang_rapat b
    LEFT JOIN ruang_rapat r ON r.id = b.room_id
    WHERE b.id = ? LIMIT 1
");
if (!$stmt) die('Query gagal: ' . $conn->error);
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$booking) die('Booking tidak ditemukan');

$lokasi = $booking['jenis_lokasi'] === 'external'
    ? ($booking['lokasi_external'] ?: '-')
    : trim(($booking['ruang'] ?: '-') . ($booking['lokasi_ruang'] ? ' - ' . $booking['lokasi_ruang'] : ''));

/* ── Aturan absensi ── */
$today = date('Y-m-d');
$isBeforeStart = $today < $booking['start_date'];
$canAttend = !$isBeforeStart;

$attendanceDate = $today;
$selectedDay = hitungDayKeMengikutiKegiatan(
    $booking['start_date'],
    $booking['end_date'],
    $attendanceDate
);

$isMultiDay = $booking['start_date'] !== $booking['end_date'];
$sesiLabel = formatSesiLabel($booking['start_date'], $booking['end_date'], $attendanceDate, $selectedDay);

$sessionKey = 'absensi_done_' . $bookingId . '_' . $attendanceDate;

/* ── Cek sudah absensi ── */
$alreadyDone = false;
$doneData = null;

if (isset($_SESSION[$sessionKey])) {
    $alreadyDone = true;
    $doneData = $_SESSION[$sessionKey];
}

if (!$alreadyDone && $canAttend) {
    $stmt = $conn->prepare("
        SELECT id, nama_peserta, tanggal_hadir, day_ke, created_at
        FROM absensi_rapat
        WHERE booking_id = ? AND tanggal_hadir = ? AND day_ke = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('isi', $bookingId, $attendanceDate, $selectedDay);
        $stmt->execute();
        $dbDone = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($dbDone) {
            $alreadyDone = true;
            $doneData = [
                'nama' => $dbDone['nama_peserta'] ?? '',
                'waktu' => !empty($dbDone['created_at']) ? date('d M Y, H:i', strtotime($dbDone['created_at'])) : '',
                'day_ke' => (int)($dbDone['day_ke'] ?? $selectedDay),
                'tanggal_hadir' => $dbDone['tanggal_hadir'] ?? $attendanceDate,
            ];
            $_SESSION[$sessionKey] = $doneData;
        }
    }
}

$success = '';
$error   = '';
$attendanceBlockedMessage = '';

if ($isBeforeStart) {
    $attendanceBlockedMessage = 'Absensi baru bisa dilakukan mulai tanggal ' . formatTanggalID($booking['start_date']) . '.';
}

/* ── Proses POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyDone && $canAttend) {
    $namaPeserta = trim($_POST['nama_peserta'] ?? '');
    $unitJabatan = trim($_POST['unit_jabatan'] ?? '');
    $instansi    = trim($_POST['instansi'] ?? '');
    $signature   = trim($_POST['signature_data'] ?? '');

    if ($namaPeserta === '' || $signature === '') {
        $error = 'Nama peserta dan tanda tangan wajib diisi.';
    } else {
        $stmt = $conn->prepare("
            SELECT id
            FROM absensi_rapat
            WHERE booking_id = ? AND tanggal_hadir = ? AND day_ke = ?
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('isi', $bookingId, $attendanceDate, $selectedDay);
            $stmt->execute();
            $dup = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($dup) {
                $alreadyDone = true;
                $doneData = [
                    'nama' => $namaPeserta,
                    'waktu' => date('d M Y, H:i'),
                    'day_ke' => $selectedDay,
                    'tanggal_hadir' => $attendanceDate
                ];
                $_SESSION[$sessionKey] = $doneData;
            }
        }

        if (!$alreadyDone) {
            $stmt = $conn->prepare("
                INSERT INTO absensi_rapat
                (booking_id, tanggal_hadir, day_ke, nama_peserta, unit_jabatan, instansi, tanda_tangan)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $error = 'Query gagal: ' . $conn->error;
            } else {
                $stmt->bind_param(
                    'isissss',
                    $bookingId,
                    $attendanceDate,
                    $selectedDay,
                    $namaPeserta,
                    $unitJabatan,
                    $instansi,
                    $signature
                );

                if ($stmt->execute()) {
                    $success = $namaPeserta;
                    $_SESSION[$sessionKey] = [
                        'nama' => $namaPeserta,
                        'waktu' => date('d M Y, H:i'),
                        'tanggal_hadir' => $attendanceDate,
                        'day_ke' => $selectedDay
                    ];
                    $alreadyDone = true;
                    $doneData = $_SESSION[$sessionKey];
                } else {
                    $error = 'Gagal menyimpan: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

$title = 'Absensi Rapat - ' . h($booking['nama']);
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
        --blue-dk: #0C447C;
        --blue-bd: #B5D4F4;
        --teal: #0F6E56;
        --teal-lt: #E1F5EE;
        --amber: #854F0B;
        --amber-lt: #FAEEDA;
        --green: #3B6D11;
        --green-lt: #EAF3DE;
        --green-bd: #C0DD97;
        --red: #A32D2D;
        --red-lt: #FCEBEB;
        --red-bd: #F7C1C1;
        --orange: #B45309;
        --orange-lt: #FFF7ED;
        --orange-bd: #FED7AA;
        --ink: #0f172a;
        --muted: #64748b;
        --border: rgba(0, 0, 0, .1);
        --bg: #f1f5f9;
        --white: #ffffff;
        --radius: 14px;
        --radius-lg: 20px;
    }

    html {
        scroll-behavior: smooth
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: var(--bg);
        color: var(--ink);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
        font-size: 15px
    }

    .topbar {
        background: var(--blue-dk);
        padding: 13px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 100
    }

    .topbar-brand {
        display: flex;
        align-items: center;
        gap: 10px
    }

    .topbar-icon {
        width: 36px;
        height: 36px;
        background: rgba(255, 255, 255, .15);
        border: 1px solid rgba(255, 255, 255, .2);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        color: #fff
    }

    .topbar-text {
        font-size: 13px;
        font-weight: 700;
        color: #fff
    }

    .topbar-sub {
        font-size: 11px;
        color: rgba(255, 255, 255, .55)
    }

    .topbar-time {
        font-size: 12px;
        color: rgba(255, 255, 255, .7);
        text-align: right
    }

    .hero {
        background: linear-gradient(150deg, var(--blue-dk) 0%, var(--blue) 55%, #0891b2 100%);
        padding: 24px 16px 58px;
        position: relative;
        overflow: hidden
    }

    .hero::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 36px;
        background: var(--bg);
        border-radius: 26px 26px 0 0
    }

    .hero-deco {
        position: absolute;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, .1)
    }

    .hero-deco-1 {
        width: 200px;
        height: 200px;
        top: -65px;
        right: -45px
    }

    .hero-deco-2 {
        width: 110px;
        height: 110px;
        top: 18px;
        right: 38px;
        background: rgba(255, 255, 255, .04)
    }

    .hero-inner {
        position: relative;
        z-index: 1
    }

    .hero-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .2);
        color: rgba(255, 255, 255, .9);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        padding: 5px 13px 5px 8px;
        border-radius: 999px;
        margin-bottom: 12px
    }

    .pulse {
        width: 7px;
        height: 7px;
        background: #4ade80;
        border-radius: 50%;
        animation: pulse 2s infinite;
        display: inline-block
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
            transform: scale(1)
        }

        50% {
            opacity: .5;
            transform: scale(.8)
        }
    }

    .hero-title {
        font-size: 26px;
        font-weight: 800;
        color: #fff;
        line-height: 1.2;
        letter-spacing: -.02em;
        margin-bottom: 8px
    }

    .hero-title span {
        background: linear-gradient(90deg, #93c5fd, #67e8f9);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text
    }

    .hero-sub {
        font-size: 13px;
        color: rgba(255, 255, 255, .65);
        line-height: 1.65;
        max-width: 300px
    }

    .form-wrap {
        padding: 0 14px 44px
    }

    .s-card {
        background: var(--white);
        border: .5px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 18px;
        margin-bottom: 12px;
        opacity: 0;
        transform: translateY(10px);
        animation: fadeUp .3s ease forwards
    }

    .s-card:nth-child(1) {
        animation-delay: .04s
    }

    .s-card:nth-child(2) {
        animation-delay: .10s
    }

    .s-card:nth-child(3) {
        animation-delay: .16s
    }

    .s-card:nth-child(4) {
        animation-delay: .22s
    }

    @keyframes fadeUp {
        to {
            opacity: 1;
            transform: translateY(0)
        }
    }

    .s-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        padding-bottom: 13px;
        border-bottom: .5px solid var(--border)
    }

    .s-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0
    }

    .ic-teal {
        background: var(--teal-lt);
        color: var(--teal)
    }

    .ic-blue {
        background: var(--blue-lt);
        color: var(--blue)
    }

    .ic-amber {
        background: var(--amber-lt);
        color: var(--amber)
    }

    .s-title {
        font-size: 14px;
        font-weight: 700
    }

    .s-sub {
        font-size: 11px;
        color: var(--muted);
        margin-top: 1px
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 11px
    }

    @media (min-width:768px) {
        .info-grid {
            grid-template-columns: 1fr 1fr;
            gap: 11px 20px
        }
    }

    .info-row {
        display: flex;
        align-items: flex-start;
        gap: 10px
    }

    .info-ico {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        background: var(--blue-lt);
        color: var(--blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        flex-shrink: 0;
        margin-top: 1px
    }

    .info-lbl {
        font-size: 10px;
        font-weight: 700;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .05em;
        line-height: 1;
        margin-bottom: 3px
    }

    .info-val {
        font-size: 14px;
        font-weight: 700;
        line-height: 1.35
    }

    .session-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: var(--blue-lt);
        border: 1px solid var(--blue-bd);
        color: var(--blue);
        font-size: 12px;
        font-weight: 800;
        border-radius: 999px;
        padding: 7px 12px;
        margin-top: 8px
    }

    .f-group {
        margin-bottom: 13px
    }

    .f-group:last-child {
        margin-bottom: 0
    }

    .f-label {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        font-weight: 700;
        color: var(--muted);
        margin-bottom: 6px
    }

    .f-label .req {
        color: var(--red);
        font-size: 14px;
        line-height: 1
    }

    .f-wrap {
        position: relative
    }

    .f-ico {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        color: var(--muted);
        pointer-events: none;
        transition: color .2s
    }

    .f-wrap:focus-within .f-ico {
        color: var(--blue-md)
    }

    .f-input {
        width: 100%;
        padding: 13px 13px 13px 38px;
        background: var(--bg);
        border: .5px solid var(--border);
        border-radius: var(--radius);
        font-size: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--ink);
        outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s
    }

    .f-input::placeholder {
        color: #94a3b8;
        font-size: 13px
    }

    .f-input:focus {
        border-color: var(--blue-md);
        box-shadow: 0 0 0 3px rgba(55, 138, 221, .1);
        background: var(--white)
    }

    .sig-wrap {
        border: .5px solid var(--border);
        border-radius: var(--radius);
        background: var(--white);
        overflow: hidden;
        position: relative
    }

    .sig-wrap canvas {
        display: block;
        width: 100%;
        height: 200px;
        cursor: crosshair;
        touch-action: none
    }

    .sig-ph {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        pointer-events: none;
        transition: opacity .3s
    }

    .sig-ph i {
        font-size: 24px;
        color: #cbd5e1
    }

    .sig-ph span {
        font-size: 12px;
        color: #94a3b8;
        font-weight: 700
    }

    .sig-line {
        position: absolute;
        bottom: 44px;
        left: 14px;
        right: 14px;
        height: 1px;
        background: var(--bg);
        pointer-events: none
    }

    .btn-clear {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        margin-top: 8px;
        border-radius: 10px;
        border: .5px solid var(--border);
        background: var(--white);
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
        font-family: 'Plus Jakarta Sans', sans-serif;
        cursor: pointer
    }

    .btn-clear:hover {
        border-color: var(--red-bd);
        color: var(--red);
        background: var(--red-lt)
    }

    .alert-err {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 13px 15px;
        border-radius: var(--radius);
        background: var(--red-lt);
        border: .5px solid var(--red-bd);
        color: var(--red);
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 12px;
        line-height: 1.5
    }

    .submit-card {
        background: var(--white);
        border: .5px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 18px;
        margin-bottom: 12px;
        opacity: 0;
        animation: fadeUp .3s .28s ease forwards
    }

    .btn-submit {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, var(--blue), #0891b2);
        color: #fff;
        border: none;
        border-radius: var(--radius);
        font-size: 15px;
        font-weight: 700;
        font-family: 'Plus Jakarta Sans', sans-serif;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 14px rgba(24, 95, 165, .3);
        transition: opacity .2s, transform .1s
    }

    .btn-submit:hover {
        opacity: .93
    }

    .btn-submit:active {
        transform: scale(.98)
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
        margin-top: 10px
    }

    @keyframes popIn {
        from {
            transform: scale(0) rotate(-15deg);
            opacity: 0
        }

        to {
            transform: scale(1) rotate(0);
            opacity: 1
        }
    }

    .success-wrap,
    .done-wrap {
        min-height: calc(100vh - 62px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 18px;
        text-align: center
    }

    .success-icon {
        width: 88px;
        height: 88px;
        background: linear-gradient(135deg, var(--blue), #0891b2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 34px;
        color: #fff;
        margin-bottom: 24px;
        box-shadow: 0 6px 24px rgba(24, 95, 165, .3);
        animation: popIn .5s cubic-bezier(.34, 1.56, .64, 1) both
    }

    .done-icon {
        width: 84px;
        height: 84px;
        background: var(--orange-lt);
        border: .5px solid var(--orange-bd);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: var(--orange);
        margin-bottom: 22px;
        animation: popIn .5s cubic-bezier(.34, 1.56, .64, 1) both
    }

    .success-title {
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -.02em;
        margin-bottom: 10px;
        animation: fadeUp .3s .12s ease both
    }

    .success-sub {
        font-size: 14px;
        color: var(--muted);
        line-height: 1.7;
        max-width: 320px;
        animation: fadeUp .3s .2s ease both
    }

    .success-card {
        margin-top: 20px;
        background: var(--white);
        border: .5px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 15px 18px;
        width: 100%;
        max-width: 310px;
        text-align: left;
        animation: fadeUp .3s .28s ease both
    }

    .success-chip {
        margin-top: 16px;
        background: var(--blue-lt);
        border: .5px solid var(--blue-bd);
        border-radius: 999px;
        padding: 8px 18px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 700;
        color: var(--blue);
        animation: fadeUp .3s .34s ease both
    }
</style>

<div class="topbar">
    <div class="topbar-brand">
        <div class="topbar-icon"><i class="fa-solid fa-clipboard-list"></i></div>
        <div>
            <div class="topbar-text">Absensi Rapat Digital</div>
            <div class="topbar-sub">Pusdiklat Mahkamah Agung RI</div>
        </div>
    </div>
    <div class="topbar-time">
        <div id="clockTime"><?= date('H:i') ?></div>
        <div><?= date('d M Y') ?></div>
    </div>
</div>

<?php if ($isBeforeStart): ?>
    <div class="done-wrap">
        <div class="done-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-.02em;margin-bottom:10px;animation:fadeUp .3s .12s ease both">
            Absensi Belum Dibuka
        </h1>
        <p style="font-size:14px;color:var(--muted);line-height:1.7;max-width:320px;animation:fadeUp .3s .2s ease both">
            <?= h($attendanceBlockedMessage) ?>
        </p>
        <div style="margin-top:18px;background:var(--white);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:14px 18px;width:100%;max-width:320px;text-align:left;animation:fadeUp .3s .28s ease both">
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:7px">Detail Rapat</div>
            <div style="font-size:15px;font-weight:700;color:var(--ink);margin-bottom:6px;line-height:1.4"><?= h($booking['nama']) ?></div>
            <div style="font-size:12px;color:var(--muted);display:flex;align-items:flex-start;gap:6px;margin-bottom:4px">
                <i class="fa-solid fa-location-dot" style="color:var(--blue);width:10px;margin-top:2px"></i>
                <span><?= h($lokasi) ?></span>
            </div>
            <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;margin-bottom:4px">
                <i class="fa-solid fa-calendar" style="color:var(--blue);width:10px"></i>
                <span>
                    <?= h(formatTanggalID($booking['start_date'])) ?>
                    <?php if ($booking['start_date'] !== $booking['end_date']): ?>
                        – <?= h(formatTanggalID($booking['end_date'])) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px">
                <i class="fa-solid fa-clock" style="color:var(--blue);width:10px"></i>
                <?= h(substr($booking['jam_start'], 0, 5)) ?> – <?= h(substr($booking['jam_end'], 0, 5)) ?> WIB
            </div>
        </div>
    </div>

<?php elseif ($success): ?>
    <div class="success-wrap">
        <div class="success-icon"><i class="fa-solid fa-check"></i></div>
        <h1 class="success-title">Absensi Tercatat!</h1>
        <p class="success-sub">
            Terima kasih, <strong style="color:var(--ink)"><?= h(explode(' ', $success)[0]) ?></strong>.
            Kehadiran Anda untuk <strong style="color:var(--ink)"><?= h($sesiLabel) ?></strong> telah berhasil dicatat.
        </p>
        <div class="success-card">
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Detail Rapat</div>
            <div style="font-size:15px;font-weight:700;color:var(--ink);margin-bottom:6px;line-height:1.4"><?= h($booking['nama']) ?></div>
            <div style="font-size:12px;color:var(--muted);display:flex;align-items:flex-start;gap:6px;margin-bottom:4px">
                <i class="fa-solid fa-location-dot" style="color:var(--blue);width:10px;margin-top:2px"></i>
                <span><?= h($lokasi) ?></span>
            </div>
            <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;margin-bottom:4px">
                <i class="fa-solid fa-calendar-day" style="color:var(--blue);width:10px"></i>
                <span><?= h($sesiLabel) ?></span>
            </div>
            <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px">
                <i class="fa-solid fa-clock" style="color:var(--blue);width:10px"></i>
                <?= h(substr($booking['jam_start'], 0, 5)) ?> – <?= h(substr($booking['jam_end'], 0, 5)) ?> WIB
            </div>
        </div>
        <div class="success-chip"><i class="fa-solid fa-clock"></i><?= date('d M Y, H:i') ?> WIB</div>
    </div>

<?php elseif ($alreadyDone): ?>
    <div class="done-wrap">
        <div class="done-icon"><i class="fa-solid fa-circle-check"></i></div>
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-.02em;margin-bottom:10px;animation:fadeUp .3s .12s ease both">Sudah Absensi Hari Ini</h1>
        <p style="font-size:14px;color:var(--muted);line-height:1.7;max-width:320px;animation:fadeUp .3s .2s ease both">
            <?php if (is_array($doneData)): ?>
                Anda (<strong style="color:var(--ink)"><?= h($doneData['nama']) ?></strong>) telah melakukan absensi untuk
                <strong style="color:var(--ink)"><?= h($sesiLabel) ?></strong>
                pada <strong style="color:var(--ink)"><?= h($doneData['waktu'] ?? '') ?></strong>.
            <?php else: ?>
                Anda sudah melakukan absensi untuk sesi ini.
            <?php endif; ?>
        </p>
        <div style="margin-top:18px;background:var(--white);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:14px 18px;width:100%;max-width:320px;text-align:left;animation:fadeUp .3s .28s ease both">
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:7px">Detail Rapat</div>
            <div style="font-size:15px;font-weight:700;color:var(--ink);margin-bottom:6px;line-height:1.4"><?= h($booking['nama']) ?></div>
            <div style="font-size:12px;color:var(--muted);display:flex;align-items:flex-start;gap:6px;margin-bottom:4px">
                <i class="fa-solid fa-location-dot" style="color:var(--blue);width:10px;margin-top:2px"></i>
                <span><?= h($lokasi) ?></span>
            </div>
            <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;margin-bottom:4px">
                <i class="fa-solid fa-calendar-day" style="color:var(--blue);width:10px"></i>
                <span><?= h($sesiLabel) ?></span>
            </div>
            <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px">
                <i class="fa-solid fa-clock" style="color:var(--blue);width:10px"></i>
                <?= h(substr($booking['jam_start'], 0, 5)) ?> – <?= h(substr($booking['jam_end'], 0, 5)) ?> WIB
            </div>
        </div>
        <div style="margin-top:14px;background:var(--orange-lt);border:.5px solid var(--orange-bd);border-radius:999px;padding:8px 18px;display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:var(--orange);animation:fadeUp .3s .34s ease both">
            <i class="fa-solid fa-triangle-exclamation"></i> Absensi hanya dapat dilakukan sekali per hari
        </div>
    </div>

<?php else: ?>
    <div class="hero">
        <div class="hero-deco hero-deco-1"></div>
        <div class="hero-deco hero-deco-2"></div>
        <div class="hero-inner">
            <div class="hero-chip">
                <div class="pulse"></div>Sesi Rapat Aktif
            </div>
            <h1 class="hero-title">Absensi<br><span>Rapat Digital</span></h1>
            <p class="hero-sub">Isi data kehadiran dan berikan tanda tangan sebagai konfirmasi.</p>
        </div>
    </div>

    <div class="form-wrap">
        <?php if ($error): ?>
            <div class="alert-err">
                <i class="fa-solid fa-circle-exclamation" style="flex-shrink:0;margin-top:1px"></i>
                <span><?= h($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="formAbsensi" novalidate>
            <div class="s-card">
                <div class="s-head">
                    <div class="s-icon ic-teal"><i class="fa-solid fa-calendar-check"></i></div>
                    <div>
                        <div class="s-title">Informasi Rapat</div>
                        <div class="s-sub">Detail kegiatan yang sedang berlangsung</div>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-file-lines"></i></div>
                        <div>
                            <div class="info-lbl">Nama Kegiatan</div>
                            <div class="info-val"><?= h($booking['nama']) ?></div>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-calendar"></i></div>
                        <div>
                            <div class="info-lbl">Tanggal</div>
                            <div class="info-val">
                                <?= h(formatTanggalID($booking['start_date'])) ?>
                                <?php if ($booking['start_date'] !== $booking['end_date']): ?>
                                    – <?= h(formatTanggalID($booking['end_date'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-location-dot"></i></div>
                        <div>
                            <div class="info-lbl">Lokasi</div>
                            <div class="info-val"><?= h($lokasi) ?></div>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-ico"><i class="fa-solid fa-clock"></i></div>
                        <div>
                            <div class="info-lbl">Waktu</div>
                            <div class="info-val"><?= h(substr($booking['jam_start'], 0, 5)) ?> – <?= h(substr($booking['jam_end'], 0, 5)) ?> WIB</div>
                        </div>
                    </div>
                </div>

                <div class="session-chip">
                    <i class="fa-solid fa-calendar-day"></i>
                    Absensi untuk <strong><?= h($sesiLabel) ?></strong>
                </div>
            </div>

            <div class="s-card">
                <div class="s-head">
                    <div class="s-icon ic-blue"><i class="fa-solid fa-id-card"></i></div>
                    <div>
                        <div class="s-title">Data Peserta</div>
                        <div class="s-sub">Informasi identitas kehadiran Anda</div>
                    </div>
                </div>

                <div class="f-group">
                    <label class="f-label"><i class="fa-solid fa-user" style="font-size:10px"></i>Nama Lengkap <span class="req">*</span></label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-user"></i>
                        <input type="text" name="nama_peserta" class="f-input" id="inputNama" placeholder="Masukkan nama lengkap" value="<?= h($_POST['nama_peserta'] ?? '') ?>" maxlength="150" required autocomplete="name">
                    </div>
                </div>

                <div class="f-group">
                    <label class="f-label"><i class="fa-solid fa-briefcase" style="font-size:10px"></i>Unit / Jabatan</label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-briefcase"></i>
                        <input type="text" name="unit_jabatan" class="f-input" placeholder="Contoh: Kabag Kepegawaian" value="<?= h($_POST['unit_jabatan'] ?? '') ?>" maxlength="150" autocomplete="organization-title">
                    </div>
                </div>

                <div class="f-group">
                    <label class="f-label"><i class="fa-solid fa-building" style="font-size:10px"></i>Instansi / Unit Kerja</label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-building"></i>
                        <input type="text" name="instansi" class="f-input" placeholder="Contoh: Pusdiklat Mahkamah Agung RI" value="<?= h($_POST['instansi'] ?? '') ?>" maxlength="200" autocomplete="organization">
                    </div>
                </div>
            </div>

            <div class="s-card">
                <div class="s-head">
                    <div class="s-icon ic-amber"><i class="fa-solid fa-signature"></i></div>
                    <div>
                        <div class="s-title">Tanda Tangan</div>
                        <div class="s-sub">Tanda tangani di area di bawah ini</div>
                    </div>
                </div>
                <div class="sig-wrap">
                    <canvas id="signature-pad"></canvas>
                    <div class="sig-ph" id="sigPh">
                        <i class="fa-solid fa-pen-nib"></i>
                        <span>Tanda tangan di sini</span>
                    </div>
                    <div class="sig-line"></div>
                </div>
                <input type="hidden" name="signature_data" id="signature_data">
                <button type="button" onclick="clearPad()" class="btn-clear">
                    <i class="fa-solid fa-eraser"></i> Hapus
                </button>
            </div>

            <div class="submit-card">
                <button type="submit" class="btn-submit" id="btnSubmit">
                    <i class="fa-solid fa-signature"></i> Simpan Kehadiran
                </button>
                <div class="privacy">
                    <i class="fa-solid fa-shield-halved" style="color:var(--green)"></i>
                    Data kehadiran Anda disimpan secara aman
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

<script>
    function updateClock() {
        const el = document.getElementById('clockTime');
        if (!el) return;
        const n = new Date();
        el.textContent = String(n.getHours()).padStart(2, '0') + ':' + String(n.getMinutes()).padStart(2, '0');
    }
    setInterval(updateClock, 10000);

    const canvas = document.getElementById('signature-pad');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        const ph = document.getElementById('sigPh');
        let drawing = false;
        let hasStroke = false;

        function setup() {
            const r = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * r;
            canvas.height = 200 * r;
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(r, r);
            ctx.lineWidth = 2.4;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#0f172a';
        }

        function pos(e) {
            const r = canvas.getBoundingClientRect();
            if (e.touches && e.touches[0]) return {
                x: e.touches[0].clientX - r.left,
                y: e.touches[0].clientY - r.top
            };
            return {
                x: e.clientX - r.left,
                y: e.clientY - r.top
            };
        }

        function start(e) {
            drawing = true;
            const p = pos(e);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            e.preventDefault();
            if (!hasStroke) {
                hasStroke = true;
                if (ph) ph.style.opacity = '0';
            }
        }

        function move(e) {
            if (!drawing) return;
            const p = pos(e);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            e.preventDefault();
        }

        function stop() {
            drawing = false;
        }

        window.clearPad = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasStroke = false;
            if (ph) ph.style.opacity = '1';
        };

        window.addEventListener('resize', setup);
        setup();

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', stop);
        canvas.addEventListener('mouseleave', stop);
        canvas.addEventListener('touchstart', start, {
            passive: false
        });
        canvas.addEventListener('touchmove', move, {
            passive: false
        });
        canvas.addEventListener('touchend', stop);
        canvas.addEventListener('touchcancel', stop);

        const form = document.getElementById('formAbsensi');
        if (form) {
            form.addEventListener('submit', e => {
                const nama = (document.getElementById('inputNama')?.value || '').trim();
                if (!nama) {
                    e.preventDefault();
                    alert('Nama peserta wajib diisi.');
                    return;
                }
                if (!hasStroke) {
                    e.preventDefault();
                    alert('Tanda tangan belum diisi.');
                    return;
                }
                document.getElementById('signature_data').value = canvas.toDataURL('image/png');
                const btn = document.getElementById('btnSubmit');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
                }
            });
        }
    }
</script>