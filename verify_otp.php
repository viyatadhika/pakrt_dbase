<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_SESSION['reset_email'];
    $otp = trim($_POST['otp1'] . $_POST['otp2'] . $_POST['otp3'] . $_POST['otp4'] . $_POST['otp5'] . $_POST['otp6']);

    $query = $conn->prepare("SELECT * FROM users WHERE email=? AND reset_code=? AND reset_expiry > NOW()");
    $query->bind_param("ss", $email, $otp);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['verified_email'] = $email;
        echo "<script>alert('Kode benar. Silakan buat kata sandi baru.'); window.location='reset_password.php';</script>";
    } else {
        echo "<script>alert('Kode salah atau kadaluarsa.'); window.location='verifikasi_kode.php';</script>";
    }
}
