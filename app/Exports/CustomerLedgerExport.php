<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomerLedgerExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array<int, array<string, mixed>>  $ledger
     */
    public function __construct(
        protected Customer $customer,
        protected array $ledger
    ) {}

    public function collection(): Collection
    {
        return collect($this->ledger);
    }

    public function headings(): array
    {
        return [
            'Date',
            'Description',
            'Reference',
            'Debit / Invoiced Due (৳)',
            'Credit / Paid & Returns (৳)',
            'Running Balance (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['date'],
            $row['description'],
            $row['reference'],
            $row['bill_amount'],
            $row['paid_amount'],
            $row['balance'],
        ];
    }
}
