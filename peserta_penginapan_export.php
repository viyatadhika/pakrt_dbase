<?php
session_start();
require 'config.php';
require 'fpdf/fpdf.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Jakarta');

/* ── Filter param ───────────────────────────────── */
$from      = isset($_GET['from'])      ? $_GET['from']            : '';
$to        = isset($_GET['to'])        ? $_GET['to']              : '';
$agenda_id = isset($_GET['agenda_id']) ? (int)$_GET['agenda_id']  : 0;
$gedung    = isset($_GET['gedung'])    ? trim($_GET['gedung'])     : '';
$status    = isset($_GET['status'])    ? trim($_GET['status'])     : '';

if (!$from || !$to) {
    $to   = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-30 days'));
}
if ($from > $to) die('Rentang tanggal tidak valid.');

/* ── Query ──────────────────────────────────────── */
$where  = array("(DATE(COALESCE(p.updated_at,p.created_at)) BETWEEN ? AND ?)");
$params = array($from, $to);
$types  = 'ss';

if ($agenda_id > 0) {
    $where[]  = 'p.agenda_id = ?';
    $params[] = $agenda_id;
    $types   .= 'i';
}
if ($gedung !== '') {
    $where[]  = 'p.gedung = ?';
    $params[] = $gedung;
    $types   .= 's';
}
if ($status !== '') {
    $where[]  = 'p.status_inap = ?';
    $params[] = $status;
    $types   .= 's';
}

$sql = "SELECT p.nama,p.instansi,p.peran,p.gedung,p.lantai,p.kamar,
               p.checkin_date,p.checkin_time,p.checkout_date,p.checkout_time,
               p.status_inap,
               COALESCE(a.judul,'Tanpa Kegiatan') AS judul
        FROM peserta_penginapan p
        LEFT JOIN agenda_kegiatan a ON a.id = p.agenda_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.judul, p.gedung,
                 CAST(COALESCE(NULLIF(p.lantai,''),'0') AS UNSIGNED),
                 CAST(COALESCE(NULLIF(p.kamar,''),'0')  AS UNSIGNED),
                 p.nama";

$stmt = $conn->prepare($sql);
if (!$stmt) die('Prepare gagal: ' . $conn->error);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* ── Rekap ──────────────────────────────────────── */
$rows   = array();
$rekap  = array();
$gTotal = 0;
$gBelum = 0;
$gCI    = 0;
$gCO    = 0;

while ($r = $result->fetch_assoc()) {
    $keg = trim($r['judul']) ? trim($r['judul']) : 'Tanpa Kegiatan';
    $st  = trim($r['status_inap'] ? $r['status_inap'] : 'Belum Check-in');

    if (!isset($rekap[$keg])) {
        $rekap[$keg] = array('total' => 0, 'belum' => 0, 'ci' => 0, 'co' => 0);
    }
    $rekap[$keg]['total']++;
    $gTotal++;
    if ($st === 'Check-in') {
        $rekap[$keg]['ci']++;
        $gCI++;
    } elseif ($st === 'Check-out') {
        $rekap[$keg]['co']++;
        $gCO++;
    } else {
        $rekap[$keg]['belum']++;
        $gBelum++;
    }

    $rows[] = $r;
}
$stmt->close();

/* ── Helper functions ───────────────────────────── */
function safe($v)
{
    $s = trim((string)(isset($v) ? $v : '-'));
    return $s !== '' ? $s : '-';
}

function dt($d)
{
    if (!$d || $d === '0000-00-00') return '-';
    $t = strtotime((string)$d);
    return $t ? date('d/m/Y', $t) : '-';
}

function tm($t)
{
    return (!$t || $t === '00:00:00') ? '-' : substr((string)$t, 0, 5);
}

function stLabel($s)
{
    $s = trim((string)$s);
    if ($s === 'Check-in')  return 'CHECK-IN';
    if ($s === 'Check-out') return 'CHECK-OUT';
    return 'BELUM CI';
}

/* ── PDF class ──────────────────────────────────── */
class PDF extends FPDF
{
    /* Lebar kolom detail & total lebar — public agar terbaca global scope */
    public $wD  = array(8, 50, 20, 46, 20, 10, 14, 30, 22, 17, 22, 18); /* total = 277 */
    public $twD = 277;

