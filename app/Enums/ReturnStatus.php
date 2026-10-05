<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnStatus: string
{
    case NONE = 'none';
    case PARTIAL = 'partial';
    case FULL = 'full';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'No Return',
            self::PARTIAL => 'Partially Returned',
            self::FULL => 'Fully Returned',
        };
    }
}
