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
        bk.ref_kode,
        bk.tanggal,
        bk.created_at,
        bk.penerima,
        bk.keterangan,
        d.kode_barang,
        d.nama_barang,
        d.qty,
        d.satuan,
        COALESCE(k.nama_kategori,'Tanpa Kategori') AS nama_kategori
    FROM barang_keluar bk
    JOIN barang_keluar_detail d ON bk.id = d.barang_keluar_id
    LEFT JOIN master_barang b ON b.kode_barang = d.kode_barang
    LEFT JOIN master_kategori_barang k ON k.id = b.kategori_id
    WHERE bk.tanggal BETWEEN '$fromSafe' AND '$toSafe'
    ORDER BY bk.tanggal DESC, bk.created_at DESC, bk.id DESC, d.id ASC
";

$q = $conn->query($sql);
if (!$q) {
    die("Query export error: " . $conn->error);
}

function cutText($text, $max = 40)
{
    $text = trim((string)$text);
    if ($text === '') return '-';
    if (strlen($text) <= $max) return $text;
    return substr($text, 0, $max - 3) . '...';
}

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'LAPORAN RIWAYAT BARANG KELUAR', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Dicetak: ' . date('d-m-Y H:i:s'), 0, 1, 'C');

        $this->Ln(2);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');

// margin kecil biar tabel muat
$pdf->SetMargins(6, 8, 6);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 6, "Periode: " . date('d-m-Y', strtotime($fromSafe)) . " s/d " . date('d-m-Y', strtotime($toSafe)), 0, 1, 'L');
$pdf->Ln(1);

/**
 * A4 Landscape width = 297mm
 * Lebar efektif = 297 - (6+6) = 285mm
 * TOTAL kolom WAJIB <= 285
 */
$w = [
    'no' => 7,
    'ref' => 20,
    'tgl' => 17,
    'jam' => 15,
    'penerima' => 30,
    'ket' => 32,
    'kode' => 22,
    'nama' => 70,
    'qty' => 10,
    'sat' => 14,
    'kat' => 28
];
// total = 265 (AMAN)

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetFillColor(235, 235, 235);

$pdf->Cell($w['no'], 7, 'No', 1, 0, 'C', true);
$pdf->Cell($w['ref'], 7, 'Ref', 1, 0, 'C', true);
$pdf->Cell($w['tgl'], 7, 'Tanggal', 1, 0, 'C', true);
$pdf->Cell($w['jam'], 7, 'Jam', 1, 0, 'C', true);
$pdf->Cell($w['penerima'], 7, 'Penerima', 1, 0, 'C', true);
$pdf->Cell($w['ket'], 7, 'Keterangan', 1, 0, 'C', true);
$pdf->Cell($w['kode'], 7, 'Kode', 1, 0, 'C', true);
$pdf->Cell($w['nama'], 7, 'Nama Barang', 1, 0, 'C', true);
$pdf->Cell($w['qty'], 7, 'Qty', 1, 0, 'C', true);
$pdf->Cell($w['sat'], 7, 'Satuan', 1, 0, 'C', true);
$pdf->Cell($w['kat'], 7, 'Kategori', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 7.5);

$no = 1;
while ($r = $q->fetch_assoc()) {
    $tgl = $r['tanggal'] ? date('d-m-Y', strtotime($r['tanggal'])) : '';
    $jam = $r['created_at'] ? date('H:i:s', strtotime($r['created_at'])) : '';

    $pdf->Cell($w['no'], 6, $no++, 1, 0, 'C');
    $pdf->Cell($w['ref'], 6, cutText($r['ref_kode'], 18), 1, 0, 'L');
    $pdf->Cell($w['tgl'], 6, $tgl, 1, 0, 'L');
    $pdf->Cell($w['jam'], 6, $jam, 1, 0, 'L');

    $pdf->Cell($w['penerima'], 6, cutText($r['penerima'], 20), 1, 0, 'L');
    $pdf->Cell($w['ket'], 6, cutText($r['keterangan'], 24), 1, 0, 'L');

    $pdf->Cell($w['kode'], 6, cutText($r['kode_barang'], 18), 1, 0, 'L');
    $pdf->Cell($w['nama'], 6, cutText($r['nama_barang'], 45), 1, 0, 'L');

    $pdf->Cell($w['qty'], 6, (int)$r['qty'], 1, 0, 'C');
    $pdf->Cell($w['sat'], 6, cutText($r['satuan'], 10), 1, 0, 'C');
    $pdf->Cell($w['kat'], 6, cutText($r['nama_kategori'], 18), 1, 1, 'L');
}

$filename = "Laporan_Barang_Keluar_" . $fromSafe . "_sd_" . $toSafe . ".pdf";
$pdf->Output("D", $filename);
exit;
