<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: beranda.php");
    exit;
}
$title = "Daftar Akun | WARGA RT Super App";
include 'config.php';
include 'header.php';

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip = $_POST['nip'];
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Cek apakah NIP sudah digunakan
    $check = $conn->prepare("SELECT * FROM users WHERE nip = ?");
    $check->bind_param("s", $nip);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $error = "NIP sudah terdaftar, silakan login.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (nip, nama, email, phone, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nip, $nama, $email, $phone, $password);

        if ($stmt->execute()) {
            $_SESSION['user'] = [
                'nip' => $nip,
                'nama' => $nama
            ];
            header("Location: beranda.php");
            exit;
        } else {
            $error = "Gagal menyimpan data.";
        }
    }
}
?>

<a href="login.php" class="btn-back">
    <i class="fa-solid fa-arrow-left"></i>
</a>

<div class="register-container">
    <div class="mb-8">
        <h2 class="title">Buat Akun Baru</h2>
        <p class="subtitle">Daftarkan diri Anda untuk menggunakan WARGA RT Super App</p>
    </div>

    <?php if (!empty($error)) : ?>
        <div class="text-red-600 text-sm mb-3"><?= $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div class="input-field">
            <i class="fa-solid fa-id-card"></i>
            <input type="text" name="nip" placeholder="NIP" class="input-box" required>
        </div>

        <div class="input-field">
            <i class="fa-solid fa-user"></i>
            <input type="text" name="nama" placeholder="Nama lengkap" class="input-box" required>
        </div>

        <div class="input-field">
            <i class="fa-solid fa-envelope"></i>
            <input type="text" name="email" placeholder="Email" class="input-box" required>
        </div>

        <div class="input-field">
            <i class="fa-solid fa-phone"></i>
            <input type="tel" name="phone" placeholder="Nomor telepon" class="input-box" required>
        </div>

        <div class="input-field">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" placeholder="Kata sandi" class="input-box pr-12" required>
            <i id="togglePassword" class="fa-regular fa-eye toggle-eye"></i>
        </div>

        <button type="submit" class="btn-primary">Daftar</button>

        <div class="text-center text-sm text-gray-600 mt-3">
            Sudah punya akun?
            <a href="login.php" class="text-sky-600 font-medium">Masuk di sini</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>