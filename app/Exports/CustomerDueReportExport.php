<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomerDueReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
            'Customer Name',
            'Phone',
            'Opening Balance (৳)',
            'Total Invoiced Due (৳)',
            'Collections Paid (৳)',
            'Returns Due Reduction (৳)',
            'Current Receivable (৳)',
            'Advance (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['customer_name'],
            $row['phone'] ?? '',
            $row['opening_balance'],
            $row['total_sales_due'],
            $row['total_paid'],
            $row['total_returns_reduction'],
            $row['current_due'],
            $row['advance'],
        ];
    }
}
