<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//require 'vendor/autoload.php'; // jika pakai Composer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php'; // gunakan 3 baris ini jika tidak pakai composer

function sendOTPEmail($toEmail, $otpCode)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'timedit1pusdiklat@gmail.com';
        $mail->Password = 'nqxx mxxk ipbu geev';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('timedit1pusdiklat@gmail.com', 'WARGA RT Super App');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Kode Reset Kata Sandi Anda';
        $mail->Body = "
            <div style='font-family:Poppins,sans-serif; text-align:center; padding:20px;'>
                <h2>Kode Reset Kata Sandi</h2>
                <p>Gunakan kode berikut untuk mengatur ulang kata sandi Anda:</p>
                <h1 style='color:#0284c7; letter-spacing:4px;'>$otpCode</h1>
                <p style='margin-top:20px; font-size:13px; color:#555;'>Kode ini berlaku selama 10 menit.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
