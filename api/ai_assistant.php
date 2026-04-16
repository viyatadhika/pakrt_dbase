<?php

/**
 * ╔══════════════════════════════════════════════════════════╗
 *  AI ASSISTANT v5 — Pure Database, Akurasi Tinggi
 *  Database : warga_rt_bsdk
 *
 *  PERUBAHAN v5 (tidak menghapus fitur v4):
 *  [FIX]  num(float $v) → num($v) null-safe cast — INI ROOT CAUSE
 *         utama "kendaraan/ringkasan tidak muncul jawaban":
 *         Saat one() gagal query (tabel/kolom tidak ada), ia return [].
 *         Akses key [] → null. num(null) dengan strict float → TypeError
 *         → PHP Fatal Error → output blank → frontend "maaf AI error"
 *  [FIX]  pct(float $n, float $d) → null-safe
 *  [FIX]  ansRingkasan — pakai array union (+) agar $ip/$rs/$tm/$kn
 *         tidak tertimpa array kosong saat one() gagal query
 *  [FIX]  ansKendaraan — cek keberadaan kolom dicatat_oleh sebelum
 *         SELECT, agar tidak error jika kolom belum ada di DB
 *  [FIX]  intent() — 'ringkasan' naik ke posisi 2 (setelah 'help'),
 *         SEBELUM tamu/checklist/surat/gudang yang bisa menelannya.
 *         Sebelumnya ringkasan di bagian paling bawah!
 *  [FIX]  nq() — normalize 'siapa saja yang belum' → 'siapa yang belum'
 *         agar trigger cekin_belum selalu cocok
 *  [NEW]  Global error handler — semua PHP error menghasilkan JSON
 *  [KEEP] Semua fitur v4 tetap ada: master tabel JOIN, multi-agenda,
 *         kendaraan operasional, gudang stok, checklist catatan, dll
 * ╚══════════════════════════════════════════════════════════╝
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

// [FIX v5] Global error handler — error PHP return JSON bukan blank response
// Mencegah "maaf AI tidak bisa dihubungi" saat ada query error
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
   HELPER DASAR
══════════════════════════════════════════ */
function rj(bool $ok, string $ans, array $m = [], int $c = 200): void
{
    http_response_code($c);
    echo json_encode(['status' => $ok, 'answer' => $ans, 'meta' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function nl(): string
{
    return date('d-m-Y H:i');
}
function td(): string
{
    return date('Y-m-d');
}

// [FIX v5] num() tidak lagi strict float — mencegah TypeError saat $v=null
function num($v): string
{
    return number_format((float)($v ?? 0), 0, ',', '.');
}

// [FIX v5] pct() tidak lagi strict float — null-safe
function pct($n, $d): string
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
function sf($v, string $fb = '-'): string
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
            // [FIX v5] Normalize semua variant "siapa belum" agar trigger cekin_belum
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
            // Semua jadi "siapa yang belum" agar konsisten
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
    foreach ($kw as $k) if ($k !== '' && mb_strpos($t, $k) !== false) return true;
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
    $r = $s->get_result();
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
    $r = $s->get_result();
    $out = [];
    if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
    $s->close();
    return $out;
}
function tbl(mysqli $c, string $t): bool
{
    $safe = $c->real_escape_string($t);
    $r = $c->query("SHOW TABLES LIKE '{$safe}'");
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
   DATE RANGE
══════════════════════════════════════════ */
function rng(string $q): array
{
    $t = td();
    if (has($q, ['hari ini', 'sekarang', 'today']))     return ['l' => 'hari ini', 's' => $t, 'e' => $t];
    if (has($q, ['kemarin', 'yesterday'])) {
        $d = date('Y-m-d', strtotime('-1 day'));
        return ['l' => 'kemarin', 's' => $d, 'e' => $d];
    }
    if (has($q, ['minggu ini', 'pekan ini']))   return ['l' => 'minggu ini', 's' => date('Y-m-d', strtotime('monday this week')), 'e' => date('Y-m-d', strtotime('sunday this week'))];
    if (has($q, ['minggu lalu', 'pekan lalu'])) return ['l' => 'minggu lalu', 's' => date('Y-m-d', strtotime('monday last week')), 'e' => date('Y-m-d', strtotime('sunday last week'))];
    if (has($q, ['bulan ini']))    return ['l' => 'bulan ini', 's' => date('Y-m-01'), 'e' => date('Y-m-t')];
    if (has($q, ['bulan lalu']))   return ['l' => 'bulan lalu', 's' => date('Y-m-01', strtotime('first day of last month')), 'e' => date('Y-m-t', strtotime('last day of last month'))];
    if (has($q, ['tahun ini']))    return ['l' => 'tahun ini', 's' => date('Y-01-01'), 'e' => date('Y-12-31')];
    if (has($q, ['7 hari', 'seminggu terakhir']))  return ['l' => '7 hari terakhir', 's' => date('Y-m-d', strtotime('-6 days')), 'e' => $t];
    if (has($q, ['30 hari', 'sebulan terakhir'])) return ['l' => '30 hari terakhir', 's' => date('Y-m-d', strtotime('-29 days')), 'e' => $t];
    if (has($q, ['3 bulan', 'triwulan']))          return ['l' => '3 bulan terakhir', 's' => date('Y-m-d', strtotime('-3 months')), 'e' => $t];
    return ['l' => 'hari ini', 's' => $t, 'e' => $t];
}

/* ══════════════════════════════════════════
   INTENT DETECTION
   [FIX v5] Urutan dibenahi:
   1. help/sapaan → paling atas
   2. RINGKASAN → naik ke posisi 2, sebelum tamu/checklist/surat/gudang
   3. KENDARAAN → sebelum checklist (mencegah 'penugasan' menelan)
   4. cekin_belum → trigger lebih lengkap
   5. ringkasan dihapus dari bawah
══════════════════════════════════════════ */
function intent(string $q): string
{
    // ── 1. SAPAAN & BANTUAN ───────────────────────────────────
    $qTrim = trim($q);
    if (in_array($qTrim, [
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
    ], true))
        return 'help';
    if (has($q, [
        'halo ',
        'hai ',
        'assalamu',
        'selamat pagi',
        'selamat siang',
        'selamat sore',
        'selamat malam',
        'bisa apa',
        'apa yang bisa',
        'fitur apa',
        'ada fitur',
        'bantuan',
        'cara pakai',
        'cara penggunaan',
        'panduan',
        'petunjuk',
        'tampilkan menu',
        'lihat menu',
        'tampilkan fitur',
        'apa saja fitur',
        'apa saja yang bisa'
    ]))
        return 'help';

    // ── 2. RINGKASAN — naik ke posisi 2 ─────────────────────
    // [FIX v5] Sebelumnya di bagian bawah setelah tamu/checklist/dll
    // yang menyebabkan 'operasional' kadang tertelan intent lain
    if (has($q, [
        'ringkasan',
        'rekap harian',
        'rekap operasional',
        'laporan harian',
        'dashboard',
        'summary',
        'rekapan',
        'ikhtisar',
        'operasional hari ini',
        'operasional harian'
    ]))
        return 'ringkasan';

    // ── 3. KENDARAAN — sebelum checklist ─────────────────────
    if (has($q, ['kendaraan operasional', 'kendaraan dinas', 'driver keluar', 'driver kembali']))
        return 'kendaraan_ops';
    if (has($q, ['kendaraan', 'plat nomor', 'log kendaraan', 'parkir kendaraan']))
        return 'kendaraan';

    // ── 4. CEKIN ─────────────────────────────────────────────
    if (has($q, [
        'cari peserta',
        'cari nama',
        'siapa peserta',
        'dimana peserta',
        'bernama',
        'cari orang',
        'cari siapa'
    ]))
        return 'cekin_cari';
    if (has($q, ['instansi terbanyak', 'instansi paling banyak', 'dari instansi mana']))
        return 'cekin_instansi';
    if (has($q, ['gender', 'jenis kelamin', 'laki-laki', 'perempuan', 'peserta laki', 'peserta perempuan']))
        return 'cekin_gender';
    if (has($q, ['per kamar', 'detail kamar', 'kamar kosong']))
        return 'cekin_kamar';
    // [FIX v5] Trigger belum check-in lebih lengkap
    // nq() sudah normalize semua "siapa saja belum" → "siapa yang belum"
    if (has($q, [
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
        'siapa2 belum',
        'belum cekout'
    ]))
        return 'cekin_belum';
    if (has($q, ['sedang menginap', 'sedang inap', 'masih inap', 'sudah cekin']))
        return 'cekin_aktif';
    if (has($q, ['sudah cekout', 'sudah keluar', 'sudah pulang', 'sudah checkout']))
        return 'cekin_selesai';
    if (has($q, ['cekin', 'cekout', 'penginapan', 'menginap', 'kamar', 'gedung', 'inap', 'check-in peserta']))
        return 'cekin_rekap';

    // ── 5. AGENDA ────────────────────────────────────────────
    if (has($q, ['agenda mendatang', 'jadwal mendatang', 'akan datang', 'minggu depan', 'bulan depan', 'berikutnya', 'selanjutnya']))
        return 'agenda_mendatang';
    if (has($q, ['kategori', 'menpim', 'teknis', 'kerjasama', 'pustrajak']))
        return 'agenda_kategori';
    if (has($q, ['peserta terbanyak', 'agenda terbesar', 'paling banyak peserta']))
        return 'agenda_terbesar';
    if (has($q, ['berapa agenda', 'berapa kegiatan']))
        return 'agenda_count';
    if (has($q, ['agenda', 'kegiatan', 'jadwal', 'pelatihan', 'diklat', 'sertifikasi', 'bimtek', 'konsinyering', 'sosialisasi']))
        return 'agenda';

    // ── 6. KERUSAKAN ─────────────────────────────────────────
    if (has($q, ['ranking teknisi', 'teknisi terbaik', 'teknisi paling aktif', 'siapa teknisi', 'top teknisi']))
        return 'rusak_teknisi';
    if (has($q, ['per kategori kerusakan', 'kategori rusak', 'jenis kategori']))
        return 'rusak_per_kategori';
    if (has($q, ['ruangan sering', 'lokasi sering', 'area rusak', 'paling sering rusak', 'per lokasi', 'lokasi kerusakan']))
        return 'rusak_per_lokasi';
    if (has($q, ['jenis kerusakan', 'tipe kerusakan', 'macam kerusakan']))
        return 'rusak_jenis';
    if (has($q, ['belum selesai', 'masih pending', 'kerusakan pending', 'belum ditangani', 'menunggu']))
        return 'rusak_pending';
    if (has($q, ['kerusakan', 'rusak', 'masalah', 'laporan kerusakan', 'teknisi', 'prioritas']))
        return 'kerusakan';

    // ── 7. CHECKLIST ─────────────────────────────────────────
    if (has($q, ['user checklist', 'pengguna checklist', 'siapa yang isi', 'siapa yang mengisi', 'akun checklist', 'login checklist']))
        return 'checklist_user';
    if (has($q, ['catatan kerusakan', 'catatan checklist', 'laporan checklist']))
        return 'checklist_catatan';
    if (has($q, ['per area', 'area kerja', 'checklist area']))
        return 'checklist_area';
    if (has($q, ['per regu', 'regu a', 'regu b', 'regu c']))
        return 'checklist_regu';
    if (has($q, ['ranking petugas', 'petugas rajin', 'petugas aktif', 'top petugas']))
        return 'checklist_top';
    if (has($q, ['checklist', 'form checklist', 'petugas', 'ob', 'security', 'regu', 'plotingjaga', 'penugasan']))
        return 'checklist';

    // ── 8. SURAT ─────────────────────────────────────────────
    if (has($q, ['surat masuk']))  return 'surat_masuk';
    if (has($q, ['surat keluar'])) return 'surat_keluar';
    if (has($q, ['pengirim terbanyak', 'surat dari', 'siapa pengirim'])) return 'surat_pengirim';
    if (has($q, ['surat', 'arsip', 'persuratan', 'nomor surat'])) return 'surat';

    // ── 9. GUDANG ────────────────────────────────────────────
    if (has($q, ['stok barang', 'daftar stok', 'stok saat ini', 'master barang'])) return 'gudang_stok';
    if (has($q, ['barang masuk']))  return 'gdg_masuk';
    if (has($q, ['barang keluar'])) return 'gdg_keluar';
    if (has($q, ['barang terbanyak', 'item terbanyak'])) return 'gdg_top';
    if (has($q, ['gudang', 'stok', 'inventaris', 'barang', 'persediaan'])) return 'gudang';

    // ── 10. TAMU ─────────────────────────────────────────────
    if (has($q, ['instansi tamu', 'asal tamu', 'tamu dari mana'])) return 'tamu_instansi';
    if (has($q, ['tamu', 'buku tamu', 'pengunjung', 'pelayanan']))   return 'tamu';

    // ── 11. PENGGUNA ─────────────────────────────────────────
    $roleKeywords = [
        'teknisi',
        'driver',
        'ob',
        'security',
        'pimpinan',
        'petugas',
        'koordinator',
        'poliklinik',
        'gudang',
        'perpustakaan',
        'sekretariat',
        'supervisor',
        'admin'
    ];
    foreach ($roleKeywords as $_rk) {
        if (has($q, [
            "daftar {$_rk}",
            "list {$_rk}",
            "siapa {$_rk}",
            "{$_rk} siapa",
            "{$_rk} ada berapa",
            "berapa {$_rk}",
            "nama {$_rk}"
        ]))
            return 'user_role';
    }
    if (has($q, ['pengguna', 'user sistem', 'akun sistem', 'daftar staf', 'daftar pegawai', 'daftar karyawan', 'semua role']))
        return 'pengguna';

    // ── FALLBACK ─────────────────────────────────────────────
    return 'fallback';
}

/* ══════════════════════════════════════════
   RESOLVE AGENDA (multi-agenda aware)
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
    $semua = resolveAgSemua($c);
    $first = $semua[0] ?? [];
    return ['id' => !empty($first) ? (int)$first['id'] : null, 'ag' => $first, 'semua' => $semua];
}

/* ══════════════════════════════════════════
   HELP
══════════════════════════════════════════ */
function ansHelp(): string
{
    return "**Hai! Langsung tanya tanpa perlu ketik \"halo\" dulu!** 🤖\n\n"
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
        . "   → \"daftar perpustakaan\", \"daftar sekretariat\", \"daftar pimpinan\"\n"
        . "   → \"pengguna\" untuk semua role sekaligus\n"
        . "📊 **Ringkasan** → \"ringkasan operasional hari ini\"\n"
        . "\n_Tambahkan periode: \"bulan ini\", \"minggu ini\", \"7 hari terakhir\", dll._";
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
    if (empty($list)) return "Tidak ada agenda aktif untuk periode **{$r['l']}**.";
    $tot = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?", 'ss', [$r['e'], $r['s']]);
    $out = "**Agenda kegiatan {$r['l']}:**\n\n📋 **" . num($tot['c']) . "** kegiatan | 👥 **" . num($tot['p']) . "** peserta total\n\n";
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
    return $out . "_Data per " . nl() . "_";
}

function ansAgendaMendatang(mysqli $c): string
{
    $list = rows($c, "SELECT id,judul,start_date,end_date,kategori,peserta,asrama FROM agenda_kegiatan WHERE start_date>? ORDER BY start_date ASC LIMIT 15", 's', [td()]);
    if (empty($list)) return "Tidak ada agenda mendatang yang terjadwal.";
    $out = "**Agenda mendatang:**\n\n";
    foreach ($list as $i => $row) {
        $hari  = (int)((strtotime($row['start_date']) - time()) / 86400);
        $label = $hari <= 0 ? '✅ mulai hari ini' : ($hari === 1 ? '⏰ besok' : "📆 {$hari} hari lagi");
        $out .= ($i + 1) . ". **{$row['judul']}** [{$row['kategori']}]\n";
        $out .= "   📅 " . tgl($row['start_date']) . " — " . tgl($row['end_date']) . " • {$label}\n";
        if ($row['peserta']) $out .= "   👥 " . num($row['peserta']) . " peserta\n";
        if ($row['asrama'])  $out .= "   🏨 {$row['asrama']}\n\n";
        else $out .= "\n";
    }
    return $out . "_Data per " . nl() . "_";
}

function ansAgendaKategori(mysqli $c, string $q, array $r): string
{
    $map = ['menpim' => 'Menpim', 'teknis' => 'Teknis', 'kerjasama' => 'Kerjasama', 'pustrajak' => 'Pustrajak'];
    $kat = null;
    foreach ($map as $kw => $v) if (mb_strpos($q, $kw) !== false) {
        $kat = $v;
        break;
    }
    if ($kat) {
        $list = rows($c, "SELECT judul,start_date,end_date,peserta FROM agenda_kegiatan WHERE kategori=? AND start_date<=? AND end_date>=? ORDER BY start_date ASC", 'sss', [$kat, $r['e'], $r['s']]);
        $tot  = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE kategori=?", 's', [$kat]);
        $out  = "**Agenda {$kat} {$r['l']}:**\n\n📊 Total: " . num($tot['c']) . " kegiatan | " . num($tot['p']) . " peserta\n\n";
        if (empty($list)) return $out . "Tidak ada agenda {$kat} aktif periode ini.";
        foreach ($list as $i => $row) {
            $out .= ($i + 1) . ". **{$row['judul']}**\n   " . tgl($row['start_date']) . " s/d " . tgl($row['end_date']);
            if ($row['peserta']) $out .= " | " . num($row['peserta']) . " peserta";
            $out .= "\n\n";
        }
        return $out . "_Data per " . nl() . "_";
    }
    $kats = rows($c, "SELECT kategori,COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE start_date<=? AND end_date>=? GROUP BY kategori ORDER BY c DESC", 'ss', [$r['e'], $r['s']]);
    $out = "**Rekap per kategori {$r['l']}:**\n\n";
    foreach ($kats as $row) $out .= "• **{$row['kategori']}**: " . num($row['c']) . " kegiatan | " . num($row['p']) . " peserta\n";
    return $out . "\n_Data per " . nl() . "_";
}

function ansAgendaTerbesar(mysqli $c): string
{
    $list = rows($c, "SELECT judul,start_date,end_date,kategori,peserta,asrama FROM agenda_kegiatan WHERE peserta>0 ORDER BY peserta DESC LIMIT 10");
    $out  = "**Agenda peserta terbanyak (semua waktu):**\n\n";
    foreach ($list as $i => $row) {
        $out .= ($i + 1) . ". **{$row['judul']}** — **" . num($row['peserta']) . " peserta**\n";
        $out .= "   " . tgl($row['start_date']) . " s/d " . tgl($row['end_date']) . " | {$row['kategori']}\n\n";
    }
    return $out . "_Data per " . nl() . "_";
}

function ansAgendaCount(mysqli $c, array $r): string
{
    $aktif = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?", 'ss', [$r['e'], $r['s']]);
    $akan  = one($c, "SELECT COUNT(*) AS c FROM agenda_kegiatan WHERE start_date>?", 's', [td()]);
    $all   = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan");
    return "**Statistik agenda:**\n\n"
        . "📅 Aktif {$r['l']} : **" . num($aktif['c'] ?? 0) . "** kegiatan | " . num($aktif['p'] ?? 0) . " peserta\n"
        . "📆 Akan datang  : **" . num($akan['c'] ?? 0) . "** kegiatan\n"
        . "📊 Total semua  : **" . num($all['c'] ?? 0) . "** kegiatan | " . num($all['p'] ?? 0) . " peserta\n\n"
        . "_Data per " . nl() . "_";
}

/* ══════════════════════════════════════════
   CEKIN — multi-agenda aware
══════════════════════════════════════════ */
function ansCekinRekap(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel penginapan tidak ditemukan.";
    $ai = resolveAg($c, $q);
    $agendas = $ai['semua'] ?? [];
    if (empty($agendas)) return "Tidak ada agenda aktif yang ditemukan.";

    if (count($agendas) === 1) {
        $ag   = $agendas[0];
        $aid  = (int)$ag['id'];
        $judul = $ag['judul'] ?? ('Agenda #' . $aid);
        $s = one($c, "SELECT COUNT(*) AS n,
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
        $tot = (int)($s['n'] ?? 0);
        $out = "**Rekap penginapan**\n📌 {$judul}\n\n";
        $out .= "👥 Terdaftar   : **" . num($tot) . "** orang\n";
        $out .= "⏳ Belum hadir : **" . num($s['belum']) . "** (" . pct($s['belum'], $tot) . ")\n";
        $out .= "🏠 Menginap    : **" . num($s['inap']) . "** (" . pct($s['inap'], $tot) . ")\n";
        $out .= "✅ Sudah keluar: **" . num($s['out_']) . "** (" . pct($s['out_'], $tot) . ")\n";
        $out .= "─────────────────────\n";
        $out .= "🎓 Peserta: **" . num($s['psr']) . "** | Panitia: **" . num($s['pan']) . "** | Pengajar: **" . num($s['pngj']) . "**\n";
        if ((int)($s['lk'] ?? 0) + (int)($s['pr'] ?? 0) > 0)
            $out .= "♂ Laki: **" . num($s['lk']) . "** | ♀ Perempuan: **" . num($s['pr']) . "**\n";
        if (!empty($gdg)) {
            $out .= "\n**Per gedung:**\n";
            foreach ($gdg as $g) {
                $nm = sf($g['gedung'], 'Tanpa Gedung');
                $out .= "🏨 **{$nm}** — " . num($g['n']) . " org | ✅" . num($g['inap']) . " inap (" . pct($g['inap'], $g['n']) . ") | ⏳" . num($g['belum']) . " | 🚪" . num($g['out_']) . "\n";
            }
        }
        return $out . "\n_Data per " . nl() . "_";
    }

    $out = "**Rekap penginapan semua kegiatan aktif hari ini:**\n(" . count($agendas) . " kegiatan)\n\n";
    $gT = 0;
    $gB = 0;
    $gI = 0;
    $gO = 0;
    foreach ($agendas as $ag) {
        $aid  = (int)$ag['id'];
        $judul = $ag['judul'] ?? ('Agenda #' . $aid);
        $s = one($c, "SELECT COUNT(*) AS n,
            SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
            SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
            SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS out_
            FROM {$t} WHERE agenda_id=?", 'i', [$aid]);
        $tot = (int)($s['n'] ?? 0);
        if ($tot === 0) continue;
        $gT += $tot;
        $gB += (int)($s['belum'] ?? 0);
        $gI += (int)($s['inap'] ?? 0);
        $gO += (int)($s['out_'] ?? 0);
        $out .= "📌 **{$judul}**\n";
        $out .= "   👥 " . num($tot) . " org | ⏳belum " . num($s['belum']) . " (" . pct($s['belum'], $tot) . ") | 🏠inap " . num($s['inap']) . " | ✅keluar " . num($s['out_']) . "\n\n";
    }
    if ($gT > 0) {
        $out .= "─────────────────────\n";
        $out .= "**Total gabungan:** " . num($gT) . " org | ⏳belum " . num($gB) . " (" . pct($gB, $gT) . ") | 🏠inap " . num($gI) . " | ✅keluar " . num($gO) . "\n";
        $out .= "\n_Sebutkan nama kegiatan untuk detail spesifik._\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

function ansCekinBelum(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai = resolveAg($c, $q);
    $agendas = $ai['semua'] ?? [];
    if (empty($agendas)) return "Tidak ada agenda aktif.";

    if (count($agendas) === 1) {
        $ag    = $agendas[0];
        $aid   = (int)$ag['id'];
        $judul = $ag['judul'] ?? ('Agenda #' . $aid);
        $tot   = one($c, "SELECT COUNT(*) AS n FROM {$t} WHERE agenda_id=? AND status_inap='Belum Check-in'", 'i', [$aid]);
        $list  = rows($c, "SELECT nama,instansi,peran,jenis_kelamin,gedung,lantai,kamar
            FROM {$t} WHERE agenda_id=? AND status_inap='Belum Check-in'
            ORDER BY gedung,lantai,kamar,nama LIMIT 100", 'i', [$aid]);
        if (empty($list)) return "✅ **Semua peserta sudah check-in!**\nKegiatan: **{$judul}**";
        $out = "**Belum check-in** — {$judul}\nTotal: **" . num($tot['n'] ?? 0) . "** orang\n\n";
        foreach ($list as $i => $r) {
            $lok = trim(sf($r['gedung'], '') . ' Lt.' . sf($r['lantai'], '-') . ' Kamar ' . sf($r['kamar'], '-'));
            $gen = $r['jenis_kelamin'] === 'L' ? '♂' : ($r['jenis_kelamin'] === 'P' ? '♀' : '');
            $ins = $r['instansi'] ? " | {$r['instansi']}" : '';
            $out .= ($i + 1) . ". **{$r['nama']}** {$gen} (" . sf($r['peran']) . ")" . $ins . "\n   📍 {$lok}\n";
        }
        if ((int)($tot['n'] ?? 0) > 100) $out .= "\n_...dan " . ((int)$tot['n'] - 100) . " orang lainnya._\n";
        return $out . "\n_Data per " . nl() . "_";
    }

    $out = "**Peserta belum check-in — semua kegiatan aktif:**\n\n";
    $grandTot = 0;
    foreach ($agendas as $ag) {
        $aid   = (int)$ag['id'];
        $judul = $ag['judul'] ?? ('Agenda #' . $aid);
        $tot   = one($c, "SELECT COUNT(*) AS n FROM {$t} WHERE agenda_id=? AND status_inap='Belum Check-in'", 'i', [$aid]);
        $n     = (int)($tot['n'] ?? 0);
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
    return $out . "\n_Data per " . nl() . "_";
}

function ansCekinAktif(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai = resolveAg($c, $q);
    $aid = (int)($ai['id'] ?? 0);
    if (!$aid) return "Tidak ada agenda aktif.";
    $judul = $ai['ag']['judul'] ?? ('Agenda #' . $aid);
    $tot   = one($c, "SELECT COUNT(*) AS n FROM {$t} WHERE agenda_id=? AND status_inap='Check-in'", 'i', [$aid]);
    $list  = rows($c, "SELECT nama,instansi,peran,jenis_kelamin,gedung,lantai,kamar,checkin_date,checkin_time
        FROM {$t} WHERE agenda_id=? AND status_inap='Check-in'
        ORDER BY gedung,lantai,kamar,nama LIMIT 60", 'i', [$aid]);
    if (empty($list)) return "Belum ada yang sedang menginap untuk **{$judul}**.";
    $out = "**Sedang menginap** — {$judul}\nTotal: **" . num($tot['n'] ?? 0) . "** orang\n\n";
    foreach ($list as $i => $r) {
        $lok = trim(sf($r['gedung'], '') . ' Lt.' . sf($r['lantai'], '-') . ' Kamar ' . sf($r['kamar'], '-'));
        $ci  = ($r['checkin_date'] ? tgl($r['checkin_date']) : '') . ($r['checkin_time'] ? ' ' . substr($r['checkin_time'], 0, 5) : '');
        $gen = $r['jenis_kelamin'] === 'L' ? '♂' : ($r['jenis_kelamin'] === 'P' ? '♀' : '');
        $out .= ($i + 1) . ". **{$r['nama']}** {$gen} (" . sf($r['peran']) . ")\n   📍 {$lok} | Check-in: " . trim($ci) . "\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

function ansCekinSelesai(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai    = resolveAg($c, $q);
    $ag    = !empty($ai['semua']) ? $ai['semua'][0] : [];
    $aid   = (int)($ag['id'] ?? 0);
    $judul = $ag['judul'] ?? ('Agenda #' . $aid);
    $tot   = one($c, "SELECT COUNT(*) AS n FROM {$t} WHERE agenda_id=? AND status_inap='Check-out'", 'i', [$aid]);
    $list  = rows($c, "SELECT nama,instansi,peran,gedung,lantai,kamar,checkin_date,checkin_time,checkout_date,checkout_time
        FROM {$t} WHERE agenda_id=? AND status_inap='Check-out'
        ORDER BY checkout_date DESC,checkout_time DESC LIMIT 50", 'i', [$aid]);
    if (empty($list)) return "Belum ada yang check-out dari **{$judul}**.";
    $out = "**Sudah check-out** — {$judul}\nTotal: **" . num($tot['n'] ?? 0) . "** orang\n\n";
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
    if ((int)($tot['n'] ?? 0) > 50) $out .= "\n_...dan " . ((int)$tot['n'] - 50) . " orang lainnya._\n";
    return $out . "\n_Data per " . nl() . "_";
}

function ansCekinCari(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $kw = $q;
    if (preg_match('/(?:cari\s+(?:peserta|nama|orang)?\s*(?:yang\s+)?|siapa\s+(?:peserta\s+)?|dimana\s+(?:peserta\s+)?)(.+)/iu', $kw, $m))
        $kw = trim($m[1]);
    $sw = ['yang', 'peserta', 'nama', 'instansi', 'dari', 'bernama', 'keluar', 'masuk', 'menginap', 'inap', 'sudah', 'belum', 'sedang', 'dengan', 'untuk'];
    foreach ($sw as $s2) $kw = preg_replace('/^' . $s2 . '\s+/iu', '', trim($kw));
    $kw = trim($kw);
    if (mb_strlen($kw) < 2) return "Sebutkan nama atau instansi (minimal 2 karakter).";
    $like = '%' . $kw . '%';
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
        $lok    = trim(sf($r['gedung'], '') . ' Lt.' . sf($r['lantai'], '-') . ' Kamar ' . sf($r['kamar'], '-'));
        if (!empty($r['bed'])) $lok .= ' Bed ' . sf($r['bed']);
        $gen    = $r['jenis_kelamin'] === 'L' ? '♂' : ($r['jenis_kelamin'] === 'P' ? '♀' : '');
        $ci = $co = $dur = '';
        if ($r['checkin_date'])  $ci = tgl($r['checkin_date']) . ($r['checkin_time'] ? ' ' . substr($r['checkin_time'], 0, 5) : '');
        if ($r['checkout_date']) {
            $co = tgl($r['checkout_date']) . ($r['checkout_time'] ? ' ' . substr($r['checkout_time'], 0, 5) : '');
            if ($r['checkin_date']) {
                $ml = (int)round((strtotime($r['checkout_date']) - strtotime($r['checkin_date'])) / 86400);
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
    return $out . "_Data per " . nl() . "_";
}

function ansCekinInstansi(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai    = resolveAg($c, $q);
    $ag    = !empty($ai['semua']) ? $ai['semua'][0] : [];
    $aid   = (int)($ag['id'] ?? 0);
    $judul = $ag['judul'] ?? ('Agenda #' . $aid);
    $list  = rows($c, "SELECT instansi,COUNT(*) AS n,
        SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
        SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS out_
        FROM {$t} WHERE agenda_id=? AND instansi IS NOT NULL AND instansi<>''
        GROUP BY instansi ORDER BY n DESC LIMIT 20", 'i', [$aid]);
    if (empty($list)) return "Tidak ada data instansi untuk **{$judul}**.";
    $out = "**Instansi peserta terbanyak — {$judul}:**\n\n";
    foreach ($list as $i => $r) {
        $out .= ($i + 1) . ". **{$r['instansi']}** — **" . num($r['n']) . "** orang";
        $out .= " (✅" . num($r['inap']) . " inap | ⏳" . num($r['belum']) . " belum | 🚪" . num($r['out_']) . " keluar)\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

function ansCekinGender(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai    = resolveAg($c, $q);
    $ag    = !empty($ai['semua']) ? $ai['semua'][0] : [];
    $aid   = (int)($ag['id'] ?? 0);
    $judul = $ag['judul'] ?? ('Agenda #' . $aid);
    $s = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN jenis_kelamin='L' THEN 1 ELSE 0 END) AS lk, SUM(CASE WHEN jenis_kelamin='P' THEN 1 ELSE 0 END) AS pr FROM {$t} WHERE agenda_id=?", 'i', [$aid]);
    $tot = (int)($s['n'] ?? 0);
    $out = "**Komposisi gender — {$judul}:**\n\n";
    $out .= "👥 Total     : **" . num($tot) . "** orang\n";
    $out .= "♂ Laki-laki  : **" . num($s['lk'] ?? 0) . "** (" . pct($s['lk'] ?? 0, $tot) . ")\n";
    $out .= "♀ Perempuan  : **" . num($s['pr'] ?? 0) . "** (" . pct($s['pr'] ?? 0, $tot) . ")\n";
    $gdg = rows($c, "SELECT gedung, SUM(CASE WHEN jenis_kelamin='L' THEN 1 ELSE 0 END) AS lk, SUM(CASE WHEN jenis_kelamin='P' THEN 1 ELSE 0 END) AS pr FROM {$t} WHERE agenda_id=? GROUP BY gedung ORDER BY gedung", 'i', [$aid]);
    if (!empty($gdg)) {
        $out .= "\n**Per gedung:**\n";
        foreach ($gdg as $g) $out .= "🏨 **" . sf($g['gedung'], '?') . "** → ♂ " . num($g['lk'] ?? 0) . " | ♀ " . num($g['pr'] ?? 0) . "\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

function ansCekinKamar(mysqli $c, string $q): string
{
    $t = cekinTbl($c);
    if (!$t) return "Tabel tidak ditemukan.";
    $ai    = resolveAg($c, $q);
    $ag    = !empty($ai['semua']) ? $ai['semua'][0] : [];
    $aid   = (int)($ag['id'] ?? 0);
    $judul = $ag['judul'] ?? ('Agenda #' . $aid);
    $list  = rows($c, "SELECT gedung,lantai,kamar,COUNT(*) AS isi,
        SUM(CASE WHEN status_inap='Check-in' THEN 1 ELSE 0 END) AS inap,
        SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status_inap='Check-out' THEN 1 ELSE 0 END) AS out_
        FROM {$t} WHERE agenda_id=? GROUP BY gedung,lantai,kamar ORDER BY gedung,lantai,kamar", 'i', [$aid]);
    if (empty($list)) return "Tidak ada data kamar untuk **{$judul}**.";
    $out = "**Detail per kamar — {$judul}:**\n";
    $lastG = '';
    foreach ($list as $r) {
        $g = sf($r['gedung'], '?');
        if ($g !== $lastG) {
            $out .= "\n🏨 **Gedung {$g}**\n";
            $lastG = $g;
        }
        $out .= "  Lt." . sf($r['lantai']) . " Kamar " . sf($r['kamar']) . " — " . num($r['isi']) . " org | ✅" . num($r['inap']) . " | ⏳" . num($r['belum']) . " | 🚪" . num($r['out_']) . "\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

/* ══════════════════════════════════════════
   KERUSAKAN — JOIN ke master tabel
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
    $tot = (int)($s['n'] ?? 0);
    if (!$tot) return "Tidak ada laporan kerusakan untuk **{$r['l']}**.";
    $out = "**Laporan kerusakan {$r['l']}:**\n\n";
    $out .= "📊 Total      : **" . num($tot) . "**\n";
    $out .= "🔴 Dilaporkan : **" . num($s['dlp']) . "** (" . pct($s['dlp'], $tot) . ")\n";
    $out .= "🟡 Diproses   : **" . num($s['pros']) . "** (" . pct($s['pros'], $tot) . ")\n";
    $out .= "🟢 Selesai    : **" . num($s['ok']) . "** (" . pct($s['ok'], $tot) . ")\n";
    if ((int)($s['tlk'] ?? 0) > 0)    $out .= "⛔ Ditolak    : **" . num($s['tlk']) . "**\n";
    if ((int)($s['darurat'] ?? 0) > 0) $out .= "🚨 Darurat    : **" . num($s['darurat']) . "**\n";
    if (!empty($s['avg_jam']))        $out .= "⏱️ Rata-rata selesai: **" . round($s['avg_jam']) . " jam**\n";
    $pri = rows($c, "SELECT lk.prioritas AS l,COUNT(*) AS c FROM {$tbl} lk WHERE DATE(lk.created_at) BETWEEN ? AND ? GROUP BY lk.prioritas ORDER BY FIELD(lk.prioritas,'darurat','tinggi','sedang','rendah')", 'ss', [$r['s'], $r['e']]);
    if (!empty($pri)) {
        $out .= "\n**Per prioritas:**\n";
        $ic = ['darurat' => '🚨', 'tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'];
        foreach ($pri as $p) $out .= ($ic[strtolower($p['l'] ?? '')] ?? '⚪') . " " . sf($p['l'], '-') . ": **" . num($p['c']) . "**\n";
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
        foreach ($list as $row) {
            $desk  = mb_substr(trim((string)$row['deskripsi']), 0, 75);
            $tek   = sf($row['teknisi_nama'], 'Belum ditugaskan');
            $tgl_  = $row['created_at'] ? date('d/m H:i', strtotime($row['created_at'])) : '-';
            $lok   = implode(' › ', array_filter([sf($row['nama_lokasi'] ?? '', ''), sf($row['nama_ruangan'] ?? '', '')]));
            $jenis = sf($row['nama_jenis'] ?? '', '');
            $kat   = sf($row['nama_kategori'] ?? '', '');
            $info  = implode(' | ', array_filter([$kat, $jenis, $lok]));
            $ico   = ['dilaporkan' => '🔴', 'selesai' => '🟢', 'ditolak' => '⛔', 'darurat' => '🚨'][$row['status']] ?? '🟡';
            $out  .= "{$ico} **#{$row['id']}** [{$tgl_}] " . sf($row['prioritas'], '-') . " | {$row['pelapor_nama']}\n";
            if ($info) $out .= "   📍 {$info}\n";
            $out  .= "   💬 {$desk}\n   🔧 {$tek}\n";
        }
    }
    return $out . "\n_Data per " . nl() . "_";
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
    $out = "**Kerusakan belum selesai** (" . num($tot['n'] ?? 0) . " laporan):\n\n";
    foreach ($list as $i => $r) {
        $desk = mb_substr(trim((string)$r['deskripsi']), 0, 75);
        $tek  = sf($r['teknisi_nama'], 'Belum ditugaskan');
        $tgl_ = $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '-';
        $hari = $r['created_at'] ? (int)round((time() - strtotime($r['created_at'])) / 86400) : 0;
        $umur = $hari > 0 ? " ⌛{$hari} hari" : '';
        $lok  = implode(' › ', array_filter([sf($r['nama_lokasi'] ?? '', ''), sf($r['nama_ruangan'] ?? '', '')]));
        $ico  = ['darurat' => '🚨', 'tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'][$r['prioritas'] ?? ''] ?? '⚪';
        $out .= ($i + 1) . ". {$ico} **#{$r['id']}** | " . sf($r['prioritas'], '-') . " | {$tgl_}{$umur}\n";
        if ($lok) $out .= "   📍 " . implode(' › ', array_filter([sf($r['nama_jenis'] ?? '', ''), $lok])) . "\n";
        $out .= "   👤 {$r['pelapor_nama']} | 💬 {$desk}\n";
        $out .= "   🔧 {$tek} | " . sf($r['status']) . "\n\n";
    }
    return $out . "_Data per " . nl() . "_";
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
    if (empty($list)) return "Tidak ada data teknisi untuk **{$r['l']}**.";
    $out = "**Ranking teknisi aktif {$r['l']}:**\n\n";
    foreach ($list as $i => $row) {
        $avg = !empty($row['avg_jam']) ? " | avg " . round($row['avg_jam']) . " jam" : '';
        $out .= ($i + 1) . ". **{$row['teknisi_nama']}** — **" . num($row['n']) . "** laporan\n";
        $out .= "   ✅ " . num($row['ok']) . " selesai (" . pct($row['ok'], $row['n']) . "){$avg}\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

function ansRusakJenis(mysqli $c, array $r): string
{
    $tbl = rusakTbl($c);
    if (!$tbl) return "Tabel tidak ditemukan.";
    $list = rows($c, "SELECT mjk.nama_jenis AS label, COUNT(*) AS c
        FROM {$tbl} lk LEFT JOIN master_jenis_kerusakan mjk ON mjk.id=lk.jenis_kerusakan_id
        WHERE DATE(lk.created_at) BETWEEN ? AND ? AND mjk.nama_jenis IS NOT NULL
        GROUP BY mjk.nama_jenis ORDER BY c DESC LIMIT 15", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return "Tidak ada data jenis kerusakan untuk **{$r['l']}**.";
    $out = "**Jenis kerusakan terbanyak {$r['l']}:**\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['label']) . "** — " . num($row['c']) . " laporan\n";
    return $out . "\n_Data per " . nl() . "_";
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
    if (empty($list)) return "Tidak ada data kerusakan per kategori untuk **{$r['l']}**.";
    $out = "**Kerusakan per kategori {$r['l']}:**\n\n";
    foreach ($list as $i => $row) {
        $kat = sf($row['kat'], 'Tidak diketahui');
        $out .= ($i + 1) . ". **{$kat}** — **" . num($row['n']) . "** laporan | ✅" . num($row['ok']) . " selesai | ⏳" . num($row['pending']) . " pending\n";
    }
    return $out . "\n_Data per " . nl() . "_";
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
    if (empty($list)) return "Tidak ada data lokasi kerusakan untuk **{$r['l']}**.";
    $out = "**Lokasi paling sering rusak {$r['l']}:**\n\n";
    foreach ($list as $i => $row) {
        $lok  = sf($row['lokasi'], '-');
        $tipe = sf($row['tipe'], '');
        $lbl  = $tipe ? "{$lok} ({$tipe})" : $lok;
        $out .= ($i + 1) . ". **{$lbl}** — **" . num($row['n']) . "** laporan | ⏳" . num($row['pending']) . " pending\n";
    }
    return $out . "\n_Data per " . nl() . "_";
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
    if (!(int)($s['n'] ?? 0)) return "Tidak ada data checklist untuk **{$r['l']}**.";
    $perForm = rows($c, "SELECT form_type,COUNT(*) AS c FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY form_type ORDER BY c DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $petugas = rows($c, "SELECT nama_petugas,COUNT(*) AS c FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY nama_petugas ORDER BY c DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $out  = "**Checklist {$r['l']}:**\n\n📋 Total form: **" . num($s['n']) . "** | 👤 Petugas: **" . num($s['pt']) . "** | 📄 Jenis: **" . num($s['fm']) . "** | 🗺️ Area: **" . num($s['ar']) . "**\n";
    if (!empty($perForm)) {
        $out .= "\n**Per jenis form:**\n";
        foreach ($perForm as $row) $out .= "• " . sf($row['form_type']) . ": " . num($row['c']) . " form\n";
    }
    if (!empty($petugas)) {
        $out .= "\n**Petugas aktif:**\n";
        foreach ($petugas as $i => $row) $out .= ($i + 1) . ". " . sf($row['nama_petugas']) . " — " . num($row['c']) . " form\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

function ansChecklistArea(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT area_kerja,COUNT(*) AS c,COUNT(DISTINCT nip_user) AS usr FROM checklist_forms WHERE tanggal BETWEEN ? AND ? AND area_kerja IS NOT NULL AND area_kerja<>'' GROUP BY area_kerja ORDER BY c DESC LIMIT 20", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return "Tidak ada data checklist per area untuk **{$r['l']}**.";
    $out = "**Checklist per area kerja {$r['l']}:**\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['area_kerja']) . "** — " . num($row['c']) . " form | " . num($row['usr']) . " petugas\n";
    return $out . "\n_Data per " . nl() . "_";
}

function ansChecklistRegu(mysqli $c, array $r): string
{
    if (col($c, 'checklist_forms', 'regu')) {
        $list = rows($c, "SELECT regu,COUNT(*) AS c,COUNT(DISTINCT nip_user) AS usr FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY regu ORDER BY c DESC", 'ss', [$r['s'], $r['e']]);
        $out  = "**Checklist per regu {$r['l']}:**\n\n";
        foreach ($list as $row) $out .= "• **" . sf($row['regu'], 'Tanpa Regu') . "**: " . num($row['c']) . " form | " . num($row['usr']) . " petugas\n";
        return $out . "\n_Data per " . nl() . "_";
    }
    return ansChecklist($c, $r);
}

function ansChecklistTop(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT nama_petugas,COUNT(*) AS c,COUNT(DISTINCT form_type) AS jenis,COUNT(DISTINCT area_kerja) AS area FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY nama_petugas ORDER BY c DESC LIMIT 15", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return "Tidak ada data checklist untuk **{$r['l']}**.";
    $out = "**Ranking petugas aktif {$r['l']}:**\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['nama_petugas']) . "** — " . num($row['c']) . " form | " . num($row['jenis']) . " jenis | " . num($row['area']) . " area\n";
    return $out . "\n_Data per " . nl() . "_";
}

function ansChecklistUser(mysqli $c, array $r): string
{
    // JOIN via nip_user = users.nip (struktur DB yang benar)
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
            $out = "**Akun yang sering isi checklist {$r['l']}:**\n\n";
            foreach ($list as $i => $row) {
                $role    = $row['role'] ? " (" . sf($row['role']) . ")" : '';
                $periode = tgl($row['tgl_awal']) . " s/d " . tgl($row['tgl_akhir']);
                $out .= ($i + 1) . ". **" . sf($row['nm'], 'Tidak Diketahui') . "{$role}**\n";
                $out .= "   📋 " . num($row['n']) . " form | 📄 " . num($row['jenis']) . " jenis | 🗺️ " . num($row['area']) . " area | 📅 {$periode}\n\n";
            }
            return $out . "_Data per " . nl() . "_";
        }
    }
    $list = rows($c, "SELECT nama_petugas, COUNT(*) AS n, COUNT(DISTINCT form_type) AS jenis, MIN(tanggal) AS tgl_awal, MAX(tanggal) AS tgl_akhir FROM checklist_forms WHERE tanggal BETWEEN ? AND ? GROUP BY nama_petugas ORDER BY n DESC LIMIT 20", 'ss', [$r['s'], $r['e']]);
    $out  = "**Petugas paling sering isi checklist {$r['l']}:**\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['nama_petugas']) . "** — " . num($row['n']) . " form | " . num($row['jenis']) . " jenis | " . tgl($row['tgl_awal']) . " s/d " . tgl($row['tgl_akhir']) . "\n";
    return $out . "\n_Data per " . nl() . "_";
}

function ansChecklistCatatan(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT cf.tanggal, cf.nama_petugas, cf.form_type, cf.area_kerja,
                cf.area_gedung, cf.catatan_kerusakan
        FROM checklist_forms cf
        WHERE cf.tanggal BETWEEN ? AND ? AND cf.catatan_kerusakan IS NOT NULL AND cf.catatan_kerusakan<>''
        ORDER BY cf.tanggal DESC, cf.id DESC LIMIT 30", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return "Tidak ada catatan kerusakan pada checklist untuk **{$r['l']}**.";
    $out = "**Catatan kerusakan dari checklist {$r['l']}** (" . count($list) . " catatan):\n\n";
    foreach ($list as $i => $row) {
        $area = implode(' › ', array_filter([sf($row['area_gedung'] ?? '', ''), sf($row['area_kerja'] ?? '', '')]));
        $out .= ($i + 1) . ". **" . tgl($row['tanggal']) . "** | " . sf($row['nama_petugas']) . " | " . sf($row['form_type']) . "\n";
        if ($area) $out .= "   📍 {$area}\n";
        $out .= "   📝 " . mb_substr(trim((string)$row['catatan_kerusakan']), 0, 120) . "\n\n";
    }
    return $out . "_Data per " . nl() . "_";
}

/* ══════════════════════════════════════════
   SURAT
══════════════════════════════════════════ */
function ansSurat(mysqli $c, array $r, string $mode = 'semua'): string
{
    $s = one($c, "SELECT SUM(CASE WHEN jenis='masuk' THEN 1 ELSE 0 END) AS mk, SUM(CASE WHEN jenis='keluar' THEN 1 ELSE 0 END) AS kl, COUNT(*) AS n FROM arsip_surat WHERE tanggal_surat BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    if (!(int)($s['n'] ?? 0)) return "Tidak ada arsip surat untuk **{$r['l']}**.";
    $ex   = $mode === 'masuk' ? " AND jenis='masuk'" : ($mode === 'keluar' ? " AND jenis='keluar'" : '');
    $list = rows($c, "SELECT nomor_surat,perihal,pengirim,jenis,tanggal_surat,keterangan FROM arsip_surat WHERE tanggal_surat BETWEEN ? AND ?{$ex} ORDER BY tanggal_surat DESC,id DESC LIMIT 15", 'ss', [$r['s'], $r['e']]);
    $lb   = $mode === 'masuk' ? ' masuk' : ($mode === 'keluar' ? ' keluar' : '');
    $out  = "**Arsip surat{$lb} {$r['l']}:**\n\n📥 Masuk: **" . num($s['mk']) . "** | 📤 Keluar: **" . num($s['kl']) . "**\n\n";
    foreach ($list as $row) {
        $ic = $row['jenis'] === 'masuk' ? '📥' : '📤';
        $out .= "{$ic} **[" . sf($row['nomor_surat']) . "]** — " . tgl($row['tanggal_surat']) . "\n";
        $out .= "   📝 " . sf($row['perihal']) . "\n   👤 " . sf($row['pengirim']) . "\n";
        if (!empty($row['keterangan'])) $out .= "   ℹ️ " . sf($row['keterangan']) . "\n";
        $out .= "\n";
    }
    return $out . "_Data per " . nl() . "_";
}

function ansSuratPengirim(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT pengirim,COUNT(*) AS c FROM arsip_surat WHERE tanggal_surat BETWEEN ? AND ? GROUP BY pengirim ORDER BY c DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return "Tidak ada data surat untuk **{$r['l']}**.";
    $out = "**Pengirim surat terbanyak {$r['l']}:**\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['pengirim']) . "** — " . num($row['c']) . " surat\n";
    return $out . "\n_Data per " . nl() . "_";
}

/* ══════════════════════════════════════════
   GUDANG
══════════════════════════════════════════ */
function ansGudang(mysqli $c, array $r): string
{
    $m = one($c, "SELECT COUNT(DISTINCT bm.id) AS tr,COALESCE(SUM(bmd.qty),0) AS qty FROM barang_masuk bm LEFT JOIN barang_masuk_detail bmd ON bmd.barang_masuk_id=bm.id WHERE bm.tanggal BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    $k = one($c, "SELECT COUNT(DISTINCT bk.id) AS tr,COALESCE(SUM(bkd.qty),0) AS qty FROM barang_keluar bk LEFT JOIN barang_keluar_detail bkd ON bkd.barang_keluar_id=bk.id WHERE bk.tanggal BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    $out  = "**Rekap gudang {$r['l']}:**\n\n";
    $out .= "📦 Barang masuk  : **" . num($m['tr']) . "** transaksi | **" . num($m['qty']) . "** item\n";
    $out .= "📤 Barang keluar : **" . num($k['tr']) . "** transaksi | **" . num($k['qty']) . "** item\n";
    $selisih = (float)($m['qty'] ?? 0) - (float)($k['qty'] ?? 0);
    $out .= "📊 Net           : **" . num(abs($selisih)) . "** item " . ($selisih >= 0 ? '➕' : '➖') . "\n";
    return $out . "\n_Data per " . nl() . "_";
}

function ansGudangTop(mysqli $c, array $r): string
{
    $topM = rows($c, "SELECT bmd.nama_barang,SUM(bmd.qty) AS qty FROM barang_masuk_detail bmd INNER JOIN barang_masuk bm ON bm.id=bmd.barang_masuk_id WHERE bm.tanggal BETWEEN ? AND ? GROUP BY bmd.nama_barang ORDER BY qty DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $topK = rows($c, "SELECT bkd.nama_barang,SUM(bkd.qty) AS qty FROM barang_keluar_detail bkd INNER JOIN barang_keluar bk ON bk.id=bkd.barang_keluar_id WHERE bk.tanggal BETWEEN ? AND ? GROUP BY bkd.nama_barang ORDER BY qty DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $out  = "**Barang terbanyak {$r['l']}:**\n\n";
    if (!empty($topM)) {
        $out .= "📦 **Masuk terbanyak:**\n";
        foreach ($topM as $i => $row) $out .= ($i + 1) . ". " . sf($row['nama_barang']) . " — **" . num($row['qty']) . "** item\n";
    }
    if (!empty($topK)) {
        $out .= "\n📤 **Keluar terbanyak:**\n";
        foreach ($topK as $i => $row) $out .= ($i + 1) . ". " . sf($row['nama_barang']) . " — **" . num($row['qty']) . "** item\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

function ansGudangStok(mysqli $c): string
{
    if (!tbl($c, 'master_barang')) return "Tabel master_barang tidak tersedia.";
    $tot  = one($c, "SELECT COUNT(*) AS n FROM master_barang");
    $list = rows($c, "SELECT mb.kode_barang, mb.nama_barang, mb.satuan, mb.stok_awal,
                COALESCE((SELECT SUM(bmd.qty) FROM barang_masuk_detail bmd WHERE bmd.kode_barang=mb.kode_barang),0) AS masuk,
                COALESCE((SELECT SUM(bkd.qty) FROM barang_keluar_detail bkd WHERE bkd.kode_barang=mb.kode_barang),0) AS keluar
        FROM master_barang mb ORDER BY mb.nama_barang ASC LIMIT 30");
    $out  = "**Daftar stok barang** (" . num($tot['n'] ?? 0) . " item):\n\n";
    foreach ($list as $i => $row) {
        $stok = (int)($row['stok_awal'] ?? 0) + (int)($row['masuk'] ?? 0) - (int)($row['keluar'] ?? 0);
        $icon = $stok <= 0 ? '🔴' : ($stok < 10 ? '🟡' : '🟢');
        $out .= ($i + 1) . ". {$icon} **" . sf($row['nama_barang']) . "** — Stok: **" . num($stok) . "** " . sf($row['satuan'], 'pcs') . "\n";
    }
    if ((int)($tot['n'] ?? 0) > 30) $out .= "\n_...ditampilkan 30 teratas._\n";
    return $out . "\n_Data per " . nl() . "_";
}

/* ══════════════════════════════════════════
   TAMU — kolom asal (bukan asal_instansi)
══════════════════════════════════════════ */
function ansTamu(mysqli $c, array $r): string
{
    $s = one($c, "SELECT COUNT(*) AS n,
        SUM(CASE WHEN jenis_layanan='pelayanan_umum'      THEN 1 ELSE 0 END) AS um,
        SUM(CASE WHEN jenis_layanan='pelayanan_informasi' THEN 1 ELSE 0 END) AS inf,
        SUM(CASE WHEN jenis_layanan='pelayanan_pengaduan' THEN 1 ELSE 0 END) AS peng
        FROM buku_tamu WHERE DATE(created_at) BETWEEN ? AND ?", 'ss', [$r['s'], $r['e']]);
    if (!(int)($s['n'] ?? 0)) return "Tidak ada tamu untuk **{$r['l']}**.";
    $list = rows($c, "SELECT nama, asal, keperluan, jenis_layanan, created_at FROM buku_tamu WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    $out  = "**Buku tamu {$r['l']}:**\n\n";
    $out .= "👥 Total: **" . num($s['n']) . "** | Umum: **" . num($s['um']) . "** | Info: **" . num($s['inf']) . "** | Pengaduan: **" . num($s['peng']) . "**\n\n";
    foreach ($list as $row) {
        $asal = !empty($row['asal']) ? ' (' . $row['asal'] . ')' : '';
        $jam  = $row['created_at'] ? date('H:i', strtotime($row['created_at'])) : '';
        $out .= "• **" . sf($row['nama']) . "{$asal}** — " . sf($row['keperluan']) . " [{$jam}]\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

function ansTamuInstansi(mysqli $c, array $r): string
{
    $list = rows($c, "SELECT asal AS inst,COUNT(*) AS c FROM buku_tamu WHERE DATE(created_at) BETWEEN ? AND ? AND asal IS NOT NULL AND asal<>'' GROUP BY asal ORDER BY c DESC LIMIT 10", 'ss', [$r['s'], $r['e']]);
    if (empty($list)) return "Tidak ada data instansi tamu untuk **{$r['l']}**.";
    $out = "**Instansi tamu terbanyak {$r['l']}:**\n\n";
    foreach ($list as $i => $row) $out .= ($i + 1) . ". **" . sf($row['inst']) . "** — " . num($row['c']) . " kunjungan\n";
    return $out . "\n_Data per " . nl() . "_";
}

/* ══════════════════════════════════════════
   KENDARAAN
   [FIX v5] cek kolom dicatat_oleh sebelum SELECT
══════════════════════════════════════════ */
function ansKendaraan(mysqli $c): string
{
    if (!tbl($c, 'kendaraan_log')) return "Data kendaraan tamu belum tersedia.";
    $t    = td();
    $s    = one($c, "SELECT SUM(CASE WHEN status='masuk' THEN 1 ELSE 0 END) AS mk, SUM(CASE WHEN status='keluar' THEN 1 ELSE 0 END) AS kl FROM kendaraan_log WHERE DATE(waktu_masuk)=?", 's', [$t]);
    // [FIX v5] Cek keberadaan kolom sebelum SELECT untuk cegah error
    $hasDicatat = col($c, 'kendaraan_log', 'dicatat_oleh');
    $selExtra   = $hasDicatat ? ',dicatat_oleh' : '';
    $list = rows($c, "SELECT plat_nomor,instansi_tamu,tujuan,waktu_masuk,waktu_keluar,status{$selExtra} FROM kendaraan_log ORDER BY waktu_masuk DESC LIMIT 15");
    $out  = "**Log kendaraan tamu hari ini:**\n\n🚗 Masuk: **" . num($s['mk'] ?? 0) . "** | Keluar: **" . num($s['kl'] ?? 0) . "**\n\n";
    foreach ($list as $r) {
        $ic = $r['status'] === 'masuk' ? '🟢' : '🔴';
        $wm = $r['waktu_masuk']  ? date('d/m H:i', strtotime($r['waktu_masuk']))  : '-';
        $wk = $r['waktu_keluar'] ? ' | Keluar: ' . date('H:i', strtotime($r['waktu_keluar'])) : '';
        $dc = ($hasDicatat && !empty($r['dicatat_oleh'])) ? " | Dicatat: " . sf($r['dicatat_oleh']) : '';
        $out .= "{$ic} **" . sf($r['plat_nomor']) . "** — " . sf($r['tujuan']) . "\n";
        $out .= "   " . sf($r['instansi_tamu']) . " | {$wm}{$wk}{$dc}\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

function ansKendaraanOperasional(mysqli $c): string
{
    if (!tbl($c, 'kendaraan_operasional_log')) return "Data kendaraan operasional belum tersedia.";
    $s    = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN status='keluar' THEN 1 ELSE 0 END) AS keluar, SUM(CASE WHEN status='kembali' THEN 1 ELSE 0 END) AS kembali FROM kendaraan_operasional_log");
    $list = rows($c, "SELECT plat_nomor,pengemudi,tujuan,keterangan,waktu_keluar,waktu_kembali,status,dicatat_oleh FROM kendaraan_operasional_log ORDER BY waktu_keluar DESC LIMIT 15");
    $out  = "**Kendaraan operasional/dinas:**\n\n🚐 Total: **" . num($s['n'] ?? 0) . "** | Belum kembali: **" . num($s['keluar'] ?? 0) . "** | Sudah kembali: **" . num($s['kembali'] ?? 0) . "**\n\n";
    foreach ($list as $r) {
        $ic = $r['status'] === 'keluar' ? '🟡' : '✅';
        $wk = $r['waktu_keluar']  ? date('d/m H:i', strtotime($r['waktu_keluar']))  : '-';
        $wb = $r['waktu_kembali'] ? date('d/m H:i', strtotime($r['waktu_kembali'])) : '-';
        $out .= "{$ic} **" . sf($r['plat_nomor']) . "** — " . sf($r['pengemudi']) . "\n";
        $out .= "   Tujuan: " . sf($r['tujuan']) . " | Keluar: {$wk} | Kembali: {$wb}\n";
        if (!empty($r['keterangan'])) $out .= "   ℹ️ " . sf($r['keterangan']) . "\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

/* ══════════════════════════════════════════
   PENGGUNA — semua 13 role dari enum
══════════════════════════════════════════ */
function ansUserRole(mysqli $c, string $q): string
{
    if (!tbl($c, 'users')) return "Tabel users tidak ditemukan.";
    $allRoles = [
        'admin',
        'pimpinan',
        'petugas',
        'security',
        'ob',
        'teknisi',
        'koordinator',
        'poliklinik',
        'gudang',
        'driver',
        'perpustakaan',
        'sekretariat',
        'supervisor'
    ];
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
    $icon = $roleIcon[$foundRole] ?? '👤';
    if (empty($list)) {
        $existing = rows($c, "SELECT DISTINCT role, COUNT(*) AS c FROM users GROUP BY role ORDER BY role ASC");
        $out = "Tidak ada pengguna dengan role **{$foundRole}**.\n\n**Role yang tersedia:**\n";
        foreach ($existing as $row) $out .= ($roleIcon[$row['role']] ?? '👤') . " {$row['role']}: **" . num($row['c']) . "** orang\n";
        return $out . "\n_Data per " . nl() . "_";
    }
    $out = "{$icon} **Daftar {$foundRole}** (" . count($list) . " orang):\n\n";
    foreach ($list as $i => $r) {
        $nip   = !empty($r['nip'])   ? " | NIP: {$r['nip']}"   : '';
        $phone = !empty($r['phone']) ? " | 📱 {$r['phone']}"   : '';
        $out  .= ($i + 1) . ". **" . sf($r['nama']) . "**{$nip}{$phone}\n";
    }
    return $out . "\n_Data per " . nl() . "_";
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
    $out  = "**Data pengguna sistem:**\n\n👤 Total akun: **" . num($tot['n'] ?? 0) . "** orang\n\n**Rekap per role:**\n";
    foreach ($perRole as $r) $out .= ($roleIcon[$r['role']] ?? '👤') . " **{$r['role']}** : **" . num($r['c']) . "** orang\n";
    $out .= "\n**Detail per role:**\n";
    foreach ($perRole as $r) {
        $ic   = $roleIcon[$r['role']] ?? '👤';
        $cnt  = (int)$r['c'];
        $out .= "\n{$ic} **" . strtoupper($r['role']) . "** ({$cnt} orang)\n";
        $members = rows($c, "SELECT nama, nip FROM users WHERE role=? ORDER BY nama ASC" . ($cnt > 15 ? ' LIMIT 5' : ''), 's', [$r['role']]);
        foreach ($members as $i => $m) $out .= "   " . ($i + 1) . ". " . sf($m['nama']) . (!empty($m['nip']) ? " — {$m['nip']}" : '') . "\n";
        if ($cnt > 15) $out .= "   _...dan " . ($cnt - 5) . " lainnya. Ketik \"daftar {$r['role']}\" untuk lengkap._\n";
    }
    return $out . "\n_Data per " . nl() . "_";
}

/* ══════════════════════════════════════════
   RINGKASAN
   [FIX v5] Gunakan array union (+) agar $ip/$rs/$tm/$kn
   tidak tertimpa array kosong saat one() gagal query
══════════════════════════════════════════ */
function ansRingkasan(mysqli $c): string
{
    $t  = td();
    // [FIX v5] Null-safe dengan ?? 0 di semua akses
    $ag = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?", 'ss', [$t, $t]);

    $ct = cekinTbl($c);
    $ip = ['n' => 0, 'inap' => 0, 'belum' => 0];
    if ($ct) {
        // [FIX v5] Array union: $tmp + $ip menjaga default jika $tmp kosong
        $tmp = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN status_inap='Check-in' THEN 1 ELSE 0 END) AS inap, SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum FROM {$ct} pp JOIN agenda_kegiatan ak ON ak.id=pp.agenda_id WHERE ak.start_date<=? AND ak.end_date>=?", 'ss', [$t, $t]);
        if (!empty($tmp)) $ip = $tmp + $ip;
    }

    $rt = rusakTbl($c);
    $rs = ['n' => 0, 'pend' => 0, 'darurat' => 0, 'tinggi' => 0];
    if ($rt) {
        $tmp = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN status NOT IN ('selesai','ditolak') THEN 1 ELSE 0 END) AS pend, SUM(CASE WHEN status NOT IN ('selesai','ditolak') AND prioritas='darurat' THEN 1 ELSE 0 END) AS darurat, SUM(CASE WHEN status NOT IN ('selesai','ditolak') AND prioritas='tinggi' THEN 1 ELSE 0 END) AS tinggi FROM {$rt}");
        if (!empty($tmp)) $rs = $tmp + $rs;
    }

    $tm = ['n' => 0];
    if (tbl($c, 'buku_tamu')) {
        $tmp = one($c, "SELECT COUNT(*) AS n FROM buku_tamu WHERE DATE(created_at)=?", 's', [$t]);
        if (!empty($tmp)) $tm = $tmp + $tm;
    }

    $sr = one($c, "SELECT SUM(CASE WHEN jenis='masuk' THEN 1 ELSE 0 END) AS m, SUM(CASE WHEN jenis='keluar' THEN 1 ELSE 0 END) AS k FROM arsip_surat WHERE tanggal_surat BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01') AND ?", 's', [$t]);
    $ck = one($c, "SELECT COUNT(*) AS c,COUNT(DISTINCT nip_user) AS pt FROM checklist_forms WHERE tanggal=?", 's', [$t]);

    $kn = ['n' => 0, 'keluar' => 0];
    if (tbl($c, 'kendaraan_operasional_log')) {
        $tmp = one($c, "SELECT COUNT(*) AS n, SUM(CASE WHEN status='keluar' THEN 1 ELSE 0 END) AS keluar FROM kendaraan_operasional_log WHERE DATE(waktu_keluar)=?", 's', [$t]);
        if (!empty($tmp)) $kn = $tmp + $kn;
    }

    $out  = "📊 **Ringkasan Operasional Hari Ini**\n" . str_repeat('─', 38) . "\n\n";
    $out .= "📅 Agenda aktif        : **" . num($ag['c'] ?? 0) . "** kegiatan (" . num($ag['p'] ?? 0) . " peserta)\n";
    $out .= "🏠 Penginapan          : **" . num($ip['n']) . "** total | 🏃" . num($ip['inap']) . " inap | ⏳" . num($ip['belum']) . " belum\n";
    $out .= "📋 Checklist hari ini  : **" . num($ck['c'] ?? 0) . "** form | " . num($ck['pt'] ?? 0) . " petugas\n";
    $out .= "🔧 Kerusakan pending   : **" . num($rs['pend']) . "** dari " . num($rs['n']) . " total";
    $alerts = [];
    if ((int)($rs['darurat'] ?? 0) > 0) $alerts[] = "🚨 " . num($rs['darurat']) . " DARURAT";
    if ((int)($rs['tinggi'] ?? 0)  > 0) $alerts[] = "🔴 " . num($rs['tinggi'])  . " tinggi";
    if (!empty($alerts)) $out .= " (" . implode(', ', $alerts) . ")";
    $out .= "\n";
    $out .= "👥 Tamu hari ini       : **" . num($tm['n']) . "** orang\n";
    $out .= "📨 Surat bulan ini     : **" . num($sr['m'] ?? 0) . "** masuk | **" . num($sr['k'] ?? 0) . "** keluar\n";
    if ((int)($kn['n'] ?? 0) > 0) $out .= "🚐 Kendaraan dinas     : **" . num($kn['keluar']) . "** belum kembali dari " . num($kn['n']) . " perjalanan\n";
    return $out . "\n_" . nl() . "_";
}

/* ══════════════════════════════════════════
   FALLBACK
══════════════════════════════════════════ */
function ansFallback(string $q): string
{
    if (has($q, ['halo', 'hai ', 'assalamu', 'selamat pagi', 'selamat siang', 'selamat sore', 'selamat malam']))
        return ansHelp();
    if (has($q, ['berapa', 'jumlah', 'total']))
        return "Coba lebih spesifik:\n• \"berapa peserta hari ini\"\n• \"berapa kerusakan bulan ini\"\n• \"berapa tamu minggu ini\"\n\nKetik **bantuan** untuk melihat semua fitur.";
    return "🤔 Hmm, saya belum paham maksudnya.\n\nCoba:\n• \"ringkasan hari ini\"\n• \"agenda hari ini\"\n• \"siapa belum check-in\"\n• \"kerusakan pending\"\n\nAtau ketik **bantuan** untuk semua fitur yang tersedia.";
}

/* ══════════════════════════════════════════
   MAIN ROUTER
══════════════════════════════════════════ */
$Q = nq($question);
$I = intent($Q);
$R = rng($Q);

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
