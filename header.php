<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <!-- Favicon -->
    <link rel="icon" href="assets/pakrt_ico.png" sizes="192x192">
    <meta name="theme-color" content="#ffffff">
    <link rel="apple-touch-icon" href="assets/pakrt_ico.png.png">
    <meta name="mobile-web-app-capable" content="yes">

    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <?php
    // Pastikan setiap halaman dapat menyediakan `$title` sebelum meng-include header.
    // Jika tidak diset, gunakan default aplikasi.
    if (!isset($title) || trim($title) === '') {
        $title = 'Pak RT Super App';
    }
    ?>
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>


    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <!-- SWIPER -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0ea5e9">

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('service-worker.js')
                .then(() => console.log("✅ Service Worker terdaftar"))
                .catch(err => console.error("❌ Gagal daftar SW", err));
        }
    </script>


</head>