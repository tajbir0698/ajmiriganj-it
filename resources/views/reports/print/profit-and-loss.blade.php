<x-print-layout :title="$title" :periodText="$periodText">
    @php
        $sales = $data['sales_summary'];
        $income = $data['other_income'];
        $expenses = $data['operating_expenses'];
    @endphp

    {{-- 1. Sales & Operating Revenue --}}
    <div style="margin-bottom: 16px;">
        <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 3px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #4f46e5;">1. Sales & Trading Revenue</span>
        </div>
        <table>
            <tbody>
                <tr>
                    <td style="padding-left: 12px; color: #374151;">Gross Product Sales</td>
                    <td class="tabular-nums" style="width: 140px; font-weight: 500;">{{ \App\Support\Money::format($sales['gross_sales']) }}</td>
                </tr>
                <tr>
                    <td style="padding-left: 12px; color: #b45309;">Less: Sales Discounts Allowed</td>
                    <td class="tabular-nums" style="color: #b45309;">- {{ \App\Support\Money::format($sales['discounts']) }}</td>
                </tr>
                <tr style="background: #f9fafb; font-weight: 600;">
                    <td style="padding-left: 12px;">Net Invoiced Sales</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($sales['net_sales']) }}</td>
                </tr>
                <tr>
                    <td style="padding-left: 12px; color: #be123c;">Less: Customer Sales Returns & Refunds</td>
                    <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($sales['sale_returns']) }}</td>
                </tr>
                <tr style="font-weight: 600;">
                    <td style="padding-left: 12px;">Effective Net Sales</td>
                    <td class="tabular-nums">{{ \App\Support\Money::format($sales['effective_sales']) }}</td>
                </tr>
                <tr>
                    <td style="padding-left: 12px; color: #4b5563;">Less: Cost of Goods Sold (FIFO Landed Cost)</td>
                    <td class="tabular-nums" style="color: #4b5563;">- {{ \App\Support\Money::format($sales['cogs']) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background: #eff6ff; font-weight: 700; color: #1e40af; border-top: 1px solid #bfdbfe;">
                    <td style="padding: 8px;">GROSS TRADING PROFIT</td>
                    <td class="tabular-nums" style="padding: 8px;">{{ \App\Support\Money::format($sales['gross_profit']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- 2. Other Operating Income --}}
    @if($income['categories']->count() > 0)
        <div style="margin-bottom: 16px;">
            <div style="border-bottom: 2px solid #059669; padding-bottom: 3px; margin-bottom: 6px;">
                <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #059669;">2. Other Operating Income</span>
            </div>
            <table>
                <tbody>
                    @foreach($income['categories'] as $cat)
                        <tr>
                            <td style="padding-left: 12px; color: #374151;">{{ $cat['category_name'] }}</td>
                            <td class="tabular-nums" style="width: 140px; font-weight: 500;">+ {{ \App\Support\Money::format($cat['net_amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background: #ecfdf5; font-weight: 700; color: #065f46;">
                        <td style="padding: 6px 8px;">Total Other Income</td>
                        <td class="tabular-nums" style="padding: 6px 8px;">+ {{ \App\Support\Money::format($income['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    {{-- 3. Operating Expenses --}}
    <div style="margin-bottom: 16px;">
        <div style="border-bottom: 2px solid #e11d48; padding-bottom: 3px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #e11d48;">3. Operating Expenses</span>
        </div>
        <table>
            <tbody>
                @forelse($expenses['categories'] as $cat)
                    <tr>
                        <td style="padding-left: 12px; color: #374151;">{{ $cat['category_name'] }}</td>
                        <td class="tabular-nums" style="width: 140px; color: #be123c;">{{ \App\Support\Money::format($cat['net_amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" style="padding-left: 12px; color: #9ca3af; font-style: italic;">No operating expenses recorded in period.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background: #fff1f2; font-weight: 700; color: #9f1239;">
                    <td style="padding: 6px 8px;">Total Operating Expenses</td>
                    <td class="tabular-nums" style="padding: 6px 8px;">- {{ \App\Support\Money::format($expenses['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- 4. Stock Losses --}}
    @if(bccomp((string)$data['stock_losses'], '0.00', 2) > 0)
        <div style="margin-bottom: 16px;">
            <div style="border-bottom: 2px solid #ea580c; padding-bottom: 3px; margin-bottom: 6px;">
                <span style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #ea580c;">4. Stock Losses & Shrinkage</span>
            </div>
            <table>
                <tbody>
                    <tr>
                        <td style="padding-left: 12px; color: #374151;">Physical Damage, Expiration & Shrinkage Adjustments</td>
                        <td class="tabular-nums" style="width: 140px; color: #c2410c;">- {{ \App\Support\Money::format($data['stock_losses']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- 5. Net Profit Summary --}}
    <div style="border-top: 2px solid #111827; padding-top: 8px;">
        <table>
            <tr style="background: {{ bccomp($data['net_profit'], '0.00', 2) >= 0 ? '#ecfdf5' : '#fef2f2' }}; font-weight: 800; font-size: 14px; color: {{ bccomp($data['net_profit'], '0.00', 2) >= 0 ? '#065f46' : '#991b1b' }};">
                <td style="padding: 10px 8px;">NET PROFIT / (LOSS)</td>
                <td class="tabular-nums" style="padding: 10px 8px; width: 140px;">{{ \App\Support\Money::format($data['net_profit']) }}</td>
            </tr>
        </table>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; margin-top: 4px; padding: 4px 8px;">
            <span style="color: #6b7280;">Accrual Basis Financial Earnings</span>
            <div>
                @if($data['reconciliation_check'])
                    <span class="badge badge-success">✓ Matches P&L</span>
                @else
                    <span class="badge badge-danger">⚠ Discrepancy Detected</span>
                @endif
            </div>
        </div>
    </div>
</x-print-layout>
