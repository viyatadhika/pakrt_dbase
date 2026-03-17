<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// ✅ Proteksi halaman: hanya admin & gudang
$allowedRoles = ['admin', 'gudang'];
if (!in_array(strtolower($_SESSION['user']['role'] ?? ''), $allowedRoles)) {
    header("Location: barang_masuk.php");
    exit;
}

$title = "Barang Masuk";
include 'header.php';
include 'config.php';

/* ambil master barang */
$masterBarang = [];
$q = $conn->query("
    SELECT
        b.kode_barang,
        b.nama_barang,
        b.stok,
        COALESCE(s.nama,'') AS satuan,
        COALESCE(k.nama_kategori,'Tanpa Kategori') AS nama_kategori,
        COALESCE(k.icon,'fa-box') AS icon,
        COALESCE(k.color,'gray') AS color
    FROM master_barang b
    LEFT JOIN master_satuan s ON b.satuan_id = s.id
    LEFT JOIN master_kategori_barang k ON b.kategori_id = k.id
    ORDER BY b.nama_barang ASC
");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $masterBarang[] = $row;
    }
}

/* ref kode */
$refKode = "IN-" . rand(1000, 9999);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #fff;
        color: #1e293b;
    }

    .header-container {
        position: sticky;
        top: 0;
        z-index: 50;
        background-color: rgba(255, 255, 255, .9);
        backdrop-filter: blur(8px);
    }

    .card-custom {
        background: white;
        border-radius: 1.5rem;
        border: 1px solid #f1f5f9;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
    }

    .input-with-icon {
        position: relative;
    }

    .input-with-icon i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: .875rem;
    }

    .input-with-icon input,
    .input-with-icon select,
    .input-with-icon textarea {
        padding-left: 2.75rem !important;
    }

    .input-field:focus {
        border-color: #7dd3fc;
        box-shadow: 0 0 0 4px rgba(224, 242, 254, .5);
    }

    .btn-sky {
        background-color: #0284c7;
        transition: all .2s;
    }

    .btn-sky:hover {
        background-color: #0369a1;
        transform: translateY(-1px);
    }

    .btn-sky:active {
        transform: translateY(0);
        scale: .98;
    }

    select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 1rem center;
        background-repeat: no-repeat;
        background-size: 1.25em 1.25em;
    }
</style>

<div class="header-container border-b border-gray-50">
    <header class="px-4 py-4 flex items-center justify-between bg-white">
        <div class="flex items-center gap-4">
            <button onclick="window.history.back()"
                class="w-10 h-10 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <div>
                <h1 class="text-lg font-extrabold text-sky-600 leading-tight">Barang Masuk</h1>
                <p class="text-[12px] text-gray-500">Input barang masuk gudang</p>
            </div>
        </div>
        <div class="flex items-center">
            <p class="text-[10px] sm:text-[11px] font-semibold text-sky-700 bg-sky-50 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full border border-sky-100">
                Ref: <?= htmlspecialchars($refKode) ?>
            </p>
        </div>
    </header>
</div>

