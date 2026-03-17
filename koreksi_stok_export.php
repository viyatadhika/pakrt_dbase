<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
require_once('fpdf/fpdf.php');

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if (!$from || !$to) {
    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-30 days'));
}

$fromSafe = date('Y-m-d', strtotime($from));
$toSafe   = date('Y-m-d', strtotime($to));

if ($fromSafe > $toSafe) {
    $tmp = $fromSafe;
    $fromSafe = $toSafe;
    $toSafe = $tmp;
}

function safeText($t)
{
    $t = trim((string)$t);
    return $t === '' ? '-' : $t;
}

function cutText($text, $max = 60)
{
    $text = trim((string)$text);
    if ($text === '') return '-';
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, $max - 3) . '...';
}

/* ===========================
   AMBIL DATA LAPORAN
=========================== */
$sql = "
    SELECT
        k.ref_kode,
        k.tanggal,
        k.created_at,
        k.jenis,
        k.alasan,
        k.keterangan,
        d.kode_barang,
        d.nama_barang,
        d.qty,
        d.satuan,
        COALESCE(mk.nama_kategori,'Tanpa Kategori') AS nama_kategori
    FROM koreksi_stok k
    JOIN koreksi_stok_detail d ON k.id = d.koreksi_stok_id
    LEFT JOIN master_barang mb ON mb.kode_barang = d.kode_barang
    LEFT JOIN master_kategori_barang mk ON mk.id = mb.kategori_id
    WHERE k.tanggal BETWEEN '$fromSafe' AND '$toSafe'
    ORDER BY k.tanggal DESC, k.created_at DESC, k.id DESC, d.id ASC
";

$q = $conn->query($sql);
if (!$q) {
    die("Query export error: " . $conn->error);
}

/* ===========================
   PDF CLASS
=========================== */
class PDF extends FPDF
{
    public $periodeText = '';

    function Header()
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'LAPORAN KOREKSI STOK', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, $this->periodeText, 0, 1, 'C');

        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'Dicetak: ' . date('d-m-Y H:i:s'), 0, 1, 'C');

        $this->Ln(3);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}

/* ===========================
   SETUP PDF
=========================== */
$pdf = new PDF('L', 'mm', 'A4');

$marginLeft  = 10;
$marginRight = 10;
$marginTop   = 10;

$pdf->SetMargins($marginLeft, $marginTop, $marginRight);
$pdf->SetAutoPageBreak(true, 12);

$pdf->periodeText = "Periode: " . date('d-m-Y', strtotime($fromSafe)) . " s/d " . date('d-m-Y', strtotime($toSafe));
$pdf->AddPage();

/* ===========================
   WIDTH TABLE PASTI PAS
=========================== */
$pageWidth   = $pdf->GetPageWidth();
$usableWidth = $pageWidth - $marginLeft - $marginRight;

/*
  FIXED + 1 FLEX biar pas 100%
*/
$w = [
    'no'     => 6,
    'ref'    => 26,
    'tgl'    => 20,
    'jam'    => 14,
    'jenis'  => 16,
    'alasan' => 55,
    'kode'   => 28,
    'nama'   => 0,   // FLEX
    'qty'    => 12,
    'sat'    => 20,
    'kat'    => 35
];

$totalFixed =
    $w['no'] + $w['ref'] + $w['tgl'] + $w['jam'] + $w['jenis'] +
    $w['alasan'] + $w['kode'] + $w['qty'] + $w['sat'] + $w['kat'];

$w['nama'] = $usableWidth - $totalFixed;

/* safety biar gak negatif */
if ($w['nama'] < 60) {
    $w['nama'] = 60;
    $w['alasan'] = max(40, $usableWidth - (
        $w['no'] + $w['ref'] + $w['tgl'] + $w['jam'] + $w['jenis'] +
        $w['kode'] + $w['nama'] + $w['qty'] + $w['sat'] + $w['kat']
    ));
}

/* ===========================
   HELPER: PAKSA MULAI DARI MARGIN KIRI
=========================== */
function setTableX($pdf, $marginLeft)
{
    $pdf->SetX($marginLeft);
}

function rowCells($pdf, $marginLeft, $cells)
{
    setTableX($pdf, $marginLeft);
    foreach ($cells as $c) {
        // $c = [width, height, text, border, ln, align, fill]
        $pdf->Cell($c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6] ?? false);
    }
}

/* ===========================
   HEADER TABLE
=========================== */
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(235, 235, 235);

rowCells($pdf, $marginLeft, [
    [$w['no'], 7, 'No', 1, 0, 'C', true],
    [$w['ref'], 7, 'Ref', 1, 0, 'C', true],
    [$w['tgl'], 7, 'Tanggal', 1, 0, 'C', true],
    [$w['jam'], 7, 'Jam', 1, 0, 'C', true],
    [$w['jenis'], 7, 'Jenis', 1, 0, 'C', true],
    [$w['alasan'], 7, 'Alasan', 1, 0, 'C', true],
    [$w['kode'], 7, 'Kode', 1, 0, 'C', true],
    [$w['nama'], 7, 'Nama Barang', 1, 0, 'C', true],
    [$w['qty'], 7, 'Qty', 1, 0, 'C', true],
    [$w['sat'], 7, 'Satuan', 1, 0, 'C', true],
    [$w['kat'], 7, 'Kategori', 1, 1, 'C', true],
]);

$pdf->SetFont('Arial', '', 8);

/* ===========================
   ROWS
=========================== */
$no = 1;

while ($r = $q->fetch_assoc()) {
    $tgl = $r['tanggal'] ? date('d-m-Y', strtotime($r['tanggal'])) : '-';
    $jam = $r['created_at'] ? date('H:i', strtotime($r['created_at'])) : '-';

    $jenis = strtolower($r['jenis'] ?? '');
    $jenisText = ($jenis === 'tambah') ? 'TAMBAH' : (($jenis === 'kurang') ? 'KURANG' : safeText($r['jenis']));

    rowCells($pdf, $marginLeft, [
        [$w['no'], 6, $no++, 1, 0, 'C'],
        [$w['ref'], 6, safeText($r['ref_kode']), 1, 0, 'L'],
        [$w['tgl'], 6, $tgl, 1, 0, 'L'],
        [$w['jam'], 6, $jam, 1, 0, 'L'],
        [$w['jenis'], 6, $jenisText, 1, 0, 'C'],
        [$w['alasan'], 6, cutText($r['alasan'], 60), 1, 0, 'L'],
        [$w['kode'], 6, safeText($r['kode_barang']), 1, 0, 'L'],
        [$w['nama'], 6, cutText($r['nama_barang'], 90), 1, 0, 'L'],
        [$w['qty'], 6, (int)$r['qty'], 1, 0, 'C'],
        [$w['sat'], 6, safeText($r['satuan']), 1, 0, 'C'],
        [$w['kat'], 6, cutText($r['nama_kategori'], 35), 1, 1, 'L'],
    ]);
}

$filename = "Laporan_Koreksi_Stok_" . $fromSafe . "_sd_" . $toSafe . ".pdf";
$pdf->Output("D", $filename);
exit;
