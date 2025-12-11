<?php
session_start();
include 'header.php';
?>

<body>
    <main>
        <a href="lupa_sandi.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i></a>

        <div class="login-container">
            <div class="mb-8">
                <h2 class="title">Verifikasi Kode</h2>
                <p class="subtitle">Masukkan kode 6 digit yang dikirim ke email Anda</p>
            </div>

            <form action="verify_otp.php" method="POST" class="space-y-4">
                <div class="flex justify-center gap-3">
                    <input type="text" name="otp1" maxlength="1" class="otp-box" required>
                    <input type="text" name="otp2" maxlength="1" class="otp-box" required>
                    <input type="text" name="otp3" maxlength="1" class="otp-box" required>
                    <input type="text" name="otp4" maxlength="1" class="otp-box" required>
                    <input type="text" name="otp5" maxlength="1" class="otp-box" required>
                    <input type="text" name="otp6" maxlength="1" class="otp-box" required>
                </div>

                <button type="submit" class="btn-primary w-full">Verifikasi</button>
            </form>

            <div class="text-center text-sm text-gray-600 mt-3">
                Belum menerima kode?
                <span id="timer" class="font-medium text-gray-500">01:00</span>
                <a href="lupa_sandi.php" id="resendLink" class="text-sky-600 font-medium ml-1 hidden">Kirim Ulang</a>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>