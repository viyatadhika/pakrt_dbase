<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!defined('CSS_VERSION')) {
    define('CSS_VERSION', '1.0.6');
}

if (!isset($title) || trim((string)$title) === '') {
    $title = 'PAK RT Super App';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0ea5e9">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="PAK RT">

    <!-- Icon -->
    <link rel="icon" href="assets/pakrt_ico.png" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="assets/pakrt_ico.png">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <!-- Style utama -->
    <link rel="stylesheet" href="style.css?v=<?= CSS_VERSION ?>">

    <script>
        if ("serviceWorker" in navigator) {
            window.addEventListener("load", function() {
                navigator.serviceWorker.register("service-worker.js")
                    .then(function(registration) {
                        console.log("Service Worker aktif:", registration.scope);
                    })
                    .catch(function(error) {
                        console.error("Service Worker gagal:", error);
                    });
            });
        }
    </script>
</head>