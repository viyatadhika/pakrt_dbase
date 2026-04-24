<?php
session_start();
ob_start();

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

    return (int)$start->diff($curr)->days + 1;
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
   PDF CLASS
========================= */
class PDF extends FPDF
{
    public $reportTitle = 'LAPORAN ABSENSI RAPAT';
    public $reportSubTitle = '';

    function Error($msg)
    {
        throw new Exception($msg);
    }

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

    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];

        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);

        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }

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

            if ($c == ' ') {
                $sep = $i;
            }

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
}

/* =========================
   PARAMETER
========================= */
$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tanggal   = trim((string)($_GET['tanggal'] ?? ''));

if ($bookingId <= 0) {
    die('ID booking tidak valid.');
}

/* =========================
   BOOKING
========================= */
$stmt = $conn->prepare("
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
        COALESCE(r.nama_ruang, '') AS ruang,
        COALESCE(r.lokasi, '') AS lokasi_ruang
    FROM booking_ruang_rapat b
    LEFT JOIN ruang_rapat r ON r.id = b.room_id
    WHERE b.id = ?
    LIMIT 1
");

if (!$stmt) die('Query booking gagal: ' . $conn->error);

$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    die('Booking tidak ditemukan.');
}

/* =========================
   SESI
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
    SELECT 
        id,
        booking_id,
        tanggal_hadir,
        day_ke,
        nama_peserta,
        unit_jabatan,
        instansi,
        tanda_tangan,
        waktu_hadir
    FROM absensi_rapat
    WHERE booking_id = ? AND tanggal_hadir = ?
    ORDER BY id ASC
");

if (!$stmt) die('Query absensi gagal: ' . $conn->error);

$stmt->bind_param('is', $bookingId, $selectedDate);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

/* =========================
   GENERATE PDF
========================= */
try {
    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->reportTitle = pdfText('LAPORAN ABSENSI RAPAT');
    $pdf->reportSubTitle = pdfText($sesiLabel);
    $pdf->SetMargins(6, 10, 6);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->AddPage();

    /* INFO RAPAT */
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

    /* UKURAN KOLOM TOTAL 198mm */
    $wNo    = 10;
    $wSesi  = 24;
    $wNama  = 47;
    $wUnit  = 35;
    $wInst  = 35;
    $wWaktu = 20;
    $wTtd   = 27;

    $drawHeader = function () use ($pdf, $wNo, $wSesi, $wNama, $wUnit, $wInst, $wWaktu, $wTtd, $isMultiDay) {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(220, 220, 220);

        $pdf->Cell($wNo,    7, pdfText('No'), 1, 0, 'C', true);
        $pdf->Cell($wSesi,  7, pdfText($isMultiDay ? 'Day' : 'Tanggal'), 1, 0, 'C', true);
        $pdf->Cell($wNama,  7, pdfText('Nama'), 1, 0, 'C', true);
        $pdf->Cell($wUnit,  7, pdfText('Unit/Jabatan'), 1, 0, 'C', true);
        $pdf->Cell($wInst,  7, pdfText('Instansi'), 1, 0, 'C', true);
        $pdf->Cell($wWaktu, 7, pdfText('Jam Hadir'), 1, 0, 'C', true);
        $pdf->Cell($wTtd,   7, pdfText('TTD'), 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 6.9);
    };

    $drawHeader();

    $tmpFiles = [];
    $no = 1;

    if (!$rows) {
        $pdf->Cell(
            $wNo + $wSesi + $wNama + $wUnit + $wInst + $wWaktu + $wTtd,
            10,
            pdfText('Belum ada data absensi untuk sesi ini'),
            1,
            1,
            'C'
        );
    } else {
        foreach ($rows as $r) {
            $nama = pdfText($r['nama_peserta'] ?: '-');
            $unit = pdfText($r['unit_jabatan'] ?: '-');
            $inst = pdfText($r['instansi'] ?: '-');

            $lineH = 4.3;

            $nb = max(
                $pdf->NbLines($wNama - 2.4, $nama),
                $pdf->NbLines($wUnit - 2.4, $unit),
                $pdf->NbLines($wInst - 2.4, $inst)
            );

            $rowH = max(13, ($nb * $lineH) + 4);

            if ($pdf->GetY() + $rowH > 280) {
                $pdf->AddPage();
                $drawHeader();
            }

            $rowLabel = $isMultiDay
                ? ('Day ' . (int)($r['day_ke'] ?: $selectedDay))
                : fmtDateIndo($r['tanggal_hadir'] ?? $selectedDate);

            $x = $pdf->GetX();
            $y = $pdf->GetY();

            $xNo    = $x;
            $xSesi  = $xNo + $wNo;
            $xNama  = $xSesi + $wSesi;
            $xUnit  = $xNama + $wNama;
            $xInst  = $xUnit + $wUnit;
            $xWaktu = $xInst + $wInst;
            $xTtd   = $xWaktu + $wWaktu;

            /* BORDER FULL ROW */
            $pdf->Rect($xNo,    $y, $wNo,    $rowH);
            $pdf->Rect($xSesi,  $y, $wSesi,  $rowH);
            $pdf->Rect($xNama,  $y, $wNama,  $rowH);
            $pdf->Rect($xUnit,  $y, $wUnit,  $rowH);
            $pdf->Rect($xInst,  $y, $wInst,  $rowH);
            $pdf->Rect($xWaktu, $y, $wWaktu, $rowH);
            $pdf->Rect($xTtd,   $y, $wTtd,   $rowH);

            /* KOLOM KECIL CENTER */
            $pdf->SetXY($xNo, $y + (($rowH - 4) / 2));
            $pdf->Cell($wNo, 4, pdfText($no++), 0, 0, 'C');

            $pdf->SetXY($xSesi, $y + (($rowH - 4) / 2));
            $pdf->Cell($wSesi, 4, pdfText($rowLabel), 0, 0, 'C');

            $pdf->SetXY($xWaktu, $y + (($rowH - 4) / 2));
            $pdf->Cell($wWaktu, 4, pdfText(fmtTimeOnly($r['waktu_hadir'] ?? '')), 0, 0, 'C');

            /* KOLOM PANJANG WRAP TANPA BORDER */
            $pdf->SetXY($xNama + 1.2, $y + 2);
            $pdf->MultiCell($wNama - 2.4, $lineH, $nama, 0, 'L');

            $pdf->SetXY($xUnit + 1.2, $y + 2);
            $pdf->MultiCell($wUnit - 2.4, $lineH, $unit, 0, 'L');

            $pdf->SetXY($xInst + 1.2, $y + 2);
            $pdf->MultiCell($wInst - 2.4, $lineH, $inst, 0, 'L');

            /* TANDA TANGAN AMAN */
            if (!empty($r['tanda_tangan']) && strpos($r['tanda_tangan'], 'data:image/png;base64,') === 0) {
                $base64 = substr($r['tanda_tangan'], strlen('data:image/png;base64,'));
                $imgData = base64_decode($base64, true);

                if ($imgData !== false && strlen($imgData) > 100) {
                    $tmp = sys_get_temp_dir() . '/ttd_absensi_' . uniqid('', true) . '.png';

                    if (@file_put_contents($tmp, $imgData) !== false) {
                        $tmpFiles[] = $tmp;

                        if (is_file($tmp) && @getimagesize($tmp)) {
                            try {
                                $imgX = $xTtd + 2;
                                $imgY = $y + 2;
                                $imgW = $wTtd - 4;
                                $imgH = max(8, $rowH - 4);

                                $pdf->Image($tmp, $imgX, $imgY, $imgW, $imgH, 'PNG');
                            } catch (Exception $e) {
                                // Lewati tanda tangan rusak supaya PDF tetap jadi
                            }
                        }
                    }
                }
            }

            $pdf->SetXY($x, $y + $rowH);
        }
    }

    foreach ($tmpFiles as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }

    $fileName = cleanFileName(
        'Absensi_Rapat_' . $booking['nama'] . '_' . str_replace(' ', '_', $sesiLabel)
    ) . '.pdf';

    if (ob_get_length()) {
        ob_end_clean();
    }

    $pdf->Output('D', $fileName);
    exit;
} catch (Exception $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }

    http_response_code(500);
    echo 'Gagal membuat PDF: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
