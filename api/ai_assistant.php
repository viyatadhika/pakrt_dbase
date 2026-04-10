<?php

/**
 * ============================================================
 *  ASISTEN OPERASIONAL v3 — Pure Database, Super Detail
 *  Database : warga_rt_bsdk
 *  Changelog v3:
 *   [FIX]  ansCekinCari — regex lebih pintar, strip kata umum
 *          lebih lengkap (yang, keluar, masuk, dari, dll)
 *   [FIX]  ansCekinCari — query sekarang cari di semua agenda,
 *          bukan hanya agenda aktif hari ini
 *   [NEW]  Intent 'checklist_user' — siapa user/petugas yang
 *          paling sering mengisi form checklist
 *   [NEW]  ansCekinCari — tampilkan info lebih lengkap:
 *          waktu cekin/cekout, nomor kamar, instansi, gender
 *   [NEW]  ansKerusakan — lebih detail: durasi penanganan,
 *          teknisi aktif, breakdown per prioritas
 *   [NEW]  ansAgenda — info makan, kelas, asrama lebih rapi
 *   [NEW]  ansRingkasan — tambah info kerusakan prioritas tinggi
 *   [IMPR] normalizeText — tambah alias kata "keluar", "masuk"
 *   [IMPR] detectIntent — "user checklist", "petugas checklist"
 * ============================================================
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

$raw      = file_get_contents('php://input');
$data     = json_decode($raw, true);
$question = trim((string)($data['question'] ?? ''));

if ($question === '') {
    http_response_code(422);
    echo json_encode(['status' => false, 'message' => 'Pertanyaan tidak boleh kosong.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ══════════════════════════════════════════════
//  HELPER DASAR
// ══════════════════════════════════════════════
function respondJson(bool $ok, string $ans, array $meta = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['status' => $ok, 'answer' => $ans, 'meta' => $meta], JSON_UNESCAPED_UNICODE);
    exit;
}

function now_label(): string
{
    return date('d-m-Y H:i');
}
function today(): string
{
    return date('Y-m-d');
}
function num(float $v): string
{
    return number_format($v, 0, ',', '.');
}

function pct(float $part, float $total): string
{
    if ($total <= 0) return '0%';
    return round($part / $total * 100) . '%';
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

function safe($v, string $fb = '-'): string
{
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $fb;
}

function normalizeText(string $t): string
{
    $t = mb_strtolower(trim($t));
    $t = str_replace(
        [
            'check in',
            'check-in',
            'cek in',
            'check out',
            'check-out',
            'rekapitulasi',
            'rekap kegiatan'
        ],
        ['cekin', 'cekin', 'cekin', 'cekout', 'cekout', 'rekap', 'rekap agenda'],
        $t
    );
    return preg_replace('/\s+/', ' ', $t);
}

function has(string $t, array $kw): bool
{
    foreach ($kw as $k) if ($k !== '' && mb_strpos($t, $k) !== false) return true;
    return false;
}

// ── DB helpers ────────────────────────────────
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

function all(mysqli $c, string $sql, string $tp = '', array $p = []): array
{
    $s = $c->prepare($sql);
    if (!$s) return [];
    if ($tp && $p) $s->bind_param($tp, ...$p);
    if (!$s->execute()) {
        $s->close();
        return [];
    }
    $r    = $s->get_result();
    $rows = [];
    if ($r) while ($row = $r->fetch_assoc()) $rows[] = $row;
    $s->close();
    return $rows;
}

function tblExists(mysqli $c, string $t): bool
{
    $safe = $c->real_escape_string($t);
    $r    = $c->query("SHOW TABLES LIKE '{$safe}'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}

function colExists(mysqli $c, string $t, string $col): bool
{
    $st = $c->real_escape_string($t);
    $sc = $c->real_escape_string($col);
    $r  = $c->query("SHOW COLUMNS FROM `{$st}` LIKE '{$sc}'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}

function cekinTable(mysqli $c): ?string
{
    foreach (
        [
            'peserta_penginapan',
            'cekin_peserta',
            'cekin_cekout',
            'checkin_peserta',
            'data_cekin'
        ] as $t
    )
        if (tblExists($c, $t)) return $t;
    return null;
}

function rusakTable(mysqli $c): ?string
{
    foreach (['kerusakan', 'laporan_kerusakan', 'data_kerusakan'] as $t)
        if (tblExists($c, $t)) return $t;
    return null;
}

// ══════════════════════════════════════════════
//  DETEKSI TANGGAL
// ══════════════════════════════════════════════
function dateRange(string $q): array
{
    $t = today();
    if (has($q, ['hari ini', 'sekarang', 'today']))
        return ['label' => 'hari ini', 'start' => $t, 'end' => $t];
    if (has($q, ['kemarin', 'yesterday'])) {
        $d = date('Y-m-d', strtotime('-1 day'));
        return ['label' => 'kemarin', 'start' => $d, 'end' => $d];
    }
    if (has($q, ['minggu ini', 'pekan ini']))
        return ['label' => 'minggu ini', 'start' => date('Y-m-d', strtotime('monday this week')), 'end' => date('Y-m-d', strtotime('sunday this week'))];
    if (has($q, ['minggu lalu', 'pekan lalu']))
        return ['label' => 'minggu lalu', 'start' => date('Y-m-d', strtotime('monday last week')), 'end' => date('Y-m-d', strtotime('sunday last week'))];
    if (has($q, ['bulan ini']))
        return ['label' => 'bulan ini', 'start' => date('Y-m-01'), 'end' => date('Y-m-t')];
    if (has($q, ['bulan lalu']))
        return ['label' => 'bulan lalu', 'start' => date('Y-m-01', strtotime('first day of last month')), 'end' => date('Y-m-t', strtotime('last day of last month'))];
    if (has($q, ['tahun ini']))
        return ['label' => 'tahun ini', 'start' => date('Y-01-01'), 'end' => date('Y-12-31')];
    if (has($q, ['7 hari', 'tujuh hari', 'seminggu terakhir']))
        return ['label' => '7 hari terakhir', 'start' => date('Y-m-d', strtotime('-6 days')), 'end' => $t];
    if (has($q, ['30 hari', 'tiga puluh hari', 'sebulan terakhir']))
        return ['label' => '30 hari terakhir', 'start' => date('Y-m-d', strtotime('-29 days')), 'end' => $t];
    return ['label' => 'hari ini', 'start' => $t, 'end' => $t];
}

// ══════════════════════════════════════════════
//  DETEKSI INTENT
// ══════════════════════════════════════════════
function detectIntent(string $q): string
{
    // ── Cekin detail ─────────────────────────
    if (has($q, [
        'cari peserta',
        'cari nama',
        'siapa peserta',
        'dimana peserta',
        'instansi mana',
        'asal instansi',
        'cari orang',
        'cari siapa'
    ]))
        return 'cekin_cari';
    if (has($q, ['instansi terbanyak', 'instansi paling banyak']))
        return 'cekin_instansi';
    if (has($q, ['peserta laki', 'peserta perempuan', 'gender', 'jenis kelamin']))
        return 'cekin_gender';
    if (has($q, ['per kamar', 'per gedung detail', 'detail kamar']))
        return 'cekin_kamar';
    if (has($q, ['belum cekin', 'belum checkin', 'belum check-in', 'belum masuk', 'belum hadir']))
        return 'cekin_belum';
    if (has($q, ['sudah cekin', 'sedang menginap', 'sedang inap', 'masih inap']))
        return 'cekin_aktif';
    if (has($q, ['sudah cekout', 'sudah checkout', 'sudah check-out', 'sudah keluar', 'sudah pulang']))
        return 'cekin_selesai';
    if (has($q, [
        'cekin',
        'cekout',
        'peserta',
        'pengajar',
        'menginap',
        'penginapan',
        'kamar',
        'gedung',
        'checkin',
        'inap'
    ]))
        return 'cekin_rekap';

    // ── Agenda ───────────────────────────────
    if (has($q, [
        'agenda mendatang',
        'jadwal mendatang',
        'akan datang',
        'minggu depan',
        'bulan depan',
        'berikutnya'
    ]))
        return 'agenda_mendatang';
    if (has($q, ['kategori menpim', 'kategori teknis', 'kategori kerjasama', 'kategori pustrajak']))
        return 'agenda_kategori';
    if (has($q, ['agenda terbanyak peserta', 'peserta terbanyak', 'agenda paling besar']))
        return 'agenda_terbesar';
    if (has($q, [
        'agenda',
        'kegiatan',
        'jadwal',
        'pelatihan',
        'diklat',
        'sertifikasi',
        'bimtek',
        'konsinyering'
    ]))
        return 'agenda';

    // ── Kerusakan ────────────────────────────
    if (has($q, ['teknisi paling aktif', 'teknisi terbaik', 'ranking teknisi']))
        return 'rusak_top_teknisi';
    if (has($q, ['ruangan paling sering', 'lokasi paling sering', 'area sering rusak']))
        return 'rusak_top_ruangan';
    if (has($q, ['jenis kerusakan', 'tipe kerusakan', 'kategori kerusakan']))
        return 'rusak_top_jenis';
    if (has($q, ['belum selesai', 'masih pending', 'kerusakan pending', 'belum ditangani']))
        return 'rusak_pending';
    if (has($q, ['kerusakan', 'rusak', 'masalah', 'laporan kerusakan', 'teknisi', 'prioritas']))
        return 'kerusakan';

    // ── Checklist ────────────────────────────
    // [NEW v3] siapa user/pengguna yang sering isi checklist
    if (has($q, [
        'user checklist',
        'pengguna checklist',
        'siapa yang isi checklist',
        'siapa yang mengisi',
        'user paling aktif checklist',
        'pengguna aktif checklist',
        'akun checklist',
        'login checklist'
    ]))
        return 'checklist_user';
    if (has($q, ['checklist area', 'checklist per area', 'area kerja']))
        return 'checklist_area';
    if (has($q, ['checklist regu', 'regu a', 'regu b', 'per regu']))
        return 'checklist_regu';
    if (has($q, ['petugas paling aktif', 'petugas rajin', 'ranking petugas']))
        return 'checklist_top';
    if (has($q, [
        'checklist',
        'form checklist',
        'petugas',
        'ob',
        'security',
        'regu',
        'plotingjaga'
    ]))
        return 'checklist';

    // ── Surat ────────────────────────────────
    if (has($q, ['surat masuk']))   return 'surat_masuk';
    if (has($q, ['surat keluar']))  return 'surat_keluar';
    if (has($q, ['pengirim terbanyak', 'surat dari', 'asal surat']))
        return 'surat_pengirim';
    if (has($q, ['surat', 'arsip', 'persuratan']))
        return 'surat';

    // ── Gudang ───────────────────────────────
    if (has($q, ['barang masuk']))  return 'gudang_masuk';
    if (has($q, ['barang keluar'])) return 'gudang_keluar';
    if (has($q, ['barang terbanyak', 'stok terbanyak', 'item terbanyak']))
        return 'gudang_top';
    if (has($q, ['gudang', 'stok', 'inventaris', 'barang']))
        return 'gudang';

    // ── Tamu ─────────────────────────────────
    if (has($q, ['tamu paling banyak', 'instansi tamu', 'asal tamu']))
        return 'tamu_instansi';
    if (has($q, ['tamu', 'buku tamu', 'pengunjung', 'pelayanan']))
        return 'tamu';

    // ── Kendaraan ────────────────────────────
    if (has($q, ['kendaraan', 'mobil', 'plat', 'parkir']))
        return 'kendaraan';

    // ── Pengguna ─────────────────────────────
    if (has($q, ['daftar teknisi', 'list teknisi', 'teknisi siapa']))  return 'user_teknisi';
    if (has($q, ['daftar driver', 'list driver', 'driver siapa']))     return 'user_driver';
    if (has($q, ['daftar ob', 'list ob', 'ob siapa']))                 return 'user_ob';
    if (has($q, ['daftar security', 'list security', 'security siapa'])) return 'user_security';
    if (has($q, ['pengguna', 'user', 'akun', 'staf', 'pegawai']))
        return 'pengguna';

    // ── Ringkasan ────────────────────────────
    if (has($q, [
        'rekap',
        'ringkasan',
        'operasional',
        'laporan harian',
        'summary',
        'dashboard'
    ]))
        return 'ringkasan';

    return 'fallback';
}

// ══════════════════════════════════════════════
//  RESOLVE AGENDA
// ══════════════════════════════════════════════
function agendaIdFromQ(string $q): ?int
{
    if (preg_match('/agenda\s*(?:id)?\s*(\d+)/i', $q, $m)) return (int)$m[1];
    if (preg_match('/\bid\s*(\d+)\b/i', $q, $m))            return (int)$m[1];
    return null;
}

function resolveAgenda(mysqli $c, string $q): array
{
    $id = agendaIdFromQ($q);
    if ($id !== null) {
        $ag = one($c, "SELECT * FROM agenda_kegiatan WHERE id=? LIMIT 1", 'i', [$id]);
        if (!empty($ag)) return ['agenda_id' => (int)$ag['id'], 'agenda' => $ag];
    }
    $t  = today();
    $ag = one($c, "SELECT * FROM agenda_kegiatan WHERE start_date<=? AND end_date>=? ORDER BY peserta DESC,id ASC LIMIT 1", 'ss', [$t, $t]);
    if (!empty($ag)) return ['agenda_id' => (int)$ag['id'], 'agenda' => $ag];
    $ag = one($c, "SELECT * FROM agenda_kegiatan ORDER BY start_date DESC,id DESC LIMIT 1");
    if (!empty($ag)) return ['agenda_id' => (int)$ag['id'], 'agenda' => $ag];
    return ['agenda_id' => null, 'agenda' => []];
}

// ══════════════════════════════════════════════
//  JAWABAN — AGENDA
// ══════════════════════════════════════════════
function ansAgenda(mysqli $c, array $r): string
{
    $list = all($c, "SELECT id,judul,start_date,end_date,kategori,asrama,peserta,kelas,makan
        FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?
        ORDER BY start_date ASC LIMIT 20", 'ss', [$r['end'], $r['start']]);
    if (empty($list)) return "Tidak ada agenda aktif untuk periode {$r['label']}.";
    $tot = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p
        FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?", 'ss', [$r['end'], $r['start']]);
    $out  = "**Agenda kegiatan {$r['label']}:**\n\n";
    $out .= "📋 Total: **" . num($tot['c']) . "** kegiatan | 👥 Total peserta: **" . num($tot['p']) . "** orang\n\n";
    foreach ($list as $i => $row) {
        $durasi = (int)round((strtotime($row['end_date']) - strtotime($row['start_date'])) / 86400) + 1;
        $out .= ($i + 1) . ". **{$row['judul']}** [{$row['kategori']}]\n";
        $out .= "   📅 " . tgl($row['start_date']) . " s/d " . tgl($row['end_date']) . " ({$durasi} hari)\n";
        if ($row['peserta']) $out .= "   👥 " . num($row['peserta']) . " peserta\n";
        if ($row['asrama'])  $out .= "   🏨 Asrama: {$row['asrama']}\n";
        if ($row['kelas'])   $out .= "   🏫 Kelas: {$row['kelas']}\n";
        if ($row['makan'])   $out .= "   🍽️ Makan: {$row['makan']}\n";
        $out .= "\n";
    }
    return $out . "_Diperbarui: " . now_label() . "_";
}

function ansAgendaMendatang(mysqli $c): string
{
    $list = all($c, "SELECT id,judul,start_date,end_date,kategori,peserta,asrama
        FROM agenda_kegiatan WHERE start_date>? ORDER BY start_date ASC LIMIT 15", 's', [today()]);
    if (empty($list)) return "Tidak ada agenda mendatang yang terjadwal.";
    $out = "**Agenda kegiatan mendatang:**\n\n";
    foreach ($list as $i => $row) {
        $hari  = (int)((strtotime($row['start_date']) - time()) / 86400);
        $label = $hari <= 0 ? 'mulai hari ini' : "mulai {$hari} hari lagi";
        $out .= ($i + 1) . ". **{$row['judul']}** [{$row['kategori']}]\n";
        $out .= "   📅 " . tgl($row['start_date']) . " s/d " . tgl($row['end_date']) . " ({$label})\n";
        if ($row['peserta']) $out .= "   👥 " . num($row['peserta']) . " peserta\n";
        if ($row['asrama'])  $out .= "   🏨 {$row['asrama']}\n";
        $out .= "\n";
    }
    return $out . "_Diperbarui: " . now_label() . "_";
}

function ansAgendaKategori(mysqli $c, string $q, array $r): string
{
    $map = ['menpim' => 'Menpim', 'teknis' => 'Teknis', 'kerjasama' => 'Kerjasama', 'pustrajak' => 'Pustrajak'];
    $kat = null;
    foreach ($map as $kw => $val)
        if (mb_strpos($q, $kw) !== false) {
            $kat = $val;
            break;
        }

    if ($kat) {
        $list = all($c, "SELECT judul,start_date,end_date,peserta FROM agenda_kegiatan
            WHERE kategori=? AND start_date<=? AND end_date>=? ORDER BY start_date ASC", 'sss', [$kat, $r['end'], $r['start']]);
        $tot  = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p
            FROM agenda_kegiatan WHERE kategori=?", 's', [$kat]);
        $out  = "**Agenda kategori {$kat} {$r['label']}:**\n\n";
        $out .= "📋 Total keseluruhan: " . num($tot['c']) . " kegiatan | " . num($tot['p']) . " peserta\n\n";
        if (empty($list)) return $out . "Tidak ada agenda {$kat} aktif periode ini.";
        foreach ($list as $i => $row) {
            $out .= ($i + 1) . ". **{$row['judul']}**\n   " . tgl($row['start_date']) . " s/d " . tgl($row['end_date']);
            if ($row['peserta']) $out .= " | " . num($row['peserta']) . " peserta";
            $out .= "\n\n";
        }
        return $out . "_Diperbarui: " . now_label() . "_";
    }
    $kats = all($c, "SELECT kategori,COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p
        FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?
        GROUP BY kategori ORDER BY c DESC", 'ss', [$r['end'], $r['start']]);
    $out = "**Rekap agenda per kategori {$r['label']}:**\n\n";
    foreach ($kats as $row)
        $out .= "• **{$row['kategori']}**: " . num($row['c']) . " kegiatan | " . num($row['p']) . " peserta\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansAgendaTerbesar(mysqli $c): string
{
    $list = all($c, "SELECT judul,start_date,end_date,kategori,peserta,asrama,kelas
        FROM agenda_kegiatan WHERE peserta IS NOT NULL AND peserta>0
        ORDER BY peserta DESC LIMIT 10");
    $out = "**Agenda dengan peserta terbanyak (semua waktu):**\n\n";
    foreach ($list as $i => $row) {
        $out .= ($i + 1) . ". **{$row['judul']}** — **" . num($row['peserta']) . " peserta**\n";
        $out .= "   " . tgl($row['start_date']) . " s/d " . tgl($row['end_date']) . " | {$row['kategori']}\n\n";
    }
    return $out . "_Diperbarui: " . now_label() . "_";
}

// ══════════════════════════════════════════════
//  JAWABAN — CEKIN
// ══════════════════════════════════════════════
function ansCekinRekap(mysqli $c, string $q): string
{
    $tbl = cekinTable($c);
    if (!$tbl) return "Tabel penginapan belum ditemukan di database.";
    $ai    = resolveAgenda($c, $q);
    if (!$ai['agenda_id']) return "Tidak ada agenda yang ditemukan.";
    $aid   = (int)$ai['agenda_id'];
    $judul = $ai['agenda']['judul'] ?? ('Agenda #' . $aid);

    $s = one($c, "SELECT COUNT(*) AS total,
        SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
        SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS out_,
        SUM(CASE WHEN peran='Peserta'              THEN 1 ELSE 0 END) AS peserta,
        SUM(CASE WHEN peran='Panitia'              THEN 1 ELSE 0 END) AS panitia,
        SUM(CASE WHEN peran='Pengajar'             THEN 1 ELSE 0 END) AS pengajar,
        SUM(CASE WHEN jenis_kelamin='L'            THEN 1 ELSE 0 END) AS laki,
        SUM(CASE WHEN jenis_kelamin='P'            THEN 1 ELSE 0 END) AS perempuan
        FROM {$tbl} WHERE agenda_id=?", 'i', [$aid]);

    $gedung = all($c, "SELECT gedung,COUNT(*) AS total,
        SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
        SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS selesai
        FROM {$tbl} WHERE agenda_id=? GROUP BY gedung ORDER BY gedung ASC", 'i', [$aid]);

    $total = (int)($s['total'] ?? 0);
    $out   = "**Rekap penginapan — {$judul}**\n\n";
    $out  .= "👥 Total terdaftar : **" . num($total) . "** orang\n";
    $out  .= "⏳ Belum check-in  : **" . num($s['belum']) . "** (" . pct($s['belum'], $total) . ")\n";
    $out  .= "🏠 Sedang menginap : **" . num($s['inap']) . "** (" . pct($s['inap'], $total) . ")\n";
    $out  .= "✅ Sudah check-out : **" . num($s['out_']) . "** (" . pct($s['out_'], $total) . ")\n";
    $out  .= "─────────────────────────\n";
    $out  .= "🎓 Peserta: **" . num($s['peserta']) . "** | Panitia: **" . num($s['panitia']) . "** | Pengajar: **" . num($s['pengajar']) . "**\n";
    if ((int)($s['laki'] ?? 0) + (int)($s['perempuan'] ?? 0) > 0)
        $out .= "♂ Laki: **" . num($s['laki']) . "** | ♀ Perempuan: **" . num($s['perempuan']) . "**\n";

    if (!empty($gedung)) {
        $out .= "\n**Rekap per gedung:**\n";
        foreach ($gedung as $g) {
            $nm   = safe($g['gedung'], 'Tanpa Gedung');
            $pInap = pct($g['inap'], $g['total']);
            $out .= "🏨 **{$nm}** — total " . num($g['total']) . " | ✅menginap " . num($g['inap']) . " ({$pInap}) | ⏳belum " . num($g['belum']) . " | 🚪keluar " . num($g['selesai']) . "\n";
        }
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansCekinBelum(mysqli $c, string $q): string
{
    $tbl = cekinTable($c);
    if (!$tbl) return "Tabel penginapan tidak ditemukan.";
    $ai    = resolveAgenda($c, $q);
    $aid   = (int)$ai['agenda_id'];
    $judul = $ai['agenda']['judul'] ?? ('Agenda #' . $aid);
    $tot   = one($c, "SELECT COUNT(*) AS t FROM {$tbl} WHERE agenda_id=? AND status_inap='Belum Check-in'", 'i', [$aid]);
    $list  = all($c, "SELECT nama,instansi,peran,jenis_kelamin,gedung,lantai,kamar
        FROM {$tbl} WHERE agenda_id=? AND status_inap='Belum Check-in'
        ORDER BY gedung,lantai,kamar,nama LIMIT 100", 'i', [$aid]);
    if (empty($list)) return "✅ Semua peserta **{$judul}** sudah check-in!";
    $out  = "Peserta **belum check-in** — **{$judul}**\n";
    $out .= "Total: **" . num($tot['t']) . "** orang\n\n";
    foreach ($list as $i => $r) {
        $lok = trim(safe($r['gedung'], '') . ' Lt.' . safe($r['lantai'], '-') . ' Kamar ' . safe($r['kamar'], '-'));
        $gen = $r['jenis_kelamin'] === 'L' ? '♂' : ($r['jenis_kelamin'] === 'P' ? '♀' : '');
        $ins = $r['instansi'] ? " | {$r['instansi']}" : '';
        $out .= ($i + 1) . ". **{$r['nama']}** {$gen} (" . safe($r['peran']) . ")" . $ins . "\n   📍 {$lok}\n";
    }
    if ((int)$tot['t'] > 100) $out .= "\n_...dan " . ((int)$tot['t'] - 100) . " orang lainnya._\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansCekinAktif(mysqli $c, string $q): string
{
    $tbl = cekinTable($c);
    if (!$tbl) return "Tabel penginapan tidak ditemukan.";
    $ai    = resolveAgenda($c, $q);
    $aid   = (int)$ai['agenda_id'];
    $judul = $ai['agenda']['judul'] ?? ('Agenda #' . $aid);
    $tot   = one($c, "SELECT COUNT(*) AS t FROM {$tbl} WHERE agenda_id=? AND status_inap='Check-in'", 'i', [$aid]);
    $list  = all($c, "SELECT nama,instansi,peran,jenis_kelamin,gedung,lantai,kamar,checkin_date,checkin_time
        FROM {$tbl} WHERE agenda_id=? AND status_inap='Check-in'
        ORDER BY gedung,lantai,kamar,nama LIMIT 60", 'i', [$aid]);
    if (empty($list)) return "Belum ada peserta yang sedang menginap untuk **{$judul}**.";
    $out  = "Peserta **sedang menginap** — **{$judul}**\n";
    $out .= "Total: **" . num($tot['t']) . "** orang\n\n";
    foreach ($list as $i => $r) {
        $lok = trim(safe($r['gedung'], '') . ' Lt.' . safe($r['lantai'], '-') . ' Kamar ' . safe($r['kamar'], '-'));
        $ci  = ($r['checkin_date'] ? tgl($r['checkin_date']) : '') . ($r['checkin_time'] ? ' ' . substr($r['checkin_time'], 0, 5) : '');
        $gen = $r['jenis_kelamin'] === 'L' ? '♂' : ($r['jenis_kelamin'] === 'P' ? '♀' : '');
        $out .= ($i + 1) . ". **{$r['nama']}** {$gen} (" . safe($r['peran']) . ")\n   📍 {$lok} | Check-in: " . trim($ci) . "\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansCekinSelesai(mysqli $c, string $q): string
{
    $tbl = cekinTable($c);
    if (!$tbl) return "Tabel penginapan tidak ditemukan.";
    $ai    = resolveAgenda($c, $q);
    $aid   = (int)$ai['agenda_id'];
    $judul = $ai['agenda']['judul'] ?? ('Agenda #' . $aid);
    $tot   = one($c, "SELECT COUNT(*) AS t FROM {$tbl} WHERE agenda_id=? AND status_inap='Check-out'", 'i', [$aid]);
    $list  = all($c, "SELECT nama,instansi,peran,gedung,lantai,kamar,checkin_date,checkin_time,checkout_date,checkout_time
        FROM {$tbl} WHERE agenda_id=? AND status_inap='Check-out'
        ORDER BY checkout_date DESC,checkout_time DESC LIMIT 50", 'i', [$aid]);
    if (empty($list)) return "Belum ada peserta yang check-out dari **{$judul}**.";
    $out  = "Peserta **sudah check-out** — **{$judul}**\n";
    $out .= "Total: **" . num($tot['t']) . "** orang\n\n";
    foreach ($list as $i => $r) {
        $lok = trim(safe($r['gedung'], '') . ' Lt.' . safe($r['lantai'], '-') . ' Kamar ' . safe($r['kamar'], '-'));
        $ci  = ($r['checkin_date']  ? tgl($r['checkin_date'])  : '') . ($r['checkin_time']  ? ' ' . substr($r['checkin_time'], 0, 5)  : '');
        $co  = ($r['checkout_date'] ? tgl($r['checkout_date']) : '') . ($r['checkout_time'] ? ' ' . substr($r['checkout_time'], 0, 5) : '');
        // Hitung durasi menginap
        $dur = '';
        if ($r['checkin_date'] && $r['checkout_date']) {
            $malam = (int)round((strtotime($r['checkout_date']) - strtotime($r['checkin_date'])) / 86400);
            $dur   = " ({$malam} malam)";
        }
        $out .= ($i + 1) . ". **{$r['nama']}** — " . safe($r['instansi'], '-') . "\n";
        $out .= "   📍 {$lok} | Check-in: " . trim($ci) . " | Keluar: " . trim($co) . $dur . "\n";
    }
    if ((int)$tot['t'] > 50) $out .= "\n_...dan " . ((int)$tot['t'] - 50) . " orang lainnya._\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

/**
 * [FIX v3] ansCekinCari
 *  - Regex lebih kuat, strip banyak kata pengantar
 *  - Cari di SEMUA agenda (tidak bergantung resolveAgenda)
 *    sehingga "yang keluar" / "CPNS" / dsb tidak bias ke satu agenda
 *  - Tampilkan nama kegiatan peserta agar lebih informatif
 *  - Tambah info durasi inap & nomor bed
 */
