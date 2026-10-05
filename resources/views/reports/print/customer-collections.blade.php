<x-print-layout :title="$title" :periodText="$periodText">
    <table>
        <thead>
            <tr>
                <th>Receipt #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Account</th>
                <th>Method</th>
                <th>Reference</th>
                <th class="text-right">Amount Collected</th>
                <th>Logged By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="font-weight: 600; color: #4f46e5;">{{ $r['receipt_no'] ?? ('CP-'.$r['id']) }}</td>
                    <td style="color: #6b7280;">{{ $r['date'] }}</td>
                    <td style="font-weight: 500;">{{ $r['customer_name'] }}</td>
                    <td>{{ $r['account_name'] }}</td>
                    <td>{{ $r['payment_method_label'] }}</td>
                    <td style="color: #6b7280;">{{ $r['reference_no'] ?? '—' }}</td>
                    <td class="tabular-nums" style="font-weight: 600; color: #059669;">{{ \App\Support\Money::format($r['amount']) }}</td>
                    <td style="color: #6b7280;">{{ $r['user_name'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No customer due collections recorded for this period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #f9fafb; font-weight: 700;">
                <td colspan="6">Grand Total Collections ({{ $totals['count'] ?? $rows->count() }} receipts)</td>
                <td class="tabular-nums" style="color: #059669; font-size: 13px;">{{ \App\Support\Money::format($totals['total_amount'] ?? $totals['total_collected'] ?? '0.00') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</x-print-layout>
