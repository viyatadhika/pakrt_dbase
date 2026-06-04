<?php
session_start();

$title = "Timetable Kegiatan";
include 'header.php';
include 'config.php';

$isAdmin = isset($_SESSION['user']) && strtolower($_SESSION['user']['role'] ?? '') === 'admin';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f8fafc;
        color: #1e293b;
    }

    body::before {
        content: "";
        position: fixed;
        inset: 0;
        background:
            radial-gradient(circle at top left, rgba(14, 165, 233, .08), transparent 28%),
            linear-gradient(180deg, #f8fbff 0%, #f8fafc 100%);
        pointer-events: none;
        z-index: -1;
    }

    html,
    body {
        overflow-x: hidden;
        height: auto !important;
        overflow-y: auto !important;
    }

    :root {
        --sky-blue: #0ea5e9;
        --sky-blue-dark: #0284c7;
        --sky-blue-light: #e0f2fe;
    }

    .text-sky {
        color: var(--sky-blue);
    }

    .bg-sky {
        background-color: var(--sky-blue);
    }

    .sticky-header {
        position: sticky;
        top: 0;
        z-index: 50;
        background: rgba(255, 255, 255, .96);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid #f1f5f9;
        margin: 8px 16px 0;
        border-radius: 18px;
        box-shadow: 0 10px 26px rgba(15, 23, 42, .04);
    }

    .tt-wrap {
        max-width: 100%;
        padding: 18px 14px 32px;
    }

    .tt-card {
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 24px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .tt-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
    }

    .tt-toolbar-title {
        font-size: 15px;
        font-weight: 900;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .tt-toolbar-sub {
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        margin-top: 2px;
    }

    .tt-toolbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .tt-btn {
        border: 1px solid #dbeafe;
        background: #fff;
        color: #0369a1;
        border-radius: 999px;
        padding: 9px 12px;
        font-size: 11px;
        font-weight: 900;
        cursor: pointer;
        transition: .2s;
    }

    .tt-btn:hover {
        background: #eff6ff;
    }

    .tt-desktop {
        display: block;
    }

    .tt-mobile {
        display: none;
    }

    .tt-table-box {
        overflow: auto;
        max-height: calc(100vh - 178px);
        background: #fff;
        border-top: 1px solid #111827;
    }

    .tt-table {
        border-collapse: collapse;
        width: max-content;
        min-width: 100%;
        table-layout: fixed;
    }

    .tt-table th,
    .tt-table td {
        border: 1px solid #111827;
        padding: 0;
        vertical-align: middle;
    }

    .tt-table tbody tr:hover td {
        background-image: linear-gradient(rgba(14, 165, 233, .045), rgba(14, 165, 233, .045));
    }

    .tt-table th {
        background: #9ca3af;
        color: #fff;
        font-size: 10px;
        font-weight: 900;
        text-align: center;
        line-height: 1.05;
    }

    .tt-sticky-left {
        position: sticky;
        z-index: 8;
    }

    .tt-col-no {
        width: 34px;
        min-width: 34px;
        max-width: 34px;
        text-align: center;
        background: #fff;
        font-size: 11px;
    }

    .tt-col-kegiatan {
        width: 330px;
        min-width: 330px;
        max-width: 330px;
        left: 34px;
        background: #fff;
        padding: 8px 9px !important;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.28;
    }

    .tt-col-penyelenggara {
        width: 120px;
        min-width: 120px;
        max-width: 120px;
        left: 364px;
        background: #fff;
        text-align: center;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.2;
    }

    .tt-col-peserta {
        width: 68px;
        min-width: 68px;
        max-width: 68px;
        text-align: center;
        font-size: 11px;
        background: #fff;
    }

    .tt-col-asrama {
        width: 78px;
        min-width: 78px;
        max-width: 78px;
        text-align: center;
        font-size: 10px;
        background: #fff;
        line-height: 1.15;
        padding: 4px !important;
    }

    .tt-col-kelas {
        width: 126px;
        min-width: 126px;
        max-width: 126px;
        text-align: center;
        font-size: 10px;
        background: #fff;
        line-height: 1.15;
        padding: 4px !important;
    }

    .tt-col-makan {
        width: 88px;
        min-width: 88px;
        max-width: 88px;
        text-align: center;
        font-size: 10px;
        background: #fff;
        line-height: 1.15;
        padding: 4px !important;
    }

    .tt-head-no {
        left: 0;
        z-index: 25;
    }

    .tt-head-kegiatan {
        left: 34px;
        z-index: 25;
    }

    .tt-head-penyelenggara {
        left: 364px;
        z-index: 25;
    }

    .tt-date-cell {
        width: 34px;
        min-width: 34px;
        max-width: 34px;
        height: 22px;
        text-align: center;
        font-size: 10px;
        font-weight: 800;
    }

    .tt-date-day {
        background: #9ca3af;
        color: #fff;
    }

    .tt-date-num {
        background: #e5e7eb;
        color: #475569;
    }

    .tt-date-weekend {
        background: #ef4444 !important;
        color: #fff !important;
    }

    .tt-date-month-label {
        background: #9ca3af;
        color: #fff;
        height: 22px;
        font-size: 11px;
        font-weight: 900;
    }

    .tt-row {
        height: 46px;
    }

    .tt-day-cell {
        width: 31px;
        height: 46px;
        background: #fff;
        cursor: pointer;
    }

    .tt-day-cell:hover {
        background: #f8fafc;
    }

    .tt-block {
        height: 46px;
        cursor: pointer;
        transition: .15s;
    }

    .tt-block:hover {
        filter: brightness(.95);
    }

    .tt-menpim {
        background: #ffff00;
    }

    .tt-teknis {
        background: #34a853;
        color: #000;
    }

    .tt-kerjasama {
        background: #6fa8dc;
        color: #000;
    }

    .tt-pustrajak {
        background: #ff9900;
        color: #000;
    }

    .tt-default {
        background: #94a3b8;
        color: #000;
    }

    .tt-cancelled {
        background: repeating-linear-gradient(45deg,
                #fecaca,
                #fecaca 6px,
                #fee2e2 6px,
                #fee2e2 12px) !important;
        opacity: .75;
    }

    .tt-selesai {
        opacity: .45;
        filter: grayscale(60%);
    }

    .tt-bentrok {
        outline: 3px solid #ef4444;
        outline-offset: -3px;
    }

    .tt-empty {
        padding: 32px;
        text-align: center;
        color: #94a3b8;
        font-size: 12px;
        font-weight: 800;
    }

    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 7px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 7px 10px;
        font-size: 10px;
        font-weight: 800;
        color: #475569;
    }

    .legend-dot {
        width: 12px;
        height: 12px;
        border-radius: 999px;
    }

    /* Mobile / tablet calendar view */
    .mobile-calendar {
        padding: 12px;
    }

    .mobile-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 7px;
    }

    .mobile-dayname {
        text-align: center;
        font-size: 10px;
        font-weight: 900;
        color: #64748b;
        padding: 8px 0;
    }

    .mobile-day {
        min-height: 86px;
        border: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 16px;
        padding: 7px;
        cursor: pointer;
    }

    .mobile-day.muted {
        opacity: .45;
        background: #f8fafc;
    }

    .mobile-day.weekend .mobile-date {
        color: #ef4444;
    }

    .mobile-date {
        font-size: 12px;
        font-weight: 900;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .mobile-event-dot {
        height: 7px;
        border-radius: 999px;
        margin-bottom: 4px;
    }

    .mobile-list {
        padding: 0 12px 14px;
        display: grid;
        gap: 10px;
    }

    .mobile-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 12px;
        box-shadow: 0 1px 8px rgba(15, 23, 42, .04);
    }

    .mobile-card-title {
        font-size: 12px;
        font-weight: 900;
        color: #0f172a;
        line-height: 1.3;
    }

    .mobile-card-meta {
        font-size: 10px;
        color: #64748b;
        font-weight: 700;
        margin-top: 5px;
        line-height: 1.35;
    }

    .mobile-card-info {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
        margin-top: 10px;
    }

    .mobile-mini {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 7px;
    }

    .mobile-mini-label {
        font-size: 8px;
        font-weight: 900;
        color: #94a3b8;
        text-transform: uppercase;
    }

    .mobile-mini-value {
        font-size: 10px;
        font-weight: 800;
        color: #334155;
        margin-top: 3px;
    }

    .modal-animate-up {
        animation: slideUp .35s cubic-bezier(.16, 1, .3, 1) forwards;
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

    .badge-selesai,
    .badge-dibatalkan {
        font-size: 8px;
        font-weight: 900;
        padding: 2px 7px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: .06em;
        line-height: 1.6;
        display: inline-flex;
        margin-top: 4px;
    }

    .badge-selesai {
        background-color: #e2e8f0;
        color: #94a3b8;
    }

    .badge-dibatalkan {
        background-color: #fee2e2;
        color: #ef4444;
    }

    ::-webkit-scrollbar {
        width: 6px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f8fafc;
    }

    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }


    .mobile-month-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 2px 10px;
    }

    .mobile-month-title strong {
        font-size: 13px;
        font-weight: 900;
        color: #0f172a;
    }

    .mobile-month-title span {
        font-size: 10px;
        font-weight: 800;
        color: #94a3b8;
    }

    .mobile-section-title {
        padding: 8px 14px 2px;
        font-size: 11px;
        font-weight: 900;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .mobile-day.has-event {
        border-color: #bae6fd;
        background: linear-gradient(180deg, #fff 0%, #f0f9ff 100%);
    }

    .mobile-day.today {
        box-shadow: 0 0 0 2px #0ea5e9 inset;
    }

    .mobile-day.selected {
        border-color: #0284c7;
        background: #e0f2fe;
    }

    .mobile-more {
        font-size: 9px;
        font-weight: 900;
        color: #0369a1;
        margin-top: 2px;
    }

    .mobile-card {
        position: relative;
        overflow: hidden;
    }

    .mobile-card:before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 5px;
        background: var(--card-color, #94a3b8);
    }

    .mobile-card-inner {
        padding-left: 6px;
    }

    .mobile-card-top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
    }

    .mobile-pill {
        flex: 0 0 auto;
        border-radius: 999px;
        padding: 5px 8px;
        font-size: 9px;
        font-weight: 900;
        background: #f8fafc;
        color: #334155;
        border: 1px solid #e2e8f0;
    }


    @media (max-width: 1024px) {
        .tt-desktop {
            display: none;
        }

        .tt-mobile {
            display: block;
        }

        .tt-wrap {
            padding: 84px 10px 24px;
        }

        .tt-toolbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .tt-toolbar-actions {
            width: 100%;
            justify-content: space-between;
        }

        .tt-btn {
            flex: 1;
            text-align: center;
            padding-left: 8px;
            padding-right: 8px;
        }

        .legend {
            display: none;
        }
    }

    @media (max-width: 520px) {
        .mobile-grid {
            gap: 5px;
        }

        .mobile-day {
            min-height: 72px;
            border-radius: 12px;
            padding: 5px;
        }

        .mobile-card-info {
            grid-template-columns: 1fr;
        }
    }

    /* ===== MOBILE & TABLET FINAL FIX ===== */
    @media (max-width: 1024px) {
        body {
            background: #f8fafc;
        }

        body::before {
            display: none;
        }

        .sticky-header {
            margin: 0;
            border-radius: 0 0 22px 22px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
            padding: 14px 16px !important;
        }

        .sticky-header .w-10 {
            width: 38px !important;
            height: 38px !important;
        }

        .sticky-header h1 {
            font-size: 16px !important;
            line-height: 1.1 !important;
        }

        .sticky-header p {
            font-size: 11px !important;
        }

        .tt-wrap {
            padding: 14px 10px 24px !important;
        }

        .tt-card {
            border-radius: 22px;
            border-color: #dbeafe;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
        }

        .tt-toolbar {
            padding: 16px 16px 14px;
            display: block;
            background: #fff;
        }

        .tt-toolbar-title {
            font-size: 16px !important;
            line-height: 1.15;
            letter-spacing: .02em;
            margin-bottom: 4px;
        }

        .tt-toolbar-sub {
            font-size: 11px;
            margin-bottom: 14px;
        }

        .tt-toolbar-actions {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            width: 100%;
        }

        .tt-btn {
            width: 100%;
            height: 38px;
            padding: 0 8px;
            font-size: 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .mobile-calendar {
            padding: 12px;
            border-top: 1px solid #e2e8f0;
            background: #fff;
        }

        .mobile-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 5px;
            width: 100%;
        }

        .mobile-month-title {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2px 10px;
        }

        .mobile-month-title strong {
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
        }

        .mobile-month-title span {
            font-size: 10px;
            font-weight: 900;
            color: #94a3b8;
        }

        .mobile-dayname {
            font-size: 9px;
            font-weight: 900;
            color: #64748b;
            text-align: center;
            padding: 5px 0 6px;
        }

        .mobile-day {
            min-height: 58px;
            border-radius: 12px;
            padding: 5px;
            border: 1px solid #e2e8f0;
            background: #fff;
            overflow: hidden;
        }

        .mobile-day.muted {
            background: #f8fafc;
            opacity: .65;
        }

        .mobile-day.has-event {
            border-color: #bae6fd;
            background: linear-gradient(180deg, #ffffff 0%, #f0f9ff 100%);
        }

        .mobile-day.today {
            box-shadow: 0 0 0 2px #0ea5e9 inset;
        }

        .mobile-day.selected {
            border-color: #0284c7;
            background: #e0f2fe;
        }

        .mobile-date {
            font-size: 11px;
            line-height: 1;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 5px;
        }

        .mobile-day.weekend .mobile-date {
            color: #ef4444;
        }

        .mobile-event-dot {
            height: 5px;
            border-radius: 999px;
            margin-bottom: 3px;
        }

        .mobile-more {
            font-size: 8px;
            line-height: 1;
            font-weight: 900;
            color: #0369a1;
            margin-top: 1px;
        }

        .mobile-section-title {
            padding: 12px 14px 4px;
            font-size: 10px;
            font-weight: 900;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .mobile-list {
            padding: 0 12px 14px;
            display: grid;
            gap: 10px;
        }

        .mobile-card {
            position: relative;
            overflow: hidden;
            border-radius: 18px;
            padding: 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .045);
        }

        .mobile-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 5px;
            background: var(--card-color, #94a3b8);
        }

        .mobile-card-inner {
            padding-left: 7px;
        }

        .mobile-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .mobile-card-title {
            font-size: 12px;
            font-weight: 900;
            line-height: 1.35;
            color: #0f172a;
        }

        .mobile-card-meta {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            line-height: 1.4;
            margin-top: 5px;
        }

        .mobile-pill {
            flex: 0 0 auto;
            border-radius: 999px;
            padding: 5px 8px;
            font-size: 8px;
            font-weight: 900;
            background: #f8fafc;
            color: #334155;
            border: 1px solid #e2e8f0;
            max-width: 92px;
            text-align: center;
        }

        .mobile-card-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 7px;
            margin-top: 10px;
        }

        .mobile-mini {
            padding: 8px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            min-width: 0;
        }

        .mobile-mini-label {
            font-size: 8px;
            line-height: 1;
            font-weight: 900;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .mobile-mini-value {
            font-size: 10px;
            line-height: 1.3;
            font-weight: 800;
            color: #334155;
            margin-top: 4px;
            word-break: break-word;
        }
    }

    @media (min-width: 768px) and (max-width: 1024px) {
        .tt-wrap {
            padding: 18px 18px 28px !important;
        }

        .mobile-day {
            min-height: 82px;
            padding: 7px;
            border-radius: 15px;
        }

        .mobile-date {
            font-size: 13px;
            margin-bottom: 7px;
        }

        .mobile-event-dot {
            height: 7px;
            margin-bottom: 4px;
        }

        .mobile-card-info {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .mobile-card-title {
            font-size: 13px;
        }

        .mobile-card-meta {
            font-size: 11px;
        }
    }

    @media (max-width: 380px) {
        .tt-toolbar-actions {
            gap: 6px;
        }

        .tt-btn {
            font-size: 9px;
        }

        .mobile-grid {
            gap: 4px;
        }

        .mobile-day {
            min-height: 54px;
            padding: 4px;
            border-radius: 10px;
        }

        .mobile-event-dot {
            height: 4px;
            margin-bottom: 2px;
        }
    }


    /* ===== DESKTOP RESPONSIVE TABLE FINAL FIX ===== */
    @media (min-width: 1025px) {
        .tt-wrap {
            padding: 18px 10px 24px !important;
        }

        .tt-card {
            width: 100%;
        }

        .tt-table-box {
            overflow-x: hidden !important;
            overflow-y: auto;
            max-height: calc(100vh - 168px);
        }

        .tt-table {
            width: 100% !important;
            min-width: 0 !important;
            table-layout: fixed !important;
        }

        .tt-table th,
        .tt-table td {
            box-sizing: border-box;
        }

        .tt-col-no,
        .tt-head-no {
            width: 28px !important;
            min-width: 28px !important;
            max-width: 28px !important;
            left: 0 !important;
        }

        .tt-col-kegiatan,
        .tt-head-kegiatan {
            width: 250px !important;
            min-width: 250px !important;
            max-width: 250px !important;
            left: 28px !important;
        }

        .tt-col-penyelenggara,
        .tt-head-penyelenggara {
            width: 92px !important;
            min-width: 92px !important;
            max-width: 92px !important;
            left: 278px !important;
        }

        .tt-col-peserta {
            width: 58px !important;
            min-width: 58px !important;
            max-width: 58px !important;
        }

        .tt-col-asrama {
            width: 68px !important;
            min-width: 68px !important;
            max-width: 68px !important;
        }

        .tt-col-kelas {
            width: 92px !important;
            min-width: 92px !important;
            max-width: 92px !important;
        }

        .tt-col-makan {
            width: 72px !important;
            min-width: 72px !important;
            max-width: 72px !important;
        }

        .tt-date-cell,
        .tt-day-cell {
            width: auto !important;
            min-width: 0 !important;
            max-width: none !important;
        }

        .tt-col-kegiatan {
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            font-size: 10px !important;
            line-height: 1.22;
            padding: 6px 8px !important;
        }

        .tt-col-penyelenggara,
        .tt-col-peserta,
        .tt-col-asrama,
        .tt-col-kelas,
        .tt-col-makan {
            font-size: 9px !important;
            line-height: 1.12;
            padding: 3px !important;
            overflow-wrap: anywhere;
        }

        .tt-table th {
            font-size: 9px !important;
            line-height: 1.05;
        }

        .tt-date-cell {
            font-size: 9px !important;
            padding: 0 !important;
        }

        .tt-row,
        .tt-day-cell,
        .tt-block {
            height: 42px !important;
        }
    }

    @media (min-width: 1025px) and (max-width: 1439px) {
        .tt-wrap {
            padding-left: 6px !important;
            padding-right: 6px !important;
        }

        .tt-col-no,
        .tt-head-no {
            width: 24px !important;
            min-width: 24px !important;
            max-width: 24px !important;
        }

        .tt-col-kegiatan,
        .tt-head-kegiatan {
            width: 218px !important;
            min-width: 218px !important;
            max-width: 218px !important;
            left: 24px !important;
        }

        .tt-col-penyelenggara,
        .tt-head-penyelenggara {
            width: 78px !important;
            min-width: 78px !important;
            max-width: 78px !important;
            left: 242px !important;
        }

        .tt-col-peserta {
            width: 48px !important;
            min-width: 48px !important;
            max-width: 48px !important;
        }

        .tt-col-asrama {
            width: 58px !important;
            min-width: 58px !important;
            max-width: 58px !important;
        }

        .tt-col-kelas {
            width: 76px !important;
            min-width: 76px !important;
            max-width: 76px !important;
        }

        .tt-col-makan {
            width: 60px !important;
            min-width: 60px !important;
            max-width: 60px !important;
        }

        .tt-col-kegiatan {
            font-size: 9px !important;
            padding: 5px 6px !important;
        }

        .tt-col-penyelenggara,
        .tt-col-peserta,
        .tt-col-asrama,
        .tt-col-kelas,
        .tt-col-makan,
        .tt-table th,
        .tt-date-cell {
            font-size: 8px !important;
        }
    }

    @media (min-width: 1600px) {

        .tt-col-no,
        .tt-head-no {
            width: 34px !important;
            min-width: 34px !important;
            max-width: 34px !important;
        }

        .tt-col-kegiatan,
        .tt-head-kegiatan {
            width: 320px !important;
            min-width: 320px !important;
            max-width: 320px !important;
            left: 34px !important;
        }

        .tt-col-penyelenggara,
        .tt-head-penyelenggara {
            width: 116px !important;
            min-width: 116px !important;
            max-width: 116px !important;
            left: 354px !important;
        }

        .tt-col-peserta {
            width: 64px !important;
            min-width: 64px !important;
            max-width: 64px !important;
        }

        .tt-col-asrama {
            width: 78px !important;
            min-width: 78px !important;
            max-width: 78px !important;
        }

        .tt-col-kelas {
            width: 116px !important;
            min-width: 116px !important;
            max-width: 116px !important;
        }

        .tt-col-makan {
            width: 82px !important;
            min-width: 82px !important;
            max-width: 82px !important;
        }

        .tt-col-kegiatan {
            font-size: 11px !important;
            padding: 7px 9px !important;
        }

        .tt-col-penyelenggara,
        .tt-col-peserta,
        .tt-col-asrama,
        .tt-col-kelas,
        .tt-col-makan,
        .tt-table th,
        .tt-date-cell {
            font-size: 10px !important;
        }

        .tt-row,
        .tt-day-cell,
        .tt-block {
            height: 46px !important;
        }
    }
</style>


<div id="pakrtHeaderFix">
    <div class="pakrt-header-card">
        <div class="pakrt-header-left">
            <button type="button" onclick="window.history.back()" class="pakrt-header-btn" aria-label="Kembali">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <div class="pakrt-header-title">
                <div class="pakrt-header-main">Timetable Kegiatan</div>
                <div class="pakrt-header-sub">Jadwal kegiatan Pusdiklat</div>
            </div>
        </div>
        <button type="button" onclick="openExportModal()" class="pakrt-header-btn" aria-label="Download">
            <i class="fa-solid fa-download"></i>
        </button>
    </div>
</div>


<div class="tt-wrap">
    <div class="tt-card">
        <div class="tt-toolbar">
            <div>
                <div class="tt-toolbar-title" id="timetable-title">Timetable</div>
                <div class="tt-toolbar-sub" id="badge-count">0 Agenda</div>
            </div>

            <div class="tt-toolbar-actions">
                <button class="tt-btn" onclick="changeMonth(-1)">
                    <i class="fa-solid fa-chevron-left mr-1"></i> Sebelumnya
                </button>
                <button class="tt-btn" onclick="goThisMonth()">Bulan Ini</button>
                <button class="tt-btn" onclick="changeMonth(1)">
                    Berikutnya <i class="fa-solid fa-chevron-right ml-1"></i>
                </button>
            </div>
        </div>

        <div class="legend tt-desktop">
            <div class="legend-item"><span class="legend-dot" style="background:#ffff00"></span>Menpim</div>
            <div class="legend-item"><span class="legend-dot" style="background:#34a853"></span>Teknis</div>
            <div class="legend-item"><span class="legend-dot" style="background:#6fa8dc"></span>Kerjasama</div>
            <div class="legend-item"><span class="legend-dot" style="background:#ff9900"></span>Pustrajak</div>
            <div class="legend-item"><span class="legend-dot" style="background:#fecaca;border:2px dashed #ef4444"></span>Dibatalkan</div>
            <div class="legend-item"><span class="legend-dot" style="background:#94a3b8;opacity:.45"></span>Selesai</div>
            <div class="legend-item"><span class="legend-dot" style="background:#fff;border:3px solid #ef4444"></span>Bentrok</div>
        </div>

        <div class="tt-desktop">
            <div class="tt-table-box">
                <table class="tt-table" id="timetable-table"></table>
            </div>
        </div>

        <div class="tt-mobile">
            <div class="mobile-calendar">
                <div class="mobile-grid" id="mobile-calendar-grid"></div>
            </div>
            <div class="mobile-list" id="mobile-agenda-list"></div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
    <button onclick="openModalTambah()"
        class="fixed bottom-8 right-8 w-12 h-12 bg-sky-600 text-white rounded-full shadow-lg shadow-sky-100 flex items-center justify-center z-[40] active:scale-90 transition-all">
        <i class="fa-solid fa-plus text-lg"></i>
    </button>
<?php endif; ?>

<div id="stokModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl modal-animate-up">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p id="sheetTitle" class="text-sm font-extrabold text-gray-800">Tambah Jadwal</p>
                    <p class="text-[11px] text-gray-500">Pusdiklat Mahkamah Agung</p>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($isAdmin): ?>
                        <button type="button" id="btnEditTrigger" onclick="enableEditMode()"
                            class="w-9 h-9 rounded-full bg-sky-50 text-sky-600 hidden">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    <?php endif; ?>
                    <button type="button" onclick="closeModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <form id="agenda-form" onsubmit="handleSave(event)" class="space-y-3">
                <input type="hidden" id="edit-id">

                <div>
                    <label class="text-xs font-bold text-gray-600">Nama Pelatihan</label>
                    <input id="f-judul" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 outline-none focus:border-sky-300">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Mulai</label>
                        <input id="f-start" type="date" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Selesai</label>
                        <input id="f-end" type="date" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-600">Kategori</label>
                    <select id="f-pny" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                        <option value="Menpim">Menpim</option>
                        <option value="Teknis">Teknis</option>
                        <option value="Kerjasama">Kerjasama</option>
                        <option value="Pustrajak">Pustrajak</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Peserta</label>
                        <input id="f-peserta" type="number" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Asrama</label>
                        <input id="f-asrama" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600">Kelas</label>
                        <input id="f-kelas" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Ruang Makan</label>
                        <input id="f-makan" type="text" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                    </div>
                </div>

                <?php if ($isAdmin): ?>
                    <button id="btnSubmit" type="submit" class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Simpan Jadwal</button>

                    <button id="btnHapus" type="button" onclick="handleDelete()" class="w-full py-3 rounded-2xl bg-red-50 text-red-600 font-extrabold text-sm hidden">
                        <i class="fa-solid fa-trash-can mr-2"></i> Hapus Jadwal
                    </button>

                    <button id="btnBatalkan" type="button" onclick="handleCancel()" class="w-full py-3 rounded-2xl bg-orange-50 text-orange-600 font-extrabold text-sm hidden">
                        <i class="fa-solid fa-ban mr-2"></i> Batalkan Kegiatan
                    </button>

                    <button id="btnAktifkan" type="button" onclick="handleReactivate()" class="w-full py-3 rounded-2xl bg-green-50 text-green-600 font-extrabold text-sm hidden">
                        <i class="fa-solid fa-rotate-left mr-2"></i> Aktifkan Kembali
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<div id="exportModal" class="fixed inset-0 bg-black/50 z-[999] hidden">
    <div class="absolute inset-0" onclick="closeExportModal()"></div>
    <div class="relative w-full h-full flex items-end justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl p-5 shadow-xl modal-animate-up">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-extrabold text-gray-800">Download Laporan</p>
                    <p class="text-[11px] text-gray-500">Pilih rentang tanggal</p>
                </div>
                <button onclick="closeExportModal()" class="w-9 h-9 rounded-full bg-gray-100 text-gray-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="text-xs font-bold text-gray-600">Dari Tanggal</label>
                    <input type="date" id="exportFrom" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600">Sampai Tanggal</label>
                    <input type="date" id="exportTo" class="w-full mt-1 px-4 py-3 rounded-2xl bg-gray-50 border border-gray-200">
                </div>
                <button onclick="downloadExport()" class="w-full py-3 rounded-2xl bg-sky-600 text-white font-extrabold text-sm">Download PDF</button>
                <p class="text-[10px] text-gray-400 text-center">Default otomatis 30 hari terakhir</p>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="fixed top-24 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-6 py-3 rounded-full text-[10px] font-bold shadow-xl opacity-0 pointer-events-none transition-all duration-300 z-[200]">Aksi Berhasil!</div>


<style id="pakrt-hard-final-css">
    /* ===== HARD FINAL FIX TIMETABLE PAK RT ===== */
    * {
        box-sizing: border-box
    }

    html,
    body {
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
        background: #f3f8fe !important;
    }

    body:before {
        display: none !important
    }

    /* hilangkan efek header lama kalau masih ada */
    .sticky-header {
        display: none !important
    }

    /* HEADER FULL WIDTH */
    #pakrtHeaderFix {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 8px 10px !important;
        background: #f3f8fe !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 9999 !important;
    }

    #pakrtHeaderFix .pakrt-header-card {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        min-height: 78px !important;
        margin: 0 !important;
        padding: 14px 24px !important;
        background: #fff !important;
        border: 1px solid #edf6ff !important;
        border-radius: 0 0 20px 20px !important;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .04) !important;
    }

    .pakrt-header-left {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
    }

    .pakrt-header-btn {
        width: 42px !important;
        height: 42px !important;
        min-width: 42px !important;
        border: 0 !important;
        border-radius: 999px !important;
        background: #eff8ff !important;
        color: #0284c7 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
        box-shadow: none !important;
        outline: 0 !important;
        padding: 0 !important;
    }

    .pakrt-header-title {
        min-width: 0 !important
    }

    .pakrt-header-main {
        font-size: 18px !important;
        line-height: 1.1 !important;
        font-weight: 900 !important;
        color: #0284c7 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pakrt-header-sub {
        margin-top: 3px !important;
        font-size: 12px !important;
        line-height: 1.15 !important;
        font-weight: 700 !important;
        color: #94a3b8 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    /* PAGE */
    .tt-wrap {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 18px 6px 0 !important;
        background: #f3f8fe !important;
    }

    .tt-card {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        background: #fff !important;
        border: 1px solid #dbeafe !important;
        border-radius: 24px 24px 0 0 !important;
        box-shadow: 0 12px 34px rgba(15, 23, 42, .055) !important;
        overflow: hidden !important;
    }

    /* TOOLBAR */
    .tt-toolbar {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        width: 100% !important;
        min-height: 72px !important;
        padding: 17px 20px 16px !important;
        background: #fff !important;
        border-bottom: 1px solid #dbeafe !important;
    }

    .tt-toolbar-title {
        font-size: 17px !important;
        line-height: 1.15 !important;
        font-weight: 900 !important;
        letter-spacing: .035em !important;
        color: #0f172a !important;
        text-transform: uppercase !important;
    }

    .tt-toolbar-sub {
        margin-top: 6px !important;
        font-size: 11px !important;
        line-height: 1.2 !important;
        font-weight: 800 !important;
        color: #94a3b8 !important;
    }

    .tt-toolbar-actions {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: 8px !important;
        flex: 0 0 auto !important;
        width: auto !important;
    }

    .tt-btn {
        height: 38px !important;
        min-width: 102px !important;
        width: auto !important;
        flex: 0 0 auto !important;
        padding: 0 14px !important;
        border: 1px solid #bfdbfe !important;
        border-radius: 999px !important;
        background: #fff !important;
        color: #0369a1 !important;
        font-size: 11px !important;
        font-weight: 900 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
    }

    /* LEGEND PILL HORIZONTAL */
    .legend {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        justify-content: flex-start !important;
        width: 100% !important;
        padding: 13px 18px !important;
        gap: 9px !important;
        background: #f8fbff !important;
        border-bottom: 1px solid #dbeafe !important;
    }

    .legend .legend-item,
    .legend-item {
        display: inline-flex !important;
        flex: 0 0 auto !important;
        width: auto !important;
        min-width: auto !important;
        max-width: none !important;
        height: 30px !important;
        padding: 0 11px !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 7px !important;
        background: #fff !important;
        border: 1px solid #dbeafe !important;
        border-radius: 999px !important;
        color: #334155 !important;
        font-size: 10px !important;
        font-weight: 900 !important;
        line-height: 1 !important;
    }

    .legend-dot {
        width: 12px !important;
        height: 12px !important;
        min-width: 12px !important;
        border-radius: 999px !important;
    }

    /* DESKTOP TABLE */
    @media(min-width:1025px) {
        .tt-desktop {
            display: block !important
        }

        .tt-mobile {
            display: none !important
        }

        .tt-table-box {
            display: block !important;
            width: 100% !important;
            max-height: calc(100vh - 206px) !important;
            overflow: auto !important;
            border-top: 1px solid #111827 !important;
            background: #fff !important;
        }

        .tt-table {
            border-collapse: collapse !important;
            table-layout: fixed !important;
            width: 100% !important;
            min-width: 1510px !important;
        }

        .tt-table th,
        .tt-table td {
            border: 1px solid #111827 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            vertical-align: middle !important;
        }

        .tt-table th {
            background: #9ca3af !important;
            color: #fff !important;
            font-size: 10px !important;
            font-weight: 900 !important;
            line-height: 1.05 !important;
            text-align: center !important;
        }

        .tt-date-day,
        .tt-date-num,
        .tt-date-month-label {
            background: #9ca3af !important;
            color: #fff !important;
        }

        .tt-date-weekend {
            background: #ef4444 !important;
            color: #fff !important;
        }

        .tt-date-cell,
        .tt-day-cell {
            width: 32px !important;
            min-width: 32px !important;
            max-width: 32px !important;
        }

        .tt-row,
        .tt-day-cell,
        .tt-block {
            height: 46px !important
        }

        .tt-sticky-left {
            position: sticky !important;
            z-index: 8 !important
        }

        .tt-head-no {
            z-index: 25 !important
        }

        .tt-head-kegiatan {
            z-index: 25 !important
        }

        .tt-head-penyelenggara {
            z-index: 25 !important
        }

        .tt-col-no,
        .tt-head-no {
            width: 32px !important;
            min-width: 32px !important;
            max-width: 32px !important;
            left: 0 !important;
        }

        .tt-col-kegiatan,
        .tt-head-kegiatan {
            width: 318px !important;
            min-width: 318px !important;
            max-width: 318px !important;
            left: 32px !important;
        }

        .tt-col-penyelenggara,
        .tt-head-penyelenggara {
            width: 118px !important;
            min-width: 118px !important;
            max-width: 118px !important;
            left: 350px !important;
        }

        .tt-col-peserta {
            width: 64px !important;
            min-width: 64px !important;
            max-width: 64px !important;
        }

        .tt-col-asrama {
            width: 74px !important;
            min-width: 74px !important;
            max-width: 74px !important;
        }

        .tt-col-kelas {
            width: 96px !important;
            min-width: 96px !important;
            max-width: 96px !important;
        }

        .tt-col-makan {
            width: 76px !important;
            min-width: 76px !important;
            max-width: 76px !important;
        }

        .tt-col-no,
        .tt-col-kegiatan,
        .tt-col-penyelenggara,
        .tt-col-peserta,
        .tt-col-asrama,
        .tt-col-kelas,
        .tt-col-makan {
            background: #fff !important;
        }

        .tt-col-kegiatan {
            padding: 7px 9px !important;
            font-size: 10px !important;
            line-height: 1.2 !important;
            font-weight: 900 !important;
            color: #0f172a !important;
            white-space: normal !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
        }

        .tt-col-penyelenggara,
        .tt-col-peserta,
        .tt-col-asrama,
        .tt-col-kelas,
        .tt-col-makan {
            padding: 4px !important;
            font-size: 9px !important;
            line-height: 1.12 !important;
            font-weight: 800 !important;
            text-align: center !important;
            overflow-wrap: anywhere !important;
        }

        .tt-menpim,
        .tt-block.tt-menpim {
            background: #ffff00 !important;
            color: #000 !important
        }

        .tt-teknis,
        .tt-block.tt-teknis {
            background: #34a853 !important;
            color: #000 !important
        }

        .tt-kerjasama,
        .tt-block.tt-kerjasama {
            background: #6fa8dc !important;
            color: #000 !important
        }

        .tt-pustrajak,
        .tt-block.tt-pustrajak {
            background: #ff9900 !important;
            color: #000 !important
        }

        .tt-bentrok {
            outline: 3px solid #ef4444 !important;
            outline-offset: -3px !important
        }
    }

    /* MOBILE/TABLET */
    @media(max-width:1024px) {
        #pakrtHeaderFix {
            padding: 8px 10px !important
        }

        #pakrtHeaderFix .pakrt-header-card {
            min-height: 58px !important;
            border-radius: 20px !important;
            padding: 9px 11px !important;
        }

        .pakrt-header-btn {
            width: 36px !important;
            height: 36px !important;
            min-width: 36px !important;
        }

        .pakrt-header-main {
            font-size: 16px !important
        }

        .pakrt-header-sub {
            font-size: 10px !important
        }

        .tt-wrap {
            padding: 12px 10px 24px !important
        }

        .tt-card {
            border-radius: 22px !important
        }

        .tt-toolbar {
            min-height: auto !important;
            display: block !important;
            padding: 15px !important;
        }

        .tt-toolbar-title {
            font-size: 16px !important
        }

        .tt-toolbar-sub {
            margin-top: 5px !important;
            margin-bottom: 12px !important
        }

        .tt-toolbar-actions {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 8px !important;
            width: 100% !important;
        }

        .tt-btn {
            min-width: 0 !important;
            width: 100% !important;
            height: 38px !important;
            padding: 0 6px !important;
            font-size: 10px !important;
        }

        .legend {
            display: none !important
        }
    }
