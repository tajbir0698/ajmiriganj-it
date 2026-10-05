<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VendorDueReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
            'Vendor Name',
            'Phone',
            'Opening Balance (৳)',
            'Total Purchased (৳)',
            'Total Paid (৳)',
            'Current Payable (৳)',
            'Advance (৳)',
            'Net Balance (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['vendor_name'],
            $row['phone'],
            $row['opening_balance'],
            $row['total_purchased'],
            $row['total_paid'],
            $row['current_due'],
            $row['advance'],
            $row['net_balance'],
        ];
    }
}
