<?php

/**
 * PENGATURAN FASILITAS - tahap persiapan master, versi 1.0
 * Simpan satu folder dengan config.php (koneksi mysqli $conn).
 * Memakai sesi login $_SESSION['user']['id'] / ['nip']; role admin dicek ke users.
 * PHP 7.3+ / 8.x, MySQL 8 atau MariaDB. Tidak memerlukan Composer/CDN.
 *
 * Hanya tiga tabel fasilitas_* di bawah yang boleh ditulis oleh halaman ini.
 * GET tidak membuat tabel. Persiapan memerlukan POST + CSRF + konfirmasi backup.
 * Struktur/data master, peserta, agenda, dan Beranda TIDAK diubah.
 * Ini konfigurasi fasilitas, BUKAN mesin ketersediaan / check-out otomatis.
 * SQL konsisten dengan 01_persiapan_fasilitas.sql yang telah diberikan.
 * DDL MySQL mempunyai implicit commit: tabel kosong dapat tertinggal bila setup
 * gagal. Pengisian data memakai transaksi; setup dapat dilanjutkan tanpa reset.
 * Rujukan teknis: php.net/manual/en/mysqli.quickstart.prepared-statements.php
 * dev.mysql.com/doc/refman/8.0/en/implicit-commit.html
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta');

class PfValidationError extends RuntimeException
{
    /** @var int */
    public $httpStatus;
    public function __construct(string $message, int $httpStatus = 400)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
}

/** @param mixed $value */
function pf_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function pf_json(array $body, int $status = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $encoded === false ? '{"success":false,"message":"Gagal menyandikan respons."}' : $encoded;
    exit;
}
function pf_error_page(string $title, string $message, int $status): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . pf_h($title) . '</title>';
    echo '<body style="margin:0;padding:32px 18px;background:#f4f6f8;color:#172b40;font:16px/1.65 Arial,sans-serif"><main style="max-width:660px;margin:8vh auto;background:white;padding:28px;border:1px solid #dfe5ec"><h1 style="font-size:23px">' . pf_h($title) . '</h1><p>' . pf_h($message) . '</p><a href="beranda.php" style="color:#075985">Kembali ke Beranda</a></main></body></html>';
    exit;
}
/** Uses bound results, so mysqlnd get_result() is not required. */
function pf_stmt(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if ($stmt === false) throw new RuntimeException('Database prepare gagal: ' . $db->error, $db->errno);
    try {
        if ($types !== '') {
            $args = [$types];
            foreach ($params as $key => $unused) $args[] = &$params[$key];
            if (!call_user_func_array([$stmt, 'bind_param'], $args)) throw new RuntimeException('Parameter query tidak valid.');
        }
        if (!$stmt->execute()) throw new RuntimeException('Database execute gagal: ' . $stmt->error, $stmt->errno);
        return $stmt;
    } catch (Throwable $e) {
        $stmt->close();
        throw $e;
    }
}
function pf_rows(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = pf_stmt($db, $sql, $types, $params);
    try {
        $meta = $stmt->result_metadata();
        if (!$meta) return [];
        $stmt->store_result();
        $row = [];
        $refs = [];
        foreach ($meta->fetch_fields() as $field) {
            $row[$field->name] = null;
            $refs[] = &$row[$field->name];
        }
        call_user_func_array([$stmt, 'bind_result'], $refs);
        $result = [];
        while ($stmt->fetch()) {
            $copy = [];
            foreach ($row as $k => $v) $copy[$k] = $v;
            $result[] = $copy;
        }
        $meta->free();
        return $result;
    } finally {
        $stmt->close();
    }
}
function pf_execute(mysqli $db, string $sql, string $types = '', array $params = []): int
{
    $stmt = pf_stmt($db, $sql, $types, $params);
    $count = $stmt->affected_rows;
    $stmt->close();
    return $count;
}
function pf_query(mysqli $db, string $sql): void
{
    if ($db->query($sql) === false) throw new RuntimeException('Query gagal: ' . $db->error, $db->errno);
}