    /* Posisikan X agar tabel terpusat */
    public function tableX($w)
    {
        $x = $this->lMargin + ($this->w - $this->lMargin - $this->rMargin - $w) / 2;
        $this->SetX(max($x, $this->lMargin));
    }

    /* Header setiap halaman */
    public function Header()
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 7, 'LAPORAN PESERTA PENGINAPAN', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(
            0,
            5,
            'Periode: ' . date('d/m/Y', strtotime($GLOBALS['from']))
                . ' s/d ' . date('d/m/Y', strtotime($GLOBALS['to'])),
            0,
            1,
            'C'
        );
        if ($GLOBALS['gedung']) {
            $this->Cell(0, 5, 'Filter Gedung: ' . $GLOBALS['gedung'], 0, 1, 'C');
        }
        if ($GLOBALS['status']) {
            $this->Cell(0, 5, 'Filter Status: ' . $GLOBALS['status'], 0, 1, 'C');
        }
        $this->Ln(2);
    }

    /* Footer setiap halaman */
    public function Footer()
    {
        $this->SetY(-11);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    /* Hitung jumlah baris yang diperlukan MultiCell */
    public function nbLines($w, $txt)
    {
        $cw = $this->CurrentFont['cw'];
        if (!$w) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s    = str_replace("\r", '', (string)$txt);
        $n    = strlen($s);
        if ($n && $s[$n - 1] === "\n") $n--;
        $sep = -1;
        $i   = 0;
        $j   = 0;
        $l   = 0;
        $nl  = 1;
        while ($i < $n) {
            $c = $s[$i];
            if ($c === "\n") {
                $i++;
                $sep = -1;
                $j   = $i;
                $l   = 0;
                $nl++;
                continue;
            }
            if ($c === ' ') $sep = $i;
            $l += isset($cw[$c]) ? $cw[$c] : 0;
            if ($l > $wmax) {
                if ($sep < 0) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j   = $i;
                $l   = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    /* Gambar satu baris tabel dengan auto-wrap dan auto page-break.
       $this->PageBreakTrigger & $this->CurOrientation valid di sini
       karena berada di dalam method subclass FPDF.                   */
    public function row($data, $widths, $aligns, $reprintFn = null)
    {
        $tw = array_sum($widths);
        $nb = 0;
        foreach ($data as $k => $v) {
            $nb = max($nb, $this->nbLines($widths[$k], (string)$v));
        }
        $h = 5.5 * $nb;

        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
            if (is_callable($reprintFn)) call_user_func($reprintFn);
        }

        $this->tableX($tw);
        foreach ($data as $k => $v) {
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $widths[$k], $h);
            $this->MultiCell($widths[$k], 5.5, (string)$v, 0, isset($aligns[$k]) ? $aligns[$k] : 'L');
            $this->SetXY($x + $widths[$k], $y);
        }
        $this->Ln($h);
    }

    /* Public getter — agar global scope tidak perlu menyentuh protected property */
    public function getBreakTrigger()
    {
        return $this->PageBreakTrigger;
    }

    /* Header kolom tabel detail — dijadikan method agar callable
       sebagai array($pdf, 'hdrDetail') tanpa scope issue.         */
    public function hdrDetail()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(245, 247, 250);
        $this->tableX($this->twD);
        $labels = array(
            'No',
            'Nama',
            'Peran',
            'Instansi',
            'Gedung',
            'Lt',
            'Kamar',
            'Status',
            'CI Date',
            'CI Time',
            'CO Date',
            'CO Time'
        );
        foreach ($labels as $k => $label) {
            $this->Cell($this->wD[$k], 7, $label, 1, 0, 'C', true);
        }
        $this->Ln(7);
        $this->SetFont('Arial', '', 8);
    }
}

/* ── Setup PDF ──────────────────────────────────── */
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 8, 10);   /* margin 10 mm kiri-kanan → area 277 mm */
$pdf->SetAutoPageBreak(true, 12);

/* ================================================================
   HALAMAN 1 — REKAP
   Total lebar = 277 mm
   Kegiatan=130, Total=32, Belum CI=38, CI=32, CO=45
================================================================ */
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'REKAP PER KEGIATAN', 0, 1, 'L');
$pdf->Ln(2);

