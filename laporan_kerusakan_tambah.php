<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$title = "Laporan Kerusakan";
include 'header.php';

$namaLengkap = $_SESSION['user']['nama'] ?? '';
?>

<style>
    main.laporan-main {
        padding-bottom: 6rem;
    }

    .hidden {
        display: none;
    }

    input[readonly] {
        text-transform: capitalize;
    }

    .date-placeholder {
        opacity: 1;
        transition: opacity 0.2s ease;
    }

    .date-field.active .date-placeholder {
        opacity: 0;
    }
</style>

<!-- ================= STICKY HEADER ================= -->
<div id="stickyHeader" class="seamless-header">
    <div class="flex items-center gap-1 mb-2">
        <a href="lainnya.php" class="back-btn p-2 bg-white shadow-sm border border-sky-100 hover:bg-sky-50 transition">
            <i class="fa-solid fa-arrow-left text-sky-600 text-lg"></i>
        </a>

        <div class="flex-1">
            <h2 class="font-bold text-xl md:text-2xl text-sky-600">
                Laporan Kerusakan
            </h2>
            <p class="text-xs md:text-sm text-gray-500">
                Pelaporan fasilitas rusak
            </p>
        </div>
    </div>
</div>

<main class="laporan-main">
    <div class="register-container checklist-page">

        <form id="laporanKerusakanForm"
            action="laporan_kerusakan_simpan.php"
            method="POST"
            enctype="multipart/form-data"
            class="space-y-4">

            <input type="hidden" name="pelapor" value="<?= htmlspecialchars($namaLengkap); ?>">

            <!-- ================= INFORMASI DASAR ================= -->
            <section class="form-section">
                <h3 class="text-base md:text-lg font-semibold text-sky-700 flex items-center gap-2 mb-2">
                    <i data-lucide="info" class="w-5 h-5"></i>
                    Informasi Kerusakan
                </h3>

                <!-- TANGGAL -->
                <div class="date-field fade-up">
                    <i class="fa-solid fa-calendar-days date-icon"></i>

                    <span class="date-placeholder" id="tanggalLabel">
                        Pilih Tanggal
                    </span>

                    <input
                        type="date"
                        name="tanggal"
                        id="tanggal"
                        class="date-input"
                        required />
                </div>

                <!-- ================= LOKASI (CASCADING FINAL) ================= -->
                <section class="form-section">
                    <h3 class="text-base md:text-lg font-semibold text-sky-700 flex items-center gap-2 mb-2">
                        <i data-lucide="map-pin" class="w-5 h-5"></i>
                        Lokasi Kerusakan
                    </h3>

                    <!-- TIPE LOKASI -->
                    <div class="input-field">
                        <i class="fa-solid fa-map-pin"></i>
                        <select id="tipeLokasiSelect" name="tipe_lokasi_id" class="input-box" required>
                            <option value="">Pilih Tipe Lokasi</option>
                        </select>
                    </div>

                    <!-- LOKASI -->
                    <div class="input-field">
                        <i class="fa-solid fa-building"></i>
                        <select id="lokasiSelect" name="lokasi_id" class="input-box" required>
                            <option value="">Pilih Lokasi</option>
                        </select>
                    </div>

                    <!-- LANTAI -->
                    <div class="input-field hidden" id="wrapLantai">
                        <i class="fa-solid fa-layer-group"></i>
                        <select id="lantaiSelect" name="lantai_id" class="input-box">
                            <option value="">Pilih Lantai</option>
                        </select>
                    </div>

                    <!-- RUANGAN (GEDUNG) -->
                    <div class="input-field hidden" id="wrapRuangan">
                        <i class="fa-solid fa-door-open"></i>
                        <select id="ruanganSelect" name="ruangan_id" class="input-box">
                            <option value="">Pilih Ruangan</option>
                        </select>
                    </div>

                    <!-- KAMAR (ASRAMA) -->
                    <div class="input-field hidden" id="wrapKamar">
                        <i class="fa-solid fa-bed"></i>
                        <select id="kamarSelect" name="kamar_id" class="input-box">
                            <option value="">Pilih Kamar</option>
                        </select>
                    </div>
                </section>




                <!-- ================= KERUSAKAN ================= -->
                <section class="form-section">
                    <h3 class="text-base md:text-lg font-semibold text-sky-700 flex items-center gap-2 mb-2">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                        Jenis Kerusakan
                    </h3>

                    <!-- KATEGORI -->
                    <div class="input-field">
                        <i class="fa-solid fa-tags"></i>
                        <select name="kategori_kerusakan_id"
                            id="kategoriKerusakan"
                            class="input-box select-custom"
                            required>
                            <option value="">Pilih Kategori Kerusakan</option>
                        </select>
                    </div>

                    <!-- JENIS -->
                    <div class="input-field hidden" id="wrapJenisKerusakan">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                        <select name="jenis_kerusakan_id"
                            id="jenisKerusakan"
                            class="input-box select-custom"
                            required>
                            <option value="">Pilih Jenis Kerusakan</option>
                        </select>
                    </div>
                </section>


                <div class="input-field">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <input
                        type="text"
                        id="prioritasPreview"
                        class="input-box bg-gray-100"
                        placeholder="Prioritas otomatis"
                        readonly>
                </div>

            </section>



            <!-- ================= FOTO ================= -->
            <section id="uploadFotoKerusakan" class="form-section mt-6">
                <h3 class="text-base md:text-lg font-semibold mb-2 flex items-center gap-2 text-sky-700">
                    <i data-lucide="image" class="w-5 h-5"></i>
                    Upload Foto Kerusakan
                </h3>

                <div id="container-foto_kerusakan" class="foto-container border-2 border-dashed border-sky-300/50 rounded-2xl p-3 text-center"
                    data-input="fotoKerusakanInput">

                    <i data-lucide="upload" class="w-6 h-6 text-sky-500 mb-1"></i>
                    <span class="text-sm font-medium text-sky-700">Foto Kerusakan</span>

                    <input type="file"
                        name="foto_kerusakan[]"
                        id="foto_kerusakan"
                        accept="image/*"
                        class="hidden foto-input"
                        multiple>

                    <div id="preview-foto_kerusakan"
                        class="flex flex-wrap gap-2 mt-3 justify-center"></div>
                </div>
            </section>

            <!-- ================= DESKRIPSI ================= -->
            <section class="form-section mt-6">
                <h3 class="text-base md:text-lg font-semibold text-sky-700 flex items-center gap-2 mb-2">
                    <i data-lucide="file-text" class="w-5 h-5"></i>
                    Deskripsi Kerusakan
                </h3>

                <textarea name="deskripsi"
                    rows="4"
                    class="w-full rounded-xl border border-sky-200 p-3 bg-white/50 focus:ring-2 focus:ring-sky-300"
                    placeholder="Jelaskan kondisi kerusakan..."
                    required></textarea>
            </section>

            <!-- ================= TOMBOL ================= -->
            <div class="mt-6 space-y-3">
                <button type="submit" class="btn-primary w-full">
                    Kirim Laporan
                </button>
                <button type="button" id="resetFormBtn" class="btn-outline w-full">
                    Reset Form
                </button>
            </div>

        </form>
    </div>
