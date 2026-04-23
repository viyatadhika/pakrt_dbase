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
    $bulan = [
        '',
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agt',
        'Sep',
        'Okt',
        'Nov',
        'Des'
    ];
    $p = explode('-', substr($d, 0, 10));
    if (count($p) < 3) return $d;
    return $p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}

function fmtTimeOnly($t)
{
    if (!$t) return '-';
    $t = (string)$t;
    if (strlen($t) >= 16 && strpos($t, ' ') !== false) {
        return substr($t, 11, 5);
    }
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
    return $name !== '' ? $name : 'Notulen_Rapat';
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

function safeValue($v)
{
    $v = trim((string)$v);
    return $v !== '' ? $v : '-';
}

function formatSesiLabelPdf($startDate, $endDate, $selectedDate, $selectedDay)
{
    if ($startDate === $endDate) {
        return fmtDateIndo($selectedDate);
    }
    return 'Day ' . $selectedDay . ' - ' . fmtDateIndo($selectedDate);
}

function drawMultiLineField(FPDF $pdf, $label, $value, $labelWidth = 32, $colonWidth = 4, $lineH = 5)
{
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell($labelWidth, $lineH, pdfText($label), 0, 0);
    $pdf->Cell($colonWidth, $lineH, ':', 0, 0);

    $valueX = $x + $labelWidth + $colonWidth;
    $pageWidth = $pdf->GetPageWidth();
    $rightMargin = 6;
    $valueW = $pageWidth - $valueX - $rightMargin;

    $pdf->SetXY($valueX, $y);
    $pdf->MultiCell($valueW, $lineH, pdfText($value), 0, 'L');

    if ($pdf->GetY() < $y + $lineH) {
        $pdf->SetY($y + $lineH);
    }
}

class PDF extends FPDF
{
    public $reportTitle = 'NOTULEN RAPAT';
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

        $this->Ln(1.5);
    }

    function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 6, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}

