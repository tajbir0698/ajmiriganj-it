@php
    use App\Support\Money;
    use Carbon\Carbon;
    use App\Models\Transaction;

    $financeService = app(\App\Services\BusinessFinanceService::class);

    $fromDate = $from ? Carbon::parse($from) : null;
    $toDate = $to ? Carbon::parse($to) : null;

    $investments = $financeService->ownerInvestment($fromDate, $toDate);
    $drawings = $financeService->ownerDrawings($fromDate, $toDate);
    $netProfit = $financeService->netProfit($fromDate, $toDate);
    $withdrawals = $financeService->profitWithdrawals($fromDate, $toDate);
    $retainedProfit = $financeService->retainedProfit();
    $ownerCapital = $financeService->ownerCapital();
    $availableProfit = $financeService->availableProfitToWithdraw();

    // Query entries
    $investmentQuery = Transaction::query()
        ->join('account_categories', 'transactions.category_id', '=', 'account_categories.id')
        ->where('account_categories.name', 'Owner Investment')
        ->with('account')
        ->orderBy('date', 'desc');

    $drawingsQuery = Transaction::query()
        ->join('account_categories', 'transactions.category_id', '=', 'account_categories.id')
        ->where('account_categories.name', 'Owner Drawing')
        ->with('account')
        ->orderBy('date', 'desc');

    $withdrawalsQuery = Transaction::query()
        ->join('account_categories', 'transactions.category_id', '=', 'account_categories.id')
        ->where('account_categories.name', 'Profit Withdrawal')
        ->with('account')
        ->orderBy('date', 'desc');

    if ($fromDate) {
        $investmentQuery->whereDate('transactions.date', '>=', $fromDate->toDateString());
        $drawingsQuery->whereDate('transactions.date', '>=', $fromDate->toDateString());
        $withdrawalsQuery->whereDate('transactions.date', '>=', $fromDate->toDateString());
    }
    if ($toDate) {
        $investmentQuery->whereDate('transactions.date', '<=', $toDate->toDateString());
        $drawingsQuery->whereDate('transactions.date', '<=', $toDate->toDateString());
        $withdrawalsQuery->whereDate('transactions.date', '<=', $toDate->toDateString());
    }

    $investmentRows = $investmentQuery->get(['transactions.*']);
    $drawingsRows = $drawingsQuery->get(['transactions.*']);
    $withdrawalsRows = $withdrawalsQuery->get(['transactions.*']);
@endphp

