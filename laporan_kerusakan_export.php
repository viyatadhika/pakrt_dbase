<?php
session_start();
require 'config.php';
require 'fpdf/fpdf.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Jakarta');

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if (!$from || !$to) {
    $toDate = new DateTime('today');
    $fromDate = (new DateTime('today'))->modify('-30 days');
    $from = $fromDate->format('Y-m-d');
    $to   = $toDate->format('Y-m-d');
}

if ($from > $to) {
    die("Rentang tanggal tidak valid.");
}

/* =============================
   QUERY DATA
============================= */
$sql = "
    SELECT 
        lk.status, lk.created_at, lk.updated_at,
        lk.deskripsi,
        tl.nama AS tipe_lokasi,
        ml.nama_lokasi,
        ml2.nama_lantai,
        mr.nama_ruangan,
        mk.nomor_kamar,
        kk.nama_kategori,
        jk.nama_jenis
    FROM laporan_kerusakan lk
    LEFT JOIN master_tipe_lokasi tl ON lk.tipe_lokasi_id = tl.id
    LEFT JOIN master_lokasi ml ON lk.lokasi_id = ml.id
    LEFT JOIN master_lantai ml2 ON lk.lantai_id = ml2.id
    LEFT JOIN master_ruangan mr ON lk.ruangan_id = mr.id
    LEFT JOIN master_kamar mk ON lk.kamar_id = mk.id
    LEFT JOIN master_kategori_kerusakan kk ON lk.kategori_kerusakan_id = kk.id
    LEFT JOIN master_jenis_kerusakan jk ON lk.jenis_kerusakan_id = jk.id
    WHERE DATE(lk.created_at) BETWEEN ? AND ?
    ORDER BY kk.nama_kategori ASC, lk.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$result = $stmt->get_result();

/* =============================
   CACHE + REKAP
============================= */
$rows = [];
$rekap = []; // per kategori: total, selesai, dilaporkan
$grandTotal = 0;
$grandSelesai = 0;
$grandDilaporkan = 0;

while ($r = $result->fetch_assoc()) {
    $kat = $r['nama_kategori'] ?? 'Tanpa Kategori';
    $st  = $r['status'] ?? '';

    if (!isset($rekap[$kat])) {
        $rekap[$kat] = ['total' => 0, 'selesai' => 0, 'dilaporkan' => 0];
    }

    $rekap[$kat]['total']++;
    $grandTotal++;

    if ($st === 'selesai') {
        $rekap[$kat]['selesai']++;
        $grandSelesai++;
    } else {
        $rekap[$kat]['dilaporkan']++;
        $grandDilaporkan++;
    }

    $rows[] = $r;
}
$stmt->close();

/* =============================
   PDF CLASS
============================= */
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 7, 'LAPORAN KERUSAKAN', 0, 1, 'C');

        $this->SetFont('Arial', '', 10);
        $periode = 'Periode: ' . date('d/m/Y', strtotime($GLOBALS['from'])) . ' s/d ' . date('d/m/Y', strtotime($GLOBALS['to']));
        $this->Cell(0, 6, $periode, 0, 1, 'C');

        $this->Ln(3);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function NbLines($w, $txt)
    {
        $cw = $this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;

        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;

        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    function CheckPageBreak($h, $reprintHeader = null)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
            if (is_callable($reprintHeader)) $reprintHeader();
        }
    }

    function Row($data, $widths, $aligns, $reprintHeader = null)
    {
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
        }
        $h = 6 * $nb;

        $this->CheckPageBreak($h, $reprintHeader);

        for ($i = 0; $i < count($data); $i++) {
            $w = $widths[$i];
            $a = $aligns[$i] ?? 'L';

            $x = $this->GetX();
            $y = $this->GetY();

            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, 6, (string)$data[$i], 0, $a);
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }
}

/* =============================
   PDF SETUP
============================= */
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(7, 8, 7);
$pdf->SetAutoPageBreak(true, 12);

/* =============================
   HALAMAN 1: REKAP
============================= */
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'REKAP PER KATEGORI', 0, 1, 'L');
$pdf->Ln(2);

