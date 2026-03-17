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

// =====================================================================
// KONSTANTA LAYOUT — Portrait A4 (210 x 297 mm)
// =====================================================================
define('BT_ML', 12);   // margin kiri
define('BT_MR', 12);   // margin kanan
define('BT_TW', 186);  // lebar konten = 210 - 12 - 12

// Lebar kolom — total HARUS = BT_TW (186)
define('BT_C_NO',   8);   // No
define('BT_C_NAMA', 40);  // Nama
define('BT_C_ASAL', 42);  // Instansi/Asal
define('BT_C_HP',   28);  // No HP
define('BT_C_JNS',  32);  // Jenis Layanan
define('BT_C_KPL',  36);  // Keperluan
// 8+40+42+28+32+36 = 186 ✓

// =====================================================================
// HELPERS
// =====================================================================
function bt_safe($s)
{
    if (!is_string($s)) return (string)$s;
    $map = ["\xC2\xA0" => ' ', 'â€"' => '-', 'â€™' => "'", 'â€¢' => '-'];
    $s   = str_replace(array_keys($map), array_values($map), $s);
    $c   = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return ($c !== false) ? $c : preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $s);
}

function bt_tanggal($tgl)
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
    if (!$t) return $tgl;
    return date('j', $t) . ' ' . $bulan[(int)date('m', $t)] . ' ' . date('Y', $t);
}

function bt_datetime($dt)
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

$jenisLabel = [
    'pelayanan_umum'      => 'Umum',
    'pelayanan_informasi' => 'Informasi',
    'pelayanan_pengaduan' => 'Pengaduan',
];

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
    SELECT id, nama, email, asal, no_hp, jenis_layanan, keperluan, created_at
    FROM buku_tamu
    WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
    ORDER BY created_at ASC
");
$stmt->bind_param('ss', $fromYmd, $toYmd);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalTamu  = count($rows);
$totalUmum  = count(array_filter($rows, function ($r) {
    return $r['jenis_layanan'] === 'pelayanan_umum';
}));
$totalInfo  = count(array_filter($rows, function ($r) {
    return $r['jenis_layanan'] === 'pelayanan_informasi';
}));
$totalPeng  = count(array_filter($rows, function ($r) {
    return $r['jenis_layanan'] === 'pelayanan_pengaduan';
}));

// =====================================================================
// CLASS PDF
// =====================================================================
class BukuTamuPDF extends FPDF
{
    function Header()
    {
        global $fromYmd, $toYmd;

        // Garis atas tebal
        $this->SetFillColor(30, 30, 30);
        $this->Rect(0, 0, $this->w, 1.5, 'F');

        $this->SetY(6);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'LAPORAN BUKU TAMU', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(
            0,
            5,
            'Periode : ' . bt_tanggal($fromYmd) . '   s/d   ' . bt_tanggal($toYmd),
            0,
            1,
            'C'
        );

        $this->Ln(2);
        $y = $this->GetY();
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Line(BT_ML, $y, BT_ML + BT_TW, $y);
        $this->SetLineWidth(0.2);
        $this->SetY($y + 4);
        $this->SetTextColor(0, 0, 0);
    }

