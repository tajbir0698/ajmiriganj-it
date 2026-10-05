<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VendorPaymentsReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
                'reference_no' => 'TOTAL ACTIVE ('.($this->totals['count'] ?? 0).' records)',
                'payment_date' => '',
                'vendor_name' => '',
                'account_name' => '',
                'payment_method_label' => '',
                'amount' => $this->totals['active_amount'] ?? '0.00',
                'status' => 'Reversals: ৳'.($this->totals['reversed_amount'] ?? '0.00'),
            ]);
        }

        return $exportRows;
    }

    public function headings(): array
    {
        return [
            'Reference No',
            'Payment Date',
            'Vendor Name',
            'Paid From Account',
            'Payment Method',
            'Amount (৳)',
            'Status',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['reference_no'],
            $row['payment_date'] ?? '',
            $row['vendor_name'] ?? '',
            $row['account_name'] ?? '',
            $row['payment_method_label'] ?? '',
            $row['amount'] ?? '0.00',
            isset($row['status']) ? $row['status'] : ($row['is_reversed'] ? 'REVERSED' : 'ACTIVE'),
        ];
    }
}
