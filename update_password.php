<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_SESSION['verified_email'];
    $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE users SET password=?, reset_code=NULL, reset_expiry=NULL WHERE email=?");
    $update->bind_param("ss", $newPassword, $email);
    $update->execute();

    session_unset();
    echo "<script>alert('Kata sandi berhasil diubah! Silakan login kembali.'); window.location='login.php';</script>";
}
