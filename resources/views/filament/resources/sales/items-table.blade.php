@php
    use App\Support\Money;

    /** @var \App\Models\Sale $sale */
    $sale = $getRecord();
    $isSuperAdmin = (bool) (auth()->user()?->isSuperAdmin() ?? false);
    $items = $sale->items ?? collect();

    $totalQty = '0.000';
    $totalLineDiscount = '0.00';
    $totalLineCost = '0.00';
    $totalLineProfit = '0.00';

    foreach ($items as $item) {
        $totalQty = bcadd($totalQty, (string) $item->qty, 3);
        $totalLineDiscount = bcadd($totalLineDiscount, (string) $item->discount, 2);
        if ($isSuperAdmin) {
            $totalLineCost = bcadd($totalLineCost, (string) $item->line_cost, 2);
            $totalLineProfit = bcadd($totalLineProfit, (string) $item->line_profit, 2);
        }
    }
@endphp

<div class="sale-items-table-wrapper" style="overflow-x: auto; border-radius: 0.5rem; border: 1px solid rgba(156, 163, 175, 0.2);">
    <table class="sale-items-table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left;">
        <thead>
            <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.2); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                <th style="padding: 10px 14px; width: 40px; text-align: center;">#</th>
                <th style="padding: 10px 14px;">Item Description</th>
                <th style="padding: 10px 14px; text-align: right; width: 100px;">Qty</th>
                <th style="padding: 10px 14px; text-align: right; width: 120px;">Unit Price</th>
                <th style="padding: 10px 14px; text-align: right; width: 110px;">Discount</th>
                <th style="padding: 10px 14px; text-align: right; width: 130px;">Line Total</th>
                @if ($isSuperAdmin)
                    <th style="padding: 10px 14px; text-align: right; width: 130px;">FIFO Cost</th>
                    <th style="padding: 10px 14px; text-align: right; width: 120px;">Profit</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $index => $item)
                <tr class="sale-item-row" style="border-bottom: 1px solid rgba(156, 163, 175, 0.15);">
                    <td style="padding: 10px 14px; text-align: center; color: #9ca3af; font-size: 0.8rem;">
                        {{ $index + 1 }}
                    </td>
                    <td style="padding: 10px 14px;">
                        <div style="font-weight: 600;">{{ $item->product_name }}</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 2px;">
                            @if ($item->sku)
                                <span style="font-family: monospace; font-size: 0.72rem; color: #9ca3af;">SKU: {{ $item->sku }}</span>
                            @endif
                            @if ($isSuperAdmin && $item->batches->isNotEmpty())
                                <span style="font-size: 0.72rem; color: #a78bfa; background: rgba(167, 139, 250, 0.1); padding: 1px 6px; border-radius: 4px;">
                                    Batches: {{ $item->batches->map(fn ($b) => '#' . $b->purchase_item_id . ' (' . Money::formatQty((string) $b->qty) . ' @ ৳' . number_format((float) $b->unit_cost, 2) . ')')->join(', ') }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td style="padding: 10px 14px; text-align: right; font-weight: 500;">
                        {{ Money::formatQty((string) $item->qty) }}
                    </td>
                    <td style="padding: 10px 14px; text-align: right; font-family: ui-monospace, monospace;">
                        {{ Money::format((string) $item->unit_price) }}
                    </td>
                    <td style="padding: 10px 14px; text-align: right; font-family: ui-monospace, monospace; color: {{ bccomp((string) $item->discount, '0.00', 2) > 0 ? '#f59e0b' : '#9ca3af' }};">
                        {{ Money::format((string) $item->discount) }}
                    </td>
                    <td style="padding: 10px 14px; text-align: right; font-weight: 700; font-family: ui-monospace, monospace;">
                        {{ Money::format((string) $item->line_total) }}
                    </td>
                    @if ($isSuperAdmin)
                        <td style="padding: 10px 14px; text-align: right; font-family: ui-monospace, monospace; color: #9ca3af;">
                            <div>{{ Money::format((string) $item->line_cost) }}</div>
                            <div style="font-size: 0.7rem; color: #6b7280;">@ {{ number_format((float) $item->unit_cost, 2) }}/u</div>
                        </td>
                        <td style="padding: 10px 14px; text-align: right; font-weight: 700; font-family: ui-monospace, monospace; color: {{ bccomp((string) $item->line_profit, '0.00', 2) >= 0 ? '#10b981' : '#ef4444' }};">
                            {{ Money::format((string) $item->line_profit) }}
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isSuperAdmin ? 8 : 6 }}" style="padding: 24px; text-align: center; color: #9ca3af;">
                        No items found for this sale.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($items->isNotEmpty())
            <tfoot>
                <tr style="border-top: 2px solid rgba(156, 163, 175, 0.3); font-weight: 700;">
                    <td colspan="2" style="padding: 12px 14px;">
                        Total ({{ $items->count() }} {{ \Illuminate\Support\Str::plural('item', $items->count()) }})
                    </td>
                    <td style="padding: 12px 14px; text-align: right; font-family: ui-monospace, monospace;">
                        {{ Money::formatQty($totalQty) }}
                    </td>
                    <td style="padding: 12px 14px; text-align: right; color: #9ca3af;">—</td>
                    <td style="padding: 12px 14px; text-align: right; font-family: ui-monospace, monospace; color: {{ bccomp($totalLineDiscount, '0.00', 2) > 0 ? '#f59e0b' : '#9ca3af' }};">
                        {{ Money::format($totalLineDiscount) }}
                    </td>
                    <td style="padding: 12px 14px; text-align: right; font-family: ui-monospace, monospace; color: var(--primary-600, #a78bfa);">
                        {{ Money::format((string) $sale->total) }}
                    </td>
                    @if ($isSuperAdmin)
                        <td style="padding: 12px 14px; text-align: right; font-family: ui-monospace, monospace; color: #9ca3af;">
                            {{ Money::format($totalLineCost) }}
                        </td>
                        <td style="padding: 12px 14px; text-align: right; font-family: ui-monospace, monospace; color: {{ bccomp((string) $sale->net_profit, '0.00', 2) >= 0 ? '#10b981' : '#ef4444' }};">
                            {{ Money::format((string) $sale->gross_profit) }}
                        </td>
                    @endif
                </tr>
            </tfoot>
        @endif
    </table>
</div>

<style>
    .sale-items-table th {
        background-color: rgba(156, 163, 175, 0.05);
        color: #6b7280;
    }
    .sale-item-row:hover {
        background-color: rgba(156, 163, 175, 0.05);
    }
    html.dark .sale-items-table th {
        background-color: rgba(255, 255, 255, 0.03);
        color: #9ca3af;
    }
    html.dark .sale-items-table tfoot tr {
        background-color: rgba(255, 255, 255, 0.02);
    }
    html:not(.dark) .sale-items-table th {
        background-color: #f9fafb;
        color: #4b5563;
    }
    html:not(.dark) .sale-items-table tfoot tr {
        background-color: #f9fafb;
    }
</style>
