<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Payment Receipt - {{ $payment->payment_no }}</title>
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

        .total-row {
            display: flex;
            justify-content: space-between;
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
        <button onclick="window.print()" style="padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: bold;">Print Receipt</button>
    </div>

    <div class="header text-center">
        <div class="shop-name">{{ $shopName }}</div>
        <div>{{ $shopAddress }}</div>
        <div>Tel: {{ $shopPhone }}</div>
        <div class="bold" style="margin-top: 4px; font-size: 12px;">DUE PAYMENT RECEIPT</div>
    </div>

    <div class="info-row">
        <span>Receipt No:</span>
        <span class="bold">{{ $payment->payment_no }}</span>
    </div>
    <div class="info-row">
        <span>Date:</span>
        <span>{{ $payment->payment_date ? $payment->payment_date->format('d-m-Y') : date('d-m-Y') }}</span>
    </div>
    <div class="info-row">
        <span>Customer:</span>
        <span class="bold">{{ $payment->customer ? $payment->customer->name : 'N/A' }}</span>
    </div>
    <div class="info-row">
        <span>Method:</span>
        <span>{{ $payment->payment_method->label() }}</span>
    </div>
    @if($payment->reference_no)
        <div class="info-row">
            <span>Ref:</span>
            <span>{{ $payment->reference_no }}</span>
        </div>
    @endif

    <div class="divider"></div>

    <div class="total-row">
        <span>Amount Received:</span>
        <span>৳ {{ number_format((float) $payment->amount, 2) }}</span>
    </div>

    @if($payment->allocations->count() > 0)
        <div style="font-size: 10px; margin-top: 4px;">
            <div class="bold" style="margin-bottom: 2px;">Applied Towards:</div>
            @foreach($payment->allocations as $alloc)
                <div class="info-row" style="color: #333;">
                    <span>{{ $alloc->sale ? 'Invoice '.$alloc->sale->invoice_no : 'Opening Due' }}:</span>
                    <span>৳ {{ number_format((float) $alloc->amount, 2) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if($payment->note)
        <div class="divider"></div>
        <div style="font-size: 10px;">
            <span class="bold">Note:</span> {{ $payment->note }}
        </div>
    @endif

    <div class="footer">
        <div class="divider"></div>
        <div>Thank you for your payment!</div>
    </div>
</body>
</html>
