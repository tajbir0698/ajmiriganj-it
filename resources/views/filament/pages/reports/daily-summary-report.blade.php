<x-filament-panels::page>
    @php
        $data = $this->reportData;
        $inflows = $data['inflows'];
        $outflows = $data['outflows'];
        $stock = $data['stock_summary'];
        $accounts = $data['accounts_summary'];
        $cashiers = $data['cashier_breakdown'];
    @endphp

    @if($thermalMode)
        {{-- 80mm Thermal Receipt Layout --}}
        <div class="print-receipt-80mm max-w-[80mm] mx-auto bg-white text-black font-mono text-xs p-2 leading-tight shadow-md border print:shadow-none print:border-none">
            <div class="text-center pb-2 border-b border-dashed border-black">
                <h1 class="text-base font-bold uppercase">Ajmiriganj IT</h1>
                <p class="text-[10px]">Daily Register Close / EOD Summary</p>
                <p class="text-[10px]">Date: {{ $data['date'] }}</p>
                <p class="text-[10px]">Printed: {{ now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->format('d M Y, h:i A') }}</p>
            </div>

            {{-- Cashier breakdown --}}
            <div class="py-2 border-b border-dashed border-black space-y-1">
                <p class="font-bold uppercase text-[11px]">Cashier Sales</p>
                @foreach($cashiers as $c)
                    <div class="flex justify-between">
                        <span>{{ $c['user_name'] }} ({{ $c['sales_count'] }}):</span>
                        <span class="tabular-nums">{{ \App\Support\Money::format($c['sales_total']) }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Inflows --}}
            <div class="py-2 border-b border-dashed border-black space-y-1">
                <p class="font-bold uppercase text-[11px]">Inflows</p>
                <div class="flex justify-between">
                    <span>POS Sales Collected:</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($inflows['pos_sales_collected']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Customer Due Collections:</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($inflows['customer_due_collections']) }}</span>
                </div>
                @if(bccomp($inflows['other_income'], '0.00', 2) > 0)
                    <div class="flex justify-between">
                        <span>Other Income:</span>
                        <span class="tabular-nums">{{ \App\Support\Money::format($inflows['other_income']) }}</span>
                    </div>
                @endif
                <div class="flex justify-between font-bold border-t border-black pt-1">
                    <span>Total Inflows:</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($inflows['total_inflows']) }}</span>
                </div>
            </div>

            {{-- Outflows --}}
            <div class="py-2 border-b border-dashed border-black space-y-1">
                <p class="font-bold uppercase text-[11px]">Outflows</p>
                <div class="flex justify-between">
                    <span>Sale Return Refunds:</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($outflows['sale_return_refunds']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Vendor Payments:</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($outflows['vendor_payments']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Operating Expenses:</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($outflows['operating_expenses']) }}</span>
                </div>
                @if(bccomp($outflows['owner_drawings'], '0.00', 2) > 0)
                    <div class="flex justify-between">
                        <span>Owner Drawings:</span>
                        <span class="tabular-nums">{{ \App\Support\Money::format($outflows['owner_drawings']) }}</span>
                    </div>
                @endif
                <div class="flex justify-between font-bold border-t border-black pt-1">
                    <span>Total Outflows:</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($outflows['total_outflows']) }}</span>
                </div>
            </div>

            {{-- Net Cash Flow --}}
            <div class="py-2 border-b border-dashed border-black">
                <div class="flex justify-between font-bold text-sm">
                    <span>Net Register Flow:</span>
                    <span class="tabular-nums">{{ \App\Support\Money::format($data['net_cash_flow']) }}</span>
                </div>
            </div>

            {{-- Account Balances --}}
            <div class="py-2 border-b border-dashed border-black space-y-1 text-[11px]">
                <p class="font-bold uppercase">Account Balances (EOD)</p>
                @foreach($accounts as $acc)
                    <div class="flex justify-between">
                        <span>{{ $acc['account_name'] }}:</span>
                        <span class="font-bold tabular-nums">{{ \App\Support\Money::format($acc['closing_balance']) }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="text-center pt-3 space-y-1">
                <p class="font-bold">*** REGISTER CLOSED ***</p>
                <p class="text-[9px]">Verified By: ___________________</p>
            </div>
        </div>

        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    @else
        {{-- Full Standard A4 / Screen View --}}
        <div class="space-y-6">
            {{-- Date Bar --}}
            <x-filament::section compact class="print:hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <form method="GET" class="flex gap-3 items-center">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Summary Date:</label>
                        <input type="date" name="date" value="{{ $data['date'] }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100" onchange="this.form.submit()">
                        <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg text-sm">View Date</button>
                    </form>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Register Status: <span class="font-bold text-emerald-600">Active EOD View</span>
                    </div>
                </div>
            </x-filament::section>

            {{-- High Level KPIs --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-filament::section compact>
                    <span class="text-xs font-semibold uppercase text-gray-400">Total Day Inflows</span>
                    <p class="text-3xl font-extrabold mt-2 text-emerald-600 tabular-nums">{{ \App\Support\Money::format($inflows['total_inflows']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">POS Sales: {{ \App\Support\Money::format($inflows['pos_sales_collected']) }} | Due Collections: {{ \App\Support\Money::format($inflows['customer_due_collections']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-semibold uppercase text-gray-400">Total Day Outflows</span>
                    <p class="text-3xl font-extrabold mt-2 text-rose-600 tabular-nums">{{ \App\Support\Money::format($outflows['total_outflows']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Vendors: {{ \App\Support\Money::format($outflows['vendor_payments']) }} | Expenses: {{ \App\Support\Money::format($outflows['operating_expenses']) }}</p>
                </x-filament::section>
                <x-filament::section compact>
                    <span class="text-xs font-semibold uppercase text-gray-400">Net Day Cash Movement</span>
                    <p class="text-3xl font-extrabold mt-2 {{ bccomp($data['net_cash_flow'], '0.00', 2) >= 0 ? 'text-primary-600' : 'text-amber-600' }} tabular-nums">
                        {{ \App\Support\Money::format($data['net_cash_flow']) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Net additions across accounts today</p>
                </x-filament::section>
            </div>

            {{-- Breakdown Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Cashier Performance --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="font-bold text-gray-900 dark:text-gray-100">Cashier Counters</span>
                    </x-slot>
                    <div class="space-y-3">
                        @forelse($cashiers as $c)
                            <div class="flex justify-between items-center text-sm p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                                <div>
                                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ $c['user_name'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $c['sales_count'] }} sales invoices processed</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-primary-600 tabular-nums">{{ \App\Support\Money::format($c['sales_total']) }}</p>
                                    <p class="text-xs text-emerald-600 tabular-nums">Collected: {{ \App\Support\Money::format($c['paid_amount']) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 italic">No sales processed today.</p>
                        @endforelse
                    </div>
                </x-filament::section>

                {{-- Accounts Opening vs Closing --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="font-bold text-gray-900 dark:text-gray-100">Account Reconciliation (EOD)</span>
                    </x-slot>
                    <div class="space-y-3">
                        @foreach($accounts as $acc)
                            <div class="text-sm p-3 bg-gray-50 dark:bg-white/5 rounded-xl space-y-1">
                                <div class="flex justify-between font-semibold">
                                    @php
                                        $lowerName = strtolower($acc['account_name']);
                                        $hasType = str_contains($lowerName, 'cash') || str_contains($lowerName, 'bank') || str_contains($lowerName, 'wallet') || str_contains($lowerName, 'mfs');
                                    @endphp
                                    <span class="text-gray-900 dark:text-gray-100">
                                        {{ $acc['account_name'] }}
                                        @if(! $hasType && !empty($acc['account_kind_label']))
                                            <span class="text-xs text-gray-400">({{ $acc['account_kind_label'] }})</span>
                                        @endif
                                    </span>
                                    <span class="text-primary-600 font-bold tabular-nums">Closing: {{ \App\Support\Money::format($acc['closing_balance']) }}</span>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                    <span>Opening: {{ \App\Support\Money::format($acc['opening_balance']) }}</span>
                                    <span>In: +{{ \App\Support\Money::format($acc['inflow']) }} | Out: -{{ \App\Support\Money::format($acc['outflow']) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            </div>

            {{-- Stock Movement Stats --}}
            <x-filament::section>
                <x-slot name="heading">
                    <span class="font-bold text-gray-900 dark:text-gray-100">Stock Physical Movements Today</span>
                </x-slot>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                        <span class="text-xs text-gray-400">Items Sold</span>
                        <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1 tabular-nums">{{ \App\Support\Money::formatQty($stock['items_sold_qty']) }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                        <span class="text-xs text-gray-400">Items Returned</span>
                        <p class="text-xl font-bold text-amber-600 mt-1 tabular-nums">{{ \App\Support\Money::formatQty($stock['items_returned_qty']) }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                        <span class="text-xs text-gray-400">Items Received (Purchases)</span>
                        <p class="text-xl font-bold text-blue-600 mt-1 tabular-nums">{{ \App\Support\Money::formatQty($stock['items_purchased_qty']) }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                        <span class="text-xs text-gray-400">Damage / Adjustment Loss</span>
                        <p class="text-xl font-bold text-rose-600 mt-1 tabular-nums">{{ \App\Support\Money::formatQty($stock['damage_loss_qty']) }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
