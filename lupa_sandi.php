<?php
$title = "Lupa Kata Sandi | WARGA RT Super App";
include 'header.php';
?>

<body>
    <a href="login.php" class="btn-back">
        <i class="fa-solid fa-arrow-left"></i>
    </a>

    <div class="login-container">
        <div class="mb-8">
            <h2 class="title">Lupa Kata Sandi?</h2>
            <p class="subtitle">Masukkan email Anda untuk mengatur ulang kata sandi.</p>
        </div>

        <form action="reset_request.php" method="POST" class="space-y-4">
            <div class="input-field">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Email terdaftar" class="input-box" required>
            </div>

            <button type="submit" class="btn-primary">Kirim Tautan Reset</button>

            <div class="text-center text-sm text-gray-600 mt-3">
                Sudah ingat kata sandi? <a href="login.php" class="text-sky-600 font-medium">Masuk di sini</a>
            </div>
        </form>
    </div>

    <?php include 'footer.php'; ?>