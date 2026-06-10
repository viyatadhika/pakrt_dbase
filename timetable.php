<?php
session_start();

$title = "Timetable Kegiatan";
include 'header.php';
include 'config.php';

$isAdmin = isset($_SESSION['user']) && strtolower($_SESSION['user']['role'] ?? '') === 'admin';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    *,
    *::before,
    *::after {
        box-sizing: border-box;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f8fafc;
        color: #1e293b;
        height: 100%;
        overflow: hidden;
        /* full-screen layout, inner area scrolls */
    }

    :root {
        --hdr-h: 56px;
        /* top header bar height */
    }

    /* ── TOP HEADER BAR ── */
    .top-bar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--hdr-h);
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        z-index: 100;
        box-shadow: 0 1px 8px rgba(15, 23, 42, .06);
    }

    .top-bar-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .top-bar-back {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #f0f9ff;
        border: none;
        cursor: pointer;
        color: #0284c7;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: .15s;
        flex-shrink: 0;
    }

    .top-bar-back:hover {
        background: #e0f2fe;
    }

    .top-bar-title {
        font-size: 16px;
        font-weight: 800;
        color: #0284c7;
        line-height: 1.15;
    }

    .top-bar-sub {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 600;
        line-height: 1;
    }

    .top-bar-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .top-bar-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: transparent;
        border: none;
        cursor: pointer;
        color: #0284c7;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        transition: .15s;
    }

    .top-bar-icon:hover {
        background: #f0f9ff;
    }

    /* ── MAIN CONTENT WRAPPER ── */
    .tt-page {
        position: fixed;
        top: var(--hdr-h);
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* ── SECOND ROW: title + nav ── */
    .tt-meta {
        flex-shrink: 0;
        background: #fff;
        padding: 14px 20px 10px;
        border-bottom: 1px solid #e8eef5;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
    }

    .tt-meta-title {
        font-size: 14px;
        font-weight: 900;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: .07em;
        line-height: 1.1;
    }

    .tt-meta-sub {
        font-size: 11px;
        font-weight: 700;
        color: #0284c7;
        margin-top: 3px;
    }

    .tt-nav {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .tt-nav-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 800;
        color: #0284c7;
        padding: 6px 14px;
        border-radius: 999px;
        transition: .15s;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .tt-nav-btn:hover {
        background: #f0f9ff;
    }

    .tt-nav-sep {
        color: #cbd5e1;
        font-size: 14px;
        font-weight: 300;
    }

    /* ── LEGEND ROW ── */
    .tt-legend {
        flex-shrink: 0;
        background: #fff;
        padding: 8px 20px;
        border-bottom: 1px solid #e8eef5;
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        color: #475569;
    }

    .legend-dot {
        width: 13px;
        height: 13px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    /* ── TABLE SCROLL AREA ── */
    .tt-scroll {
        flex: 1;
        overflow: auto;
        background: #fff;
    }

    /* ── TABLE ── */
    .tt-table {
        border-collapse: collapse;
        min-width: 100%;
        width: max-content;
    }

    .tt-table th,
    .tt-table td {
        border: 1px solid #9ca3af;
        padding: 0;
        vertical-align: middle;
        white-space: nowrap;
    }

    /* sticky left columns */
    .col-no {
        position: sticky;
        left: 0;
        z-index: 10;
        width: 42px;
        min-width: 42px;
        background: #d1d5db;
        text-align: center;
        font-size: 10px;
        font-weight: 900;
        color: #374151;
    }

    .col-kegiatan {
        position: sticky;
        left: 42px;
        z-index: 10;
        width: 265px;
        min-width: 265px;
        background: #d1d5db;
        font-size: 10px;
        font-weight: 900;
        color: #374151;
        text-align: center;
        padding: 6px 10px !important;
        white-space: normal;
        word-break: break-word;
    }

    .col-pny {
        position: sticky;
        left: 307px;
        z-index: 10;
        width: 100px;
        min-width: 100px;
        background: #d1d5db;
        text-align: center;
        font-size: 10px;
        font-weight: 900;
        color: #374151;
        padding: 4px !important;
        white-space: normal;
    }

    /* header rows */
    .th-month {
        background: #9ca3af;
        color: #fff;
        font-size: 11px;
        font-weight: 900;
        text-align: center;
        height: 24px;
        text-transform: uppercase;
    }

    .th-day {
        background: #9ca3af;
        color: #fff;
        font-size: 9px;
        font-weight: 900;
        text-align: center;
        width: 28px;
        min-width: 28px;
        height: 22px;
    }

    .th-num {
        background: #e5e7eb;
        color: #475569;
        font-size: 9px;
        font-weight: 800;
        text-align: center;
        width: 28px;
        min-width: 28px;
        height: 20px;
    }

    .th-day.weekend,
    .th-num.weekend {
        background: #ef4444 !important;
        color: #fff !important;
    }

    /* info columns (right side) */
    .col-info {
        background: #d1d5db;
        text-align: center;
        font-size: 9px;
        font-weight: 900;
        color: #374151;
        white-space: normal;
        padding: 3px 4px !important;
        vertical-align: middle;
        min-width: 56px;
    }

    /* body cells */
    .td-no {
        position: sticky;
        left: 0;
        z-index: 5;
        background: #fff;
        text-align: center;
        font-size: 11px;
        color: #6b7280;
        font-weight: 700;
        width: 42px;
        min-width: 42px;
    }

    .td-kegiatan {
        position: sticky;
        left: 42px;
        z-index: 5;
        background: #fff;
        padding: 7px 10px !important;
        font-size: 10px;
        font-weight: 800;
        color: #111827;
        line-height: 1.3;
        width: 265px;
        min-width: 265px;
        white-space: normal;
        word-break: break-word;
    }

    .td-pny {
        position: sticky;
        left: 307px;
        z-index: 5;
        width: 100px;
        min-width: 100px;
        text-align: center;
        font-size: 10px;
        font-weight: 800;
        padding: 4px !important;
        white-space: normal;
    }

    .td-info {
        text-align: center;
        font-size: 10px;
        color: #374151;
        font-weight: 700;
        padding: 3px 5px !important;
        white-space: normal;
        line-height: 1.3;
    }

    /* day cells */
    .td-day {
        width: 28px;
        min-width: 28px;
        height: 44px;
        background: #fff;
        cursor: pointer;
    }

    .td-day:hover {
        background: #f8fafc;
    }

    /* event block */
    .td-block {
        height: 44px;
        cursor: pointer;
        transition: .1s;
    }

    .td-block:hover {
        filter: brightness(.93);
    }

    .c-menpim {
        background: #ffff00;
    }

    .c-teknis {
        background: #34a853;
    }

    .c-kerjasama {
        background: #6fa8dc;
    }

    .c-pustrajak {
        background: #ff9900;
    }

    .c-default {
        background: #94a3b8;
    }

    .c-cancelled {
        background: repeating-linear-gradient(45deg, #fecaca, #fecaca 5px, #fee2e2 5px, #fee2e2 10px) !important;
    }

    .c-selesai {
        opacity: .42;
        filter: grayscale(55%);
    }

    .c-bentrok {
        outline: 2px solid #ef4444;
        outline-offset: -2px;
    }

    /* badges */
    .badge {
        display: inline-block;
        font-size: 8px;
        font-weight: 900;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-top: 3px;
    }

    .badge-cancel {
        background: #fee2e2;
        color: #ef4444;
    }

    .badge-done {
        background: #e2e8f0;
        color: #94a3b8;
    }

    /* body row hover */
    .tt-table tbody tr:hover .td-no,
    .tt-table tbody tr:hover .td-kegiatan,
    .tt-table tbody tr:hover .td-pny,
    .tt-table tbody tr:hover .td-day,
    .tt-table tbody tr:hover .td-info {
        background-image: linear-gradient(rgba(14, 165, 233, .04), rgba(14, 165, 233, .04));
    }

    /* pny badge inside cell */
    .pny-badge {
        display: inline-block;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.2;
    }

    /* empty */
    .tt-empty {
        padding: 48px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
        font-weight: 700;
    }

    /* scrollbar */
    ::-webkit-scrollbar {
        width: 5px;
        height: 6px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    /* ── MODAL ── */
    .modal-wrap {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        z-index: 300;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding: 16px;
    }

    .modal-box {
        width: 100%;
        max-width: 460px;
        background: #fff;
        border-radius: 28px;
        padding: 20px;
        box-shadow: 0 24px 64px rgba(0, 0, 0, .18);
        animation: slideUp .32s cubic-bezier(.16, 1, .3, 1) forwards;
    }

    @keyframes slideUp {
        from {
            transform: translateY(100%);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .modal-title {
        font-size: 14px;
        font-weight: 900;
        color: #111827;
    }

    .modal-sub {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 600;
        margin-top: 2px;
    }

    .modal-close {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #f3f4f6;
        border: none;
        cursor: pointer;
        color: #6b7280;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-close:hover {
        background: #e5e7eb;
    }

    .modal-edit-btn {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #f0f9ff;
        border: none;
        cursor: pointer;
        color: #0284c7;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .form-label {
        font-size: 11px;
        font-weight: 800;
        color: #374151;
        display: block;
        margin-bottom: 4px;
    }

    .form-input {
        width: 100%;
        padding: 10px 14px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: #f9fafb;
        font-size: 13px;
        outline: none;
        font-family: inherit;
        transition: .15s;
    }

    .form-input:focus {
        border-color: #7dd3fc;
        background: #fff;
    }

    .form-input:disabled {
        opacity: .65;
        cursor: default;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .form-space {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .btn-primary {
        width: 100%;
        padding: 12px;
        border-radius: 16px;
        background: #0284c7;
        color: #fff;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 800;
        font-family: inherit;
        transition: .15s;
    }

    .btn-primary:hover {
        background: #0369a1;
    }

    .btn-danger {
        width: 100%;
        padding: 12px;
        border-radius: 16px;
        background: #fef2f2;
        color: #ef4444;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 800;
        font-family: inherit;
    }

    .btn-warning {
        width: 100%;
        padding: 12px;
        border-radius: 16px;
        background: #fff7ed;
        color: #ea580c;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 800;
        font-family: inherit;
    }

    .btn-success {
        width: 100%;
        padding: 12px;
        border-radius: 16px;
        background: #f0fdf4;
        color: #16a34a;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 800;
        font-family: inherit;
    }

    /* ── TOAST ── */
    .toast {
        position: fixed;
        top: 68px;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: #fff;
        padding: 10px 22px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .2);
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s;
        z-index: 500;
        white-space: nowrap;
    }

    /* ── FAB ── */
    .fab {
        position: fixed;
        bottom: 28px;
        right: 28px;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #0284c7;
        color: #fff;
        border: none;
        cursor: pointer;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 20px rgba(2, 132, 199, .35);
        z-index: 90;
        transition: .15s;
    }

    .fab:hover {
        background: #0369a1;
        transform: scale(1.06);
    }

    .fab:active {
        transform: scale(.94);
    }

    /* ── MOBILE ── */
    @media(max-width:900px) {
        :root {
            --hdr-h: 52px;
        }

        .top-bar {
            padding: 0 14px;
        }

        .top-bar-title {
            font-size: 15px;
        }

        .tt-meta {
            padding: 12px 14px 8px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .tt-nav-btn {
            font-size: 11px;
            padding: 5px 10px;
        }

        .tt-legend {
            padding: 8px 14px;
            gap: 12px;
        }

        .legend-item {
            font-size: 10px;
        }
    }
</style>

<!-- TOP HEADER BAR -->
<div class="top-bar">
    <div class="top-bar-left">
        <button class="top-bar-back" onclick="window.history.back()">
            <i class="fa-solid fa-arrow-left" style="font-size:13px"></i>
        </button>
        <div>
            <div class="top-bar-title">Timetable Kegiatan</div>
            <div class="top-bar-sub">Jadwal kegiatan Pusdiklat</div>
        </div>
    </div>
    <div class="top-bar-right">
        <button class="top-bar-icon" onclick="openExportModal()" title="Download">
            <i class="fa-solid fa-download"></i>
        </button>
    </div>
</div>

<!-- MAIN CONTENT AREA -->
<div class="tt-page">

    <!-- TITLE + NAV ROW -->
    <div class="tt-meta">
        <div>
            <div class="tt-meta-title" id="tt-title">Timetable</div>
            <div class="tt-meta-sub" id="tt-count">0 Agenda Bulan Ini</div>
        </div>
        <div class="tt-nav">
            <button class="tt-nav-btn" onclick="changeMonth(-1)">
                <i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Sebelumnya
            </button>
            <span class="tt-nav-sep">|</span>
            <button class="tt-nav-btn" onclick="goThisMonth()">Bulan Ini</button>
            <span class="tt-nav-sep">|</span>
            <button class="tt-nav-btn" onclick="changeMonth(1)">
                Berikutnya <i class="fa-solid fa-chevron-right" style="font-size:11px"></i>
            </button>
        </div>
    </div>

    <!-- LEGEND ROW -->
    <div class="tt-legend">
        <div class="legend-item">
            <span class="legend-dot" style="background:#ffff00;border:1px solid #c9c900"></span>Menpim
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#34a853"></span>Teknis
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#6fa8dc"></span>Kerjasama
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#ff9900"></span>Pustrajak
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#fecaca;border:2px dashed #ef4444"></span>Dibatalkan
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#94a3b8;opacity:.45"></span>Selesai
        </div>
        <div class="legend-item">
            <span class="legend-dot" style="background:#fff;border:2px solid #ef4444"></span>Bentrok
        </div>
    </div>

    <!-- TABLE SCROLL AREA -->
    <div class="tt-scroll">
        <table class="tt-table" id="tt-table"></table>
    </div>
</div>

<?php if ($isAdmin): ?>
    <button class="fab" onclick="openModalTambah()" title="Tambah Jadwal">
        <i class="fa-solid fa-plus"></i>
    </button>
<?php endif; ?>

<!-- DETAIL / TAMBAH MODAL -->
<div id="stokModal" class="modal-wrap" style="display:none">
    <div class="absolute-bg" onclick="closeModal()" style="position:absolute;inset:0"></div>
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <div class="modal-title" id="sheetTitle">Detail Jadwal</div>
                <div class="modal-sub">Pusdiklat Mahkamah Agung</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <?php if ($isAdmin): ?>
                    <button class="modal-edit-btn" id="btnEditTrigger" onclick="enableEditMode()" style="display:none">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                <?php endif; ?>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <form id="agenda-form" onsubmit="handleSave(event)">
            <input type="hidden" id="edit-id">
            <div class="form-space">
                <div>
                    <label class="form-label">Nama Pelatihan</label>
                    <input id="f-judul" type="text" class="form-input">
                </div>
                <div class="form-grid">
                    <div>
                        <label class="form-label">Mulai</label>
                        <input id="f-start" type="date" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Selesai</label>
                        <input id="f-end" type="date" class="form-input">
                    </div>
                </div>
                <div>
                    <label class="form-label">Kategori</label>
                    <select id="f-pny" class="form-input">
                        <option value="Menpim">Menpim</option>
                        <option value="Teknis">Teknis</option>
                        <option value="Kerjasama">Kerjasama</option>
                        <option value="Pustrajak">Pustrajak</option>
                    </select>
                </div>
                <div class="form-grid">
                    <div>
                        <label class="form-label">Peserta</label>
                        <input id="f-peserta" type="number" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Asrama</label>
                        <input id="f-asrama" type="text" class="form-input">
                    </div>
                </div>
                <div class="form-grid">
                    <div>
                        <label class="form-label">Kelas</label>
                        <input id="f-kelas" type="text" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Ruang Makan</label>
                        <input id="f-makan" type="text" class="form-input">
                    </div>
                </div>

                <?php if ($isAdmin): ?>
                    <button id="btnSubmit" type="submit" class="btn-primary" style="display:none">Simpan Jadwal</button>
                    <button id="btnHapus" type="button" onclick="handleDelete()" class="btn-danger" style="display:none"><i class="fa-solid fa-trash-can"></i> Hapus Jadwal</button>
                    <button id="btnBatalkan" type="button" onclick="handleCancel()" class="btn-warning" style="display:none"><i class="fa-solid fa-ban"></i> Batalkan Kegiatan</button>
                    <button id="btnAktifkan" type="button" onclick="handleReactivate()" class="btn-success" style="display:none"><i class="fa-solid fa-rotate-left"></i> Aktifkan Kembali</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- EXPORT MODAL -->
<div id="exportModal" class="modal-wrap" style="display:none">
    <div style="position:absolute;inset:0" onclick="closeExportModal()"></div>
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <div class="modal-title">Download Laporan</div>
                <div class="modal-sub">Pilih rentang tanggal</div>
            </div>
            <button class="modal-close" onclick="closeExportModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="form-space">
            <div>
                <label class="form-label">Dari Tanggal</label>
                <input type="date" id="exportFrom" class="form-input">
            </div>
            <div>
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" id="exportTo" class="form-input">
            </div>
            <button onclick="downloadExport()" class="btn-primary">Download PDF</button>
            <p style="text-align:center;font-size:10px;color:#94a3b8;margin:0">Default otomatis 30 hari terakhir</p>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast"></div>

<script>
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

    let agendaData = [];
    let viewDate = new Date();
    let abort = new AbortController();

    /* ── helpers ── */
    function parseDate(s) {
        const [y, m, d] = String(s).split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    function toStr(d) {
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    }

    function addDays(d, n) {
        const x = new Date(d);
        x.setDate(x.getDate() + n);
        return x;
    }

    function isWE(d) {
        return d.getDay() === 0 || d.getDay() === 6;
    }

    function dayShort(d) {
        return ['M', 'SN', 'SL', 'R', 'K', 'J', 'SB'][d.getDay()];
    }

    function fmtDate(s) {
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }).format(parseDate(s));
    }

    function fmtMonth(d) {
        return new Intl.DateTimeFormat('id-ID', {
            month: 'long',
            year: 'numeric'
        }).format(d);
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        } [m]));
    }

    function monthRange() {
        return {
            start: new Date(viewDate.getFullYear(), viewDate.getMonth(), 1),
            end: new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0)
        };
    }

    function daysInView() {
        const {
            start,
            end
        } = monthRange();
        const days = [];
        for (let d = new Date(start); d <= end; d = addDays(d, 1)) days.push(new Date(d));
        return days;
    }

    function colorClass(pny) {
        return {
            Menpim: 'c-menpim',
            Teknis: 'c-teknis',
            Kerjasama: 'c-kerjasama',
            Pustrajak: 'c-pustrajak'
        } [pny] || 'c-default';
    }

    function colorHex(pny) {
        return {
            Menpim: '#ffff00',
            Teknis: '#34a853',
            Kerjasama: '#6fa8dc',
            Pustrajak: '#ff9900'
        } [pny] || '#94a3b8';
    }

    function pnyTextColor(pny) {
        return pny === 'Menpim' ? '#7a6f00' : '#fff'; // yellow needs dark text
    }

    function markBentrok(data) {
        return data.map((a, i) => {
            if (a.status === 'cancelled') return {
                ...a,
                isBentrok: false
            };
            const b = data.some((b, j) => i !== j && b.status !== 'cancelled' && a.start <= b.end && a.end >= b.start);
            return {
                ...a,
                isBentrok: b
            };
        });
    }

    /* ── load ── */
    async function loadAgenda() {
        abort.abort();
        abort = new AbortController();
        try {
            const r = await fetch('get_timetable.php?action=read', {
                signal: abort.signal
            });
            agendaData = markBentrok(await r.json());
            render();
        } catch (e) {
            if (e.name !== 'AbortError') console.error(e);
        }
    }
    window.onload = loadAgenda;

    /* ── render ── */
    function render() {
        const {
            start,
            end
        } = monthRange();
        const sStr = toStr(start),
            eStr = toStr(end);
        const list = agendaData
            .filter(e => e.start <= eStr && e.end >= sStr)
            .sort((a, b) => a.start.localeCompare(b.start) || String(a.judul || '').localeCompare(String(b.judul || '')));

        document.getElementById('tt-title').textContent = `TIMETABLE ${fmtMonth(viewDate).toUpperCase()}`;
        document.getElementById('tt-count').textContent = `${list.length} Agenda Bulan Ini`;

        const tbl = document.getElementById('tt-table');
        if (!list.length) {
            tbl.innerHTML = `<tbody><tr><td class="tt-empty" colspan="99">Tidak ada agenda pada bulan ini</td></tr></tbody>`;
            return;
        }

        const days = daysInView();
        const todayStr = toStr(new Date());
        const mName = new Intl.DateTimeFormat('id-ID', {
            month: 'long'
        }).format(viewDate).toUpperCase();

        /* ── HEAD ── */
        let h = `<thead>
    <tr>
        <th class="col-no"  rowspan="3">No.</th>
        <th class="col-kegiatan" rowspan="3">KEGIATAN</th>
        <th class="col-pny" rowspan="3">PENYELENGGARA</th>
        <th class="th-month" colspan="${days.length}">${mName}</th>
        <th class="col-info" rowspan="3">JUMLAH<br>PESERTA</th>
        <th class="col-info" rowspan="3">ASRAMA</th>
        <th class="col-info" rowspan="3">KELAS</th>
        <th class="col-info" rowspan="3">RUANG<br>MAKAN</th>
    </tr>
    <tr>${days.map(d=>`<th class="th-day${isWE(d)?' weekend':''}">${dayShort(d)}</th>`).join('')}</tr>
    <tr>${days.map(d=>`<th class="th-num${isWE(d)?' weekend':''}">${d.getDate()}</th>`).join('')}</tr>
    </thead><tbody>`;

        /* ── BODY ── */
        list.forEach((item, idx) => {
            const cancelled = item.status === 'cancelled';
            const done = !cancelled && item.end < todayStr;
            const cc = colorClass(item.pny);
            let blockCls = cc;
            if (cancelled) blockCls += ' c-cancelled';
            if (done) blockCls += ' c-selesai';
            if (item.isBentrok) blockCls += ' c-bentrok';

            const pnyBg = colorHex(item.pny);
            const pnyTx = pnyTextColor(item.pny);

            h += `<tr>
        <td class="td-no">${idx+1}</td>
        <td class="td-kegiatan" onclick="openModalDetail(${item.id})">
            <span style="${cancelled?'text-decoration:line-through;color:#9ca3af':''}">${esc(item.judul||'-')}</span>
            ${cancelled?'<br><span class="badge badge-cancel">Dibatalkan</span>':''}
            ${done?'<br><span class="badge badge-done">Selesai</span>':''}
        </td>
        <td class="td-pny" onclick="openModalDetail(${item.id})" style="background:${pnyBg}">
            <span class="pny-badge" style="color:${pnyTx};font-size:9px">${esc(item.pny||'-')}</span>
        </td>`;

            days.forEach(d => {
                const ds = toStr(d);
                const active = ds >= item.start && ds <= item.end;
                h += active ?
                    `<td class="td-block ${blockCls}" onclick="openModalDetail(${item.id})" title="${esc(item.judul||'')}"></td>` :
                    `<td class="td-day" onclick="filterByDate('${ds}')"></td>`;
            });

            h += `<td class="td-info">${esc(item.peserta||'0')}</td>
        <td class="td-info">${esc(item.asrama||'-')}</td>
        <td class="td-info">${esc(item.kelas||'-')}</td>
        <td class="td-info">${esc(item.makan||'-')}</td>
        </tr>`;
        });

        tbl.innerHTML = h + '</tbody>';
    }

    /* ── month nav ── */
    function changeMonth(d) {
        viewDate.setMonth(viewDate.getMonth() + d);
        render();
    }

    function goThisMonth() {
        viewDate = new Date();
        render();
    }

    /* ── modal ── */
    function openModalTambah() {
        if (!IS_ADMIN) return;
        document.getElementById('sheetTitle').textContent = 'Tambah Jadwal';
        document.getElementById('agenda-form').reset();
        document.getElementById('edit-id').value = '';
        const t = toStr(new Date());
        document.getElementById('f-start').value = t;
        document.getElementById('f-end').value = t;
        toggleInputs(false);
        showBtn('btnSubmit', 'Simpan Jadwal');
        hideBtn('btnEditTrigger');
        hideBtn('btnHapus');
        hideBtn('btnBatalkan');
        hideBtn('btnAktifkan');
        showModal();
    }

    function openModalDetail(id) {
        const item = agendaData.find(a => String(a.id) === String(id));
        if (!item) return;
        document.getElementById('sheetTitle').textContent = 'Detail Jadwal';
        document.getElementById('edit-id').value = item.id;
        document.getElementById('f-judul').value = item.judul || '';
        document.getElementById('f-start').value = item.start;
        document.getElementById('f-end').value = item.end;
        document.getElementById('f-pny').value = item.pny || 'Menpim';
        document.getElementById('f-peserta').value = item.peserta || '';
        document.getElementById('f-asrama').value = item.asrama || '';
        document.getElementById('f-kelas').value = item.kelas || '';
        document.getElementById('f-makan').value = item.makan || '';
        toggleInputs(true);
        hideBtn('btnSubmit');
        hideBtn('btnHapus');
        if (IS_ADMIN) {
            showBtnEl('btnEditTrigger');
            const cancelled = item.status === 'cancelled';
            cancelled ? hideBtn('btnBatalkan') : showBtnEl('btnBatalkan');
            cancelled ? showBtnEl('btnAktifkan') : hideBtn('btnAktifkan');
        }
        showModal();
    }

    function enableEditMode() {
        if (!IS_ADMIN) return;
        document.getElementById('sheetTitle').textContent = 'Ubah Jadwal';
        toggleInputs(false);
        showBtn('btnSubmit', 'Simpan Perubahan');
        hideBtn('btnEditTrigger');
        showBtnEl('btnHapus');
        hideBtn('btnBatalkan');
        hideBtn('btnAktifkan');
    }

    function toggleInputs(dis) {
        ['f-judul', 'f-start', 'f-end', 'f-pny', 'f-peserta', 'f-asrama', 'f-kelas', 'f-makan'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.disabled = dis;
                el.style.opacity = dis ? '.65' : '1';
            }
        });
    }

    function showBtn(id, txt) {
        const el = document.getElementById(id);
        if (el) {
            el.style.display = 'block';
            if (txt) el.textContent = txt;
        }
    }

    function showBtnEl(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'flex';
    }

    function hideBtn(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    function showModal() {
        document.getElementById('stokModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('stokModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    /* ── CRUD ── */
    async function handleSave(e) {
        e.preventDefault();
        if (!IS_ADMIN) return;
        const id = document.getElementById('edit-id').value;
        const fd = new FormData();
        ['judul', 'start', 'end', 'pny', 'peserta', 'asrama', 'kelas', 'makan'].forEach(k => {
            fd.append(k, document.getElementById('f-' + k).value);
        });
        let url = 'get_timetable.php?action=create';
        if (id) {
            fd.append('id', id);
            url = 'get_timetable.php?action=update';
        }
        await fetch(url, {
            method: 'POST',
            body: fd
        });
        closeModal();
        toast('Data tersimpan');
        loadAgenda();
    }

    async function handleDelete() {
        if (!IS_ADMIN || !confirm('Hapus jadwal ini?')) return;
        const fd = new FormData();
        fd.append('id', document.getElementById('edit-id').value);
        await fetch('get_timetable.php?action=delete', {
            method: 'POST',
            body: fd
        });
        closeModal();
        toast('Jadwal dihapus');
        loadAgenda();
    }

    async function handleCancel() {
        if (!IS_ADMIN || !confirm('Batalkan kegiatan ini?')) return;
        const fd = new FormData();
        fd.append('id', document.getElementById('edit-id').value);
        fd.append('new_status', 'cancelled');
        await fetch('get_timetable.php?action=cancel', {
            method: 'POST',
            body: fd
        });
        closeModal();
        toast('Kegiatan dibatalkan');
        loadAgenda();
    }

    async function handleReactivate() {
        if (!IS_ADMIN || !confirm('Aktifkan kembali?')) return;
        const fd = new FormData();
        fd.append('id', document.getElementById('edit-id').value);
        fd.append('new_status', 'active');
        await fetch('get_timetable.php?action=cancel', {
            method: 'POST',
            body: fd
        });
        closeModal();
        toast('Kegiatan diaktifkan');
        loadAgenda();
    }

    function filterByDate(dateStr) {
        const evs = agendaData.filter(e => dateStr >= e.start && dateStr <= e.end);
        if (!evs.length) {
            toast('Tidak ada agenda');
            return;
        }
        if (evs.length === 1) {
            openModalDetail(evs[0].id);
            return;
        }
        toast(`${evs.length} agenda pada tanggal ini`);
    }

    /* ── export ── */
    function openExportModal() {
        document.getElementById('exportModal').style.display = 'flex';
        const t = new Date(),
            p = new Date();
        p.setDate(t.getDate() - 30);
        document.getElementById('exportTo').value = toStr(t);
        document.getElementById('exportFrom').value = toStr(p);
    }

    function closeExportModal() {
        document.getElementById('exportModal').style.display = 'none';
    }

    function downloadExport() {
        const from = document.getElementById('exportFrom').value;
        const to = document.getElementById('exportTo').value;
        if (!from || !to) {
            alert('Pilih rentang tanggal');
            return;
        }
        if (from > to) {
            alert('Tanggal awal > tanggal akhir');
            return;
        }
        window.location.href = `timetable_export.php?from=${from}&to=${to}`;
        closeExportModal();
    }

    /* ── toast ── */
    function toast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', 2800);
    }

    /* cleanup */
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) abort.abort();
        else loadAgenda();
    });
    window.addEventListener('beforeunload', () => abort.abort());
</script>