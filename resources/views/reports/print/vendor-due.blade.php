<x-print-layout :title="$title" :periodText="'Aging As of ' . $asOfDate">
    <table>
        <thead>
            <tr>
                <th>Vendor</th>
                <th>Phone</th>
                <th class="text-right">Opening</th>
                <th class="text-right">0–30 Days</th>
                <th class="text-right">31–60 Days</th>
                <th class="text-right">61–90 Days</th>
                <th class="text-right">90+ Days</th>
                <th class="text-right">Total Outst.</th>
                <th class="text-right">Advance</th>
                <th class="text-right">Net Due</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="font-weight: 500;">{{ $r['vendor_name'] }}</td>
                    <td style="color: #6b7280;">{{ $r['vendor_phone'] ?? '—' }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['opening_balance']) }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['bucket_0_30']) }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['bucket_31_60']) }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($r['bucket_61_90']) }}</td>
                    <td class="tabular-nums" style="color: #be123c;">{{ \App\Support\Money::format($r['bucket_90_plus']) }}</td>
                    <td class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::format($r['total_outstanding']) }}</td>
                    <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($r['advance']) }}</td>
                    <td class="tabular-nums" style="font-weight: 700; color: #be123c;">{{ \App\Support\Money::format($r['net_due']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No vendor dues or aging records found.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #f9fafb; font-weight: 700;">
                <td colspan="2">Grand Totals ({{ $rows->count() }} vendors)</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['opening_balance'] ?? '0.00') }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['bucket_0_30'] ?? '0.00') }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['bucket_31_60'] ?? '0.00') }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['bucket_61_90'] ?? '0.00') }}</td>
                <td class="tabular-nums" style="color: #be123c;">{{ \App\Support\Money::format($totals['bucket_90_plus'] ?? '0.00') }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($totals['total_outstanding'] ?? '0.00') }}</td>
                <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($totals['advance'] ?? '0.00') }}</td>
                <td class="tabular-nums" style="color: #be123c; font-size: 13px;">{{ \App\Support\Money::format($totals['net_due'] ?? '0.00') }}</td>
            </tr>
        </tfoot>
    </table>
</x-print-layout>
