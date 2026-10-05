<x-print-layout :title="$title" :periodText="$periodText">
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th class="text-right">Sold Qty</th>
                <th class="text-right">Ret Qty</th>
                <th class="text-right">Net Qty</th>
                <th class="text-right">Revenue</th>
                <th class="text-right">Profit</th>
                <th class="text-right">Margin</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="font-weight: 500;">{{ $r['product_name'] }}</td>
                    <td style="color: #6b7280;">{{ $r['sku'] }}</td>
                    <td style="color: #6b7280;">{{ $r['category_name'] }}</td>
                    <td class="tabular-nums">{{ \App\Support\Money::formatQty($r['sold_qty']) }}</td>
                    <td class="tabular-nums" style="color: #b45309;">{{ \App\Support\Money::formatQty($r['returned_qty']) }}</td>
                    <td class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::formatQty($r['net_qty']) }}</td>
                    <td class="tabular-nums" style="font-weight: 600;">{{ \App\Support\Money::format($r['revenue']) }}</td>
                    <td class="tabular-nums" style="color: #059669; font-weight: 600;">{{ \App\Support\Money::format($r['profit_after_returns']) }}</td>
                    <td class="tabular-nums" style="color: #4b5563;">{{ $r['margin_percent'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No product sales recorded for this period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #f9fafb; font-weight: 700;">
                <td colspan="3">Grand Totals</td>
                <td class="tabular-nums">{{ \App\Support\Money::formatQty($summary['total_sold_qty']) }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::formatQty($summary['total_returned_qty']) }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::formatQty(bcsub((string)$summary['total_sold_qty'], (string)$summary['total_returned_qty'], 3)) }}</td>
                <td class="tabular-nums">{{ \App\Support\Money::format($summary['total_revenue']) }}</td>
                <td class="tabular-nums" style="color: #059669;">{{ \App\Support\Money::format($summary['profit_after_returns']) }}</td>
                <td></td>
            </tr>
            @if(bccomp((string)$summary['less_bill_discounts'], '0.00', 2) > 0)
                <tr style="color: #b45309;">
                    <td colspan="7" style="font-style: italic;">Less: Bill Level Discounts</td>
                    <td class="tabular-nums" style="font-weight: 600;">- {{ \App\Support\Money::format($summary['less_bill_discounts']) }}</td>
                    <td></td>
                </tr>
            @endif
            <tr style="background: #ecfdf5; font-weight: 700; border-top: 1px solid #a7f3d0; color: #065f46;">
                <td colspan="7">
                    Reconciled Gross Profit
                    @if($matches)
                        <span class="badge badge-success" style="margin-left: 6px;">✓ Matches P&L</span>
                    @else
                        <span class="badge badge-danger" style="margin-left: 6px;">⚠ Difference ৳{{ $diff }}</span>
                    @endif
                </td>
                <td class="tabular-nums" style="font-size: 13px;">{{ \App\Support\Money::format($summary['reconciled_gross_profit']) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</x-print-layout>
