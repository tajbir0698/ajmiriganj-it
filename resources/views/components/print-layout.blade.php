<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Report' }} - {{ \App\Models\Setting::get('shop_name', \App\Models\Setting::get('company_name', config('app.name', 'Ajmiriganj IT'))) }}</title>
    <style>
        @page {
            @if(isset($format) && $format === '80mm')
                size: 80mm auto;
                margin: 4mm 3mm;
            @else
                size: A4 portrait;
                margin: 12mm;
            @endif
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #111827;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans Bengali", sans-serif;
            font-size: {{ (isset($format) && $format === '80mm') ? '10px' : '12px' }};
            line-height: 1.4;
        }

        /* Screen toolbar (hidden in print) */
        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 16px;
            font-size: 13px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .print-toolbar .btn-group {
            display: flex;
            gap: 8px;
        }

        .print-toolbar button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: #4f46e5;
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-secondary {
            background: #ffffff;
            color: #374151;
            border-color: #d1d5db !important;
        }
        .btn-secondary:hover {
            background: #f9fafb;
        }

        /* Report Paper Container */
        .report-paper {
            max-width: {{ (isset($format) && $format === '80mm') ? '74mm' : '210mm' }};
            margin: 0 auto;
            padding: {{ (isset($format) && $format === '80mm') ? '0' : '16px 20px' }};
            background: #ffffff;
        }

        /* Report Header */
        .report-header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #111827;
        }

        .shop-name {
            font-size: {{ (isset($format) && $format === '80mm') ? '14px' : '18px' }};
            font-weight: 800;
            color: #111827;
            margin: 0 0 2px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .shop-meta {
            font-size: {{ (isset($format) && $format === '80mm') ? '9px' : '11px' }};
            color: #4b5563;
            margin: 0 0 6px 0;
        }

        .report-title {
            font-size: {{ (isset($format) && $format === '80mm') ? '12px' : '15px' }};
            font-weight: 700;
            color: #1f2937;
            margin: 4px 0 2px 0;
            text-transform: uppercase;
        }

        .report-period {
            font-size: {{ (isset($format) && $format === '80mm') ? '9px' : '11px' }};
            font-weight: 500;
            color: #6b7280;
            margin: 0;
        }

        .report-meta-bar {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #6b7280;
            margin-bottom: 12px;
            padding-bottom: 4px;
            border-bottom: 1px solid #f3f4f6;
        }

        /* Tables & Typography */
        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
            margin-bottom: 14px;
        }

        thead {
            display: table-header-group;
        }

        tfoot {
            display: table-footer-group;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        th, td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        th {
            background-color: #f9fafb !important;
            font-weight: 700;
            color: #374151;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .tabular-nums, .text-right {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: 700;
        }

        .font-semibold {
            font-weight: 600;
        }

        /* Badges & Highlights (Light colors only) */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-success {
            background-color: #ecfdf5 !important;
            color: #065f46 !important;
            border: 1px solid #a7f3d0;
        }
        .badge-danger {
            background-color: #fef2f2 !important;
            color: #991b1b !important;
            border: 1px solid #fecaca;
        }
        .badge-warning {
            background-color: #fffbeb !important;
            color: #92400e !important;
            border: 1px solid #fde68a;
        }

        .summary-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }

        /* Page Footer for Print */
        .report-footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #9ca3af;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            .print-toolbar {
                display: none !important;
            }
            body {
                background: #ffffff !important;
            }
            .report-paper {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <div>
            <strong>{{ $title ?? 'Report' }}</strong>
            <span style="color: #6b7280; margin-left: 8px;">({{ (isset($format) && $format === '80mm') ? '80mm POS Thermal' : 'A4 Portrait' }})</span>
        </div>
        <div class="btn-group">
            <button class="btn-secondary" onclick="window.close()">Close</button>
            <button class="btn-primary" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print Report
            </button>
        </div>
    </div>

    <div class="report-paper">
        <header class="report-header">
            <h1 class="shop-name">{{ \App\Models\Setting::get('shop_name', \App\Models\Setting::get('company_name', config('app.name', 'Ajmiriganj IT'))) }}</h1>
            @php
                $address = \App\Models\Setting::get('shop_address', \App\Models\Setting::get('company_address'));
                $phone = \App\Models\Setting::get('shop_phone', \App\Models\Setting::get('company_phone'));
            @endphp
            @if($address || $phone)
                <p class="shop-meta">{{ implode(' | ', array_filter([$address, $phone])) }}</p>
            @endif
            <h2 class="report-title">{{ $title }}</h2>
            @if(isset($periodText) && !empty($periodText))
                <p class="report-period">{{ $periodText }}</p>
            @endif
        </header>

        <div class="report-meta-bar">
            <span>Generated: {{ now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->format('d M Y, h:i A') }}</span>
            <span>User: {{ auth()->user()?->name ?? 'System' }}</span>
        </div>

        <main>
            {{ $slot }}
        </main>

        <footer class="report-footer">
            <span>{{ \App\Models\Setting::get('shop_name', config('app.name', 'Ajmiriganj IT')) }} — Confidential Financial Statement</span>
            <span>Software by Ajmiriganj IT</span>
        </footer>
    </div>
</body>
</html>
