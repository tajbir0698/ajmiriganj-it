<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters Bar --}}
        <x-filament::section compact class="print:hidden">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
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
                    <button type="submit" class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-btn-color-primary fi-color-primary bg-primary-600 hover:bg-primary-500 text-white shadow-sm px-4 py-2 text-sm w-full inline-flex">
                        Update Report
                    </button>
                </div>
            </form>
        </x-filament::section>

        @php
            $data = $this->reportData;
            $totals = $data['totals'];
            $rows = $data['rows'];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Payments Count</span>
                <p class="text-2xl font-bold mt-1 text-gray-900 dark:text-gray-100 tabular-nums">{{ $totals['count'] }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Vendor Paid</span>
                <p class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-400 tabular-nums">{{ \App\Support\Money::format($totals['active_amount']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Reversals</span>
                <p class="text-2xl font-bold mt-1 text-rose-600 dark:text-rose-400 tabular-nums">{{ \App\Support\Money::format($totals['reversed_amount']) }}</p>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">
                Vendor Payments Details
            </x-slot>

            <div class="overflow-x-auto -mx-6 -my-4">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                    <thead>
                        <tr>
                            <th>Reference No</th>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th>Account</th>
                            <th>Method</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($rows as $r)
                            <tr class="{{ $r['is_reversed'] ? 'opacity-50 line-through' : '' }}">
                                <td class="font-medium text-primary-600 dark:text-primary-400">{{ $r['reference_no'] }}</td>
                                <td class="text-gray-500 dark:text-gray-400">{{ $r['payment_date'] }}</td>
                                <td class="font-medium text-gray-900 dark:text-gray-100">{{ $r['vendor_name'] }}</td>
                                <td class="text-gray-500 dark:text-gray-400">{{ $r['account_name'] }}</td>
                                <td class="text-gray-500 dark:text-gray-400">{{ $r['payment_method_label'] }}</td>
                                <td class="text-right font-bold text-rose-600 dark:text-rose-400 tabular-nums">{{ \App\Support\Money::format($r['amount']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-400 dark:text-gray-500">No vendor payments recorded in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
