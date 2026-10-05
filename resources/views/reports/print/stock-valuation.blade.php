<x-print-layout :title="$title" :periodText="'Valuation as of ' . now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->format('d M Y')">
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th class="text-right">Stock Qty</th>
                <th class="text-right">Unit Cost</th>
                <th class="text-right">FIFO Valuation</th>
                <th class="text-right">Retail Value</th>
                <th class="text-right">Potential Profit</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="font-weight: 500;">{{ $r['product_name'] }}</td>
                    <td style="color: #6b7280;">{{ $r['sku'] }}</td>
                    <td style="color: #6b7280;">{{ $r['category_name'] }}</td>
                    <td class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::formatQty($r['stock_qty']) }} {{ $r['unit'] }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['last_cost']) }}</td>
                    <td class="tabular-nums" style="font-weight: 600; color: #1d4ed8;">{{ \App\Support\Money::format($r['fifo_stock_value']) }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['retail_value']) }}</td>
                    <td class="tabular-nums" style="color: #059669; font-weight: 600;">{{ \App\Support\Money::format($r['potential_profit']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No inventory items currently in stock.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #eff6ff; font-weight: 700; color: #1e40af;">
                <td colspan="3">Grand Totals ({{ $rows->count() }} items)</td>
                <td class="tabular-nums">{{ \App\Support\Money::formatQty($totals['total_stock_qty']) }}</td>
                <td></td>
                <td class="tabular-nums" style="font-size: 13px;">{{ \App\Support\Money::format($totals['total_fifo_value']) }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['total_retail_value']) }}</td>
                <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($totals['total_potential_profit']) }}</td>
            </tr>
        </tfoot>
    </table>
</x-print-layout>
