<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
require_once('fpdf/fpdf.php');

/* ==========================
   DEFAULT TANPA PARAMETER
   laporan stok = per hari ini
========================== */
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if (!$from || !$to) {
    $from = date('Y-m-d');
    $to   = date('Y-m-d');
}

$fromSafe = date('Y-m-d', strtotime($from));
$toSafe   = date('Y-m-d', strtotime($to));

/* ==========================
   QUERY DATA STOK + KATEGORI
========================== */
$sql = "
    SELECT
        COALESCE(k.nama_kategori,'Tanpa Kategori') AS kategori,
        b.kode_barang,
        b.nama_barang,
        b.stok,
        COALESCE(s.nama,'') AS satuan
    FROM master_barang b
    LEFT JOIN master_kategori_barang k ON b.kategori_id = k.id
    LEFT JOIN master_satuan s ON b.satuan_id = s.id
    ORDER BY kategori ASC, b.nama_barang ASC
";

$q = $conn->query($sql);
if (!$q) {
    die("Query export error: " . $conn->error);
}

/* ==========================
   PDF CLASS
========================== */
class PDF extends FPDF
{
    public $periodeText = '';

    function Header()
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 8, 'LAPORAN STOK BARANG (PER HARI INI)', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, $this->periodeText, 0, 1, 'C');

        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, 'Dicetak: ' . date('d-m-Y H:i:s'), 0, 1, 'C');

        $this->Ln(4);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}

/* ==========================
   HELPER
========================== */
function cutText($text, $max = 100)
{
    $text = trim((string)$text);
    if ($text === '') return '-';
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, $max - 3) . '...';
}

/* ==========================
   SETUP PDF
========================== */
$pdf = new PDF('L', 'mm', 'A4');

/* margin kiri kanan sama */
$marginLR = 12;
$pdf->SetMargins($marginLR, 10, $marginLR);
$pdf->SetAutoPageBreak(true, 12);

$pdf->periodeText = "Tanggal: " . date('d-m-Y', strtotime($toSafe));
$pdf->AddPage();

/* ==========================
   AUTO WIDTH TABLE (FULL WIDTH)
   lebar tabel = lebar halaman - margin kiri - margin kanan
========================== */
$pageWidth = $pdf->GetPageWidth();
$tableWidth = $pageWidth - ($marginLR * 2);

/*
  Kita buat rasio kolom agar proporsional
  total ratio = 100
*/
$ratio = [
    'no'   => 4,
    'kode' => 14,
    'nama' => 55,
    'stok' => 8,
    'sat'  => 8
];

$ratioTotal = array_sum($ratio);

$w = [];
foreach ($ratio as $k => $r) {
    $w[$k] = round(($tableWidth * $r) / $ratioTotal, 2);
}

/* biar benar-benar PAS mentok, kita koreksi rounding */
$sumW = array_sum($w);
$diff = $tableWidth - $sumW;
$w['nama'] += $diff; // tambahkan selisih ke kolom nama (paling besar)

/* ==========================
   HEADER TABEL
========================== */
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);

$pdf->Cell($w['no'], 8, 'No', 1, 0, 'C', true);
$pdf->Cell($w['kode'], 8, 'Kode', 1, 0, 'C', true);
$pdf->Cell($w['nama'], 8, 'Nama Barang', 1, 0, 'C', true);
$pdf->Cell($w['stok'], 8, 'Stok', 1, 0, 'C', true);
$pdf->Cell($w['sat'], 8, 'Satuan', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

/* ==========================
   ISI TABEL (1 tabel kesatuan)
========================== */
$kategoriAktif = '';
$no = 1;

while ($r = $q->fetch_assoc()) {

    $kategori = $r['kategori'] ?? 'Tanpa Kategori';

    // baris kategori pemisah
    if ($kategoriAktif !== $kategori) {
        $kategoriAktif = $kategori;

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(245, 248, 255);

        $pdf->Cell($tableWidth, 7, "  " . $kategoriAktif, 1, 1, 'L', true);

        $pdf->SetFont('Arial', '', 9);
    }

    $kode = (string)$r['kode_barang'];
    $nama = cutText($r['nama_barang'], 120);
    $stok = (int)$r['stok'];
    $sat  = (string)$r['satuan'];

    $pdf->Cell($w['no'], 7, $no++, 1, 0, 'C');
    $pdf->Cell($w['kode'], 7, $kode, 1, 0, 'L');
    $pdf->Cell($w['nama'], 7, $nama, 1, 0, 'L');
    $pdf->Cell($w['stok'], 7, number_format($stok), 1, 0, 'C');
    $pdf->Cell($w['sat'], 7, $sat, 1, 1, 'C');
}

$filename = "Laporan_Stok_Barang_Per_Hari_Ini_" . date('Y-m-d') . ".pdf";
$pdf->Output("D", $filename);
exit;
