<?php
require_once __DIR__ . '/config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$bookingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pin       = trim($_GET['pin'] ?? '');

if ($bookingId <= 0 || $pin === '') {
    if (isset($_POST['action']) || isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Akses ditolak']);
        exit;
    }
    die('Akses ditolak. PIN wajib diisi.');
}

$stmt = $conn->prepare("
    SELECT
        b.id,
        b.pin,
        b.nama,
        b.peminjam,
        b.start_date,
        b.end_date,
        b.jam_start,
        b.jam_end,
        b.jenis_lokasi,
        b.lokasi_external,
        COALESCE(r.nama_ruang,'') AS ruang,
        COALESCE(r.lokasi,'') AS lokasi_ruang
    FROM booking_ruang_rapat b
    LEFT JOIN ruang_rapat r ON r.id = b.room_id
    WHERE b.id = ? AND b.pin = ?
    LIMIT 1
");

if (!$stmt) {
    die('Query booking gagal: ' . $conn->error);
}

$stmt->bind_param('is', $bookingId, $pin);
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

$lokasi = $booking['jenis_lokasi'] === 'external'
    ? ($booking['lokasi_external'] ?: '-')
    : trim($booking['ruang'] . ($booking['lokasi_ruang'] ? ' - ' . $booking['lokasi_ruang'] : ''));

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmtDate($d)
{
    if (!$d) return '−';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    $p = explode('-', substr($d, 0, 10));
    if (count($p) !== 3) return $d;
    return $p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}

function fmtTime($t)
{
    return $t ? substr($t, 0, 5) : '−';
}

function ensureUploadDir($dir)
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return is_dir($dir);
}

/* =========================
   AJAX / INLINE ACTIONS
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

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
            (booking_id, agenda, pimpinan_rapat, moderator, notulis, peserta_text, pembahasan, keputusan, tindak_lanjut)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                agenda = VALUES(agenda),
                pimpinan_rapat = VALUES(pimpinan_rapat),
                moderator = VALUES(moderator),
                notulis = VALUES(notulis),
                peserta_text = VALUES(peserta_text),
                pembahasan = VALUES(pembahasan),
                keputusan = VALUES(keputusan),
                tindak_lanjut = VALUES(tindak_lanjut)
        ");

        if (!$stmt) {
            echo json_encode(['status' => false, 'message' => 'Query simpan gagal: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param(
            'issssssss',
            $bookingId,
            $agenda,
            $pimpinan,
            $moderator,
            $notulis,
            $pesertaText,
            $pembahasan,
            $keputusan,
            $tindakLanjut
        );

        if ($stmt->execute()) {
            echo json_encode(['status' => true, 'message' => 'Notulen berhasil disimpan']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Gagal menyimpan: ' . $stmt->error]);
        }

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
            echo json_encode(['status' => false, 'message' => 'Folder uploads/notulen tidak tersedia']);
            exit;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $saved = 0;

        $names = $_FILES['files']['name'] ?? [];
        $tmps  = $_FILES['files']['tmp_name'] ?? [];
        $errs  = $_FILES['files']['error'] ?? [];

        for ($i = 0; $i < count($names); $i++) {
            if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

            $orig = $names[$i];
            $tmp  = $tmps[$i];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed, true)) continue;

            $newName = 'notulen_' . $bookingId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destFs  = $uploadDirFs . $newName;
            $destDb  = $uploadDirDb . $newName;

            if (move_uploaded_file($tmp, $destFs)) {
                $s = $conn->prepare("INSERT INTO notulen_dokumentasi (booking_id, file_path) VALUES (?, ?)");
                if ($s) {
                    $s->bind_param('is', $bookingId, $destDb);
                    $s->execute();
                    $s->close();
                    $saved++;
                }
            }
        }

        echo json_encode([
            'status' => $saved > 0,
            'message' => $saved > 0 ? ($saved . ' foto berhasil diupload') : 'Tidak ada file yang berhasil diupload'
        ]);
        exit;
    }

    if ($action === 'delete_foto') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => false, 'message' => 'ID tidak valid']);
            exit;
        }

        $s = $conn->prepare("SELECT file_path FROM notulen_dokumentasi WHERE id = ? AND booking_id = ?");
        if (!$s) {
            echo json_encode(['status' => false, 'message' => 'Query gagal']);
            exit;
        }

        $s->bind_param('ii', $id, $bookingId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();

        if (!$row) {
            echo json_encode(['status' => false, 'message' => 'Foto tidak ditemukan']);
            exit;
        }

        $fsPath = __DIR__ . '/' . $row['file_path'];
        if (is_file($fsPath)) {
            @unlink($fsPath);
        }

        $d = $conn->prepare("DELETE FROM notulen_dokumentasi WHERE id = ? AND booking_id = ?");
        $d->bind_param('ii', $id, $bookingId);
        $d->execute();
        $ok = $d->affected_rows > 0;
        $d->close();

        echo json_encode([
            'status' => $ok,
            'message' => $ok ? 'Foto berhasil dihapus' : 'Gagal menghapus foto'
        ]);
        exit;
    }

    echo json_encode(['status' => false, 'message' => 'Action tidak dikenal']);
    exit;
}

/* =========================
   JSON REFRESH
   ========================= */
