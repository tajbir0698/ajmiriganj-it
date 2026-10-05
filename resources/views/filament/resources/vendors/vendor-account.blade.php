@php
    use App\Support\Money;
    $record = $getRecord();
    $accountService = app(\App\Services\VendorAccountService::class);
    $currentDue = $accountService->getCurrentDue($record);
    $totalPurchased = $accountService->getTotalPurchased($record);
    $totalPaid = $accountService->getTotalPaid($record);
    $lastPurchase = $accountService->getLastPurchaseDate($record);
    $lastPayment = $accountService->getLastPaymentDate($record);
    $ledger = $accountService->getLedger($record);

    $purchases = $record->purchases()->latest('purchase_date')->with(['items.product'])->get();
    $payments = $record->payments()->latest('payment_date')->with(['account', 'attachments'])->get();

    $purchaseIds = $record->purchases()->pluck('id')->toArray();
    $paymentIds = $record->payments()->pluck('id')->toArray();

    $files = \App\Models\Attachment::query()
        ->where(function ($q) use ($record, $purchaseIds, $paymentIds) {
            $q->where(function ($sub) use ($purchaseIds) {
                $sub->where('attachable_type', \App\Models\Purchase::class)
                    ->whereIn('attachable_id', $purchaseIds);
            })->orWhere(function ($sub) use ($paymentIds) {
                $sub->where('attachable_type', \App\Models\VendorPayment::class)
                    ->whereIn('attachable_id', $paymentIds);
            })->orWhere(function ($sub) use ($record) {
                $sub->where('attachable_type', \App\Models\Vendor::class)
                    ->where('attachable_id', $record->id);
            });
        })
        ->latest()
        ->get();

    // Products supplied
    $suppliedItems = \App\Models\PurchaseItem::query()
        ->whereIn('purchase_id', $purchaseIds)
        ->with('product.unit')
        ->latest('id')
        ->get()
        ->unique('product_id');
@endphp

