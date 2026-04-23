<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/fpdf/fpdf.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Koneksi database tidak ditemukan.');
}

$conn->set_charset('utf8mb4');

/* =========================
   HELPER
========================= */
function fmtDateIndo($d)
{
    if (!$d) return '-';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    $p = explode('-', substr($d, 0, 10));
    if (count($p) < 3) return $d;
    return $p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}

function fmtTimeOnly($t)
{
    if (!$t) return '-';
    $t = (string)$t;
    if (strlen($t) >= 16 && strpos($t, ' ') !== false) return substr($t, 11, 5);
    return substr($t, 0, 5);
}

function hitungDayKeMengikutiKegiatan($startDate, $endDate, $currentDate)
{
    $start = new DateTime($startDate);
    $end   = new DateTime($endDate);
    $curr  = new DateTime($currentDate);

    if ($curr < $start) return 0;
    if ($curr > $end) $curr = clone $end;

    $diff = $start->diff($curr);
    return (int)$diff->days + 1;
}

function cleanFileName($name)
{
    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $name);
    $name = trim($name, '_');
    return $name !== '' ? $name : 'Absensi_Rapat';
}

function pdfText($text)
{
    $text = (string)$text;

    $replace = [
        '•' => '-',
        '–' => '-',
        '—' => '-',
        '’' => "'",
        '‘' => "'",
        '“' => '"',
        '”' => '"',
        '…' => '...',
        '−' => '-',
    ];

    $text = strtr($text, $replace);

    $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
    return $converted !== false ? $converted : $text;
}

function formatSesiLabelPdf($startDate, $endDate, $selectedDate, $selectedDay)
{
    if ($startDate === $endDate) {
        return fmtDateIndo($selectedDate);
    }
    return 'Day ' . $selectedDay . ' - ' . fmtDateIndo($selectedDate);
}

/* =========================
   AKSES
========================= */
$isAdmin = is_array($_SESSION['user'] ?? null)
    && isset($_SESSION['user']['role'])
    && strtolower((string)$_SESSION['user']['role']) === 'admin';

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pin       = trim((string)($_GET['pin'] ?? ''));
$tanggal   = trim((string)($_GET['tanggal'] ?? ''));

if ($bookingId <= 0 || (!$isAdmin && $pin === '')) {
    die('Akses ditolak. PIN wajib diisi.');
}

/* =========================
   BOOKING
========================= */
$sql = $isAdmin
    ? "SELECT b.id, b.pin, b.nama, b.peminjam, b.start_date, b.end_date,
              b.jam_start, b.jam_end, b.jenis_lokasi, b.lokasi_external,
              COALESCE(r.nama_ruang,'') AS ruang,
              COALESCE(r.lokasi,'') AS lokasi_ruang
       FROM booking_ruang_rapat b
       LEFT JOIN ruang_rapat r ON r.id = b.room_id
       WHERE b.id = ?
       LIMIT 1"
    : "SELECT b.id, b.pin, b.nama, b.peminjam, b.start_date, b.end_date,
              b.jam_start, b.jam_end, b.jenis_lokasi, b.lokasi_external,
              COALESCE(r.nama_ruang,'') AS ruang,
              COALESCE(r.lokasi,'') AS lokasi_ruang
       FROM booking_ruang_rapat b
       LEFT JOIN ruang_rapat r ON r.id = b.room_id
       WHERE b.id = ? AND b.pin = ?
       LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) die('Query booking gagal: ' . $conn->error);

if ($isAdmin) {
    $stmt->bind_param('i', $bookingId);
} else {
    $stmt->bind_param('is', $bookingId, $pin);
}

$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    die('Akses ditolak. PIN salah atau booking tidak ditemukan.');
}

if ($isAdmin && $pin === '') {
    $pin = $booking['pin'] ?? '';
}

/* =========================
   TANGGAL / DAY AKTIF
========================= */
$today = date('Y-m-d');
$selectedDate = $tanggal !== '' ? $tanggal : $today;

if ($selectedDate < $booking['start_date']) {
    $selectedDate = $booking['start_date'];
}

$selectedDay = hitungDayKeMengikutiKegiatan(
    $booking['start_date'],
    $booking['end_date'],
    $selectedDate
);

