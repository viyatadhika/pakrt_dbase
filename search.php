<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

$title = "Pencarian";
include 'header.php';
?>

<style>
    /* ================= SEARCH PAGE ================= */

    .search-header {
        padding: 16px;
        background: #ffffff;
    }

    .search-input-container {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
    }

    .search-input-container input {
        width: 100%;
        font-size: 15px;
        border: none;
        outline: none;
        background: transparent;
    }

    /* ================= RESULTS ================= */

    .search-results {
        padding: 16px;
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

<!-- ================= HEADER SEARCH (SAMA DENGAN BERANDA) ================= -->
<div class="search-header">
    <div class="search-box">

        <!-- ICON -->
        <i class="fa-solid fa-magnifying-glass"></i>

        <!-- TEKS ANIMASI -->
        <span id="searchHint" class="search-hint">
            Cari laporan hari ini
        </span>

        <!-- INPUT -->
        <input
            type="text"
            id="searchQuery"
            class="search-input"
            autocomplete="off"
            autofocus>
    </div>
</div>


<!-- ================= SEARCH RESULTS ================= -->
<div id="searchResults" class="search-results">
    <div class="empty-state">
        <i class="fa-solid fa-magnifying-glass text-sky-400"></i>
        <p class="text-sm">Mulai ketik untuk mencari data…</p>
    </div>
</div>

<script>
    const searchInput = document.getElementById('searchQuery');
    const resultsBox = document.getElementById('searchResults');

    /* ===== Pretty Form Mapping ===== */
    const prettyMap = {
        'piketob': 'Piket OB',
        'piket_ob': 'Piket OB',
        'piket ob': 'Piket OB',
        'plotingjaga': 'Ploting Jaga',
        'general_cleaning': 'General Cleaning',
        'ptsp': 'PTSP',
    };

    const formatForm = value => {
        const key = value.toLowerCase().trim();
        return prettyMap[key] ?? value;
    };

    const emptyState = (icon, text) => `
    <div class="empty-state">
        <i class="fa-solid ${icon}"></i>
        <p class="text-sm">${text}</p>
    </div>
`;

    searchInput.addEventListener('keyup', function() {
        const query = this.value.trim();

        if (query.length < 2) {
            resultsBox.innerHTML = emptyState(
                'fa-magnifying-glass text-sky-400',
                'Ketik lebih banyak untuk mencari…'
            );
            return;
        }

        fetch(`api/search_api.php?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                resultsBox.innerHTML = '';

                if (!data.length) {
                    resultsBox.innerHTML = emptyState(
                        'fa-circle-xmark text-red-400',
                        `Tidak ditemukan hasil untuk "<b>${query}</b>"`
                    );
                    return;
                }

                data.forEach(item => {

                    const nomorRumah = item.nomor_rumah ?
                        `<p class="text-xs text-gray-500 mt-1">
                        ${item.area_gedung ? 'Kamar No: ' : 'No. Rumah: '}
                        ${item.nomor_rumah}
                      </p>` :
                        '';

                    resultsBox.innerHTML += `
                    <div class="group bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 p-4 mb-3">

                        <div class="flex justify-between items-start mb-2">

                            <div class="flex items-start gap-2">
                                <div class="w-9 h-9 flex items-center justify-center bg-sky-100 text-sky-600 rounded-xl mt-0.5">
                                    <i class="fa-solid fa-building"></i>
                                </div>

                                <div>
                                    <p class="font-semibold text-gray-800">
                                        ${formatForm(item.form_type)}
                                    </p>
                                    <p class="text-xs text-gray-500">${item.lokasi}</p>
                                    ${nomorRumah}
                                </div>
                            </div>

                            <div class="flex flex-col items-end text-right min-w-[90px]">
                                <span class="text-xs text-gray-400">${item.tanggal}</span>
                                <span class="text-[11px] text-gray-600 flex items-center gap-1 mt-1">
                                    <i class="fa-solid fa-user text-[10px]"></i>
                                    ${item.nama_petugas}
                                </span>
                            </div>
                        </div>

                        <a href="detail.php?id=${item.id}"
                           class="inline-flex items-center gap-2 text-xs text-sky-600 bg-sky-50 px-3 py-1.5 rounded-full">
                            <i class="fa-solid fa-eye text-[11px]"></i>
                            Detail
                        </a>
                    </div>
                `;
                });
            });
    });
</script>


<?php include 'nav_monitoring.php'; ?>
<?php include 'footer.php'; ?>