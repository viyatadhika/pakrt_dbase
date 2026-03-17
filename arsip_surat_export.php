<?php
ob_start();
session_start();
require __DIR__ . '/fpdf/fpdf.php';
include 'config.php';

function tanggalIndo($tgl)
{
    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $t = strtotime($tgl);
    return date('j', $t) . ' ' . $bulan[(int)date('m', $t)] . ' ' . date('Y', $t);
}

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to'] ?? date('Y-m-d');

$stmt = $conn->prepare("SELECT * FROM arsip_surat 
    WHERE DATE(tanggal_surat) BETWEEN ? AND ?
    ORDER BY tanggal_surat DESC");
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$result = $stmt->get_result();

class PDF extends FPDF
{
    function Header()
    {
        global $from, $to;

        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, 'LAPORAN ARSIP SURAT', 0, 1, 'C');

        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 6, 'Periode: ' . tanggalIndo($from) . ' s/d ' . tanggalIndo($to), 0, 1, 'C');

        $this->Ln(4);

        $this->SetLineWidth(0.8);
        $this->Line(10, 30, 287, 30);
        $this->SetLineWidth(0.2);
        $this->Line(10, 32, 287, 32);

        $this->Ln(8);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }

    function Row($data, $widths)
    {
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
        }
        $h = 6 * $nb;

        if ($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);

        for ($i = 0; $i < count($data); $i++) {
            $w = $widths[$i];
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, 6, $data[$i], 0, 'L');
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n")
            $nb--;
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
            if ($c == ' ')
                $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j)
                        $i++;
                } else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else
                $i++;
        }
        return $nl;
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

if ($result->num_rows == 0) {
    $pdf->Cell(0, 10, 'Tidak ada data pada periode ini.', 0, 1);
} else {

    // TOTAL WIDTH = 277 mm (A4 landscape minus margin)
    $widths = [12, 28, 35, 28, 60, 114];

    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Row(['No', 'Tanggal', 'Nomor Surat', 'Jenis', 'Pengirim / Tujuan', 'Perihal'], $widths);

    $pdf->SetFont('Arial', '', 10);

    $no = 1;
    while ($row = $result->fetch_assoc()) {
        $pdf->Row([
            $no,
            tanggalIndo($row['tanggal_surat']),
            $row['nomor_surat'],
            ucfirst($row['jenis']),
            $row['pengirim'],
            $row['perihal']
        ], $widths);
        $no++;
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Total Surat: ' . ($no - 1) . ' Data', 0, 1, 'L');
}

@ob_end_clean();
$pdf->Output('D', 'Laporan_Arsip_Surat.pdf');
exit;
