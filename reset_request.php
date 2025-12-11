<?php
include 'config.php';
include 'mail_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $query = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $query->bind_param("s", $email);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        // Generate OTP & waktu kadaluarsa
        $otpCode = random_int(100000, 999999); // lebih aman dari rand()
        $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

        // Simpan OTP ke database
        $update = $conn->prepare("UPDATE users SET reset_code = ?, reset_expiry = ? WHERE email = ?");
        $update->bind_param("sss", $otpCode, $expiry, $email);

        $update->execute();

        if (sendOTPEmail($email, $otpCode)) {
            session_start();
            $_SESSION['reset_email'] = $email;
            echo "<script>alert('Kode verifikasi telah dikirim ke email Anda.'); window.location='verifikasi_kode.php';</script>";
        } else {
            echo "<script>alert('Gagal mengirim email. Silakan coba lagi.'); window.location='lupa_sandi.php';</script>";
        }
    } else {
        echo "<script>alert('Email tidak ditemukan!'); window.location='lupa_sandi.php';</script>";
    }
}
