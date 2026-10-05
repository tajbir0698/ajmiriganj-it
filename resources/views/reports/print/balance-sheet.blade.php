<x-print-layout :title="$title" :periodText="'As of ' . $data['as_of_date']">
    @php
        $assets = $data['assets'];
        $liabilities = $data['liabilities'];
        $equity = $data['equity'];
    @endphp

    <style>
        .report-paper table {
            margin-bottom: 4px !important;
        }
        .report-paper th, .report-paper td {
            padding: 3px 6px !important;
        }
    </style>

    <div style="margin-bottom: 8px;">
        {{-- 1. ASSETS --}}
        <div style="margin-bottom: 10px;">
            <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 2px; margin-bottom: 4px;">
                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #4f46e5;">1. Assets</span>
            </div>

            <div style="margin-bottom: 4px;">
                <div style="font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 2px;">Current Liquid Assets (Cash & Bank)</div>
                <table style="margin-bottom: 2px;">
                    <tbody>
                        @foreach($assets['cash_and_bank']['accounts'] as $acc)
                            @php
                                $lowerName = strtolower($acc['name']);
                                $hasType = str_contains($lowerName, 'cash') || str_contains($lowerName, 'bank') || str_contains($lowerName, 'wallet') || str_contains($lowerName, 'mfs');
                            @endphp
                            <tr>
                                <td style="padding-left: 10px; color: #374151;">
                                    {{ $acc['name'] }}
                                    @if(! $hasType && !empty($acc['type_label']))
                                        <span style="font-size: 10px; color: #6b7280;">({{ $acc['type_label'] }})</span>
                                    @endif
                                </td>
                                <td class="tabular-nums" style="width: 140px; font-weight: 500;">{{ \App\Support\Money::format($acc['balance']) }}</td>
                            </tr>
                        @endforeach
                        <tr style="background: #f9fafb; font-weight: 600;">
                            <td style="padding-left: 10px;">Total Cash & Bank</td>
                            <td class="tabular-nums">{{ \App\Support\Money::format($assets['cash_and_bank']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <div style="font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 2px;">Operating Assets & Inventory</div>
                <table style="margin-bottom: 2px;">
                    <tbody>
                        <tr>
                            <td style="padding-left: 10px; color: #374151;">Accounts Receivable (Customer Dues)</td>
                            <td class="tabular-nums" style="width: 140px; font-weight: 600; color: #b45309;">{{ \App\Support\Money::format($assets['accounts_receivable']) }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 10px; color: #374151;">Merchandise Inventory (FIFO Valuation)</td>
                            <td class="tabular-nums" style="font-weight: 600; color: #1d4ed8;">{{ \App\Support\Money::format($assets['inventory']) }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 10px; color: #374151;">Advances to Vendors (Unallocated Payments)</td>
                            <td class="tabular-nums" style="font-weight: 500;">{{ \App\Support\Money::format($assets['vendor_advances']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <table style="margin-top: 2px; margin-bottom: 0;">
                <tfoot>
                    <tr style="background: #eff6ff; font-weight: 700; font-size: 11px; color: #1e40af; border-top: 1px solid #bfdbfe;">
                        <td style="padding: 5px 8px;">TOTAL ASSETS</td>
                        <td class="tabular-nums" style="padding: 5px 8px; width: 140px;">{{ \App\Support\Money::format($assets['total_assets']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- 2. LIABILITIES --}}
        <div style="margin-bottom: 10px;">
            <div style="border-bottom: 2px solid #e11d48; padding-bottom: 2px; margin-bottom: 4px;">
                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #e11d48;">2. Liabilities</span>
            </div>

            <table style="margin-bottom: 2px;">
                <tbody>
                    <tr>
                        <td style="padding-left: 10px; color: #374151;">Accounts Payable (Vendor Dues)</td>
                        <td class="tabular-nums" style="width: 140px; font-weight: 600; color: #be123c;">{{ \App\Support\Money::format($liabilities['accounts_payable']) }}</td>
                    </tr>
                    <tr>
                        <td style="padding-left: 10px; color: #374151;">Advances from Customers (Unallocated Receipts)</td>
                        <td class="tabular-nums" style="font-weight: 500;">{{ \App\Support\Money::format($liabilities['customer_advances']) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr style="background: #fff1f2; font-weight: 700; font-size: 11px; color: #9f1239; border-top: 1px solid #fecdd3;">
                        <td style="padding: 5px 8px;">TOTAL LIABILITIES</td>
                        <td class="tabular-nums" style="padding: 5px 8px;">{{ \App\Support\Money::format($liabilities['total_liabilities']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- 3. EQUITY --}}
        <div style="margin-bottom: 10px;">
            <div style="border-bottom: 2px solid #059669; padding-bottom: 2px; margin-bottom: 4px;">
                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #059669;">3. Owner's Equity</span>
            </div>

            <table style="margin-bottom: 2px;">
                <tbody>
                    <tr>
                        <td style="padding-left: 10px; color: #374151;">Owner Capital Contributions</td>
                        <td class="tabular-nums" style="width: 140px; font-weight: 500;">{{ \App\Support\Money::format($equity['owner_investment']) }}</td>
                    </tr>
                    <tr>
                        <td style="padding-left: 10px; color: #be123c;">Less: Owner Drawings</td>
                        <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($equity['owner_drawings']) }}</td>
                    </tr>
                    <tr>
                        <td style="padding-left: 10px; color: #047857;">Retained Earnings (Net Profit - Withdrawals)</td>
                        <td class="tabular-nums" style="color: #047857; font-weight: 600;">+ {{ \App\Support\Money::format($equity['retained_profit']) }}</td>
                    </tr>

                    @if(bccomp($equity['opening_account_balances'], '0.00', 2) > 0)
                        <tr>
                            <td style="padding-left: 10px; color: #374151;">Opening Account Balances (Initial Cash & Bank)</td>
                            <td class="tabular-nums">+ {{ \App\Support\Money::format($equity['opening_account_balances']) }}</td>
                        </tr>
                    @endif

                    @if(bccomp($equity['opening_stock_batches'], '0.00', 2) > 0)
                        <tr>
                            <td style="padding-left: 10px; color: #374151;">Opening Stock Batches (Initial Inventory)</td>
                            <td class="tabular-nums">+ {{ \App\Support\Money::format($equity['opening_stock_batches']) }}</td>
                        </tr>
                    @endif

                    @if(bccomp($equity['opening_customer_balances'], '0.00', 2) > 0)
                        <tr>
                            <td style="padding-left: 10px; color: #374151;">Opening Customer Receivables</td>
                            <td class="tabular-nums">+ {{ \App\Support\Money::format($equity['opening_customer_balances']) }}</td>
                        </tr>
                    @endif

                    @if(bccomp($equity['opening_vendor_balances'], '0.00', 2) > 0)
                        <tr>
                            <td style="padding-left: 10px; color: #be123c;">Less: Opening Vendor Payables</td>
                            <td class="tabular-nums" style="color: #be123c;">- {{ \App\Support\Money::format($equity['opening_vendor_balances']) }}</td>
                        </tr>
                    @endif

                    @if(bccomp($equity['positive_stock_adjustments'], '0.00', 2) > 0)
                        <tr>
                            <td style="padding-left: 10px; color: #047857;">Positive Stock Adjustments Gain</td>
                            <td class="tabular-nums" style="color: #047857;">+ {{ \App\Support\Money::format($equity['positive_stock_adjustments']) }}</td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr style="background: #ecfdf5; font-weight: 700; font-size: 11px; color: #065f46; border-top: 1px solid #a7f3d0;">
                        <td style="padding: 5px 8px;">TOTAL EQUITY</td>
                        <td class="tabular-nums" style="padding: 5px 8px;">{{ \App\Support\Money::format($equity['total_equity']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- 4. TOTAL LIABILITIES & EQUITY RECONCILIATION --}}
        <div style="border-top: 2px solid #111827; padding-top: 6px;">
            <table style="margin-bottom: 2px;">
                <tr style="background: #f3f4f6; font-weight: 800; font-size: 12px; color: #111827;">
                    <td style="padding: 6px 8px;">TOTAL LIABILITIES & EQUITY</td>
                    <td class="tabular-nums" style="padding: 6px 8px; width: 140px;">{{ \App\Support\Money::format($data['total_liabilities_and_equity']) }}</td>
                </tr>
            </table>

            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10px; margin-top: 2px; padding: 2px 8px;">
                <span style="color: #6b7280;">Assets = Liabilities + Equity</span>
                <div>
                    @if($data['is_balanced'])
                        <span class="badge badge-success">✓ Matches (Difference ৳0.00)</span>
                    @else
                        <span class="badge badge-danger">⚠ Difference ৳{{ $data['difference'] }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-print-layout>
