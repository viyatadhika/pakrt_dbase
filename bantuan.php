<?php
include 'header.php';
$title = "Pusat Bantuan";
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
    <h2 class="detail-title">Pusat Bantuan</h2>
</div>

<!-- CONTENT -->
<div class="detail-content-wrapper">

    <!-- KONTEN -->
    <div class="p-5 text-gray-700 text-sm leading-relaxed space-y-4">
        <p class="text-sm md:text-base text-gray-700 leading-relaxed text-justify mb-1"> Jika kamu mengalami kendala dalam penggunaan aplikasi, berikut beberapa langkah yang bisa dilakukan:</p>
        <ul class="list-disc pl-6 space-y-1">
            <li>Pastikan koneksi internet kamu stabil.</li>
            <li>Coba tutup dan buka kembali aplikasi.</li>
            <li>Periksa apakah ada pembaruan aplikasi yang tersedia.</li>
        </ul>
        <p>Masih butuh bantuan? Hubungi kami melalui:</p>
        <div class="bg-sky-50 p-4 rounded-xl text-sm">
            <p><i class="fa-solid fa-envelope text-sky-600 mr-2"></i> Email: <a href="mailto:formchecklist@gmail.com" class="text-sky-600 font-medium">formchecklist@gmail.com</a></p>
        </div>
    </div>