function ansCekinCari(mysqli $c, string $q): string
{
    $tbl = cekinTable($c);
    if (!$tbl) return "Tabel penginapan tidak ditemukan.";

    // ── Ekstrak keyword pencarian ────────────
    // Hapus semua kata pengantar yang umum sebelum kata kunci
    $kw = $q;

    // Potong "cari peserta yang ...", "siapa peserta ...", dll
    if (preg_match('/(?:cari\s+(?:peserta|nama|orang)?\s*(?:yang\s+)?|siapa\s+(?:peserta\s+)?|dimana\s+(?:peserta\s+)?)(.+)/iu', $kw, $m))
        $kw = trim($m[1]);

    // Bersihkan kata-kata non-kunci di awal
    $stopWords = [
        'yang',
        'peserta',
        'nama',
        'instansi',
        'dari',
        'bernama',
        'keluar',
        'masuk',
        'menginap',
        'inap',
        'sudah',
        'belum',
        'sedang',
        'dengan',
        'untuk',
        'adalah',
        'yaitu'
    ];
    foreach ($stopWords as $sw)
        $kw = preg_replace('/^' . $sw . '\s+/iu', '', trim($kw));

    $kw = trim($kw);

    if (mb_strlen($kw) < 2)
        return "Sebutkan nama atau instansi yang ingin dicari (minimal 2 karakter).";

    $like = '%' . $kw . '%';

    // ── Cari di SEMUA agenda (tidak filter agenda_id) ──
    $rows = all(
        $c,
        "SELECT pp.nama, pp.instansi, pp.peran, pp.jenis_kelamin,
                pp.gedung, pp.lantai, pp.kamar, pp.bed,
                pp.status_inap,
                pp.checkin_date, pp.checkin_time,
                pp.checkout_date, pp.checkout_time,
                ak.judul AS nama_kegiatan,
                ak.start_date, ak.end_date
         FROM {$tbl} pp
         LEFT JOIN agenda_kegiatan ak ON ak.id = pp.agenda_id
         WHERE (pp.nama LIKE ? OR pp.instansi LIKE ?)
         ORDER BY ak.start_date DESC, pp.nama ASC
         LIMIT 50",
        'ss',
        [$like, $like]
    );

    if (empty($rows))
        return "Tidak ditemukan peserta dengan kata kunci \"**{$kw}**\" di seluruh data penginapan.";

    $out  = "Hasil pencarian \"**{$kw}**\":\n";
    $out .= "Ditemukan **" . count($rows) . "** data\n\n";

    foreach ($rows as $i => $r) {
        $lok    = trim(safe($r['gedung'], '') . ' Lt.' . safe($r['lantai'], '-') . ' Kamar ' . safe($r['kamar'], '-'));
        if (!empty($r['bed'])) $lok .= ' Bed ' . safe($r['bed']);
        $gen    = $r['jenis_kelamin'] === 'L' ? '♂' : ($r['jenis_kelamin'] === 'P' ? '♀' : '');
        $status = safe($r['status_inap']);

        // Waktu
        $ci  = '';
        $co  = '';
        $dur = '';
        if ($r['checkin_date'])
            $ci = tgl($r['checkin_date']) . ($r['checkin_time'] ? ' ' . substr($r['checkin_time'], 0, 5) : '');
        if ($r['checkout_date']) {
            $co = tgl($r['checkout_date']) . ($r['checkout_time'] ? ' ' . substr($r['checkout_time'], 0, 5) : '');
            if ($r['checkin_date']) {
                $malam = (int)round((strtotime($r['checkout_date']) - strtotime($r['checkin_date'])) / 86400);
                $dur   = " ({$malam} malam)";
            }
        }

        $out .= ($i + 1) . ". **{$r['nama']}** {$gen} | " . safe($r['peran']) . "\n";
        $out .= "   🏛️ Instansi : " . safe($r['instansi']) . "\n";
        $out .= "   📋 Kegiatan : " . safe($r['nama_kegiatan']) . "\n";
        $out .= "   📅 Jadwal   : " . tgl($r['start_date']) . " s/d " . tgl($r['end_date']) . "\n";
        $out .= "   📍 Kamar    : {$lok}\n";
        $out .= "   🔖 Status   : {$status}";
        if ($ci) $out .= " | Masuk: {$ci}";
        if ($co) $out .= " | Keluar: {$co}{$dur}";
        $out .= "\n\n";
    }

    if (count($rows) === 50) $out .= "_...ditampilkan 50 teratas. Perjelas keyword untuk hasil lebih spesifik._\n";
    return $out . "_Diperbarui: " . now_label() . "_";
}

