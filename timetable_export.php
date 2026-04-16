<?php
session_start();
if (!isset($_SESSION['user'])) exit;

include 'config.php';
require_once('fpdf/fpdf.php');

/* =========================
   PARAM RENTANG TANGGAL
========================= */
$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to']   ?? date('Y-12-31');

/* =========================
   AMBIL DATA AGENDA
========================= */
$sql = "
SELECT *
FROM agenda_kegiatan
WHERE start_date <= '$to'
AND end_date >= '$from'
ORDER BY start_date ASC
";
$q = $conn->query($sql);

$data = [];
while ($r = $q->fetch_assoc()) {
    $data[] = $r;
}

/* =========================
   HELPER BULAN
========================= */
function getMonths($from, $to)
{
    $months = [];
    $start = new DateTime(date('Y-m-01', strtotime($from)));
    $end   = new DateTime(date('Y-m-01', strtotime($to)));
    $end->modify('+1 month');

    foreach (new DatePeriod($start, new DateInterval('P1M'), $end) as $dt) {
        $months[] = $dt;
    }
    return $months;
}

/* =========================
   HELPER REKAP
========================= */
function parseItemCount($text)
{
    $text = trim((string)$text);
    if ($text === '') return 0;

    $parts = preg_split('/\s*,\s*|\s*\/\s*|\s*;\s*|\s*\n+\s*/', $text);
    $parts = array_filter(array_map('trim', $parts), function ($v) {
        return $v !== '';
    });

    return count($parts);
}

/* =========================
   PDF CLASS
========================= */
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, 'MAHKAMAH AGUNG REPUBLIK INDONESIA', 0, 1, 'C');
        $this->Cell(0, 6, 'BADAN STRAJAK DIKLAT KUMDIL', 0, 1, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, 'REKAPITULASI KEGIATAN PELATIHAN', 0, 1, 'C');
        $this->Ln(4);
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
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        $sep = -1;
        $i = $j = $l = 0;
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
}

/* =========================
   SET PDF
========================= */
$pdf = new PDF('L', 'mm', 'Legal');
$pdf->SetMargins(6, 10, 6);
$pdf->SetAutoPageBreak(true, 12);

$months = getMonths($from, $to);

