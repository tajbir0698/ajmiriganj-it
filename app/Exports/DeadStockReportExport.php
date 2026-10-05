<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DeadStockReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    public function __construct(
        protected Collection $rows,
        protected ?array $totals = null
    ) {}

    public function collection(): Collection
    {
        $exportRows = collect($this->rows);

        if ($this->totals) {
            $exportRows->push([
                'product_name' => 'TOTAL ('.($this->totals['count'] ?? 0).' items)',
                'name' => 'TOTAL ('.($this->totals['count'] ?? 0).' items)',
                'sku' => '',
                'category_name' => '',
                'stock_qty' => $this->totals['total_qty'] ?? '0.000',
                'unit' => '',
                'unit_cost' => '',
                'fifo_stock_value' => $this->totals['total_fifo_value'] ?? $this->totals['total_locked_capital'] ?? '0.00',
                'locked_capital' => $this->totals['total_fifo_value'] ?? $this->totals['total_locked_capital'] ?? '0.00',
                'days_inactive' => '',
                'last_activity_date' => '',
            ]);
        }

        return $exportRows;
    }

    public function headings(): array
    {
        return [
            'Product Name',
            'SKU',
            'Category',
            'Stock Qty',
            'Unit',
            'Unit Cost (৳)',
            'Locked Capital (৳)',
            'Days Inactive',
            'Last Sale / Stock Date',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['name'] ?? $row['product_name'] ?? '',
            $row['sku'] ?? '',
            $row['category_name'] ?? '',
            $row['stock_qty'] ?? '0.000',
            $row['unit'] ?? '',
            $row['unit_cost'] ?? '0.00',
            $row['fifo_stock_value'] ?? $row['locked_capital'] ?? '0.00',
            $row['days_since_sale'] ?? $row['days_inactive'] ?? '',
            $row['last_sale_date'] ?? $row['last_activity_date'] ?? 'Never Sold',
        ];
    }
}
