@php
    use App\Support\Money;
    $product = $getRecord();
    $batches = $product->batches()->with(['purchase.vendor'])->orderBy('id')->get();
    $histories = $product->priceHistories()->with('changedBy')->take(20)->get();
@endphp

@if (auth()->user()?->isSuperAdmin())
    <div class="space-y-6">
        <!-- FIFO Batches Table -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800 flex items-center justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">
                    Active FIFO Stock Batches
                </h3>
                <span class="text-xs text-gray-500">Ordered by arrival (oldest first)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-xs">
                    <thead class="bg-gray-50 uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                        <tr>
                            <th class="py-2.5 px-4">Purchase Invoice</th>
                            <th class="py-2.5 px-4">Vendor</th>
                            <th class="py-2.5 px-4">Arrival Date</th>
                            <th class="py-2.5 px-4 text-right">Landed Cost</th>
                            <th class="py-2.5 px-4 text-right">Remaining / Original Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($batches as $batch)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="py-2.5 px-4 font-semibold text-primary-600 dark:text-primary-400">
                                    @if ($batch->purchase_id && $batch->purchase?->invoice_no)
                                        <a href="{{ route('filament.admin.resources.purchases.view', $batch->purchase_id) }}" class="hover:underline">
                                            {{ $batch->purchase->invoice_no }}
                                        </a>
                                    @else
                                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $batch->source?->label() ?? 'Standalone' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-4">
                                    {{ $batch->purchase?->vendor?->name ?? ($batch->isOpening() ? 'Opening Stock' : ($batch->isAdjustment() ? 'Stock Adjustment' : 'Internal')) }}
                                </td>
                                <td class="py-2.5 px-4 text-gray-500">
                                    {{ $batch->batch_date ? date('d M Y', strtotime((string)$batch->batch_date)) : '-' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-medium">
                                    {{ Money::format($batch->landed_unit_cost) }}
                                </td>
                                <td class="py-2.5 px-4 text-right">
                                    <span class="inline-flex items-center rounded bg-emerald-100 px-2 py-0.5 font-bold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        {{ Money::formatQty($batch->remaining_qty) }} / {{ Money::formatQty($batch->qty) }} {{ $product->unit?->short_name }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-gray-400">No active stock batches available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Price History Table -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <h3 class="text-sm font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">
                    Price & Cost Change Audit History
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-xs">
                    <thead class="bg-gray-50 uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                        <tr>
                            <th class="py-2.5 px-4">Date & Time</th>
                            <th class="py-2.5 px-4">Cost Change</th>
                            <th class="py-2.5 px-4">Sale Price Change</th>
                            <th class="py-2.5 px-4">Reason</th>
                            <th class="py-2.5 px-4">Changed By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($histories as $hist)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="py-2.5 px-4 text-gray-500">{{ $hist->changed_at ? $hist->changed_at->format('d M Y, h:i A') : '-' }}</td>
                                <td class="py-2.5 px-4">
                                    <span class="text-gray-400">{{ Money::format($hist->old_cost) }}</span>
                                    <span class="mx-1 text-gray-400">→</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ Money::format($hist->new_cost) }}</span>
                                </td>
                                <td class="py-2.5 px-4">
                                    <span class="text-gray-400">{{ Money::format($hist->old_sale_price) }}</span>
                                    <span class="mx-1 text-gray-400">→</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ Money::format($hist->new_sale_price) }}</span>
                                </td>
                                <td class="py-2.5 px-4 text-gray-700 dark:text-gray-300">{{ $hist->reason }}</td>
                                <td class="py-2.5 px-4 text-gray-500">{{ $hist->changedBy?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-gray-400">No price change records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
