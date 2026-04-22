<?php
require_once 'config.php';
require_once 'fpdf/fpdf.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$db = $conn ?? $koneksi ?? null;
if (!($db instanceof mysqli)) die('Koneksi database tidak ditemukan.');
$db->set_charset('utf8mb4');

$id  = (int)($_GET['id'] ?? 0);
$pin = trim($_GET['pin'] ?? '');

if ($id <= 0 || $pin === '') die('Akses tidak valid');

/* =========================
   Booking
========================= */
$st = $db->prepare("
    SELECT b.*, 
           COALESCE(r.nama_ruang,'') AS nama_ruang,
           COALESCE(r.lokasi,'') AS lokasi_ruang
    FROM booking_ruang_rapat b
    LEFT JOIN ruang_rapat r ON r.id = b.room_id
    WHERE b.id = ? AND b.pin = ?
    LIMIT 1
");
$st->bind_param('is', $id, $pin);
$st->execute();
$booking = $st->get_result()->fetch_assoc();
$st->close();

if (!$booking) die('Booking tidak ditemukan');

/* =========================
   Absensi
========================= */
$data = [];
$st2 = $db->prepare("
    SELECT nama_peserta, unit_jabatan, instansi, tanda_tangan
    FROM absensi_rapat
    WHERE booking_id = ?
    ORDER BY waktu_hadir ASC
");
$st2->bind_param('i', $id);
$st2->execute();
$rs = $st2->get_result();
while ($r = $rs->fetch_assoc()) $data[] = $r;
$st2->close();

/* =========================
   Helper
========================= */
function txt($v)
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $v ?? '');
}

function tgl($d)
{
    if (!$d) return '-';
    $b = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    $p = explode('-', substr($d, 0, 10));
    return $p[2] . ' ' . $b[(int)$p[1]] . ' ' . $p[0];
}

$tanggal = $booking['start_date'] === $booking['end_date']
    ? tgl($booking['start_date'])
    : tgl($booking['start_date']) . ' s.d. ' . tgl($booking['end_date']);

$lokasi = $booking['jenis_lokasi'] === 'internal'
    ? trim($booking['nama_ruang'] . ' - ' . $booking['lokasi_ruang'])
    : ($booking['lokasi_external'] ?: '-');

/* =========================
   PDF CLASS
========================= */
class PDF extends FPDF
{

    function Header()
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 8, 'DAFTAR HADIR RAPAT', 0, 1, 'C');
        $this->Ln(2);
    }

    function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }

    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
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
                $i = ($sep == -1) ? $i + 1 : $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else $i++;
        }
        return $nl;
    }

    function RowMulti($data, $w)
    {
        $lineH = 4;        // <<< tinggi baris kecil
        $minH = 16;        // <<< minimum height kecil

        $nb = 0;
        foreach ($data as $i => $txt)
            $nb = max($nb, $this->NbLines($w[$i], $txt));

        $h = max($minH, $lineH * $nb + 4);

        if ($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage();

        $x = $this->GetX();
        $y = $this->GetY();

        foreach ($data as $i => $txt) {
            $this->Rect($x, $y, $w[$i], $h);
            $this->SetXY($x, $y + 2);
            $this->MultiCell($w[$i], $lineH, $txt, 0, 'L');
            $x += $w[$i];
            $this->SetXY($x, $y);
        }

        $this->SetXY($this->lMargin, $y + $h);
        return $h;
    }
}

/* =========================
   BUILD PDF
========================= */
$pdf = new PDF('P', 'mm', 'A4');
$left = 10;
$pdf->SetMargins($left, 10, 10);
$pdf->AddPage();

/* Info */
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(35, 6, 'Kegiatan', 0, 0);
$pdf->Cell(4, 6, ':');
$pdf->Cell(0, 6, txt($booking['nama']), 0, 1);
$pdf->Cell(35, 6, 'Tanggal', 0, 0);
$pdf->Cell(4, 6, ':');
$pdf->Cell(0, 6, txt($tanggal), 0, 1);
$pdf->Cell(35, 6, 'Jam', 0, 0);
$pdf->Cell(4, 6, ':');
$pdf->Cell(0, 6, substr($booking['jam_start'], 0, 5) . ' - ' . substr($booking['jam_end'], 0, 5), 0, 1);
$pdf->Cell(35, 6, 'Lokasi', 0, 0);
$pdf->Cell(4, 6, ':');
$pdf->MultiCell(0, 6, txt($lokasi));

$pdf->Ln(4);

/* Kolom */
$w = [8, 45, 40, 40, 57];

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell($w[0], 8, 'No', 1, 0, 'C', true);
$pdf->Cell($w[1], 8, 'Nama', 1, 0, 'C', true);
$pdf->Cell($w[2], 8, 'Unit/Jabatan', 1, 0, 'C', true);
$pdf->Cell($w[3], 8, 'Instansi', 1, 0, 'C', true);
$pdf->Cell($w[4], 8, 'Tanda Tangan', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 8);

$tmp = [];
$no = 1;

foreach ($data as $d) {

    $y = $pdf->GetY();

    $h = $pdf->RowMulti([
        $no++,
        txt($d['nama_peserta']),
        txt($d['unit_jabatan']),
        txt($d['instansi']),
        ''
    ], $w);

    if (!empty($d['tanda_tangan']) && strpos($d['tanda_tangan'], 'base64') !== false) {
        $img = base64_decode(explode(',', $d['tanda_tangan'])[1]);
        $file = sys_get_temp_dir() . '/ttd_' . uniqid() . '.png';
        file_put_contents($file, $img);
        $tmp[] = $file;

        $pdf->Image(
            $file,
            $left + $w[0] + $w[1] + $w[2] + $w[3] + 1.5,
            $y + 1.5,
            $w[4] - 3,
            $h - 3
        );
    }
}

/* cleanup */
foreach ($tmp as $f) if (file_exists($f)) unlink($f);

/* output */
$filename = 'Absensi_Rapat_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $booking['nama']) . '.pdf';
$pdf->Output('D', $filename);
exit;
