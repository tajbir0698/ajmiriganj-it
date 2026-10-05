<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Account;
use Illuminate\Support\Collection;

class LedgerResult
{
    /**
     * @param  Collection<int, LedgerRow>  $rows
     */
    public function __construct(
        public Account $account,
        public ?string $from,
        public ?string $to,
        public string $openingBalance,
        public Collection $rows,
        public string $totalIn,
        public string $totalOut,
        public string $closingBalance,
    ) {}
}