/** @return array<string,string> */
function pf_ddl(): array
{
    return [
        'fasilitas_kamar_pengaturan' => <<<'PFDDL0'
CREATE TABLE IF NOT EXISTS fasilitas_kamar_pengaturan (
    kamar_id INT NOT NULL,
    kategori ENUM('kamar_inap','bukan_kamar','perlu_verifikasi')
        NOT NULL DEFAULT 'perlu_verifikasi',
    status_operasional ENUM('belum_diverifikasi','aktif','nonaktif')
        NOT NULL DEFAULT 'belum_diverifikasi',
    kapasitas_orang SMALLINT UNSIGNED DEFAULT NULL,
    catatan VARCHAR(500) DEFAULT NULL,
    diverifikasi_oleh VARCHAR(50) DEFAULT NULL,
    diverifikasi_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kamar_id),
    KEY idx_fkp_kategori_status (kategori, status_operasional)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
PFDDL0,
        'fasilitas_kelas_operasional' => <<<'PFDDL1'
CREATE TABLE IF NOT EXISTS fasilitas_kelas_operasional (
    id INT NOT NULL AUTO_INCREMENT,
    kode VARCHAR(80) NOT NULL,
    lantai_id INT NOT NULL,
    nama_kelas VARCHAR(100) NOT NULL,
    jenis ENUM('kelas','laboratorium','auditorium','ruang_rapat','lainnya')
        NOT NULL DEFAULT 'kelas',
    status_operasional ENUM('belum_diverifikasi','aktif','nonaktif')
        NOT NULL DEFAULT 'belum_diverifikasi',
    kapasitas_orang SMALLINT UNSIGNED DEFAULT NULL,
    urutan INT UNSIGNED NOT NULL DEFAULT 0,
    catatan VARCHAR(500) DEFAULT NULL,
    diverifikasi_oleh VARCHAR(50) DEFAULT NULL,
    diverifikasi_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fko_kode (kode),
    KEY idx_fko_lantai (lantai_id),
    KEY idx_fko_jenis_status (jenis, status_operasional)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
PFDDL1,
        'fasilitas_kelas_anggota' => <<<'PFDDL2'
CREATE TABLE IF NOT EXISTS fasilitas_kelas_anggota (
    master_ruangan_id INT NOT NULL,
    kelas_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (master_ruangan_id),
    KEY idx_fka_kelas (kelas_id),
    CONSTRAINT fk_fka_kelas_v1 FOREIGN KEY (kelas_id)
        REFERENCES fasilitas_kelas_operasional (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
PFDDL2

    ];
}
function pf_table_meta(mysqli $db, string $name): array
{
    $r = pf_rows($db, 'SELECT TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', 's', [$name]);
    return $r ? $r[0] : [];
}
/** Return missing/incompatible states; never repair existing schemas implicitly. */
function pf_schema(mysqli $db): array
{
    $specs = [
        'fasilitas_kamar_pengaturan' => [
            'pk' => 'kamar_id',
            'cols' => ['kamar_id', 'kategori', 'status_operasional', 'kapasitas_orang', 'catatan', 'diverifikasi_oleh', 'diverifikasi_at', 'created_at', 'updated_at'],
            'enums' => ['kategori' => ['kamar_inap', 'bukan_kamar', 'perlu_verifikasi'], 'status_operasional' => ['belum_diverifikasi', 'aktif', 'nonaktif']]
        ],
        'fasilitas_kelas_operasional' => [
            'pk' => 'id',
            'cols' => ['id', 'kode', 'lantai_id', 'nama_kelas', 'jenis', 'status_operasional', 'kapasitas_orang', 'urutan', 'catatan', 'diverifikasi_oleh', 'diverifikasi_at', 'created_at', 'updated_at'],
            'enums' => ['jenis' => ['kelas', 'laboratorium', 'auditorium', 'ruang_rapat', 'lainnya'], 'status_operasional' => ['belum_diverifikasi', 'aktif', 'nonaktif']]
        ],
        'fasilitas_kelas_anggota' => [
            'pk' => 'master_ruangan_id',
            'cols' => ['master_ruangan_id', 'kelas_id', 'created_at'],
            'enums' => []
        ]
    ];
    $tables = [];
    $missing = [];
    $issues = [];
    foreach ($specs as $table => $spec) {
        $meta = pf_table_meta($db, $table);
        if (!$meta) {
            $missing[] = $table;
            $tables[$table] = 'belum_ada';
            continue;
        }
        $start = count($issues);
        if ($meta['TABLE_TYPE'] !== 'BASE TABLE' || strcasecmp((string)$meta['ENGINE'], 'InnoDB') !== 0) {
            $issues[] = $table . ': harus berupa tabel InnoDB.';
            $tables[$table] = 'tidak_sesuai';
            continue;
        }
        $cols = [];
        foreach (pf_rows($db, 'SHOW COLUMNS FROM `' . $table . '`') as $c) $cols[$c['Field']] = $c;
        foreach ($spec['cols'] as $col) {
            if (!isset($cols[$col])) $issues[] = $table . ': kolom ' . $col . ' belum tersedia.';
        }
        foreach (['id', 'kamar_id', 'lantai_id', 'master_ruangan_id', 'kelas_id'] as $col) {
            if (isset($cols[$col]) && !preg_match('/^int(?:\([0-9]+\))?$/iD', $cols[$col]['Type'])) {
                $issues[] = $table . ': tipe ' . $col . ' harus INT signed sesuai skema persiapan.';
            }
        }
        if (isset($cols['kapasitas_orang']) && !preg_match('/^smallint(?:\([0-9]+\))? unsigned$/iD', $cols['kapasitas_orang']['Type'])) $issues[] = $table . ': kapasitas_orang harus SMALLINT UNSIGNED.';
        if (isset($cols['urutan']) && !preg_match('/^int(?:\([0-9]+\))? unsigned$/iD', $cols['urutan']['Type'])) $issues[] = $table . ': urutan harus INT UNSIGNED.';
        foreach (['kode' => 80, 'nama_kelas' => 100, 'catatan' => 500, 'diverifikasi_oleh' => 50] as $col => $minimum) {
            if (!isset($cols[$col])) continue;
            if (!preg_match('/^varchar\(([0-9]+)\)$/iD', $cols[$col]['Type'], $size) || (int)$size[1] < $minimum) $issues[] = $table . ': ukuran kolom ' . $col . ' tidak sesuai.';
        }
        foreach (['created_at', 'updated_at'] as $col) {
            if (isset($cols[$col]) && stripos($cols[$col]['Type'], 'timestamp') !== 0) $issues[] = $table . ': ' . $col . ' harus TIMESTAMP.';
        }
        if (isset($cols['diverifikasi_at']) && stripos($cols['diverifikasi_at']['Type'], 'datetime') !== 0) $issues[] = $table . ': diverifikasi_at harus DATETIME.';
        foreach ($spec['enums'] as $col => $values) {
            if (!isset($cols[$col])) continue;
            $actual = strtolower($cols[$col]['Type']);
            foreach ($values as $v) if (strpos($actual, "'" . $v . "'") === false) $issues[] = $table . ': pilihan ' . $col . ' tidak sesuai.';
        }
        foreach (['kapasitas_orang', 'catatan', 'diverifikasi_oleh', 'diverifikasi_at'] as $col) {
            if (isset($cols[$col]) && $cols[$col]['Null'] !== 'YES') $issues[] = $table . ': ' . $col . ' harus boleh NULL.';
        }
        foreach ($cols as $name => $c) {
            if (!in_array($name, $spec['cols'], true) && $c['Null'] === 'NO' && $c['Default'] === null && stripos($c['Extra'], 'auto_increment') === false && stripos($c['Extra'], 'generated') === false) {
                $issues[] = $table . ': ada kolom tambahan wajib ' . $name . '. Tidak diubah otomatis.';
            }
        }
        $indices = [];
        foreach (pf_rows($db, 'SHOW INDEX FROM `' . $table . '`') as $idx) {
            $indices[$idx['Key_name']]['columns'][(int)$idx['Seq_in_index']] = $idx['Column_name'];
            $indices[$idx['Key_name']]['unique'] = ((int)$idx['Non_unique'] === 0);
        }
        $pk = isset($indices['PRIMARY']) ? array_values($indices['PRIMARY']['columns']) : [];
        if ($pk !== [$spec['pk']]) $issues[] = $table . ': primary key tidak sesuai.';
        if ($table === 'fasilitas_kelas_operasional') {
            $codeUnique = false;
            foreach ($indices as $idx) if ($idx['unique'] && array_values($idx['columns']) === ['kode']) $codeUnique = true;
            if (!$codeUnique) $issues[] = $table . ': kode harus memiliki unique index.';
            if (isset($cols['id']) && stripos($cols['id']['Extra'], 'auto_increment') === false) $issues[] = $table . ': id harus auto_increment.';
        }
        $tables[$table] = count($issues) === $start ? 'siap' : 'tidak_sesuai';
    }
    return ['ready' => !$missing && !$issues, 'missing' => $missing, 'issues' => array_values(array_unique($issues)), 'tables' => $tables];
}
/** Read only known public facility fields, never personnel/guest data. */
function pf_master(mysqli $db): array
{
    $rooms = pf_rows($db, "SELECT k.id,k.lantai_id,k.nomor_kamar,l.nama_lantai,l.lokasi_id,m.nama_lokasi,t.kode AS tipe_kode
        FROM master_kamar k LEFT JOIN master_lantai l ON l.id=k.lantai_id
        LEFT JOIN master_lokasi m ON m.id=l.lokasi_id LEFT JOIN master_tipe_lokasi t ON t.id=m.tipe_lokasi_id
        ORDER BY m.nama_lokasi,l.id,k.id");
    $spaces = pf_rows($db, "SELECT r.id,r.lantai_id,r.nama_ruangan,l.nama_lantai,l.lokasi_id,m.nama_lokasi
        FROM master_ruangan r LEFT JOIN master_lantai l ON l.id=r.lantai_id
        LEFT JOIN master_lokasi m ON m.id=l.lokasi_id ORDER BY r.lantai_id,r.id");
    return ['rooms' => $rooms, 'spaces' => $spaces];
}
/** @param array<string,mixed> $room */
function pf_proposed_category(array $room): string
{
    $name = strtoupper(trim((string)$room['nomor_kamar']));
    if (strtoupper(trim((string)$room['tipe_kode'])) === 'ASRAMA' && preg_match('/^(?:[0-9]+|VIP[ ]*[0-9]+)$/D', $name)) return 'kamar_inap';
    if (in_array($name, ['KORIDOR', 'PANTRI', 'TOILET', 'LIFT', 'RUANG LAUNDRY'], true)) return 'bukan_kamar';
    return 'perlu_verifikasi';
}
/** Propose deterministic units. Incomplete/duplicate combinations are not guessed. */
function pf_plan_classes(array $spaces): array
{
    $buckets = [];
    $warnings = [];
    $numbered = 0;
    $pairs = [1 => [1, 2], 2 => [1, 2], 6 => [6, 7], 7 => [6, 7], 9 => [9, 10], 10 => [9, 10]];
    foreach ($spaces as $r) {
        if (!preg_match('/^kelas[ ]+([0-9]+)$/iD', trim((string)$r['nama_ruangan']), $match)) continue;
        $n = (int)$match[1];
        if ($n < 1) continue;
        $numbered++;
        if (empty($r['lokasi_id']) || $r['nama_lokasi'] === null) {
            $warnings[] = 'Ruangan ID ' . $r['id'] . ' tidak memiliki lokasi/lantai lengkap.';
            continue;
        }
        $nums = isset($pairs[$n]) ? $pairs[$n] : [$n];
        $code = 'KLS-L' . (int)$r['lantai_id'] . '-' . implode('_', $nums);
        if (!isset($buckets[$code])) $buckets[$code] = ['kode' => $code, 'lantai_id' => (int)$r['lantai_id'], 'nama_kelas' => 'Kelas ' . implode('/', $nums), 'expected' => $nums, 'urutan' => min($nums), 'members' => [], 'numbers' => []];
        $buckets[$code]['members'][] = (int)$r['id'];
        $buckets[$code]['numbers'][] = $n;
    }
    $units = [];
    foreach ($buckets as $unit) {
        sort($unit['numbers']);
        sort($unit['members']);
        if ($unit['numbers'] !== $unit['expected']) {
            $warnings[] = $unit['nama_kelas'] . ' (lantai ID ' . $unit['lantai_id'] . ') tidak dipetakan: anggota kurang atau ada nomor ganda.';
            continue;
        }
        $units[] = $unit;
    }
    usort($units, function (array $a, array $b): int {
        return $a['lantai_id'] === $b['lantai_id'] ? $a['urutan'] <=> $b['urutan'] : $a['lantai_id'] <=> $b['lantai_id'];
    });
    return ['units' => $units, 'warnings' => $warnings, 'numbered' => $numbered];
}
/** @param array<string,mixed> $row */
function pf_version(array $row, string $kind): string
{
    $fields = $kind === 'kamar'
        ? ['kamar_id', 'kategori', 'status_operasional', 'kapasitas_orang', 'catatan', 'diverifikasi_oleh', 'diverifikasi_at', 'created_at', 'updated_at']
        : ['id', 'kode', 'lantai_id', 'nama_kelas', 'jenis', 'status_operasional', 'kapasitas_orang', 'urutan', 'catatan', 'diverifikasi_oleh', 'diverifikasi_at', 'created_at', 'updated_at'];
    $v = [];
    foreach ($fields as $field) $v[$field] = isset($row[$field]) ? (string)$row[$field] : null;
    return hash('sha256', (string)json_encode($v));
}
function pf_read_state(mysqli $db, string $database): array
{
    $schema = pf_schema($db);
    $masters = pf_master($db);
    $plan = pf_plan_classes($masters['spaces']);
    $proposal = 0;
    foreach ($masters['rooms'] as $room) if (pf_proposed_category($room) === 'kamar_inap') $proposal++;
    $state = [
        'database' => $database,
        'schema' => $schema,
        'source' => ['room_entries' => count($masters['rooms']), 'room_candidates' => $proposal, 'class_entries' => $plan['numbered'], 'class_units' => count($plan['units'])],
        'rooms' => [],
        'classes' => [],
        'warnings' => $plan['warnings'],
        'new_rooms' => 0,
        'new_units' => 0,
        'updated_at' => date('d/m/Y H:i:s') . ' WIB'
    ];
    if (!$schema['ready']) return $state;
    $rooms = pf_rows($db, "SELECT p.*,k.nomor_kamar,k.lantai_id,l.nama_lantai,l.lokasi_id,m.nama_lokasi,t.kode AS tipe_kode
        FROM fasilitas_kamar_pengaturan p LEFT JOIN master_kamar k ON k.id=p.kamar_id
        LEFT JOIN master_lantai l ON l.id=k.lantai_id LEFT JOIN master_lokasi m ON m.id=l.lokasi_id
        LEFT JOIN master_tipe_lokasi t ON t.id=m.tipe_lokasi_id ORDER BY m.nama_lokasi,l.id,k.id,p.kamar_id");
    $registered = [];
    foreach ($rooms as &$r) {
        $r['id'] = (int)$r['kamar_id'];
        $r['version'] = pf_version($r, 'kamar');
        $r['source_valid'] = $r['nomor_kamar'] !== null && $r['nama_lokasi'] !== null && $r['tipe_kode'] !== null;
        $registered[$r['id']] = true;
        if (!$r['source_valid']) $state['warnings'][] = 'Kamar ID ' . $r['id'] . ' tidak mempunyai rantai master lengkap. Periksa master sebelum diaktifkan.';
    }
    unset($r);
    usort($rooms, function (array $a, array $b): int {
        $v = strnatcasecmp((string)$a['nama_lokasi'], (string)$b['nama_lokasi']);
        if ($v !== 0) return $v;
        $v = (int)$a['lantai_id'] <=> (int)$b['lantai_id'];
        return $v !== 0 ? $v : strnatcasecmp((string)$a['nomor_kamar'], (string)$b['nomor_kamar']);
    });
    $classes = pf_rows($db, "SELECT c.*,l.nama_lantai,l.lokasi_id,m.nama_lokasi
        FROM fasilitas_kelas_operasional c LEFT JOIN master_lantai l ON l.id=c.lantai_id
        LEFT JOIN master_lokasi m ON m.id=l.lokasi_id ORDER BY c.urutan,c.lantai_id,c.id");
    $members = pf_rows($db, "SELECT a.master_ruangan_id,a.kelas_id,r.nama_ruangan,r.lantai_id
        FROM fasilitas_kelas_anggota a LEFT JOIN master_ruangan r ON r.id=a.master_ruangan_id ORDER BY a.master_ruangan_id");
    $memberMap = [];
    $unitsById = [];
    $unitCodes = [];
    foreach ($members as $a) $memberMap[(int)$a['kelas_id']][] = $a;
    foreach ($classes as &$c) {
        $c['id'] = (int)$c['id'];
        $c['version'] = pf_version($c, 'kelas');
        $c['members'] = isset($memberMap[$c['id']]) ? $memberMap[$c['id']] : [];
        $c['source_valid'] = $c['nama_lokasi'] !== null && count($c['members']) > 0;
        foreach ($c['members'] as $m) if ($m['nama_ruangan'] === null || (int)$m['lantai_id'] !== (int)$c['lantai_id']) $c['source_valid'] = false;
        if (!$c['source_valid']) $state['warnings'][] = 'Unit ' . $c['nama_kelas'] . ' memiliki pemetaan kosong/tidak sesuai lantai. Jangan diaktifkan sebelum diverifikasi.';
        $unitCodes[$c['kode']] = true;
        $unitsById[$c['id']] = true;
    }
    unset($c);
    foreach ($members as $a) if (!isset($unitsById[(int)$a['kelas_id']])) $state['warnings'][] = 'Pemetaan ruangan ID ' . $a['master_ruangan_id'] . ' merujuk unit kelas yang tidak ada.';
    foreach ($masters['rooms'] as $r) if (!isset($registered[(int)$r['id']])) $state['new_rooms']++;
    foreach ($plan['units'] as $u) if (!isset($unitCodes[$u['kode']])) $state['new_units']++;
    $state['rooms'] = $rooms;
    $state['classes'] = $classes;
    $state['warnings'] = array_values(array_unique($state['warnings']));
    return $state;
}
function pf_acquire_lock(mysqli $db, string $database): string
{
    $name = 'pf_setup_v1_' . substr(hash('sha256', $database), 0, 36);
    $r = pf_rows($db, 'SELECT GET_LOCK(?, 8) AS acquired', 's', [$name]);
    if (!$r || (int)$r[0]['acquired'] !== 1) throw new PfValidationError('Pengaturan sedang diproses oleh sesi lain. Coba kembali beberapa saat lagi.', 409);
    return $name;
}
function pf_release_lock(mysqli $db, string $name): void
{
    try {
        pf_rows($db, 'SELECT RELEASE_LOCK(?) AS released', 's', [$name]);
    } catch (Throwable $ignored) { /* Connection close releases the advisory lock. */
    }
}
function pf_setup(mysqli $db, string $database): array
{
    $lock = pf_acquire_lock($db, $database);
    $inTransaction = false;
    $ddlStarted = false;
    try {
        $schema = pf_schema($db);
        if ($schema['issues']) throw new PfValidationError('Struktur tabel tambahan tidak sesuai. Tidak ada tabel yang ditimpa. ' . implode(' ', $schema['issues']), 409);
        $masters = pf_master($db); // Verify source BEFORE any DDL.
        $plan = pf_plan_classes($masters['spaces']);
        $ddl = pf_ddl();
        foreach ($schema['missing'] as $table) {
            $ddlStarted = true;
            pf_query($db, $ddl[$table]);
        }
        $after = pf_schema($db);
        if (!$after['ready']) throw new PfValidationError('Tabel persiapan belum sesuai. ' . implode(' ', $after['issues']), 409);
        if (!$db->begin_transaction()) throw new RuntimeException('Transaksi tidak dapat dimulai.');
        $inTransaction = true;
        $registered = [];
        foreach (pf_rows($db, 'SELECT kamar_id FROM fasilitas_kamar_pengaturan') as $r) $registered[(int)$r['kamar_id']] = true;
        $counts = ['rooms' => 0, 'units' => 0, 'members' => 0];
        $warnings = $plan['warnings'];
        foreach ($masters['rooms'] as $r) {
            if (isset($registered[(int)$r['id']])) continue;
            $counts['rooms'] += pf_execute($db, "INSERT INTO fasilitas_kamar_pengaturan (kamar_id,kategori,status_operasional,catatan)
                VALUES (?,?,'belum_diverifikasi',?)", 'iss', [(int)$r['id'], pf_proposed_category($r), 'Usulan dari master. Periksa kategori dan kondisi sebelum diaktifkan.']);
        }
        $existing = [];
        $mapped = [];
        foreach (pf_rows($db, 'SELECT * FROM fasilitas_kelas_operasional') as $r) $existing[$r['kode']] = $r;
        foreach (pf_rows($db, 'SELECT * FROM fasilitas_kelas_anggota') as $r) $mapped[(int)$r['master_ruangan_id']] = (int)$r['kelas_id'];
        foreach ($plan['units'] as $u) {
            $found = isset($existing[$u['kode']]) ? $existing[$u['kode']] : null;
            $unitId = $found ? (int)$found['id'] : 0;
            $conflict = false;
            foreach ($u['members'] as $masterId) if (isset($mapped[$masterId]) && $mapped[$masterId] !== $unitId) $conflict = true;
            if ($found && (int)$found['lantai_id'] !== $u['lantai_id']) $conflict = true;
            if ($found) {
                $current = [];
                foreach ($mapped as $masterId => $targetId) if ($targetId === $unitId) $current[] = $masterId;
                if (array_diff($current, $u['members'])) $conflict = true;
                $missingMembers = array_diff($u['members'], $current);
                if ($missingMembers && $found['status_operasional'] !== 'belum_diverifikasi') $conflict = true;
            }
            if ($conflict) {
                $warnings[] = $u['nama_kelas'] . ' (lantai ID ' . $u['lantai_id'] . ') dilewati: ada pemetaan lama/terverifikasi yang berbeda. Pengaturan lama dipertahankan.';
                continue;
            }
            if (!$found) {
                pf_execute(
                    $db,
                    "INSERT INTO fasilitas_kelas_operasional
                    (kode,lantai_id,nama_kelas,jenis,status_operasional,urutan,catatan) VALUES (?,?,?,'kelas','belum_diverifikasi',?,?)",
                    'sisis',
                    [$u['kode'], $u['lantai_id'], $u['nama_kelas'], $u['urutan'], 'Pemetaan awal dari master ruangan. Kondisi operasional belum diverifikasi.']
                );
                $unitId = (int)$db->insert_id;
                $counts['units']++;
            }
            foreach ($u['members'] as $masterId) {
                if (isset($mapped[$masterId])) continue;
                $counts['members'] += pf_execute($db, 'INSERT INTO fasilitas_kelas_anggota (master_ruangan_id,kelas_id) VALUES (?,?)', 'ii', [$masterId, $unitId]);
                $mapped[$masterId] = $unitId;
            }
        }
        if (!$db->commit()) throw new RuntimeException('Commit gagal.');
        $inTransaction = false;
        return ['counts' => $counts, 'warnings' => array_values(array_unique($warnings))];
    } catch (Throwable $e) {
        if ($inTransaction) {
            try {
                $db->rollback();
            } catch (Throwable $ignored) {
            }
        }
        if ($ddlStarted && !($e instanceof PfValidationError)) {
            error_log('[Fasilitas setup] ' . $e->getMessage());
            throw new PfValidationError('Persiapan belum selesai. Tabel baru yang sudah dibuat dapat tetap ada; pengisian data dibatalkan. Periksa izin CREATE/INSERT dan log PHP, lalu jalankan Persiapan kembali. Data master lama tidak diubah.', 500);
        }
        throw $e;
    } finally {
        pf_release_lock($db, $lock);
    }
}
/** @param mixed $v */
function pf_text($v, int $max, string $label): string
{
    if (!is_string($v)) throw new PfValidationError($label . ' harus berupa teks.');
    $v = trim($v);
    if (preg_match('//u', $v) !== 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $v)) throw new PfValidationError($label . ' mengandung karakter tidak valid.');
    $length = preg_match_all('/./us', $v, $unused);
    if ($length === false || $length > $max) throw new PfValidationError($label . ' maksimal ' . $max . ' karakter.');
    return $v;
}
/** @param mixed $v */
function pf_id($v): int
{
    if ((!is_string($v) && !is_int($v)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$v) || (float)$v > 2147483647) throw new PfValidationError('ID fasilitas tidak valid.');
    return (int)$v;
}
/** @param mixed $v */
function pf_capacity($v): ?int
{
    if ($v === null || $v === '') return null;
    if ((!is_string($v) && !is_int($v)) || !preg_match('/^[1-9][0-9]{0,4}$/D', (string)$v) || (int)$v > 65535) throw new PfValidationError('Kapasitas harus 1 sampai 65535 orang, atau dikosongkan jika belum diketahui.');
    return (int)$v;
}
function pf_normalize_change(array $change, string $kind): array
{
    $id = pf_id(isset($change['id']) ? $change['id'] : null);
    $version = isset($change['version']) ? $change['version'] : '';
    if (!is_string($version) || !preg_match('/^[a-f0-9]{64}$/D', $version)) throw new PfValidationError('Versi data tidak valid. Muat ulang halaman.', 409);
    $status = isset($change['status_operasional']) ? $change['status_operasional'] : '';
    if (!is_string($status) || !in_array($status, ['belum_diverifikasi', 'aktif', 'nonaktif'], true)) throw new PfValidationError('Status operasional tidak valid.');
    $out = [
        'id' => $id,
        'version' => $version,
        'status_operasional' => $status,
        'kapasitas_orang' => pf_capacity(isset($change['kapasitas_orang']) ? $change['kapasitas_orang'] : null),
        'catatan' => pf_text(isset($change['catatan']) ? $change['catatan'] : '', 500, 'Catatan')
    ];
    if ($kind === 'kamar') {
        $category = isset($change['kategori']) ? $change['kategori'] : '';
        if (!is_string($category) || !in_array($category, ['kamar_inap', 'bukan_kamar', 'perlu_verifikasi'], true)) throw new PfValidationError('Kategori kamar tidak valid.');
        if ($category === 'perlu_verifikasi' && $status !== 'belum_diverifikasi') throw new PfValidationError('Tentukan kategori kamar sebelum menyatakan terverifikasi.');
        if ($category !== 'kamar_inap' && $status === 'aktif') throw new PfValidationError('Hanya kategori Kamar menginap yang dapat ditandai Aktif.');
        if ($category !== 'kamar_inap' && $out['kapasitas_orang'] !== null) throw new PfValidationError('Kapasitas orang hanya diisi untuk kamar menginap.');
        $out['kategori'] = $category;
    } else {
        $kindValue = isset($change['jenis']) ? $change['jenis'] : '';
        if (!is_string($kindValue) || !in_array($kindValue, ['kelas', 'laboratorium', 'auditorium', 'ruang_rapat', 'lainnya'], true)) throw new PfValidationError('Jenis kelas tidak valid.');
        $out['jenis'] = $kindValue;
        $out['nama_kelas'] = pf_text(isset($change['nama_kelas']) ? $change['nama_kelas'] : '', 100, 'Nama kelas');
        if ($out['nama_kelas'] === '') throw new PfValidationError('Nama kelas wajib diisi.');
    }
    return $out;
}
function pf_save(mysqli $db, string $database, string $actor, array $body): int
{
    $kind = isset($body['kind']) ? $body['kind'] : '';
    if (!in_array($kind, ['kamar', 'kelas'], true)) throw new PfValidationError('Jenis pengaturan tidak valid.');
    $changes = isset($body['changes']) ? $body['changes'] : null;
    if (!is_array($changes) || count($changes) < 1 || count($changes) > 1000) throw new PfValidationError('Pilih 1 sampai 1000 fasilitas dalam sekali simpan.');
    $clean = [];
    $seen = [];
    foreach ($changes as $change) {
        if (!is_array($change)) throw new PfValidationError('Isi pengaturan tidak valid.');
        $r = pf_normalize_change($change, $kind);
        if (isset($seen[$r['id']])) throw new PfValidationError('ID pilihan tidak boleh ganda.');
        $seen[$r['id']] = true;
        $clean[] = $r;
        if ($r['status_operasional'] !== 'belum_diverifikasi' && (!isset($body['confirmed']) || $body['confirmed'] !== true)) throw new PfValidationError('Konfirmasikan bahwa kategori dan kondisi fasilitas telah diperiksa.');
    }
    usort($clean, function (array $a, array $b): int {
        return $a['id'] <=> $b['id'];
    });
    $lock = pf_acquire_lock($db, $database);
    $inTransaction = false;
    try {
        $schema = pf_schema($db);
        if (!$schema['ready']) throw new PfValidationError('Persiapan fasilitas belum lengkap atau struktur tidak sesuai.', 409);
        if (!$db->begin_transaction()) throw new RuntimeException('Transaksi tidak dapat dimulai.');
        $inTransaction = true;
        $saved = 0;
        foreach ($clean as $new) {
            $table = $kind === 'kamar' ? 'fasilitas_kamar_pengaturan' : 'fasilitas_kelas_operasional';
            $pk = $kind === 'kamar' ? 'kamar_id' : 'id';
            $rows = pf_rows($db, 'SELECT * FROM `' . $table . '` WHERE `' . $pk . '`=? FOR UPDATE', 'i', [$new['id']]);
            if (!$rows) throw new PfValidationError('Fasilitas ID ' . $new['id'] . ' tidak ditemukan. Tidak ada pilihan yang disimpan.', 409);
            $old = $rows[0];
            if (!hash_equals(pf_version($old, $kind), $new['version'])) throw new PfValidationError('Data ID ' . $new['id'] . ' sudah diubah dari sesi lain. Muat ulang data sebelum menyimpan. Seluruh pilihan dibatalkan.', 409);
            if ($new['status_operasional'] === 'aktif') {
                if ($kind === 'kamar') {
                    $valid = pf_rows($db, 'SELECT k.id FROM master_kamar k JOIN master_lantai l ON l.id=k.lantai_id JOIN master_lokasi m ON m.id=l.lokasi_id JOIN master_tipe_lokasi t ON t.id=m.tipe_lokasi_id WHERE k.id=?', 'i', [$new['id']]);
                    if (!$valid) throw new PfValidationError('Rantai master kamar ID ' . $new['id'] . ' tidak lengkap.');
                } else {
                    $valid = pf_rows($db, "SELECT a.master_ruangan_id,r.lantai_id AS master_lantai,l.id AS unit_lantai,m.id AS lokasi_id
                        FROM fasilitas_kelas_anggota a LEFT JOIN master_ruangan r ON r.id=a.master_ruangan_id
                        LEFT JOIN master_lantai l ON l.id=? LEFT JOIN master_lokasi m ON m.id=l.lokasi_id WHERE a.kelas_id=?", 'ii', [(int)$old['lantai_id'], $new['id']]);
                    if (!$valid) throw new PfValidationError('Unit kelas belum memiliki anggota master.');
                    foreach ($valid as $v) if ($v['lokasi_id'] === null || $v['master_lantai'] === null || (int)$v['master_lantai'] !== (int)$old['lantai_id']) throw new PfValidationError('Pemetaan unit kelas ' . $old['nama_kelas'] . ' tidak sesuai master/lantai.');
                }
            }
            $keys = $kind === 'kamar' ? ['kategori', 'status_operasional', 'kapasitas_orang', 'catatan'] : ['nama_kelas', 'jenis', 'status_operasional', 'kapasitas_orang', 'catatan'];
            $changed = false;
            foreach ($keys as $key) if ((string)$old[$key] !== (string)$new[$key]) $changed = true;
            if ($new['status_operasional'] !== 'belum_diverifikasi' && (empty($old['diverifikasi_oleh']) || empty($old['diverifikasi_at']))) $changed = true;
            if (!$changed) continue;
            $verified = $new['status_operasional'] !== 'belum_diverifikasi';
            $by = $verified ? $actor : null;
            $at = $verified ? date('Y-m-d H:i:s') : null;
            if ($kind === 'kamar') {
                pf_execute(
                    $db,
                    'UPDATE fasilitas_kamar_pengaturan SET kategori=?,status_operasional=?,kapasitas_orang=?,catatan=?,diverifikasi_oleh=?,diverifikasi_at=?,updated_at=CURRENT_TIMESTAMP WHERE kamar_id=?',
                    'ssisssi',
                    [$new['kategori'], $new['status_operasional'], $new['kapasitas_orang'], $new['catatan'], $by, $at, $new['id']]
                );
            } else {
                pf_execute(
                    $db,
                    'UPDATE fasilitas_kelas_operasional SET nama_kelas=?,jenis=?,status_operasional=?,kapasitas_orang=?,catatan=?,diverifikasi_oleh=?,diverifikasi_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',
                    'sssisssi',
                    [$new['nama_kelas'], $new['jenis'], $new['status_operasional'], $new['kapasitas_orang'], $new['catatan'], $by, $at, $new['id']]
                );
            }
            $saved++;
        }
        if (!$db->commit()) throw new RuntimeException('Commit gagal.');
        $inTransaction = false;
        return $saved;
    } catch (Throwable $e) {
        if ($inTransaction) {
            try {
                $db->rollback();
            } catch (Throwable $ignored) {
            }
        }
        throw $e;
    } finally {
        pf_release_lock($db, $lock);
    }
}
/** Do not reveal SQL or connection credentials to the browser. */
function pf_friendly_error(Throwable $e): string
{
    if ($e instanceof PfValidationError) return $e->getMessage();
    $code = (int)$e->getCode();
    if (in_array($code, [1044, 1045, 1142, 1227], true)) return 'Akun database tidak memiliki izin yang diperlukan. Persiapan membutuhkan SELECT, CREATE, INSERT, dan REFERENCES pada tabel terkait. Penyimpanan membutuhkan UPDATE pada tabel fasilitas. Hubungi pengelola server.';
    if (in_array($code, [1054, 1146], true)) return 'Tabel atau kolom yang dibutuhkan tidak ditemukan. Pastikan config.php mengarah ke database aplikasi yang memiliki master_kamar, master_ruangan, master_lantai, master_lokasi, master_tipe_lokasi, dan users.';
    return 'Proses belum berhasil. Periksa koneksi/izin database dan log PHP server. Tidak ada perubahan pada master, peserta, atau agenda.';
}

/* ----------------------- Request controller ----------------------- */
$pfApi = isset($_GET['pf_api']);
try {
    if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) throw new RuntimeException('Sesi tidak dapat dimulai.');
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        if ($pfApi) pf_json(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.'], 401);
        while (ob_get_level() > 0) ob_end_clean();
        header('Location: login.php');
        exit;
    }
    if (!extension_loaded('mysqli')) throw new PfValidationError('Ekstensi PHP mysqli belum aktif di server.', 500);
    if (!is_file(__DIR__ . '/config.php')) throw new PfValidationError('Letakkan pengaturan_fasilitas.php satu folder dengan config.php aplikasi.', 500);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/config.php';
    if (!isset($conn) || !($conn instanceof mysqli)) throw new PfValidationError('config.php harus menyediakan koneksi mysqli bernama $conn, sesuai file Beranda Anda.', 500);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->set_charset('utf8mb4');
    pf_query($conn, "SET time_zone = '+07:00'");
    $sessionUser = $_SESSION['user'];
    $uid = isset($sessionUser['id']) && is_scalar($sessionUser['id']) ? (string)$sessionUser['id'] : '';
    $nip = isset($sessionUser['nip']) && is_scalar($sessionUser['nip']) ? trim((string)$sessionUser['nip']) : '';
    $uidValid = preg_match('/^[1-9][0-9]{0,9}$/D', $uid) && (float)$uid <= 2147483647;
    if ($uidValid) {
        $accounts = pf_rows($conn, 'SELECT id,nip,nama,role FROM users WHERE id=? LIMIT 1', 'i', [(int)$uid]);
        if ($accounts && $nip !== '' && (string)$accounts[0]['nip'] !== $nip) $accounts = [];
    } elseif ($nip !== '') {
        $accounts = pf_rows($conn, 'SELECT id,nip,nama,role FROM users WHERE nip=? LIMIT 1', 's', [$nip]);
    } else {
        $accounts = [];
    }
    if (!$accounts || strtolower(trim((string)$accounts[0]['role'])) !== 'admin') {
        if ($pfApi) pf_json(['success' => false, 'message' => 'Akses ditolak. Pengaturan fasilitas hanya untuk role admin.'], 403);
        pf_error_page('Akses khusus admin', 'Akun ini tidak memiliki role admin pada database. Halaman tidak mengizinkan petugas, admin_ob, maupun pengunjung mengubah pengaturan fasilitas.', 403);
    }
    $pfAdmin = $accounts[0];
    if (empty($_SESSION['pf_facilities_csrf']) || !is_string($_SESSION['pf_facilities_csrf'])) $_SESSION['pf_facilities_csrf'] = bin2hex(random_bytes(32));
    $pfCsrf = $_SESSION['pf_facilities_csrf'];
    session_write_close();
    $dbName = pf_rows($conn, 'SELECT DATABASE() AS nama');
    $pfDatabase = (string)($dbName[0]['nama'] ?? '');
    if ($pfDatabase === '') throw new PfValidationError('Tidak ada database yang dipilih oleh config.php.', 500);
    $pfActor = (string)$pfAdmin['nip'];
    if ($pfActor === '') $pfActor = 'id:' . $pfAdmin['id'];
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== 0) throw new PfValidationError('Permintaan harus menggunakan JSON. Muat ulang halaman.');
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($csrf) || !hash_equals($pfCsrf, $csrf)) throw new PfValidationError('Token keamanan tidak valid/kedaluwarsa. Muat ulang halaman.', 403);
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1048576) throw new PfValidationError('Data terlalu besar. Simpan dalam beberapa kelompok.', 413);
        $raw = file_get_contents('php://input', false, null, 0, 1048577);
        if ($raw === false || strlen($raw) > 1048576) throw new PfValidationError('Data terlalu besar.', 413);
        $body = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) throw new PfValidationError('JSON tidak valid. Muat ulang halaman.');
        $action = $body['action'] ?? '';
        if ($action === 'setup') {
            if (($body['confirmed_backup'] ?? false) !== true || ($body['confirmed_database'] ?? '') !== $pfDatabase) throw new PfValidationError('Konfirmasikan backup dan database tujuan sebelum persiapan.');
            $result = pf_setup($conn, $pfDatabase);
            pf_json(['success' => true, 'message' => 'Persiapan selesai. Ditambahkan ' . $result['counts']['rooms'] . ' pengaturan kamar, ' . $result['counts']['units'] . ' unit kelas, dan ' . $result['counts']['members'] . ' pemetaan. Data lama dipertahankan.', 'result' => $result]);
        }
        if ($action === 'save') {
            $saved = pf_save($conn, $pfDatabase, $pfActor, $body);
            pf_json(['success' => true, 'message' => $saved . ' pengaturan diperbarui. Status operasional ini bukan status kamar kosong/terisi.', 'saved' => $saved]);
        }
        throw new PfValidationError('Aksi tidak dikenali.');
    }
    if ($method !== 'GET' && $method !== 'HEAD') throw new PfValidationError('Metode tidak didukung.', 405);
    if ($pfApi) pf_json(['success' => true, 'state' => pf_read_state($conn, $pfDatabase)]);
    $pfState = pf_read_state($conn, $pfDatabase);
} catch (Throwable $e) {
    error_log('[Pengaturan Fasilitas] ' . $e->getMessage());
    $status = $e instanceof PfValidationError ? $e->httpStatus : 500;
    if ($pfApi || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') pf_json(['success' => false, 'message' => pf_friendly_error($e)], $status);
    pf_error_page('Pengaturan belum dapat dibuka', pf_friendly_error($e), $status);
}
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
$pfInitial = json_encode(['state' => $pfState, 'csrf' => $pfCsrf, 'admin_name' => (string)$pfAdmin['nama']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
if ($pfInitial === false) pf_error_page('Data belum dapat ditampilkan', 'Encoding data master tidak valid. Periksa encoding UTF-8 pada database.', 500);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>Pengaturan Fasilitas - Warga RT</title>
    <style>
        :root {
            --ink: #182635;
            --muted: #667687;
            --line: #dfe6eb;
            --pale: #f5f7f9;
            --accent: #075985;
            --good: #19664d;
            --warning: #8b5b12;
            --bad: #b42318
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: var(--pale);
            color: var(--ink);
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            font-size: 14px;
            line-height: 1.55
        }

        button,
        input,
        select,
        textarea {
            font: inherit
        }

        button,
        a,
        input,
        select,
        textarea {
            -webkit-tap-highlight-color: transparent
        }

        button {
            cursor: pointer
        }

        button:disabled {
            cursor: not-allowed;
            opacity: .52
        }

        a {
            color: var(--accent);
            text-decoration: none
        }

        a:hover {
            text-decoration: underline
        }

        button:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible,
        a:focus-visible {
            outline: 3px solid #60a5fa;
            outline-offset: 2px
        }

        [hidden] {
            display: none !important
        }

        h1,
        h2,
        h3,
        p {
            margin: 0
        }

        button svg,
        a svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0
        }

        button .spin {
            animation: spin .8s linear infinite
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        .appbar {
            background: white;
            border-bottom: 1px solid var(--line)
        }

        .appbar-inner {
            max-width: 1580px;
            margin: auto;
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px
        }

        .brand {
            display: flex;
            gap: 12px;
            align-items: center
        }

        .brandmark {
            background: #142e40;
            color: white;
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 13px;
            border-radius: 4px
        }

        .brand b {
            font-size: 14px;
            letter-spacing: .5px
        }

        .brand small {
            display: block;
            color: var(--muted);
            font-size: 11px
        }

        .session {
            display: flex;
            align-items: center;
            gap: 12px
        }

        .session-name {
            max-width: 260px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--muted);
            font-size: 12px
        }

        .role-label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            border: 1px solid var(--line);
            padding: 5px 8px;
            background: #f5f8fa
        }

        .wrap {
            max-width: 1580px;
            margin: auto;
            padding: 28px
        }

        .page-head {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            margin-bottom: 22px
        }

        .eyebrow {
            font-size: 11px;
            letter-spacing: 1.5px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 4px
        }

        h1 {
            font-size: 25px;
            letter-spacing: -.6px;
            line-height: 1.3
        }

        .subtitle {
            margin-top: 6px;
            font-size: 13px;
            color: var(--muted);
            max-width: 780px
        }

        .head-actions {
            display: flex;
            gap: 9px;
            flex-shrink: 0;
            align-items: center
        }

        .btn {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 14px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-size: 12px;
            font-weight: 700;
            border-radius: 4px;
            text-decoration: none;
            line-height: 1.4
        }

        .btn:hover {
            text-decoration: none;
            background: #f0f5f8
        }

        .btn.primary {
            background: #172e40;
            color: #fff;
            border-color: #172e40
        }

        .btn.primary:hover {
            background: #25455c
        }

        .btn.quiet {
            border-color: transparent;
            background: transparent;
            color: var(--accent)
        }

        .btn.small {
            min-height: 34px;
            padding: 6px 11px;
            font-size: 12px
        }

        .btn.danger {
            color: var(--bad);
            background: #fff7f6;
            border-color: #f6c9c3
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px
        }

        .metric {
            border: 1px solid var(--line);
            background: #fff;
            padding: 17px 18px;
            border-radius: 4px
        }

        .metric-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600
        }

        .metric-number {
            font-size: 28px;
            font-weight: 750;
            letter-spacing: -1px;
            line-height: 1.3;
            margin: 5px 0
        }

        .metric-foot {
            font-size: 11px;
            color: var(--muted)
        }

        .info-note {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 13px 15px;
            border-left: 3px solid #82abc2;
            background: #edf4f8;
            color: #345168;
            margin-bottom: 18px;
            font-size: 12px
        }

        .info-note strong {
            font-weight: 700
        }

        .info-symbol {
            font-weight: 800;
            border: 1px solid #82abc2;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            line-height: 16px;
            text-align: center;
            flex-shrink: 0;
            margin-top: 1px
        }

        .panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 4px
        }

        .tabs {
            display: flex;
            gap: 6px;
            padding: 13px 16px;
            border-bottom: 1px solid var(--line);
            align-items: center
        }

        .tab {
            background: transparent;
            border: 0;
            padding: 10px 15px;
            color: var(--muted);
            font-weight: 700;
            font-size: 13px;
            border-radius: 3px;
            min-height: 40px
        }

        .tab.active {
            background: #172e40;
            color: #fff
        }

        .tab-count {
            font-size: 11px;
            display: inline-block;
            margin-left: 7px;
            opacity: .72
        }

        .tab-right {
            margin-left: auto;
            font-size: 11px;
            color: var(--muted)
        }

        .filters {
            padding: 17px 18px;
            border-bottom: 1px solid var(--line);
            display: grid;
            grid-template-columns: minmax(180px, 2fr) repeat(4, minmax(130px, 1fr));
            gap: 12px
        }

        .field label,
        .dialog label.field-label {
            font-size: 11px;
            font-weight: 700;
            color: #425568;
            display: block;
            margin-bottom: 5px
        }

        .input {
            width: 100%;
            min-height: 41px;
            border: 1px solid #ccd6de;
            padding: 9px 10px;
            background: white;
            color: var(--ink);
            border-radius: 3px;
            min-width: 0
        }

        .input::placeholder {
            color: #8995a1
        }

        .input:disabled {
            background: #f5f7f9;
            color: #88929c
        }

        .input-small {
            font-size: 12px;
            min-height: 34px;
            padding: 6px 9px
        }

        .toolbar {
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border-bottom: 1px solid var(--line);
            font-size: 12px;
            color: var(--muted)
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap
        }

        .selected-text {
            color: var(--accent);
            font-weight: 700
        }

        .notice {
            font-size: 12px;
            margin: 16px 18px;
            padding: 12px 14px;
            border: 1px solid #f1deb1;
            background: #fffbf0;
            color: #76501b
        }

        .notice details summary {
            cursor: pointer;
            font-weight: 700
        }

        .notice ul {
            margin: 8px 0 0;
            padding-left: 20px
        }

        .table-wrap {
            overflow-x: auto
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            table-layout: fixed
        }

        .data-table th {
            font-size: 11px;
            font-weight: 700;
            color: #526477;
            text-transform: uppercase;
            letter-spacing: .3px;
            padding: 12px 13px;
            background: #f5f7f9;
            border-bottom: 1px solid var(--line)
        }

        .data-table td {
            padding: 13px;
            vertical-align: middle;
            border-bottom: 1px solid #edf0f3;
            font-size: 12px;
            overflow-wrap: anywhere
        }

        .data-table tbody tr:hover {
            background: #fbfcfe
        }

        .data-table .c-check {
            width: 46px;
            text-align: center
        }

        .data-table .c-name {
            width: 22%
        }

        .data-table .c-location {
            width: 20%
        }

        .data-table .c-kind {
            width: 16%
        }

        .data-table .c-status {
            width: 18%
        }

        .data-table .c-capacity {
            width: 95px
        }

        .data-table .c-action {
            width: 90px
        }

        .row-title {
            font-size: 13px;
            font-weight: 750;
            line-height: 1.5
        }

        .row-sub {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px
        }

        .row-note {
            font-size: 10px;
            color: #8b5b12;
            margin-top: 5px
        }

        .row-sub.members {
            color: #075985
        }

        input[type=checkbox] {
            width: 17px;
            height: 17px;
            accent-color: #172e40;
            vertical-align: middle;
            cursor: pointer;
            flex-shrink: 0
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 7px;
            border: 1px solid #e1e5e8;
            border-radius: 3px;
            white-space: nowrap
        }

        .badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor
        }

        .badge.active {
            color: #186346;
            background: #f0faf4;
            border-color: #cce5d5
        }

        .badge.pending {
            color: #895c16;
            background: #fff9ec;
            border-color: #efdfbb
        }

        .badge.inactive {
            color: #66717e;
            background: #f4f5f7;
            border-color: #dfe3e9
        }

        .badge.invalid {
            color: #ae332b;
            background: #fff3f1;
            border-color: #f2cfc9
        }

        .empty {
            padding: 50px 22px;
            text-align: center;
            color: var(--muted)
        }

        .empty b {
            display: block;
            color: var(--ink);
            font-size: 15px;
            margin-bottom: 6px
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 18px;
            gap: 15px;
            color: var(--muted);
            font-size: 12px
        }

        .pager-buttons {
            display: flex;
            align-items: center;
            gap: 8px
        }

        .page-label {
            min-width: 90px;
            text-align: center
        }

        .page-size {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap
        }

        .page-size select {
            width: 74px
        }

        .setup {
            padding: 24px
        }

        .setup-head {
            max-width: 880px;
            margin-bottom: 22px
        }

        .setup-head h2 {
            font-size: 19px;
            margin-bottom: 6px
        }

        .setup-head p {
            color: var(--muted);
            font-size: 13px
        }

        .setup-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 1fr);
            gap: 24px
        }

        .setup-box {
            border: 1px solid var(--line);
            padding: 20px;
            border-radius: 4px
        }

        .setup-box h3 {
            font-size: 14px;
            margin-bottom: 13px
        }

        .setup-box p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 10px
        }

        .setup-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px
        }

        .setup-table td {
            padding: 10px 0;
            border-bottom: 1px solid #edf1f4;
            vertical-align: middle;
            overflow-wrap: anywhere
        }

        .setup-table td:last-child {
            text-align: right;
            padding-left: 12px
        }

        .db-name {
            font: 12px ui-monospace, Consolas, monospace;
            background: #f2f5f7;
            padding: 5px 8px;
            border-radius: 3px;
            color: #35536c;
            display: inline-block;
            overflow-wrap: anywhere
        }

        .source-count {
            font-size: 18px;
            font-weight: 750;
            color: #172e40
        }

        .setup-action {
            margin-top: 22px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap
        }

        .setup-help {
            font-size: 12px;
            color: var(--muted);
            max-width: 750px;
            line-height: 1.65
        }

        .mapping-rule {
            display: grid;
            grid-template-columns: 1fr 25px 1fr;
            gap: 8px;
            align-items: center;
            padding: 9px 0;
            border-bottom: 1px solid #edf0f3;
            font-size: 12px
        }

        .mapping-rule strong {
            color: #075985
        }

        .footer-note {
            font-size: 11px;
            color: var(--muted);
            padding: 18px 1px 12px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap
        }

        .security-hint {
            color: #385268
        }

        .status-message {
            margin-bottom: 15px;
            border: 1px solid #cce3d5;
            background: #f3faf5;
            padding: 13px 16px;
            font-size: 13px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px
        }

        .status-message.error {
            border-color: #f0c7c1;
            background: #fff5f3;
            color: #982b22
        }

        .status-message button {
            background: none;
            border: 0;
            color: inherit;
            font-size: 18px;
            line-height: 1.1
        }

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(17, 31, 46, .48);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto
        }

        .dialog {
            width: 100%;
            max-width: 560px;
            max-height: calc(100vh - 40px);
            background: #fff;
            border: 1px solid #cbd5df;
            border-radius: 6px;
            box-shadow: 0 20px 70px #13253840;
            display: flex;
            flex-direction: column
        }

        .dialog-head {
            padding: 20px 22px 16px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 16px
        }

        .dialog-head h2 {
            font-size: 19px;
            letter-spacing: -.3px
        }

        .dialog-head p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 5px
        }

        .close-modal {
            border: 0;
            background: transparent;
            color: #677989;
            font-size: 24px;
            width: 30px;
            height: 30px;
            line-height: 1
        }

        .dialog-body {
            padding: 20px 22px;
            overflow: auto
        }

        .dialog-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px
        }

        .dialog-grid .wide {
            grid-column: 1/-1
        }

        .field-help {
            font-size: 11px;
            color: var(--muted);
            margin-top: 5px
        }

        .verify-box {
            display: flex;
            gap: 10px;
            font-size: 12px;
            padding: 12px;
            background: #f5f8fa;
            border: 1px solid var(--line);
            margin-top: 18px;
            align-items: flex-start
        }

        .verify-box input {
            margin-top: 2px
        }

        .verify-box b {
            display: block;
            margin-bottom: 3px
        }

        .dialog-actions {
            padding: 16px 22px;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: flex-end;
            gap: 10px
        }

        .modal-error {
            padding: 11px 12px;
            border: 1px solid #edcac5;
            background: #fff6f4;
            color: #9b2c22;
            font-size: 12px;
            margin-bottom: 15px
        }

        .pending-bar {
            font-size: 12px;
            padding: 10px 14px;
            background: #fffbef;
            color: #7b591f;
            border: 1px solid #ebddb9;
            margin-bottom: 14px
        }

        .setup-disclaimer {
            font-size: 12px;
            line-height: 1.7;
            color: #5f7181;
            margin-top: 12px
        }

        .setup-disclaimer strong {
            color: #374e61
        }

        .detail-list {
            font-size: 12px;
            padding: 12px;
            background: #f7f9fb;
            border: 1px solid var(--line);
            margin-bottom: 15px;
            overflow-wrap: anywhere
        }

        .detail-list b {
            display: block;
            margin-bottom: 3px;
            color: #244b65
        }

        .small-label {
            font-size: 11px;
            color: var(--muted);
            display: block;
            margin-top: 12px
        }

        .state-reading {
            opacity: .55;
            pointer-events: none
        }

        @media(max-width:1150px) {
            .filters {
                grid-template-columns: repeat(4, minmax(0, 1fr))
            }

            .filter-search {
                grid-column: 1/-1
            }

            .wrap {
                padding: 22px
            }

            .head-actions {
                align-self: flex-start
            }
        }

        @media(max-width:950px) {
            .appbar-inner {
                padding: 13px 18px
            }

            .wrap {
                padding: 20px 16px
            }

            .page-head {
                align-items: flex-start;
                flex-direction: column;
                gap: 16px
            }

            .summary {
                gap: 10px
            }

            .metric {
                padding: 14px
            }

            .metric-number {
                font-size: 25px
            }

            .data-table {
                display: block
            }

            .data-table thead {
                display: none
            }

            .data-table tbody {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                padding: 15px
            }

            .data-table tr {
                display: grid;
                grid-template-columns: 1fr 1fr;
                position: relative;
                border: 1px solid var(--line);
                border-radius: 4px;
                background: #fff;
                padding: 14px;
                align-content: start;
                gap: 12px
            }

            .data-table td {
                display: block;
                border: 0;
                padding: 0;
                width: auto !important;
                font-size: 12px
            }

            .data-table td::before {
                content: attr(data-label);
                display: block;
                font-size: 10px;
                font-weight: 700;
                color: var(--muted);
                margin-bottom: 3px
            }

            .data-table td.c-check {
                position: absolute;
                top: 13px;
                right: 13px;
                width: 22px !important
            }

            .data-table td.c-check::before,
            .data-table td.c-name::before {
                display: none
            }

            .data-table td.c-name {
                grid-column: 1/-1;
                padding-right: 34px;
                min-height: 43px
            }

            .data-table td.c-action {
                align-self: end;
                display: flex;
                justify-content: flex-end
            }

            .data-table td.c-action::before {
                display: none
            }

            .data-table .row-title {
                font-size: 14px
            }

            .table-wrap {
                overflow: visible
            }

            .setup-grid {
                grid-template-columns: 1fr
            }

            .tab-right {
                display: none
            }

            .pagination {
                flex-wrap: wrap
            }
        }

        @media(max-width:600px) {
            body {
                font-size: 13px
            }

            .session-name {
                display: none
            }

            .wrap {
                padding: 18px 12px
            }

            .appbar-inner {
                padding: 12px
            }

            .brandmark {
                width: 34px;
                height: 34px
            }

            .brand small {
                font-size: 10px
            }

            h1 {
                font-size: 22px
            }

            .subtitle {
                font-size: 12px
            }

            .page-head {
                margin-bottom: 16px
            }

            .head-actions {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr
            }

            .summary {
                grid-template-columns: 1fr 1fr
            }

            .metric {
                padding: 13px 12px
            }

            .metric-label {
                font-size: 11px
            }

            .metric-number {
                font-size: 25px
            }

            .metric-foot {
                font-size: 10px;
                line-height: 1.45
            }

            .tabs {
                padding: 10px;
                gap: 5px
            }

            .tab {
                flex: 1;
                padding: 9px 8px;
                font-size: 12px
            }

            .tab-count {
                margin-left: 4px
            }

            .filters {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                padding: 14px
            }

            .input {
                font-size: 16px;
                min-height: 43px
            }

            .filters .input {
                font-size: 13px
            }

            .filter-search {
                grid-column: 1/-1
            }

            .toolbar {
                padding: 12px 14px;
                gap: 12px
            }

            .toolbar-actions {
                width: 100%
            }

            .toolbar-actions .btn {
                flex: 1
            }

            .data-table tbody {
                grid-template-columns: 1fr;
                padding: 12px;
                gap: 10px
            }

            .row-sub {
                font-size: 11px
            }

            .info-note {
                padding: 12px;
                font-size: 11px
            }

            .pagination {
                justify-content: center;
                gap: 13px;
                padding: 14px
            }

            .page-summary {
                width: 100%;
                text-align: center
            }

            .page-size {
                font-size: 11px
            }

            .pager-buttons {
                flex: 1;
                justify-content: center
            }

            .page-label {
                min-width: 60px;
                font-size: 11px
            }

            .pager-buttons .btn {
                padding: 7px 10px
            }

            .setup {
                padding: 16px
            }

            .setup-box {
                padding: 16px
            }

            .setup-head h2 {
                font-size: 17px
            }

            .setup-grid {
                gap: 14px
            }

            .setup-action .btn {
                width: 100%
            }

            .footer-note {
                font-size: 10px
            }

            .modal {
                padding: 10px
            }

            .dialog {
                max-height: calc(100vh - 20px)
            }

            .dialog-head,
            .dialog-body {
                padding: 17px
            }

            .dialog-actions {
                padding: 14px 17px
            }

            .dialog-actions .btn {
                flex: 1
            }

            .dialog-grid {
                grid-template-columns: 1fr
            }

            .dialog-grid .wide {
                grid-column: auto
            }

            .verify-box {
                font-size: 11px
            }

            .source-count {
                font-size: 17px
            }
        }

        @media print {

            .appbar,
            .head-actions,
            .filters,
            .toolbar,
            .pagination,
            .modal,
            .c-check,
            .c-action {
                display: none !important
            }

            .wrap {
                max-width: none;
                padding: 0
            }

            .panel {
                border: 0
            }

            .data-table {
                display: table
            }

            .data-table tbody {
                display: table-row-group
            }

            .data-table tr {
                display: table-row
            }

            .data-table td {
                display: table-cell
            }

            .data-table td::before {
                display: none
            }

            .data-table thead {
                display: table-header-group
            }

            body {
                background: #fff
            }
        }
    </style>
