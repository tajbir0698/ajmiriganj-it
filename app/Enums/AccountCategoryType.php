<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountCategoryType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case EQUITY = 'equity';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Income',
            self::EXPENSE => 'Expense',
            self::EQUITY => 'Equity',
            self::TRANSFER => 'Transfer',
        };
    }
}
