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
                        Apply Filters
                    </button>
                </div>
            </form>
        </x-filament::section>

        @php
            $data = $this->reportData;
            $summary = $data['summary'];
            $rows = $data['rows'];
        @endphp

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Gross Sales Revenue</span>
                <p class="text-2xl font-bold mt-1 text-primary-600 tabular-nums">{{ \App\Support\Money::format($summary['total_revenue']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total FIFO Cost</span>
                <p class="text-2xl font-bold mt-1 text-gray-700 dark:text-gray-300 tabular-nums">{{ \App\Support\Money::format($summary['total_cost']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Item Profit After Returns</span>
                <p class="text-2xl font-bold mt-1 text-blue-600 tabular-nums">{{ \App\Support\Money::format($summary['profit_after_returns']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Reconciled Gross Profit</span>
                <p class="text-2xl font-bold mt-1 text-emerald-600 tabular-nums">{{ \App\Support\Money::format($summary['reconciled_gross_profit']) }}</p>
                <span class="text-xs text-gray-400 mt-1 block">After {{ \App\Support\Money::format($summary['less_bill_discounts']) }} bill discounts</span>
            </x-filament::section>
        </div>

        {{-- Table --}}
        <x-filament::section>
            <div class="overflow-x-auto -mx-6 -my-4">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                    <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-right">Sold Qty</th>
                            <th class="px-4 py-3 text-right">Returned</th>
                            <th class="px-4 py-3 text-right">Net Qty</th>
                            <th class="px-4 py-3 text-right">Revenue</th>
                            <th class="px-4 py-3 text-right">Cost</th>
                            <th class="px-4 py-3 text-right">Net Profit</th>
                            <th class="px-4 py-3 text-right">Margin %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($rows as $r)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $r['name'] }}
                                    <span class="block text-xs text-gray-400">SKU: {{ $r['sku'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $r['category_name'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ \App\Support\Money::formatQty($r['sold_qty']) }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 tabular-nums">{{ \App\Support\Money::formatQty($r['returned_qty']) }}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100 tabular-nums">{{ \App\Support\Money::formatQty($r['net_qty']) }} {{ $r['unit'] }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ \App\Support\Money::format($r['revenue']) }}</td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($r['cost']) }}</td>
                                <td class="px-4 py-3 text-right font-medium text-emerald-600 tabular-nums">{{ \App\Support\Money::format($r['profit_after_returns']) }}</td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ $r['margin_percent'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-400">
                                    No product sales recorded for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-white/5 text-sm font-semibold border-t-2 border-gray-200 dark:border-white/10">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-gray-700 dark:text-gray-300">Total Item Profits</td>
                            <td class="px-4 py-3 text-right font-bold tabular-nums">{{ \App\Support\Money::format($summary['total_revenue']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($summary['total_cost']) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600 tabular-nums">{{ \App\Support\Money::format($summary['profit_after_returns']) }}</td>
                            <td></td>
                        </tr>
                        <tr class="text-amber-600 dark:text-amber-400">
                            <td colspan="7" class="px-4 py-2 italic">Less: Bill Level Discounts</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums">- {{ \App\Support\Money::format($summary['less_bill_discounts']) }}</td>
                            <td></td>
                        </tr>
                        @php
                            $periodObj = \App\Services\Reports\ReportPeriod::fromPreset($period, $from, $to);
                            $bfGross = app(\App\Services\BusinessFinanceService::class)->grossProfit($periodObj->from, $periodObj->to);
                            $diff = bcsub($summary['reconciled_gross_profit'] ?? '0.00', $bfGross, 2);
                            $matches = bccomp($diff, '0.00', 2) === 0;
                        @endphp
                        <tr class="{{ $matches ? 'bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-300 border-t border-emerald-200 dark:border-emerald-800' : 'bg-rose-50/50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-300 border-t border-rose-200 dark:border-rose-800' }}">
                            <td colspan="7" class="px-4 py-3 font-bold text-base">
                                Reconciled Gross Profit
                                @if($matches)
                                    <span class="ml-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200">✓ Matches P&L</span>
                                @else
                                    <span class="ml-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-800 dark:text-rose-200">⚠ Difference ৳{{ $diff }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-base tabular-nums">{{ \App\Support\Money::format($summary['reconciled_gross_profit']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
