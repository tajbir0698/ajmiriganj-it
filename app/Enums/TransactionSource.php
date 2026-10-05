<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionSource: string
{
    case SYSTEM = 'system';
    case MANUAL = 'manual';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'System Generated',
            self::MANUAL => 'Manual Entry',
            self::TRANSFER => 'Account Transfer',
        };
    }
}
