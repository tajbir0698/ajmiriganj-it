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
            $totals = $data['totals'];
            $adjustments = $data['adjustments'];
            $returns = $data['purchase_returns'];
        @endphp

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Damage & Decrease Adjustments</span>
                <p class="text-2xl font-bold mt-1 text-rose-600 tabular-nums">{{ \App\Support\Money::format($totals['adjustment_losses']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Purchase Return Price Losses</span>
                <p class="text-2xl font-bold mt-1 text-amber-600 tabular-nums">{{ \App\Support\Money::format($totals['purchase_return_losses']) }}</p>
                <span class="text-xs text-gray-400 mt-1 block">Total cost removed vs credit allowed</span>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Stock Losses (P&L Deduction)</span>
                <p class="text-2xl font-bold mt-1 text-rose-700 dark:text-rose-400 tabular-nums">{{ \App\Support\Money::format($totals['total_stock_losses']) }}</p>
            </x-filament::section>
        </div>

        {{-- Adjustments Table --}}
        <x-filament::section>
            <x-slot name="heading">
                <span class="font-bold text-gray-900 dark:text-gray-100">Physical Inventory Adjustments & Damages</span>
            </x-slot>
            <div class="overflow-x-auto -mx-6 -my-4">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                    <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Total Cost</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3">Entered By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($adjustments as $adj)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $adj['date'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                        {{ ucfirst($adj['type']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $adj['product_name'] }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ \App\Support\Money::formatQty($adj['qty']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-rose-600 tabular-nums">{{ \App\Support\Money::format($adj['total_cost']) }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $adj['reason'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ $adj['created_by'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-400">No stock loss adjustments in period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Purchase Returns Variation Table --}}
        <x-filament::section>
            <x-slot name="heading">
                <span class="font-bold text-gray-900 dark:text-gray-100">Purchase Returns Cost vs Credit Variances</span>
            </x-slot>
            <div class="overflow-x-auto -mx-6 -my-4">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                    <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Return No</th>
                            <th class="px-4 py-3">Vendor</th>
                            <th class="px-4 py-3 text-right">FIFO Cost Removed</th>
                            <th class="px-4 py-3 text-right">Credit Received</th>
                            <th class="px-4 py-3 text-right">Loss / (Gain)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($returns as $pr)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $pr['date'] }}</td>
                                <td class="px-4 py-3 font-medium text-primary-600">{{ $pr['return_no'] }}</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $pr['vendor_name'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::format($pr['total_cost_removed']) }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 tabular-nums">{{ \App\Support\Money::format($pr['credit_amount']) }}</td>
                                <td class="px-4 py-3 text-right font-bold tabular-nums {{ bccomp($pr['loss_amount'], '0.00', 2) >= 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                    {{ \App\Support\Money::format($pr['loss_amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-400">No purchase return cost variances recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