$isMultiDay = $booking['start_date'] !== $booking['end_date'];
$sesiLabel = formatSesiLabelPdf($booking['start_date'], $booking['end_date'], $selectedDate, $selectedDay);

$lokasi = $booking['jenis_lokasi'] === 'external'
    ? ($booking['lokasi_external'] ?: '-')
    : trim(($booking['ruang'] ?: '-') . (!empty($booking['lokasi_ruang']) ? ' - ' . $booking['lokasi_ruang'] : ''));

/* =========================
   DATA ABSENSI
========================= */
$stmt = $conn->prepare("
    SELECT id, booking_id, tanggal_hadir, day_ke, nama_peserta, unit_jabatan, instansi, tanda_tangan, waktu_hadir
    FROM absensi_rapat
    WHERE booking_id = ? AND tanggal_hadir = ?
    ORDER BY id ASC
");
if (!$stmt) die('Query absensi gagal: ' . $conn->error);
$stmt->bind_param('is', $bookingId, $selectedDate);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) $rows[] = $row;
$stmt->close();

/* =========================
   PDF CLASS
========================= */
class PDF extends FPDF
{
    public $reportTitle = 'LAPORAN ABSENSI RAPAT';
    public $reportSubTitle = '';

    function Header()
    {
        $this->SetY(8);
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 6, $this->reportTitle, 0, 1, 'C');

        if ($this->reportSubTitle !== '') {
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, $this->reportSubTitle, 0, 1, 'C');
        }

        $this->Ln(1);
    }

    function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 6, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}

/* =========================
   GENERATE PDF
========================= */
$pdf = new PDF('P', 'mm', 'A4');
$pdf->reportTitle = pdfText('LAPORAN ABSENSI RAPAT');
$pdf->reportSubTitle = pdfText($sesiLabel);
$pdf->SetMargins(6, 10, 6);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

/* =========================
   INFO RAPAT
========================= */
$pdf->SetFont('Arial', '', 9);

$pdf->Cell(34, 6, pdfText('Nama Kegiatan'), 0, 0);
$pdf->Cell(4, 6, ':', 0, 0);
$pdf->Cell(0, 6, pdfText($booking['nama']), 0, 1);

$pdf->Cell(34, 6, pdfText('Peminjam / Bidang'), 0, 0);
$pdf->Cell(4, 6, ':', 0, 0);
$pdf->Cell(0, 6, pdfText($booking['peminjam'] ?: '-'), 0, 1);

$pdf->Cell(34, 6, pdfText('Tanggal Kegiatan'), 0, 0);
$pdf->Cell(4, 6, ':', 0, 0);
if ($booking['start_date'] === $booking['end_date']) {
    $pdf->Cell(0, 6, pdfText(fmtDateIndo($booking['start_date'])), 0, 1);
} else {
    $pdf->Cell(0, 6, pdfText(fmtDateIndo($booking['start_date']) . ' s.d. ' . fmtDateIndo($booking['end_date'])), 0, 1);
}

$pdf->Cell(34, 6, pdfText('Waktu'), 0, 0);
$pdf->Cell(4, 6, ':', 0, 0);
$pdf->Cell(0, 6, pdfText(fmtTimeOnly($booking['jam_start']) . ' - ' . fmtTimeOnly($booking['jam_end']) . ' WIB'), 0, 1);

$pdf->Cell(34, 6, pdfText('Lokasi'), 0, 0);
$pdf->Cell(4, 6, ':', 0, 0);
$pdf->Cell(0, 6, pdfText($lokasi), 0, 1);

$pdf->Cell(34, 6, pdfText('Sesi Absensi'), 0, 0);
$pdf->Cell(4, 6, ':', 0, 0);
$pdf->Cell(0, 6, pdfText($sesiLabel), 0, 1);

$pdf->Cell(34, 6, pdfText('Total Hadir'), 0, 0);
$pdf->Cell(4, 6, ':', 0, 0);
$pdf->Cell(0, 6, pdfText(count($rows) . ' peserta'), 0, 1);

$pdf->Ln(4);

/* =========================
   TABEL
========================= */
$wNo    = 10;
$wSesi  = 24;
$wNama  = 47;
$wUnit  = 35;
$wInst  = 35;
$wWaktu = 20;
$wTtd   = 27;

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(220, 220, 220);

