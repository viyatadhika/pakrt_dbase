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
    }

    :root {
        --hdr-h: 56px;
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
        font-size: 17px;
        transition: .15s;
        display: flex;
        align-items: center;
        justify-content: center;
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

    /* ── TITLE + NAV ── */
    .tt-meta {
        flex-shrink: 0;
        background: #fff;
        padding: 11px 20px 9px;
        border-bottom: 1px solid #e8eef5;
        display: flex;
        align-items: center;
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
        gap: 2px;
    }

    .tt-nav-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 800;
        color: #0284c7;
        padding: 6px 12px;
        border-radius: 999px;
        transition: .15s;
        display: flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
        font-family: inherit;
    }

    .tt-nav-btn:hover {
        background: #f0f9ff;
    }

    .tt-nav-sep {
        color: #cbd5e1;
        font-size: 14px;
        padding: 0 2px;
    }

    /* ── LEGEND ── */
    .tt-legend {
        flex-shrink: 0;
        background: #fff;
        padding: 6px 20px;
        border-bottom: 1px solid #e8eef5;
        display: flex;
        align-items: center;
        gap: 16px;
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
        width: 12px;
        height: 12px;
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
        table-layout: fixed;
        width: max-content;
        min-width: 100%;
    }

    .tt-table th,
    .tt-table td {
        border: 1px solid #9ca3af;
        padding: 0;
        vertical-align: middle;
    }

    /* ── STICKY HEADER COLS ── */
    .col-no {
        position: sticky;
        left: 0;
        z-index: 12;
        width: 36px;
        min-width: 36px;
        background: #d1d5db;
        text-align: center;
        font-size: 9px;
        font-weight: 900;
        color: #374151;
    }

    .col-kegiatan {
        position: sticky;
        left: 36px;
        z-index: 12;
        width: 280px;
        min-width: 280px;
        background: #d1d5db;
        font-size: 9px;
        font-weight: 900;
        color: #374151;
        text-align: center;
        padding: 5px 8px !important;
        white-space: normal;
        word-break: break-word;
    }

    .col-pny {
        position: sticky;
        left: 316px;
        z-index: 12;
        width: 88px;
        min-width: 88px;
        background: #d1d5db;
        text-align: center;
        font-size: 9px;
        font-weight: 900;
        color: #374151;
        padding: 4px !important;
        white-space: normal;
    }

    /* ── DATE HEADER ROWS ── */
    .th-month {
        background: #9ca3af;
        color: #fff;
        font-size: 10px;
        font-weight: 900;
        text-align: center;
        height: 22px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .th-day {
        background: #9ca3af;
        color: #fff;
        font-size: 8px;
        font-weight: 900;
        text-align: center;
        height: 20px;
        width: 26px;
        min-width: 26px;
    }

    .th-num {
        background: #e5e7eb;
        color: #475569;
        font-size: 8px;
        font-weight: 800;
        text-align: center;
        height: 18px;
        width: 26px;
        min-width: 26px;
    }

    .th-day.weekend,
    .th-num.weekend {
        background: #ef4444 !important;
        color: #fff !important;
    }

    /* ── INFO HEADER COLS ── */
    .col-info {
        background: #d1d5db;
        text-align: center;
        font-size: 8px;
        font-weight: 900;
        color: #374151;
        white-space: normal;
        line-height: 1.2;
        padding: 3px 2px !important;
        vertical-align: middle;
        width: 58px;
        min-width: 58px;
    }

    /* ── BODY: NO ── */
    .td-no {
        position: sticky;
        left: 0;
        z-index: 6;
        background: #fff;
        text-align: center;
        font-size: 10px;
        color: #6b7280;
        font-weight: 700;
        width: 36px;
        min-width: 36px;
    }

    /* ── BODY: KEGIATAN ──
   Key fix: teks tidak strikethrough, tidak abu.
   Badge ditampilkan sebagai chip kecil di pojok kiri atas.
   Row height dikontrol hanya dari .td-day/.td-block height. ── */
    .td-kegiatan {
        position: sticky;
        left: 36px;
        z-index: 6;
        background: #fff;
        padding: 5px 8px !important;
        font-size: 10px;
        font-weight: 700;
        color: #111827;
        line-height: 1.3;
        width: 280px;
        min-width: 280px;
        white-space: normal;
        word-break: break-word;
        cursor: pointer;
    }

    .td-kegiatan:hover {
        background: #f8fafc !important;
    }

    /* status badge chip – kecil, di atas teks */
    .status-chip {
        display: inline-block;
        font-size: 7.5px;
        font-weight: 900;
        padding: 1px 5px;
        border-radius: 3px;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 3px;
        line-height: 1.5;
    }

    .chip-done {
        background: #e2e8f0;
        color: #64748b;
    }

    .chip-cancel {
        background: #fee2e2;
        color: #ef4444;
    }

    /* kegiatan dibatalkan: teks coret dan abu */
    .td-kegiatan.is-cancelled .keg-name {
        text-decoration: line-through;
        color: #9ca3af;
    }

    /* ── BODY: PNY ── */
    .td-pny {
        position: sticky;
        left: 316px;
        z-index: 6;
        width: 88px;
        min-width: 88px;
        text-align: center;
        font-size: 9px;
        font-weight: 800;
        padding: 4px !important;
        white-space: normal;
        cursor: pointer;
    }

    .pny-badge {
        display: inline-block;
        border-radius: 5px;
        padding: 3px 7px;
        font-size: 9px;
        font-weight: 800;
        line-height: 1.2;
    }

    /* ── BODY: INFO COLS ── */
    .td-info {
        text-align: center;
        font-size: 9px;
        color: #374151;
        font-weight: 700;
        padding: 2px 3px !important;
        white-space: normal;
        line-height: 1.2;
        width: 58px;
        min-width: 58px;
    }

    /* ── DAY CELLS ── */
    .td-day {
        width: 26px;
        min-width: 26px;
        height: 44px;
        /* controls row height */
        background: #fff;
        cursor: pointer;
    }

    .td-day:hover {
        background: #f0f9ff;
    }

    /* ── EVENT BLOCKS ── */
    .td-block {
        width: 26px;
        min-width: 26px;
        height: 44px;
        cursor: pointer;
        transition: filter .1s;
    }

    .td-block:hover {
        filter: brightness(.88);
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

    /* selesai: same color, just faded */
    .c-selesai {
        opacity: .45;
    }

    .c-bentrok {
        outline: 2px solid #ef4444;
        outline-offset: -2px;
    }

    /* row hover tint (only non-sticky) */
    .tt-table tbody tr:hover .td-day {
        background: #f0f9ff;
    }

    .tt-table tbody tr:hover .td-info {
        background: #f8fafc;
    }

    /* ── EMPTY ── */
    .tt-empty {
        padding: 56px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
        font-weight: 700;
    }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar {
        width: 5px;
        height: 5px;
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
        max-height: 92vh;
        overflow-y: auto;
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

    @media (max-width: 900px) {
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
            padding: 10px 14px 8px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tt-legend {
            padding: 6px 14px;
            gap: 10px;
        }

        .col-kegiatan,
        .td-kegiatan {
            width: 200px !important;
            min-width: 200px !important;
        }

        .col-pny,
        .td-pny {
            left: 236px !important;
        }
    }

    /* =========================================================
   FULL FINAL CLEAN TIMETABLE
   Desktop: tabel clean.
   Mobile/tablet: kalender + card list rapi.
   Fix warning line-clamp.
   ========================================================= */
    :root {
        --hdr-h: 66px !important;
        --bg: #f4f8fc;
        --blue: #0284c7;
        --blue-soft: #eff8ff;
        --line: #dbeafe;
        --line-soft: #edf2f7;
        --text: #0f172a;
        --muted: #94a3b8;
    }

    html,
    body {
        background: var(--bg) !important;
        overflow: hidden !important;
    }

    /* ===== DESKTOP HEADER ===== */
    .top-bar {
        height: var(--hdr-h) !important;
        padding: 8px 12px !important;
        background: var(--bg) !important;
        border: 0 !important;
        box-shadow: none !important;
    }

    .top-bar::before {
        content: "";
        position: absolute;
        inset: 8px 10px;
        background: #fff;
        border: 1px solid #e0f2fe;
        border-radius: 20px;
        box-shadow: 0 10px 26px rgba(15, 23, 42, .045);
        z-index: -1;
    }

    .top-bar-back,
    .top-bar-icon {
        width: 40px !important;
        height: 40px !important;
        background: #eff8ff !important;
        color: #0284c7 !important;
        box-shadow: none !important;
    }

    .top-bar-title {
        font-size: 17px !important;
        font-weight: 900 !important;
        color: #0284c7 !important;
        line-height: 1.1 !important;
    }

    .top-bar-sub {
        margin-top: 3px !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        color: #94a3b8 !important;
    }

    /* ===== DESKTOP PAGE ===== */
    .tt-page {
        top: var(--hdr-h) !important;
        bottom: 0 !important;
        height: calc(100vh - var(--hdr-h)) !important;
        padding: 10px 8px 18px !important;
        background: var(--bg) !important;
    }

    .tt-meta {
        flex-shrink: 0 !important;
        padding: 15px 18px 13px !important;
        background: #fff !important;
        border: 1px solid var(--line) !important;
        border-bottom: 0 !important;
        border-radius: 24px 24px 0 0 !important;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .04) !important;
    }

    .tt-meta-title {
        font-size: 16px !important;
        line-height: 1.15 !important;
        font-weight: 900 !important;
        color: #0f172a !important;
        letter-spacing: .04em !important;
    }

    .tt-meta-sub {
        margin-top: 5px !important;
        color: #94a3b8 !important;
        font-size: 11px !important;
        font-weight: 800 !important;
    }

    .tt-nav {
        gap: 7px !important;
        flex-wrap: wrap !important;
        justify-content: flex-end !important;
    }

    .tt-nav-sep {
        display: none !important
    }

    .tt-nav-btn {
        height: 36px !important;
        padding: 0 13px !important;
        background: #fff !important;
        border: 1px solid #bfdbfe !important;
        border-radius: 999px !important;
        color: #0369a1 !important;
        font-size: 11px !important;
        font-weight: 900 !important;
        box-shadow: none !important;
    }

    .tt-nav-btn:hover {
        background: #eff8ff !important
    }

    .tt-legend {
        flex-shrink: 0 !important;
        padding: 11px 16px !important;
        gap: 9px !important;
        background: #f8fbff !important;
        border-left: 1px solid var(--line) !important;
        border-right: 1px solid var(--line) !important;
        border-bottom: 1px solid var(--line) !important;
    }

    .legend-item {
        height: 29px !important;
        padding: 0 11px !important;
        background: #fff !important;
        border: 1px solid var(--line) !important;
        border-radius: 999px !important;
        color: #334155 !important;
        font-size: 10px !important;
        font-weight: 900 !important;
    }

    .legend-dot {
        width: 12px !important;
        height: 12px !important;
    }

    .tt-scroll {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 0 22px !important;
        overflow: auto !important;
        background: #fff !important;
        border-left: 1px solid var(--line) !important;
        border-right: 1px solid var(--line) !important;
        border-bottom: 1px solid var(--line) !important;
        border-radius: 0 0 20px 20px !important;
        box-shadow: 0 18px 35px rgba(15, 23, 42, .04) !important;
    }

    /* ===== DESKTOP TABLE ===== */
    .tt-table {
        width: max-content !important;
        min-width: 100% !important;
        margin: 0 !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        table-layout: fixed !important;
        background: #fff !important;
    }

    .tt-table th,
    .tt-table td {
        border: 0 !important;
        border-right: 1px solid #d9e3ef !important;
        border-bottom: 1px solid #d9e3ef !important;
        padding: 0 !important;
        box-sizing: border-box !important;
        vertical-align: middle !important;
    }

    .tt-table thead th {
        background: #fff !important;
        color: #0f172a !important;
        border-top: 1px solid #d9e3ef !important;
    }

    .col-no,
    .col-kegiatan,
    .col-pny,
    .col-info {
        background: #fff !important;
        color: #0f172a !important;
        font-weight: 900 !important;
    }

    .col-no,
    .td-no {
        width: 38px !important;
        min-width: 38px !important;
        left: 0 !important
    }

    .col-kegiatan,
    .td-kegiatan {
        width: 286px !important;
        min-width: 286px !important;
        left: 38px !important
    }

    .col-pny,
    .td-pny {
        width: 96px !important;
        min-width: 96px !important;
        left: 324px !important
    }

    .col-info,
    .td-info {
        width: 64px !important;
        min-width: 64px !important
    }

    .col-no,
    .col-kegiatan,
    .col-pny {
        z-index: 32 !important
    }

    .td-no,
    .td-kegiatan,
    .td-pny {
        z-index: 14 !important;
        background: #fff !important;
        box-shadow: 1px 0 0 #d9e3ef !important;
    }

    .th-month {
        height: 24px !important;
        color: #0284c7 !important;
        font-size: 11px !important;
        font-weight: 900 !important;
    }

    .th-day,
    .th-num,
    .td-day,
    .td-block {
        width: 28px !important;
        min-width: 28px !important;
        max-width: 28px !important;
    }

    .th-day {
        height: 22px !important;
        color: #64748b !important;
        background: #fff !important;
        font-size: 8px !important;
        font-weight: 900 !important;
    }

    .th-num {
        height: 21px !important;
        color: #0f172a !important;
        background: #fff !important;
        font-size: 9px !important;
        font-weight: 900 !important;
    }

    .th-day.weekend,
    .th-num.weekend {
        background: #fff7f7 !important;
        color: #ef4444 !important;
    }

    .td-no {
        color: #64748b !important;
        font-size: 10px !important;
        font-weight: 900 !important;
        text-align: center !important;
    }

    .td-kegiatan {
        padding: 6px 9px !important;
        color: #0f172a !important;
        font-size: 9.5px !important;
        line-height: 1.24 !important;
        font-weight: 900 !important;
    }

    .td-pny {
        text-align: center !important;
        font-size: 9px !important;
        font-weight: 900 !important;
        color: #0f172a !important;
    }

    .pny-badge {
        font-size: 9px !important;
        font-weight: 900 !important;
        color: #0f172a !important;
        text-shadow: none !important;
    }

    .td-info {
        padding: 3px 4px !important;
        background: #fff !important;
        color: #1e293b !important;
        text-align: center !important;
        font-size: 9px !important;
        line-height: 1.18 !important;
        font-weight: 700 !important;
    }

    .td-day,
    .td-block {
        height: 42px !important;
        min-height: 42px !important;
    }

    .td-day {
        background: #fff !important
    }

    .td-day:hover,
    .tt-table tbody tr:hover .td-day {
        background: #fbfdff !important
    }

    /* Event colors */
    .c-menpim {
        background: #fff200 !important
    }

    .c-teknis {
        background: linear-gradient(135deg, #2fa864, #66bf83) !important
    }

    .c-kerjasama {
        background: linear-gradient(135deg, #5da2e3, #86bdec) !important
    }

    .c-pustrajak {
        background: linear-gradient(135deg, #ff8a18, #f2c98f) !important
    }

    .c-default {
        background: #b9c3cf !important
    }

    .c-cancelled {
        background: repeating-linear-gradient(45deg, #ffd0d0 0, #ffd0d0 7px, #ffe7e7 7px, #ffe7e7 14px) !important;
    }

    .c-selesai {
        opacity: .48 !important;
        filter: grayscale(80%) !important;
    }

    .c-bentrok {
        outline: 2px solid #ef4444 !important;
        outline-offset: -2px !important;
    }

    /* Penyelenggara color */
    .td-pny.pny-menpim {
        background: #fff200 !important
    }

    .td-pny.pny-teknis {
        background: linear-gradient(135deg, #2fa864, #66bf83) !important
    }

    .td-pny.pny-kerjasama {
        background: linear-gradient(135deg, #5da2e3, #86bdec) !important
    }

    .td-pny.pny-pustrajak {
        background: linear-gradient(135deg, #ff8a18, #f2c98f) !important
    }

    .td-pny.pny-default {
        background: #b9c3cf !important
    }

    .td-pny.pny-selesai {
        background: #dfe5ec !important;
        color: #64748b !important;
    }

    .td-pny.pny-selesai .pny-badge {
        color: #64748b !important
    }

    .td-pny.pny-cancelled {
        background: repeating-linear-gradient(45deg, #ffd0d0 0, #ffd0d0 7px, #ffe7e7 7px, #ffe7e7 14px) !important;
    }

    .td-pny.pny-cancelled .pny-badge {
        color: #94a3b8 !important;
        text-decoration: line-through !important;
        text-decoration-thickness: 2px !important;
    }

    .status-chip {
        display: inline-flex !important;
        align-items: center !important;
        width: max-content !important;
        margin-bottom: 4px !important;
        padding: 2px 7px !important;
        border-radius: 7px !important;
        font-size: 7.5px !important;
        line-height: 1.35 !important;
        font-weight: 900 !important;
        letter-spacing: .04em !important;
        text-transform: uppercase !important;
    }

    .chip-done {
        background: #e2e8f0 !important;
        color: #64748b !important
    }

    .chip-cancel {
        background: #fee2e2 !important;
        color: #ef4444 !important
    }

    .td-kegiatan.is-cancelled .keg-name,
    .keg-name[style*="line-through"] {
        color: #9ca3af !important;
        text-decoration: line-through !important;
        text-decoration-thickness: 2px !important;
    }

    /* Modal / export */
    .modal-wrap {
        backdrop-filter: blur(7px) !important
    }

    .modal-box {
        border-radius: 30px !important;
        border: 1px solid #e0f2fe !important
    }

    .form-input {
        border-radius: 18px !important;
        background: #f8fafc !important;
        font-weight: 700 !important
    }

    .btn-primary,
    .btn-danger,
    .btn-warning,
    .btn-success {
        border-radius: 18px !important
    }

    #exportModal {
        z-index: 1200 !important
    }

    #exportModal .modal-box {
        position: relative !important;
        z-index: 1201 !important
    }

    #stokModal {
        z-index: 1100 !important
    }

    .toast {
        z-index: 1300 !important
    }

    .fab {
        width: 54px !important;
        height: 54px !important;
        right: 24px !important;
        bottom: 24px !important;
        border-radius: 50% !important;
        background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
        box-shadow: 0 16px 30px rgba(2, 132, 199, .32) !important;
    }

    /* ===== MOBILE / TABLET ===== */
    .tt-mobile-calendar,
    .tt-mobile-list {
        display: none
    }

    @media(max-width:900px) {

        html,
        body {
            overflow-x: hidden !important;
            overflow-y: auto !important;
            height: auto !important;
            background: #fff !important;
        }

        .top-bar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 73px !important;
            padding: 16px 20px 0 !important;
            background: #fff !important;
            border: 0 !important;
            border-bottom: 1px solid #f1f5f9 !important;
            box-shadow: none !important;
            z-index: 50 !important;
        }

        .top-bar::before {
            display: none !important
        }

        .top-bar-left {
            gap: 12px !important;
            min-width: 0 !important
        }

        .top-bar-back {
            width: 40px !important;
            height: 40px !important;
            border-radius: 999px !important;
            background: #f0f9ff !important;
            color: #0284c7 !important;
            border: 0 !important;
        }

        .top-bar-icon {
            position: absolute !important;
            top: 20px !important;
            right: 16px !important;
            width: 44px !important;
            height: 44px !important;
            border-radius: 999px !important;
            background: transparent !important;
            border: 0 !important;
            color: #0284c7 !important;
            font-size: 18px !important;
        }

        .top-bar-title {
            font-size: 17px !important;
            line-height: 1.15 !important;
            color: #0284c7 !important;
            text-transform: none !important;
            letter-spacing: 0 !important;
        }

        .top-bar-sub {
            margin-top: 1px !important;
            font-size: 12px !important;
            line-height: 1.1 !important;
            font-weight: 500 !important;
            color: #9ca3af !important;
            text-transform: none !important;
            letter-spacing: 0 !important;
        }

        .tt-page {
            position: static !important;
            height: auto !important;
            min-height: 100vh !important;
            padding: 73px 0 96px !important;
            display: block !important;
            background: #fff !important;
            overflow: visible !important;
        }

        .tt-meta {
            margin: 0 !important;
            padding: 18px 24px 10px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 12px !important;
            background: #fff !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .tt-meta-title {
            font-size: 12px !important;
            color: #1e293b !important;
            text-transform: uppercase !important;
            letter-spacing: .12em !important;
        }

        .tt-meta-sub {
            margin-top: 4px !important;
            font-size: 10px !important;
            color: #0284c7 !important;
            font-weight: 900 !important;
        }

        .tt-nav {
            display: flex !important;
            align-items: center !important;
            gap: 2px !important;
            width: auto !important;
            flex: 0 0 auto !important;
        }

        .tt-nav-btn {
            width: 34px !important;
            min-width: 34px !important;
            height: 34px !important;
            padding: 0 !important;
            border: 0 !important;
            background: transparent !important;
            color: #94a3b8 !important;
            border-radius: 999px !important;
            font-size: 0 !important;
        }

        .tt-nav-btn i {
            font-size: 12px !important
        }

        .tt-nav-btn:nth-child(3) {
            width: auto !important;
            min-width: auto !important;
            padding: 0 10px !important;
            font-size: 10px !important;
            background: #f1f5f9 !important;
            color: #64748b !important;
            font-weight: 900 !important;
        }

        .tt-legend,
        .tt-scroll {
            display: none !important
        }

        .tt-mobile-calendar {
            display: block !important;
            margin: 0 20px 18px !important;
            padding: 24px !important;
            background: #fff !important;
            border: 1px solid #f1f5f9 !important;
            border-radius: 32px !important;
            box-shadow: 0 1px 10px rgba(15, 23, 42, .025) !important;
        }

        .mobile-cal-head {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 22px !important;
        }

        .mobile-cal-title {
            font-size: 12px !important;
            font-weight: 900 !important;
            color: #1e293b !important;
            text-transform: uppercase !important;
            letter-spacing: .16em !important;
        }

        .mobile-cal-reset {
            border: 0 !important;
            background: #f1f5f9 !important;
            color: #64748b !important;
            border-radius: 999px !important;
            padding: 8px 12px !important;
            font-size: 10px !important;
            font-weight: 900 !important;
        }

        .mobile-cal-week,
        .mobile-cal-grid {
            display: grid !important;
            grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
            gap: 6px !important;
        }

        .mobile-cal-week {
            margin-bottom: 8px !important
        }

        .mobile-cal-week span {
            text-align: center !important;
            color: #cbd5e1 !important;
            font-size: 8px !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
        }

        .mobile-cal-day {
            aspect-ratio: 1 !important;
            height: auto !important;
            border: 0 !important;
            border-radius: 14px !important;
            background: #f8fafc !important;
            color: #94a3b8 !important;
            font-size: 11px !important;
            font-weight: 900 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
            cursor: pointer !important;
        }

        .mobile-cal-day.empty {
            visibility: hidden !important
        }

        .mobile-cal-day.weekend {
            background: #fff7f7 !important;
            color: #ef4444 !important
        }

        .mobile-cal-day.has-event {
            background: #eff8ff !important;
            color: #0369a1 !important
        }

        .mobile-cal-day.has-event::after {
            content: "";
            position: absolute;
            bottom: 5px;
            left: 50%;
            width: 5px;
            height: 5px;
            transform: translateX(-50%);
            border-radius: 999px;
            background: var(--day-color, #0284c7);
        }

        .mobile-cal-day.selected {
            background: #0284c7 !important;
            color: #fff !important;
            box-shadow: 0 8px 20px rgba(2, 132, 199, .22) !important;
        }

        .mobile-cal-day.selected::after {
            background: #fff !important
        }

        .mobile-cal-day.today {
            box-shadow: 0 0 0 2px #0ea5e9 !important
        }

        .tt-mobile-list {
            display: block !important;
            padding: 0 24px 20px !important;
            background: #fff !important;
            border: 0 !important;
            overflow: visible !important;
        }

        .mobile-filter-note {
            margin: 0 0 16px !important;
            background: #f0f9ff !important;
            border: 1px solid #bae6fd !important;
            color: #0369a1 !important;
            border-radius: 18px !important;
            padding: 12px 14px !important;
            font-size: 11px !important;
            font-weight: 900 !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            gap: 8px !important;
        }

        .mobile-filter-note button {
            border: 0 !important;
            background: #fff !important;
            color: #0369a1 !important;
            border-radius: 999px !important;
            padding: 6px 10px !important;
            font-size: 10px !important;
            font-weight: 900 !important;
        }

        .mobile-agenda-card {
            position: relative !important;
            display: flex !important;
            gap: 16px !important;
            align-items: flex-start !important;
            background: #fff !important;
            border: 1px solid #f1f5f9 !important;
            border-radius: 34px !important;
            padding: 18px !important;
            margin-bottom: 16px !important;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .035) !important;
            overflow: hidden !important;
            cursor: pointer !important;
        }

        .mobile-agenda-card::before {
            display: none !important
        }

        .mobile-date-badge {
            width: 58px !important;
            height: 58px !important;
            border-radius: 20px !important;
            background: var(--cat-color, #0ea5e9) !important;
            color: #fff !important;
            flex: 0 0 auto !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 900 !important;
        }

        .mobile-date-badge .day {
            font-size: 16px !important;
            line-height: 1 !important;
        }

        .mobile-date-badge .mon {
            margin-top: 3px !important;
            font-size: 8px !important;
            text-transform: uppercase !important;
            opacity: .85 !important;
        }

        .mobile-agenda-content {
            flex: 1 1 auto !important;
            min-width: 0 !important;
        }

        .mobile-agenda-top {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            gap: 10px !important;
        }

        .mobile-agenda-title {
            margin: 0 !important;
            padding-right: 2px !important;
            color: #1e293b !important;
            font-size: 13px !important;
            font-weight: 900 !important;
            line-height: 1.35 !important;
            display: -webkit-box !important;
            -webkit-box-orient: vertical !important;
            -webkit-line-clamp: 2 !important;
            line-clamp: 2 !important;
            overflow: hidden !important;
        }

        .mobile-agenda-card.is-cancelled,
        .mobile-agenda-card.is-done {
            opacity: .74 !important;
            filter: grayscale(35%) !important;
        }

        .mobile-agenda-card.is-cancelled .mobile-agenda-title,
        .mobile-agenda-card.is-done .mobile-agenda-title {
            color: #94a3b8 !important;
            text-decoration: line-through !important;
            text-decoration-thickness: 2px !important;
        }

        .mobile-agenda-no {
            display: none !important
        }

        .mobile-agenda-date-line {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            margin-top: 8px !important;
            color: #94a3b8 !important;
            font-size: 10px !important;
            font-weight: 800 !important;
        }

        .mobile-agenda-meta {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 6px !important;
            margin-top: 9px !important;
        }

        .mobile-chip {
            display: inline-flex !important;
            align-items: center !important;
            height: 23px !important;
            padding: 0 8px !important;
            border-radius: 7px !important;
            background: #f1f5f9 !important;
            color: #64748b !important;
            border: 0 !important;
            font-size: 8px !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
            letter-spacing: .05em !important;
        }

        .mobile-chip.category {
            background: var(--cat-soft, #f1f5f9) !important;
            color: #334155 !important;
        }

        .mobile-chip.cancelled {
            background: #fee2e2 !important;
            color: #991b1b !important
        }

        .mobile-chip.done {
            background: #e2e8f0 !important;
            color: #94a3b8 !important
        }

        .mobile-agenda-grid {
            margin-top: 12px !important;
            padding-top: 12px !important;
            border-top: 1px solid #f1f5f9 !important;
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 8px 12px !important;
        }

        .mobile-mini {
            background: transparent !important;
            border: 0 !important;
            border-radius: 0 !important;
            padding: 0 !important;
            min-height: auto !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            min-width: 0 !important;
        }

        .mobile-mini::before {
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            width: 12px;
            color: #cbd5e1;
            font-size: 10px;
            flex: 0 0 auto;
        }

        .mobile-mini:nth-child(1)::before {
            content: "\f073"
        }

        .mobile-mini:nth-child(2)::before {
            content: "\f0c0"
        }

        .mobile-mini:nth-child(3)::before {
            content: "\f015"
        }

        .mobile-mini:nth-child(4)::before {
            content: "\f51c"
        }

        .mobile-mini:nth-child(5)::before {
            content: "\f2e7"
        }

        .mobile-mini-label {
            display: none !important
        }

        .mobile-mini-value {
            margin: 0 !important;
            color: #64748b !important;
            font-size: 10px !important;
            line-height: 1.2 !important;
            font-weight: 800 !important;
            word-break: break-word !important;
        }

        .mobile-agenda-empty {
            margin: 0 !important;
            padding: 40px 18px !important;
            background: #fff !important;
            border: 1px solid #f1f5f9 !important;
            border-radius: 34px !important;
            color: #cbd5e1 !important;
            text-align: center !important;
            font-size: 10px !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
            letter-spacing: .08em !important;
        }

        .fab {
            bottom: 30px !important;
            right: 28px !important;
            width: 52px !important;
            height: 52px !important;
            z-index: 40 !important;
        }

        .modal-wrap {
            align-items: flex-end !important;
            padding: 12px !important;
        }

        .modal-box {
            border-radius: 30px 30px 24px 24px !important;
            max-height: 92vh !important;
        }

        .form-grid {
            grid-template-columns: 1fr !important;
        }
    }


    /* =========================================================
   FULL FINAL UI V2 MOBILE CARDLIST
   Card mobile lebih rapi, rata kiri, padding konsisten.
   Desktop tidak diubah.
   ========================================================= */
    @media(max-width:900px) {
        .tt-mobile-list {
            padding: 0 18px 24px !important;
        }

        .mobile-agenda-card {
            border-radius: 30px !important;
            padding: 16px !important;
            gap: 14px !important;
            align-items: flex-start !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .045) !important;
        }

        .mobile-date-badge {
            width: 56px !important;
            height: 56px !important;
            border-radius: 19px !important;
            margin-top: 2px !important;
        }

        .mobile-agenda-content {
            width: 100% !important;
            min-width: 0 !important;
        }

        .mobile-agenda-title {
            font-size: 13px !important;
            line-height: 1.35 !important;
            font-weight: 900 !important;
            color: #0f172a !important;
            padding-right: 0 !important;
            text-align: left !important;
            display: -webkit-box !important;
            -webkit-box-orient: vertical !important;
            -webkit-line-clamp: 2 !important;
            line-clamp: 2 !important;
            overflow: hidden !important;
        }

        .mobile-agenda-date-line {
            margin-top: 8px !important;
            justify-content: flex-start !important;
            text-align: left !important;
            color: #94a3b8 !important;
            font-size: 10px !important;
            font-weight: 800 !important;
        }

        .mobile-agenda-meta {
            margin-top: 9px !important;
            justify-content: flex-start !important;
            gap: 6px !important;
        }

        .mobile-chip {
            height: 24px !important;
            padding: 0 9px !important;
            border-radius: 8px !important;
            font-size: 8px !important;
            line-height: 1 !important;
        }

        .mobile-agenda-grid {
            margin-top: 13px !important;
            padding-top: 13px !important;
            border-top: 1px solid #eef2f7 !important;
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 9px !important;
            width: 100% !important;
        }

        .mobile-mini {
            width: 100% !important;
            min-width: 0 !important;
            display: flex !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            gap: 10px !important;
            padding: 0 !important;
            background: transparent !important;
            border: 0 !important;
            text-align: left !important;
        }

        .mobile-mini::before {
            width: 17px !important;
            min-width: 17px !important;
            text-align: center !important;
            flex: 0 0 17px !important;
            color: #cbd5e1 !important;
            font-size: 11px !important;
            line-height: 1.35 !important;
            margin-top: 1px !important;
        }

        .mobile-mini-value {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            margin: 0 !important;
            text-align: left !important;
            color: #475569 !important;
            font-size: 11px !important;
            line-height: 1.35 !important;
            font-weight: 800 !important;
            word-break: break-word !important;
        }

        .mobile-filter-note {
            margin-left: 2px !important;
            margin-right: 2px !important;
            border-radius: 18px !important;
        }

        .tt-mobile-calendar {
            margin-left: 18px !important;
            margin-right: 18px !important;
            padding: 20px !important;
            border-radius: 28px !important;
        }

        .mobile-cal-day {
            border-radius: 12px !important;
        }

        .mobile-mini-peserta::before {
            content: "\f0c0" !important
        }

        .mobile-mini-asrama::before {
            content: "\f015" !important
        }

        .mobile-mini-kelas::before {
            content: "\f51c" !important
        }

        .mobile-mini-makan::before {
            content: "\f2e7" !important
        }
    }

    @media(max-width:390px) {
        .tt-mobile-list {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }

        .tt-mobile-calendar {
            margin-left: 14px !important;
            margin-right: 14px !important;
            padding: 18px !important;
        }

        .mobile-agenda-card {
            padding: 14px !important;
            gap: 12px !important;
        }

        .mobile-date-badge {
            width: 52px !important;
            height: 52px !important;
            border-radius: 18px !important;
        }

        .mobile-agenda-title {
            font-size: 12px !important;
        }

        .mobile-mini-value {
            font-size: 10.5px !important;
        }
    }


    /* =========================================================
   FULL FINAL UI V3 2 KOLOM CARD INFO
   Info card mobile: 2 kolom x 2 baris, rata kiri, clean.
   ========================================================= */
    @media(max-width:900px) {
        .mobile-agenda-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 0 !important;
            width: 100% !important;
            margin-top: 14px !important;
            padding-top: 0 !important;
            border-top: 1px solid #eef2f7 !important;
            border-left: 1px solid #eef2f7 !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            background: #fff !important;
        }

        .mobile-mini {
            min-height: 46px !important;
            width: 100% !important;
            min-width: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 9px !important;
            padding: 10px 11px !important;
            background: #fff !important;
            border: 0 !important;
            border-right: 1px solid #eef2f7 !important;
            border-bottom: 1px solid #eef2f7 !important;
            border-radius: 0 !important;
            text-align: left !important;
        }

        .mobile-mini::before {
            width: 16px !important;
            min-width: 16px !important;
            flex: 0 0 16px !important;
            text-align: center !important;
            color: #94a3b8 !important;
            font-size: 11px !important;
            line-height: 1 !important;
            margin: 0 !important;
        }

        .mobile-mini-value {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            margin: 0 !important;
            text-align: left !important;
            color: #475569 !important;
            font-size: 10.5px !important;
            line-height: 1.25 !important;
            font-weight: 800 !important;
            word-break: break-word !important;
            display: -webkit-box !important;
            -webkit-box-orient: vertical !important;
            -webkit-line-clamp: 2 !important;
            line-clamp: 2 !important;
            overflow: hidden !important;
        }

        .mobile-agenda-card {
            padding: 16px !important;
        }

        .mobile-agenda-date-line {
            margin-top: 8px !important;
            font-size: 10px !important;
            color: #94a3b8 !important;
        }

        .mobile-agenda-meta {
            margin-top: 9px !important;
        }

        /* Modal/input lebih mirip contoh */
        .modal-box {
            border-radius: 28px !important;
            padding: 22px !important;
        }

        .modal-title {
            font-size: 20px !important;
            font-weight: 900 !important;
            color: #0f172a !important;
        }

        .form-label {
            font-size: 12px !important;
            font-weight: 900 !important;
            color: #475569 !important;
        }

        .form-input,
        .form-select,
        .form-textarea {
            min-height: 50px !important;
            border-radius: 16px !important;
            border: 1px solid #e2e8f0 !important;
            background: #f8fafc !important;
            color: #475569 !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            padding: 12px 14px !important;
            box-shadow: none !important;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            background: #fff !important;
            border-color: #93c5fd !important;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, .08) !important;
            outline: 0 !important;
        }

        .btn-primary {
            min-height: 52px !important;
            border-radius: 16px !important;
            background: #0284c7 !important;
            color: #fff !important;
            font-size: 14px !important;
            font-weight: 900 !important;
        }
    }

    @media(max-width:390px) {
        .mobile-agenda-grid {
            border-radius: 15px !important;
        }

        .mobile-mini {
            min-height: 44px !important;
            padding: 9px 9px !important;
            gap: 7px !important;
        }

        .mobile-mini-value {
            font-size: 10px !important;
        }

        .mobile-mini::before {
            width: 14px !important;
            min-width: 14px !important;
            flex-basis: 14px !important;
            font-size: 10px !important;
        }
    }


    /* =========================================================
   FULL FINAL UI V4 INFO LEBAR CLEAN
   Info card mobile 2 kolom x 2 baris lebih lebar, mepet kiri,
   tulisan lebih terlihat, tanpa border grid yang kaku.
   ========================================================= */
    @media(max-width:900px) {
        .tt-mobile-list {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }

        .mobile-agenda-card {
            padding: 14px !important;
            gap: 12px !important;
            border-radius: 28px !important;
        }

        .mobile-date-badge {
            width: 50px !important;
            height: 50px !important;
            min-width: 50px !important;
            border-radius: 17px !important;
            margin-top: 1px !important;
        }

        .mobile-date-badge .day {
            font-size: 15px !important;
        }

        .mobile-date-badge .mon {
            font-size: 7.5px !important;
        }

        .mobile-agenda-content {
            min-width: 0 !important;
            width: calc(100% - 62px) !important;
        }

        .mobile-agenda-title {
            font-size: 12.5px !important;
            line-height: 1.32 !important;
        }

        .mobile-agenda-grid {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important;
            gap: 9px 10px !important;
            width: 100% !important;
            margin-top: 12px !important;
            padding-top: 12px !important;
            border-top: 1px solid #eef2f7 !important;
            border-left: 0 !important;
            border-right: 0 !important;
            border-bottom: 0 !important;
            border-radius: 0 !important;
            overflow: visible !important;
            background: transparent !important;
        }

        .mobile-mini {
            min-height: auto !important;
            width: 100% !important;
            min-width: 0 !important;
            display: flex !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            gap: 6px !important;
            padding: 0 !important;
            background: transparent !important;
            border: 0 !important;
            border-radius: 0 !important;
            text-align: left !important;
        }

        .mobile-mini::before {
            width: 13px !important;
            min-width: 13px !important;
            flex: 0 0 13px !important;
            text-align: center !important;
            color: #cbd5e1 !important;
            font-size: 10px !important;
            line-height: 1.25 !important;
            margin-top: 2px !important;
        }

        .mobile-mini-value {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            margin: 0 !important;
            text-align: left !important;
            color: #64748b !important;
            font-size: 10.5px !important;
            line-height: 1.25 !important;
            font-weight: 800 !important;
            word-break: break-word !important;
            white-space: normal !important;
            display: -webkit-box !important;
            -webkit-box-orient: vertical !important;
            -webkit-line-clamp: 2 !important;
            line-clamp: 2 !important;
            overflow: hidden !important;
        }

        .mobile-agenda-date-line {
            margin-top: 8px !important;
            font-size: 9.5px !important;
        }

        .mobile-chip {
            height: 22px !important;
            padding: 0 8px !important;
            font-size: 7.8px !important;
        }

        .tt-mobile-calendar {
            margin-left: 14px !important;
            margin-right: 14px !important;
        }
    }

    @media(max-width:390px) {
        .tt-mobile-list {
            padding-left: 10px !important;
            padding-right: 10px !important;
        }

        .tt-mobile-calendar {
            margin-left: 10px !important;
            margin-right: 10px !important;
        }

        .mobile-agenda-card {
            padding: 12px !important;
            gap: 10px !important;
        }

        .mobile-date-badge {
            width: 46px !important;
            height: 46px !important;
            min-width: 46px !important;
            border-radius: 16px !important;
        }

        .mobile-agenda-content {
            width: calc(100% - 56px) !important;
        }

        .mobile-agenda-grid {
            gap: 8px 8px !important;
        }

        .mobile-mini {
            gap: 5px !important;
        }

        .mobile-mini::before {
            width: 12px !important;
            min-width: 12px !important;
            flex-basis: 12px !important;
            font-size: 9.5px !important;
        }

        .mobile-mini-value {
            font-size: 9.8px !important;
            line-height: 1.22 !important;
        }
    }


    /* =========================================================
   FULL FINAL MODAL TAMBAH JADWAL STYLE
   Tampilan form tambah kegiatan seperti contoh:
   putih, rounded besar, input clean, 2 kolom.
   ========================================================= */
    .modal-wrap {
        background: rgba(15, 23, 42, .50) !important;
        backdrop-filter: blur(3px) !important;
        -webkit-backdrop-filter: blur(3px) !important;
        z-index: 1200 !important;
    }

    .modal-box {
        width: min(92vw, 520px) !important;
        max-height: 92vh !important;
        overflow-y: auto !important;
        background: #fff !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 28px !important;
        padding: 24px 26px !important;
        box-shadow: 0 28px 70px rgba(15, 23, 42, .22) !important;
    }

    .modal-head {
        display: flex !important;
        align-items: flex-start !important;
        justify-content: space-between !important;
        gap: 16px !important;
        margin-bottom: 22px !important;
    }

    .modal-title {
        margin: 0 !important;
        color: #0f172a !important;
        font-size: 21px !important;
        line-height: 1.15 !important;
        font-weight: 900 !important;
        letter-spacing: -.02em !important;
    }

    .modal-sub {
        margin-top: 4px !important;
        color: #64748b !important;
        font-size: 14px !important;
        line-height: 1.2 !important;
        font-weight: 600 !important;
    }

    .modal-close {
        width: 42px !important;
        height: 42px !important;
        flex: 0 0 auto !important;
        border: 0 !important;
        border-radius: 999px !important;
        background: #f1f5f9 !important;
        color: #334155 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 18px !important;
    }

    .modal-close:hover {
        background: #e2e8f0 !important;
    }

    .form-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 16px 18px !important;
    }

    .form-group {
        min-width: 0 !important;
    }

    .form-group.full {
        grid-column: 1 / -1 !important;
    }

    .form-label {
        display: block !important;
        margin-bottom: 8px !important;
        color: #475569 !important;
        font-size: 12px !important;
        line-height: 1.1 !important;
        font-weight: 900 !important;
    }

    .form-input,
    .form-select,
    .form-textarea {
        width: 100% !important;
        min-height: 54px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 17px !important;
        background: #f8fafc !important;
        color: #1e293b !important;
        padding: 13px 15px !important;
        font-size: 15px !important;
        font-weight: 700 !important;
        outline: 0 !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .8) !important;
        transition: border-color .18s ease, box-shadow .18s ease, background .18s ease !important;
    }

    .form-input::placeholder,
    .form-textarea::placeholder {
        color: #94a3b8 !important;
        font-weight: 600 !important;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        background: #fff !important;
        border-color: #93c5fd !important;
        box-shadow: 0 0 0 4px rgba(14, 165, 233, .10) !important;
    }

    .form-select {
        appearance: auto !important;
    }

    .form-textarea {
        min-height: 96px !important;
        resize: vertical !important;
    }

    .btn-primary {
        width: 100% !important;
        min-height: 58px !important;
        border: 0 !important;
        border-radius: 17px !important;
        background: #0284c7 !important;
        color: #fff !important;
        font-size: 15px !important;
        font-weight: 900 !important;
        box-shadow: 0 14px 28px rgba(2, 132, 199, .22) !important;
    }

    .btn-primary:hover {
        background: #0369a1 !important;
    }

    .btn-danger,
    .btn-warning,
    .btn-success {
        min-height: 48px !important;
        border-radius: 16px !important;
        font-weight: 900 !important;
    }

    @media(max-width:900px) {
        .modal-wrap {
            align-items: center !important;
            padding: 20px !important;
        }

        .modal-box {
            width: 100% !important;
            max-width: 420px !important;
            max-height: 92vh !important;
            border-radius: 26px !important;
            padding: 22px 20px 20px !important;
        }

        .modal-head {
            margin-bottom: 22px !important;
        }

        .modal-title {
            font-size: 18px !important;
        }

        .modal-sub {
            font-size: 12px !important;
        }

        .modal-close {
            width: 40px !important;
            height: 40px !important;
        }

        .form-grid {
            grid-template-columns: 1fr 1fr !important;
            gap: 16px 12px !important;
        }

        .form-input,
        .form-select,
        .form-textarea {
            min-height: 50px !important;
            border-radius: 16px !important;
            padding: 12px 14px !important;
            font-size: 14px !important;
        }

        .btn-primary {
            min-height: 54px !important;
            border-radius: 16px !important;
            margin-top: 2px !important;
        }
    }

    @media(max-width:380px) {
        .modal-wrap {
            padding: 14px !important;
        }

        .modal-box {
            padding: 20px 18px 18px !important;
        }

        .form-grid {
            gap: 14px 10px !important;
        }

        .form-label {
            font-size: 11px !important;
        }

        .form-input,
        .form-select {
            font-size: 13px !important;
            padding-left: 12px !important;
            padding-right: 12px !important;
        }
    }

    /* =========================================================
   FULL FINAL CLEAN HEADER SUPER APP
   Header mengikuti contoh: card putih rounded, soft shadow,
   icon back bulat kiri, judul biru, download kanan.
   ========================================================= */
    :root {
        --hdr-h: 88px !important;
        --bg: #f4f8fc !important;
        --header-card-radius: 24px !important;
    }

    html,
    body {
        background: var(--bg) !important;
    }

    .top-bar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        height: var(--hdr-h) !important;
        padding: 8px 10px !important;
        display: flex !important;
        align-items: stretch !important;
        justify-content: space-between !important;
        background: var(--bg) !important;
        border: 0 !important;
        box-shadow: none !important;
        z-index: 100 !important;
    }

    .top-bar::before {
        content: "" !important;
        position: absolute !important;
        inset: 6px 8px !important;
        background: #ffffff !important;
        border: 1px solid #eaf3fb !important;
        border-radius: var(--header-card-radius) !important;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .045) !important;
        z-index: -1 !important;
    }

    .top-bar-left {
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        gap: 14px !important;
        min-width: 0 !important;
        padding-left: 16px !important;
    }

    .top-bar-back {
        width: 44px !important;
        height: 44px !important;
        min-width: 44px !important;
        border-radius: 999px !important;
        border: 0 !important;
        background: #eef8ff !important;
        color: #0284c7 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: none !important;
        cursor: pointer !important;
    }

    .top-bar-back:hover {
        background: #e0f2fe !important;
    }

    .top-bar-back i {
        font-size: 15px !important;
    }

    .top-bar-title {
        margin: 0 !important;
        color: #0284c7 !important;
        font-size: 18px !important;
        line-height: 1.12 !important;
        font-weight: 900 !important;
        letter-spacing: -.02em !important;
        white-space: nowrap !important;
    }

    .top-bar-sub {
        margin-top: 3px !important;
        color: #94a3b8 !important;
        font-size: 12px !important;
        line-height: 1.1 !important;
        font-weight: 600 !important;
        white-space: nowrap !important;
    }

    .top-bar-right {
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        padding-right: 18px !important;
    }

    .top-bar-icon {
        width: 44px !important;
        height: 44px !important;
        border: 0 !important;
        border-radius: 999px !important;
        background: transparent !important;
        color: #0284c7 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: none !important;
        cursor: pointer !important;
        font-size: 20px !important;
    }

    .top-bar-icon:hover {
        background: #eef8ff !important;
    }

    .top-bar-icon i {
        font-size: 20px !important;
    }

    .tt-page {
        top: var(--hdr-h) !important;
        height: calc(100vh - var(--hdr-h)) !important;
        padding-top: 10px !important;
    }

    @media(max-width:900px) {
        :root {
            --hdr-h: 88px !important;
            --header-card-radius: 22px !important;
        }

        html,
        body {
            background: var(--bg) !important;
        }

        .top-bar {
            height: var(--hdr-h) !important;
            padding: 8px 8px !important;
            background: var(--bg) !important;
            border: 0 !important;
            box-shadow: none !important;
        }

        .top-bar::before {
            display: block !important;
            inset: 5px 8px !important;
            border-radius: 22px !important;
            background: #ffffff !important;
            border: 1px solid #eaf3fb !important;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .045) !important;
        }

        .top-bar-left {
            padding-left: 14px !important;
            gap: 12px !important;
        }

        .top-bar-back {
            width: 42px !important;
            height: 42px !important;
            min-width: 42px !important;
        }

        .top-bar-title {
            font-size: 17px !important;
            line-height: 1.12 !important;
        }

        .top-bar-sub {
            font-size: 11.5px !important;
            margin-top: 3px !important;
        }

        .top-bar-right {
            padding-right: 14px !important;
        }

        .top-bar-icon {
            position: static !important;
            width: 42px !important;
            height: 42px !important;
            font-size: 19px !important;
            background: transparent !important;
        }

        .top-bar-icon i {
            font-size: 19px !important;
        }

        .tt-page {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }
    }

    @media(max-width:380px) {
        .top-bar-left {
            padding-left: 10px !important;
            gap: 10px !important;
        }

        .top-bar-back,
        .top-bar-icon {
            width: 40px !important;
            height: 40px !important;
            min-width: 40px !important;
        }

        .top-bar-title {
            font-size: 16px !important;
        }

        .top-bar-sub {
            font-size: 11px !important;
        }

        .top-bar-right {
            padding-right: 10px !important;
        }
    }



    /* =========================================================
   FULL FINAL CLEAN TYPOGRAPHY BADGE END
   Font tabel lebih proporsional untuk semua kolom.
   Badge status diletakkan di akhir teks kegiatan.
   ========================================================= */

    /* Header tabel */
    .tt-table thead th,
    thead th {
        font-size: 11px !important;
        font-weight: 800 !important;
        letter-spacing: .15px !important;
    }

    /* Semua isi tabel */
    .tt-table tbody td,
    tbody td {
        font-size: 11.5px !important;
        line-height: 1.25 !important;
        vertical-align: middle !important;
    }

    /* Nomor */
    .td-no,
    tbody td:first-child {
        font-size: 11px !important;
        font-weight: 700 !important;
        color: #475569 !important;
    }

    /* Kegiatan */
    .td-kegiatan {
        font-size: 12px !important;
        line-height: 1.32 !important;
        font-weight: 800 !important;
        padding: 8px 10px !important;
    }

    /* Penyelenggara */
    .td-pny,
    .pny-badge {
        font-size: 11px !important;
        font-weight: 800 !important;
    }

    /* Kolom info: peserta, asrama, kelas, ruang makan */
    .td-info {
        font-size: 11px !important;
        line-height: 1.22 !important;
        font-weight: 700 !important;
        padding: 5px 6px !important;
    }

    /* Tinggi baris tetap lega, tapi tidak membesar berlebihan */
    .td-day,
    .td-block {
        height: 46px !important;
        min-height: 46px !important;
    }

    /* Layout teks kegiatan + badge di akhir */
    .keg-line {
        display: flex !important;
        align-items: flex-start !important;
        justify-content: space-between !important;
        gap: 8px !important;
        width: 100% !important;
    }

    .keg-name {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        display: block !important;
    }

    .keg-status-end {
        flex: 0 0 auto !important;
        align-self: flex-start !important;
        margin-left: 8px !important;
    }

    /* Badge lebih kecil dan soft */
    .status-chip {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: max-content !important;
        min-height: 18px !important;
        margin: 0 !important;
        padding: 3px 7px !important;
        border-radius: 999px !important;
        font-size: 7px !important;
        line-height: 1 !important;
        font-weight: 900 !important;
        letter-spacing: .04em !important;
        text-transform: uppercase !important;
        white-space: nowrap !important;
    }

    .chip-done {
        background: #e2e8f0 !important;
        color: #64748b !important;
    }

    .chip-cancel {
        background: #fee2e2 !important;
        color: #ef4444 !important;
    }

    /* Dibatalkan tetap coret teks, badge di kanan */
    .td-kegiatan.is-cancelled .keg-name,
    .keg-name[style*="line-through"] {
        color: #9ca3af !important;
        text-decoration: line-through !important;
        text-decoration-thickness: 2px !important;
    }

    /* Desktop kecil */
    @media(max-width:1280px) {

        .tt-table thead th,
        thead th {
            font-size: 10.5px !important;
        }

        .tt-table tbody td,
        tbody td {
            font-size: 11px !important;
        }

        .td-kegiatan {
            font-size: 11.5px !important;
        }

        .td-info,
        .td-pny,
        .pny-badge {
            font-size: 10.5px !important;
        }
    }

    /* Mobile card list tetap rapi */
    @media(max-width:900px) {
        .mobile-agenda-title {
            font-size: 12.5px !important;
            line-height: 1.32 !important;
        }

        .mobile-chip {
            font-size: 7.5px !important;
            height: 22px !important;
            padding: 0 8px !important;
        }

        .mobile-mini-value {
            font-size: 10px !important;
            line-height: 1.25 !important;
        }
    }


    /* =========================================================
   FULL FINAL MOBILE CALENDAR NOT CUT
   Kalender mobile/tablet tidak kepotong kanan-kiri.
   ========================================================= */
    @media(max-width:900px) {

        html,
        body {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
        }

        .tt-page {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            box-sizing: border-box !important;
        }

        .tt-mobile-calendar {
            width: auto !important;
            max-width: none !important;
            margin-left: 12px !important;
            margin-right: 12px !important;
            padding: 18px 16px !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
            border-radius: 26px !important;
        }

        .mobile-cal-head {
            gap: 10px !important;
            margin-bottom: 18px !important;
        }

        .mobile-cal-title {
            min-width: 0 !important;
            font-size: 12px !important;
            letter-spacing: .12em !important;
            white-space: nowrap !important;
        }

        .mobile-cal-reset {
            flex: 0 0 auto !important;
            padding: 8px 11px !important;
            font-size: 9px !important;
            white-space: nowrap !important;
        }

        .mobile-cal-week,
        .mobile-cal-grid {
            width: 100% !important;
            min-width: 0 !important;
            grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
            gap: 6px !important;
            box-sizing: border-box !important;
        }

        .mobile-cal-day {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            aspect-ratio: 1 / 1 !important;
            height: auto !important;
            border-radius: 13px !important;
            box-sizing: border-box !important;
            font-size: 11px !important;
        }

        .tt-mobile-list {
            width: 100% !important;
            max-width: 100% !important;
            padding-left: 12px !important;
            padding-right: 12px !important;
            box-sizing: border-box !important;
            overflow-x: hidden !important;
        }
    }

    @media(max-width:420px) {
        .tt-mobile-calendar {
            margin-left: 10px !important;
            margin-right: 10px !important;
            padding: 16px 14px !important;
            border-radius: 24px !important;
        }

        .mobile-cal-week,
        .mobile-cal-grid {
            gap: 5px !important;
        }

        .mobile-cal-day {
            border-radius: 12px !important;
            font-size: 10.5px !important;
        }

        .mobile-cal-week span {
            font-size: 7.5px !important;
        }

        .tt-mobile-list {
            padding-left: 10px !important;
            padding-right: 10px !important;
        }
    }

    @media(max-width:360px) {
        .tt-mobile-calendar {
            margin-left: 8px !important;
            margin-right: 8px !important;
            padding: 14px 12px !important;
        }

        .mobile-cal-week,
        .mobile-cal-grid {
            gap: 4px !important;
        }

        .mobile-cal-day {
            border-radius: 11px !important;
            font-size: 10px !important;
        }

        .mobile-cal-reset {
            padding: 7px 9px !important;
            font-size: 8px !important;
        }
    }


    /* =========================================================
   FULL FINAL MOBILE HEADER SPACING FIX
   Header mobile/tablet tidak menutupi kalender.
   ========================================================= */
    @media(max-width:900px) {
        :root {
            --hdr-h: 96px !important;
        }

        .top-bar {
            height: var(--hdr-h) !important;
            padding: 8px 8px !important;
            z-index: 200 !important;
        }

        .top-bar::before {
            inset: 6px 8px !important;
            border-radius: 22px !important;
        }

        .tt-page {
            position: relative !important;
            top: auto !important;
            height: auto !important;
            min-height: 100vh !important;
            padding-top: calc(var(--hdr-h) + 10px) !important;
            padding-bottom: 96px !important;
            margin-top: 0 !important;
            overflow: visible !important;
            background: var(--bg) !important;
        }

        .tt-meta {
            margin-top: 0 !important;
            border-radius: 24px 24px 0 0 !important;
        }

        .tt-mobile-calendar {
            margin-top: 0 !important;
        }
    }

    @media(max-width:420px) {
        :root {
            --hdr-h: 94px !important;
        }

        .tt-page {
            padding-top: calc(var(--hdr-h) + 8px) !important;
        }
    }

    @media(max-width:360px) {
        :root {
            --hdr-h: 92px !important;
        }

        .tt-page {
            padding-top: calc(var(--hdr-h) + 6px) !important;
        }
    }


    /* =========================================================
   FULL FINAL MOBILE CALENDAR FLAT LEGEND
   Kalender mobile/tablet menyatu dengan background,
   tanpa card rounded, plus legenda warna.
   ========================================================= */
    @media(max-width:900px) {
        .tt-mobile-calendar {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 8px 18px 18px !important;
            background: var(--bg) !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            overflow: visible !important;
            box-sizing: border-box !important;
        }

        .mobile-cal-head {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            gap: 12px !important;
            margin: 0 0 12px !important;
            padding: 0 !important;
        }

        .mobile-cal-title {
            color: #0f172a !important;
            font-size: 13px !important;
            font-weight: 900 !important;
            letter-spacing: .14em !important;
            line-height: 1.15 !important;
            text-transform: uppercase !important;
            white-space: nowrap !important;
        }

        .mobile-cal-reset {
            flex: 0 0 auto !important;
            border: 0 !important;
            background: #ffffff !important;
            color: #64748b !important;
            border-radius: 999px !important;
            padding: 8px 12px !important;
            font-size: 9px !important;
            font-weight: 900 !important;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .035) !important;
            white-space: nowrap !important;
        }

        .mobile-cal-legend {
            display: flex !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 7px !important;
            margin: 0 0 16px !important;
            padding: 0 !important;
        }

        .mobile-cal-legend-item {
            display: inline-flex !important;
            align-items: center !important;
            gap: 5px !important;
            min-height: 24px !important;
            padding: 0 9px !important;
            border-radius: 999px !important;
            background: #ffffff !important;
            border: 1px solid #e8f1fa !important;
            color: #475569 !important;
            font-size: 9px !important;
            line-height: 1 !important;
            font-weight: 900 !important;
            box-shadow: 0 3px 10px rgba(15, 23, 42, .025) !important;
        }

        .mobile-cal-legend-dot {
            width: 8px !important;
            height: 8px !important;
            min-width: 8px !important;
            border-radius: 999px !important;
            display: block !important;
        }

        .mobile-cal-legend-dot.menpim {
            background: #facc15 !important
        }

        .mobile-cal-legend-dot.teknis {
            background: #10b981 !important
        }

        .mobile-cal-legend-dot.kerjasama {
            background: #3b82f6 !important
        }

        .mobile-cal-legend-dot.pustrajak {
            background: #f97316 !important
        }

        .mobile-cal-legend-dot.bentrok {
            background: #ef4444 !important
        }

        .mobile-cal-legend-dot.selesai {
            background: #d1d5db !important;
            border: 1px solid #cbd5e1 !important;
        }

        .mobile-cal-legend-dot.dibatalkan {
            background: repeating-linear-gradient(45deg, #fee2e2 0, #fee2e2 3px, #fff 3px, #fff 6px) !important;
            border: 1px solid #ef4444 !important;
        }

        .mobile-cal-week,
        .mobile-cal-grid {
            width: 100% !important;
            display: grid !important;
            grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
            gap: 7px !important;
            box-sizing: border-box !important;
        }

        .mobile-cal-week {
            margin-bottom: 8px !important;
        }

        .mobile-cal-week span {
            color: #b8c5d5 !important;
            font-size: 8px !important;
            font-weight: 900 !important;
            text-align: center !important;
            text-transform: uppercase !important;
        }

        .mobile-cal-day {
            width: 100% !important;
            min-width: 0 !important;
            aspect-ratio: 1 / 1 !important;
            height: auto !important;
            border: 0 !important;
            border-radius: 15px !important;
            background: #ffffff !important;
            color: #0284c7 !important;
            font-size: 11px !important;
            font-weight: 900 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .035) !important;
        }

        .mobile-cal-day.empty {
            visibility: hidden !important;
        }

        .mobile-cal-day.weekend {
            background: #fff !important;
            color: #0284c7 !important;
        }

        .mobile-cal-day.has-event {
            background: #ffffff !important;
            color: #0284c7 !important;
        }

        .mobile-cal-day.has-event::after {
            content: "" !important;
            position: absolute !important;
            bottom: 6px !important;
            left: 50% !important;
            width: 5px !important;
            height: 5px !important;
            border-radius: 999px !important;
            transform: translateX(-50%) !important;
            background: var(--day-color, #0284c7) !important;
        }

        .mobile-cal-day.selected {
            background: #e0f2fe !important;
            color: #0369a1 !important;
            outline: 2px solid #0284c7 !important;
            outline-offset: 0 !important;
            box-shadow: 0 8px 20px rgba(2, 132, 199, .16) !important;
        }

        .mobile-cal-day.today {
            outline: 2px solid #0ea5e9 !important;
            outline-offset: 0 !important;
        }

        .tt-mobile-list {
            background: var(--bg) !important;
            padding-left: 14px !important;
            padding-right: 14px !important;
        }
    }

    @media(max-width:420px) {
        .tt-mobile-calendar {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }

        .mobile-cal-week,
        .mobile-cal-grid {
            gap: 6px !important;
        }

        .mobile-cal-day {
            border-radius: 14px !important;
            font-size: 10.5px !important;
        }

        .mobile-cal-legend {
            gap: 6px !important;
        }

        .mobile-cal-legend-item {
            min-height: 23px !important;
            padding: 0 8px !important;
            font-size: 8.5px !important;
        }
    }

    @media(max-width:360px) {
        .tt-mobile-calendar {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        .mobile-cal-week,
        .mobile-cal-grid {
            gap: 5px !important;
        }

        .mobile-cal-day {
            border-radius: 12px !important;
            font-size: 10px !important;
        }

        .mobile-cal-reset {
            padding: 7px 9px !important;
            font-size: 8px !important;
        }
    }


    /* =========================================================
   FULL FINAL BUTTON CENTER FIX
   Hanya merapikan posisi teks/icon tombol agar benar-benar tengah.
   Tidak mengubah logika dan tampilan utama.
   ========================================================= */

    /* Semua tombol utama modal */
    .btn-primary,
    .btn-danger,
    .btn-warning,
    .btn-success,
    #btnSubmit,
    #btnHapus,
    #btnBatalkan,
    #btnAktifkan,
    #exportModal button[onclick="downloadExport()"] {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;

        text-align: center !important;
        vertical-align: middle !important;
        line-height: 1 !important;

        padding-top: 0 !important;
        padding-bottom: 0 !important;

        white-space: nowrap !important;
    }

    /* Tinggi tombol agar teks tepat di tengah */
    .btn-primary,
    #btnSubmit,
    #exportModal button[onclick="downloadExport()"] {
        min-height: 56px !important;
        height: 56px !important;
    }

    .btn-danger,
    .btn-warning,
    .btn-success,
    #btnHapus,
    #btnBatalkan,
    #btnAktifkan {
        min-height: 48px !important;
        height: 48px !important;
    }

    /* Pastikan icon tidak menggeser baseline tulisan */
    .btn-primary i,
    .btn-danger i,
    .btn-warning i,
    .btn-success i,
    #btnSubmit i,
    #btnHapus i,
    #btnBatalkan i,
    #btnAktifkan i {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        line-height: 1 !important;
        margin: 0 !important;
        transform: none !important;
    }

    /* Kalau teks tombol dibungkus span tetap rata tengah */
    .btn-primary span,
    .btn-danger span,
    .btn-warning span,
    .btn-success span,
    #btnSubmit span,
    #btnHapus span,
    #btnBatalkan span,
    #btnAktifkan span {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        line-height: 1 !important;
    }

    /* Tombol close/edit bulat tetap icon center */
    .modal-close,
    .modal-edit-btn,
    .top-bar-back,
    .top-bar-icon,
    .tt-nav-btn,
    .mobile-cal-reset {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        line-height: 1 !important;
    }

    .modal-close i,
    .modal-edit-btn i,
    .top-bar-back i,
    .top-bar-icon i,
    .tt-nav-btn i {
        line-height: 1 !important;
        margin: 0 !important;
    }

    /* Mobile tetap proporsional */
    @media(max-width:900px) {

        .btn-primary,
        #btnSubmit,
        #exportModal button[onclick="downloadExport()"] {
            min-height: 54px !important;
            height: 54px !important;
        }

        .btn-danger,
        .btn-warning,
        .btn-success,
        #btnHapus,
        #btnBatalkan,
        #btnAktifkan {
            min-height: 46px !important;
            height: 46px !important;
        }
    }



    /* =========================================================
   FULL FINAL CRUD BUTTON LOGIC FORCE
   Hanya mengatur visibilitas tombol CRUD.
   ========================================================= */
    #stokModal #btnSubmit.hidden,
    #stokModal #btnHapus.hidden,
    #stokModal #btnBatalkan.hidden,
    #stokModal #btnAktifkan.hidden,
    #stokModal #btnEditTrigger.hidden {
        display: none !important;
    }

    #stokModal #btnSubmit,
    #stokModal #btnHapus,
    #stokModal #btnBatalkan,
    #stokModal #btnAktifkan {
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        gap: 8px !important;
        line-height: 1 !important;
    }
