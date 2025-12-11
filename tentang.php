<?php
include 'header.php';
$title = "Tentang Aplikasi";
?>
<!-- Modern Sticky Header -->
<div id="stickyHeader" class="seamless-header">
    <div class="flex items-center gap-1 mb-2">
        <!-- Back Button -->
        <a href="profil.php" class="back-btn p-2 bg-white shadow-sm border border-sky-100 hover:bg-sky-50 transition">
            <i class="fa-solid fa-arrow-left text-sky-600 text-lg"></i>
        </a>

        <!-- Title & Subtitle -->
        <div class="flex-1">
            <h2 class="title checklist-page font-bold text-xl md:text-2xl text-sky-600 leading-tight">Tentang Aplikasi</h2>
        </div>
    </div>
</div>

<!-- KONTEN -->
<div class="login-container bantuan-page p-6 text-gray-700 text-sm leading-relaxed space-y-4">
    <div class="flex flex-col items-center mb-4">
        <img src="wargart_splash.png" alt="Logo Aplikasi" class="w-20 h-20 mb-3 rounded-xl shadow-sm">
        <h3 class="text-lg font-bold text-sky-700">WARGA RT Super App</h3>
        <p class="text-gray-500 text-xs">Versi 1.0.0</p>
    </div>

    <p class="text-sm md:text-base text-gray-700 leading-relaxed text-justify mb-1"><strong>WARGA RT Super App</strong> adalah aplikasi terpadu untuk membantu pengelolaan kegiatan WARGA RT, administrasi, serta sarana prasarana secara digital.</p>

    <h3 class="font-semibold text-gray-800 mt-4">Fitur Utama:</h3>
    <ul class="list-disc pl-6 space-y-2">
        <li>Form ceklist asrama & fasilitas.</li>
        <li>Riwayat kegiatan dan laporan petugas.</li>
        <li>Profil pengguna dan manajemen data akun.</li>
    </ul>

    <p class="text-sm md:text-base text-gray-700 leading-relaxed text-justify mb-1">Dikembangkan oleh <span class="font-semibold text-sky-600">Tim WARGA RT Developer</span> untuk mendukung lingkungan yang lebih efisien dan transparan 💙.</p>
</div>