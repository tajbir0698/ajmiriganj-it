<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        protected Collection $rows,
        protected bool $isManager = false
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if ($this->isManager) {
            $showCashier = \App\Models\Setting::get('manager_sales_visibility', 'all') === 'all';
            if ($showCashier) {
                return [
                    'Invoice No',
                    'Date',
                    'Cashier',
                    'Customer',
                    'Payment Method',
                    'Invoiced Total (৳)',
                    'Paid (৳)',
                    'Due (৳)',
                ];
            }

            return [
                'Invoice No',
                'Date',
                'Customer',
                'Payment Method',
                'Invoiced Total (৳)',
                'Paid (৳)',
                'Due (৳)',
            ];
        }

        return [
            'Invoice No',
            'Date',
            'Cashier',
            'Customer',
            'Payment Method',
            'Subtotal (৳)',
            'Discount (৳)',
            'Total (৳)',
            'Returns Refund (৳)',
            'COGS (৳)',
            'Net Profit (৳)',
            'Margin %',
            'Paid (৳)',
            'Due (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        if ($this->isManager) {
            $showCashier = \App\Models\Setting::get('manager_sales_visibility', 'all') === 'all';
            if ($showCashier) {
                return [
                    $row['invoice_no'],
                    $row['date'],
                    $row['cashier_name'] ?? 'N/A',
                    $row['customer_name'],
                    $row['payment_method_label'],
                    $row['total'],
                    $row['paid_amount'],
                    $row['due_amount'],
                ];
            }

            return [
                $row['invoice_no'],
                $row['date'],
                $row['customer_name'],
                $row['payment_method_label'],
                $row['total'],
                $row['paid_amount'],
                $row['due_amount'],
            ];
        }

        return [
            $row['invoice_no'],
            $row['date'],
            $row['cashier_name'] ?? 'N/A',
            $row['customer_name'],
            $row['payment_method_label'],
            $row['subtotal'],
            $row['discount'],
            $row['total'],
            $row['returns_refund'] ?? '0.00',
            $row['cogs'] ?? '0.00',
            $row['net_profit'] ?? '0.00',
            $row['margin_percent'] ?? '0%',
            $row['paid_amount'],
            $row['due_amount'],
        ];
    }
}
