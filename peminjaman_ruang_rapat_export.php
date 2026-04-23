<?php
ob_start();
session_start();

require __DIR__ . '/fpdf/fpdf.php';
include __DIR__ . '/config.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Koneksi database tidak tersedia.');
}

define('BR_ML', 12);
define('BR_MR', 12);
define('BR_TW', 273);

// =====================================================================
// HELPERS
// =====================================================================
function br_safe($s)
{
    if (!is_string($s)) return (string)$s;
    $map = ['â€¢' => '-', "\xC2\xA0" => ' ', 'â€"' => '-', 'â€™' => "'"];
    $s = str_replace(array_keys($map), array_values($map), $s);
    $c = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return ($c !== false) ? $c : preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $s);
}

function br_tanggal($tgl)
{
    if (!$tgl || $tgl === '0000-00-00') return '-';

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
    if (!$t) return $tgl;

    return date('j', $t) . ' ' . $bulan[(int)date('m', $t)] . ' ' . date('Y', $t);
}

function br_tanggal_short($tgl)
{
    if (!$tgl || $tgl === '0000-00-00') return '-';

    $bulan = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agt',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des'
    ];

    $t = strtotime($tgl);
    if (!$t) return $tgl;

    return date('d', $t) . ' ' . $bulan[(int)date('m', $t)] . ' ' . date('Y', $t);
}

function br_rentang_tanggal($start, $end)
{
    if (!$start || !$end) return '-';
    if ($start === $end) return br_tanggal_short($start);
    return br_tanggal_short($start) . ' s/d ' . br_tanggal_short($end);
}