</style>

<script>
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

    let agendaData = [];
    let viewDate = new Date();
    let abortController = new AbortController();
    let selectedMobileDate = null;

    const stokModal = document.getElementById('stokModal');
    const sheetTitle = document.getElementById('sheetTitle');
    const btnSubmit = IS_ADMIN ? document.getElementById('btnSubmit') : null;
    const btnHapus = IS_ADMIN ? document.getElementById('btnHapus') : null;
    const btnEditTrigger = IS_ADMIN ? document.getElementById('btnEditTrigger') : null;
    const btnBatalkan = IS_ADMIN ? document.getElementById('btnBatalkan') : null;
    const btnAktifkan = IS_ADMIN ? document.getElementById('btnAktifkan') : null;

    async function loadAgenda() {
        abortController.abort();
        abortController = new AbortController();

        try {
            const res = await fetch('get_timetable.php?action=read', {
                signal: abortController.signal
            });
            const rawData = await res.json();
            agendaData = markBentrok(rawData);
            renderAllViews();
        } catch (e) {
            if (e.name !== 'AbortError') console.error(e);
        }
    }

    window.onload = loadAgenda;

    function parseDate(str) {
        const [y, m, d] = String(str).split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    function toDateStr(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function addDays(date, days) {
        const d = new Date(date);
        d.setDate(d.getDate() + days);
        return d;
    }

    function formatDateID(dateStr) {
        const d = parseDate(dateStr);
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }).format(d);
    }

    function formatMonthID(date) {
        return new Intl.DateTimeFormat('id-ID', {
            month: 'long',
            year: 'numeric'
        }).format(date);
    }

    function getMonthRange() {
        const start = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
        const end = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
        return {
            start,
            end
        };
    }

    function getDaysInView() {
        const {
            start,
            end
        } = getMonthRange();
        const days = [];
        for (let d = new Date(start); d <= end; d = addDays(d, 1)) {
            days.push(new Date(d));
        }
        return days;
    }

    function isWeekend(date) {
        const day = date.getDay();
        return day === 0 || day === 6;
    }

    function dayNameShort(date) {
        const names = ['M', 'SN', 'SL', 'R', 'K', 'J', 'SB'];
        return names[date.getDay()];
    }

    function getColor(pny) {
        const map = {
            'Menpim': '#ffff00',
            'Teknis': '#34a853',
            'Kerjasama': '#6fa8dc',
            'Pustrajak': '#ff9900'
        };
        return map[pny] || '#94a3b8';
    }

    function getBlockClass(item) {
        const map = {
            'Menpim': 'tt-menpim',
            'Teknis': 'tt-teknis',
            'Kerjasama': 'tt-kerjasama',
            'Pustrajak': 'tt-pustrajak'
        };
        return map[item.pny] || 'tt-default';
    }

    function markBentrok(data) {
        return data.map((a, i) => {
            if (a.status === 'cancelled') {
                return {
                    ...a,
                    isBentrok: false
                };
            }

            const bentrok = data.some((b, j) =>
                i !== j &&
                b.status !== 'cancelled' &&
                a.start <= b.end &&
                a.end >= b.start
            );

            return {
                ...a,
                isBentrok: bentrok
            };
        });
    }

    function countAgendaBulan(data) {
        const {
            start,
            end
        } = getMonthRange();
        const startStr = toDateStr(start);
        const endStr = toDateStr(end);

        return data.filter(ev => ev.start <= endStr && ev.end >= startStr).length;
    }

    function getAgendaBulan() {
        const {
            start,
            end
        } = getMonthRange();
        const startStr = toDateStr(start);
        const endStr = toDateStr(end);

        return agendaData
            .filter(ev => ev.start <= endStr && ev.end >= startStr)
            .sort((a, b) => {
                const c = a.start.localeCompare(b.start);
                if (c !== 0) return c;
                return String(a.judul || '').localeCompare(String(b.judul || ''));
            });
    }

    function renderAllViews() {
        document.getElementById('timetable-title').innerText = `Timetable ${formatMonthID(viewDate)}`;
        document.getElementById('badge-count').innerText = `${countAgendaBulan(agendaData)} Agenda Bulan Ini`;
        renderTimetableDesktop();
        renderMobileCalendar();
        renderMobileAgendaList();
    }

    function renderTimetableDesktop() {
        const table = document.getElementById('timetable-table');
        const days = getDaysInView();
        const data = getAgendaBulan();

        if (!data.length) {
            table.innerHTML = `
            <tbody>
                <tr>
                    <td class="tt-empty">Tidak ada agenda pada bulan ini</td>
                </tr>
            </tbody>
        `;
            return;
        }

        const monthName = new Intl.DateTimeFormat('id-ID', {
            month: 'long'
        }).format(viewDate).toUpperCase();

        let html = `
        <thead>
            <tr>
                <th class="tt-sticky-left tt-head-no" rowspan="3" style="width:28px">No.</th>
                <th class="tt-sticky-left tt-head-kegiatan" rowspan="3" style="width:250px">KEGIATAN</th>
                <th class="tt-sticky-left tt-head-penyelenggara" rowspan="3" style="width:92px">PENYELENGGARA</th>
                <th class="tt-date-month-label" colspan="${days.length}">${monthName}</th>
                <th rowspan="3" style="width:58px">JUMLAH<br>PESERTA</th>
                <th rowspan="3" style="width:68px">ASRAMA</th>
                <th rowspan="3" style="width:92px">KELAS</th>
                <th rowspan="3" style="width:72px">RUANG<br>MAKAN</th>
            </tr>
            <tr>
                ${days.map(d => `<th class="tt-date-cell tt-date-day ${isWeekend(d) ? 'tt-date-weekend' : ''}">${dayNameShort(d)}</th>`).join('')}
            </tr>
            <tr>
                ${days.map(d => `<th class="tt-date-cell tt-date-num ${isWeekend(d) ? 'tt-date-weekend' : ''}">${d.getDate()}</th>`).join('')}
            </tr>
        </thead>
        <tbody>
    `;

        const todayStr = toDateStr(new Date());

        data.forEach((item, index) => {
            const start = item.start;
            const end = item.end;
            const isDibatalkan = item.status === 'cancelled';
            const isSelesai = !isDibatalkan && item.end < todayStr;

            let blockClasses = getBlockClass(item);
            if (isDibatalkan) blockClasses += ' tt-cancelled';
            if (isSelesai) blockClasses += ' tt-selesai';
            if (item.isBentrok) blockClasses += ' tt-bentrok';

            html += `
            <tr class="tt-row">
                <td class="tt-sticky-left tt-col-no">${index + 1}</td>
                <td class="tt-sticky-left tt-col-kegiatan" onclick="openModalDetail(${item.id})">
                    <div style="${isDibatalkan ? 'text-decoration:line-through;color:#94a3b8' : ''}">
                        ${escapeHtml(item.judul || '-')}
                    </div>
                    ${isDibatalkan ? '<span class="badge-dibatalkan">Dibatalkan</span>' : ''}
                    ${isSelesai ? '<span class="badge-selesai">Selesai</span>' : ''}
                </td>
                <td class="tt-sticky-left tt-col-penyelenggara ${getBlockClass(item)} ${isDibatalkan ? 'tt-cancelled' : ''} ${isSelesai ? 'tt-selesai' : ''}" onclick="openModalDetail(${item.id})">
                    ${escapeHtml(item.pny || '-')}
                </td>
        `;

            days.forEach(d => {
                const dateStr = toDateStr(d);
                const active = dateStr >= start && dateStr <= end;
                html += active ?
                    `<td class="tt-day-cell tt-block ${blockClasses}" onclick="openModalDetail(${item.id})" title="${escapeHtml(item.judul || '')}"></td>` :
                    `<td class="tt-day-cell" onclick="filterByDate('${dateStr}')"></td>`;
            });

            html += `
                <td class="tt-col-peserta">${escapeHtml(item.peserta || '0')}</td>
                <td class="tt-col-asrama">${escapeHtml(item.asrama || '-')}</td>
                <td class="tt-col-kelas">${escapeHtml(item.kelas || '-')}</td>
                <td class="tt-col-makan">${escapeHtml(item.makan || '-')}</td>
            </tr>
        `;
        });

        html += `</tbody>`;
        table.innerHTML = html;
    }

    function renderMobileCalendar() {
        const grid = document.getElementById('mobile-calendar-grid');
        const y = viewDate.getFullYear();
        const m = viewDate.getMonth();
        const first = new Date(y, m, 1);
        const last = new Date(y, m + 1, 0);
        const firstIdx = (first.getDay() + 6) % 7;
        const total = last.getDate();
        const dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        const todayStr = toDateStr(new Date());

        let html = `
        <div class="mobile-month-title">
            <strong>${formatMonthID(viewDate)}</strong>
            <span>${countAgendaBulan(agendaData)} agenda</span>
        </div>
    `;

        dayNames.forEach(n => {
            html += `<div class="mobile-dayname">${n}</div>`;
        });

        for (let i = 0; i < firstIdx; i++) {
            html += `<div class="mobile-day muted"></div>`;
        }

        for (let day = 1; day <= total; day++) {
            const date = new Date(y, m, day);
            const dateStr = toDateStr(date);
            const events = agendaData.filter(ev => ev.start <= dateStr && ev.end >= dateStr);
            const selected = selectedMobileDate === dateStr;

            html += `
            <div class="mobile-day ${isWeekend(date) ? 'weekend' : ''} ${events.length ? 'has-event' : ''} ${dateStr === todayStr ? 'today' : ''} ${selected ? 'selected' : ''}" onclick="selectMobileDate('${dateStr}')">
                <div class="mobile-date">${day}</div>
                ${events.slice(0, 4).map(ev => `<div class="mobile-event-dot" style="background:${getColor(ev.pny)}"></div>`).join('')}
                ${events.length > 4 ? `<div class="mobile-more">+${events.length - 4}</div>` : ''}
            </div>
        `;
        }

        grid.innerHTML = html;
    }

    function renderMobileAgendaList() {
        const box = document.getElementById('mobile-agenda-list');
        const data = selectedMobileDate ?
            agendaData.filter(ev => ev.start <= selectedMobileDate && ev.end >= selectedMobileDate) :
            getAgendaBulan();

        const title = selectedMobileDate ?
            `Agenda ${formatDateID(selectedMobileDate)}` :
            `Daftar Agenda ${formatMonthID(viewDate)}`;

        if (!data.length) {
            box.innerHTML = `
            <div class="mobile-section-title">${title}</div>
            <div class="tt-empty">Tidak ada agenda</div>
        `;
            return;
        }

        box.innerHTML = `
        <div class="mobile-section-title">${title}</div>
        ${data.map(item => {
            const isDibatalkan = item.status === 'cancelled';
            const isSelesai = !isDibatalkan && item.end < toDateStr(new Date());
            return `
                <div class="mobile-card" style="--card-color:${getColor(item.pny)}" onclick="openModalDetail(${item.id})">
                    <div class="mobile-card-inner">
                        <div class="mobile-card-top">
                            <div>
                                <div class="mobile-card-title">${escapeHtml(item.judul || '-')}</div>
                                <div class="mobile-card-meta">
                                    ${formatDateID(item.start)} s.d. ${formatDateID(item.end)}
                                </div>
                            </div>
                            <div class="mobile-pill">${escapeHtml(item.pny || '-')}</div>
                        </div>

                        ${isDibatalkan ? '<span class="badge-dibatalkan">Dibatalkan</span>' : ''}
                        ${isSelesai ? '<span class="badge-selesai">Selesai</span>' : ''}

                        <div class="mobile-card-info">
                            <div class="mobile-mini">
                                <div class="mobile-mini-label">Peserta</div>
                                <div class="mobile-mini-value">${escapeHtml(item.peserta || '0')}</div>
                            </div>
                            <div class="mobile-mini">
                                <div class="mobile-mini-label">Asrama</div>
                                <div class="mobile-mini-value">${escapeHtml(item.asrama || '-')}</div>
                            </div>
                            <div class="mobile-mini">
                                <div class="mobile-mini-label">Kelas</div>
                                <div class="mobile-mini-value">${escapeHtml(item.kelas || '-')}</div>
                            </div>
                            <div class="mobile-mini">
                                <div class="mobile-mini-label">Ruang Makan</div>
                                <div class="mobile-mini-value">${escapeHtml(item.makan || '-')}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('')}
    `;
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        } [m]));
    }

    function openModalTambah() {
        if (!IS_ADMIN) return;

        sheetTitle.innerText = "Tambah Jadwal";
        resetForm();
        toggleInputs(false);

        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnHapus) btnHapus.classList.add('hidden');
        if (btnBatalkan) btnBatalkan.classList.add('hidden');
        if (btnAktifkan) btnAktifkan.classList.add('hidden');

        if (btnSubmit) {
            btnSubmit.classList.remove('hidden');
            btnSubmit.innerText = "Simpan Jadwal";
        }

        stokModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function openModalDetail(id) {
        const item = agendaData.find(a => String(a.id) === String(id));
        if (!item) return;

        sheetTitle.innerText = "Detail Jadwal";

        document.getElementById('edit-id').value = item.id;
        document.getElementById('f-judul').value = item.judul;
        document.getElementById('f-start').value = item.start;
        document.getElementById('f-end').value = item.end;
        document.getElementById('f-asrama').value = item.asrama;
        document.getElementById('f-pny').value = item.pny;
        document.getElementById('f-peserta').value = item.peserta || '';
        document.getElementById('f-kelas').value = item.kelas || '';
        document.getElementById('f-makan').value = item.makan || '';

        toggleInputs(true);

        if (IS_ADMIN && btnEditTrigger) btnEditTrigger.classList.remove('hidden');
        if (btnHapus) btnHapus.classList.add('hidden');
        if (btnSubmit) btnSubmit.classList.add('hidden');

        if (IS_ADMIN) {
            const isCancelled = item.status === 'cancelled';
            if (btnBatalkan) btnBatalkan.classList.toggle('hidden', isCancelled);
            if (btnAktifkan) btnAktifkan.classList.toggle('hidden', !isCancelled);
        }

        stokModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function enableEditMode() {
        if (!IS_ADMIN) return;

        sheetTitle.innerText = "Ubah Jadwal";
        toggleInputs(false);

        if (btnEditTrigger) btnEditTrigger.classList.add('hidden');
        if (btnHapus) btnHapus.classList.remove('hidden');
        if (btnBatalkan) btnBatalkan.classList.add('hidden');
        if (btnAktifkan) btnAktifkan.classList.add('hidden');

        if (btnSubmit) {
            btnSubmit.classList.remove('hidden');
            btnSubmit.innerText = "Simpan Perubahan";
        }
    }

    function closeModal() {
        stokModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function toggleInputs(disabled) {
        ['f-judul', 'f-start', 'f-end', 'f-asrama', 'f-pny', 'f-peserta', 'f-kelas', 'f-makan'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;

            el.disabled = disabled;
            disabled ? el.classList.add('opacity-70') : el.classList.remove('opacity-70');
        });
    }

    function resetForm() {
        document.getElementById('agenda-form').reset();
        document.getElementById('edit-id').value = '';

        const today = toDateStr(new Date());
        document.getElementById('f-start').value = today;
        document.getElementById('f-end').value = today;
    }

    async function handleSave(e) {
        e.preventDefault();
        if (!IS_ADMIN) return;

        const id = document.getElementById('edit-id').value;

        const formData = new FormData();
        formData.append('judul', document.getElementById('f-judul').value);
        formData.append('start', document.getElementById('f-start').value);
        formData.append('end', document.getElementById('f-end').value);
        formData.append('pny', document.getElementById('f-pny').value);
        formData.append('asrama', document.getElementById('f-asrama').value);
        formData.append('peserta', document.getElementById('f-peserta').value);
        formData.append('kelas', document.getElementById('f-kelas').value);
        formData.append('makan', document.getElementById('f-makan').value);

        let url = 'get_timetable.php?action=create';

        if (id) {
            formData.append('id', id);
            url = 'get_timetable.php?action=update';
        }

        await fetch(url, {
            method: 'POST',
            body: formData
        });

        closeModal();
        showToast('Data tersimpan');
        loadAgenda();
    }

    async function handleDelete() {
        if (!IS_ADMIN) return;
        if (!confirm('Hapus jadwal ini?')) return;

        const id = document.getElementById('edit-id').value;
        const fd = new FormData();
        fd.append('id', id);

        await fetch('get_timetable.php?action=delete', {
            method: 'POST',
            body: fd
        });

        closeModal();
        showToast('Jadwal dihapus');
        loadAgenda();
    }

    async function handleCancel() {
        if (!IS_ADMIN) return;
        if (!confirm('Batalkan kegiatan ini? Status akan berubah menjadi Dibatalkan.')) return;

        const id = document.getElementById('edit-id').value;
        const fd = new FormData();
        fd.append('id', id);
        fd.append('new_status', 'cancelled');

        await fetch('get_timetable.php?action=cancel', {
            method: 'POST',
            body: fd
        });

        closeModal();
        showToast('Kegiatan dibatalkan');
        loadAgenda();
    }

    async function handleReactivate() {
        if (!IS_ADMIN) return;
        if (!confirm('Aktifkan kembali kegiatan ini?')) return;

        const id = document.getElementById('edit-id').value;
        const fd = new FormData();
        fd.append('id', id);
        fd.append('new_status', 'active');

        await fetch('get_timetable.php?action=cancel', {
            method: 'POST',
            body: fd
        });

        closeModal();
        showToast('Kegiatan diaktifkan kembali');
        loadAgenda();
    }

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.innerText = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', 3000);
    }

    function changeMonth(dir) {
        viewDate.setMonth(viewDate.getMonth() + dir);
        selectedMobileDate = null;
        renderAllViews();
    }

    function goThisMonth() {
        viewDate = new Date();
        selectedMobileDate = null;
        renderAllViews();
    }

    function filterByDate(dateStr) {
        const events = agendaData.filter(ev => dateStr >= ev.start && dateStr <= ev.end);

        if (!events.length) {
            showToast('Tidak ada agenda pada tanggal ini');
            return;
        }

        if (events.length === 1) {
            openModalDetail(events[0].id);
            return;
        }

        showToast(`${events.length} agenda pada tanggal ini`);
    }

    function openExportModal() {
        const modal = document.getElementById('exportModal');
        modal.classList.remove('hidden');

        const today = new Date();
        const prior = new Date();
        prior.setDate(today.getDate() - 30);

        document.getElementById('exportTo').value = toDateStr(today);
        document.getElementById('exportFrom').value = toDateStr(prior);
    }

    function closeExportModal() {
        document.getElementById('exportModal').classList.add('hidden');
    }

    function downloadExport() {
        const from = document.getElementById('exportFrom').value;
        const to = document.getElementById('exportTo').value;

        if (!from || !to) {
            alert('Silakan pilih rentang tanggal');
            return;
        }

        if (from > to) {
            alert('Tanggal awal tidak boleh lebih besar dari tanggal akhir');
            return;
        }

        window.location.href = `timetable_export.php?from=${from}&to=${to}`;
        closeExportModal();
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) abortController.abort();
        else loadAgenda();
    });

    window.addEventListener('beforeunload', () => abortController.abort());
    window.addEventListener('pagehide', () => abortController.abort());
</script>