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
                    </select>
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg text-sm w-full transition">
                        Update Report
                    </button>
                </div>
            </form>
        </x-filament::section>

        @php
            $data = $this->reportData;
            $methods = $data['methods'] ?? [];
            $totalInflows = $data['total_inflow'] ?? '0.00';
        @endphp

        <x-filament::section compact>
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Inflows Across All Channels</span>
            <p class="text-3xl font-extrabold mt-1 text-emerald-600 tabular-nums">{{ \App\Support\Money::format((string) $totalInflows) }}</p>
        </x-filament::section>

        <x-filament::section>
            <div class="overflow-x-auto -mx-6 -my-4">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                    <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                        <tr>
                            <th class="px-4 py-3">Channel / Payment Method</th>
                            <th class="px-4 py-3 text-right">Transactions Count</th>
                            <th class="px-4 py-3 text-right">POS Sales</th>
                            <th class="px-4 py-3 text-right">Due Collections</th>
                            <th class="px-4 py-3 text-right">Total Inflow</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($methods as $m)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">{{ $m['label'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ $m['tx_count'] }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 tabular-nums">{{ \App\Support\Money::format($m['pos_sales_total']) }}</td>
                                <td class="px-4 py-3 text-right text-blue-600 tabular-nums">{{ \App\Support\Money::format($m['collections_total']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-primary-600 tabular-nums">{{ \App\Support\Money::format($m['total_inflow']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400">No payment inflow data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-white/5 text-sm font-semibold border-t-2 border-gray-200 dark:border-white/10">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-gray-700 dark:text-gray-300">Total Inflow</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600 tabular-nums">{{ \App\Support\Money::format((string) $totalInflows) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
