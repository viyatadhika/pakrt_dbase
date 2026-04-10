<?php
$host = "localhost";
$user = "root";
$pass = "DBdevel@#2024";     // password MySQL
$db   = "warga_rt_bsdk";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Gemini AI — dipanggil dari ai_assistant.php
//define('GEMINI_API_KEY', 'AIzaSyDRyQtPW4LSDdWMqeN127c4Rv6l96lCJVs');
