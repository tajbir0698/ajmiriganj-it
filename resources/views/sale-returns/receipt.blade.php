<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Receipt - {{ $return->return_no }}</title>
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

        th, td {
            padding: 2px 0;
            font-size: 11px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }

        .total-row.grand {
            font-size: 13px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 3px 0;
            margin: 4px 0;
        }

        .footer {
            margin-top: 8px;
            text-align: center;
            font-size: 10px;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 10px; text-align: center;">
        <button onclick="window.print()" style="padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: bold;">Print Return Receipt</button>
    </div>

    <div class="header text-center">
        <div class="shop-name">{{ $shopName }}</div>
        <div>{{ $shopAddress }}</div>
        <div>Tel: {{ $shopPhone }}</div>
        <div class="bold" style="margin-top: 4px; font-size: 12px;">SALE RETURN VOUCHER</div>
    </div>

    <div class="info-row">
        <span>Return No:</span>
        <span class="bold">{{ $return->return_no }}</span>
    </div>
    <div class="info-row">
        <span>Original Invoice:</span>
        <span>{{ $return->sale->invoice_no }}</span>
    </div>
    <div class="info-row">
        <span>Date:</span>
        <span>{{ $return->return_date ? $return->return_date->format('d-m-Y') : date('d-m-Y') }}</span>
    </div>
    <div class="info-row">
        <span>Customer:</span>
        <span>{{ $return->customer ? $return->customer->name : 'Walk-in' }}</span>
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th class="text-left" style="width: 50%;">Item</th>
                <th class="text-center" style="width: 15%;">Qty</th>
                <th class="text-right" style="width: 15%;">Rate</th>
                <th class="text-right" style="width: 20%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($return->items as $item)
                <tr>
                    <td class="text-left">{{ $item->product ? $item->product->name : 'Product' }}</td>
                    <td class="text-center">{{ (float) $item->qty }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_refund, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->line_refund, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="total-row grand">
        <span>Total Refund:</span>
        <span>৳ {{ number_format((float) $return->refund_amount, 2) }}</span>
    </div>

    <div class="total-row">
        <span>Due Reduction:</span>
        <span>৳ {{ number_format((float) $return->due_reduction, 2) }}</span>
    </div>

    <div class="total-row bold">
        <span>Cash Refund Paid:</span>
        <span>৳ {{ number_format((float) $return->cash_refund, 2) }}</span>
    </div>

    @if($return->reason)
        <div class="divider"></div>
        <div style="font-size: 10px; margin-top: 2px;">
            <span class="bold">Reason:</span> {{ $return->reason }}
        </div>
    @endif

    <div class="footer">
        <div class="divider"></div>
        <div>Items returned to stock in good condition.</div>
    </div>

    @if(request()->has('autoprint'))
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    @endif
</body>
</html>
