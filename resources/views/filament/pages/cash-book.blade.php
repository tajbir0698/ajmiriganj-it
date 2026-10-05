@php
    use App\Models\Account;
    use App\Models\Transaction;
    use App\Support\Money;
    use Carbon\Carbon;

    $accountService = app(\App\Services\AccountService::class);
    $selectedDate = $date ? Carbon::parse($date) : now();

    $accounts = Account::where('is_active', true)->get();

    $grandOpening = '0.00';
    $grandIn = '0.00';
    $grandOut = '0.00';
    $grandClosing = '0.00';

    $accountBooks = [];

    foreach ($accounts as $acc) {
        $ledger = $accountService->ledger($acc, $selectedDate->copy()->startOfDay(), $selectedDate->copy()->endOfDay());

        $accountBooks[] = [
            'account' => $acc,
            'opening' => $ledger->openingBalance,
            'in' => $ledger->totalIn,
            'out' => $ledger->totalOut,
            'closing' => $ledger->closingBalance,
            'rows' => $ledger->rows,
        ];

        $grandOpening = bcadd($grandOpening, $ledger->openingBalance, 2);
        $grandIn = bcadd($grandIn, $ledger->totalIn, 2);
        $grandOut = bcadd($grandOut, $ledger->totalOut, 2);
        $grandClosing = bcadd($grandClosing, $ledger->closingBalance, 2);
    }
@endphp

