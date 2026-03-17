<?php
ob_start();
session_start();

require __DIR__ . '/fpdf/fpdf.php';
include __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Jakarta');

define('KV_ML', 12);
define('KV_MR', 12);
define('KV_TW', 273);

// =====================================================================
// HELPERS
// =====================================================================
function kv_safe($s)
{
    if (!is_string($s)) return (string)$s;
    $map = ['â€¢' => '-', "\xC2\xA0" => ' ', 'â€"' => '-', 'â€™' => "'"];
    $s = str_replace(array_keys($map), array_values($map), $s);
    $c = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return ($c !== false) ? $c : preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $s);
}

function kv_tanggal($tgl)
{
    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    $t = strtotime($tgl);
    return date('j', $t) . ' ' . $bulan[(int)date('m', $t)] . ' ' . date('Y', $t);
}

function kv_datetime($dt)
{
    if (!$dt) return '-';
    $bulan = [
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
    $t = strtotime($dt);
    return date('d', $t) . ' ' . $bulan[(int)date('m', $t)] . ' ' . date('Y H:i', $t);
}

function kv_durasi($masuk, $keluar)
{
    if (empty($masuk) || empty($keluar)) return '-';
    if (strpos($keluar, '0000') === 0 || strpos($masuk, '0000') === 0) return '-';
    $a = strtotime($masuk);
    $b = strtotime($keluar);
    if (!$a || !$b || $a <= 0 || $b <= 0) return '-';
    $sel = $b - $a;
    if ($sel <= 0) return '-';
    $mnt = floor($sel / 60) % 60;
    $jam = floor($sel / 3600) % 24;
    $hari = floor($sel / 86400);
    if ($hari > 0) return "{$hari}h {$jam}j {$mnt}m";
    if ($jam > 0)  return "{$jam}j {$mnt}m";
    return "{$mnt} menit";
}

// =====================================================================
// PERIODE
// =====================================================================
$from    = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to      = $_GET['to']   ?? date('Y-m-d');
$fromYmd = date('Y-m-d', strtotime($from));
$toYmd   = date('Y-m-d', strtotime($to));
if (!$fromYmd || !$toYmd) {
    http_response_code(400);
    echo "Tanggal tidak valid.";
    exit;
}

// =====================================================================
// QUERY
// =====================================================================
$stmt = $conn->prepare("SELECT * FROM kendaraan_log WHERE DATE(waktu_masuk)>=? AND DATE(waktu_masuk)<=? ORDER BY waktu_masuk ASC");
$stmt->bind_param('ss', $fromYmd, $toYmd);
$stmt->execute();
$rowsTamu = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalTamuMasuk  = count($rowsTamu);
$totalTamuKeluar = count(array_filter($rowsTamu, function ($r) {
    return $r['status'] === 'keluar';
}));
$totalTamuDalam  = $totalTamuMasuk - $totalTamuKeluar;

$stmt2 = $conn->prepare("SELECT * FROM kendaraan_operasional_log WHERE DATE(waktu_keluar)>=? AND DATE(waktu_keluar)<=? ORDER BY waktu_keluar ASC");
$stmt2->bind_param('ss', $fromYmd, $toYmd);
$stmt2->execute();
$rowsOps = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$totalOpsKeluar  = count($rowsOps);
$totalOpsKembali = count(array_filter($rowsOps, function ($r) {
    return $r['status'] === 'kembali';
}));
$totalOpsDiLuar  = $totalOpsKeluar - $totalOpsKembali;

// =====================================================================
// PDF CLASS
// =====================================================================
class KendaraanPDF extends FPDF
{
    public $currentHeaderFn = null; // fungsi header tabel aktif untuk page break

    function Header()
    {
        global $fromYmd, $toYmd;
        $this->SetFillColor(0, 0, 0);
        $this->Rect(0, 0, $this->w, 1.5, 'F');
        $this->SetY(6);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 9, 'LAPORAN PENCATATAN KENDARAAN', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, 'Periode : ' . kv_tanggal($fromYmd) . '   s/d   ' . kv_tanggal($toYmd), 0, 1, 'C');
        $this->Ln(2);
        $y = $this->GetY();
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Line(KV_ML, $y, KV_ML + KV_TW, $y);
        $this->SetLineWidth(0.2);
        $this->SetY($y + 4);
        $this->SetTextColor(0, 0, 0);
        // Cetak ulang header tabel jika ada
        if ($this->currentHeaderFn) {
            call_user_func($this->currentHeaderFn);
        }
    }

    function Footer()
    {
        $this->SetY(-13);
        $y = $this->GetY();
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.3);
        $this->Line(KV_ML, $y, KV_ML + KV_TW, $y);
        $this->SetLineWidth(0.2);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->SetX(KV_ML);
        $this->Cell(KV_TW / 2, 9, 'Dicetak otomatis oleh sistem', 0, 0, 'L');
        $this->Cell(KV_TW / 2, 9, 'Halaman ' . $this->PageNo(), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    // ── Stat Block ──────────────────────────────────────────
    function StatBlock($title, $labels, $values, $rgb)
    {
        $tw = KV_TW;
        $wc = $tw / count($labels);
        $this->SetX(KV_ML);
        $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($tw, 7, kv_safe($title), 1, 1, 'C', true);
        $this->SetX(KV_ML);
        $this->SetFillColor(225, 225, 225);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 8);
        foreach ($labels as $l) $this->Cell($wc, 6, kv_safe($l), 1, 0, 'C', true);
        $this->Ln();
        $this->SetX(KV_ML);
        $this->SetFillColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 13);
        foreach ($values as $v) $this->Cell($wc, 9, (string)$v, 1, 0, 'C', true);
        $this->Ln();
        $this->Ln(3);
    }

    // ── Section Title ────────────────────────────────────────
    function SectionTitle($title, $rgb)
    {
        if ($this->GetY() > $this->h - 50) $this->AddPage();
        $this->SetX(KV_ML);
        $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(KV_TW, 8, kv_safe('  ' . $title), 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    // ================================================================
    // TABEL DENGAN AUTO-WRAP
    // Teknik: hitung tinggi baris dulu (GetStringWidth), lalu gambar
    // ================================================================

    /**
     * Hitung tinggi baris yang dibutuhkan untuk MultiCell
     * @param float  $w    lebar kolom (mm)
     * @param string $txt  teks
     * @param float  $lh   line height per baris
     */
    function calcCellHeight($w, $txt, $lh = 5.5)
    {
        if ($w <= 0 || $txt === '') return $lh;
        $charPerLine = max(1, floor($w / ($this->FontSize * 0.55)));
        // Hitung jumlah baris setelah wrap
        $words = explode(' ', $txt);
        $line  = '';
        $lines = 1;
        foreach ($words as $word) {
            $test = $line === '' ? $word : $line . ' ' . $word;
            if (mb_strlen($test) > $charPerLine && $line !== '') {
                $lines++;
                $line = $word;
            } else {
                $line = $test;
            }
        }
        return $lines * $lh;
    }

    /**
     * Gambar satu baris tabel multi-kolom dengan wrap otomatis
     * $cols = array of ['w'=>float, 'txt'=>string, 'align'=>'L'|'C'|'R', 'italic'=>false, 'gray'=>false]
     */
    function MultiRow($cols, $lh = 5.5, $odd = true)
    {
        // 1. Hitung tinggi maksimum baris
        $maxH = $lh;
        foreach ($cols as $col) {
            $h = $this->calcCellHeight($col['w'], $col['txt'], $lh);
            if ($h > $maxH) $maxH = $h;
        }

        // 2. Page break jika perlu
        if ($this->GetY() + $maxH > $this->h - 18) {
            $this->AddPage();
        }

        $startY = $this->GetY();
        $startX = KV_ML;
        $bg     = $odd ? 248 : 255;

        // 3. Gambar background dulu semua kolom (fill tanpa border)
        $x = $startX;
        foreach ($cols as $col) {
            $this->SetFillColor($bg, $bg, $bg);
            $this->SetDrawColor(255, 255, 255); // transparan sementara
            $this->Rect($x, $startY, $col['w'], $maxH, 'F');
            $x += $col['w'];
        }

        // 4. Isi teks tiap kolom
        $x = $startX;
        foreach ($cols as $col) {
            $italic = $col['italic'] ?? false;
            $gray   = $col['gray']   ?? false;
            $align  = $col['align']  ?? 'L';

            if ($italic) $this->SetFont('Arial', 'I', 8);
            else         $this->SetFont('Arial', '', 8);

            if ($gray) $this->SetTextColor(120, 120, 120);
            else       $this->SetTextColor(0, 0, 0);

            $padX = 1;
            $this->SetXY($x + $padX, $startY + 0.5);
            $this->MultiCell($col['w'] - ($padX * 2), $lh, $col['txt'], 0, $align, false);

            $x += $col['w'];
        }

        // 5. Gambar SEMUA border setelah teks — 1 Rect per kolom
        //    Ini memastikan garis tidak tertimpa MultiCell
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $x = $startX;
        foreach ($cols as $col) {
            $this->Rect($x, $startY, $col['w'], $maxH);
            $x += $col['w'];
        }

        // 6. Gambar ulang garis bawah baris sebagai garis tegas
        //    Supaya tidak putus-putus di baris terakhir
        $this->SetLineWidth(0.3);
        $this->Line($startX, $startY + $maxH, $startX + KV_TW, $startY + $maxH);
        $this->SetLineWidth(0.2);

        // 7. Pindah Y ke bawah baris
        $this->SetXY(KV_ML, $startY + $maxH);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 8);
    }

    // ── Header Tabel Tamu ─────────────────────────────────────
    function TableHeaderTamu()
    {
        $this->SetX(KV_ML);
        $this->SetFillColor(50, 50, 50);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        // NO=8, PLAT=28, INSTANSI=52, TUJUAN=55, MSK=36, KLR=36, DUR=20, CAT=38 = 273
        $this->Cell(8, 7, 'No',             1, 0, 'C', true);
        $this->Cell(28, 7, 'Plat Nomor',     1, 0, 'C', true);
        $this->Cell(52, 7, 'Instansi / Tamu', 1, 0, 'C', true);
        $this->Cell(55, 7, 'Tujuan',         1, 0, 'C', true);
        $this->Cell(36, 7, 'Waktu Masuk',    1, 0, 'C', true);
        $this->Cell(36, 7, 'Waktu Keluar',   1, 0, 'C', true);
        $this->Cell(20, 7, 'Durasi',         1, 0, 'C', true);
        $this->Cell(38, 7, 'Dicatat Oleh',   1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
    }

    function TableRowTamu($no, $row, $odd)
    {
        $isKeluar = $row['status'] === 'keluar';
        $cols = [
            ['w' => 8,  'txt' => (string)$no,                                                               'align' => 'C'],
            ['w' => 28, 'txt' => kv_safe($row['plat_nomor']),                                               'align' => 'C'],
            ['w' => 52, 'txt' => kv_safe($row['instansi_tamu'] ?: '-'),                                     'align' => 'L'],
            ['w' => 55, 'txt' => kv_safe($row['tujuan'] ?: '-'),                                            'align' => 'L'],
            ['w' => 36, 'txt' => kv_safe(kv_datetime($row['waktu_masuk'])),                                 'align' => 'C'],
            ['w' => 36, 'txt' => kv_safe($isKeluar ? kv_datetime($row['waktu_keluar']) : 'Masih di dalam'), 'align' => 'C', 'italic' => !$isKeluar, 'gray' => !$isKeluar],
            ['w' => 20, 'txt' => kv_safe($isKeluar ? kv_durasi($row['waktu_masuk'], $row['waktu_keluar']) : '-'), 'align' => 'C'],
            ['w' => 38, 'txt' => kv_safe($row['dicatat_oleh'] ?: '-'),                                      'align' => 'L'],
        ];
        $this->MultiRow($cols, 5.5, $odd);
    }

    // ── Header Tabel Operasional ──────────────────────────────
    function TableHeaderOps()
    {
        $this->SetX(KV_ML);
        $this->SetFillColor(50, 50, 50);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        // NO=8, PLAT=28, DRV=45, TUJ=70, KLR=36, KMB=36, DUR=18, CAT=32 = 273
        $this->Cell(8, 7, 'No',            1, 0, 'C', true);
        $this->Cell(28, 7, 'Plat Nomor',    1, 0, 'C', true);
        $this->Cell(45, 7, 'Pengemudi',     1, 0, 'C', true);
        $this->Cell(70, 7, 'Tujuan',        1, 0, 'C', true);
        $this->Cell(36, 7, 'Waktu Keluar',  1, 0, 'C', true);
        $this->Cell(36, 7, 'Waktu Kembali', 1, 0, 'C', true);
        $this->Cell(18, 7, 'Durasi',        1, 0, 'C', true);
        $this->Cell(32, 7, 'Dicatat Oleh',  1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
    }

    function TableRowOps($no, $row, $odd)
    {
        $isKembali = $row['status'] === 'kembali';
        $cols = [
            ['w' => 8,  'txt' => (string)$no,                                                                    'align' => 'C'],
            ['w' => 28, 'txt' => kv_safe($row['plat_nomor']),                                                    'align' => 'C'],
            ['w' => 45, 'txt' => kv_safe($row['pengemudi'] ?: '-'),                                              'align' => 'L'],
            ['w' => 70, 'txt' => kv_safe($row['tujuan'] ?: '-'),                                                 'align' => 'L'],
            ['w' => 36, 'txt' => kv_safe(kv_datetime($row['waktu_keluar'])),                                     'align' => 'C'],
            ['w' => 36, 'txt' => kv_safe($isKembali ? kv_datetime($row['waktu_kembali']) : 'Masih di luar'),     'align' => 'C', 'italic' => !$isKembali, 'gray' => !$isKembali],
            ['w' => 18, 'txt' => kv_safe($isKembali ? kv_durasi($row['waktu_keluar'], $row['waktu_kembali']) : '-'), 'align' => 'C'],
            ['w' => 32, 'txt' => kv_safe($row['dicatat_oleh'] ?: '-'),                                           'align' => 'L'],
        ];
        $this->MultiRow($cols, 5.5, $odd);
    }
}

// =====================================================================
// BUILD PDF
// =====================================================================
$pdf = new KendaraanPDF('L', 'mm', 'A4');
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetMargins(KV_ML, 36, KV_MR);
$pdf->SetLineWidth(0.2);
$pdf->AddPage();

// Ringkasan
$pdf->StatBlock(
    'RINGKASAN TAMU / PENGUNJUNG',
    ['Total Kendaraan', 'Sudah Keluar', 'Masih Di Dalam'],
    [$totalTamuMasuk, $totalTamuKeluar, $totalTamuDalam],
    [2, 90, 160]
);

$pdf->StatBlock(
    'RINGKASAN KENDARAAN OPERASIONAL',
    ['Total Perjalanan', 'Sudah Kembali', 'Masih Di Luar'],
    [$totalOpsKeluar, $totalOpsKembali, $totalOpsDiLuar],
    [180, 100, 2]
);

$pdf->Ln(2);

// ── Tabel Tamu ──────────────────────────────────────────────
$pdf->SectionTitle('DAFTAR KENDARAAN TAMU / PENGUNJUNG', [2, 90, 160]);
$pdf->currentHeaderFn = [$pdf, 'TableHeaderTamu'];
$pdf->TableHeaderTamu();

if (empty($rowsTamu)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetX(KV_ML);
    $pdf->Cell(KV_TW, 10, 'Tidak ada data tamu pada periode ini.', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
} else {
    $no = 1;
    $odd = true;
    foreach ($rowsTamu as $row) {
        $pdf->TableRowTamu($no, $row, $odd);
        $no++;
        $odd = !$odd;
    }
}

$pdf->currentHeaderFn = null;
$pdf->Ln(5);

// ── Tabel Operasional ───────────────────────────────────────
$pdf->SectionTitle('DAFTAR KENDARAAN OPERASIONAL / DINAS', [180, 100, 2]);
$pdf->currentHeaderFn = [$pdf, 'TableHeaderOps'];
$pdf->TableHeaderOps();

if (empty($rowsOps)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetX(KV_ML);
    $pdf->Cell(KV_TW, 10, 'Tidak ada data operasional pada periode ini.', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
} else {
    $no = 1;
    $odd = true;
    foreach ($rowsOps as $row) {
        $pdf->TableRowOps($no, $row, $odd);
        $no++;
        $odd = !$odd;
    }
}

$pdf->currentHeaderFn = null;

// =====================================================================
// OUTPUT
// =====================================================================
@ob_end_clean();
$fn = 'Laporan_Kendaraan_' . $fromYmd . '_sd_' . $toYmd . '.pdf';
$pdf->Output('D', $fn);
exit;
