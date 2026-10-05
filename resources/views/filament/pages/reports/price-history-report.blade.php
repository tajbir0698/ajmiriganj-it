<x-filament-panels::page>
    <div class="space-y-6">
        {{-- View Switcher --}}
        <div class="flex gap-2 print:hidden">
            <a href="?view_type=changes" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $viewType === 'changes' ? 'bg-primary-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Price Change Audit
            </a>
            <a href="?view_type=vendor_comparison" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $viewType === 'vendor_comparison' ? 'bg-primary-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Vendor Cost Comparison
            </a>
        </div>

        @php
            $data = $this->reportData;
            $changes = $data['price_changes'];
            $vendorComparison = $data['vendor_comparison'];
        @endphp

        @if($viewType === 'changes')
            <x-filament::section>
                <div class="overflow-x-auto -mx-6 -my-4">
                    <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                        <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3 text-right">Old Cost</th>
                                <th class="px-4 py-3 text-right">New Cost</th>
                                <th class="px-4 py-3 text-right">Old Sale Price</th>
                                <th class="px-4 py-3 text-right">New Sale Price</th>
                                <th class="px-4 py-3">Reason</th>
                                <th class="px-4 py-3">Changed By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse($changes as $ch)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $ch['date'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $ch['product_name'] }}
                                        <span class="block text-xs text-gray-400">SKU: {{ $ch['sku'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($ch['old_cost']) }}</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums {{ bccomp($ch['cost_difference'], '0.00', 2) > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                        {{ \App\Support\Money::format($ch['new_cost']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 tabular-nums">{{ \App\Support\Money::format($ch['old_sale_price']) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-primary-600 tabular-nums">
                                        {{ \App\Support\Money::format($ch['new_sale_price']) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $ch['reason'] ?? 'System Update' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-400">{{ $ch['changed_by'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">No price changes recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="overflow-x-auto -mx-6 -my-4">
                    <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                        <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400 font-semibold">
                            <tr>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">Vendor</th>
                                <th class="px-4 py-3">Last Purchase Date</th>
                                <th class="px-4 py-3 text-right">Latest Base Unit Cost</th>
                                <th class="px-4 py-3 text-right">Latest Landed Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse($vendorComparison as $vc)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $vc['product_name'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $vc['vendor_name'] }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $vc['last_purchase_date'] }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100 tabular-nums">{{ \App\Support\Money::format($vc['latest_unit_cost']) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-primary-600 tabular-nums">{{ \App\Support\Money::format($vc['latest_landed_cost']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400">No vendor purchase records available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
