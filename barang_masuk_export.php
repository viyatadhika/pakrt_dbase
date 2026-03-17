<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
require_once('fpdf/fpdf.php'); // sesuaikan path FPDF kamu

// ambil parameter tanggal
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

// default kalau kosong = 30 hari terakhir
if (!$from || !$to) {
    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-30 days'));
}

$fromSafe = date('Y-m-d', strtotime($from));
$toSafe   = date('Y-m-d', strtotime($to));

$sql = "
    SELECT 
        bm.ref_kode,
        bm.tanggal,
        bm.created_at,
        bm.supplier,
        bm.no_sj,
        bm.file_sj,
        d.kode_barang,
        d.nama_barang,
        d.qty,
        d.satuan,
        COALESCE(k.nama_kategori,'Tanpa Kategori') AS nama_kategori
    FROM barang_masuk bm
    JOIN barang_masuk_detail d ON bm.id = d.barang_masuk_id
    LEFT JOIN master_barang b 
        ON b.kode_barang COLLATE utf8mb4_general_ci = d.kode_barang COLLATE utf8mb4_general_ci
    LEFT JOIN master_kategori_barang k ON k.id = b.kategori_id
    WHERE bm.tanggal BETWEEN '$fromSafe' AND '$toSafe'
    ORDER BY bm.tanggal DESC, bm.created_at DESC, bm.id DESC, d.id ASC
";


$q = $conn->query($sql);
if (!$q) {
    die("Query export error: " . $conn->error);
}

// ====== PDF ======
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'LAPORAN RIWAYAT BARANG MASUK', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Dicetak: ' . date('d-m-Y H:i:s'), 0, 1, 'C');

        $this->Ln(4);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape biar muat banyak kolom
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, "Periode: " . date('d-m-Y', strtotime($fromSafe)) . " s/d " . date('d-m-Y', strtotime($toSafe)), 0, 1, 'L');
$pdf->Ln(2);

// Header tabel
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(230, 230, 230);

// Lebar kolom (total harus <= 277mm kira2)
$w = [
    'no' => 8,
    'ref' => 20,
    'tgl' => 18,
    'jam' => 15,
    'sup' => 35,
    'sj'  => 22,
    'kode' => 22,
    'nama' => 65,
    'qty' => 12,
    'sat' => 15,
    'kat' => 30
];

$pdf->Cell($w['no'], 7, 'No', 1, 0, 'C', true);
$pdf->Cell($w['ref'], 7, 'Ref', 1, 0, 'C', true);
$pdf->Cell($w['tgl'], 7, 'Tanggal', 1, 0, 'C', true);
$pdf->Cell($w['jam'], 7, 'Jam', 1, 0, 'C', true);
$pdf->Cell($w['sup'], 7, 'Supplier', 1, 0, 'C', true);
$pdf->Cell($w['sj'], 7, 'No SJ', 1, 0, 'C', true);
$pdf->Cell($w['kode'], 7, 'Kode', 1, 0, 'C', true);
$pdf->Cell($w['nama'], 7, 'Nama Barang', 1, 0, 'C', true);
$pdf->Cell($w['qty'], 7, 'Qty', 1, 0, 'C', true);
$pdf->Cell($w['sat'], 7, 'Satuan', 1, 0, 'C', true);
$pdf->Cell($w['kat'], 7, 'Kategori', 1, 1, 'C', true);

// Isi data
$pdf->SetFont('Arial', '', 8);

$no = 1;
while ($r = $q->fetch_assoc()) {
    $tgl = $r['tanggal'] ? date('d-m-Y', strtotime($r['tanggal'])) : '';
    $jam = $r['created_at'] ? date('H:i:s', strtotime($r['created_at'])) : '';

    $pdf->Cell($w['no'], 6, $no++, 1, 0, 'C');
    $pdf->Cell($w['ref'], 6, $r['ref_kode'], 1, 0, 'L');
    $pdf->Cell($w['tgl'], 6, $tgl, 1, 0, 'L');
    $pdf->Cell($w['jam'], 6, $jam, 1, 0, 'L');

    $pdf->Cell($w['sup'], 6, substr($r['supplier'], 0, 30), 1, 0, 'L');
    $pdf->Cell($w['sj'], 6, substr($r['no_sj'], 0, 18), 1, 0, 'L');

    $pdf->Cell($w['kode'], 6, $r['kode_barang'], 1, 0, 'L');
    $pdf->Cell($w['nama'], 6, substr($r['nama_barang'], 0, 45), 1, 0, 'L');

    $pdf->Cell($w['qty'], 6, $r['qty'], 1, 0, 'C');
    $pdf->Cell($w['sat'], 6, $r['satuan'], 1, 0, 'C');
    $pdf->Cell($w['kat'], 6, substr($r['nama_kategori'] ?? 'Tanpa Kategori', 0, 18), 1, 1, 'L');
}

$filename = "Laporan_Barang_Masuk_" . $fromSafe . "_sd_" . $toSafe . ".pdf";
$pdf->Output("D", $filename);
exit;