$pdf->Cell($wNo,    7, pdfText('No'), 1, 0, 'C', true);
$pdf->Cell($wSesi,  7, pdfText($isMultiDay ? 'Day' : 'Tanggal'), 1, 0, 'C', true);
$pdf->Cell($wNama,  7, pdfText('Nama'), 1, 0, 'C', true);
$pdf->Cell($wUnit,  7, pdfText('Unit/Jabatan'), 1, 0, 'C', true);
$pdf->Cell($wInst,  7, pdfText('Instansi'), 1, 0, 'C', true);
$pdf->Cell($wWaktu, 7, pdfText('Jam Hadir'), 1, 0, 'C', true);
$pdf->Cell($wTtd,   7, pdfText('TTD'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 7.2);

$tmpFiles = [];
$no = 1;

if (!$rows) {
    $pdf->Cell($wNo + $wSesi + $wNama + $wUnit + $wInst + $wWaktu + $wTtd, 10, pdfText('Belum ada data absensi untuk sesi ini'), 1, 1, 'C');
} else {
    foreach ($rows as $r) {
        $rowH = 13;

        if ($pdf->GetY() + $rowH > 280) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell($wNo,    7, pdfText('No'), 1, 0, 'C', true);
            $pdf->Cell($wSesi,  7, pdfText($isMultiDay ? 'Day' : 'Tanggal'), 1, 0, 'C', true);
            $pdf->Cell($wNama,  7, pdfText('Nama'), 1, 0, 'C', true);
            $pdf->Cell($wUnit,  7, pdfText('Unit/Jabatan'), 1, 0, 'C', true);
            $pdf->Cell($wInst,  7, pdfText('Instansi'), 1, 0, 'C', true);
            $pdf->Cell($wWaktu, 7, pdfText('Jam Hadir'), 1, 0, 'C', true);
            $pdf->Cell($wTtd,   7, pdfText('TTD'), 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 7.2);
        }

        $rowLabel = $isMultiDay
            ? ('Day ' . (int)($r['day_ke'] ?: $selectedDay))
            : fmtDateIndo($r['tanggal_hadir'] ?? $selectedDate);

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->Cell($wNo,    $rowH, pdfText($no++), 1, 0, 'C');
        $pdf->Cell($wSesi,  $rowH, pdfText($rowLabel), 1, 0, 'C');
        $pdf->Cell($wNama,  $rowH, pdfText($r['nama_peserta']), 1, 0);
        $pdf->Cell($wUnit,  $rowH, pdfText($r['unit_jabatan'] ?: '-'), 1, 0);
        $pdf->Cell($wInst,  $rowH, pdfText($r['instansi'] ?: '-'), 1, 0);
        $pdf->Cell($wWaktu, $rowH, pdfText(fmtTimeOnly($r['waktu_hadir'] ?? '')), 1, 0, 'C');
        $pdf->Cell($wTtd,   $rowH, '', 1, 1);

        if (!empty($r['tanda_tangan']) && strpos($r['tanda_tangan'], 'data:image/png;base64,') === 0) {
            $base64 = substr($r['tanda_tangan'], strlen('data:image/png;base64,'));
            $imgData = base64_decode($base64);

            if ($imgData !== false) {
                $tmp = sys_get_temp_dir() . '/ttd_absensi_' . uniqid() . '.png';
                file_put_contents($tmp, $imgData);
                $tmpFiles[] = $tmp;

                $imgX = $x + $wNo + $wSesi + $wNama + $wUnit + $wInst + $wWaktu + 2;
                $imgY = $y + 1.5;
                $imgW = $wTtd - 4;
                $imgH = $rowH - 3;

                $pdf->Image($tmp, $imgX, $imgY, $imgW, $imgH, 'PNG');
            }
        }
    }
}

foreach ($tmpFiles as $f) {
    if (is_file($f)) @unlink($f);
}

$fileName = cleanFileName(
    'Absensi_Rapat_' . $booking['nama'] . '_' . str_replace(' ', '_', $sesiLabel)
) . '.pdf';

$pdf->Output('D', $fileName);
exit;
