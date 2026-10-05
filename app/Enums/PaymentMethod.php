<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BKASH = 'bkash';
    case NAGAD = 'nagad';
    case BANK = 'bank';
    case CHEQUE = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::BKASH => 'bKash',
            self::NAGAD => 'Nagad',
            self::BANK => 'Bank Transfer',
            self::CHEQUE => 'Cheque',
        };
    }
}
