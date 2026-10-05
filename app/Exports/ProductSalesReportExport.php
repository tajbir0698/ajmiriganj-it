<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductSalesReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
            'Sold Qty',
            'Returned Qty',
            'Net Qty',
            'Unit',
            'Revenue (৳)',
            'Cost (৳)',
            'Line Profit (৳)',
            'Profit Reversed (৳)',
            'Net Profit After Returns (৳)',
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
            $row['category_name'],
            $row['sold_qty'],
            $row['returned_qty'],
            $row['net_qty'],
            $row['unit'],
            $row['revenue'],
            $row['cost'],
            $row['line_profit'],
            $row['profit_reversed'],
            $row['profit_after_returns'],
            $row['margin_percent'],
        ];
    }
}
