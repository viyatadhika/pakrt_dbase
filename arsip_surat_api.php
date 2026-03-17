<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'config.php';

$action = $_GET['action'] ?? '';
$role   = strtolower($_SESSION['user']['role'] ?? '');

// Proteksi server-side: hanya admin & sekretariat yang bisa ubah data
$allowedEdit = ['admin', 'sekretariat'];
if (in_array($action, ['create', 'update', 'delete']) && !in_array($role, $allowedEdit)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Tidak diizinkan']);
    exit;
}

/* ================= HELPER ================= */

function json_ok($data = null, $msg = 'OK')
{
    echo json_encode(['ok' => true, 'message' => $msg, 'data' => $data]);
    exit;
}

function json_fail($msg = 'Gagal', $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

function post($k)
{
    return isset($_POST[$k]) ? trim($_POST[$k]) : '';
}

// Path absolut ke folder wargart
define('WARGART_DIR', dirname(__DIR__) . '/wargart/');
define('UPLOAD_DIR',  WARGART_DIR . 'uploads/arsip_surat/');
define('UPLOAD_URL',  '../wargart/uploads/arsip_surat/'); // path relatif untuk akses dari browser

/* ================= FILE HANDLER ================= */

function handle_upload($field = 'file')
{
    if (empty($_FILES[$field]['name'])) return '';

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK)
        json_fail('Upload gagal');

    if ($_FILES[$field]['size'] > 2 * 1024 * 1024)
        json_fail('File maksimal 2MB');

    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed))
        json_fail('Format file tidak diizinkan (PDF/JPG/JPEG/PNG/WebP)');

    if (!is_dir(UPLOAD_DIR))
        mkdir(UPLOAD_DIR, 0777, true);

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES[$field]['name']);
    $newName  = time() . '_' . $safeName;
    $target   = UPLOAD_DIR . $newName;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target))
        json_fail('Gagal menyimpan file');

    // Simpan path relatif dari wargart/ agar konsisten dengan Warga RT
    return 'uploads/arsip_surat/' . $newName;
}

function delete_file($path)
{
    if (!$path) return;
    $full = WARGART_DIR . $path;
    if (file_exists($full)) unlink($full);
}

/* ================= LIST ================= */

if ($action === 'list') {
    $q = $conn->query("SELECT * FROM arsip_surat ORDER BY tanggal_surat DESC, id DESC");
    if (!$q) json_fail('Query gagal', 500);

    $data = [];
    while ($r = $q->fetch_assoc()) {
        // Konversi path file agar bisa diakses dari folder Pak RT
        if (!empty($r['file_path'])) {
            $r['file_path'] = '../wargart/' . $r['file_path'];
        }
        $data[] = $r;
    }

    json_ok($data);
}

/* ================= CREATE ================= */

if ($action === 'create') {
    $nomor    = post('nomor_surat');
    $perihal  = post('perihal');
    $pengirim = post('pengirim_tujuan');
    $jenis    = post('jenis');
    $tanggal  = post('tanggal_surat');
    $ket      = post('keterangan');

    if (!$nomor || !$perihal || !$tanggal)
        json_fail('Nomor, Perihal, dan Tanggal wajib diisi');

    if (!in_array($jenis, ['masuk', 'keluar']))
        json_fail('Jenis tidak valid');

    $filePath = handle_upload('file');

    $stmt = $conn->prepare("
        INSERT INTO arsip_surat
        (nomor_surat, perihal, pengirim, jenis, tanggal_surat, keterangan, file_path, created_at)
        VALUES (?,?,?,?,?,?,?,NOW())
    ");

    if (!$stmt) json_fail('Prepare gagal', 500);

    $stmt->bind_param("sssssss", $nomor, $perihal, $pengirim, $jenis, $tanggal, $ket, $filePath);

    if (!$stmt->execute()) json_fail('Gagal menyimpan', 500);

    json_ok(['id' => $stmt->insert_id], 'Berhasil menambah surat');
}

/* ================= UPDATE ================= */

if ($action === 'update') {
    $id       = (int)post('id');
    $nomor    = post('nomor_surat');
    $perihal  = post('perihal');
    $pengirim = post('pengirim_tujuan');
    $jenis    = post('jenis');
    $tanggal  = post('tanggal_surat');
    $ket      = post('keterangan');

    if ($id <= 0)                          json_fail('ID tidak valid');
    if (!$nomor || !$perihal || !$tanggal) json_fail('Nomor, Perihal, dan Tanggal wajib diisi');

    $old = '';
    $get = $conn->prepare("SELECT file_path FROM arsip_surat WHERE id=?");
    $get->bind_param("i", $id);
    $get->execute();
    $res = $get->get_result();
    if ($row = $res->fetch_assoc()) $old = $row['file_path'];

    $newFile = handle_upload('file');

    if ($newFile) {
        $stmt = $conn->prepare("
            UPDATE arsip_surat
            SET nomor_surat=?, perihal=?, pengirim=?, jenis=?, tanggal_surat=?, keterangan=?, file_path=?
            WHERE id=?
        ");
        $stmt->bind_param("sssssssi", $nomor, $perihal, $pengirim, $jenis, $tanggal, $ket, $newFile, $id);
        if ($stmt->execute()) {
            if ($old && $old !== $newFile) delete_file($old);
            json_ok(null, 'Berhasil update');
        }
    } else {
        $stmt = $conn->prepare("
            UPDATE arsip_surat
            SET nomor_surat=?, perihal=?, pengirim=?, jenis=?, tanggal_surat=?, keterangan=?
            WHERE id=?
        ");
        $stmt->bind_param("ssssssi", $nomor, $perihal, $pengirim, $jenis, $tanggal, $ket, $id);
        if ($stmt->execute()) json_ok(null, 'Berhasil update');
    }

    json_fail('Gagal update', 500);
}

/* ================= DELETE ================= */

if ($action === 'delete') {
    $id = (int)post('id');
    if ($id <= 0) json_fail('ID tidak valid');

    $old = '';
    $get = $conn->prepare("SELECT file_path FROM arsip_surat WHERE id=?");
    $get->bind_param("i", $id);
    $get->execute();
    $res = $get->get_result();
    if ($row = $res->fetch_assoc()) $old = $row['file_path'];

    $stmt = $conn->prepare("DELETE FROM arsip_surat WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($old) delete_file($old);
        json_ok(null, 'Berhasil hapus');
    }

    json_fail('Gagal hapus', 500);
}

json_fail('Action tidak dikenali', 404);
