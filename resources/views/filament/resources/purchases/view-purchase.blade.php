@php
    use App\Support\Money;
    $purchase = $getRecord();
@endphp

<div class="space-y-6">
    <!-- Header Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Bill</span>
            <div class="mt-2 text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ Money::format($purchase->total) }}
            </div>
            <div class="mt-1 text-xs text-gray-400">
                Subtotal: {{ Money::format($purchase->subtotal) }}
                @if ((float) $purchase->discount > 0)
                    | Disc: {{ Money::format($purchase->discount) }}
                @endif
                @if ((float) $purchase->shipping_cost > 0)
                    | Ship: {{ Money::format($purchase->shipping_cost) }}
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Paid Amount</span>
            <div class="mt-2 text-xl font-bold text-emerald-600 dark:text-emerald-400">
                {{ Money::format($purchase->paid_amount) }}
            </div>
            <span class="text-xs text-gray-400">Paid on purchase</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Due Amount</span>
            <div class="mt-2 text-xl font-bold {{ (float) $purchase->due_amount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                {{ Money::format($purchase->due_amount) }}
            </div>
            <span class="text-xs text-gray-400">Outstanding balance</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Order Status</span>
            <div class="mt-2 text-base font-semibold">
                @if ($purchase->isCancelled())
                    <span class="inline-flex items-center rounded-md bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                        CANCELLED
                    </span>
                @else
                    <span class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                        ACTIVE
                    </span>
                @endif
            </div>
            <span class="text-xs text-gray-400">Created by: {{ $purchase->creator?->name ?? 'System' }}</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Payment Status</span>
            <div class="mt-2 text-base font-semibold">
                <span class="inline-flex items-center rounded-md bg-purple-100 px-2.5 py-0.5 text-xs font-bold text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                    {{ strtoupper($purchase->payment_status->value) }}
                </span>
            </div>
            <span class="text-xs text-gray-400">{{ date('d M Y', strtotime($purchase->purchase_date)) }}</span>
        </div>
    </div>

    <!-- FIFO Batches / Items Table -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">
                Purchase Items (FIFO Batches)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                    <tr>
                        <th class="py-3 px-4">Product</th>
                        <th class="py-3 px-4">SKU</th>
                        <th class="py-3 px-4 text-right">Purchased Qty</th>
                        <th class="py-3 px-4 text-right">Invoice Unit Cost</th>
                        <th class="py-3 px-4 text-right">Landed Unit Cost</th>
                        <th class="py-3 px-4 text-right">Remaining (Batch)</th>
                        <th class="py-3 px-4 text-right">Sale Price</th>
                        <th class="py-3 px-4 text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($purchase->items as $item)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                            <td class="py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">
                                {{ $item->product?->name }}
                            </td>
                            <td class="py-3 px-4 font-mono text-xs text-gray-500">{{ $item->product?->sku }}</td>
                            <td class="py-3 px-4 text-right font-medium">
                                {{ Money::formatQty($item->qty) }} {{ $item->product?->unit?->short_name }}
                            </td>
                            <td class="py-3 px-4 text-right">{{ Money::format($item->unit_cost) }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-primary-600 dark:text-primary-400">
                                {{ Money::format($item->landed_unit_cost) }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold {{ (float) $item->remaining_qty > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                    {{ Money::formatQty($item->remaining_qty) }} / {{ Money::formatQty($item->qty) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-gray-700 dark:text-gray-300">
                                {{ $item->new_sale_price ? Money::format($item->new_sale_price) : ($item->product ? Money::format($item->product->sale_price) : '-') }}
                            </td>
                            <td class="py-3 px-4 text-right font-bold">
                                {{ Money::format(bcmul((string) $item->qty, (string) $item->unit_cost, 2)) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payments & Attachments Section -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Payments Table -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">
                Payment History
            </h3>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-xs">
                <thead>
                    <tr class="text-gray-400">
                        <th class="py-2">Date</th>
                        <th class="py-2">Method</th>
                        <th class="py-2">Account</th>
                        <th class="py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($purchase->payments as $payment)
                        <tr>
                            <td class="py-2 text-gray-700 dark:text-gray-300">{{ date('d M Y', strtotime($payment->payment_date)) }}</td>
                            <td class="py-2"><span class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $payment->payment_method->label() }}</span></td>
                            <td class="py-2 text-gray-600 dark:text-gray-400">{{ $payment->account?->name }}</td>
                            <td class="py-2 text-right font-semibold text-emerald-600">{{ Money::format($payment->amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-gray-400">No payments recorded for this invoice yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Attachments Table -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">
                Uploaded Bills & Receipts
            </h3>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($purchase->attachments as $att)
                    <div class="flex items-center justify-between py-2 text-xs">
                        <div>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $att->original_name }}</span>
                            <span class="ml-2 text-gray-400">({{ $att->formatted_size }})</span>
                        </div>
                        <a href="{{ $att->download_url }}" class="inline-flex items-center rounded bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-100 dark:bg-primary-950/50 dark:text-primary-300">
                            Download
                        </a>
                    </div>
                @empty
                    <div class="py-4 text-center text-xs text-gray-400">
                        No bill or receipt files uploaded.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($purchase->note)
        <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Note:</span>
            <p class="mt-1 text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $purchase->note }}</p>
        </div>
    @endif
</div>
