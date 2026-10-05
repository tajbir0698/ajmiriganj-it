<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Account;
use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct(
        public readonly Account $account,
        public readonly string $availableBalance,
        string $message = ''
    ) {
        $msg = $message ?: sprintf(
            'Insufficient funds in account "%s". Available balance is ৳ %s.',
            $account->name,
            $availableBalance
        );

        parent::__construct($msg);
    }
}