</style>

<!-- TOP HEADER BAR -->
<div class="top-bar">
    <div class="top-bar-left">
        <button type="button" class="top-bar-back" onclick="window.history.back()">
            <i class="fa-solid fa-arrow-left" style="font-size:13px"></i>
        </button>
        <div>
            <div class="top-bar-title">Timetable Kegiatan</div>
            <div class="top-bar-sub">Jadwal kegiatan Pusdiklat</div>
        </div>
    </div>
    <div class="top-bar-right">
        <button type="button" class="top-bar-icon" onclick="openExportModal()" title="Download">
            <i class="fa-solid fa-download"></i>
        </button>
    </div>
</div>

<div class="tt-page">
    <!-- TITLE + NAV -->
    <div class="tt-meta">
        <div>
            <div class="tt-meta-title" id="tt-title">Timetable</div>
            <div class="tt-meta-sub" id="tt-count">0 Agenda Bulan Ini</div>
        </div>
        <div class="tt-nav">
            <button class="tt-nav-btn" onclick="changeMonth(-1)">
                <i class="fa-solid fa-chevron-left" style="font-size:10px"></i> Sebelumnya
            </button>
            <span class="tt-nav-sep">|</span>
            <button class="tt-nav-btn" onclick="goThisMonth()">Bulan Ini</button>
            <span class="tt-nav-sep">|</span>
            <button class="tt-nav-btn" onclick="changeMonth(1)">
                Berikutnya <i class="fa-solid fa-chevron-right" style="font-size:10px"></i>
            </button>
        </div>
    </div>

    <!-- LEGEND -->
    <div class="tt-legend">
        <div class="legend-item"><span class="legend-dot" style="background:#ffff00;border:1px solid #c9c900"></span>Menpim</div>
        <div class="legend-item"><span class="legend-dot" style="background:#34a853"></span>Teknis</div>
        <div class="legend-item"><span class="legend-dot" style="background:#6fa8dc"></span>Kerjasama</div>
        <div class="legend-item"><span class="legend-dot" style="background:#ff9900"></span>Pustrajak</div>
        <div class="legend-item"><span class="legend-dot" style="background:#fecaca;border:2px dashed #ef4444"></span>Dibatalkan</div>
        <div class="legend-item"><span class="legend-dot" style="background:#94a3b8;opacity:.5"></span>Selesai</div>
        <div class="legend-item"><span class="legend-dot" style="background:#fff;border:2px solid #ef4444"></span>Bentrok</div>
    </div>

    <!-- TABLE -->
    <div class="tt-scroll">
        <table class="tt-table" id="tt-table"></table>
    </div>

    <!-- MOBILE/TABLET CALENDAR + CARD LIST -->
    <div class="tt-mobile-calendar" id="tt-mobile-calendar"></div>
    <div class="tt-mobile-list" id="tt-mobile-list"></div>
