<x-print-layout :title="$title" :periodText="$periodText">
    {{-- By Payment Method --}}
    <div style="margin-bottom: 16px;">
        <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 3px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #4f46e5;">1. Inflow & Outflow by Payment Method</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th class="text-right">POS Sales In</th>
                    <th class="text-right">Collections In</th>
                    <th class="text-right">Total Inflow</th>
                    <th class="text-right">Refunds Out</th>
                    <th class="text-right">Net Flow</th>
                </tr>
            </thead>
            <tbody>
                @foreach($methods as $m)
                    <tr>
                        <td style="font-weight: 500;">{{ $m['label'] }}</td>
                        <td class="tabular-nums">{{ \App\Support\Money::format($m['pos_sales']) }}</td>
                        <td class="tabular-nums">{{ \App\Support\Money::format($m['collections']) }}</td>
                        <td class="tabular-nums" style="font-weight: 600; color: #059669;">+ {{ \App\Support\Money::format($m['total_in']) }}</td>
                        <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($m['refunds_out']) }}</td>
                        <td class="tabular-nums" style="font-weight: 700; color: {{ bccomp($m['net_amount'], '0.00', 2) >= 0 ? '#059669' : '#be123c' }};">
                            {{ \App\Support\Money::format($m['net_amount']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: #eff6ff; font-weight: 700; color: #1e40af;">
                    <td>Total Payment Gateway Flow</td>
                    <td colspan="2"></td>
                    <td class="tabular-nums">+ {{ \App\Support\Money::format($totalInflow) }}</td>
                    <td colspan="1"></td>
                    <td class="tabular-nums" style="font-size: 13px;">{{ \App\Support\Money::format($totalNet) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- By Account --}}
    @if(isset($accounts) && $accounts->count() > 0)
        <div>
            <div style="border-bottom: 2px solid #059669; padding-bottom: 3px; margin-bottom: 6px;">
                <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #059669;">2. Inflow & Outflow by Liquidity Account</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="text-right">POS Sales In</th>
                        <th class="text-right">Collections In</th>
                        <th class="text-right">Total Inflow</th>
                        <th class="text-right">Refunds Out</th>
                        <th class="text-right">Net Flow</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $acc)
                        <tr>
                            <td style="font-weight: 500;">{{ $acc['account_name'] }}</td>
                            <td class="tabular-nums">{{ \App\Support\Money::format($acc['pos_sales']) }}</td>
                            <td class="tabular-nums">{{ \App\Support\Money::format($acc['collections']) }}</td>
                            <td class="tabular-nums" style="font-weight: 600; color: #059669;">+ {{ \App\Support\Money::format($acc['total_in']) }}</td>
                            <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($acc['refunds_out']) }}</td>
                            <td class="tabular-nums" style="font-weight: 700;">{{ \App\Support\Money::format($acc['net_amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-print-layout>
