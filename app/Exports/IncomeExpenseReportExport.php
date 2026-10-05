<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class IncomeExpenseReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(
        protected array $reportData
    ) {}

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->reportData['categories'] ?? [] as $cat) {
            $catName = $cat['category_name'].' ('.strtoupper($cat['category_type']).')';
            foreach ($cat['transactions'] ?? [] as $tx) {
                $rows->push([
                    'category' => $catName,
                    'date' => $tx['date'],
                    'account' => $tx['account_name'],
                    'description' => $tx['description'],
                    'in' => $tx['type'] === 'in' ? $tx['amount'] : '0.00',
                    'out' => $tx['type'] === 'out' ? $tx['amount'] : '0.00',
                    'status' => $tx['is_reversal'] ? 'REVERSAL' : 'ACTIVE',
                ]);
            }
            $rows->push([
                'category' => 'Subtotal: '.$cat['category_name'],
                'date' => '',
                'account' => '',
                'description' => '',
                'in' => $cat['category_type'] === 'income' ? $cat['net_amount'] : '0.00',
                'out' => $cat['category_type'] === 'expense' ? $cat['net_amount'] : '0.00',
                'status' => 'NET: ৳'.$cat['net_amount'],
            ]);
            $rows->push([
                'category' => '',
                'date' => '',
                'account' => '',
                'description' => '',
                'in' => '',
                'out' => '',
                'status' => '',
            ]);
        }

        $totals = $this->reportData['totals'] ?? [];
        $rows->push([
            'category' => 'GRAND TOTALS',
            'date' => '',
            'account' => '',
            'description' => '',
            'in' => $totals['total_income'] ?? '0.00',
            'out' => $totals['total_expense'] ?? '0.00',
            'status' => 'NET FLOW: ৳'.($totals['net_cash_flow'] ?? '0.00'),
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Category',
            'Date',
            'Account',
            'Description',
            'Money In (৳)',
            'Money Out (৳)',
            'Status / Net',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['category'],
            $row['date'],
            $row['account'],
            $row['description'],
            $row['in'],
            $row['out'],
            $row['status'],
        ];
    }
}
