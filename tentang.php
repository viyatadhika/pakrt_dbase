<?php
include 'header.php';
$title = "Tentang Aplikasi";
?>

<style>
    /* ===============================
   HEADER DETAIL — PREMIUM
================================*/
    .detail-header-bar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: #ffffff;

        padding: 14px 20px 12px;
        display: flex;
        align-items: center;
        gap: 15px;
        /* 
        border-bottom: 1px solid #eef2f7;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05); */
    }

    .detail-back-btn {
        width: 40px;
        height: 40px;
        border-radius: 14px;

        background: #ffffff;
        border: 1px solid #e2e8f0;

        display: flex;
        align-items: center;
        justify-content: center;

        color: #0369a1;
        font-size: 17px;
        transition: .18s ease-in-out;
    }

    .detail-back-btn:hover {
        background: #e0f2fe;
    }

    .detail-title {
        font-size: 20px;
        font-weight: 700;
        color: #0369a1;
        margin: 0;
    }

    /* ===============================
   WRAPPER KONTEN
================================*/
    /* .detail-content-wrapper {
        padding: 18px 20px 100px;
    } */

    /* Card utama */
    .detail-card {
        background: #ffffff;
        padding: 18px;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    /* ===============================
   TEXT SPACING
================================*/
    .detail-card p {
        font-size: 14px;
        color: #374151;
        margin-bottom: 6px;
    }

    .detail-card strong {
        color: #0c4a6e;
    }
</style>

<!-- DETAIL HEADER -->
<div class="detail-header-bar">
    <a href="profil.php" class="detail-back-btn" aria-label="Kembali">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h2 class="detail-title">Tentang Aplikasi</h2>
</div>

<!-- CONTENT -->
<div class="detail-content-wrapper">
    <section class="p-5 text-gray-700 text-sm leading-relaxed space-y-4">

        <!-- Logo & Info -->
        <div class="flex flex-col items-center mb-4">
            <img
                src="assets/pakrt.png"
                alt="Logo PAK RT Super App"
                class="w-20 h-20 mb-3 rounded-xl shadow-sm"
                loading="lazy">
            <h3 class="text-lg font-bold text-sky-700">PAK RT Super App</h3>
            <p class="text-gray-500 text-xs">Versi 1.0.0</p>
        </div>

        <!-- Deskripsi -->
        <p class="text-sm md:text-base leading-relaxed text-justify">
            <strong>PAK RT</strong> (Pemantauan Administrasi Kinerja Rumah Tangga) merupakan aplikasi yang
            digunakan oleh Badan Strajak Diklat Kumdil untuk memantau dan mengelola administrasi kinerja
            rumah tangga secara digital.
        </p>


        <h3 class="font-semibold text-gray-800 mt-4">Fitur Utama:</h3>
        <ul class="list-disc pl-6 space-y-2">
            <li>Riwayat checklist sebagai dokumentasi pemantauan administrasi kinerja rumah tangga.</li>
            <li>Statistik dan rekapitulasi data hasil checklist sebagai bahan evaluasi kinerja.</li>
        </ul>


        <!-- Footer Info -->
        <p class="text-sm md:text-base leading-relaxed text-justify">
            Dikembangkan oleh
            <span class="font-semibold text-sky-600">Tim PAK RT Developer</span>
            untuk mendukung lingkungan yang lebih efisien, tertib, dan transparan 💙
        </p>

    </section>
</div>