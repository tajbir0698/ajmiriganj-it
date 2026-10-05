<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomerAgingReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
                'customer_name' => 'TOTAL',
                'phone' => '',
                'opening_balance' => $this->totals['opening_balance'] ?? '0.00',
                'bucket_0_30' => $this->totals['bucket_0_30'] ?? '0.00',
                'bucket_31_60' => $this->totals['bucket_31_60'] ?? '0.00',
                'bucket_61_90' => $this->totals['bucket_61_90'] ?? '0.00',
                'bucket_90_plus' => $this->totals['bucket_90_plus'] ?? '0.00',
                'total_outstanding' => $this->totals['total_outstanding'] ?? '0.00',
                'advance' => $this->totals['advance'] ?? '0.00',
                'net_due' => $this->totals['net_due'] ?? '0.00',
            ]);
        }

        return $exportRows;
    }

    public function headings(): array
    {
        return [
            'Customer Name',
            'Phone',
            'Opening Balance (৳)',
            '0-30 Days (৳)',
            '31-60 Days (৳)',
            '61-90 Days (৳)',
            '90+ Days (৳)',
            'Total Outstanding (৳)',
            'Advance / Credit (৳)',
            'Net Receivable Due (৳)',
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
            $row['opening_balance'] ?? '0.00',
            $row['bucket_0_30'] ?? '0.00',
            $row['bucket_31_60'] ?? '0.00',
            $row['bucket_61_90'] ?? '0.00',
            $row['bucket_90_plus'] ?? '0.00',
            $row['total_outstanding'] ?? '0.00',
            $row['advance'] ?? '0.00',
            $row['net_due'] ?? '0.00',
        ];
    }
}
