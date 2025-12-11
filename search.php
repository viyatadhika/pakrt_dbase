<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
$title = "Pencarian";
include 'header.php';
?>

<style>
    /* ================= SEARCH PAGE STYLE ================= */
    .search-header {
        padding: 16px;
        background: #ffffff;
    }

    .search-input-container {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        padding: 12px 16px;
        border-radius: 14px;
    }

    .search-input-container input {
        border: none;
        background: transparent;
        width: 100%;
        font-size: 15px;
        outline: none;
    }

    .search-results {
        padding: 16px;
    }

    .search-card {
        background: #ffffff;
        padding: 16px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        margin-bottom: 12px;
    }

    .search-card:hover {
        background: #f0f9ff;
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 60px;
        margin-bottom: 12px;
    }
</style>

<!-- ====================== HEADER SEARCH ======================= -->
<div class="search-header">
    <div class="search-input-container">
        <i class="fas fa-search text-gray-500"></i>
        <input
            type="text"
            id="searchQuery"
            placeholder="Cari laporan, petugas, area..."
            autofocus>
    </div>
</div>

<!-- ====================== HASIL PENCARIAN ======================= -->
<div class="search-results" id="searchResults">
    <div class="empty-state">
        <i class="fa-solid fa-magnifying-glass text-sky-400"></i>
        <p class="text-sm">Mulai ketik untuk mencari data…</p>
    </div>
</div>

<script>
    document.getElementById("searchQuery").addEventListener("keyup", function() {
        let q = this.value.trim();

        if (q.length < 2) {
            document.getElementById("searchResults").innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-magnifying-glass text-sky-400"></i>
                <p class="text-sm">Ketik lebih banyak untuk mencari…</p>
            </div>`;
            return;
        }

        // Fetch data
        fetch("api/search_api.php?q=" + encodeURIComponent(q))
            .then(res => res.json())
            .then(data => {
                let box = document.getElementById("searchResults");
                box.innerHTML = "";

                if (data.length === 0) {
                    box.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-circle-xmark text-red-400"></i>
                        <p class="text-sm">Tidak ditemukan hasil untuk "<b>${q}</b>"</p>
                    </div>`;
                    return;
                }

                data.forEach(item => {
                    box.innerHTML += `
                    <div class="search-card">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-gray-800">${item.form_type}</p>
                                <p class="text-xs text-gray-500">${item.nama_petugas}</p>
                                <p class="text-xs text-gray-500">${item.lokasi}</p>
                            </div>
                            <span class="text-xs text-gray-400">${item.tanggal}</span>
                        </div>

                        <a href="detail.php?id=${item.id}"
                            class="inline-flex items-center mt-2 gap-2 text-xs text-sky-600 bg-sky-50 px-3 py-1.5 rounded-full">
                            <i class="fa-solid fa-eye text-[11px]"></i> Detail
                        </a>
                    </div>`;
                });
            });
    });
</script>

<?php include 'nav_monitoring.php'; ?>
<?php include 'footer.php'; ?>