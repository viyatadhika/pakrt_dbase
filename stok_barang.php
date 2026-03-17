<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Stok Barang";
include 'header.php';
include 'config.php';

// ✅ Hanya admin & gudang yang bisa tambah/edit/hapus
$canEdit = in_array(strtolower($_SESSION['user']['role'] ?? ''), ['admin', 'gudang']);

/* ================= QUERY DATA ================= */
$sql = "
SELECT
    b.id, b.kode_barang, b.nama_barang, b.stok,
    b.satuan_id, b.kategori_id,
    s.nama AS satuan,
    k.nama_kategori, k.icon, k.color
FROM master_barang b
JOIN master_kategori_barang k ON b.kategori_id = k.id
LEFT JOIN master_satuan s ON b.satuan_id = s.id
ORDER BY k.nama_kategori, b.nama_barang
";
$barangResult = $conn->query($sql);
if (!$barangResult) die('Query error: ' . $conn->error);
$totalData = $barangResult->num_rows;

/* ================= COLOR MAP ================= */
$colorMap = [
    'sky'     => ['bar' => 'bg-sky-500',     'icon' => 'bg-sky-50 text-sky-600',       'kode' => 'text-sky-500'],
    'purple'  => ['bar' => 'bg-purple-500',  'icon' => 'bg-purple-50 text-purple-600', 'kode' => 'text-purple-500'],
    'amber'   => ['bar' => 'bg-amber-500',   'icon' => 'bg-amber-50 text-amber-600',   'kode' => 'text-amber-500'],
    'teal'    => ['bar' => 'bg-teal-500',    'icon' => 'bg-teal-50 text-teal-600',     'kode' => 'text-teal-500'],
    'emerald' => ['bar' => 'bg-emerald-500', 'icon' => 'bg-emerald-50 text-emerald-600', 'kode' => 'text-emerald-500'],
];
?>

<style>
    .header-container {
        position: sticky;
        top: 0;
        z-index: 50;
        background: #fff
    }

    .kategori-tab.active {
        background: #0284c7;
        color: #fff
    }
</style>

<!-- Header -->
<div class="header-container">
    <header class="px-4 py-4 flex items-center justify-between bg-white">
        <div class="flex items-center gap-4">
            <a href="javascript:window.history.back()"
                class="w-10 h-10 flex items-center justify-center rounded-full bg-sky-50 text-sky-600 hover:bg-sky-100 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-lg font-extrabold text-sky-600 leading-tight">Stok Barang</h1>
                <p class="text-[11px] text-gray-500 font-medium">Lihat dan kelola stok barang</p>
            </div>
        </div>
        <a href="stok_barang_export.php"
            class="w-10 h-10 flex items-center justify-center text-sky-600 hover:bg-sky-50 rounded-full transition">
            <i class="fa-solid fa-download text-lg"></i>
        </a>
    </header>

    <!-- Search -->
    <div class="px-4 pt-2 pb-2 bg-white">
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
            </div>
            <input type="text" id="mainSearch" placeholder="Cari nama atau kode barang..."
                class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-sm focus:bg-white focus:border-sky-300 focus:ring-4 focus:ring-sky-50 outline-none transition-all"
                onkeyup="cariBarang(this.value)">
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex gap-2 px-4 py-3 overflow-x-auto scrollbar-hide bg-white" id="tabContainer">
        <button type="button"
            class="kategori-tab active shrink-0 px-5 py-2 rounded-2xl text-xs font-semibold border border-transparent transition"
            onclick="filterKategori('Semua', this)">Semua</button>
        <?php
        $qKategori = $conn->query("SELECT nama_kategori FROM master_kategori_barang WHERE status='aktif' ORDER BY nama_kategori ASC");
        while ($kat = $qKategori->fetch_assoc()):
        ?>
            <button type="button"
                class="kategori-tab bg-gray-50 text-gray-600 border border-transparent shrink-0 px-5 py-2 rounded-2xl text-xs font-semibold transition"
                onclick="filterKategori('<?= htmlspecialchars($kat['nama_kategori']) ?>', this)">
                <?= htmlspecialchars($kat['nama_kategori']) ?>
            </button>
        <?php endwhile; ?>
    </div>
