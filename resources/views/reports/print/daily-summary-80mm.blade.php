<x-print-layout :title="$title" :periodText="'Date: ' . $data['date']" :format="'80mm'">
    @php
        $inflows = $data['inflows'];
        $outflows = $data['outflows'];
        $stock = $data['stock_summary'];
        $accounts = $data['accounts_summary'];
        $cashiers = $data['cashier_breakdown'];
        $netCashFlow = $data['net_cash_flow'];
    @endphp

    <div style="font-size: 10px; line-height: 1.3;">
        {{-- Cashiers --}}
        @if($cashiers->isNotEmpty())
            <div style="border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 6px;">
                <div style="font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">CASHIER SALES:</div>
                @foreach($cashiers as $c)
                    <div style="display: flex; justify-content: space-between;">
                        <span>{{ $c['user_name'] }} ({{ $c['sales_count'] }}):</span>
                        <span class="tabular-nums">{{ \App\Support\Money::format($c['sales_total']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Cash Inflows --}}
        <div style="border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 6px;">
            <div style="font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">CASH INFLOWS:</div>
            <div style="display: flex; justify-content: space-between;">
                <span>POS Sales:</span>
                <span class="tabular-nums">+ {{ \App\Support\Money::format($inflows['pos_sales_collected']) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Due Collections:</span>
                <span class="tabular-nums">+ {{ \App\Support\Money::format($inflows['customer_due_collections']) }}</span>
            </div>
            @if(bccomp((string)$inflows['other_income'], '0.00', 2) > 0)
                <div style="display: flex; justify-content: space-between;">
                    <span>Other Income:</span>
                    <span class="tabular-nums">+ {{ \App\Support\Money::format($inflows['other_income']) }}</span>
                </div>
            @endif
            @if(bccomp((string)$inflows['owner_investments'], '0.00', 2) > 0)
                <div style="display: flex; justify-content: space-between;">
                    <span>Owner Infusion:</span>
                    <span class="tabular-nums">+ {{ \App\Support\Money::format($inflows['owner_investments']) }}</span>
                </div>
            @endif
            <div style="display: flex; justify-content: space-between; font-weight: 700; border-top: 1px solid #ddd; margin-top: 2px;">
                <span>Total Inflows:</span>
                <span class="tabular-nums">{{ \App\Support\Money::format($inflows['total_inflows']) }}</span>
            </div>
        </div>

        {{-- Cash Outflows --}}
        <div style="border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 6px;">
            <div style="font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">CASH OUTFLOWS:</div>
            @if(bccomp((string)$outflows['sale_return_refunds'], '0.00', 2) > 0)
                <div style="display: flex; justify-content: space-between;">
                    <span>Sale Refunds:</span>
                    <span class="tabular-nums">- {{ \App\Support\Money::format($outflows['sale_return_refunds']) }}</span>
                </div>
            @endif
            <div style="display: flex; justify-content: space-between;">
                <span>Vendor Payments:</span>
                <span class="tabular-nums">- {{ \App\Support\Money::format($outflows['vendor_payments']) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Operating Expenses:</span>
                <span class="tabular-nums">- {{ \App\Support\Money::format($outflows['operating_expenses']) }}</span>
            </div>
            @if(bccomp((string)$outflows['owner_drawings'], '0.00', 2) > 0)
                <div style="display: flex; justify-content: space-between;">
                    <span>Owner Drawings:</span>
                    <span class="tabular-nums">- {{ \App\Support\Money::format($outflows['owner_drawings']) }}</span>
                </div>
            @endif
            @if(bccomp((string)$outflows['profit_withdrawals'], '0.00', 2) > 0)
                <div style="display: flex; justify-content: space-between;">
                    <span>Profit Withdrawals:</span>
                    <span class="tabular-nums">- {{ \App\Support\Money::format($outflows['profit_withdrawals']) }}</span>
                </div>
            @endif
            <div style="display: flex; justify-content: space-between; font-weight: 700; border-top: 1px solid #ddd; margin-top: 2px;">
                <span>Total Outflows:</span>
                <span class="tabular-nums">{{ \App\Support\Money::format($outflows['total_outflows']) }}</span>
            </div>
        </div>

        {{-- Net Cash Flow --}}
        <div style="border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 6px; font-weight: 700; font-size: 11px;">
            <div style="display: flex; justify-content: space-between;">
                <span>NET CASH FLOW:</span>
                <span class="tabular-nums">{{ \App\Support\Money::format($netCashFlow) }}</span>
            </div>
        </div>

        {{-- Account Balances --}}
        <div style="border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 6px;">
            <div style="font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">CLOSING ACCOUNTS (EOD):</div>
            @foreach($accounts as $acc)
                <div style="display: flex; justify-content: space-between; margin-bottom: 1px;">
                    <span>{{ $acc['account_name'] }}:</span>
                    <span class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::format($acc['closing_balance']) }}</span>
                </div>
            @endforeach
        </div>

        {{-- Stock Quantities --}}
        <div style="margin-bottom: 6px;">
            <div style="font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">STOCK MOVEMENTS:</div>
            <div style="display: flex; justify-content: space-between;">
                <span>Items Sold:</span>
                <span class="tabular-nums">{{ \App\Support\Money::formatQty($stock['items_sold_qty']) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Items Returned:</span>
                <span class="tabular-nums">{{ \App\Support\Money::formatQty($stock['items_returned_qty']) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Items Received:</span>
                <span class="tabular-nums">{{ \App\Support\Money::formatQty($stock['items_purchased_qty']) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Loss/Damages:</span>
                <span class="tabular-nums">{{ \App\Support\Money::formatQty($stock['damage_loss_qty']) }}</span>
            </div>
        </div>
    </div>
</x-print-layout>
