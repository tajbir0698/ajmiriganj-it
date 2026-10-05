<x-print-layout :title="$title" :periodText="$periodText">
    {{-- Physical Adjustments Table --}}
    <div style="margin-bottom: 16px;">
        <div style="border-bottom: 2px solid #be123c; padding-bottom: 3px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #be123c;">1. Physical Inventory Adjustments & Damages</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Product</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total Loss</th>
                    <th>Reason</th>
                    <th>Logged By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $a)
                    <tr>
                        <td style="color: #6b7280;">{{ $a['date'] }}</td>
                        <td><span class="badge badge-danger">{{ strtoupper($a['type']) }}</span></td>
                        <td style="font-weight: 500;">{{ $a['product_name'] }}</td>
                        <td class="tabular-nums">{{ \App\Support\Money::formatQty($a['qty']) }}</td>
                        <td class="tabular-nums" style="color: #be123c; font-weight: 600;">{{ \App\Support\Money::format($a['total_cost']) }}</td>
                        <td style="color: #4b5563;">{{ $a['reason'] ?? '—' }}</td>
                        <td style="color: #6b7280;">{{ $a['created_by'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 16px; color: #9ca3af;">No physical damage adjustments recorded.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: 700;">
                    <td colspan="4">Subtotal Physical Adjustments</td>
                    <td class="tabular-nums" style="color: #be123c;">{{ \App\Support\Money::format($totals['adjustment_losses']) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Purchase Return Price Losses Table --}}
    @if($purchaseReturns->count() > 0)
        <div style="margin-bottom: 16px;">
            <div style="border-bottom: 2px solid #d97706; padding-bottom: 3px; margin-bottom: 6px;">
                <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #d97706;">2. Purchase Return Price Differences (Removed Cost vs Credit)</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Return #</th>
                        <th>Vendor</th>
                        <th class="text-right">Cost Removed</th>
                        <th class="text-right">Credit Allowed</th>
                        <th class="text-right">Loss / (Gain)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseReturns as $pr)
                        <tr>
                            <td style="color: #6b7280;">{{ $pr['date'] }}</td>
                            <td style="font-weight: 600; color: #4f46e5;">{{ $pr['return_no'] }}</td>
                            <td>{{ $pr['vendor_name'] }}</td>
                            <td class="tabular-nums">{{ \App\Support\Money::format($pr['total_cost_removed']) }}</td>
                            <td class="tabular-nums">{{ \App\Support\Money::format($pr['credit_amount']) }}</td>
                            <td class="tabular-nums" style="font-weight: 600; color: {{ bccomp((string)$pr['loss_amount'], '0.00', 2) > 0 ? '#be123c' : '#059669' }};">
                                {{ \App\Support\Money::format($pr['loss_amount']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background: #f9fafb; font-weight: 700;">
                        <td colspan="5">Subtotal Purchase Return Losses</td>
                        <td class="tabular-nums" style="color: #d97706;">{{ \App\Support\Money::format($totals['purchase_return_losses']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    {{-- Grand Total Stock Loss --}}
    <table style="border-top: 2px solid #111827;">
        <tr style="background: #fef2f2; font-weight: 800; font-size: 13px; color: #991b1b;">
            <td style="padding: 10px 8px;">TOTAL STOCK LOSSES (P&L DEDUCTION)</td>
            <td class="tabular-nums" style="padding: 10px 8px; width: 140px;">{{ \App\Support\Money::format($totals['total_stock_losses']) }}</td>
        </tr>
    </table>
</x-print-layout>
