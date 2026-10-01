{{-- View ini milik app/Livewire/MttrForm.php.
     Bagian dashboard membaca data dari render(), sedangkan form bawah memanggil save()
     untuk INSERT data baru ke tabel mttr_records. --}}
<div class="ltro-page">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 14px;
        }

        .ltro-page {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0e1219;
            color: #d1dae8;
            min-height: 100vh;
        }

        /* ===== KPMOG BRAND TOKENS =====
       Blue  : #3AAFE4  (primary accent)
       Amber : #F5A623  (secondary accent, gradient Ã¢â€ â€™ #F07E1A)
       Gray  : #6B7280  (text / neutral)
       Dark bg: layered navy-charcoal
    */

        /* ======================== HEADER ======================== */
        .header {
            background: #12181f;
            border-bottom: 2px solid #3AAFE4;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-box {
            background: transparent;
            padding: 0;
            border-radius: 0;
        }

        .logo-box img {
            height: 40px;
            width: auto;
            display: block;
        }

        .header-title {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.06em;
        }

        /* "OG" accent matches logo gradient */
        .header-title .accent {
            background: linear-gradient(90deg, #F5A623, #F07E1A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-sub {
            color: #6B7280;
            font-size: 12px;
            margin-top: 2px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-input {
            background: linear-gradient(135deg, #F5A623, #F07E1A);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: opacity 0.15s;
        }

        .btn-input:hover {
            opacity: 0.88;
        }

        .clock {
            font-size: 12px;
            color: #3AAFE4;
            font-variant-numeric: tabular-nums;
            background: #0e1219;
            padding: 6px 12px;
            border-radius: 4px;
            border: 0.5px solid #3AAFE4;
            letter-spacing: 0.04em;
        }

        /* ======================== MAIN ======================== */
        .main {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* ======================== STATS ROW ======================== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1px;
            background: #1e2a38;
            border: 0.5px solid #1e2a38;
            border-radius: 6px;
            overflow: hidden;
        }

        .stat-card {
            background: #12181f;
            padding: 1rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 6px;
            border-top: 3px solid transparent;
        }

        .stat-card.blue {
            border-top-color: #3AAFE4;
        }

        .stat-card.amber {
            border-top-color: #F5A623;
        }

        .stat-card.gray {
            border-top-color: #6B7280;
        }

        .stat-label {
            font-size: 10px;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }

        .stat-value .unit {
            font-size: 13px;
            font-weight: 400;
            opacity: 0.65;
            margin-left: 2px;
        }

        .stat-badge {
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 2px;
            width: fit-content;
            letter-spacing: 0.04em;
        }

        .badge-blue {
            background: rgba(58, 175, 228, 0.12);
            color: #3AAFE4;
        }

        .badge-amber {
            background: rgba(245, 166, 35, 0.12);
            color: #F5A623;
        }

        .badge-red {
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
        }

        .badge-green {
            background: rgba(52, 211, 153, 0.12);
            color: #34d399;
        }

        /* ======================== SECTION HEADER ======================== */
        .section-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .section-title {
            font-size: 10px;
            font-weight: 700;
            color: #3AAFE4;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            white-space: nowrap;
        }

        .section-line {
            flex: 1;
            height: 1px;
            background: #1e2a38;
        }

        .section-count {
            font-size: 10px;
            background: #12181f;
            border: 0.5px solid #1e2a38;
            color: #6B7280;
            padding: 2px 8px;
            border-radius: 2px;
        }

        /* ======================== DARK TABLE ======================== */
        .table-wrap {
            border: 0.5px solid #1e2a38;
            border-radius: 6px;
            overflow-x: auto;
        }

        .dark-table {
            width: 100%;
            min-width: 960px;
            border-collapse: collapse;
            font-size: 12px;
        }

        .dark-table thead {
            background: #0e1219;
        }

        .dark-table th {
            padding: 10px 12px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            color: #3AAFE4;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            border-bottom: 1px solid #1e2a38;
            white-space: nowrap;
        }

        .dark-table th.center {
            text-align: center;
        }

        .dark-table th.right {
            text-align: right;
        }

        .dark-table td {
            padding: 10px 12px;
            color: #c8d6e8;
            border-bottom: 0.5px solid #12181f;
            vertical-align: middle;
        }

        .dark-table td.center {
            text-align: center;
        }

        .dark-table td.right {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .dark-table tbody tr:last-child td {
            border-bottom: none;
        }

        .dark-table tbody tr:hover {
            background: #12181f;
        }

        /* pills */
        .unit-pill {
            display: inline-block;
            background: #3AAFE4;
            color: #0e1219;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 2px;
            letter-spacing: 0.06em;
        }

        .cat-unplan {
            display: inline-block;
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 2px;
        }

        .cat-plan {
            display: inline-block;
            background: rgba(52, 211, 153, 0.12);
            color: #34d399;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 2px;
        }

        .cat-ext {
            display: inline-block;
            background: rgba(245, 166, 35, 0.12);
            color: #F5A623;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 2px;
        }

        .downtime-val {
            color: #f87171;
            font-weight: 700;
        }

        .date-shutdown {
            color: #F5A623;
        }

        .date-running {
            color: #3AAFE4;
        }

        .empty-cell {
            color: #4a5568;
            text-align: center;
            padding: 2.5rem;
            font-size: 13px;
        }

        /* ======================== DONUT GRID ======================== */
        .donut-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1px;
            background: #1e2a38;
            border: 0.5px solid #1e2a38;
            border-radius: 6px;
            overflow: hidden;
        }

        .donut-card {
            background: #12181f;
            padding: 1.25rem;
        }

        .donut-card-header {
            font-size: 10px;
            color: #3AAFE4;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 2px;
            font-weight: 700;
        }

        .donut-card-sub {
            font-size: 10px;
            color: #2d3f55;
            margin-bottom: 1rem;
        }

        .donut-wrap {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .donut-svg {
            flex-shrink: 0;
            overflow: visible;
        }

        .donut-segment {
            transform-origin: 42px 42px;
            animation: donut-spin 18s linear infinite;
        }

        .donut-card:nth-child(2) .donut-segment {
            animation-duration: 22s;
            animation-direction: reverse;
        }

        .donut-card:nth-child(3) .donut-segment {
            animation-duration: 26s;
        }

        .donut-card:hover .donut-segment {
            animation-duration: 9s;
        }

        @keyframes donut-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .donut-segment {
                animation: none;
            }
        }

        .donut-legend {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .legend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .legend-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-name {
            font-size: 11px;
            color: #6B7280;
            flex: 1;
        }

        .legend-val {
            font-size: 11px;
            font-weight: 700;
            color: #c8d6e8;
            font-variant-numeric: tabular-nums;
        }

        .availability-panel {
            margin-top: 1rem;
            background: radial-gradient(circle at center, #555 0%, #3d3d3d 46%, #242424 100%);
            border: 0.5px solid #303030;
            border-radius: 6px;
            padding: 1rem;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.26);
        }

        .availability-chart-wrap {
            height: 420px;
            width: 100%;
            min-height: 320px;
        }

        .availability-chart-wrap canvas,
        .availability-svg {
            max-width: 100%;
            width: 100%;
            height: 100%;
            display: block;
        }

        /* ======================== FORM SECTION ======================== */
        .form-section {
            background: #fff;
            border: 0.5px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
            /* top accent line matches blue brand */
            border-top: 3px solid #3AAFE4;
        }

        .form-header {
            padding: 1rem 1.5rem;
            border-bottom: 0.5px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .form-view-link {
            font-size: 12px;
            color: #3AAFE4;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }

        .form-view-link:hover {
            text-decoration: underline;
        }

        .import-bar {
            background: #f8fafc;
            border-bottom: 0.5px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .import-label {
            font-size: 12px;
            font-weight: 700;
            color: #374151;
        }

        .import-sub {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .btn-import {
            background: linear-gradient(135deg, #F5A623, #F07E1A);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 16px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.15s;
        }

        .btn-import:hover {
            opacity: 0.88;
        }

        .form-table-wrap {
            overflow-x: auto;
            padding: 1rem 1.5rem;
        }

        .form-table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
            font-size: 12px;
        }

        .form-table th {
            background: #f0f9ff;
            padding: 8px 10px;
            font-size: 10px;
            font-weight: 700;
            color: #3AAFE4;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            border: 0.5px solid #bae6fd;
            text-align: center;
            white-space: nowrap;
        }

        .form-table td {
            border: 0.5px solid #e2e8f0;
            padding: 0;
            vertical-align: top;
        }

        .form-table input,
        .form-table select,
        .form-table textarea {
            width: 100%;
            border: none;
            outline: none;
            padding: 8px;
            font-size: 12px;
            font-family: inherit;
            background: transparent;
            color: #0f172a;
            resize: none;
        }

        .form-table input:focus,
        .form-table select:focus,
        .form-table textarea:focus {
            background: #f0f9ff;
        }

        /* unit column Ã¢â‚¬â€ amber brand colour */
        .form-table td.amber-col {
            background: rgba(245, 166, 35, 0.08);
        }

        .form-table td.amber-col select {
            color: #b45309;
            font-weight: 700;
            text-align: center;
        }

        /* auto-computed downtime */
        .form-table td.auto-col {
            background: #f8fafc;
        }

        .auto-val {
            padding: 8px;
            text-align: center;
            font-weight: 700;
            font-size: 12px;
            color: #3AAFE4;
        }

        .alert-success {
            margin: 0.75rem 1.5rem 0;
            background: #f0fdf4;
            border: 0.5px solid #bbf7d0;
            color: #166534;
            padding: 0.75rem 1rem;
            font-size: 12px;
            border-radius: 4px;
        }

        .alert-error {
            margin: 0.75rem 1.5rem 0;
            background: #fef2f2;
            border: 0.5px solid #fecaca;
            color: #991b1b;
            padding: 0.75rem 1rem;
            font-size: 12px;
            border-radius: 4px;
        }

        .alert-error ul {
            margin-top: 6px;
            padding-left: 1.2rem;
        }

        .alert-error li {
            margin-top: 3px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 1rem 1.5rem;
            border-top: 0.5px solid #e2e8f0;
        }

        .btn-view-data {
            border: 0.5px solid #3AAFE4;
            background: transparent;
            color: #3AAFE4;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 16px;
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn-view-data:hover {
            background: rgba(58, 175, 228, 0.08);
        }

        .btn-save {
            background: linear-gradient(135deg, #F5A623, #F07E1A);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 18px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.15s;
        }

        .btn-save:hover {
            opacity: 0.88;
        }

        .btn-save:disabled {
            opacity: 0.5;
            cursor: wait;
        }

        /* scroll hint */
        .scroll-hint {
            font-size: 10px;
            color: #4a5568;
            text-align: right;
            margin-bottom: 4px;
        }

        /* ======================== LIGHT THEME OVERRIDE ======================== */
        .ltro-page {
            background: #f3f4f6;
            color: #0f172a;
        }

        .ltro-page .header {
            background: #ffffff;
            border-bottom-color: #3AAFE4;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .ltro-page .header-title {
            color: #0f172a;
        }

        .ltro-page .header-sub,
        .ltro-page .stat-label,
        .ltro-page .scroll-hint,
        .ltro-page .empty-cell,
        .ltro-page .legend-name {
            color: #64748b;
        }

        .ltro-page .clock {
            background: #f8fafc;
            color: #3AAFE4;
        }

        .ltro-page .stats-row,
        .ltro-page .donut-grid {
            background: #e5e7eb;
            border-color: #e5e7eb;
        }

        .ltro-page .stat-card,
        .ltro-page .donut-card {
            background: #ffffff;
        }

        .ltro-page .section-line {
            background: #e5e7eb;
        }

        .ltro-page .section-count {
            background: #ffffff;
            border-color: #e5e7eb;
            color: #64748b;
        }

        .ltro-page .table-wrap {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .ltro-page .dark-table thead {
            background: #f0f9ff;
        }

        .ltro-page .dark-table th {
            color: #0369a1;
            border-bottom-color: #bae6fd;
        }

        .ltro-page .dark-table td {
            color: #0f172a;
            border-bottom-color: #e5e7eb;
        }

        .ltro-page .dark-table tbody tr:hover {
            background: #f8fafc;
        }

        .ltro-page .unit-pill {
            background: #e0f2fe;
            color: #0369a1;
            border: 0.5px solid #7dd3fc;
        }

        .ltro-page .cat-unplan {
            background: #fef2f2;
            color: #b91c1c;
            border: 0.5px solid #fecaca;
        }

        .ltro-page .cat-plan {
            background: #f0fdf4;
            color: #15803d;
            border: 0.5px solid #bbf7d0;
        }

        .ltro-page .cat-ext {
            background: #fff7ed;
            color: #c2410c;
            border: 0.5px solid #fed7aa;
        }

        .ltro-page .downtime-val {
            color: #b91c1c;
        }

        .ltro-page .date-shutdown {
            color: #c2410c;
        }

        .ltro-page .date-running {
            color: #0369a1;
        }

        .ltro-page .donut-card-sub {
            color: #64748b;
        }

        .ltro-page .donut-svg > circle:first-of-type {
            stroke: #e5e7eb;
        }

        .ltro-page .donut-svg text {
            fill: #0f172a;
        }

        .ltro-page .donut-svg text + text {
            fill: #64748b;
        }

        .ltro-page .donut-segment {
            filter: none;
        }

        .ltro-page .legend-val {
            color: #0f172a;
        }

        /* ======================== PLAIN TEXT / NO LIGHT EFFECTS ======================== */
        .ltro-page,
        .ltro-page .header-title,
        .ltro-page .header-title .accent,
        .ltro-page .header-sub,
        .ltro-page .stat-label,
        .ltro-page .stat-value,
        .ltro-page .stat-value .unit,
        .ltro-page .stat-badge,
        .ltro-page .section-title,
        .ltro-page .section-count,
        .ltro-page .dark-table th,
        .ltro-page .dark-table td,
        .ltro-page .unit-pill,
        .ltro-page .cat-unplan,
        .ltro-page .cat-plan,
        .ltro-page .cat-ext,
        .ltro-page .downtime-val,
        .ltro-page .date-shutdown,
        .ltro-page .date-running,
        .ltro-page .donut-card-header,
        .ltro-page .donut-card-sub,
        .ltro-page .legend-name,
        .ltro-page .legend-val,
        .ltro-page .clock,
        .ltro-page .scroll-hint,
        .ltro-page .btn-input,
        .ltro-page .form-title,
        .ltro-page .form-view-link,
        .ltro-page .import-label,
        .ltro-page .import-sub,
        .ltro-page .btn-import,
        .ltro-page .btn-view-data,
        .ltro-page .btn-save,
        .ltro-page .auto-val {
            color: #0f172a !important;
        }

        .ltro-page :where(h1, h2, h3, h4, h5, h6, p, span, div, a, button, label, small, strong, em, th, td, input, select, textarea, option) {
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
        }

        .ltro-page .header-title .accent {
            background: none !important;
            -webkit-text-fill-color: #0f172a !important;
        }

        .ltro-page .header,
        .ltro-page .stats-row,
        .ltro-page .stat-card,
        .ltro-page .table-wrap,
        .ltro-page .donut-grid,
        .ltro-page .donut-card,
        .ltro-page .form-section,
        .ltro-page .btn-input,
        .ltro-page .btn-import,
        .ltro-page .btn-view-data,
        .ltro-page .btn-save,
        .ltro-page .donut-segment {
            box-shadow: none !important;
            filter: none !important;
            text-shadow: none !important;
        }

        .ltro-page .donut-svg text,
        .ltro-page .donut-svg text + text {
            fill: #0f172a !important;
        }

        /* ======================== RESPONSIVE ======================== */
        @media (max-width: 640px) {
            .main {
                padding: 1rem;
                gap: 1rem;
            }

            .header {
                padding: 0.75rem 1rem;
            }

            .stats-row {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>

    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="header-left">
            <div class="logo-box">
                <img src="{{ asset('img/brands/kpmog.png') }}" alt="KPMOG" style="height:38px; width:auto;">
            </div>
            <div>
                <div class="header-title">
                    REPORT LTR<span class="accent">O</span>
                </div>
                <div class="header-sub">Monitoring shutdown &amp; downtime</div>
            </div>
        </div>
        <div class="header-right">
            <a href="{{ url('/panel') }}" class="btn-input">
                <i class="ti ti-layout-dashboard" aria-hidden="true"></i> Kembali ke Dashboard
            </a>
            <a href="{{ route('daily.input') }}" class="btn-input">
                <i class="ti ti-plus" aria-hidden="true"></i> Input Harian
            </a>
            <a href="{{ route('availability.ltro-1b') }}" class="btn-input">
                <i class="ti ti-chart-bar" aria-hidden="true"></i> Availability LTRO-1B
            </a>
            <div class="clock" id="live-clock">Ã¢â‚¬â€</div>
        </div>
    </header>

    <div class="main">

        <!-- ===== STATS ROW ===== -->
        {{-- Angka ringkasan ini berasal dari query MttrRecord di method render(). --}}
        <div class="stats-row">
            <div class="stat-card blue">
                <div class="stat-label">Total Event</div>
                <div class="stat-value">{{ number_format($totalRecords) }}</div>
                <span class="stat-badge badge-blue">Bulan ini</span>
            </div>
            <div class="stat-card amber">
                <div class="stat-label">Downtime Unplan</div>
                <div class="stat-value">
                    {{ number_format($unplannedDowntime) }}<span class="unit">mnt</span>
                </div>
                <span class="stat-badge badge-amber">Unplanned</span>
            </div>
            <div class="stat-card gray">
                <div class="stat-label">Downtime Plan</div>
                <div class="stat-value">
                    {{ number_format($plannedDowntime ?? 0) }}<span class="unit">mnt</span>
                </div>
                <span class="stat-badge badge-green">Planned</span>
            </div>
            <div class="stat-card blue">
                <div class="stat-label">Unit Aktif</div>
                <div class="stat-value">{{ count($unitDashboard) }}</div>
                <span class="stat-badge badge-blue">Unit</span>
            </div>
            <div class="stat-card amber">
                <div class="stat-label">MTTR Rata-rata</div>
                <div class="stat-value">
                    {{ $totalRecords > 0 ? number_format($unplannedDowntime / $totalRecords, 0) : 0 }}<span
                        class="unit">mnt</span>
                </div>
                <span class="stat-badge badge-amber">Per event</span>
            </div>
        </div>

        <!-- ===== LAST EVENT TABLE ===== -->
        {{-- Tabel ini menampilkan record MTTR terakhir dari variable $recentRecords. --}}
        <div>
            <div class="section-head">
                <span class="section-title">Last Event</span>
                <div class="section-line"></div>
                <span class="section-count">{{ $recentRecords->count() }} record</span>
            </div>
            <p class="scroll-hint"><i class="ti ti-arrows-horizontal" aria-hidden="true"></i> Geser untuk lihat semua
                kolom</p>
            <div class="table-wrap">
                <table class="dark-table" style="min-width:1400px;">
                    <thead>
                        <tr>
                            <th class="center">No</th>
                            <th>Month</th>
                            <th>Rental Period</th>
                            <th>Date Shutdown</th>
                            <th>Date Running</th>
                            <th class="center">Unit</th>
                            <th class="center">Time Shutdown</th>
                            <th class="center">Time Running</th>
                            <th class="right">Downtime (mnt)</th>
                            @if($rhPk100Enabled)
                            <th class="right">RH</th>
                            <th class="right">PK-100</th>
                            @endif
                            <th>Category</th>
                            <th>Indication</th>
                            <th>Immediate Cause</th>
                            <th>Activity Troubleshooting</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentRecords as $record)
                        <tr>
                            <td class="center">{{ $record->sequence_no ?? $loop->iteration }}</td>
                            <td>{{ $record->shutdown_month ?
                                \Carbon\Carbon::parse($record->shutdown_month)->format('M-y') : 'Ã¢â‚¬â€' }}</td>
                            <td>{{ $record->rental_period ?? 'Ã¢â‚¬â€' }}</td>
                            <td class="date-shutdown">{{ $record->shutdown_datetime ?
                                \Carbon\Carbon::parse($record->shutdown_datetime)->format('d-M-y') : 'Ã¢â‚¬â€' }}</td>
                            <td class="date-running">{{ $record->running_datetime ?
                                \Carbon\Carbon::parse($record->running_datetime)->format('d-M-y') : 'Ã¢â‚¬â€' }}</td>
                            <td class="center"><span class="unit-pill">{{ $record->unit?->unit_name ?? 'Ã¢â‚¬â€' }}</span>
                            </td>
                            <td class="center">{{ $record->shutdown_datetime ?
                                \Carbon\Carbon::parse($record->shutdown_datetime)->format('H:i') : 'Ã¢â‚¬â€' }}</td>
                            <td class="center">{{ $record->running_datetime ?
                                \Carbon\Carbon::parse($record->running_datetime)->format('H:i') : 'Ã¢â‚¬â€' }}</td>
                            <td class="right"><span class="downtime-val">{{ number_format($record->downtime_minutes)
                                    }}</span></td>
                            @if($rhPk100Enabled)
                            <td class="right">{{ $record->running_hours !== null ? number_format($record->running_hours, 2) : '-' }}</td>
                            <td class="right">{{ $record->pk_100 !== null ? number_format($record->pk_100, 2) : '-' }}</td>
                            @endif
                            <td>
                                @php $catName = strtolower($record->categoryMaster?->category_name ?? $record->category
                                ?? ''); @endphp
                                @if(str_contains($catName,'unplan'))
                                <span class="cat-unplan">{{ $record->categoryMaster?->category_name ?? $record->category
                                    }}</span>
                                @elseif(str_contains($catName,'plan'))
                                <span class="cat-plan">{{ $record->categoryMaster?->category_name ?? $record->category
                                    }}</span>
                                @else
                                <span class="cat-ext">{{ $record->categoryMaster?->category_name ?? $record->category ??
                                    'Ã¢â‚¬â€' }}</span>
                                @endif
                            </td>
                            <td>{{ $record->indication ?: 'Ã¢â‚¬â€' }}</td>
                            <td>{{ $record->immediate_cause ?: 'Ã¢â‚¬â€' }}</td>
                            <td>{{ $record->activity_troubleshooting ?: 'Ã¢â‚¬â€' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $rhPk100Enabled ? 15 : 13 }}" class="empty-cell">Belum ada event MTTR.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== DONUT SUMMARY ===== -->
        <div>
            <div class="section-head">
                <span class="section-title">Ringkasan</span>
                <div class="section-line"></div>
            </div>
            <div class="donut-grid">

                <!-- Downtime Unplan per Unit -->
                @php
                $circumference = 2 * M_PI * 30;
                $unitColors = ['#3AAFE4','#F5A623','#6B7280','#34d399','#f87171','#a78bfa'];
                $catColors = ['#f87171','#34d399','#F5A623','#3AAFE4','#a78bfa'];
                $totalDT = $unplannedDowntime ?: 1;
                $totalEv = $totalRecords ?: 1;
                @endphp

                <div class="donut-card">
                    <div class="donut-card-header">Downtime Unplan</div>
                    <div class="donut-card-sub">Menit per unit</div>
                    <div class="donut-wrap">
                        <svg class="donut-svg" width="84" height="84" viewBox="0 0 84 84" role="img"
                            aria-label="Downtime unplan per unit. Total {{ number_format($unplannedDowntime) }} menit.">
                            <circle cx="42" cy="42" r="30" fill="none" stroke="#e5e7eb" stroke-width="14" />
                            @php $off = $circumference * 0.25; @endphp
                            @foreach($unitDashboard as $i => $u)
                            @php
                            $d = $circumference * ($u['unplan_downtime'] / $totalDT);
                            $g = $circumference - $d;
                            $c = $unitColors[$i % count($unitColors)];
                            @endphp
                            <circle class="donut-segment" cx="42" cy="42" r="30" fill="none" stroke="{{ $c }}" stroke-width="14"
                                stroke-dasharray="{{ round($d,2) }} {{ round($g,2) }}"
                                stroke-dashoffset="{{ round($off,2) }}" stroke-linecap="butt" />
                            @php $off -= $d; @endphp
                            @endforeach
                            <text x="42" y="39" text-anchor="middle" font-size="11" font-weight="700" fill="#0f172a">{{
                                number_format($unplannedDowntime) }}</text>
                            <text x="42" y="52" text-anchor="middle" font-size="9" fill="#64748b">mnt</text>
                        </svg>
                        <div class="donut-legend">
                            @foreach($unitDashboard as $i => $u)
                            <div class="legend-row">
                                <span class="legend-dot"
                                    style="background:{{ $unitColors[$i % count($unitColors)] }}"></span>
                                <span class="legend-name">{{ $u['name'] }}</span>
                                <span class="legend-val">{{ number_format($u['unplan_downtime']) }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Shutdown per Unit -->
                <div class="donut-card">
                    <div class="donut-card-header">Total Shutdown</div>
                    <div class="donut-card-sub">Event per unit</div>
                    <div class="donut-wrap">
                        <svg class="donut-svg" width="84" height="84" viewBox="0 0 84 84" role="img"
                            aria-label="Total shutdown per unit. Total {{ number_format($totalRecords) }} event.">
                            <circle cx="42" cy="42" r="30" fill="none" stroke="#e5e7eb" stroke-width="14" />
                            @php $off2 = $circumference * 0.25; @endphp
                            @foreach($unitDashboard as $i => $u)
                            @php
                            $d2 = $circumference * ($u['shutdown_count'] / $totalEv);
                            $g2 = $circumference - $d2;
                            @endphp
                            <circle class="donut-segment" cx="42" cy="42" r="30" fill="none"
                                stroke="{{ $unitColors[$i % count($unitColors)] }}" stroke-width="14"
                                stroke-dasharray="{{ round($d2,2) }} {{ round($g2,2) }}"
                                stroke-dashoffset="{{ round($off2,2) }}" stroke-linecap="butt" />
                            @php $off2 -= $d2; @endphp
                            @endforeach
                            <text x="42" y="39" text-anchor="middle" font-size="13" font-weight="700" fill="#0f172a">{{
                                number_format($totalRecords) }}</text>
                            <text x="42" y="52" text-anchor="middle" font-size="9" fill="#64748b">event</text>
                        </svg>
                        <div class="donut-legend">
                            @foreach($unitDashboard as $i => $u)
                            <div class="legend-row">
                                <span class="legend-dot"
                                    style="background:{{ $unitColors[$i % count($unitColors)] }}"></span>
                                <span class="legend-name">{{ $u['name'] }}</span>
                                <span class="legend-val">{{ number_format($u['shutdown_count']) }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Shutdown per Category -->
                <div class="donut-card">
                    <div class="donut-card-header">Total Shutdown</div>
                    <div class="donut-card-sub">Event per kategori</div>
                    <div class="donut-wrap">
                        <svg class="donut-svg" width="84" height="84" viewBox="0 0 84 84" role="img"
                            aria-label="Total shutdown per kategori. Total {{ number_format($totalRecords) }} event.">
                            <circle cx="42" cy="42" r="30" fill="none" stroke="#e5e7eb" stroke-width="14" />
                            @php $off3 = $circumference * 0.25; @endphp
                            @foreach($categoryDashboard as $i => $cat)
                            @php
                            $d3 = $circumference * ($cat['shutdown_count'] / $totalEv);
                            $g3 = $circumference - $d3;
                            @endphp
                            <circle class="donut-segment" cx="42" cy="42" r="30" fill="none" stroke="{{ $catColors[$i % count($catColors)] }}"
                                stroke-width="14" stroke-dasharray="{{ round($d3,2) }} {{ round($g3,2) }}"
                                stroke-dashoffset="{{ round($off3,2) }}" stroke-linecap="butt" />
                            @php $off3 -= $d3; @endphp
                            @endforeach
                            <text x="42" y="39" text-anchor="middle" font-size="13" font-weight="700" fill="#0f172a">{{
                                number_format($totalRecords) }}</text>
                            <text x="42" y="52" text-anchor="middle" font-size="9" fill="#64748b">event</text>
                        </svg>
                        <div class="donut-legend">
                            @foreach($categoryDashboard as $i => $cat)
                            <div class="legend-row">
                                <span class="legend-dot"
                                    style="background:{{ $catColors[$i % count($catColors)] }}"></span>
                                <span class="legend-name">{{ $cat['name'] }}</span>
                                <span class="legend-val">{{ number_format($cat['shutdown_count']) }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>
        </div>

        @if($rhPk100Enabled)
        <!-- ===== AVAILABILITY KPI GRAPH ===== -->
        <div class="availability-panel" wire:ignore>
            <div class="availability-chart-wrap">
                @php
                    $availabilityLabels = collect($pk100AvailabilityChart['labels'] ?? [])->values();
                    $availabilityUnits = collect($pk100AvailabilityChart['unit_series'] ?? [])->values();
                    $actualKpiValues = collect($pk100AvailabilityChart['actual_kpi'] ?? [])->values();
                    $targetKpiValues = collect($pk100AvailabilityChart['target_kpi'] ?? [])->values();
                    $chartTitle = $pk100AvailabilityChart['title'] ?? 'Availability (%)';
                    $chartWidth = 940;
                    $chartHeight = 460;
                    $plotLeft = 70;
                    $plotTop = 72;
                    $plotWidth = 790;
                    $plotHeight = 250;
                    $plotBottom = $plotTop + $plotHeight;
                    $monthCount = max($availabilityLabels->count(), 1);
                    $unitCount = max($availabilityUnits->count(), 1);
                    $groupWidth = $plotWidth / $monthCount;
                    $barWidth = min(16, max(5, ($groupWidth * 0.62) / $unitCount));
                    $unitColors = ['#5B9BD5', '#ED7D31', '#A5A5A5', '#70AD47'];
                    $pointX = fn ($index) => $plotLeft + ($groupWidth * $index) + ($groupWidth / 2);
                    $pointY = fn ($value) => $plotBottom - ((max(min((float) $value, 100), 0) / 100) * $plotHeight);
                    $actualPoints = $actualKpiValues
                        ->map(fn ($value, $index) => round($pointX($index), 2).','.round($pointY($value), 2))
                        ->implode(' ');
                    $targetPoints = $targetKpiValues
                        ->map(fn ($value, $index) => round($pointX($index), 2).','.round($pointY($value), 2))
                        ->implode(' ');
                @endphp

                <svg class="availability-svg" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="{{ $chartTitle }}">
                    <defs>
                        <radialGradient id="availabilityBg" cx="50%" cy="45%" r="78%">
                            <stop offset="0%" stop-color="#555555" />
                            <stop offset="56%" stop-color="#3d3d3d" />
                            <stop offset="100%" stop-color="#242424" />
                        </radialGradient>
                        <filter id="availabilityShadow" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#000000" flood-opacity="0.45" />
                        </filter>
                    </defs>

                    <rect width="{{ $chartWidth }}" height="{{ $chartHeight }}" fill="url(#availabilityBg)" rx="4" />
                    <text x="{{ $chartWidth / 2 }}" y="34" text-anchor="middle" fill="#f8fafc" font-size="28" font-weight="700" letter-spacing="2">
                        {{ $chartTitle }}
                    </text>

                    @foreach([0, 20, 40, 60, 80, 100] as $tick)
                        @php $tickY = $pointY($tick); @endphp
                        <line x1="{{ $plotLeft }}" y1="{{ $tickY }}" x2="{{ $plotLeft + $plotWidth }}" y2="{{ $tickY }}" stroke="rgba(255,255,255,0.16)" stroke-width="1" />
                        <text x="{{ $plotLeft - 18 }}" y="{{ $tickY + 5 }}" text-anchor="end" fill="#f8fafc" font-size="15">
                            {{ number_format($tick, 2) }}%
                        </text>
                    @endforeach

                    <line x1="{{ $plotLeft }}" y1="{{ $plotTop }}" x2="{{ $plotLeft }}" y2="{{ $plotBottom }}" stroke="rgba(255,255,255,0.40)" stroke-width="1" />
                    <line x1="{{ $plotLeft }}" y1="{{ $plotBottom }}" x2="{{ $plotLeft + $plotWidth }}" y2="{{ $plotBottom }}" stroke="rgba(255,255,255,0.55)" stroke-width="2" />

                    @foreach($availabilityLabels as $monthIndex => $monthLabel)
                        @php
                            $groupStart = $plotLeft + ($groupWidth * $monthIndex);
                            $barStart = $groupStart + (($groupWidth - ($barWidth * $unitCount)) / 2);
                        @endphp

                        @foreach($availabilityUnits as $unitIndex => $unitSeries)
                            @php
                                $value = (float) collect($unitSeries['values'] ?? [])->get($monthIndex, 0);
                                $barHeight = ($value / 100) * $plotHeight;
                                $barX = $barStart + ($barWidth * $unitIndex);
                                $barY = $plotBottom - $barHeight;
                            @endphp
                            <rect x="{{ round($barX, 2) }}" y="{{ round($barY, 2) }}" width="{{ round($barWidth * 0.82, 2) }}" height="{{ round($barHeight, 2) }}" fill="{{ $unitColors[$unitIndex % count($unitColors)] }}" opacity="0.95" />
                        @endforeach

                        <text x="{{ round($pointX($monthIndex), 2) }}" y="{{ $plotBottom + 18 }}" text-anchor="end" fill="#f8fafc" font-size="15" transform="rotate(-90 {{ round($pointX($monthIndex), 2) }} {{ $plotBottom + 18 }})">
                            {{ $monthLabel }}
                        </text>
                    @endforeach

                    @if($targetPoints !== '')
                        <polyline points="{{ $targetPoints }}" fill="none" stroke="#4472C4" stroke-width="3" />
                    @endif

                    @if($actualPoints !== '')
                        <polyline points="{{ $actualPoints }}" fill="none" stroke="#FFF200" stroke-width="4" stroke-linejoin="round" stroke-linecap="round" filter="url(#availabilityShadow)" />
                        @foreach($actualKpiValues as $index => $value)
                            @php
                                $labelX = $pointX($index);
                                $labelY = $pointY($value);
                            @endphp
                            <circle cx="{{ round($labelX, 2) }}" cy="{{ round($labelY, 2) }}" r="7" fill="#FFF200" stroke="#FFF200" />
                            <text x="{{ round($labelX + 9, 2) }}" y="{{ round($labelY + 34, 2) }}" fill="#ffffff" font-size="15" font-weight="700" transform="rotate(62 {{ round($labelX + 9, 2) }} {{ round($labelY + 34, 2) }})">
                                {{ number_format((float) $value, 2) }}%
                            </text>
                        @endforeach
                    @endif

                    @php
                        $legendX = $plotLeft + 30;
                        $legendY = 392;
                        $legendGap = 132;
                    @endphp
                    @foreach($availabilityUnits as $unitIndex => $unitSeries)
                        @php
                            $x = $legendX + ($legendGap * $unitIndex);
                        @endphp
                        <rect x="{{ $x }}" y="{{ $legendY - 9 }}" width="42" height="10" fill="{{ $unitColors[$unitIndex % count($unitColors)] }}" />
                        <text x="{{ $x + 48 }}" y="{{ $legendY }}" fill="#f8fafc" font-size="16">{{ $unitSeries['label'] ?? 'Unit' }}</text>
                    @endforeach

                    @php $lineLegendY = 430; @endphp
                    <line x1="{{ $legendX }}" y1="{{ $lineLegendY - 5 }}" x2="{{ $legendX + 42 }}" y2="{{ $lineLegendY - 5 }}" stroke="#FFF200" stroke-width="4" />
                    <circle cx="{{ $legendX + 21 }}" cy="{{ $lineLegendY - 5 }}" r="6" fill="#FFF200" />
                    <text x="{{ $legendX + 48 }}" y="{{ $lineLegendY }}" fill="#f8fafc" font-size="16">Actual KPI</text>
                    <line x1="{{ $legendX + 170 }}" y1="{{ $lineLegendY - 5 }}" x2="{{ $legendX + 212 }}" y2="{{ $lineLegendY - 5 }}" stroke="#4472C4" stroke-width="3" />
                    <text x="{{ $legendX + 218 }}" y="{{ $lineLegendY }}" fill="#f8fafc" font-size="16">Target KPI</text>
                </svg>
            </div>
        </div>
        @endif

        <!-- ===== INPUT FORM ===== -->
        <div class="form-section">
            <div class="form-header">
                <span class="form-title">Input data MTTR</span>
                <a href="{{ route('mttr-records.index') }}" class="form-view-link">
                    <i class="ti ti-table" aria-hidden="true"></i> View Data
                </a>
            </div>

            @if (session()->has('success'))
            <div class="alert-success">
                <i class="ti ti-circle-check" aria-hidden="true"></i>
                {{ session('success') }}
            </div>
            @endif

            @if (session()->has('import_warnings'))
            <div class="alert-error">
                <strong>Catatan import:</strong>
                <ul>
                    @foreach(session('import_warnings') as $warning)
                    <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Import -->
            <form method="POST" action="{{ route('mttr-records.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="import-bar">
                    <div>
                        <div class="import-label">Import dari Excel</div>
                        <div class="import-sub">Format didukung: .xlsx, .xls, .csv</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <input id="mttr_file" name="mttr_file" type="file" accept=".xlsx,.xls,.csv" required
                            style="font-size:11px;">
                        @error('mttr_file')
                        <div style="font-size:11px;">{{ $message }}</div>
                        @enderror
                        <button type="submit" class="btn-import">
                            <i class="ti ti-upload" aria-hidden="true"></i> Import Excel
                        </button>
                    </div>
                </div>
            </form>

            <!-- Manual input -->
            {{-- Submit form ini memanggil method save() di app/Livewire/MttrForm.php. --}}
            <form wire:submit.prevent="save">
                <div class="form-table-wrap">
                    <table class="form-table">
                        <thead>
                            <tr>
                                <th style="width:44px;">No</th>
                                <th style="width:96px;">Month</th>
                                <th style="width:110px;">Rental Period</th>
                                <th style="width:110px;">Date<br>Shutdown</th>
                                <th style="width:110px;">Date<br>Running</th>
                                <th style="width:96px;">Unit</th>
                                <th style="width:90px;">Time<br>Shutdown</th>
                                <th style="width:90px;">Time<br>Running</th>
                                <th style="width:96px;">Downtime<br>(Menit)</th>
                                @if($rhPk100Enabled)
                                <th style="width:90px;">RH</th>
                                <th style="width:90px;">PK-100</th>
                                @endif
                                <th style="width:110px;">Category</th>
                                <th>Indication</th>
                                <th>Immediate Cause</th>
                                <th>Activity Troubleshooting</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="number" wire:model="sequence_no" placeholder="Ã¢â‚¬â€"
                                        style="text-align:center;"></td>
                                <td><input type="month" wire:model="report_month"></td>
                                <td><input type="text" wire:model="rental_period" placeholder="Period 16"></td>
                                <td><input type="date" wire:model.live="shutdown_date"></td>
                                <td><input type="date" wire:model.live="running_date"></td>
                                <td class="amber-col">
                                    <select wire:model="unit_id">
                                        <option value="">Unit</option>
                                        @foreach($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="time" step="1" wire:model.live="shutdown_time"
                                        style="text-align:center;"></td>
                                <td><input type="time" step="1" wire:model.live="running_time"
                                        style="text-align:center;"></td>
                                <td class="auto-col">
                                    <div class="auto-val">{{ number_format($downtime_minutes) }}</div>
                                </td>
                                @if($rhPk100Enabled)
                                <td><input type="number" step="0.01" min="0" wire:model="running_hours"
                                        placeholder="0.00" style="text-align:right;"></td>
                                <td><input type="number" step="0.01" min="0" wire:model="pk_100"
                                        placeholder="0.00" style="text-align:right;"></td>
                                @endif
                                <td>
                                    <select wire:model="category_id">
                                        <option value="">Category</option>
                                        @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><textarea wire:model="indication" rows="3"
                                        placeholder="Deskripsi indikasi..."></textarea></td>
                                <td><textarea wire:model="immediate_cause" rows="3"
                                        placeholder="Penyebab langsung..."></textarea></td>
                                <td><textarea wire:model="activity_troubleshooting" rows="3"
                                        placeholder="Langkah troubleshooting..."></textarea></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if ($errors->any())
                <div class="alert-error" style="margin: 0 1.5rem 0.75rem;">
                    <strong>Data input belum lengkap.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="form-actions">
                    <a href="{{ route('mttr-records.index') }}" class="btn-view-data">
                        <i class="ti ti-table" aria-hidden="true"></i> View Data
                    </a>
                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="btn-save">
                        <span wire:loading.remove wire:target="save">
                            <i class="ti ti-device-floppy" aria-hidden="true"></i> Simpan Data MTTR
                        </span>
                        <span wire:loading wire:target="save">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>

    </div><!-- /main -->

    <script>
        function tick() {
      const el = document.getElementById('live-clock');
      if (!el) return;
      const now = new Date();
      const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
      const d  = String(now.getDate()).padStart(2, '0');
      const m  = months[now.getMonth()];
      const y  = now.getFullYear();
      const hh = String(now.getHours()).padStart(2, '0');
      const mm = String(now.getMinutes()).padStart(2, '0');
      const ss = String(now.getSeconds()).padStart(2, '0');
      el.textContent = `${d} ${m} ${y}  ${hh}:${mm}:${ss}`;
    }
    tick();
    setInterval(tick, 1000);
    </script>

</div>
