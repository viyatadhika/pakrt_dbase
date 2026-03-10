<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: beranda.php");
    exit;
}

$title = "Login | WARGA RT Super App";
include 'config.php';
include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip      = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("
        SELECT id, nip, nama, role, password
        FROM users
        WHERE nip = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $nip);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            // ✅ Hanya role ini yang boleh login di aplikasi pimpinan
            $allowedRoles = ['admin', 'pimpinan', 'supervisor', 'koordinator'];

            if (!in_array(strtolower($user['role']), $allowedRoles)) {
                $error = "Akun Anda tidak memiliki akses ke aplikasi ini.";
            } else {
                $_SESSION['user'] = [
                    'id'   => (int)$user['id'],
                    'nip'  => $user['nip'],
                    'nama' => $user['nama'],
                    'role' => $user['role']
                ];
                header("Location: beranda.php");
                exit;
            }
        } else {
            $error = "Kata sandi salah.";
        }
    } else {
        $error = "Akun tidak ditemukan.";
    }
}
?>

<div class="login-container">
    <div class="mb-8">
        <h2 class="title">Selamat Datang</h2>
        <p class="subtitle">Silakan masuk ke akun PAK RT Anda</p>
    </div>

    <?php if (!empty($error)) : ?>
        <div class="text-red-600 text-sm mb-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div class="input-field">
            <i class="fa-solid fa-id-card"></i>
            <input type="text" name="username" placeholder="NIP" class="input-box" required>
        </div>

        <div class="input-field">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" placeholder="Kata sandi" class="input-box pr-12" required>
            <i id="togglePassword" class="fa-regular fa-eye toggle-eye"></i>
        </div>

        <div class="flex justify-end">
            <a href="lupa_sandi.php" class="text-xs text-gray-500 hover:text-gray-700">Lupa kata sandi?</a>
        </div>

        <button type="submit" class="btn-primary">Masuk</button>
    </form>
</div>

<?php include 'footer.php'; ?>