</div>

<!-- Main List -->
<main id="listBarang" class="px-4 py-4 mb-28 bg-white">
    <div id="emptyState" class="<?= ($totalData == 0 ? 'flex' : 'hidden') ?> flex-col items-center justify-center py-20 px-6 text-center">
        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-6">
            <i class="fa-solid fa-box-open text-3xl text-gray-200"></i>
        </div>
        <h3 class="text-gray-800 font-bold text-sm">Data barang belum tersedia</h3>
        <p class="text-gray-400 text-xs mt-2 leading-relaxed">Silakan tambah barang baru atau ubah kriteria pencarian Anda.</p>
        <button type="button" onclick="resetTampilan()" class="mt-8 text-sky-600 font-bold text-sm hover:underline">Tampilkan Semua</button>
    </div>

    <?php if ($totalData > 0): ?>
        <div id="sectionsWrapper" class="space-y-8">
            <?php
            $currentKategori = '';
            while ($row = $barangResult->fetch_assoc()):
                $warna = $colorMap[$row['color']] ?? $colorMap['sky'];
                if ($currentKategori !== $row['nama_kategori']) {
                    if ($currentKategori !== '') echo '</div></section>';
                    $currentKategori = $row['nama_kategori'];
            ?>
                    <section class="kategori-section" data-kategori="<?= htmlspecialchars($currentKategori) ?>">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="h-5 w-1.5 <?= $warna['bar'] ?> rounded-full"></div>
                            <h2 class="font-bold text-gray-800 text-sm kategoriTitle"><?= htmlspecialchars($currentKategori) ?></h2>
                        </div>
                        <div class="space-y-3 kategoriList">
                        <?php } ?>

                        <!-- CARD -->
                        <div class="item-card bg-white rounded-3xl p-4 flex justify-between items-center border shadow-sm cursor-pointer"
                            data-id="<?= (int)$row['id'] ?>"
                            data-name="<?= strtolower($row['nama_barang'] . ' ' . $row['kode_barang']) ?>"
                            data-kode="<?= strtolower($row['kode_barang']) ?>"
                            data-satuan-id="<?= (int)$row['satuan_id'] ?>"
                            data-kategori-id="<?= (int)$row['kategori_id'] ?>"
                            data-nama="<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES) ?>"
                            data-kode-asli="<?= htmlspecialchars($row['kode_barang'], ENT_QUOTES) ?>"
                            data-stok="<?= (int)$row['stok'] ?>"
                            data-satuan="<?= htmlspecialchars($row['satuan'] ?? '', ENT_QUOTES) ?>"
                            onclick="openDetailFromCard(this)">

                            <div class="flex gap-4 items-center min-w-0">
                                <div class="w-12 h-12 rounded-2xl <?= $warna['icon'] ?> flex items-center justify-center shrink-0 iconWrap">
                                    <i class="fa-solid <?= htmlspecialchars($row['icon']) ?> text-xl iconEl"></i>
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[10px] font-bold <?= $warna['kode'] ?> kodeText"><?= htmlspecialchars($row['kode_barang']) ?></span>
                                    <h3 class="font-bold text-gray-800 text-sm truncate namaText"><?= htmlspecialchars($row['nama_barang']) ?></h3>
                                    <p class="text-xs text-gray-500">Stok: <span class="font-bold text-green-600 stokText"><?= (int)$row['stok'] ?> <?= htmlspecialchars($row['satuan']) ?></span></p>
                                </div>
                            </div>

                            <button type="button"
                                class="w-10 h-10 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-400"
                                onclick="event.stopPropagation(); openDetailFromCard(this.closest('.item-card'));">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </button>
                        </div>

                    <?php endwhile; ?>
                        </div>
                    </section>
        </div>
    <?php endif; ?>
</main>

