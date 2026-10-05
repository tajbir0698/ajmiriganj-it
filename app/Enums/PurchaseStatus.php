<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::CANCELLED => 'Cancelled',
        };
    }
}