function ansCekinInstansi(mysqli $c, string $q): string
{
    $tbl = cekinTable($c);
    if (!$tbl) return "Tabel penginapan tidak ditemukan.";
    $ai    = resolveAgenda($c, $q);
    $aid   = (int)$ai['agenda_id'];
    $judul = $ai['agenda']['judul'] ?? ('Agenda #' . $aid);
    $list  = all($c, "SELECT instansi,COUNT(*) AS total,
        SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
        SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS out_
        FROM {$tbl} WHERE agenda_id=? AND instansi IS NOT NULL AND instansi<>''
        GROUP BY instansi ORDER BY total DESC LIMIT 20", 'i', [$aid]);
    if (empty($list)) return "Tidak ada data instansi untuk kegiatan **{$judul}**.";
    $out = "**Instansi peserta terbanyak — {$judul}:**\n\n";
    foreach ($list as $i => $r) {
        $out .= ($i + 1) . ". **{$r['instansi']}** — **" . num($r['total']) . "** orang";
        $out .= " (menginap: " . num($r['inap']) . " | belum: " . num($r['belum']) . " | keluar: " . num($r['out_']) . ")\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansCekinGender(mysqli $c, string $q): string
{
    $tbl = cekinTable($c);
    if (!$tbl) return "Tabel penginapan tidak ditemukan.";
    $ai    = resolveAgenda($c, $q);
    $aid   = (int)$ai['agenda_id'];
    $judul = $ai['agenda']['judul'] ?? ('Agenda #' . $aid);
    $s     = one($c, "SELECT COUNT(*) AS total,
        SUM(CASE WHEN jenis_kelamin='L' THEN 1 ELSE 0 END) AS laki,
        SUM(CASE WHEN jenis_kelamin='P' THEN 1 ELSE 0 END) AS perempuan
        FROM {$tbl} WHERE agenda_id=?", 'i', [$aid]);
    $t     = (int)($s['total'] ?? 0);
    $out   = "**Komposisi gender peserta — {$judul}:**\n\n";
    $out  .= "👥 Total     : **" . num($t) . "** orang\n";
    $out  .= "♂ Laki-laki  : **" . num($s['laki']) . "** (" . pct($s['laki'], $t) . ")\n";
    $out  .= "♀ Perempuan  : **" . num($s['perempuan']) . "** (" . pct($s['perempuan'], $t) . ")\n";
    // Breakdown gender per gedung
    $gedung = all($c, "SELECT gedung,
        SUM(CASE WHEN jenis_kelamin='L' THEN 1 ELSE 0 END) AS laki,
        SUM(CASE WHEN jenis_kelamin='P' THEN 1 ELSE 0 END) AS perempuan
        FROM {$tbl} WHERE agenda_id=? GROUP BY gedung ORDER BY gedung", 'i', [$aid]);
    if (!empty($gedung)) {
        $out .= "\n**Per gedung:**\n";
        foreach ($gedung as $g)
            $out .= "🏨 **" . safe($g['gedung'], '?') . "** → ♂ " . num($g['laki']) . " | ♀ " . num($g['perempuan']) . "\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansCekinKamar(mysqli $c, string $q): string
{
    $tbl = cekinTable($c);
    if (!$tbl) return "Tabel penginapan tidak ditemukan.";
    $ai    = resolveAgenda($c, $q);
    $aid   = (int)$ai['agenda_id'];
    $judul = $ai['agenda']['judul'] ?? ('Agenda #' . $aid);
    $list  = all($c, "SELECT gedung,lantai,kamar,COUNT(*) AS isi,
        SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
        SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status_inap='Check-out'      THEN 1 ELSE 0 END) AS out_
        FROM {$tbl} WHERE agenda_id=? GROUP BY gedung,lantai,kamar ORDER BY gedung,lantai,kamar", 'i', [$aid]);
    if (empty($list)) return "Tidak ada data kamar untuk kegiatan **{$judul}**.";
    $out = "**Detail per kamar — {$judul}:**\n\n";
    $lastGedung = '';
    foreach ($list as $r) {
        $g = safe($r['gedung'], '?');
        if ($g !== $lastGedung) {
            $out .= "\n🏨 **Gedung {$g}**\n";
            $lastGedung = $g;
        }
        $out .= "  Lt." . safe($r['lantai']) . " Kamar " . safe($r['kamar'])
            . " — " . num($r['isi']) . " org | ✅menginap " . num($r['inap'])
            . " | ⏳belum " . num($r['belum']) . " | 🚪keluar " . num($r['out_']) . "\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

// ══════════════════════════════════════════════
//  JAWABAN — KERUSAKAN
// ══════════════════════════════════════════════
function ansKerusakan(mysqli $c, array $r, bool $detail = false): string
{
    $tbl = rusakTable($c);
    if (!$tbl) return "Tabel kerusakan tidak ditemukan di database.";

    $s = one($c, "SELECT COUNT(*) AS total,
        SUM(CASE WHEN status='dilaporkan' THEN 1 ELSE 0 END) AS dilaporkan,
        SUM(CASE WHEN status IN ('diverifikasi','diterima_teknisi',
            'dalam_perbaikan','menunggu_part','diproses') THEN 1 ELSE 0 END) AS proses,
        SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END) AS selesai,
        SUM(CASE WHEN status='ditolak' THEN 1 ELSE 0 END) AS ditolak,
        AVG(CASE WHEN status='selesai' AND selesai_at IS NOT NULL
            THEN TIMESTAMPDIFF(HOUR,created_at,selesai_at) END) AS avg_jam
        FROM {$tbl} WHERE DATE(created_at) BETWEEN ? AND ?", 'ss', [$r['start'], $r['end']]);

    $t = (int)($s['total'] ?? 0);
    if (!$t) return "Tidak ada laporan kerusakan untuk periode {$r['label']}.";

    $out  = "**Laporan kerusakan {$r['label']}:**\n\n";
    $out .= "📊 Total      : **" . num($t) . "**\n";
    $out .= "🔴 Dilaporkan : **" . num($s['dilaporkan']) . "** (" . pct($s['dilaporkan'], $t) . ")\n";
    $out .= "🟡 Diproses   : **" . num($s['proses']) . "** (" . pct($s['proses'], $t) . ")\n";
    $out .= "🟢 Selesai    : **" . num($s['selesai']) . "** (" . pct($s['selesai'], $t) . ")\n";
    if ((int)($s['ditolak'] ?? 0) > 0)
        $out .= "⛔ Ditolak    : **" . num($s['ditolak']) . "**\n";
    if (!empty($s['avg_jam']))
        $out .= "⏱️ Rata-rata selesai: **" . round($s['avg_jam']) . " jam**\n";

    // Breakdown prioritas
    $pri = all($c, "SELECT prioritas AS label,COUNT(*) AS c
        FROM {$tbl} WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY prioritas ORDER BY FIELD(prioritas,'tinggi','sedang','rendah')", 'ss', [$r['start'], $r['end']]);
    if (!empty($pri)) {
        $out .= "\n**Per prioritas:**\n";
        $icon = ['tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'];
        foreach ($pri as $p) {
            $ic = $icon[strtolower($p['label'] ?? '')] ?? '⚪';
            $out .= "{$ic} " . safe($p['label'], 'tanpa prioritas') . ": **" . num($p['c']) . "**\n";
        }
    }

    // Teknisi aktif (selalu tampil jika ada)
    $tek = all($c, "SELECT teknisi_nama AS label,COUNT(*) AS total,
        SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END) AS selesai
        FROM {$tbl} WHERE teknisi_nama IS NOT NULL AND teknisi_nama<>''
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY teknisi_nama ORDER BY total DESC LIMIT 5", 'ss', [$r['start'], $r['end']]);
    if (!empty($tek)) {
        $out .= "\n**Teknisi aktif:**\n";
        foreach ($tek as $i => $row) {
            $rate = pct($row['selesai'], $row['total']);
            $out .= ($i + 1) . ". **" . safe($row['label']) . "** — " . num($row['total']) . " laporan | selesai: " . num($row['selesai']) . " ({$rate})\n";
        }
    }

    // Laporan terbaru
    $list = all($c, "SELECT id,pelapor_nama,deskripsi,status,prioritas,teknisi_nama,created_at
        FROM {$tbl} WHERE DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
    if (!empty($list)) {
        $out .= "\n**Laporan terbaru:**\n";
        foreach ($list as $row) {
            $desk = mb_substr(trim((string)$row['deskripsi']), 0, 80);
            $tek  = safe($row['teknisi_nama'], 'Belum ditugaskan');
            $tgl_ = $row['created_at'] ? date('d/m H:i', strtotime($row['created_at'])) : '-';
            $ico  = ['dilaporkan' => '🔴', 'selesai' => '🟢', 'ditolak' => '⛔'][$row['status']] ?? '🟡';
            $out .= "{$ico} **#{$row['id']}** [{$tgl_}] " . safe($row['prioritas'], '-') . " | {$row['pelapor_nama']}\n";
            $out .= "   💬 {$desk}\n";
            $out .= "   🔧 {$tek}\n";
        }
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansRusakPending(mysqli $c): string
{
    $tbl = rusakTable($c);
    if (!$tbl) return "Tabel kerusakan tidak ditemukan.";
    $tot  = one($c, "SELECT COUNT(*) AS t FROM {$tbl} WHERE status NOT IN ('selesai','ditolak')");
    $list = all($c, "SELECT id,pelapor_nama,deskripsi,status,prioritas,teknisi_nama,created_at
        FROM {$tbl} WHERE status NOT IN ('selesai','ditolak')
        ORDER BY CASE prioritas WHEN 'tinggi' THEN 1 WHEN 'sedang' THEN 2 ELSE 3 END,
                 created_at ASC LIMIT 25");
    if (empty($list)) return "✅ Tidak ada kerusakan yang masih pending. Semua sudah tertangani!";

    // Hitung berapa hari sudah pending
    $out = "**Kerusakan belum selesai** (" . num($tot['t']) . " laporan):\n\n";
    foreach ($list as $i => $r) {
        $desk  = mb_substr(trim((string)$r['deskripsi']), 0, 80);
        $tek   = safe($r['teknisi_nama'], 'Belum ditugaskan');
        $tgl_  = $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '-';
        $hari  = $r['created_at'] ? (int)round((time() - strtotime($r['created_at'])) / 86400) : 0;
        $umur  = $hari > 0 ? " | ⌛{$hari} hari lalu" : '';
        $icon  = ['tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'][$r['prioritas'] ?? ''] ?? '⚪';
        $out  .= ($i + 1) . ". {$icon} **#{$r['id']}** | " . safe($r['prioritas'], '-') . " | {$tgl_}{$umur}\n";
        $out  .= "   Pelapor : {$r['pelapor_nama']}\n";
        $out  .= "   Masalah : {$desk}\n";
        $out  .= "   Teknisi : {$tek} | Status: " . safe($r['status']) . "\n\n";
    }
    return $out . "_Diperbarui: " . now_label() . "_";
}

function ansRusakTopTeknisi(mysqli $c, array $r): string
{
    $tbl = rusakTable($c);
    if (!$tbl) return "Tabel kerusakan tidak ditemukan.";
    $list = all($c, "SELECT teknisi_nama,COUNT(*) AS total,
        SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END) AS selesai,
        AVG(CASE WHEN status='selesai' AND selesai_at IS NOT NULL
            THEN TIMESTAMPDIFF(HOUR,created_at,selesai_at) END) AS avg_jam
        FROM {$tbl} WHERE teknisi_nama IS NOT NULL AND teknisi_nama<>''
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY teknisi_nama ORDER BY total DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
    if (empty($list)) return "Tidak ada data teknisi untuk periode {$r['label']}.";
    $out = "**Ranking teknisi aktif {$r['label']}:**\n\n";
    foreach ($list as $i => $row) {
        $rate = pct($row['selesai'], $row['total']);
        $avg  = !empty($row['avg_jam']) ? " | avg " . round($row['avg_jam']) . " jam" : '';
        $out .= ($i + 1) . ". **{$row['teknisi_nama']}** — **" . num($row['total']) . "** laporan\n";
        $out .= "   ✅ " . num($row['selesai']) . " selesai ({$rate}){$avg}\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansRusakTopJenis(mysqli $c, array $r): string
{
    $tbl = rusakTable($c);
    if (!$tbl) return "Tabel kerusakan tidak ditemukan.";
    if (colExists($c, $tbl, 'jenis_kerusakan')) {
        $list = all($c, "SELECT jenis_kerusakan AS label,COUNT(*) AS c
            FROM {$tbl} WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY jenis_kerusakan ORDER BY c DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
        if (!empty($list)) {
            $out = "**Jenis kerusakan terbanyak {$r['label']}:**\n\n";
            foreach ($list as $i => $row)
                $out .= ($i + 1) . ". **" . safe($row['label'], 'Lainnya') . "** — " . num($row['c']) . " laporan\n";
            return $out . "\n_Diperbarui: " . now_label() . "_";
        }
    }
    $list = all($c, "SELECT deskripsi,COUNT(*) AS c FROM {$tbl}
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY deskripsi ORDER BY c DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
    $out = "**Laporan kerusakan terbanyak {$r['label']}:**\n\n";
    foreach ($list as $i => $r2)
        $out .= ($i + 1) . ". " . mb_substr(trim((string)$r2['deskripsi']), 0, 70) . " — " . num($r2['c']) . "x\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

// ══════════════════════════════════════════════
//  JAWABAN — CHECKLIST
// ══════════════════════════════════════════════
function ansChecklist(mysqli $c, array $r): string
{
    $s = one($c, "SELECT COUNT(*) AS total,COUNT(DISTINCT nama_petugas) AS petugas,
        COUNT(DISTINCT form_type) AS form,
        COUNT(DISTINCT CASE WHEN area_kerja IS NOT NULL AND area_kerja<>'' THEN area_kerja END) AS area
        FROM checklist_forms WHERE tanggal BETWEEN ? AND ?", 'ss', [$r['start'], $r['end']]);
    if (!(int)($s['total'] ?? 0)) return "Tidak ada data checklist untuk periode {$r['label']}.";

    $perForm = all($c, "SELECT form_type,COUNT(*) AS c FROM checklist_forms
        WHERE tanggal BETWEEN ? AND ? GROUP BY form_type ORDER BY c DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
    $petugas = all($c, "SELECT nama_petugas,COUNT(*) AS c FROM checklist_forms
        WHERE tanggal BETWEEN ? AND ? GROUP BY nama_petugas ORDER BY c DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);

    $out  = "**Checklist {$r['label']}:**\n\n";
    $out .= "📋 Total form    : **" . num($s['total']) . "**\n";
    $out .= "👤 Petugas aktif : **" . num($s['petugas']) . "**\n";
    $out .= "📄 Jenis form    : **" . num($s['form']) . "**\n";
    $out .= "🗺️ Area kerja    : **" . num($s['area']) . "**\n";
    if (!empty($perForm)) {
        $out .= "\n**Per jenis form:**\n";
        foreach ($perForm as $row)
            $out .= "• " . safe($row['form_type']) . ": " . num($row['c']) . " form\n";
    }
    if (!empty($petugas)) {
        $out .= "\n**Petugas paling aktif:**\n";
        foreach ($petugas as $i => $row)
            $out .= ($i + 1) . ". " . safe($row['nama_petugas']) . " — " . num($row['c']) . " form\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansChecklistArea(mysqli $c, array $r): string
{
    $list = all($c, "SELECT area_kerja,COUNT(*) AS c,COUNT(DISTINCT nama_petugas) AS petugas
        FROM checklist_forms WHERE tanggal BETWEEN ? AND ?
        AND area_kerja IS NOT NULL AND area_kerja<>''
        GROUP BY area_kerja ORDER BY c DESC LIMIT 20", 'ss', [$r['start'], $r['end']]);
    if (empty($list)) return "Tidak ada data checklist per area untuk periode {$r['label']}.";
    $out = "**Checklist per area kerja {$r['label']}:**\n\n";
    foreach ($list as $i => $r2)
        $out .= ($i + 1) . ". **" . safe($r2['area_kerja']) . "** — " . num($r2['c']) . " form | " . num($r2['petugas']) . " petugas\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansChecklistRegu(mysqli $c, array $r): string
{
    $hasRegu = colExists($c, 'checklist_forms', 'regu');
    if (!$hasRegu) {
        $list = all($c, "SELECT form_type,COUNT(*) AS c FROM checklist_forms
            WHERE tanggal BETWEEN ? AND ? AND form_type LIKE '%regu%'
            GROUP BY form_type ORDER BY c DESC", 'ss', [$r['start'], $r['end']]);
        if (empty($list)) return "Kolom regu tidak tersedia atau tidak ada data regu untuk periode {$r['label']}.";
        $out = "**Checklist per regu {$r['label']}:**\n\n";
        foreach ($list as $row) $out .= "• " . safe($row['form_type']) . ": " . num($row['c']) . " form\n";
        return $out . "\n_Diperbarui: " . now_label() . "_";
    }
    $list = all($c, "SELECT regu,COUNT(*) AS c,COUNT(DISTINCT nama_petugas) AS petugas
        FROM checklist_forms WHERE tanggal BETWEEN ? AND ?
        GROUP BY regu ORDER BY c DESC", 'ss', [$r['start'], $r['end']]);
    $out = "**Checklist per regu {$r['label']}:**\n\n";
    foreach ($list as $row)
        $out .= "• **" . safe($row['regu'], 'Tanpa Regu') . "**: " . num($row['c']) . " form | " . num($row['petugas']) . " petugas\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansChecklistTop(mysqli $c, array $r): string
{
    $list = all($c, "SELECT nama_petugas,COUNT(*) AS c,
        COUNT(DISTINCT form_type) AS jenis,COUNT(DISTINCT area_kerja) AS area
        FROM checklist_forms WHERE tanggal BETWEEN ? AND ?
        GROUP BY nama_petugas ORDER BY c DESC LIMIT 15", 'ss', [$r['start'], $r['end']]);
    if (empty($list)) return "Tidak ada data checklist untuk periode {$r['label']}.";
    $out = "**Ranking petugas paling aktif {$r['label']}:**\n\n";
    foreach ($list as $i => $row)
        $out .= ($i + 1) . ". **" . safe($row['nama_petugas']) . "** — " . num($row['c']) . " form | " . num($row['jenis']) . " jenis | " . num($row['area']) . " area\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

/**
 * [NEW v3] ansChecklistUser
 * Menampilkan akun/user (dari tabel users) yang paling sering mengisi
 * checklist, dengan join ke tabel users jika kolom user_id tersedia.
 */
function ansChecklistUser(mysqli $c, array $r): string
{
    // Cek apakah ada kolom user_id / created_by di checklist_forms
    $colUserId    = colExists($c, 'checklist_forms', 'user_id')    ? 'user_id'    : null;
    $colCreatedBy = colExists($c, 'checklist_forms', 'created_by') ? 'created_by' : null;
    $colUser      = $colUserId ?? $colCreatedBy;

    if ($colUser && tblExists($c, 'users')) {
        // Join ke tabel users untuk dapat nama & role
        $list = all(
            $c,
            "SELECT u.nama AS nama_user, u.role,
                    COUNT(cf.id) AS total_form,
                    COUNT(DISTINCT cf.form_type) AS jenis_form,
                    COUNT(DISTINCT cf.area_kerja) AS jumlah_area,
                    MIN(cf.tanggal) AS pertama,
                    MAX(cf.tanggal) AS terakhir
             FROM checklist_forms cf
             LEFT JOIN users u ON u.id = cf.{$colUser}
             WHERE cf.tanggal BETWEEN ? AND ?
             GROUP BY cf.{$colUser}, u.nama, u.role
             ORDER BY total_form DESC
             LIMIT 20",
            'ss',
            [$r['start'], $r['end']]
        );

        if (empty($list))
            return "Tidak ada data checklist dari akun manapun untuk periode {$r['label']}.";

        $out  = "**Akun pengguna yang sering mengisi checklist {$r['label']}:**\n\n";
        foreach ($list as $i => $row) {
            $role    = $row['role'] ? " (" . safe($row['role']) . ")" : '';
            $periode = tgl($row['pertama']) . " s/d " . tgl($row['terakhir']);
            $out .= ($i + 1) . ". **" . safe($row['nama_user'], 'Tidak Diketahui') . "{$role}**\n";
            $out .= "   📋 Total form  : **" . num($row['total_form']) . "**\n";
            $out .= "   📄 Jenis form  : " . num($row['jenis_form']) . "\n";
            $out .= "   🗺️ Area terlibat: " . num($row['jumlah_area']) . "\n";
            $out .= "   📅 Aktif       : {$periode}\n\n";
        }
        return $out . "_Diperbarui: " . now_label() . "_";
    }

    // Fallback: tidak ada relasi ke users, gunakan nama_petugas saja
    $list = all(
        $c,
        "SELECT nama_petugas,
                COUNT(*) AS total_form,
                COUNT(DISTINCT form_type) AS jenis_form,
                COUNT(DISTINCT area_kerja) AS jumlah_area,
                MIN(tanggal) AS pertama,
                MAX(tanggal) AS terakhir
         FROM checklist_forms
         WHERE tanggal BETWEEN ? AND ?
         GROUP BY nama_petugas
         ORDER BY total_form DESC
         LIMIT 20",
        'ss',
        [$r['start'], $r['end']]
    );

    if (empty($list))
        return "Tidak ada data checklist untuk periode {$r['label']}.";

    $out  = "**Petugas/user yang sering mengisi checklist {$r['label']}:**\n\n";
    $out .= "_ℹ️ Kolom user_id tidak tersedia, data berdasarkan nama_petugas._\n\n";
    foreach ($list as $i => $row) {
        $periode = tgl($row['pertama']) . " s/d " . tgl($row['terakhir']);
        $out .= ($i + 1) . ". **" . safe($row['nama_petugas']) . "**\n";
        $out .= "   📋 Total form  : **" . num($row['total_form']) . "**\n";
        $out .= "   📄 Jenis form  : " . num($row['jenis_form']) . "\n";
        $out .= "   🗺️ Area terlibat: " . num($row['jumlah_area']) . "\n";
        $out .= "   📅 Aktif       : {$periode}\n\n";
    }
    return $out . "_Diperbarui: " . now_label() . "_";
}

// ══════════════════════════════════════════════
//  JAWABAN — SURAT
// ══════════════════════════════════════════════
function ansSurat(mysqli $c, array $r, string $mode = 'semua'): string
{
    $s = one($c, "SELECT SUM(CASE WHEN jenis='masuk' THEN 1 ELSE 0 END) AS masuk,
        SUM(CASE WHEN jenis='keluar' THEN 1 ELSE 0 END) AS keluar,COUNT(*) AS total
        FROM arsip_surat WHERE tanggal_surat BETWEEN ? AND ?", 'ss', [$r['start'], $r['end']]);
    if (!(int)($s['total'] ?? 0)) return "Tidak ada arsip surat untuk periode {$r['label']}.";
    $extra = $mode === 'masuk' ? " AND jenis='masuk'" : ($mode === 'keluar' ? " AND jenis='keluar'" : '');
    $list  = all($c, "SELECT nomor_surat,perihal,pengirim,jenis,tanggal_surat,keterangan
        FROM arsip_surat WHERE tanggal_surat BETWEEN ? AND ?{$extra}
        ORDER BY tanggal_surat DESC,id DESC LIMIT 15", 'ss', [$r['start'], $r['end']]);
    $lb    = $mode === 'masuk' ? ' masuk' : ($mode === 'keluar' ? ' keluar' : '');
    $out   = "**Arsip surat{$lb} {$r['label']}:**\n\n";
    $out  .= "📥 Masuk: **" . num($s['masuk']) . "** | 📤 Keluar: **" . num($s['keluar']) . "**\n\n";
    foreach ($list as $row) {
        $ic  = $row['jenis'] === 'masuk' ? '📥' : '📤';
        $out .= "{$ic} **[" . safe($row['nomor_surat']) . "]** — " . tgl($row['tanggal_surat']) . "\n";
        $out .= "   📝 " . safe($row['perihal']) . "\n";
        $out .= "   👤 Dari/Ke: " . safe($row['pengirim']) . "\n";
        if (!empty($row['keterangan'])) $out .= "   ℹ️ " . safe($row['keterangan']) . "\n";
        $out .= "\n";
    }
    return $out . "_Diperbarui: " . now_label() . "_";
}

function ansSuratPengirim(mysqli $c, array $r): string
{
    $list = all($c, "SELECT pengirim,COUNT(*) AS c FROM arsip_surat
        WHERE tanggal_surat BETWEEN ? AND ?
        GROUP BY pengirim ORDER BY c DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
    if (empty($list)) return "Tidak ada data surat untuk periode {$r['label']}.";
    $out = "**Pengirim surat terbanyak {$r['label']}:**\n\n";
    foreach ($list as $i => $row)
        $out .= ($i + 1) . ". **" . safe($row['pengirim']) . "** — " . num($row['c']) . " surat\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

// ══════════════════════════════════════════════
//  JAWABAN — GUDANG
// ══════════════════════════════════════════════
function ansGudang(mysqli $c, array $r): string
{
    $m = one($c, "SELECT COUNT(DISTINCT bm.id) AS trx,COALESCE(SUM(bmd.qty),0) AS qty
        FROM barang_masuk bm LEFT JOIN barang_masuk_detail bmd ON bmd.barang_masuk_id=bm.id
        WHERE bm.tanggal BETWEEN ? AND ?", 'ss', [$r['start'], $r['end']]);
    $k = one($c, "SELECT COUNT(DISTINCT bk.id) AS trx,COALESCE(SUM(bkd.qty),0) AS qty
        FROM barang_keluar bk LEFT JOIN barang_keluar_detail bkd ON bkd.barang_keluar_id=bk.id
        WHERE bk.tanggal BETWEEN ? AND ?", 'ss', [$r['start'], $r['end']]);
    $out  = "**Rekap gudang {$r['label']}:**\n\n";
    $out .= "📦 Barang masuk  : **" . num($m['trx']) . "** transaksi | **" . num($m['qty']) . "** item\n";
    $out .= "📤 Barang keluar : **" . num($k['trx']) . "** transaksi | **" . num($k['qty']) . "** item\n";
    $out .= "📊 Selisih stok  : **" . num($m['qty'] - $k['qty']) . "** item\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansGudangTop(mysqli $c, array $r): string
{
    $topM = all($c, "SELECT bmd.nama_barang,SUM(bmd.qty) AS qty
        FROM barang_masuk_detail bmd INNER JOIN barang_masuk bm ON bm.id=bmd.barang_masuk_id
        WHERE bm.tanggal BETWEEN ? AND ? GROUP BY bmd.nama_barang ORDER BY qty DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
    $topK = all($c, "SELECT bkd.nama_barang,SUM(bkd.qty) AS qty
        FROM barang_keluar_detail bkd INNER JOIN barang_keluar bk ON bk.id=bkd.barang_keluar_id
        WHERE bk.tanggal BETWEEN ? AND ? GROUP BY bkd.nama_barang ORDER BY qty DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
    $out = "**Barang terbanyak {$r['label']}:**\n\n";
    if (!empty($topM)) {
        $out .= "📦 **Barang masuk terbanyak:**\n";
        foreach ($topM as $i => $row) $out .= ($i + 1) . ". " . safe($row['nama_barang']) . " — **" . num($row['qty']) . "** item\n";
    }
    if (!empty($topK)) {
        $out .= "\n📤 **Barang keluar terbanyak:**\n";
        foreach ($topK as $i => $row) $out .= ($i + 1) . ". " . safe($row['nama_barang']) . " — **" . num($row['qty']) . "** item\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

// ══════════════════════════════════════════════
//  JAWABAN — TAMU
// ══════════════════════════════════════════════
function ansTamu(mysqli $c, array $r): string
{
    $s = one($c, "SELECT COUNT(*) AS total,
        SUM(CASE WHEN jenis_layanan='pelayanan_umum'      THEN 1 ELSE 0 END) AS umum,
        SUM(CASE WHEN jenis_layanan='pelayanan_informasi' THEN 1 ELSE 0 END) AS info,
        SUM(CASE WHEN jenis_layanan='pelayanan_pengaduan' THEN 1 ELSE 0 END) AS pengaduan
        FROM buku_tamu WHERE DATE(created_at) BETWEEN ? AND ?", 'ss', [$r['start'], $r['end']]);
    if (!(int)($s['total'] ?? 0)) return "Tidak ada tamu untuk periode {$r['label']}.";
    $list = all($c, "SELECT nama,asal_instansi,keperluan,jenis_layanan,created_at
        FROM buku_tamu WHERE DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
    $out  = "**Buku tamu {$r['label']}:**\n\n";
    $out .= "👥 Total: **" . num($s['total']) . "** | Umum: **" . num($s['umum']) . "** | Info: **" . num($s['info']) . "** | Pengaduan: **" . num($s['pengaduan']) . "**\n\n";
    foreach ($list as $row) {
        $asal = !empty($row['asal_instansi']) ? ' (' . $row['asal_instansi'] . ')' : '';
        $jam  = $row['created_at'] ? date('H:i', strtotime($row['created_at'])) : '';
        $out .= "• **" . safe($row['nama']) . "{$asal}** — " . safe($row['keperluan']) . " [{$jam}]\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansTamuInstansi(mysqli $c, array $r): string
{
    $col = colExists($c, 'buku_tamu', 'asal_instansi') ? 'asal_instansi'
        : (colExists($c, 'buku_tamu', 'asal') ? 'asal' : null);
    if (!$col) return "Kolom instansi tamu tidak tersedia.";
    $list = all($c, "SELECT {$col} AS inst,COUNT(*) AS c FROM buku_tamu
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND {$col} IS NOT NULL AND {$col}<>''
        GROUP BY {$col} ORDER BY c DESC LIMIT 10", 'ss', [$r['start'], $r['end']]);
    if (empty($list)) return "Tidak ada data instansi tamu untuk periode {$r['label']}.";
    $out = "**Instansi tamu terbanyak {$r['label']}:**\n\n";
    foreach ($list as $i => $row)
        $out .= ($i + 1) . ". **" . safe($row['inst']) . "** — " . num($row['c']) . " kunjungan\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

// ══════════════════════════════════════════════
//  JAWABAN — KENDARAAN
// ══════════════════════════════════════════════
function ansKendaraan(mysqli $c): string
{
    if (!tblExists($c, 'kendaraan_log')) return "Data kendaraan belum tersedia.";
    $t   = today();
    $s   = one($c, "SELECT COUNT(*) AS total,
        SUM(CASE WHEN status='masuk'  THEN 1 ELSE 0 END) AS masuk,
        SUM(CASE WHEN status='keluar' THEN 1 ELSE 0 END) AS keluar
        FROM kendaraan_log WHERE DATE(waktu_masuk)=?", 's', [$t]);
    $list = all($c, "SELECT plat_nomor,instansi_tamu,tujuan,waktu_masuk,waktu_keluar,status
        FROM kendaraan_log ORDER BY waktu_masuk DESC LIMIT 15");
    $out  = "**Log kendaraan hari ini:**\n\n";
    $out .= "🚗 Masuk: **" . num($s['masuk']) . "** | Keluar: **" . num($s['keluar']) . "**\n\n";
    foreach ($list as $r) {
        $ic = $r['status'] === 'masuk' ? '🟢' : '🔴';
        $wm = $r['waktu_masuk'] ? date('d/m H:i', strtotime($r['waktu_masuk'])) : '-';
        $out .= "{$ic} **" . safe($r['plat_nomor']) . "** — " . safe($r['tujuan']) . "\n   " . safe($r['instansi_tamu']) . " | {$wm}\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

// ══════════════════════════════════════════════
//  JAWABAN — PENGGUNA
// ══════════════════════════════════════════════
function ansPenggunaByRole(mysqli $c, string $role): string
{
    if (!tblExists($c, 'users')) return "Tabel users tidak ditemukan.";
    $list = all($c, "SELECT nama,role,created_at FROM users WHERE role=? ORDER BY nama ASC", 's', [$role]);
    if (empty($list)) return "Tidak ada pengguna dengan role **{$role}**.";
    $out = "**Daftar {$role}** (" . count($list) . " orang):\n\n";
    foreach ($list as $i => $r) $out .= ($i + 1) . ". " . safe($r['nama']) . "\n";
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

function ansPengguna(mysqli $c): string
{
    if (!tblExists($c, 'users')) return "Tabel users tidak ditemukan.";
    $tot     = one($c, "SELECT COUNT(*) AS t FROM users");
    $perRole = all($c, "SELECT role,COUNT(*) AS c FROM users GROUP BY role ORDER BY c DESC");
    $terbaru = all($c, "SELECT nama,role,created_at FROM users ORDER BY created_at DESC LIMIT 10");
    $out  = "**Data pengguna sistem:**\n\n";
    $out .= "👤 Total akun: **" . num($tot['t']) . "**\n\n";
    $out .= "**Rekap per role:**\n";
    foreach ($perRole as $r) $out .= "• {$r['role']}: **" . num($r['c']) . "** orang\n";
    if (!empty($terbaru)) {
        $out .= "\n**Pengguna terbaru terdaftar:**\n";
        foreach ($terbaru as $r) $out .= "• " . safe($r['nama']) . " (" . safe($r['role']) . ")\n";
    }
    return $out . "\n_Diperbarui: " . now_label() . "_";
}

// ══════════════════════════════════════════════
//  JAWABAN — RINGKASAN
// ══════════════════════════════════════════════
function ansRingkasan(mysqli $c): string
{
    $t   = today();
    $ag  = one($c, "SELECT COUNT(*) AS c,COALESCE(SUM(peserta),0) AS p
        FROM agenda_kegiatan WHERE start_date<=? AND end_date>=?", 'ss', [$t, $t]);
    $ct  = cekinTable($c);
    $ip  = ['total' => 0, 'inap' => 0, 'belum' => 0];
    if ($ct) $ip = one($c, "SELECT COUNT(*) AS total,
        SUM(CASE WHEN status_inap='Check-in'       THEN 1 ELSE 0 END) AS inap,
        SUM(CASE WHEN status_inap='Belum Check-in' THEN 1 ELSE 0 END) AS belum
        FROM {$ct} pp JOIN agenda_kegiatan ak ON ak.id=pp.agenda_id
        WHERE ak.start_date<=? AND ak.end_date>=?", 'ss', [$t, $t]);
    $rt  = rusakTable($c);
    $rs  = ['total' => 0, 'pending' => 0, 'tinggi' => 0];
    if ($rt) $rs = one($c, "SELECT COUNT(*) AS total,
        SUM(CASE WHEN status NOT IN ('selesai','ditolak') THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status NOT IN ('selesai','ditolak') AND prioritas='tinggi' THEN 1 ELSE 0 END) AS tinggi
        FROM {$rt}");
    $tm  = ['total' => 0];
    if (tblExists($c, 'buku_tamu'))
        $tm = one($c, "SELECT COUNT(*) AS total FROM buku_tamu WHERE DATE(created_at)=?", 's', [$t]);
    $sr  = one($c, "SELECT SUM(CASE WHEN jenis='masuk' THEN 1 ELSE 0 END) AS m,
        SUM(CASE WHEN jenis='keluar' THEN 1 ELSE 0 END) AS k
        FROM arsip_surat WHERE tanggal_surat BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01') AND ?", 's', [$t]);
    $ck  = one($c, "SELECT COUNT(*) AS c,COUNT(DISTINCT nama_petugas) AS p
        FROM checklist_forms WHERE tanggal=?", 's', [$t]);

    $out  = "📊 **Ringkasan Operasional Hari Ini**\n" . str_repeat('─', 38) . "\n\n";
    $out .= "📅 Agenda aktif       : **" . num($ag['c']) . "** kegiatan (" . num($ag['p']) . " peserta)\n";
    $out .= "🏠 Penginapan         : **" . num($ip['total']) . "** total | 🏃" . num($ip['inap']) . " menginap | ⏳" . num($ip['belum']) . " belum\n";
    $out .= "📋 Checklist hari ini : **" . num($ck['c']) . "** form dari " . num($ck['p']) . " petugas\n";
    $out .= "🔧 Kerusakan pending  : **" . num($rs['pending']) . "** dari " . num($rs['total']) . " total";
    if ((int)($rs['tinggi'] ?? 0) > 0) $out .= " (🔴 " . num($rs['tinggi']) . " prioritas tinggi!)";
    $out .= "\n";
    $out .= "👥 Tamu hari ini      : **" . num($tm['total']) . "** orang\n";
    $out .= "📨 Surat bulan ini    : **" . num($sr['m']) . "** masuk | **" . num($sr['k']) . "** keluar\n";
    $out .= "\n_" . now_label() . "_";
    return $out;
}

// ══════════════════════════════════════════════
//  FALLBACK
// ══════════════════════════════════════════════
function ansFallback(): string
{
    return implode("\n", [
        "Halo! Saya Asisten Operasional v3. Berikut yang bisa ditanyakan:",
        "",
        "📅 **Agenda**",
        "   → \"agenda hari ini\", \"agenda mendatang\", \"kategori teknis\",",
        "   → \"agenda peserta terbanyak\"",
        "",
        "🏠 **Penginapan Peserta**",
        "   → \"rekap cekin\", \"siapa belum check-in\",",
        "   → \"cari peserta [nama/instansi]\", \"instansi terbanyak\",",
        "   → \"detail kamar\", \"peserta perempuan\"",
        "",
        "🔧 **Kerusakan**",
        "   → \"kerusakan hari ini\", \"laporan pending\",",
        "   → \"teknisi paling aktif\", \"jenis kerusakan terbanyak\"",
        "",
        "📋 **Checklist Petugas**",
        "   → \"checklist hari ini\", \"checklist per area\",",
        "   → \"ranking petugas aktif\",",
        "   → \"user mana yang sering isi checklist\" ← [NEW]",
        "",
        "📨 **Surat** → \"surat masuk bulan ini\", \"pengirim terbanyak\"",
        "📦 **Gudang** → \"barang masuk\", \"barang terbanyak\"",
        "👥 **Tamu**   → \"tamu hari ini\", \"instansi tamu terbanyak\"",
        "👤 **Staf**   → \"daftar teknisi\", \"daftar driver\", \"daftar ob\"",
        "📊 **Ringkasan** → \"ringkasan operasional hari ini\"",
    ]);
}

// ══════════════════════════════════════════════
//  MAIN ROUTER
// ══════════════════════════════════════════════
$q      = normalizeText($question);
$intent = detectIntent($q);
$range  = dateRange($q);

switch ($intent) {
    // Agenda
    case 'agenda':
        $answer = ansAgenda($conn, $range);
        break;
    case 'agenda_mendatang':
        $answer = ansAgendaMendatang($conn);
        break;
    case 'agenda_kategori':
        $answer = ansAgendaKategori($conn, $q, $range);
        break;
    case 'agenda_terbesar':
        $answer = ansAgendaTerbesar($conn);
        break;

    // Cekin
    case 'cekin_rekap':
        $answer = ansCekinRekap($conn, $q);
        break;
    case 'cekin_belum':
        $answer = ansCekinBelum($conn, $q);
        break;
    case 'cekin_aktif':
        $answer = ansCekinAktif($conn, $q);
        break;
    case 'cekin_selesai':
        $answer = ansCekinSelesai($conn, $q);
        break;
    case 'cekin_cari':
        $answer = ansCekinCari($conn, $q);
        break;
    case 'cekin_instansi':
        $answer = ansCekinInstansi($conn, $q);
        break;
    case 'cekin_gender':
        $answer = ansCekinGender($conn, $q);
        break;
    case 'cekin_kamar':
        $answer = ansCekinKamar($conn, $q);
        break;

    // Kerusakan
    case 'kerusakan':
    case 'kerusakan_detail':
        $answer = ansKerusakan($conn, $range, false);
        break;
    case 'rusak_pending':
        $answer = ansRusakPending($conn);
        break;
    case 'rusak_top_teknisi':
        $answer = ansRusakTopTeknisi($conn, $range);
        break;
    case 'rusak_top_jenis':
    case 'rusak_top_ruangan':
        $answer = ansRusakTopJenis($conn, $range);
        break;

    // Checklist
    case 'checklist':
        $answer = ansChecklist($conn, $range);
        break;
    case 'checklist_area':
        $answer = ansChecklistArea($conn, $range);
        break;
    case 'checklist_regu':
        $answer = ansChecklistRegu($conn, $range);
        break;
    case 'checklist_top':
        $answer = ansChecklistTop($conn, $range);
        break;
    case 'checklist_user':
        $answer = ansChecklistUser($conn, $range);
        break; // [NEW v3]

    // Surat
    case 'surat':
        $answer = ansSurat($conn, $range);
        break;
    case 'surat_masuk':
        $answer = ansSurat($conn, $range, 'masuk');
        break;
    case 'surat_keluar':
        $answer = ansSurat($conn, $range, 'keluar');
        break;
    case 'surat_pengirim':
        $answer = ansSuratPengirim($conn, $range);
        break;

    // Gudang
    case 'gudang':
    case 'gudang_masuk':
    case 'gudang_keluar':
        $answer = ansGudang($conn, $range);
        break;
    case 'gudang_top':
        $answer = ansGudangTop($conn, $range);
        break;

    // Tamu
    case 'tamu':
        $answer = ansTamu($conn, $range);
        break;
    case 'tamu_instansi':
        $answer = ansTamuInstansi($conn, $range);
        break;

    // Kendaraan
    case 'kendaraan':
        $answer = ansKendaraan($conn);
        break;

    // Pengguna
    case 'user_teknisi':
        $answer = ansPenggunaByRole($conn, 'teknisi');
        break;
    case 'user_driver':
        $answer = ansPenggunaByRole($conn, 'driver');
        break;
    case 'user_ob':
        $answer = ansPenggunaByRole($conn, 'ob');
        break;
    case 'user_security':
        $answer = ansPenggunaByRole($conn, 'security');
        break;
    case 'pengguna':
        $answer = ansPengguna($conn);
        break;

    // Ringkasan & fallback
    case 'ringkasan':
        $answer = ansRingkasan($conn);
        break;
    default:
        $answer = ansFallback();
        break;
}

respondJson(true, $answer, [
    'intent'       => $intent,
    'ai_used'      => false,
    'mode'         => 'rules_based_v3',
    'range'        => $range,
    'generated_at' => now_label(),
]);