<!-- MODAL STOK (TAMBAH/DETAIL/EDIT) -->
<div id="stokModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeStokModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl">

            <div class="flex items-center justify-between mb-4">
                <div>
                    <p id="sheetTitle" class="text-sm font-extrabold text-gray-800">Detail Barang</p>
                    <p id="sheetSubTitle" class="text-[11px] text-gray-500">Lihat data barang</p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- ✅ Tombol edit hanya untuk admin & gudang -->
                    <?php if ($canEdit): ?>
                        <button type="button" id="btnEditTrigger" onclick="enableEditMode()"
                            class="w-9 h-9 rounded-full bg-sky-50 text-sky-600 hidden">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    <?php endif; ?>
                    <button type="button" onclick="closeStokModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <form id="mainForm" onsubmit="event.preventDefault(); handleSimpan();" class="space-y-3">
                <input type="hidden" id="inpId">

                <div>
                    <label class="text-xs font-bold text-gray-600">Kategori</label>
                    <select id="inpKategori"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300"
                        onchange="generateKodeByKategori(this.value)">
                        <option value="">-- Pilih Kategori --</option>
                        <?php
                        $qKat2 = $conn->query("SELECT id, nama_kategori FROM master_kategori_barang WHERE status='aktif' ORDER BY nama_kategori ASC");
                        while ($kat = $qKat2->fetch_assoc()):
                        ?>
                            <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Kode Barang</label>
                    <input type="text" id="inpKode"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-100 border border-gray-200 text-gray-500 font-bold text-sm"
                        readonly>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Nama Barang</label>
                    <input type="text" id="inpNama"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300"
                        placeholder="Nama barang...">
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Satuan</label>
                    <select id="inpSatuan"
                        class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                        <option value="">-- Pilih Satuan --</option>
                        <?php
                        $qSatuan2 = $conn->query("SELECT id, nama FROM master_satuan ORDER BY nama ASC");
                        while ($s = $qSatuan2->fetch_assoc()):
                        ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- ✅ Tombol simpan & hapus hanya untuk admin & gudang -->
                <?php if ($canEdit): ?>
                    <button id="btnSubmit" type="submit"
                        class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm hidden">
                        Simpan Data
                    </button>
                    <button id="btnHapus" type="button" onclick="handleHapus()"
                        class="w-full py-3 rounded-2xl bg-red-50 text-red-600 font-extrabold text-sm hidden">
                        <i class="fa-solid fa-trash-can mr-2"></i> Hapus Barang
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- ✅ FAB Tambah — hanya untuk admin & gudang -->
<?php if ($canEdit): ?>
    <button type="button" onclick="openStokModalTambah()"
        class="fixed bottom-8 right-8 w-12 h-12 bg-sky-600 text-white rounded-full shadow-lg shadow-sky-100 flex items-center justify-center z-[90] active:scale-90 transition-all">
        <i class="fa-solid fa-plus text-lg"></i>
    </button>
<?php endif; ?>

