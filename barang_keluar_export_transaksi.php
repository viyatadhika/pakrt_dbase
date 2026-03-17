<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
require_once('fpdf/fpdf.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID transaksi tidak valid");
}

// header
$qHeader = $conn->query("
    SELECT id, ref_kode, tanggal, created_at, penerima, keterangan
    FROM barang_keluar
    WHERE id = $id
    LIMIT 1
");
if (!$qHeader || $qHeader->num_rows < 1) {
    die("Transaksi tidak ditemukan");
}
$h = $qHeader->fetch_assoc();

// detail
$qDetail = $conn->query("
    SELECT
        d.kode_barang,
        d.nama_barang,
        d.qty,
        d.satuan,
        COALESCE(k.nama_kategori, 'Tanpa Kategori') AS nama_kategori
    FROM barang_keluar_detail d
    LEFT JOIN master_barang b ON b.kode_barang = d.kode_barang
    LEFT JOIN master_kategori_barang k ON k.id = b.kategori_id
    WHERE d.barang_keluar_id = $id
    ORDER BY d.id ASC
");
if (!$qDetail) {
    die("Query detail error: " . $conn->error);
}

function safeText($t)
{
    $t = trim((string)$t);
    return $t === '' ? '-' : $t;
}

function cutText($text, $max = 80)
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
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 8, 'BUKTI PENGELUARAN BARANG', 0, 1, 'C');

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
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

// info transaksi
$ref = safeText($h['ref_kode']);
$tgl = $h['tanggal'] ? date('d-m-Y', strtotime($h['tanggal'])) : '-';
$jam = $h['created_at'] ? date('H:i:s', strtotime($h['created_at'])) : '-';

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, "Ref: $ref", 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, "Tanggal: $tgl   Jam: $jam", 0, 1, 'L');
$pdf->Cell(0, 5, "Penerima: " . cutText($h['penerima'], 100), 0, 1, 'L');
$pdf->MultiCell(0, 5, "Keterangan: " . safeText($h['keterangan']), 0, 'L');

$pdf->Ln(3);

// tabel detail (lebar aman)
$w = [
    'no'   => 10,
    'kode' => 32,
    'nama' => 105,
    'kat'  => 55,
    'qty'  => 20,
    'sat'  => 25
];

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(235, 235, 235);

$pdf->Cell($w['no'], 8, 'No', 1, 0, 'C', true);
$pdf->Cell($w['kode'], 8, 'Kode', 1, 0, 'C', true);
$pdf->Cell($w['nama'], 8, 'Nama Barang', 1, 0, 'C', true);
$pdf->Cell($w['kat'], 8, 'Kategori', 1, 0, 'C', true);
$pdf->Cell($w['qty'], 8, 'Qty', 1, 0, 'C', true);
$pdf->Cell($w['sat'], 8, 'Satuan', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

$no = 1;
$totalQty = 0;

while ($d = $qDetail->fetch_assoc()) {
    $totalQty += (int)$d['qty'];

    $pdf->Cell($w['no'], 7, $no++, 1, 0, 'C');
    $pdf->Cell($w['kode'], 7, safeText($d['kode_barang']), 1, 0, 'L');
    $pdf->Cell($w['nama'], 7, cutText($d['nama_barang'], 60), 1, 0, 'L');
    $pdf->Cell($w['kat'], 7, cutText($d['nama_kategori'], 35), 1, 0, 'L');
    $pdf->Cell($w['qty'], 7, (int)$d['qty'], 1, 0, 'C');
    $pdf->Cell($w['sat'], 7, safeText($d['satuan']), 1, 1, 'C');
}

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, "Total Item: " . ($no - 1) . "    |    Total Qty: " . $totalQty, 0, 1, 'R');

$filename = "Bukti_Barang_Keluar_" . $ref . ".pdf";
$pdf->Output("D", $filename);
exit;
