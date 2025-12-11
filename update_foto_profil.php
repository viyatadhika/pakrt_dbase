<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'config.php';

if (!isset($_SESSION['user'])) {
    die("Akses ditolak! (session)");
}

$nip = $_SESSION['user']['nip'];

if (!isset($_FILES['foto_profil'])) {
    die("Tidak ada file terkirim!");
}

if ($_FILES['foto_profil']['error'] !== 0) {
    die("Upload error code: " . $_FILES['foto_profil']['error']);
}

$folder = "uploads/profile/";
if (!is_dir($folder)) mkdir($folder, 0777, true);

$ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];

if (!in_array($ext, $allowed)) {
    die("Format tidak diizinkan!");
}

$newName = $nip . "_" . time() . "." . $ext;
$path = $folder . $newName;

if (!move_uploaded_file($_FILES['foto_profil']['tmp_name'], $path)) {
    die("Gagal memindahkan file!");
}

// update database
$stmt = $conn->prepare("UPDATE users SET foto_profil = ? WHERE nip = ?");
if (!$stmt) die("SQL ERROR: " . $conn->error);

$stmt->bind_param("si", $newName, $nip);
$stmt->execute();
$stmt->close();

// update session
$_SESSION['user']['foto_profil'] = $newName;

// redirect ✔
header("Location: profil.php?updated=1");
exit;
