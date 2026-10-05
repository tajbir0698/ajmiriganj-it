<x-print-layout :title="$title" :periodText="$periodText">
    <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Vendor</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Shipping</th>
                <th class="text-right">Total</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Due</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="font-weight: 600; color: #4f46e5;">{{ $r['invoice_no'] }}</td>
                    <td style="color: #6b7280;">{{ $r['date'] }}</td>
                    <td>{{ $r['vendor_name'] }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['subtotal']) }}</td>
                    <td class="tabular-nums" style="color: #b45309;">{{ \App\Support\Money::format($r['discount']) }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['shipping_cost']) }}</td>
                    <td class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::format($r['total']) }}</td>
                    <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($r['paid_amount']) }}</td>
                    <td class="tabular-nums" style="color: #d97706; font-weight: 600;">{{ \App\Support\Money::format($r['due_amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No purchases found for the selected period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #f9fafb; font-weight: 700;">
                <td colspan="3">Grand Totals ({{ $totals['purchase_count'] ?? $rows->count() }} bills)</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['total_subtotal'] ?? '0.00') }}</td>
                <td class="tabular-nums" style="color: #b45309;">{{ \App\Support\Money::format($totals['total_discount'] ?? '0.00') }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['total_shipping'] ?? '0.00') }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['total_purchases'] ?? '0.00') }}</td>
                <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($totals['total_paid'] ?? '0.00') }}</td>
                <td class="tabular-nums" style="color: #d97706;">{{ \App\Support\Money::format($totals['total_due'] ?? '0.00') }}</td>
            </tr>
        </tfoot>
    </table>
</x-print-layout>
