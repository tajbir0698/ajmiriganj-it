<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BalanceSheetReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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

        // 1. ASSETS
        $rows->push(['Section' => '1. ASSETS', 'Amount (৳)' => '']);
        foreach ($this->reportData['assets']['cash_and_bank']['accounts'] ?? [] as $acc) {
            $rows->push(['Section' => '   Cash/Bank: '.$acc['name'], 'Amount (৳)' => $acc['balance']]);
        }
        $rows->push(['Section' => '   Total Cash & Bank', 'Amount (৳)' => $this->reportData['assets']['cash_and_bank']['total'] ?? '0.00']);
        $rows->push(['Section' => '   Accounts Receivable (Customer Dues)', 'Amount (৳)' => $this->reportData['assets']['accounts_receivable'] ?? '0.00']);
        $rows->push(['Section' => '   Vendor Advances / Overpayments', 'Amount (৳)' => $this->reportData['assets']['vendor_advances'] ?? '0.00']);
        $rows->push(['Section' => '   Inventory (FIFO Valuation)', 'Amount (৳)' => $this->reportData['assets']['inventory'] ?? '0.00']);
        $rows->push(['Section' => 'TOTAL ASSETS', 'Amount (৳)' => $this->reportData['assets']['total_assets'] ?? '0.00']);
        $rows->push(['Section' => '', 'Amount (৳)' => '']);

        // 2. LIABILITIES
        $rows->push(['Section' => '2. LIABILITIES', 'Amount (৳)' => '']);
        $rows->push(['Section' => '   Accounts Payable (Vendor Dues)', 'Amount (৳)' => $this->reportData['liabilities']['accounts_payable'] ?? '0.00']);
        $rows->push(['Section' => '   Advances from Customers', 'Amount (৳)' => $this->reportData['liabilities']['customer_advances'] ?? '0.00']);
        $rows->push(['Section' => 'TOTAL LIABILITIES', 'Amount (৳)' => $this->reportData['liabilities']['total_liabilities'] ?? '0.00']);
        $rows->push(['Section' => '', 'Amount (৳)' => '']);

        // 3. EQUITY
        $eq = $this->reportData['equity'] ?? [];
        $rows->push(['Section' => "3. OWNER'S EQUITY", 'Amount (৳)' => '']);
        $rows->push(['Section' => '   Owner Capital Contributions', 'Amount (৳)' => $eq['owner_investment'] ?? '0.00']);
        $rows->push(['Section' => '   Less: Owner Drawings', 'Amount (৳)' => '-'.($eq['owner_drawings'] ?? '0.00')]);
        $rows->push(['Section' => '   Retained Earnings (Net Profit - Withdrawals)', 'Amount (৳)' => $eq['retained_profit'] ?? '0.00']);
        $rows->push(['Section' => '   Opening Cash & Bank Balances', 'Amount (৳)' => $eq['opening_account_balances'] ?? '0.00']);
        $rows->push(['Section' => '   Opening Stock Batches (Initial Inventory)', 'Amount (৳)' => $eq['opening_stock_batches'] ?? '0.00']);
        $rows->push(['Section' => '   Opening Customer Receivables', 'Amount (৳)' => $eq['opening_customer_balances'] ?? '0.00']);
        $rows->push(['Section' => '   Less: Opening Vendor Payables', 'Amount (৳)' => '-'.($eq['opening_vendor_balances'] ?? '0.00')]);
        $rows->push(['Section' => '   Positive Stock Adjustments Gain', 'Amount (৳)' => $eq['positive_stock_adjustments'] ?? '0.00']);
        $rows->push(['Section' => 'TOTAL EQUITY', 'Amount (৳)' => $eq['total_equity'] ?? '0.00']);
        $rows->push(['Section' => '', 'Amount (৳)' => '']);

        $rows->push(['Section' => 'TOTAL LIABILITIES & EQUITY', 'Amount (৳)' => $this->reportData['total_liabilities_and_equity'] ?? '0.00']);
        $rows->push(['Section' => 'DIFFERENCE (Assets - Liab&Equity)', 'Amount (৳)' => $this->reportData['difference'] ?? '0.00']);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Balance Sheet Account / Item',
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
            $row['Amount (৳)'],
        ];
    }
}
