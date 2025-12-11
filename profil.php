<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
$activePage = basename($_SERVER['PHP_SELF']);
$title = "Profil";
include 'header.php';



$user_id = $_SESSION['user']['nip'];

/* ===================== AMBIL DATA USER ===================== */

$stmt = $conn->prepare("
    SELECT nip, nama, email, created_at, phone, foto_profil 
    FROM users 
    WHERE nip = ?
");
$stmt->bind_param("s", $user_id); // ← PERBAIKAN: gunakan STRING
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$nama = trim($user['nama']);
$email = $user['email'] ?? '-';
$telepon = $user['phone'] ?? '-';
$foto = $user['foto_profil'] ?? null;

/* ===================== GENERATE AVATAR ===================== */

$parts = explode(" ", $nama);
$first = strtoupper(substr($parts[0], 0, 1));
$last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : "";
$initial = $first . $last;

$colors = ["#3B82F6", "#10B981", "#F59E0B", "#EF4444", "#6366F1", "#14B8A6"];
$colorIndex = hexdec(substr(md5($nama), 0, 2)) % count($colors);
$avatarColor = $colors[$colorIndex];
?>

<!-- ===================== HEADER ===================== -->
<div class="p-6 pb-2 text-left">
    <h2 class="text-xl font-bold text-sky-700">Profil Pengguna</h2>
</div>

<!-- ===================== AVATAR ===================== -->
<div class="flex flex-col items-center mt-2">

    <form id="formFotoProfil" action="update_foto_profil.php" method="POST" enctype="multipart/form-data">

        <div onclick="document.getElementById('fotoProfilInput').click()"
            class="relative w-28 h-28 mb-4 cursor-pointer">

            <?php if ($foto): ?>
                <!-- Jika user punya foto profil -->
                <img src="uploads/<?= htmlspecialchars($foto); ?>"
                    id="fotoProfilPreview"
                    class="w-28 h-28 rounded-full object-cover shadow-lg border" />
            <?php else: ?>
                <!-- Jika user TIDAK punya foto → tampilkan inisial -->
                <div id="fotoProfilPreview"
                    class="w-28 h-28 rounded-full flex items-center justify-center text-white text-3xl font-bold shadow-lg border"
                    style="background-color: <?= $avatarColor ?>;">
                    <?= $initial ?>
                </div>
            <?php endif; ?>

            <div class="absolute bottom-1 right-1 bg-sky-600 w-8 h-8 rounded-full flex items-center justify-center text-white shadow-md">
                <i class="fa-solid fa-camera"></i>
            </div>
        </div>

        <input type="file" id="fotoProfilInput" name="foto_profil" class="hidden" accept="image/*">

        <button type="submit" id="btnSimpanFoto"
            class="w-40 py-2 rounded-xl bg-sky-600 text-white font-semibold hidden transition active:scale-95">
            Simpan Foto
        </button>
    </form>

    <h2 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($nama) ?></h2>
    <p class="text-xs text-gray-500">
        Bergabung sejak <?= date('d M Y', strtotime($user["created_at"])) ?>
    </p>
</div>

<!-- ===================== DETAIL INFORMASI ===================== -->
<div class="mt-6 px-6 space-y-2 max-w-md mx-auto">

    <div class="info-card">
        <span>NIP</span>
        <strong><?= htmlspecialchars($user["nip"]) ?></strong>
    </div>

    <div class="info-card">
        <span>Email</span>
        <strong><?= htmlspecialchars($email) ?></strong>
    </div>

    <div class="info-card">
        <span>No. Telepon</span>
        <strong><?= htmlspecialchars($telepon) ?></strong>
    </div>

</div>

<!-- ===================== MENU TAMBAHAN ===================== -->
<div class="mt-8 px-6 max-w-md mx-auto space-y-2 text-sm">

    <a href="bantuan.php" class="menu-link">
        <div><i class="fa-solid fa-circle-question text-sky-600"></i> Pusat Bantuan</div>
        <i class="fa-solid fa-chevron-right"></i>
    </a>

    <a href="kebijakan.php" class="menu-link">
        <div><i class="fa-solid fa-shield-halved text-sky-600"></i> Kebijakan Privasi</div>
        <i class="fa-solid fa-chevron-right"></i>
    </a>

    <a href="tentang.php" class="menu-link">
        <div><i class="fa-solid fa-circle-info text-sky-600"></i> Tentang Aplikasi</div>
        <i class="fa-solid fa-chevron-right"></i>
    </a>

</div>

<!-- ===================== LOGOUT ===================== -->
<div class="px-6 mt-10 mb-24 max-w-md mx-auto">
    <button onclick="window.location.href='logout.php'"
        class="btn-primary">
        Keluar Akun
    </button>
</div>

<?php include 'nav_monitoring.php'; ?>
<?php include 'footer.php'; ?>

<style>
    .info-card {
        background: white;
        border: 1px solid #e2e8f0;
        padding: 12px 16px;
        border-radius: 14px;
        font-size: 14px;
        color: #475569;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .menu-link {
        background: white;
        border: 1px solid #e2e8f0;
        padding: 12px 16px;
        border-radius: 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #334155;
        font-weight: 600;
        transition: 0.25s;
    }

    .menu-link:hover {
        background: #ecfeff;
    }
</style>