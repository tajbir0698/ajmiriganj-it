<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Transaction;

class LedgerRow
{
    public function __construct(
        public int $id,
        public string $date,
        public ?string $voucherNo,
        public ?string $categoryName,
        public ?string $description,
        public ?string $partyName,
        public string $inAmount,
        public string $outAmount,
        public string $runningBalance,
        public bool $isReversed,
        public bool $isReversal,
        public ?string $referenceType,
        public ?int $referenceId,
        public ?string $referenceTitle,
        public string $source,
        public ?Transaction $transaction = null,
    ) {}
}
