<?php
session_start();
if (!isset($_SESSION['user'])) exit;

include 'config.php';
require_once('fpdf/fpdf.php');

/* =========================
   PARAM RENTANG TANGGAL
========================= */
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-01-01');
$to   = isset($_GET['to'])   ? $_GET['to']   : date('Y-12-31');

/* =========================
   AMBIL DATA
========================= */
$sql = "
SELECT *
FROM agenda_kegiatan
WHERE start_date <= '$to'
  AND end_date   >= '$from'
ORDER BY start_date ASC
";
$q    = $conn->query($sql);
$data = array();
while ($r = $q->fetch_assoc()) $data[] = $r;

/* =========================
   HELPER BULAN
========================= */
function getMonths(string $from, string $to)
{
    $months = array();
    $start  = new DateTime(date('Y-m-01', strtotime($from)));
    $end    = new DateTime(date('Y-m-01', strtotime($to)));
    $end->modify('+1 month');
    foreach (new DatePeriod($start, new DateInterval('P1M'), $end) as $dt)
        $months[] = $dt;
    return $months;
}

$namaBulanArr = array(
    1  => 'JANUARI',
    2  => 'FEBRUARI',
    3  => 'MARET',
    4  => 'APRIL',
    5  => 'MEI',
    6  => 'JUNI',
    7  => 'JULI',
    8  => 'AGUSTUS',
    9  => 'SEPTEMBER',
    10 => 'OKTOBER',
    11 => 'NOVEMBER',
    12 => 'DESEMBER',
);

/* =========================
   LEBAR KOLOM (tetap)
========================= */
$wNo   = 6;
$wNama = 55;
$wPeny = 28;
$wPes  = 16;
$wAs   = 24;
$wKl   = 22;
$wMk   = 26;

/* Legal Landscape = 355.6 mm lebar, margin 6+6=12 */
$pageW  = 355 - 12;
$fixedW = $wNo + $wNama + $wPeny + $wPes + $wAs + $wKl + $wMk;

/* warna per kategori */
$katColor = array(
    'Menpim'    => array(255, 193,   7),
    'Teknis'    => array(40, 167,  69),
    'Kerjasama' => array(0, 123, 255),
);

/* =========================
   PDF CLASS
========================= */
class PDF extends FPDF
{
    var $dates      = array();
    var $wDay       = 0;
    var $wNo        = 6;
    var $wNama      = 55;
    var $wPeny      = 28;
    var $wPes       = 16;
    var $wAs        = 24;
    var $wKl        = 22;
    var $wMk        = 26;
    var $namaBulan  = '';
    var $pageW      = 343;
    var $rekapMode  = false;

    /* ── Header dokumen ── */
    function Header()
    {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, 'MAHKAMAH AGUNG REPUBLIK INDONESIA', 0, 1, 'C');
        $this->Cell(0, 6, 'BADAN STRAJAK DIKLAT KUMDIL', 0, 1, 'C');
        $this->Ln(1);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, 'REKAPITULASI KEGIATAN PELATIHAN', 0, 1, 'C');
        $this->Ln(3);

        if ($this->rekapMode) return;

        if ($this->namaBulan !== '') {
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(0, 5, $this->namaBulan, 0, 1);
            $this->Ln(1);
            $this->drawTableHeader();
        }
    }

    /* ── Footer dokumen ── */
    function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }

    /* ── Gambar baris header tabel ── */
    function drawTableHeader()
    {
        $this->SetFont('Arial', 'B', 7);
        $this->SetFillColor(220, 220, 220);

        $this->Cell($this->wNo,   8, 'No',             1, 0, 'C', true);
        $this->Cell($this->wNama, 8, 'Nama Pelatihan', 1, 0, 'C', true);
        $this->Cell($this->wPeny, 8, 'Penyelenggara',  1, 0, 'C', true);

        foreach ($this->dates as $d)
            $this->Cell($this->wDay, 8, date('d', strtotime($d)), 1, 0, 'C', true);

        $this->Cell($this->wPes, 8, 'Peserta',  1, 0, 'C', true);
        $this->Cell($this->wAs,  8, 'Asrama',   1, 0, 'C', true);
        $this->Cell($this->wKl,  8, 'Kelas',    1, 0, 'C', true);
        $this->Cell($this->wMk,  8, 'R. Makan', 1, 1, 'C', true);

        $this->SetFont('Arial', '', 7);
    }

    /* ── Hitung jumlah baris MultiCell ── */
    function NbLines(float $w, string $txt)
    {
        $cw   = &$this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s    = str_replace("\r", '', (string)$txt);
        $nb   = strlen($s);
        $sep  = -1;
        $i = $j = $l = 0;
        $nl   = 1;

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
            if ($c == ' ')  $sep = $i;
            $l += $cw[$c];
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

    /* ── Cek page break untuk baris data biasa ── */
    function checkPageBreak(float $h)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage();
        }
    }

    /* ── Cek page break untuk blok rekap (FIX: akses PageBreakTrigger dari dalam class) ── */
    function checkPageBreakRekap(float $h)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage();
            return true;
        }
        return false;
    }
}