</main>

<script>
    document.addEventListener("DOMContentLoaded", () => {

        /* ===================== UTIL ===================== */
        const $ = id => document.getElementById(id);
        const resetSelect = (el, text) => {
            if (el) el.innerHTML = `<option value="">${text}</option>`;
        };
        const hide = (...els) => els.forEach(e => e && e.classList.add("hidden"));
        const show = (...els) => els.forEach(e => e && e.classList.remove("hidden"));

        /* ===================== FOTO PREVIEW ===================== */
        function initFotoPreview() {
            const input = $("foto_kerusakan");
            const preview = $("preview-foto_kerusakan");
            if (!input || !preview) return;

            input.addEventListener("change", () => {
                preview.innerHTML = "";
                [...input.files].forEach(file => {
                    if (!file.type.startsWith("image")) return;
                    const reader = new FileReader();
                    reader.onload = e => {
                        const img = document.createElement("img");
                        img.src = e.target.result;
                        img.className = "w-20 h-20 object-cover rounded-lg border";
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        /* ===================== LOKASI CASCADING ===================== */
        function initLokasi() {
            const tipe = $("tipeLokasiSelect");
            const lokasi = $("lokasiSelect");
            const lantai = $("lantaiSelect");
            const ruang = $("ruanganSelect");
            const kamar = $("kamarSelect");

            const wLantai = $("wrapLantai");
            const wRuang = $("wrapRuangan");
            const wKamar = $("wrapKamar");

            if (!tipe || !lokasi) return;

            /* LOAD TIPE LOKASI */
            fetch("api/get_tipe_lokasi.php")
                .then(r => r.json())
                .then(d => d.forEach(x =>
                    tipe.insertAdjacentHTML("beforeend",
                        `<option value="${x.id}">${x.nama}</option>`)
                ));

            /* TIPE LOKASI CHANGE */
            tipe.addEventListener("change", () => {
                hide(wLantai, wRuang, wKamar);
                resetSelect(lokasi, "Pilih Lokasi");
                resetSelect(lantai, "Pilih Lantai");
                resetSelect(ruang, "Pilih Ruangan");
                resetSelect(kamar, "Pilih Kamar");

                kamar.removeAttribute("required");

                if (!tipe.value) return;

                fetch(`api/get_lokasi.php?tipe=${tipe.value}`)
                    .then(r => r.json())
                    .then(d => d.forEach(x =>
                        lokasi.insertAdjacentHTML("beforeend",
                            `<option value="${x.id}">${x.nama_lokasi}</option>`)
                    ));
            });

            /* LOKASI CHANGE */
            lokasi.addEventListener("change", () => {
                hide(wLantai, wRuang, wKamar);
                resetSelect(lantai, "Pilih Lantai");
                resetSelect(ruang, "Pilih Ruangan");
                resetSelect(kamar, "Pilih Kamar");

                kamar.removeAttribute("required");

                if (!lokasi.value) return;

                const tipeText = tipe.selectedOptions[0].text.toLowerCase();

                // 🔴 RUMAH DINAS → STOP DI LOKASI (TIDAK ADA KAMAR)
                if (tipeText.includes("rumah")) {
                    return;
                }

                fetch(`api/get_lantai.php?gedung=${lokasi.value}`)
                    .then(r => r.json())
                    .then(d => {
                        if (d.length === 1 && d[0].is_virtual == 1) {
                            tipeText.includes("gedung") ?
                                loadRuang(d[0].id) :
                                loadKamar(d[0].id);
                        } else {
                            show(wLantai);
                            d.forEach(x =>
                                lantai.insertAdjacentHTML("beforeend",
                                    `<option value="${x.id}">${x.nama_lantai}</option>`)
                            );
                        }
                    });
            });

            /* LANTAI CHANGE */
            lantai.addEventListener("change", () => {
                hide(wRuang, wKamar);
                kamar.removeAttribute("required");

                if (!lantai.value) return;

                tipe.selectedOptions[0].text.toLowerCase().includes("gedung") ?
                    loadRuang(lantai.value) :
                    loadKamar(lantai.value);
            });

            function loadRuang(id) {
                show(wRuang);
                resetSelect(ruang, "Pilih Ruangan");
                fetch(`api/get_ruangan.php?lantai=${id}`)
                    .then(r => r.json())
                    .then(d => d.forEach(x =>
                        ruang.insertAdjacentHTML("beforeend",
                            `<option value="${x.id}">${x.nama_ruangan}</option>`)
                    ));
            }

            function loadKamar(id) {
                show(wKamar);
                resetSelect(kamar, "Pilih Kamar");
                kamar.setAttribute("required", "required");

                fetch(`api/get_kamar.php?lantai=${id}`)
                    .then(r => r.json())
                    .then(d => d.forEach(x =>
                        kamar.insertAdjacentHTML("beforeend",
                            `<option value="${x.id}">${x.nomor_kamar}</option>`)
                    ));
            }
        }

        /* ===================== KERUSAKAN + PRIORITAS ===================== */
        function initKerusakan() {
            const kategori = $("kategoriKerusakan");
            const jenis = $("jenisKerusakan");
            const wJenis = $("wrapJenisKerusakan");
            const prioritasPreview = $("prioritasPreview");

            if (!kategori || !jenis) return;

            fetch("api/get_kategori_kerusakan.php")
                .then(r => r.json())
                .then(d => d.forEach(x =>
                    kategori.insertAdjacentHTML("beforeend",
                        `<option value="${x.id}">${x.nama_kategori}</option>`)
                ));

            kategori.addEventListener("change", () => {
                resetSelect(jenis, "Pilih Jenis Kerusakan");
                hide(wJenis);
                prioritasPreview.value = "";

                if (!kategori.value) return;

                show(wJenis);
                fetch(`api/get_jenis_kerusakan.php?kategori=${kategori.value}`)
                    .then(r => r.json())
                    .then(d => d.forEach(x =>
                        jenis.insertAdjacentHTML("beforeend",
                            `<option value="${x.id}">${x.nama_jenis}</option>`)
                    ));
            });

            jenis.addEventListener("change", () => {
                prioritasPreview.value = "";
                if (!jenis.value) return;

                fetch(`api/get_prioritas_jenis.php?id=${jenis.value}`)
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            prioritasPreview.value = d.prioritas;
                        }
                    });
            });
        }

        /* ===================== DATE FIELD ===================== */
        function initDateField() {
            const field = document.querySelector(".date-field");
            const input = $("tanggal");

            if (!field || !input) return;

            input.addEventListener("focus", () => field.classList.add("active"));
            input.addEventListener("change", () => input.value && field.classList.add("active"));
            input.addEventListener("blur", () => !input.value && field.classList.remove("active"));
        }

        /* ===================== RESET FORM ===================== */
        function initReset() {
            const btn = $("resetFormBtn");
            const form = $("laporanKerusakanForm");

            if (!btn || !form) return;

            btn.addEventListener("click", () => {
                form.reset();

                ["wrapLantai", "wrapRuangan", "wrapKamar"].forEach(id => {
                    const el = $(id);
                    if (el) el.classList.add("hidden");
                });

                const kamar = $("kamarSelect");
                if (kamar) kamar.removeAttribute("required");

                document.querySelectorAll(".date-field").forEach(df => {
                    df.classList.remove("active");
                    const input = df.querySelector("input[type=date]");
                    if (input) input.value = "";
                });

                form.scrollIntoView({
                    behavior: "smooth"
                });
            });
        }

        /* ===================== INIT ALL ===================== */
        initFotoPreview();
        initLokasi();
        initKerusakan();
        initDateField();
        initReset();

    });
</script>
<?php if (isset($_GET['duplikat'], $_GET['id'])): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (confirm(
                    "⚠️ Kerusakan ini sudah pernah dilaporkan\n" +
                    "dan masih dalam proses.\n\n" +
                    "Ingin melihat laporan sebelumnya?"
                )) {
                window.location.href =
                    "laporan_kerusakan_detail.php?id=<?= (int)$_GET['id'] ?>";
            }
        });
    </script>
<?php endif; ?>

<?php include 'footer.php'; ?>