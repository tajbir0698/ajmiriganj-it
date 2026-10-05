<?php

declare(strict_types=1);

namespace App\Enums;

enum BatchSource: string
{
    case PURCHASE = 'purchase';
    case OPENING = 'opening';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::OPENING => 'Opening Stock',
            self::ADJUSTMENT => 'Stock Adjustment',
        };
    }
}
