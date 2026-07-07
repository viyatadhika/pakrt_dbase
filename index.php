<?php
$title = "PAK RT Super App";
include 'header.php';
?>

<body>
    <div class="splash-screen splash-active" id="splash">
        <img src="assets/pakrt.png" alt="PAK RT Logo" class="splash-logo">
        <h1 class="text-sky-900 text-[22px] font-semibold tracking-wide">PAK RT</h1>
        <p class="text-sky-700 text-xs mt-1 opacity-90 slide-up">Pemantauan • Administrasi • Kinerja • Rumah • Tangga</p>
        <div class="loading-dots">
            <div class="loading-dot"></div>
            <div class="loading-dot"></div>
            <div class="loading-dot"></div>
        </div>
    </div>

    <footer class="footer">&copy; 2025 PAK RT. All rights reserved.</footer>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const splash = document.querySelector(".splash-screen");
            const footer = document.querySelector(".footer");

            if (splash) splash.classList.remove("fade-out");
            if (footer) footer.classList.remove("fade-out");

            setTimeout(function() {
                if (splash) splash.classList.add("fade-out");
                if (footer) footer.classList.add("fade-out");

                setTimeout(function() {
                    window.location.href = "login.php";
                }, 500);
            }, 3000);
        });
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>