<main class="max-w-4xl mx-auto px-4 py-6 pb-24">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        <!-- KIRI -->
        <div class="space-y-6">

            <!-- DATA SURAT JALAN -->
            <div class="card-custom p-6 bg-white border-sky-50 bg-sky-50/5">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 bg-sky-100 text-sky-600 rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-file-invoice text-sm"></i>
                    </div>
                    <h2 class="text-sm font-bold text-gray-800">Data Surat Jalan</h2>
                </div>
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Supplier</label>
                        <div class="input-with-icon">
                            <input type="text" id="inpSupplier" required placeholder="Nama supplier"
                                class="input-field w-full px-4 py-3 bg-white border border-slate-100 rounded-xl outline-none transition text-sm">
                            <i class="fa-solid fa-building"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">No Surat Jalan</label>
                        <div class="input-with-icon">
                            <input type="text" id="inpNoSj" required placeholder="Contoh: SJ-001"
                                class="input-field w-full px-4 py-3 bg-white border border-slate-100 rounded-xl outline-none transition text-sm">
                            <i class="fa-solid fa-hashtag"></i>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1 ml-1">*No SJ yang sama tidak boleh diinput ulang untuk supplier yang sama</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Tanggal Masuk</label>
                        <div class="input-with-icon">
                            <input type="date" id="inpTgl" required
                                class="input-field w-full px-4 py-3 bg-white border border-slate-100 rounded-xl outline-none transition text-sm">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Upload Surat Jalan (PDF/JPG/PNG)</label>
                        <div class="input-with-icon">
                            <input type="file" id="inpFileSj" accept=".pdf,.jpg,.jpeg,.png"
                                class="input-field w-full px-4 py-3 bg-white border border-slate-100 rounded-xl outline-none transition text-sm">
                            <i class="fa-solid fa-upload"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PILIH BARANG -->
            <div class="card-custom p-6 bg-white border-sky-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 bg-sky-600 text-white rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-box-open text-sm"></i>
                    </div>
                    <h2 class="text-sm font-bold text-gray-800">Pilih Barang</h2>
                </div>
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Nama Barang</label>
                        <div class="input-with-icon">
                            <select id="selBarang" onchange="updateStockPreview()"
                                class="input-field w-full px-4 py-3 bg-gray-50 border border-transparent rounded-xl outline-none transition text-sm font-semibold text-slate-700">
                                <option value="">Pilih barang...</option>
                                <?php foreach ($masterBarang as $b): ?>
                                    <option value="<?= htmlspecialchars($b['kode_barang']) ?>"
                                        data-nama="<?= htmlspecialchars($b['nama_barang']) ?>"
                                        data-unit="<?= htmlspecialchars($b['satuan']) ?>"
                                        data-stok="<?= (int)$b['stok'] ?>"
                                        data-kategori="<?= htmlspecialchars($b['nama_kategori']) ?>"
                                        data-icon="<?= htmlspecialchars($b['icon']) ?>"
                                        data-color="<?= htmlspecialchars($b['color']) ?>">
                                        <?= htmlspecialchars($b['nama_barang']) ?> (<?= htmlspecialchars($b['kode_barang']) ?> • stok: <?= (int)$b['stok'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-search"></i>
                        </div>
                        <p id="stokHint" class="hidden text-[10px] mt-2 ml-1 text-sky-600 font-bold uppercase tracking-tight">
                            Stok Saat Ini: <span id="stokVal">0</span>
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Jumlah Masuk</label>
                        <div class="input-with-icon">
                            <input type="number" id="inpQty" placeholder="0"
                                class="input-field w-full px-4 py-3 bg-sky-50 border-sky-200 rounded-xl outline-none transition text-sm font-bold text-sky-700">
                            <i class="fa-solid fa-plus-circle"></i>
                        </div>
                    </div>
                    <button type="button" onclick="addItem()"
                        class="btn-sky w-full h-[46px] text-white rounded-xl font-bold text-xs shadow-lg shadow-sky-100">
                        Tambahkan ke Daftar
                    </button>
                </div>
            </div>
        </div>

        <!-- KANAN -->
        <div class="space-y-6">
            <div class="card-custom min-h-[400px] flex flex-col bg-white">
                <div class="p-6 border-b border-gray-50 flex items-center justify-between">
                    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Daftar Barang Masuk</h2>
                    <span id="itemCount" class="text-[10px] bg-sky-50 text-sky-600 font-bold px-3 py-1 rounded-full">0 Item</span>
                </div>
                <div id="cartList" class="flex-1 p-4 sm:p-6 space-y-3 overflow-y-auto max-h-[500px]">
                    <div class="h-full flex flex-col items-center justify-center text-gray-300 py-10 text-center">
                        <i class="fa-solid fa-cart-flatbed text-4xl mb-3 opacity-20"></i>
                        <p class="text-xs font-medium px-6">Belum ada barang dipilih.</p>
                    </div>
                </div>
                <div id="cartFooter" class="hidden p-6 bg-slate-50/50 rounded-b-[1.5rem] border-t border-gray-100">
                    <button type="button" id="btnSimpan" onclick="simpanTransaksi()"
                        class="btn-sky w-full py-4 text-white rounded-xl font-bold text-sm shadow-xl shadow-sky-100">
                        Konfirmasi Barang Masuk
                    </button>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    const state = {
        items: [],
        ref: <?= json_encode($refKode) ?>
    };

    window.onload = () => {
        document.getElementById('inpTgl').value = new Date().toISOString().split('T')[0];
    };

    function updateStockPreview() {
        const sel = document.getElementById('selBarang');
        const hint = document.getElementById('stokHint');
        const val = document.getElementById('stokVal');
        if (sel.value) {
            const opt = sel.options[sel.selectedIndex];
            hint.classList.remove('hidden');
            val.innerText = opt.getAttribute('data-stok') + ' ' + opt.getAttribute('data-unit');
        } else {
            hint.classList.add('hidden');
        }
    }

    function addItem() {
        const sel = document.getElementById('selBarang');
        const qtyInput = document.getElementById('inpQty');
        const qty = parseInt(qtyInput.value);

        if (!sel.value) {
            alert("Pilih barang dulu");
            return;
        }
        if (isNaN(qty) || qty <= 0) {
            alert("Qty harus lebih dari 0");
            return;
        }

        const opt = sel.options[sel.selectedIndex];
        const kodeBarang = sel.value;
        const existingItem = state.items.find(item => item.kode === kodeBarang);

        if (existingItem) {
            existingItem.qty += qty;
        } else {
            state.items.push({
                kode: kodeBarang,
                nama: opt.getAttribute('data-nama'),
                qty: qty,
                unit: opt.getAttribute('data-unit'),
                kategori: opt.getAttribute('data-kategori') || 'Tanpa Kategori',
                icon: opt.getAttribute('data-icon') || 'fa-box',
                color: opt.getAttribute('data-color') || 'gray'
            });
        }

        sel.value = '';
        qtyInput.value = '';
        document.getElementById('stokHint').classList.add('hidden');
        renderCart();
    }

    function renderCart() {
        const list = document.getElementById('cartList');
        const footer = document.getElementById('cartFooter');

        if (state.items.length === 0) {
            list.innerHTML = `
                <div class="h-full flex flex-col items-center justify-center text-gray-300 py-10 text-center">
                    <i class="fa-solid fa-cart-flatbed text-4xl mb-3 opacity-20"></i>
                    <p class="text-xs font-medium px-6">Belum ada barang dipilih.</p>
                </div>`;
        } else {
            list.innerHTML = state.items.map((item, index) => `
                <div class="bg-white p-4 rounded-xl border border-gray-100 flex flex-col gap-2 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-sky-400"></div>
                    <div class="flex justify-between items-start pl-2">
                        <div class="min-w-0 text-left">
                            <h4 class="text-xs font-bold text-gray-800 truncate">${item.nama}</h4>
                            <p class="text-[9px] text-gray-400 font-mono uppercase">${item.kode}</p>
                            <p class="text-[10px] font-bold text-sky-600 mt-1">
                                <i class="fa-solid ${item.icon} mr-1"></i> ${item.kategori}
                            </p>
                        </div>
                        <button onclick="state.items.splice(${index}, 1); renderCart();"
                            class="text-gray-300 hover:text-red-500 transition-colors">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>
                    <div class="flex justify-between items-center bg-slate-50 p-2 rounded-lg mt-1">
                        <div class="pl-2">
                            <p class="text-[8px] text-gray-400 font-bold uppercase tracking-widest">Jumlah Masuk</p>
                            <p class="text-sm font-black text-sky-700">${item.qty}
                                <span class="text-[10px] font-bold text-gray-400 ml-1">${item.unit}</span>
                            </p>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        footer.classList.toggle('hidden', state.items.length === 0);
        document.getElementById('itemCount').innerText = state.items.length + ' Item';
    }

    async function simpanTransaksi() {
        const btn = document.getElementById('btnSimpan');
        const supplier = document.getElementById('inpSupplier').value.trim();
        const noSj = document.getElementById('inpNoSj').value.trim();
        const tanggal = document.getElementById('inpTgl').value;
        const fileSj = document.getElementById('inpFileSj').files[0] || null;

        if (!supplier) {
            alert("Supplier wajib diisi");
            return;
        }
        if (!noSj) {
            alert("No Surat Jalan wajib diisi");
            return;
        }
        if (!tanggal) {
            alert("Tanggal wajib diisi");
            return;
        }
        if (!state.items.length) {
            alert("Barang belum dipilih");
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin mr-2"></i> Memproses...';

        try {
            const fd = new FormData();
            fd.append("ref", state.ref);
            fd.append("tanggal", tanggal);
            fd.append("supplier", supplier);
            fd.append("no_sj", noSj);
            fd.append("items", JSON.stringify(state.items));
            if (fileSj) fd.append("file_sj", fileSj);

            const res = await fetch("barang_masuk_simpan.php", {
                method: "POST",
                body: fd
            });
            const json = await res.json();

            if (json.status !== "ok") {
                alert(json.message || "Gagal menyimpan");
                btn.disabled = false;
                btn.innerHTML = "Konfirmasi Barang Masuk";
                return;
            }

            alert("Barang masuk berhasil disimpan ✅");
            window.location.href = "barang_masuk.php";

        } catch (e) {
            alert("Koneksi gagal / Response bukan JSON ❌");
            btn.disabled = false;
            btn.innerHTML = "Konfirmasi Barang Masuk";
        }
    }
</script>

<?php include 'footer.php'; ?>