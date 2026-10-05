<?php

declare(strict_types=1);

namespace App\Exports;

use App\DTOs\AccountLedgerDTO;
use App\Models\Account;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AccountLedgerExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        protected Account $account,
        protected AccountLedgerDTO $ledger
    ) {}

    public function collection(): Collection
    {
        $rows = collect();

        // Opening Balance Row
        $rows->push([
            'date' => '',
            'voucher' => 'OPENING',
            'category' => '',
            'description' => 'Opening Balance',
            'party' => '',
            'in' => '0.00',
            'out' => '0.00',
            'balance' => $this->ledger->openingBalance,
            'status' => 'OPENING',
        ]);

        foreach ($this->ledger->rows as $r) {
            $rows->push([
                'date' => $r->date,
                'voucher' => $r->voucherNo ?: '-',
                'category' => $r->categoryName ?: '-',
                'description' => $r->description ?: '-',
                'party' => $r->partyName ?: '-',
                'in' => $r->inAmount,
                'out' => $r->outAmount,
                'balance' => $r->runningBalance,
                'status' => $r->isReversed ? 'REVERSED' : ($r->isReversal ? 'REVERSAL' : 'ACTIVE'),
            ]);
        }

        // Totals Row
        $rows->push([
            'date' => 'TOTALS',
            'voucher' => '',
            'category' => '',
            'description' => 'Summary Closing',
            'party' => '',
            'in' => $this->ledger->totalIn,
            'out' => $this->ledger->totalOut,
            'balance' => $this->ledger->closingBalance,
            'status' => 'CLOSING',
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Voucher #',
            'Category',
            'Description',
            'Party',
            'Money In (৳)',
            'Money Out (৳)',
            'Running Balance (৳)',
            'Status',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['date'],
            $row['voucher'],
            $row['category'],
            $row['description'],
            $row['party'],
            $row['in'],
            $row['out'],
            $row['balance'],
            $row['status'],
        ];
    }
}
