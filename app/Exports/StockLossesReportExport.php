<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockLossesReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
                'adjustment_no' => 'TOTAL',
                'date' => '',
                'product_name' => '',
                'sku' => '',
                'type_label' => '',
                'qty' => $this->totals['total_qty_lost'] ?? '',
                'unit_cost' => '',
                'total_loss' => $this->totals['total_stock_losses'] ?? $this->totals['total_loss_amount'] ?? '0.00',
                'reason' => '',
                'user_name' => '',
            ]);
        }

        return $exportRows;
    }

    public function headings(): array
    {
        return [
            'Adjustment #',
            'Date',
            'Product Name',
            'SKU',
            'Loss Type',
            'Qty Lost',
            'Unit Cost (৳)',
            'Total Loss Value (৳)',
            'Reason / Notes',
            'Logged By',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['id'] ?? $row['adjustment_no'] ?? '',
            $row['date'] ?? '',
            $row['product_name'] ?? '',
            $row['sku'] ?? '',
            $row['type'] ?? $row['type_label'] ?? '',
            $row['qty'] ?? '0.000',
            $row['unit_cost'] ?? '0.00',
            $row['total_cost'] ?? $row['total_loss'] ?? '0.00',
            $row['reason'] ?? '',
            $row['created_by'] ?? $row['user_name'] ?? '',
        ];
    }
}
