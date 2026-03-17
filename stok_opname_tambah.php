<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// ✅ Proteksi halaman: hanya admin & gudang
$allowedRoles = ['admin', 'gudang'];
if (!in_array(strtolower($_SESSION['user']['role'] ?? ''), $allowedRoles)) {
    header("Location: stok_opname.php");
    exit;
}

$title = "Tambah Stok Opname";
include 'header.php';
include 'config.php';

// ambil master barang + kategori + satuan
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

// ref kode
$refKode = "OPN-" . rand(1000, 9999);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: #ffffff;
        color: #1e293b;
    }

    .header-container {
        position: sticky;
        top: 0;
        z-index: 50;
        background-color: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(8px);
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
        font-size: 0.875rem;
    }

    .input-with-icon input,
    .input-with-icon select,
    .input-with-icon textarea {
        padding-left: 2.75rem !important;
    }

    .card-custom {
        background: white;
        border-radius: 1.5rem;
        border: 1px solid #f1f5f9;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }

    .input-field:focus {
        border-color: #c4b5fd;
        box-shadow: 0 0 0 4px rgba(237, 233, 254, 0.6);
    }

    .btn-purple {
        background-color: #7c3aed;
        transition: all 0.2s;
    }

    .btn-purple:hover {
        background-color: #6d28d9;
        transform: translateY(-1px);
    }

    .btn-purple:active {
        transform: translateY(0);
        scale: 0.98;
    }
</style>

<div class="header-container border-b border-gray-50">
    <header class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <button onclick="window.history.back()" class="w-10 h-10 flex items-center justify-center rounded-full bg-purple-50 text-purple-600 hover:bg-purple-100 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <div>
                <h1 class="text-lg font-extrabold text-purple-600 leading-tight">Tambah Stok Opname</h1>
                <p class="text-[12px] text-gray-500">Input pengecekan stok fisik</p>
            </div>
        </div>
        <div class="flex items-center">
            <p class="text-[10px] sm:text-[11px] font-semibold text-purple-700 bg-purple-50 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full border border-purple-100">
                Ref: <?= htmlspecialchars($refKode) ?>
            </p>
        </div>
    </header>
</div>

<main class="max-w-4xl mx-auto px-4 py-6 pb-24">
    <form id="formOpname" onsubmit="event.preventDefault();">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

            <!-- KIRI -->
            <div class="space-y-6">

                <div class="card-custom p-6 bg-white border-purple-50 bg-purple-50/5">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-clipboard-check text-sm"></i>
                        </div>
                        <h2 class="text-sm font-bold text-gray-800">Data Opname</h2>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Lokasi</label>
                            <div class="input-with-icon">
                                <input type="text" id="inpLokasi" placeholder="Gudang / Ruangan"
                                    class="input-field w-full px-4 py-3 bg-white border border-slate-100 rounded-xl outline-none transition text-sm">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Tanggal Opname</label>
                            <div class="input-with-icon">
                                <input type="date" id="inpTgl" required
                                    class="input-field w-full px-4 py-3 bg-white border border-slate-100 rounded-xl outline-none transition text-sm">
                                <i class="fa-solid fa-calendar-day"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Keterangan</label>
                            <div class="input-with-icon">
                                <textarea id="inpKet" rows="2" placeholder="Catatan opname..."
                                    class="input-field w-full px-4 py-3 bg-gray-50 border border-transparent rounded-xl outline-none transition text-sm"></textarea>
                                <i class="fa-solid fa-comment-dots"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PILIH BARANG -->
                <div class="card-custom p-6 bg-white border-purple-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 bg-purple-600 text-white rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-boxes-stacked text-sm"></i>
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
                                            <?= htmlspecialchars($b['nama_barang']) ?> (<?= htmlspecialchars($b['kode_barang']) ?> • stok sistem: <?= (int)$b['stok'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fa-solid fa-search"></i>
                            </div>
                            <p id="stokHint" class="hidden text-[10px] mt-2 ml-1 text-purple-600 font-bold uppercase tracking-tight">
                                Stok Sistem: <span id="stokVal">0</span>
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Stok Fisik (hasil hitung)</label>
                            <div class="input-with-icon">
                                <input type="number" id="inpFisik" placeholder="0"
                                    class="input-field w-full px-4 py-3 bg-purple-50 border-purple-200 rounded-xl outline-none transition text-sm font-bold text-purple-700">
                                <i class="fa-solid fa-pen"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5 ml-1">Catatan Item</label>
                            <div class="input-with-icon">
                                <textarea id="inpNote" rows="2" placeholder="Catatan item..."
                                    class="input-field w-full px-4 py-3 bg-gray-50 border border-transparent rounded-xl outline-none transition text-sm"></textarea>
                                <i class="fa-solid fa-note-sticky"></i>
                            </div>
                        </div>

                        <button type="button" onclick="addItem()"
                            class="btn-purple w-full h-[46px] text-white rounded-xl font-bold text-xs shadow-lg shadow-purple-100">
                            Tambahkan ke Daftar
                        </button>
                    </div>
                </div>
            </div>

            <!-- KANAN -->
            <div class="space-y-6">
                <div class="card-custom min-h-[400px] flex flex-col bg-white">
                    <div class="p-6 border-b border-gray-50 flex items-center justify-between">
                        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Daftar Item Opname</h2>
                        <span id="itemCount" class="text-[10px] bg-purple-50 text-purple-600 font-bold px-3 py-1 rounded-full">0 Item</span>
                    </div>

                    <div id="cartList" class="flex-1 p-4 sm:p-6 space-y-3 custom-scrollbar overflow-y-auto max-h-[520px]">
                        <div class="h-full flex flex-col items-center justify-center text-gray-300 py-10 text-center">
                            <i class="fa-solid fa-clipboard-list text-4xl mb-3 opacity-20"></i>
                            <p class="text-xs font-medium px-6">Belum ada barang dipilih.</p>
                        </div>
                    </div>

                    <div id="cartFooter" class="hidden p-6 bg-slate-50/50 rounded-b-[1.5rem] border-t border-gray-100">
                        <button type="button" id="btnSimpan" onclick="simpanTransaksi()"
                            class="btn-purple w-full py-4 text-white rounded-xl font-bold text-sm shadow-xl shadow-purple-100">
                            Simpan Stok Opname
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>
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
        const fisikInput = document.getElementById('inpFisik');
        const fisik = parseInt(fisikInput.value);
        const note = document.getElementById('inpNote').value;

        if (!sel.value) {
            alert("Pilih barang dulu");
            return;
        }
        if (isNaN(fisik) || fisik < 0) {
            alert("Stok fisik wajib angka (>=0)");
            return;
        }

        const opt = sel.options[sel.selectedIndex];
        const stokSistem = parseInt(opt.getAttribute('data-stok')) || 0;
        const kodeBarang = sel.value;
        const selisih = fisik - stokSistem;

        const existingItem = state.items.find(item => item.kode === kodeBarang);

        if (existingItem) {
            existingItem.fisik = fisik;
            existingItem.selisih = selisih;
            existingItem.note = note || existingItem.note;
        } else {
            state.items.push({
                kode: kodeBarang,
                nama: opt.getAttribute('data-nama'),
                unit: opt.getAttribute('data-unit'),
                sistem: stokSistem,
                fisik: fisik,
                selisih: selisih,
                note: note || '-',
                kategori: opt.getAttribute('data-kategori') || 'Tanpa Kategori',
                icon: opt.getAttribute('data-icon') || 'fa-box',
                color: opt.getAttribute('data-color') || 'gray'
            });
        }

        sel.value = '';
        fisikInput.value = '';
        document.getElementById('inpNote').value = '';
        document.getElementById('stokHint').classList.add('hidden');
        renderCart();
    }

    function renderCart() {
        const list = document.getElementById('cartList');
        const footer = document.getElementById('cartFooter');

        if (state.items.length === 0) {
            list.innerHTML = `
                <div class="h-full flex flex-col items-center justify-center text-gray-300 py-10 text-center">
                    <i class="fa-solid fa-clipboard-list text-4xl mb-3 opacity-20"></i>
                    <p class="text-xs font-medium px-6">Belum ada barang dipilih.</p>
                </div>`;
        } else {
            list.innerHTML = state.items.map((item, index) => {
                let selisihBadge = `<span class="text-[10px] font-bold text-gray-500">0</span>`;
                if (item.selisih > 0) selisihBadge = `<span class="text-[10px] font-extrabold text-green-600">+${item.selisih}</span>`;
                if (item.selisih < 0) selisihBadge = `<span class="text-[10px] font-extrabold text-red-600">${item.selisih}</span>`;

                return `
                <div class="bg-white p-4 rounded-xl border border-gray-100 flex flex-col gap-2 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-purple-400"></div>
                    <div class="flex justify-between items-start pl-2">
                        <div class="min-w-0 text-left">
                            <h4 class="text-xs font-bold text-gray-800 truncate">${item.nama}</h4>
                            <p class="text-[9px] text-gray-400 font-mono uppercase">${item.kode}</p>
                            <p class="text-[10px] font-bold text-purple-600 mt-1">
                                <i class="fa-solid ${item.icon} mr-1"></i> ${item.kategori}
                            </p>
                        </div>
                        <button onclick="state.items.splice(${index}, 1); renderCart();" class="text-gray-300 hover:text-red-500 transition-colors">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-3 gap-2 bg-slate-50 p-2 rounded-lg mt-1">
                        <div class="pl-2">
                            <p class="text-[8px] text-gray-400 font-bold uppercase tracking-widest">Sistem</p>
                            <p class="text-sm font-black text-gray-700">${item.sistem}</p>
                        </div>
                        <div class="pl-2">
                            <p class="text-[8px] text-gray-400 font-bold uppercase tracking-widest">Fisik</p>
                            <p class="text-sm font-black text-gray-700">${item.fisik}</p>
                        </div>
                        <div class="pl-2">
                            <p class="text-[8px] text-gray-400 font-bold uppercase tracking-widest">Selisih</p>
                            <p class="text-sm font-black">${selisihBadge}</p>
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-400 italic truncate px-2">"${item.note}"</p>
                </div>`;
            }).join('');
        }

        footer.classList.toggle('hidden', state.items.length === 0);
        document.getElementById('itemCount').innerText = state.items.length + ' Item';
    }

    async function simpanTransaksi() {
        const btn = document.getElementById('btnSimpan');
        const lokasi = document.getElementById('inpLokasi').value.trim();
        const tanggal = document.getElementById('inpTgl').value;
        const ket = document.getElementById('inpKet').value.trim();

        if (!tanggal) {
            alert('Tanggal wajib diisi');
            return;
        }
        if (!state.items.length) {
            alert('Belum ada item opname');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin mr-2"></i> Memproses...';

        try {
            const payload = {
                ref_kode: state.ref,
                tanggal: tanggal,
                lokasi: lokasi,
                keterangan: ket,
                items: state.items.map(x => ({
                    kode_barang: x.kode,
                    nama_barang: x.nama,
                    stok_sistem: x.sistem,
                    stok_fisik: x.fisik,
                    selisih: x.selisih,
                    satuan: x.unit,
                    catatan: x.note
                }))
            };

            const res = await fetch('stok_opname_simpan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const json = await res.json();

            if (json.status !== 'ok') {
                alert(json.message || 'Gagal menyimpan');
                btn.disabled = false;
                btn.innerHTML = 'Simpan Stok Opname';
                return;
            }

            alert('Stok opname berhasil disimpan');
            window.location.href = 'stok_opname.php';

        } catch (e) {
            alert('Koneksi gagal');
            btn.disabled = false;
            btn.innerHTML = 'Simpan Stok Opname';
        }
    }
</script>

<?php include 'footer.php'; ?>