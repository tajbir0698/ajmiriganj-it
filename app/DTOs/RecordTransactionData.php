<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;

class RecordTransactionData
{
    public function __construct(
        public int $accountId,
        public TransactionType|string $type,
        public string $amount,
        public ?int $categoryId = null,
        public ?string $date = null,
        public TransactionSource|string $source = TransactionSource::MANUAL,
        public ?string $description = null,
        public ?string $partyType = null,
        public ?int $partyId = null,
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public ?string $transferGroupId = null,
        public ?string $voucherNo = null,
        public ?int $createdBy = null,
    ) {
        $this->date = $this->date ?: now()->toDateString();
    }
}