<x-filament-panels::page>
    <!-- Date Selector -->
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; background: rgba(156, 163, 175, 0.05); border-radius: 8px; border: 1px solid rgba(156, 163, 175, 0.15);">
        <form method="GET" style="display: flex; align-items: center; gap: 10px;">
            <label style="font-size: 0.85rem; font-weight: 600;">Select Date:</label>
            <input 
                type="date" 
                name="date" 
                value="{{ $selectedDate->toDateString() }}"
                style="padding: 5px 8px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(156, 163, 175, 0.3); background: transparent; color: inherit;"
            >
            <button 
                type="submit"
                style="padding: 5px 14px; font-size: 0.85rem; font-weight: 600; border-radius: 6px; background: #a78bfa; color: #1e1b4b; border: none; cursor: pointer;"
            >
                View Cash Book
            </button>
        </form>

        <div style="font-size: 0.85rem; font-weight: 700; color: #a78bfa;">
            {{ $selectedDate->format('l, d F Y') }}
        </div>
    </div>

    <!-- Daily Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <div style="padding: 14px; border-radius: 8px; border: 1px solid rgba(156, 163, 175, 0.2); background: rgba(156, 163, 175, 0.03);">
            <div style="font-size: 0.72rem; text-transform: uppercase; color: #9ca3af; font-weight: 700;">Day Opening (All Accounts)</div>
            <div style="font-size: 1.25rem; font-weight: 800; margin-top: 4px; font-family: ui-monospace, monospace;">
                {{ Money::format($grandOpening) }}
            </div>
        </div>

        <div style="padding: 14px; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2); background: rgba(16, 185, 129, 0.05);">
            <div style="font-size: 0.72rem; text-transform: uppercase; color: #10b981; font-weight: 700;">Total In Today (+)</div>
            <div style="font-size: 1.25rem; font-weight: 800; margin-top: 4px; color: #10b981; font-family: ui-monospace, monospace;">
                +{{ Money::format($grandIn) }}
            </div>
        </div>

        <div style="padding: 14px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.05);">
            <div style="font-size: 0.72rem; text-transform: uppercase; color: #ef4444; font-weight: 700;">Total Out Today (-)</div>
            <div style="font-size: 1.25rem; font-weight: 800; margin-top: 4px; color: #ef4444; font-family: ui-monospace, monospace;">
                -{{ Money::format($grandOut) }}
            </div>
        </div>

        <div style="padding: 14px; border-radius: 8px; border: 1px solid rgba(167, 139, 250, 0.3); background: rgba(167, 139, 250, 0.05);">
            <div style="font-size: 0.72rem; text-transform: uppercase; color: #a78bfa; font-weight: 700;">Day Closing Balance</div>
            <div style="font-size: 1.25rem; font-weight: 800; margin-top: 4px; color: #a78bfa; font-family: ui-monospace, monospace;">
                {{ Money::format($grandClosing) }}
            </div>
        </div>
    </div>

    <!-- Per-Account Cash Book Tables -->
    @foreach ($accountBooks as $book)
        <div style="border-radius: 10px; border: 1px solid rgba(156, 163, 175, 0.2); overflow: hidden; margin-top: 8px;">
            <div style="padding: 12px 16px; background: rgba(156, 163, 175, 0.05); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px;">
                <div style="font-weight: 700; font-size: 0.95rem;">
                    {{ $book['account']->name }}
                    <span style="font-size: 0.75rem; color: #9ca3af; font-weight: normal; margin-left: 6px;">({{ $book['account']->type->label() }})</span>
                </div>
                <div style="display: flex; gap: 16px; font-size: 0.8rem; font-family: ui-monospace, monospace;">
                    <span>Opening: <strong>{{ Money::format($book['opening']) }}</strong></span>
                    <span style="color: #10b981;">In: <strong>+{{ Money::format($book['in']) }}</strong></span>
                    <span style="color: #ef4444;">Out: <strong>-{{ Money::format($book['out']) }}</strong></span>
                    <span style="color: #a78bfa;">Closing: <strong>{{ Money::format($book['closing']) }}</strong></span>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.2); font-size: 0.72rem; text-transform: uppercase; color: #9ca3af; letter-spacing: 0.05em;">
                            <th style="padding: 8px 14px; width: 120px;">Voucher #</th>
                            <th style="padding: 8px 14px; width: 140px;">Category</th>
                            <th style="padding: 8px 14px;">Description / Party</th>
                            <th style="padding: 8px 14px; text-align: right; width: 110px;">In (৳)</th>
                            <th style="padding: 8px 14px; text-align: right; width: 110px;">Out (৳)</th>
                            <th style="padding: 8px 14px; text-align: right; width: 120px;">Balance (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($book['rows'] as $row)
                            <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.1); {{ $row->isReversed ? 'opacity: 0.6; text-decoration: line-through;' : '' }}">
                                <td style="padding: 8px 14px; font-family: ui-monospace, monospace; font-size: 0.8rem;">
                                    {{ $row->voucherNo ?: "TRX-{$row->id}" }}
                                </td>
                                <td style="padding: 8px 14px; font-weight: 600;">
                                    {{ $row->categoryName ?: 'General' }}
                                </td>
                                <td style="padding: 8px 14px;">
                                    <div>{{ $row->description ?: '—' }}</div>
                                    @if ($row->partyName)
                                        <span style="font-size: 0.72rem; color: #9ca3af;">Party: {{ $row->partyName }}</span>
                                    @endif
                                </td>
                                <td style="padding: 8px 14px; text-align: right; font-family: ui-monospace, monospace; color: {{ bccomp($row->inAmount, '0.00', 2) > 0 ? '#10b981' : '#9ca3af' }}; font-weight: {{ bccomp($row->inAmount, '0.00', 2) > 0 ? '700' : 'normal' }};">
                                    {{ bccomp($row->inAmount, '0.00', 2) > 0 ? Money::format($row->inAmount) : '—' }}
                                </td>
                                <td style="padding: 8px 14px; text-align: right; font-family: ui-monospace, monospace; color: {{ bccomp($row->outAmount, '0.00', 2) > 0 ? '#ef4444' : '#9ca3af' }}; font-weight: {{ bccomp($row->outAmount, '0.00', 2) > 0 ? '700' : 'normal' }};">
                                    {{ bccomp($row->outAmount, '0.00', 2) > 0 ? Money::format($row->outAmount) : '—' }}
                                </td>
                                <td style="padding: 8px 14px; text-align: right; font-family: ui-monospace, monospace; font-weight: 700;">
                                    {{ Money::format($row->runningBalance) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding: 14px; text-align: center; color: #9ca3af; font-style: italic;">
                                    No transaction movements recorded for {{ $book['account']->name }} on this date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</x-filament-panels::page>
