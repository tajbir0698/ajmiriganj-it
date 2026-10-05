<x-print-layout :title="$title" :periodText="'Date: ' . $data['date']">
    @php
        $inflows = $data['inflows'];
        $outflows = $data['outflows'];
        $stock = $data['stock_summary'];
        $accounts = $data['accounts_summary'];
        $cashiers = $data['cashier_breakdown'];
        $netCashFlow = $data['net_cash_flow'];
    @endphp

    {{-- Cashier Breakdown --}}
    @if($cashiers->isNotEmpty())
        <div style="margin-bottom: 16px;">
            <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 3px; margin-bottom: 6px;">
                <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #4f46e5;">Cashier Sales Breakdown</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Cashier</th>
                        <th class="text-center">Sales Count</th>
                        <th class="text-right">Sales Total</th>
                        <th class="text-right">Paid Amount</th>
                        <th class="text-right">Due Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cashiers as $c)
                        <tr>
                            <td style="font-weight: 500;">{{ $c['user_name'] }}</td>
                            <td class="text-center tabular-nums">{{ $c['sales_count'] }}</td>
                            <td class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::format($c['sales_total']) }}</td>
                            <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($c['paid_amount']) }}</td>
                            <td class="tabular-nums" style="color: #d97706;">{{ \App\Support\Money::format($c['due_amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- 1. Cash Inflows & Outflows --}}
    <div style="margin-bottom: 16px;">
        <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 3px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #4f46e5;">1. Daily Cash Inflow & Outflow Summary</span>
        </div>
        <table>
            <tbody>
                <tr>
                    <td style="padding-left: 12px; color: #374151;">POS Sales Cash Collected</td>
                    <td class="tabular-nums" style="width: 140px; font-weight: 500;">+ {{ \App\Support\Money::format($inflows['pos_sales_collected']) }}</td>
                </tr>
                <tr>
                    <td style="padding-left: 12px; color: #374151;">Customer Due Collections</td>
                    <td class="tabular-nums" style="font-weight: 500;">+ {{ \App\Support\Money::format($inflows['customer_due_collections']) }}</td>
                </tr>
                @if(bccomp((string)$inflows['other_income'], '0.00', 2) > 0)
                    <tr>
                        <td style="padding-left: 12px; color: #374151;">Other Income Collected</td>
                        <td class="tabular-nums">+ {{ \App\Support\Money::format($inflows['other_income']) }}</td>
                    </tr>
                @endif
                @if(bccomp((string)$inflows['owner_investments'], '0.00', 2) > 0)
                    <tr>
                        <td style="padding-left: 12px; color: #374151;">Owner Capital Infusion</td>
                        <td class="tabular-nums">+ {{ \App\Support\Money::format($inflows['owner_investments']) }}</td>
                    </tr>
                @endif
                <tr style="background: #eff6ff; font-weight: 700; color: #1e40af;">
                    <td style="padding: 6px 8px;">Total Inflows</td>
                    <td class="tabular-nums" style="padding: 6px 8px;">{{ \App\Support\Money::format($inflows['total_inflows']) }}</td>
                </tr>
                <tr>
                    <td style="padding-left: 12px; color: #be123c;">Customer Returns Cash Refunded</td>
                    <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($outflows['sale_return_refunds']) }}</td>
                </tr>
                <tr>
                    <td style="padding-left: 12px; color: #be123c;">Vendor Dues & Bill Payments Paid</td>
                    <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($outflows['vendor_payments']) }}</td>
                </tr>
                <tr>
                    <td style="padding-left: 12px; color: #be123c;">Shop Operating Expenses Paid</td>
                    <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($outflows['operating_expenses']) }}</td>
                </tr>
                @if(bccomp((string)$outflows['owner_drawings'], '0.00', 2) > 0)
                    <tr>
                        <td style="padding-left: 12px; color: #be123c;">Owner Drawings</td>
                        <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($outflows['owner_drawings']) }}</td>
                    </tr>
                @endif
                @if(bccomp((string)$outflows['profit_withdrawals'], '0.00', 2) > 0)
                    <tr>
                        <td style="padding-left: 12px; color: #be123c;">Profit Withdrawals</td>
                        <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($outflows['profit_withdrawals']) }}</td>
                    </tr>
                @endif
                <tr style="background: #fff1f2; font-weight: 700; color: #9f1239;">
                    <td style="padding: 6px 8px;">Total Outflows</td>
                    <td class="tabular-nums" style="padding: 6px 8px;">{{ \App\Support\Money::format($outflows['total_outflows']) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background: {{ bccomp($netCashFlow, '0.00', 2) >= 0 ? '#ecfdf5' : '#fef2f2' }}; font-weight: 800; font-size: 13px; color: {{ bccomp($netCashFlow, '0.00', 2) >= 0 ? '#065f46' : '#991b1b' }};">
                    <td style="padding: 8px;">NET CASH FLOW (TODAY)</td>
                    <td class="tabular-nums" style="padding: 8px;">{{ \App\Support\Money::format($netCashFlow) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- 2. Accounts Reconciliation (EOD) --}}
    <div style="margin-bottom: 16px;">
        <div style="border-bottom: 2px solid #059669; padding-bottom: 3px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #059669;">2. Account Reconciliation (EOD)</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">Opening</th>
                    <th class="text-right">Inflow (+)</th>
                    <th class="text-right">Outflow (-)</th>
                    <th class="text-right">Closing Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $acc)
                    @php
                        $lowerName = strtolower($acc['account_name']);
                        $hasType = str_contains($lowerName, 'cash') || str_contains($lowerName, 'bank') || str_contains($lowerName, 'wallet') || str_contains($lowerName, 'mfs');
                    @endphp
                    <tr>
                        <td style="font-weight: 500;">
                            {{ $acc['account_name'] }}
                            @if(! $hasType && !empty($acc['account_kind_label']))
                                <span style="font-size: 10px; color: #6b7280;">({{ $acc['account_kind_label'] }})</span>
                            @endif
                        </td>
                        <td class="tabular-nums">{{ \App\Support\Money::format($acc['opening_balance']) }}</td>
                        <td class="tabular-nums" style="color: #059669;">+ {{ \App\Support\Money::format($acc['inflow']) }}</td>
                        <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($acc['outflow']) }}</td>
                        <td class="tabular-nums" style="font-weight: 700; color: #111827;">{{ \App\Support\Money::format($acc['closing_balance']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- 3. Stock Physical Movements --}}
    <div style="margin-bottom: 16px;">
        <div style="border-bottom: 2px solid #ea580c; padding-bottom: 3px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #ea580c;">3. Physical Stock Movements (Qty)</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">Items Sold</th>
                    <th class="text-center">Items Returned</th>
                    <th class="text-center">Items Received (Purchases)</th>
                    <th class="text-center">Damage / Adjustment Loss</th>
                </tr>
            </thead>
            <tbody>
                <tr style="font-size: 13px; font-weight: 700;">
                    <td class="text-center tabular-nums">{{ \App\Support\Money::formatQty($stock['items_sold_qty']) }}</td>
                    <td class="text-center tabular-nums" style="color: #b45309;">{{ \App\Support\Money::formatQty($stock['items_returned_qty']) }}</td>
                    <td class="text-center tabular-nums" style="color: #1d4ed8;">{{ \App\Support\Money::formatQty($stock['items_purchased_qty']) }}</td>
                    <td class="text-center tabular-nums" style="color: #be123c;">{{ \App\Support\Money::formatQty($stock['damage_loss_qty']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-print-layout>