<script>
    // ✅ Kirim status hak akses dari PHP ke JavaScript
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;

    const emptyState = document.getElementById('emptyState');
    const sectionsWrapper = document.getElementById('sectionsWrapper');
    const mainSearch = document.getElementById('mainSearch');
    const tabContainer = document.getElementById('tabContainer');

    let currentFilter = 'Semua';
    let sheetMode = 'tambah';
    let currentDetailData = null;

    // =========================
    // FILTER & SEARCH
    // =========================
    function resetTampilan() {
        mainSearch.value = '';
        tabContainer.scrollTo({
            left: 0,
            behavior: 'smooth'
        });
        const allTab = document.querySelector('.kategori-tab[onclick*="Semua"]');
        filterKategori('Semua', allTab);
    }

    function filterKategori(kat, btn) {
        currentFilter = kat;
        document.querySelectorAll('.kategori-tab').forEach(t => {
            t.classList.remove('active');
            t.classList.add('bg-gray-50', 'text-gray-600');
        });
        btn.classList.add('active');
        btn.classList.remove('bg-gray-50', 'text-gray-600');
        updateDisplay(mainSearch.value);
    }

    function updateDisplay(searchQuery = '') {
        const sections = document.querySelectorAll('.kategori-section');
        let totalMatch = 0;

        sections.forEach(sec => {
            const isMatchKat = (currentFilter === 'Semua' || sec.dataset.kategori === currentFilter);
            const items = sec.querySelectorAll('.item-card');
            let visibleCount = 0;

            items.forEach(item => {
                const nameAttr = item.getAttribute('data-name') || '';
                const matchSearch = nameAttr.includes(searchQuery.toLowerCase());
                if (isMatchKat && matchSearch) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else item.style.display = 'none';
            });

            sec.style.display = (visibleCount > 0) ? 'block' : 'none';
            totalMatch += visibleCount;
        });

        if (totalMatch === 0) {
            emptyState.classList.remove('hidden');
            emptyState.classList.add('flex');
            if (sectionsWrapper) sectionsWrapper.style.display = 'none';
        } else {
            emptyState.classList.add('hidden');
            emptyState.classList.remove('flex');
            if (sectionsWrapper) sectionsWrapper.style.display = 'block';
        }
    }

    function cariBarang(val) {
        updateDisplay(val);
    }

    // =========================
    // MODAL STOK
    // =========================
    function openStokModalTambah() {
        if (!CAN_EDIT) return;
        currentDetailData = null;
        sheetMode = 'tambah';

        document.getElementById('sheetTitle').innerText = 'Tambah Barang';
        document.getElementById('sheetSubTitle').innerText = 'Isi data barang baru';
        document.getElementById('inpId').value = '';
        document.getElementById('inpNama').value = '';
        document.getElementById('inpKategori').value = '';
        document.getElementById('inpSatuan').value = '';
        document.getElementById('inpKode').value = '';

        toggleInputs(false);

        if (CAN_EDIT) {
            document.getElementById('btnEditTrigger')?.classList.add('hidden');
            document.getElementById('btnHapus')?.classList.add('hidden');
            document.getElementById('btnSubmit')?.classList.remove('hidden');
        }

        document.getElementById('stokModal').classList.remove('hidden');
    }

    function openStokModalDetail(data) {
        sheetMode = 'detail';
        currentDetailData = data;

        document.getElementById('sheetTitle').innerText = 'Detail Barang';
        document.getElementById('sheetSubTitle').innerText = 'Lihat data barang';
        document.getElementById('inpId').value = data.id;
        document.getElementById('inpNama').value = data.nama;
        document.getElementById('inpKategori').value = data.kategori_id;
        document.getElementById('inpSatuan').value = data.satuan_id;
        document.getElementById('inpKode').value = data.kode;

        toggleInputs(true);

        // ✅ Tombol edit hanya untuk admin & gudang
        if (CAN_EDIT) {
            document.getElementById('btnEditTrigger')?.classList.remove('hidden');
            document.getElementById('btnHapus')?.classList.add('hidden');
            document.getElementById('btnSubmit')?.classList.add('hidden');
        }

        document.getElementById('stokModal').classList.remove('hidden');
    }

    function closeStokModal() {
        document.getElementById('stokModal').classList.add('hidden');
    }

    function toggleInputs(disabled) {
        ['inpNama', 'inpSatuan', 'inpKategori'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = disabled;
        });
    }

    function openDetailFromCard(card) {
        const data = {
            id: card.getAttribute('data-id'),
            nama: card.getAttribute('data-nama'),
            kode: card.getAttribute('data-kode-asli'),
            satuan_id: card.getAttribute('data-satuan-id'),
            kategori_id: card.getAttribute('data-kategori-id'),
        };
        openStokModalDetail(data);
    }

    function enableEditMode() {
        if (!CAN_EDIT) return;
        if (!currentDetailData?.id) {
            alert('Data detail tidak ditemukan');
            return;
        }

        sheetMode = 'edit';
        document.getElementById('sheetTitle').innerText = 'Edit Barang';
        document.getElementById('sheetSubTitle').innerText = 'Ubah data barang';

        toggleInputs(false);

        document.getElementById('btnEditTrigger')?.classList.add('hidden');
        document.getElementById('btnHapus')?.classList.remove('hidden');
        document.getElementById('btnSubmit')?.classList.remove('hidden');
    }

    // =========================
    // GENERATE KODE
    // =========================
    function generateKodeByKategori(kategoriId) {
        if (!kategoriId || sheetMode !== 'tambah') {
            document.getElementById('inpKode').value = '';
            return;
        }
        fetch('generate_kode_barang.php?kategori_id=' + kategoriId)
            .then(r => r.json())
            .then(data => {
                if (data.success) document.getElementById('inpKode').value = data.kode;
            })
            .catch(err => console.error(err));
    }

    // =========================
    // DOM HELPERS
    // =========================
    function highlightCard(el) {
        if (!el) return;
        el.classList.add('ring-4', 'ring-emerald-300', 'ring-opacity-40');
        el.style.transform = 'scale(1.02)';
        el.style.transition = 'transform .2s ease';
        setTimeout(() => {
            el.style.transform = '';
            el.classList.remove('ring-4', 'ring-emerald-300', 'ring-opacity-40');
        }, 1000);
    }

    function getKategoriNameById(id) {
        const opt = document.querySelector(`#inpKategori option[value="${id}"]`);
        return opt ? opt.innerText.trim() : 'Lainnya';
    }

    function getSatuanNameById(id) {
        const opt = document.querySelector(`#inpSatuan option[value="${id}"]`);
        return opt ? opt.innerText.trim() : '';
    }

    function ensureKategoriSection(kategoriName) {
        let sec = document.querySelector(`.kategori-section[data-kategori="${kategoriName}"]`);
        if (sec) return sec;
        sec = document.createElement('section');
        sec.className = 'kategori-section';
        sec.dataset.kategori = kategoriName;
        sec.innerHTML = `
            <div class="flex items-center gap-2 mb-4">
                <div class="h-5 w-1.5 bg-sky-500 rounded-full"></div>
                <h2 class="font-bold text-gray-800 text-sm kategoriTitle">${kategoriName}</h2>
            </div>
            <div class="space-y-3 kategoriList"></div>`;
        sectionsWrapper.appendChild(sec);
        return sec;
    }

    function buildCardFromItem(item) {
        const satuanName = getSatuanNameById(item.satuan_id);
        const kategoriName = getKategoriNameById(item.kategori_id);
        const card = document.createElement('div');
        card.className = 'item-card bg-white rounded-3xl p-4 flex justify-between items-center border shadow-sm cursor-pointer';
        card.setAttribute('data-id', item.id);
        card.setAttribute('data-name', (item.nama_barang + ' ' + item.kode_barang).toLowerCase());
        card.setAttribute('data-kode', (item.kode_barang || '').toLowerCase());
        card.setAttribute('data-satuan-id', item.satuan_id);
        card.setAttribute('data-kategori-id', item.kategori_id);
        card.setAttribute('data-nama', item.nama_barang);
        card.setAttribute('data-kode-asli', item.kode_barang);
        card.setAttribute('data-stok', item.stok || 0);
        card.setAttribute('data-satuan', satuanName);
        card.onclick = function() {
            openDetailFromCard(card);
        };

        card.innerHTML = `
            <div class="flex gap-4 items-center min-w-0">
                <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0 iconWrap">
                    <i class="fa-solid fa-box text-xl iconEl"></i>
                </div>
                <div class="min-w-0">
                    <span class="text-[10px] font-bold text-sky-500 kodeText">${item.kode_barang}</span>
                    <h3 class="font-bold text-gray-800 text-sm truncate namaText">${item.nama_barang}</h3>
                    <p class="text-xs text-gray-500">Stok: <span class="font-bold text-green-600 stokText">${item.stok || 0} ${satuanName}</span></p>
                </div>
            </div>
            <button type="button" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </button>`;

        card.querySelector('button').onclick = function(e) {
            e.stopPropagation();
            openDetailFromCard(card);
        };
        return {
            card,
            kategoriName
        };
    }

    function updateCardDom(item) {
        const el = document.querySelector(`.item-card[data-id="${item.id}"]`);
        if (!el) return;
        const satuanName = getSatuanNameById(item.satuan_id);
        el.setAttribute('data-name', (item.nama_barang + ' ' + item.kode_barang).toLowerCase());
        el.setAttribute('data-kode', (item.kode_barang || '').toLowerCase());
        el.setAttribute('data-satuan-id', item.satuan_id);
        el.setAttribute('data-kategori-id', item.kategori_id);
        el.setAttribute('data-nama', item.nama_barang);
        el.setAttribute('data-kode-asli', item.kode_barang);
        el.setAttribute('data-stok', item.stok || 0);
        el.setAttribute('data-satuan', satuanName);
        const kodeText = el.querySelector('.kodeText');
        const namaText = el.querySelector('.namaText');
        const stokText = el.querySelector('.stokText');
        if (kodeText) kodeText.innerText = item.kode_barang;
        if (namaText) namaText.innerText = item.nama_barang;
        if (stokText) stokText.innerText = (item.stok || 0) + ' ' + satuanName;
        highlightCard(el);
    }

    // =========================
    // SIMPAN
    // =========================
    function handleSimpan() {
        if (!CAN_EDIT) return;
        const id = document.getElementById('inpId').value;
        const nama = document.getElementById('inpNama').value.trim();
        const kategori_id = document.getElementById('inpKategori').value;
        const satuan_id = document.getElementById('inpSatuan').value;

        if (!nama || !kategori_id || !satuan_id) {
            alert('Lengkapi nama, kategori, dan satuan');
            return;
        }

        const isEditMode = (sheetMode === 'edit' && id);
        const url = isEditMode ? 'stok_barang_update.php' : 'stok_barang_simpan.php';

        const btn = document.getElementById('btnSubmit');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin mr-2"></i> Menyimpan...';
        btn.disabled = true;

        const formData = new FormData();
        if (isEditMode) formData.append('id', id);
        formData.append('nama_barang', nama);
        formData.append('kategori_id', kategori_id);
        formData.append('satuan_id', satuan_id);

        fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                if (res.status !== 'success') {
                    alert('Gagal: ' + (res.message || 'Terjadi kesalahan'));
                    return;
                }

                const item = res.data;
                if (isEditMode) {
                    const oldCard = document.querySelector(`.item-card[data-id="${item.id}"]`);
                    const oldKategoriId = oldCard ? oldCard.getAttribute('data-kategori-id') : null;
                    updateCardDom(item);
                    if (oldCard && oldKategoriId !== String(item.kategori_id)) {
                        const sec = ensureKategoriSection(getKategoriNameById(item.kategori_id));
                        const list = sec.querySelector('.kategoriList');
                        if (list) list.prepend(oldCard);
                    }
                    alert('Perubahan tersimpan ✅');
                } else {
                    const {
                        card,
                        kategoriName
                    } = buildCardFromItem(item);
                    const sec = ensureKategoriSection(kategoriName);
                    const list = sec.querySelector('.kategoriList');
                    if (list) list.prepend(card);
                    emptyState.classList.add('hidden');
                    emptyState.classList.remove('flex');
                    if (sectionsWrapper) sectionsWrapper.style.display = 'block';
                    alert('Berhasil ditambahkan ✅');
                }

                closeStokModal();
                updateDisplay(mainSearch.value);
            })
            .catch(err => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Network error: ' + err);
            });
    }

    // =========================
    // HAPUS
    // =========================
    function handleHapus() {
        if (!CAN_EDIT) return;
        const id = document.getElementById('inpId').value;
        if (!id) {
            alert('ID kosong');
            return;
        }
        if (!confirm('Hapus barang ini?')) return;

        const btn = document.getElementById('btnHapus');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin mr-2"></i> Menghapus...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('id', id);

        fetch('stok_barang_hapus.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                if (res.status !== 'success') {
                    alert('Gagal: ' + (res.message || 'Terjadi kesalahan'));
                    return;
                }

                const card = document.querySelector(`.item-card[data-id="${id}"]`);
                if (card) {
                    const section = card.closest('.kategori-section');
                    card.remove();
                    if (section) {
                        const list = section.querySelector('.kategoriList');
                        if (list && list.children.length === 0) section.remove();
                    }
                }

                closeStokModal();
                updateDisplay(mainSearch.value);
                alert('Berhasil dihapus ✅');
            })
            .catch(err => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Network error: ' + err);
            });
    }

    window.addEventListener('load', () => updateDisplay());
</script>

<?php include 'footer.php'; ?>