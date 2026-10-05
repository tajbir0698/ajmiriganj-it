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
            $categories = $data['categories'];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Categorized Income</span>
                <p class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-400 tabular-nums">{{ \App\Support\Money::format($totals['total_income']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Operating Expenses</span>
                <p class="text-2xl font-bold mt-1 text-rose-600 dark:text-rose-400 tabular-nums">{{ \App\Support\Money::format($totals['total_expense']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Net Operating Flow</span>
                <p class="text-2xl font-bold mt-1 tabular-nums {{ bccomp($totals['net_cash_flow'], '0.00', 2) >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ \App\Support\Money::format($totals['net_cash_flow']) }}
                </p>
            </x-filament::section>
        </div>

        <div class="space-y-4">
            @forelse($categories as $cat)
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <span>{{ $cat['category_name'] }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-normal {{ $cat['category_type'] === 'income' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' }}">
                                {{ ucfirst($cat['category_type']) }}
                            </span>
                        </div>
                    </x-slot>
                    <x-slot name="headerEnd">
                        <div class="text-sm font-bold tabular-nums {{ $cat['category_type'] === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            Net Total: {{ \App\Support\Money::format($cat['net_amount']) }}
                        </div>
                    </x-slot>

                    <div class="overflow-x-auto -mx-6 -my-4">
                        <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th class="text-right">In</th>
                                    <th class="text-right">Out</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach($cat['transactions'] as $tx)
                                    <tr class="{{ $tx['is_reversal'] ? 'opacity-50 italic' : '' }}">
                                        <td class="text-gray-500 dark:text-gray-400 text-xs">{{ $tx['date'] }}</td>
                                        <td class="text-gray-600 dark:text-gray-300">{{ $tx['account_name'] }}</td>
                                        <td class="font-medium text-gray-900 dark:text-gray-100">{{ $tx['description'] }}</td>
                                        <td class="text-right text-emerald-600 dark:text-emerald-400 font-medium tabular-nums">{{ $tx['type'] === 'in' ? \App\Support\Money::format($tx['amount']) : '-' }}</td>
                                        <td class="text-right text-rose-600 dark:text-rose-400 font-medium tabular-nums">{{ $tx['type'] === 'out' ? \App\Support\Money::format($tx['amount']) : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <div class="py-8 text-center text-gray-400 dark:text-gray-500">
                        No transactions recorded in this period.
                    </div>
                </x-filament::section>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
