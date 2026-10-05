<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountKind: string
{
    case CASH = 'cash';
    case BANK = 'bank';
    case MOBILE_WALLET = 'mobile_wallet';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash in Hand',
            self::BANK => 'Bank Account',
            self::MOBILE_WALLET => 'Mobile Financial Service (MFS)',
            self::OTHER => 'Other Account',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CASH => 'heroicon-o-banknotes',
            self::BANK => 'heroicon-o-building-library',
            self::MOBILE_WALLET => 'heroicon-o-device-phone-mobile',
            self::OTHER => 'heroicon-o-credit-card',
        };
    }
}
