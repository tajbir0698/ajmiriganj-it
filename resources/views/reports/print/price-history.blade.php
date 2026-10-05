<x-print-layout :title="$title" :periodText="'Historical Price and Landed Cost Timeline'">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Product Name</th>
                <th>SKU</th>
                <th class="text-right">Old Cost</th>
                <th class="text-right">New Cost</th>
                <th class="text-right">Old Price</th>
                <th class="text-right">New Price</th>
                <th>Reason / Reference</th>
                <th>Changed By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="color: #6b7280;">{{ $r['date'] }}</td>
                    <td style="font-weight: 500;">{{ $r['product_name'] }}</td>
                    <td style="color: #6b7280;">{{ $r['sku'] }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['old_cost']) }}</td>
                    <td class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::format($r['new_cost']) }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['old_sale_price']) }}</td>
                    <td class="tabular-nums" style="font-weight: 600; color: #4f46e5;">{{ \App\Support\Money::format($r['new_sale_price']) }}</td>
                    <td style="color: #4b5563;">{{ $r['reason'] ?? '—' }}</td>
                    <td style="color: #6b7280;">{{ $r['user_name'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No price adjustments or purchase cost revisions recorded.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-print-layout>
