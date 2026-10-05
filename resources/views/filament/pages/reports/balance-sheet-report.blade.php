<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $data = $this->reportData;
            $assets = $data['assets'];
            $liabilities = $data['liabilities'];
            $equity = $data['equity'];
        @endphp

        {{-- Filter Bar --}}
        <x-filament::section compact class="print:hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <form method="GET" class="flex gap-3 items-center">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">As Of Date:</label>
                    <input type="date" name="as_of_date" value="{{ $data['as_of_date'] }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100" onchange="this.form.submit()">
                    <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg text-sm">Update Balance Sheet</button>
                </form>
                <div>
                    @if($data['is_balanced'])
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Balanced (Diff: ৳ 0.00)
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            Unbalanced Diff: ৳ {{ $data['difference'] }}
                        </span>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Balance Sheet Document Card --}}
        <x-filament::section class="max-w-4xl mx-auto space-y-8">
            <div class="border-b border-gray-100 dark:border-white/10 pb-4 text-center">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Ajmiriganj IT</h2>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Statement of Financial Position (Balance Sheet)</p>
                <p class="text-xs text-gray-400 mt-1">As of {{ $data['as_of_date'] }}</p>
            </div>

            {{-- 1. ASSETS --}}
            <div class="space-y-4">
                <div class="border-b-2 border-primary-600 pb-1">
                    <h3 class="text-base font-bold uppercase tracking-wider text-primary-600">Assets</h3>
                </div>

                {{-- Cash & Bank --}}
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase text-gray-400">Current Liquid Assets (Cash & Bank)</h4>
                    <div class="pl-4 space-y-1 text-sm">
                        @foreach($assets['cash_and_bank']['accounts'] as $acc)
                            @php
                                $lowerName = strtolower($acc['name']);
                                $hasType = str_contains($lowerName, 'cash') || str_contains($lowerName, 'bank') || str_contains($lowerName, 'wallet') || str_contains($lowerName, 'mfs');
                            @endphp
                            <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                                <span class="text-gray-600 dark:text-gray-300">
                                    {{ $acc['name'] }}
                                    @if(! $hasType && !empty($acc['type_label']))
                                        <span class="text-xs text-gray-400">({{ $acc['type_label'] }})</span>
                                    @endif
                                </span>
                                <span class="font-medium tabular-nums">{{ \App\Support\Money::format($acc['balance']) }}</span>
                            </div>
                        @endforeach
                        <div class="flex justify-between font-semibold text-gray-900 dark:text-gray-100 pt-1">
                            <span>Total Cash & Bank</span>
                            <span class="tabular-nums">{{ \App\Support\Money::format($assets['cash_and_bank']['total']) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Receivables, Stock, Advances --}}
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase text-gray-400">Operating Assets & Inventory</h4>
                    <div class="pl-4 space-y-1 text-sm">
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                            <span class="text-gray-600 dark:text-gray-300">Accounts Receivable (Customer Dues)</span>
                            <span class="font-medium text-amber-600 tabular-nums">{{ \App\Support\Money::format($assets['accounts_receivable']) }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                            <span class="text-gray-600 dark:text-gray-300">Merchandise Inventory (FIFO Valuation)</span>
                            <span class="font-medium text-blue-600 tabular-nums">{{ \App\Support\Money::format($assets['inventory']) }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                            <span class="text-gray-600 dark:text-gray-300">Advances to Vendors (Unallocated Payments)</span>
                            <span class="font-medium tabular-nums">{{ \App\Support\Money::format($assets['vendor_advances']) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between font-extrabold text-base bg-primary-50/50 dark:bg-primary-950/20 px-4 py-3 rounded-xl text-primary-700 dark:text-primary-300">
                    <span>TOTAL ASSETS</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($assets['total_assets']) }}</span>
                </div>
            </div>

            {{-- 2. LIABILITIES --}}
            <div class="space-y-4 pt-4">
                <div class="border-b-2 border-rose-600 pb-1">
                    <h3 class="text-base font-bold uppercase tracking-wider text-rose-600">Liabilities</h3>
                </div>

                <div class="pl-4 space-y-1 text-sm">
                    <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                        <span class="text-gray-600 dark:text-gray-300">Accounts Payable (Vendor Dues)</span>
                        <span class="font-medium text-rose-600 tabular-nums">{{ \App\Support\Money::format($liabilities['accounts_payable']) }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                        <span class="text-gray-600 dark:text-gray-300">Advances from Customers (Unallocated Receipts)</span>
                        <span class="font-medium tabular-nums">{{ \App\Support\Money::format($liabilities['customer_advances']) }}</span>
                    </div>
                </div>

                <div class="flex justify-between font-bold text-sm bg-rose-50/50 dark:bg-rose-950/20 px-4 py-2.5 rounded-xl text-rose-700 dark:text-rose-300">
                    <span>TOTAL LIABILITIES</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($liabilities['total_liabilities']) }}</span>
                </div>
            </div>

            {{-- 3. EQUITY --}}
            <div class="space-y-4 pt-4">
                <div class="border-b-2 border-emerald-600 pb-1">
                    <h3 class="text-base font-bold uppercase tracking-wider text-emerald-600">Owner's Equity</h3>
                </div>

                <div class="pl-4 space-y-1 text-sm">
                    <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                        <span class="text-gray-600 dark:text-gray-300">Owner Capital Contributions</span>
                        <span class="font-medium tabular-nums">{{ \App\Support\Money::format($equity['owner_investment']) }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5 text-rose-600">
                        <span>Less: Owner Drawings</span>
                        <span class="tabular-nums">- {{ \App\Support\Money::format($equity['owner_drawings']) }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5 text-emerald-600">
                        <span>Retained Earnings (Net Profit - Withdrawals)</span>
                        <span class="tabular-nums">+ {{ \App\Support\Money::format($equity['retained_profit']) }}</span>
                    </div>

                    @if(bccomp($equity['opening_account_balances'], '0.00', 2) > 0)
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5 text-gray-600 dark:text-gray-300">
                            <span>Opening Cash & Bank Balances</span>
                            <span class="tabular-nums">+ {{ \App\Support\Money::format($equity['opening_account_balances']) }}</span>
                        </div>
                    @endif

                    @if(bccomp($equity['opening_stock_batches'], '0.00', 2) > 0)
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5 text-gray-600 dark:text-gray-300">
                            <span>Opening Stock Batches (Initial Inventory)</span>
                            <span class="tabular-nums">+ {{ \App\Support\Money::format($equity['opening_stock_batches']) }}</span>
                        </div>
                    @endif

                    @if(bccomp($equity['opening_customer_balances'], '0.00', 2) > 0)
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5 text-gray-600 dark:text-gray-300">
                            <span>Opening Customer Receivables</span>
                            <span class="tabular-nums">+ {{ \App\Support\Money::format($equity['opening_customer_balances']) }}</span>
                        </div>
                    @endif

                    @if(bccomp($equity['opening_vendor_balances'], '0.00', 2) > 0)
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5 text-rose-600">
                            <span>Less: Opening Vendor Payables</span>
                            <span class="tabular-nums">- {{ \App\Support\Money::format($equity['opening_vendor_balances']) }}</span>
                        </div>
                    @endif

                    @if(bccomp($equity['positive_stock_adjustments'], '0.00', 2) > 0)
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5 text-emerald-600">
                            <span>Positive Stock Adjustments Gain</span>
                            <span class="tabular-nums">+ {{ \App\Support\Money::format($equity['positive_stock_adjustments']) }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex justify-between font-bold text-sm bg-emerald-50/50 dark:bg-emerald-950/20 px-4 py-2.5 rounded-xl text-emerald-700 dark:text-emerald-300">
                    <span>TOTAL EQUITY</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($equity['total_equity']) }}</span>
                </div>
            </div>

            {{-- 4. TOTAL LIABILITIES & EQUITY --}}
            <div class="pt-4 border-t-2 border-gray-900 dark:border-white/20">
                <div class="flex justify-between items-center py-4 px-6 rounded-xl bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 font-extrabold text-lg">
                    <span>TOTAL LIABILITIES & EQUITY</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($data['total_liabilities_and_equity']) }}</span>
                </div>
                <div class="mt-2 text-right">
                    @if($data['is_balanced'])
                        <span class="inline-flex items-center text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                            ✓ Matches (Difference ৳0.00)
                        </span>
                    @else
                        <span class="inline-flex items-center text-xs text-rose-600 dark:text-rose-400 font-semibold">
                            ⚠ Difference ৳{{ $data['difference'] }}
                        </span>
                    @endif
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
