@php
    use App\Support\Money;
    $record = $getRecord();
    $accountService = app(\App\Services\CustomerAccountService::class);
    $currentDue = $accountService->getCurrentDue($record);
    $ledger = $accountService->getLedger($record);

    $sales = $record->sales()->latest('sale_date')->get();
    $payments = $record->payments()->latest('payment_date')->with(['account', 'attachments'])->get();
    $returns = $record->saleReturns()->latest('return_date')->get();

    $totalSales = '0.00';
    foreach ($sales as $s) {
        $totalSales = bcadd($totalSales, (string) $s->total, 2);
    }

    $totalPaid = '0.00';
    foreach ($payments as $p) {
        if (!$p->isReversed()) {
            $totalPaid = bcadd($totalPaid, (string) $p->amount, 2);
        }
    }

    $totalReturns = '0.00';
    foreach ($returns as $r) {
        $totalReturns = bcadd($totalReturns, (string) $r->refund_amount, 2);
    }
@endphp

<div class="space-y-6" x-data="{ tab: 'ledger' }">
    <!-- Top Summary Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Sales</span>
            <div class="mt-2 text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ Money::format($totalSales) }}
            </div>
            <span class="text-xs text-gray-400">{{ $sales->count() }} sales</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Payments</span>
            <div class="mt-2 text-xl font-bold text-emerald-600 dark:text-emerald-400">
                {{ Money::format($totalPaid) }}
            </div>
            <span class="text-xs text-gray-400">{{ $payments->count() }} payments recorded</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Returns</span>
            <div class="mt-2 text-xl font-bold text-amber-600 dark:text-amber-400">
                {{ Money::format($totalReturns) }}
            </div>
            <span class="text-xs text-gray-400">{{ $returns->count() }} returns</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ bccomp($currentDue, '0.00', 2) < 0 ? 'Advance / Credit Balance' : 'Current Due Balance' }}
            </span>
            <div class="mt-2 text-xl font-bold {{ bccomp($currentDue, '0.00', 2) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                @if(bccomp($currentDue, '0.00', 2) < 0)
                    {{ Money::format(bcmul($currentDue, '-1', 2)) }} <span class="text-xs font-normal text-gray-500">(Credit)</span>
                @else
                    {{ Money::format($currentDue) }}
                @endif
            </div>
            <span class="text-xs text-gray-400">Net outstanding</span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-800">
        <nav class="-mb-px flex space-x-6">
            <button @click="tab = 'ledger'" :class="{ 'border-primary-500 text-primary-600 dark:text-primary-400': tab === 'ledger', 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'ledger' }" class="border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                Account Ledger ({{ $ledger->count() }})
            </button>
            <button @click="tab = 'sales'" :class="{ 'border-primary-500 text-primary-600 dark:text-primary-400': tab === 'sales', 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'sales' }" class="border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                Invoices ({{ $sales->count() }})
            </button>
            <button @click="tab = 'payments'" :class="{ 'border-primary-500 text-primary-600 dark:text-primary-400': tab === 'payments', 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'payments' }" class="border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                Due Payments ({{ $payments->count() }})
            </button>
            <button @click="tab = 'returns'" :class="{ 'border-primary-500 text-primary-600 dark:text-primary-400': tab === 'returns', 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'returns' }" class="border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                Returns ({{ $returns->count() }})
            </button>
        </nav>
    </div>

    <!-- Tab 1: Ledger -->
    <div x-show="tab === 'ledger'" class="space-y-4">
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm text-gray-700 dark:text-gray-300">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Ref</th>
                        <th class="px-4 py-3 text-right">Debit (Due)</th>
                        <th class="px-4 py-3 text-right">Credit (Paid/Return)</th>
                        <th class="px-4 py-3 text-right">Running Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($ledger as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row['date'] }}</td>
                            <td class="px-4 py-3">{{ $row['description'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $row['reference'] }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ bccomp($row['bill_amount'], '0.00', 2) > 0 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400' }}">
                                {{ bccomp($row['bill_amount'], '0.00', 2) > 0 ? Money::format($row['bill_amount']) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium {{ bccomp($row['paid_amount'], '0.00', 2) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                {{ bccomp($row['paid_amount'], '0.00', 2) > 0 ? Money::format($row['paid_amount']) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold {{ bccomp($row['balance'], '0.00', 2) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ Money::format($row['balance']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No ledger entries recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 2: Sales Invoices -->
    <div x-show="tab === 'sales'" class="space-y-4" style="display: none;">
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm text-gray-700 dark:text-gray-300">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Invoice #</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Paid</th>
                        <th class="px-4 py-3 text-right">Outstanding Due</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($sales as $sale)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-mono font-medium">{{ $sale->invoice_no }}</td>
                            <td class="px-4 py-3">{{ $sale->sale_date ? $sale->sale_date->format('d-m-Y') : '' }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ Money::format((string) $sale->total) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ Money::format((string) $sale->paid_amount) }}</td>
                            <td class="px-4 py-3 text-right font-bold {{ bccomp((string) $sale->outstanding_due, '0.00', 2) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                {{ Money::format((string) $sale->outstanding_due) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="/sales/{{ $sale->id }}/receipt" target="_blank" class="text-xs text-primary-600 hover:underline">Receipt</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No sales recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 3: Payments -->
    <div x-show="tab === 'payments'" class="space-y-4" style="display: none;">
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm text-gray-700 dark:text-gray-300">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Receipt #</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-center">Receipt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($payments as $pmt)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-mono font-medium">{{ $pmt->payment_no }}</td>
                            <td class="px-4 py-3">{{ $pmt->payment_date ? $pmt->payment_date->format('d-m-Y') : '' }}</td>
                            <td class="px-4 py-3">{{ $pmt->payment_method->label() }}</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ Money::format((string) $pmt->amount) }}</td>
                            <td class="px-4 py-3">
                                @if($pmt->isReversed())
                                    <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">Reversed</span>
                                @else
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Active</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="/customer-payments/{{ $pmt->id }}/receipt" target="_blank" class="text-xs text-primary-600 hover:underline">Print</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No payments recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 4: Returns -->
    <div x-show="tab === 'returns'" class="space-y-4" style="display: none;">
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm text-gray-700 dark:text-gray-300">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Return #</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 text-right">Refund Amount</th>
                        <th class="px-4 py-3 text-right">Due Reduced</th>
                        <th class="px-4 py-3 text-right">Cash Paid</th>
                        <th class="px-4 py-3 text-center">Receipt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($returns as $ret)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-mono font-medium">{{ $ret->return_no }}</td>
                            <td class="px-4 py-3">{{ $ret->return_date ? $ret->return_date->format('d-m-Y') : '' }}</td>
                            <td class="px-4 py-3 text-right font-bold">{{ Money::format((string) $ret->refund_amount) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ Money::format((string) $ret->due_reduction) }}</td>
                            <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">{{ Money::format((string) $ret->cash_refund) }}</td>
                            <td class="px-4 py-3 text-center">
                                <a href="/sale-returns/{{ $ret->id }}/receipt" target="_blank" class="text-xs text-primary-600 hover:underline">Print</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No returns recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
