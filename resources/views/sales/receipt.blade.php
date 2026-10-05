<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $sale->invoice_no }}</title>
    <style>
        @page {
            size: {{ $receiptWidth }}mm auto;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 4mm 2mm;
            font-family: "Courier New", Courier, monospace, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Bengali", sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            background: #fff;
            width: {{ $receiptWidth == 58 ? '48mm' : '72mm' }};
            box-sizing: border-box;
            margin-left: auto;
            margin-right: auto;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        
        .header {
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px dashed #000;
        }
        
        .shop-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }

        .divider-double {
            border-top: 2px solid #000;
            margin: 4px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }

        th {
            text-align: left;
            border-bottom: 1px dashed #000;
            padding: 2px 0;
            font-size: 10px;
        }

        td {
            padding: 2px 0;
            vertical-align: top;
            font-size: 11px;
        }

        .item-discount {
            font-size: 10px;
            padding-left: 6px;
            font-style: italic;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
        }

        .grand-total {
            font-size: 13px;
            font-weight: bold;
        }

        .duplicate-tag {
            border: 1px solid #000;
            padding: 2px 4px;
            display: inline-block;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 4px;
        }

        .footer {
            margin-top: 8px;
            padding-top: 4px;
            border-top: 1px dashed #000;
            font-size: 10px;
            text-align: center;
        }

        .no-print {
            text-align: center;
            margin-bottom: 10px;
        }

        .print-btn {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 6px 16px;
            font-size: 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="print-btn" onclick="window.print()">Print Receipt</button>
    </div>

    @if ($isDuplicate)
        <div class="text-center">
            <span class="duplicate-tag">*** DUPLICATE COPY ***</span>
        </div>
    @endif

    <div class="header text-center">
        <div class="shop-name">{{ $shopName }}</div>
        <div>{{ $shopAddress }}</div>
        <div>Tel: {{ $shopPhone }}</div>
    </div>

    <div class="info-row">
        <span>Invoice:</span>
        <span class="bold">{{ $sale->invoice_no }}</span>
    </div>
    <div class="info-row">
        <span>Date:</span>
        <span>{{ $sale->created_at->format('d/m/Y h:i A') }}</span>
    </div>
    <div class="info-row">
        <span>Cashier:</span>
        <span>{{ $sale->creator?->name ?? 'POS' }}</span>
    </div>
    @if ($sale->customer)
        <div class="info-row">
            <span>Customer:</span>
            <span>{{ $sale->customer->name }}</span>
        </div>
        @if ($sale->customer->phone)
            <div class="info-row">
                <span>Phone:</span>
                <span>{{ $sale->customer->phone }}</span>
            </div>
        @endif
    @endif

    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th style="width: 50%;">Item</th>
                <th class="text-right" style="width: 18%;">Qty</th>
                <th class="text-right" style="width: 32%;">Total (৳)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td colspan="3">
                        <div class="bold">{{ $item->product_name }}</div>
                        <div class="info-row" style="font-size: 10px;">
                            <span>{{ \App\Support\Money::formatQty((string) $item->qty) }} × {{ \App\Support\Money::format((string) $item->unit_price) }}</span>
                            <span>{{ \App\Support\Money::format((string) $item->line_total) }}</span>
                        </div>
                        @if (bccomp((string) $item->discount, '0.00', 2) > 0)
                            <div class="item-discount">Discount: -{{ \App\Support\Money::format((string) $item->discount) }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="totals-row">
        <span>Subtotal:</span>
        <span>{{ \App\Support\Money::format((string) $sale->subtotal) }}</span>
    </div>

    @if (bccomp((string) $sale->discount, '0.00', 2) > 0)
        <div class="totals-row">
            <span>Overall Discount:</span>
            <span>-{{ \App\Support\Money::format((string) $sale->discount) }}</span>
        </div>
    @endif

    <div class="divider-double"></div>

    <div class="totals-row grand-total">
        <span>TOTAL PAYABLE:</span>
        <span>{{ \App\Support\Money::format((string) $sale->total) }}</span>
    </div>

    <div class="divider"></div>

    <div class="totals-row">
        <span>Paid Amount:</span>
        <span class="bold">{{ \App\Support\Money::format((string) $sale->paid_amount) }}</span>
    </div>

    @if ($sale->payment_method)
        <div class="totals-row" style="font-size: 10px;">
            <span>Payment Method:</span>
            <span>{{ $sale->payment_method->label() }}</span>
        </div>
    @endif

    @if (bccomp((string) $sale->change_amount, '0.00', 2) > 0)
        <div class="totals-row">
            <span>Change Given:</span>
            <span>{{ \App\Support\Money::format((string) $sale->change_amount) }}</span>
        </div>
    @endif

    @if (bccomp((string) $sale->due_amount, '0.00', 2) > 0)
        <div class="totals-row bold" style="font-size: 12px;">
            <span>CURRENT DUE:</span>
            <span>{{ \App\Support\Money::format((string) $sale->due_amount) }}</span>
        </div>
    @endif

    <div class="footer">
        <div>{{ $footerText }}</div>
        <div style="font-size: 8px; margin-top: 3px;">Software by Ajmiriganj IT</div>
    </div>

    @if (request()->has('autoprint'))
        <script>
            window.addEventListener('load', () => {
                setTimeout(() => window.print(), 250);
            });
        </script>
    @endif

</body>
</html>
