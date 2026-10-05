@php
    use App\Support\Money;
@endphp

<div class="space-y-4">
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-xs">
            <thead class="bg-gray-50 uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                <tr>
                    <th class="py-2.5 px-3">Batch Source / Ref</th>
                    <th class="py-2.5 px-3">Batch Date</th>
                    <th class="py-2.5 px-3 text-right">Landed Cost</th>
                    <th class="py-2.5 px-3 text-right">Remaining / Original Qty</th>
                    <th class="py-2.5 px-3 text-right">Subtotal Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($batches as $b)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                        <td class="py-2.5 px-3 font-semibold text-primary-600 dark:text-primary-400">
                            @if ($b->isPurchase() && $b->purchase_id)
                                {{ $b->purchase?->invoice_no ?? 'PUR-'.$b->purchase_id }}
                                @if ($b->purchase?->vendor?->name)
                                    <div class="text-[10px] text-gray-400">{{ $b->purchase->vendor->name }}</div>
                                @endif
                            @else
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ $b->source?->label() ?? 'Standalone Batch' }}
                                </span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-gray-600 dark:text-gray-300">
                            {{ date('d M Y', strtotime((string)$b->batch_date)) }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-medium">
                            {{ Money::format($b->landed_unit_cost) }}
                        </td>
                        <td class="py-2.5 px-3 text-right">
                            <span class="inline-flex items-center rounded bg-emerald-100 px-2 py-0.5 font-bold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                {{ Money::formatQty($b->remaining_qty) }} / {{ Money::formatQty($b->qty) }} {{ $product->unit?->short_name }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-right font-bold text-gray-900 dark:text-white">
                            {{ Money::format(bcmul((string)$b->remaining_qty, (string)$b->landed_unit_cost, 2)) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center text-gray-400">No open batches found for this product.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
