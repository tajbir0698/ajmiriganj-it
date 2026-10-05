<x-filament-panels::page>
    <div class="space-y-6">
        {{-- View Switcher --}}
        <div class="flex gap-2 print:hidden">
            <a href="?view_type=summary" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $viewType === 'summary' ? 'bg-primary-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Dues Summary
            </a>
            <a href="?view_type=aging" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $viewType === 'aging' ? 'bg-primary-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Aging Analysis (0-30, 31-60, 61-90, 90+)
            </a>
        </div>

        @if($viewType === 'summary')
            @php
                $data = $this->summaryData;
                $totals = $data['totals'];
                $rows = $data['rows'];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Opening Balance Total</span>
                    <p class="text-2xl font-bold mt-1 text-gray-900 dark:text-gray-100 tabular-nums">{{ \App\Support\Money::format($totals['opening_balance']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Sales Due</span>
                    <p class="text-2xl font-bold mt-1 text-amber-600 tabular-nums">{{ \App\Support\Money::format($totals['total_sales_due']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Collections Paid</span>
                    <p class="text-2xl font-bold mt-1 text-emerald-600 tabular-nums">{{ \App\Support\Money::format($totals['total_paid']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Current Receivables (Due)</span>
                    <p class="text-2xl font-bold mt-1 text-rose-600 tabular-nums">{{ \App\Support\Money::format($totals['current_due']) }}</p>
                </x-filament::section>
            </div>

            <x-filament::section>
                <div class="overflow-x-auto -mx-6 -my-4">
                    <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                        <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                            <tr>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Phone</th>
                                <th class="px-4 py-3 text-right">Opening</th>
                                <th class="px-4 py-3 text-right">Sales Due</th>
                                <th class="px-4 py-3 text-right">Paid</th>
                                <th class="px-4 py-3 text-right">Returns Reduction</th>
                                <th class="px-4 py-3 text-right">Current Due</th>
                                <th class="px-4 py-3 text-right">Advance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse($rows as $r)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $r['customer_name'] }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $r['phone'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($r['opening_balance']) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::format($r['total_sales_due']) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 tabular-nums">{{ \App\Support\Money::format($r['total_paid']) }}</td>
                                    <td class="px-4 py-3 text-right text-amber-600 tabular-nums">{{ \App\Support\Money::format($r['total_returns_reduction']) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-rose-600 tabular-nums">{{ \App\Support\Money::format($r['current_due']) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-600 tabular-nums">{{ \App\Support\Money::format($r['advance']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">No customer records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-white/5 text-sm font-semibold border-t-2 border-gray-200 dark:border-white/10">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-gray-700 dark:text-gray-300">Total Dues</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($totals['opening_balance']) }}</td>
                                <td class="px-4 py-3 text-right font-bold tabular-nums">{{ \App\Support\Money::format($totals['total_sales_due']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-600 tabular-nums">{{ \App\Support\Money::format($totals['total_paid']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-amber-600 tabular-nums">{{ \App\Support\Money::format($totals['total_returns_reduction']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-rose-600 tabular-nums">{{ \App\Support\Money::format($totals['current_due']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-blue-600 tabular-nums">{{ \App\Support\Money::format($totals['advance']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>
        @else
            @php
                $aging = $this->agingData;
                $totals = $aging['totals'];
                $rows = $aging['rows'];
            @endphp

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">0 - 30 Days</span>
                    <p class="text-xl font-bold mt-1 text-emerald-600 tabular-nums">{{ \App\Support\Money::format($totals['bucket_0_30']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">31 - 60 Days</span>
                    <p class="text-xl font-bold mt-1 text-blue-600 tabular-nums">{{ \App\Support\Money::format($totals['bucket_31_60']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">61 - 90 Days</span>
                    <p class="text-xl font-bold mt-1 text-amber-600 tabular-nums">{{ \App\Support\Money::format($totals['bucket_61_90']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">90+ Days Overdue</span>
                    <p class="text-xl font-bold mt-1 text-rose-600 tabular-nums">{{ \App\Support\Money::format($totals['bucket_90_plus']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Net Global Due</span>
                    <p class="text-xl font-bold mt-1 text-purple-600 tabular-nums">{{ \App\Support\Money::format($totals['net_due']) }}</p>
                </x-filament::section>
            </div>

            <x-filament::section>
                <div class="overflow-x-auto -mx-6 -my-4">
                    <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                        <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                            <tr>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3 text-right">Opening</th>
                                <th class="px-4 py-3 text-right">0-30 Days</th>
                                <th class="px-4 py-3 text-right">31-60 Days</th>
                                <th class="px-4 py-3 text-right">61-90 Days</th>
                                <th class="px-4 py-3 text-right">90+ Days</th>
                                <th class="px-4 py-3 text-right">Total Out</th>
                                <th class="px-4 py-3 text-right">Advance</th>
                                <th class="px-4 py-3 text-right">Net Due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse($rows as $r)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $r['customer_name'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($r['opening_balance']) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 tabular-nums">{{ \App\Support\Money::format($r['bucket_0_30']) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-600 tabular-nums">{{ \App\Support\Money::format($r['bucket_31_60']) }}</td>
                                    <td class="px-4 py-3 text-right text-amber-600 tabular-nums">{{ \App\Support\Money::format($r['bucket_61_90']) }}</td>
                                    <td class="px-4 py-3 text-right text-rose-600 font-medium tabular-nums">{{ \App\Support\Money::format($r['bucket_90_plus']) }}</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums">{{ \App\Support\Money::format($r['total_outstanding']) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-600 tabular-nums">{{ \App\Support\Money::format($r['advance']) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ \App\Support\Money::format($r['net_due']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-400">No aging records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-white/5 text-sm font-semibold border-t-2 border-gray-200 dark:border-white/10">
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">Aging Totals</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-700 dark:text-gray-300 tabular-nums">{{ \App\Support\Money::format($totals['opening_balance']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-600 tabular-nums">{{ \App\Support\Money::format($totals['bucket_0_30']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-blue-600 tabular-nums">{{ \App\Support\Money::format($totals['bucket_31_60']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-amber-600 tabular-nums">{{ \App\Support\Money::format($totals['bucket_61_90']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-rose-600 tabular-nums">{{ \App\Support\Money::format($totals['bucket_90_plus']) }}</td>
                                <td class="px-4 py-3 text-right font-bold tabular-nums">{{ \App\Support\Money::format($totals['total_outstanding'] ?? '0.00') }}</td>
                                <td class="px-4 py-3 text-right font-bold text-blue-600 tabular-nums">{{ \App\Support\Money::format($totals['advance']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-purple-600 tabular-nums">{{ \App\Support\Money::format($totals['net_due']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
