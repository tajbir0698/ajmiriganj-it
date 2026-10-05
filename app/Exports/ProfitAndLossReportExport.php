<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProfitAndLossReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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

        $rows->push(['Line Item' => '1. TRADING REVENUE & INFLOWS', 'Amount (৳)' => '']);
        $rows->push(['Line Item' => '   Sales Invoiced Total', 'Amount (৳)' => $this->reportData['sales_revenue'] ?? '0.00']);
        $rows->push(['Line Item' => '   Less: Sales Returns & Refunds', 'Amount (৳)' => '-'.($this->reportData['sales_returns_refunded'] ?? '0.00')]);
        $rows->push(['Line Item' => '   Net Sales Revenue', 'Amount (৳)' => $this->reportData['net_sales_revenue'] ?? '0.00']);
        $rows->push(['Line Item' => '   Other Direct / Categorized Income', 'Amount (৳)' => $this->reportData['other_income'] ?? '0.00']);
        $rows->push(['Line Item' => 'TOTAL REVENUE', 'Amount (৳)' => $this->reportData['total_income'] ?? '0.00']);
        $rows->push(['Line Item' => '', 'Amount (৳)' => '']);

        $rows->push(['Line Item' => '2. COST OF GOODS SOLD (COGS) & INVENTORY', 'Amount (৳)' => '']);
        $rows->push(['Line Item' => '   FIFO Cost of Goods Sold', 'Amount (৳)' => $this->reportData['cogs'] ?? '0.00']);
        $rows->push(['Line Item' => '   Less: Return Cost Restored to Inventory', 'Amount (৳)' => '-'.($this->reportData['cost_restored'] ?? '0.00')]);
        $rows->push(['Line Item' => '   Net Cost of Sold Goods', 'Amount (৳)' => $this->reportData['net_cogs'] ?? '0.00']);
        $rows->push(['Line Item' => '   Damaged / Stock Loss Adjustments', 'Amount (৳)' => $this->reportData['stock_losses'] ?? '0.00']);
        $rows->push(['Line Item' => 'TOTAL COST OF SALES', 'Amount (৳)' => $this->reportData['total_cost_of_sales'] ?? '0.00']);
        $rows->push(['Line Item' => '', 'Amount (৳)' => '']);

        $rows->push(['Line Item' => 'GROSS PROFIT', 'Amount (৳)' => $this->reportData['gross_profit'] ?? '0.00']);
        $rows->push(['Line Item' => '', 'Amount (৳)' => '']);

        $rows->push(['Line Item' => '3. OPERATING EXPENSES', 'Amount (৳)' => '']);
        foreach ($this->reportData['expenses_by_category'] ?? [] as $exp) {
            $rows->push(['Line Item' => '   '.$exp['category_name'], 'Amount (৳)' => $exp['amount']]);
        }
        $rows->push(['Line Item' => 'TOTAL OPERATING EXPENSES', 'Amount (৳)' => $this->reportData['total_expenses'] ?? '0.00']);
        $rows->push(['Line Item' => '', 'Amount (৳)' => '']);

        $rows->push(['Line Item' => 'NET PROFIT', 'Amount (৳)' => $this->reportData['net_profit'] ?? '0.00']);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Financial Statement / Account',
            'Amount (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['Line Item'],
            $row['Amount (৳)'],
        ];
    }
}
