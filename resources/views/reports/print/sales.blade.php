<x-print-layout :title="$title" :periodText="$periodText">
    @php
        $showCashier = ! $isManager || \App\Models\Setting::get('manager_sales_visibility', 'all') === 'all';
    @endphp
    <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Customer</th>
                @if($showCashier)
                    <th>Cashier</th>
                @endif
                <th>Method</th>
                <th class="text-right">Total</th>
                @if(! $isManager)
                    <th class="text-right">Profit</th>
                    <th class="text-right">Margin</th>
                @endif
                <th class="text-right">Paid</th>
                <th class="text-right">Due</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="font-weight: 600; color: #4f46e5;">{{ $r['invoice_no'] }}</td>
                    <td style="color: #6b7280;">{{ $r['date'] }}</td>
                    <td>{{ $r['customer_name'] }}</td>
                    @if($showCashier)
                        <td style="color: #6b7280;">{{ $r['cashier_name'] ?? 'N/A' }}</td>
                    @endif
                    <td>{{ $r['payment_method_label'] }}</td>
                    <td class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::format($r['total']) }}</td>
                    @if(! $isManager)
                        <td class="tabular-nums" style="color: #059669; font-weight: 600;">{{ \App\Support\Money::format($r['net_profit'] ?? '0.00') }}</td>
                        <td class="tabular-nums" style="color: #4b5563;">{{ $r['margin_percent'] ?? '0%' }}</td>
                    @endif
                    <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($r['paid_amount']) }}</td>
                    <td class="tabular-nums" style="color: #d97706; font-weight: 600;">{{ \App\Support\Money::format($r['due_amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 6 + ($showCashier ? 1 : 0) + (! $isManager ? 2 : 0) }}" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No sales found for the selected period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #f9fafb; font-weight: 700;">
                <td colspan="{{ 4 + ($showCashier ? 1 : 0) }}">Grand Totals ({{ $totals['sales_count'] ?? $rows->count() }} sales)</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['total_sales'] ?? '0.00') }}</td>
                @if(! $isManager)
                    <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($totals['total_profit'] ?? '0.00') }}</td>
                    <td></td>
                @endif
                <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($totals['total_paid'] ?? '0.00') }}</td>
                <td class="tabular-nums" style="color: #d97706;">{{ \App\Support\Money::format($totals['total_due'] ?? '0.00') }}</td>
            </tr>
        </tfoot>
    </table>
</x-print-layout>
