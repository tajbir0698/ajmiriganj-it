<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters Bar --}}
        <x-filament::section compact class="print:hidden">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Period Preset</label>
                    <select name="period" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100" onchange="this.form.submit()">
                        <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
                        <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                        <option value="this_week" {{ $period === 'this_week' ? 'selected' : '' }}>This Week</option>
                        <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ $period === 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_year" {{ $period === 'this_year' ? 'selected' : '' }}>This Year</option>
                        <option value="all" {{ $period === 'all' ? 'selected' : '' }}>All Time</option>
                        <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Custom Date Range</option>
                    </select>
                </div>

                @if($period === 'custom')
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">From Date</label>
                        <input type="date" name="from" value="{{ $from }}" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">To Date</label>
                        <input type="date" name="to" value="{{ $to }}" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    </div>
                @endif

                <div>
                    <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg text-sm w-full transition">
                        Update Statement
                    </button>
                </div>
            </form>
        </x-filament::section>

        @php
            $data = $this->reportData;
            $sales = $data['sales_summary'];
            $otherIncome = $data['other_income'];
            $operatingExpenses = $data['operating_expenses'];
        @endphp

        {{-- Financial Statement Card --}}
        <x-filament::section class="max-w-4xl mx-auto space-y-6">
            <div class="border-b border-gray-100 dark:border-white/10 pb-4 text-center">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Ajmiriganj IT</h2>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Statement of Profit & Loss</p>
                <p class="text-xs text-gray-400 mt-1">Period: {{ $data['period']['start_date'] ?? 'Beginning' }} to {{ $data['period']['end_date'] ?? 'Present' }}</p>
            </div>

            {{-- 1. Sales Revenue --}}
            <div class="space-y-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Trading Revenue & Cost of Goods Sold</h3>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                        <span class="text-gray-600 dark:text-gray-300">Gross Sales (Invoiced Items)</span>
                        <span class="font-medium tabular-nums">{{ \App\Support\Money::format($sales['gross_sales']) }}</span>
                    </div>
                    <div class="flex justify-between py-1 text-amber-600 border-b border-gray-50 dark:border-white/5">
                        <span>Less: Bill Discounts</span>
                        <span class="tabular-nums">- {{ \App\Support\Money::format($sales['discounts']) }}</span>
                    </div>
                    <div class="flex justify-between py-1 text-rose-600 border-b border-gray-50 dark:border-white/5">
                        <span>Less: Sale Returns (Refunds)</span>
                        <span class="tabular-nums">- {{ \App\Support\Money::format($sales['sale_returns']) }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-white/10">
                        <span>Net Sales Revenue</span>
                        <span class="tabular-nums">{{ \App\Support\Money::format($sales['effective_sales']) }}</span>
                    </div>
                    <div class="flex justify-between py-1 text-gray-500 dark:text-gray-400 border-b border-gray-50 dark:border-white/5">
                        <span>Less: Cost of Goods Sold (FIFO Cost Net of Returns)</span>
                        <span class="tabular-nums">- {{ \App\Support\Money::format($sales['cogs']) }}</span>
                    </div>
                    <div class="flex justify-between py-2 font-bold text-base text-primary-600 bg-primary-50/50 dark:bg-primary-950/20 px-3 rounded-lg">
                        <span>Gross Profit</span>
                        <span class="tabular-nums">{{ \App\Support\Money::format($sales['gross_profit']) }}</span>
                    </div>
                </div>
            </div>

            {{-- 2. Other Income --}}
            <div class="space-y-2 pt-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Other Operating Income</h3>
                <div class="space-y-1.5 text-sm">
                    @forelse($otherIncome['categories'] as $inc)
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                            <span class="text-gray-600 dark:text-gray-300">{{ $inc['category_name'] }}</span>
                            <span class="font-medium text-emerald-600 tabular-nums">+ {{ \App\Support\Money::format($inc['net_amount']) }}</span>
                        </div>
                    @empty
                        <div class="text-xs text-gray-400 italic py-1">No other income entries recorded.</div>
                    @endforelse
                    <div class="flex justify-between py-1.5 font-semibold text-gray-900 dark:text-gray-100 border-t border-gray-200 dark:border-white/10">
                        <span>Total Other Income</span>
                        <span class="text-emerald-600 tabular-nums">{{ \App\Support\Money::format($otherIncome['total']) }}</span>
                    </div>
                </div>
            </div>

            {{-- 3. Operating Expenses & Stock Losses --}}
            <div class="space-y-2 pt-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Operating Expenses & Stock Losses</h3>
                <div class="space-y-1.5 text-sm">
                    @forelse($operatingExpenses['categories'] as $exp)
                        <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5">
                            <span class="text-gray-600 dark:text-gray-300">{{ $exp['category_name'] }}</span>
                            <span class="font-medium text-rose-600 tabular-nums">- {{ \App\Support\Money::format($exp['net_amount']) }}</span>
                        </div>
                    @empty
                        <div class="text-xs text-gray-400 italic py-1">No operating expenses recorded.</div>
                    @endforelse

                    <div class="flex justify-between py-1 border-b border-gray-50 dark:border-white/5 text-rose-600">
                        <span>Stock Losses (Adjustments & Return Variations)</span>
                        <span class="font-medium tabular-nums">- {{ \App\Support\Money::format($data['stock_losses']) }}</span>
                    </div>

                    <div class="flex justify-between py-1.5 font-semibold text-gray-900 dark:text-gray-100 border-t border-gray-200 dark:border-white/10">
                        <span>Total Operating Deductions</span>
                        <span class="text-rose-600 tabular-nums">{{ \App\Support\Money::format(bcadd($operatingExpenses['total'], $data['stock_losses'], 2)) }}</span>
                    </div>
                </div>
            </div>

            {{-- 4. Net Profit --}}
            <div class="pt-4 border-t-2 border-gray-200 dark:border-white/10">
                <div class="flex justify-between items-center py-4 px-6 rounded-xl {{ bccomp($data['net_profit'], '0.00', 2) >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-200' : 'bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-200' }}">
                    <div>
                        <span class="text-lg font-bold">NET PROFIT / (LOSS)</span>
                        <p class="text-xs text-gray-500 mt-0.5">Accrual basis net earnings for the business</p>
                    </div>
                    <span class="text-3xl font-extrabold tabular-nums">{{ \App\Support\Money::format($data['net_profit']) }}</span>
                </div>
                <div class="mt-2 text-right">
                    @if($data['reconciliation_check'])
                        <span class="inline-flex items-center text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                            ✓ Reconciled with BusinessFinanceService
                        </span>
                    @endif
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
