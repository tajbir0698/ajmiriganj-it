<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DailySummaryReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(
        protected array $reportData
    ) {}

    public function collection(): Collection
    {
        $rows = collect();

        $rows->push(['Section' => 'DAILY FINANCIAL SUMMARY (EOD)', 'Detail' => $this->reportData['date'] ?? '', 'Amount (৳)' => '']);
        $rows->push(['Section' => '', 'Detail' => '', 'Amount (৳)' => '']);

        // Inflows
        $in = $this->reportData['inflows'] ?? [];
        $rows->push(['Section' => '1. CASH INFLOWS', 'Detail' => '', 'Amount (৳)' => '']);
        $rows->push(['Section' => '   POS Sales Collected', 'Detail' => 'Direct counter sales', 'Amount (৳)' => $in['pos_sales_collected'] ?? '0.00']);
        $rows->push(['Section' => '   Customer Due Collections', 'Detail' => 'Prior sales collection', 'Amount (৳)' => $in['customer_due_collected'] ?? '0.00']);
        $rows->push(['Section' => '   Direct / Other Income', 'Detail' => 'Consulting & miscellaneous', 'Amount (৳)' => $in['other_income'] ?? '0.00']);
        $rows->push(['Section' => 'TOTAL INFLOWS', 'Detail' => '', 'Amount (৳)' => $in['total_inflows'] ?? '0.00']);
        $rows->push(['Section' => '', 'Detail' => '', 'Amount (৳)' => '']);

        // Outflows
        $out = $this->reportData['outflows'] ?? [];
        $rows->push(['Section' => '2. CASH OUTFLOWS', 'Detail' => '', 'Amount (৳)' => '']);
        $rows->push(['Section' => '   Vendor Purchases Paid', 'Detail' => 'Direct purchase payouts', 'Amount (৳)' => $out['vendor_purchases_paid'] ?? '0.00']);
        $rows->push(['Section' => '   Vendor Due Paid', 'Detail' => 'Prior bill settlements', 'Amount (৳)' => $out['vendor_due_paid'] ?? '0.00']);
        $rows->push(['Section' => '   Operating Expenses', 'Detail' => 'Shop rent, electricity, bills', 'Amount (৳)' => $out['operating_expenses'] ?? '0.00']);
        $rows->push(['Section' => '   Owner Drawings', 'Detail' => 'Capital withdrawal', 'Amount (৳)' => $out['owner_drawings'] ?? '0.00']);
        $rows->push(['Section' => '   Sales Returns Cash Refunded', 'Detail' => 'Counter refunds', 'Amount (৳)' => $out['sales_refunds'] ?? '0.00']);
        $rows->push(['Section' => 'TOTAL OUTFLOWS', 'Detail' => '', 'Amount (৳)' => $out['total_outflows'] ?? '0.00']);
        $rows->push(['Section' => '', 'Detail' => '', 'Amount (৳)' => '']);

        $rows->push(['Section' => 'NET CASH FLOW FOR DAY', 'Detail' => '', 'Amount (৳)' => $this->reportData['net_cash_flow'] ?? '0.00']);
        $rows->push(['Section' => '', 'Detail' => '', 'Amount (৳)' => '']);

        // Accounts summary
        $rows->push(['Section' => '3. ACCOUNTS CLOSING SUMMARY', 'Detail' => 'Opening / In / Out / Closing', 'Amount (৳)' => '']);
        foreach ($this->reportData['accounts_summary'] ?? [] as $acc) {
            $desc = sprintf('Open: ৳%s | In: +৳%s | Out: -৳%s', $acc['opening'], $acc['in'], $acc['out']);
            $rows->push(['Section' => '   '.$acc['account_name'], 'Detail' => $desc, 'Amount (৳)' => $acc['closing']]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Summary Metric / Account',
            'Details / Breakdown',
            'Amount (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['Section'],
            $row['Detail'],
            $row['Amount (৳)'],
        ];
    }
}
