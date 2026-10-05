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
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Purchase Bills Count</span>
                <p class="text-2xl font-bold mt-1 text-gray-900 dark:text-gray-100 tabular-nums">{{ $summary['purchases_count'] }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Purchases</span>
                <p class="text-2xl font-bold mt-1 text-primary-600 tabular-nums">{{ \App\Support\Money::format($summary['total_purchases']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Paid</span>
                <p class="text-2xl font-bold mt-1 text-emerald-600 tabular-nums">{{ \App\Support\Money::format($summary['total_paid']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Due On Bills</span>
                <p class="text-2xl font-bold mt-1 text-amber-600 tabular-nums">{{ \App\Support\Money::format($summary['total_due']) }}</p>
            </x-filament::section>
        </div>

        {{-- Table --}}
        <x-filament::section>
            <div class="overflow-x-auto -mx-6 -my-4">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                    <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                        <tr>
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Vendor</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3 text-right">Discount</th>
                            <th class="px-4 py-3 text-right">Extra Cost</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Paid</th>
                            <th class="px-4 py-3 text-right">Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($rows as $r)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                <td class="px-4 py-3 font-medium text-primary-600">{{ $r['invoice_no'] }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $r['date'] }}</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $r['vendor_name'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $r['payment_status'] === 'paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($r['payment_status'] === 'partial' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300') }}">
                                        {{ ucfirst($r['payment_status']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($r['subtotal']) }}</td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($r['discount']) }}</td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($r['extra_cost']) }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ \App\Support\Money::format($r['total']) }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 tabular-nums">{{ \App\Support\Money::format($r['paid_amount']) }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 font-medium tabular-nums">{{ \App\Support\Money::format($r['due_amount']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-gray-400">
                                    No purchase bills recorded for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-white/5 text-sm font-semibold border-t-2 border-gray-200 dark:border-white/10">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-gray-700 dark:text-gray-300">Summary Totals</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($summary['subtotal']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($summary['discount']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($summary['extra_cost']) }}</td>
                            <td class="px-4 py-3 text-right font-bold tabular-nums">{{ \App\Support\Money::format($summary['total_purchases']) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600 tabular-nums">{{ \App\Support\Money::format($summary['total_paid']) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-amber-600 tabular-nums">{{ \App\Support\Money::format($summary['total_due']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