if (isset($_GET['json']) && $_GET['json'] === '1') {
    $notulen = [
        'agenda' => '',
        'pimpinan_rapat' => '',
        'moderator' => '',
        'notulis' => '',
        'peserta_text' => '',
        'pembahasan' => '',
        'keputusan' => '',
        'tindak_lanjut' => ''
    ];

    $s = $conn->prepare("
        SELECT agenda, pimpinan_rapat, moderator, notulis, peserta_text, pembahasan, keputusan, tindak_lanjut
        FROM notulen_rapat
        WHERE booking_id = ?
        LIMIT 1
    ");
    if ($s) {
        $s->bind_param('i', $bookingId);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        if ($r) $notulen = $r;
        $s->close();
    }

    $docs = [];
    $s = $conn->prepare("SELECT id, file_path, created_at FROM notulen_dokumentasi WHERE booking_id = ? ORDER BY id DESC");
    if ($s) {
        $s->bind_param('i', $bookingId);
        $s->execute();
        $rr = $s->get_result();
        while ($row = $rr->fetch_assoc()) $docs[] = $row;
        $s->close();
    }

    header('Content-Type: application/json');
    echo json_encode([
        'notulen' => $notulen,
        'dokumentasi' => $docs
    ]);
    exit;
}

/* =========================
   INITIAL DATA
   ========================= */
$notulen = [
    'agenda' => '',
    'pimpinan_rapat' => '',
    'moderator' => '',
    'notulis' => '',
    'peserta_text' => '',
    'pembahasan' => '',
    'keputusan' => '',
    'tindak_lanjut' => ''
];

$stmt = $conn->prepare("
    SELECT agenda, pimpinan_rapat, moderator, notulis, peserta_text, pembahasan, keputusan, tindak_lanjut
    FROM notulen_rapat
    WHERE booking_id = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $notulen = $row;
    $stmt->close();
}

$dokumentasi = [];
$stmt = $conn->prepare("SELECT id, file_path, created_at FROM notulen_dokumentasi WHERE booking_id = ? ORDER BY id DESC");
if ($stmt) {
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $dokumentasi[] = $row;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Notulen Rapat — <?= h($booking['nama']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
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
            --white: #ffffff;
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
            padding: 7px 14px 5px
        }

        .hdr-titles {
            flex: 1;
            min-width: 0
        }

        /* ===== FONT SIZES DIPERBESAR ===== */
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

        .hdr-offset {
            padding-top: 58px
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 4px 14px 4px;
            margin: 0
        }

        .toolbar-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--blue);
            line-height: 1.2
        }

        .toolbar-sub {
            font-size: 11px;
            color: var(--muted);
            margin-top: 1px;
            line-height: 1.2
        }

        .btn-primary-sm {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 9px 13px;
            background: var(--blue-lt);
            border: .5px solid var(--blue-bd);
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 700;
            color: var(--blue);
            cursor: pointer
        }

        .btn-primary-sm:hover {
            background: var(--blue-bd)
        }

        .section-card {
            margin: 0 14px 6px;
            background: var(--white);
            border: .5px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden
        }

        .section-head {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 14px 8px;
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

        .f-group {
            margin-bottom: 12px
        }

        .f-group:last-child {
            margin-bottom: 0
        }

        .f-lbl {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 5px;
            display: block;
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
            outline: none
        }

        .f-input:focus,
        .f-textarea:focus {
            border-color: var(--blue-md);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(55, 138, 221, .1)
        }

        .f-textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.65
        }

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
            padding: 9px 10px;
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
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 8px;
            background: var(--red-lt);
            color: var(--red);
            cursor: pointer;
            flex-shrink: 0;
            font-size: 13px
        }

        .btn-del-img:hover {
            background: var(--red-bd)
        }

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

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        .spin {
            animation: spin .6s linear infinite
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

        main.hdr-offset>.toolbar:first-child {
            padding-top: 0;
            padding-bottom: 2px;
        }

        main.hdr-offset>.section-card:first-of-type {
            margin-top: 0;
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
        }
    </style>
</head>

<body>

    <header class="sticky-hdr no-print">
        <div class="hdr-top">
            <div class="hdr-titles">
                <h1>Notulen Rapat</h1>
                <p><?= h($booking['nama']) ?></p>
            </div>
            <div class="hdr-actions">
                <button type="button" class="icon-btn" onclick="window.location.href='notulen_export.php?id=<?= $bookingId ?>&pin=<?= urlencode($pin) ?>'" title="Export PDF">
                    <i class="fa-solid fa-download"></i>
                </button>
                <button type="button" class="icon-btn" onclick="refreshData()" title="Refresh">
                    <i class="fa-solid fa-rotate-right" id="refreshIcon"></i>
                </button>
            </div>
        </div>
    </header>

    <main class="hdr-offset">
        <div class="toolbar no-print">
            <div>
                <div class="toolbar-title">Dokumen Notulen Digital</div>
                <div class="toolbar-sub">Isi notulen dapat diedit kembali kapan saja</div>
            </div>
            <button type="button" class="btn-primary-sm" onclick="saveNotulen(true)">
                <i class="fa-solid fa-floppy-disk"></i> Simpan
            </button>
        </div>

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
                                <div class="info-lbl">Tanggal</div>
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
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-head">
                <div class="section-icon ic-blue"><i class="fa-solid fa-pen"></i></div>
                <div>
                    <div class="section-title">Identitas Notulen</div>
                    <div class="section-sub">Agenda dan petugas rapat</div>
                </div>
            </div>
            <div class="section-body">
                <div class="f-group">
                    <label class="f-lbl">Agenda</label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-bullseye"></i>
                        <input id="agenda" type="text" class="f-input" value="<?= h($notulen['agenda']) ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="f-group">
                        <label class="f-lbl">Pimpinan Rapat</label>
                        <div class="f-wrap">
                            <i class="f-ico fa-solid fa-user-tie"></i>
                            <input id="pimpinan_rapat" type="text" class="f-input" value="<?= h($notulen['pimpinan_rapat']) ?>">
                        </div>
                    </div>
                    <div class="f-group">
                        <label class="f-lbl">Moderator</label>
                        <div class="f-wrap">
                            <i class="f-ico fa-solid fa-microphone"></i>
                            <input id="moderator" type="text" class="f-input" value="<?= h($notulen['moderator']) ?>">
                        </div>
                    </div>
                </div>

                <div class="f-group">
                    <label class="f-lbl">Notulis</label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-pencil"></i>
                        <input id="notulis" type="text" class="f-input" value="<?= h($notulen['notulis']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-head">
                <div class="section-icon ic-amber"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="section-title">Isi Notulen</div>
                    <div class="section-sub">Peserta, pembahasan, keputusan, dan tindak lanjut</div>
                </div>
            </div>
            <div class="section-body">
                <div class="f-group">
                    <label class="f-lbl">Peserta</label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-users"></i>
                        <textarea id="peserta_text" class="f-textarea"><?= h($notulen['peserta_text']) ?></textarea>
                    </div>
                </div>

                <div class="f-group">
                    <label class="f-lbl">Pembahasan</label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-comments"></i>
                        <textarea id="pembahasan" class="f-textarea"><?= h($notulen['pembahasan']) ?></textarea>
                    </div>
                </div>

                <div class="f-group">
                    <label class="f-lbl">Keputusan</label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-circle-check"></i>
                        <textarea id="keputusan" class="f-textarea"><?= h($notulen['keputusan']) ?></textarea>
                    </div>
                </div>

                <div class="f-group">
                    <label class="f-lbl">Tindak Lanjut</label>
                    <div class="f-wrap">
                        <i class="f-ico fa-solid fa-list-check"></i>
                        <textarea id="tindak_lanjut" class="f-textarea"><?= h($notulen['tindak_lanjut']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-head">
                <div class="section-icon ic-green"><i class="fa-solid fa-camera"></i></div>
                <div>
                    <div class="section-title">Dokumentasi Rapat</div>
                    <div class="section-sub">Upload foto dokumentasi dan kelola galeri kegiatan</div>
                </div>
            </div>
            <div class="section-body">
                <div class="upload-box no-print">
                    <label class="upload-label" for="fileUpload"><i class="fa-solid fa-upload"></i> Pilih Foto Dokumentasi</label>
                    <input id="fileUpload" type="file" multiple accept=".jpg,.jpeg,.png,.webp" onchange="uploadFiles(this.files)">
                    <div class="upload-note">Format yang didukung: JPG, JPEG, PNG, WEBP. Bisa upload lebih dari satu foto.</div>
                </div>

                <div id="galleryWrap" style="margin-top:12px">
                    <?php if (empty($dokumentasi)): ?>
                        <div class="empty-st" id="galleryEmpty"><i class="fa-solid fa-images"></i>Belum ada foto dokumentasi</div>
                    <?php else: ?>
                        <div class="gallery" id="galleryGrid">
                            <?php foreach ($dokumentasi as $img): ?>
                                <div class="gallery-item" data-id="<?= (int)$img['id'] ?>">
                                    <img src="<?= h($img['file_path']) ?>" class="gallery-thumb" alt="Dokumentasi" onclick="openImage('<?= h($img['file_path']) ?>')">
                                    <div class="gallery-meta">
                                        <div class="gallery-cap">Dokumentasi #<?= (int)$img['id'] ?></div>
                                        <button type="button" class="btn-del-img no-print" onclick="deleteFoto(<?= (int)$img['id'] ?>)">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="empty-st" id="galleryEmpty" style="display:none"><i class="fa-solid fa-images"></i>Belum ada foto dokumentasi</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="action-card no-print">
            <button type="button" class="btn-submit" onclick="saveNotulen(true)">
                <i class="fa-solid fa-floppy-disk"></i> Simpan Notulen
            </button>
            <div class="autosave" id="autosaveStatus">
                <i class="fa-solid fa-cloud-arrow-up" style="color:var(--blue)"></i>
                Siap disimpan
            </div>
            <div class="privacy">
                <i class="fa-solid fa-shield-halved" style="color:var(--green)"></i>
                Notulen dan dokumentasi dapat diperbarui kembali kapan saja
            </div>
        </div>
    </main>

    <div id="imgModal" class="modal-ov hidden">
        <div style="position:absolute;inset:0" onclick="closeImage()"></div>
        <div class="modal-box">
            <div class="modal-head">
                <div>
                    <h2>Preview Dokumentasi</h2>
                    <p>Pusdiklat Mahkamah Agung RI</p>
                </div>
                <button type="button" class="icon-btn" onclick="closeImage()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
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
        const SELF_URL = location.pathname + '?id=' + BOOKING_ID + '&pin=' + encodeURIComponent(PIN);

        let docsData = <?= json_encode(array_values($dokumentasi), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let autosaveTimer = null;
        let autosaveBusy = false;
        let lastSavedPayload = '';

        const $id = id => document.getElementById(id);
        const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

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
                <div class="gallery-cap">Dokumentasi #${img.id}</div>
                <button type="button" class="btn-del-img no-print" onclick="deleteFoto(${img.id})">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
        }

        async function saveNotulen(showManualToast = false) {
            if (autosaveBusy) return;

            const payload = getNotulenPayload();
            const payloadString = JSON.stringify(payload);

            autosaveBusy = true;
            setAutosaveStatus('Menyimpan...', 'var(--blue)', 'fa-rotate-right');

            const fd = new FormData();
            fd.append('action', 'save_notulen');
            fd.append('agenda', payload.agenda);
            fd.append('pimpinan_rapat', payload.pimpinan_rapat);
            fd.append('moderator', payload.moderator);
            fd.append('notulis', payload.notulis);
            fd.append('peserta_text', payload.peserta_text);
            fd.append('pembahasan', payload.pembahasan);
            fd.append('keputusan', payload.keputusan);
            fd.append('tindak_lanjut', payload.tindak_lanjut);

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
            clearTimeout(autosaveTimer);
            setAutosaveStatus('Perubahan terdeteksi...', 'var(--amber)', 'fa-pen');

            autosaveTimer = setTimeout(() => {
                const payloadString = JSON.stringify(getNotulenPayload());
                if (payloadString !== lastSavedPayload) {
                    saveNotulen(false);
                } else {
                    setAutosaveStatus('Tidak ada perubahan', 'var(--muted)', 'fa-check');
                }
            }, 1800);
        }

        async function uploadFiles(files) {
            if (!files || !files.length) return;

            const fd = new FormData();
            fd.append('action', 'upload_dokumentasi');
            [...files].forEach(file => fd.append('files[]', file));

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
                } else {
                    showToast(j.message || 'Upload gagal');
                }
            } catch (e) {
                showToast('Error: ' + e.message);
            }
        }

        async function deleteFoto(id) {
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
                } else {
                    showToast(j.message || 'Gagal menghapus foto');
                }
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
                    $id('agenda').value = j.notulen.agenda || '';
                    $id('pimpinan_rapat').value = j.notulen.pimpinan_rapat || '';
                    $id('moderator').value = j.notulen.moderator || '';
                    $id('notulis').value = j.notulen.notulis || '';
                    $id('peserta_text').value = j.notulen.peserta_text || '';
                    $id('pembahasan').value = j.notulen.pembahasan || '';
                    $id('keputusan').value = j.notulen.keputusan || '';
                    $id('tindak_lanjut').value = j.notulen.tindak_lanjut || '';
                }

                docsData = j.dokumentasi || [];
                renderGallery();

                lastSavedPayload = JSON.stringify(getNotulenPayload());
                setAutosaveStatus('Semua perubahan tersimpan', 'var(--green)', 'fa-check-circle');

                if (showMsg) showToast('✓ Data diperbarui');
            } catch (e) {
                showToast('Gagal refresh data');
            } finally {
                setTimeout(() => icon.classList.remove('spin'), 700);
            }
        }

        [
            'agenda', 'pimpinan_rapat', 'moderator', 'notulis',
            'peserta_text', 'pembahasan', 'keputusan', 'tindak_lanjut'
        ].forEach(id => {
            const el = $id(id);
            if (el) {
                el.addEventListener('input', queueAutosave);
                el.addEventListener('change', queueAutosave);
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                const current = JSON.stringify(getNotulenPayload());
                if (current !== lastSavedPayload) saveNotulen(false);
            }
        });

        window.addEventListener('beforeunload', () => {
            const current = JSON.stringify(getNotulenPayload());
            if (current !== lastSavedPayload) saveNotulen(false);
        });

        renderGallery();
        lastSavedPayload = JSON.stringify(getNotulenPayload());
        setAutosaveStatus('Semua perubahan tersimpan', 'var(--green)', 'fa-check-circle');
    </script>
</body>

</html>