// Lebar efektif 283mm
$wRekap = [120, 40, 40, 40, 43]; // total 283
$aRekap = ['L', 'C', 'C', 'C', 'C'];

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(245, 247, 250);
$pdf->Cell($wRekap[0], 8, 'Kategori', 1, 0, 'C', true);
$pdf->Cell($wRekap[1], 8, 'Total', 1, 0, 'C', true);
$pdf->Cell($wRekap[2], 8, 'Dilaporkan', 1, 0, 'C', true);
$pdf->Cell($wRekap[3], 8, 'Selesai', 1, 0, 'C', true);
$pdf->Cell($wRekap[4], 8, 'Persentase Selesai', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

foreach ($rekap as $kat => $r) {
    $pct = ($r['total'] > 0) ? round(($r['selesai'] / $r['total']) * 100, 1) . '%' : '0%';
    $pdf->Row([$kat, (string)$r['total'], (string)$r['dilaporkan'], (string)$r['selesai'], $pct], $wRekap, $aRekap);
}

$pdf->SetFont('Arial', 'B', 9);
$pctAll = ($grandTotal > 0) ? round(($grandSelesai / $grandTotal) * 100, 1) . '%' : '0%';
$pdf->SetFillColor(230, 244, 255);
$pdf->Cell($wRekap[0], 8, 'TOTAL', 1, 0, 'L', true);
$pdf->Cell($wRekap[1], 8, $grandTotal, 1, 0, 'C', true);
$pdf->Cell($wRekap[2], 8, $grandDilaporkan, 1, 0, 'C', true);
$pdf->Cell($wRekap[3], 8, $grandSelesai, 1, 0, 'C', true);
$pdf->Cell($wRekap[4], 8, $pctAll, 1, 1, 'C', true);

/* =============================
   HALAMAN 2+: DETAIL
============================= */
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'DETAIL LAPORAN PER KATEGORI', 0, 1, 'L');
$pdf->Ln(2);

// Lebar efektif 283mm (tambah deskripsi)
$wDet = [10, 20, 40, 65, 98, 25, 25]; // total 283
$aDet = ['C', 'C', 'L', 'L', 'L', 'C', 'C'];

// header tabel detail (dipakai ulang saat page break)
$printDetailHeader = function () use ($pdf, $wDet) {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(245, 247, 250);

    $pdf->Cell($wDet[0], 8, 'No', 1, 0, 'C', true);
    $pdf->Cell($wDet[1], 8, 'Status', 1, 0, 'C', true);
    $pdf->Cell($wDet[2], 8, 'Jenis', 1, 0, 'C', true);
    $pdf->Cell($wDet[3], 8, 'Lokasi', 1, 0, 'C', true);
    $pdf->Cell($wDet[4], 8, 'Deskripsi', 1, 0, 'C', true);
    $pdf->Cell($wDet[5], 8, 'Lap.', 1, 0, 'C', true);
    $pdf->Cell($wDet[6], 8, 'Upd.', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 8);
};

$printDetailHeader();

if (count($rows) === 0) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Tidak ada data pada rentang tanggal tersebut.', 0, 1, 'C');
} else {

    $prevKat = null;
    $no = 1;

    foreach ($rows as $row) {
        $kat = $row['nama_kategori'] ?? 'Tanpa Kategori';

        if ($prevKat !== $kat) {
            // baris header kategori + rekap mini
            $pdf->CheckPageBreak(18, $printDetailHeader);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(230, 244, 255);
            $mini = $rekap[$kat] ?? ['total' => 0, 'dilaporkan' => 0, 'selesai' => 0];

            $judul = "Kategori: " . $kat . " | Total: " . $mini['total'] . " | Dilaporkan: " . $mini['dilaporkan'] . " | Selesai: " . $mini['selesai'];
            $pdf->Cell(283, 8, $judul, 1, 1, 'L', true);

            $pdf->SetFont('Arial', '', 8);
            $printDetailHeader();

            $prevKat = $kat;
            $no = 1; // reset nomor per kategori (hapus kalau mau global)
        }

        $lokasi = array_filter([
            $row['tipe_lokasi'],
            $row['nama_lokasi'],
            $row['nama_lantai'],
            $row['nama_ruangan'],
            $row['nomor_kamar'] ? 'No. ' . $row['nomor_kamar'] : null
        ]);
        $lokasiText = implode(' - ', $lokasi);

        $desc = trim((string)($row['deskripsi'] ?? ''));
        if ($desc === '') $desc = '-';

        // tanggal dipersingkat biar muat kolom kecil
        $tglLap = date('d/m/y', strtotime($row['created_at']));
        $tglUpd = date('d/m/y', strtotime($row['updated_at']));

        $pdf->Row([
            (string)$no++,
            ucfirst((string)$row['status']),
            (string)($row['nama_jenis'] ?? '-'),
            (string)($lokasiText ?: '-'),
            $desc,
            $tglLap,
            $tglUpd
        ], $wDet, $aDet, $printDetailHeader);
    }
}

$pdf->Output('D', 'laporan_kerusakan_' . $from . '_sd_' . $to . '_rekap_detail.pdf');
exit;
