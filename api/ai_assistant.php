<?php

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 *  AI ASSISTANT v6 — Compatible PHP 7.0+
 *
 *  PERUBAHAN KOMPATIBILITAS:
 *  - Arrow function fn() → anonymous function biasa (PHP 7.4 → 7.0)
 *  - array_key_first() → reset()+key() fallback (PHP 7.3 → 7.0)
 *  - Semua fitur v6 tetap: scoring intent, context session,
 *    variasi jawaban, fallback pintar, multi-agenda, JOIN master, dll.
 * ╚══════════════════════════════════════════════════════════════════╝
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Sesi login berakhir. Silakan login ulang.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Jakarta');

// Global error handler
set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'status'  => false,
        'answer'  => '⚠️ Kesalahan sistem: ' . $e->getMessage() . ' (baris ' . $e->getLine() . ')',
        'meta'    => ['error' => true]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if ($errno & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    return false;
});

$raw      = file_get_contents('php://input');
$inp      = json_decode($raw, true);
$question = trim((string)($inp['question'] ?? ''));

if ($question === '') {
    http_response_code(422);
    echo json_encode(['status' => false, 'message' => 'Pertanyaan tidak boleh kosong.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ══════════════════════════════════════════
   HELPER: array_key_first polyfill (PHP < 7.3)
══════════════════════════════════════════ */
if (!function_exists('array_key_first')) {
    function array_key_first(array $arr)
    {
        reset($arr);
        return key($arr);
    }
}

/* ══════════════════════════════════════════
   HELPER DASAR
══════════════════════════════════════════ */
function rj(bool $ok, string $ans, array $m = [], int $c = 200): void
{
    http_response_code($c);
    echo json_encode(['status' => $ok, 'answer' => $ans, 'meta' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function nl2(): string
{
    return date('d-m-Y H:i');
}
function td(): string
{
    return date('Y-m-d');
}
function num($v = null): string
{
    return number_format((float)($v ?? 0), 0, ',', '.');
}
function pct($n = null, $d = null): string
{
    $n = (float)($n ?? 0);
    $d = (float)($d ?? 0);
    return $d > 0 ? round($n / $d * 100) . '%' : '0%';
}
function tgl(?string $d): string
{
    if (!$d) return '-';
    $ts = strtotime($d);
    if (!$ts) return $d;
    $b = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des'
    ];
    return date('j', $ts) . ' ' . $b[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}
function sf($v = null, string $fb = '-'): string
{
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $fb;
}

function nq(string $t): string
{
    $t = mb_strtolower(trim($t));
    $t = str_replace(
        [
            "assalamu'alaikum",
            "assalamualaikum",
            "assalamu alaikum",
            "assalamu'alaikum wr wb",
            "assalamualaikum wr wb",
            "wa'alaikumsalam",
            "waalaikumsalam",
            "check in",
            "check-in",
            "cek in",
            "check out",
            "check-out",
            "rekapitulasi",
            "rekap kegiatan",
            "siapa saja yang belum",
            "siapa saja belum",
            "siapa yang belum",
            "siapa belum"
        ],
        [
            "assalamualaikum",
            "assalamualaikum",
            "assalamualaikum",
            "assalamualaikum",
            "assalamualaikum",
            "waalaikumsalam",
            "waalaikumsalam",
            "cekin",
            "cekin",
            "cekin",
            "cekout",
            "cekout",
            "rekap",
            "rekap agenda",
            "siapa yang belum",
            "siapa yang belum",
            "siapa yang belum",
            "siapa yang belum"
        ],
        $t
    );
    return preg_replace('/\s+/', ' ', $t);
}

function has(string $t, array $kw): bool
{
    foreach ($kw as $k) {
        if ($k !== '' && mb_strpos($t, $k) !== false) return true;
    }
    return false;
}

/* ── DB helpers ── */
function one(mysqli $c, string $sql, string $tp = '', array $p = []): array
{
    $s = $c->prepare($sql);
    if (!$s) return [];
    if ($tp && $p) $s->bind_param($tp, ...$p);
    if (!$s->execute()) {
        $s->close();
        return [];
    }
    $r   = $s->get_result();
    $row = $r ? ($r->fetch_assoc() ?: []) : [];
    $s->close();
    return $row;
}
function rows(mysqli $c, string $sql, string $tp = '', array $p = []): array
{
    $s = $c->prepare($sql);
    if (!$s) return [];
    if ($tp && $p) $s->bind_param($tp, ...$p);
    if (!$s->execute()) {
        $s->close();
        return [];
    }
    $r   = $s->get_result();
    $out = [];
    if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
    $s->close();
    return $out;
}
function tbl(mysqli $c, string $t): bool
{
    $safe = $c->real_escape_string($t);
    $r    = $c->query("SHOW TABLES LIKE '{$safe}'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}
function col(mysqli $c, string $t, string $col): bool
{
    $st = $c->real_escape_string($t);
    $sc = $c->real_escape_string($col);
    $r  = $c->query("SHOW COLUMNS FROM `{$st}` LIKE '{$sc}'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}

/* ── Table detectors ── */
function cekinTbl(mysqli $c): ?string
{
    foreach (['peserta_penginapan', 'cekin_peserta', 'cekin_cekout', 'checkin_peserta', 'data_cekin'] as $t)
        if (tbl($c, $t)) return $t;
    return null;
}
function rusakTbl(mysqli $c): ?string
{
    foreach (['laporan_kerusakan', 'kerusakan', 'data_kerusakan'] as $t)
        if (tbl($c, $t)) return $t;
    return null;
}

/* ══════════════════════════════════════════
   CONTEXT SESSION
══════════════════════════════════════════ */
function simpanKonteks(string $intent, ?int $agendaId = null, ?string $periode = null, array $extra = []): void
{
    $_SESSION['ai_ctx'] = [
        'intent'    => $intent,
        'agenda_id' => $agendaId,
        'periode'   => $periode,
        'extra'     => $extra,
        'time'      => time(),
    ];
}

function ambilKonteks(): array
{
    $ctx = isset($_SESSION['ai_ctx']) ? $_SESSION['ai_ctx'] : [];
    if (empty($ctx)) return [];
    if ((time() - (isset($ctx['time']) ? $ctx['time'] : 0)) > 600) {
        unset($_SESSION['ai_ctx']);
        return [];
    }
    return $ctx;
}

function isFollowUp(string $q): bool
{
    return has($q, [
        'siapa saja',
        'detail',
        'lengkap',
        'semua',
        'lanjut',
        'yang mana',
        'berapa',
        'sebutkan',
        'tampilkan',
        'lihat',
        'lagi',
        'lebih',
        'tambah',
        'lainnya',
        'selanjutnya',
        'tadi',
        'itu',
        'tersebut',
        'nya ',
        'gimana',
        'bagaimana',
        'dan siapa',
        'terus',
        'lalu',
        'kemudian',
        'juga'
    ]);
}

/* ══════════════════════════════════════════
   VARIASI JAWABAN
══════════════════════════════════════════ */
function variasiPembuka(string $topik, string $periode = '', string $emoji = '📊'): string
{
    $p  = $periode ? " {$periode}" : '';
    $v  = [
        "{$emoji} **" . ucfirst($topik) . "{$p}:**",
        "{$emoji} Berikut data **{$topik}{$p}**:",
        "{$emoji} Ini informasi **{$topik}{$p}**:",
        "{$emoji} Data **{$topik}{$p}** tersedia:",
    ];
    return $v[array_rand($v)];
}

function variasiTidakAda(string $topik, string $periode = ''): string
{
    $p = $periode ? " untuk **{$periode}**" : '';
    $v = [
        "Tidak ada data {$topik}{$p}.",
        "Belum ada {$topik}{$p} yang tercatat.",
        "Data {$topik}{$p} kosong.",
        "Tidak ditemukan {$topik}{$p}.",
    ];
    return $v[array_rand($v)];
}

/* ══════════════════════════════════════════
   DATE RANGE
══════════════════════════════════════════ */
function rng(string $q): array
{
    $t = td();
    if (has($q, ['hari ini', 'sekarang', 'today']))
        return ['l' => 'hari ini', 's' => $t, 'e' => $t];
    if (has($q, ['kemarin', 'yesterday'])) {
        $d = date('Y-m-d', strtotime('-1 day'));
        return ['l' => 'kemarin', 's' => $d, 'e' => $d];
    }
    if (has($q, ['minggu ini', 'pekan ini']))
        return ['l' => 'minggu ini', 's' => date('Y-m-d', strtotime('monday this week')), 'e' => date('Y-m-d', strtotime('sunday this week'))];
    if (has($q, ['minggu lalu', 'pekan lalu']))
        return ['l' => 'minggu lalu', 's' => date('Y-m-d', strtotime('monday last week')), 'e' => date('Y-m-d', strtotime('sunday last week'))];
    if (has($q, ['bulan ini']))
        return ['l' => 'bulan ini', 's' => date('Y-m-01'), 'e' => date('Y-m-t')];
    if (has($q, ['bulan lalu']))
        return ['l' => 'bulan lalu', 's' => date('Y-m-01', strtotime('first day of last month')), 'e' => date('Y-m-t', strtotime('last day of last month'))];
    if (has($q, ['tahun ini']))
        return ['l' => 'tahun ini', 's' => date('Y-01-01'), 'e' => date('Y-12-31')];
    if (has($q, ['7 hari', 'seminggu terakhir']))
        return ['l' => '7 hari terakhir', 's' => date('Y-m-d', strtotime('-6 days')), 'e' => $t];
    if (has($q, ['30 hari', 'sebulan terakhir']))
        return ['l' => '30 hari terakhir', 's' => date('Y-m-d', strtotime('-29 days')), 'e' => $t];
    if (has($q, ['3 bulan', 'triwulan']))
        return ['l' => '3 bulan terakhir', 's' => date('Y-m-d', strtotime('-3 months')), 'e' => $t];
    return ['l' => 'hari ini', 's' => $t, 'e' => $t];
}

/* ══════════════════════════════════════════
   INTENT DETECTION — SCORING BASED
   KOMPATIBEL PHP 7.0+:
   - Tidak pakai fn() arrow function
   - Tidak pakai array_key_first() langsung (sudah ada polyfill)
══════════════════════════════════════════ */
function scoreIntent(string $q, array $keywords): int
{
    $total = 0;
    foreach ($keywords as $kw) {
        if ($kw !== '' && mb_strpos($q, $kw) !== false) {
            $total += mb_strlen($kw);
        }
    }
    return $total;
}

function getIntentMap(): array
{
    return [
        'help' => [
            'halo',
            'hai',
            'hi',
            'hello',
            'hey',
            'hy',
            'assalamualaikum',
            'selamat pagi',
            'selamat siang',
            'selamat sore',
            'selamat malam',
            'permisi',
            'bantuan',
            'help',
            'menu',
            'panduan',
            'fitur',
            'bisa apa',
            'apa yang bisa',
            'fitur apa',
            'ada fitur',
            'cara pakai',
            'cara penggunaan',
            'petunjuk',
            'tampilkan menu',
            'lihat menu',
            'apa saja fitur',
            'apa saja yang bisa',
            'tampilkan fitur',
        ],
        'ringkasan' => [
            'ringkasan',
            'rekap harian',
            'rekap operasional',
            'laporan harian',
            'dashboard',
            'summary',
            'rekapan',
            'ikhtisar',
            'operasional hari ini',
            'operasional harian',
            'kondisi hari ini',
            'kondisi sekarang',
            'situasi hari ini',
            'situasi sekarang',
            'update hari ini',
            'gimana hari ini',
            'bagaimana hari ini',
            'gimana kondisi',
            'bagaimana kondisi',
            'update dong',
            'update status',
            'status hari ini',
            'semua data',
            'data lengkap hari ini',
            'rekap semua',
            'overview',
            'pantauan',
            'pantau',
            'monitor',
        ],
        'kendaraan_ops' => [
            'kendaraan operasional',
            'kendaraan dinas',
            'driver keluar',
            'driver kembali',
            'mobil dinas',
            'kendaraan belum kembali',
            'log kendaraan dinas',
        ],
        'kendaraan' => [
            'kendaraan',
            'plat nomor',
            'log kendaraan',
            'parkir kendaraan',
            'kendaraan tamu',
            'parkir',
            'mobil tamu',
            'kendaraan masuk',
            'kendaraan keluar',
        ],
        'cekin_cari' => [
            'cari peserta',
            'cari nama',
            'siapa peserta',
            'dimana peserta',
            'bernama',
            'cari orang',
            'cari siapa',
            'temukan peserta',
            'cari data peserta',
        ],
        'cekin_instansi' => [
            'instansi terbanyak',
            'instansi paling banyak',
            'dari instansi mana',
            'asal instansi peserta',
            'instansi peserta',
            'peserta dari mana',
        ],
        'cekin_gender' => [
            'gender',
            'jenis kelamin',
            'laki-laki',
            'perempuan',
            'peserta laki',
            'peserta perempuan',
            'komposisi gender',
            'perempuan berapa',
            'laki berapa',
        ],
        'cekin_kamar' => [
            'per kamar',
            'detail kamar',
            'kamar kosong',
            'isi kamar',
            'kamar berapa',
            'kamar siapa',
        ],
        'cekin_belum' => [
            'belum cekin',
            'belum checkin',
            'belum hadir',
            'belum check-in',
            'belum masuk',
            'siapa yang belum',
            'yang belum cekin',
            'yang belum masuk',
            'yang belum hadir',
            'siapa yg belum',
            'belum cekout',
            'belum datang',
            'belum tiba',
            'yang belum tiba',
            'yang belum sampai',
            'ada yang belum',
            'masih belum',
            'siapa belum hadir',
            'belum terdata',
            'belum tercatat hadir',
        ],
        'cekin_aktif' => [
            'sedang menginap',
            'sedang inap',
            'masih inap',
            'sudah cekin',
            'yang menginap sekarang',
            'yang sedang inap',
            'sedang check-in',
        ],
        'cekin_selesai' => [
            'sudah cekout',
            'sudah keluar',
            'sudah pulang',
            'sudah checkout',
            'yang sudah keluar',
            'yang sudah pulang',
            'sudah check-out',
        ],
        'cekin_rekap' => [
            'cekin',
            'cekout',
            'penginapan',
            'menginap',
            'kamar',
            'gedung',
            'inap',
            'check-in peserta',
            'rekap penginapan',
            'rekap cekin',
            'rekap inap',
        ],
        'agenda_mendatang' => [
            'agenda mendatang',
            'jadwal mendatang',
            'akan datang',
            'minggu depan',
            'bulan depan',
            'berikutnya',
            'selanjutnya',
            'agenda ke depan',
            'jadwal ke depan',
            'agenda yang akan datang',
        ],
        'agenda_kategori' => [
            'kategori',
            'menpim',
            'teknis',
            'kerjasama',
            'pustrajak',
            'per kategori agenda',
            'kategori kegiatan',
        ],
        'agenda_terbesar' => [
            'peserta terbanyak',
            'agenda terbesar',
            'paling banyak peserta',
            'agenda peserta terbanyak',
        ],
        'agenda_count' => [
            'berapa agenda',
            'berapa kegiatan',
            'jumlah agenda',
            'jumlah kegiatan',
            'total agenda',
        ],
        'agenda' => [
            'agenda',
            'kegiatan',
            'jadwal',
            'pelatihan',
            'diklat',
            'sertifikasi',
            'bimtek',
            'konsinyering',
            'sosialisasi',
            'rekap agenda',
            'data agenda',
        ],
        'rusak_teknisi' => [
            'ranking teknisi',
            'teknisi terbaik',
            'teknisi paling aktif',
            'siapa teknisi',
            'top teknisi',
            'teknisi terbanyak',
            'teknisi paling rajin',
        ],
        'rusak_per_kategori' => [
            'per kategori kerusakan',
            'kategori rusak',
            'jenis kategori',
            'kerusakan per kategori',
        ],
        'rusak_per_lokasi' => [
            'ruangan sering',
            'lokasi sering',
            'area rusak',
            'paling sering rusak',
            'per lokasi',
            'lokasi kerusakan',
            'lokasi paling sering',
            'tempat sering rusak',
        ],
        'rusak_jenis' => [
            'jenis kerusakan',
            'tipe kerusakan',
            'macam kerusakan',
            'kerusakan jenis apa',
            'tipe kerusakan terbanyak',
        ],
        'rusak_pending' => [
            'belum selesai',
            'masih pending',
            'kerusakan pending',
            'belum ditangani',
            'menunggu perbaikan',
            'kerusakan yang belum',
            'yang belum diperbaiki',
            'antrian kerusakan',
            'kerusakan menunggu',
            'yang belum beres',
            'masih tertunda',
        ],
        'kerusakan' => [
            'kerusakan',
            'rusak',
            'masalah',
            'laporan kerusakan',
            'teknisi',
            'prioritas',
            'perbaikan',
            'laporan rusak',
        ],
        'checklist_user' => [
            'user checklist',
            'pengguna checklist',
            'siapa yang isi',
            'siapa yang mengisi',
            'akun checklist',
            'login checklist',
            'siapa isi checklist',
        ],
        'checklist_catatan' => [
            'catatan kerusakan',
            'catatan checklist',
            'laporan checklist',
            'catatan dari checklist',
        ],
        'checklist_area' => [
            'per area',
            'area kerja',
            'checklist area',
            'checklist per area',
            'area checklist',
        ],
        'checklist_regu' => [
            'per regu',
            'regu a',
            'regu b',
            'regu c',
            'checklist per regu',
            'regu checklist',
        ],
        'checklist_top' => [
            'ranking petugas',
            'petugas rajin',
            'petugas aktif',
            'top petugas',
            'petugas terbanyak',
            'petugas paling aktif',
        ],
        'checklist' => [
            'checklist',
            'form checklist',
            'petugas',
            'ob',
            'security',
            'regu',
            'plotingjaga',
            'penugasan',
            'rekap checklist',
            'data checklist',
        ],
        'surat_masuk'    => ['surat masuk', 'arsip masuk', 'masuk surat'],
        'surat_keluar'   => ['surat keluar', 'arsip keluar', 'keluar surat'],
        'surat_pengirim' => ['pengirim terbanyak', 'surat dari', 'siapa pengirim', 'asal surat'],
        'surat'          => ['surat', 'arsip', 'persuratan', 'nomor surat', 'buku surat'],
        'gudang_stok'    => ['stok barang', 'daftar stok', 'stok saat ini', 'master barang', 'cek stok'],
        'gdg_masuk'      => ['barang masuk', 'penerimaan barang', 'terima barang'],
        'gdg_keluar'     => ['barang keluar', 'pengeluaran barang', 'ambil barang'],
        'gdg_top'        => ['barang terbanyak', 'item terbanyak', 'barang paling banyak'],
        'gudang'         => ['gudang', 'stok', 'inventaris', 'barang', 'persediaan'],
        'tamu_instansi'  => ['instansi tamu', 'asal tamu', 'tamu dari mana', 'asal pengunjung'],
        'tamu'           => ['tamu', 'buku tamu', 'pengunjung', 'pelayanan', 'kunjungan'],
        'user_role' => [
            'daftar teknisi',
            'list teknisi',
            'siapa teknisi',
            'teknisi siapa',
            'daftar driver',
            'list driver',
            'siapa driver',
            'driver siapa',
            'daftar ob',
            'list ob',
            'siapa ob',
            'daftar security',
            'list security',
            'siapa security',
            'daftar pimpinan',
            'list pimpinan',
            'siapa pimpinan',
            'daftar petugas',
            'list petugas',
            'siapa petugas',
            'daftar koordinator',
            'list koordinator',
            'siapa koordinator',
            'daftar poliklinik',
            'list poliklinik',
            'daftar gudang',
            'list gudang petugas',
            'daftar perpustakaan',
            'list perpustakaan',
            'daftar sekretariat',
            'list sekretariat',
            'daftar supervisor',
            'list supervisor',
            'siapa supervisor',
            'daftar admin',
            'list admin',
            'siapa admin',
            'berapa teknisi',
            'berapa driver',
            'berapa ob',
            'berapa security',
            'berapa petugas',
            'nama teknisi',
            'nama driver',
            'nama security',
        ],
        'pengguna' => [
            'pengguna',
            'user sistem',
            'akun sistem',
            'daftar staf',
            'daftar pegawai',
            'daftar karyawan',
            'semua role',
            'semua user',
            'semua pengguna',
            'data pegawai',
            'data staf',
        ],
    ];
}

function intent(string $q): string
{
    // Sapaan exact match tetap prioritas
    $qTrim = trim($q);
    $sapaanExact = [
        'halo',
        'hai',
        'hi',
        'hello',
        'hey',
        'hy',
        'assalamualaikum',
        'selamat pagi',
        'selamat siang',
        'selamat sore',
        'selamat malam',
        'permisi',
        'bantuan',
        'help',
        'menu',
        'panduan',
        'fitur'
    ];
    if (in_array($qTrim, $sapaanExact, true)) return 'help';

    $map    = getIntentMap();
    $scores = [];

    foreach ($map as $intentName => $keywords) {
        $scores[$intentName] = scoreIntent($q, $keywords);
    }

    // Sort descending
    arsort($scores);

    // Ambil skor tertinggi — KOMPATIBEL PHP 7.0 (pakai array_key_first polyfill)
    $topIntent = array_key_first($scores);
    $topScore  = $scores[$topIntent];

    if ($topScore === 0) return 'fallback';

    // Tiebreaker jika skor sama: pilih intent dengan nama terpanjang (lebih spesifik)
    // KOMPATIBEL PHP 7.0: ganti fn() dengan anonymous function biasa
    $topScores = array_filter($scores, function ($s) use ($topScore) {
        return $s === $topScore;
    });

    if (count($topScores) > 1) {
        $candidates = array_keys($topScores);
        usort($candidates, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        return $candidates[0];
    }

    return $topIntent;
}

/* ══════════════════════════════════════════
   RESOLVE AGENDA
══════════════════════════════════════════ */
function isAgendaSpesifik(string $q): bool
{
    if (preg_match('/(?:agenda|id)\s*#?\s*\d+/i', $q)) return true;
    if (preg_match('/(?:pelatihan|diklat|sertifikasi|bimtek|konsinyering|latsar|cpns|seleksi|sosialisasi|penyusunan|profil|assessment|teknis\s+yudisial|niaga|mediasi|kepemimpinan)\s+\S+/iu', $q)) return true;
    return false;
}

function resolveAgSemua(mysqli $c): array
{
    $t    = td();
    $list = rows($c, "SELECT * FROM agenda_kegiatan WHERE start_date<=? AND end_date>=? ORDER BY peserta DESC, id ASC", 'ss', [$t, $t]);
    if (!empty($list)) return $list;
    $ag = one($c, "SELECT * FROM agenda_kegiatan ORDER BY start_date DESC, id DESC LIMIT 1");
    return !empty($ag) ? [$ag] : [];
}

function resolveAg(mysqli $c, string $q): array
{
    $ctx         = ambilKonteks();
    $ctxAgendaId = isset($ctx['agenda_id']) ? $ctx['agenda_id'] : null;

    if (preg_match('/(?:agenda|id)\s*#?\s*(\d+)/i', $q, $m)) {
        $ag = one($c, "SELECT * FROM agenda_kegiatan WHERE id=? LIMIT 1", 'i', [(int)$m[1]]);
        if (!empty($ag)) return ['id' => (int)$ag['id'], 'ag' => $ag, 'semua' => [$ag]];
    }
    if (isAgendaSpesifik($q)) {
        if (preg_match('/(?:agenda|kegiatan|pelatihan|diklat|cpns|latsar|bimtek|seleksi|sertifikasi|sosialisasi|penyusunan|mediasi|kepemimpinan)\s+(.{4,80})/iu', $q, $m)) {
            $kw = '%' . trim($m[1]) . '%';
            $t  = td();
            $ag = one($c, "SELECT * FROM agenda_kegiatan WHERE judul LIKE ? AND start_date<=? AND end_date>=? ORDER BY id ASC LIMIT 1", 'sss', [$kw, $t, $t]);
            if (empty($ag)) $ag = one($c, "SELECT * FROM agenda_kegiatan WHERE judul LIKE ? ORDER BY start_date DESC LIMIT 1", 's', [$kw]);
            if (!empty($ag)) return ['id' => (int)$ag['id'], 'ag' => $ag, 'semua' => [$ag]];
        }
    }
    if ($ctxAgendaId) {
        $ag = one($c, "SELECT * FROM agenda_kegiatan WHERE id=? LIMIT 1", 'i', [$ctxAgendaId]);
        if (!empty($ag)) return ['id' => (int)$ag['id'], 'ag' => $ag, 'semua' => [$ag]];
    }
    $semua = resolveAgSemua($c);
    $first = isset($semua[0]) ? $semua[0] : [];
    return ['id' => !empty($first) ? (int)$first['id'] : null, 'ag' => $first, 'semua' => $semua];
}

/* ══════════════════════════════════════════
   FALLBACK PINTAR
══════════════════════════════════════════ */
function logFallback(string $q): void
{
    if (!isset($_SESSION['ai_fallback_log'])) $_SESSION['ai_fallback_log'] = [];
    $_SESSION['ai_fallback_log'][] = ['q' => $q, 'time' => date('Y-m-d H:i:s')];
    $logFile = __DIR__ . '/ai_fallback.log';
    if (is_writable(dirname($logFile))) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' | ' . $q . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function ansFallback(string $q): string
{
    logFallback($q);
    $saran = [];

    if (has($q, ['berapa', 'jumlah', 'total', 'banyak', 'sedikit', 'hitung']))
        $saran[] = ['"berapa agenda hari ini"', '"berapa tamu minggu ini"', '"berapa kerusakan bulan ini"'];
    if (has($q, ['siapa', 'nama', 'orang', 'daftar', 'list']))
        $saran[] = ['"siapa belum check-in"', '"daftar teknisi"', '"cari peserta [nama]"'];
    if (has($q, ['kapan', 'tanggal', 'jadwal', 'waktu', 'hari', 'minggu', 'bulan']))
        $saran[] = ['"agenda mendatang"', '"jadwal minggu ini"', '"agenda bulan ini"'];
    if (has($q, ['mana', 'dimana', 'lokasi', 'ruangan', 'gedung', 'kamar', 'tempat']))
        $saran[] = ['"detail per kamar"', '"lokasi kerusakan"', '"rekap per gedung"'];
    if (has($q, ['rusak', 'kerusak', 'masalah', 'perbaik', 'teknisi']))
        $saran[] = ['"kerusakan hari ini"', '"kerusakan pending"', '"ranking teknisi"'];
    if (has($q, ['tamu', 'kunjung', 'pengunjung', 'masuk', 'datang']))
        $saran[] = ['"tamu hari ini"', '"instansi tamu"'];
    if (has($q, ['surat', 'arsip', 'dokumen', 'administrasi']))
        $saran[] = ['"surat masuk bulan ini"', '"pengirim surat terbanyak"'];
    if (has($q, ['barang', 'stok', 'gudang', 'inventaris']))
        $saran[] = ['"stok barang"', '"barang masuk"', '"barang terbanyak"'];
    if (has($q, ['kendaraan', 'mobil', 'driver', 'plat', 'parkir']))
        $saran[] = ['"kendaraan hari ini"', '"kendaraan operasional"'];
    if (has($q, ['inap', 'cekin', 'kamar', 'menginap', 'peserta', 'check']))
        $saran[] = ['"rekap penginapan"', '"siapa belum check-in"', '"cari peserta [nama]"'];

    $out = "Maaf, saya kurang memahami:\n_\"{$q}\"_\n\n";

    if (!empty($saran)) {
        $out .= "Mungkin maksudnya:\n";
        foreach ($saran as $group) {
            foreach ($group as $s) $out .= "→ {$s}\n";
        }
        $out .= "\nAtau ketik **bantuan** untuk semua fitur.";
    } else {
        $ctx = ambilKonteks();
        if (!empty($ctx)) {
            $out .= "Apakah ini lanjutan dari pertanyaan sebelumnya tentang **{$ctx['intent']}**?\n";
            $out .= "Coba lebih spesifik, misalnya:\n";
            $out .= "→ \"detail nya\"\n→ \"tampilkan semua\"\n→ \"siapa saja\"\n\n";
        }
        $out .= "Ketik **bantuan** untuk melihat semua fitur yang tersedia.";
    }

    return $out;
}

/* ══════════════════════════════════════════
   HELP
══════════════════════════════════════════ */
function ansHelp(): string
{
    $sapa    = ['Hei!', 'Halo!', 'Hai!', 'Siap!'];
    $pembuka = $sapa[array_rand($sapa)];
    return "{$pembuka} **Langsung tanya tanpa perlu sapaan dulu.** 🤖\n\n"
        . "📅 **Agenda** → \"agenda hari ini\", \"agenda mendatang\", \"kategori teknis\"\n"
        . "🏠 **Penginapan** → \"rekap cekin\", \"siapa belum check-in\", \"cari peserta Budi\"\n"
        . "   → \"instansi terbanyak\", \"detail per kamar\", \"komposisi gender\"\n"
        . "🔧 **Kerusakan** → \"kerusakan hari ini\", \"laporan pending\", \"ranking teknisi\"\n"
        . "   → \"per kategori kerusakan\", \"lokasi paling sering rusak\"\n"
        . "📋 **Checklist** → \"checklist hari ini\", \"per area kerja\", \"ranking petugas\"\n"
        . "   → \"siapa yang isi checklist\", \"catatan kerusakan checklist\"\n"
        . "📨 **Surat** → \"surat masuk bulan ini\", \"pengirim terbanyak\"\n"
        . "📦 **Gudang** → \"barang masuk\", \"barang terbanyak\", \"stok barang\"\n"
        . "👥 **Tamu** → \"tamu hari ini\", \"instansi tamu terbanyak\"\n"
        . "🚗 **Kendaraan** → \"kendaraan hari ini\", \"kendaraan operasional\"\n"
        . "👤 **Staf** → \"daftar teknisi\", \"daftar driver\", \"daftar ob\", \"daftar security\"\n"
        . "   → \"daftar koordinator\", \"daftar supervisor\", \"daftar poliklinik\"\n"
        . "   → \"pengguna\" untuk semua role sekaligus\n"
        . "📊 **Ringkasan** → \"ringkasan hari ini\", \"kondisi sekarang\", \"update status\"\n"
        . "\n_Tambahkan periode: \"bulan ini\", \"minggu ini\", \"7 hari terakhir\", dll._\n"
        . "_Pertanyaan lanjutan seperti \"siapa saja?\" atau \"detail nya?\" juga dimengerti._";
}

/* ══════════════════════════════════════════
   AGENDA
══════════════════════════════════════════ */
function ansAgenda(mysqli $c, array $r): string
{
    $list = rows(
        $c,
        "SELECT id,judul,start_date,end_date,kategori,asrama,peserta,kelas,makan
         FROM agenda_kegiatan WHERE start_date<=? AND end_date>=? ORDER BY start_date ASC LIMIT 20",
        'ss',
        [$r['e'], $r['s']]
    );
    if (empty($list)) return variasiTidakAda('agenda aktif', $r['l']);
    $tot = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?", 'ss', [$r['e'], $r['s']]);
    $out = variasiPembuka('agenda kegiatan', $r['l'], '📅') . "\n\n";
    $out .= "📋 **" . num($tot['c']) . "** kegiatan | 👥 **" . num($tot['p']) . "** peserta total\n\n";
    foreach ($list as $i => $row) {
        $dur = (int)round((strtotime($row['end_date']) - strtotime($row['start_date'])) / 86400) + 1;
        $out .= ($i + 1) . ". **{$row['judul']}** [{$row['kategori']}]\n";
        $out .= "   📅 " . tgl($row['start_date']) . " s/d " . tgl($row['end_date']) . " ({$dur} hari)\n";
        if ($row['peserta']) $out .= "   👥 " . num($row['peserta']) . " peserta\n";
        if ($row['asrama'])  $out .= "   🏨 {$row['asrama']}\n";
        if ($row['kelas'])   $out .= "   🏫 {$row['kelas']}\n";
        if ($row['makan'])   $out .= "   🍽️ {$row['makan']}\n";
        $out .= "\n";
    }
    simpanKonteks('agenda', null, $r['l']);
    return $out . "_Data per " . nl2() . "_";
}

function ansAgendaMendatang(mysqli $c): string
{
    $list = rows($c, "SELECT id,judul,start_date,end_date,kategori,peserta,asrama FROM agenda_kegiatan WHERE start_date>? ORDER BY start_date ASC LIMIT 15", 's', [td()]);
    if (empty($list)) return variasiTidakAda('agenda mendatang');
    $out = variasiPembuka('agenda mendatang', '', '📆') . "\n\n";
    foreach ($list as $i => $row) {
        $hari  = (int)((strtotime($row['start_date']) - time()) / 86400);
        $label = $hari <= 0 ? '✅ mulai hari ini' : ($hari === 1 ? '⏰ besok' : "📆 {$hari} hari lagi");
        $out  .= ($i + 1) . ". **{$row['judul']}** [{$row['kategori']}]\n";
        $out  .= "   📅 " . tgl($row['start_date']) . " — " . tgl($row['end_date']) . " • {$label}\n";
        if ($row['peserta']) $out .= "   👥 " . num($row['peserta']) . " peserta\n";
        if ($row['asrama'])  $out .= "   🏨 {$row['asrama']}\n\n";
        else $out .= "\n";
    }
    simpanKonteks('agenda_mendatang');
    return $out . "_Data per " . nl2() . "_";
}

function ansAgendaKategori(mysqli $c, string $q, array $r): string
{
    $map = ['menpim' => 'Menpim', 'teknis' => 'Teknis', 'kerjasama' => 'Kerjasama', 'pustrajak' => 'Pustrajak'];
    $kat = null;
    foreach ($map as $kw => $v) {
        if (mb_strpos($q, $kw) !== false) {
            $kat = $v;
            break;
        }
    }
    if ($kat) {
        $list = rows($c, "SELECT judul,start_date,end_date,peserta FROM agenda_kegiatan WHERE kategori=? AND start_date<=? AND end_date>=? ORDER BY start_date ASC", 'sss', [$kat, $r['e'], $r['s']]);
        $tot  = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE kategori=?", 's', [$kat]);
        $out  = variasiPembuka("agenda {$kat}", $r['l'], '📋') . "\n\n";
        $out .= "📊 Total: " . num($tot['c']) . " kegiatan | " . num($tot['p']) . " peserta\n\n";
        if (empty($list)) return $out . variasiTidakAda("agenda {$kat}", 'periode ini');
        foreach ($list as $i => $row) {
            $out .= ($i + 1) . ". **{$row['judul']}**\n   " . tgl($row['start_date']) . " s/d " . tgl($row['end_date']);
            if ($row['peserta']) $out .= " | " . num($row['peserta']) . " peserta";
            $out .= "\n\n";
        }
        simpanKonteks('agenda_kategori', null, $r['l'], ['kategori' => $kat]);
        return $out . "_Data per " . nl2() . "_";
    }
    $kats = rows($c, "SELECT kategori,COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE start_date<=? AND end_date>=? GROUP BY kategori ORDER BY c DESC", 'ss', [$r['e'], $r['s']]);
    $out  = variasiPembuka('rekap per kategori', $r['l'], '📊') . "\n\n";
    foreach ($kats as $row) $out .= "• **{$row['kategori']}**: " . num($row['c']) . " kegiatan | " . num($row['p']) . " peserta\n";
    simpanKonteks('agenda_kategori', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansAgendaTerbesar(mysqli $c): string
{
    $list = rows($c, "SELECT judul,start_date,end_date,kategori,peserta,asrama FROM agenda_kegiatan WHERE peserta>0 ORDER BY peserta DESC LIMIT 10");
    $out  = variasiPembuka('agenda peserta terbanyak (semua waktu)', '', '🏆') . "\n\n";
    foreach ($list as $i => $row) {
        $out .= ($i + 1) . ". **{$row['judul']}** — **" . num($row['peserta']) . " peserta**\n";
        $out .= "   " . tgl($row['start_date']) . " s/d " . tgl($row['end_date']) . " | {$row['kategori']}\n\n";
    }
    simpanKonteks('agenda_terbesar');
    return $out . "_Data per " . nl2() . "_";
}

function ansAgendaCount(mysqli $c, array $r): string
{
    $aktif = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?", 'ss', [$r['e'], $r['s']]);
    $akan  = one($c, "SELECT COUNT(*) AS c FROM agenda_kegiatan WHERE start_date>?", 's', [td()]);
    $all   = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan");
    simpanKonteks('agenda_count', null, $r['l']);
    return "**Statistik agenda:**\n\n"
        . "📅 Aktif {$r['l']} : **" . num(isset($aktif['c']) ? $aktif['c'] : 0) . "** kegiatan | " . num(isset($aktif['p']) ? $aktif['p'] : 0) . " peserta\n"
        . "📆 Akan datang  : **" . num(isset($akan['c'])  ? $akan['c']  : 0) . "** kegiatan\n"
        . "📊 Total semua  : **" . num(isset($all['c'])   ? $all['c']   : 0) . "** kegiatan | " . num(isset($all['p']) ? $all['p'] : 0) . " peserta\n\n"
        . "_Data per " . nl2() . "_";
}

/* ══════════════════════════════════════════
   CEKIN
══════════════════════════════════════════ */
function ansCekinRekap(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel penginapan tidak ditemukan.";
    $ai      = resolveAg($c, $q);
    $agendas = isset($ai['semua']) ? $ai['semua'] : [];
    if (empty($agendas)) return variasiTidakAda('agenda aktif');

    if (count($agendas) === 1) {
        $ag    = $agendas[0];
        $aid   = (int)$ag['id'];
        $judul = isset($ag['judul']) ? $ag['judul'] : ('Agenda #' . $aid);
        $s     = one($c, "SELECT COUNT(*) AS n,
            SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
            SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
            SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS out_,
            SUM(CASE WHEN peran='Peserta'              THEN 1 ELSE 0 END) AS psr,
            SUM(CASE WHEN peran='Panitia'              THEN 1 ELSE 0 END) AS pan,
            SUM(CASE WHEN peran='Pengajar'             THEN 1 ELSE 0 END) AS pngj,
            SUM(CASE WHEN jenis_kelamin='L'            THEN 1 ELSE 0 END) AS lk,
            SUM(CASE WHEN jenis_kelamin='P'            THEN 1 ELSE 0 END) AS pr
            FROM {$t} WHERE agenda_id=?", 'i', [$aid]);
        $gdg = rows($c, "SELECT gedung,COUNT(*) AS n,
            SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
            SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
            SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS out_
            FROM {$t} WHERE agenda_id=? GROUP BY gedung ORDER BY gedung", 'i', [$aid]);
        $tot  = (int)(isset($s['n']) ? $s['n'] : 0);
        $out  = variasiPembuka('rekap penginapan', '', '🏠') . "\n📌 {$judul}\n\n";
        $out .= "👥 Terdaftar   : **" . num($tot) . "** orang\n";
        $out .= "⏳ Belum hadir : **" . num($s['belum']) . "** (" . pct($s['belum'], $tot) . ")\n";
        $out .= "🏠 Menginap    : **" . num($s['inap'])  . "** (" . pct($s['inap'],  $tot) . ")\n";
        $out .= "✅ Sudah keluar: **" . num($s['out_'])  . "** (" . pct($s['out_'],  $tot) . ")\n";
        $out .= "─────────────────────\n";
        $out .= "🎓 Peserta: **" . num($s['psr']) . "** | Panitia: **" . num($s['pan']) . "** | Pengajar: **" . num($s['pngj']) . "**\n";
        if ((int)(isset($s['lk']) ? $s['lk'] : 0) + (int)(isset($s['pr']) ? $s['pr'] : 0) > 0)
            $out .= "♂ Laki: **" . num($s['lk']) . "** | ♀ Perempuan: **" . num($s['pr']) . "**\n";
        if (!empty($gdg)) {
            $out .= "\n**Per gedung:**\n";
            foreach ($gdg as $g) {
                $nm = sf($g['gedung'], 'Tanpa Gedung');
                $out .= "🏨 **{$nm}** — " . num($g['n']) . " org | ✅" . num($g['inap']) . " inap (" . pct($g['inap'], $g['n']) . ") | ⏳" . num($g['belum']) . " | 🚪" . num($g['out_']) . "\n";
            }
        }
        simpanKonteks('cekin_rekap', $aid);
        return $out . "\n_Data per " . nl2() . "_";
    }

    $out  = variasiPembuka('penginapan semua kegiatan aktif hari ini', '', '🏠') . "\n";
    $out .= "(" . count($agendas) . " kegiatan)\n\n";
    $gT = $gB = $gI = $gO = 0;
    foreach ($agendas as $ag) {
        $aid   = (int)$ag['id'];
        $judul = isset($ag['judul']) ? $ag['judul'] : ('Agenda #' . $aid);
        $s     = one($c, "SELECT COUNT(*) AS n,
            SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
            SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
            SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS out_
            FROM {$t} WHERE agenda_id=?", 'i', [$aid]);
        $tot   = (int)(isset($s['n']) ? $s['n'] : 0);
        if ($tot === 0) continue;
        $gT += $tot;
        $gB += (int)(isset($s['belum']) ? $s['belum'] : 0);
        $gI += (int)(isset($s['inap'])  ? $s['inap']  : 0);
        $gO += (int)(isset($s['out_'])  ? $s['out_']  : 0);
        $out .= "📌 **{$judul}**\n";
        $out .= "   👥 " . num($tot) . " org | ⏳belum " . num($s['belum']) . " (" . pct($s['belum'], $tot) . ") | 🏠inap " . num($s['inap']) . " | ✅keluar " . num($s['out_']) . "\n\n";
    }
    if ($gT > 0) {
        $out .= "─────────────────────\n";
        $out .= "**Total gabungan:** " . num($gT) . " org | ⏳belum " . num($gB) . " (" . pct($gB, $gT) . ") | 🏠inap " . num($gI) . " | ✅keluar " . num($gO) . "\n";
        $out .= "\n_Sebutkan nama kegiatan untuk detail spesifik._\n";
    }
    simpanKonteks('cekin_rekap');
    return $out . "\n_Data per " . nl2() . "_";
}

function ansCekinBelum(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai      = resolveAg($c, $q);
    $agendas = isset($ai['semua']) ? $ai['semua'] : [];
    if (empty($agendas)) return variasiTidakAda('agenda aktif');

    if (count($agendas) === 1) {
        $ag    = $agendas[0];
        $aid   = (int)$ag['id'];
        $judul = isset($ag['judul']) ? $ag['judul'] : ('Agenda #' . $aid);
        $tot   = one($c, "SELECT COUNT(*) AS n FROM {$t} WHERE agenda_id=? AND status_inap='Belum Check-in'", 'i', [$aid]);
        $list  = rows($c, "SELECT nama,instansi,peran,jenis_kelamin,gedung,lantai,kamar
            FROM {$t} WHERE agenda_id=? AND status_inap='Belum Check-in'
            ORDER BY gedung,lantai,kamar,nama LIMIT 100", 'i', [$aid]);
        if (empty($list)) {
            simpanKonteks('cekin_belum', $aid);
            return "✅ **Semua peserta sudah check-in!**\nKegiatan: **{$judul}**";
        }
        $out = variasiPembuka('peserta belum check-in', '', '⏳') . "\n📌 {$judul}\nTotal: **" . num(isset($tot['n']) ? $tot['n'] : 0) . "** orang\n\n";
        foreach ($list as $i => $r) {
            $lok = trim(sf($r['gedung'], '') . ' Lt.' . sf($r['lantai'], '-') . ' Kamar ' . sf($r['kamar'], '-'));
            $gen = $r['jenis_kelamin'] === 'L' ? '♂' : ($r['jenis_kelamin'] === 'P' ? '♀' : '');
            $ins = $r['instansi'] ? " | {$r['instansi']}" : '';
            $out .= ($i + 1) . ". **{$r['nama']}** {$gen} (" . sf($r['peran']) . ")" . $ins . "\n   📍 {$lok}\n";
        }
        if ((int)(isset($tot['n']) ? $tot['n'] : 0) > 100)
            $out .= "\n_...dan " . ((int)$tot['n'] - 100) . " orang lainnya._\n";
        simpanKonteks('cekin_belum', $aid);
        return $out . "\n_Data per " . nl2() . "_";
    }

    $out      = variasiPembuka('peserta belum check-in — semua kegiatan aktif', '', '⏳') . "\n\n";
    $grandTot = 0;
    foreach ($agendas as $ag) {
        $aid   = (int)$ag['id'];
        $judul = isset($ag['judul']) ? $ag['judul'] : ('Agenda #' . $aid);
        $tot   = one($c, "SELECT COUNT(*) AS n FROM {$t} WHERE agenda_id=? AND status_inap='Belum Check-in'", 'i', [$aid]);
        $n     = (int)(isset($tot['n']) ? $tot['n'] : 0);
        $grandTot += $n;
        $icon  = $n === 0 ? '✅' : '⏳';
        $out  .= "{$icon} **{$judul}**\n";
        if ($n === 0) {
            $out .= "   Semua sudah check-in\n\n";
        } else {
            $out .= "   **{$n} orang** belum check-in\n";
            $sample = rows($c, "SELECT nama,gedung,lantai,kamar FROM {$t} WHERE agenda_id=? AND status_inap='Belum Check-in' ORDER BY gedung,lantai,kamar,nama LIMIT 5", 'i', [$aid]);
            foreach ($sample as $r) {
                $lok = trim(sf($r['gedung'], '') . ' Lt.' . sf($r['lantai'], '-') . ' Kamar ' . sf($r['kamar'], '-'));
                $out .= "   • {$r['nama']} — {$lok}\n";
            }
            if ($n > 5) $out .= "   _...dan " . ($n - 5) . " lainnya. Sebutkan nama kegiatan untuk detail lengkap._\n";
            $out .= "\n";
        }
    }
    $out .= "─────────────────────\n";
    $out .= "**Total belum check-in: " . num($grandTot) . " orang**\n";
    simpanKonteks('cekin_belum');
    return $out . "\n_Data per " . nl2() . "_";
}

function ansCekinAktif(mysqli $c, string $q): string
{
    $t   = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai  = resolveAg($c, $q);
    $aid = (int)(isset($ai['id']) ? $ai['id'] : 0);
    if (!$aid) return variasiTidakAda('agenda aktif');
    $judul = isset($ai['ag']['judul']) ? $ai['ag']['judul'] : ('Agenda #' . $aid);
    $tot   = one($c, "SELECT COUNT(*) AS n FROM {$t} WHERE agenda_id=? AND status_inap='Check-in'", 'i', [$aid]);
    $list  = rows($c, "SELECT nama,instansi,peran,jenis_kelamin,gedung,lantai,kamar,checkin_date,checkin_time
        FROM {$t} WHERE agenda_id=? AND status_inap='Check-in'
        ORDER BY gedung,lantai,kamar,nama LIMIT 60", 'i', [$aid]);
    if (empty($list)) return "Belum ada yang sedang menginap untuk **{$judul}**.";
    $out = variasiPembuka('peserta sedang menginap', '', '🏠') . "\n📌 {$judul}\nTotal: **" . num(isset($tot['n']) ? $tot['n'] : 0) . "** orang\n\n";
    foreach ($list as $i => $r) {
        $lok = trim(sf($r['gedung'], '') . ' Lt.' . sf($r['lantai'], '-') . ' Kamar ' . sf($r['kamar'], '-'));
        $ci  = ($r['checkin_date'] ? tgl($r['checkin_date']) : '') . ($r['checkin_time'] ? ' ' . substr($r['checkin_time'], 0, 5) : '');
        $gen = $r['jenis_kelamin'] === 'L' ? '♂' : ($r['jenis_kelamin'] === 'P' ? '♀' : '');
        $out .= ($i + 1) . ". **{$r['nama']}** {$gen} (" . sf($r['peran']) . ")\n   📍 {$lok} | Check-in: " . trim($ci) . "\n";
    }
    simpanKonteks('cekin_aktif', $aid);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansCekinSelesai(mysqli $c, string $q): string
{
    $t     = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai    = resolveAg($c, $q);
    $ag    = !empty($ai['semua']) ? $ai['semua'][0] : [];
    $aid   = (int)(isset($ag['id']) ? $ag['id'] : 0);
    $judul = isset($ag['judul']) ? $ag['judul'] : ('Agenda #' . $aid);
    $tot   = one($c, "SELECT COUNT(*) AS n FROM {$t} WHERE agenda_id=? AND status_inap='Check-out'", 'i', [$aid]);
    $list  = rows($c, "SELECT nama,instansi,peran,gedung,lantai,kamar,checkin_date,checkin_time,checkout_date,checkout_time
        FROM {$t} WHERE agenda_id=? AND status_inap='Check-out'
        ORDER BY checkout_date DESC,checkout_time DESC LIMIT 50", 'i', [$aid]);
    if (empty($list)) return "Belum ada yang check-out dari **{$judul}**.";
    $out = variasiPembuka('peserta sudah check-out', '', '✅') . "\n📌 {$judul}\nTotal: **" . num(isset($tot['n']) ? $tot['n'] : 0) . "** orang\n\n";
    foreach ($list as $i => $r) {
        $lok = trim(sf($r['gedung'], '') . ' Lt.' . sf($r['lantai'], '-') . ' Kamar ' . sf($r['kamar'], '-'));
        $ci  = ($r['checkin_date']  ? tgl($r['checkin_date'])  : '') . ($r['checkin_time']  ? ' ' . substr($r['checkin_time'], 0, 5)  : '');
        $co  = ($r['checkout_date'] ? tgl($r['checkout_date']) : '') . ($r['checkout_time'] ? ' ' . substr($r['checkout_time'], 0, 5) : '');
        $dur = '';
        if ($r['checkin_date'] && $r['checkout_date']) {
            $m = (int)round((strtotime($r['checkout_date']) - strtotime($r['checkin_date'])) / 86400);
            $dur = " ({$m} malam)";
        }
        $out .= ($i + 1) . ". **{$r['nama']}** — " . sf($r['instansi'], '-') . "\n";
        $out .= "   📍 {$lok} | Masuk: " . trim($ci) . " | Keluar: " . trim($co) . $dur . "\n";
    }
    if ((int)(isset($tot['n']) ? $tot['n'] : 0) > 50)
        $out .= "\n_...dan " . ((int)$tot['n'] - 50) . " orang lainnya._\n";
    simpanKonteks('cekin_selesai', $aid);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansCekinCari(mysqli $c, string $q): string
{
    $t  = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $kw = $q;
    if (preg_match('/(?:cari\s+(?:peserta|nama|orang)?\s*(?:yang\s+)?|siapa\s+(?:peserta\s+)?|dimana\s+(?:peserta\s+)?)(.+)/iu', $kw, $m))
        $kw = trim($m[1]);
    $sw = ['yang', 'peserta', 'nama', 'instansi', 'dari', 'bernama', 'keluar', 'masuk', 'menginap', 'inap', 'sudah', 'belum', 'sedang', 'dengan', 'untuk'];
    foreach ($sw as $s2) $kw = preg_replace('/^' . $s2 . '\s+/iu', '', trim($kw));
    $kw = trim($kw);
    if (mb_strlen($kw) < 2) return "Sebutkan nama atau instansi (minimal 2 karakter).";
    $like  = '%' . $kw . '%';
    $found = rows($c, "SELECT pp.nama,pp.instansi,pp.peran,pp.jenis_kelamin,pp.gedung,pp.lantai,
                pp.kamar,pp.bed,pp.status_inap,pp.checkin_date,pp.checkin_time,
                pp.checkout_date,pp.checkout_time,
                ak.judul AS kegiatan, ak.start_date, ak.end_date
         FROM {$t} pp LEFT JOIN agenda_kegiatan ak ON ak.id=pp.agenda_id
         WHERE (pp.nama LIKE ? OR pp.instansi LIKE ?)
         ORDER BY ak.start_date DESC, pp.nama ASC LIMIT 50", 'ss', [$like, $like]);
    if (empty($found)) return "❌ Tidak ditemukan peserta dengan kata kunci \"**{$kw}**\".";
    $out = "🔍 Hasil \"**{$kw}**\" — ditemukan **" . count($found) . "** data\n\n";
    foreach ($found as $i => $r) {
        $lok  = trim(sf($r['gedung'], '') . ' Lt.' . sf($r['lantai'], '-') . ' Kamar ' . sf($r['kamar'], '-'));
        if (!empty($r['bed'])) $lok .= ' Bed ' . sf($r['bed']);
        $gen  = $r['jenis_kelamin'] === 'L' ? '♂' : ($r['jenis_kelamin'] === 'P' ? '♀' : '');
        $ci   = $co = $dur = '';
        if ($r['checkin_date'])  $ci = tgl($r['checkin_date']) . ($r['checkin_time'] ? ' ' . substr($r['checkin_time'], 0, 5) : '');
        if ($r['checkout_date']) {
            $co = tgl($r['checkout_date']) . ($r['checkout_time'] ? ' ' . substr($r['checkout_time'], 0, 5) : '');
            if ($r['checkin_date']) {
                $ml  = (int)round((strtotime($r['checkout_date']) - strtotime($r['checkin_date'])) / 86400);
                $dur = " ({$ml} malam)";
            }
        }
        $out .= ($i + 1) . ". **{$r['nama']}** {$gen} | " . sf($r['peran']) . "\n";
        $out .= "   🏛️ " . sf($r['instansi']) . "\n";
        $out .= "   📋 " . sf($r['kegiatan']) . " (" . tgl($r['start_date']) . " s/d " . tgl($r['end_date']) . ")\n";
        $out .= "   📍 {$lok} | " . sf($r['status_inap']);
        if ($ci) $out .= " | Masuk: {$ci}";
        if ($co) $out .= " | Keluar: {$co}{$dur}";
        $out .= "\n\n";
    }
    if (count($found) === 50) $out .= "_Ditampilkan 50 teratas._\n";
    simpanKonteks('cekin_cari', null, null, ['keyword' => $kw]);
    return $out . "_Data per " . nl2() . "_";
}

function ansCekinInstansi(mysqli $c, string $q): string
{
    $t     = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai    = resolveAg($c, $q);
    $ag    = !empty($ai['semua']) ? $ai['semua'][0] : [];
    $aid   = (int)(isset($ag['id']) ? $ag['id'] : 0);
    $judul = isset($ag['judul']) ? $ag['judul'] : ('Agenda #' . $aid);
    $list  = rows($c, "SELECT instansi,COUNT(*) AS n,
        SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
        SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS out_
        FROM {$t} WHERE agenda_id=? AND instansi IS NOT NULL AND instansi<>''
        GROUP BY instansi ORDER BY n DESC LIMIT 20", 'i', [$aid]);
    if (empty($list)) return variasiTidakAda('data instansi', $judul);
    $out = variasiPembuka("instansi peserta terbanyak", '', '🏛️') . "\n📌 {$judul}\n\n";
    foreach ($list as $i => $r) {
        $out .= ($i + 1) . ". **{$r['instansi']}** — **" . num($r['n']) . "** orang";
        $out .= " (✅" . num($r['inap']) . " inap | ⏳" . num($r['belum']) . " belum | 🚪" . num($r['out_']) . " keluar)\n";
    }
    simpanKonteks('cekin_instansi', $aid);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansCekinGender(mysqli $c, string $q): string
{
    $t     = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai    = resolveAg($c, $q);
    $ag    = !empty($ai['semua']) ? $ai['semua'][0] : [];
    $aid   = (int)(isset($ag['id']) ? $ag['id'] : 0);
    $judul = isset($ag['judul']) ? $ag['judul'] : ('Agenda #' . $aid);
    $s     = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN jenis_kelamin='L' THEN 1 ELSE 0 END) AS lk, SUM(CASE WHEN jenis_kelamin='P' THEN 1 ELSE 0 END) AS pr FROM {$t} WHERE agenda_id=?", 'i', [$aid]);
    $tot   = (int)(isset($s['n']) ? $s['n'] : 0);
    $out   = variasiPembuka('komposisi gender', '', '👥') . "\n📌 {$judul}\n\n";
    $out  .= "👥 Total     : **" . num($tot) . "** orang\n";
    $out  .= "♂ Laki-laki  : **" . num(isset($s['lk']) ? $s['lk'] : 0) . "** (" . pct(isset($s['lk']) ? $s['lk'] : 0, $tot) . ")\n";
    $out  .= "♀ Perempuan  : **" . num(isset($s['pr']) ? $s['pr'] : 0) . "** (" . pct(isset($s['pr']) ? $s['pr'] : 0, $tot) . ")\n";
    $gdg   = rows($c, "SELECT gedung, SUM(CASE WHEN jenis_kelamin='L' THEN 1 ELSE 0 END) AS lk, SUM(CASE WHEN jenis_kelamin='P' THEN 1 ELSE 0 END) AS pr FROM {$t} WHERE agenda_id=? GROUP BY gedung ORDER BY gedung", 'i', [$aid]);
    if (!empty($gdg)) {
        $out .= "\n**Per gedung:**\n";
        foreach ($gdg as $g) $out .= "🏨 **" . sf($g['gedung'], '?') . "** → ♂ " . num(isset($g['lk']) ? $g['lk'] : 0) . " | ♀ " . num(isset($g['pr']) ? $g['pr'] : 0) . "\n";
    }
    simpanKonteks('cekin_gender', $aid);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansCekinKamar(mysqli $c, string $q): string
{
    $t     = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai    = resolveAg($c, $q);
    $ag    = !empty($ai['semua']) ? $ai['semua'][0] : [];
    $aid   = (int)(isset($ag['id']) ? $ag['id'] : 0);
    $judul = isset($ag['judul']) ? $ag['judul'] : ('Agenda #' . $aid);
    $list  = rows($c, "SELECT gedung,lantai,kamar,COUNT(*) AS isi,
        SUM(CASE WHEN status_inap='Check-in' THEN 1 ELSE 0 END) AS inap,
        SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status_inap='Check-out' THEN 1 ELSE 0 END) AS out_
        FROM {$t} WHERE agenda_id=? GROUP BY gedung,lantai,kamar ORDER BY gedung,lantai,kamar", 'i', [$aid]);
    if (empty($list)) return variasiTidakAda('data kamar', $judul);
    $out   = variasiPembuka('detail per kamar', '', '🏨') . "\n📌 {$judul}\n";
    $lastG = '';
    foreach ($list as $r) {
        $g = sf($r['gedung'], '?');
        if ($g !== $lastG) {
            $out .= "\n🏨 **Gedung {$g}**\n";
            $lastG = $g;
        }
        $out .= "  Lt." . sf($r['lantai']) . " Kamar " . sf($r['kamar']) . " — " . num($r['isi']) . " org | ✅" . num($r['inap']) . " | ⏳" . num($r['belum']) . " | 🚪" . num($r['out_']) . "\n";
    }
    simpanKonteks('cekin_kamar', $aid);
    return $out . "\n_Data per " . nl2() . "_";
}

/* ══════════════════════════════════════════
   KERUSAKAN
══════════════════════════════════════════ */
function rusakJoinSQL(): string
{
    return "LEFT JOIN master_jenis_kerusakan  mjk  ON mjk.id  = lk.jenis_kerusakan_id
            LEFT JOIN master_kategori_kerusakan mkk ON mkk.id = lk.kategori_kerusakan_id
            LEFT JOIN master_lokasi             ml  ON ml.id  = lk.lokasi_id
            LEFT JOIN master_tipe_lokasi        mtl ON mtl.id = lk.tipe_lokasi_id
            LEFT JOIN master_lantai             mlt ON mlt.id = lk.lantai_id
            LEFT JOIN master_ruangan            mr  ON mr.id  = lk.ruangan_id";
}

function ansKerusakan(mysqli $c, array $r): string
{
    $tbl = rusakTbl($c);
    if (!$tbl) return "Tabel kerusakan tidak ditemukan.";
    $s = one($c, "SELECT COUNT(*) AS n,
        SUM(CASE WHEN lk.status='dilaporkan' THEN 1 ELSE 0 END) AS dlp,
        SUM(CASE WHEN lk.status IN ('diverifikasi','diterima_teknisi','dalam_perbaikan','menunggu_part','diproses') THEN 1 ELSE 0 END) AS pros,
        SUM(CASE WHEN lk.status='selesai' THEN 1 ELSE 0 END) AS ok,
        SUM(CASE WHEN lk.status='ditolak' THEN 1 ELSE 0 END) AS tlk,
        SUM(CASE WHEN lk.prioritas='darurat' THEN 1 ELSE 0 END) AS darurat,
        AVG(CASE WHEN lk.status='selesai' AND lk.selesai_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,lk.created_at,lk.selesai_at) END) AS avg_jam
        FROM {$tbl} lk WHERE DATE(lk.created_at) BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    $tot = (int)(isset($s['n']) ? $s['n'] : 0);
    if (!$tot) return variasiTidakAda('laporan kerusakan', $r['l']);
    $out  = variasiPembuka('laporan kerusakan', $r['l'], '🔧') . "\n\n";
    $out .= "📊 Total      : **" . num($tot) . "**\n";
    $out .= "🔴 Dilaporkan : **" . num($s['dlp'])  . "** (" . pct($s['dlp'],  $tot) . ")\n";
    $out .= "🟡 Diproses   : **" . num($s['pros']) . "** (" . pct($s['pros'], $tot) . ")\n";
    $out .= "🟢 Selesai    : **" . num($s['ok'])   . "** (" . pct($s['ok'],   $tot) . ")\n";
    if ((int)(isset($s['tlk'])    ? $s['tlk']    : 0) > 0) $out .= "⛔ Ditolak    : **" . num($s['tlk']) . "**\n";
    if ((int)(isset($s['darurat']) ? $s['darurat'] : 0) > 0) $out .= "🚨 Darurat    : **" . num($s['darurat']) . "**\n";
    if (!empty($s['avg_jam']))      $out .= "⏱️ Rata-rata selesai: **" . round($s['avg_jam']) . " jam**\n";
    $pri = rows($c, "SELECT lk.prioritas AS l,COUNT(*) AS c FROM {$tbl} lk WHERE DATE(lk.created_at) BETWEEN ? AND ? GROUP BY lk.prioritas ORDER BY FIELD(lk.prioritas,'darurat','tinggi','sedang','rendah')", 'ss', [$r['s'], $r['e']]);
    if (!empty($pri)) {
        $out .= "\n**Per prioritas:**\n";
        $ic = ['darurat' => '🚨', 'tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'];
        foreach ($pri as $p) $out .= (isset($ic[strtolower($p['l'] ?? '')]) ? $ic[strtolower($p['l'] ?? '')] : '⚪') . " " . sf($p['l'], '-') . ": **" . num($p['c']) . "**\n";
    }
    $tek = rows($c, "SELECT lk.teknisi_nama,COUNT(*) AS n,SUM(CASE WHEN lk.status='selesai' THEN 1 ELSE 0 END) AS ok
        FROM {$tbl} lk WHERE lk.teknisi_nama IS NOT NULL AND lk.teknisi_nama<>'' AND DATE(lk.created_at) BETWEEN ? AND ?
        GROUP BY lk.teknisi_nama ORDER BY n DESC LIMIT 5", 'ss', [$r['s'], $r['e']]);
    if (!empty($tek)) {
        $out .= "\n**Teknisi aktif:**\n";
        foreach ($tek as $i => $row) $out .= ($i + 1) . ". **" . sf($row['teknisi_nama']) . "** — " . num($row['n']) . " laporan | ✅" . num($row['ok']) . " selesai (" . pct($row['ok'], $row['n']) . ")\n";
    }
    $js   = rusakJoinSQL();
    $list = rows($c, "SELECT lk.id, lk.pelapor_nama, lk.deskripsi, lk.status, lk.prioritas, lk.teknisi_nama,
                lk.created_at, mjk.nama_jenis, mkk.nama_kategori, ml.nama_lokasi, mr.nama_ruangan
        FROM {$tbl} lk {$js} WHERE DATE(lk.created_at) BETWEEN ? AND ? ORDER BY lk.created_at DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    if (!empty($list)) {
        $out .= "\n**Laporan terbaru:**\n";
        $icoMap = ['dilaporkan' => '🔴', 'selesai' => '🟢', 'ditolak' => '⛔', 'darurat' => '🚨'];
        foreach ($list as $row) {
            $desk  = mb_substr(trim((string)$row['deskripsi']), 0, 75);
            $tek   = sf($row['teknisi_nama'], 'Belum ditugaskan');
            $tgl_  = $row['created_at'] ? date('d/m H:i', strtotime($row['created_at'])) : '-';
            $lok   = implode(' › ', array_filter([sf(isset($row['nama_lokasi'])  ? $row['nama_lokasi']  : '', ''), sf(isset($row['nama_ruangan']) ? $row['nama_ruangan'] : '', '')]));
            $jenis = sf(isset($row['nama_jenis'])    ? $row['nama_jenis']    : '', '');
            $kat   = sf(isset($row['nama_kategori']) ? $row['nama_kategori'] : '', '');
            $info  = implode(' | ', array_filter([$kat, $jenis, $lok]));
            $ico   = isset($icoMap[$row['status']]) ? $icoMap[$row['status']] : '🟡';
            $out  .= "{$ico} **#{$row['id']}** [{$tgl_}] " . sf($row['prioritas'], '-') . " | {$row['pelapor_nama']}\n";
            if ($info) $out .= "   📍 {$info}\n";
            $out  .= "   💬 {$desk}\n   🔧 {$tek}\n";
        }
    }
    simpanKonteks('kerusakan', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansRusakPending(mysqli $c): string
{
    $tbl = rusakTbl($c);
    if (!$tbl) return "Tabel tidak ditemukan.";
    $tot  = one($c, "SELECT COUNT(*) AS n FROM {$tbl} WHERE status NOT IN ('selesai','ditolak')");
    $js   = rusakJoinSQL();
    $list = rows($c, "SELECT lk.id, lk.pelapor_nama, lk.deskripsi, lk.status, lk.prioritas, lk.teknisi_nama,
                lk.created_at, mjk.nama_jenis, mkk.nama_kategori, ml.nama_lokasi, mr.nama_ruangan
        FROM {$tbl} lk {$js} WHERE lk.status NOT IN ('selesai','ditolak')
        ORDER BY FIELD(lk.prioritas,'darurat','tinggi','sedang','rendah'), lk.created_at ASC LIMIT 25");
    if (empty($list)) return "✅ **Semua kerusakan sudah tertangani!**";
    $out = variasiPembuka('kerusakan belum selesai', '', '⏳') . " (" . num(isset($tot['n']) ? $tot['n'] : 0) . " laporan)\n\n";
    $priIcon = ['darurat' => '🚨', 'tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'];
    foreach ($list as $i => $r) {
        $desk = mb_substr(trim((string)$r['deskripsi']), 0, 75);
        $tek  = sf($r['teknisi_nama'], 'Belum ditugaskan');
        $tgl_ = $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '-';
        $hari = $r['created_at'] ? (int)round((time() - strtotime($r['created_at'])) / 86400) : 0;
        $umur = $hari > 0 ? " ⌛{$hari} hari" : '';
        $lok  = implode(' › ', array_filter([sf(isset($r['nama_lokasi']) ? $r['nama_lokasi'] : '', ''), sf(isset($r['nama_ruangan']) ? $r['nama_ruangan'] : '', '')]));
        $ico  = isset($priIcon[$r['prioritas'] ?? '']) ? $priIcon[$r['prioritas']] : '⚪';
        $out .= ($i + 1) . ". {$ico} **#{$r['id']}** | " . sf($r['prioritas'], '-') . " | {$tgl_}{$umur}\n";
        if ($lok) $out .= "   📍 " . implode(' › ', array_filter([sf(isset($r['nama_jenis']) ? $r['nama_jenis'] : '', ''), $lok])) . "\n";
        $out .= "   👤 {$r['pelapor_nama']} | 💬 {$desk}\n";
        $out .= "   🔧 {$tek} | " . sf($r['status']) . "\n\n";
    }
    simpanKonteks('rusak_pending');
    return $out . "_Data per " . nl2() . "_";
}

function ansRusakTeknisi(mysqli $c, array $r): string
{
    $tbl = rusakTbl($c);
    if (!$tbl) return "Tabel tidak ditemukan.";
    $list = rows($c, "SELECT teknisi_nama,COUNT(*) AS n,
        SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END) AS ok,
        AVG(CASE WHEN status='selesai' AND selesai_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,created_at,selesai_at) END) AS avg_jam
        FROM {$tbl} WHERE teknisi_nama IS NOT NULL AND teknisi_nama<>'' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY teknisi_nama ORDER BY n DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return variasiTidakAda('data teknisi', $r['l']);
    $out = variasiPembuka('ranking teknisi aktif', $r['l'], '🏆') . "\n\n";
    foreach ($list as $i => $row) {
        $avg = !empty($row['avg_jam']) ? " | avg " . round($row['avg_jam']) . " jam" : '';
        $out .= ($i + 1) . ". **{$row['teknisi_nama']}** — **" . num($row['n']) . "** laporan\n";
        $out .= "   ✅ " . num($row['ok']) . " selesai (" . pct($row['ok'], $row['n']) . "){$avg}\n";
    }
    simpanKonteks('rusak_teknisi', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansRusakJenis(mysqli $c, array $r): string
{
    $tbl = rusakTbl($c);
    if (!$tbl) return "Tabel tidak ditemukan.";
    $list = rows($c, "SELECT mjk.nama_jenis AS label, COUNT(*) AS c
        FROM {$tbl} lk LEFT JOIN master_jenis_kerusakan mjk ON mjk.id=lk.jenis_kerusakan_id
        WHERE DATE(lk.created_at) BETWEEN ? AND ? AND mjk.nama_jenis IS NOT NULL
        GROUP BY mjk.nama_jenis ORDER BY c DESC LIMIT 15", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return variasiTidakAda('data jenis kerusakan', $r['l']);
    $out = variasiPembuka('jenis kerusakan terbanyak', $r['l'], '🔧') . "\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['label']) . "** — " . num($row['c']) . " laporan\n";
    simpanKonteks('rusak_jenis', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansRusakPerKategori(mysqli $c, array $r): string
{
    $tbl = rusakTbl($c);
    if (!$tbl) return "Tabel tidak ditemukan.";
    $list = rows($c, "SELECT mkk.nama_kategori AS kat, COUNT(*) AS n,
        SUM(CASE WHEN lk.status='selesai' THEN 1 ELSE 0 END) AS ok,
        SUM(CASE WHEN lk.status NOT IN ('selesai','ditolak') THEN 1 ELSE 0 END) AS pending
        FROM {$tbl} lk
        LEFT JOIN master_kategori_kerusakan mkk ON mkk.id=lk.kategori_kerusakan_id
        WHERE DATE(lk.created_at) BETWEEN ? AND ?
        GROUP BY mkk.nama_kategori ORDER BY n DESC", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return variasiTidakAda('data kerusakan per kategori', $r['l']);
    $out = variasiPembuka('kerusakan per kategori', $r['l'], '📊') . "\n\n";
    foreach ($list as $i => $row) {
        $kat = sf($row['kat'], 'Tidak diketahui');
        $out .= ($i + 1) . ". **{$kat}** — **" . num($row['n']) . "** laporan | ✅" . num($row['ok']) . " selesai | ⏳" . num($row['pending']) . " pending\n";
    }
    simpanKonteks('rusak_per_kategori', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansRusakPerLokasi(mysqli $c, array $r): string
{
    $tbl = rusakTbl($c);
    if (!$tbl) return "Tabel tidak ditemukan.";
    $list = rows($c, "SELECT ml.nama_lokasi AS lokasi, mtl.nama_tipe_lokasi AS tipe,
        COUNT(*) AS n,
        SUM(CASE WHEN lk.status NOT IN ('selesai','ditolak') THEN 1 ELSE 0 END) AS pending
        FROM {$tbl} lk
        LEFT JOIN master_lokasi ml ON ml.id=lk.lokasi_id
        LEFT JOIN master_tipe_lokasi mtl ON mtl.id=lk.tipe_lokasi_id
        WHERE DATE(lk.created_at) BETWEEN ? AND ? AND ml.nama_lokasi IS NOT NULL
        GROUP BY ml.nama_lokasi, mtl.nama_tipe_lokasi ORDER BY n DESC LIMIT 15", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return variasiTidakAda('data lokasi kerusakan', $r['l']);
    $out = variasiPembuka('lokasi paling sering rusak', $r['l'], '📍') . "\n\n";
    foreach ($list as $i => $row) {
        $lok  = sf($row['lokasi'], '-');
        $tipe = sf($row['tipe'], '');
        $lbl  = $tipe ? "{$lok} ({$tipe})" : $lok;
        $out .= ($i + 1) . ". **{$lbl}** — **" . num($row['n']) . "** laporan | ⏳" . num($row['pending']) . " pending\n";
    }
    simpanKonteks('rusak_per_lokasi', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

/* ══════════════════════════════════════════
   CHECKLIST
══════════════════════════════════════════ */
function ansChecklist(mysqli $c, array $r): string
{
    $s = one($c, "SELECT COUNT(*) AS n, COUNT(DISTINCT nip_user) AS usr,
        COUNT(DISTINCT nama_petugas) AS pt, COUNT(DISTINCT form_type) AS fm,
        COUNT(DISTINCT CASE WHEN area_kerja<>'' THEN area_kerja END) AS ar
        FROM checklist_forms WHERE tanggal BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    if (!(int)(isset($s['n']) ? $s['n'] : 0)) return variasiTidakAda('data checklist', $r['l']);
    $perForm = rows($c, "SELECT form_type,COUNT(*) AS c FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY form_type ORDER BY c DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $petugas = rows($c, "SELECT nama_petugas,COUNT(*) AS c FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY nama_petugas ORDER BY c DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $out  = variasiPembuka('checklist', $r['l'], '📋') . "\n\n";
    $out .= "📋 Total form: **" . num($s['n']) . "** | 👤 Petugas: **" . num($s['pt']) . "** | 📄 Jenis: **" . num($s['fm']) . "** | 🗺️ Area: **" . num($s['ar']) . "**\n";
    if (!empty($perForm)) {
        $out .= "\n**Per jenis form:**\n";
        foreach ($perForm as $row) $out .= "• " . sf($row['form_type']) . ": " . num($row['c']) . " form\n";
    }
    if (!empty($petugas)) {
        $out .= "\n**Petugas aktif:**\n";
        foreach ($petugas as $i => $row) $out .= ($i + 1) . ". " . sf($row['nama_petugas']) . " — " . num($row['c']) . " form\n";
    }
    simpanKonteks('checklist', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansChecklistArea(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT area_kerja,COUNT(*) AS c,COUNT(DISTINCT nip_user) AS usr FROM checklist_forms WHERE tanggal BETWEEN ? AND ? AND area_kerja IS NOT NULL AND area_kerja<>'' GROUP BY area_kerja ORDER BY c DESC LIMIT 20", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return variasiTidakAda('data checklist per area', $r['l']);
    $out = variasiPembuka('checklist per area kerja', $r['l'], '🗺️') . "\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['area_kerja']) . "** — " . num($row['c']) . " form | " . num($row['usr']) . " petugas\n";
    simpanKonteks('checklist_area', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansChecklistRegu(mysqli $c, array $r): string
{
    if (col($c, 'checklist_forms', 'regu')) {
        $list = rows($c, "SELECT regu,COUNT(*) AS c,COUNT(DISTINCT nip_user) AS usr FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY regu ORDER BY c DESC", 'ss', [$r['s'], $r['e']]);
        $out  = variasiPembuka('checklist per regu', $r['l'], '👥') . "\n\n";
        foreach ($list as $row) $out .= "• **" . sf($row['regu'], 'Tanpa Regu') . "**: " . num($row['c']) . " form | " . num($row['usr']) . " petugas\n";
        simpanKonteks('checklist_regu', null, $r['l']);
        return $out . "\n_Data per " . nl2() . "_";
    }
    return ansChecklist($c, $r);
}

function ansChecklistTop(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT nama_petugas,COUNT(*) AS c,COUNT(DISTINCT form_type) AS jenis,COUNT(DISTINCT area_kerja) AS area FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY nama_petugas ORDER BY c DESC LIMIT 15", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return variasiTidakAda('data checklist', $r['l']);
    $out = variasiPembuka('ranking petugas aktif', $r['l'], '🏆') . "\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['nama_petugas']) . "** — " . num($row['c']) . " form | " . num($row['jenis']) . " jenis | " . num($row['area']) . " area\n";
    simpanKonteks('checklist_top', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansChecklistUser(mysqli $c, array $r): string
{
    if (tbl($c, 'users')) {
        $list = rows($c, "SELECT u.nama AS nm, u.role, COUNT(cf.id) AS n,
                COUNT(DISTINCT cf.form_type) AS jenis, COUNT(DISTINCT cf.area_kerja) AS area,
                MIN(cf.tanggal) AS tgl_awal, MAX(cf.tanggal) AS tgl_akhir
            FROM checklist_forms cf
            LEFT JOIN users u ON u.nip = cf.nip_user
            WHERE cf.tanggal BETWEEN ? AND ?
            GROUP BY cf.nip_user, u.nama, u.role
            ORDER BY n DESC LIMIT 20", 'ss', [$r['s'], $r['e']]);
        if (!empty($list)) {
            $out = variasiPembuka('akun yang sering isi checklist', $r['l'], '👤') . "\n\n";
            foreach ($list as $i => $row) {
                $role    = $row['role'] ? " (" . sf($row['role']) . ")" : '';
                $periode = tgl($row['tgl_awal']) . " s/d " . tgl($row['tgl_akhir']);
                $out .= ($i + 1) . ". **" . sf($row['nm'], 'Tidak Diketahui') . "{$role}**\n";
                $out .= "   📋 " . num($row['n']) . " form | 📄 " . num($row['jenis']) . " jenis | 🗺️ " . num($row['area']) . " area | 📅 {$periode}\n\n";
            }
            simpanKonteks('checklist_user', null, $r['l']);
            return $out . "_Data per " . nl2() . "_";
        }
    }
    $list = rows($c, "SELECT nama_petugas, COUNT(*) AS n, COUNT(DISTINCT form_type) AS jenis, MIN(tanggal) AS tgl_awal, MAX(tanggal) AS tgl_akhir FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY nama_petugas ORDER BY n DESC LIMIT 20", 'ss', [$r['s'], $r['e']]);
    $out  = variasiPembuka('petugas paling sering isi checklist', $r['l'], '👤') . "\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['nama_petugas']) . "** — " . num($row['n']) . " form | " . num($row['jenis']) . " jenis | " . tgl($row['tgl_awal']) . " s/d " . tgl($row['tgl_akhir']) . "\n";
    simpanKonteks('checklist_user', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansChecklistCatatan(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT cf.tanggal, cf.nama_petugas, cf.form_type, cf.area_kerja,
                cf.area_gedung, cf.catatan_kerusakan
        FROM checklist_forms cf
        WHERE cf.tanggal BETWEEN ? AND ? AND cf.catatan_kerusakan IS NOT NULL AND cf.catatan_kerusakan<>''
        ORDER BY cf.tanggal DESC, cf.id DESC LIMIT 30", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return variasiTidakAda('catatan kerusakan pada checklist', $r['l']);
    $out = variasiPembuka('catatan kerusakan dari checklist', $r['l'], '📝') . " (" . count($list) . " catatan)\n\n";
    foreach ($list as $i => $row) {
        $area = implode(' › ', array_filter([sf(isset($row['area_gedung']) ? $row['area_gedung'] : '', ''), sf(isset($row['area_kerja']) ? $row['area_kerja'] : '', '')]));
        $out .= ($i + 1) . ". **" . tgl($row['tanggal']) . "** | " . sf($row['nama_petugas']) . " | " . sf($row['form_type']) . "\n";
        if ($area) $out .= "   📍 {$area}\n";
        $out .= "   📝 " . mb_substr(trim((string)$row['catatan_kerusakan']), 0, 120) . "\n\n";
    }
    simpanKonteks('checklist_catatan', null, $r['l']);
    return $out . "_Data per " . nl2() . "_";
}

/* ══════════════════════════════════════════
   SURAT
══════════════════════════════════════════ */
function ansSurat(mysqli $c, array $r, string $mode = 'semua'): string
{
    $s = one($c, "SELECT SUM(CASE WHEN jenis='masuk' THEN 1 ELSE 0 END) AS mk, SUM(CASE WHEN jenis='keluar' THEN 1 ELSE 0 END) AS kl, COUNT(*) AS n FROM arsip_surat WHERE tanggal_surat BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    if (!(int)(isset($s['n']) ? $s['n'] : 0)) return variasiTidakAda('arsip surat', $r['l']);
    $ex   = $mode === 'masuk' ? " AND jenis='masuk'" : ($mode === 'keluar' ? " AND jenis='keluar'" : '');
    $list = rows($c, "SELECT nomor_surat,perihal,pengirim,jenis,tanggal_surat,keterangan FROM arsip_surat WHERE tanggal_surat BETWEEN ? AND ?{$ex} ORDER BY tanggal_surat DESC,id DESC LIMIT 15", 'ss', [$r['s'], $r['e']]);
    $lb   = $mode === 'masuk' ? ' masuk' : ($mode === 'keluar' ? ' keluar' : '');
    $out  = variasiPembuka("arsip surat{$lb}", $r['l'], '📨') . "\n\n";
    $out .= "📥 Masuk: **" . num($s['mk']) . "** | 📤 Keluar: **" . num($s['kl']) . "**\n\n";
    foreach ($list as $row) {
        $ic = $row['jenis'] === 'masuk' ? '📥' : '📤';
        $out .= "{$ic} **[" . sf($row['nomor_surat']) . "]** — " . tgl($row['tanggal_surat']) . "\n";
        $out .= "   📝 " . sf($row['perihal']) . "\n   👤 " . sf($row['pengirim']) . "\n";
        if (!empty($row['keterangan'])) $out .= "   ℹ️ " . sf($row['keterangan']) . "\n";
        $out .= "\n";
    }
    simpanKonteks('surat', null, $r['l']);
    return $out . "_Data per " . nl2() . "_";
}

function ansSuratPengirim(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT pengirim,COUNT(*) AS c FROM arsip_surat WHERE tanggal_surat BETWEEN ? AND ? GROUP BY pengirim ORDER BY c DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return variasiTidakAda('data surat', $r['l']);
    $out = variasiPembuka('pengirim surat terbanyak', $r['l'], '📨') . "\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['pengirim']) . "** — " . num($row['c']) . " surat\n";
    simpanKonteks('surat_pengirim', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

/* ══════════════════════════════════════════
   GUDANG
══════════════════════════════════════════ */
function ansGudang(mysqli $c, array $r): string
{
    $m   = one($c, "SELECT COUNT(DISTINCT bm.id) AS tr,COALESCE(SUM(bmd.qty),0) AS qty FROM barang_masuk bm LEFT JOIN barang_masuk_detail bmd ON bmd.barang_masuk_id=bm.id WHERE bm.tanggal BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    $k   = one($c, "SELECT COUNT(DISTINCT bk.id) AS tr,COALESCE(SUM(bkd.qty),0) AS qty FROM barang_keluar bk LEFT JOIN barang_keluar_detail bkd ON bkd.barang_keluar_id=bk.id WHERE bk.tanggal BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    $out = variasiPembuka('rekap gudang', $r['l'], '📦') . "\n\n";
    $out .= "📦 Barang masuk  : **" . num(isset($m['tr']) ? $m['tr'] : 0) . "** transaksi | **" . num(isset($m['qty']) ? $m['qty'] : 0) . "** item\n";
    $out .= "📤 Barang keluar : **" . num(isset($k['tr']) ? $k['tr'] : 0) . "** transaksi | **" . num(isset($k['qty']) ? $k['qty'] : 0) . "** item\n";
    $selisih = (float)(isset($m['qty']) ? $m['qty'] : 0) - (float)(isset($k['qty']) ? $k['qty'] : 0);
    $out .= "📊 Net           : **" . num(abs($selisih)) . "** item " . ($selisih >= 0 ? '➕' : '➖') . "\n";
    simpanKonteks('gudang', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansGudangTop(mysqli $c, array $r): string
{
    $topM = rows($c, "SELECT bmd.nama_barang,SUM(bmd.qty) AS qty FROM barang_masuk_detail bmd INNER JOIN barang_masuk bm ON bm.id=bmd.barang_masuk_id WHERE bm.tanggal BETWEEN ? AND ? GROUP BY bmd.nama_barang ORDER BY qty DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $topK = rows($c, "SELECT bkd.nama_barang,SUM(bkd.qty) AS qty FROM barang_keluar_detail bkd INNER JOIN barang_keluar bk ON bk.id=bkd.barang_keluar_id WHERE bk.tanggal BETWEEN ? AND ? GROUP BY bkd.nama_barang ORDER BY qty DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $out  = variasiPembuka('barang terbanyak', $r['l'], '📦') . "\n\n";
    if (!empty($topM)) {
        $out .= "📦 **Masuk terbanyak:**\n";
        foreach ($topM as $i => $row) $out .= ($i + 1) . ". " . sf($row['nama_barang']) . " — **" . num($row['qty']) . "** item\n";
    }
    if (!empty($topK)) {
        $out .= "\n📤 **Keluar terbanyak:**\n";
        foreach ($topK as $i => $row) $out .= ($i + 1) . ". " . sf($row['nama_barang']) . " — **" . num($row['qty']) . "** item\n";
    }
    simpanKonteks('gdg_top', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansGudangStok(mysqli $c): string
{
    if (!tbl($c, 'master_barang')) return "Tabel master_barang tidak tersedia.";
    $tot  = one($c, "SELECT COUNT(*) AS n FROM master_barang");
    $list = rows($c, "SELECT mb.kode_barang, mb.nama_barang, mb.satuan, mb.stok_awal,
                COALESCE((SELECT SUM(bmd.qty) FROM barang_masuk_detail bmd WHERE bmd.kode_barang=mb.kode_barang),0) AS masuk,
                COALESCE((SELECT SUM(bkd.qty) FROM barang_keluar_detail bkd WHERE bkd.kode_barang=mb.kode_barang),0) AS keluar
        FROM master_barang mb ORDER BY mb.nama_barang ASC LIMIT 30");
    $out = variasiPembuka('daftar stok barang', '', '📦') . " (" . num(isset($tot['n']) ? $tot['n'] : 0) . " item)\n\n";
    foreach ($list as $i => $row) {
        $stok = (int)(isset($row['stok_awal']) ? $row['stok_awal'] : 0) + (int)(isset($row['masuk']) ? $row['masuk'] : 0) - (int)(isset($row['keluar']) ? $row['keluar'] : 0);
        $icon = $stok <= 0 ? '🔴' : ($stok < 10 ? '🟡' : '🟢');
        $out .= ($i + 1) . ". {$icon} **" . sf($row['nama_barang']) . "** — Stok: **" . num($stok) . "** " . sf($row['satuan'], 'pcs') . "\n";
    }
    if ((int)(isset($tot['n']) ? $tot['n'] : 0) > 30) $out .= "\n_...ditampilkan 30 teratas._\n";
    simpanKonteks('gudang_stok');
    return $out . "\n_Data per " . nl2() . "_";
}

/* ══════════════════════════════════════════
   TAMU
══════════════════════════════════════════ */
function ansTamu(mysqli $c, array $r): string
{
    $s = one($c, "SELECT COUNT(*) AS n,
        SUM(CASE WHEN jenis_layanan='pelayanan_umum'      THEN 1 ELSE 0 END) AS um,
        SUM(CASE WHEN jenis_layanan='pelayanan_informasi' THEN 1 ELSE 0 END) AS inf,
        SUM(CASE WHEN jenis_layanan='pelayanan_pengaduan' THEN 1 ELSE 0 END) AS peng
        FROM buku_tamu WHERE DATE(created_at) BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    if (!(int)(isset($s['n']) ? $s['n'] : 0)) return variasiTidakAda('data tamu', $r['l']);
    $list = rows($c, "SELECT nama, asal, keperluan, jenis_layanan, created_at FROM buku_tamu WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $out  = variasiPembuka('buku tamu', $r['l'], '👥') . "\n\n";
    $out .= "👥 Total: **" . num($s['n']) . "** | Umum: **" . num($s['um']) . "** | Info: **" . num($s['inf']) . "** | Pengaduan: **" . num($s['peng']) . "**\n\n";
    foreach ($list as $row) {
        $asal = !empty($row['asal']) ? ' (' . $row['asal'] . ')' : '';
        $jam  = $row['created_at'] ? date('H:i', strtotime($row['created_at'])) : '';
        $out .= "• **" . sf($row['nama']) . "{$asal}** — " . sf($row['keperluan']) . " [{$jam}]\n";
    }
    simpanKonteks('tamu', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansTamuInstansi(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT asal AS inst,COUNT(*) AS c FROM buku_tamu WHERE DATE(created_at) BETWEEN ? AND ? AND asal IS NOT NULL AND asal<>'' GROUP BY asal ORDER BY c DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return variasiTidakAda('data instansi tamu', $r['l']);
    $out = variasiPembuka('instansi tamu terbanyak', $r['l'], '🏛️') . "\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['inst']) . "** — " . num($row['c']) . " kunjungan\n";
    simpanKonteks('tamu_instansi', null, $r['l']);
    return $out . "\n_Data per " . nl2() . "_";
}

/* ══════════════════════════════════════════
   KENDARAAN
══════════════════════════════════════════ */
function ansKendaraan(mysqli $c): string
{
    if (!tbl($c, 'kendaraan_log')) return "Data kendaraan tamu belum tersedia.";
    $t          = td();
    $s          = one($c, "SELECT SUM(CASE WHEN status='masuk' THEN 1 ELSE 0 END) AS mk, SUM(CASE WHEN status='keluar' THEN 1 ELSE 0 END) AS kl FROM kendaraan_log WHERE DATE(waktu_masuk)=?", 's', [$t]);
    $hasDicatat = col($c, 'kendaraan_log', 'dicatat_oleh');
    $selExtra   = $hasDicatat ? ',dicatat_oleh' : '';
    $list       = rows($c, "SELECT plat_nomor,instansi_tamu,tujuan,waktu_masuk,waktu_keluar,status{$selExtra} FROM kendaraan_log ORDER BY waktu_masuk DESC LIMIT 15");
    $out        = variasiPembuka('log kendaraan tamu hari ini', '', '🚗') . "\n\n";
    $out       .= "🚗 Masuk: **" . num(isset($s['mk']) ? $s['mk'] : 0) . "** | Keluar: **" . num(isset($s['kl']) ? $s['kl'] : 0) . "**\n\n";
    foreach ($list as $r) {
        $ic = $r['status'] === 'masuk' ? '🟢' : '🔴';
        $wm = $r['waktu_masuk']  ? date('d/m H:i', strtotime($r['waktu_masuk']))  : '-';
        $wk = $r['waktu_keluar'] ? ' | Keluar: ' . date('H:i', strtotime($r['waktu_keluar'])) : '';
        $dc = ($hasDicatat && !empty($r['dicatat_oleh'])) ? " | Dicatat: " . sf($r['dicatat_oleh']) : '';
        $out .= "{$ic} **" . sf($r['plat_nomor']) . "** — " . sf($r['tujuan']) . "\n";
        $out .= "   " . sf($r['instansi_tamu']) . " | {$wm}{$wk}{$dc}\n";
    }
    simpanKonteks('kendaraan');
    return $out . "\n_Data per " . nl2() . "_";
}

function ansKendaraanOperasional(mysqli $c): string
{
    if (!tbl($c, 'kendaraan_operasional_log')) return "Data kendaraan operasional belum tersedia.";
    $s    = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN status='keluar' THEN 1 ELSE 0 END) AS keluar, SUM(CASE WHEN status='kembali' THEN 1 ELSE 0 END) AS kembali FROM kendaraan_operasional_log");
    $list = rows($c, "SELECT plat_nomor,pengemudi,tujuan,keterangan,waktu_keluar,waktu_kembali,status,dicatat_oleh FROM kendaraan_operasional_log ORDER BY waktu_keluar DESC LIMIT 15");
    $out  = variasiPembuka('kendaraan operasional/dinas', '', '🚐') . "\n\n";
    $out .= "🚐 Total: **" . num(isset($s['n']) ? $s['n'] : 0) . "** | Belum kembali: **" . num(isset($s['keluar']) ? $s['keluar'] : 0) . "** | Sudah kembali: **" . num(isset($s['kembali']) ? $s['kembali'] : 0) . "**\n\n";
    foreach ($list as $r) {
        $ic = $r['status'] === 'keluar' ? '🟡' : '✅';
        $wk = $r['waktu_keluar']  ? date('d/m H:i', strtotime($r['waktu_keluar']))  : '-';
        $wb = $r['waktu_kembali'] ? date('d/m H:i', strtotime($r['waktu_kembali'])) : '-';
        $out .= "{$ic} **" . sf($r['plat_nomor']) . "** — " . sf($r['pengemudi']) . "\n";
        $out .= "   Tujuan: " . sf($r['tujuan']) . " | Keluar: {$wk} | Kembali: {$wb}\n";
        if (!empty($r['keterangan'])) $out .= "   ℹ️ " . sf($r['keterangan']) . "\n";
    }
    simpanKonteks('kendaraan_ops');
    return $out . "\n_Data per " . nl2() . "_";
}

/* ══════════════════════════════════════════
   PENGGUNA
══════════════════════════════════════════ */
function ansUserRole(mysqli $c, string $q): string
{
    if (!tbl($c, 'users')) return "Tabel users tidak ditemukan.";
    $allRoles = ['admin', 'pimpinan', 'petugas', 'security', 'ob', 'teknisi', 'koordinator', 'poliklinik', 'gudang', 'driver', 'perpustakaan', 'sekretariat', 'supervisor'];
    $roleIcon = [
        'admin' => '👑',
        'pimpinan' => '🏛️',
        'koordinator' => '📋',
        'supervisor' => '🔍',
        'teknisi' => '🔧',
        'security' => '🛡️',
        'ob' => '🧹',
        'driver' => '🚗',
        'poliklinik' => '🏥',
        'gudang' => '📦',
        'perpustakaan' => '📚',
        'sekretariat' => '📝',
        'petugas' => '👤'
    ];
    $foundRole = null;
    foreach ($allRoles as $r) {
        if (mb_strpos($q, $r) !== false) {
            $foundRole = $r;
            break;
        }
    }
    if (!$foundRole) return ansPengguna($c);
    $list = rows($c, "SELECT nama, nip, role, phone, created_at FROM users WHERE role=? ORDER BY nama ASC", 's', [$foundRole]);
    $icon = isset($roleIcon[$foundRole]) ? $roleIcon[$foundRole] : '👤';
    if (empty($list)) {
        $existing = rows($c, "SELECT DISTINCT role, COUNT(*) AS c FROM users GROUP BY role ORDER BY role ASC");
        $out = "Tidak ada pengguna dengan role **{$foundRole}**.\n\n**Role yang tersedia:**\n";
        foreach ($existing as $row) $out .= (isset($roleIcon[$row['role']]) ? $roleIcon[$row['role']] : '👤') . " {$row['role']}: **" . num($row['c']) . "** orang\n";
        return $out . "\n_Data per " . nl2() . "_";
    }
    $out = "{$icon} **Daftar {$foundRole}** (" . count($list) . " orang):\n\n";
    foreach ($list as $i => $r) {
        $nip   = !empty($r['nip'])   ? " | NIP: {$r['nip']}"   : '';
        $phone = !empty($r['phone']) ? " | 📱 {$r['phone']}"   : '';
        $out  .= ($i + 1) . ". **" . sf($r['nama']) . "**{$nip}{$phone}\n";
    }
    simpanKonteks('user_role', null, null, ['role' => $foundRole]);
    return $out . "\n_Data per " . nl2() . "_";
}

function ansPengguna(mysqli $c): string
{
    if (!tbl($c, 'users')) return "Tabel users tidak ditemukan.";
    $roleIcon = [
        'admin' => '👑',
        'pimpinan' => '🏛️',
        'koordinator' => '📋',
        'supervisor' => '🔍',
        'teknisi' => '🔧',
        'security' => '🛡️',
        'ob' => '🧹',
        'driver' => '🚗',
        'poliklinik' => '🏥',
        'gudang' => '📦',
        'perpustakaan' => '📚',
        'sekretariat' => '📝',
        'petugas' => '👤'
    ];
    $tot     = one($c, "SELECT COUNT(*) AS n FROM users");
    $perRole = rows($c, "SELECT role, COUNT(*) AS c FROM users GROUP BY role ORDER BY c DESC");
    $out     = variasiPembuka('data pengguna sistem', '', '👤') . "\n\n";
    $out    .= "👤 Total akun: **" . num(isset($tot['n']) ? $tot['n'] : 0) . "** orang\n\n**Rekap per role:**\n";
    foreach ($perRole as $r) $out .= (isset($roleIcon[$r['role']]) ? $roleIcon[$r['role']] : '👤') . " **{$r['role']}** : **" . num($r['c']) . "** orang\n";
    $out .= "\n**Detail per role:**\n";
    foreach ($perRole as $r) {
        $ic  = isset($roleIcon[$r['role']]) ? $roleIcon[$r['role']] : '👤';
        $cnt = (int)$r['c'];
        $out .= "\n{$ic} **" . strtoupper($r['role']) . "** ({$cnt} orang)\n";
        $members = rows($c, "SELECT nama, nip FROM users WHERE role=? ORDER BY nama ASC" . ($cnt > 15 ? ' LIMIT 5' : ''), 's', [$r['role']]);
        foreach ($members as $i => $m) $out .= "   " . ($i + 1) . ". " . sf($m['nama']) . (!empty($m['nip']) ? " — {$m['nip']}" : '') . "\n";
        if ($cnt > 15) $out .= "   _...dan " . ($cnt - 5) . " lainnya. Ketik \"daftar {$r['role']}\" untuk lengkap._\n";
    }
    simpanKonteks('pengguna');
    return $out . "\n_Data per " . nl2() . "_";
}

/* ══════════════════════════════════════════
   RINGKASAN
══════════════════════════════════════════ */
function ansRingkasan(mysqli $c): string
{
    $t  = td();
    $ag = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?", 'ss', [$t, $t]);

    $ct = cekinTbl($c);
    $ip = ['n' => 0, 'inap' => 0, 'belum' => 0];
    if ($ct) {
        $tmp = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN status_inap='Check-in' THEN 1 ELSE 0 END) AS inap, SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum FROM {$ct} pp JOIN agenda_kegiatan ak ON ak.id=pp.agenda_id WHERE ak.start_date<=? AND ak.end_date>=?", 'ss', [$t, $t]);
        if (!empty($tmp)) $ip = array_merge($ip, $tmp);
    }

    $rt = rusakTbl($c);
    $rs = ['n' => 0, 'pend' => 0, 'darurat' => 0, 'tinggi' => 0];
    if ($rt) {
        $tmp = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN status NOT IN ('selesai','ditolak') THEN 1 ELSE 0 END) AS pend, SUM(CASE WHEN status NOT IN ('selesai','ditolak') AND prioritas='darurat' THEN 1 ELSE 0 END) AS darurat, SUM(CASE WHEN status NOT IN ('selesai','ditolak') AND prioritas='tinggi' THEN 1 ELSE 0 END) AS tinggi FROM {$rt}");
        if (!empty($tmp)) $rs = array_merge($rs, $tmp);
    }

    $tm = ['n' => 0];
    if (tbl($c, 'buku_tamu')) {
        $tmp = one($c, "SELECT COUNT(*) AS n FROM buku_tamu WHERE DATE(created_at)=?", 's', [$t]);
        if (!empty($tmp)) $tm = array_merge($tm, $tmp);
    }

    $sr = one($c, "SELECT SUM(CASE WHEN jenis='masuk' THEN 1 ELSE 0 END) AS m, SUM(CASE WHEN jenis='keluar' THEN 1 ELSE 0 END) AS k FROM arsip_surat WHERE tanggal_surat BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01') AND ?", 's', [$t]);
    $ck = one($c, "SELECT COUNT(*) AS c,COUNT(DISTINCT nip_user) AS pt FROM checklist_forms WHERE tanggal=?", 's', [$t]);

    $kn = ['n' => 0, 'keluar' => 0];
    if (tbl($c, 'kendaraan_operasional_log')) {
        $tmp = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN status='keluar' THEN 1 ELSE 0 END) AS keluar FROM kendaraan_operasional_log WHERE DATE(waktu_keluar)=?", 's', [$t]);
        if (!empty($tmp)) $kn = array_merge($kn, $tmp);
    }

    $judul = [
        '📊 **Ringkasan Operasional Hari Ini**',
        '📊 **Status Operasional ' . date('d M Y') . '**',
        '📊 **Update Harian — ' . date('d M Y') . '**',
        '📊 **Pantauan Operasional Hari Ini**'
    ];
    $out  = $judul[array_rand($judul)] . "\n" . str_repeat('─', 38) . "\n\n";
    $out .= "📅 Agenda aktif        : **" . num(isset($ag['c']) ? $ag['c'] : 0) . "** kegiatan (" . num(isset($ag['p']) ? $ag['p'] : 0) . " peserta)\n";
    $out .= "🏠 Penginapan          : **" . num($ip['n']) . "** total | 🏃" . num($ip['inap']) . " inap | ⏳" . num($ip['belum']) . " belum\n";
    $out .= "📋 Checklist hari ini  : **" . num(isset($ck['c']) ? $ck['c'] : 0) . "** form | " . num(isset($ck['pt']) ? $ck['pt'] : 0) . " petugas\n";
    $out .= "🔧 Kerusakan pending   : **" . num($rs['pend']) . "** dari " . num($rs['n']) . " total";
    $alerts = [];
    if ((int)(isset($rs['darurat']) ? $rs['darurat'] : 0) > 0) $alerts[] = "🚨 " . num($rs['darurat']) . " DARURAT";
    if ((int)(isset($rs['tinggi'])  ? $rs['tinggi']  : 0) > 0) $alerts[] = "🔴 " . num($rs['tinggi'])  . " tinggi";
    if (!empty($alerts)) $out .= " (" . implode(', ', $alerts) . ")";
    $out .= "\n";
    $out .= "👥 Tamu hari ini       : **" . num($tm['n']) . "** orang\n";
    $out .= "📨 Surat bulan ini     : **" . num(isset($sr['m']) ? $sr['m'] : 0) . "** masuk | **" . num(isset($sr['k']) ? $sr['k'] : 0) . "** keluar\n";
    if ((int)(isset($kn['n']) ? $kn['n'] : 0) > 0)
        $out .= "🚐 Kendaraan dinas     : **" . num($kn['keluar']) . "** belum kembali dari " . num($kn['n']) . " perjalanan\n";
    simpanKonteks('ringkasan');
    return $out . "\n_" . nl2() . "_";
}

/* ══════════════════════════════════════════
   MAIN ROUTER
══════════════════════════════════════════ */
$Q   = nq($question);
$I   = intent($Q);
$R   = rng($Q);
$ctx = ambilKonteks();

// Resolusi follow-up
if ($I === 'fallback' && isFollowUp($Q) && !empty($ctx)) {
    $prevIntent   = isset($ctx['intent']) ? $ctx['intent'] : '';
    $followUpAble = [
        'cekin_rekap',
        'cekin_belum',
        'cekin_aktif',
        'cekin_selesai',
        'cekin_instansi',
        'cekin_gender',
        'cekin_kamar',
        'kerusakan',
        'rusak_pending',
        'rusak_teknisi',
        'checklist',
        'checklist_top',
        'checklist_area',
        'agenda',
        'agenda_mendatang',
        'gudang',
        'tamu',
        'surat',
        'kendaraan',
        'kendaraan_ops',
        'pengguna',
        'user_role',
        'ringkasan',
    ];
    if (in_array($prevIntent, $followUpAble, true)) {
        $I = $prevIntent;
    }
}

switch ($I) {
    case 'help':
        rj(true, ansHelp());
        break;
    case 'agenda':
        rj(true, ansAgenda($conn, $R));
        break;
    case 'agenda_mendatang':
        rj(true, ansAgendaMendatang($conn));
        break;
    case 'agenda_kategori':
        rj(true, ansAgendaKategori($conn, $Q, $R));
        break;
    case 'agenda_terbesar':
        rj(true, ansAgendaTerbesar($conn));
        break;
    case 'agenda_count':
        rj(true, ansAgendaCount($conn, $R));
        break;
    case 'cekin_rekap':
        rj(true, ansCekinRekap($conn, $Q));
        break;
    case 'cekin_belum':
        rj(true, ansCekinBelum($conn, $Q));
        break;
    case 'cekin_aktif':
        rj(true, ansCekinAktif($conn, $Q));
        break;
    case 'cekin_selesai':
        rj(true, ansCekinSelesai($conn, $Q));
        break;
    case 'cekin_cari':
        rj(true, ansCekinCari($conn, $Q));
        break;
    case 'cekin_instansi':
        rj(true, ansCekinInstansi($conn, $Q));
        break;
    case 'cekin_gender':
        rj(true, ansCekinGender($conn, $Q));
        break;
    case 'cekin_kamar':
        rj(true, ansCekinKamar($conn, $Q));
        break;
    case 'kerusakan':
        rj(true, ansKerusakan($conn, $R));
        break;
    case 'rusak_pending':
        rj(true, ansRusakPending($conn));
        break;
    case 'rusak_teknisi':
        rj(true, ansRusakTeknisi($conn, $R));
        break;
    case 'rusak_jenis':
        rj(true, ansRusakJenis($conn, $R));
        break;
    case 'rusak_per_kategori':
        rj(true, ansRusakPerKategori($conn, $R));
        break;
    case 'rusak_per_lokasi':
        rj(true, ansRusakPerLokasi($conn, $R));
        break;
    case 'checklist':
        rj(true, ansChecklist($conn, $R));
        break;
    case 'checklist_area':
        rj(true, ansChecklistArea($conn, $R));
        break;
    case 'checklist_regu':
        rj(true, ansChecklistRegu($conn, $R));
        break;
    case 'checklist_top':
        rj(true, ansChecklistTop($conn, $R));
        break;
    case 'checklist_user':
        rj(true, ansChecklistUser($conn, $R));
        break;
    case 'checklist_catatan':
        rj(true, ansChecklistCatatan($conn, $R));
        break;
    case 'surat':
        rj(true, ansSurat($conn, $R));
        break;
    case 'surat_masuk':
        rj(true, ansSurat($conn, $R, 'masuk'));
        break;
    case 'surat_keluar':
        rj(true, ansSurat($conn, $R, 'keluar'));
        break;
    case 'surat_pengirim':
        rj(true, ansSuratPengirim($conn, $R));
        break;
    case 'gudang':
    case 'gdg_masuk':
    case 'gdg_keluar':
        rj(true, ansGudang($conn, $R));
        break;
    case 'gdg_top':
        rj(true, ansGudangTop($conn, $R));
        break;
    case 'gudang_stok':
        rj(true, ansGudangStok($conn));
        break;
    case 'tamu':
        rj(true, ansTamu($conn, $R));
        break;
    case 'tamu_instansi':
        rj(true, ansTamuInstansi($conn, $R));
        break;
    case 'kendaraan':
        rj(true, ansKendaraan($conn));
        break;
    case 'kendaraan_ops':
        rj(true, ansKendaraanOperasional($conn));
        break;
    case 'user_role':
        rj(true, ansUserRole($conn, $Q));
        break;
    case 'pengguna':
        rj(true, ansPengguna($conn));
        break;
    case 'ringkasan':
        rj(true, ansRingkasan($conn));
        break;
    default:
        rj(true, ansFallback($Q), ['intent' => 'fallback']);
        break;
}