    function Footer()
    {
        $this->SetY(-13);
        $y = $this->GetY();
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.3);
        $this->Line(BT_ML, $y, BT_ML + BT_TW, $y);
        $this->SetLineWidth(0.2);

        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->SetX(BT_ML);
        $this->Cell(BT_TW / 2, 9, 'Dicetak otomatis oleh sistem', 0, 0, 'L');
        $this->Cell(BT_TW / 2, 9, 'Halaman ' . $this->PageNo(), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    function StatBlock($totalTamu, $totalUmum, $totalInfo, $totalPeng)
    {
        $x  = BT_ML;
        $tw = BT_TW;
        $w4 = $tw / 4;

        // Header RINGKASAN
        $this->SetX($x);
        $this->SetFillColor(30, 30, 30);
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($tw, 7, 'RINGKASAN', 1, 1, 'C', true);

        // Label
        $this->SetX($x);
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 8);
        foreach (['Total Tamu', 'Umum', 'Informasi', 'Pengaduan'] as $l) {
            $this->Cell($w4, 7, bt_safe($l), 1, 0, 'C', true);
        }
        $this->Ln();

        // Nilai
        $this->SetX($x);
        $this->SetFillColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        foreach ([$totalTamu, $totalUmum, $totalInfo, $totalPeng] as $v) {
            $this->Cell($w4, 10, (string)$v, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->Ln(5);
    }

    function TableHeader()
    {
        $this->SetX(BT_ML);
        $this->SetFillColor(30, 30, 30);
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);

        $this->Cell(BT_C_NO,   7, 'No',        1, 0, 'C', true);
        $this->Cell(BT_C_NAMA, 7, 'Nama',       1, 0, 'C', true);
        $this->Cell(BT_C_ASAL, 7, 'Instansi',   1, 0, 'C', true);
        $this->Cell(BT_C_HP,   7, 'No. HP',     1, 0, 'C', true);
        $this->Cell(BT_C_JNS,  7, 'Layanan',    1, 0, 'C', true);
        $this->Cell(BT_C_KPL,  7, 'Keperluan',  1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
    }

    // Baris dengan MultiCell untuk keperluan panjang
    function TableRow($no, $row, $odd)
    {
        global $jenisLabel;

        $bg    = $odd ? 248 : 255;
        $jenis = bt_safe($jenisLabel[$row['jenis_layanan']] ?? '-');
        $kep   = bt_safe($row['keperluan'] ?? '-');

        // Hitung tinggi baris dari keperluan (MultiCell wrap)
        $this->SetFont('Arial', '', 7);
        $lineH  = 4.2;
        // Estimasi jumlah baris keperluan
        $words  = explode(' ', $kep);
        $line   = '';
        $nLines = 1;
        foreach ($words as $w) {
            $test = $line === '' ? $w : $line . ' ' . $w;
            if ($this->GetStringWidth($test) > BT_C_KPL - 2) {
                $nLines++;
                $line = $w;
            } else {
                $line = $test;
            }
        }
        $rowH = max(6.5, $nLines * $lineH + 2);

        // Page break manual
        if ($this->GetY() + $rowH > $this->h - 18) {
            $this->AddPage();
            $this->TableHeader();
        }

        $xStart = BT_ML;
        $yStart = $this->GetY();

        $this->SetFillColor($bg, $bg, $bg);
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);

        // Gambar background seluruh baris dulu (1 rect penuh)
        $this->Rect($xStart, $yStart, BT_TW, $rowH, 'F');

        // Gambar garis vertikal pemisah kolom + border luar
        $this->SetLineWidth(0.2);
        $cols = [BT_C_NO, BT_C_NAMA, BT_C_ASAL, BT_C_HP, BT_C_JNS, BT_C_KPL];
        $xLine = $xStart;
        foreach ($cols as $cw) {
            $this->Line($xLine, $yStart, $xLine, $yStart + $rowH);
            $xLine += $cw;
        }
        $this->Line($xLine, $yStart, $xLine, $yStart + $rowH); // garis kanan terakhir
        $this->Line($xStart, $yStart,          $xStart + BT_TW, $yStart);          // atas
        $this->Line($xStart, $yStart + $rowH,  $xStart + BT_TW, $yStart + $rowH);  // bawah

        // Tulis teks kolom pendek (Cell vertikal tengah)
        $vPad = ($rowH - 5) / 2;
        $x    = $xStart;

        // No
        $this->SetFont('Arial', '', 8);
        $this->SetXY($x, $yStart + $vPad);
        $this->Cell(BT_C_NO, 5, $no, 0, 0, 'C');
        $x += BT_C_NO;

        // Nama
        $this->SetXY($x + 1, $yStart + $vPad);
        $this->Cell(BT_C_NAMA - 1, 5, bt_safe($row['nama'] ?? '-'), 0, 0, 'L');
        $x += BT_C_NAMA;

        // Instansi
        $this->SetXY($x + 1, $yStart + $vPad);
        $this->Cell(BT_C_ASAL - 1, 5, bt_safe($row['asal'] ?? '-'), 0, 0, 'L');
        $x += BT_C_ASAL;

        // No HP
        $this->SetXY($x + 1, $yStart + $vPad);
        $this->Cell(BT_C_HP - 1, 5, bt_safe($row['no_hp'] ?? '-'), 0, 0, 'C');
        $x += BT_C_HP;

        // Jenis
        $this->SetXY($x + 1, $yStart + $vPad);
        $this->Cell(BT_C_JNS - 1, 5, $jenis, 0, 0, 'C');
        $x += BT_C_JNS;

        // Keperluan — MultiCell (wrap)
        $this->SetFont('Arial', '', 7);
        $this->SetXY($x + 1, $yStart + 1.5);
        $this->MultiCell(BT_C_KPL - 2, $lineH, $kep, 0, 'L');

        // Set posisi Y ke baris berikutnya
        $this->SetXY(BT_ML, $yStart + $rowH);
    }
}

// =====================================================================
// BUILD PDF
// =====================================================================
$pdf = new BukuTamuPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetMargins(BT_ML, 34, BT_MR);
$pdf->SetLineWidth(0.2);
$pdf->AddPage();

// Ringkasan
$pdf->StatBlock($totalTamu, $totalUmum, $totalInfo, $totalPeng);

// Tabel
if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetX(BT_ML);
    $pdf->Cell(BT_TW, 10, 'Tidak ada data pada periode yang dipilih.', 0, 1, 'C');
} else {
    $pdf->TableHeader();
    $no  = 1;
    $odd = true;
    foreach ($rows as $row) {
        $pdf->TableRow($no, $row, $odd);
        $no++;
        $odd = !$odd;
    }
}

// =====================================================================
// OUTPUT
// =====================================================================
@ob_end_clean();
$fn = 'Laporan_Buku_Tamu_' . $fromYmd . '_sd_' . $toYmd . '.pdf';
$pdf->Output('D', $fn);
exit;
