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

$sql = "
    SELECT 
        so.ref_kode,
        so.tanggal,
        so.created_at,
        so.lokasi,
        so.keterangan,
        d.kode_barang,
        d.nama_barang,
        d.stok_sistem,
        d.stok_fisik,
        d.selisih,
        d.satuan,
        COALESCE(k.nama_kategori,'Tanpa Kategori') AS nama_kategori
    FROM stok_opname so
    JOIN stok_opname_detail d ON so.id = d.stok_opname_id
    LEFT JOIN master_barang b 
        ON b.kode_barang COLLATE utf8mb4_general_ci = d.kode_barang COLLATE utf8mb4_general_ci
    LEFT JOIN master_kategori_barang k ON k.id = b.kategori_id
    WHERE so.tanggal BETWEEN '$fromSafe' AND '$toSafe'
    ORDER BY so.tanggal DESC, so.created_at DESC, so.id DESC, d.id ASC
";

$q = $conn->query($sql);
if (!$q) {
    die("Query export error: " . $conn->error);
}

/* ==========================
   HELPER
========================== */
function safeText($t)
{
    $t = trim((string)$t);
    return $t === '' ? '-' : $t;
}

function cutText($text, $max = 40)
{
    $text = trim((string)$text);
    if ($text === '') return '-';
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, $max - 3) . '...';
}

/* ==========================
   PDF CLASS + FIT CELL
========================== */
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'LAPORAN STOK OPNAME', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Dicetak: ' . date('d-m-Y H:i:s'), 0, 1, 'C');
        $this->Ln(3);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }

    // Fit text into cell (shrink font if needed)
    function FitCell($w, $h, $txt, $border = 0, $ln = 0, $align = 'L', $fill = false)
    {
        $txt = (string)$txt;
        $strWidth = $this->GetStringWidth($txt);

        if ($strWidth <= $w - 2) {
            $this->Cell($w, $h, $txt, $border, $ln, $align, $fill);
            return;
        }

        $currentSize = $this->FontSizePt;
        $minSize = 6;

        while ($strWidth > ($w - 2) && $currentSize > $minSize) {
            $currentSize -= 0.5;
            $this->SetFont($this->FontFamily, $this->FontStyle, $currentSize);
            $strWidth = $this->GetStringWidth($txt);
        }

        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill);
        $this->SetFont($this->FontFamily, $this->FontStyle, 8); // balik normal
    }
}

$pdf = new PDF('L', 'mm', 'A4');

/* ==========================
   MARGIN & WIDTH TABLE
========================== */
$marginL = 8;
$marginR = 8;
$marginT = 10;

$pdf->SetMargins($marginL, $marginT, $marginR);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

// lebar halaman A4 landscape = 297mm
$tableW = 297 - $marginL - $marginR; // harus full rata kiri kanan

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, "Periode: " . date('d-m-Y', strtotime($fromSafe)) . " s/d " . date('d-m-Y', strtotime($toSafe)), 0, 1, 'L');
$pdf->Ln(2);

/* ==========================
   SET KOLOM (TOTAL HARUS PAS)
   tableW = 281
========================== */
$w = [];
$w['no']   = 6;
$w['ref']  = 22;
$w['tgl']  = 18;
$w['jam']  = 16;
$w['lok']  = 30;
$w['kode'] = 24;
$w['nama'] = 0; // fleksibel
$w['sys']  = 14;
$w['fis']  = 14;
$w['sel']  = 14;
$w['sat']  = 13;

// hitung sisa untuk nama barang supaya pas rata kiri kanan
$fixed = $w['no'] + $w['ref'] + $w['tgl'] + $w['jam'] + $w['lok'] + $w['kode'] + $w['sys'] + $w['fis'] + $w['sel'] + $w['sat'];
$w['nama'] = $tableW - $fixed; // otomatis full

/* ==========================
   HEADER TABLE
========================== */
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(235, 235, 235);

$pdf->Cell($w['no'], 7, 'No', 1, 0, 'C', true);
$pdf->Cell($w['ref'], 7, 'Ref', 1, 0, 'C', true);
$pdf->Cell($w['tgl'], 7, 'Tanggal', 1, 0, 'C', true);
$pdf->Cell($w['jam'], 7, 'Jam', 1, 0, 'C', true);
$pdf->Cell($w['lok'], 7, 'Lokasi', 1, 0, 'C', true);
$pdf->Cell($w['kode'], 7, 'Kode', 1, 0, 'C', true);
$pdf->Cell($w['nama'], 7, 'Nama Barang', 1, 0, 'C', true);
$pdf->Cell($w['sys'], 7, 'Sistem', 1, 0, 'C', true);
$pdf->Cell($w['fis'], 7, 'Fisik', 1, 0, 'C', true);
$pdf->Cell($w['sel'], 7, 'Selisih', 1, 0, 'C', true);
$pdf->Cell($w['sat'], 7, 'Satuan', 1, 1, 'C', true);

/* ==========================
   ISI TABLE
========================== */
$pdf->SetFont('Arial', '', 8);

$no = 1;
while ($r = $q->fetch_assoc()) {

    $tgl = $r['tanggal'] ? date('d-m-Y', strtotime($r['tanggal'])) : '-';
    $jam = $r['created_at'] ? date('H:i:s', strtotime($r['created_at'])) : '-';

    $pdf->Cell($w['no'], 6, $no++, 1, 0, 'C');
    $pdf->FitCell($w['ref'], 6, safeText($r['ref_kode']), 1, 0, 'L');
    $pdf->Cell($w['tgl'], 6, $tgl, 1, 0, 'L');
    $pdf->Cell($w['jam'], 6, $jam, 1, 0, 'L');

    $pdf->FitCell($w['lok'], 6, cutText($r['lokasi'], 25), 1, 0, 'L');
    $pdf->FitCell($w['kode'], 6, safeText($r['kode_barang']), 1, 0, 'L');
    $pdf->FitCell($w['nama'], 6, cutText($r['nama_barang'], 60), 1, 0, 'L');

    $pdf->Cell($w['sys'], 6, (int)$r['stok_sistem'], 1, 0, 'C');
    $pdf->Cell($w['fis'], 6, (int)$r['stok_fisik'], 1, 0, 'C');
    $pdf->Cell($w['sel'], 6, (int)$r['selisih'], 1, 0, 'C');
    $pdf->FitCell($w['sat'], 6, safeText($r['satuan']), 1, 1, 'C');
}

$filename = "Laporan_Stok_Opname_" . $fromSafe . "_sd_" . $toSafe . ".pdf";
$pdf->Output("D", $filename);
exit;
