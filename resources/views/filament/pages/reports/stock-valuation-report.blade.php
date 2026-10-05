<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $data = $this->reportData;
            $totals = $data['totals'];
            $categories = $data['categories'];
        @endphp

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Products In Scope</span>
                <p class="text-2xl font-bold mt-1 text-gray-900 dark:text-gray-100 tabular-nums">{{ $totals['product_count'] }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Stock Qty</span>
                <p class="text-2xl font-bold mt-1 text-blue-600 tabular-nums">{{ \App\Support\Money::formatQty($totals['total_qty']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">FIFO Stock Valuation</span>
                <p class="text-2xl font-bold mt-1 text-primary-600 tabular-nums">{{ \App\Support\Money::format($totals['total_fifo_value']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Potential Retail Value</span>
                <p class="text-2xl font-bold mt-1 text-emerald-600 tabular-nums">{{ \App\Support\Money::format($totals['total_retail_value']) }}</p>
                <span class="text-xs text-gray-400 mt-1 block">Potential Profit: {{ \App\Support\Money::format($totals['total_potential_profit']) }} ({{ $totals['margin_percent'] }}%)</span>
            </x-filament::section>
        </div>

        {{-- Categories and Products Table --}}
        @forelse($categories as $cat)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between w-full gap-2">
                        <span class="font-bold text-gray-900 dark:text-gray-100">{{ $cat['category_name'] }}</span>
                        <div class="text-xs space-x-3 text-gray-500 dark:text-gray-400 font-normal">
                            <span>Items: <strong>{{ count($cat['products']) }}</strong></span>
                            <span>Stock Qty: <strong class="tabular-nums">{{ \App\Support\Money::formatQty($cat['total_qty']) }}</strong></span>
                            <span>FIFO Value: <strong class="text-primary-600 tabular-nums">{{ \App\Support\Money::format($cat['total_fifo_value']) }}</strong></span>
                        </div>
                    </div>
                </x-slot>

                <div class="overflow-x-auto -mx-6 -my-4">
                    <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                        <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                            <tr>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3 text-right">Stock Qty</th>
                                <th class="px-4 py-3 text-right">Avg Cost</th>
                                <th class="px-4 py-3 text-right">Last Cost</th>
                                <th class="px-4 py-3 text-right">FIFO Value</th>
                                <th class="px-4 py-3 text-right">Sale Price</th>
                                <th class="px-4 py-3 text-right">Retail Value</th>
                                <th class="px-4 py-3 text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($cat['products'] as $p)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $p['name'] }}
                                        <span class="block text-xs text-gray-400">SKU: {{ $p['sku'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-800 dark:text-gray-200 tabular-nums">
                                        {{ \App\Support\Money::formatQty($p['stock_qty']) }} {{ $p['unit'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($p['avg_cost']) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($p['last_cost']) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-primary-600 tabular-nums">{{ \App\Support\Money::format($p['fifo_stock_value']) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300 tabular-nums">{{ \App\Support\Money::format($p['sale_price']) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 tabular-nums">{{ \App\Support\Money::format($p['potential_retail_value']) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ $p['margin_percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="p-8 text-center text-gray-400">
                    No products found in inventory.
                </div>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
