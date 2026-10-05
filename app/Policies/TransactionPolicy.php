<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        // Transactions are created exclusively through AccountService
        return false;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return false; // Transactions are immutable audit records
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return false; // Transactions cannot be deleted
    }
}
