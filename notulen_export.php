<?php
session_start();

require_once 'config.php';
require_once 'fpdf/fpdf.php';

$db = $conn ?? $koneksi ?? null;
if (!($db instanceof mysqli)) {
    die('Koneksi database tidak ditemukan.');
}
$db->set_charset('utf8mb4');

$id  = (int)($_GET['id'] ?? 0);
$pin = trim($_GET['pin'] ?? '');

if ($id <= 0 || $pin === '') {
    die('Parameter tidak valid.');
}

/* =========================
   Ambil data booking + validasi pin
   ========================= */
$st = $db->prepare("
    SELECT
        b.id,
        b.pin,
        b.nama,
        b.peminjam,
        b.start_date,
        b.end_date,
        b.jam_start,
        b.jam_end,
        b.jenis_lokasi,
        b.lokasi_external,
        COALESCE(r.nama_ruang, '') AS nama_ruang,
        COALESCE(r.lokasi, '') AS lokasi_ruang
    FROM booking_ruang_rapat b
    LEFT JOIN ruang_rapat r ON r.id = b.room_id
    WHERE b.id = ? AND b.pin = ?
    LIMIT 1
");
if (!$st) {
    die('Query booking gagal: ' . $db->error);
}
$st->bind_param('is', $id, $pin);
$st->execute();
$booking = $st->get_result()->fetch_assoc();
$st->close();

if (!$booking) {
    die('Booking tidak ditemukan atau PIN salah.');
}

/* =========================
   Ambil notulen
   ========================= */
$notulen = [
    'agenda' => '',
    'pimpinan_rapat' => '',
    'moderator' => '',
    'notulis' => '',
    'peserta_text' => '',
    'pembahasan' => '',
    'keputusan' => '',
    'tindak_lanjut' => ''
];

$st2 = $db->prepare("
    SELECT
        agenda,
        pimpinan_rapat,
        moderator,
        notulis,
        peserta_text,
        pembahasan,
        keputusan,
        tindak_lanjut
    FROM notulen_rapat
    WHERE booking_id = ?
    LIMIT 1
");
if (!$st2) {
    die('Query notulen gagal: ' . $db->error);
}
$st2->bind_param('i', $id);
$st2->execute();
$row = $st2->get_result()->fetch_assoc();
$st2->close();

if ($row) {
    $notulen = $row;
}

/* =========================
   Ambil dokumentasi
   ========================= */
$dokumentasi = [];
$st3 = $db->prepare("
    SELECT id, file_path, created_at
    FROM notulen_dokumentasi
    WHERE booking_id = ?
    ORDER BY id ASC
");
if ($st3) {
    $st3->bind_param('i', $id);
    $st3->execute();
    $rs3 = $st3->get_result();
    while ($r = $rs3->fetch_assoc()) {
        $dokumentasi[] = $r;
    }
    $st3->close();
}

/* =========================
   Helper
   ========================= */
function safeText($text)
{
    $text = (string)$text;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
}

function fmtTanggal($tanggal)
{
    if (!$tanggal) return '-';
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
    $p = explode('-', substr($tanggal, 0, 10));
    if (count($p) !== 3) return $tanggal;
    return (int)$p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}

function fmtJam($jam)
{
    return $jam ? substr($jam, 0, 5) : '-';
}

$lokasi = $booking['jenis_lokasi'] === 'external'
    ? ($booking['lokasi_external'] ?: '-')
    : trim($booking['nama_ruang'] . ($booking['lokasi_ruang'] ? ' - ' . $booking['lokasi_ruang'] : ''));

class PDF_Notulen extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, 'NOTULEN RAPAT', 0, 1, 'C');
        $this->Ln(2);
    }

    function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }

    function SectionTitle($title)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, safeText($title), 1, 1, 'L', true);
    }

    function LabelValue($label, $value)
    {
        $this->SetFont('Arial', '', 10);
        $this->Cell(42, 7, safeText($label), 0, 0);
        $this->Cell(4, 7, ':', 0, 0);
        $this->Cell(0, 7, safeText($value), 0, 1);
    }

    function MultiTextBlock($title, $content)
    {
        $this->SectionTitle($title);
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 6, safeText($content ?: '-'), 1, 'L');
        $this->Ln(2);
    }
}

/* =========================
   Buat PDF
   ========================= */
$pdf = new PDF_Notulen('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

/* Info rapat */
$pdf->SetFont('Arial', '', 10);
$pdf->LabelValue('Nama Kegiatan', $booking['nama']);
$pdf->LabelValue('Peminjam / Bidang', $booking['peminjam']);
$pdf->LabelValue('Tanggal', fmtTanggal($booking['start_date']) . ' s.d. ' . fmtTanggal($booking['end_date']));
$pdf->LabelValue('Jam', fmtJam($booking['jam_start']) . ' - ' . fmtJam($booking['jam_end']) . ' WIB');
$pdf->LabelValue('Lokasi', $lokasi);
$pdf->Ln(3);

/* Isi notulen */
$pdf->MultiTextBlock('Agenda', $notulen['agenda']);
$pdf->MultiTextBlock('Pimpinan Rapat', $notulen['pimpinan_rapat']);
$pdf->MultiTextBlock('Moderator', $notulen['moderator']);
$pdf->MultiTextBlock('Notulis', $notulen['notulis']);
$pdf->MultiTextBlock('Peserta', $notulen['peserta_text']);
$pdf->MultiTextBlock('Pembahasan', $notulen['pembahasan']);
$pdf->MultiTextBlock('Keputusan', $notulen['keputusan']);
$pdf->MultiTextBlock('Tindak Lanjut', $notulen['tindak_lanjut']);

/* Dokumentasi */
if (!empty($dokumentasi)) {
    $pdf->SectionTitle('Dokumentasi Rapat');
    $pdf->Ln(2);

    $imgWidth = 58;
    $imgHeight = 42;
    $marginX = 6;
    $startX = 12;
    $currentX = $startX;
    $currentY = $pdf->GetY();

    foreach ($dokumentasi as $i => $img) {
        $fileFs = __DIR__ . '/' . $img['file_path'];

        if (!is_file($fileFs)) {
            continue;
        }

        if ($currentX + $imgWidth > 195) {
            $currentX = $startX;
            $currentY += $imgHeight + 12;
        }

        if ($currentY + $imgHeight + 12 > 275) {
            $pdf->AddPage();
            $pdf->SectionTitle('Dokumentasi Rapat (Lanjutan)');
            $pdf->Ln(2);
            $currentX = $startX;
            $currentY = $pdf->GetY();
        }

        $pdf->Image($fileFs, $currentX, $currentY, $imgWidth, $imgHeight);
        $pdf->SetXY($currentX, $currentY + $imgHeight + 1);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell($imgWidth, 5, safeText('Dokumentasi #' . ($i + 1)), 0, 0, 'C');

        $currentX += $imgWidth + $marginX;
    }
}

/* Output */
$filename = 'Notulen_Rapat_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $booking['nama']) . '.pdf';
$pdf->Output('D', $filename);
exit;