/* =========================
   LOOP PER BULAN
========================= */
foreach ($months as $m) {

    $bulanAwal  = $m->format('Y-m-01');
    $bulanAkhir = $m->format('Y-m-t');

    $namaBulan = [
        1 => 'JANUARI',
        2 => 'FEBRUARI',
        3 => 'MARET',
        4 => 'APRIL',
        5 => 'MEI',
        6 => 'JUNI',
        7 => 'JULI',
        8 => 'AGUSTUS',
        9 => 'SEPTEMBER',
        10 => 'OKTOBER',
        11 => 'NOVEMBER',
        12 => 'DESEMBER'
    ];

    $bulanData = [];
    foreach ($data as $r) {
        if ($r['end_date'] < $bulanAwal || $r['start_date'] > $bulanAkhir) continue;
        $bulanData[] = $r;
    }

    /* =========================
       REKAP DARI AGENDA
    ========================= */
    $totalPesertaAgenda = 0;
    $totalPemakaianKelas = 0;

    foreach ($bulanData as $r) {
        $totalPesertaAgenda += (int)($r['peserta'] ?? 0);

        $kelasText = trim((string)($r['kelas'] ?? ''));
        $kelasCount = parseItemCount($kelasText);
        $totalPemakaianKelas += $kelasCount;
    }

    /* =========================
       REKAP KAMAR TERPAKAI
       DIAMBIL DARI peserta_penginapan
       BERDASARKAN agenda_id YANG MASUK BULAN INI
    ========================= */
    $jumlahKamarTerpakai = 0;
    $agendaIdsBulan = [];

    foreach ($bulanData as $ag) {
        $agendaIdsBulan[] = (int)$ag['id'];
    }

    if (!empty($agendaIdsBulan)) {
        $idList = implode(',', $agendaIdsBulan);

        $sqlOcc = "
            SELECT COUNT(DISTINCT CONCAT(
                TRIM(COALESCE(gedung, '')),
                '|',
                TRIM(COALESCE(kamar, ''))
            )) AS jumlah_kamar_terpakai
            FROM peserta_penginapan
            WHERE agenda_id IN ($idList)
              AND TRIM(COALESCE(gedung, '')) <> ''
              AND TRIM(COALESCE(kamar, '')) <> ''
        ";

        $qOcc = $conn->query($sqlOcc);
        if ($qOcc && $occ = $qOcc->fetch_assoc()) {
            $jumlahKamarTerpakai = (int)($occ['jumlah_kamar_terpakai'] ?? 0);
        }
    }

    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, $namaBulan[(int)$m->format('m')] . ' ' . $m->format('Y'), 0, 1);
    $pdf->Ln(2);

    /* ===== TANGGAL ===== */
    $dates = [];
    foreach (
        new DatePeriod(
            new DateTime($bulanAwal),
            new DateInterval('P1D'),
            (new DateTime($bulanAkhir))->modify('+1 day')
        ) as $d
    ) {
        $dates[] = $d->format('Y-m-d');
    }

    /* ===== LEBAR KOLOM ===== */
    $wNo = 6;
    $wNama = 55;
    $wPeny = 28;
    $wPes = 16;
    $wAs = 24;
    $wKl = 22;
    $wMk = 26;

    $pageW = 355 - 12;
    $fixed = $wNo + $wNama + $wPeny + $wPes + $wAs + $wKl + $wMk;
    $wDay = ($pageW - $fixed) / count($dates);

    /* ===== HEADER ===== */
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(220, 220, 220);

    $pdf->Cell($wNo, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell($wNama, 8, 'Nama Pelatihan', 1, 0, 'C', true);
    $pdf->Cell($wPeny, 8, 'Penyelenggara', 1, 0, 'C', true);

    foreach ($dates as $d) {
        $pdf->Cell($wDay, 8, date('d', strtotime($d)), 1, 0, 'C', true);
    }

    $pdf->Cell($wPes, 8, 'Peserta', 1, 0, 'C', true);
    $pdf->Cell($wAs, 8, 'Asrama', 1, 0, 'C', true);
    $pdf->Cell($wKl, 8, 'Kelas', 1, 0, 'C', true);
    $pdf->Cell($wMk, 8, 'R. Makan', 1, 1, 'C', true);

    /* ===== ISI ===== */
    $pdf->SetFont('Arial', '', 7);
    $no = 1;

    foreach ($bulanData as $r) {
        $lineH = 5;

        $hNama = $pdf->NbLines($wNama, $r['judul']) * $lineH;
        $hAs   = $pdf->NbLines($wAs, $r['asrama']) * $lineH;
        $hKl   = $pdf->NbLines($wKl, $r['kelas']) * $lineH;
        $hMk   = $pdf->NbLines($wMk, $r['makan']) * $lineH;

        $rowH = max($hNama, $hAs, $hKl, $hMk, 10);
        $y = $pdf->GetY();
        $x = $pdf->GetX();

        $pdf->Rect($x, $y, $wNo, $rowH);
        $pdf->Rect($x + $wNo, $y, $wNama, $rowH);
        $pdf->Rect($x + $wNo + $wNama, $y, $wPeny, $rowH);

        $xx = $x + $wNo + $wNama + $wPeny;
        foreach ($dates as $_) {
            $pdf->Rect($xx, $y, $wDay, $rowH);
            $xx += $wDay;
        }

        $pdf->Rect($xx, $y, $wPes, $rowH);
        $xx += $wPes;
        $pdf->Rect($xx, $y, $wAs, $rowH);
        $xx += $wAs;
        $pdf->Rect($xx, $y, $wKl, $rowH);
        $xx += $wKl;
        $pdf->Rect($xx, $y, $wMk, $rowH);

        $pdf->SetXY($x, $y + ($rowH - $lineH) / 2);
        $pdf->Cell($wNo, $lineH, $no++, 0, 0, 'C');

        $pdf->SetXY($x + $wNo, $y + ($rowH - $hNama) / 2);
        $pdf->MultiCell($wNama, $lineH, $r['judul'], 0, 'L');

        $pdf->SetXY($x + $wNo + $wNama, $y + ($rowH - $lineH) / 2);
        $pdf->Cell($wPeny, $lineH, $r['kategori'], 0, 0, 'C');

        $xx = $x + $wNo + $wNama + $wPeny;

        foreach ($dates as $d) {
            if ($d >= $r['start_date'] && $d <= $r['end_date']) {

                if ($r['kategori'] == 'Menpim') {
                    $pdf->SetFillColor(255, 193, 7);
                } elseif ($r['kategori'] == 'Teknis') {
                    $pdf->SetFillColor(40, 167, 69);
                } elseif ($r['kategori'] == 'Kerjasama') {
                    $pdf->SetFillColor(0, 123, 255);
                } else {
                    $pdf->SetFillColor(255, 133, 27);
                }

                $pdf->Rect($xx, $y, $wDay, $rowH, 'F');
            }

            $pdf->Rect($xx, $y, $wDay, $rowH);
            $xx += $wDay;
        }

        $pdf->SetXY($x + $pageW - $wMk - $wKl - $wAs - $wPes, $y + ($rowH - $lineH) / 2);
        $pdf->Cell($wPes, $lineH, (int)$r['peserta'], 0, 0, 'C');

        $pdf->SetXY($x + $pageW - $wMk - $wKl - $wAs, $y + ($rowH - $hAs) / 2);
        $pdf->MultiCell($wAs, $lineH, $r['asrama'], 0, 'C');

        $pdf->SetXY($x + $pageW - $wMk - $wKl, $y + ($rowH - $hKl) / 2);
        $pdf->MultiCell($wKl, $lineH, $r['kelas'], 0, 'C');

        $pdf->SetXY($x + $pageW - $wMk, $y + ($rowH - $hMk) / 2);
        $pdf->MultiCell($wMk, $lineH, $r['makan'], 0, 'C');

        $pdf->SetY($y + $rowH);
    }

    if (empty($bulanData)) {
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 8, 'Tidak ada agenda pada bulan ini.', 1, 1, 'C');
    }

    $pdf->Ln(4);

    /* =========================
       TABEL REKAP OKUPANSI
    ========================= */
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(0, 6, 'REKAP OKUPANSI', 0, 1);

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(220, 220, 220);

    $wLabel = 100;
    $wVal   = 55;

    $pdf->Cell($wLabel, 6, 'Keterangan', 1, 0, 'C', true);
    $pdf->Cell($wVal, 6, 'Jumlah', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 7);

    $pdf->Cell($wLabel, 6, 'Total Peserta Agenda', 1, 0);
    $pdf->Cell($wVal, 6, $totalPesertaAgenda . ' orang', 1, 1, 'C');

    $pdf->Cell($wLabel, 6, 'Pemakaian Kelas', 1, 0);
    $pdf->Cell($wVal, 6, $totalPemakaianKelas . ' kelas', 1, 1, 'C');

    $pdf->Cell($wLabel, 6, 'Kamar Terpakai', 1, 0);
    $pdf->Cell($wVal, 6, $jumlahKamarTerpakai . ' kamar', 1, 1, 'C');
}

/* =========================
   OUTPUT
========================= */
$pdf->Output('D', "Rekap_Kegiatan_Pelatihan_{$from}_sd_{$to}.pdf");
exit;