<div class="space-y-6" x-data="{ tab: 'purchases' }">
    <!-- Top Summary Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Purchased</span>
            <div class="mt-2 text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ Money::format($totalPurchased) }}
            </div>
            <span class="text-xs text-gray-400">{{ $purchases->count() }} active orders</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Paid</span>
            <div class="mt-2 text-xl font-bold text-emerald-600 dark:text-emerald-400">
                {{ Money::format($totalPaid) }}
            </div>
            <span class="text-xs text-gray-400">{{ $payments->count() }} payments recorded</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Current Due</span>
            <div class="mt-2 text-xl font-bold {{ (float) $currentDue > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                {{ Money::format($currentDue) }}
            </div>
            <span class="text-xs text-gray-400">Running payable balance</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Purchase</span>
            <div class="mt-2 text-base font-semibold text-gray-800 dark:text-gray-200">
                {{ $lastPurchase ? date('d M Y', strtotime($lastPurchase)) : 'None' }}
            </div>
            <span class="text-xs text-gray-400">Most recent order</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Payment</span>
            <div class="mt-2 text-base font-semibold text-gray-800 dark:text-gray-200">
                {{ $lastPayment ? date('d M Y', strtotime($lastPayment)) : 'None' }}
            </div>
            <span class="text-xs text-gray-400">Most recent payout</span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-800">
        <nav class="-mb-px flex space-x-6">
            <button type="button" @click="tab = 'purchases'" :class="tab === 'purchases' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'" class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition">
                Purchases ({{ $purchases->count() }})
            </button>
            <button type="button" @click="tab = 'payments'" :class="tab === 'payments' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'" class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition">
                Payments ({{ $payments->count() }})
            </button>
            <button type="button" @click="tab = 'ledger'" :class="tab === 'ledger' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'" class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition">
                Ledger (Running Balance)
            </button>
            <button type="button" @click="tab = 'products'" :class="tab === 'products' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'" class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition">
                Products Supplied ({{ $suppliedItems->count() }})
            </button>
            <button type="button" @click="tab = 'files'" :class="tab === 'files' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'" class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition">
                Files & Bills ({{ $files->count() }})
            </button>
        </nav>
    </div>

    <!-- TAB 1: PURCHASES -->
    <div x-show="tab === 'purchases'" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                <tr>
                    <th class="py-3 px-4">Invoice No</th>
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Items</th>
                    <th class="py-3 px-4 text-right">Total</th>
                    <th class="py-3 px-4 text-right">Paid</th>
                    <th class="py-3 px-4 text-right">Due</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($purchases as $p)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                        <td class="py-3 px-4 font-semibold text-primary-600 dark:text-primary-400">
                            {{ $p->invoice_no }}
                            @if ($p->vendor_invoice_no)
                                <div class="text-xs text-gray-400">Ref: {{ $p->vendor_invoice_no }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4">{{ date('d M Y', strtotime($p->purchase_date)) }}</td>
                        <td class="py-3 px-4">{{ $p->items->count() }} items</td>
                        <td class="py-3 px-4 text-right font-medium">{{ Money::format($p->total) }}</td>
                        <td class="py-3 px-4 text-right text-emerald-600">{{ Money::format($p->paid_amount) }}</td>
                        <td class="py-3 px-4 text-right text-rose-600">{{ Money::format($p->due_amount) }}</td>
                        <td class="py-3 px-4 text-center">
                            @if ($p->isCancelled())
                                <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">Cancelled</span>
                            @else
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">{{ ucfirst($p->payment_status->value) }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            <a href="{{ route('filament.admin.resources.purchases.view', $p->id) }}" class="text-xs font-medium text-primary-600 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-sm text-gray-400">No purchases found for this vendor.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- TAB 2: PAYMENTS -->
    <div x-show="tab === 'payments'" style="display: none;" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                <tr>
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Purchase Ref</th>
                    <th class="py-3 px-4">Method</th>
                    <th class="py-3 px-4">Account</th>
                    <th class="py-3 px-4">Ref No</th>
                    <th class="py-3 px-4 text-right">Amount</th>
                    <th class="py-3 px-4 text-center">Receipts</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($payments as $pay)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                        <td class="py-3 px-4">{{ date('d M Y', strtotime($pay->payment_date)) }}</td>
                        <td class="py-3 px-4 font-medium text-gray-700 dark:text-gray-300">
                            {{ $pay->purchase ? $pay->purchase->invoice_no : 'Advance / Lump Sum' }}
                        </td>
                        <td class="py-3 px-4"><span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-300">{{ $pay->payment_method->label() }}</span></td>
                        <td class="py-3 px-4">{{ $pay->account?->name ?? '-' }}</td>
                        <td class="py-3 px-4 text-gray-500">{{ $pay->reference_no ?? '-' }}</td>
                        <td class="py-3 px-4 text-right font-semibold text-emerald-600">{{ Money::format($pay->amount) }}</td>
                        <td class="py-3 px-4 text-center">
                            @if ($pay->attachments->isNotEmpty())
                                @foreach ($pay->attachments as $att)
                                    <a href="{{ $att->download_url }}" class="inline-block text-xs text-primary-600 hover:underline">Receipt #{{ $att->id }}</a>
                                @endforeach
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-sm text-gray-400">No payment records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- TAB 3: LEDGER (RUNNING BALANCE) -->
    <div x-show="tab === 'ledger'" style="display: none;" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                <tr>
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Reference</th>
                    <th class="py-3 px-4">Description</th>
                    <th class="py-3 px-4 text-right">Bill / Due (+)</th>
                    <th class="py-3 px-4 text-right">Payment / Reduction (-)</th>
                    <th class="py-3 px-4 text-right font-bold">Running Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($ledger as $row)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                        <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ date('d M Y', strtotime($row['date'])) }}</td>
                        <td class="py-3 px-4 font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $row['reference'] }}</td>
                        <td class="py-3 px-4">{{ $row['description'] }}</td>
                        <td class="py-3 px-4 text-right {{ (float) $row['bill_amount'] != 0 ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-400' }}">
                            {{ (float) $row['bill_amount'] != 0 ? Money::format($row['bill_amount']) : '-' }}
                        </td>
                        <td class="py-3 px-4 text-right {{ (float) $row['paid_amount'] > 0 ? 'text-emerald-600 font-medium' : 'text-gray-400' }}">
                            {{ (float) $row['paid_amount'] > 0 ? Money::format($row['paid_amount']) : '-' }}
                        </td>
                        <td class="py-3 px-4 text-right font-bold {{ (float) $row['balance'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ Money::format($row['balance']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- TAB 4: PRODUCTS SUPPLIED -->
    <div x-show="tab === 'products'" style="display: none;" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                <tr>
                    <th class="py-3 px-4">Product Name</th>
                    <th class="py-3 px-4">SKU</th>
                    <th class="py-3 px-4">Unit</th>
                    <th class="py-3 px-4 text-right">Last Purchase Cost</th>
                    <th class="py-3 px-4 text-right">Current Sale Price</th>
                    <th class="py-3 px-4 text-center">Last Supplied Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($suppliedItems as $item)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                        <td class="py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">{{ $item->product?->name }}</td>
                        <td class="py-3 px-4 font-mono text-xs text-gray-500">{{ $item->product?->sku }}</td>
                        <td class="py-3 px-4">{{ $item->product?->unit?->name }}</td>
                        <td class="py-3 px-4 text-right font-semibold">{{ Money::format($item->landed_unit_cost) }}</td>
                        <td class="py-3 px-4 text-right text-gray-700 dark:text-gray-300">{{ Money::format($item->product?->sale_price) }}</td>
                        <td class="py-3 px-4 text-center text-gray-500">{{ $item->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-sm text-gray-400">No products supplied by this vendor yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- TAB 5: FILES & BILLS -->
    <div x-show="tab === 'files'" style="display: none;" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                <tr>
                    <th class="py-3 px-4">Filename</th>
                    <th class="py-3 px-4">Type</th>
                    <th class="py-3 px-4">Size</th>
                    <th class="py-3 px-4">Uploaded</th>
                    <th class="py-3 px-4 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($files as $file)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                        <td class="py-3 px-4 font-medium text-gray-800 dark:text-gray-200">{{ $file->original_name }}</td>
                        <td class="py-3 px-4"><span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $file->mime }}</span></td>
                        <td class="py-3 px-4 text-gray-500">{{ $file->formatted_size }}</td>
                        <td class="py-3 px-4 text-gray-500">{{ $file->created_at->format('d M Y, h:i A') }}</td>
                        <td class="py-3 px-4 text-center">
                            <a href="{{ $file->download_url }}" class="inline-flex items-center rounded-lg bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-100 dark:bg-primary-950/50 dark:text-primary-300">
                                Download
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-sm text-gray-400">No attached bills or receipts for this vendor.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
