<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Jakarta');

session_start();
require_once 'config.php';

// Fallback aman: definisi utama sebaiknya tetap berada di config.php.
if (!defined('EXTERNAL_API_KEY')) {
    define('EXTERNAL_API_KEY', '');
}

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

// Ambil header request dengan cara yang aman meski getallheaders() tidak tersedia
function get_request_header($name)
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) return $v;
        }
    }
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$serverKey]) ? $_SERVER[$serverKey] : '';
}

$action = $_GET['action'] ?? '';

/* ── EXTERNAL API: SYNC KAMAR (laskar.bsdk) ───────────────
   Endpoint khusus untuk aplikasi laskar.bsdk.mahkamahagung.go.id
   menarik data kamar peserta beserta NIP untuk keperluan sinkronisasi.
   Metode: POST, autentikasi via header X-API-Key.
──────────────────────────────────────────────────────────── */
if ($action === 'sync_kamar') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        err('Method tidak diizinkan, gunakan POST', null, 405);
    }

    header('Access-Control-Allow-Origin: https://laskar.bsdk.mahkamahagung.go.id');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: X-API-Key, Content-Type');

    if (!defined('EXTERNAL_API_KEY') || EXTERNAL_API_KEY === '') {
        err('API key belum dikonfigurasi di server', null, 500);
    }

    $key = get_request_header('X-API-Key');

    if ($key === '' || !hash_equals(EXTERNAL_API_KEY, $key)) {
        err('Unauthorized', null, 401);
    }

    // Filter opsional: agenda_id dan kategori (penyelenggara, mis. "Pusdiklat Teknis")
    // Bisa dikirim lewat form POST biasa atau JSON body.
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    if (!is_array($jsonBody)) $jsonBody = [];

    $agendaFilter = '';
    if (isset($_POST['agenda_id']) && $_POST['agenda_id'] !== '') {
        $agendaFilter = $_POST['agenda_id'];
    } elseif (isset($jsonBody['agenda_id']) && $jsonBody['agenda_id'] !== '') {
        $agendaFilter = $jsonBody['agenda_id'];
    }
    $agendaFilter = $agendaFilter === '' ? null : (int)$agendaFilter;

    $kategoriFilter = '';
    if (isset($_POST['kategori']) && $_POST['kategori'] !== '') {
        $kategoriFilter = $_POST['kategori'];
    } elseif (isset($jsonBody['kategori']) && $jsonBody['kategori'] !== '') {
        $kategoriFilter = $jsonBody['kategori'];
    }
    $kategoriFilter = trim((string)$kategoriFilter) === '' ? null : trim((string)$kategoriFilter);

    // Validasi kategori: hanya 4 nilai ini yang valid di sistem
    $validKategori = ['Kerjasama', 'Teknis', 'Menpim', 'Pustrajak'];
    if ($kategoriFilter !== null && !in_array($kategoriFilter, $validKategori, true)) {
        err('Kategori tidak valid. Gunakan salah satu: ' . implode(', ', $validKategori), null, 400);
    }

    $peranFilter = '';
    if (isset($_POST['peran']) && $_POST['peran'] !== '') {
        $peranFilter = $_POST['peran'];
    } elseif (isset($jsonBody['peran']) && $jsonBody['peran'] !== '') {
        $peranFilter = $jsonBody['peran'];
    }
    $peranFilter = trim((string)$peranFilter) === '' ? null : trim((string)$peranFilter);

    // Validasi peran: hanya 3 nilai ini yang valid di sistem
    $validPeran = ['Peserta', 'Pengajar', 'Panitia'];
    if ($peranFilter !== null && !in_array($peranFilter, $validPeran, true)) {
        err('Peran tidak valid. Gunakan salah satu: ' . implode(', ', $validPeran), null, 400);
    }

    // Filter per personal: berdasarkan NIP (untuk ambil data satu orang saja)
    $nipFilter = '';
    if (isset($_POST['nip']) && $_POST['nip'] !== '') {
        $nipFilter = $_POST['nip'];
    } elseif (isset($jsonBody['nip']) && $jsonBody['nip'] !== '') {
        $nipFilter = $jsonBody['nip'];
    }
    $nipFilter = trim((string)$nipFilter) === '' ? null : trim((string)$nipFilter);

    $sql = "SELECT 
                p.id,
                p.nama,
                p.nip,
                p.instansi,
                p.peran,
                p.jenis_kelamin,
                p.no_hp,
                p.gedung,
                p.lantai,
                p.kamar,
                p.bed,
                p.checkin_date,
                p.checkin_time,
                p.checkout_date,
                p.checkout_time,
                p.status_inap,
                p.kondisi,
                p.catatan,
                p.updated_at,
                a.id AS agenda_id,
                a.judul,
                a.kategori,
                a.start_date,
                a.end_date
            FROM peserta_penginapan p
            LEFT JOIN agenda_kegiatan a ON a.id = p.agenda_id
            WHERE (p.agenda_id IS NULL OR COALESCE(a.status, 'active') = 'active')";

    $params = [];
    $types = '';

    if ($agendaFilter !== null) {
        $sql .= " AND p.agenda_id = ?";
        $params[] = $agendaFilter;
        $types .= 'i';
    }

    if ($peranFilter !== null) {
        $sql .= " AND p.peran = ?";
        $params[] = $peranFilter;
        $types .= 's';
    }

    if ($nipFilter !== null) {
        $sql .= " AND p.nip = ?";
        $params[] = $nipFilter;
        $types .= 's';
    }

    if ($kategoriFilter !== null) {
        $sql .= " AND a.kategori = ?";
        $params[] = $kategoriFilter;
        $types .= 's';
    }

    $sql .= " ORDER BY 
                p.gedung,
                CAST(COALESCE(NULLIF(p.lantai,''),'0') AS UNSIGNED),
                CAST(COALESCE(NULLIF(p.kamar,''),'0') AS UNSIGNED),
                p.nama";

    if (empty($params)) {
        $res = $db->query($sql);
        if (!$res) err('Gagal query: ' . $db->error);
    } else {
        $st = $db->prepare($sql);
        if (!$st) err('Prepare gagal: ' . $db->error);
        $st->bind_param($types, ...$params);
        $st->execute();
        $res = $st->get_result();
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    ok('OK', $rows, [
        'meta' => [
            'synced_at' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'filter_agenda_id' => $agendaFilter,
            'filter_peran' => $peranFilter,
            'filter_kategori' => $kategoriFilter,
            'filter_nip' => $nipFilter,
            'count' => count($rows)
        ]
    ]);
}