$wR  = array(130, 32, 38, 32, 45);   /* total = 277 */
$aR  = array('L', 'C', 'C', 'C', 'C');
$twR = 277;

/* Header rekap */
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(245, 247, 250);
$pdf->tableX($twR);
$pdf->Cell($wR[0], 8, 'Kegiatan',       1, 0, 'C', true);
$pdf->Cell($wR[1], 8, 'Total',          1, 0, 'C', true);
$pdf->Cell($wR[2], 8, 'Belum Check-In', 1, 0, 'C', true);
$pdf->Cell($wR[3], 8, 'Check-In',       1, 0, 'C', true);
$pdf->Cell($wR[4], 8, 'Check-Out',      1, 1, 'C', true);

/* Baris rekap */
$pdf->SetFont('Arial', '', 9);
foreach ($rekap as $keg => $r) {
    $pdf->row(
        array($keg, (string)$r['total'], (string)$r['belum'], (string)$r['ci'], (string)$r['co']),
        $wR,
        $aR
    );
}

/* Total rekap */
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(218, 237, 255);
$pdf->tableX($twR);
$pdf->Cell($wR[0], 8, 'TOTAL',          1, 0, 'L', true);
$pdf->Cell($wR[1], 8, (string)$gTotal,  1, 0, 'C', true);
$pdf->Cell($wR[2], 8, (string)$gBelum,  1, 0, 'C', true);
$pdf->Cell($wR[3], 8, (string)$gCI,     1, 0, 'C', true);
$pdf->Cell($wR[4], 8, (string)$gCO,     1, 1, 'C', true);

/* ================================================================
   HALAMAN 2+ — DETAIL
   Total lebar = 277 mm
   No=8, Nama=50, Peran=20, Instansi=46, Gedung=20, Lt=10, Kamar=14,
   Status=30, CI Date=22, CI Time=17, CO Date=22, CO Time=18  → 277
================================================================ */
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'DETAIL PESERTA PER KEGIATAN', 0, 1, 'L');
$pdf->Ln(2);

/* Ambil wD & twD dari property kelas */
$wD  = $pdf->wD;
$aD  = array('C', 'L', 'C', 'L', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C');
$twD = $pdf->twD;

/* Callable ke method hdrDetail — tidak menyentuh protected property dari luar */
$hdrDetail = array($pdf, 'hdrDetail');

$pdf->hdrDetail();

if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Tidak ada data pada rentang tanggal tersebut.', 0, 1, 'C');
} else {
    $prevKeg = null;
    $no      = 1;

    foreach ($rows as $row) {
        $keg = trim($row['judul']) ? trim($row['judul']) : 'Tanpa Kegiatan';

        /* Sub-header per kegiatan */
        if ($prevKeg !== $keg) {
            if ($pdf->GetY() + 18 > $pdf->getBreakTrigger()) {
                $pdf->AddPage();
                $pdf->hdrDetail();
            }
            $m     = isset($rekap[$keg]) ? $rekap[$keg] : array('total' => 0, 'belum' => 0, 'ci' => 0, 'co' => 0);
            $label = "Kegiatan: $keg  |  Total: {$m['total']}"
                . "  |  Belum CI: {$m['belum']}  |  CI: {$m['ci']}  |  CO: {$m['co']}";
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->SetFillColor(218, 237, 255);
            $pdf->tableX($twD);
            $pdf->Cell($twD, 7, $label, 1, 1, 'L', true);
            $pdf->SetFont('Arial', '', 8);
            $pdf->hdrDetail();
            $prevKeg = $keg;
            $no      = 1;
        }

        $pdf->row(
            array(
                (string)$no++,
                safe($row['nama']),
                safe($row['peran']),
                safe($row['instansi']),
                safe($row['gedung']),
                safe($row['lantai']),
                safe($row['kamar']),
                stLabel($row['status_inap']),
                dt($row['checkin_date']),
                tm($row['checkin_time']),
                dt($row['checkout_date']),
                tm($row['checkout_time']),
            ),
            $wD,
            $aD,
            $hdrDetail
        );
    }
}

/* ── Output ─────────────────────────────────────── */
$pdf->Output('D', 'laporan_peserta_penginapan_' . $from . '_sd_' . $to . '.pdf');
exit;