function br_jam($jam)
{
    if (!$jam) return '-';
    return substr($jam, 0, 5);
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
$stmt = $conn->prepare("
    SELECT
        b.id,
        b.jenis_lokasi,
        b.room_id,
        b.lokasi_external,
        b.nama,
        b.peminjam,
        b.start_date,
        b.end_date,
        b.jam_start,
        b.jam_end,
        b.peserta,
        b.wa,
        b.ket,
        COALESCE(r.nama_ruang, '') AS ruang,
        COALESCE(r.lokasi, '') AS lokasi_ruang
    FROM booking_ruang_rapat b
    LEFT JOIN ruang_rapat r ON r.id = b.room_id
    WHERE b.start_date <= ? AND b.end_date >= ?
    ORDER BY b.start_date ASC, b.jam_start ASC
");

if (!$stmt) {
    die('Prepare query gagal: ' . $conn->error);
}

$stmt->bind_param('ss', $toYmd, $fromYmd);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// =====================================================================
// RINGKASAN
// =====================================================================
$totalBooking  = count($rows);
$totalInternal = count(array_filter($rows, function ($r) {
    return ($r['jenis_lokasi'] ?? 'internal') === 'internal';
}));
$totalExternal = count(array_filter($rows, function ($r) {
    return ($r['jenis_lokasi'] ?? '') === 'external';
}));

$today        = date('Y-m-d');
$totalSelesai = count(array_filter($rows, function ($r) use ($today) {
    return $r['end_date'] < $today;
}));
$totalAktif = $totalBooking - $totalSelesai;

$ruangCount = [];
foreach ($rows as $r) {
    if (($r['jenis_lokasi'] ?? 'internal') === 'internal' && !empty($r['ruang'])) {
        $ruangCount[$r['ruang']] = ($ruangCount[$r['ruang']] ?? 0) + 1;
    }
}
arsort($ruangCount);
$ruangTersibuk = $ruangCount ? array_key_first($ruangCount) . ' (' . reset($ruangCount) . 'x)' : '-';

// =====================================================================
// PDF CLASS
// =====================================================================
class BookingRapatPDF extends FPDF
{
    public $currentHeaderFn = null;

    function Header()
    {
        global $fromYmd, $toYmd;

        $this->SetFillColor(0, 0, 0);
        $this->Rect(0, 0, $this->w, 1.5, 'F');

        $this->SetY(6);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 9, 'LAPORAN PEMINJAMAN RUANG RAPAT', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, 'Periode : ' . br_tanggal($fromYmd) . '   s/d   ' . br_tanggal($toYmd), 0, 1, 'C');

        $this->Ln(2);
        $y = $this->GetY();
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Line(BR_ML, $y, BR_ML + BR_TW, $y);
        $this->SetLineWidth(0.2);
        $this->SetY($y + 4);
        $this->SetTextColor(0, 0, 0);

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
        $this->Line(BR_ML, $y, BR_ML + BR_TW, $y);
        $this->SetLineWidth(0.2);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->SetX(BR_ML);
        $this->Cell(BR_TW / 2, 9, 'Dicetak otomatis oleh sistem', 0, 0, 'L');
        $this->Cell(BR_TW / 2, 9, 'Halaman ' . $this->PageNo(), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    function StatBlock($title, $labels, $values, $rgb)
    {
        $tw = BR_TW;
        $wc = $tw / count($labels);

        $this->SetX(BR_ML);
        $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($tw, 7, br_safe($title), 1, 1, 'C', true);

        $this->SetX(BR_ML);
        $this->SetFillColor(225, 225, 225);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 8);
        foreach ($labels as $l) {
            $this->Cell($wc, 6, br_safe($l), 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetX(BR_ML);
        $this->SetFillColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 13);
        foreach ($values as $v) {
            $this->Cell($wc, 9, (string)$v, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->Ln(3);
    }

    function SectionTitle($title, $rgb)
    {
        if ($this->GetY() > $this->h - 50) $this->AddPage();

        $this->SetX(BR_ML);
        $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(BR_TW, 8, br_safe('  ' . $title), 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    function calcCellHeight($w, $txt, $lh = 5.5)
    {
        if ($w <= 0 || $txt === '') return $lh;

        $charPerLine = max(1, floor($w / ($this->FontSize * 0.55)));
        $words = explode(' ', $txt);
        $line  = '';
        $lines = 1;

        foreach ($words as $word) {
            $test = $line === '' ? $word : $line . ' ' . $word;
            $len  = function_exists('mb_strlen') ? mb_strlen($test) : strlen($test);

            if ($len > $charPerLine && $line !== '') {
                $lines++;
                $line = $word;
            } else {
                $line = $test;
            }
        }

        return $lines * $lh;
    }

    function MultiRow($cols, $lh = 5.5, $odd = true)
    {
        $maxH = $lh;
        foreach ($cols as $col) {
            $h = $this->calcCellHeight($col['w'], $col['txt'], $lh);
            if ($h > $maxH) $maxH = $h;
        }

        if ($this->GetY() + $maxH > $this->h - 18) {
            $this->AddPage();
        }

        $startY = $this->GetY();
        $startX = BR_ML;
        $bg     = $odd ? 248 : 255;

        $x = $startX;
        foreach ($cols as $col) {
            $this->SetFillColor($bg, $bg, $bg);
            $this->SetDrawColor(255, 255, 255);
            $this->Rect($x, $startY, $col['w'], $maxH, 'F');
            $x += $col['w'];
        }

        $x = $startX;
        foreach ($cols as $col) {
            $italic = $col['italic'] ?? false;
            $gray   = $col['gray'] ?? false;
            $align  = $col['align'] ?? 'L';

            if ($italic) $this->SetFont('Arial', 'I', 8);
            else $this->SetFont('Arial', '', 8);

            if ($gray) $this->SetTextColor(120, 120, 120);
            else $this->SetTextColor(0, 0, 0);

            $padX = 1;
            $this->SetXY($x + $padX, $startY + 0.5);
            $this->MultiCell($col['w'] - ($padX * 2), $lh, $col['txt'], 0, $align, false);

            $x += $col['w'];
        }

        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $x = $startX;
        foreach ($cols as $col) {
            $this->Rect($x, $startY, $col['w'], $maxH);
            $x += $col['w'];
        }

        $this->SetLineWidth(0.3);
        $this->Line($startX, $startY + $maxH, $startX + BR_TW, $startY + $maxH);
        $this->SetLineWidth(0.2);

        $this->SetXY(BR_ML, $startY + $maxH);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 8);
    }

    function TableHeaderBooking()
    {
        $this->SetX(BR_ML);
        $this->SetFillColor(50, 50, 50);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);

        // total = 273
        $this->Cell(8,  7, 'No',           1, 0, 'C', true);
        $this->Cell(52, 7, 'Nama Kegiatan', 1, 0, 'C', true);
        $this->Cell(34, 7, 'Peminjam',     1, 0, 'C', true);
        $this->Cell(42, 7, 'Lokasi',       1, 0, 'C', true);
        $this->Cell(34, 7, 'Tanggal',      1, 0, 'C', true);
        $this->Cell(22, 7, 'Jam',          1, 0, 'C', true);
        $this->Cell(16, 7, 'Peserta',      1, 0, 'C', true);
        $this->Cell(28, 7, 'No. WA',       1, 0, 'C', true);
        $this->Cell(37, 7, 'Keterangan',   1, 1, 'C', true);

        $this->SetTextColor(0, 0, 0);
    }

    function TableRowBooking($no, $row, $odd)
    {
        $lokasi = (($row['jenis_lokasi'] ?? 'internal') === 'external')
            ? br_safe($row['lokasi_external'] ?: '-')
            : br_safe(($row['ruang'] ?: '-') . (!empty($row['lokasi_ruang']) ? "\n" . $row['lokasi_ruang'] : ''));

        $cols = [
            ['w' => 8,  'txt' => (string)$no, 'align' => 'C'],
            ['w' => 52, 'txt' => br_safe($row['nama'] ?: '-'), 'align' => 'L'],
            ['w' => 34, 'txt' => br_safe($row['peminjam'] ?: '-'), 'align' => 'L'],
            ['w' => 42, 'txt' => $lokasi, 'align' => 'L'],
            ['w' => 34, 'txt' => br_safe(br_rentang_tanggal($row['start_date'], $row['end_date'])), 'align' => 'C'],
            ['w' => 22, 'txt' => br_safe(br_jam($row['jam_start']) . ' - ' . br_jam($row['jam_end'])), 'align' => 'C'],
            ['w' => 16, 'txt' => (string)($row['peserta'] ?? 0), 'align' => 'C'],
            ['w' => 28, 'txt' => br_safe($row['wa'] ?: '-'), 'align' => 'C'],
            ['w' => 37, 'txt' => br_safe($row['ket'] ?: '-'), 'align' => 'L', 'italic' => empty($row['ket']), 'gray' => empty($row['ket'])],
        ];

        $this->MultiRow($cols, 5.5, $odd);
    }
}

// =====================================================================
// BUILD PDF
// =====================================================================
$pdf = new BookingRapatPDF('L', 'mm', 'A4');
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetMargins(BR_ML, 36, BR_MR);
$pdf->SetLineWidth(0.2);
$pdf->AddPage();

$pdf->StatBlock(
    'RINGKASAN PEMINJAMAN RUANG RAPAT',
    ['Total Booking', 'Booking Aktif', 'Sudah Selesai', 'Internal', 'Eksternal', 'Ruang Tersibuk'],
    [$totalBooking, $totalAktif, $totalSelesai, $totalInternal, $totalExternal, $ruangTersibuk],
    [2, 90, 160]
);

$pdf->Ln(2);

$pdf->SectionTitle('DAFTAR PEMINJAMAN RUANG RAPAT', [2, 90, 160]);
$pdf->currentHeaderFn = [$pdf, 'TableHeaderBooking'];
$pdf->TableHeaderBooking();

if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetX(BR_ML);
    $pdf->Cell(BR_TW, 10, 'Tidak ada data booking pada periode ini.', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
} else {
    $no = 1;
    $odd = true;
    foreach ($rows as $row) {
        $pdf->TableRowBooking($no, $row, $odd);
        $no++;
        $odd = !$odd;
    }
}

$pdf->currentHeaderFn = null;

// =====================================================================
// OUTPUT
// =====================================================================
@ob_end_clean();
$fn = 'Laporan_Booking_Rapat_' . $fromYmd . '_sd_' . $toYmd . '.pdf';
$pdf->Output('D', $fn);
exit;