</head>

<body>
    <header class="appbar">
        <div class="appbar-inner">
            <div class="brand"><span class="brandmark" aria-hidden="true">RT</span>
                <div><b>WARGA RT</b><small>Administrasi fasilitas</small></div>
            </div>
            <div class="session"><span class="session-name"><?= pf_h($pfAdmin['nama']) ?></span><span class="role-label">ADMIN</span></div>
        </div>
    </header>
    <main class="wrap">
        <div class="page-head">
            <div>
                <p class="eyebrow">MASTER &amp; PENGATURAN</p>
                <h1>Pengaturan Fasilitas</h1>
                <p class="subtitle">Periksa kamar menginap dan kelas operasional dari data master yang sudah ada.</p>
            </div>
            <div class="head-actions"><a class="btn" href="beranda.php"><span aria-hidden="true">&larr;</span> Beranda</a><button class="btn" id="reloadBtn" type="button">Muat Ulang Data</button></div>
        </div>
        <div id="message" class="status-message" role="status" aria-live="polite" hidden><span id="messageText"></span><button id="messageClose" type="button" aria-label="Tutup pemberitahuan">&times;</button></div>
        <div class="summary" id="metrics"></div>
        <div class="info-note"><span class="info-symbol" aria-hidden="true">i</span>
            <div><strong>Aktif tidak berarti kosong.</strong> Halaman ini mengatur fasilitas yang digunakan dalam operasional, bukan mengubah check-in/check-out. Angka di Beranda belum otomatis berubah; perhitungan pemakaian akan disambungkan pada tahap berikutnya.</div>
        </div>
        <section class="panel" aria-label="Pengaturan kamar dan kelas">
            <nav class="tabs" aria-label="Bagian pengaturan"><button class="tab active" data-tab="kamar" type="button">Kamar <span class="tab-count" id="roomTabCount"></span></button><button class="tab" data-tab="kelas" type="button">Kelas <span class="tab-count" id="classTabCount"></span></button><button class="tab" data-tab="persiapan" type="button">Persiapan</button><span class="tab-right" id="lastUpdated"></span></nav>
            <div id="setupPane" class="setup" hidden>
                <div class="setup-head">
                    <h2 id="setupTitle">Siapkan data, tanpa mengetik ulang.</h2>
                    <p>Persiapan hanya menambah pengaturan fasilitas dan pemetaan kelas. Master asli tetap menjadi sumber data.</p>
                </div>
                <div class="setup-grid">
                    <div class="setup-box">
                        <h3>Database &amp; kesiapan tabel</h3><span class="db-name" id="databaseName"></span>
                        <table class="setup-table" aria-label="Kesiapan tabel">
                            <tbody id="schemaRows"></tbody>
                        </table>
                        <div id="schemaErrors" class="modal-error" hidden></div>
                        <p>Jika SQL persiapan sebelumnya sudah diimpor, tabel yang sesuai langsung dipakai. Struktur yang berbeda tidak ditimpa otomatis.</p>
                    </div>
                    <div class="setup-box">
                        <h3>Data master yang terbaca</h3>
                        <table class="setup-table">
                            <tbody id="sourceRows"></tbody>
                        </table>
                        <p>Usulan kamar belum menjamin kamar layak atau siap dihuni. Kondisi awal seluruh pengaturan baru: <strong>Belum diverifikasi</strong>.</p>
                    </div>
                </div>
                <div class="setup-grid" style="margin-top:20px">
                    <div class="setup-box">
                        <h3>Penggabungan kelas yang disepakati</h3>
                        <div class="mapping-rule"><span>Kelas 1 + Kelas 2</span><span>&rarr;</span><strong>Kelas 1/2</strong></div>
                        <div class="mapping-rule"><span>Kelas 6 + Kelas 7</span><span>&rarr;</span><strong>Kelas 6/7</strong></div>
                        <div class="mapping-rule"><span>Kelas 9 + Kelas 10</span><span>&rarr;</span><strong>Kelas 9/10</strong></div>
                        <p>Gabungan hanya dibentuk apabila kedua anggota ada pada lantai yang sama dan tidak bertabrakan dengan pemetaan lama. ID/nama pada master tidak dihapus.</p>
                    </div>
                    <div class="setup-box">
                        <h3>Batas tahap persiapan</h3>
                        <p>Hanya kelas bernomor yang dipetakan otomatis. Laboratorium, auditorium, dan ruang rapat tidak ditambahkan ke jumlah kelas reguler secara otomatis.</p>
                        <p>Status <strong>Nonaktif</strong> dapat dipakai untuk fasilitas di luar layanan disertai catatan. Periode blokir, reservasi, dan ketersediaan belum dikelola di halaman ini.</p>
                        <p>Tidak ada perubahan otomatis pada peserta, agenda, histori, atau Beranda.</p>
                    </div>
                </div>
                <div class="setup-action"><button class="btn primary" id="setupBtn" type="button">Siapkan Data Fasilitas</button><span class="setup-help" id="setupHelp">Buat backup database terlebih dahulu. Persiapan baru dijalankan setelah konfirmasi admin.</span></div>
            </div>
            <div id="dataPane">
                <div class="filters">
                    <div class="field filter-search"><label for="searchInput">Pencarian</label><input class="input" type="search" id="searchInput" placeholder="Cari nomor kamar, nama kelas, atau gedung..." autocomplete="off"></div>
                    <div class="field"><label for="locationFilter">Gedung / asrama</label><select class="input" id="locationFilter">
                            <option value="">Semua gedung</option>
                        </select></div>
                    <div class="field"><label for="floorFilter">Lantai</label><select class="input" id="floorFilter">
                            <option value="">Semua lantai</option>
                        </select></div>
                    <div class="field"><label for="categoryFilter" id="categoryFilterLabel">Kategori</label><select class="input" id="categoryFilter"></select></div>
                    <div class="field"><label for="statusFilter">Status</label><select class="input" id="statusFilter">
                            <option value="">Semua status</option>
                            <option value="belum_diverifikasi">Belum diverifikasi</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select></div>
                </div>
                <div class="toolbar">
                    <div><span id="selectedCount" class="selected-text">0 dipilih</span><span id="resultCount"></span></div>
                    <div class="toolbar-actions"><button class="btn small" id="selectPageBtn" type="button">Pilih halaman ini</button><button class="btn small quiet" id="selectAllBtn" type="button">Pilih semua hasil</button><button class="btn small quiet" id="clearSelectionBtn" type="button" hidden>Batal pilih</button><button class="btn small primary" id="bulkBtn" type="button" disabled>Ubah Terpilih</button></div>
                </div>
                <div id="newDataNotice" class="notice" hidden></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="c-check"><input id="pageCheckbox" type="checkbox" aria-label="Pilih semua pada halaman ini"></th>
                                <th class="c-name" id="nameHeading">Kamar</th>
                                <th class="c-location">Gedung &amp; lantai</th>
                                <th class="c-kind" id="kindHeading">Kategori</th>
                                <th class="c-status">Status operasional</th>
                                <th class="c-capacity">Kapasitas</th>
                                <th class="c-action">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
                <div class="empty" id="emptyState" hidden><b>Belum ada data yang cocok</b>
                    <p>Ubah pencarian atau filter untuk melihat fasilitas lainnya.</p>
                </div>
                <div class="pagination"><span class="page-summary" id="pageSummary"></span>
                    <div class="page-size"><label for="pageSize">Per halaman</label><select class="input input-small" id="pageSize">
                            <option>25</option>
                            <option>50</option>
                            <option>100</option>
                        </select></div>
                    <div class="pager-buttons"><button class="btn small" id="prevBtn" type="button">&larr; Sebelumnya</button><span class="page-label" id="pageLabel"></span><button class="btn small" id="nextBtn" type="button">Berikutnya &rarr;</button></div>
                </div>
            </div>
            <div id="warningsPanel" class="notice" hidden>
                <details>
                    <summary id="warningsSummary"></summary>
                    <ul id="warningsList"></ul>
                </details>
            </div>
        </section>
        <div class="footer-note"><span class="security-hint">Akses admin &middot; Verifikasi disimpan per fasilitas &middot; Zona waktu WIB</span><span>Pengaturan Fasilitas v1.0</span></div>
        <noscript>
            <div class="notice">Aktifkan JavaScript untuk menggunakan tombol persiapan dan penyimpanan. Tanpa JavaScript, tidak ada perubahan database yang dilakukan.</div>
        </noscript>
    </main>
    <div class="modal" id="editModal" role="dialog" aria-modal="true" aria-labelledby="editTitle" hidden>
        <form class="dialog" id="editForm">
            <div class="dialog-head">
                <div>
                    <h2 id="editTitle">Ubah fasilitas</h2>
                    <p id="editSubtitle"></p>
                </div><button class="close-modal" type="button" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="dialog-body">
                <div class="modal-error" id="editError" role="alert" hidden></div>
                <div class="detail-list" id="editMembers" hidden></div>
                <div class="dialog-grid">
                    <div class="field wide" id="nameField" hidden><label for="editName">Nama kelas operasional</label><input class="input" id="editName" maxlength="100"></div>
                    <div class="field"><label for="editCategory" id="editCategoryLabel">Kategori</label><select class="input" id="editCategory"></select></div>
                    <div class="field"><label for="editStatus">Status operasional</label><select class="input" id="editStatus">
                            <option value="belum_diverifikasi">Belum diverifikasi</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select></div>
                    <div class="field wide"><label for="editCapacity">Kapasitas orang (opsional)</label><input class="input" id="editCapacity" type="number" min="1" max="65535" step="1" inputmode="numeric" placeholder="Kosongkan jika belum diketahui">
                        <p class="field-help">Bukan jumlah penghuni atau tempat tidur yang sedang kosong.</p>
                    </div>
                    <div class="field wide"><label for="editNote">Catatan</label><textarea class="input" id="editNote" rows="3" maxlength="500" placeholder="Contoh: sedang diperbaiki atau bukan kamar menginap."></textarea></div>
                </div>
                <label class="verify-box"><input type="checkbox" id="editConfirmed"><span><b>Saya sudah memeriksa fasilitas ini.</b>Wajib dicentang untuk menyimpan status Aktif atau Nonaktif. Aktif berarti termasuk fasilitas operasional, bukan berarti kosong.</span></label>
                <small id="editAudit" class="small-label"></small>
            </div>
            <div class="dialog-actions"><button class="btn" type="button" data-close>Batal</button><button class="btn primary" id="editSubmit" type="submit">Simpan Pengaturan</button></div>
        </form>
    </div>
    <div class="modal" id="bulkModal" role="dialog" aria-modal="true" aria-labelledby="bulkTitle" hidden>
        <form class="dialog" id="bulkForm">
            <div class="dialog-head">
                <div>
                    <h2 id="bulkTitle">Ubah fasilitas terpilih</h2>
                    <p id="bulkSubtitle"></p>
                </div><button class="close-modal" type="button" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="dialog-body">
                <div class="modal-error" id="bulkError" role="alert" hidden></div>
                <div class="pending-bar">Perubahan berlaku pada seluruh ID yang dipilih, termasuk yang berada di halaman berikutnya.</div>
                <div class="dialog-grid">
                    <div class="field"><label for="bulkCategory" id="bulkCategoryLabel">Kategori</label><select class="input" id="bulkCategory"></select></div>
                    <div class="field"><label for="bulkStatus">Status operasional</label><select class="input" id="bulkStatus">
                            <option value="">Jangan ubah status</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                            <option value="belum_diverifikasi">Belum diverifikasi</option>
                        </select></div>
                    <div class="field"><label for="bulkCapacityMode">Kapasitas orang</label><select class="input" id="bulkCapacityMode">
                            <option value="keep">Jangan ubah kapasitas</option>
                            <option value="set">Isi kapasitas yang sama</option>
                            <option value="clear">Kosongkan kapasitas</option>
                        </select></div>
                    <div class="field"><label for="bulkCapacity">Jumlah orang</label><input class="input" type="number" min="1" max="65535" step="1" id="bulkCapacity" inputmode="numeric" disabled></div>
                    <div class="field wide"><label for="bulkNote">Catatan (kosong = pertahankan catatan lama)</label><textarea class="input" id="bulkNote" maxlength="500" rows="3"></textarea></div>
                </div>
                <label class="verify-box"><input type="checkbox" id="bulkConfirmed"><span><b>Saya telah memeriksa seluruh fasilitas yang dipilih.</b>Wajib jika menyimpan status Aktif/Nonaktif. Untuk kategori Bukan kamar, gunakan Nonaktif dan kosongkan kapasitas.</span></label>
            </div>
            <div class="dialog-actions"><button class="btn" type="button" data-close>Batal</button><button class="btn primary" id="bulkSubmit" type="submit">Simpan Terpilih</button></div>
        </form>
    </div>
    <div class="modal" id="setupModal" role="dialog" aria-modal="true" aria-labelledby="setupDialogTitle" hidden>
        <form class="dialog" id="setupForm">
            <div class="dialog-head">
                <div>
                    <h2 id="setupDialogTitle">Konfirmasi persiapan</h2>
                    <p>Perubahan hanya pada tabel pengaturan fasilitas.</p>
                </div><button class="close-modal" type="button" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="dialog-body">
                <div class="modal-error" id="setupError" role="alert" hidden></div>
                <div class="detail-list"><b>Database tujuan</b><span id="confirmDatabase" class="db-name"></span></div>
                <p style="font-size:13px">Tiga tabel tambahan akan dibuat jika belum ada. Data master yang belum memiliki pengaturan akan ditambahkan sebagai <strong>Belum diverifikasi</strong>.</p>
                <label class="verify-box"><input id="backupConfirmed" type="checkbox" required><span><b>Backup sudah disiapkan, dan database tujuan sudah benar.</b>Halaman ini tidak membuat backup otomatis. Uji pada salinan database terlebih dahulu.</span></label>
                <p class="setup-disclaimer">Pengaturan yang sudah ada tidak direset. <strong>Master, peserta, agenda, dan Beranda tidak diubah.</strong> Bila persiapan berhenti di tengah proses, tabel kosong yang sudah dibuat dapat tetap ada. Jangan hapus tabel lama; catat pesan kesalahan dan lanjutkan persiapan setelah penyebabnya diperbaiki.</p>
            </div>
            <div class="dialog-actions"><button class="btn" type="button" data-close>Batal</button><button class="btn primary" type="submit" id="setupSubmit">Jalankan Persiapan</button></div>
        </form>
    </div>
    <script id="pf-initial" type="application/json">
        <?= $pfInitial ?>
    </script>
    <script>
        (function() {
            'use strict';
            var initial = JSON.parse(document.getElementById('pf-initial').textContent);
            var state = initial.state;
            var activeTab = state.schema.ready ? 'kamar' : 'persiapan';
            var selected = new Set(),
                page = 1,
                pageSize = 25,
                editing = null,
                busy = false,
                previousFocus = null;
            var categories = {
                kamar_inap: 'Kamar menginap',
                bukan_kamar: 'Bukan kamar',
                perlu_verifikasi: 'Perlu verifikasi'
            };
            var kinds = {
                kelas: 'Kelas reguler',
                laboratorium: 'Laboratorium',
                auditorium: 'Auditorium',
                ruang_rapat: 'Ruang rapat',
                lainnya: 'Lainnya'
            };
            var statuses = {
                belum_diverifikasi: 'Belum diverifikasi',
                aktif: 'Aktif',
                nonaktif: 'Nonaktif'
            };

            function el(id) {
                return document.getElementById(id);
            }

            function escapeText(v) {
                return String(v == null ? '' : v).replace(/[&<>"']/g, function(c) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    } [c];
                });
            }

            function num(n) {
                return Number(n || 0).toLocaleString('id-ID');
            }

            function option(value, text) {
                return '<option value="' + escapeText(value) + '">' + escapeText(text) + '</option>';
            }

            function options(map, allLabel) {
                return (allLabel !== null ? option('', allLabel) : '') + Object.keys(map).map(function(k) {
                    return option(k, map[k]);
                }).join('');
            }

            function message(text, error) {
                el('messageText').textContent = text;
                el('message').classList.toggle('error', !!error);
                el('message').hidden = false;
            }

            function modalError(id, text) {
                el(id).textContent = text;
                el(id).hidden = false;
            }

            function sourceRows() {
                return activeTab === 'kelas' ? state.classes : state.rooms;
            }

            function label(r) {
                return activeTab === 'kelas' ? r.nama_kelas : ('Kamar / entri ' + (r.nomor_kamar || '#' + r.id));
            }

            function badge(status) {
                return '<span class="badge ' + (status === 'aktif' ? 'active' : status === 'nonaktif' ? 'inactive' : 'pending') + '">' + escapeText(statuses[status] || status) + '</span>';
            }

            function isRoomActive(r) {
                return r.kategori === 'kamar_inap' && r.status_operasional === 'aktif' && r.source_valid;
            }

            function metrics() {
                var pendingRooms = state.rooms.filter(function(r) {
                    return r.status_operasional === 'belum_diverifikasi';
                }).length;
                var pendingClasses = state.classes.filter(function(r) {
                    return r.status_operasional === 'belum_diverifikasi';
                }).length;
                var specs = state.schema.ready ? [
                    ['Entri kamar terdaftar', state.rooms.length, num(pendingRooms) + ' belum diverifikasi'],
                    ['Kamar aktif terverifikasi', state.rooms.filter(isRoomActive).length, 'Bukan jumlah kamar kosong'],
                    ['Unit kelas terdaftar', state.classes.filter(function(r) {
                        return r.jenis === 'kelas';
                    }).length, 'Termasuk kelas gabungan'],
                    ['Kelas aktif terverifikasi', state.classes.filter(function(r) {
                        return r.jenis === 'kelas' && r.status_operasional === 'aktif' && r.source_valid;
                    }).length, num(pendingClasses) + ' unit belum diverifikasi']
                ] : [
                    ['Entri master kamar', state.source.room_entries, 'Sumber database, bukan kamar siap'],
                    ['Usulan kamar menginap', state.source.room_candidates, 'Perlu diperiksa admin'],
                    ['Entri kelas bernomor', state.source.class_entries, 'Sumber master ruangan'],
                    ['Usulan kelas operasional', state.source.class_units, 'Setelah penggabungan lengkap']
                ];
                el('metrics').innerHTML = specs.map(function(s) {
                    return '<div class="metric"><div class="metric-label">' + escapeText(s[0]) + '</div><div class="metric-number">' + num(s[1]) + '</div><div class="metric-foot">' + escapeText(s[2]) + '</div></div>';
                }).join('');
            }

            function setupPanel() {
                el('databaseName').textContent = state.database;
                el('confirmDatabase').textContent = state.database;
                var names = {
                    fasilitas_kamar_pengaturan: 'Pengaturan kamar',
                    fasilitas_kelas_operasional: 'Kelas operasional',
                    fasilitas_kelas_anggota: 'Pemetaan anggota kelas'
                };
                el('schemaRows').innerHTML = Object.keys(state.schema.tables).map(function(k) {
                    var s = state.schema.tables[k];
                    return '<tr><td>' + escapeText(names[k]) + '<div class="row-sub">' + escapeText(k) + '</div></td><td><span class="badge ' + (s === 'siap' ? 'active' : s === 'belum_ada' ? 'pending' : 'invalid') + '">' + (s === 'siap' ? 'Siap' : s === 'belum_ada' ? 'Belum ada' : 'Perlu diperiksa') + '</span></td></tr>';
                }).join('');
                el('sourceRows').innerHTML = [
                    ['Entri master kamar', state.source.room_entries],
                    ['Usulan kamar menginap', state.source.room_candidates],
                    ['Kelas bernomor pada master', state.source.class_entries],
                    ['Unit setelah gabungan lengkap', state.source.class_units]
                ].map(function(x) {
                    return '<tr><td>' + escapeText(x[0]) + '</td><td class="source-count">' + num(x[1]) + '</td></tr>';
                }).join('');
                el('schemaErrors').hidden = state.schema.issues.length === 0;
                el('schemaErrors').textContent = state.schema.issues.join(' ');
                el('setupBtn').disabled = state.schema.issues.length > 0 || busy;
                el('setupBtn').textContent = state.schema.ready ? 'Sinkronkan Master Baru' : 'Siapkan Data Fasilitas';
                el('setupTitle').textContent = state.schema.ready ? 'Pengaturan siap diperiksa.' : 'Siapkan data, tanpa mengetik ulang.';
                el('setupHelp').textContent = state.schema.ready ? 'Sinkronisasi menambah entri baru tanpa menimpa status, kapasitas, catatan, atau pemetaan yang sudah diperiksa.' : 'Buat backup database terlebih dahulu. Persiapan baru dijalankan setelah konfirmasi admin.';
            }

            function renderWarnings() {
                el('warningsPanel').hidden = !state.warnings.length;
                el('warningsSummary').textContent = num(state.warnings.length) + ' catatan master / pemetaan perlu diperiksa';
                el('warningsList').innerHTML = state.warnings.map(function(w) {
                    return '<li>' + escapeText(w) + '</li>';
                }).join('');
            }

            function rebuildFilters(reset) {
                var rows = sourceRows(),
                    locations = {};
                rows.forEach(function(r) {
                    if (r.lokasi_id != null) locations[String(r.lokasi_id)] = r.nama_lokasi;
                });
                var oldLocation = reset ? '' : el('locationFilter').value;
                el('locationFilter').innerHTML = option('', 'Semua gedung') + Object.keys(locations).sort(function(a, b) {
                    return String(locations[a]).localeCompare(String(locations[b]));
                }).map(function(k) {
                    return option(k, locations[k]);
                }).join('');
                if (locations[oldLocation]) el('locationFilter').value = oldLocation;
                rebuildFloors(reset);
                var map = activeTab === 'kelas' ? kinds : categories;
                var oldCategory = reset ? '' : el('categoryFilter').value;
                el('categoryFilter').innerHTML = options(map, 'Semua kategori');
                if (map[oldCategory]) el('categoryFilter').value = oldCategory;
                el('categoryFilterLabel').textContent = activeTab === 'kelas' ? 'Jenis ruang' : 'Kategori';
                if (reset) {
                    el('searchInput').value = '';
                    el('statusFilter').value = '';
                }
            }

            function rebuildFloors(reset) {
                var floors = {},
                    loc = el('locationFilter').value,
                    old = reset ? '' : el('floorFilter').value;
                sourceRows().forEach(function(r) {
                    if ((!loc || String(r.lokasi_id) === loc) && r.lantai_id != null) floors[String(r.lantai_id)] = (loc ? '' : (r.nama_lokasi || '-') + ' - ') + (r.nama_lantai || 'Lantai #' + r.lantai_id);
                });
                el('floorFilter').innerHTML = option('', 'Semua lantai') + Object.keys(floors).map(function(k) {
                    return option(k, floors[k]);
                }).join('');
                if (floors[old]) el('floorFilter').value = old;
            }

            function filtered() {
                var q = el('searchInput').value.trim().toLowerCase(),
                    loc = el('locationFilter').value,
                    floor = el('floorFilter').value,
                    cat = el('categoryFilter').value,
                    status = el('statusFilter').value;
                return sourceRows().filter(function(r) {
                    if (loc && String(r.lokasi_id) !== loc) return false;
                    if (floor && String(r.lantai_id) !== floor) return false;
                    if (cat && (activeTab === 'kelas' ? r.jenis : r.kategori) !== cat) return false;
                    if (status && r.status_operasional !== status) return false;
                    if (!q) return true;
                    var words = [r.nomor_kamar, r.nama_kelas, r.kode, r.nama_lokasi, r.nama_lantai, r.catatan];
                    (r.members || []).forEach(function(m) {
                        words.push(m.nama_ruangan);
                    });
                    return words.join(' ').toLowerCase().indexOf(q) !== -1;
                });
            }

            function renderTable() {
                if (activeTab === 'persiapan') return;
                var results = filtered(),
                    pages = Math.max(1, Math.ceil(results.length / pageSize));
                page = Math.min(page, pages);
                var slice = results.slice((page - 1) * pageSize, page * pageSize);
                el('nameHeading').textContent = activeTab === 'kelas' ? 'Kelas operasional' : 'Kamar / entri';
                el('kindHeading').textContent = activeTab === 'kelas' ? 'Jenis ruang' : 'Kategori';
                el('rows').innerHTML = slice.map(function(r) {
                    var isClass = activeTab === 'kelas';
                    var title = isClass ? r.nama_kelas : (r.nomor_kamar == null ? 'Master tidak ditemukan' : r.nomor_kamar);
                    var members = isClass ? (r.members || []).map(function(m) {
                        return m.nama_ruangan || 'ID ' + m.master_ruangan_id;
                    }).join(' + ') : '';
                    var secondary = isClass ? members : 'ID master: ' + r.id;
                    var kindValue = isClass ? (kinds[r.jenis] || r.jenis) : (categories[r.kategori] || r.kategori);
                    return '<tr><td class="c-check"><input type="checkbox" class="row-check" data-id="' + r.id + '" aria-label="Pilih ' + escapeText(title) + '" ' + (selected.has(r.id) ? 'checked' : '') + '></td>' +
                        '<td class="c-name" data-label="Fasilitas"><div class="row-title">' + escapeText(title) + '</div><div class="row-sub ' + (isClass ? 'members' : '') + '">' + escapeText(secondary) + '</div></td>' +
                        '<td class="c-location" data-label="Gedung / lantai"><strong>' + escapeText(r.nama_lokasi || 'Master lokasi tidak ditemukan') + '</strong><div class="row-sub">' + escapeText(r.nama_lantai || '-') + '</div></td>' +
                        '<td class="c-kind" data-label="' + (isClass ? 'Jenis' : 'Kategori') + '">' + escapeText(kindValue) + '</td>' +
                        '<td class="c-status" data-label="Status">' + badge(r.status_operasional) + (!r.source_valid ? '<div class="row-note">Master / pemetaan perlu diperiksa</div>' : '') + '</td>' +
                        '<td class="c-capacity" data-label="Kapasitas">' + (r.kapasitas_orang == null ? '<span class="row-sub">Belum diisi</span>' : num(r.kapasitas_orang) + ' orang') + '</td>' +
                        '<td class="c-action"><button class="btn small row-edit" type="button" data-id="' + r.id + '">Ubah</button></td></tr>';
                }).join('');
                el('emptyState').hidden = results.length > 0;
                el('selectedCount').textContent = num(selected.size) + ' dipilih';
                el('resultCount').textContent = ' / ' + num(results.length) + ' hasil';
                el('bulkBtn').disabled = selected.size === 0 || busy;
                el('clearSelectionBtn').hidden = selected.size === 0;
                el('selectAllBtn').textContent = 'Pilih semua hasil (' + num(results.length) + ')';
                el('selectAllBtn').disabled = results.length === 0 || results.length > 1000 || busy;
                el('selectPageBtn').disabled = slice.length === 0 || busy;
                var checked = slice.filter(function(r) {
                    return selected.has(r.id);
                }).length;
                el('pageCheckbox').checked = slice.length > 0 && checked === slice.length;
                el('pageCheckbox').indeterminate = checked > 0 && checked < slice.length;
                el('pageCheckbox').disabled = slice.length === 0 || busy;
                el('pageSummary').textContent = results.length ? (num((page - 1) * pageSize + 1) + ' - ' + num(Math.min(page * pageSize, results.length)) + ' dari ' + num(results.length) + ' fasilitas') : '0 fasilitas';
                el('pageLabel').textContent = page + ' / ' + pages;
                el('prevBtn').disabled = page === 1 || busy;
                el('nextBtn').disabled = page === pages || busy;
                var pending = state.new_rooms + state.new_units;
                el('newDataNotice').hidden = pending === 0;
                el('newDataNotice').textContent = pending + ' entri/unit dari master belum memiliki pengaturan. Buka tab Persiapan lalu Sinkronkan Master Baru untuk memeriksanya.';
            }

            function renderAll(reset) {
                metrics();
                setupPanel();
                renderWarnings();
                if (!state.schema.ready) activeTab = 'persiapan';
                el('roomTabCount').textContent = state.schema.ready ? num(state.rooms.length) : '';
                el('classTabCount').textContent = state.schema.ready ? num(state.classes.length) : '';
                el('lastUpdated').textContent = 'Diperbarui ' + state.updated_at;
                document.querySelectorAll('[data-tab]').forEach(function(b) {
                    b.classList.toggle('active', b.dataset.tab === activeTab);
                    b.setAttribute('aria-pressed', b.dataset.tab === activeTab ? 'true' : 'false');
                    b.disabled = !state.schema.ready && b.dataset.tab !== 'persiapan';
                });
                el('setupPane').hidden = activeTab !== 'persiapan';
                el('dataPane').hidden = activeTab === 'persiapan';
                if (activeTab !== 'persiapan') {
                    rebuildFilters(!!reset);
                    renderTable();
                }
            }

            function endpoint() {
                var url = new URL(window.location.href);
                url.hash = '';
                url.search = '';
                url.searchParams.set('pf_api', '1');
                return url.href;
            }
            async function api(body) {
                var init = {
                    method: body ? 'POST' : 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json'
                    }
                };
                if (body) {
                    init.headers['Content-Type'] = 'application/json';
                    init.headers['X-CSRF-Token'] = initial.csrf;
                    init.body = JSON.stringify(body);
                }
                var res = await fetch(endpoint(), init);
                var text = await res.text();
                var obj;
                try {
                    obj = JSON.parse(text);
                } catch (e) {
                    throw new Error('Server tidak mengirim JSON. Periksa sesi login, konfigurasi PHP, dan batas ukuran request.');
                }
                if (!res.ok || !obj || obj.success !== true) throw new Error(obj && obj.message ? obj.message : 'Permintaan belum berhasil.');
                return obj;
            }
            async function reloadState() {
                var response = await api(null);
                state = response.state;
                selected.clear();
                renderAll(false);
            }

            function setBusy(value, button, text) {
                busy = value;
                el('reloadBtn').disabled = value;
                document.querySelectorAll('.modal button,.modal input,.modal select,.modal textarea').forEach(function(b) {
                    b.disabled = value;
                });
                if (button) {
                    if (value) {
                        button.dataset.original = button.textContent;
                        button.textContent = text || 'Menyimpan...';
                    } else if (button.dataset.original) {
                        button.textContent = button.dataset.original;
                        delete button.dataset.original;
                    }
                }
                el('bulkCapacity').disabled = value || el('bulkCapacityMode').value !== 'set';
                el('setupBtn').disabled = value || state.schema.issues.length > 0;
            }

            function openModal(id) {
                if (busy) return;
                previousFocus = document.activeElement;
                el(id).hidden = false;
                document.body.style.overflow = 'hidden';
                var focusable = el(id).querySelector('input:not([disabled]),select:not([disabled]),button');
                if (focusable) focusable.focus();
            }

            function closeModal() {
                if (busy) return;
                document.querySelectorAll('.modal').forEach(function(m) {
                    m.hidden = true;
                });
                document.body.style.overflow = '';
                editing = null;
                if (previousFocus && document.contains(previousFocus)) previousFocus.focus();
            }

            function changeFor(r) {
                var c = {
                    id: r.id,
                    version: r.version,
                    status_operasional: r.status_operasional,
                    kapasitas_orang: r.kapasitas_orang,
                    catatan: r.catatan || ''
                };
                if (activeTab === 'kelas') {
                    c.nama_kelas = r.nama_kelas;
                    c.jenis = r.jenis;
                } else c.kategori = r.kategori;
                return c;
            }

            function openEdit(id) {
                editing = sourceRows().find(function(r) {
                    return r.id === id;
                });
                if (!editing) return;
                var isClass = activeTab === 'kelas';
                el('editError').hidden = true;
                el('editForm').reset();
                el('editTitle').textContent = isClass ? 'Ubah kelas operasional' : 'Ubah pengaturan kamar';
                el('editSubtitle').textContent = label(editing) + ' - ' + (editing.nama_lokasi || '-') + ' / ' + (editing.nama_lantai || '-');
                el('nameField').hidden = !isClass;
                el('editName').value = editing.nama_kelas || '';
                el('editCategoryLabel').textContent = isClass ? 'Jenis ruang' : 'Kategori';
                el('editCategory').innerHTML = options(isClass ? kinds : categories, null);
                el('editCategory').value = isClass ? editing.jenis : editing.kategori;
                el('editStatus').value = editing.status_operasional;
                el('editCapacity').value = editing.kapasitas_orang == null ? '' : editing.kapasitas_orang;
                el('editNote').value = editing.catatan || '';
                el('editMembers').hidden = !isClass;
                el('editMembers').innerHTML = '<b>Anggota master (tidak diubah)</b>' + escapeText((editing.members || []).map(function(m) {
                    return (m.nama_ruangan || 'Master hilang') + ' [ID ' + m.master_ruangan_id + ']';
                }).join(' + '));
                el('editAudit').textContent = editing.diverifikasi_at ? ('Verifikasi terakhir: ' + editing.diverifikasi_at + ' WIB oleh ' + editing.diverifikasi_oleh) : 'Belum ada verifikasi operasional yang tersimpan.';
                openModal('editModal');
            }
            async function commit(body, button, errorId) {
                el(errorId).hidden = true;
                setBusy(true, button, body.action === 'setup' ? 'Menyiapkan data...' : 'Menyimpan...');
                var succeeded = false;
                try {
                    var r = await api(body);
                    succeeded = true;
                    setBusy(false, button);
                    closeModal();
                    message(r.message, false);
                    if (body.action === 'setup') {
                        activeTab = 'kamar';
                        page = 1;
                    }
                    try {
                        await reloadState();
                    } catch (readError) {
                        message(r.message + ' Data sudah diproses, tetapi daftar belum termuat ulang: ' + readError.message, true);
                    }
                    if (r.result && r.result.warnings && r.result.warnings.length) {
                        state.warnings = Array.from(new Set(state.warnings.concat(r.result.warnings)));
                        renderWarnings();
                    }
                } catch (e) {
                    modalError(errorId, e.message || 'Jaringan terputus. Muat ulang data untuk memeriksa hasil sebelum mengulangi.');
                } finally {
                    setBusy(false, button);
                    if (succeeded) renderTable();
                }
            }
            el('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                if (busy || !editing) return;
                var c = changeFor(editing);
                c.status_operasional = el('editStatus').value;
                c.kapasitas_orang = el('editCapacity').value === '' ? null : el('editCapacity').value;
                c.catatan = el('editNote').value;
                if (activeTab === 'kelas') {
                    c.nama_kelas = el('editName').value;
                    c.jenis = el('editCategory').value;
                } else c.kategori = el('editCategory').value;
                commit({
                    action: 'save',
                    kind: activeTab,
                    changes: [c],
                    confirmed: el('editConfirmed').checked
                }, el('editSubmit'), 'editError');
            });
            el('bulkBtn').addEventListener('click', function() {
                if (busy || !selected.size) return;
                el('bulkForm').reset();
                el('bulkError').hidden = true;
                el('bulkSubtitle').textContent = num(selected.size) + ' ' + (activeTab === 'kelas' ? 'unit kelas' : 'entri kamar') + ' dipilih. Hanya kolom yang Anda ubah yang akan diganti.';
                el('bulkCategoryLabel').textContent = activeTab === 'kelas' ? 'Jenis ruang' : 'Kategori';
                el('bulkCategory').innerHTML = options(activeTab === 'kelas' ? kinds : categories, 'Jangan ubah kategori');
                el('bulkCapacity').disabled = true;
                openModal('bulkModal');
            });
            el('bulkCapacityMode').addEventListener('change', function() {
                el('bulkCapacity').disabled = this.value !== 'set';
            });
            el('bulkForm').addEventListener('submit', function(e) {
                e.preventDefault();
                if (busy) return;
                if (el('bulkCapacityMode').value === 'set' && el('bulkCapacity').value === '') {
                    modalError('bulkError', 'Isi kapasitas atau pilih Jangan ubah kapasitas.');
                    return;
                }
                if (!el('bulkCategory').value && !el('bulkStatus').value && el('bulkCapacityMode').value === 'keep' && !el('bulkNote').value.trim()) {
                    modalError('bulkError', 'Pilih setidaknya satu kolom yang akan diubah.');
                    return;
                }
                var changes = sourceRows().filter(function(r) {
                    return selected.has(r.id);
                }).map(function(r) {
                    var c = changeFor(r);
                    if (el('bulkCategory').value) c[activeTab === 'kelas' ? 'jenis' : 'kategori'] = el('bulkCategory').value;
                    if (el('bulkStatus').value) c.status_operasional = el('bulkStatus').value;
                    if (el('bulkCapacityMode').value === 'clear') c.kapasitas_orang = null;
                    if (el('bulkCapacityMode').value === 'set') c.kapasitas_orang = el('bulkCapacity').value;
                    if (el('bulkNote').value.trim()) c.catatan = el('bulkNote').value;
                    return c;
                });
                commit({
                    action: 'save',
                    kind: activeTab,
                    changes: changes,
                    confirmed: el('bulkConfirmed').checked
                }, el('bulkSubmit'), 'bulkError');
            });
            el('setupBtn').addEventListener('click', function() {
                if (busy) return;
                el('setupForm').reset();
                el('setupError').hidden = true;
                openModal('setupModal');
            });
            el('setupForm').addEventListener('submit', function(e) {
                e.preventDefault();
                if (busy) return;
                commit({
                    action: 'setup',
                    confirmed_backup: el('backupConfirmed').checked,
                    confirmed_database: state.database
                }, el('setupSubmit'), 'setupError');
            });
            el('reloadBtn').addEventListener('click', async function() {
                if (busy) return;
                setBusy(true, this, 'Memuat...');
                try {
                    await reloadState();
                    message('Data terbaru berhasil dimuat.', false);
                } catch (e) {
                    message(e.message, true);
                } finally {
                    setBusy(false, this);
                    renderTable();
                }
            });
            el('messageClose').addEventListener('click', function() {
                el('message').hidden = true;
            });
            document.querySelectorAll('[data-tab]').forEach(function(b) {
                b.addEventListener('click', function() {
                    if (busy) return;
                    activeTab = b.dataset.tab;
                    page = 1;
                    selected.clear();
                    renderAll(true);
                });
            });

            function filterChanged() {
                page = 1;
                selected.clear();
                renderTable();
            }
            el('searchInput').addEventListener('input', filterChanged);
            el('locationFilter').addEventListener('change', function() {
                rebuildFloors(true);
                filterChanged();
            });
            ['floorFilter', 'categoryFilter', 'statusFilter'].forEach(function(id) {
                el(id).addEventListener('change', filterChanged);
            });
            el('rows').addEventListener('change', function(e) {
                if (e.target.matches('.row-check')) {
                    var id = Number(e.target.dataset.id);
                    if (e.target.checked) selected.add(id);
                    else selected.delete(id);
                    renderTable();
                }
            });
            el('rows').addEventListener('click', function(e) {
                var b = e.target.closest('.row-edit');
                if (b) openEdit(Number(b.dataset.id));
            });

            function pageSelection(checked) {
                filtered().slice((page - 1) * pageSize, page * pageSize).forEach(function(r) {
                    if (checked) selected.add(r.id);
                    else selected.delete(r.id);
                });
                renderTable();
            }
            el('pageCheckbox').addEventListener('change', function() {
                pageSelection(this.checked);
            });
            el('selectPageBtn').addEventListener('click', function() {
                pageSelection(true);
            });
            el('selectAllBtn').addEventListener('click', function() {
                var all = filtered();
                if (all.length > 1000) {
                    message('Pilih maksimal 1000 fasilitas per penyimpanan. Gunakan filter gedung/lantai.', true);
                    return;
                }
                all.forEach(function(r) {
                    selected.add(r.id);
                });
                renderTable();
            });
            el('clearSelectionBtn').addEventListener('click', function() {
                selected.clear();
                renderTable();
            });
            el('prevBtn').addEventListener('click', function() {
                if (page > 1) page--;
                renderTable();
            });
            el('nextBtn').addEventListener('click', function() {
                page++;
                renderTable();
            });
            el('pageSize').addEventListener('change', function() {
                pageSize = Number(this.value);
                page = 1;
                renderTable();
            });
            document.querySelectorAll('[data-close]').forEach(function(b) {
                b.addEventListener('click', closeModal);
            });
            document.querySelectorAll('.modal').forEach(function(m) {
                m.addEventListener('click', function(e) {
                    if (e.target === m) closeModal();
                });
            });
            document.addEventListener('keydown', function(e) {
                var modal = document.querySelector('.modal:not([hidden])');
                if (!modal) return;
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeModal();
                }
                if (e.key === 'Tab') {
                    var nodes = Array.from(modal.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled])')).filter(function(n) {
                        return n.offsetParent !== null;
                    });
                    if (!nodes.length) {
                        e.preventDefault();
                        return;
                    }
                    var first = nodes[0],
                        last = nodes[nodes.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
            renderAll(true);
        })();
    </script>
</body>

</html>