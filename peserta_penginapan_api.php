<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

if (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} elseif (isset($koneksi) && $koneksi instanceof mysqli) {
    $db = $koneksi;
} else {
    echo json_encode([
        'status' => false,
        'message' => 'Koneksi database tidak ditemukan dari config.php'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$db->set_charset('utf8mb4');

function jsonResponse($status, $message, $data = null)
{
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function cleanText($value)
{
    return trim((string)$value);
}

function nullable($value)
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function upperText($value)
{
    $value = trim((string)$value);
    return $value === '' ? '' : mb_strtoupper($value, 'UTF-8');
}

function titleCaseSafe($value)
{
    $value = trim((string)$value);
    if ($value === '') return '';
    return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
}

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $sql = "SELECT 
                p.id,
                p.agenda_id,
                p.nama,
                p.instansi,
                p.nip,
                p.no_hp,
                p.peran,
                p.jenis_kelamin,
                p.gedung,
                p.lantai,
                p.kamar,
                p.bed,
                p.checkin_date,
                p.checkin_time,
                p.checkout_date,
                p.checkout_time,
                p.status_inap,
                p.kondisi,
                p.catatan,
                p.created_at,
                p.updated_at,
                a.judul,
                a.start_date,
                a.end_date,
                a.kategori
            FROM peserta_penginapan p
            LEFT JOIN agenda_kegiatan a ON a.id = p.agenda_id
            ORDER BY
                p.gedung ASC,
                CAST(COALESCE(NULLIF(p.lantai, ''), '0') AS UNSIGNED) ASC,
                CAST(COALESCE(NULLIF(p.kamar, ''), '0') AS UNSIGNED) ASC,
                p.nama ASC";

    $result = $db->query($sql);
    if (!$result) {
        jsonResponse(false, 'Gagal mengambil data: ' . $db->error);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    jsonResponse(true, 'Data berhasil diambil', $rows);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, 'ID tidak valid');
    }

    $stmt = $db->prepare("SELECT * FROM peserta_penginapan WHERE id = ?");
    if (!$stmt) {
        jsonResponse(false, 'Gagal prepare detail: ' . $db->error);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();

    if (!$data) {
        jsonResponse(false, 'Data tidak ditemukan');
    }

    jsonResponse(true, 'Detail data berhasil diambil', $data);
}

if ($action === 'save') {
    $id            = (int)($_POST['id'] ?? 0);
    $agenda_id_raw = cleanText($_POST['agenda_id'] ?? '');
    $agenda_id     = ($agenda_id_raw === '') ? null : (int)$agenda_id_raw;

    $nama          = cleanText($_POST['nama'] ?? '');
    $instansi      = titleCaseSafe($_POST['instansi'] ?? '');
    $nip           = cleanText($_POST['nip'] ?? '');
    $no_hp         = cleanText($_POST['no_hp'] ?? '');
    $peran         = cleanText($_POST['peran'] ?? 'Peserta');
    $jenis_kelamin = cleanText($_POST['jenis_kelamin'] ?? '');
    $gedung        = titleCaseSafe($_POST['gedung'] ?? '');
    $lantai        = cleanText($_POST['lantai'] ?? '');
    $kamar         = upperText($_POST['kamar'] ?? '');
    $bed           = upperText($_POST['bed'] ?? '');
    $checkin_date  = cleanText($_POST['checkin_date'] ?? '');
    $checkin_time  = cleanText($_POST['checkin_time'] ?? '');
    $checkout_date = cleanText($_POST['checkout_date'] ?? '');
    $checkout_time = cleanText($_POST['checkout_time'] ?? '');
    $status_inap   = cleanText($_POST['status_inap'] ?? 'Belum Check-in');
    $kondisi       = titleCaseSafe($_POST['kondisi'] ?? '');
    $catatan       = cleanText($_POST['catatan'] ?? '');

    $allowedPeran  = ['Peserta', 'Pengajar', 'Panitia'];
    $allowedJK     = ['', 'L', 'P'];
    $allowedStatus = ['Belum Check-in', 'Check-in', 'Check-out'];

    if ($nama === '') {
        jsonResponse(false, 'Nama wajib diisi');
    }

    if ($gedung === '') {
        jsonResponse(false, 'Gedung wajib diisi');
    }

    if ($kamar === '') {
        jsonResponse(false, 'Kamar wajib diisi');
    }

    if (!in_array($peran, $allowedPeran, true)) {
        jsonResponse(false, 'Peran tidak valid');
    }

    if (!in_array($jenis_kelamin, $allowedJK, true)) {
        jsonResponse(false, 'Jenis kelamin tidak valid');
    }

    if (!in_array($status_inap, $allowedStatus, true)) {
        jsonResponse(false, 'Status inap tidak valid');
    }

    if ($checkin_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin_date)) {
        jsonResponse(false, 'Format tanggal check-in tidak valid');
    }

    if ($checkout_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout_date)) {
        jsonResponse(false, 'Format tanggal check-out tidak valid');
    }

    if ($checkin_time !== '' && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $checkin_time)) {
        jsonResponse(false, 'Format jam check-in tidak valid');
    }

    if ($checkout_time !== '' && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $checkout_time)) {
        jsonResponse(false, 'Format jam check-out tidak valid');
    }

    if ($checkin_date !== '' && $checkout_date !== '' && $checkout_date < $checkin_date) {
        jsonResponse(false, 'Tanggal check-out tidak boleh lebih kecil dari check-in');
    }

    if ($agenda_id !== null && $agenda_id > 0) {
        $cekAgenda = $db->prepare("SELECT id FROM agenda_kegiatan WHERE id = ? LIMIT 1");
        if (!$cekAgenda) {
            jsonResponse(false, 'Gagal prepare cek agenda: ' . $db->error);
        }

        $cekAgenda->bind_param('i', $agenda_id);
        $cekAgenda->execute();
        $agendaRes = $cekAgenda->get_result();

        if (!$agendaRes->fetch_assoc()) {
            jsonResponse(false, 'Agenda tidak ditemukan');
        }
    } else {
        $agenda_id = null;
    }

    $checkin_date  = nullable($checkin_date);
    $checkin_time  = nullable($checkin_time);
    $checkout_date = nullable($checkout_date);
    $checkout_time = nullable($checkout_time);
    $jenis_kelamin = nullable($jenis_kelamin);
    $instansi      = nullable($instansi);
    $nip           = nullable($nip);
    $no_hp         = nullable($no_hp);
    $lantai        = nullable($lantai);
    $bed           = nullable($bed);
    $kondisi       = nullable($kondisi);
    $catatan       = nullable($catatan);

    if ($id > 0) {
        $sql = "UPDATE peserta_penginapan SET
                    agenda_id = ?,
                    nama = ?,
                    instansi = ?,
                    nip = ?,
                    no_hp = ?,
                    peran = ?,
                    jenis_kelamin = ?,
                    gedung = ?,
                    lantai = ?,
                    kamar = ?,
                    bed = ?,
                    checkin_date = ?,
                    checkin_time = ?,
                    checkout_date = ?,
                    checkout_time = ?,
                    status_inap = ?,
                    kondisi = ?,
                    catatan = ?
                WHERE id = ?";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            jsonResponse(false, 'Gagal prepare update: ' . $db->error);
        }

        $stmt->bind_param(
            'isssssssssssssssssi',
            $agenda_id,
            $nama,
            $instansi,
            $nip,
            $no_hp,
            $peran,
            $jenis_kelamin,
            $gedung,
            $lantai,
            $kamar,
            $bed,
            $checkin_date,
            $checkin_time,
            $checkout_date,
            $checkout_time,
            $status_inap,
            $kondisi,
            $catatan,
            $id
        );

        if ($stmt->execute()) {
            jsonResponse(true, 'Data berhasil diperbarui');
        } else {
            jsonResponse(false, 'Gagal memperbarui data: ' . $stmt->error);
        }
    } else {
        $sql = "INSERT INTO peserta_penginapan (
                    agenda_id, nama, instansi, nip, no_hp, peran, jenis_kelamin,
                    gedung, lantai, kamar, bed, checkin_date, checkin_time,
                    checkout_date, checkout_time, status_inap, kondisi, catatan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            jsonResponse(false, 'Gagal prepare insert: ' . $db->error);
        }

        $stmt->bind_param(
            'isssssssssssssssss',
            $agenda_id,
            $nama,
            $instansi,
            $nip,
            $no_hp,
            $peran,
            $jenis_kelamin,
            $gedung,
            $lantai,
            $kamar,
            $bed,
            $checkin_date,
            $checkin_time,
            $checkout_date,
            $checkout_time,
            $status_inap,
            $kondisi,
            $catatan
        );

        if ($stmt->execute()) {
            jsonResponse(true, 'Data berhasil disimpan');
        } else {
            jsonResponse(false, 'Gagal menyimpan data: ' . $stmt->error);
        }
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, 'ID tidak valid');
    }

    $stmt = $db->prepare("DELETE FROM peserta_penginapan WHERE id = ?");
    if (!$stmt) {
        jsonResponse(false, 'Gagal prepare delete: ' . $db->error);
    }

    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        jsonResponse(true, 'Data berhasil dihapus');
    } else {
        jsonResponse(false, 'Gagal menghapus data: ' . $stmt->error);
    }
}

jsonResponse(false, 'Action tidak dikenali');