function addSectionTitle(FPDF $pdf, $title)
{
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(230, 241, 251);
    $pdf->SetTextColor(24, 95, 165);
    $pdf->Cell(0, 7, pdfText($title), 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(1.5);
}

function addSectionBody(FPDF $pdf, $text)
{
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, pdfText(safeValue($text)), 0, 'L');
    $pdf->Ln(2.5);
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
if (!$stmt) {
    die('Query booking gagal: ' . $conn->error);
}

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

$sesiLabel = formatSesiLabelPdf($booking['start_date'], $booking['end_date'], $selectedDate, $selectedDay);

$lokasi = $booking['jenis_lokasi'] === 'external'
    ? ($booking['lokasi_external'] ?: '-')
    : trim(($booking['ruang'] ?: '-') . (!empty($booking['lokasi_ruang']) ? ' - ' . $booking['lokasi_ruang'] : ''));

/* =========================
   AMBIL NOTULEN PER DAY
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

$stmt = $conn->prepare("
    SELECT agenda, pimpinan_rapat, moderator, notulis,
           peserta_text, pembahasan, keputusan, tindak_lanjut
    FROM notulen_rapat
    WHERE booking_id = ? AND tanggal_notulen = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param('is', $bookingId, $selectedDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $notulen = $row;
    $stmt->close();
}

/* =========================
   AMBIL FOTO PER DAY
========================= */
$dokumentasi = [];
$stmt = $conn->prepare("
    SELECT id, file_path, created_at
    FROM notulen_dokumentasi
    WHERE booking_id = ? AND tanggal_notulen = ?
    ORDER BY id ASC
");
if ($stmt) {
    $stmt->bind_param('is', $bookingId, $selectedDate);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dokumentasi[] = $row;
    }
    $stmt->close();
}

/* =========================
   PDF
========================= */
$pdf = new PDF('P', 'mm', 'A4');
$pdf->reportTitle = pdfText('NOTULEN RAPAT');
$pdf->reportSubTitle = pdfText($sesiLabel);
$pdf->SetMargins(6, 10, 6);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

drawMultiLineField($pdf, 'Nama Kegiatan', safeValue($booking['nama']), 32, 4, 5);
drawMultiLineField($pdf, 'Peminjam / Bidang', safeValue($booking['peminjam']), 32, 4, 5);

if ($booking['start_date'] === $booking['end_date']) {
    drawMultiLineField($pdf, 'Tanggal Kegiatan', fmtDateIndo($booking['start_date']), 32, 4, 5);
} else {
    drawMultiLineField($pdf, 'Tanggal Kegiatan', fmtDateIndo($booking['start_date']) . ' s.d. ' . fmtDateIndo($booking['end_date']), 32, 4, 5);
}

drawMultiLineField($pdf, 'Waktu', fmtTimeOnly($booking['jam_start']) . ' - ' . fmtTimeOnly($booking['jam_end']) . ' WIB', 32, 4, 5);
drawMultiLineField($pdf, 'Lokasi', safeValue($lokasi), 32, 4, 5);
drawMultiLineField($pdf, 'Sesi Notulen', $sesiLabel, 32, 4, 5);

$pdf->Ln(2);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(6, $pdf->GetY(), 204, $pdf->GetY());
$pdf->Ln(4);

addSectionTitle($pdf, 'IDENTITAS NOTULEN');
drawMultiLineField($pdf, 'Agenda', safeValue($notulen['agenda']), 32, 4, 5);
drawMultiLineField($pdf, 'Pimpinan Rapat', safeValue($notulen['pimpinan_rapat']), 32, 4, 5);
drawMultiLineField($pdf, 'Moderator', safeValue($notulen['moderator']), 32, 4, 5);
drawMultiLineField($pdf, 'Notulis', safeValue($notulen['notulis']), 32, 4, 5);
$pdf->Ln(2);

addSectionTitle($pdf, 'PESERTA');
addSectionBody($pdf, $notulen['peserta_text']);

addSectionTitle($pdf, 'PEMBAHASAN');
addSectionBody($pdf, $notulen['pembahasan']);

addSectionTitle($pdf, 'KEPUTUSAN');
addSectionBody($pdf, $notulen['keputusan']);

addSectionTitle($pdf, 'TINDAK LANJUT');
addSectionBody($pdf, $notulen['tindak_lanjut']);

addSectionTitle($pdf, 'DOKUMENTASI');

if (empty($dokumentasi)) {
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, pdfText('Belum ada dokumentasi untuk sesi ini.'), 0, 'L');
} else {
    $imgW = 63;
    $imgH = 46;
    $gapX = 3;
    $xStart = 6;
    $x = $xStart;
    $y = $pdf->GetY();
    $perRow = 3;
    $index = 0;

    foreach ($dokumentasi as $img) {
        $path = __DIR__ . '/' . ltrim($img['file_path'], '/');
        if (!is_file($path)) {
            continue;
        }

        if (($index % $perRow) === 0 && $index > 0) {
            $x = $xStart;
            $y += $imgH + 10;
        }

        if ($y + $imgH + 10 > 280) {
            $pdf->AddPage();
            addSectionTitle($pdf, 'DOKUMENTASI (LANJUTAN)');
            $x = $xStart;
            $y = $pdf->GetY();
        }

        $pdf->Image($path, $x, $y, $imgW, $imgH);

        $pdf->SetXY($x, $y + $imgH + 1.5);
        $pdf->SetFont('Arial', '', 8);
        $caption = 'Dokumentasi #' . (int)$img['id'];
        $pdf->Cell($imgW, 4, pdfText($caption), 0, 0, 'C');

        $x += $imgW + $gapX;
        $index++;
    }

    $pdf->SetY($y + $imgH + 8);
}

$fileName = cleanFileName(
    'Notulen_Rapat_' . $booking['nama'] . '_' . str_replace(' ', '_', $sesiLabel)
) . '.pdf';

$pdf->Output('D', $fileName);
exit;
