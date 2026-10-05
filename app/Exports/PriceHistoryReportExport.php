<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PriceHistoryReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
            'Date & Time',
            'Product Name',
            'SKU',
            'Old Cost (৳)',
            'New Cost (৳)',
            'Old Sale Price (৳)',
            'New Sale Price (৳)',
            'Change Trigger / Reason',
            'Changed By',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['date'],
            $row['product_name'] ?? '',
            $row['sku'] ?? '',
            $row['old_cost'] ?? '0.00',
            $row['new_cost'] ?? '0.00',
            $row['old_sale_price'] ?? '0.00',
            $row['new_sale_price'] ?? '0.00',
            $row['reason'] ?? '',
            $row['user_name'] ?? '',
        ];
    }
}
