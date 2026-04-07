<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Jakarta');

session_start();
require_once 'config.php';

$db = $conn ?? $koneksi ?? null;
if (!($db instanceof mysqli)) {
    echo json_encode([
        'status' => false,
        'message' => 'Koneksi database tidak ditemukan'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$db->set_charset('utf8mb4');

function ok($msg, $data = null, $extra = [])
{
    echo json_encode(array_merge([
        'status' => true,
        'message' => $msg,
        'data' => $data
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function err($msg, $data = null, $code = 200)
{
    http_response_code($code);
    echo json_encode([
        'status' => false,
        'message' => $msg,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function clean($v)
{
    return trim((string)($v ?? ''));
}

function nullable($v)
{
    $v = trim((string)($v ?? ''));
    return $v === '' ? null : $v;
}

function upper($v)
{
    $v = trim((string)($v ?? ''));
    return $v === '' ? null : mb_strtoupper($v, 'UTF-8');
}

function tc($v)
{
    $v = trim((string)($v ?? ''));
    return $v === '' ? null : mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
}

$action = $_GET['action'] ?? '';

/* ── LIST ALL ───────────────────────────────────────── */
if ($action === 'list') {
    $sql = "SELECT 
                p.*, 
                a.judul, 
                a.start_date, 
                a.end_date, 
                a.kategori
            FROM peserta_penginapan p
            LEFT JOIN agenda_kegiatan a ON a.id = p.agenda_id
            ORDER BY 
                p.gedung,
                CAST(COALESCE(NULLIF(p.lantai,''),'0') AS UNSIGNED),
                CAST(COALESCE(NULLIF(p.kamar,''),'0') AS UNSIGNED),
                p.nama";

    $res = $db->query($sql);
    if (!$res) err('Gagal query: ' . $db->error);

    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    ok('OK', $rows, [
        'meta' => [
            'server_date' => date('Y-m-d'),
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'count' => count($rows)
        ]
    ]);
}

/* ── LIST CEKIN / CEKOUT ───────────────────────────── */
if ($action === 'list_cekin') {
    $today = date('Y-m-d');

    $sqlAgenda = "SELECT id, judul, start_date, end_date
                  FROM agenda_kegiatan
                  WHERE start_date <= CURDATE()
                    AND (end_date >= CURDATE() OR end_date IS NULL)
                  ORDER BY start_date DESC, id DESC";

    $resA = $db->query($sqlAgenda);
    if (!$resA) err('Gagal query agenda: ' . $db->error);

    $agendas = [];
    while ($a = $resA->fetch_assoc()) $agendas[] = $a;

    $isFallback = false;

    if (empty($agendas)) {
        $isFallback = true;

        $sqlFb = "SELECT id, judul, start_date, end_date
                  FROM agenda_kegiatan
                  ORDER BY 
                    COALESCE(end_date, start_date) DESC,
                    start_date DESC,
                    id DESC
                  LIMIT 3";

        $resFb = $db->query($sqlFb);
        if (!$resFb) err('Gagal query fallback agenda: ' . $db->error);

        while ($a = $resFb->fetch_assoc()) $agendas[] = $a;
    }

    $result = [];

    foreach ($agendas as $ag) {
        $agId = (int)$ag['id'];

        $stmt = $db->prepare("SELECT
                                id,
                                agenda_id,
                                nama,
                                instansi,
                                nip,
                                no_hp,
                                peran,
                                jenis_kelamin,
                                gedung,
                                lantai,
                                kamar,
                                bed,
                                checkin_date,
                                checkin_time,
                                checkout_date,
                                checkout_time,
                                status_inap,
                                kondisi,
                                catatan,
                                updated_at
                              FROM peserta_penginapan
                              WHERE agenda_id = ?
                              ORDER BY 
                                gedung,
                                CAST(COALESCE(NULLIF(lantai,''),'0') AS UNSIGNED),
                                CAST(COALESCE(NULLIF(kamar,''),'0') AS UNSIGNED),
                                nama");
        if (!$stmt) err('Prepare peserta gagal: ' . $db->error);

        $stmt->bind_param('i', $agId);
        $stmt->execute();
        $res2 = $stmt->get_result();

        $peserta = [];
        while ($p = $res2->fetch_assoc()) $peserta[] = $p;

        $belum = 0;
        $ci = 0;
        $co = 0;

        foreach ($peserta as $p) {
            if ($p['status_inap'] === 'Check-in') $ci++;
            elseif ($p['status_inap'] === 'Check-out') $co++;
            else $belum++;
        }

        $result[] = [
            'agenda_id' => $agId,
            'judul' => $ag['judul'],
            'start_date' => $ag['start_date'],
            'end_date' => $ag['end_date'],
            'total' => count($peserta),
            'belum' => $belum,
            'hadir' => $ci,
            'checkout' => $co,
            'peserta' => $peserta
        ];
    }

    ok('OK', [
        'agendas' => $result,
        'is_fallback' => $isFallback,
        'server_date' => $today,
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ]);
}

/* ── GET SINGLE ───────────────────────────────────── */
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) err('ID tidak valid');

    $st = $db->prepare("SELECT
                            p.*,
                            a.judul,
                            a.start_date,
                            a.end_date
                        FROM peserta_penginapan p
                        LEFT JOIN agenda_kegiatan a ON a.id = p.agenda_id
                        WHERE p.id = ?");
    if (!$st) err('Prepare gagal: ' . $db->error);

    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if (!$row) err('Data tidak ditemukan');
    ok('OK', $row);
}

/* ── CHECK DUPLIKAT / KAMAR ───────────────────────── */
if ($action === 'check') {
    $nama   = clean($_GET['nama'] ?? '');
    $nip    = clean($_GET['nip'] ?? '');
    $kamar  = clean($_GET['kamar'] ?? '');
    $gedung = clean($_GET['gedung'] ?? '');
    $ci     = clean($_GET['checkin_date'] ?? '');
    $co     = clean($_GET['checkout_date'] ?? '');
    $excl   = (int)($_GET['exclude_id'] ?? 0);

    $result = [
        'nama_nip' => false,
        'kamar' => false,
        'kamar_penghuni' => []
    ];

    if ($nama && $nip) {
        $st = $db->prepare("SELECT id FROM peserta_penginapan WHERE nama=? AND nip=? AND id!=?");
        if (!$st) err('Prepare gagal: ' . $db->error);

        $st->bind_param('ssi', $nama, $nip, $excl);
        $st->execute();
        $result['nama_nip'] = (bool)$st->get_result()->fetch_assoc();
    }

    if ($kamar && $gedung) {
        $q = "SELECT nama, checkin_date, checkout_date, status_inap
              FROM peserta_penginapan
              WHERE kamar=? AND gedung=? AND id!=?";
        $args = [$kamar, $gedung, $excl];
        $types = 'ssi';

        if ($ci && $co) {
            $q .= " AND NOT (checkout_date < ? OR checkin_date > ?)";
            $args[] = $ci;
            $args[] = $co;
            $types .= 'ss';
        }

        $st = $db->prepare($q);
        if (!$st) err('Prepare gagal: ' . $db->error);

        $st->bind_param($types, ...$args);
        $st->execute();
        $rs = $st->get_result();

        while ($r = $rs->fetch_assoc()) {
            $result['kamar_penghuni'][] = $r;
        }

        $result['kamar'] = count($result['kamar_penghuni']) > 0;
    }

    ok('OK', $result);
}

/* ── SAVE ─────────────────────────────────────────── */
if ($action === 'save') {
    $id        = (int)($_POST['id'] ?? 0);
    $agenda_id = ($r = clean($_POST['agenda_id'] ?? '')) === '' ? null : (int)$r;
    $nama      = clean($_POST['nama'] ?? '');
    $instansi  = tc($_POST['instansi'] ?? '');
    $nip       = nullable($_POST['nip'] ?? '');
    $no_hp     = nullable($_POST['no_hp'] ?? '');
    $peran     = clean($_POST['peran'] ?? 'Peserta');
    $jk        = nullable($_POST['jenis_kelamin'] ?? '');
    $gedung    = tc($_POST['gedung'] ?? '') ?? '';
    $lantai    = nullable($_POST['lantai'] ?? '');
    $kamar     = nullable(upper($_POST['kamar'] ?? ''));
    $bed       = nullable(upper($_POST['bed'] ?? ''));
    $ci_date   = nullable($_POST['checkin_date'] ?? '');
    $ci_time   = nullable($_POST['checkin_time'] ?? '');
    $co_date   = nullable($_POST['checkout_date'] ?? '');
    $co_time   = nullable($_POST['checkout_time'] ?? '');
    $status    = clean($_POST['status_inap'] ?? 'Belum Check-in');
    $kondisi   = tc($_POST['kondisi'] ?? '');
    $catatan   = nullable($_POST['catatan'] ?? '');
    $force     = (int)($_POST['force_kamar'] ?? 0);

    if ($nama === '') err('Nama wajib diisi');

    if (!in_array($peran, ['Peserta', 'Pengajar', 'Panitia'], true)) {
        $peran = 'Peserta';
    }

    if (!in_array($status, ['Belum Check-in', 'Check-in', 'Check-out'], true)) {
        $status = 'Belum Check-in';
    }

    if ($nip) {
        $st = $db->prepare("SELECT id FROM peserta_penginapan WHERE nama=? AND nip=? AND id!=?");
        if (!$st) err('Prepare gagal: ' . $db->error);

        $st->bind_param('ssi', $nama, $nip, $id);
        $st->execute();

        if ($st->get_result()->fetch_assoc()) {
            err('Data dengan nama dan NIP yang sama sudah ada');
        }
    }

    if (!$force && $kamar && $gedung) {
        $q = "SELECT nama, checkin_date, checkout_date
              FROM peserta_penginapan
              WHERE kamar=? AND gedung=? AND id!=?";
        $args = [$kamar, $gedung, $id];
        $types = 'ssi';

        if ($ci_date && $co_date) {
            $q .= " AND NOT (checkout_date < ? OR checkin_date > ?)";
            $args[] = $ci_date;
            $args[] = $co_date;
            $types .= 'ss';
        }

        $st = $db->prepare($q);
        if (!$st) err('Prepare gagal: ' . $db->error);

        $st->bind_param($types, ...$args);
        $st->execute();

        $penghuni = [];
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) $penghuni[] = $r;

        if (count($penghuni) > 0) {
            err('KAMAR_BENTROK', ['penghuni' => $penghuni]);
        }
    }

    if ($agenda_id !== null) {
        $ca = $db->prepare("SELECT id FROM agenda_kegiatan WHERE id=? LIMIT 1");
        if (!$ca) err('Prepare gagal: ' . $db->error);

        $ca->bind_param('i', $agenda_id);
        $ca->execute();

        if (!$ca->get_result()->fetch_assoc()) {
            $agenda_id = null;
        }
    }

    if ($id > 0) {
        $st = $db->prepare("UPDATE peserta_penginapan SET
            agenda_id=?, nama=?, instansi=?, nip=?, no_hp=?, peran=?, jenis_kelamin=?,
            gedung=?, lantai=?, kamar=?, bed=?,
            checkin_date=?, checkin_time=?, checkout_date=?, checkout_time=?,
            status_inap=?, kondisi=?, catatan=?
            WHERE id=?");
        if (!$st) err('Prepare gagal: ' . $db->error);

        $st->bind_param(
            'isssssssssssssssssi',
            $agenda_id,
            $nama,
            $instansi,
            $nip,
            $no_hp,
            $peran,
            $jk,
            $gedung,
            $lantai,
            $kamar,
            $bed,
            $ci_date,
            $ci_time,
            $co_date,
            $co_time,
            $status,
            $kondisi,
            $catatan,
            $id
        );
    } else {
        $st = $db->prepare("INSERT INTO peserta_penginapan
            (agenda_id, nama, instansi, nip, no_hp, peran, jenis_kelamin, gedung, lantai,
             kamar, bed, checkin_date, checkin_time, checkout_date, checkout_time,
             status_inap, kondisi, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$st) err('Prepare gagal: ' . $db->error);

        $st->bind_param(
            'isssssssssssssssss',
            $agenda_id,
            $nama,
            $instansi,
            $nip,
            $no_hp,
            $peran,
            $jk,
            $gedung,
            $lantai,
            $kamar,
            $bed,
            $ci_date,
            $ci_time,
            $co_date,
            $co_time,
            $status,
            $kondisi,
            $catatan
        );
    }

    if (!$st->execute()) {
        err('Gagal: ' . $st->error);
    }

    ok($id > 0 ? 'Data diperbarui' : 'Data disimpan');
}

/* ── BATCH SAVE ──────────────────────────────────── */
if ($action === 'batch_save') {
    $body = json_decode(file_get_contents('php://input'), true);
    $rows = isset($body['rows']) ? $body['rows'] : [];

    if (empty($rows)) err('Tidak ada data');

    $ph = [];
    $vals = [];
    $types = '';
    $skip = 0;

    foreach ($rows as $r) {
        $nama = clean(isset($r['nama']) ? $r['nama'] : '');
        if ($nama === '') {
            $skip++;
            continue;
        }

        $agenda_id = ($v = clean(isset($r['agenda_id']) ? $r['agenda_id'] : '')) === '' ? null : (int)$v;
        $instansi = tc(isset($r['instansi']) ? $r['instansi'] : '');
        $nip = nullable(isset($r['nip']) ? $r['nip'] : '');
        $no_hp = nullable(isset($r['no_hp']) ? $r['no_hp'] : '');
        $peran = clean(isset($r['peran']) ? $r['peran'] : 'Peserta');
        $jk = nullable(isset($r['jenis_kelamin']) ? $r['jenis_kelamin'] : '');
        $gedung = tc(isset($r['gedung']) ? $r['gedung'] : '') ?? '';
        $lantai = nullable(isset($r['lantai']) ? $r['lantai'] : '');
        $kamar = nullable(upper(isset($r['kamar']) ? $r['kamar'] : ''));
        $bed = nullable(upper(isset($r['bed']) ? $r['bed'] : ''));
        $ci_date = nullable(isset($r['checkin_date']) ? $r['checkin_date'] : '');
        $ci_time = nullable(isset($r['checkin_time']) ? $r['checkin_time'] : '');
        $co_date = nullable(isset($r['checkout_date']) ? $r['checkout_date'] : '');
        $co_time = nullable(isset($r['checkout_time']) ? $r['checkout_time'] : '');
        $status = clean(isset($r['status_inap']) ? $r['status_inap'] : 'Belum Check-in');
        $kondisi = tc(isset($r['kondisi']) ? $r['kondisi'] : '');
        $catatan = nullable(isset($r['catatan']) ? $r['catatan'] : '');

        if (!in_array($peran, ['Peserta', 'Pengajar', 'Panitia'], true)) $peran = 'Peserta';
        if (!in_array($status, ['Belum Check-in', 'Check-in', 'Check-out'], true)) $status = 'Belum Check-in';

        $ph[] = '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        array_push(
            $vals,
            $agenda_id,
            $nama,
            $instansi,
            $nip,
            $no_hp,
            $peran,
            $jk,
            $gedung,
            $lantai,
            $kamar,
            $bed,
            $ci_date,
            $ci_time,
            $co_date,
            $co_time,
            $status,
            $kondisi,
            $catatan
        );
        $types .= 'isssssssssssssssss';
    }

    if (empty($ph)) err('Semua baris tidak valid');

    $sql = "INSERT INTO peserta_penginapan
        (agenda_id,nama,instansi,nip,no_hp,peran,jenis_kelamin,gedung,lantai,
         kamar,bed,checkin_date,checkin_time,checkout_date,checkout_time,
         status_inap,kondisi,catatan)
        VALUES " . implode(',', $ph);

    $st = $db->prepare($sql);
    if (!$st) err('Prepare batch gagal: ' . $db->error);

    $st->bind_param($types, ...$vals);

    if (!$st->execute()) {
        err('Batch gagal: ' . $st->error);
    }

    ok(count($ph) . ' data disimpan' . ($skip ? " ($skip dilewati)" : ''));
}

/* ── DELETE ──────────────────────────────────────── */
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) err('ID tidak valid');

    $st = $db->prepare("DELETE FROM peserta_penginapan WHERE id=?");
    if (!$st) err('Prepare gagal: ' . $db->error);

    $st->bind_param('i', $id);

    if (!$st->execute()) err('Gagal: ' . $st->error);
    ok('Data dihapus');
}

/* ── DELETE BATCH ────────────────────────────────── */
if ($action === 'delete_batch') {
    $body = json_decode(file_get_contents('php://input'), true);
    $raw_ids = isset($body['ids']) ? $body['ids'] : [];

    $ids = [];
    foreach ($raw_ids as $v) {
        $v = (int)$v;
        if ($v > 0) $ids[] = $v;
    }

    if (empty($ids)) err('Tidak ada ID');

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("DELETE FROM peserta_penginapan WHERE id IN ($ph)");
    if (!$st) err('Prepare gagal: ' . $db->error);

    $types = str_repeat('i', count($ids));
    $st->bind_param($types, ...array_values($ids));

    if (!$st->execute()) err('Gagal: ' . $st->error);
    ok(count($ids) . ' data dihapus');
}

err('Action tidak dikenali');
