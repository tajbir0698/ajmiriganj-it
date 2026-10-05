<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnSettlement: string
{
    case REDUCE_DUE_CREDIT = 'reduce_due_credit';
    case REFUND_RECEIVED = 'refund_received';

    public function label(): string
    {
        return match ($this) {
            self::REDUCE_DUE_CREDIT => 'Reduce Due / Vendor Credit',
            self::REFUND_RECEIVED => 'Refund Received (Cash/Bank)',
        };
    }
}
