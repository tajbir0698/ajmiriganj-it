<x-print-layout :title="$title" :periodText="$periodText">
    {{-- Income Section --}}
    <div style="margin-bottom: 16px;">
        <div style="border-bottom: 2px solid #059669; padding-bottom: 3px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #059669;">1. Operating Income by Category</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-right">Net Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incomes as $inc)
                    <tr>
                        <td style="font-weight: 500;">{{ $inc['category_name'] }}</td>
                        <td class="tabular-nums" style="width: 140px; font-weight: 600; color: #059669;">+ {{ \App\Support\Money::format($inc['net_amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-center" style="padding: 14px; color: #9ca3af;">No other operating income recorded.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background: #ecfdf5; font-weight: 700; color: #065f46;">
                    <td>Total Operating Income</td>
                    <td class="tabular-nums">+ {{ \App\Support\Money::format($totals['total_income']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Expense Section --}}
    <div style="margin-bottom: 16px;">
        <div style="border-bottom: 2px solid #be123c; padding-bottom: 3px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #be123c;">2. Operating Expenses by Category</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-right">Net Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $exp)
                    <tr>
                        <td style="font-weight: 500;">{{ $exp['category_name'] }}</td>
                        <td class="tabular-nums" style="width: 140px; font-weight: 600; color: #be123c;">- {{ \App\Support\Money::format($exp['net_amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-center" style="padding: 14px; color: #9ca3af;">No operating expenses recorded.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background: #fff1f2; font-weight: 700; color: #9f1239;">
                    <td>Total Operating Expenses</td>
                    <td class="tabular-nums">- {{ \App\Support\Money::format($totals['total_expense']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Net Margin --}}
    @php
        $netAmount = $totals['net_cash_flow'] ?? $totals['net_margin'] ?? '0.00';
    @endphp
    <table style="border-top: 2px solid #111827;">
        <tr style="background: {{ bccomp((string)$netAmount, '0.00', 2) >= 0 ? '#ecfdf5' : '#fef2f2' }}; font-weight: 800; font-size: 13px; color: {{ bccomp((string)$netAmount, '0.00', 2) >= 0 ? '#065f46' : '#991b1b' }};">
            <td style="padding: 10px 8px;">NET OPERATING MARGIN</td>
            <td class="tabular-nums" style="padding: 10px 8px; width: 140px;">{{ \App\Support\Money::format($netAmount) }}</td>
        </tr>
    </table>
</x-print-layout>