<x-filament-panels::page>
    <!-- Date Range Filter -->
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; background: rgba(156, 163, 175, 0.05); border-radius: 8px; border: 1px solid rgba(156, 163, 175, 0.15);">
        <form method="GET" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
            <label style="font-size: 0.85rem; font-weight: 600;">Date Range:</label>
            <input 
                type="date" 
                name="from" 
                value="{{ $from }}"
                style="padding: 5px 8px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(156, 163, 175, 0.3); background: transparent; color: inherit;"
            >
            <span style="color: #9ca3af;">to</span>
            <input 
                type="date" 
                name="to" 
                value="{{ $to }}"
                style="padding: 5px 8px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(156, 163, 175, 0.3); background: transparent; color: inherit;"
            >
            <button 
                type="submit"
                style="padding: 5px 14px; font-size: 0.85rem; font-weight: 600; border-radius: 6px; background: #a78bfa; color: #1e1b4b; border: none; cursor: pointer;"
            >
                Filter
            </button>
            @if ($from || $to)
                <a href="{{ url()->current() }}" style="font-size: 0.85rem; color: #9ca3af; text-decoration: underline;">
                    Clear Filter
                </a>
            @endif
        </form>

        <div style="font-size: 0.8rem; color: #9ca3af;">
            Calculated on an <strong style="color: inherit;">accrual basis</strong> (sales counted when finalized).
        </div>
    </div>

    <!-- Summary Metrics Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
        <div style="padding: 16px; border-radius: 10px; border: 1px solid rgba(16, 185, 129, 0.2); background: rgba(16, 185, 129, 0.04);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: #10b981; font-weight: 700; letter-spacing: 0.05em;">Total Owner Investment (+)</div>
            <div style="font-size: 1.35rem; font-weight: 800; margin-top: 6px; color: #10b981; font-family: ui-monospace, monospace;">
                +{{ Money::format($investments) }}
            </div>
            <div style="font-size: 0.72rem; color: #9ca3af; margin-top: 4px;">Capital injected into business</div>
        </div>

        <div style="padding: 16px; border-radius: 10px; border: 1px solid rgba(245, 158, 11, 0.2); background: rgba(245, 158, 11, 0.04);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: #f59e0b; font-weight: 700; letter-spacing: 0.05em;">Total Owner Drawings (-)</div>
            <div style="font-size: 1.35rem; font-weight: 800; margin-top: 6px; color: #f59e0b; font-family: ui-monospace, monospace;">
                -{{ Money::format($drawings) }}
            </div>
            <div style="font-size: 0.72rem; color: #9ca3af; margin-top: 4px;">Personal capital withdrawn</div>
        </div>

        <div style="padding: 16px; border-radius: 10px; border: 1px solid rgba(59, 130, 246, 0.2); background: rgba(59, 130, 246, 0.04);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: #3b82f6; font-weight: 700; letter-spacing: 0.05em;">Net Business Profit</div>
            <div style="font-size: 1.35rem; font-weight: 800; margin-top: 6px; color: {{ bccomp($netProfit, '0.00', 2) >= 0 ? '#10b981' : '#ef4444' }}; font-family: ui-monospace, monospace;">
                {{ Money::format($netProfit) }}
            </div>
            <div style="font-size: 0.72rem; color: #9ca3af; margin-top: 4px;">Gross profit + income - expenses - losses</div>
        </div>

        <div style="padding: 16px; border-radius: 10px; border: 1px solid rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.04);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: #ef4444; font-weight: 700; letter-spacing: 0.05em;">Profit Withdrawals (-)</div>
            <div style="font-size: 1.35rem; font-weight: 800; margin-top: 6px; color: #ef4444; font-family: ui-monospace, monospace;">
                -{{ Money::format($withdrawals) }}
            </div>
            <div style="font-size: 0.72rem; color: #9ca3af; margin-top: 4px;">Dividends / profit taken</div>
        </div>

        <div style="padding: 16px; border-radius: 10px; border: 1px solid rgba(167, 139, 250, 0.3); background: rgba(167, 139, 250, 0.06); grid-column: span 2 / span 2;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.8rem; text-transform: uppercase; color: #a78bfa; font-weight: 800; letter-spacing: 0.05em;">TOTAL OWNER CAPITAL (EQUITY)</span>
                <span style="font-size: 0.75rem; color: #9ca3af;">All-time Retained Profit: <strong>{{ Money::format($retainedProfit) }}</strong></span>
            </div>
            <div style="font-size: 1.75rem; font-weight: 900; margin-top: 6px; color: #a78bfa; font-family: ui-monospace, monospace;">
                {{ Money::format($ownerCapital) }}
            </div>
            <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 4px;">
                Formula: Investment ({{ Money::format($investments) }}) - Drawings ({{ Money::format($drawings) }}) + Retained Profit ({{ Money::format($retainedProfit) }})
            </div>
        </div>
    </div>

    <!-- Section 1: Owner Investments Table -->
    <div style="border-radius: 10px; border: 1px solid rgba(156, 163, 175, 0.2); overflow: hidden;">
        <div style="padding: 12px 16px; background: rgba(156, 163, 175, 0.05); font-weight: 700; font-size: 0.9rem; display: flex; justify-content: space-between;">
            <span>1. Owner Capital Injections / Investments ({{ $investmentRows->count() }})</span>
            <span style="color: #10b981; font-family: ui-monospace, monospace;">Total: {{ Money::format($investments) }}</span>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.2); font-size: 0.75rem; text-transform: uppercase; color: #9ca3af;">
                        <th style="padding: 8px 14px;">Date</th>
                        <th style="padding: 8px 14px;">Voucher #</th>
                        <th style="padding: 8px 14px;">Deposited To</th>
                        <th style="padding: 8px 14px;">Description / Memo</th>
                        <th style="padding: 8px 14px; text-align: right;">Amount (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($investmentRows as $row)
                        <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.1);">
                            <td style="padding: 8px 14px;">{{ $row->date->format('d M Y') }}</td>
                            <td style="padding: 8px 14px; font-family: ui-monospace, monospace;">{{ $row->voucher_no ?: "TRX-{$row->id}" }}</td>
                            <td style="padding: 8px 14px; font-weight: 600;">{{ $row->account?->name }}</td>
                            <td style="padding: 8px 14px; color: #9ca3af;">{{ $row->description ?: '—' }}</td>
                            <td style="padding: 8px 14px; text-align: right; font-weight: 700; color: #10b981; font-family: ui-monospace, monospace;">
                                +{{ Money::format((string) $row->amount) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding: 16px; text-align: center; color: #9ca3af;">No owner investments recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Owner Drawings Table -->
    <div style="border-radius: 10px; border: 1px solid rgba(156, 163, 175, 0.2); overflow: hidden;">
        <div style="padding: 12px 16px; background: rgba(156, 163, 175, 0.05); font-weight: 700; font-size: 0.9rem; display: flex; justify-content: space-between;">
            <span>2. Owner Drawings (Personal Withdrawals) ({{ $drawingsRows->count() }})</span>
            <span style="color: #f59e0b; font-family: ui-monospace, monospace;">Total: {{ Money::format($drawings) }}</span>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.2); font-size: 0.75rem; text-transform: uppercase; color: #9ca3af;">
                        <th style="padding: 8px 14px;">Date</th>
                        <th style="padding: 8px 14px;">Voucher #</th>
                        <th style="padding: 8px 14px;">Withdrawn From</th>
                        <th style="padding: 8px 14px;">Purpose / Memo</th>
                        <th style="padding: 8px 14px; text-align: right;">Amount (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($drawingsRows as $row)
                        <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.1);">
                            <td style="padding: 8px 14px;">{{ $row->date->format('d M Y') }}</td>
                            <td style="padding: 8px 14px; font-family: ui-monospace, monospace;">{{ $row->voucher_no ?: "TRX-{$row->id}" }}</td>
                            <td style="padding: 8px 14px; font-weight: 600;">{{ $row->account?->name }}</td>
                            <td style="padding: 8px 14px; color: #9ca3af;">{{ $row->description ?: '—' }}</td>
                            <td style="padding: 8px 14px; text-align: right; font-weight: 700; color: #f59e0b; font-family: ui-monospace, monospace;">
                                -{{ Money::format((string) $row->amount) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding: 16px; text-align: center; color: #9ca3af;">No owner personal drawings recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 3: Profit Withdrawals Table -->
    <div style="border-radius: 10px; border: 1px solid rgba(156, 163, 175, 0.2); overflow: hidden;">
        <div style="padding: 12px 16px; background: rgba(156, 163, 175, 0.05); font-weight: 700; font-size: 0.9rem; display: flex; justify-content: space-between;">
            <span>3. Profit Withdrawals (Dividends) ({{ $withdrawalsRows->count() }})</span>
            <span style="color: #ef4444; font-family: ui-monospace, monospace;">Total: {{ Money::format($withdrawals) }}</span>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.2); font-size: 0.75rem; text-transform: uppercase; color: #9ca3af;">
                        <th style="padding: 8px 14px;">Date</th>
                        <th style="padding: 8px 14px;">Voucher #</th>
                        <th style="padding: 8px 14px;">Withdrawn From</th>
                        <th style="padding: 8px 14px;">Note</th>
                        <th style="padding: 8px 14px; text-align: right;">Amount (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($withdrawalsRows as $row)
                        <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.1);">
                            <td style="padding: 8px 14px;">{{ $row->date->format('d M Y') }}</td>
                            <td style="padding: 8px 14px; font-family: ui-monospace, monospace;">{{ $row->voucher_no ?: "TRX-{$row->id}" }}</td>
                            <td style="padding: 8px 14px; font-weight: 600;">{{ $row->account?->name }}</td>
                            <td style="padding: 8px 14px; color: #9ca3af;">{{ $row->description ?: '—' }}</td>
                            <td style="padding: 8px 14px; text-align: right; font-weight: 700; color: #ef4444; font-family: ui-monospace, monospace;">
                                -{{ Money::format((string) $row->amount) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding: 16px; text-align: center; color: #9ca3af;">No profit withdrawals recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Financial Formula Guide -->
    <div style="padding: 16px; border-radius: 8px; border: 1px dashed rgba(156, 163, 175, 0.3); background: rgba(156, 163, 175, 0.03); font-size: 0.8rem; line-height: 1.6; color: #9ca3af;">
        <strong style="color: inherit;">Financial Principles & Formula Guide:</strong>
        <ul style="list-style: disc; margin-left: 20px; margin-top: 4px;">
            <li><strong>Accrual Profit:</strong> A sale counts toward Gross Profit when completed, even if customer balance is due.</li>
            <li><strong>Inventory Neutrality:</strong> Vendor payments do not affect net profit because inventory cost is already accounted for through FIFO cost of goods sold.</li>
            <li><strong>Net Profit Calculation:</strong> Gross Profit + Other Operating Income - Operating Expenses (Rent, Salary, etc.) - Stock Losses (Damage/Write-offs).</li>
            <li><strong>Owner Capital (Equity):</strong> Total Owner Investment - Total Owner Drawings + (Net Profit - Profit Withdrawals).</li>
        </ul>
    </div>
</x-filament-panels::page>
