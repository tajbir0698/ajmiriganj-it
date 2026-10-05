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
            $isManager = $data['is_manager'];
            $rows = $data['rows'];
            $showCashier = ! $isManager || \App\Models\Setting::get('manager_sales_visibility', 'all') === 'all';
        @endphp

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Sales Count</span>
                <p class="text-2xl font-bold mt-1 text-gray-900 dark:text-gray-100 tabular-nums">{{ $summary['sales_count'] }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Sales</span>
                <p class="text-2xl font-bold mt-1 text-primary-600 tabular-nums">{{ \App\Support\Money::format($summary['total_sales']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Paid Amount</span>
                <p class="text-2xl font-bold mt-1 text-emerald-600 tabular-nums">{{ \App\Support\Money::format($summary['total_paid']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Outstanding Due</span>
                <p class="text-2xl font-bold mt-1 text-amber-600 tabular-nums">{{ \App\Support\Money::format($summary['total_due']) }}</p>
            </x-filament::section>

            @if(! $isManager)
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bill Discounts</span>
                    <p class="text-xl font-bold mt-1 text-gray-700 dark:text-gray-300 tabular-nums">{{ \App\Support\Money::format($summary['bill_discounts']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Net Sales</span>
                    <p class="text-xl font-bold mt-1 text-blue-600 tabular-nums">{{ \App\Support\Money::format($summary['net_sales']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Net Profit</span>
                    <p class="text-xl font-bold mt-1 text-emerald-600 tabular-nums">{{ \App\Support\Money::format($summary['net_profit']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Profit Margin</span>
                    <p class="text-xl font-bold mt-1 text-purple-600 tabular-nums">{{ $summary['margin_percent'] }}</p>
                </x-filament::section>
            @endif
        </div>

        {{-- Table --}}
        <x-filament::section>
            <div class="overflow-x-auto -mx-6 -my-4">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                    <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                        <tr>
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Customer</th>
                            @if($showCashier)
                                <th class="px-4 py-3">Cashier</th>
                            @endif
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            @if(! $isManager)
                                <th class="px-4 py-3 text-right">Profit</th>
                                <th class="px-4 py-3 text-right">Margin</th>
                            @endif
                            <th class="px-4 py-3 text-right">Paid</th>
                            <th class="px-4 py-3 text-right">Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($rows as $r)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                <td class="px-4 py-3 font-medium text-primary-600">{{ $r['invoice_no'] }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $r['date'] }}</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $r['customer_name'] }}</td>
                                @if($showCashier)
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $r['cashier_name'] ?? 'N/A' }}</td>
                                @endif
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $r['payment_method_label'] }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ \App\Support\Money::format($r['total']) }}</td>
                                @if(! $isManager)
                                    <td class="px-4 py-3 text-right font-medium text-emerald-600 tabular-nums">{{ \App\Support\Money::format($r['net_profit'] ?? '0.00') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ $r['margin_percent'] ?? '0%' }}</td>
                                @endif
                                <td class="px-4 py-3 text-right text-emerald-600 tabular-nums">{{ \App\Support\Money::format($r['paid_amount']) }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 font-medium tabular-nums">{{ \App\Support\Money::format($r['due_amount']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 6 + ($showCashier ? 1 : 0) + (! $isManager ? 2 : 0) }}" class="px-4 py-8 text-center text-gray-400">
                                    No sales found for the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
