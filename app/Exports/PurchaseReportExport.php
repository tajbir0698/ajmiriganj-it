<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchaseReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
            'Invoice No',
            'Vendor Invoice',
            'Date',
            'Vendor',
            'Payment Status',
            'Subtotal (৳)',
            'Discount (৳)',
            'Extra Cost (৳)',
            'Total (৳)',
            'Credit Applied (৳)',
            'Paid (৳)',
            'Due (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['invoice_no'],
            $row['vendor_invoice_no'] ?? '',
            $row['date'],
            $row['vendor_name'],
            $row['payment_status'],
            $row['subtotal'],
            $row['discount'],
            $row['extra_cost'],
            $row['total'],
            $row['credit_amount'],
            $row['paid_amount'],
            $row['due_amount'],
        ];
    }
}