</div>

<?php if ($isAdmin): ?>
    <button class="fab" onclick="openModalTambah()" title="Tambah Jadwal">
        <i class="fa-solid fa-plus"></i>
    </button>
<?php endif; ?>

<!-- MODAL FORM -->
<div id="stokModal" class="modal-wrap" style="display:none">
    <div style="position:absolute;inset:0" onclick="closeModal()"></div>
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
                    <div><label class="form-label">Mulai</label><input id="f-start" type="date" class="form-input"></div>
                    <div><label class="form-label">Selesai</label><input id="f-end" type="date" class="form-input"></div>
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
                    <div><label class="form-label">Peserta</label><input id="f-peserta" type="number" class="form-input"></div>
                    <div><label class="form-label">Asrama</label><input id="f-asrama" type="text" class="form-input"></div>
                </div>
                <div class="form-grid">
                    <div><label class="form-label">Kelas</label><input id="f-kelas" type="text" class="form-input"></div>
                    <div><label class="form-label">Ruang Makan</label><input id="f-makan" type="text" class="form-input"></div>
                </div>
                <?php if ($isAdmin): ?>
                    <button id="btnSubmit" type="submit" class="hidden btn-primary" style="display:none">Simpan Jadwal</button>
                    <button id="btnHapus" type="button" onclick="handleDelete()" class="hidden btn-danger" style="display:none"><i class="fa-solid fa-trash-can"></i> Hapus Jadwal</button>
                    <button id="btnBatalkan" type="button" onclick="handleCancel()" class="hidden btn-warning" style="display:none"><i class="fa-solid fa-ban"></i> Batalkan Jadwal</button>
                    <button id="btnAktifkan" type="button" onclick="handleReactivate()" class="hidden btn-success" style="display:none"><i class="fa-solid fa-rotate-left"></i> Aktifkan Kembali</button>
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
            <button type="button" class="modal-close" onclick="closeExportModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="form-space">
            <div><label class="form-label">Dari Tanggal</label><input type="date" id="exportFrom" class="form-input"></div>
            <div><label class="form-label">Sampai Tanggal</label><input type="date" id="exportTo" class="form-input"></div>
            <button type="button" onclick="downloadExport()" class="btn-primary">Download PDF</button>
            <p style="text-align:center;font-size:10px;color:#94a3b8;margin:0">Default otomatis 30 hari terakhir</p>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

    let agendaData = [];
    let viewDate = new Date();
    let selectedMobileDate = null;
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

    function pnyTxt(pny) {
        return pny === 'Menpim' ? '#6b6200' : '#fff';
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

        renderMobileCalendar(list);
        renderMobileList(list, todayStr);
        const mName = new Intl.DateTimeFormat('id-ID', {
            month: 'long'
        }).format(viewDate).toUpperCase();

        /* HEAD */
        let h = `<thead>
    <tr>
        <th class="col-no"       rowspan="3">No.</th>
        <th class="col-kegiatan" rowspan="3">KEGIATAN</th>
        <th class="col-pny"      rowspan="3">PENYELENGGARA</th>
        <th class="th-month"     colspan="${days.length}">${mName}</th>
        <th class="col-info"     rowspan="3">JUMLAH<br>PESERTA</th>
        <th class="col-info"     rowspan="3">ASRAMA</th>
        <th class="col-info"     rowspan="3">KELAS</th>
        <th class="col-info"     rowspan="3">RUANG<br>MAKAN</th>
    </tr>
    <tr>${days.map(d=>`<th class="th-day${isWE(d)?' weekend':''}">${dayShort(d)}</th>`).join('')}</tr>
    <tr>${days.map(d=>`<th class="th-num${isWE(d)?' weekend':''}">${d.getDate()}</th>`).join('')}</tr>
    </thead><tbody>`;

        /* BODY */
        list.forEach((item, idx) => {
            const cancelled = item.status === 'cancelled';
            const done = !cancelled && item.end < todayStr;

            let blockCls = colorClass(item.pny);
            if (cancelled) blockCls += ' c-cancelled';
            if (done) blockCls += ' c-selesai';
            if (item.isBentrok) blockCls += ' c-bentrok';

            const pnyBg = colorHex(item.pny);
            const pnyTx = pnyTxt(item.pny);
            const pnyClass = {
                Menpim: 'pny-menpim',
                Teknis: 'pny-teknis',
                Kerjasama: 'pny-kerjasama',
                Pustrajak: 'pny-pustrajak'
            } [item.pny] || 'pny-default';

            /* status chip — kecil, sebelum nama, hanya untuk cancelled/done */
            const chipHtml = cancelled ?
                `<span class="status-chip chip-cancel">Dibatalkan</span>` :
                done ?
                `<span class="status-chip chip-done">Selesai</span>` :
                '';

            /* teks nama: coret hanya jika dibatalkan */
            const nameStyle = cancelled ? 'text-decoration:line-through;color:#9ca3af' : '';

            h += `<tr>
        <td class="td-no">${idx+1}</td>
        <td class="td-kegiatan ${cancelled ? 'is-cancelled' : ''}" onclick="openModalDetail(${item.id})">
            <div class="keg-line">
                <span class="keg-name" style="${nameStyle}">${esc(item.judul||'-')}</span>
                ${chipHtml ? `<span class="keg-status-end">${chipHtml}</span>` : ''}
            </div>
        </td>
        <td class="td-pny ${pnyClass} ${cancelled ? 'pny-cancelled' : ''} ${done ? 'pny-selesai' : ''}" onclick="openModalDetail(${item.id})">
            <span class="pny-badge">${esc(item.pny||'-')}</span>
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


    function mobileCategoryColor(pny) {
        return {
            Menpim: '#facc15',
            Teknis: '#10b981',
            Kerjasama: '#3b82f6',
            Pustrajak: '#f97316'
        } [pny] || '#94a3b8';
    }

    function mobileCategorySoft(pny) {
        return {
            Menpim: '#fef9c3',
            Teknis: '#dcfce7',
            Kerjasama: '#dbeafe',
            Pustrajak: '#ffedd5'
        } [pny] || '#f1f5f9';
    }

    function renderMobileCalendar(list) {
        const box = document.getElementById('tt-mobile-calendar');
        if (!box) return;

        const days = daysInView();
        const firstDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();
        const monthLabel = fmtMonth(viewDate).toUpperCase();
        const eventMap = {};

        list.forEach(item => {
            const s = parseDate(item.start);
            const e = parseDate(item.end);
            for (let d = new Date(s); d <= e; d = addDays(d, 1)) {
                const ds = toStr(d);
                if (!eventMap[ds]) eventMap[ds] = [];
                eventMap[ds].push(item);
            }
        });

        const blanks = Array.from({
            length: firstDay
        }, () => `<button type="button" class="mobile-cal-day empty"></button>`).join('');

        const buttons = days.map(d => {
            const ds = toStr(d);
            const evs = eventMap[ds] || [];
            const first = evs[0];
            let color = first ? mobileCategoryColor(first.pny) : '#0284c7';

            if (evs.some(e => e.isBentrok)) color = '#ef4444';
            if (first && first.status === 'cancelled') color = '#ef4444';
            if (first && first.status !== 'cancelled' && first.end < toStr(new Date())) color = '#d1d5db';

            const cls = [
                'mobile-cal-day',
                isWE(d) ? 'weekend' : '',
                evs.length ? 'has-event' : '',
                selectedMobileDate === ds ? 'selected' : '',
                ds === toStr(new Date()) ? 'today' : ''
            ].filter(Boolean).join(' ');

            return `<button type="button" class="${cls}" style="--day-color:${color}" onclick="selectMobileDate('${ds}')">${d.getDate()}</button>`;
        }).join('');

        box.innerHTML = `
            <div class="mobile-cal-head">
                <div>
                    <div class="mobile-cal-title">${monthLabel}</div>
                </div>
                <button type="button" class="mobile-cal-reset" onclick="resetMobileDate()">Semua Agenda</button>
            </div>

            <div class="mobile-cal-legend">
                <span class="mobile-cal-legend-item"><i class="mobile-cal-legend-dot menpim"></i>Menpim</span>
                <span class="mobile-cal-legend-item"><i class="mobile-cal-legend-dot teknis"></i>Teknis</span>
                <span class="mobile-cal-legend-item"><i class="mobile-cal-legend-dot kerjasama"></i>Kerjasama</span>
                <span class="mobile-cal-legend-item"><i class="mobile-cal-legend-dot pustrajak"></i>Pustrajak</span>
                <span class="mobile-cal-legend-item"><i class="mobile-cal-legend-dot bentrok"></i>Bentrok</span>
                <span class="mobile-cal-legend-item"><i class="mobile-cal-legend-dot selesai"></i>Selesai</span>
                <span class="mobile-cal-legend-item"><i class="mobile-cal-legend-dot dibatalkan"></i>Dibatalkan</span>
            </div>

            <div class="mobile-cal-week">
                <span>Min</span><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span>
            </div>
            <div class="mobile-cal-grid">${blanks}${buttons}</div>
        `;
    }

    function selectMobileDate(dateStr) {
        selectedMobileDate = dateStr;
        render();
    }

    function resetMobileDate() {
        selectedMobileDate = null;
        render();
    }

    function renderMobileList(list, todayStr = toStr(new Date())) {
        const box = document.getElementById('tt-mobile-list');
        if (!box) return;

        const displayList = selectedMobileDate ?
            list.filter(item => selectedMobileDate >= item.start && selectedMobileDate <= item.end) :
            list;

        if (!displayList.length) {
            box.innerHTML = `<div class="mobile-agenda-empty">Tidak ada agenda pada ${selectedMobileDate || 'bulan ini'}</div>`;
            return;
        }

        const note = selectedMobileDate ?
            `<div class="mobile-filter-note"><span>Agenda tanggal ${selectedMobileDate}</span><button type="button" onclick="resetMobileDate()">Reset</button></div>` :
            '';

        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

        box.innerHTML = note + displayList.map((item, idx) => {
            const cancelled = item.status === 'cancelled';
            const done = !cancelled && item.end < todayStr;
            const color = mobileCategoryColor(item.pny);
            const soft = mobileCategorySoft(item.pny);
            const d = parseDate(item.start);
            const day = String(d.getDate()).padStart(2, '0');
            const mon = monthNames[d.getMonth()];

            const statusChip = cancelled ?
                `<span class="mobile-chip cancelled">Dibatalkan</span>` :
                done ?
                `<span class="mobile-chip done">Selesai</span>` :
                '';

            const bentrokChip = item.isBentrok ? `<span class="mobile-chip cancelled">Bentrok</span>` : '';

            return `
                <div class="mobile-agenda-card ${cancelled ? 'is-cancelled' : ''} ${done ? 'is-done' : ''}"
                     style="--cat-color:${color};--cat-soft:${soft}"
                     onclick="openModalDetail(${item.id})">

                    <div class="mobile-date-badge">
                        <span class="day">${day}</span>
                        <span class="mon">${mon}</span>
                    </div>

                    <div class="mobile-agenda-content">
                        <div class="mobile-agenda-top">
                            <h3 class="mobile-agenda-title">${esc(item.judul || '-')}</h3>
                            <span class="mobile-agenda-no">${idx + 1}</span>
                        </div>

                        <div class="mobile-agenda-date-line">
                            <i class="fa-regular fa-calendar-check"></i>
                            <span>${esc(item.start || '-')} — ${esc(item.end || '-')}</span>
                        </div>

                        <div class="mobile-agenda-meta">
                            <span class="mobile-chip category">${esc(item.pny || '-')}</span>
                            ${statusChip}
                            ${bentrokChip}
                        </div>

                        <div class="mobile-agenda-grid">
                            <div class="mobile-mini mobile-mini-peserta">
                                <span class="mobile-mini-label">Peserta</span>
                                <span class="mobile-mini-value">${esc(item.peserta || '0')} Peserta</span>
                            </div>
                            <div class="mobile-mini mobile-mini-asrama">
                                <span class="mobile-mini-label">Asrama</span>
                                <span class="mobile-mini-value">${esc(item.asrama || '-')}</span>
                            </div>
                            <div class="mobile-mini mobile-mini-kelas">
                                <span class="mobile-mini-label">Kelas</span>
                                <span class="mobile-mini-value">${esc(item.kelas || '-')}</span>
                            </div>
                            <div class="mobile-mini mobile-mini-makan">
                                <span class="mobile-mini-label">Ruang Makan</span>
                                <span class="mobile-mini-value">${esc(item.makan || '-')}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }


    /* ── nav ── */
    function changeMonth(d) {
        viewDate.setMonth(viewDate.getMonth() + d);
        selectedMobileDate = null;
        render();
    }

    function goThisMonth() {
        viewDate = new Date();
        selectedMobileDate = null;
        render();
    }

    /* ── modal helpers ── */
    function showBtn(id, txt) {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.display = 'block';
        if (txt) el.textContent = txt;
    }

    function showFlex(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'flex';
    }

    function hideBtn(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    function toggleInputs(dis) {
        ['f-judul', 'f-start', 'f-end', 'f-pny', 'f-peserta', 'f-asrama', 'f-kelas', 'f-makan'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.disabled = dis;
            el.style.opacity = dis ? '.65' : '1';
        });
    }

    function showModal() {
        document.getElementById('stokModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('stokModal').style.display = 'none';
        document.body.style.overflow = '';
    }









    function crudEl(id) {
        return document.getElementById(id);
    }

    function crudHide(id) {
        const el = crudEl(id);
        if (!el) return;
        el.classList.add('hidden');
        el.style.display = 'none';
    }

    function crudShow(id, display = 'flex') {
        const el = crudEl(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.style.display = display;
    }

    function resetCrudButtons() {
        crudHide('btnSubmit');
        crudHide('btnHapus');
        crudHide('btnBatalkan');
        crudHide('btnAktifkan');
        crudHide('btnEditTrigger');
    }

    function setCrudButtonMode(mode) {
        resetCrudButtons();
        if (!IS_ADMIN) return;

        const btnSubmit = crudEl('btnSubmit');
        const btnHapus = crudEl('btnHapus');
        const btnBatalkan = crudEl('btnBatalkan');
        const btnAktifkan = crudEl('btnAktifkan');

        if (mode === 'add') {
            if (btnSubmit) btnSubmit.innerHTML = 'Simpan Jadwal';
            crudShow('btnSubmit');
            return;
        }

        if (mode === 'detail-active') {
            crudShow('btnEditTrigger', 'flex');
            return;
        }

        if (mode === 'edit-active') {
            if (btnSubmit) btnSubmit.innerHTML = 'Simpan Perubahan';
            if (btnBatalkan) btnBatalkan.innerHTML = '<i class="fa-solid fa-ban"></i> Batalkan Jadwal';
            if (btnHapus) btnHapus.innerHTML = '<i class="fa-solid fa-trash-can"></i> Hapus Jadwal';

            crudShow('btnSubmit');
            crudShow('btnBatalkan');
            crudShow('btnHapus');
            return;
        }

        if (mode === 'inactive') {
            if (btnAktifkan) btnAktifkan.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Aktifkan Kembali';
            crudShow('btnAktifkan');
        }
    }

    function openModalTambah() {
        if (!IS_ADMIN) return;

        document.getElementById('sheetTitle').textContent = 'Tambah Jadwal';
        document.getElementById('agenda-form').reset();
        document.getElementById('edit-id').value = '';

        const t = toStr(new Date());
        document.getElementById('f-start').value = t;
        document.getElementById('f-end').value = t;

        toggleInputs(false);
        setCrudButtonMode('add');

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

        const isCancelled = item.status === 'cancelled';
        setCrudButtonMode(isCancelled ? 'inactive' : 'detail-active');

        showModal();
    }

    function enableEditMode() {
        if (!IS_ADMIN) return;

        document.getElementById('sheetTitle').textContent = 'Ubah Jadwal';
        toggleInputs(false);
        setCrudButtonMode('edit-active');
    }

    /* ── CRUD ── */
    async function handleSave(e) {
        e.preventDefault();
        if (!IS_ADMIN) return;
        const id = document.getElementById('edit-id').value;
        const fd = new FormData();
        ['judul', 'start', 'end', 'pny', 'peserta', 'asrama', 'kelas', 'makan'].forEach(k => fd.append(k, document.getElementById('f-' + k).value));
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
        const modal = document.getElementById('exportModal');
        if (!modal) {
            alert('Modal export tidak ditemukan');
            return;
        }
        modal.style.display = 'flex';
        modal.style.zIndex = '1200';
        document.body.style.overflow = 'hidden';
        const t = new Date(),
            p = new Date();
        p.setDate(t.getDate() - 30);
        document.getElementById('exportTo').value = toStr(t);
        document.getElementById('exportFrom').value = toStr(p);
    }

    function closeExportModal() {
        const modal = document.getElementById('exportModal');
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
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

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) abort.abort();
        else loadAgenda();
    });
    window.addEventListener('beforeunload', () => abort.abort());
</script>