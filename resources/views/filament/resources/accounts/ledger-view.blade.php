@php
    use App\Support\Money;
    use Carbon\Carbon;

    /** @var \App\Models\Account $account */
    $account = $getRecord();
    $accountService = app(\App\Services\AccountService::class);

    $fromDate = request('from') ? Carbon::parse(request('from')) : null;
    $toDate = request('to') ? Carbon::parse(request('to')) : null;

    $ledger = $accountService->ledger($account, $fromDate, $toDate);
@endphp

<div class="space-y-4" style="margin-top: 8px;">
    <!-- Date Range & Print Controls -->
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 12px; background: rgba(156, 163, 175, 0.05); border-radius: 8px; border: 1px solid rgba(156, 163, 175, 0.15);">
        <form method="GET" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
            <label style="font-size: 0.8rem; font-weight: 600;">From:</label>
            <input 
                type="date" 
                name="from" 
                value="{{ request('from') }}"
                style="padding: 4px 8px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(156, 163, 175, 0.3); background: transparent; color: inherit;"
            >

            <label style="font-size: 0.8rem; font-weight: 600;">To:</label>
            <input 
                type="date" 
                name="to" 
                value="{{ request('to') }}"
                style="padding: 4px 8px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(156, 163, 175, 0.3); background: transparent; color: inherit;"
            >

            <button 
                type="submit"
                style="padding: 5px 12px; font-size: 0.85rem; font-weight: 600; border-radius: 6px; background: #a78bfa; color: #1e1b4b; border: none; cursor: pointer;"
            >
                Filter
            </button>

            @if (request('from') || request('to'))
                <a 
                    href="{{ url()->current() }}"
                    style="font-size: 0.85rem; color: #9ca3af; text-decoration: underline; margin-left: 4px;"
                >
                    Clear Filter
                </a>
            @endif
        </form>

        <button 
            type="button" 
            onclick="window.print()"
            style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; font-size: 0.85rem; font-weight: 600; border-radius: 6px; border: 1px solid rgba(156, 163, 175, 0.3); background: transparent; color: inherit; cursor: pointer;"
        >
            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Ledger
        </button>
    </div>

    <!-- Ledger Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <div style="padding: 12px; border-radius: 8px; border: 1px solid rgba(156, 163, 175, 0.15); background: rgba(156, 163, 175, 0.03);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: #9ca3af; font-weight: 600;">Opening Balance</div>
            <div style="font-size: 1.1rem; font-weight: 700; margin-top: 4px; font-family: ui-monospace, monospace;">
                {{ Money::format($ledger->openingBalance) }}
            </div>
            <div style="font-size: 0.72rem; color: #9ca3af; margin-top: 2px;">
                {{ $fromDate ? 'Before ' . $fromDate->format('d M Y') : 'Account Initial' }}
            </div>
        </div>

        <div style="padding: 12px; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2); background: rgba(16, 185, 129, 0.05);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: #10b981; font-weight: 600;">Period Money In (+)</div>
            <div style="font-size: 1.1rem; font-weight: 700; margin-top: 4px; color: #10b981; font-family: ui-monospace, monospace;">
                +{{ Money::format($ledger->totalIn) }}
            </div>
        </div>

        <div style="padding: 12px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.05);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: #ef4444; font-weight: 600;">Period Money Out (-)</div>
            <div style="font-size: 1.1rem; font-weight: 700; margin-top: 4px; color: #ef4444; font-family: ui-monospace, monospace;">
                -{{ Money::format($ledger->totalOut) }}
            </div>
        </div>

        <div style="padding: 12px; border-radius: 8px; border: 1px solid rgba(167, 139, 250, 0.2); background: rgba(167, 139, 250, 0.05);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: #a78bfa; font-weight: 600;">Closing Balance</div>
            <div style="font-size: 1.1rem; font-weight: 700; margin-top: 4px; color: #a78bfa; font-family: ui-monospace, monospace;">
                {{ Money::format($ledger->closingBalance) }}
            </div>
            <div style="font-size: 0.72rem; color: #9ca3af; margin-top: 2px;">
                {{ $toDate ? 'As of ' . $toDate->format('d M Y') : 'Current' }}
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div style="overflow-x: auto; border-radius: 8px; border: 1px solid rgba(156, 163, 175, 0.2);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.2); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; background: rgba(156, 163, 175, 0.05);">
                    <th style="padding: 10px 12px; width: 105px;">Date</th>
                    <th style="padding: 10px 12px; width: 120px;">Voucher #</th>
                    <th style="padding: 10px 12px; width: 140px;">Category</th>
                    <th style="padding: 10px 12px;">Description / Party</th>
                    <th style="padding: 10px 12px; text-align: right; width: 120px;">In (৳)</th>
                    <th style="padding: 10px 12px; text-align: right; width: 120px;">Out (৳)</th>
                    <th style="padding: 10px 12px; text-align: right; width: 130px;">Balance (৳)</th>
                </tr>
            </thead>
            <tbody>
                <!-- Opening Balance Row -->
                <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.15); font-style: italic; background: rgba(156, 163, 175, 0.02);">
                    <td style="padding: 9px 12px; color: #9ca3af;">{{ $fromDate ? $fromDate->format('d M Y') : 'Initial' }}</td>
                    <td style="padding: 9px 12px; color: #9ca3af;">—</td>
                    <td style="padding: 9px 12px; font-weight: 600;">Opening Balance</td>
                    <td style="padding: 9px 12px; color: #9ca3af;">Brought forward</td>
                    <td style="padding: 9px 12px; text-align: right;">—</td>
                    <td style="padding: 9px 12px; text-align: right;">—</td>
                    <td style="padding: 9px 12px; text-align: right; font-weight: 700; font-family: ui-monospace, monospace;">
                        {{ Money::format($ledger->openingBalance) }}
                    </td>
                </tr>

                @forelse ($ledger->rows as $row)
                    <tr style="border-bottom: 1px solid rgba(156, 163, 175, 0.1); {{ $row->isReversed ? 'opacity: 0.6; text-decoration: line-through;' : '' }}">
                        <td style="padding: 9px 12px; white-space: nowrap;">
                            {{ date('d M Y', strtotime($row->date)) }}
                        </td>
                        <td style="padding: 9px 12px; font-family: ui-monospace, monospace; font-size: 0.8rem;">
                            {{ $row->voucherNo ?: '—' }}
                        </td>
                        <td style="padding: 9px 12px;">
                            <span style="font-weight: 600;">{{ $row->categoryName ?: 'General' }}</span>
                            @if ($row->isReversed)
                                <span style="font-size: 0.68rem; color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 1px 4px; border-radius: 3px; margin-left: 4px;">REVERSED</span>
                            @elseif ($row->isReversal)
                                <span style="font-size: 0.68rem; color: #f59e0b; background: rgba(245, 158, 11, 0.1); padding: 1px 4px; border-radius: 3px; margin-left: 4px;">REVERSAL</span>
                            @endif
                        </td>
                        <td style="padding: 9px 12px;">
                            <div>{{ $row->description ?: '—' }}</div>
                            @if ($row->partyName)
                                <div style="font-size: 0.75rem; color: #9ca3af;">Party: {{ $row->partyName }}</div>
                            @endif
                            @if ($row->referenceTitle)
                                <div style="font-size: 0.72rem; color: #a78bfa;">Ref: {{ $row->referenceTitle }}</div>
                            @endif
                        </td>
                        <td style="padding: 9px 12px; text-align: right; font-family: ui-monospace, monospace; color: {{ bccomp($row->inAmount, '0.00', 2) > 0 ? '#10b981' : '#9ca3af' }}; font-weight: {{ bccomp($row->inAmount, '0.00', 2) > 0 ? '600' : 'normal' }};">
                            {{ bccomp($row->inAmount, '0.00', 2) > 0 ? Money::format($row->inAmount) : '—' }}
                        </td>
                        <td style="padding: 9px 12px; text-align: right; font-family: ui-monospace, monospace; color: {{ bccomp($row->outAmount, '0.00', 2) > 0 ? '#ef4444' : '#9ca3af' }}; font-weight: {{ bccomp($row->outAmount, '0.00', 2) > 0 ? '600' : 'normal' }};">
                            {{ bccomp($row->outAmount, '0.00', 2) > 0 ? Money::format($row->outAmount) : '—' }}
                        </td>
                        <td style="padding: 9px 12px; text-align: right; font-family: ui-monospace, monospace; font-weight: 700;">
                            {{ Money::format($row->runningBalance) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 20px; text-align: center; color: #9ca3af;">
                            No transactions recorded for this period.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="border-top: 2px solid rgba(156, 163, 175, 0.3); font-weight: 700; background: rgba(156, 163, 175, 0.04);">
                    <td colspan="4" style="padding: 10px 12px;">
                        Period Totals ({{ $ledger->rows->count() }} {{ \Illuminate\Support\Str::plural('entry', $ledger->rows->count()) }})
                    </td>
                    <td style="padding: 10px 12px; text-align: right; font-family: ui-monospace, monospace; color: #10b981;">
                        +{{ Money::format($ledger->totalIn) }}
                    </td>
                    <td style="padding: 10px 12px; text-align: right; font-family: ui-monospace, monospace; color: #ef4444;">
                        -{{ Money::format($ledger->totalOut) }}
                    </td>
                    <td style="padding: 10px 12px; text-align: right; font-family: ui-monospace, monospace; color: #a78bfa;">
                        {{ Money::format($ledger->closingBalance) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
