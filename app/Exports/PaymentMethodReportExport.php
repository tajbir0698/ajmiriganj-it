<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentMethodReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
                'method_label' => 'TOTAL',
                'pos_sales' => '',
                'customer_collections' => '',
                'total_in' => $this->totals['total_in'] ?? '0.00',
                'vendor_purchases' => '',
                'vendor_payments' => '',
                'expenses' => '',
                'total_out' => $this->totals['total_out'] ?? '0.00',
                'net_cash_flow' => $this->totals['net_cash_flow'] ?? '0.00',
            ]);
        }

        return $exportRows;
    }

    public function headings(): array
    {
        return [
            'Payment Method',
            'POS Sales (৳)',
            'Due Collections (৳)',
            'Total Cash In (৳)',
            'Vendor Purchases (৳)',
            'Vendor Payments (৳)',
            'Expenses Paid (৳)',
            'Total Cash Out (৳)',
            'Net Flow (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['label'] ?? $row['method_label'] ?? '',
            $row['pos_sales'] ?? '0.00',
            $row['collections'] ?? $row['customer_collections'] ?? '0.00',
            $row['total_in'] ?? '0.00',
            $row['vendor_purchases'] ?? '0.00',
            $row['vendor_payments'] ?? '0.00',
            $row['expenses'] ?? '0.00',
            $row['refunds_out'] ?? $row['total_out'] ?? '0.00',
            $row['net_amount'] ?? $row['net_cash_flow'] ?? '0.00',
        ];
    }
}
