<?php
// ================= TIMEZONE PHP =================
date_default_timezone_set('Asia/Jakarta');

// ================= DATABASE =================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "warga_rt_bsdk";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// ================= TIMEZONE MYSQL =================
// Paksa MySQL pakai WIB (+07:00)
$conn->query("SET time_zone = '+07:00'");

// ================= EXTERNAL API KEY =================
// Dipakai untuk autentikasi sinkronisasi data ke aplikasi eksternal (laskar.bsdk)
define('EXTERNAL_API_KEY', '19eef3a3de3acc23fd54359d413207901d2c29402825ed3e1245584b61656d28');
