<?php

declare(strict_types=1);

namespace App\Enums;

enum AdjustmentType: string
{
    case INCREASE = 'increase';
    case DECREASE = 'decrease';
    case DAMAGE = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::INCREASE => 'Stock Increase (+)',
            self::DECREASE => 'Stock Decrease (-)',
            self::DAMAGE => 'Damaged Goods (-)',
        };
    }

    public function isPositive(): bool
    {
        return $this === self::INCREASE;
    }
}
