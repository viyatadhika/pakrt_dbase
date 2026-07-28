<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Koneksi database tidak tersedia. Pastikan $conn (mysqli) ada di config.php'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$conn->set_charset('utf8mb4');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'room_list':
        roomList($conn);
        break;
    case 'booking_list':
        bookingList($conn);
        break;
    case 'booking_check':
        bookingCheck($conn);
        break;
    case 'booking_create':
        bookingCreate($conn);
        break;
    case 'booking_update':
        bookingUpdate($conn);
        break;
    case 'booking_delete':
        bookingDelete($conn);
        break;
    case 'booking_verify':
        bookingVerify($conn);
        break;
    default:
        jsonOut(['error' => 'Action tidak valid'], 400);
        break;
}

exit;

/* =========================================================================
   HELPERS
   ========================================================================= */

function jsonOut($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function post($key, $default = '')
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function cleanWa(string $wa): string
{
    $wa = preg_replace('/[^0-9]/', '', $wa);
    if ($wa === '') return '';
    if (strpos($wa, '0') === 0)       $wa = '62' . substr($wa, 1);
    elseif (strpos($wa, '62') !== 0)  $wa = '62' . $wa;
    return $wa;
}

function generatePin(): string
{
    return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

function appBaseUrl(): string
{
    return 'https://app.bsdk.mahkamahagung.go.id/wargart/';
}

function absUrl(string $path): string
{
    return rtrim(appBaseUrl(), '/') . '/' . ltrim($path, '/');
}

function buildWhatsappUrl(string $waNumber, string $message): string
{
    return 'https://wa.me/' . rawurlencode($waNumber) . '?text=' . rawurlencode($message);
}

function buildQrUrl(string $targetUrl, string $size = '300x300'): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size='
        . rawurlencode($size)
        . '&data='
        . rawurlencode($targetUrl);
}

function isAdminUser(): bool
{
    if (!isset($_SESSION['user'])) return false;
    if (is_array($_SESSION['user']) && isset($_SESSION['user']['role'])) {
        return strtolower((string)$_SESSION['user']['role']) === 'admin';
    }
    return true;
}

function roomExists(mysqli $conn, string $roomId): bool
{
    $stmt = $conn->prepare("SELECT id FROM ruang_rapat WHERE id = ? AND aktif = 1 LIMIT 1");
    if (!$stmt) return false;
    $roomIdInt = (int)$roomId;
    $stmt->bind_param('i', $roomIdInt);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

function getRoomInfo(mysqli $conn, string $roomId): ?array
{
    $stmt = $conn->prepare("SELECT id, nama_ruang, lokasi, kapasitas, fasilitas FROM ruang_rapat WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $roomIdInt = (int)$roomId;
    $stmt->bind_param('i', $roomIdInt);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function verifyBookingPin(mysqli $conn, string $id, string $pin): bool
{
    $stmt = $conn->prepare("SELECT id FROM booking_ruang_rapat WHERE id = ? AND pin = ? LIMIT 1");
    if (!$stmt) return false;
    $idInt = (int)$id;
    $stmt->bind_param('is', $idInt, $pin);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

function getDisplayLokasi(array $row): string
{
    if (($row['jenis_lokasi'] ?? '') === 'external') {
        return $row['lokasi_external'] ?: '-';
    }
    $ruang       = $row['ruang'] ?? '';
    $lokasiRuang = $row['lokasi_ruang'] ?? '';
    if ($ruang !== '' && $lokasiRuang !== '') return $ruang . ' - ' . $lokasiRuang;
    return $ruang ?: '-';
}

function formatTanggalRingkas(string $startDate, string $endDate): string
{
    return $startDate === $endDate
        ? $startDate
        : $startDate . ' s.d. ' . $endDate;
}

function validateEditOrDeleteAccess(mysqli $conn, string $id, string $pin): void
{
    if ($id === '') jsonOut(['error' => 'ID booking wajib diisi'], 422);
    if (isAdminUser()) return;
    if ($pin === '') jsonOut(['error' => 'PIN wajib diisi'], 422);
    if (!verifyBookingPin($conn, $id, $pin)) jsonOut(['error' => 'PIN salah'], 403);
}

function findBentrokInternal(
    mysqli $conn,
    string $roomId,
    string $startDate,
    string $endDate,
    string $jamStart,
    string $jamEnd,
    ?string $excludeId = null
): array {
    $sql = "
        SELECT b.id, b.nama, b.start_date, b.end_date, b.jam_start, b.jam_end,
               COALESCE(r.nama_ruang,'') AS ruang
        FROM booking_ruang_rapat b
        LEFT JOIN ruang_rapat r ON r.id = b.room_id
        WHERE b.jenis_lokasi = 'internal'
          AND b.room_id = ?
          AND b.start_date <= ?
          AND b.end_date >= ?
          AND b.jam_start < ?
          AND b.jam_end > ?
    ";
    if ($excludeId !== null && $excludeId !== '') $sql .= " AND b.id <> ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    $roomIdInt = (int)$roomId;
    if ($excludeId !== null && $excludeId !== '') {
        $excludeIdInt = (int)$excludeId;
        $stmt->bind_param('issssi', $roomIdInt, $endDate, $startDate, $jamEnd, $jamStart, $excludeIdInt);
    } else {
        $stmt->bind_param('issss', $roomIdInt, $endDate, $startDate, $jamEnd, $jamStart);
    }

    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function buildWaMessage(
    string $nama,
    string $peminjam,
    string $lokasiDisplay,
    string $startDate,
    string $endDate,
    string $jamStart,
    string $jamEnd,
    string $pin,
    string $linkAbsensi,
    string $linkMonitor,
    string $linkNotulen,
    string $qrAbsensiUrl
): string {
    $sep = str_repeat('-', 30);
    $tgl = formatTanggalRingkas($startDate, $endDate);
    $jam = substr($jamStart, 0, 5) . ' - ' . substr($jamEnd, 0, 5) . ' WIB';

    return
        "[ INFORMASI BOOKING RUANG RAPAT ]\n" .
        "Pusdiklat Mahkamah Agung RI\n" .
        "{$sep}\n" .
        "Kegiatan : {$nama}\n" .
        "Peminjam : {$peminjam}\n" .
        "Lokasi   : {$lokasiDisplay}\n" .
        "Tanggal  : {$tgl}\n" .
        "Waktu    : {$jam}\n" .
        "{$sep}\n" .
        "PIN Booking : {$pin}\n" .
        "_(Gunakan PIN untuk akses monitoring,\n" .
        "notulen, edit & hapus booking)_\n" .
        "{$sep}\n" .
        "[ LINK AKSES ]\n" .
        ">> Isi Absensi\n" .
        "{$linkAbsensi}\n\n" .
        ">> Monitoring Absensi\n" .
        "{$linkMonitor}\n\n" .
        ">> Notulen\n" .
        "{$linkNotulen}\n\n" .
        ">> QR Code Absensi\n" .
        "{$qrAbsensiUrl}\n" .
        "{$sep}\n" .
        "_Terima kasih. Hubungi admin jika ada pertanyaan._";
}

/* =========================================================================
   ACTIONS
   ========================================================================= */

function roomList(mysqli $conn): void
{
    $res = $conn->query("
        SELECT id, nama_ruang, lokasi, kapasitas, fasilitas, aktif
        FROM ruang_rapat WHERE aktif = 1 ORDER BY nama_ruang ASC
    ");
    if (!$res) jsonOut(['error' => 'Query room_list gagal', 'mysql_error' => $conn->error], 500);

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id'         => (string)$row['id'],
            'nama_ruang' => $row['nama_ruang'],
            'lokasi'     => $row['lokasi'],
            'kapasitas'  => (int)$row['kapasitas'],
            'fasilitas'  => $row['fasilitas'],
            'aktif'      => (int)$row['aktif']
        ];
    }
    jsonOut($rows);
}

function bookingList(mysqli $conn): void
{
    /*
     * ORDER BY:
     *   1. start_date  — tanggal kegiatan
     *   2. jam_start   — jam kegiatan
     *   3. created_at  — siapa booking lebih dulu (jika tanggal & jam sama)
     *   4. id          — fallback
     */
    $res = $conn->query("
        SELECT b.id, b.jenis_lokasi, b.room_id, b.lokasi_external,
               COALESCE(r.nama_ruang,'') AS ruang,
               COALESCE(r.lokasi,'') AS lokasi_ruang,
               b.nama, b.peminjam, b.start_date, b.end_date,
               b.jam_start, b.jam_end, b.peserta, b.wa,
               COALESCE(b.ket,'') AS ket, b.pin,
               b.created_at
        FROM booking_ruang_rapat b
        LEFT JOIN ruang_rapat r ON r.id = b.room_id
        ORDER BY b.start_date ASC, b.jam_start ASC, b.created_at ASC, b.id ASC
    ");
    if (!$res) jsonOut(['error' => 'Query booking_list gagal', 'mysql_error' => $conn->error], 500);

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id'              => (string)$row['id'],
            'jenis_lokasi'    => $row['jenis_lokasi'],
            'room_id'         => $row['room_id'] !== null ? (string)$row['room_id'] : '',
            'lokasi_external' => $row['lokasi_external'] ?? '',
            'ruang'           => $row['ruang'],
            'lokasi_ruang'    => $row['lokasi_ruang'],
            'lokasi_display'  => getDisplayLokasi($row),
            'nama'            => $row['nama'],
            'peminjam'        => $row['peminjam'],
            'start_date'      => $row['start_date'],
            'end_date'        => $row['end_date'],
            'jam_start'       => $row['jam_start'],
            'jam_end'         => $row['jam_end'],
            'peserta'         => (int)$row['peserta'],
            'wa'              => $row['wa'],
            'ket'             => $row['ket'],
            'pin'             => isAdminUser() ? ($row['pin'] ?? '') : '',
            'created_at'      => $row['created_at'] ?? '',
            'is_bentrok'      => false
        ];
    }
    jsonOut($rows);
}

function bookingCheck(mysqli $conn): void
{
    $id          = post('id');
    $jenisLokasi = post('jenis_lokasi', 'internal');
    $roomId      = post('room_id');
    $startDate   = post('start');
    $endDate     = post('end');
    $jamStart    = post('jam_start', '08:00');
    $jamEnd      = post('jam_end',   '12:00');

    if (!in_array($jenisLokasi, ['internal', 'external'], true))
        jsonOut(['error' => 'Jenis lokasi tidak valid'], 422);

    if ($jenisLokasi === 'external')
        jsonOut(['ok' => true, 'bentrok' => false, 'message' => 'Lokasi luar kantor tidak dicek bentrok ruangan']);

    if ($roomId === '' || !roomExists($conn, $roomId))
        jsonOut(['error' => 'Ruangan internal tidak valid'], 422);

    if ($startDate === '' || $endDate === '' || $jamStart === '' || $jamEnd === '')
        jsonOut(['ok' => true, 'bentrok' => false, 'message' => 'Lengkapi tanggal dan jam untuk cek bentrok']);

    if ($startDate > $endDate) jsonOut(['error' => 'Tanggal mulai tidak boleh lebih besar dari selesai'], 422);
    if ($jamStart > $jamEnd)   jsonOut(['error' => 'Jam mulai tidak boleh lebih besar dari jam selesai'], 422);

    $bentrok = findBentrokInternal($conn, $roomId, $startDate, $endDate, $jamStart, $jamEnd, $id !== '' ? $id : null);

    if (!empty($bentrok))
        jsonOut(['ok' => true, 'bentrok' => true, 'message' => 'Jadwal bentrok dengan booking lain', 'items' => $bentrok]);

    jsonOut(['ok' => true, 'bentrok' => false, 'message' => 'Jadwal tersedia']);
}

function bookingCreate(mysqli $conn): void
{
    $jenisLokasi    = post('jenis_lokasi', 'internal');
    $roomId         = post('room_id');
    $lokasiExternal = post('lokasi_external');
    $nama           = post('nama');
    $peminjam       = post('peminjam');
    $startDate      = post('start');
    $endDate        = post('end');
    $jamStart       = post('jam_start', '08:00');
    $jamEnd         = post('jam_end',   '12:00');
    $peserta        = (int)post('peserta', 0);
    $wa             = cleanWa(post('wa'));
    $ket            = post('ket');

    if (!in_array($jenisLokasi, ['internal', 'external'], true))
        jsonOut(['error' => 'Jenis lokasi tidak valid'], 422);

    if ($nama === '' || $peminjam === '' || $startDate === '' || $endDate === '' || $wa === '')
        jsonOut(['error' => 'Lengkapi semua field wajib'], 422);

    if ($startDate > $endDate) jsonOut(['error' => 'Tanggal mulai tidak boleh lebih besar dari selesai'], 422);
    if ($jamStart !== '' && $jamEnd !== '' && $jamStart > $jamEnd)
        jsonOut(['error' => 'Jam mulai tidak boleh lebih besar dari jam selesai'], 422);

    $pin = generatePin();

    if ($jenisLokasi === 'internal') {
        if ($roomId === '' || !roomExists($conn, $roomId)) jsonOut(['error' => 'Ruangan internal tidak valid'], 422);

        $bentrok = findBentrokInternal($conn, $roomId, $startDate, $endDate, $jamStart, $jamEnd);
        if (!empty($bentrok)) jsonOut(['error' => 'Jadwal bentrok dengan booking lain', 'bentrok' => $bentrok], 409);

        $stmt = $conn->prepare("
            INSERT INTO booking_ruang_rapat
            (jenis_lokasi, room_id, lokasi_external, nama, peminjam,
             start_date, end_date, jam_start, jam_end, peserta, wa, ket, pin)
            VALUES ('internal', ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) jsonOut(['error' => 'Prepare gagal', 'mysql_error' => $conn->error], 500);

        $roomIdInt = (int)$roomId;
        $stmt->bind_param('issssssisss', $roomIdInt, $nama, $peminjam, $startDate, $endDate, $jamStart, $jamEnd, $peserta, $wa, $ket, $pin);

        $roomInfo      = getRoomInfo($conn, $roomId);
        $lokasiDisplay = $roomInfo
            ? trim(($roomInfo['nama_ruang'] ?? '-') . (($roomInfo['lokasi'] ?? '') !== '' ? ' - ' . $roomInfo['lokasi'] : ''))
            : '-';
    } else {
        if ($lokasiExternal === '') jsonOut(['error' => 'Lokasi luar kantor wajib diisi'], 422);

        $stmt = $conn->prepare("
            INSERT INTO booking_ruang_rapat
            (jenis_lokasi, room_id, lokasi_external, nama, peminjam,
             start_date, end_date, jam_start, jam_end, peserta, wa, ket, pin)
            VALUES ('external', NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) jsonOut(['error' => 'Prepare gagal', 'mysql_error' => $conn->error], 500);

        $stmt->bind_param('sssssssisss', $lokasiExternal, $nama, $peminjam, $startDate, $endDate, $jamStart, $jamEnd, $peserta, $wa, $ket, $pin);
        $lokasiDisplay = $lokasiExternal;
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        jsonOut(['error' => 'Gagal menyimpan booking', 'mysql_error' => $err], 500);
    }

    $bookingId = (string)$stmt->insert_id;
    $stmt->close();

    $linkBooking  = absUrl('peminjaman_ruang_rapat.php');
    $linkAbsensi  = absUrl('absensi_rapat.php?id=' . rawurlencode($bookingId));
    $linkMonitor  = absUrl('absensi.php?id=' . rawurlencode($bookingId) . '&pin=' . rawurlencode($pin));
    $linkNotulen  = absUrl('notulen.php?id='  . rawurlencode($bookingId) . '&pin=' . rawurlencode($pin));
    $qrAbsensiUrl = buildQrUrl($linkAbsensi, '300x300');

    $waMessage = buildWaMessage(
        $nama,
        $peminjam,
        $lokasiDisplay,
        $startDate,
        $endDate,
        $jamStart,
        $jamEnd,
        $pin,
        $linkAbsensi,
        $linkMonitor,
        $linkNotulen,
        $qrAbsensiUrl
    );

    $waUrl = buildWhatsappUrl($wa, $waMessage);

    jsonOut([
        'success'        => true,
        'id'             => $bookingId,
        'pin'            => $pin,
        'wa_number'      => $wa,
        'wa_message'     => $waMessage,
        'wa_url'         => $waUrl,
        'link_booking'   => $linkBooking,
        'link_absensi'   => $linkAbsensi,
        'link_monitor'   => $linkMonitor,
        'link_notulen'   => $linkNotulen,
        'qr_absensi_url' => $qrAbsensiUrl
    ]);
}

function bookingUpdate(mysqli $conn): void
{
    $id             = post('id');
    $pin            = post('pin');
    $jenisLokasi    = post('jenis_lokasi', 'internal');
    $roomId         = post('room_id');
    $lokasiExternal = post('lokasi_external');
    $nama           = post('nama');
    $peminjam       = post('peminjam');
    $startDate      = post('start');
    $endDate        = post('end');
    $jamStart       = post('jam_start', '08:00');
    $jamEnd         = post('jam_end',   '12:00');
    $peserta        = (int)post('peserta', 0);
    $wa             = cleanWa(post('wa'));
    $ket            = post('ket');

    validateEditOrDeleteAccess($conn, $id, $pin);

    if (!in_array($jenisLokasi, ['internal', 'external'], true))
        jsonOut(['error' => 'Jenis lokasi tidak valid'], 422);

    if ($nama === '' || $peminjam === '' || $startDate === '' || $endDate === '' || $wa === '')
        jsonOut(['error' => 'Lengkapi semua field wajib'], 422);

    if ($startDate > $endDate) jsonOut(['error' => 'Tanggal mulai tidak boleh lebih besar dari selesai'], 422);
    if ($jamStart !== '' && $jamEnd !== '' && $jamStart > $jamEnd)
        jsonOut(['error' => 'Jam mulai tidak boleh lebih besar dari jam selesai'], 422);

    if ($jenisLokasi === 'internal') {
        if ($roomId === '' || !roomExists($conn, $roomId)) jsonOut(['error' => 'Ruangan internal tidak valid'], 422);

        $bentrok = findBentrokInternal($conn, $roomId, $startDate, $endDate, $jamStart, $jamEnd, $id);
        if (!empty($bentrok)) jsonOut(['error' => 'Jadwal bentrok dengan booking lain', 'bentrok' => $bentrok], 409);

        if (isAdminUser()) {
            $stmt = $conn->prepare("
                UPDATE booking_ruang_rapat
                SET jenis_lokasi='internal', room_id=?, lokasi_external=NULL,
                    nama=?, peminjam=?, start_date=?, end_date=?,
                    jam_start=?, jam_end=?, peserta=?, wa=?, ket=?
                WHERE id=?
            ");
            if (!$stmt) jsonOut(['error' => 'Prepare gagal', 'mysql_error' => $conn->error], 500);
            $roomIdInt = (int)$roomId;
            $idInt = (int)$id;
            $stmt->bind_param('issssssissi', $roomIdInt, $nama, $peminjam, $startDate, $endDate, $jamStart, $jamEnd, $peserta, $wa, $ket, $idInt);
        } else {
            $stmt = $conn->prepare("
                UPDATE booking_ruang_rapat
                SET jenis_lokasi='internal', room_id=?, lokasi_external=NULL,
                    nama=?, peminjam=?, start_date=?, end_date=?,
                    jam_start=?, jam_end=?, peserta=?, wa=?, ket=?
                WHERE id=? AND pin=?
            ");
            if (!$stmt) jsonOut(['error' => 'Prepare gagal', 'mysql_error' => $conn->error], 500);
            $roomIdInt = (int)$roomId;
            $idInt = (int)$id;
            $stmt->bind_param('issssssissis', $roomIdInt, $nama, $peminjam, $startDate, $endDate, $jamStart, $jamEnd, $peserta, $wa, $ket, $idInt, $pin);
        }
    } else {
        if ($lokasiExternal === '') jsonOut(['error' => 'Lokasi luar kantor wajib diisi'], 422);

        if (isAdminUser()) {
            $stmt = $conn->prepare("
                UPDATE booking_ruang_rapat
                SET jenis_lokasi='external', room_id=NULL, lokasi_external=?,
                    nama=?, peminjam=?, start_date=?, end_date=?,
                    jam_start=?, jam_end=?, peserta=?, wa=?, ket=?
                WHERE id=?
            ");
            if (!$stmt) jsonOut(['error' => 'Prepare gagal', 'mysql_error' => $conn->error], 500);
            $idInt = (int)$id;
            $stmt->bind_param('sssssssissi', $lokasiExternal, $nama, $peminjam, $startDate, $endDate, $jamStart, $jamEnd, $peserta, $wa, $ket, $idInt);
        } else {
            $stmt = $conn->prepare("
                UPDATE booking_ruang_rapat
                SET jenis_lokasi='external', room_id=NULL, lokasi_external=?,
                    nama=?, peminjam=?, start_date=?, end_date=?,
                    jam_start=?, jam_end=?, peserta=?, wa=?, ket=?
                WHERE id=? AND pin=?
            ");
            if (!$stmt) jsonOut(['error' => 'Prepare gagal', 'mysql_error' => $conn->error], 500);
            $idInt = (int)$id;
            $stmt->bind_param('sssssssissis', $lokasiExternal, $nama, $peminjam, $startDate, $endDate, $jamStart, $jamEnd, $peserta, $wa, $ket, $idInt, $pin);
        }
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        jsonOut(['error' => 'Gagal mengubah booking', 'mysql_error' => $err], 500);
    }
    $stmt->close();
    jsonOut(['success' => true, 'message' => 'Booking berhasil diubah']);
}

function bookingDelete(mysqli $conn): void
{
    $id  = post('id');
    $pin = post('pin');
    validateEditOrDeleteAccess($conn, $id, $pin);

    if (isAdminUser()) {
        $stmt = $conn->prepare("DELETE FROM booking_ruang_rapat WHERE id=?");
        if (!$stmt) jsonOut(['error' => 'Prepare gagal', 'mysql_error' => $conn->error], 500);
        $idInt = (int)$id;
        $stmt->bind_param('i', $idInt);
    } else {
        $stmt = $conn->prepare("DELETE FROM booking_ruang_rapat WHERE id=? AND pin=?");
        if (!$stmt) jsonOut(['error' => 'Prepare gagal', 'mysql_error' => $conn->error], 500);
        $idInt = (int)$id;
        $stmt->bind_param('is', $idInt, $pin);
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        jsonOut(['error' => 'Gagal menghapus booking', 'mysql_error' => $err], 500);
    }
    $stmt->close();
    jsonOut(['success' => true, 'message' => 'Booking berhasil dihapus']);
}

function bookingVerify(mysqli $conn): void
{
    $id  = post('id');
    $pin = post('pin');
    if ($id === '' || $pin === '') jsonOut(['valid' => false, 'error' => 'ID booking dan PIN wajib diisi'], 422);
    jsonOut(['valid' => verifyBookingPin($conn, $id, $pin)]);
}
