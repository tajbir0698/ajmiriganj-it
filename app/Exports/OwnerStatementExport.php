<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OwnerStatementExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $statementData
     */
    public function __construct(
        protected array $statementData
    ) {}

    public function collection(): Collection
    {
        $rows = collect();

        $rows->push(['Metric' => 'OWNER STATEMENT & EQUITY SUMMARY', 'Amount (৳)' => '']);
        $rows->push(['Metric' => 'Generated At: '.now()->toDateTimeString(), 'Amount (৳)' => '']);
        $rows->push(['Metric' => '', 'Amount (৳)' => '']);

        $rows->push(['Metric' => 'Total Owner Investment (+)', 'Amount (৳)' => $this->statementData['investments'] ?? '0.00']);
        $rows->push(['Metric' => 'Total Owner Drawings (-)', 'Amount (৳)' => '-'.($this->statementData['drawings'] ?? '0.00')]);
        $rows->push(['Metric' => 'Net Business Profit (+)', 'Amount (৳)' => $this->statementData['netProfit'] ?? '0.00']);
        $rows->push(['Metric' => 'Profit Withdrawals (-)', 'Amount (৳)' => '-'.($this->statementData['withdrawals'] ?? '0.00')]);
        $rows->push(['Metric' => 'All-time Retained Profit', 'Amount (৳)' => $this->statementData['retainedProfit'] ?? '0.00']);
        $rows->push(['Metric' => '', 'Amount (৳)' => '']);
        $rows->push(['Metric' => 'TOTAL OWNER CAPITAL / EQUITY', 'Amount (৳)' => $this->statementData['ownerCapital'] ?? '0.00']);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Financial Metric',
            'Amount (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['Metric'],
            $row['Amount (৳)'],
        ];
    }
}
