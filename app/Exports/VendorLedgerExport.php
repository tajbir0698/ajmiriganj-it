<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Vendor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VendorLedgerExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array<int, array<string, mixed>>  $ledger
     */
    public function __construct(
        protected Vendor $vendor,
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
            'Reference',
            'Description',
            'Bill / Due Incurred (৳)',
            'Payment / Return Settled (৳)',
            'Running Payable Balance (৳)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['date'],
            $row['reference'],
            $row['description'],
            $row['bill_amount'],
            $row['paid_amount'],
            $row['balance'],
        ];
    }
}