/* ── EXTERNAL API: SYNC PELATIHAN (laskar.bsdk) ───────────
   Endpoint untuk laskar.bsdk mengambil daftar pelatihan/agenda
   (id, judul, kategori, tanggal) agar bisa memilih agenda_id
   untuk difilter di endpoint sync_kamar.
   Metode: POST, autentikasi via header X-API-Key.
──────────────────────────────────────────────────────────── */
if ($action === 'sync_pelatihan') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        err('Method tidak diizinkan, gunakan POST', null, 405);
    }

    header('Access-Control-Allow-Origin: https://laskar.bsdk.mahkamahagung.go.id');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: X-API-Key, Content-Type');

    if (!defined('EXTERNAL_API_KEY') || EXTERNAL_API_KEY === '') {
        err('API key belum dikonfigurasi di server', null, 500);
    }

    $key = get_request_header('X-API-Key');

    if ($key === '' || !hash_equals(EXTERNAL_API_KEY, $key)) {
        err('Unauthorized', null, 401);
    }

    // Filter opsional: kategori (penyelenggara)
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    if (!is_array($jsonBody)) $jsonBody = [];

    $kategoriFilter = '';
    if (isset($_POST['kategori']) && $_POST['kategori'] !== '') {
        $kategoriFilter = $_POST['kategori'];
    } elseif (isset($jsonBody['kategori']) && $jsonBody['kategori'] !== '') {
        $kategoriFilter = $jsonBody['kategori'];
    }
    $kategoriFilter = trim((string)$kategoriFilter) === '' ? null : trim((string)$kategoriFilter);

    $validKategori = ['Kerjasama', 'Teknis', 'Menpim', 'Pustrajak'];
    if ($kategoriFilter !== null && !in_array($kategoriFilter, $validKategori, true)) {
        err('Kategori tidak valid. Gunakan salah satu: ' . implode(', ', $validKategori), null, 400);
    }

    $sql = "SELECT 
                id AS agenda_id,
                judul,
                kategori,
                start_date,
                end_date
            FROM agenda_kegiatan
            WHERE COALESCE(status, 'active') = 'active'";

    $params = [];
    $types = '';

    if ($kategoriFilter !== null) {
        $sql .= " AND kategori = ?";
        $params[] = $kategoriFilter;
        $types .= 's';
    }

    $sql .= " ORDER BY start_date DESC, id DESC";

    if (empty($params)) {
        $res = $db->query($sql);
        if (!$res) err('Gagal query: ' . $db->error);
    } else {
        $st = $db->prepare($sql);
        if (!$st) err('Prepare gagal: ' . $db->error);
        $st->bind_param($types, ...$params);
        $st->execute();
        $res = $st->get_result();
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    ok('OK', $rows, [
        'meta' => [
            'synced_at' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'filter_kategori' => $kategoriFilter,
            'count' => count($rows)
        ]
    ]);
}

