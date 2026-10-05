<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case OPENING = 'opening';
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case SALE_RETURN = 'sale_return';
    case PURCHASE_RETURN = 'purchase_return';
    case ADJUSTMENT = 'adjustment';
    case DAMAGE = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::OPENING => 'Opening Stock',
            self::PURCHASE => 'Purchase',
            self::SALE => 'Sale',
            self::SALE_RETURN => 'Sale Return',
            self::PURCHASE_RETURN => 'Purchase Return',
            self::ADJUSTMENT => 'Stock Adjustment',
            self::DAMAGE => 'Damaged Stock',
        };
    }
}
