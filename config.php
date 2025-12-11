<?php
$host = "localhost";
$user = "root";     // ganti jika username MySQL kamu beda
$pass = "";         // password MySQL DBdevel@#2024
$db   = "warga_rt_bsdk";  // nama database

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

date_default_timezone_set('Asia/Jakarta');