/* =========================
   INISIALISASI PDF
========================= */
$pdf = new PDF('L', 'mm', 'Legal');
$pdf->wNo   = $wNo;
$pdf->wNama = $wNama;
$pdf->wPeny = $wPeny;
$pdf->wPes  = $wPes;
$pdf->wAs   = $wAs;
$pdf->wKl   = $wKl;
$pdf->wMk   = $wMk;
$pdf->pageW = $pageW;
$pdf->SetMargins(6, 10, 6);
$pdf->SetAutoPageBreak(true, 12);

$months = getMonths($from, $to);

/* =========================
   LOOP PER BULAN
========================= */
foreach ($months as $mDt) {

    $bulanAwal  = $mDt->format('Y-m-01');
    $bulanAkhir = $mDt->format('Y-m-t');
    $bulanNo    = (int)$mDt->format('m');

    $dataMonth = array();
    foreach ($data as $r) {
        if ($r['end_date'] >= $bulanAwal && $r['start_date'] <= $bulanAkhir)
            $dataMonth[] = $r;
    }

    if (empty($dataMonth)) continue;

    /* Buat daftar tanggal bulan ini */
    $dates   = array();
    $dCursor = new DateTime($bulanAwal);
    $dEnd    = new DateTime($bulanAkhir);
    while ($dCursor <= $dEnd) {
        $dates[] = $dCursor->format('Y-m-d');
        $dCursor->modify('+1 day');
    }

    $wDay = ($pageW - $fixedW) / count($dates);

    $pdf->dates     = $dates;
    $pdf->wDay      = $wDay;
    $pdf->namaBulan = $namaBulanArr[$bulanNo] . ' ' . $mDt->format('Y');

    $pdf->AddPage();

    /* ===== BARIS DATA ===== */
    $pdf->SetFont('Arial', '', 7);
    $no = 1;

    foreach ($dataMonth as $r) {

        $lineH = 5;

        $hNama = $pdf->NbLines($wNama, (string)$r['judul'])  * $lineH;
        $hAs   = $pdf->NbLines($wAs,   (string)$r['asrama']) * $lineH;
        $hKl   = $pdf->NbLines($wKl,   (string)$r['kelas'])  * $lineH;
        $hMk   = $pdf->NbLines($wMk,   (string)$r['makan'])  * $lineH;
        $rowH  = max($hNama, $hAs, $hKl, $hMk, 10);

        $pdf->checkPageBreak($rowH);

        $y = $pdf->GetY();
        $x = $pdf->GetX();

        /* Kolom hari: warna + border */
        $xx = $x + $wNo + $wNama + $wPeny;
        foreach ($dates as $d) {
            if ($d >= $r['start_date'] && $d <= $r['end_date']) {
                if (isset($katColor[$r['kategori']])) {
                    $col = $katColor[$r['kategori']];
                } else {
                    $col = array(255, 133, 27);
                }
                $pdf->SetFillColor($col[0], $col[1], $col[2]);
                $pdf->Rect($xx, $y, $wDay, $rowH, 'F');
            }
            $pdf->Rect($xx, $y, $wDay, $rowH);
            $xx += $wDay;
        }

        /* Border kolom tetap */
        $pdf->Rect($x,                 $y, $wNo,   $rowH);
        $pdf->Rect($x + $wNo,          $y, $wNama, $rowH);
        $pdf->Rect($x + $wNo + $wNama, $y, $wPeny, $rowH);

        $xRight = $x + $pageW;
        $pdf->Rect($xRight - $wMk - $wKl - $wAs - $wPes, $y, $wPes, $rowH);
        $pdf->Rect($xRight - $wMk - $wKl - $wAs,         $y, $wAs,  $rowH);
        $pdf->Rect($xRight - $wMk - $wKl,                $y, $wKl,  $rowH);
        $pdf->Rect($xRight - $wMk,                        $y, $wMk,  $rowH);

        /* Teks */
        $pdf->SetXY($x, $y + ($rowH - $lineH) / 2);
        $pdf->Cell($wNo, $lineH, (string)$no++, 0, 0, 'C');

        $pdf->SetXY($x + $wNo, $y + ($rowH - $hNama) / 2);
        $pdf->MultiCell($wNama, $lineH, (string)$r['judul'], 0, 'L');

        $pdf->SetXY($x + $wNo + $wNama, $y + ($rowH - $lineH) / 2);
        $pdf->Cell($wPeny, $lineH, (string)$r['kategori'], 0, 0, 'C');

        $pdf->SetXY($xRight - $wMk - $wKl - $wAs - $wPes, $y + ($rowH - $lineH) / 2);
        $pdf->Cell($wPes, $lineH, (string)(int)$r['peserta'], 0, 0, 'C');

        $pdf->SetXY($xRight - $wMk - $wKl - $wAs, $y + ($rowH - $hAs) / 2);
        $pdf->MultiCell($wAs, $lineH, (string)$r['asrama'], 0, 'C');

        $pdf->SetXY($xRight - $wMk - $wKl, $y + ($rowH - $hKl) / 2);
        $pdf->MultiCell($wKl, $lineH, (string)$r['kelas'], 0, 'C');

        $pdf->SetXY($xRight - $wMk, $y + ($rowH - $hMk) / 2);
        $pdf->MultiCell($wMk, $lineH, (string)$r['makan'], 0, 'C');

        $pdf->SetY($y + $rowH);
    }

    /* =====================================================
       TABEL REKAPITULASI
    ===================================================== */

    $totalPeserta  = 0;
    $totalKegiatan = 0;
    $kelasSet      = array();
    $asramaSet     = array();
    $makanSet      = array();

    $rekapKat = array(
        'Menpim'    => array('kegiatan' => 0, 'peserta' => 0),
        'Teknis'    => array('kegiatan' => 0, 'peserta' => 0),
        'Kerjasama' => array('kegiatan' => 0, 'peserta' => 0),
        'Pustrajak' => array('kegiatan' => 0, 'peserta' => 0),
        'Lainnya'   => array('kegiatan' => 0, 'peserta' => 0),
    );

    foreach ($dataMonth as $r) {
        $peserta = (int)$r['peserta'];
        $totalPeserta  += $peserta;
        $totalKegiatan++;

        $kat = $r['kategori'];
        if (!isset($rekapKat[$kat])) $kat = 'Lainnya';
        $rekapKat[$kat]['kegiatan']++;
        $rekapKat[$kat]['peserta'] += $peserta;

        if (!empty($r['kelas'])) {
            foreach (preg_split('/[,;\n]+/', $r['kelas']) as $kl) {
                $kl = trim($kl);
                if ($kl !== '') $kelasSet[$kl] = true;
            }
        }
        if (!empty($r['asrama'])) {
            foreach (preg_split('/[,;\n]+/', $r['asrama']) as $as) {
                $as = trim($as);
                if ($as !== '') $asramaSet[$as] = true;
            }
        }
        if (!empty($r['makan'])) {
            foreach (preg_split('/[,;\n]+/', $r['makan']) as $mk) {
                $mk = trim($mk);
                if ($mk !== '') $makanSet[$mk] = true;
            }
        }
    }

    /* Hari aktif */
    $hariAktif = array();
    foreach ($dataMonth as $r) {
        $dC = new DateTime(max($r['start_date'], $bulanAwal));
        $dE = new DateTime(min($r['end_date'],   $bulanAkhir));
        while ($dC <= $dE) {
            $hariAktif[$dC->format('Y-m-d')] = true;
            $dC->modify('+1 day');
        }
    }
    $totalHariAktif = count($hariAktif);

    /* Bersihkan set */
    $kelasClean  = array();
    foreach (array_keys($kelasSet)  as $v) {
        $v = trim($v);
        if ($v !== '' && $v !== '-') $kelasClean[$v]  = true;
    }
    $asramaClean = array();
    foreach (array_keys($asramaSet) as $v) {
        $v = trim($v);
        if ($v !== '' && $v !== '-') $asramaClean[$v] = true;
    }
    $makanClean  = array();
    foreach (array_keys($makanSet)  as $v) {
        $v = trim($v);
        if ($v !== '' && $v !== '-') $makanClean[$v]  = true;
    }

    $totalKelas  = count($kelasClean);
    $totalAsrama = count($asramaClean);
    $totalMakan  = count($makanClean);
    $kelasStr    = implode(', ', array_keys($kelasClean));
    $asramaStr   = implode(', ', array_keys($asramaClean));
    $makanStr    = implode(', ', array_keys($makanClean));

    /* Lebar kolom rekap */
    $wR1 = 55;
    $wR2 = 25;
    $wR3 = 25;
    $wR4 = $pageW - $wR1 - $wR2 - $wR3;
    $wF1 = 55;
    $wF2 = $pageW - $wF1;

    $katLabels = array(
        'Menpim'    => 'Kepemimpinan (Menpim)',
        'Teknis'    => 'Teknis Yudisial',
        'Kerjasama' => 'Kerjasama / Seleksi',
        'Pustrajak' => 'Penyusunan Naskah (Pustrajak)',
        'Lainnya'   => 'Lainnya',
    );

    $katAktif = 0;
    foreach ($rekapKat as $kVal) {
        if ($kVal['kegiatan'] > 0) $katAktif++;
    }

    $hKelasRek  = max($pdf->NbLines($wF2, $kelasStr  ?: '-') * 5, 6);
    $hAsramaRek = max($pdf->NbLines($wF2, $asramaStr ?: '-') * 5, 6);
    $hMakanRek  = max($pdf->NbLines($wF2, $makanStr  ?: '-') * 5, 6);

    $totalRekapH = 5 + 7 + 1 + 7 + ($katAktif * 6) + 7
        + 3 + 7 + $hKelasRek + $hAsramaRek + $hMakanRek + 6 + 4;

    /* FIX: gunakan method dalam class, bukan akses langsung ke PageBreakTrigger */
    $pdf->rekapMode = true;

    if (!$pdf->checkPageBreakRekap($totalRekapH)) {
        $pdf->Ln(5);
    }

    $x = $pdf->GetX();

    /* Judul rekapitulasi */
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(50, 100, 50);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($pageW, 7, 'REKAPITULASI BULAN ' . $pdf->namaBulan, 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);

    /* Tabel ringkasan kategori */
    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(200, 220, 200);
    $pdf->Cell($wR1, 7, 'Kategori',      1, 0, 'C', true);
    $pdf->Cell($wR2, 7, 'Jml Kegiatan',  1, 0, 'C', true);
    $pdf->Cell($wR3, 7, 'Jml Peserta',   1, 0, 'C', true);
    $pdf->Cell($wR4, 7, 'Keterangan',    1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 7);
    $fill = false;
    foreach ($rekapKat as $kKey => $kVal) {
        if ($kVal['kegiatan'] == 0) continue;
        $pdf->SetFillColor(240, 248, 240);
        $pdf->Cell($wR1, 6, $katLabels[$kKey],         1, 0, 'L', $fill);
        $pdf->Cell($wR2, 6, (string)$kVal['kegiatan'], 1, 0, 'C', $fill);
        $pdf->Cell($wR3, 6, (string)$kVal['peserta'],  1, 0, 'C', $fill);
        $pdf->Cell($wR4, 6, '',                        1, 1, 'C', $fill);
        $fill = !$fill;
    }
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell($wR1, 7, 'TOTAL',                1, 0, 'C', true);
    $pdf->Cell($wR2, 7, (string)$totalKegiatan, 1, 0, 'C', true);
    $pdf->Cell($wR3, 7, (string)$totalPeserta,  1, 0, 'C', true);
    $pdf->Cell($wR4, 7, '',                     1, 1, 'C', true);

    /* Tabel fasilitas */
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(200, 210, 230);
    $pdf->Cell($wF1, 7, 'Fasilitas', 1, 0, 'C', true);
    $pdf->Cell($wF2, 7, 'Rincian',   1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 7);

    /* Ruang Kelas */
    $yF = $pdf->GetY();
    $pdf->SetFillColor(240, 244, 250);
    $pdf->Rect($x,        $yF, $wF1, $hKelasRek, 'DF');
    $pdf->Rect($x + $wF1, $yF, $wF2, $hKelasRek);
    $pdf->SetXY($x,        $yF + ($hKelasRek - 5) / 2);
    $pdf->Cell($wF1, 5, 'Ruang Kelas (' . $totalKelas . ' kelas)', 0, 0, 'L');
    $pdf->SetXY($x + $wF1, $yF);
    $pdf->MultiCell($wF2, 5, $kelasStr ?: '-', 0, 'L');
    $pdf->SetY($yF + $hKelasRek);

    /* Asrama */
    $yF = $pdf->GetY();
    $pdf->SetFillColor(248, 248, 248);
    $pdf->Rect($x,        $yF, $wF1, $hAsramaRek, 'DF');
    $pdf->Rect($x + $wF1, $yF, $wF2, $hAsramaRek);
    $pdf->SetXY($x,        $yF + ($hAsramaRek - 5) / 2);
    $pdf->Cell($wF1, 5, 'Asrama (' . $totalAsrama . ' blok)', 0, 0, 'L');
    $pdf->SetXY($x + $wF1, $yF);
    $pdf->MultiCell($wF2, 5, $asramaStr ?: '-', 0, 'L');
    $pdf->SetY($yF + $hAsramaRek);

    /* Ruang Makan */
    $yF = $pdf->GetY();
    $pdf->SetFillColor(240, 244, 250);
    $pdf->Rect($x,        $yF, $wF1, $hMakanRek, 'DF');
    $pdf->Rect($x + $wF1, $yF, $wF2, $hMakanRek);
    $pdf->SetXY($x,        $yF + ($hMakanRek - 5) / 2);
    $pdf->Cell($wF1, 5, 'Ruang Makan (' . $totalMakan . ' ruang)', 0, 0, 'L');
    $pdf->SetXY($x + $wF1, $yF);
    $pdf->MultiCell($wF2, 5, $makanStr ?: '-', 0, 'L');
    $pdf->SetY($yF + $hMakanRek);

    /* Hari Aktif */
    $yF = $pdf->GetY();
    $pdf->SetFillColor(240, 244, 250);
    $pdf->Rect($x,        $yF, $wF1, 6, 'DF');
    $pdf->Rect($x + $wF1, $yF, $wF2, 6);
    $pdf->SetXY($x,        $yF);
    $pdf->Cell($wF1, 6, 'Hari Aktif Kegiatan', 0, 0, 'L');
    $pdf->SetXY($x + $wF1, $yF);
    $pdf->Cell($wF2, 6, $totalHariAktif . ' hari (dari ' . count($dates) . ' hari)', 0, 1, 'L');

    /* Kembalikan ke mode normal */
    $pdf->rekapMode = false;
}

/* =========================
   OUTPUT
========================= */
$pdf->Output('D', "Rekap_Kegiatan_Pelatihan_{$from}_sd_{$to}.pdf");
exit;
