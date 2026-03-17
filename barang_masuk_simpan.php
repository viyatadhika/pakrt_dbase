<?php
session_start();
include 'config.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

function jsonOut($arr)
{
    echo json_encode($arr);
    exit;
}
function safeText($t)
{
    return trim((string)$t);
}

// ✅ Proteksi server-side: hanya admin & gudang
if (!isset($_SESSION['user'])) {
    jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
}
$allowedRoles = ['admin', 'gudang'];
if (!in_array(strtolower($_SESSION['user']['role'] ?? ''), $allowedRoles)) {
    jsonOut(['status' => 'error', 'message' => 'Tidak diizinkan']);
}

function compressImageToMaxKB_NoDestroy($tmpPath, $destPath, $maxKB = 50)
{
    $info = @getimagesize($tmpPath);
    if (!$info) return false;
    $mime = $info['mime'] ?? '';

    $img = null;
    if ($mime === 'image/jpeg')     $img = @imagecreatefromjpeg($tmpPath);
    elseif ($mime === 'image/png')  $img = @imagecreatefrompng($tmpPath);
    elseif ($mime === 'image/webp') $img = @imagecreatefromwebp($tmpPath);
    else return false;
    if (!$img) return false;

    $w = imagesx($img);
    $h = imagesy($img);
    $canvas = imagecreatetruecolor($w, $h);
    imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);

    $quality = 80;
    $minQuality = 35;
    do {
        ob_start();
        imagejpeg($canvas, null, $quality);
        $data = ob_get_clean();
        $sizeKB = strlen($data) / 1024;
        if ($sizeKB <= $maxKB) {
            file_put_contents($destPath, $data);
            return true;
        }
        $quality -= 5;
    } while ($quality >= $minQuality);

    ob_start();
    imagejpeg($canvas, null, $minQuality);
    $data = ob_get_clean();
    file_put_contents($destPath, $data);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['status' => 'error', 'message' => 'Invalid request method']);
}

$user_id  = (int)($_SESSION['user']['id'] ?? 0);
if ($user_id <= 0) jsonOut(['status' => 'error', 'message' => 'Unauthorized']);

$ref      = safeText($_POST['ref']      ?? '');
$tanggal  = safeText($_POST['tanggal']  ?? date('Y-m-d'));
$supplier = safeText($_POST['supplier'] ?? '');
$no_sj    = safeText($_POST['no_sj']    ?? '');
$items    = json_decode($_POST['items'] ?? '[]', true);

if ($ref === '' || $supplier === '' || empty($items) || !is_array($items)) {
    jsonOut(['status' => 'error', 'message' => 'Data belum lengkap (ref/supplier/items wajib)']);
}

// Cek duplikat SJ
if ($no_sj !== '') {
    $cek = $conn->prepare("SELECT id FROM barang_masuk WHERE supplier = ? AND no_sj = ? LIMIT 1");
    if (!$cek) jsonOut(['status' => 'error', 'message' => 'Query cek SJ gagal: ' . $conn->error]);
    $cek->bind_param("ss", $supplier, $no_sj);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        jsonOut(['status' => 'error', 'message' => "No Surat Jalan sudah pernah diinput untuk supplier ini ($supplier - $no_sj)"]);
    }
}

// Upload file SJ
$file_sj_db = null;
if (!empty($_FILES['file_sj']['name'])) {
    $ext = strtolower(pathinfo($_FILES['file_sj']['name'], PATHINFO_EXTENSION));
    $allow = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allow)) jsonOut(['status' => 'error', 'message' => 'Format file tidak didukung (jpg/jpeg/png/webp/pdf)']);
    if (!is_dir('uploads/sj')) @mkdir('uploads/sj', 0777, true);

    if ($ext === 'pdf') {
        $file_sj_db = $ref . ".pdf";
        if (!move_uploaded_file($_FILES['file_sj']['tmp_name'], "uploads/sj/$file_sj_db")) {
            jsonOut(['status' => 'error', 'message' => 'Gagal upload file PDF surat jalan']);
        }
    } else {
        $file_sj_db = $ref . ".jpg";
        if (!compressImageToMaxKB_NoDestroy($_FILES['file_sj']['tmp_name'], "uploads/sj/$file_sj_db", 50)) {
            jsonOut(['status' => 'error', 'message' => 'Gagal kompres/upload gambar surat jalan']);
        }
    }
}

// Simpan transaksi
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO barang_masuk (ref_kode, tanggal, supplier, no_sj, file_sj, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) throw new Exception("Prepare header gagal: " . $conn->error);
    $stmt->bind_param("sssssi", $ref, $tanggal, $supplier, $no_sj, $file_sj_db, $user_id);
    $stmt->execute();

    $barang_masuk_id = (int)$conn->insert_id;
    if ($barang_masuk_id <= 0) throw new Exception("Gagal membuat transaksi header");

    foreach ($items as $i) {
        $kode = safeText($i['kode'] ?? '');
        $qty  = (int)($i['qty']  ?? 0);
        if ($kode === '' || $qty <= 0) throw new Exception("Item tidak valid (kode/qty)");

        $q = $conn->prepare("SELECT b.id, b.kode_barang, b.nama_barang, COALESCE(s.nama,'') AS satuan FROM master_barang b LEFT JOIN master_satuan s ON b.satuan_id = s.id WHERE b.kode_barang = ? LIMIT 1");
        if (!$q) throw new Exception("Prepare master barang gagal: " . $conn->error);
        $q->bind_param("s", $kode);
        $q->execute();
        $res = $q->get_result();
        if (!$res || $res->num_rows === 0) throw new Exception("Barang tidak ditemukan: $kode");
        $barang = $res->fetch_assoc();

        $d = $conn->prepare("INSERT INTO barang_masuk_detail (barang_masuk_id, kode_barang, nama_barang, qty, satuan) VALUES (?, ?, ?, ?, ?)");
        if (!$d) throw new Exception("Prepare detail gagal: " . $conn->error);
        $d->bind_param("issis", $barang_masuk_id, $barang['kode_barang'], $barang['nama_barang'], $qty, $barang['satuan']);
        $d->execute();

        $u = $conn->prepare("UPDATE master_barang SET stok = stok + ? WHERE id = ?");
        if (!$u) throw new Exception("Prepare update stok gagal: " . $conn->error);
        $u->bind_param("ii", $qty, $barang['id']);
        $u->execute();
    }

    $conn->commit();
    jsonOut(['status' => 'ok', 'id' => $barang_masuk_id, 'ref' => $ref]);
} catch (Throwable $e) {
    $conn->rollback();
    if (!empty($file_sj_db)) {
        $p = "uploads/sj/$file_sj_db";
        if (file_exists($p)) @unlink($p);
    }
    $msg = $e->getMessage();
    if (strpos($msg, 'Duplicate entry') !== false && strpos($msg, 'uniq_supplier_sj') !== false) {
        $msg = "No Surat Jalan sudah pernah diinput untuk supplier ini ($supplier - $no_sj)";
    }
    jsonOut(['status' => 'error', 'message' => $msg]);
}
