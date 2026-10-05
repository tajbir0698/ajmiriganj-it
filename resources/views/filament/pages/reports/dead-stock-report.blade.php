<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Bar --}}
        <x-filament::section compact class="print:hidden">
            <form method="GET" class="flex gap-4 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Inactivity Threshold (Days)</label>
                    <input type="number" name="days" value="{{ $days }}" min="1" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 w-36">
                </div>
                <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg text-sm transition">
                    Update Report
                </button>
            </form>
        </x-filament::section>

        @php
            $data = $this->reportData;
            $totals = $data['totals'];
            $rows = $data['rows'];
        @endphp

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Dead Stock Products</span>
                <p class="text-2xl font-bold mt-1 text-gray-900 dark:text-gray-100 tabular-nums">{{ $totals['count'] }}</p>
                <span class="text-xs text-gray-400 mt-1 block">Zero sales in last {{ $data['days_threshold'] }} days</span>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Dead Stock Qty</span>
                <p class="text-2xl font-bold mt-1 text-amber-600 tabular-nums">{{ \App\Support\Money::formatQty($totals['total_qty']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tied-Up Capital (FIFO Value)</span>
                <p class="text-2xl font-bold mt-1 text-rose-600 tabular-nums">{{ \App\Support\Money::format($totals['total_fifo_value']) }}</p>
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
                            <th class="px-4 py-3 text-right">Current Stock</th>
                            <th class="px-4 py-3 text-right">FIFO Value</th>
                            <th class="px-4 py-3 text-right">Last Sold Date</th>
                            <th class="px-4 py-3 text-right">Days Inactive</th>
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
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ \App\Support\Money::formatQty($r['stock_qty']) }} {{ $r['unit'] }}</td>
                                <td class="px-4 py-3 text-right font-bold text-rose-600 tabular-nums">{{ \App\Support\Money::format($r['fifo_stock_value']) }}</td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ $r['last_sale_date'] ?? 'Never Sold' }}</td>
                                <td class="px-4 py-3 text-right font-medium text-amber-600 tabular-nums">{{ $r['days_since_sale'] }} days</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                    No dead stock items found for the selected threshold.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-white/5 text-sm font-semibold border-t-2 border-gray-200 dark:border-white/10">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-gray-700 dark:text-gray-300">Total Dead Stock</td>
                            <td class="px-4 py-3 text-right font-bold tabular-nums">{{ \App\Support\Money::formatQty($totals['total_qty']) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-rose-600 tabular-nums">{{ \App\Support\Money::format($totals['total_fifo_value']) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
