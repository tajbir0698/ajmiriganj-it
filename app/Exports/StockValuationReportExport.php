<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockValuationReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        protected Collection $rows
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Product Name',
            'Category',
            'Unit',
            'Stock Qty',
            'FIFO Valuation (৳)',
            'Average Unit Cost (৳)',
            'Last Cost (৳)',
            'Sale Price (৳)',
            'Retail Value (৳)',
            'Potential Profit (৳)',
            'Margin %',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['sku'],
            $row['name'],
            $row['category_name'] ?? '',
            $row['unit'],
            $row['stock_qty'],
            $row['fifo_stock_value'],
            $row['avg_cost'],
            $row['last_cost'],
            $row['sale_price'],
            $row['potential_retail_value'],
            $row['potential_profit'],
            $row['margin_percent'],
        ];
    }
}