/* ── LIST ALL ───────────────────────────────────────── */
if ($action === 'list') {
    // ✅ Peserta dari kegiatan yang dibatalkan tidak dimunculkan
    // Peserta tanpa kegiatan (agenda_id NULL) tetap ditampilkan
    $sql = "SELECT 
                p.*, 
                a.judul, 
                a.start_date, 
                a.end_date, 
                a.kategori
            FROM peserta_penginapan p
            LEFT JOIN agenda_kegiatan a ON a.id = p.agenda_id
            WHERE (p.agenda_id IS NULL OR COALESCE(a.status, 'active') = 'active')
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

    // Rentang tampil daftar peserta:
    // - mulai 2 hari sebelum kegiatan
    // - selama kegiatan berlangsung
    // - sampai 2 hari setelah kegiatan selesai
    $beforeDays = isset($_GET['before_days']) ? max(0, min(30, (int)$_GET['before_days'])) : 2;
    $afterDays  = isset($_GET['after_days']) ? max(0, min(30, (int)$_GET['after_days'])) : 2;

    $sqlAgenda = "SELECT id, judul, start_date, end_date
                  FROM agenda_kegiatan
                  WHERE DATE_SUB(start_date, INTERVAL {$beforeDays} DAY) <= CURDATE()
                    AND (
                        end_date IS NULL
                        OR DATE_ADD(end_date, INTERVAL {$afterDays} DAY) >= CURDATE()
                    )
                    AND COALESCE(status, 'active') = 'active'
                  ORDER BY start_date DESC, id DESC";

    $resA = $db->query($sqlAgenda);
    if (!$resA) err('Gagal query agenda: ' . $db->error);

    $agendas = [];
    while ($a = $resA->fetch_assoc()) $agendas[] = $a;

    $isFallback = false;

    if (empty($agendas)) {
        // Tidak menampilkan agenda lama di luar rentang H-2 sampai H+2.
        $isFallback = false;
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
        'timezone' => date_default_timezone_get(),
        'before_days' => $beforeDays,
        'after_days' => $afterDays
    ]);
}

/* ── SEARCH ALL PESERTA / RIWAYAT ─────────────────── */
if ($action === 'search_peserta') {
    $q = clean($_GET['q'] ?? '');
    if ($q === '') {
        ok('OK', [], [
            'meta' => [
                'query' => '',
                'count' => 0,
                'limit' => 100
            ]
        ]);
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $limit = max(1, min(200, $limit));
    $like = '%' . $q . '%';

    $sql = "SELECT
                p.id,
                p.agenda_id,
                p.nama,
                p.instansi,
                p.nip,
                p.no_hp,
                p.peran,
                p.jenis_kelamin,
                p.gedung,
                p.lantai,
                p.kamar,
                p.bed,
                p.checkin_date,
                p.checkin_time,
                p.checkout_date,
                p.checkout_time,
                p.status_inap,
                p.kondisi,
                p.catatan,
                p.updated_at,
                a.judul,
                a.start_date,
                a.end_date,
                CASE
                    WHEN a.id IS NOT NULL
                     AND DATE_SUB(a.start_date, INTERVAL 2 DAY) <= CURDATE()
                     AND (a.end_date IS NULL OR DATE_ADD(a.end_date, INTERVAL 2 DAY) >= CURDATE())
                     AND COALESCE(a.status, 'active') = 'active'
                    THEN 1 ELSE 0
                END AS can_operate
            FROM peserta_penginapan p
            LEFT JOIN agenda_kegiatan a ON a.id = p.agenda_id
            WHERE p.nama LIKE ?
               OR COALESCE(p.nip, '') LIKE ?
               OR COALESCE(p.instansi, '') LIKE ?
               OR COALESCE(p.kamar, '') LIKE ?
               OR COALESCE(a.judul, '') LIKE ?
            ORDER BY
                CASE WHEN a.end_date IS NULL THEN 0 ELSE 1 END,
                COALESCE(a.end_date, a.start_date) DESC,
                a.start_date DESC,
                p.nama ASC
            LIMIT ?";

    $st = $db->prepare($sql);
    if (!$st) err('Prepare pencarian gagal: ' . $db->error);

    $st->bind_param('sssssi', $like, $like, $like, $like, $like, $limit);
    if (!$st->execute()) err('Pencarian gagal: ' . $st->error);

    $rs = $st->get_result();
    $rows = [];
    while ($row = $rs->fetch_assoc()) {
        $row['can_operate'] = (bool)$row['can_operate'];
        $rows[] = $row;
    }

    ok('OK', $rows, [
        'meta' => [
            'query' => $q,
            'count' => count($rows),
            'limit' => $limit,
            'server_date' => date('Y-m-d'),
            'server_time' => date('Y-m-d H:i:s')
        ]
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
