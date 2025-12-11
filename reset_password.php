<?php
session_start();
include 'header.php';

if (!isset($_SESSION['verified_email'])) {
    header("Location: lupa_sandi.php");
    exit;
}
?>

<body>
    <main>
        <div class="login-container">
            <div class="mb-8">
                <h2 class="title">Atur Ulang Kata Sandi</h2>
                <p class="subtitle">Masukkan kata sandi baru Anda</p>
            </div>

            <form action="update_password.php" method="POST" class="space-y-4">
                <div class="input-field">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="new_password" id="new_password" class="input-box pr-12" placeholder="Kata sandi baru" required>
                    <i id="togglePassword" class="fa-regular fa-eye toggle-eye"></i>
                </div>

                <button type="submit" class="btn-primary">Simpan Sandi Baru</button>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>