<x-print-layout :title="$title" :periodText="$periodText ?? ('Inactive Threshold: ' . ($days ?? 60) . ' days without sales')">
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Unit</th>
                <th class="text-right">Stock Qty</th>
                <th class="text-right">Locked Capital (FIFO)</th>
                <th>Last Sale Date</th>
                <th class="text-right">Days Inactive</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="font-weight: 500;">{{ $r['name'] ?? $r['product_name'] }}</td>
                    <td style="color: #6b7280;">{{ $r['sku'] }}</td>
                    <td style="color: #6b7280;">{{ $r['category_name'] }}</td>
                    <td>{{ $r['unit'] }}</td>
                    <td class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::formatQty($r['stock_qty']) }}</td>
                    <td class="tabular-nums" style="font-weight: 600; color: #be123c;">{{ \App\Support\Money::format($r['fifo_stock_value']) }}</td>
                    <td style="color: #6b7280;">{{ $r['last_sale_date'] ?? 'Never Sold' }}</td>
                    <td class="tabular-nums" style="font-weight: 600; color: #ea580c;">{{ $r['days_since_sale'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No dead stock items detected for the current threshold.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #f9fafb; font-weight: 700;">
                <td colspan="4">Grand Totals ({{ $totals['count'] ?? $rows->count() }} items)</td>
                <td class="tabular-nums">{{ \App\Support\Money::formatQty($totals['total_qty']) }}</td>
                <td class="tabular-nums" style="color: #be123c; font-size: 13px;">{{ \App\Support\Money::format($totals['total_fifo_value']) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</x-print-layout>
