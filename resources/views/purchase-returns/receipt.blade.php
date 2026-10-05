<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Return Debit Note - {{ $return->return_no }}</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Bengali", sans-serif;
            font-size: 13px;
            line-height: 1.4;
            color: #111;
            background: #fff;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        
        .header {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #333;
        }
        
        .shop-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .voucher-title {
            font-size: 16px;
            font-weight: bold;
            color: #4b5563;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .meta-card {
            background: #f9fafb;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            font-weight: 600;
        }

        td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
        }

        .summary-table {
            width: 300px;
            margin-left: auto;
            margin-top: 15px;
        }

        .summary-table td {
            border: none;
            padding: 4px 8px;
        }

        .grand-total {
            font-size: 15px;
            font-weight: bold;
            border-top: 2px solid #111 !important;
            border-bottom: 2px solid #111 !important;
            padding: 6px 8px !important;
        }

        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            border-top: 1px solid #666;
            width: 180px;
            text-align: center;
            padding-top: 6px;
            font-size: 12px;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; cursor: pointer; font-size: 13px; font-weight: bold; background: #2563eb; color: #fff; border: none; border-radius: 4px;">Print Debit Note</button>
    </div>

    <div class="header text-center">
        <div class="shop-name">{{ $shopName }}</div>
        <div>{{ $shopAddress }} | Tel: {{ $shopPhone }}</div>
        <div class="voucher-title">Purchase Return / Debit Note</div>
    </div>

    <div class="meta-grid">
        <div class="meta-card">
            <div class="info-row">
                <span class="bold">Return No:</span>
                <span>{{ $return->return_no }}</span>
            </div>
            <div class="info-row">
                <span class="bold">Return Date:</span>
                <span>{{ $return->return_date ? $return->return_date->format('d M Y') : date('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="bold">Purchase Ref:</span>
                <span>{{ $return->purchase ? $return->purchase->invoice_no : 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="bold">Settlement:</span>
                <span>{{ $return->settlement->label() }}</span>
            </div>
        </div>

        <div class="meta-card">
            <div class="info-row">
                <span class="bold">Vendor:</span>
                <span>{{ $return->vendor ? $return->vendor->name : 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span>Phone:</span>
                <span>{{ $return->vendor ? $return->vendor->phone : 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span>Address:</span>
                <span>{{ $return->vendor ? $return->vendor->address : 'N/A' }}</span>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 8%;">#</th>
                <th class="text-left" style="width: 50%;">Product Description</th>
                <th class="text-center" style="width: 14%;">Returned Qty</th>
                <th class="text-right" style="width: 14%;">Credit Rate</th>
                <th class="text-right" style="width: 14%;">Total Credit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($return->items as $index => $item)
                @php
                    $lineCredit = bcmul((string) $item->qty, (string) $item->unit_cost, 2);
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-left">
                        <div class="bold">{{ $item->product ? $item->product->name : 'Product' }}</div>
                        <div style="font-size: 11px; color: #666;">SKU: {{ $item->product ? $item->product->sku : '-' }}</div>
                    </td>
                    <td class="text-center">{{ (float) $item->qty }}</td>
                    <td class="text-right">৳ {{ number_format((float) $item->unit_cost, 2) }}</td>
                    <td class="text-right">৳ {{ number_format((float) $lineCredit, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary-table">
        <tr class="grand-total">
            <td class="text-left">Total Credit:</td>
            <td class="text-right">৳ {{ number_format((float) $return->credit_amount, 2) }}</td>
        </tr>
        @if(bccomp((string) $return->refund_received_amount, '0.00', 2) > 0)
            <tr>
                <td class="text-left">Cash Refund Received:</td>
                <td class="text-right bold">৳ {{ number_format((float) $return->refund_received_amount, 2) }}</td>
            </tr>
        @endif
    </table>

    @if($return->reason)
        <div style="margin-top: 15px; font-size: 12px; background: #f9fafb; padding: 8px; border-radius: 4px;">
            <span class="bold">Return Reason:</span> {{ $return->reason }}
        </div>
    @endif

    <div class="signature-section">
        <div class="signature-box">Authorized Store Signature</div>
        <div class="signature-box">Vendor / Receiver Signature</div>
    </div>
</body>
</html>
