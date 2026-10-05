<x-print-layout :title="$title" :periodText="$periodText">
    <table>
        <thead>
            <tr>
                <th>Voucher #</th>
                <th>Date</th>
                <th>Vendor</th>
                <th>Account</th>
                <th>Method</th>
                <th>Reference</th>
                <th class="text-right">Amount Paid</th>
                <th>Logged By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="font-weight: 600; color: #4f46e5;">{{ $r['voucher_no'] ?? ('VP-'.$r['id']) }}</td>
                    <td style="color: #6b7280;">{{ $r['date'] }}</td>
                    <td style="font-weight: 500;">{{ $r['vendor_name'] }}</td>
                    <td>{{ $r['account_name'] }}</td>
                    <td>{{ $r['payment_method_label'] }}</td>
                    <td style="color: #6b7280;">{{ $r['reference_no'] ?? '—' }}</td>
                    <td class="tabular-nums" style="font-weight: 600; color: #be123c;">{{ \App\Support\Money::format($r['amount']) }}</td>
                    <td style="color: #6b7280;">{{ $r['user_name'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No vendor payments recorded for this period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #f9fafb; font-weight: 700;">
                <td colspan="6">Grand Total Payments ({{ $totals['count'] ?? $rows->count() }} vouchers)</td>
                <td class="tabular-nums" style="color: #be123c; font-size: 13px;">{{ \App\Support\Money::format($totals['total_amount'] ?? $totals['total_paid'] ?? '0.00') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</x-print-layout>
